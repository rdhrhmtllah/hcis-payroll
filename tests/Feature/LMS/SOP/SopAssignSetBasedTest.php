<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPenjadwalanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * assignKeHeader() harus berbentuk HIMPUNAN, bukan perulangan per karyawan.
 *
 * Bentuk lama mengirim satu insertGetId per karyawan ditambah satu insert
 * progress per karyawan — ±200 pernyataan untuk 100 orang, seluruhnya di dalam
 * SATU transaksi yang memegang kunci pada N_SOP_Penjadwalan_Detail dan
 * N_SOP_Progress selama itu. Basis data ini berjalan tanpa RCSI, jadi pembaca
 * ikut mengantre.
 *
 * PERINGATAN UNTUK PEMBACA BERIKUTNYA. Penjaga jumlah-kueri di berkas ini
 * TIDAK BOLEH berdiri sendiri: operasi yang tak menyentuh apa pun menghasilkan
 * jumlah pernyataan yang SEMPURNA. Tanpa penegasan bahwa baris detail dan
 * progress benar-benar terbentuk, penjaga itu justru menghadiahi kode yang
 * rusak. Karena itu setiap penjaga di sini selalu berpasangan:
 * (1) jumlah pernyataan, DAN (2) jumlah baris yang nyata-nyata terbentuk.
 *
 * Data dev terlalu kecil untuk membuktikan apa pun lewat wall-clock, jadi yang
 * diukur adalah BENTUKNYA — jumlah pernyataan yang tidak tumbuh bersama jumlah
 * karyawan.
 */
class SopAssignSetBasedTest extends TestCase
{
    use DatabaseTransactions;

    /** Berapa karyawan yang dipakai penjaga. Bentuk lama = ±2× angka ini. */
    private const JUMLAH_TARGET = 50;

    /**
     * Paket dengan 1–5 item wajib aktif.
     *
     * Batas atas ada karena rollup memang menerbitkan dua kueri PER ITEM
     * ber-versi (exists + update) — itu tumbuh mengikuti ITEM, bukan karyawan,
     * dan memang tidak dipersoalkan tugas ini. Membatasi fikstur menjaga
     * plafon 20 tetap mengukur sumbu karyawan, bukan sumbu item.
     *
     * @return object{Id_SOP_Paket:int, jml:int}|null
     */
    private function paketBerItemWajib(): ?object
    {
        return DB::table('N_SOP_Paket_Item')
            ->where('Flag_Wajib', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Id_SOP_Paket')
            ->havingRaw('COUNT(*) BETWEEN 1 AND 5')
            ->selectRaw('Id_SOP_Paket, COUNT(*) as jml')
            ->orderBy('Id_SOP_Paket')
            ->first();
    }

    /** Karyawan aktif yang BELUM punya penugasan aktif atas paket tsb. */
    private function karyawanBelumTerassign(int $idPaket, int $butuh): array
    {
        return DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNotExists(function ($q) use ($idPaket) {
                $q->select(DB::raw('1'))
                    ->from('N_SOP_Penjadwalan_Detail as d')
                    ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                    ->whereColumn('d.Kode_Karyawan', 'k.Kode_Karyawan')
                    ->where('pj.Id_SOP_Paket', $idPaket)
                    ->where('d.Flag_Aktif', 'Y')
                    ->where('d.Flag_Cabut_Akses', 'T');
            })
            ->orderBy('k.Kode_Karyawan')
            ->limit($butuh)
            ->pluck('k.Kode_Karyawan')
            ->all();
    }

    /**
     * @return array{svc:SopPenjadwalanService, headerId:int, idPaket:int, kode:array, jmlItem:int}
     */
    private function siapkan(): array
    {
        $paket = $this->paketBerItemWajib();
        if (! $paket) {
            $this->markTestSkipped(
                'Tak ada paket dgn 1–5 item wajib aktif (N_SOP_Paket_Item: Flag_Wajib=Y, Flag_Aktif=Y) di data dev.'
            );
        }

        $idPaket = (int) $paket->Id_SOP_Paket;
        $kode = $this->karyawanBelumTerassign($idPaket, self::JUMLAH_TARGET);

        if (count($kode) < self::JUMLAH_TARGET) {
            $this->markTestSkipped(
                'Butuh '.self::JUMLAH_TARGET.' karyawan aktif yang belum ter-assign paket '
                .$idPaket.'; data dev hanya menyediakan '.count($kode).'.'
            );
        }

        $svc = app(SopPenjadwalanService::class);

        $headerId = $svc->buatHeader($idPaket, [
            'nama_batch' => 'UJI Set-Based '.now()->format('His'),
            'sumber' => 'MANUAL',
            'tipe_tenggat' => 'SAMPAI_DICABUT',
        ], null);

        return [
            'svc' => $svc,
            'headerId' => (int) $headerId,
            'idPaket' => $idPaket,
            'kode' => $kode,
            'jmlItem' => (int) $paket->jml,
        ];
    }

    private function jumlahDetail(int $headerId): int
    {
        return DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan', $headerId)
            ->count();
    }

    private function jumlahProgress(int $headerId): int
    {
        return DB::table('N_SOP_Progress as p')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'p.Id_SOP_Penjadwalan_Detail')
            ->where('d.Id_SOP_Penjadwalan', $headerId)
            ->count();
    }

