<?php

namespace App\Jobs\ESS\Dashboard\Leadership;

use App\Services\ESS\Dashboard\Homepage\HomepageLeaderDashboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sync penuh notifikasi needs-action leader dashboard (HCIS full re-scan + KPI +
 * Approval) untuk satu leader, dijalankan di background supaya endpoint
 * /needs-action tetap ringan (cache-first). Endpoint hanya membaca notif cache dan
 * men-dispatch job ini (throttled + deduped) untuk me-refresh data di belakang.
 *
 * ShouldBeUnique mencegah penumpukan job untuk leader+tahun yang sama selama job
 * sebelumnya belum selesai (dedup tambahan di sisi dispatch via cache lock).
 */
class LeaderDashboardSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    // Beberapa kali percobaan agar error sementara (mis. DB timeout) bisa pulih,
    // tanpa retry tak terbatas. Cloud Tasks akan menjadwalkan ulang sesuai retryConfig
    // queue selama job melempar exception (tidak menelannya).
    public $tries = 3;

    public function __construct(
        protected string $leaderKode,
        protected int $kpiYear,
    ) {
        // Isolasi ke queue khusus agar sync berat ini tidak bersaing dengan job lain
        // di queue "default". Nama queue harus cocok dengan queue di Cloud Tasks.
        $this->onQueue((string) config('queue.names.leader_dashboard'));
    }

    public function uniqueId(): string
    {
        return $this->leaderKode.'|'.$this->kpiYear;
    }

    public function handle(HomepageLeaderDashboardService $service): void
    {
        // Sengaja TIDAK menelan exception: biarkan naik supaya (1) Cloud Tasks tahu
        // job gagal lalu retry, dan (2) setelah retry habis job tercatat di
        // N_LMS_Failed_Jobs (lihat failed()). Sync ini idempotent, jadi retry aman.
        $service->runLeaderDashboardSync($this->leaderKode, $this->kpiYear);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('LeaderDashboardSyncJob gagal permanen (semua retry habis): '.$e->getMessage(), [
            'leader_kode' => $this->leaderKode,
            'kpi_year' => $this->kpiYear,
            'exception' => $e,
        ]);
    }
}
