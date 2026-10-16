<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class KurvaPerhitunganTest extends TestCase
{
    use DatabaseTransactions;

    public function test_penerbitan_menolak_siklus_kurva_yang_belum_dikunci(): void
    {
        $cycleId = (int) DB::table('N_PA_Cycle')->where('Status_Cycle', 'CLOSED')->value('Id_PA_Cycle');

        if ($cycleId === 0) {
            $this->markTestSkipped('Tidak ada siklus CLOSED di dev.');
        }

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Metode_Grade' => 'KURVA']);
        DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->update(['Flag_Aktif' => 'T', 'Unlocked_At' => now(), 'Alasan_Buka' => 'uji']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kunci kurva');
        app(PaResultPublishService::class)->terbitkan($cycleId, null);
    }

    public function test_perhitungan_siklus_pita_tidak_menyentuh_kurva(): void
    {
        $this->assertSame(['kode' => null, 'curve_id' => null, 'alasan' => null], app(PaKurvaService::class)->gradeBeku(
            (int) DB::table('N_PA_Participant as p')->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
                ->where('c.Metode_Grade', 'PITA')->value('p.Id_PA_Participant'),
            80.0,
        ));
    }

    public function test_hitung_memanggil_grade_beku_pada_siklus_kurva(): void
    {
        $sumber = file_get_contents(base_path('app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php'));

        // Penjaga struktural: jalur KURVA harus lewat gradeBeku, bukan cariPita.
        $this->assertStringContainsString('$this->kurva->gradeBeku(', $sumber);
        $this->assertStringContainsString("'PA_Cycle_Curve_Id' =>", $sumber);
        // Peserta tanpa kurva beku DITAHAN, bukan dilempar.
        $this->assertStringContainsString("\$tertahan = \$beku['alasan'];", $sumber);
    }
}
