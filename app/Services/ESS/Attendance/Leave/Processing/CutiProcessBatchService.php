<?php

namespace App\Services\ESS\Attendance\Leave\Processing;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CutiProcessBatchService
{
    // ====================================================================
    // Batch Status (header) — kolom dbo.N_HRIS_Cuti_Process_Batch.Status
    // ====================================================================
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_RUNNING = 'RUNNING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_COMPLETED_WITH_ERRORS = 'COMPLETED_WITH_ERRORS';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_DRY_RUN = 'DRY_RUN';

    // ====================================================================
    // Detail Status — kolom dbo.N_HRIS_Cuti_Process_Batch_Detail.Status
    // ====================================================================
    public const DETAIL_SUCCESS = 'SUCCESS';

    public const DETAIL_DRY_RUN = 'DRY_RUN';

    public const DETAIL_FAILED = 'FAILED';

    public const DETAIL_SKIPPED_EXISTING_KEY = 'SKIPPED_EXISTING_KEY';

    public const DETAIL_SKIPPED_NOT_DUE = 'SKIPPED_NOT_DUE';

    public const DETAIL_SKIPPED_BEFORE_CUTOFF = 'SKIPPED_BEFORE_CUTOFF';

    public const DETAIL_SKIPPED_NOT_ELIGIBLE = 'SKIPPED_NOT_ELIGIBLE';

    // ====================================================================
    // Source Type — kolom Source_Type di header & detail
    // ====================================================================
    public const SOURCE_MONTHLY_ACCRUAL = 'MONTHLY_ACCRUAL';

    public const SOURCE_LOYALTY_ACCRUAL = 'LOYALTY_ACCRUAL';

    public const SOURCE_MANUAL_RECOVERY = 'MANUAL_RECOVERY';

    public const SOURCE_MANUAL_ADJUSTMENT = 'MANUAL_ADJUSTMENT';

    public const SOURCE_MATURED_DEBT_PAYMENT = 'MATURED_DEBT_PAYMENT';

    public const SOURCE_PROMOTION_UNLOCK = 'PROMOTION_UNLOCK';

    public const SOURCE_EXPIRED_CLEANUP = 'EXPIRED_CLEANUP';

    // ====================================================================
    // Konfigurasi (sebelumnya di config/cuti.php)
    // ====================================================================
    /** Tanggal cutoff sistem akrual baru. Override via env CUTI_ACCRUAL_CUTOFF_DATE. */
    public const DEFAULT_ACCRUAL_CUTOFF_DATE = '2026-06-01';

    public const DASHBOARD_CACHE_TTL_MINUTES = 5;

    public const DASHBOARD_CACHE_KEY = 'cuti.process.dashboard.v4';

    public const LOG_UNIQUE_INDEX_NAME = 'UX_N_HRIS_Log_Buku_Cuti_Cuti_Key';

    public const WIDGET_CHUNK_SIZE = 500;

    /** Widget keys dashboard cuti. Caller pakai untuk Cache key & flush. */
    public const WIDGET_KEYS = [
        'heatmap-akrual',
        'tren-akrual-bulanan',
        'tren-akrual-loyalty',
        'anniversary-bulan-ini',
    ];

    private const BATCH_TABLE = 'N_HRIS_Cuti_Process_Batch';

    private const DETAIL_TABLE = 'N_HRIS_Cuti_Process_Batch_Detail';

    private ?bool $available = null;

    /**
     * Source type yang valid untuk admin recovery (subset dari semua source type).
     *
     * @return array<int, string>
     */
    public static function recoverableSourceTypes(): array
    {
        return [self::SOURCE_MONTHLY_ACCRUAL, self::SOURCE_LOYALTY_ACCRUAL];
    }

    /**
     * Tanggal cutoff dipakai oleh command/recovery untuk menolak periode legacy.
     * Bisa di-override tanpa deploy via env CUTI_ACCRUAL_CUTOFF_DATE.
     */
    public static function accrualCutoffDate(): string
    {
        return (string) env('CUTI_ACCRUAL_CUTOFF_DATE', self::DEFAULT_ACCRUAL_CUTOFF_DATE);
    }

