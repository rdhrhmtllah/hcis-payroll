<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Leave\Shared\AturanAkrualCuti;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Aturan akrual cuti dipakai bersama oleh yang MEMBERI dan yang MENAMPILKAN.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * Aturannya ditulis dua kali, di dua tempat yang perannya berlawanan:
 *
 *   tambahKuotaCutiBulanan   perintah yang BENAR-BENAR memberi kuotanya
 *   CutiDashboardService     layar yang MENAMPILKAN perkiraannya
 *
 * Selama keduanya salinan terpisah, tak ada apa pun yang memaksa angka di layar
 * sama dengan angka yang terjadi. Karyawan membaca "bulan depan +1" lalu
 * menerima +2 — dan tidak ada galat, karena dua-duanya benar menurut salinannya
 * masing-masing.
 *
 * Saat dipusatkan ketiga metode tanggalnya masih identik, dan aturan jumlah
 * harinya pun sama (satu berupa metode, satu lagi if inline). Yang diperbaiki
 * bukan perbedaan hari ini melainkan bentuk yang membuat perbedaan besok tidak
 * terdeteksi.
 *
 * Seluruh uji di bawah ini murni aritmetika tanggal — nol kueri.
 */
class AturanAkrualCutiSatuRumahTest extends TestCase
{
    public function test_tanggal_tahunan_dijepit_ke_akhir_bulan(): void
    {
        /*
         * Karyawan yang masuk 29 Februari tetap harus punya tanggal siklus di
         * tahun biasa. Tanpa penjepitan, Carbon melimpah ke 1 Maret dan seluruh
         * siklus tahun kerjanya bergeser sehari — lalu paruh keduanya ikut
         * bergeser, dan dengan itu siapa yang dapat dua hari.
         */
        $this->assertSame(
            '2023-02-28',
            AturanAkrualCuti::tanggalTahunan(Carbon::create(2020, 2, 29), 2023)->toDateString(),
        );

        $this->assertSame(
            '2024-02-29',
            AturanAkrualCuti::tanggalTahunan(Carbon::create(2020, 2, 29), 2024)->toDateString(),
            'Tahun kabisat harus tetap 29 Februari, bukan ikut dijepit ke 28.',
        );

        $this->assertSame(
            '2026-01-31',
            AturanAkrualCuti::tanggalTahunan(Carbon::create(2019, 1, 31), 2026)->toDateString(),
        );
    }

    public function test_jatuh_tempo_bulanan_dijepit_ke_panjang_bulan_periode(): void
    {
        /*
         * Masuk tanggal 31: jatuh temponya di Februari WAJIB 28/29, bukan 3
         * Maret. Ini yang membuat akrual orang bertanggal-masuk akhir bulan
         * tidak melompati satu periode.
         */
        $masuk = Carbon::create(2019, 1, 31);

        $this->assertSame(
            '2026-02-28',
            AturanAkrualCuti::jatuhTempoBulanan($masuk, Carbon::create(2026, 2, 1))->toDateString(),
        );

        $this->assertSame(
            '2024-02-29',
            AturanAkrualCuti::jatuhTempoBulanan($masuk, Carbon::create(2024, 2, 1))->toDateString(),
        );

        $this->assertSame(
            '2026-03-31',
            AturanAkrualCuti::jatuhTempoBulanan($masuk, Carbon::create(2026, 3, 1))->toDateString(),
        );
    }

