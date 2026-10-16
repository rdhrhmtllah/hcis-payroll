<?php

namespace App\Jobs\SYSTEM\Organization;

use App\Services\SYSTEM\Organization\OrganizationRelationAsyncExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Membuat berkas Export Relasi Organisasi di antrean ekspor (lihat OrganizationRelationAsyncExportService). */
class GenerateRelasiOrganisasiExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 1;

    public function __construct(protected string $exportId)
    {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(OrganizationRelationAsyncExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        try {
            app(OrganizationRelationAsyncExportService::class)->markFailed($this->exportId, $e->getMessage());
        } catch (\Throwable $lain) {
            Log::error('Gagal menandai ekspor relasi organisasi sebagai failed.', [
                'export_id' => $this->exportId,
                'message' => $lain->getMessage(),
            ]);
        }
    }
}
