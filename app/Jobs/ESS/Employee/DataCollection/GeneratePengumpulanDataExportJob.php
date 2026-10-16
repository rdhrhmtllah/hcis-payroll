<?php

namespace App\Jobs\ESS\Employee\DataCollection;

use App\Services\ESS\Employee\DataCollection\PengumpulanDataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Render berkas ekspor Pembaruan Data Karyawan di latar belakang. Kemajuan dan
 * tautan unduhnya dilacak lewat mesin ekspor terpusat. Cerminan
 * GenerateSopComplianceExportJob.
 */
class GeneratePengumpulanDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    // Sekali jalan: gagal -> langsung failed (lewat failed()), tanpa retry otomatis.
    public $tries = 1;

    public function __construct(protected string $exportId)
    {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(PengumpulanDataExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(PengumpulanDataExportService::class)->markFailed(
                $this->exportId,
                $e->getMessage(),
            );
        } catch (\Throwable $lain) {
            Log::error('Gagal menandai ekspor pengumpulan data sebagai failed.', [
                'export_id' => $this->exportId,
                'message' => $lain->getMessage(),
            ]);
        }
    }
}
