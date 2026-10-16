<?php

namespace Tests\Feature\SYSTEM;

use App\Services\SYSTEM\Governance\Approval\Shared\AturanKedaluwarsaApproval;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Kedaluwarsa approval izin/cuti: satu aturan, satu bacaan.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * Satu nilai konfigurasi dibaca dengan dua arti yang BERLAWANAN:
 *
 *   parseFlagApproval()  — disalin identik (1.209 byte) di
 *   IzinAtasanApprovalController dan AbsensiController:
 *       batas null  ->  tidak ada kedaluwarsa
 *
 *   AbsensiController, saat MENULIS Expired_At untuk cuti:
 *       (int) (config(...batas_approve_cuti) ?? 0)  ->  batas 0 hari
 *       Carbon::now()->addDays(0)                   ->  kedaluwarsa seketika
 *
 * `batas_approve_cuti` memang null (config/services.php mengembalikan null bila
 * HCIS_APPROVE_CUTI_LIMIT tidak diset). Jadi satu jalur bilang cuti tidak pernah
 * kedaluwarsa sementara jalur lain menuliskannya kedaluwarsa pada detik
 * pengajuan dibuat.
 *
 * Data memutuskan mana yang berlaku: 122 baris approval CT di
 * HRIS_Approval_Request, seluruhnya ber-Expired_At NULL — nol baris kedaluwarsa
 * seketika. Jalur "tanpa batas" itulah yang nyata.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 *   1. batas yang tidak diset (null, kosong, 0, negatif) berarti TANPA batas
 *   2. tanpa batas -> Expired_At NULL, bukan "sekarang"
 *   3. terjemahan flag setia pada perilaku lama, termasuk kehalusannya:
 *      flag string KOSONG dikembalikan kosong, bukan dinormalkan jadi 'N'
 *   4. tidak ada lagi salinan aturan di controller
 *   5. tidak ada lagi yang memaksa null jadi 0
 *
 * Uji ini tidak menyentuh basis data. Konfigurasi diubah lewat config()->set()
 * dan waktu dibekukan lewat Carbon::setTestNow().
 */
class AturanKedaluwarsaApprovalSatuRumahTest extends TestCase
{
    private const RUMAH = 'app/Services/SYSTEM/Governance/Approval/Shared/AturanKedaluwarsaApproval.php';

    private const KUNCI_IZIN = 'services.hcis.batas_approve_izin';

    private const KUNCI_CUTI = 'services.hcis.batas_approve_cuti';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Buang komentar, pertahankan nomor baris. */
    private function tanpaKomentar(string $berkas): string
    {
        $keluar = '';
        foreach (token_get_all((string) file_get_contents($berkas)) as $t) {
            if (is_array($t)) {
                if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                    $keluar .= str_repeat("\n", substr_count($t[1], "\n"));

                    continue;
                }
                $keluar .= $t[1];
            } else {
                $keluar .= $t;
            }
        }

