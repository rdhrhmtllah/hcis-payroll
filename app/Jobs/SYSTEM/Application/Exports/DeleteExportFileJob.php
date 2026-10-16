<?php

namespace App\Jobs\SYSTEM\Application\Exports;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * DeleteExportFileJob
 *
 * Hapus file hasil export dari storage untuk menghemat ruang. Di-dispatch dengan
 * delay ~1 jam saat user PERTAMA kali mengunduh file. Menandai Deleted_At di
 * N_HRIS_Export_Jobs.
 */
class DeleteExportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public function __construct(
        protected string $exportId,
    ) {
        $this->onQueue((string) config('queue.names.async_export'));
    }

    public function handle(): void
    {
        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $this->exportId)->first();
        if (! $job || ! $job->File_Path) {
            return;
        }

        try {
            if (Storage::disk(AbstractExportService::DISK)->exists($job->File_Path)) {
                Storage::disk(AbstractExportService::DISK)->delete($job->File_Path);
            }
        } catch (\Throwable $e) {
            Log::channel('UpdatePerformanceController')->warning('DeleteExportFileJob gagal: '.$e->getMessage(), [
                'export_id' => $this->exportId,
            ]);
        }

        DB::table(AbstractExportService::TABLE)->where('Export_Id', $this->exportId)->update([
            'File_Path' => null,
            'Deleted_At' => now(),
            'Updated_At' => now(),
        ]);
    }
}
