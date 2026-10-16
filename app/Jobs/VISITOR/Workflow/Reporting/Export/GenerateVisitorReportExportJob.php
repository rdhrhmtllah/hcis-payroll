<?php

namespace App\Jobs\VISITOR\Workflow\Reporting\Export;

use App\Services\VISITOR\Workflow\Reporting\Export\VisitorReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVisitorReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public function __construct(protected string $exportId)
    {
        // Sama seperti PublishVisitorNotificationsJob: 'sync' selalu pilihan
        // sadar, dan memaksanya ke cloudtasks (satu-satunya koneksi
        // ber-`after_commit`) membuat job menunggu commit yang tak pernah
        // datang di dalam uji bertransaksi.
        $koneksi = (string) config('queue.default');
        $this->onConnection($koneksi === 'sync' ? 'sync' : 'cloudtasks');
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(VisitorReportExportService $service): void
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
            app(VisitorReportExportService::class)->markFailed(
                $this->exportId,
                'Laporan Visitor gagal diproses. Coba lagi.'
            );
        } catch (\Throwable $statusError) {
            Log::warning('VISITOR_REPORT_EXPORT_FAILED_STATUS_UPDATE_FAILED', [
                'export_id' => $this->exportId,
                'exception' => $statusError,
            ]);
        }

        Log::error('VISITOR_REPORT_EXPORT_FAILED', [
            'export_id' => $this->exportId,
            'exception' => $e,
        ]);
    }
}
