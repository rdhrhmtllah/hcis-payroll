<?php

namespace App\Console\Commands\SYSTEM\Audit\Activity;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneActivity extends Command
{
    protected $signature = 'activity:prune
        {--event-days= : Override retensi event (hari)}
        {--session-days= : Override retensi session (hari)}
        {--daily-days= : Override retensi daily rollup (hari)}
        {--batch=5000 : Batch size delete}';

    protected $description = 'Hapus event/session/daily yang melewati masa retensi sesuai konfigurasi.';

    public function handle(): int
    {
        $conn = (string) config('activity-tracker.db_connection', config('database.default'));
        $retention = (array) config('activity-tracker.retention_days', []);
        $batch = (int) $this->option('batch') ?: 5000;

        $eventDays = (int) ($this->option('event-days') ?? $retention['event'] ?? 365);
        $sessionDays = (int) ($this->option('session-days') ?? $retention['session'] ?? 365);
        $dailyDays = (int) ($this->option('daily-days') ?? $retention['daily'] ?? 1095);

        try {
            $this->info("Pruning events > {$eventDays} days …");
            DB::connection($conn)->statement(
                'EXEC dbo.sp_NHRIS_Activity_Prune_Events @KeepDays = ?, @BatchSize = ?',
                [$eventDays, $batch],
            );

            $this->info("Pruning sessions > {$sessionDays} days …");
            DB::connection($conn)->statement(
                'EXEC dbo.sp_NHRIS_Activity_Prune_Sessions @KeepDays = ?, @BatchSize = ?',
                [$sessionDays, $batch],
            );

            $this->info("Pruning daily rollup > {$dailyDays} days …");
            DB::connection($conn)->statement(
                'EXEC dbo.sp_NHRIS_Activity_Prune_Daily @KeepDays = ?, @BatchSize = ?',
                [$dailyDays, $batch],
            );

            $this->info('Done.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('PruneActivity failed', ['error' => $e->getMessage()]);
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
