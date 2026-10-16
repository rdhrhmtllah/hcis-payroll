<?php

namespace App\Jobs\LMS\Training\Export;

use App\Services\LMS\LmsTrainingExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * GenerateLmsTrainingExportJob
 *
 * Render file export data training LMS (Excel/CSV/PDF) di background via Cloud
 * Tasks. Progress & link download dilacak di N_HRIS_Export_Jobs (status ready
 * saat file siap). Dataset berlapis bisa berat -> async agar tidak timeout.
 */
class GenerateLmsTrainingExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;

    // Sekali jalan: gagal -> langsung failed (lewat failed()), tanpa retry otomatis.
    // User memicu ulang manual via "Coba lagi" (hapus job gagal -> dispatch baru).
    public $tries = 1;

    public function __construct(
        protected string $exportId,
    ) {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(LmsTrainingExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        // Tandai job gagal di tabel export agar muncul status "gagal" + tombol
        // "Coba lagi" di panel (tidak menggantung di progress).
        try {
            app(LmsTrainingExportService::class)->markFailed(
                $this->exportId,
                'Export gagal diproses (data terlalu besar atau timeout). Coba lagi atau gunakan filter.',
            );
        } catch (\Throwable $ignored) {
            // jangan biarkan handler gagal menutupi error asli
        }

        Log::channel('UpdatePerformanceController')->error(
            'GenerateLmsTrainingExportJob gagal permanen: '.$e->getMessage(),
            ['export_id' => $this->exportId, 'exception' => $e],
        );
    }
}
