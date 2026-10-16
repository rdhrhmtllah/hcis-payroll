<?php

namespace App\Services\ESS\Attendance\Shift\Repair;

use App\Helpers\FormatTanggalHelper;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatusHariTanggalSelesaiBackfillService
{
    private const KONFIRMASI_APPLY = 'APPLY_STATUS_HARI_BACKFILL';

    private const STRATEGI_MUTASI = 'DEACTIVATE_AND_INSERT';

    private const KEBIJAKAN_URUTAN = 'PRESERVE_ORIGINAL_CREATED_AT';

    private const KETERANGAN_WORK_OVERLAY = [
        'Auto override: shift applied on OFF day',
        'Auto override because shift applied',
    ];

    private const MAKSIMUM_KANDIDAT_DIPINDAI = 10000;

    public function __construct(private readonly AttendanceDirtyResolverService $dirtyResolver) {}

    public function run(array $input, string $requestId): array
    {
        $dryRun = (bool) ($input['dry_run'] ?? true);
        $context = $this->databaseContext();
        $this->pastikanTargetSesuai($input, $context, $dryRun);

        $candidates = $this->candidates($input);
        if ($candidates->count() > self::MAKSIMUM_KANDIDAT_DIPINDAI) {
            throw new StatusHariBackfillException(
                'Jumlah kandidat melewati batas aman. Persempit scope tanggal atau perusahaan.',
                'STATUS_HARI_BACKFILL_SCOPE_TOO_LARGE',
                422,
            );
        }

        $classified = $candidates
            ->map(fn (object $row) => $this->classify($row))
            ->values();
        $autoFixable = $classified
            ->where('auto_fixable', true)
            ->sortBy('id')
            ->values();
        $manualReview = $classified
            ->where('auto_fixable', false)
            ->sortBy('id')
            ->values();
        $batchSize = (int) ($input['batch_size'] ?? 200);
        $batch = $autoFixable->take($batchSize)->values();
        $dirtyTargets = $this->dirtyTargets($batch->all());
        $planHash = $this->planHash($context, $input, $batch->all());

        $base = [
            'mode' => $dryRun ? 'DRY_RUN' : 'APPLY',
            'request_id' => $requestId,
            'target' => array_merge($context, [
                'requested_environment' => $input['target_environment'],
            ]),
            'scope' => $this->scope($input, $batchSize),
            'summary' => [
                'invalid_total' => $classified->count(),
                'auto_fixable_total' => $autoFixable->count(),
                'manual_review_total' => $manualReview->count(),
                'batch_auto_fixable' => $batch->count(),
                'classification' => $classified->countBy('classification')->sortKeys()->all(),
            ],
            'plan_hash' => $planHash,
            'required_confirmation' => self::KONFIRMASI_APPLY,
            'mutation_plan' => [
                'strategy' => self::STRATEGI_MUTASI,
                'ordering_policy' => self::KEBIJAKAN_URUTAN,
                'deactivate_rows' => $batch->count(),
                'insert_rows' => $batch->count(),
            ],
            'dirty_plan' => [
                'range_count' => $dirtyTargets->count(),
                'user_date_count' => $dirtyTargets->sum(
                    fn (array $target) => Carbon::parse($target['tgl_awal'])
                        ->diffInDays(Carbon::parse($target['tgl_akhir'])) + 1
                ),
                'ranges' => $dirtyTargets->all(),
            ],
            'candidates' => $batch->all(),
            'manual_review_sample' => $manualReview->take(50)->values()->all(),
        ];

        if ($dryRun) {
            Log::info('STATUS_HARI_BACKFILL_DRY_RUN_COMPLETED', [
                'request_id' => $requestId,
                'database' => $context['database'],
                'environment' => $input['target_environment'],
                'invalid_total' => $classified->count(),
                'auto_fixable_total' => $autoFixable->count(),
                'manual_review_total' => $manualReview->count(),
                'batch_auto_fixable' => $batch->count(),
                'plan_hash' => $planHash,
            ]);

            return $base;
        }

        $this->pastikanApplySesuaiDryRun($input, $batch, $planHash);
        $actorId = (int) $input['actor_id'];
        $this->pastikanActorValid($actorId);
        $mutatedAt = FormatTanggalHelper::getCurrentTime('status-hari-backfill')->format('Y-m-d H:i:s.v');

        $mutation = DB::transaction(function () use ($batch, $actorId, $mutatedAt): array {
            $deactivatedRows = 0;
            $insertedRows = 0;
            $replacements = [];

            foreach ($batch as $candidate) {
                $query = DB::table('N_HRIS_Status_Hari_Karyawan')
                    ->where('Id', $candidate['id'])
                    ->where('Kode_Perusahaan', $candidate['kode_perusahaan'])
                    ->where('Kode_Karyawan', $candidate['kode_karyawan'])
                    ->where('Status_Hari', $candidate['status_hari'])
                    ->where('Flag_Aktif', 'Y')
                    ->whereDate('Tgl_Mulai', $candidate['tgl_mulai'])
                    ->whereDate('Tgl_Selesai', $candidate['tgl_selesai_lama'])
                    ->whereColumn('Tgl_Selesai', '<', 'Tgl_Mulai');

                $this->whereNullableValue($query, 'Hari', $candidate['hari']);
                $this->whereNullableValue($query, 'No_Transaksi_Reference', $candidate['no_transaksi_reference']);
                $this->whereNullableValue($query, 'Keterangan', $candidate['keterangan']);
                $this->whereNullableValue($query, 'Created_At', $candidate['created_at']);
                $this->whereNullableValue($query, 'Created_By', $candidate['created_by']);

                if ($candidate['updated_at_lama'] === null) {
                    $query->whereNull('Updated_At');
                } else {
                    $query->where('Updated_At', $candidate['updated_at_lama']);
                }
                $this->whereNullableValue($query, 'Updated_By', $candidate['updated_by_lama']);

                $changed = $query->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => $mutatedAt,
                    'Updated_By' => (string) $actorId,
                ]);

                if ($changed !== 1) {
                    throw new StatusHariBackfillException(
                        'Data berubah setelah dry-run. Jalankan dry-run ulang dan gunakan plan_hash terbaru.',
                        'STATUS_HARI_BACKFILL_STALE_PLAN',
                        409,
                    );
                }

                $newId = DB::table('N_HRIS_Status_Hari_Karyawan')->insertGetId([
                    'Kode_Perusahaan' => $candidate['kode_perusahaan'],
                    'Kode_Karyawan' => $candidate['kode_karyawan'],
                    'Status_Hari' => $candidate['status_hari'],
                    'Hari' => $candidate['hari'],
                    'Tgl_Mulai' => $candidate['tgl_mulai'],
                    'Tgl_Selesai' => $candidate['tgl_selesai_baru'],
                    'Flag_Aktif' => 'Y',
                    'No_Transaksi_Reference' => $candidate['no_transaksi_reference'],
                    'Keterangan' => $candidate['keterangan'],
                    // Resolver status hari memakai Created_At sebagai tie-breaker.
                    // Versi pengganti wajib mempertahankan urutan bisnis row lama.
                    'Created_At' => $candidate['created_at'],
                    'Created_By' => $candidate['created_by'],
                    'Updated_At' => $mutatedAt,
                    'Updated_By' => (string) $actorId,
                ]);

                $deactivatedRows += $changed;
                $insertedRows++;
                $replacements[] = [
                    'old_id' => $candidate['id'],
                    'new_id' => (int) $newId,
                    'kode_perusahaan' => $candidate['kode_perusahaan'],
                    'kode_karyawan' => $candidate['kode_karyawan'],
                    'tgl_mulai' => $candidate['tgl_mulai'],
                    'tgl_selesai_baru' => $candidate['tgl_selesai_baru'],
                ];
            }

            return [
                'deactivated_rows' => $deactivatedRows,
                'inserted_rows' => $insertedRows,
                'replacements' => $replacements,
            ];
        }, 1);

        $dirty = $this->markAttendanceDirty($dirtyTargets, $actorId);
        $remaining = $this->candidates($input)->count();

        Log::info('STATUS_HARI_BACKFILL_APPLIED', [
            'request_id' => $requestId,
            'database' => $context['database'],
            'environment' => $input['target_environment'],
            'actor_id' => $actorId,
            'deactivated_rows' => $mutation['deactivated_rows'],
            'inserted_rows' => $mutation['inserted_rows'],
            'replacements' => $mutation['replacements'],
            'remaining_invalid_in_scope' => $remaining,
            'plan_hash' => $planHash,
            'dirty_mark_success' => (bool) ($dirty['success'] ?? false),
        ]);

        return array_merge($base, [
            'applied' => [
                'deactivated_rows' => $mutation['deactivated_rows'],
                'inserted_rows' => $mutation['inserted_rows'],
                'replacements' => $mutation['replacements'],
                'remaining_invalid_in_scope' => $remaining,
                'dirty_mark' => $dirty,
                'status' => ($dirty['success'] ?? false) ? 'APPLIED' : 'APPLIED_DIRTY_MARK_WARNING',
            ],
        ]);
    }

    private function databaseContext(): array
    {
        $live = DB::selectOne('SELECT DB_NAME() AS Database_Name, @@SERVERNAME AS Server_Name');
        $connectionName = DB::getDefaultConnection();

        return [
            'host' => (string) config("database.connections.{$connectionName}.host", ''),
            'server' => (string) $live->Server_Name,
            'database' => (string) $live->Database_Name,
            'configured_environment' => (string) config('services.hcis.status_hari_backfill.environment', ''),
            'configured_database' => (string) config('services.hcis.status_hari_backfill.database', ''),
        ];
    }

    private function pastikanTargetSesuai(array $input, array $context, bool $dryRun): void
    {
        if (! hash_equals($context['database'], (string) $input['target_database'])) {
            throw new StatusHariBackfillException(
                'Target database request tidak sama dengan koneksi aktif.',
                'STATUS_HARI_BACKFILL_DATABASE_MISMATCH',
                409,
            );
        }

        $configuredEnvironment = $context['configured_environment'];
        if ($configuredEnvironment !== '' && $configuredEnvironment !== $input['target_environment']) {
            throw new StatusHariBackfillException(
                'Target environment request tidak sama dengan konfigurasi deployment.',
                'STATUS_HARI_BACKFILL_ENVIRONMENT_MISMATCH',
                409,
            );
        }

        if ($dryRun) {
            return;
        }

        if (! in_array($input['target_environment'], ['development', 'staging'], true)) {
            throw new StatusHariBackfillException(
                'Mode apply endpoint hanya diizinkan untuk development atau staging. Production wajib melalui SQL Tracker.',
                'STATUS_HARI_BACKFILL_APPLY_ENVIRONMENT_FORBIDDEN',
                403,
            );
        }

        $configuredDatabase = (string) config('services.hcis.status_hari_backfill.database', '');
        $applyEnabled = (bool) config('services.hcis.status_hari_backfill.apply_enabled', false);
        if (! $applyEnabled || $configuredEnvironment === '' || $configuredDatabase === '') {
            throw new StatusHariBackfillException(
                'Mode apply belum diaktifkan secara eksplisit pada deployment ini.',
                'STATUS_HARI_BACKFILL_APPLY_DISABLED',
                403,
            );
        }

        if (! hash_equals($configuredDatabase, $context['database'])) {
            throw new StatusHariBackfillException(
                'Database aktif tidak termasuk target apply yang dikonfigurasi.',
                'STATUS_HARI_BACKFILL_CONFIG_DATABASE_MISMATCH',
                409,
            );
        }
    }

    private function pastikanApplySesuaiDryRun(array $input, $batch, string $planHash): void
    {
        if (($input['confirmation'] ?? null) !== self::KONFIRMASI_APPLY) {
            throw new StatusHariBackfillException(
                'Konfirmasi apply tidak sesuai.',
                'STATUS_HARI_BACKFILL_CONFIRMATION_REQUIRED',
                422,
            );
        }

        if (! hash_equals($planHash, (string) ($input['plan_hash'] ?? ''))) {
            throw new StatusHariBackfillException(
                'plan_hash tidak cocok. Jalankan dry-run ulang.',
                'STATUS_HARI_BACKFILL_PLAN_MISMATCH',
                409,
            );
        }

        if ((int) ($input['expected_auto_fixable_count'] ?? -1) !== $batch->count()) {
            throw new StatusHariBackfillException(
                'Jumlah kandidat apply tidak sama dengan hasil dry-run.',
                'STATUS_HARI_BACKFILL_COUNT_MISMATCH',
                409,
            );
        }
    }

    private function pastikanActorValid(int $actorId): void
    {
        $valid = DB::table('KPI_Users')
            ->where('Id_Users', $actorId)
            ->where('Flag_Active', 'Y')
            ->where('Is_System_Admin', 'Y')
            ->exists();

        if (! $valid) {
            throw new StatusHariBackfillException(
                'actor_id harus menunjuk System Admin aktif.',
                'STATUS_HARI_BACKFILL_ACTOR_INVALID',
                422,
            );
        }
    }

    private function candidates(array $input)
    {
        $sql = <<<'SQL'
            WITH Invalid_Status AS (
                SELECT
                    i.Id,
                    i.Kode_Perusahaan,
                    i.Kode_Karyawan,
                    i.Status_Hari,
                    i.Hari,
                    CAST(i.Tgl_Mulai AS date) AS Tgl_Mulai,
                    CAST(i.Tgl_Selesai AS date) AS Tgl_Selesai,
                    i.No_Transaksi_Reference,
                    i.Keterangan,
                    i.Created_At,
                    i.Created_By,
                    i.Updated_At,
                    i.Updated_By
                FROM N_HRIS_Status_Hari_Karyawan i
                WHERE i.Flag_Aktif = 'Y'
                  AND i.Tgl_Selesai IS NOT NULL
                  AND i.Tgl_Selesai < i.Tgl_Mulai
                  AND (? IS NULL OR i.Kode_Perusahaan = ?)
                  AND (? IS NULL OR i.Tgl_Mulai >= ?)
                  AND (? IS NULL OR i.Tgl_Mulai <= ?)
            )
            SELECT
                i.*,
                source_status.Target_Table AS Source_Target,
                causal_work.Id AS Causal_Work_Id,
                causal_shift.Urut AS Causal_Shift_Urut,
                COALESCE(CAST(causal_work.Tgl_Mulai AS date), CAST(causal_shift.Periode AS date)) AS Causal_Date,
                COALESCE(causal_work.No_Transaksi_Reference, causal_shift.No_Transaksi_Reference) AS Causal_Reference,
                CASE
                    WHEN causal_work.Id IS NOT NULL
                     AND causal_shift.Urut IS NOT NULL
                     AND CAST(causal_work.Tgl_Mulai AS date) <> CAST(causal_shift.Periode AS date)
                        THEN 1
                    ELSE 0
                END AS Causal_Ambiguous
            FROM Invalid_Status i
            OUTER APPLY (
                SELECT TOP (1) detail.Target_Table
                FROM N_HRIS_Transaksi_Status_Hari_Detail detail
                LEFT JOIN N_HRIS_Transaksi_Status_Hari_Det tanggal
                  ON tanggal.Id_Detail = detail.Id
                 AND tanggal.No_Transaksi = detail.No_Transaksi
                 AND CAST(tanggal.Tanggal AS date) = i.Tgl_Mulai
                WHERE detail.No_Transaksi = i.No_Transaksi_Reference
                  AND detail.Kode_Perusahaan = i.Kode_Perusahaan
                  AND detail.Kode_Karyawan = i.Kode_Karyawan
                ORDER BY CASE WHEN tanggal.Id IS NULL THEN 1 ELSE 0 END, detail.Id DESC
            ) source_status
            OUTER APPLY (
                SELECT TOP (1)
                    work_overlay.Id,
                    work_overlay.Tgl_Mulai,
                    work_overlay.No_Transaksi_Reference
                FROM N_HRIS_Status_Hari_Karyawan work_overlay
                WHERE work_overlay.Kode_Perusahaan = i.Kode_Perusahaan
                  AND work_overlay.Kode_Karyawan = i.Kode_Karyawan
                  AND work_overlay.Status_Hari = 'WORK'
                  AND work_overlay.Hari IS NULL
                  AND work_overlay.No_Transaksi_Reference LIKE 'SH%'
                  AND work_overlay.Keterangan IN (
                      'Auto override: shift applied on OFF day',
                      'Auto override because shift applied'
                  )
                  AND work_overlay.Updated_At = i.Updated_At
                  AND ISNULL(work_overlay.Updated_By, '') = ISNULL(i.Updated_By, '')
                  AND work_overlay.Created_At BETWEEN DATEADD(second, -2, i.Updated_At)
                                                  AND DATEADD(second, 2, i.Updated_At)
                ORDER BY ABS(DATEDIFF(millisecond, work_overlay.Created_At, i.Updated_At)),
                         work_overlay.Id DESC
            ) causal_work
            OUTER APPLY (
                SELECT TOP (1)
                    shift.Urut,
                    shift.Periode,
                    shift.No_Transaksi_Reference
                FROM HRIS_Shift_Per_Karyawan shift
                WHERE shift.Kode_Perusahaan = i.Kode_Perusahaan
                  AND shift.Kode_Karyawan = i.Kode_Karyawan
                  AND shift.No_Transaksi_Reference LIKE 'SH%'
                  AND shift.created_at BETWEEN DATEADD(second, -2, i.Updated_At)
                                           AND DATEADD(second, 2, i.Updated_At)
                ORDER BY ABS(DATEDIFF(millisecond, shift.created_at, i.Updated_At)),
                         shift.Urut DESC
            ) causal_shift
            ORDER BY i.Id
            SQL;

        $company = $input['kode_perusahaan'] ?? null;
        $from = $input['tanggal_mulai_dari'] ?? null;
        $until = $input['tanggal_mulai_sampai'] ?? null;

        return collect(DB::select($sql, [$company, $company, $from, $from, $until, $until]));
    }

    private function classify(object $row): array
    {
        $sourceTarget = strtoupper((string) ($row->Source_Target ?? ''));
        $status = strtoupper((string) $row->Status_Hari);
        $isAutoWorkOverlay = $status === 'WORK'
            && $row->Hari === null
            && str_starts_with((string) $row->No_Transaksi_Reference, 'SH')
            && in_array((string) $row->Keterangan, self::KETERANGAN_WORK_OVERLAY, true);
        $start = Carbon::parse($row->Tgl_Mulai)->toDateString();
        $oldEnd = Carbon::parse($row->Tgl_Selesai)->toDateString();
        $causalDate = $row->Causal_Date ? Carbon::parse($row->Causal_Date)->toDateString() : null;
        $causalAmbiguous = (bool) $row->Causal_Ambiguous;
        $classification = 'MANUAL_REVIEW';
        $proposedEnd = null;
        $autoFixable = true;
        $reason = '';

        if ($isAutoWorkOverlay) {
            $classification = 'WORK_AUTO_ONE_DAY';
            $proposedEnd = $start;
            $reason = 'WORK overlay dibuat sebagai record satu hari.';
        } elseif ($sourceTarget === 'SEMENTARA') {
            $classification = 'STATUS_SEMENTARA_ONE_DAY';
            $proposedEnd = $start;
            $reason = 'Transaksi status hari SEMENTARA dibuat sebagai record satu hari.';
        } elseif ($sourceTarget === 'PERMANEN' && $status === 'WORK') {
            $classification = 'STATUS_PERMANEN_WORK_RESTORE_OPEN';
            $proposedEnd = null;
            $reason = 'WORK dari transaksi status hari PERMANEN semula open-ended dan bukan sasaran penutupan OFF.';
        } elseif ($sourceTarget === 'PERMANEN' && $status === 'OFF' && $causalDate !== null && ! $causalAmbiguous) {
            if ($start > $causalDate) {
                $classification = 'STATUS_PERMANEN_OFF_RESTORE_OPEN';
                $proposedEnd = null;
                $reason = 'OFF PERMANEN berawal setelah shift penyebab dan seharusnya tidak ikut ditutup.';
            } else {
                $classification = 'STATUS_PERMANEN_OFF_CLOSE_AT_CAUSAL_SHIFT';
                $proposedEnd = $this->endBeforeOrAtStart($start, $causalDate);
                $reason = 'OFF PERMANEN ditutup tepat sebelum shift penyebab, tanpa menghasilkan rentang terbalik.';
            }
        } elseif ($status === 'OFF' && $causalDate !== null && ! $causalAmbiguous && $start <= $causalDate) {
            $classification = 'OFF_CLOSE_AT_CAUSAL_SHIFT';
            $proposedEnd = $this->endBeforeOrAtStart($start, $causalDate);
            $reason = 'Jejak WORK overlay membuktikan tanggal efektif penutupan OFF.';
        } else {
            $autoFixable = false;
            $reason = 'Bukti mode transaksi atau shift penyebab belum cukup untuk menentukan tanggal akhir asli.';
        }

        return [
            'id' => (int) $row->Id,
            'kode_perusahaan' => (string) $row->Kode_Perusahaan,
            'kode_karyawan' => (string) $row->Kode_Karyawan,
            'status_hari' => $status,
            'hari' => $row->Hari === null ? null : (int) $row->Hari,
            'tgl_mulai' => $start,
            'tgl_selesai_lama' => $oldEnd,
            'tgl_selesai_baru' => $proposedEnd,
            'no_transaksi_reference' => $row->No_Transaksi_Reference,
            'keterangan' => $row->Keterangan,
            'classification' => $classification,
            'auto_fixable' => $autoFixable,
            'reason' => $reason,
            'evidence' => [
                'source_target' => $sourceTarget !== '' ? $sourceTarget : null,
                'causal_work_id' => $row->Causal_Work_Id === null ? null : (int) $row->Causal_Work_Id,
                'causal_shift_urut' => $row->Causal_Shift_Urut === null ? null : (int) $row->Causal_Shift_Urut,
                'causal_date' => $causalDate,
                'causal_reference' => $row->Causal_Reference,
                'causal_ambiguous' => $causalAmbiguous,
            ],
            'created_at' => $row->Created_At,
            'created_by' => $row->Created_By,
            'updated_at_lama' => $row->Updated_At,
            'updated_by_lama' => $row->Updated_By,
        ];
    }

    private function endBeforeOrAtStart(string $start, string $causalDate): string
    {
        $candidate = Carbon::parse($causalDate)->subDay();
        $startDate = Carbon::parse($start);

        return $candidate->lt($startDate) ? $start : $candidate->toDateString();
    }

    private function planHash(array $context, array $input, array $batch): string
    {
        $canonicalRows = array_map(static fn (array $row) => [
            'id' => $row['id'],
            'kode_perusahaan' => $row['kode_perusahaan'],
            'kode_karyawan' => $row['kode_karyawan'],
            'status_hari' => $row['status_hari'],
            'hari' => $row['hari'],
            'tgl_mulai' => $row['tgl_mulai'],
            'tgl_selesai_lama' => $row['tgl_selesai_lama'],
            'tgl_selesai_baru' => $row['tgl_selesai_baru'],
            'no_transaksi_reference' => $row['no_transaksi_reference'],
            'keterangan' => $row['keterangan'],
            'classification' => $row['classification'],
            'created_at' => $row['created_at'],
            'created_by' => $row['created_by'],
            'updated_at_lama' => $row['updated_at_lama'],
            'updated_by_lama' => $row['updated_by_lama'],
        ], $batch);

        return hash('sha256', json_encode([
            'database' => $context['database'],
            'environment' => $input['target_environment'],
            'mutation_strategy' => self::STRATEGI_MUTASI,
            'ordering_policy' => self::KEBIJAKAN_URUTAN,
            'scope' => $this->scope($input, (int) ($input['batch_size'] ?? 200)),
            'rows' => $canonicalRows,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function whereNullableValue($query, string $column, mixed $value): void
    {
        if ($value === null) {
            $query->whereNull($column);

            return;
        }

        $query->where($column, $value);
    }

    private function scope(array $input, int $batchSize): array
    {
        return [
            'kode_perusahaan' => $input['kode_perusahaan'] ?? null,
            'tanggal_mulai_dari' => $input['tanggal_mulai_dari'] ?? null,
            'tanggal_mulai_sampai' => $input['tanggal_mulai_sampai'] ?? null,
            'batch_size' => $batchSize,
        ];
    }

    private function dirtyTargets(array $batch)
    {
        return collect($batch)
            ->map(function (array $row) {
                $end = $row['evidence']['causal_date'] ?? $row['tgl_selesai_baru'] ?? $row['tgl_mulai'];

                if ($row['tgl_selesai_baru'] === null) {
                    $end = $this->dirtyResolver->permanentEffectEndDate($row['tgl_mulai']);
                }

                return [
                    'kode_perusahaan' => $row['kode_perusahaan'],
                    'kode_karyawan' => $row['kode_karyawan'],
                    'tgl_awal' => $row['tgl_mulai'],
                    'tgl_akhir' => max($row['tgl_mulai'], $end),
                ];
            })
            ->unique(fn (array $row) => implode('|', [
                $row['kode_perusahaan'],
                $row['kode_karyawan'],
                $row['tgl_awal'],
                $row['tgl_akhir'],
            ]))
            ->sortBy(fn (array $row) => implode('|', [
                $row['kode_perusahaan'],
                $row['kode_karyawan'],
                $row['tgl_awal'],
                $row['tgl_akhir'],
            ]))
            ->values();
    }

    private function markAttendanceDirty($targets, int $actorId): array
    {
        return $this->dirtyResolver->markMany(
            $targets,
            'STATUS_HARI_TANGGAL_SELESAI_BACKFILLED',
            'STATUS_HARI_BACKFILL_ENDPOINT',
            1,
            (string) $actorId,
        );
    }
}
