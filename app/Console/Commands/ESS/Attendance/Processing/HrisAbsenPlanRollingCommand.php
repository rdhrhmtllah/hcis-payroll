<?php

namespace App\Console\Commands\ESS\Attendance\Processing;

use App\Services\ESS\Attendance\Processing\PerencanaAntreanAbsen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HrisAbsenPlanRollingCommand extends Command
{
    /**
     * Tidak menerima tanggal sama sekali: pada MODE_ROLLING, SP-nya sendiri yang
     * menghitung hari ini-6 s/d hari ini. Menambahkan opsi tanggal di sini akan
     * diabaikan diam-diam oleh SP.
     */
    protected $signature = 'hris:absen-plan-rolling
        {--kode_perusahaan=001 : Kode perusahaan}
        {--refresh_mode=MISSING_OR_DIRTY : MISSING_ONLY, DIRTY_ONLY, MISSING_OR_DIRTY, FORCE}
        {--priority=3 : Queue priority}';

    protected $description = 'Plan HRIS attendance refresh queue for rolling 7 days';

    public function __construct(private PerencanaAntreanAbsen $perencana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $kodePerusahaan = (string) $this->option('kode_perusahaan');
        $refreshMode = PerencanaAntreanAbsen::normalkanRefreshMode($this->option('refresh_mode'));
        $priority = PerencanaAntreanAbsen::normalkanPrioritas((int) $this->option('priority'), 3);

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS rolling 7 days plan command', [
                'kode_perusahaan' => $kodePerusahaan,
                'refresh_mode' => $refreshMode,
                'priority' => $priority,
            ]);

            $result = $this->perencana->rencanakan(
                $kodePerusahaan,
                null,
                null,
                null,
                $refreshMode,
                'SCHEDULED_ROLLING_7_DAYS',
                'laravel-scheduler',
                $priority,
                PerencanaAntreanAbsen::MODE_ROLLING,
            );

            Log::channel('attendance_queue_worker')->info('Finished HRIS rolling 7 days plan command', [
                'result' => $result,
            ]);

            $this->info('HRIS rolling 7 days plan created.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed HRIS rolling 7 days plan command', [
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
