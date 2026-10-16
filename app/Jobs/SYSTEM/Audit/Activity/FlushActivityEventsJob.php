<?php

namespace App\Jobs\SYSTEM\Audit\Activity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlushActivityEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  array<int, array<string, mixed>>  $events  setiap entri = row N_HRIS_Activity_Event
     * @param  string|null  $sessionId  untuk update counter sesi
     */
    public function __construct(public array $events, public ?string $sessionId = null) {}

    public function handle(): void
    {
        if (empty($this->events)) {
            return;
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));
        $batch = (int) (config('activity-tracker.flush_batch_size') ?: 200);

        // Bulk insert events (batch, untuk hindari batas 1000-row SQL Server)
        $totalEvents = 0;
        $totalPageviews = 0;

        foreach (array_chunk($this->events, $batch) as $chunk) {
            try {
                DB::connection($conn)->table('N_HRIS_Activity_Event')->insert($chunk);
                $totalEvents += count($chunk);
                foreach ($chunk as $row) {
                    if (($row['Event_Type'] ?? null) === 'pageview') {
                        $totalPageviews++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('FlushActivityEventsJob bulk insert failed', [
                    'error' => $e->getMessage(),
                    'count' => count($chunk),
                ]);
                throw $e;
            }
        }

        // Update counter sesi (kalau ada Session_Id)
        if ($this->sessionId && $totalEvents > 0) {
            try {
                DB::connection($conn)->table('N_HRIS_Activity_Session')
                    ->where('Session_Id', $this->sessionId)
                    ->update([
                        'Total_Events' => DB::raw('Total_Events + '.(int) $totalEvents),
                        'Total_Pageviews' => DB::raw('Total_Pageviews + '.(int) $totalPageviews),
                        'Last_Activity_At' => now(),
                        'Updated_At' => now(),
                    ]);
            } catch (\Throwable $e) {
                Log::warning('FlushActivityEventsJob session counter update failed', [
                    'error' => $e->getMessage(),
                    'session_id' => $this->sessionId,
                ]);
            }
        }

        // Trigger geo lookup untuk IP baru (best-effort, jangan blok)
        try {
            $ips = array_values(array_unique(array_filter(array_column($this->events, 'Ip_Address'))));
            if (! empty($ips)) {
                $existing = DB::connection($conn)->table('N_HRIS_Activity_Geo_Cache')
                    ->whereIn('Ip_Address', $ips)
                    ->pluck('Ip_Address')
                    ->all();
                $missing = array_diff($ips, $existing);
                foreach ($missing as $ip) {
                    GeoLookupJob::dispatch($ip)
                        ->onConnection((string) config('activity-tracker.queue.connection'))
                        ->onQueue((string) config('activity-tracker.queue.name'));
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Geo lookup dispatch skipped', ['error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FlushActivityEventsJob ultimately failed', [
            'error' => $e->getMessage(),
            'count' => count($this->events),
        ]);
    }
}
