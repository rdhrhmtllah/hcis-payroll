<?php

namespace Tests\Feature\SYSTEM;

use App\Services\SYSTEM\Application\Database\GalatDeadlock;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use Illuminate\Database\QueryException;
use RuntimeException;
use Tests\TestCase;

/**
 * Dua penjaga infrastruktur SQL Server yang tadinya disalin.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 *   insertWithSqlServerLimit()  — dua salinan identik di SystemAuthController
 *                                 dan AdminRoleManagerController
 *   isDeadlockException()       — dua salinan identik di LmsModulAbsensiController
 *                                 dan TrainingExecutionService
 *
 * Keduanya keputusan infrastruktur, dan keduanya gagal dengan cara yang
 * menyamar. Melewati 2.100 parameter tidak memunculkan pesan "terlalu banyak
 * parameter" yang jelas — ia muncul sebagai galat driver. Salah mengenali
 * deadlock punya dua arah gagal yang sama buruknya: terlalu longgar berarti
 * MENGULANG operasi tulis yang seharusnya gagal, terlalu ketat berarti deadlock
 * yang wajar diulang malah sampai ke pengguna.
 *
 * ── Kenapa ukuran potongan dihitung, bukan dikonstanta ───────────────────────
 *
 * Tujuh tempat lain memotong dengan angka tetap, dan ketujuhnya SUDAH BENAR —
 * masing-masing membawa aritmetikanya di komentar (5 kolom x 400 = 2.000;
 * 8-9 kolom x 200 = 1.600-1.800; dan seterusnya). Yang rapuh bukan angkanya
 * melainkan caranya: angka itu benar hanya selama jumlah kolomnya tidak
 * berubah. Satu kolom tambahan pada payload berpotongan 250 menjadikannya
 * 2.250, dan tidak ada yang mengingatkan sampai penyimpanan besar pertama
 * gagal.
 *
 * Uji ini tidak menyentuh basis data.
 */
class InfrastrukturSqlServerSatuRumahTest extends TestCase
{
    private const RUMAH_SISIP = 'app/Services/SYSTEM/Application/Database/PenyisipanBerbatasParameter.php';

    private const RUMAH_DEADLOCK = 'app/Services/SYSTEM/Application/Database/GalatDeadlock.php';

    /**
     * Penyisipan yang masih memotong dengan angka tetap, beserta angkanya.
     *
     * Ketujuhnya diperiksa dan berada di bawah batas. Daftar ini DIBEKUKAN:
     * penyisipan berkonstanta yang BARU membuat uji ini merah, dan yang beralih
     * ke PenyisipanBerbatasParameter juga membuatnya merah sampai barisnya
     * dihapus.
     *
     * @var array<string, int> jalur => jumlah penyisipan berkonstanta di berkas itu
     */
    private const POTONGAN_KONSTANTA = [
        'app/Console/Commands/LMS/SOP/Operations/SopSeedDemo.php' => 2,
        'app/Console/Commands/SYSTEM/Application/Maintenance/DbHealthWatch.php' => 1,
        'app/Http/Controllers/ESS/Attendance/Leave/Personal/CutiController.php' => 1,
        'app/Http/Controllers/SYSTEM/Governance/Authorization/SystemAuthController.php' => 1,
        'app/Jobs/LMS/Training/Scheduling/ProcessBulkPenjadwalan.php' => 1,
        'app/Services/LMS/SOP/SopPenjadwalanService.php' => 1,
    ];

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

    public function test_ukuran_potongan_tidak_pernah_menembus_batas(): void
    {
        /*
         * Sifat yang sebenarnya dijaga, diperiksa untuk SETIAP lebar baris yang
         * masuk akal — bukan sekadar beberapa contoh yang kebetulan lolos.
         */
        for ($kolom = 1; $kolom <= 120; $kolom++) {
            $baris = array_fill(0, 3, array_fill(0, $kolom, 'x'));
            $ukuran = PenyisipanBerbatasParameter::ukuranPotongan($baris);

            $this->assertGreaterThanOrEqual(1, $ukuran,
                "Lebar {$kolom} kolom menghasilkan ukuran potongan nol — perulangan tak berujung.");
            $this->assertLessThanOrEqual(
                PenyisipanBerbatasParameter::BATAS_AMAN,
                $ukuran * $kolom,
                "Lebar {$kolom} kolom x {$ukuran} baris menembus batas aman.");
            $this->assertLessThan(
                PenyisipanBerbatasParameter::BATAS_KERAS,
                $ukuran * $kolom,
                "Lebar {$kolom} kolom x {$ukuran} baris menembus batas keras SQL Server.");
        }
    }

    public function test_ukuran_potongan_sepadan_dengan_perhitungan_tangan_yang_digantikannya(): void
    {
        /*
         * Angka yang selama ini ditulis tangan harus tetap dihormati, bukan
         * diperketat diam-diam: 8 kolom pernah dipotong 250, 5 kolom dipotong 400.
         */
        $delapanKolom = [array_fill(0, 8, 'x')];
        $limaKolom = [array_fill(0, 5, 'x')];

        $this->assertSame(250, PenyisipanBerbatasParameter::ukuranPotongan($delapanKolom));
        $this->assertSame(400, PenyisipanBerbatasParameter::ukuranPotongan($limaKolom));
    }

