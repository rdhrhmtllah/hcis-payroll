<?php

namespace App\Jobs\ESS\Attendance\Monitoring;

use App\Services\ESS\Attendance\Monitoring\RekapKehadiranExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Render berkas Rekap Kehadiran di latar belakang. Cerminan
 * GeneratePengumpulanDataExportJob.
 */
class GenerateRekapKehadiranExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    // Sekali jalan: gagal -> langsung failed (lewat failed()), tanpa retry otomatis.
    public $tries = 1;

    public function __construct(protected string $exportId)
    {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(RekapKehadiranExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(RekapKehadiranExportService::class)->markFailed($this->exportId, $e->getMessage());
        } catch (\Throwable $lain) {
            Log::error('Gagal menandai ekspor rekap kehadiran sebagai failed.', [
                'export_id' => $this->exportId,
                'message' => $lain->getMessage(),
            ]);
        }
    }
}
