<?php

namespace App\Console\Commands\ESS\Attendance\Processing;

use App\Services\ESS\Attendance\Processing\CutoffService;
use App\Services\ESS\Attendance\Processing\PerencanaAntreanAbsen;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HrisAbsenPlanCutoffCommand extends Command
{
    /**
     * Perintah inilah yang dimaksudkan untuk jadwal cutoff harian: rentangnya
     * TIDAK diberikan pemanggil melainkan dihitung dari periode cutoff yang
     * sedang berjalan, jadi ia tidak bisa membeku pada tanggal tertentu.
     */
    protected $signature = 'hris:absen-plan-cutoff
        {--kode_perusahaan=001 : Kode perusahaan}
        {--user_id_list= : Optional comma-separated UserID}
        {--refresh_mode=MISSING_OR_DIRTY : MISSING_ONLY, DIRTY_ONLY, MISSING_OR_DIRTY, FORCE}
        {--request_source=MANUAL_REFRESH : Request source}
        {--requested_by=laravel-scheduler : Requested by}
        {--priority=4 : Queue priority}';

    protected $description = 'Plan HRIS attendance refresh queue for cutoff date range';

    public function __construct(
        protected CutoffService $cutoffService,
        private PerencanaAntreanAbsen $perencana,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now()->toDateTimeString();
        $cutoffDate = $this->cutoffService->getCurrentPeriod($now);

        $kodePerusahaan = (string) $this->option('kode_perusahaan');
        $tglAwal = $cutoffDate ? $cutoffDate['Start_Date'] : null;
        $tglAkhir = $cutoffDate ? $cutoffDate['End_Date'] : null;
        $userIdList = $this->option('user_id_list') ?: null;
        $refreshMode = PerencanaAntreanAbsen::normalkanRefreshMode($this->option('refresh_mode'));
        $requestSource = strtoupper((string) $this->option('request_source'));
        $requestedBy = (string) $this->option('requested_by');
        $priority = PerencanaAntreanAbsen::normalkanPrioritas((int) $this->option('priority'), 4);

        if (! $tglAwal || ! $tglAkhir) {
            $this->error('tgl_awal dan tgl_akhir wajib diisi untuk cutoff plan.');

            return self::FAILURE;
        }

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS Cutoff plan command', [
                'kode_perusahaan' => $kodePerusahaan,
                'tgl_awal' => $tglAwal,
                'tgl_akhir' => $tglAkhir,
                'user_id_list' => $userIdList,
                'refresh_mode' => $refreshMode,
                'request_source' => $requestSource,
                'requested_by' => $requestedBy,
                'priority' => $priority,
            ]);

            $result = $this->perencana->rencanakan(
                $kodePerusahaan,
                $tglAwal,
                $tglAkhir,
                $userIdList,
                $refreshMode,
                $requestSource,
                $requestedBy,
                $priority,
                PerencanaAntreanAbsen::MODE_RENTANG,
            );

            Log::channel('attendance_queue_worker')->info('Finished HRIS Cutoff plan command', [
                'result' => $result,
            ]);

            $this->info('HRIS Cutoff plan created.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed HRIS Cutoff plan command', [
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
