<?php

namespace App\Jobs\KPI\Export;

use App\Services\KPI\KpiExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * GenerateKpiExportJob
 *
 * Render file export (Excel/PDF) monitoring KPI di background via Cloud Tasks.
 * Progress & link download dilacak di N_HRIS_Export_Jobs (status ready saat
 * file siap). Export detail berlapis bisa berat -> async agar tidak timeout.
 */
class GenerateKpiExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;

    // Sekali jalan saja: bila gagal, langsung tandai failed (lewat failed()) dan
    // JANGAN biarkan Cloud Tasks retry otomatis. User memicu ulang manual via
    // tombol "Coba lagi" (hapus job gagal -> dispatch job baru).
    public $tries = 1;

    public function __construct(
        protected string $exportId,
    ) {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(KpiExportService $service): void
    {
        $service->process($this->exportId);
    }

    public function failed(\Throwable $e): void
    {
        // Tandai job gagal di tabel export agar tidak menggantung di ~99% saat
        // worker mati keras (OOM/timeout) & Cloud Tasks kehabisan retry.
        try {
            app(KpiExportService::class)->markFailed(
                $this->exportId,
                'Export gagal diproses (data terlalu besar atau timeout). Coba pakai filter agar lebih kecil.',
            );
        } catch (\Throwable $ignored) {
            // jangan biarkan handler gagal menutupi error asli
        }

        Log::channel('UpdatePerformanceController')->error(
            'GenerateKpiExportJob gagal permanen: '.$e->getMessage(),
            ['export_id' => $this->exportId, 'exception' => $e],
        );
    }
}
