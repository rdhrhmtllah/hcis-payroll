<?php

namespace App\Http\Controllers\LMS\SOP\Integration;

use App\Http\Controllers\Controller;
use App\Services\LMS\SecurePdfPageRenderService;
use App\Services\LMS\SOP\SopBerkasService;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Cloud Task untuk render PDF SOP.
 * Dipanggil oleh Google Cloud Tasks (bukan browser) → guard bearer token,
 * TANPA session/CSRF. Route di grup api (routes/api.php).
 *
 * Reuse SecurePdfPageRenderService::renderAllPages() dengan ID ber-OFFSET
 * (SopBerkasService::renderId) supaya path render SOP tak menimpa LMS.
 */
class SopCloudTaskWebhookController extends Controller
{
    public function __construct(private SopBerkasService $berkas) {}

    /** POST /api/v1/tasks/sop/process-pdf */
    public function processPdf(Request $request)
    {
        $expectedToken = config('services.project_config.SCHEDULER_BEARER_TOKEN');
        if ($request->bearerToken() !== $expectedToken) {
            Log::warning('Unauthorized access attempt to SOP Cloud Tasks Webhook');

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        set_time_limit(0);

        $berkasId = (int) $request->input('berkas_id');
        $logId = $request->input('_cloud_task_log_id');

        if (! $berkasId || ! $logId) {
            return response()->json(['message' => 'Missing payloads'], 400);
        }

        try {
            CloudTaskService::markProcessing($logId);

            $row = $this->berkas->raw($berkasId);
            if (! $row) {
                throw new \Exception("Berkas Dokumen ID {$berkasId} tidak ditemukan.");
            }
            if (empty($row->File_Path)) {
                throw new \Exception("Berkas Dokumen ID {$berkasId} tidak punya File_Path.");
            }

            $this->berkas->setRenderStatus($berkasId, 'PROCESSING');

            // reuse render engine LMS, tapi ID di-offset agar path tak tabrakan
            $renderId = $this->berkas->renderId($berkasId);
            $totalPages = (new SecurePdfPageRenderService)->renderAllPages($renderId, $row->File_Path);

            $this->berkas->setRenderStatus($berkasId, 'DONE', $totalPages);
            CloudTaskService::markCompleted($logId, $totalPages, $totalPages, 0, ['pages_rendered' => $totalPages]);

            return response()->json(['success' => true, 'pages' => $totalPages], 200);
        } catch (\Throwable $e) {
            $this->berkas->setRenderStatus($berkasId, 'FAILED', null, $e->getMessage());
            CloudTaskService::markFailed($logId, $e->getMessage());
            Log::error('SOP cloud task processPdf gagal', ['exception' => $e]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