    /**
     * Flush semua cache dashboard (header + widget utama). Dipanggil oleh:
     * - Command setelah completeBatch (scheduler trigger update)
     * - Controller setelah executeRecovery sukses
     * - Endpoint manual flush dari UI
     */
    public static function flushAllDashboardCaches(): void
    {
        Cache::forget(self::DASHBOARD_CACHE_KEY);
        foreach (self::dashboardWidgetCacheKeys() as $key) {
            Cache::forget("cuti.dashboard.widget.{$key}.v1");
            Cache::forget("cuti.dashboard.widget.{$key}.v2");
            Cache::forget("cuti.dashboard.widget.{$key}.v3");
            Cache::forget("cuti.dashboard.widget.{$key}.v4");
        }
    }

    /**
     * Cache key variants used by dashboard endpoints with query parameters.
     *
     * @return array<int, string>
     */
    private static function dashboardWidgetCacheKeys(): array
    {
        $keys = self::WIDGET_KEYS;

        foreach (['ALL', self::SOURCE_MONTHLY_ACCRUAL, self::SOURCE_LOYALTY_ACCRUAL] as $source) {
            foreach (range(1, 90) as $days) {
                $keys[] = "heatmap-akrual.{$source}.{$days}";
            }
        }

        foreach (range(1, 24) as $months) {
            $keys[] = "tren-akrual-bulanan.{$months}";
            $keys[] = "tren-akrual-loyalty.{$months}";
        }

        $keys[] = 'anniversary-bulan-ini.current.current';

        return array_values(array_unique($keys));
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $this->available = Schema::hasTable(self::BATCH_TABLE) && Schema::hasTable(self::DETAIL_TABLE);
        } catch (\Throwable) {
            $this->available = false;
        }

