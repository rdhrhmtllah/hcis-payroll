<?php

namespace App\Console\Commands\ESS\Employee\Promotion;

use App\Helpers\FormatTanggalHelper;
use App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService;
use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Jangan lupa import ini

class promotedEmployees extends Command
{
    protected $signature = 'cuti:checkKartab';

    protected $description = 'Mendeteksi karyawan yang baru jadi Kartap, membuka saldo, dan melunasi hutang(Satu Jam sekali).';

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
        $now = FormatTanggalHelper::getCurrentTime('promotedEmployee');
        $today = $now->toDateString();
        // Batas range sargable pengganti whereDate() (CAST di kolom mematikan
        // index seek): "tanggal(col) > hari ini" == "col >= besok 00:00",
        // "tanggal(col) <= hari ini" == "col < besok 00:00".
        $besok = $now->clone()->addDay()->toDateString();
        $summary = [
            'checked' => 0,
            'eligible' => 0,
            'success' => 0,
            'dry_run' => 0,
            'no_debt' => 0,
            'failed' => 0,
        ];
        $batchId = $this->batchService->startBatch(CutiProcessBatchService::SOURCE_PROMOTION_UNLOCK, [
            'period_start' => $today,
            'period_end' => $today,
            'is_dry_run' => false,
            'triggered_from' => 'COMMAND',
            'requested_by' => 'SYSTEM_SCHEDULER',
            'options' => [
                'command' => $this->signature,
                'date' => $today,
                'eligibility' => 'status_kontrak=PKWTT',
            ],
        ]);

        $this->info('Mencari karyawan Kartap dengan saldo terkunci...');
        Log::channel('promotedEmployees')->info("Mencari karyawan Kartap dengan saldo terkunci {$now}...");

        // $candidates = Karyawan::where('Status_Kepegawaian', 'Tetap')
        //     ->where('Aktif', 'Y')
        //     ->whereHas('bukuCuti', function($q) use ($now) {
        //         $q->where('Flag_Aktif', 'Y')
        //         ->whereDate('Activated_At', '>', $now);
        //     })
        //     ->get();

        $candidates = DB::table('Karyawan')
            ->join('HRIS_Buku_Cuti', 'Karyawan.Kode_Karyawan', '=', 'HRIS_Buku_Cuti.Kode_Karyawan')
            ->where('Karyawan.status_kontrak', 'PKWTT')
            ->where('Karyawan.Aktif', 'Y')
            ->where('HRIS_Buku_Cuti.Flag_Aktif', 'Y')
            ->where('HRIS_Buku_Cuti.sisa_cuti', '>', 0)
            ->where('HRIS_Buku_Cuti.Activated_At', '>=', $besok)
            ->where('HRIS_Buku_Cuti.tanggal_expired', '>=', $today)
            ->select('Karyawan.*') // Select all columns from Karyawan table
            ->distinct() // Ensure unique Karyawan records
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada karyawan yang perlu diproses.');
            Log::channel('promotedEmployees')->info('Tidak ada karyawan yang perlu diproses.');
            $this->batchService->completeBatch($batchId, $summary);

