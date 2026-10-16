<?php

namespace App\Http\Controllers\ESS\Attendance\Processing\Queue;

use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Processing\PerencanaAntreanAbsen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceQueueWorkerController extends Controller
{
    public function process(Request $request)
    {
        $token = $request->header('X-Scheduler-Token');

        if ($token !== config('services.project_config.SCHEDULER_BEARER_TOKEN')) {
            return response()->json(
                [
                    'message' => 'Unauthorized',
                ],
                403,
            );
        }

        $maxQueuePerRun = (int) $request->input('max_queue_per_run', 1);

        if ($maxQueuePerRun < 1) {
            $maxQueuePerRun = 1;
        }

        if ($maxQueuePerRun > 5) {
            $maxQueuePerRun = 5;
        }

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS attendance queue processing', [
                'max_queue_per_run' => $maxQueuePerRun,
            ]);

            $result = DB::select('EXEC dbo.N_HRIS_SP_PROCESS_ABSEN_RECALC_QUEUE @MaxQueuePerRun = ?, @WorkerName = ?', [
                $maxQueuePerRun,
                'cloud-scheduler',
            ]);

            Log::channel('attendance_queue_worker')->info('Finished HRIS attendance queue processing', [
                'processed_count' => count($result),
                'details' => $result,
            ]);

            return response()->json([
                'message' => 'Queue worker executed',
                'max_queue_per_run' => $maxQueuePerRun,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed to process HRIS attendance queue', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(
                [
                    'message' => 'Failed to process queue',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function peopleProcess(Request $request)
    {
        $token = $request->header('X-Scheduler-Token');

        if ($token !== config('services.project_config.SCHEDULER_BEARER_TOKEN')) {
            return response()->json(
                [
                    'message' => 'Unauthorized',
                ],
                403,
            );
        }

        $maxQueuePerRun = (int) $request->input('max_queue_per_run', 1);

        if ($maxQueuePerRun < 1) {
            $maxQueuePerRun = 1;
        }

        if ($maxQueuePerRun > 5) {
            $maxQueuePerRun = 5;
        }

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS attendance queue processing', [
                'max_queue_per_run' => $maxQueuePerRun,
            ]);

            $result = DB::select('EXEC dbo.N_HRIS_SP_PROCESS_ABSEN_RECALC_QUEUE @MaxQueuePerRun = ?, @WorkerName = ?', [
                $maxQueuePerRun,
                'cloud-scheduler',
            ]);

            Log::channel('attendance_queue_worker')->info('Finished HRIS attendance queue processing', [
                'processed_count' => count($result),
                'details' => $result,
            ]);

            return response()->json([
                'message' => 'Queue worker executed',
                'max_queue_per_run' => $maxQueuePerRun,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed to process HRIS attendance queue', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(
                [
                    'message' => 'Failed to process queue',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function planRefresh(Request $request)
    {
        $token = $request->header('X-Scheduler-Token');

        if ($token !== config('services.project_config.SCHEDULER_BEARER_TOKEN')) {
            return response()->json(
                [
                    'message' => 'Unauthorized',
                ],
                403,
            );
        }

        $validated = $request->validate([
            'kode_perusahaan' => 'required|string|max:3',

            'tgl_awal' => 'nullable|date',
            'tgl_akhir' => 'nullable|date',

            'user_id_list' => 'nullable|string',

            'refresh_mode' => 'nullable|string|in:MISSING_ONLY,DIRTY_ONLY,MISSING_OR_DIRTY,FORCE',
            'request_source' => 'nullable|string|max:50',
            'requested_by' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:1|max:9',

            'mode' => 'nullable|string|in:CUSTOM,ROLLING_7_DAYS',
        ]);

        $kodePerusahaan = $validated['kode_perusahaan'];
        $tglAwal = $validated['tgl_awal'] ?? null;
        $tglAkhir = $validated['tgl_akhir'] ?? null;
        $userIdList = $validated['user_id_list'] ?? null;

        $refreshMode = $validated['refresh_mode'] ?? 'MISSING_OR_DIRTY';
        $requestSource = $validated['request_source'] ?? 'SCHEDULER';
        $requestedBy = $validated['requested_by'] ?? 'cloud-scheduler';
        $priority = $validated['priority'] ?? null;
        $mode = $validated['mode'] ?? 'CUSTOM';

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS attendance plan refresh', [
                'kode_perusahaan' => $kodePerusahaan,
                'tgl_awal' => $tglAwal,
                'tgl_akhir' => $tglAkhir,
                'user_id_list' => $userIdList,
                'refresh_mode' => $refreshMode,
                'request_source' => $requestSource,
                'requested_by' => $requestedBy,
                'priority' => $priority,
                'mode' => $mode,
            ]);

            /*
             * Satu pintu ke prosedurnya. Urutan sembilan parameter posisionalnya
             * dulu ditulis di sini DAN di ketiga perintah artisan; tertukarnya
             * sepasang tidak menghasilkan galat, hanya perencanaan yang salah.
             *
             * $priority sengaja boleh null di sini: prosedurnya menurunkan
             * prioritas dari Request_Source bila tidak diberi. Endpoint ini
             * satu-satunya pemakai cabang itu.
             */
            $result = app(PerencanaAntreanAbsen::class)->rencanakan(
                $kodePerusahaan,
                $tglAwal,
                $tglAkhir,
                $userIdList,
                $refreshMode,
                $requestSource,
                $requestedBy,
                $priority,
                $mode,
            );

            Log::channel('attendance_queue_worker')->info('Successfully planned HRIS attendance refresh', [
                'result' => $result,
            ]);

            return response()->json([
                'message' => 'Attendance refresh planned',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed to plan HRIS attendance refresh', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(
                [
                    'message' => 'Failed to plan attendance refresh',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