        return $this->available;
    }

    public function startBatch(string $sourceType, array $attributes = []): ?int
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $now = now();
        $status = $attributes['status'] ?? self::STATUS_RUNNING;
        $isDryRun = (bool) ($attributes['is_dry_run'] ?? false);

        return (int) DB::table(self::BATCH_TABLE)->insertGetId([
            'Batch_Key' => $attributes['batch_key'] ?? $this->makeBatchKey($sourceType),
            'Source_Type' => $sourceType,
            'Status' => $status,
            'Period_Start' => $attributes['period_start'] ?? null,
            'Period_End' => $attributes['period_end'] ?? null,
            'Is_Dry_Run' => $isDryRun ? 1 : 0,
            'Triggered_From' => $attributes['triggered_from'] ?? null,
            'Requested_By' => $attributes['requested_by'] ?? null,
            'Options_Json' => $this->encodeJson($attributes['options'] ?? []),
            'Summary_Json' => $this->encodeJson($attributes['summary'] ?? []),
            'Total_Checked' => 0,
            'Total_Eligible' => 0,
            'Total_Success' => 0,
            'Total_Dry_Run' => 0,
            'Total_Skipped' => 0,
            'Total_Failed' => 0,
            'Started_At' => $now,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);
    }

    public function recordDetail(?int $batchId, array $detail): ?int
    {
        if (! $batchId || ! $this->isAvailable()) {
            return null;
        }

        return (int) DB::table(self::DETAIL_TABLE)->insertGetId($this->buildDetailRow($batchId, $detail));
    }

    /**
     * Bulk insert detail rows. Pakai untuk SKIPPED_* / DRY_RUN yang bisa puluhan-ribuan baris
     * (mis. recovery periode lama, SKIPPED_BEFORE_CUTOFF di scan periode jauh).
     *
     * @param  array<int, array<string, mixed>>  $details
     */
    public function recordDetailsBatch(?int $batchId, array $details, int $chunkSize = 500): int
    {
        if (! $batchId || ! $this->isAvailable() || empty($details)) {
            return 0;
        }

        $inserted = 0;
        foreach (array_chunk($details, $chunkSize) as $chunk) {
            $rows = array_map(fn (array $d) => $this->buildDetailRow($batchId, $d), $chunk);
            DB::table(self::DETAIL_TABLE)->insert($rows);
            $inserted += count($rows);
        }

        return $inserted;
    }

    public function completeBatch(?int $batchId, array $summary, ?string $status = null): void
    {
        if (! $batchId || ! $this->isAvailable()) {
            return;
        }

        $failed = (int) ($summary['failed'] ?? 0);
        $dryRun = (int) ($summary['dry_run'] ?? 0);
        $success = (int) ($summary['success'] ?? 0);
        $checked = (int) ($summary['checked'] ?? 0);

        if (! $status) {
            if ($failed > 0) {
                $status = self::STATUS_COMPLETED_WITH_ERRORS;
            } elseif ($dryRun > 0 && $success === 0) {
                $status = self::STATUS_DRY_RUN;
            } else {
                $status = self::STATUS_COMPLETED;
            }
        }

        DB::table(self::BATCH_TABLE)
            ->where('Id_Batch', $batchId)
            ->update([
                'Status' => $status,
                'Summary_Json' => $this->encodeJson($summary),
                'Total_Checked' => $checked,
                'Total_Eligible' => (int) ($summary['eligible'] ?? 0),
                'Total_Success' => $success,
                'Total_Dry_Run' => $dryRun,
                'Total_Skipped' => $this->countSkipped($summary),
                'Total_Failed' => $failed,
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    public function failBatch(?int $batchId, \Throwable $e, array $summary = []): void
    {
        if (! $batchId || ! $this->isAvailable()) {
            return;
        }

        $summary['fatal_error'] = $e->getMessage();

        DB::table(self::BATCH_TABLE)
            ->where('Id_Batch', $batchId)
            ->update([
                'Status' => self::STATUS_FAILED,
                'Summary_Json' => $this->encodeJson($summary),
                'Total_Checked' => (int) ($summary['checked'] ?? 0),
                'Total_Eligible' => (int) ($summary['eligible'] ?? 0),
                'Total_Success' => (int) ($summary['success'] ?? 0),
                'Total_Dry_Run' => (int) ($summary['dry_run'] ?? 0),
                'Total_Skipped' => $this->countSkipped($summary),
                'Total_Failed' => (int) ($summary['failed'] ?? 0),
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Return SEMUA Id_Log yang punya Cuti_Key tertentu. Diurutkan terbaru duluan.
     *
     * @return array<int, int>
     */
    public function findLogIdsByCutiKey(?string $cutiKey): array
    {
        if (! $cutiKey) {
            return [];
        }

        return DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('Cuti_Key', $cutiKey)
            ->orderByDesc('Created_At')
            ->orderByDesc('Id_Log')
            ->pluck('Id_Log')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function findLogIdByCutiKey(?string $cutiKey): ?int
    {
        return $this->findLogIdsByCutiKey($cutiKey)[0] ?? null;
    }

    /**
     * Deteksi apakah QueryException berasal dari unique violation di
     * N_HRIS_Log_Buku_Cuti(Cuti_Key). SQL Server: error code 2601 / 2627.
     */
    public function isCutiKeyUniqueViolation(QueryException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if (! in_array($code, [2601, 2627], true)) {
            return false;
        }

        return str_contains((string) $e->getMessage(), self::LOG_UNIQUE_INDEX_NAME);
    }

    private function buildDetailRow(int $batchId, array $detail): array
    {
        $now = now();

        return [
            'Id_Batch' => $batchId,
            'Source_Type' => $detail['source_type'] ?? null,
            'Kode_Karyawan' => $detail['kode_karyawan'] ?? null,
            'Nama_Karyawan' => $detail['nama_karyawan'] ?? null,
            'Id_Jenis_Cuti' => $detail['id_jenis_cuti'] ?? null,
            'Period_Key' => $detail['period_key'] ?? null,
            'Due_Date' => $detail['due_date'] ?? null,
            'Effective_Date' => $detail['effective_date'] ?? null,
            'Jumlah' => $detail['jumlah'] ?? null,
            'Cuti_Key' => $detail['cuti_key'] ?? null,
            'No_Transaksi_Ref' => $detail['no_transaksi_ref'] ?? null,
            'Id_Log' => $detail['id_log'] ?? null,
            'Status' => $detail['status'] ?? self::DETAIL_SUCCESS,
            'Reason_Code' => $detail['reason_code'] ?? null,
            'Message' => $detail['message'] ?? null,
            'Metadata_Json' => $this->encodeJson($detail['metadata'] ?? []),
            'Created_At' => $now,
            'Updated_At' => $now,
        ];
    }

    private function makeBatchKey(string $sourceType): string
    {
        return $sourceType.':'.now()->format('YmdHis').':'.strtoupper(substr(uniqid('', true), -8));
    }

    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return json_encode([
                'error' => 'JSON_ENCODE_FAILED',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function countSkipped(array $summary): int
    {
        $skipped = 0;
        foreach ($summary as $key => $value) {
            if (str_starts_with((string) $key, 'skipped_') || $key === 'legacy_before_cutoff') {
                $skipped += (int) $value;
            }
        }

        return $skipped;
    }
}
