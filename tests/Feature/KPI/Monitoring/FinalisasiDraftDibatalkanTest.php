<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Draft yang dibatalkan admin tidak boleh terkirim dari halaman yang masih
 * terbuka di browser karyawan.
 *
 * Finalisasi dulu hanya memeriksa Flag_Pindah, sehingga draft dengan
 * Flag_Aktif='T' — dibatalkan langsung dari monitoring atau ikut batal
 * bersama pengajuan parameternya — tetap bisa jadi transaksi final.
 *
 * Ketiga jalur (mandiri, oleh tim, by-pass) diuji terpisah karena masing-
 * masing punya salinan pemeriksaannya sendiri.
 */
class FinalisasiDraftDibatalkanTest extends TestCase
{
    use DatabaseTransactions;

    private function drafDibatalkan(): object
    {
        $header = DB::table('KPI_Update_Performance_Sementara as s')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 's.Id_User')
            ->orderByDesc('s.Id_Update_Performance_Sementara')
            ->first(['s.Id_Update_Performance_Sementara', 's.Id_User', 's.Id_User_Sender', 'k.Kode_Karyawan']);

        if (! $header) {
            $this->markTestSkipped('Tidak ada header draft berkaryawan di data dev.');
        }

        DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Update_Performance_Sementara', $header->Id_Update_Performance_Sementara)
            ->update([
                'Flag_Aktif' => 'T',
                'Flag_Pindah' => null,
                'Id_User_Sender' => $header->Id_User_Sender ?? $header->Id_User,
                'Cancelled_At' => now(),
                'Cancel_Batch_Id' => 'CNL-UJI',
            ]);

        $header->Id_User_Sender = $header->Id_User_Sender ?? $header->Id_User;

        return $header;
    }

    private function panggil(string $metode, array $argumen): void
    {
        $controller = app(UpdatePerformanceController::class);
        $ref = new \ReflectionMethod($controller, $metode);
        $ref->setAccessible(true);
        $ref->invokeArgs($controller, $argumen);
    }

    private function jumlahFinal(): int
    {
        return DB::table('KPI_Update_Performance')->count();
    }

    public static function jalurFinalisasi(): array
    {
        return [
            'mandiri' => ['processPerformanceTransaction'],
            'oleh tim' => ['processPerformanceTransactionByTeam'],
            'by-pass' => ['processPerformanceTransactionByPass'],
        ];
    }

    /** @dataProvider jalurFinalisasi */
    public function test_draft_yang_dibatalkan_tidak_bisa_difinalisasi(string $metode): void
    {
        $h = $this->drafDibatalkan();
        $karyawan = (object) ['Kode_Karyawan' => $h->Kode_Karyawan];
        $sebelum = $this->jumlahFinal();

        $argumen = $metode === 'processPerformanceTransaction'
            ? [(int) $h->Id_Update_Performance_Sementara, (object) ['Id_Users' => $h->Id_User], $karyawan]
            : [(int) $h->Id_Update_Performance_Sementara, $h->Id_User, (object) ['Id_Users' => $h->Id_User_Sender], $karyawan];

        try {
            $this->panggil($metode, $argumen);
            $this->fail('Finalisasi draft yang dibatalkan seharusnya ditolak.');
        } catch (\Exception $e) {
            $this->assertStringContainsString(
                'sudah dibatalkan admin',
                $e->getMessage(),
                'Penolakan harus datang dari penjaga pembatalan, bukan dari alasan lain.',
            );
        }

        $this->assertSame($sebelum, $this->jumlahFinal(), 'Tidak boleh ada transaksi final yang lahir.');
        $this->assertNull(
            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $h->Id_Update_Performance_Sementara)
                ->value('Flag_Pindah'),
            'Draft yang ditolak tidak boleh ditandai sudah dipindah.',
        );
    }
}
