<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cloud Task cuti bersama dikirim DI LUAR transaksi ber-lockForUpdate.
 *
 * `execute()` dan `rollback()` dulu memanggil `cloudTaskService->dispatch()`
 * dari dalam `DB::transaction` yang memegang `lockForUpdate` pada
 * `N_HRIS_Cuti_Bersama`. Panggilan itu berujung `createTask()` ke Google —
 * jaringan. Selama Google menjawab, baris cuti bersama tetap terkunci, dan
 * RCSI mati di basis data ini: setiap pembaca baris yang sama ikut menunggu.
 * Itu temuan #18, dan pemindai statis TIDAK bisa melihatnya karena rantainya
 * bersarang tiga lapis.
 *
 * Yang dipisahkan: `catat()` (menyisipkan baris log = KLAIM, tanpa jaringan)
 * boleh dan memang perlu di dalam transaksi — justru kuncinya yang membuat
 * klaim tak bisa kembar. `kirim()` (createTask) wajib sesudah commit.
 *
 * KENAPA DIUJI LEWAT MATA-MATA, BUKAN LEWAT PERINGATAN RUNTIME.
 * `PeringatanJaringanDalamTransaksi` menyatakan pelanggaran bila
 * `DB::transactionLevel() >= 1`. Di dalam uji, `DatabaseTransactions` sudah
 * membuka satu transaksi pembungkus, sehingga ambang itu selalu terpenuhi dan
 * tak bisa membedakan benar dari salah. Mata-mata di bawah mencatat tingkat
 * transaksi TEPAT saat `kirim()` dipanggil, lalu mengadunya dengan garis dasar.
 */
class CutiBersamaTaskDiLuarTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kirim_task_tidak_terjadi_di_dalam_transaksi(): void
    {
        $garisDasar = DB::transactionLevel();
        $tingkatSaatKirim = [];

        /* Mata-mata menggantikan HANYA kirim(); catat() dibiarkan asli supaya
           klaimnya benar-benar tertulis dan alurnya tetap seperti aslinya. */
        $mataMata = new class($tingkatSaatKirim) extends CloudTaskService
        {
            public array $tingkat = [];

            public function __construct(&$penampung)
            {
                parent::__construct();
                $this->tingkat = &$penampung;
            }

            public function kirim(int $logId, ?string $customLocation = null, ?string $customProjectId = null): void
            {
                $this->tingkat[] = DB::transactionLevel();
            }
        };

        $this->app->instance(CloudTaskService::class, $mataMata);

        /* Alih-alih menjalankan seluruh rute — yang menuntut data cuti bersama
           siap-eksekusi dan admin berwenang — sifatnya diperiksa langsung:
           transaksi dibuka, klaim dicatat, transaksi ditutup, baru dikirim.
           Itulah bentuk yang dituntut dari execute() dan rollback(). */
        $logId = null;

        DB::transaction(function () use (&$logId, $mataMata) {
            $logId = $mataMata->catat(
                url: 'https://contoh.test/api/v1/tasks/uji',
                queue: 'cuti-bersama-queue',
                payload: ['uji' => true],
                taskType: 'UJI_TEMUAN_18',
                referenceId: null,
                referenceNo: 'UJI',
                dispatchedBy: 'SYSTEM',
            );
        });

        $mataMata->kirim($logId);

        $this->assertNotEmpty(
            $tingkatSaatKirim,
            'kirim() tak pernah dipanggil; uji ini tak membuktikan apa-apa.'
        );

        $didalam = array_values(array_filter($tingkatSaatKirim, fn ($n) => $n > $garisDasar));

        $this->assertSame(
            [],
            $didalam,
            'kirim() dijalankan selagi transaksi terbuka (tingkat: '.implode(', ', $didalam)
            .', garis dasar '.$garisDasar.').'
        );

        /* Premis: klaimnya harus benar-benar tertulis, kalau tidak yang teruji
           cuma metode yang tak melakukan apa-apa. */
        $this->assertNotNull($logId, 'catat() tidak mengembalikan id log.');
        $this->assertDatabaseHas('N_HRIS_Cloud_Task_Log', ['Id_Log' => $logId, 'Status' => 'DISPATCHED']);
    }

    /**
     * Sisi kedua, dan ini yang menjaga controllernya — bukan cuma servicenya.
     *
     * Pemisahan di CloudTaskService tak ada gunanya bila controllernya tetap
     * memanggil `dispatch()` (yang menggabungkan keduanya) dari dalam
     * transaksi. Penjaga ini membaca berkasnya: di dalam blok
     * `DB::transaction(...)` pada execute() dan rollback() tidak boleh ada
     * pemanggilan yang mengirim.
     */
    public function test_controller_tidak_memanggil_pengirim_di_dalam_transaksi(): void
    {
        $isi = (string) file_get_contents(base_path(
            'app/Http/Controllers/ESS/Attendance/Leave/Admin/CutiBersamaAdvancedController.php'
        ));

        $baris = explode("\n", $isi);
        $kedalaman = 0;
        $didalamTransaksi = false;
        $pelanggaran = [];

        foreach ($baris as $i => $l) {
            $bersih = ltrim($l);
            if (str_starts_with($bersih, '//') || str_starts_with($bersih, '*')) {
                continue;
            }

            if (str_contains($l, 'DB::transaction(')) {
                $didalamTransaksi = true;
                $kedalaman = 0;
            }

            if ($didalamTransaksi) {
                $kedalaman += substr_count($l, '{') - substr_count($l, '}');

                /* Yang dilarang: bentuk yang MENGIRIM. `catat()` justru
                   diharapkan ada di sini. */
                if (str_contains($l, 'cloudTaskService->dispatch(')
                    || str_contains($l, 'cloudTaskService->kirim(')
                    || str_contains($l, 'dispatchExecutionTaskById(')
                    || str_contains($l, 'dispatchRollbackTaskById(')) {
                    $pelanggaran[] = ($i + 1).': '.trim($l);
                }

                if ($kedalaman <= 0 && str_contains($l, '}')) {
                    $didalamTransaksi = false;
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            "Ada pemanggilan yang MENGIRIM task di dalam DB::transaction:\n  "
            .implode("\n  ", $pelanggaran)
            ."\n\nDi dalam transaksi hanya boleh catat()/klaim*; pengirimannya sesudah commit."
        );
    }
}
