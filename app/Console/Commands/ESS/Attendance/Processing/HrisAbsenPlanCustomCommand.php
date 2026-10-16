<?php

namespace App\Console\Commands\ESS\Attendance\Processing;

use App\Services\ESS\Attendance\Processing\PerencanaAntreanAbsen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HrisAbsenPlanCustomCommand extends Command
{
    /**
     * Rentang tanggalnya HARUS diberikan pemanggil dan tidak punya nilai baku.
     *
     * Kalau butuh periode cutoff yang berjalan, pakai hris:absen-plan-cutoff —
     * ia menghitungnya sendiri lewat CutoffService. Memaku tanggal di baris
     * penjadwal berarti tanggal itu membeku pada hari ia ditulis; lihat
     * DML-2026-098.
     */
    protected $signature = 'hris:absen-plan-custom
        {--kode_perusahaan=001 : Kode perusahaan}
        {--tgl_awal= : Tanggal awal YYYY-MM-DD}
        {--tgl_akhir= : Tanggal akhir YYYY-MM-DD}
        {--user_id_list= : Optional comma-separated UserID}
        {--refresh_mode=MISSING_OR_DIRTY : MISSING_ONLY, DIRTY_ONLY, MISSING_OR_DIRTY, FORCE}
        {--request_source=MANUAL_REFRESH : Request source}
        {--requested_by=laravel-scheduler : Requested by}
        {--priority=4 : Queue priority}';

    protected $description = 'Plan HRIS attendance refresh queue for custom date range';

    public function __construct(private PerencanaAntreanAbsen $perencana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $kodePerusahaan = (string) $this->option('kode_perusahaan');
        $tglAwal = $this->option('tgl_awal') ?: null;
        $tglAkhir = $this->option('tgl_akhir') ?: null;
        $userIdList = $this->option('user_id_list') ?: null;
        $refreshMode = PerencanaAntreanAbsen::normalkanRefreshMode($this->option('refresh_mode'));
        $requestSource = strtoupper((string) $this->option('request_source'));
        $requestedBy = (string) $this->option('requested_by');
        $priority = PerencanaAntreanAbsen::normalkanPrioritas((int) $this->option('priority'), 4);

        if (! $tglAwal || ! $tglAkhir) {
            $this->error('tgl_awal dan tgl_akhir wajib diisi untuk custom plan.');

            return self::FAILURE;
        }

        try {
            Log::channel('attendance_queue_worker')->info('Starting HRIS custom plan command', [
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

            Log::channel('attendance_queue_worker')->info('Finished HRIS custom plan command', [
                'result' => $result,
            ]);

            $this->info('HRIS custom plan created.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed HRIS custom plan command', [
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
