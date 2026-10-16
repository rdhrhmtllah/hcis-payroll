<?php

namespace App\Console\Commands\ESS\Attendance\Processing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HrisAbsenProcessQueueCommand extends Command
{
    protected $signature = 'hris:absen-process-queue
        {--max_queue_per_run=3 : Maksimal execution group per run}
        {--max_user_per_exec=20 : Maksimal user per EXEC refresh}';

    protected $description = 'Process HRIS attendance recalculation queue';

    public function handle(): int
    {
        $maxQueuePerRun = (int) $this->option('max_queue_per_run');
        $maxUserPerExec = (int) $this->option('max_user_per_exec');

        if ($maxQueuePerRun < 1) {
            $maxQueuePerRun = 1;
        }

        if ($maxQueuePerRun > 10) {
            $maxQueuePerRun = 10;
        }

        if ($maxUserPerExec < 1) {
            $maxUserPerExec = 20;
        }

        if ($maxUserPerExec > 50) {
            $maxUserPerExec = 50;
        }

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS attendance queue command', [
                'max_queue_per_run' => $maxQueuePerRun,
                'max_user_per_exec' => $maxUserPerExec,
            ]);

            $result = DB::select(
                'EXEC dbo.N_HRIS_SP_PROCESS_ABSEN_RECALC_QUEUE
                    @MaxQueuePerRun = ?,
                    @MaxUserPerExec = ?,
                    @WorkerName = ?',
                [$maxQueuePerRun, $maxUserPerExec, 'laravel-scheduler'],
            );

            Log::channel('attendance_queue_worker')->info('Finished HRIS attendance queue command', [
                'result' => $result,
            ]);

            $this->info('HRIS attendance queue processed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed HRIS attendance queue command', [
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
