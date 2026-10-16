<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\ESS\Activity\Center\ActivityCenterController;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Activity\Center\ActivityCenterService;
use App\Services\KPI\KpiEnforcementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Eksekusi Auto-Cap KPI terhubung ke panel Tugas & Aktivitas (Activity Center) global.
 */
class KpiAutoCapActivityCenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_batch_auto_cap_tercatat_di_panel_tugas_dan_aktivitas(): void
    {
        $admin = User::first();
        if (! $admin) {
            $this->markTestSkipped('Butuh akun user untuk pengujian.');
        }

        $kodeKaryawan = optional($admin->karyawan)->Kode_Karyawan ?: 'ADM-01';

        $service = app(KpiEnforcementService::class);
        $batchId = $service->makeBatchId();

        $service->startBatch($batchId, '2026-07-01', 10, $kodeKaryawan, ['periode' => '2026-07-01']);

        // Verifikasi tercatat di N_HRIS_Async_Tasks
        $asyncTask = DB::table('N_HRIS_Async_Tasks')
            ->where('Export_Id', $batchId)
            ->where('Task_Type', KpiEnforcementService::ASYNC_TASK_TYPE)
            ->first();

        $this->assertNotNull($asyncTask, 'Task tidak tercatat di N_HRIS_Async_Tasks.');
        $this->assertSame('queued', $asyncTask->Status);

        // Verifikasi terbaca oleh ActivityCenterService
        $activitySvc = app(ActivityCenterService::class);
        $items = $activitySvc->forUser($kodeKaryawan)->list(50);
        $item = collect($items)->firstWhere('id', $batchId);

        $this->assertNotNull($item, 'Batch auto-cap tidak muncul di list() ActivityCenterService.');
        $this->assertSame('kpi-auto-cap', $item['type']);
        $this->assertStringContainsString('Auto-Cap KPI', $item['label']);

        // Verifikasi markProcessing
        $service->markProcessing($batchId);
        $asyncTaskFresh = DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batchId)->first();
        $this->assertSame('processing', $asyncTaskFresh->Status);

        // Verifikasi dismiss via ActivityCenterController
        Auth::setUser($admin);
        $controller = app(ActivityCenterController::class);
        $res = $controller->dismiss('kpi-auto-cap', $batchId);
        $this->assertSame(200, $res->getStatusCode());

        $asyncTaskDismissed = DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batchId)->first();
        $this->assertNotNull($asyncTaskDismissed->Deleted_At, 'Deleted_At tidak terisi setelah di-dismiss.');
    }

    public function test_tombol_panel_untuk_batch_berjalan_adalah_batalkan_dan_benar_benar_menghentikan(): void
    {
        // 12 Okt 2026: tombol X panel hanya menyembunyikan task, batch tetap jalan.
        $idUser = DB::table('KPI_Users as u')->join('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
            ->where('k.Aktif', 'Y')->value('u.Id_Users');
        $admin = $idUser ? User::find($idUser) : null;
        if (! $admin || ! optional($admin->karyawan)->Kode_Karyawan) {
            $this->markTestSkipped('Butuh akun yang tertaut karyawan.');
        }
        $kode = $admin->karyawan->Kode_Karyawan;

        $service = app(KpiEnforcementService::class);
        $batchId = $service->makeBatchId();
        $service->startBatch($batchId, '2026-07-01', 3, $kode, []);

        $item = collect(app(ActivityCenterService::class)->forUser($kode)->list(50))->firstWhere('id', $batchId);
        $this->assertNotNull($item['abort_url'] ?? null, 'Batch antre tak menawarkan Batalkan — tombolnya hanya menyembunyikan.');

        Auth::setUser($admin);
        $res = app(ActivityCenterController::class)->abort('kpi-auto-cap', $batchId);
        $this->assertSame(200, $res->getStatusCode(), $res->getContent());

        $this->assertTrue($service->batchDibatalkan($batchId), 'Batch tidak berstatus cancelled setelah dibatalkan.');
        $this->assertSame(
            KpiEnforcementService::STATUS_CANCELLED,
            DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batchId)->value('Status'),
        );
    }

    public function test_kiriman_ganda_satu_karyawan_dihitung_sekali(): void
    {
        // Cloud Tasks mengulang job yang sudah selesai bila pengirimnya timeout.
        $service = app(KpiEnforcementService::class);
        $batchId = $service->makeBatchId();
        $service->startBatch($batchId, '2026-07-01', 2, 'UJI-GANDA', []);

        $catat = new \ReflectionMethod($service, 'recordDetailAndIncrement');
        $catat->setAccessible(true);
        $catat->invoke($service, $batchId, 901, 'K901', 'AKPI-UJI-1', 'SUCCESS', null);
        $catat->invoke($service, $batchId, 901, 'K901', null, 'SUCCESS', 'Sudah memiliki performance aktif (skip).');

        $log = DB::table(KpiEnforcementService::LOG_TABLE)->where('Batch_Id', $batchId)->first();
        $this->assertSame(1, (int) $log->Processed_Records, 'Kiriman ulang untuk karyawan yang sama ikut dihitung.');
        $this->assertNotSame(KpiEnforcementService::STATUS_COMPLETED, $log->Status,
            'Batch 2 orang ditandai selesai padahal baru 1 karyawan yang diproses.');
    }

    public function test_job_anak_batch_dibatalkan_keluar_tanpa_memproses(): void
    {
        // Task Cloud Tasks yang sudah antre tak bisa dihapus dari aplikasi (izin
        // akun layanan), jadi job-nya sendiri yang wajib berhenti.
        $service = app(KpiEnforcementService::class);
        $batchId = $service->makeBatchId();
        $service->startBatch($batchId, '2026-07-01', 2, 'UJI-BATAL', []);
        $service->batalkanBatch($batchId, 'UJI-BATAL');

        $idUser = (int) DB::table('Karyawan')->where('Aktif', 'Y')->value('UserID_Web');
        (new \App\Jobs\KPI\Monitoring\Enforcement\EnforceKpiCapJob($batchId, $idUser, '2026-07-01'))->handle($service);

        $this->assertSame(0, DB::table(KpiEnforcementService::LOG_DETAIL_TABLE)->where('Batch_Id', $batchId)->count(),
            'Job anak tetap memproses karyawan padahal batch sudah dibatalkan.');
        $this->assertTrue($service->batchDibatalkan($batchId), 'Status cancelled tertimpa.');
    }
}
