<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService;
use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckMaturedBalances extends Command
{
    protected $signature = 'cuti:checkMaturedBalances';

    protected $description = 'Mengecek saldo future yang aktif hari ini dan melunasi hutang jika ada.';

    protected $transactionService;

    protected $batchService;

    protected $cutiNotificationService;

    public function __construct(
        TransactionService $transactionService,
        CutiProcessBatchService $batchService,
        CutiNotificationService $cutiNotificationService,
    ) {
        parent::__construct();
        $this->transactionService = $transactionService;
        $this->batchService = $batchService;
        $this->cutiNotificationService = $cutiNotificationService;
    }

    public function handle()
    {
        $now = FormatTanggalHelper::getCurrentTime('checkMatured');
        $today = $now->toDateString();
        $bulanTahun = $now->clone()->format('m-y');
        $summary = [
            'checked' => 0,
            'eligible' => 0,
            'success' => 0,
            'dry_run' => 0,
            'skipped_no_debt' => 0,
            'skipped_no_positive_balance' => 0,
            'failed' => 0,
        ];
        $batchId = $this->batchService->startBatch(CutiProcessBatchService::SOURCE_MATURED_DEBT_PAYMENT, [
            'period_start' => $today,
            'period_end' => $today,
            'is_dry_run' => false,
            'triggered_from' => 'COMMAND',
            'requested_by' => 'SYSTEM_SCHEDULER',
            'options' => [
                'command' => $this->signature,
                'date' => $today,
            ],
        ]);

        $this->info("Mengecek saldo yang cair/aktif pada {$today}...");
        Log::channel('checkMatured')->info("CheckMaturedBalances running for {$today}");

        // Ambil saldo aktif yang masih bisa dipakai bayar hutang.
        // Activated_At <= today tetap dipakai supaya run yang sempat miss tetap bisa recovery.
        // Range sargable pengganti whereDate() (CAST di kolom mematikan index
        // seek): "tanggal(col) <= hari ini" == "col < besok 00:00".
        $besok = $now->clone()->addDay()->toDateString();

        $maturedUsers = DB::table('HRIS_Buku_Cuti')
            ->select('Kode_Karyawan')
            ->where('Flag_Aktif', 'Y')
            ->where('sisa_cuti', '>', 0)
            ->where('Activated_At', '<', $besok)
            ->where('tanggal_expired', '>=', $today)
            ->distinct()
            ->get();

        if ($maturedUsers->isEmpty()) {
            $this->info('Tidak ada saldo yang cair hari ini.');
            Log::channel('checkMatured')->info('Tidak ada saldo yang cair hari ini.');
            $this->batchService->completeBatch($batchId, $summary);

            return 0;
        }

        foreach ($maturedUsers as $user) {
            $kode = $user->Kode_Karyawan;
            $summary['checked']++;
            $hutangAbsolut = 0;

            DB::beginTransaction();
            try {
                $debtRows = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $kode)
                    ->where('Flag_Aktif', 'Y')
                    ->where('sisa_cuti', '<', 0)
                    ->lockForUpdate()
                    ->get();

                $hutangAbsolut = abs((float) $debtRows->sum('sisa_cuti'));

                // Hitung hutang di dalam transaksi supaya run paralel/berulang tidak memakai angka lama.
                if ($hutangAbsolut == 0) {
                    DB::commit();
                    $summary['skipped_no_debt']++;

                    continue;
                }

                $summary['eligible']++;

                // 3. CARI JENIS CUTI YANG BISA DIPAKAI BAYAR
                // Kita ambil semua jenis cuti aktif user ini (termasuk yang baru cair tadi)
                $targetJenisIds = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $kode)
                    ->where('Flag_Aktif', 'Y')
                    ->where('sisa_cuti', '>', 0)
                    ->where('Activated_At', '<', $besok) // Ambil yang sudah aktif
                    ->where('tanggal_expired', '>=', $today)
                    ->lockForUpdate()
                    ->pluck('Id_Jenis_Cuti')
                    ->unique()
                    ->toArray();

                $refNoDebit = null;
                $refNoRepay = null;
                $sisaHutangTakTerbayar = $hutangAbsolut;
                $terbayar = 0;
                $availableBalance = 0;

                if (! empty($targetJenisIds)) {
                    $this->info("User {$kode} saldo aktif & punya hutang {$hutangAbsolut}. Memproses pelunasan...");
                    Log::channel('checkMatured')->info("User {$kode} saldo aktif & punya hutang {$hutangAbsolut}. Memproses pelunasan...");

                    $availableBalance = (float) DB::table('HRIS_Buku_Cuti')
                        ->where('Kode_Karyawan', $kode)
                        ->whereIn('Id_Jenis_Cuti', $targetJenisIds)
                        ->where('Flag_Aktif', 'Y')
                        ->where('sisa_cuti', '>', 0)
                        ->where('Activated_At', '<', $besok)
                        ->where('tanggal_expired', '>=', $today)
                        ->sum('sisa_cuti');

                    // A. TARIK DANA DARI SALDO (DEBIT)
                    $refNoDebit = $this->transactionService->getNoTransaction(
                        'N_HRIS_Log_Buku_Cuti',
                        'MATURE_DEBT_PAYMENT',
                        'No_Transaksi_Ref',
                        $bulanTahun
                    );

                    $sisaHutangTakTerbayar = $this->transactionService->debitBalance(
                        $kode,
                        $targetJenisIds,
                        $hutangAbsolut,
                        $refNoDebit,
                        'PELUNASAN_HUTANG_MATURE',
                        'Saldo Cair Otomatis Melunasi Hutang',
                        'SYSTEM_SCHEDULER',
                        $now
                    );

                    // B. BAYAR KE SALDO NEGATIF (REPAY)
                    $rencanaTerbayar = $hutangAbsolut - $sisaHutangTakTerbayar;

                    if ($rencanaTerbayar > 0) {
                        $refNoRepay = $this->transactionService->getNoTransaction(
                            'N_HRIS_Log_Buku_Cuti',
                            'MATURE_DEBT_REPAY',
                            'No_Transaksi_Ref',
                            $bulanTahun
                        );

                        $terbayar = $this->transactionService->repayDebt(
                            $kode,
                            $rencanaTerbayar,
                            $refNoRepay,
                            'PAY_DEBT',
                            'Lunas Otomatis (Credit) Saldo Cair',
                            'SYSTEM_SCHEDULER',
                            null,
                            null,
                            $now
                        );

                        $sisaHutangTakTerbayar = max(0, $hutangAbsolut - $terbayar);

                        $this->info("  -> Sukses melunasi hutang sebesar: $terbayar");
                    } else {
                        $this->warn("  -> Tidak ada saldo yang terdebit. Saldo tersedia menurut scan: {$availableBalance}");
                    }
                } else {
                    $summary['skipped_no_positive_balance']++;
                    $this->recordBatchDetail($batchId, $kode, $today, $hutangAbsolut, 0, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'NO_POSITIVE_BALANCE', 'Tidak ada saldo positif aktif untuk membayar hutang.', [
                        'debt_before' => $hutangAbsolut,
                    ]);
                }

                DB::commit();

                if ($terbayar > 0) {
                    $summary['success']++;
                    $this->recordBatchDetail($batchId, $kode, $today, $hutangAbsolut, $terbayar, CutiProcessBatchService::DETAIL_SUCCESS, null, "Berhasil melunasi hutang {$terbayar}.", [
                        'debt_before' => $hutangAbsolut,
                        'debt_after' => $sisaHutangTakTerbayar,
                        'debited_amount' => $terbayar,
                        'repaid_amount' => $terbayar,
                        'target_jenis_ids' => $targetJenisIds,
                        'debit_ref' => $refNoDebit,
                        'repay_ref' => $refNoRepay,
                    ], $refNoRepay ?: $refNoDebit);

                    $this->cutiNotificationService->notifyAccrual($kode, [
                        'source' => CutiNotificationService::SOURCE_MATURED,
                        'jumlah' => $terbayar,
                        'idJenisCutis' => $targetJenisIds,
                        'periodKey' => $today,
                        'refNo' => $refNoRepay ?: $refNoDebit,
                        'meta' => [
                            'debt_before' => $hutangAbsolut,
                            'debt_after' => $sisaHutangTakTerbayar,
                            'debit_ref' => $refNoDebit,
                            'repay_ref' => $refNoRepay,
                            'target_jenis_ids' => $targetJenisIds,
                        ],
                    ]);
                } elseif (! empty($targetJenisIds)) {
                    $summary['skipped_no_positive_balance']++;
                    $this->recordBatchDetail($batchId, $kode, $today, $hutangAbsolut, 0, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'NO_DEBT_REPAID', 'Tidak ada hutang yang berhasil dilunasi.', [
                        'debt_before' => $hutangAbsolut,
                        'debt_after' => $sisaHutangTakTerbayar,
                        'target_jenis_ids' => $targetJenisIds,
                        'debit_ref' => $refNoDebit,
                    ], $refNoDebit);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                $summary['failed']++;
                Log::channel('checkMatured')->error("Gagal CheckMaturedBalances {$kode}: ".$e->getMessage());
                $this->error('Gagal: '.$e->getMessage());
                $this->recordBatchDetail($batchId, $kode, $today, $hutangAbsolut, 0, CutiProcessBatchService::DETAIL_FAILED, 'EXCEPTION', $e->getMessage(), [
                    'debt_before' => $hutangAbsolut,
                ]);
            } catch (\Throwable $e) {
                /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
                   jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
                   supaya handler global tetap mencatat stack trace-nya dan responsnya
                   tak berubah sedikit pun. */
                DB::rollBack();

                throw $e;
            }
        }

        $this->batchService->completeBatch($batchId, $summary);
        CutiProcessBatchService::flushAllDashboardCaches();

        return 0;
    }

    private function recordBatchDetail(
        ?int $batchId,
        string $kodeKaryawan,
        string $today,
        float $debtBefore,
        float $amount,
        string $status,
        ?string $reasonCode,
        string $message,
        array $metadata = [],
        ?string $refNo = null,
    ): void {
        $this->batchService->recordDetail($batchId, [
            'source_type' => CutiProcessBatchService::SOURCE_MATURED_DEBT_PAYMENT,
            'kode_karyawan' => $kodeKaryawan,
            'period_key' => $today,
            'due_date' => $today,
            'effective_date' => $today,
            'jumlah' => $amount,
            'no_transaksi_ref' => $refNo,
            'status' => $status,
            'reason_code' => $reasonCode,
            'message' => $message,
            'metadata' => array_merge([
                'debt_before' => $debtBefore,
            ], $metadata),
        ]);
    }
}
