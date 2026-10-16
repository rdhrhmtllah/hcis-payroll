<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use App\Jobs\ESS\Dashboard\Leadership\LeaderDashboardSyncJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaderDashboardPrewarmCommand extends Command
{
    /**
     * Pre-warm cache notifikasi needs-action leader dashboard.
     *
     * Mengantrekan LeaderDashboardSyncJob untuk setiap leader aktif (punya anggota
     * tim) sehingga cache notifikasi (HCIS/KPI/Approval) ter-refresh di background
     * sebelum leader membuka halaman. Endpoint /needs-action sudah cache-first, jadi
     * command ini hanya menjaga datanya tetap segar.
     *
     * @var string
     */
    protected $signature = 'leader-dashboard:prewarm {--kpi-year= : Tahun KPI (default: tahun berjalan)}';

    protected $description = 'Antrekan sync needs-action leader dashboard untuk semua leader aktif (pre-warm cache).';

    public function handle(): int
    {
        $kpiYear = (int) ($this->option('kpi-year') ?: Carbon::now('Asia/Jakarta')->year);

        $leaderKodes = DB::table('HRIS_Karyawan_Team as team')
            ->join('Karyawan as k', 'team.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->whereColumn('team.Kode_Karyawan_Team', '<>', 'team.Kode_Karyawan')
            ->distinct()
            ->pluck('team.Kode_Karyawan_Team')
            ->filter(fn ($kode) => trim((string) $kode) !== '')
            ->unique()
            ->values();

        if ($leaderKodes->isEmpty()) {
            $this->info('Tidak ada leader aktif untuk di-pre-warm.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($leaderKodes as $leaderKode) {
            try {
                LeaderDashboardSyncJob::dispatch((string) $leaderKode, $kpiYear);
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning('LeaderDashboardPrewarm dispatch failed: '.$e->getMessage(), [
                    'leader_kode' => $leaderKode,
                    'kpi_year' => $kpiYear,
                ]);
            }
        }

        $this->info("Pre-warm leader dashboard: {$dispatched}/{$leaderKodes->count()} job di-antrekan (KPI year {$kpiYear}).");
        Log::info('LeaderDashboardPrewarm selesai', [
            'dispatched' => $dispatched,
            'total_leaders' => $leaderKodes->count(),
            'kpi_year' => $kpiYear,
        ]);

        return self::SUCCESS;
    }
}
