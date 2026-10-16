<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPaketService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saringan berdaftar & sebaran rail pada daftar paket SOP Builder.
 *
 * TIGA HAL YANG DIJAGA, dan ketiganya gagal secara diam-diam kalau rusak:
 *
 *   1. Saringan berdaftar benar-benar MELEBAR. `whereIn` yang tak sengaja
 *      ditulis `where` menghasilkan daftar yang tetap masuk akal — berisi
 *      paket, terurut, berpaginasi — hanya saja kehilangan seluruh nilai
 *      kedua dan seterusnya. Tak ada pesan, tak ada nol baris.
 *
 *   2. Ember MEMBAGI HABIS populasinya. Ember yang tumpang tindih menghitung
 *      satu paket dua kali; ember yang berlubang membuat paket lenyap dari
 *      setiap pilihan. Dua-duanya hanya terlihat kalau seluruh embernya
 *      dijumlahkan dan dibandingkan dengan totalnya.
 *
 *   3. Rail MENGABAIKAN saringan sumbunya sendiri. Kalau tidak, rail menyusut
 *      jadi satu baris begitu sebuah kelompok dipilih, dan jalan kembalinya
 *      hilang.
 *
 * Read-only: seluruh angka diturunkan dari isi basis data yang ada, dan tiap
 * pengujian menolak berjalan kalau populasinya terlalu tipis untuk membuktikan
 * apa pun. Operasi yang tak menyentuh apa pun menghasilkan angka sempurna.
 */
class SopBuilderSaringanPaketTest extends TestCase
{
    private function svc(): SopPaketService
    {
        return app(SopPaketService::class);
    }

