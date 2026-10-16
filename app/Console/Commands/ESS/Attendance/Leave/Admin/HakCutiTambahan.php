<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService;
use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use App\Services\ESS\Attendance\Leave\Shared\PeriodeBulananCuti;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HakCutiTambahan extends Command
{
    protected $signature = 'cuti:hakCutiTambahan {--month=} {--kode=} {--dry-run}';

    protected $description = 'Menambahkan Cuti Tambahan (Loyalty) sesuai masa kerja (Anniversary)';

    protected $transactionService;

    protected $batchService;

    protected $cutiNotificationService;

    private const ID_CUTI_LOYALTY = 2;

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
        $today = FormatTanggalHelper::getCurrentTime('HakCutiTambahan');
        $cutoffString = CutiProcessBatchService::accrualCutoffDate();
        $cutoffDate = Carbon::parse($cutoffString)->startOfDay();
        $targetPeriod = $this->resolveTargetPeriod($today);

        if (! $targetPeriod) {
            return 1;
        }

        [$periodStart, $periodEnd] = $targetPeriod;
        $periodKey = $periodStart->format('Y-m');
        $dryRun = (bool) $this->option('dry-run');

        if ($periodEnd->lt($cutoffDate)) {
            $dryRun = true;
            $this->warn(
                "Periode {$periodKey} berada sebelum cutoff {$cutoffString}. Command berjalan sebagai dry-run saja.",
            );
        }

        $this->info(
            "Mencari karyawan Anniversary Periode {$periodKey} ({$periodStart->toDateString()} s/d {$periodEnd->toDateString()})...",
        );

        $batchId = $this->batchService->startBatch(CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL, [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'is_dry_run' => $dryRun,
            'triggered_from' => 'COMMAND',
            'requested_by' => 'SYSTEM_LOYALTY',
            'status' => $dryRun ? CutiProcessBatchService::STATUS_DRY_RUN : CutiProcessBatchService::STATUS_RUNNING,
            'options' => [
                'month' => $this->option('month'),
                'kode' => $this->option('kode'),
                'cutoff_date' => $cutoffString,
            ],
        ]);

        $allRules = DB::table('HRIS_Hak_Cuti_Tambahan')->whereNull('Status')->orderBy('tahun_minimal', 'desc')->get();

        if ($allRules->isEmpty()) {
            $this->info('Tidak ada aturan hak cuti tambahan yang aktif.');
            $this->batchService->completeBatch($batchId, $this->emptySummary(), $dryRun ? CutiProcessBatchService::STATUS_DRY_RUN : CutiProcessBatchService::STATUS_COMPLETED);

            return 0;
        }

        $existingKeys = DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('Cuti_Key', 'like', "CUTI_LOYALTY:{$periodStart->year}:%")
            ->pluck('Cuti_Key')
            ->flip()
            ->all();

        $summary = $this->emptySummary();

        $query = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->whereNull('k.Tanggal_Resign')
            ->where('k.Aktif', 'Y')
            ->where('k.Status_Kontrak', 'PKWTT')
            ->whereMonth('k.Tgl_Masuk', $periodStart->month)
            ->select('k.*', 'g.ID_Golongan')
            ->orderBy('k.Kode_Karyawan');

        $kodeOptions = $this->resolveKodeOptions();
        if (! empty($kodeOptions)) {
            $query->whereIn('k.Kode_Karyawan', $kodeOptions);
        }

        $query->chunk(500, function ($karyawans) use (
            $periodStart,
            $periodEnd,
            $cutoffDate,
            $allRules,
            &$existingKeys,
            &$summary,
            $dryRun,
            $batchId,
        ) {
            foreach ($karyawans as $karyawan) {
                $this->processKaryawan(
                    $karyawan,
                    $periodStart,
                    $periodEnd,
                    $cutoffDate,
                    $allRules,
                    $existingKeys,
                    $summary,
                    $dryRun,
                    $batchId,
                );
            }
        });

        $this->batchService->completeBatch($batchId, $summary, $dryRun ? CutiProcessBatchService::STATUS_DRY_RUN : null);
        CutiProcessBatchService::flushAllDashboardCaches();
        $this->printSummary($summary);

        return 0;
    }

    private function processKaryawan(
        object $karyawan,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $cutoffDate,
        $allRules,
        array &$existingKeys,
        array &$summary,
        bool $dryRun,
        ?int $batchId,
    ): void {
        $summary['checked']++;
        $tglMasuk = Carbon::parse($karyawan->Tgl_Masuk);
        $dueDate = $this->resolveAnniversaryDueDate($tglMasuk, $periodStart->year);
        $periodKey = $periodStart->format('Y-m');

        if ($dueDate->lt($periodStart) || $dueDate->gt($periodEnd)) {
            $summary['skipped_not_due']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, null, null, CutiProcessBatchService::DETAIL_SKIPPED_NOT_DUE, 'DUE_DATE_OUTSIDE_PERIOD', 'Anniversary tidak masuk periode target.');

            return;
        }

        if ($dueDate->lt($cutoffDate)) {
            $summary['legacy_before_cutoff']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, null, null, CutiProcessBatchService::DETAIL_SKIPPED_BEFORE_CUTOFF, 'BEFORE_CUTOFF', 'Due date berada sebelum cutoff.');

            return;
        }

        $masaKerjaTahun = $tglMasuk->diffInYears($dueDate);
        $rule = $allRules->first(function ($r) use ($masaKerjaTahun, $karyawan) {
            return $masaKerjaTahun >= $r->tahun_minimal &&
                $karyawan->ID_Golongan >= $r->golongan_min_id &&
                $karyawan->ID_Golongan <= $r->golongan_max_id;
        });

        if (! $rule) {
            $summary['skipped_rule']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, null, null, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'RULE_NOT_FOUND', "Tidak ada rule loyalty untuk masa kerja {$masaKerjaTahun} tahun.");

            return;
        }

        $cutiKey = "CUTI_LOYALTY:{$dueDate->year}:{$karyawan->Kode_Karyawan}:YEAR-{$masaKerjaTahun}:JENIS-".self::ID_CUTI_LOYALTY;

        if (isset($existingKeys[$cutiKey])) {
            $summary['skipped_existing_key']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $dueDate, $cutiKey, CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY, 'CUTI_KEY_EXISTS', 'Cuti key sudah pernah tercatat.', [
                'jumlah' => $rule->tambahan_hari,
                'masa_kerja_tahun' => $masaKerjaTahun,
            ]);

            return;
        }

        $summary['eligible']++;

        if ($dryRun) {
            $summary['dry_run']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $dueDate, $cutiKey, CutiProcessBatchService::DETAIL_DRY_RUN, null, "Preview loyalty +{$rule->tambahan_hari}.", [
                'jumlah' => $rule->tambahan_hari,
                'masa_kerja_tahun' => $masaKerjaTahun,
                'rule' => $rule->nama_rule,
            ]);

            return;
        }

        $bulanTahun = $dueDate->clone()->format('m-y');
        $refNo = $this->transactionService->getNoTransaction(
            'N_HRIS_Log_Buku_Cuti',
            'LOYALTY',
            'No_Transaksi_Ref',
            $bulanTahun,
        );
        $expiredDate = $dueDate->copy()->addMonths($rule->masa_berlaku_cuti)->toDateString();

        try {
            $outcome = DB::transaction(function () use (
                $karyawan, $rule, $refNo, $expiredDate, $dueDate, $cutiKey, $masaKerjaTahun, $batchId, $periodKey,
            ) {
                $processed = $this->transactionService->creditBalance(
                    $karyawan->Kode_Karyawan,
                    self::ID_CUTI_LOYALTY,
                    $rule->tambahan_hari,
                    $refNo,
                    'REWARD_LOYALTY',
                    "Reward Masa Kerja {$masaKerjaTahun} Tahun ({$rule->nama_rule})",
                    'SYSTEM_LOYALTY',
                    $dueDate->toDateString(),
                    $expiredDate,
                    $dueDate->year,
                    $cutiKey,
                    $dueDate->toDateString(),
                );

                if ($processed === false) {
                    $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $dueDate, $cutiKey, CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY, 'CUTI_KEY_EXISTS_DB_GUARD', 'Cuti key sudah ada saat final guard.', [
                        'jumlah' => $rule->tambahan_hari,
                        'masa_kerja_tahun' => $masaKerjaTahun,
                    ]);

                    return 'skipped';
                }

                $idLog = $this->batchService->findLogIdsByCutiKey($cutiKey)[0] ?? null;
                $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $dueDate, $cutiKey, CutiProcessBatchService::DETAIL_SUCCESS, null, "Berhasil loyalty +{$rule->tambahan_hari}.", [
                    'jumlah' => $rule->tambahan_hari,
                    'masa_kerja_tahun' => $masaKerjaTahun,
                    'rule' => $rule->nama_rule,
                    'no_transaksi_ref' => $refNo,
                    'id_log' => $idLog,
                ]);

                return 'success';
            });

            if ($outcome === 'success') {
                $summary['success']++;
                $existingKeys[$cutiKey] = true;

                $this->cutiNotificationService->notifyAccrual($karyawan->Kode_Karyawan, [
                    'source' => CutiNotificationService::SOURCE_LOYALTY,
                    'jumlah' => $rule->tambahan_hari,
                    'idJenisCuti' => self::ID_CUTI_LOYALTY,
                    'periodKey' => $periodKey,
                    'refNo' => $refNo,
                    'cutiKey' => $cutiKey,
                    'meta' => [
                        'masa_kerja_tahun' => $masaKerjaTahun,
                        'rule' => $rule->nama_rule,
                        'effective_date' => $dueDate->toDateString(),
                        'expired_at' => $expiredDate,
                    ],
                ]);
            } else {
                $summary['skipped_existing_key']++;
                $existingKeys[$cutiKey] = true;
            }
        } catch (QueryException $e) {
            if ($this->batchService->isCutiKeyUniqueViolation($e)) {
                $summary['skipped_existing_key']++;
                $existingKeys[$cutiKey] = true;
                $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $dueDate, $cutiKey, CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY, 'CUTI_KEY_UNIQUE_VIOLATION', 'Cuti key duplicate dideteksi di DB level.', [
                    'jumlah' => $rule->tambahan_hari,
                    'masa_kerja_tahun' => $masaKerjaTahun,
                ]);

                return;
            }
            $this->handleFailure($e, $karyawan, $periodKey, $dueDate, $cutiKey, $rule, $masaKerjaTahun, $refNo, $batchId, $summary);
        } catch (\Throwable $e) {
            $this->handleFailure($e, $karyawan, $periodKey, $dueDate, $cutiKey, $rule, $masaKerjaTahun, $refNo, $batchId, $summary);
        }
    }

    private function handleFailure(
        \Throwable $e,
        object $karyawan,
        string $periodKey,
        Carbon $dueDate,
        string $cutiKey,
        $rule,
        int $masaKerjaTahun,
        ?string $refNo,
        ?int $batchId,
        array &$summary,
    ): void {
        $summary['failed']++;
        $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $dueDate, $cutiKey, CutiProcessBatchService::DETAIL_FAILED, 'EXCEPTION', Str::limit($e->getMessage(), 950), [
            'jumlah' => $rule->tambahan_hari,
            'masa_kerja_tahun' => $masaKerjaTahun,
            'no_transaksi_ref' => $refNo,
        ]);
        Log::channel('HakCutiTambahan')->error('Loyalty accrual failed', [
            'kode_karyawan' => $karyawan->Kode_Karyawan,
            'cuti_key' => $cutiKey,
            'batch_id' => $batchId,
            'error_class' => $e::class,
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);
    }

    private function emptySummary(): array
    {
        return [
            'checked' => 0,
            'eligible' => 0,
            'success' => 0,
            'dry_run' => 0,
            'skipped_existing_key' => 0,
            'skipped_rule' => 0,
            'skipped_not_due' => 0,
            'legacy_before_cutoff' => 0,
            'failed' => 0,
        ];
    }

    /**
     * Jendela periode yang akan diproses, atau null bila masukannya ditolak.
     *
     * Aturannya dipindahkan ke PeriodeBulananCuti. Sampai perubahan ini metode
     * ini ADA DUA — di sini dan di tambahKuotaCutiBulanan — identik sampai ke
     * tanda bacanya, dan keduanya perintah terjadwal yang MENAMBAH saldo cuti.
     */
    private function resolveTargetPeriod(Carbon $today): ?array
    {
        $hasil = PeriodeBulananCuti::untuk($this->option('month'), $today);

        if ($hasil['galat'] !== null) {
            $this->error($hasil['galat']);

            return null;
        }

        return [$hasil['mulai'], $hasil['selesai']];
    }

    private function resolveKodeOptions(): array
    {
        $kode = $this->option('kode');
        if (! $kode) {
            return [];
        }

        return collect(preg_split('/[,\s]+/', (string) $kode))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveAnniversaryDueDate(Carbon $joinDate, int $targetYear): Carbon
    {
        $base = Carbon::create($targetYear, $joinDate->month, 1)->startOfDay();
        $day = min($joinDate->day, $base->daysInMonth);

        return Carbon::create($targetYear, $joinDate->month, $day)->startOfDay();
    }

    private function printSummary(array $summary): void
    {
        $this->info('Proses Selesai.');
        foreach ($summary as $key => $value) {
            $this->line("{$key}: {$value}");
        }
    }

    private function recordBatchDetail(
        ?int $batchId,
        object $karyawan,
        string $periodKey,
        ?Carbon $dueDate,
        ?Carbon $effectiveDate,
        ?string $cutiKey,
        string $status,
        ?string $reasonCode,
        ?string $message,
        array $metadata = [],
    ): void {
        $this->batchService->recordDetail($batchId, [
            'source_type' => CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL,
            'kode_karyawan' => $karyawan->Kode_Karyawan ?? null,
            'nama_karyawan' => $karyawan->Nama ?? null,
            'id_jenis_cuti' => self::ID_CUTI_LOYALTY,
            'period_key' => $periodKey,
            'due_date' => $dueDate?->toDateString(),
            'effective_date' => $effectiveDate?->toDateString(),
            'jumlah' => $metadata['jumlah'] ?? null,
            'cuti_key' => $cutiKey,
            'no_transaksi_ref' => $metadata['no_transaksi_ref'] ?? null,
            'id_log' => $metadata['id_log'] ?? null,
            'status' => $status,
            'reason_code' => $reasonCode,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
