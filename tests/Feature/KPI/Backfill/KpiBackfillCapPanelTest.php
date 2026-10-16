<?php

namespace Tests\Feature\KPI\Backfill;

use App\Http\Controllers\ESS\Activity\Center\ActivityCenterController;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Activity\Center\ActivityCenterService;
use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Batch backfill cap tampil di panel Tugas & Aktivitas peminta (requested_by). */
class KpiBackfillCapPanelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_batch_tampil_di_panel_peminta_dengan_progres(): void
    {
        $svc = app(Svc::class);
        $batch = $svc->mulai('jalankan', 'UJI-PANEL');
        $svc->setelTotal($batch, 4);

        $item = collect(app(ActivityCenterService::class)->forUser('UJI-PANEL')->list(50))->firstWhere('id', $batch);

        $this->assertNotNull($item, 'Batch backfill tidak tampil di panel Tugas & Aktivitas.');
        $this->assertSame('kpi-backfill-cap', $item['type']);
        $this->assertStringContainsString('Backfill Cap KPI', $item['label']);
        $this->assertStringContainsString('4 header', $item['label']);
        $this->assertSame(1, app(ActivityCenterService::class)->forUser('UJI-PANEL')->summary()['active_count']);
    }

    public function test_batch_bisa_disembunyikan_dari_panel(): void
    {
        $idUser = DB::table('KPI_Users as u')->join('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
            ->where('k.Aktif', 'Y')->value('u.Id_Users');
        $user = $idUser ? User::find($idUser) : null;
        if (! $user || ! optional($user->karyawan)->Kode_Karyawan) {
            $this->markTestSkipped('Butuh akun yang tertaut karyawan.');
        }
        $batch = app(Svc::class)->mulai('jalankan', $user->karyawan->Kode_Karyawan);
        app(Svc::class)->setelTotal($batch, 0);   // selesai → boleh disembunyikan

        Auth::setUser($user);
        $res = app(ActivityCenterController::class)->dismiss('kpi-backfill-cap', $batch);

        $this->assertSame(200, $res->getStatusCode(), $res->getContent());
        $this->assertNotNull(DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Deleted_At'));
    }
}