    /** Filter lengkap dengan seluruh kunci terisi bawaan; hanya yang disebut yang menyaring. */
    private function f(array $over = []): array
    {
        return $over + [
            'search' => null,
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

    /** Jumlah baris yang cocok, tanpa peduli isi halamannya. */
    private function total(array $filters): int
    {
        return $this->svc()->paginate($filters, 1, 5)[1];
    }

    /** Seluruh paket aktif, sebagai pembanding paling luar. */
    private function totalSemua(): int
    {
        return (int) DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->count();
    }

    /** Dua tingkat prioritas yang sama-sama berisi paket; null bila tak ada dua. */
    private function duaPrioritas(): ?array
    {
        $rows = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Prioritas')
            ->groupBy('Prioritas')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(2)
            ->pluck('Prioritas');

        // Prioritas berkode TEKS ('KRITIS', 'NORMAL'), bukan id. Cast ke int
        // yang diwarisi dari bentuk lama (yang memang menyaring id kategori)
        // mengubah setiap kode jadi 0, dan SQL Server menggagalkan seluruh
        // query dengan galat konversi — bukan mengembalikan nol baris.
        return $rows->count() === 2 ? $rows->map(fn ($v) => (string) $v)->all() : null;
    }

    public function test_saringan_berdaftar_melebar_bukan_mengambil_yang_pertama(): void
    {
        $dua = $this->duaPrioritas();
        if (! $dua) {
            $this->markTestSkipped('Butuh minimal dua tingkat prioritas yang sama-sama berisi paket.');
        }

        [$a, $b] = $dua;
        $satu = $this->total($this->f(['prioritas' => [$a]]));
        $lain = $this->total($this->f(['prioritas' => [$b]]));
        $gabung = $this->total($this->f(['prioritas' => [$a, $b]]));

        $this->assertGreaterThan(0, $satu, 'Tingkat pertama harus berisi paket.');
        $this->assertGreaterThan(0, $lain, 'Tingkat kedua harus berisi paket.');

        /* Satu paket tepat satu prioritas, jadi dua kelompok ini terpisah dan
           gabungannya wajib persis penjumlahannya. `where` yang menggantikan
           `whereIn` akan berhenti di $satu. */
        $this->assertSame($satu + $lain, $gabung);
        $this->assertGreaterThan($satu, $gabung);
    }

    public function test_saringan_status_berdaftar_sepadan_dengan_badge_chip(): void
    {
        $hitung = $this->svc()->statusCounts($this->f());
        if ($hitung['DRAFT'] < 1 || $hitung['PUBLISHED'] < 1) {
            $this->markTestSkipped('Butuh paket berstatus DRAFT dan PUBLISHED sekaligus.');
        }

        $gabung = $this->total($this->f(['status' => ['DRAFT', 'PUBLISHED']]));

        $this->assertSame($hitung['DRAFT'] + $hitung['PUBLISHED'], $gabung);
    }

    /**
     * Badge chip status tak boleh ikut disaring oleh status yang sedang dipilih
     * — kalau ikut, tiga dari empat chip selalu tertulis nol.
     */
    public function test_status_counts_mengabaikan_saringan_status_sendiri(): void
    {
        $polos = $this->svc()->statusCounts($this->f());
        $disaring = $this->svc()->statusCounts($this->f(['status' => ['DRAFT']]));

        $this->assertSame($polos, $disaring);
    }

    public function test_ember_estimasi_membagi_habis_populasinya(): void
    {
        $semua = $this->total($this->f());
        if ($semua < 1) {
            $this->markTestSkipped('Butuh minimal satu paket aktif.');
        }

        $jumlah = 0;
        foreach (['pendek', 'sedang', 'panjang', 'tanpa'] as $ember) {
            $jumlah += $this->total($this->f(['estimasi' => $ember]));
        }

        /* Tumpang tindih membuat jumlahnya melebihi total; lubang membuatnya
           kurang. Keduanya hanya kelihatan di sini. */
        $this->assertSame($semua, $jumlah);
    }

    public function test_ember_estimasi_tanpa_mencakup_null_dan_nol(): void
    {
        $harusnya = (int) DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where(function ($q) {
                $q->whereNull('Estimasi_Waktu_Baca_Menit')
                    ->orWhere('Estimasi_Waktu_Baca_Menit', '<=', 0);
            })
            ->count();

        $this->assertSame($harusnya, $this->total($this->f(['estimasi' => 'tanpa'])));
    }

    public function test_ember_terbit_belum_adalah_tanggal_publish_yang_kosong(): void
    {
        $harusnya = (int) DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNull('Tanggal_Publish')
            ->count();

        $this->assertSame($harusnya, $this->total($this->f(['terbit' => 'belum'])));
    }

    public function test_ember_terbit_berbatas_waktu_lebih_sempit_daripada_seluruhnya(): void
    {
        $pernahTerbit = (int) DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Tanggal_Publish')
            ->count();

        if ($pernahTerbit < 1) {
            $this->markTestSkipped('Butuh minimal satu paket yang pernah terbit.');
        }

        $tahun = $this->total($this->f(['terbit' => 'tahun']));
        $tujuhHari = $this->total($this->f(['terbit' => '7hari']));

        // Ember berbatas waktu tak pernah memuat yang belum pernah terbit.
        $this->assertLessThanOrEqual($pernahTerbit, $tahun);
        $this->assertLessThanOrEqual($tahun, $tujuhHari);
    }

    public function test_grup_counts_mengabaikan_saringan_sumbunya_sendiri(): void
    {
        $dua = $this->duaPrioritas();
        if (! $dua) {
            $this->markTestSkipped('Butuh minimal dua tingkat prioritas yang sama-sama berisi paket.');
        }

        [$a] = $dua;
        $polos = $this->svc()->grupCounts($this->f(), 'prioritas');
        $disaring = $this->svc()->grupCounts($this->f(['prioritas' => [$a]]), 'prioritas');

        $this->assertGreaterThan(1, count($polos), 'Butuh lebih dari satu kelompok agar bukti ini berarti.');
        $this->assertEquals($polos, $disaring);

        /* Sementara DAFTARNYA memang menyempit — kalau tidak, yang diuji di
           atas cuma saringan yang tak pernah bekerja. */
        $this->assertLessThan($this->total($this->f()), $this->total($this->f(['prioritas' => [$a]])));
    }

    public function test_grup_counts_tetap_menghormati_saringan_sumbu_lain(): void
    {
        $hitung = $this->svc()->statusCounts($this->f());
        if ($hitung['DRAFT'] < 1 || $hitung['PUBLISHED'] < 1) {
            $this->markTestSkipped('Butuh paket berstatus DRAFT dan PUBLISHED sekaligus.');
        }

        $grup = $this->svc()->grupCounts($this->f(['status' => ['DRAFT']]), 'prioritas');
        $jumlah = array_sum(array_column($grup, 'total'));

        $this->assertSame($hitung['DRAFT'], $jumlah);
        $this->assertLessThan($this->totalSemua(), $jumlah);
    }

    public function test_grup_counts_menjumlah_ke_total_daftar_pada_setiap_sumbu(): void
    {
        $semua = $this->total($this->f());
        if ($semua < 1) {
            $this->markTestSkipped('Butuh minimal satu paket aktif.');
        }

        foreach (['prioritas', 'status', 'distribusi'] as $sumbu) {
            $grup = $this->svc()->grupCounts($this->f(), $sumbu);

            $this->assertSame(
                $semua,
                array_sum(array_column($grup, 'total')),
                "Sebaran sumbu {$sumbu} tidak menjumlah ke jumlah paket.",
            );
        }
    }

    /**
     * Sumbu Kategori dicabut 28 Agu 2026 bersama masternya.
     *
     * Uji lamanya menjaga id kategori dikirim terenkode ke rail. Setelah
     * pencabutan, seluruh sumbu yang tersisa berkode TEKS — tak ada lagi id
     * yang bisa bocor. Penjaganya dibalik: yang dijaga sekarang bahwa sumbu itu
     * benar-benar tak dikenal lagi, supaya menghidupkannya kembali tanpa
     * menghidupkan kolomnya di query tidak lolos diam-diam.
     */
    public function test_sumbu_kategori_sudah_tak_dikenal(): void
    {
        $this->assertFalse($this->svc()->sumbuGrupDikenal('kategori'));
        $this->assertSame([], $this->svc()->grupCounts($this->f(), 'kategori'));
    }

    /**
     * Sumbu yang memang tak ada harus ditolak, bukan dijawab rail kosong.
     *
     * Contohnya sengaja sebuah nama yang tak akan pernah menjadi sumbu. Uji ini
     * dulu memakai 'departemen', yang MENJADI SAH ketika identitas dokumen
     * masuk ke rail (SopIdentitasDokumenService::SUMBU, commit 19b8fc39) —
     * sehingga penjaganya berbalik menuduh fitur baru sebagai kerusakan.
     *
     * Pelajarannya: contoh negatif jangan diambil dari kosakata yang masih
     * tumbuh. Ambil yang mustahil.
     */
    public function test_sumbu_tak_dikenal_ditolak_bukan_dijawab_rail_kosong(): void
    {
        $this->assertTrue($this->svc()->sumbuGrupDikenal('prioritas'));

        // Sekalian menjaga ketiga sumbu identitas dokumen tetap dikenal, supaya
        // mencabut salah satunya tak lolos tanpa terlihat.
        foreach (['departemen', 'jenis', 'apqc'] as $sumbu) {
            $this->assertTrue($this->svc()->sumbuGrupDikenal($sumbu),
                "Sumbu identitas dokumen '{$sumbu}' seharusnya dikenal rail Builder.");
        }

        $this->assertFalse($this->svc()->sumbuGrupDikenal('sumbu_yang_tak_pernah_ada'));
        $this->assertSame([], $this->svc()->grupCounts($this->f(), 'sumbu_yang_tak_pernah_ada'));
    }

    /**
     * Paket tanpa estimasi bukan paket tercepat.
     *
     * SQL Server menaruh NULL paling awal pada ASC, jadi tanpa penjaga di
     * paginate() urutan "estimasi terpendek" membuka daftar dengan paket yang
     * justru belum punya perkiraan sama sekali.
     */
    public function test_urutan_estimasi_terpendek_tidak_membuka_dengan_yang_tanpa_estimasi(): void
    {
        $adaBerestimasi = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Estimasi_Waktu_Baca_Menit', '>', 0)
            ->exists();
        $adaTanpa = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where(function ($q) {
                $q->whereNull('Estimasi_Waktu_Baca_Menit')
                    ->orWhere('Estimasi_Waktu_Baca_Menit', '<=', 0);
            })
            ->exists();

        if (! $adaBerestimasi || ! $adaTanpa) {
            $this->markTestSkipped('Butuh paket berestimasi dan paket tanpa estimasi sekaligus.');
        }

        [$rows] = $this->svc()->paginate($this->f(['sort' => 'estimasi_pendek']), 1, 1);

        $this->assertGreaterThan(0, (int) $rows->first()->Estimasi_Waktu_Baca_Menit);
    }
}