    public function test_assign_tidak_menerbitkan_satu_pernyataan_per_karyawan(): void
    {
        ['svc' => $svc, 'headerId' => $headerId, 'idPaket' => $idPaket,
            'kode' => $kode, 'jmlItem' => $jmlItem] = $this->siapkan();

        $pernyataan = 0;
        DB::listen(function () use (&$pernyataan) {
            $pernyataan++;
        });

        // assignKeHeader() TIDAK membuka transaksi sendiri; DatabaseTransactions
        // sudah memegang satu, jadi memanggilnya langsung sekaligus menghindari
        // pernyataan savepoint yang akan mengotori hitungan.
        $hasil = $svc->assignKeHeader($headerId, $idPaket, $kode, null);

        $terhitung = $pernyataan; // dibekukan: penegasan di bawah ikut terhitung.

        // ── PENJAGA KERJA-NYATA ────────────────────────────────────────────
        // Ini yang membuat penjaga jumlah kueri di bawah berarti. Operasi yang
        // tak menyentuh apa pun menghasilkan angka yang SEMPURNA.
        $this->assertSame(self::JUMLAH_TARGET, $hasil['assigned'], 'Tak semua karyawan ter-assign.');
        $this->assertSame(0, $hasil['skipped'], 'Ada karyawan yang dilewati padahal fikstur memilih yang belum ter-assign.');
        $this->assertCount(self::JUMLAH_TARGET, $hasil['targets'], 'Kunci targets tak lagi memuat seluruh kode; ProsesBulkAssignSop memakainya untuk notifikasi.');

        $this->assertSame(
            self::JUMLAH_TARGET,
            $this->jumlahDetail($headerId),
            'Baris N_SOP_Penjadwalan_Detail tidak terbentuk untuk seluruh target.'
        );

        $diharapkan = self::JUMLAH_TARGET * $jmlItem;
        $this->assertSame(
            $diharapkan,
            $this->jumlahProgress($headerId),
            'Baris progress tidak terbentuk untuk '.self::JUMLAH_TARGET." karyawan x {$jmlItem} item wajib. "
            .'INSERT ... SELECT gagal menjoin balik lewat (Id_SOP_Penjadwalan, Kode_Karyawan).'
        );

        // ── PENJAGA BENTUK ─────────────────────────────────────────────────
        $this->assertLessThan(
            20,
            $terhitung,
            'Assign '.self::JUMLAH_TARGET." karyawan menerbitkan {$terhitung} pernyataan. Bentuk per-karyawan "
            .'(insertGetId + insert progress) belum diganti bentuk himpunan.'
        );
    }

    /**
     * Idempotensi adalah SYARAT retry Cloud Tasks: ProsesBulkAssignSop
     * menjalankan ulang SELURUH chunk saat job di-retry. Penugasan ulang tak
     * boleh melahirkan detail kembar maupun progress kembar.
     */
    public function test_assign_ulang_tidak_menggandakan_detail_maupun_progress(): void
    {
        ['svc' => $svc, 'headerId' => $headerId, 'idPaket' => $idPaket,
            'kode' => $kode, 'jmlItem' => $jmlItem] = $this->siapkan();

        $pertama = $svc->assignKeHeader($headerId, $idPaket, $kode, null);
        $this->assertSame(self::JUMLAH_TARGET, $pertama['assigned'], 'Assign pertama tidak menyentuh apa pun; uji berikutnya jadi tak berarti.');

        $detailSetelah1 = $this->jumlahDetail($headerId);
        $progressSetelah1 = $this->jumlahProgress($headerId);
        $this->assertSame(self::JUMLAH_TARGET * $jmlItem, $progressSetelah1);

        $kedua = $svc->assignKeHeader($headerId, $idPaket, $kode, null);

        $this->assertSame(0, $kedua['assigned'], 'Assign ulang menugaskan lagi orang yang sudah aktif.');
        $this->assertSame(self::JUMLAH_TARGET, $kedua['skipped'], 'Diff anti-duplikat tidak melewati orang yang sudah ter-assign.');

        $this->assertSame($detailSetelah1, $this->jumlahDetail($headerId), 'Detail kembar terbentuk pada assign ulang.');
        $this->assertSame($progressSetelah1, $this->jumlahProgress($headerId), 'Progress kembar terbentuk pada assign ulang.');
    }

    /**
     * Sumbu yang diukur adalah KARYAWAN. Bila jumlah pernyataan sama untuk 5
     * orang dan 50 orang, bentuknya memang himpunan — bukan sekadar konstanta
     * yang kebetulan lolos plafon.
     */
    public function test_jumlah_pernyataan_tidak_tumbuh_bersama_jumlah_karyawan(): void
    {
        ['svc' => $svc, 'headerId' => $headerId, 'idPaket' => $idPaket, 'kode' => $kode] = $this->siapkan();

        // SATU pendengar saja. DB::listen tidak bisa dilepas, jadi mendaftarkan
        // pendengar kedua akan membuat pengukuran berikutnya terhitung ganda —
        // dan penjaganya lalu "gagal" karena cara mengukurnya, bukan karena
        // kodenya. Penghitung di-nol-kan di antara dua pengukuran.
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $hitung = function (array $subset) use ($svc, $headerId, $idPaket, &$n): array {
            $n = 0;
            $hasil = $svc->assignKeHeader($headerId, $idPaket, $subset, null);

            return ['kueri' => $n, 'assigned' => $hasil['assigned']];
        };

        $kecil = $hitung(array_slice($kode, 0, 5));
        $besar = $hitung(array_slice($kode, 5, 45));

        // Penjaga kerja-nyata untuk KEDUA pengukuran.
        $this->assertSame(5, $kecil['assigned'], 'Pengukuran kecil tak menyentuh apa pun.');
        $this->assertSame(45, $besar['assigned'], 'Pengukuran besar tak menyentuh apa pun.');

        $this->assertSame(
            $kecil['kueri'],
            $besar['kueri'],
            "5 karyawan menerbitkan {$kecil['kueri']} pernyataan, 45 karyawan menerbitkan {$besar['kueri']}. "
            .'Jumlah pernyataan masih tumbuh bersama jumlah karyawan.'
        );
    }
}
