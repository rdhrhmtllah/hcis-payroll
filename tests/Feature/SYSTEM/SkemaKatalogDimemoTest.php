<?php

namespace Tests\Feature\SYSTEM;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Pertanyaan skema ditanyakan ke basis data SEKALI, bukan tiap kali dibutuhkan.
 *
 * ── Kenapa ini bukan kerewelan gaya ──────────────────────────────────────────
 *
 * Di SQL Server, `Schema::hasTable()` TIDAK dikompilasi menjadi lookup ringan.
 * Laravel menerjemahkannya menjadi kueri yang mengagregasi UKURAN SELURUH TABEL
 * di basis data (`sum(u.total_pages)` atas `sys.tables` × `sys.partitions` ×
 * `sys.allocation_units`). Terukur di basis data ini lewat dm_exec_query_stats:
 *
 *     46.285 halaman dibaca dan 99 ms CPU untuk SATU panggilan,
 *     4.457 eksekusi, total 442 detik CPU
 *
 * — pemakan CPU terbesar dari sisi aplikasi ini, untuk pertanyaan yang
 * jawabannya tidak pernah berubah.
 *
 * Terukur pada perubahan yang menyertai penjaga ini, memanggil
 * SopPaketDraftService::available() sembilan kali (persis jumlah pemanggilnya
 * di dalam kelas itu):
 *
 *     sebelum : 36 kueri katalog, 20.600 ms
 *     sesudah :  4 kueri katalog,  2.693 ms
 *
 * Jawaban pembantu dibuktikan IDENTIK dengan `Schema::` aslinya pada 17 kasus,
 * termasuk kasus negatif (tabel dan kolom yang memang tidak ada).
 */
class SkemaKatalogDimemoTest extends TestCase
{
    /** Rumah satu-satunya untuk pertanyaan skema. Ia memang harus memanggil Schema::. */
    private const PEMBANTU = 'app/Services/SYSTEM/Application/Database/SkemaTersedia.php';

    /**
     * Keadaan terukur 8 Sep 2026 di app/Http + app/Services, di luar pembantu.
     *
     * Dua belas pemanggilan langsung yang tersisa BUKAN utang: seluruhnya adalah
     * implementasi memo itu sendiri — cache statis milik ApproverResolverService,
     * ApprovalMatrixService, dan AuthorizationScopeResolver; Cache::remember di
     * HandleInertiaRequests dan InertiaShellService; serta pola `??=` atau
     * properti instance di beberapa service. Masing-masing sudah menanyakan
     * basis data sekali. Memaksanya lewat SkemaTersedia hanya memindahkan rumah
     * cache tanpa menghemat satu perjalanan pun.
     *
     * RATCHET, bukan NOL, karena nol di sini akan MENYESATKAN: ia menuntut
     * membongkar lima cache yang sudah bekerja.
     *
     * Yang dijaga: angkanya tidak boleh NAIK.
     */
    private const BATAS = 12;

    /** @return list<string> */
    private function berkasSumber(): array
    {
        $hasil = [];

        foreach (['app/Http', 'app/Services'] as $akar) {
            $absolut = base_path($akar);
            if (! is_dir($absolut)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $iterator */
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolut));
            foreach ($iterator as $berkas) {
                if (! $berkas->isFile() || $berkas->getExtension() !== 'php') {
                    continue;
                }

                /* Urutan penting: pemisah dinormalkan LEBIH DULU pada KEDUA sisi.
                   Versi pertama mengganti DIRECTORY_SEPARATOR dulu lalu mencoba
                   membuang base_path() yang masih ber-backslash — sudah tidak
                   cocok, sehingga path tetap absolut dan base_path($rel)
                   menghasilkan path ganda. Ketahuan hanya karena uji ini
                   dijalankan sungguhan, bukan disalin logikanya. */
                $absolutNormal = str_replace('\\', '/', $berkas->getPathname());
                $akarNormal = str_replace('\\', '/', base_path()).'/';
                $rel = str_replace($akarNormal, '', $absolutNormal);
                if ($rel !== self::PEMBANTU) {
                    $hasil[] = $rel;
                }
            }
        }

