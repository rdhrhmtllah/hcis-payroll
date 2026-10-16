<?php

namespace Tests\Feature\SYSTEM;

use App\Services\SYSTEM\Audit\Numbering\AdminLogNumberService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * No_Transaksi untuk HRIS_Transaksi_Log_Admin: satu pintu, satu strategi.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * AuditAdminTransaction memuat SALINAN PERSIS AdminLogNumberService::generate()
 * — token demi token — dan kedua berkas memasang komentar yang menyatakan
 * keduanya "sengaja identik" supaya saling-eksklusi pada applock yang sama.
 *
 * Komentar bukan penjaga. Begitu satu sisi diperbaiki dan yang lain tidak,
 * keduanya berhenti saling-eksklusi dan nomor kembar muncul justru pada tabel
 * AUDIT — tempat yang paling tidak boleh berbohong.
 *
 * Strategi lamanya sendiri mahal: sp_getapplock Exclusive dengan
 * LockOwner='Transaction' pada resource per-BULAN. Setiap penulisan admin dalam
 * bulan yang sama antre di satu lock dengan timeout 10 detik, dan lock itu
 * dipegang sampai transaksi pemanggil commit — salah satunya
 * karyawanTeamController::simpanUser, transaksi ratusan baris.
 *
 * Sekarang keduanya lewat AdminLogNumberService, yang memakai counter atomik
 * per-prefix (HRIS_Counter) — sama seperti seri Cuti, Lembur, Shift, KPI, dan
 * Visitor. Yang dikunci tinggal satu baris counter.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 *   1. tidak ada lagi sp_getapplock untuk tabel itu di mana pun
 *   2. hanya AdminLogNumberService yang membangun prefix LHC
 *   3. bentuk prefix dan panjang urutnya tidak bergeser — kalau bergeser, nomor
 *      baru tidak lagi sebaris dengan nomor lama dan penyemaian counter mulai
 *      dari nol
 *
 * Uji ini tidak menyentuh basis data. Komentar dibuang lewat token_get_all lebih
 * dulu, supaya kalimat penjelasan di atas (yang menyebut sp_getapplock dan LHC)
 * tidak ikut terhitung sebagai kode — kesalahan yang sudah beberapa kali terjadi
 * pada pemindai sebelumnya.
 */
class NomorLogAdminSatuPintuTest extends TestCase
{
    private const PINTU = 'app/Services/SYSTEM/Audit/Numbering/AdminLogNumberService.php';

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

    /** @return array<string, string> jalur relatif => isi tanpa komentar */
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

    public function test_tidak_ada_lagi_applock_untuk_log_admin(): void
    {
        $pemakai = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            /*
             * Hanya applock yang menyangkut tabel ini. VisitorMasterDataService
             * memakai applock untuk keperluan lain yang sah — penjaga yang
             * menyeretnya akan dimatikan orang, dan penjaga yang dimatikan tidak
             * menjaga apa pun.
             */
            if (! str_contains($isi, 'HRIS_Transaksi_Log_Admin')) {
                continue;
            }
            if (stripos($isi, 'sp_getapplock') !== false) {
                $pemakai[] = $rel;
            }
        }

        $this->assertSame([], $pemakai,
            'sp_getapplock muncul lagi. Untuk penomoran, pakai counter atomik per-prefix '
            .'(TransactionNumberService): applock per-bulan membuat semua penulisan dalam '
            .'bulan yang sama antre di satu lock, dipegang sampai transaksi pemanggil commit.');
    }

    public function test_hanya_satu_berkas_yang_membangun_prefix_log_admin(): void
    {
        $pembangun = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            /*
             * Yang dicari bentuk KODE-nya, bukan sekadar kata "LHC": literal
             * 'LHC' yang dirangkai dengan format bulan-tahun.
             */
            if (preg_match("/'LHC'\s*\./", $isi) || preg_match("/'LHC\{/", $isi)) {
                $pembangun[] = $rel;
            }
        }

        $this->assertSame([self::PINTU], $pembangun,
            'Prefix LHC dibangun di lebih dari satu tempat. Semua penulis '
            .'HRIS_Transaksi_Log_Admin harus lewat AdminLogNumberService — dua salinan '
            .'yang "sengaja identik" berhenti identik pada perbaikan pertama yang '
            .'hanya menyentuh satu sisi.');
    }

    public function test_pintu_memakai_counter_atomik_bukan_max_sebagai_nomor(): void
    {
        $isi = $this->tanpaKomentar(base_path(self::PINTU));

        $this->assertStringContainsString('TransactionNumberService', $isi,
            'Pintu penomoran harus lewat counter atomik.');
        $this->assertStringNotContainsString('sp_getapplock', $isi);

        /*
         * MAX/orderBy masih boleh ADA — tetapi hanya di dalam seedResolver, yang
         * jalan sekali per prefix. Yang dilarang adalah mengembalikannya langsung
         * sebagai nomor. Penanda paling jujur: hasil generate() harus berasal dari
         * TransactionNumberService::generate.
         */
        $this->assertMatchesRegularExpression(
            '/return\s+app\(\s*TransactionNumberService::class\s*\)\s*->\s*generate\(/',
            $isi,
            'generate() harus mengembalikan hasil TransactionNumberService::generate.');
    }

    public function test_bentuk_prefix_dan_panjang_urut_tidak_bergeser(): void
    {
        /*
         * 213 nomor lama berbentuk LHC{mm-yy}-{4 digit}, mis. LHC12-25-0010.
         * Kalau bentuk prefix bergeser, penyemaian counter tidak menemukan nomor
         * lama untuk prefix itu dan mulai dari nol — menabrak nomor yang sudah ada.
         */
        $this->assertSame('LHC09-26-',
            AdminLogNumberService::prefix(Carbon::create(2026, 9, 8, 12, 0, 0)));
        $this->assertSame('LHC12-25-',
            AdminLogNumberService::prefix(Carbon::create(2025, 12, 31, 23, 59, 0)));
        $this->assertSame('LHC01-26-',
            AdminLogNumberService::prefix(Carbon::create(2026, 1, 1, 0, 0, 0)));

        $isi = $this->tanpaKomentar(base_path(self::PINTU));
        $this->assertStringContainsString('PANJANG_URUT = 4', $isi,
            'Panjang urut berubah: nomor baru tidak lagi sebaris dengan nomor lama.');
    }

    public function test_middleware_audit_tidak_lagi_punya_generator_sendiri(): void
    {
        $isi = $this->tanpaKomentar(base_path('app/Http/Middleware/AuditAdminTransaction.php'));

        $this->assertStringNotContainsString('generateNoTransaksiWithLock', $isi,
            'Middleware punya generator sendiri lagi.');
        $this->assertStringContainsString('AdminLogNumberService', $isi,
            'Middleware harus memakai pintu tunggal.');
    }
}
