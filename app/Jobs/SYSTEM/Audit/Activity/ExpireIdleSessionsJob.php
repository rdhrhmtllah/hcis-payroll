<?php

namespace App\Jobs\SYSTEM\Audit\Activity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireIdleSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $conn = (string) config('activity-tracker.db_connection', config('database.default'));
        $idleMinutes = (int) config('activity-tracker.idle_session_minutes', 30);

        try {
            $affected = DB::connection($conn)->table('N_HRIS_Activity_Session')
                ->whereNull('Logout_At')
                ->where('Last_Activity_At', '<', now()->subMinutes($idleMinutes))
                ->update([
                    'Logout_At' => DB::raw('DATEADD(MINUTE, '.$idleMinutes.', Last_Activity_At)'),
                    'Logout_Type' => 'expired',
                    'Updated_At' => now(),
                ]);

            if ($affected > 0) {
                Log::info('ExpireIdleSessionsJob closed '.$affected.' idle sessions');
            }
        } catch (\Throwable $e) {
            Log::warning('ExpireIdleSessionsJob failed', ['error' => $e->getMessage()]);
        }
    }
}
