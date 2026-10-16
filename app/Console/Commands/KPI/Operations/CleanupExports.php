<?php

namespace App\Console\Commands\KPI\Operations;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * CleanupExports
 *
 * Pembersih file export TERPUSAT (semua modul) dari tabel N_HRIS_Export_Jobs:
 *   - File yang sudah diunduh (Downloaded_At) > N jam lalu.
 *   - File ready yang tidak pernah diunduh > 24 jam (kedaluwarsa).
 *
 * Bekerja lintas queue driver (tidak bergantung delayed job). Jadwalkan via
 * scheduler atau N_HRIS_Scheduler_Configs, mis. tiap 15 menit. Bisa dibatasi
 * per modul dengan opsi --module.
 */
class CleanupExports extends Command
{
    protected $signature = 'export:cleanup
        {--hours=1 : Jam setelah download sebelum file dihapus}
        {--module= : Batasi ke satu modul (mis. lms-training, kpi-export)}';

    protected $description = 'Hapus file export (semua modul) yang sudah diunduh / kedaluwarsa';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $module = $this->option('module');

        $jobs = DB::table(AbstractExportService::TABLE)
            ->when($module, fn ($q) => $q->where('Module', $module))
            ->whereNotNull('File_Path')
            ->whereNull('Deleted_At')
            ->where(function ($q) use ($hours) {
                // Sudah diunduh > N jam lalu.
                $q->whereNotNull('Downloaded_At')
                    ->where('Downloaded_At', '<=', now()->subHours($hours));
            })
            ->orWhere(function ($q) use ($module) {
                // Ready tapi tidak pernah diunduh > 24 jam (kedaluwarsa).
                $q->when($module, fn ($qq) => $qq->where('Module', $module))
                    ->whereNotNull('File_Path')
                    ->whereNull('Deleted_At')
                    ->whereNull('Downloaded_At')
                    ->where('Status', AbstractExportService::STATUS_READY)
                    ->where('Completed_At', '<=', now()->subDay());
            })
            ->get(['Export_Id', 'File_Path']);

        $deleted = 0;
        foreach ($jobs as $job) {
            try {
                if ($job->File_Path && Storage::disk(AbstractExportService::DISK)->exists($job->File_Path)) {
                    Storage::disk(AbstractExportService::DISK)->delete($job->File_Path);
                }
            } catch (\Throwable $e) {
                Log::channel('UpdatePerformanceController')->warning('CleanupExports gagal hapus file: '.$e->getMessage(), [
                    'export_id' => $job->Export_Id,
                ]);
            }

            DB::table(AbstractExportService::TABLE)
                ->where('Export_Id', $job->Export_Id)
                ->update(['File_Path' => null, 'Deleted_At' => now(), 'Updated_At' => now()]);
            $deleted++;
        }

        $this->info("Cleanup selesai. {$deleted} file export dihapus.");

        return self::SUCCESS;
    }
}
