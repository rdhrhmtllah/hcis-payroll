<?php

namespace App\Jobs\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Render berkas laporan Export Studio di background via Cloud Tasks.
 *
 * Kegagalan sementara dicoba kembali dengan backoff. Klaim atomik pada service
 * mencegah delivery Cloud Tasks yang tumpang tindih merender file yang sama.
 */
class GenerateSopReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

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

    public function handle(SopReportExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(SopReportExportService::class)->markFailed(
                $this->exportId,
                'Laporan gagal diproses. Coba lagi.',
            );
        } catch (\Throwable $ignored) {
            // jangan biarkan handler gagal menutupi error asli
        }

        Log::error('GenerateSopReportExportJob gagal permanen', [
            'export_id' => $this->exportId,
            'exception' => $e,
        ]);
    }
}
