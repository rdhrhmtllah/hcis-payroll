<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

class BalancingKurvaSimulasiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    public function test_simulasi_sama_dengan_kunci_ulang_sungguhan(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [50, 55, 60, 65, 70, 75, 80]]);
        $kurva = app(PaKurvaService::class);
        $kurva->kunci($s['cycle_id'], 7);
        [$p0, , , , , , $p6] = $s['peserta'][$level];

        $sim = $kurva->simulasikanGrade($s['cycle_id'], [$p0 => 72.0, $p6 => 95.0]);

        // Jalur sungguhan: buka → tulis Final baru → kunci.
        $kurva->buka($s['cycle_id'], 'Uji simulasi', 7);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $p0)->update(['Final_Score' => 72.0]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $p6)->update(['Final_Score' => 95.0]);
        $kurva->kunci($s['cycle_id'], 7);

        foreach ($s['peserta'][$level] as $pid) {
            $this->assertSame(
                DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->value('Kode_Grade_Sistem'),
                $sim['grade'][$pid],
                "Grade simulasi peserta {$pid} berbeda dari hasil kunci ulang.",
            );
        }
    }

    public function test_simulasi_tidak_menulis(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [50, 60, 70, 80, 90]]);
        $kurva = app(PaKurvaService::class);
        $kurva->kunci($s['cycle_id'], 7);
        $sebelum = DB::table('N_PA_Result')->whereIn('PA_Participant_Id', $s['peserta'][$level])->orderBy('Id_PA_Result')->get();

        $kurva->simulasikanGrade($s['cycle_id'], [$s['peserta'][$level][0] => 99.0]);

        $this->assertEquals($sebelum, DB::table('N_PA_Result')->whereIn('PA_Participant_Id', $s['peserta'][$level])->orderBy('Id_PA_Result')->get());
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->count());
    }
}
