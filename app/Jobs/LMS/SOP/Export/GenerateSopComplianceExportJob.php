<?php

namespace App\Jobs\LMS\SOP\Export;

use App\Jobs\LMS\Training\Export\GenerateLmsTrainingExportJob;
use App\Services\LMS\SOP\SopComplianceExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Render file export SOP (Kartu Kepatuhan / Coverage) di background via Cloud
 * Tasks. Progress & link download dilacak di N_HRIS_Async_Tasks (status ready
 * saat file siap). Mirror GenerateLmsTrainingExportJob.
 */
class GenerateSopComplianceExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    // Retry terbatas untuk timeout/provider sementara; failed() menutup status
    // setelah seluruh percobaan habis.
    public $tries = 3;

    /** @return int[] */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function __construct(protected string $exportId)
    {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(SopComplianceExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(SopComplianceExportService::class)->markFailed(
                $this->exportId,
                'Export gagal diproses. Coba lagi.',
            );
        } catch (\Throwable $ignored) {
            // jangan biarkan handler gagal menutupi error asli
        }

        Log::error('GenerateSopComplianceExportJob gagal permanen', [
            'export_id' => $this->exportId,
            'exception' => $e,
        ]);
    }
}
