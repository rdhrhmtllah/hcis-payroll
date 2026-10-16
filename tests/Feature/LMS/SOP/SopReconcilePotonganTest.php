<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopReconciliationService;
use App\Services\LMS\SOP\Support\SopSetting;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Rekonsiliasi memecah pekerjaannya — dan pemecahannya harus BENAR-BENAR terjadi.
 *
 * Basis data ini berjalan tanpa RCSI, jadi selama sebuah transaksi menulis ke
 * N_SOP_Progress, setiap pembaca tabel itu menunggu. Request web dibatasi
 * `SET LOCK_TIMEOUT 10000`, sehingga transaksi tulis yang melewati sepuluh
 * detik tidak memperlambat halaman karyawan — ia MENGGAGALKANNYA.
 *
 * Karena itu reconcile menulis per potongan karyawan, bukan sekali untuk semua.
 * Yang sempat menggugurkan seluruh penjagaan itu: ukuran potongannya 100,
 * sementara paket terbesar berisi 57 orang. Kodenya berbentuk memotong,
 * perilakunya tetap satu transaksi — dan tak ada satu pun tanda bahwa
 * penjagaannya sedang tidak bekerja. Terlihat 12 Agt 2026 sebagai
 * "Lock request time out period exceeded" di beranda karyawan sesudah rollback.
 *
 * Uji ini menghitung TRANSAKSI yang benar-benar dibuka, bukan memeriksa
 * konstantanya: konstanta yang benar dengan pemakaian yang salah persis
 * menghasilkan cacat yang sama.
 */
class SopReconcilePotonganTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Paksa ukuran potongan tanpa menyentuh tabel setting.
     *
     * flush() DULU, baru put(), dan urutannya mengikat. Sejak SopSetting
     * memoisasi nilainya per-proses, menyuntik baris cache saja tak berefek
     * bila memo sudah terisi lebih awal di proses yang sama — dan itu pasti
     * terjadi pada suite penuh walau mungkin tidak saat berkas ini dijalankan
     * sendirian. Akibatnya chunkDetail() memulangkan bawaan, bukan angka yang
     * disuntik, dan uji ini menguji ukuran potongan yang salah.
     *
     * flush() juga menghapus baris cache-nya, jadi memanggilnya SESUDAH put()
     * akan membuang suntikan itu sendiri.
     */
    private function pakaiPotongan(int $n): void
    {
        SopSetting::flush();
        Cache::store('database')->put('sop_setting_all', ['sop_reconcile_chunk' => (string) $n], 600);
    }

    private function penugasanPerPaket()
    {
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw('pj.Id_SOP_Paket as paket, COUNT(*) as n')
            ->orderByDesc('n')
            ->get();
    }

    /**
     * Batas yang lebih besar dari populasinya bukan batas — ia hanya berbentuk
     * batas. Ini bentuk cacat yang paling sulit terlihat karena kodenya benar.
     */
    public function test_ukuran_potongan_lebih_kecil_dari_paket_terbesar(): void
    {
        // flush(), bukan forget(): memo per-proses SopSetting juga harus ikut kosong.
        SopSetting::flush();

        $terbesar = $this->penugasanPerPaket()->first();
        if (! $terbesar) {
            $this->markTestSkipped('Belum ada penugasan aktif.');
        }

        $svc = new SopReconciliationService;
        $m = new \ReflectionMethod($svc, 'chunkDetail');
        $m->setAccessible(true);

        $this->assertLessThan(
            (int) $terbesar->n,
            $m->invoke($svc),
            "Ukuran potongan melampaui paket terbesar ({$terbesar->n} penugasan) — "
            .'pemotongannya tak pernah terjadi dan seluruh isinya masuk satu transaksi.',
        );
    }

    /**
     * Dan pembuktiannya lewat transaksi yang benar-benar dibuka.
     *
     * Memeriksa konstantanya saja tak membuktikan apa pun: nilai yang benar
     * dengan pemakaian yang salah — misalnya chunk() yang tak pernah dipanggil
     * lagi — menghasilkan cacat yang sama persis.
     */
    public function test_paket_yang_melebihi_potongan_ditulis_dalam_beberapa_transaksi(): void
    {
        // Paket termurah yang tetap membuktikan pemotongan: potongan dipaksa
        // kecil supaya ujinya tak ikut menanggung ongkos paket terbesar.
        $paket = $this->penugasanPerPaket()->last(fn ($r) => (int) $r->n >= 6);
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket dengan penugasan cukup.');
        }

        $this->pakaiPotongan(2);
        $harapMinimal = (int) ceil($paket->n / 2);

        $jumlah = 0;
        Event::listen(TransactionBeginning::class, function () use (&$jumlah) {
            $jumlah++;
        });

        app(SopReconciliationService::class)->reconcilePaket(
            (int) $paket->paket, 'MANUAL', null, tanpaNotifikasi: true);

        $this->assertGreaterThanOrEqual($harapMinimal, $jumlah,
            "Paket berisi {$paket->n} penugasan hanya membuka {$jumlah} transaksi; "
            ."dengan potongan 2 seharusnya minimal {$harapMinimal}.");
    }

    /**
     * Sifat yang membuat pemecahan itu aman sejak awal: reconcile idempoten.
     *
     * Kalau tidak, potongan yang sudah commit akan dikerjakan lagi pada
     * pemanggilan berikutnya — dan memecahnya justru melipatgandakan kerusakan
     * alih-alih memperpendek lock.
     */
    public function test_dijalankan_dua_kali_tak_menyemai_apa_pun_lagi(): void
    {
        $paket = $this->penugasanPerPaket()->last(fn ($r) => (int) $r->n >= 6);
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket dengan penugasan cukup.');
        }

        $this->pakaiPotongan(2);
        $svc = app(SopReconciliationService::class);

        $svc->reconcilePaket((int) $paket->paket, 'MANUAL', null, tanpaNotifikasi: true);
        $kedua = $svc->reconcilePaket((int) $paket->paket, 'MANUAL', null, tanpaNotifikasi: true);

        $this->assertSame(0, (int) $kedua['seeded'],
            'Jalan kedua menyemai ulang — pemotongan jadi tak aman diulang.');
        $this->assertSame(0, (int) ($kedua['gagal'] ?? 0));
    }
}
