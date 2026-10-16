<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class SalinanKurvaTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    public function test_grup_disalin_dan_level_tanpa_grup_jadi_grupnya_sendiri(): void
    {
        [$a, $b] = DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();
        DB::table('N_PA_Curve_Group_Level')->update(['Flag_Aktif' => 'T']);
        DB::table('N_PA_Curve_Group')->update(['Flag_Aktif' => 'T']);
        $g = (int) DB::table('N_PA_Curve_Group')->insertGetId(['Kode_Grup' => 'UJI_G', 'Nama_Grup' => 'Uji'], 'Id_PA_Curve_Group');
        DB::table('N_PA_Curve_Group_Level')->insert(['PA_Curve_Group_Id' => $g, 'ID_Level' => $a]);

        $cycleId = $this->siklusLembar('OPEN');
        $n = app(PaCyclePreparationService::class)->salinGrupKurva($cycleId, null);

        $peta = DB::table('N_PA_Cycle_Curve_Group_Level as l')
            ->join('N_PA_Cycle_Curve_Group as g', 'g.Id_PA_Cycle_Curve_Group', '=', 'l.PA_Cycle_Curve_Group_Id')
            ->where('l.PA_Cycle_Id', $cycleId)->where('l.Flag_Aktif', 'Y')
            ->pluck('g.Kode_Grup', 'l.ID_Level');

        $this->assertSame('UJI_G', $peta[$a]);
        $this->assertSame('LV'.$b, $peta[$b]);
        // Sejak Master Level PA (#693) yang disalin adalah N_PA_Level aktif — termasuk level
        // khusus PA seperti Officer Specialist — bukan HRIS_Level.
        $this->assertSame(DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')->count(), $peta->count(), 'Setiap level wajib punya grup di siklus.');
        $this->assertSame(0, app(PaCyclePreparationService::class)->salinGrupKurva($cycleId, null), 'Salinan kedua harus no-op.');
        $this->assertGreaterThan(0, $n);
    }
}
