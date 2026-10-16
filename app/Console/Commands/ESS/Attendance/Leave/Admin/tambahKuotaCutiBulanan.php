<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService;
use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use App\Services\ESS\Attendance\Leave\Shared\AturanAkrualCuti;
use App\Services\ESS\Attendance\Leave\Shared\PeriodeBulananCuti;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class tambahKuotaCutiBulanan extends Command
{
    protected $signature = 'cuti:tambahCuti {--month=} {--kode=} {--dry-run} {--debug-cycle}';

    protected $description = 'Penambahan kuota cuti karyawan otomatis sesuai aturan kelayakan (bulanan)';

    protected $transactionService;

    protected $batchService;

    protected $cutiNotificationService;

    private const ID_CUTI_TAHUNAN = 1;

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
        $now = FormatTanggalHelper::getCurrentTime('tambahKuotaCutiBulanan');
        $cutoffString = CutiProcessBatchService::accrualCutoffDate();
        $cutoffDate = Carbon::parse($cutoffString)->startOfDay();
        $targetPeriod = $this->resolveTargetPeriod($now);

        if (! $targetPeriod) {
            return 1;
        }

        [$periodStart, $periodEnd] = $targetPeriod;
        $periodKey = $periodStart->format('Y-m');
        $dryRun = (bool) $this->option('dry-run');

        if ($periodEnd->lt($cutoffDate)) {
            $dryRun = true;
            $this->warn("Periode {$periodKey} berada sebelum cutoff {$cutoffString}. Command berjalan sebagai dry-run saja.");
        }

        $this->info("Proses Penambahan Kuota Cuti Bulanan Periode {$periodKey} ({$periodStart->toDateString()} s/d {$periodEnd->toDateString()}) dimulai...");
        Log::channel('tambahKuotaCutiBulanan')->info('Start monthly accrual', [
            'period_key' => $periodKey,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'dry_run' => $dryRun,
        ]);

        $batchId = $this->batchService->startBatch(CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL, [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'is_dry_run' => $dryRun,
            'triggered_from' => 'COMMAND',
            'requested_by' => 'SYSTEM_SCHEDULER',
            'status' => $dryRun ? CutiProcessBatchService::STATUS_DRY_RUN : CutiProcessBatchService::STATUS_RUNNING,
            'options' => [
                'month' => $this->option('month'),
                'kode' => $this->option('kode'),
                'cutoff_date' => $cutoffString,
            ],
        ]);

        $allRules = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->get();
        $existingKeys = DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('Cuti_Key', 'like', "CUTI_MONTHLY:{$periodKey}:%")
            ->pluck('Cuti_Key')
            ->flip()
            ->all();

        $summary = $this->emptySummary();

        $query = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->select('k.*', 'g.ID_Golongan', 'g.nama_golongan')
            ->orderBy('k.Kode_Karyawan');

        $kodeOptions = $this->resolveKodeOptions();
        if (! empty($kodeOptions)) {
            $query->whereIn('k.Kode_Karyawan', $kodeOptions);
        }

        $query->chunk(500, function ($karyawans) use (
            $periodStart, $periodEnd, $cutoffDate, $allRules, &$existingKeys,
            &$summary, $dryRun, $periodKey, $batchId,
        ) {
            foreach ($karyawans as $karyawan) {
                $this->processKaryawan(
                    $karyawan, $periodStart, $periodEnd, $cutoffDate,
                    $allRules, $existingKeys, $summary, $dryRun, $periodKey, $batchId,
                );
            }
        });

        $this->batchService->completeBatch($batchId, $summary, $dryRun ? CutiProcessBatchService::STATUS_DRY_RUN : null);
        CutiProcessBatchService::flushAllDashboardCaches();
        $this->printSummary($summary);
        Log::channel('tambahKuotaCutiBulanan')->info('Proses selesai', array_merge(['date' => $now->toDateString()], $summary));

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
        string $periodKey,
        ?int $batchId,
    ): void {
        $summary['checked']++;

        $rule = $allRules->first(fn ($r) => $r->Status_Kontrak == $karyawan->status_kontrak);

        if (! $rule) {
            $summary['skipped_rule']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, null, null, null, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'RULE_NOT_FOUND', 'Tidak ada aturan kelayakan cuti.');

            return;
        }

        $kolomAcuan = $rule->Ref_Date_Source ?? 'Tgl_Masuk';
        if (empty($karyawan->$kolomAcuan)) {
            $summary['skipped_rule']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, null, null, null, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'REF_DATE_EMPTY', "Kolom acuan {$kolomAcuan} kosong.");

            return;
        }

        $tglStart = Carbon::parse($karyawan->$kolomAcuan);
        $dueDate = AturanAkrualCuti::jatuhTempoBulanan($tglStart, $periodStart);

        if ($dueDate->lt($periodStart) || $dueDate->gt($periodEnd)) {
            $summary['skipped_not_due']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, null, null, CutiProcessBatchService::DETAIL_SKIPPED_NOT_DUE, 'DUE_DATE_OUTSIDE_PERIOD', 'Due date tidak masuk periode target.');

            return;
        }

        if ($dueDate->lt($cutoffDate)) {
            $summary['legacy_before_cutoff']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, null, null, CutiProcessBatchService::DETAIL_SKIPPED_BEFORE_CUTOFF, 'BEFORE_CUTOFF', 'Due date berada sebelum cutoff.');

            return;
        }

        $cutiKey = "CUTI_MONTHLY:{$periodKey}:{$karyawan->Kode_Karyawan}:JENIS-".self::ID_CUTI_TAHUNAN;
        if (isset($existingKeys[$cutiKey])) {
            $summary['skipped_existing_key']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, null, $cutiKey, CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY, 'CUTI_KEY_EXISTS', 'Cuti key sudah pernah tercatat.');

            return;
        }

        $masaKerjaBulan = $tglStart->diffInMonths($dueDate);

        if ($masaKerjaBulan < $rule->Bulan_Minimum) {
            $summary['skipped_rule']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, null, $cutiKey, CutiProcessBatchService::DETAIL_SKIPPED_NOT_ELIGIBLE, 'MINIMUM_TENURE_NOT_MET', "Masa kerja {$masaKerjaBulan} bulan kurang dari minimum {$rule->Bulan_Minimum} bulan.");

            return;
        }

        $summary['eligible']++;

        $cycleInfo = AturanAkrualCuti::siklusTahunKerja(Carbon::parse($karyawan->Tgl_Masuk), $dueDate);

        /*
         * Aturan "berapa hari" tinggal di rumah bersamanya. Dulu ia ditulis di
         * sini sebagai if inline, dan di CutiDashboardService sebagai metode —
         * dua bentuk, satu aturan, tanpa apa pun yang memaksa keduanya sama.
         */
        $tambahHari = AturanAkrualCuti::jumlahBulanan(
            $karyawan->ID_Golongan ?? null,
            $karyawan->status_kontrak ?? null,
            $cycleInfo,
        );

        if ($this->option('debug-cycle')) {
            $this->line(sprintf(
                'DEBUG-CYCLE %s | due=%s | join=%s | cycle_start=%s | month_in_cycle=%s | six_month_point=%s | next_anniversary=%s | second_half=%s | golongan=%s | kontrak=%s | tambah=%s',
                $karyawan->Kode_Karyawan,
                $dueDate->toDateString(),
                Carbon::parse($karyawan->Tgl_Masuk)->toDateString(),
                $cycleInfo['cycle_start']->toDateString(),
                $cycleInfo['month_in_cycle'],
                $cycleInfo['six_month_point']->toDateString(),
                $cycleInfo['next_anniversary']->toDateString(),
                $cycleInfo['is_second_half'] ? 'Y' : 'T',
                $karyawan->ID_Golongan ?? '-',
                $karyawan->status_kontrak ?? '-',
                $tambahHari,
            ));
        }

        $targetActivatedAt = $dueDate->clone();
        if ($masaKerjaBulan < $rule->Delay_Aktivasi) {
            $targetActivatedAt = $tglStart->copy()->addMonths($rule->Delay_Aktivasi);
        }

        if ($dryRun) {
            $summary['dry_run']++;
            $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $targetActivatedAt, $cutiKey, CutiProcessBatchService::DETAIL_DRY_RUN, null, "Preview akrual bulanan +{$tambahHari}.", [
                'jumlah' => $tambahHari,
                'masa_kerja_bulan' => $masaKerjaBulan,
            ]);

            return;
        }

        $bulanTahun = $dueDate->clone()->format('m-y');
        $refNo = $this->transactionService->getNoTransaction(
            'N_HRIS_Log_Buku_Cuti', 'ADDCUTI', 'No_Transaksi_Ref', $bulanTahun,
        );

        $expiredDate = $dueDate->clone()->addYear()->endOfYear()->toDateString();
        $periode = $dueDate->year;
        $bulanIni = $dueDate->clone()->format('F Y');

        try {
            $outcome = DB::transaction(function () use (
                $karyawan, $tambahHari, $refNo, $bulanIni, $targetActivatedAt, $expiredDate, $periode,
                $cutiKey, $dueDate, $batchId, $periodKey,
            ) {
                $processed = $this->transactionService->creditBalance(
                    $karyawan->Kode_Karyawan,
                    self::ID_CUTI_TAHUNAN,
                    $tambahHari,
                    $refNo,
                    'AKRUAL_BULANAN',
                    "Jatah Bulanan {$bulanIni}",
                    'SYSTEM_SCHEDULER',
                    $targetActivatedAt->toDateString(),
                    $expiredDate,
                    $periode,
                    $cutiKey,
                    $dueDate->toDateString(),
                );

                if ($processed === false) {
                    $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $targetActivatedAt, $cutiKey, CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY, 'CUTI_KEY_EXISTS_DB_GUARD', 'Cuti key sudah ada saat final guard.');

                    return 'skipped';
                }

                $idLog = $this->batchService->findLogIdsByCutiKey($cutiKey)[0] ?? null;
                $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $targetActivatedAt, $cutiKey, CutiProcessBatchService::DETAIL_SUCCESS, null, "Berhasil akrual bulanan +{$tambahHari}.", [
                    'jumlah' => $tambahHari,
                    'no_transaksi_ref' => $refNo,
                    'id_log' => $idLog,
                ]);

                return 'success';
            });

            if ($outcome === 'success') {
                $summary['success']++;

                $this->cutiNotificationService->notifyAccrual($karyawan->Kode_Karyawan, [
                    'source' => CutiNotificationService::SOURCE_MONTHLY,
                    'jumlah' => $tambahHari,
                    'idJenisCuti' => self::ID_CUTI_TAHUNAN,
                    'periodKey' => $periodKey,
                    'refNo' => $refNo,
                    'cutiKey' => $cutiKey,
                    'meta' => [
                        'effective_date' => $targetActivatedAt->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'bulan' => $bulanIni,
                    ],
                ]);
            } else {
                $summary['skipped_existing_key']++;
            }
            $existingKeys[$cutiKey] = true;
        } catch (QueryException $e) {
            if ($this->batchService->isCutiKeyUniqueViolation($e)) {
                $summary['skipped_existing_key']++;
                $existingKeys[$cutiKey] = true;
                $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $targetActivatedAt, $cutiKey, CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY, 'CUTI_KEY_UNIQUE_VIOLATION', 'Cuti key duplicate dideteksi di DB level.');

                return;
            }
            $this->handleFailure($e, $karyawan, $periodKey, $dueDate, $targetActivatedAt, $cutiKey, $tambahHari, $refNo, $batchId, $summary);
        } catch (\Throwable $e) {
            $this->handleFailure($e, $karyawan, $periodKey, $dueDate, $targetActivatedAt, $cutiKey, $tambahHari, $refNo, $batchId, $summary);
        }
    }

    private function handleFailure(
        \Throwable $e,
        object $karyawan,
        string $periodKey,
        Carbon $dueDate,
        Carbon $targetActivatedAt,
        string $cutiKey,
        int $tambahHari,
        ?string $refNo,
        ?int $batchId,
        array &$summary,
    ): void {
        $summary['failed']++;
        $this->recordBatchDetail($batchId, $karyawan, $periodKey, $dueDate, $targetActivatedAt, $cutiKey, CutiProcessBatchService::DETAIL_FAILED, 'EXCEPTION', Str::limit($e->getMessage(), 950), [
            'jumlah' => $tambahHari,
            'no_transaksi_ref' => $refNo,
        ]);
        Log::channel('tambahKuotaCutiBulanan')->error('Monthly accrual failed', [
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
     * ini ADA DUA — di sini dan di HakCutiTambahan — identik sampai ke
     * tanda bacanya, dan keduanya perintah terjadwal yang MENAMBAH saldo cuti.
     */
    private function resolveTargetPeriod(Carbon $now): ?array
    {
        $hasil = PeriodeBulananCuti::untuk($this->option('month'), $now);

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
            'source_type' => CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL,
            'kode_karyawan' => $karyawan->Kode_Karyawan ?? null,
            'nama_karyawan' => $karyawan->Nama ?? null,
            'id_jenis_cuti' => self::ID_CUTI_TAHUNAN,
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