        return $keluar;
    }

    /** @return array<string, string> */
    private function berkasPhp(): array
    {
        $keluar = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            $rel = str_replace([base_path().DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'],
                $f->getPathname());
            $keluar[$rel] = $this->tanpaKomentar($f->getPathname());
        }
        ksort($keluar);

        return $keluar;
    }

    public function test_batas_yang_tidak_diset_berarti_tanpa_batas(): void
    {
        foreach ([null, '', 0, -1] as $nilai) {
            config()->set(self::KUNCI_CUTI, $nilai);
            $this->assertNull(AturanKedaluwarsaApproval::batasHari('cuti'),
                'Nilai '.var_export($nilai, true).' harus dibaca sebagai tanpa batas, '
                .'bukan sebagai batas nol hari.');
        }

        config()->set(self::KUNCI_CUTI, 5);
        $this->assertSame(5, AturanKedaluwarsaApproval::batasHari('cuti'));
        $this->assertSame(5, AturanKedaluwarsaApproval::batasHari('CUTI '));
    }

    public function test_jenis_selain_cuti_memakai_ambang_izin(): void
    {
        config()->set(self::KUNCI_IZIN, 2);
        config()->set(self::KUNCI_CUTI, 30);

        $this->assertSame(30, AturanKedaluwarsaApproval::batasHari('cuti'));
        foreach (['izinFull', 'sakit', 'terlambat', 'pulangCepat', null, ''] as $jenis) {
            $this->assertSame(2, AturanKedaluwarsaApproval::batasHari($jenis),
                'Jenis '.var_export($jenis, true).' harus memakai ambang izin.');
        }
    }

    public function test_tanpa_batas_menghasilkan_expired_at_null_bukan_sekarang(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 8, 10, 30, 0));
        config()->set(self::KUNCI_CUTI, null);

        $this->assertNull(AturanKedaluwarsaApproval::kedaluwarsaPada('cuti'),
            'Tanpa batas harus berarti kolom Expired_At NULL. Mengembalikan "sekarang" '
            .'membuat setiap pengajuan cuti kedaluwarsa pada detik ia dibuat — dan '
            .'seluruh 122 baris CT yang ada justru ber-Expired_At NULL.');
    }

    public function test_dengan_batas_kedaluwarsa_jatuh_di_akhir_hari(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 8, 10, 30, 0));
        config()->set(self::KUNCI_IZIN, 2);

        $hasil = AturanKedaluwarsaApproval::kedaluwarsaPada('izinFull');

        $this->assertNotNull($hasil);
        $this->assertSame('2026-09-10 23:59:59', $hasil->format('Y-m-d H:i:s'),
            'Mengikuti ApprovalFlowService (penulis approval terpusat) yang memakai '
            .'addDays($limit)->endOfDay().');
    }

    public function test_terjemahan_flag_setia_pada_perilaku_lama(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 8, 10, 0, 0));
        config()->set(self::KUNCI_IZIN, 2);
        config()->set(self::KUNCI_CUTI, null);

        $lama = Carbon::create(2026, 9, 1);     // 7 hari lalu, lewat batas
        $baru = Carbon::create(2026, 9, 8);     // hari ini, belum lewat

        // sudah disetujui: umur tidak lagi relevan
        $this->assertSame('Y', AturanKedaluwarsaApproval::statusFlag('Y', $lama, 'izinFull'));

        // kosong dan sudah lewat batas -> dianggap kedaluwarsa sistem
        $this->assertSame('P', AturanKedaluwarsaApproval::statusFlag(null, $lama, 'izinFull'));

        // kosong tapi belum lewat -> null tetap jadi 'N'
        $this->assertSame('N', AturanKedaluwarsaApproval::statusFlag(null, $baru, 'izinFull'));

        // sudah ada isinya -> dikembalikan apa adanya
        $this->assertSame('T', AturanKedaluwarsaApproval::statusFlag('T', $lama, 'izinFull'));

        // cuti tanpa batas -> tidak pernah jadi 'P' walau setua apa pun
        $this->assertSame('N', AturanKedaluwarsaApproval::statusFlag(null, $lama, 'cuti'));
        $this->assertSame('Y', AturanKedaluwarsaApproval::statusFlag('Y', $lama, 'cuti'));
    }

    public function test_flag_string_kosong_tidak_dinormalkan_jadi_n(): void
    {
        /*
         * Kehalusan yang mudah hilang saat memindahkan kode: `$flag ?? 'N'`
         * hanya menangkap null. Flag berupa string kosong dikembalikan KOSONG,
         * dan tampilan di hilir membedakan keduanya.
         */
        Carbon::setTestNow(Carbon::create(2026, 9, 8, 10, 0, 0));
        config()->set(self::KUNCI_IZIN, 2);
        config()->set(self::KUNCI_CUTI, null);

        $baru = Carbon::create(2026, 9, 8);

        $this->assertSame('', AturanKedaluwarsaApproval::statusFlag('', $baru, 'izinFull'),
            'Flag string kosong harus tetap kosong (jalur berbatas, belum lewat).');
        $this->assertSame('', AturanKedaluwarsaApproval::statusFlag('', $baru, 'cuti'),
            'Flag string kosong harus tetap kosong (jalur tanpa batas).');
    }

    /**
     * Tempat yang masih MENYEBUT ambang ini sendiri, beserta jumlah sebutannya.
     *
     * Koreksi atas pembacaan awal saya: ini BUKAN delapan salinan dari satu
     * aturan. Setelah ditelusuri satu per satu, ketiganya menjawab pertanyaan
     * yang berbeda —
     *
     *   a. "sudah kedaluwarsa atau belum" (terjemahan flag)  -> sudah dipusatkan
     *      di kelas ini
     *   b. "batas umur untuk MENAMPILKAN antrean approver"    -> jendela tampilan,
     *      dan daftar jenis yang terkena sudah dipusatkan sebagai
     *      AllTransactionsReadService::JENIS_DIBATASI_UMUR
     *   c. "kapan Expired_At ditulis"                          -> sudah lewat
     *      kedaluwarsaPada() di kelas ini
     *
     * Yang benar-benar tersisa kembar hanyalah satu ungkapan cutoff umur
     * (Carbon::now()->subDays(...)) di dua service, dan keduanya sudah memakai
     * daftar jenis yang sama. Menyatukan sisanya berarti menyamakan tiga
     * pertanyaan yang memang berbeda — itu akan memperburuk, bukan memperbaiki.
     *
     * Daftarnya tetap dibekukan sebagai RATCHET: gunanya bukan menagih
     * penyatuan, melainkan memastikan tempat BARU yang menyebut ambang ini
     * dibahas lebih dulu.
     *
     * Daftar ini DIBEKUKAN, bukan diabaikan. Berkas baru yang menyebut ambang
     * membuat uji ini merah, dan berkas yang berhasil dibebaskan juga membuatnya
     * merah sampai barisnya dihapus. Utangnya tidak bisa bertambah diam-diam dan
     * tidak bisa lenyap dari catatan.
     */
    private const MASIH_MENGUNGKAPKAN_SENDIRI = [
        'app/Http/Controllers/ESS/Attendance/Leave/Approval/IzinAtasanApprovalController.php' => 4,
        'app/Http/Controllers/ESS/Attendance/Leave/Personal/CutiController.php' => 5,
        'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php' => 4,
        'app/Http/Controllers/ESS/Dashboard/Personal/uDashController.php' => 2,
        'app/Http/Controllers/ESS/Organization/Team/karyawanTeamController.php' => 2,
        'app/Services/ESS/Dashboard/Homepage/HomepageLeaderDashboardService.php' => 1,
        'app/Services/ESS/Dashboard/Personal/UDashReadModelService.php' => 1,
        'app/Services/SYSTEM/Application/Transactions/AllTransactionsReadService.php' => 1,
    ];

    public function test_tidak_ada_salinan_php_dari_terjemahan_flag(): void
    {
        /*
         * Yang dilarang bukan menyebut ambangnya, melainkan MENGULANG
         * keputusannya di PHP: sebuah metode yang membaca ambang lalu
         * memutuskan 'P' sendiri. Itulah bentuk parseFlagApproval yang tadinya
         * ada dua salinan identik.
         */
        $penyalin = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            if ($rel === self::RUMAH) {
                continue;
            }
            if (! preg_match_all('/function\s+\w*(?:parseFlagApproval|statusFlag|flagApproval)\w*\s*\(/i',
                $isi, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as $x) {
                /* 1.500 karakter sesudah tanda tangan: cukup memuat badan metode sebesar itu */
                $jendela = substr($isi, $x[1], 1500);
                if (str_contains($jendela, 'batas_approve')) {
                    $penyalin[] = $rel;
                    break;
                }
            }
        }

        $this->assertSame([], $penyalin,
            'Terjemahan flag diulang di PHP di luar AturanKedaluwarsaApproval. Dua salinan '
            .'berhenti sama pada perbaikan pertama yang hanya menyentuh satu sisi.');
    }

    public function test_daftar_yang_masih_mengungkapkan_aturan_sendiri_tidak_bertambah(): void
    {
        $nyata = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            if ($rel === self::RUMAH) {
                continue;
            }
            $n = preg_match_all('/batas_approve_\w+/', $isi);
            if ($n > 0) {
                $nyata[$rel] = $n;
            }
        }

        $baru = array_values(array_diff(array_keys($nyata), array_keys(self::MASIH_MENGUNGKAPKAN_SENDIRI)));
        $this->assertSame([], $baru,
            'Tempat BARU yang mengungkapkan aturan kedaluwarsa sendiri. Pakai '
            .'AturanKedaluwarsaApproval; kalau memang harus SQL, tambahkan alasannya '
            .'ke daftar beku setelah dibahas.');

        $sudahBebas = array_values(array_diff(array_keys(self::MASIH_MENGUNGKAPKAN_SENDIRI), array_keys($nyata)));
        $this->assertSame([], $sudahBebas,
            'Berkas ini sudah tidak mengungkapkan aturan sendiri. Hapus barisnya dari '
            .'MASIH_MENGUNGKAPKAN_SENDIRI supaya daftarnya tidak jadi tempat sampah abadi.');

        foreach (self::MASIH_MENGUNGKAPKAN_SENDIRI as $rel => $jumlah) {
            $this->assertSame($jumlah, $nyata[$rel] ?? 0,
                "Jumlah sebutan ambang di {$rel} berubah — periksa apakah aturannya diulang lagi.");
        }
    }

    public function test_tidak_ada_yang_memaksa_batas_null_menjadi_nol(): void
    {
        $pemaksa = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            if (preg_match('/batas_approve_\w+\'\s*\)\s*\?\?\s*0/', $isi)
                || preg_match('/\(int\)\s*\(\s*config\(\s*\'services\.hcis\.batas_approve_\w+\'\s*\)\s*\?\?\s*0/', $isi)) {
                $pemaksa[] = $rel;
            }
        }

        $this->assertSame([], $pemaksa,
            'Ambang null dipaksa jadi 0. Nol berarti "kedaluwarsa seketika", '
            .'kebalikan dari arti null di jalur baca — inilah cacat aslinya.');
    }

    public function test_kedua_controller_mendelegasi_ke_aturan_bersama(): void
    {
        foreach ([
            'app/Http/Controllers/ESS/Attendance/Leave/Approval/IzinAtasanApprovalController.php',
            'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php',
        ] as $rel) {
            $isi = $this->tanpaKomentar(base_path($rel));
            $this->assertStringContainsString('AturanKedaluwarsaApproval::statusFlag', $isi,
                "{$rel} tidak lagi mendelegasi terjemahan flag.");
        }

        $absensi = $this->tanpaKomentar(base_path(
            'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php'));
        $this->assertStringContainsString('AturanKedaluwarsaApproval::kedaluwarsaPada', $absensi,
            'Penulisan Expired_At harus lewat aturan bersama.');
        $this->assertStringNotContainsString('addDays($batasApprovalCuti)', $absensi);
        $this->assertStringNotContainsString('addDays($batasApprovalIzin)', $absensi);
    }
}
