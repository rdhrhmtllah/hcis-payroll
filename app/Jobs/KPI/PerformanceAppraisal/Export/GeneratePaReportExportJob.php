<?php

namespace App\Jobs\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePaReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public function __construct(protected string $exportId)
    {
        $this->onConnection('cloudtasks');
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(PaReportExportService $service): void
    {
        $service->processOrFail($this->exportId);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(PaReportExportService::class)->markFailed(
                $this->exportId,
                'Laporan Performance Appraisal gagal diproses. Coba lagi.'
            );
        } catch (\Throwable $statusError) {
            Log::warning('PA_REPORT_EXPORT_FAILED_STATUS_UPDATE_FAILED', [
                'export_id' => $this->exportId,
                'exception' => $statusError,
            ]);
        }

        Log::error('PA_REPORT_EXPORT_FAILED', [
            'export_id' => $this->exportId,
            'exception' => $e,
        ]);
    }
}