            return 0;
        }

        foreach ($candidates as $karyawan) {
            $summary['checked']++;
            $summary['eligible']++;
            $this->info("Memproses: {$karyawan->Nama} ({$karyawan->Kode_Karyawan})");
            Log::channel('promotedEmployees')->info("Promosi Kartap Detected: {$karyawan->Nama}");

            DB::beginTransaction();
            try {
                $lockedWallets = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                    ->where('Flag_Aktif', 'Y')
                    ->where('sisa_cuti', '>', 0)
                    ->where('Activated_At', '>=', $besok)
                    ->where('tanggal_expired', '>=', $today)
                    ->lockForUpdate()
                    ->get();

                if ($lockedWallets->isEmpty()) {
                    DB::commit();
                    $summary['no_debt']++;
                    $this->line('  - Skip: tidak ada saldo future valid yang bisa dibuka.');
                    $this->recordBatchDetail($batchId, $karyawan, $today, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'NO_VALID_LOCKED_WALLET', 'Tidak ada saldo future valid yang bisa dibuka.');

                    continue;
                }

                $bulanTahun = $now->clone()->format('m-y');
                $unlockRef = $this->transactionService->getNoTransaction(
                    'N_HRIS_Log_Buku_Cuti',
                    'PROMOTION_UNLOCK',
                    'No_Transaksi_Ref',
                    $bulanTahun
                );
                $totalUnlocked = (float) $lockedWallets->sum(fn ($wallet) => max(0, (float) $wallet->sisa_cuti));

                DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                    ->where('Flag_Aktif', 'Y')
                    ->where('sisa_cuti', '>', 0)
                    ->where('Activated_At', '>=', $besok)
                    ->where('tanggal_expired', '>=', $today)
                    ->update([
                        'Activated_At' => $now->toDateString(),
                        // 'notes' => DB::raw("CONCAT(notes, ' [Unlocked by System]')"),
                        'updated_at' => $now,
                    ]);

                $saldoPositifSesudahUnlock = (float) DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                    ->where('Flag_Aktif', 'Y')
                    ->where('sisa_cuti', '>', 0)
                    ->where('Activated_At', '<', $besok)
                    ->where('tanggal_expired', '>=', $today)
                    ->sum('sisa_cuti');

                $totalHutangSesudahUnlock = abs((float) DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                    ->where('Flag_Aktif', 'Y')
                    ->where('sisa_cuti', '<', 0)
                    ->sum('sisa_cuti'));

                $saldoEfektifSesudahUnlock = $saldoPositifSesudahUnlock - $totalHutangSesudahUnlock;
                $saldoEfektifCursor = $saldoEfektifSesudahUnlock - $totalUnlocked;

                foreach ($lockedWallets as $wallet) {
                    $jumlahUnlock = max(0, (float) $wallet->sisa_cuti);
                    if ($jumlahUnlock <= 0) {
                        continue;
                    }

                    $saldoEfektifSebelum = $saldoEfektifCursor;
                    $saldoEfektifSesudah = $saldoEfektifCursor + $jumlahUnlock;

                    $this->transactionService->recordBalanceAvailabilityChange(
                        $wallet->Id_Buku_Cuti,
                        $unlockRef,
                        'PROMOTION_UNLOCK',
                        $jumlahUnlock,
                        (float) $wallet->sisa_cuti,
                        'Saldo future dibuka karena karyawan menjadi PKWTT/Kartap',
                        'SYSTEM_SCHEDULER',
                        $saldoEfektifSebelum,
                        $saldoEfektifSesudah,
                        $totalHutangSesudahUnlock,
                        $totalHutangSesudahUnlock,
                        $now,
                    );

                    $saldoEfektifCursor = $saldoEfektifSesudah;
                }

                if ($totalUnlocked > 0) {
                    $this->line("  - Saldo aktif dibuka: {$totalUnlocked} hari. Ref: {$unlockRef}");
                }

                $totalHutang = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                    ->where('Flag_Aktif', 'Y')
                    ->where('sisa_cuti', '<', 0)
                    ->sum('sisa_cuti');

                $hutangAbsolut = abs($totalHutang);
                $terbayar = 0;
                $sisaHutangTakTerbayar = $hutangAbsolut;
                $targetJenisIds = [];
                $refNo = null;
                $refNo2 = null;

                if ($hutangAbsolut > 0) {
                    $this->line("  - Ditemukan hutang: {$hutangAbsolut} hari. Melakukan pelunasan...");
                    Log::channel('promotedEmployees')->info("  - Ditemukan hutang: {$hutangAbsolut} hari untuk {$karyawan->Nama}. Melakukan pelunasan...");

                    // C. CARI SUMBER DANA (JENIS CUTI YANG POSITIF)
                    $targetJenisIds = DB::table('HRIS_Buku_Cuti')
                        ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                        ->where('Flag_Aktif', 'Y')
                        ->where('sisa_cuti', '>', 0)
                        ->where('Activated_At', '<', $besok)
                        ->where('tanggal_expired', '>=', $today)
                        ->pluck('Id_Jenis_Cuti')
                        ->unique()
                        ->toArray();

                    if (! empty($targetJenisIds)) {

                        // dd($bulanTahun);
                        $refNo = $this->transactionService->getNoTransaction(
                            'N_HRIS_Log_Buku_Cuti',
                            'PROMOTION',
                            'No_Transaksi_Ref',
                            $bulanTahun
                        );

                        // 1. TARIK DANA DARI SALDO POSITIF (DEBIT)
                        $sisaHutangTakTerbayar = $this->transactionService->debitBalance(
                            $karyawan->Kode_Karyawan,
                            $targetJenisIds,
                            $hutangAbsolut,
                            $refNo,
                            'Hutang_Cuti',
                            'Auto-Deduction (Debit) Promosi Kartap',
                            'SYSTEM_SCHEDULER',
                            $now
                        );

                        // 2. BAYAR KE SALDO NEGATIF (CREDIT/REPAY)
                        // Hitung berapa uang yang berhasil kita tarik
                        $terbayar = $hutangAbsolut - $sisaHutangTakTerbayar;
                        $refNo2 = $this->transactionService->getNoTransaction(
                            'N_HRIS_Log_Buku_Cuti',
                            'PROMOTION',
                            'No_Transaksi_Ref',
                            $bulanTahun
                        );

                        if ($terbayar > 0) {
                            // Panggil fungsi baru repayDebt
                            $terbayar = $this->transactionService->repayDebt(
                                $karyawan->Kode_Karyawan,
                                $terbayar,
                                $refNo2,
                                'PAY_DEBT',
                                'Lunas Otomatis (Credit) Promosi Kartap',
                                'SYSTEM_SCHEDULER',
                                null,
                                null,
                                $now
                            );

                            $sisaHutangTakTerbayar = max(0, $hutangAbsolut - $terbayar);

                            $this->info("  - Berhasil melunasi hutang sebesar: $terbayar");
                        }
                    }
                }

                DB::commit();
                $summary['success']++;
                if ($hutangAbsolut == 0) {
                    $summary['no_debt']++;
                }
                $this->recordBatchDetail($batchId, $karyawan, $today, CutiProcessBatchService::DETAIL_SUCCESS, null, 'Saldo future berhasil dibuka.', [
                    'unlocked_wallet_ids' => $lockedWallets->pluck('Id_Buku_Cuti')->all(),
                    'unlocked_wallet_count' => $lockedWallets->count(),
                    'unlocked_amount' => $totalUnlocked,
                    'debt_before' => $hutangAbsolut,
                    'debt_after' => $sisaHutangTakTerbayar,
                    'repaid_amount' => $terbayar,
                    'target_jenis_ids' => $targetJenisIds,
                    'unlock_ref' => $unlockRef,
                    'debit_ref' => $refNo,
                    'repay_ref' => $refNo2,
                    'status_kontrak' => $karyawan->status_kontrak ?? null,
                ], $refNo2 ?: ($refNo ?: $unlockRef));

                $unlockedJenisIds = $lockedWallets->pluck('Id_Jenis_Cuti')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $this->cutiNotificationService->notifyAccrual($karyawan->Kode_Karyawan, [
                    'source' => CutiNotificationService::SOURCE_PROMOTION,
                    'jumlah' => $terbayar,
                    'idJenisCutis' => array_values(array_unique(array_merge($unlockedJenisIds, $targetJenisIds))),
                    'periodKey' => $today,
                    'refNo' => $refNo2 ?: ($refNo ?: $unlockRef),
                    'meta' => [
                        'unlocked_wallet_count' => $lockedWallets->count(),
                        'unlocked_jenis_ids' => $unlockedJenisIds,
                        'unlocked_amount' => $totalUnlocked,
                        'debt_before' => $hutangAbsolut,
                        'debt_after' => $sisaHutangTakTerbayar,
                        'repaid_amount' => $terbayar,
                        'unlock_ref' => $unlockRef,
                        'status_kontrak' => $karyawan->status_kontrak ?? null,
                    ],
                ]);

                $this->info('  - Sukses.');

            } catch (\Exception $e) {
                DB::rollBack();
                $summary['failed']++;
                $this->error('  - Gagal: '.$e->getMessage());
                Log::channel('promotedEmployees')->error("Gagal proses promosi {$karyawan->Nama}: ".$e->getMessage());
                $this->recordBatchDetail($batchId, $karyawan, $today, CutiProcessBatchService::DETAIL_FAILED, 'EXCEPTION', $e->getMessage());
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
        object $karyawan,
        string $today,
        string $status,
        ?string $reasonCode,
        string $message,
        array $metadata = [],
        ?string $refNo = null,
    ): void {
        $this->batchService->recordDetail($batchId, [
            'source_type' => CutiProcessBatchService::SOURCE_PROMOTION_UNLOCK,
            'kode_karyawan' => $karyawan->Kode_Karyawan,
            'nama_karyawan' => $karyawan->Nama ?? null,
            'period_key' => $today,
            'due_date' => $today,
            'effective_date' => $today,
            'jumlah' => $metadata['repaid_amount'] ?? null,
            'no_transaksi_ref' => $refNo,
            'status' => $status,
            'reason_code' => $reasonCode,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
