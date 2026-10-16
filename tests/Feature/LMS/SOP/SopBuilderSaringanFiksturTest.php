<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPaketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saringan Builder atas populasi yang disemai sendiri, lalu dibatalkan.
 *
 * KENAPA TIDAK CUKUP `SopBuilderSaringanPaketTest`. Yang di sana membaca data
 * apa adanya, dan basis data pengembangan kebetulan berisi tujuh paket yang
 * SELURUHNYA berstatus PUBLISHED dan SELURUHNYA punya estimasi. Tiga penjaga
 * paling penting di sana — status berdaftar, pembagian ember estimasi, dan
 * urutan yang tak boleh dibuka paket tanpa estimasi — karena itu tak pernah
 * berjalan; mereka terlewat, bukan hijau.
 *
 * Penjaga yang tak pernah dijalankan tak menjaga apa pun. Berkas ini menyemai
 * keragaman yang dibutuhkan, membuktikan hal yang sama, lalu mengembalikan
 * basis data ke keadaan semula lewat transaksi yang selalu dibatalkan.
 *
 * Seluruh perbandingannya RELATIF terhadap paket yang disemai sendiri (ditandai
 * lewat nama berimbuhan unik), bukan terhadap angka mutlak — data nyata di
 * sekelilingnya boleh berubah kapan saja tanpa membuat berkas ini merah.
 */
class SopBuilderSaringanFiksturTest extends TestCase
{
    use DatabaseTransactions;

    private string $tanda;