        return $hasil;
    }

    /**
     * Pemanggilan langsung per berkas. Komentar tidak dihitung.
     *
     * @return array<string, int>
     */
    private function pemanggilanLangsung(): array
    {
        $per = [];

        foreach ($this->berkasSumber() as $rel) {
            foreach (explode("\n", (string) file_get_contents(base_path($rel))) as $baris) {
                $bersih = ltrim($baris);
                if (str_starts_with($bersih, '//') || str_starts_with($bersih, '*')) {
                    continue;
                }

                $n = preg_match_all('/Schema::has(Table|Column)\s*\(/', $baris);
                if ($n) {
                    $per[$rel] = ($per[$rel] ?? 0) + $n;
                }
            }
        }

        return $per;
    }

    public function test_pemanggilan_skema_langsung_tidak_bertambah(): void
    {
        $per = $this->pemanggilanLangsung();
        $jumlah = array_sum($per);

        arsort($per);
        $ringkas = [];
        foreach (array_slice($per, 0, 6, true) as $berkas => $n) {
            $ringkas[] = "  {$berkas} ×{$n}";
        }

        $this->assertLessThanOrEqual(
            self::BATAS,
            $jumlah,
            'Pemanggilan Schema::hasTable/hasColumn langsung bertambah dari '.self::BATAS." jadi {$jumlah}.\n".
            "Di SQL Server satu hasTable() membaca 46.285 halaman — ia bukan lookup murah.\n".
            "Pakai SkemaTersedia::tabel()/kolom() yang menanyakannya sekali per proses.\n".
            "Terbanyak sekarang:\n".implode("\n", $ringkas),
        );
    }

    public function test_berkas_yang_sudah_dibereskan_tidak_kembali_memanggil_langsung(): void
    {
        /*
         * Menjaga per berkas, terpisah dari ratchet total, dan itu perlu:
         * tanpa ini satu berkas boleh kembali memanggil langsung asalkan berkas
         * lain kebetulan berkurang — ratchet totalnya tetap hijau sementara
         * perbaikan yang sudah diukur diam-diam hilang.
         */
        $sudahBersih = [
            // sisa 1: hasColumn ber-`??=` di baris lain, memang sudah bermemo.
            'app/Services/LMS/SOP/SopPaketDraftService.php' => 1,
            // sisa 2: dua hasTable pada satu baris, dimemo ke $this->available.
            'app/Services/LMS/SOP/SopPaketVersionService.php' => 2,

            'app/Http/Controllers/LMS/Content/Material/LmsMateriController.php' => 0,
            'app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoringController.php' => 0,
            'app/Http/Controllers/ESS/Dashboard/Personal/uDashController.php' => 0,
            'app/Http/Controllers/ESS/Organization/Team/karyawanTeamController.php' => 0,
            'app/Http/Controllers/ESS/Employee/Profile/userController.php' => 0,
            'app/Http/Controllers/SYSTEM/Governance/Authorization/SystemAuthController.php' => 0,
            'app/Services/ESS/Attendance/Leave/Admin/cutiBersamaService.php' => 0,
            'app/Services/LMS/SOP/SopPaketService.php' => 0,
            'app/Services/SYSTEM/Application/Transactions/TransactionService.php' => 0,
            'app/Services/SYSTEM/Governance/Approval/Shared/ApprovalFlowService.php' => 0,
            'app/Services/SYSTEM/Governance/Authorization/Membership/MembershipManagementService.php' => 0,
            'app/Services/SYSTEM/Governance/Authorization/Menu/MenuDelegationPolicyService.php' => 0,
            'app/Services/SYSTEM/Governance/Authorization/Scope/EmployeeTargetScopeService.php' => 0,
            'app/Services/SYSTEM/Governance/Authorization/Shared/AuthorizationAuditLogger.php' => 0,
        ];

        $per = $this->pemanggilanLangsung();

        foreach ($sudahBersih as $berkas => $maks) {
            $this->assertLessThanOrEqual(
                $maks,
                $per[$berkas] ?? 0,
                "{$berkas} kembali memanggil Schema:: langsung. Berkas ini sudah ".
                'dikonversi ke SkemaTersedia dan hasilnya diukur; jangan dibalik.',
            );
        }
    }

    public function test_pembantu_benar_benar_memoisasi(): void
    {
        $isi = (string) file_get_contents(base_path(self::PEMBANTU));

        /*
         * Penjaga atas MAKNA, bukan atas keberadaan berkas. Pembantu yang
         * meneruskan panggilan tanpa mengingat jawabannya akan lulus setiap uji
         * di atas sambil tidak menghemat apa pun — dan pemanggilnya akan yakin
         * masalahnya sudah beres.
         */
        $this->assertMatchesRegularExpression(
            '/private static array \$tabel\s*=\s*\[\];/',
            $isi,
            'Cache tabel harus STATIC: service pemakainya bukan singleton, jadi cache '.
            'tingkat-instance tidak akan pernah kena.',
        );

        $this->assertMatchesRegularExpression(
            '/private static array \$kolom\s*=\s*\[\];/',
            $isi,
            'Cache kolom harus STATIC, dengan alasan yang sama.',
        );

        $this->assertSame(
            2,
            preg_match_all('/array_key_exists\(/', $isi),
            'Kedua pembacaan (tabel dan kolom) harus dijaga array_key_exists — bukan '.
            'isset atau ??, yang memperlakukan jawaban "false" sebagai belum pernah '.
            'ditanyakan dan membuat memo-nya tidak berlaku justru pada kasus '.
            '"tabel tidak ada" yang paling sering ditanyakan berulang.',
        );
    }

    /**
     * Berkas penjaga ini pernah KOSONG tanpa ada yang menyadarinya.
     *
     * Sebabnya sepele dan justru karena itu berbahaya: sebuah skrip perapian
     * akhir-baris membuka berkas untuk DITULIS sebelum membaca isinya, sehingga
     * yang tersimpan adalah hasil membaca berkas yang sudah terpotong. `php -l`
     * lulus pada berkas kosong, dan verifikasi sabotase saat itu menguji SALINAN
     * logikanya, bukan berkas ini — jadi semuanya tampak hijau sementara tak ada
     * satu pun uji yang berjalan.
     *
     * Uji ini menutup celah itu: kelas yang kehilangan isinya tidak bisa lagi
     * lolos diam-diam.
     */
    public function test_penjaga_ini_tidak_kosong(): void
    {
        $isi = (string) file_get_contents(__FILE__);

        $this->assertGreaterThan(
            2000,
            strlen($isi),
            'Berkas penjaga menyusut drastis — kemungkinan terpotong oleh perkakas.',
        );

        $this->assertSame(
            4,
            preg_match_all('/\n    public function test_/', $isi),
            'Jumlah uji di berkas ini berubah. Kalau memang sengaja, perbarui angka '.
            'ini; kalau tidak, ada uji yang hilang.',
        );
    }
}
