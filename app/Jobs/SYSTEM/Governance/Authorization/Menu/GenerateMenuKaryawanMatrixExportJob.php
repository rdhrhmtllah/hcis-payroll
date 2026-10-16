<?php

namespace App\Jobs\SYSTEM\Governance\Authorization\Menu;

use App\Services\SYSTEM\Governance\Authorization\Menu\MenuKaryawanMatrixExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Render berkas ekspor matrix menu x karyawan di latar belakang. Kemajuan dan
 * tautan unduhnya dilacak mesin ekspor terpusat (Pusat Aktivitas).
 */
class GenerateMenuKaryawanMatrixExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    // Sekali jalan: gagal -> langsung failed (lewat failed()), tanpa retry otomatis.
    public $tries = 1;

    public function __construct(protected string $exportId)
    {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(MenuKaryawanMatrixExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(MenuKaryawanMatrixExportService::class)->markFailed($this->exportId, $e->getMessage());
        } catch (\Throwable $lain) {
            Log::error('Gagal menandai ekspor matrix menu x karyawan sebagai failed.', [
                'export_id' => $this->exportId,
                'message' => $lain->getMessage(),
            ]);
        }
    }
}