    private array $disemai = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tanda = 'UJI-SARING-'.bin2hex(random_bytes(4));
    }

    private function svc(): SopPaketService
    {
        return app(SopPaketService::class);
    }

    /**
     * Semai satu paket dan pulangkan idnya.
     *
     * Lewat `create()` yang sesungguhnya, bukan INSERT tangan: kolom wajib dan
     * nilai bawaan jadi urusan satu tempat, dan fikstur ini ikut rusak kalau
     * bentuk paket berubah — yang memang seharusnya.
     */
    private function semai(string $nama, array $over = []): int
    {
        $id = $this->svc()->create([
            'Nama_Paket' => "{$this->tanda} {$nama}",
            'Estimasi_Waktu_Baca_Menit' => $over['estimasi'] ?? null,
            'Mode_Distribusi' => $over['distribusi'] ?? 'TERBATAS',
        ], 1);

        $ubah = [];
        if (isset($over['status'])) {
            $ubah['Status'] = $over['status'];
        }
        if (array_key_exists('terbit', $over)) {
            $ubah['Tanggal_Publish'] = $over['terbit'];
        }
        if ($ubah) {
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $id)->update($ubah);
        }

        $this->disemai[] = (int) $id;

        return (int) $id;
    }

    /** Filter lengkap; hanya yang disebut yang menyaring. `search` mengunci ke fikstur ini. */
    private function f(array $over = []): array
    {
        return $over + [
            'search' => $this->tanda,
            'status' => [],
            'prioritas' => [],
            'distribusi' => [],
            'absensi' => null,
            'estimasi' => null,
            'terbit' => null,
            'tgl_dari' => null,
            'tgl_sampai' => null,
            'sort' => 'newest',
        ];
    }

    private function total(array $filters): int
    {
        return $this->svc()->paginate($filters, 1, 50)[1];
    }

    /** Populasi campuran yang dipakai sebagian besar pengujian di bawah. */
    private function semaiCampuran(): void
    {
        $this->semai('draft-pendek', ['status' => 'DRAFT', 'estimasi' => 5]);
        $this->semai('draft-sedang', ['status' => 'DRAFT', 'estimasi' => 25]);
        $this->semai('terbit-panjang', ['status' => 'PUBLISHED', 'estimasi' => 90, 'terbit' => now()->subDays(2)]);
        $this->semai('terbit-tanpa', ['status' => 'PUBLISHED', 'estimasi' => null, 'terbit' => now()->subDays(400)]);
        $this->semai('arsip-nol', ['status' => 'ARCHIVED', 'estimasi' => 0]);
    }

    public function test_fikstur_benar_benar_tersemai(): void
    {
        $this->semaiCampuran();

        /* Penjaga kerja-nyata. Tanpa ini, seluruh perbandingan di bawah bisa
           saja membandingkan nol dengan nol dan tetap hijau sempurna. */
        $this->assertCount(5, $this->disemai);
        $this->assertSame(5, $this->total($this->f()));
    }

    public function test_status_berdaftar_menggabung_bukan_mengambil_yang_pertama(): void
    {
        $this->semaiCampuran();

        $draft = $this->total($this->f(['status' => ['DRAFT']]));
        $terbit = $this->total($this->f(['status' => ['PUBLISHED']]));
        $gabung = $this->total($this->f(['status' => ['DRAFT', 'PUBLISHED']]));

        $this->assertSame(2, $draft);
        $this->assertSame(2, $terbit);

        // `where` yang menggantikan `whereIn` berhenti di 2, bukan 4.
        $this->assertSame(4, $gabung);
    }

    public function test_status_counts_menghitung_seluruh_status_meski_satu_sedang_disaring(): void
    {
        $this->semaiCampuran();

        $hitung = $this->svc()->statusCounts($this->f(['status' => ['DRAFT']]));

        $this->assertSame(2, $hitung['DRAFT']);
        $this->assertSame(2, $hitung['PUBLISHED']);
        $this->assertSame(1, $hitung['ARCHIVED']);
        $this->assertSame(5, $hitung['ALL']);
    }

    public function test_ember_estimasi_membagi_habis_tanpa_tumpang_tindih(): void
    {
        $this->semaiCampuran();

        $per = [];
        foreach (['pendek', 'sedang', 'panjang', 'tanpa'] as $ember) {
            $per[$ember] = $this->total($this->f(['estimasi' => $ember]));
        }

        $this->assertSame(['pendek' => 1, 'sedang' => 1, 'panjang' => 1, 'tanpa' => 2], $per);
        $this->assertSame(5, array_sum($per));
    }

    /** Batas ember adalah 10 dan 30 menit — tepat di angkanya, bukan di sekitarnya. */
    public function test_batas_ember_estimasi_inklusif_di_kedua_ujungnya(): void
    {
        $this->semai('tepat-10', ['estimasi' => 10]);
        $this->semai('tepat-11', ['estimasi' => 11]);
        $this->semai('tepat-30', ['estimasi' => 30]);
        $this->semai('tepat-31', ['estimasi' => 31]);

        $this->assertSame(1, $this->total($this->f(['estimasi' => 'pendek'])));
        $this->assertSame(2, $this->total($this->f(['estimasi' => 'sedang'])));
        $this->assertSame(1, $this->total($this->f(['estimasi' => 'panjang'])));
    }

    public function test_ember_terbit_memisahkan_yang_baru_dari_yang_lama_dan_yang_belum(): void
    {
        $this->semaiCampuran();

        $this->assertSame(1, $this->total($this->f(['terbit' => '7hari'])));
        $this->assertSame(1, $this->total($this->f(['terbit' => '30hari'])));
        $this->assertSame(1, $this->total($this->f(['terbit' => 'tahun'])));
        // Tiga paket lain tak pernah terbit: dua DRAFT dan satu ARCHIVED.
        $this->assertSame(3, $this->total($this->f(['terbit' => 'belum'])));
    }

    public function test_ember_terbit_dan_rentang_tanggal_saling_mempersempit(): void
    {
        $this->semai('lama', ['status' => 'PUBLISHED', 'terbit' => now()->subDays(20)]);
        $this->semai('baru', ['status' => 'PUBLISHED', 'terbit' => now()->subDays(2)]);

        $this->assertSame(2, $this->total($this->f(['terbit' => '30hari'])));

        // Ember 30 hari DAN rentang yang hanya memuat yang paling baru.
        $this->assertSame(1, $this->total($this->f([
            'terbit' => '30hari',
            'tgl_dari' => now()->subDays(5)->toDateString(),
        ])));
    }

    public function test_saringan_distribusi_berdaftar(): void
    {
        $this->semai('publik-a', ['distribusi' => 'PUBLIK']);
        $this->semai('publik-b', ['distribusi' => 'PUBLIK']);
        $this->semai('terbatas', ['distribusi' => 'TERBATAS']);

        $this->assertSame(2, $this->total($this->f(['distribusi' => ['PUBLIK']])));
        $this->assertSame(3, $this->total($this->f(['distribusi' => ['PUBLIK', 'TERBATAS']])));
    }

    public function test_grup_counts_menyebar_sesuai_sumbu_yang_diminta(): void
    {
        $this->semaiCampuran();

        $perStatus = collect($this->svc()->grupCounts($this->f(), 'status'))
            ->pluck('total', 'nilai')->all();

        $this->assertSame(2, $perStatus['DRAFT'] ?? 0);
        $this->assertSame(2, $perStatus['PUBLISHED'] ?? 0);
        $this->assertSame(1, $perStatus['ARCHIVED'] ?? 0);
    }

    public function test_grup_counts_sumbu_status_tak_menyusut_saat_status_disaring(): void
    {
        $this->semaiCampuran();

        $polos = $this->svc()->grupCounts($this->f(), 'status');
        $disaring = $this->svc()->grupCounts($this->f(['status' => ['DRAFT']]), 'status');

        /* Kalau rail ikut disaring oleh pilihannya sendiri, daftar ini menyusut
           jadi satu baris dan tak ada lagi jalan kembali ke status lain. */
        $this->assertCount(3, $polos);
        $this->assertEquals($polos, $disaring);
    }

    public function test_grup_counts_sumbu_lain_tetap_menyempit_saat_status_disaring(): void
    {
        $this->semaiCampuran();

        $grup = $this->svc()->grupCounts($this->f(['status' => ['DRAFT']]), 'prioritas');

        $this->assertSame(2, array_sum(array_column($grup, 'total')));
    }

    /**
     * Paket tanpa estimasi bukan paket tercepat.
     *
     * SQL Server menaruh NULL paling awal pada ASC. Tanpa penjaga di
     * `paginate()`, urutan "estimasi terpendek" membuka daftar dengan paket
     * yang justru belum punya perkiraan sama sekali — dan populasi nyata di
     * basis data pengembangan tak punya satu pun paket seperti itu, jadi cacat
     * ini tak akan pernah tampak tanpa fikstur ini.
     */
    public function test_urutan_estimasi_menaruh_yang_tanpa_estimasi_di_bawah_pada_kedua_arah(): void
    {
        $this->semaiCampuran();

        [$naik] = $this->svc()->paginate($this->f(['sort' => 'estimasi_pendek']), 1, 50);
        [$turun] = $this->svc()->paginate($this->f(['sort' => 'estimasi_panjang']), 1, 50);

        $this->assertSame(5, $naik->count());
        $this->assertSame(5, $this->svc()->paginate($this->f(['sort' => 'estimasi_panjang']), 1, 50)[1]);

        $menit = fn ($rows) => $rows->pluck('Estimasi_Waktu_Baca_Menit')
            ->map(fn ($m) => $m === null ? null : (int) $m)->all();

        $naikMenit = $menit($naik);
        $turunMenit = $menit($turun);

        // Yang berestimasi terurut penuh, di kedua arah.
        $this->assertSame([5, 25, 90], array_slice($naikMenit, 0, 3));
        $this->assertSame([90, 25, 5], array_slice($turunMenit, 0, 3));

        /* Dua yang tanpa estimasi selalu di BAWAH — itu yang dijaga. Urutan di
           DALAM kelompok itu sendiri (NULL vs nol) memang ikut arah dan tak
           berarti apa-apa: keduanya sama-sama "belum punya perkiraan". */
        foreach ([$naikMenit, $turunMenit] as $urut) {
            $ekor = array_slice($urut, 3);

            $this->assertCount(2, $ekor);
            $this->assertContains(null, $ekor);
            $this->assertContains(0, $ekor);
        }
    }

    public function test_urutan_nama_tetap_bekerja_di_samping_urutan_estimasi(): void
    {
        $this->semai('bbb', ['estimasi' => 1]);
        $this->semai('aaa', ['estimasi' => 99]);

        [$rows] = $this->svc()->paginate($this->f(['sort' => 'nama']), 1, 50);

        $this->assertStringEndsWith('aaa', $rows->first()->Nama_Paket);
    }

    public function test_saringan_absensi_hanya_memuat_yang_menuntut_kehadiran(): void
    {
        $idAbsen = $this->semai('berabsensi');
        $this->semai('biasa');
        DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idAbsen)->update(['Flag_Absensi' => 'Y']);

        $this->assertSame(2, $this->total($this->f()));
        $this->assertSame(1, $this->total($this->f(['absensi' => 'Y'])));
    }
}
