<?php

namespace App\Jobs\ESS\Dashboard\Personal;

use App\Services\ESS\Dashboard\Homepage\HomepagePersonalDashboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generate/sync notifikasi "Perlu Tindakan" (KPI/LMS/submission/absensi) di
 * BACKGROUND, bukan di request user.
 *
 * Sebelumnya sync ini berjalan INLINE di homepage/personal/needs-action (page 1):
 * ~20-75 query + beberapa detik per user (getKpiSummary ~20 query, getSubmissionItems
 * union-9-view, recheck absensi, tulis notif). Saat rush pagi, ratusan request
 * serentak menjalankan ini → CPU Cloud SQL 100% → "Login timeout expired".
 *
 * Dengan lewat queue:
 *  - Request user cukup MEMBACA notifikasi yang sudah ada → ringan & cepat.
 *  - Concurrency queue MEMBATASI berapa sync jalan serentak (backpressure) →
 *    meredam lonjakan CPU saat rush (kerja disebar, bukan menumpuk).
 *  - Di-dispatch hanya saat throttle (5 menit/user) habis, dan ditandai
 *    (markNeedsActionSynced) sebelum dispatch agar tidak dobel.
 */
class SyncPersonalNeedsActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $kodeKaryawan,
        public string $userIdAbsen,
        public ?int $kpiYear = null,
    ) {}

    public function handle(HomepagePersonalDashboardService $service): void
    {
        try {
            $service->syncPersonalDashboardNeedsActionCache(
                $this->kodeKaryawan,
                $this->userIdAbsen,
                $this->kpiYear,
            );
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->warning('SyncPersonalNeedsActionJob gagal: '.$e->getMessage(), [
                'kode_karyawan' => $this->kodeKaryawan,
            ]);

            throw $e; // lempar ulang agar di-retry (tries=3)
        }
    }
}
