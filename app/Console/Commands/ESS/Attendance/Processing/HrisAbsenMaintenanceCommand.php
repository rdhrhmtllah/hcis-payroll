<?php

namespace App\Console\Commands\ESS\Attendance\Processing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HrisAbsenMaintenanceCommand extends Command
{
    protected $signature = 'hris:absen-maintenance
        {--processing_timeout_minutes=15 : Menit maksimal queue/snapshot boleh processing}
        {--max_retry_attempt=3 : Maksimal retry attempt}
        {--auto_retry_error=1 : 1 auto retry ERROR, 0 tidak}
        {--cleanup_done=0 : 1 cleanup queue DONE lama, 0 tidak}
        {--cleanup_done_older_than_days=30 : Hapus DONE lebih lama dari N hari}';

    protected $description = 'Run HRIS attendance queue and snapshot maintenance';

    public function handle(): int
    {
        $processingTimeoutMinutes = (int) $this->option('processing_timeout_minutes');
        $maxRetryAttempt = (int) $this->option('max_retry_attempt');
        $autoRetryError = (int) $this->option('auto_retry_error');
        $cleanupDone = (int) $this->option('cleanup_done');
        $cleanupDoneOlderThanDays = (int) $this->option('cleanup_done_older_than_days');

        if ($processingTimeoutMinutes < 5) {
            $processingTimeoutMinutes = 5;
        }

        if ($processingTimeoutMinutes > 120) {
            $processingTimeoutMinutes = 120;
        }

        if ($maxRetryAttempt < 1) {
            $maxRetryAttempt = 3;
        }

        if ($maxRetryAttempt > 10) {
            $maxRetryAttempt = 10;
        }

        if (! in_array($autoRetryError, [0, 1], true)) {
            $autoRetryError = 1;
        }

        if (! in_array($cleanupDone, [0, 1], true)) {
            $cleanupDone = 0;
        }

        if ($cleanupDoneOlderThanDays < 7) {
            $cleanupDoneOlderThanDays = 30;
        }

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS attendance maintenance command', [
                'processing_timeout_minutes' => $processingTimeoutMinutes,
                'max_retry_attempt' => $maxRetryAttempt,
                'auto_retry_error' => $autoRetryError,
                'cleanup_done' => $cleanupDone,
                'cleanup_done_older_than_days' => $cleanupDoneOlderThanDays,
            ]);

            $result = DB::select(
                'EXEC dbo.N_HRIS_SP_ABSEN_MAINTENANCE
                    @ProcessingTimeoutMinutes = ?,
                    @MaxRetryAttempt = ?,
                    @AutoRetryError = ?,
                    @CleanupDone = ?,
                    @CleanupDoneOlderThanDays = ?,
                    @MaintenanceBy = ?',
                [
                    $processingTimeoutMinutes,
                    $maxRetryAttempt,
                    $autoRetryError,
                    $cleanupDone,
                    $cleanupDoneOlderThanDays,
                    'laravel-scheduler',
                ],
            );

            Log::channel('attendance_queue_worker')->info('Finished HRIS attendance maintenance command', [
                'result' => $result,
            ]);

            $this->info('HRIS attendance maintenance executed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed HRIS attendance maintenance command', [
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
