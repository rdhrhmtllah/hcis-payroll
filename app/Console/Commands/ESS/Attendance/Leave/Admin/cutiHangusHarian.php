<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class cutiHangusHarian extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cuti:cutiHangusHarian';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mencari cuti yang expired hari ini dan menghanguskannya (HARIAN)';

    protected $transactionService;

    protected $batchService;

    public function __construct(TransactionService $transactionService, CutiProcessBatchService $batchService)
    {
        parent::__construct();
        $this->transactionService = $transactionService;
        $this->batchService = $batchService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = FormatTanggalHelper::getCurrentTime('cutiHangusHarian');
        $hariIni = $now->toDateString();
        $summary = [
            'checked' => 0,
            'eligible' => 0,
            'success' => 0,
            'dry_run' => 0,
            'skipped_already_closed' => 0,
            'failed' => 0,
        ];
        $batchId = $this->batchService->startBatch(CutiProcessBatchService::SOURCE_EXPIRED_CLEANUP, [
            'period_start' => $hariIni,
            'period_end' => $hariIni,
            'is_dry_run' => false,
            'triggered_from' => 'COMMAND',
            'requested_by' => 'SYSTEM_SCHEDULER',
            'options' => [
                'command' => $this->signature,
                'date' => $hariIni,
            ],
        ]);

        $this->info("Mencari cuti yang expired pada {$hariIni}...");
        Log::channel('cutiHangusHarian')->info("Start process checking expired leave for {$hariIni}...");

        $expiredRows = DB::table('HRIS_Buku_Cuti')
            ->whereDate('tanggal_expired', '<=', $hariIni)
            ->where('sisa_cuti', '>', 0)
            ->where('Flag_Aktif', 'Y')
            ->get();

        if ($expiredRows->isEmpty()) {
            $this->info('Tidak ada cuti yang expired hari ini.');
            Log::channel('cutiHangusHarian')->info('Tidak ada cuti yang expired hari ini.');
            $this->batchService->completeBatch($batchId, $summary);

            return 0;
        }

        $bar = $this->output->createProgressBar($expiredRows->count());
        $count = 0;

        foreach ($expiredRows as $row) {
            $summary['checked']++;
            $summary['eligible']++;
            try {

                $refNo = 'EXP-'.$now->format('Ymd').'-'.$row->Id_Buku_Cuti;

                $closed = $this->transactionService->closeBalance(
                    $row->Id_Buku_Cuti,
                    "Hangus Harian Otomatis (Exp: {$row->tanggal_expired})",
                    'SYSTEM_SCHEDULER',
                    $refNo
                );

                if ($closed) {
                    $summary['success']++;
                    $count++;
                    $idLog = DB::table('N_HRIS_Log_Buku_Cuti')
                        ->where('No_Transaksi_Ref', $refNo)
                        ->orderByDesc('Id_Log')
                        ->value('Id_Log');

                    $this->recordBatchDetail($batchId, $row, $hariIni, $refNo, CutiProcessBatchService::DETAIL_SUCCESS, null, 'Saldo expired berhasil dihanguskan.', [
                        'saldo_before' => (float) $row->sisa_cuti,
                        'expired_date' => $row->tanggal_expired,
                    ], $idLog ? (int) $idLog : null);
                } else {
                    $summary['skipped_already_closed']++;
                    $this->recordBatchDetail($batchId, $row, $hariIni, $refNo, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'ALREADY_CLOSED_OR_EMPTY', 'Saldo sudah tidak aktif atau sisa cuti nol.', [
                        'saldo_before' => (float) $row->sisa_cuti,
                        'expired_date' => $row->tanggal_expired,
                    ]);
                }
                $bar->advance();

            } catch (\Exception $e) {
                $summary['failed']++;
                Log::channel('cutiHangusHarian')->error("Gagal menghanguskan ID Buku {$row->Id_Buku_Cuti}: ".$e->getMessage());
                $this->recordBatchDetail($batchId, $row, $hariIni, $refNo ?? null, CutiProcessBatchService::DETAIL_FAILED, 'EXCEPTION', $e->getMessage(), [
                    'saldo_before' => (float) $row->sisa_cuti,
                    'expired_date' => $row->tanggal_expired,
                ]);
            }
        }

        $bar->finish();
        $this->newLine();

        $pesanSukses = "Proses selesai. Telah menghanguskan {$count} data cuti pada {$hariIni}";
        Log::channel('cutiHangusHarian')->info($pesanSukses);
        $this->info($pesanSukses);

        $this->batchService->completeBatch($batchId, $summary);
        CutiProcessBatchService::flushAllDashboardCaches();

        return 0;
    }

    private function recordBatchDetail(
        ?int $batchId,
        object $row,
        string $hariIni,
        ?string $refNo,
        string $status,
        ?string $reasonCode,
        string $message,
        array $metadata = [],
        ?int $idLog = null,
    ): void {
        $this->batchService->recordDetail($batchId, [
            'source_type' => CutiProcessBatchService::SOURCE_EXPIRED_CLEANUP,
            'kode_karyawan' => $row->Kode_Karyawan ?? null,
            'id_jenis_cuti' => $row->Id_Jenis_Cuti ?? null,
            'period_key' => $hariIni,
            'due_date' => $row->tanggal_expired ?? $hariIni,
            'effective_date' => $hariIni,
            'jumlah' => isset($row->sisa_cuti) ? -abs((float) $row->sisa_cuti) : null,
            'no_transaksi_ref' => $refNo,
            'id_log' => $idLog,
            'status' => $status,
            'reason_code' => $reasonCode,
            'message' => $message,
            'metadata' => array_merge([
                'id_buku_cuti' => $row->Id_Buku_Cuti ?? null,
            ], $metadata),
        ]);
    }
}