    public function test_siklus_mundur_setahun_bila_ulang_tahun_belum_lewat(): void
    {
        $masuk = Carbon::create(2020, 7, 10);

        // jatuh tempo SEBELUM ulang tahun masuk kerja tahun itu
        $siklus = AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2026, 3, 5));
        $this->assertSame('2025-07-10', $siklus['cycle_start']->toDateString(),
            'Siklus harus mundur ke ulang tahun TAHUN LALU selama ulang tahun tahun ini belum lewat.');

        // jatuh tempo SESUDAH ulang tahun
        $siklus = AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2026, 9, 5));
        $this->assertSame('2026-07-10', $siklus['cycle_start']->toDateString());
    }

    public function test_batas_paruh_kedua_persis_di_bulan_keenam(): void
    {
        /*
         * INI BATAS YANG MENENTUKAN UANG ORANG. Paruh kedua dimulai TEPAT pada
         * titik enam bulan (>=) dan berakhir SEBELUM ulang tahun berikutnya (<).
         * Menggeser salah satu tanda sehari saja memindahkan satu bulan akrual
         * dari +1 ke +2, atau sebaliknya.
         */
        $masuk = Carbon::create(2020, 1, 15);

        $sehariSebelum = AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2026, 7, 14));
        $this->assertFalse($sehariSebelum['is_second_half'], 'Sehari sebelum titik enam bulan masih paruh pertama.');

        $tepat = AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2026, 7, 15));
        $this->assertTrue($tepat['is_second_half'], 'Tepat di titik enam bulan sudah paruh kedua (>=, bukan >).');

        $sehariSebelumUlangTahun = AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2027, 1, 14));
        $this->assertTrue($sehariSebelumUlangTahun['is_second_half']);

        $tepatUlangTahun = AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2027, 1, 15));
        $this->assertFalse(
            $tepatUlangTahun['is_second_half'],
            'Pada ulang tahun berikutnya siklusnya sudah BERGANTI, jadi ia paruh pertama lagi.',
        );
    }

    public function test_batas_atas_hanya_teruji_lewat_penjepitan_kabisat(): void
    {
        /*
         * Batas atasnya (`< ulang tahun berikutnya`) TAMPAK tak terjangkau:
         * begitu jatuh tempo mencapai ulang tahun, siklusnya sudah berganti,
         * jadi ulang tahun berikutnya selalu ada di depan. Versi pertama uji
         * ini karena itu HIJAU ketika `<` disabotase menjadi `<=`.
         *
         * Satu-satunya jalan masuk adalah penjepitan kabisat. Karyawan yang
         * masuk 29 Februari 2020, dengan jatuh tempo 28 Februari 2028:
         *
         *   ulang tahun 2028   = 29 Feb 2028  -> lewat dari jatuh tempo,
         *                                        jadi siklus mundur
         *   cycle_start        = 28 Feb 2027  (dijepit, 2027 bukan kabisat)
         *   next_anniversary   = 28 Feb 2028  = TEPAT jatuh temponya
         *
         * Di titik itulah `<` dan `<=` berbeda — dan bedanya satu bulan akrual
         * bernilai dua hari bagi golongan 6 PKWTT.
         */
        $masuk = Carbon::create(2020, 2, 29);
        $siklus = AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2028, 2, 28));

        $this->assertSame('2027-02-28', $siklus['cycle_start']->toDateString());
        $this->assertSame('2028-02-28', $siklus['next_anniversary']->toDateString());
        $this->assertFalse(
            $siklus['is_second_half'],
            'Jatuh tempo yang jatuh TEPAT pada ulang tahun berikutnya sudah di luar '.
            'siklus ini. Memakai <= di sana memberi satu bulan akrual ekstra.',
        );
    }

    public function test_bulan_ke_berapa_dalam_siklus_dihitung_dari_satu(): void
    {
        $masuk = Carbon::create(2020, 1, 15);

        $this->assertSame(
            1,
            AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2026, 1, 15))['month_in_cycle'],
            'Bulan pertama siklus adalah 1, bukan 0.',
        );

        $this->assertSame(
            7,
            AturanAkrualCuti::siklusTahunKerja($masuk, Carbon::create(2026, 7, 15))['month_in_cycle'],
        );
    }

    /** @return array<string, array{0: ?int, 1: ?string, 2: bool, 3: int}> */
    public static function kasusJumlah(): array
    {
        return [
            'golongan 6 PKWTT paruh kedua' => [6, 'PKWTT', true, 2],
            'golongan 6 PKWTT paruh pertama' => [6, 'PKWTT', false, 1],
            'golongan 6 tapi PKWT' => [6, 'PKWT', true, 1],
            'golongan lain meski PKWTT paruh kedua' => [5, 'PKWTT', true, 1],
            'golongan null' => [null, 'PKWTT', true, 1],
            'kontrak null' => [6, null, true, 1],
            'golongan 6 sebagai teks' => [6, 'PKWTT', true, 2],
        ];
    }

    /**
     * @dataProvider kasusJumlah
     */
    public function test_hanya_satu_kombinasi_yang_mendapat_dua_hari(
        ?int $golongan,
        ?string $kontrak,
        bool $paruhKedua,
        int $harap,
    ): void {
        /*
         * KETIGA syaratnya harus terpenuhi bersama-sama. Mengendurkan salah
         * satunya menggandakan kuota sekelompok orang yang tidak berhak, dan
         * itu tidak akan terlihat sampai seseorang menghitung ulang saldonya.
         */
        $this->assertSame(
            $harap,
            AturanAkrualCuti::jumlahBulanan($golongan, $kontrak, ['is_second_half' => $paruhKedua]),
        );
    }

    public function test_siklus_tanpa_kunci_paruh_kedua_dianggap_paruh_pertama(): void
    {
        $this->assertSame(
            1,
            AturanAkrualCuti::jumlahBulanan(6, 'PKWTT', []),
            'Larik siklus tanpa kunci is_second_half harus jatuh ke satu hari, bukan melempar.',
        );
    }

    public function test_tidak_ada_satu_pun_salinan_tersisa_di_seluruh_aplikasi(): void
    {
        /*
         * SAPUAN MENYELURUH, bukan daftar berkas.
         *
         * Versi pertama penjaga ini hanya memeriksa dua berkas — perintah dan
         * dashboard — karena pemindai duplikasi bekerja per PASANGAN berkas dan
         * hanya menyorot pasangan teratas. Salinan KETIGA di
         * KuotaManagementController lolos begitu saja, dan baru ketahuan saat
         * seluruh app/ disapu untuk nama metodenya.
         *
         * Karena itu yang dijaga sekarang bukan "kedua berkas itu bersih"
         * melainkan "tidak ada satu pun salinan di mana pun".
         */
        $ditemukan = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            $isi = (string) file_get_contents($berkas->getPathname());
            $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $berkas->getPathname());

            foreach (['resolveMonthlyDueDate', 'getWorkYearCycleInfo', 'resolveYearlyDate'] as $metode) {
                if (str_contains($isi, "function {$metode}(")) {
                    $ditemukan[] = "{$rel}::{$metode}()";
                }
            }

            /*
             * Aturan jumlah harinya juga tidak boleh ditulis ulang inline — di
             * ketiga salinan lama justru bentuk itulah yang dipakai, dan ia
             * tidak terlihat oleh pencarian nama metode.
             */
            if (preg_match('/ID_Golongan\s*(?:===|==)\s*6/', $isi)) {
                $ditemukan[] = "{$rel} (aturan golongan 6 inline)";
            }
        }

        $this->assertSame(
            [],
            $ditemukan,
            "Aturan akrual ditulis ulang di luar rumah bersamanya:\n  "
            .implode("\n  ", $ditemukan)."\n"
            .'Selama ada dua tempat, tidak ada yang menjamin angka yang DIBERIKAN sama '
            .'dengan angka yang DITAMPILKAN.',
        );
    }

    public function test_ketiga_bekas_pemilik_salinan_memakai_rumah_bersamanya(): void
    {
        foreach ([
            'app/Console/Commands/ESS/Attendance/Leave/Admin/tambahKuotaCutiBulanan.php',
            'app/Services/ESS/Attendance/Leave/Dashboard/CutiDashboardService.php',
            'app/Http/Controllers/ESS/Attendance/Leave/Admin/KuotaManagementController.php',
        ] as $berkas) {
            $isi = (string) file_get_contents(base_path($berkas));

            foreach (['resolveMonthlyDueDate', 'getWorkYearCycleInfo', 'resolveYearlyDate'] as $metode) {
                $this->assertStringNotContainsString(
                    "function {$metode}(",
                    $isi,
                    "{$berkas} memegang salinan {$metode}() lagi.",
                );
            }

            $this->assertStringContainsString(
                'AturanAkrualCuti::',
                $isi,
                "{$berkas} tidak lagi memakai aturan bersamanya.",
            );

            /*
             * Aturan jumlah harinya juga tidak boleh ditulis ulang inline —
             * bentuk itulah yang dulu menyembunyikannya di perintah, sementara
             * dashboard menyimpannya sebagai metode.
             */
            $this->assertStringNotContainsString(
                '=== 6 && ',
                $isi,
                "{$berkas} menulis ulang aturan golongan 6 secara inline.",
            );
        }
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(5000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