    public function test_ukuran_potongan_aman_pada_masukan_pinggiran(): void
    {
        $this->assertSame(1, PenyisipanBerbatasParameter::ukuranPotongan([]),
            'Daftar kosong tidak boleh menghasilkan nol.');
        $this->assertSame(2000, PenyisipanBerbatasParameter::ukuranPotongan([['a']]));
        $this->assertSame(2000, PenyisipanBerbatasParameter::ukuranPotongan(['bukan array']),
            'Baris non-array dihitung satu kolom, seperti kode asal.');
        $this->assertSame(2000, PenyisipanBerbatasParameter::ukuranPotongan([[]]),
            'Baris kosong dibulatkan jadi satu kolom — yang penting bukan angkanya '
            .'melainkan bahwa ia tidak nol: intdiv oleh nol akan melempar DivisionByZeroError.');
    }

    public function test_deadlock_dikenali_dari_sqlstate_dan_kode_driver(): void
    {
        $this->assertTrue(GalatDeadlock::adalah($this->kueriGagal(['40001', 1205, 'apa saja'])));
        $this->assertTrue(GalatDeadlock::adalah($this->kueriGagal(['HY000', 1205, 'apa saja'])));
        $this->assertTrue(GalatDeadlock::adalah($this->kueriGagal(['40001', 9999, 'apa saja'])));
    }

    public function test_deadlock_dikenali_dari_pesan_saat_bukan_query_exception(): void
    {
        /*
         * Lapis kedua: kegagalan yang sampai terbungkus ulang oleh lapisan lain
         * tidak lagi berupa QueryException, tetapi pesannya masih membawa
         * penandanya.
         */
        $this->assertTrue(GalatDeadlock::adalah(
            new RuntimeException('Transaction was deadlocked on lock resources')));
        $this->assertTrue(GalatDeadlock::adalah(
            new RuntimeException('SQLSTATE[40001]: Serialization failure')));
        $this->assertTrue(GalatDeadlock::adalah(
            new RuntimeException('driver reported 1205')));
    }

    public function test_kegagalan_biasa_tidak_dikira_deadlock(): void
    {
        /*
         * Sisi yang lebih penting: keliru mengenali berarti MENGULANG operasi
         * tulis yang seharusnya gagal.
         */
        $this->assertFalse(GalatDeadlock::adalah(new RuntimeException('Duplicate key')));
        $this->assertFalse(GalatDeadlock::adalah($this->kueriGagal(['23000', 2601, 'Cannot insert duplicate key'])));
        $this->assertFalse(GalatDeadlock::adalah(new RuntimeException('Login timeout expired')));
    }

    public function test_tidak_ada_lagi_salinan_kedua_penjaga(): void
    {
        $penyalin = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            if (in_array($rel, [self::RUMAH_SISIP, self::RUMAH_DEADLOCK], true)) {
                continue;
            }
            if (preg_match('/function\s+insertWithSqlServerLimit\s*\(/', $isi)) {
                $penyalin[] = $rel.' :: insertWithSqlServerLimit';
            }
            if (preg_match('/function\s+isDeadlockException\s*\(/', $isi)) {
                $penyalin[] = $rel.' :: isDeadlockException';
            }
        }

        $this->assertSame([], $penyalin,
            'Penjaga infrastruktur disalin lagi. Pakai PenyisipanBerbatasParameter '
            .'dan GalatDeadlock: dua salinan berhenti sama pada perbaikan pertama '
            .'yang hanya menyentuh satu sisi.');
    }

    public function test_penyisipan_berpotongan_konstanta_tidak_bertambah(): void
    {
        $nyata = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            if (! preg_match_all('/array_chunk\(\s*([^,]+),\s*(\d+)\s*\)/', $isi, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $x) {
                /*
                 * Hanya potongan yang benar-benar dipakai MENYISIPKAN: potongan
                 * untuk whereIn membatasi jumlah NILAI, bukan jumlah kolom x baris,
                 * dan aritmetikanya berbeda.
                 */
                if (! preg_match('/->\s*(insert|insertGetId|upsert)\s*\(/', substr($isi, $x[0][1], 320))) {
                    continue;
                }
                $nyata[$rel] = ($nyata[$rel] ?? 0) + 1;
            }
        }

        $baru = array_values(array_diff(array_keys($nyata), array_keys(self::POTONGAN_KONSTANTA)));
        $this->assertSame([], $baru,
            'Penyisipan berpotongan KONSTANTA yang baru. Pakai '
            .'PenyisipanBerbatasParameter::sisipkan() supaya aritmetikanya tidak basi '
            .'saat kolomnya bertambah.');

        $sudahBeralih = array_values(array_diff(array_keys(self::POTONGAN_KONSTANTA), array_keys($nyata)));
        $this->assertSame([], $sudahBeralih,
            'Berkas ini sudah tidak memotong dengan konstanta. Hapus barisnya dari '
            .'POTONGAN_KONSTANTA.');

        foreach (self::POTONGAN_KONSTANTA as $rel => $jumlah) {
            $this->assertSame($jumlah, $nyata[$rel] ?? 0,
                "Jumlah penyisipan berkonstanta di {$rel} berubah.");
        }
    }

    /** @param  array{0:string,1:int,2:string}  $errorInfo */
    private function kueriGagal(array $errorInfo): QueryException
    {
        $galat = new \PDOException($errorInfo[2]);
        $galat->errorInfo = $errorInfo;

        return new QueryException('sqlsrv', 'select 1', [], $galat);
    }
}
