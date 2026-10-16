<?php

namespace App\Services\ESS\Attendance\Leave\Dashboard;

use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use App\Services\ESS\Attendance\Leave\Shared\AturanAkrualCuti;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service dashboard widget Cuti untuk admin HC (non-teknis).
 *
 * Widget yang disediakan:
 * 1. heatmapAkrual()         — grid kalender (skip pre-cutoff). Validasi karyawan due
 *                              vs SUCCESS log, plus monitoring apakah scheduler jalan.
 * 2. trenAkrualBulanan()     — bar trend N bulan untuk MONTHLY_ACCRUAL (success counts)
 * 3. trenAkrualLoyalty()     — bar trend N bulan untuk LOYALTY_ACCRUAL
 * 4. anniversaryBulanIni()   — list karyawan anniversary bulan ini + status loyalty
 *
 * Detail drill-down (untuk modal):
 * - heatmapDetail(date, source)        — list karyawan due + status per karyawan + alasan
 * - trendDetail(periodKey, source)     — list batch + karyawan untuk bulan tertentu
 * - anniversaryDetail(kodeKaryawan)    — detail loyalty status 1 karyawan
 *
 * Semua method baca-only, di-wrap safeRun supaya 1 widget broken tidak menumbangkan dashboard.
 *
 * HEATMAP LOGIC BARU (PR-12):
 * Heatmap MENVALIDASI cuti karyawan, bukan hanya monitor batch. Per tanggal:
 *   1. Scan Karyawan master, hitung due berdasar Tgl_Masuk (monthly) atau anniversary (loyalty).
 *   2. Per karyawan due, cek apakah ada SUCCESS Cuti_Key di N_HRIS_Log_Buku_Cuti.
 *   3. Yang belum SUCCESS: cek alasan dari N_HRIS_Cuti_Process_Batch_Detail (skipped/failed/not_processed).
 *   4. Color cell:
 *      - hijau  → semua karyawan due sudah SUCCESS
 *      - orange → sebagian sudah, sebagian belum
 *      - merah  → tidak ada yang SUCCESS
 *      - idle   → tidak ada karyawan due hari itu
 *   5. Flag scheduler_missing → ada yang due tapi scheduler tidak jalan hari itu.
 */
class CutiDashboardService
{
    private const ID_CUTI_TAHUNAN = 1;

    private const ID_CUTI_LOYALTY = 2;

    private const DEFAULT_HEATMAP_DAYS = 30;

    private const DEFAULT_FUTURE_ACTIVATION_DAYS = 30;

    private const MAX_FUTURE_ACTIVATION_DAYS = 90;

    private const FUTURE_ACTIVATION_DISPLAY_LIMIT = 50;

    private const DEFAULT_TREND_MONTHS = 6;

    private const SYSTEM_REQUESTERS = ['SYSTEM_SCHEDULER', 'SYSTEM_LOYALTY'];

    // Reason code translation untuk pesan friendly ke admin HC
    private const REASON_MAP = [
        'RULE_NOT_FOUND' => 'Tidak ada aturan kelayakan untuk status kontrak karyawan ini',
        'REF_DATE_EMPTY' => 'Kolom acuan tanggal kosong di profil karyawan',
        'MINIMUM_TENURE_NOT_MET' => 'Masa kerja belum mencukupi syarat minimum',
        'DUE_DATE_OUTSIDE_PERIOD' => 'Tanggal due di luar periode target scheduler',
        'BEFORE_CUTOFF' => 'Tanggal due sebelum cutoff sistem',
        'CUTI_KEY_EXISTS' => 'Sudah pernah dapat (cuti key duplikat)',
        'CUTI_KEY_EXISTS_DB_GUARD' => 'Sudah pernah dapat (terdeteksi guard DB)',
        'CUTI_KEY_UNIQUE_VIOLATION' => 'Sudah pernah dapat (constraint DB)',
        'JOIN_DATE_EMPTY' => 'Tanggal masuk kosong di profil karyawan',
        'NOT_PKWTT' => 'Status kontrak bukan PKWTT (loyalty hanya untuk PKWTT)',
        'EXCEPTION' => 'Error sistem saat memproses (cek log teknis)',
        'NOT_PROCESSED' => 'Belum diproses scheduler — kemungkinan scheduler tidak jalan',
        'KARYAWAN_NOT_FOUND' => 'Kode karyawan tidak ditemukan saat recovery',
        'MISSING_STATUS_KONTRAK' => 'Profil karyawan tidak memiliki status kontrak — lengkapi data master karyawan',
    ];

    /** ID level jabatan yang tidak dapat cuti (PHL = Pekerja Harian Lepas). */
    private const EXCLUDED_LEVEL_IDS = [21];

    // ====================================================================
    // 1. HEATMAP AKRUAL — validasi per tanggal
    // ====================================================================

    public function heatmapAkrual(
        int $days = self::DEFAULT_HEATMAP_DAYS,
        ?string $sourceType = null,
        ?int $targetMonth = null,
        ?int $targetYear = null,
    ): array {
        return $this->safeRun('heatmapAkrual', function () use ($days, $sourceType, $targetMonth, $targetYear) {
            $today = Carbon::now()->startOfDay();
            $cutoff = Carbon::parse(CutiProcessBatchService::accrualCutoffDate())->startOfDay();
            $periodKey = null;

            if ($targetMonth && $targetYear) {
                $periodKey = sprintf('%04d-%02d', $targetYear, $targetMonth);
                $periodStart = Carbon::create($targetYear, $targetMonth, 1)->startOfDay();
                $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();

                if ($periodStart->gt($today->copy()->startOfMonth())) {
                    return [
                        'metric' => 0,
                        'items' => [],
                        'meta' => [
                            'start' => $periodStart->toDateString(),
                            'end' => $periodEnd->toDateString(),
                            'cutoff' => $cutoff->toDateString(),
                            'source_filter' => $sourceType ?: 'ALL',
                            'days_in_range' => 0,
                            'stats' => [
                                'ok' => 0,
                                'warn' => 0,
                                'fail' => 0,
                                'idle' => 0,
                                'scheduler_missing' => 0,
                                'total_due' => 0,
                                'total_done' => 0,
                                'total_problem' => 0,
                            ],
                            'month' => $targetMonth,
                            'year' => $targetYear,
                            'period_key' => $periodKey,
                            'blocked_by_future' => true,
                        ],
                    ];
                }

                $start = $periodStart->gte($cutoff) ? $periodStart : $cutoff;
                $end = $periodEnd;

                if ($end->lt($start)) {
                    return [
                        'metric' => 0,
                        'items' => [],
                        'meta' => [
                            'start' => $periodStart->toDateString(),
                            'end' => $periodEnd->toDateString(),
                            'cutoff' => $cutoff->toDateString(),
                            'source_filter' => $sourceType ?: 'ALL',
                            'days_in_range' => 0,
                            'stats' => [
                                'ok' => 0,
                                'warn' => 0,
                                'fail' => 0,
                                'idle' => 0,
                                'scheduler_missing' => 0,
                                'total_due' => 0,
                                'total_done' => 0,
                                'total_problem' => 0,
                            ],
                            'month' => $targetMonth,
                            'year' => $targetYear,
                            'period_key' => $periodKey,
                            'blocked_by_cutoff' => true,
                        ],
                    ];
                }
            } else {
                $startCandidate = $today->copy()->subDays($days - 1);
                $start = $startCandidate->gte($cutoff) ? $startCandidate : $cutoff;
                $end = $today;
            }

            $includeMonthly = ! $sourceType || $sourceType === CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL;
            $includeLoyalty = ! $sourceType || $sourceType === CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL;

            $ctx = $this->buildHeatmapContext($start, $end, $includeMonthly, $includeLoyalty);

            $dayMap = [];
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $dayMap[$cursor->toDateString()] = $this->buildHeatmapCell(
                    $cursor->copy(),
                    $ctx,
                    $includeMonthly,
                    $includeLoyalty,
                );
                $cursor->addDay();
            }

            $items = array_values($dayMap);
            $stats = [
                'ok' => 0,
                'warn' => 0,
                'fail' => 0,
                'idle' => 0,
                'scheduler_missing' => 0,
                'total_due' => 0,
                'total_done' => 0,
                'total_problem' => 0,
            ];
            foreach ($items as $d) {
                $stats[$d['status']] = ($stats[$d['status']] ?? 0) + 1;
                if ($d['scheduler_missing']) {
                    $stats['scheduler_missing']++;
                }
                $stats['total_due'] += $d['due_count'];
                $stats['total_done'] += $d['done_count'];
                $stats['total_problem'] += $d['problem_count'];
            }

            return [
                'metric' => $stats['fail'] + $stats['warn'] + $stats['scheduler_missing'],
                'items' => $items,
                'meta' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'cutoff' => $cutoff->toDateString(),
                    'source_filter' => $sourceType ?: 'ALL',
                    'days_in_range' => count($items),
                    'stats' => $stats,
                    'month' => $targetMonth,
                    'year' => $targetYear,
                    'period_key' => $periodKey,
                ],
            ];
        });
    }

    /**
     * Detail untuk modal klik 1 kotak heatmap — list karyawan due + status + alasan.
     */
    public function heatmapDetail(string $date, ?string $sourceType = null): array
    {
        return $this->safeRun('heatmapDetail', function () use ($date, $sourceType) {
            $day = Carbon::parse($date)->startOfDay();
            $includeMonthly = ! $sourceType || $sourceType === CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL;
            $includeLoyalty = ! $sourceType || $sourceType === CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL;

            $ctx = $this->buildHeatmapContext($day, $day, $includeMonthly, $includeLoyalty);
            $dueList = $this->collectDueListForDate($day, $ctx, $includeMonthly, $includeLoyalty);

            // Grouping by status untuk display di modal
            $grouped = [
                'done' => [],
                'problem' => [], // skipped + failed + not_processed
            ];
            foreach ($dueList as $item) {
                if ($item['status'] === 'done') {
                    $grouped['done'][] = $item;
                } else {
                    $grouped['problem'][] = $item;
                }
            }

            $batchesToday = $ctx['scheduler_batches']->get($day->toDateString(), collect());
            $schedulerRan = [
                'monthly' => $batchesToday
                    ->where('Source_Type', CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL)
                    ->isNotEmpty(),
                'loyalty' => $batchesToday
                    ->where('Source_Type', CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL)
                    ->isNotEmpty(),
            ];

            // Recovery hint hanya untuk yang problem skipped/failed/not_processed.
            // Exclude 'blocked' — recovery tidak akan jalan tanpa status_kontrak.
            $recoveryHint = null;
            $recoverable = collect($grouped['problem'])
                ->reject(fn ($i) => ($i['status'] ?? null) === 'blocked')
                ->values();
            if ($recoverable->isNotEmpty()) {
                $problemBySource = $recoverable->groupBy('source_type');
                $firstSourceProblem = $problemBySource->keys()->first();
                $firstProblemItems = $problemBySource->get($firstSourceProblem);
                $recoveryHint = [
                    'source_type' => $firstSourceProblem,
                    'months' => $firstProblemItems->pluck('period_key')->filter()->unique()->values()->all(),
                    'due_date' => $day->toDateString(),
                    'kode_karyawan' => $firstProblemItems->pluck('kode_karyawan')->filter()->unique()->values()->all(),
                ];
            }

            $isFuture = $day->startOfDay()->gt(Carbon::now()->startOfDay());

            return [
                'metric' => count($dueList),
                'items' => $dueList,
                'meta' => [
                    'date' => $day->toDateString(),
                    'is_future' => $isFuture,
                    'source_filter' => $sourceType ?: 'ALL',
                    'due_count' => count($dueList),
                    'done_count' => count($grouped['done']),
                    'problem_count' => count($grouped['problem']),
                    'scheduler_ran' => $schedulerRan,
                    'scheduler_batches' => $batchesToday->values(),
                    'grouped' => $grouped,
                    'recovery_hint' => $recoveryHint,
                ],
            ];
        });
    }

    /**
     * Build context (pre-fetch semua data yang dibutuhkan untuk evaluasi heatmap).
     * Dijalankan 1x per request supaya tidak ada N+1 query.
     */
    private function buildHeatmapContext(Carbon $start, Carbon $end, bool $includeMonthly, bool $includeLoyalty): array
    {
        // 1. Karyawan aktif (sekali fetch). Exclude PHL (ID_Level_Jabatan=21).
        $karyawans = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereNotNull('k.Tgl_Masuk')
            ->where(function ($q) {
                // PHL tidak dapat cuti. Tetap include yang ID_Level_Jabatan NULL agar
                // missing data tampil sebagai 'blocked' di heatmap, bukan invisible.
                $q->whereNotIn('k.ID_Level_Jabatan', self::EXCLUDED_LEVEL_IDS)->orWhereNull('k.ID_Level_Jabatan');
            })
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.status_kontrak',
                'k.ID_Level_Jabatan',
                'g.ID_Golongan',
                DB::raw('vds.nama_divisi AS divisi'),
            )
            ->get();

        // 2. Monthly rules
        $monthlyRules = $includeMonthly ? DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->get() : collect();

        // 3. Loyalty rules
        $loyaltyRules = $includeLoyalty
            ? DB::table('HRIS_Hak_Cuti_Tambahan')->whereNull('Status')->orderByDesc('tahun_minimal')->get()
            : collect();

        // 4. SUCCESS logs (Cuti_Key lookup)
        $logsQuery = DB::table('N_HRIS_Log_Buku_Cuti')->whereNotNull('Cuti_Key');
        $startPeriod = $start->format('Y-m');
        $endPeriodCap = $end->copy()->endOfMonth()->format('Y-m');
        $logsQuery->where(function ($q) use (
            $includeMonthly,
            $includeLoyalty,
            $startPeriod,
            $endPeriodCap,
            $start,
            $end,
        ) {
            if ($includeMonthly) {
                $q->orWhere(function ($qq) use ($startPeriod, $endPeriodCap) {
                    $qq->where('Cuti_Key', 'like', 'CUTI_MONTHLY:%')
                        ->whereRaw('SUBSTRING(Cuti_Key, 14, 7) >= ?', [$startPeriod])
                        ->whereRaw('SUBSTRING(Cuti_Key, 14, 7) <= ?', [$endPeriodCap]);
                });
            }
            if ($includeLoyalty) {
                $startYear = $start->year;
                $endYear = $end->year;
                $q->orWhere(function ($qq) use ($startYear, $endYear) {
                    $qq->where('Cuti_Key', 'like', 'CUTI_LOYALTY:%')->whereRaw(
                        'CAST(SUBSTRING(Cuti_Key, 14, 4) AS INT) BETWEEN ? AND ?',
                        [$startYear, $endYear],
                    );
                });
            }
        });
        $logs = $logsQuery->select('Cuti_Key', 'Id_Log', 'Created_At')->get()->keyBy('Cuti_Key');

        // 5. Batch detail untuk lookup alasan skip/fail (per Cuti_Key + composite key)
        $sourceFilter = array_filter([
            $includeMonthly ? CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL : null,
            $includeLoyalty ? CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL : null,
        ]);
        $batchDetails = DB::table('N_HRIS_Cuti_Process_Batch_Detail as d')
            ->join('N_HRIS_Cuti_Process_Batch as b', 'd.Id_Batch', '=', 'b.Id_Batch')
            ->where('b.Is_Dry_Run', 0)
            ->whereIn('d.Source_Type', $sourceFilter)
            ->where('b.Created_At', '>=', $start->copy()->subDays(2)->toDateString())
            ->where('b.Created_At', '<', $end->copy()->addDay()->toDateString())
            ->select(
                'd.Id_Batch',
                'd.Source_Type',
                'd.Period_Key',
                'd.Kode_Karyawan',
                'd.Nama_Karyawan',
                'd.Status',
                'd.Reason_Code',
                'd.Message',
                'd.Cuti_Key',
                'd.Due_Date',
                'b.Batch_Key',
                'b.Triggered_From',
                'b.Requested_By',
                'b.Created_At as Batch_Created_At',
            )
            ->get();

        // 6. Scheduler batches per tanggal (untuk deteksi scheduler jalan/tidak)
        $schedulerBatches = DB::table('N_HRIS_Cuti_Process_Batch')
            ->whereIn('Source_Type', $sourceFilter)
            ->where('Triggered_From', 'COMMAND')
            ->whereIn('Requested_By', self::SYSTEM_REQUESTERS)
            ->where('Is_Dry_Run', 0)
            ->where('Created_At', '>=', $start->toDateString())
            ->where('Created_At', '<', $end->copy()->addDay()->toDateString())
            ->select(
                'Id_Batch',
                'Batch_Key',
                'Source_Type',
                'Status',
                'Created_At',
                'Total_Checked',
                'Total_Success',
                'Total_Failed',
                'Total_Skipped',
            )
            ->get()
            ->groupBy(fn ($b) => Carbon::parse($b->Created_At)->toDateString());

        return [
            'karyawans' => $karyawans,
            'monthly_rules' => $monthlyRules,
            'loyalty_rules' => $loyaltyRules,
            'logs' => $logs,
            'details_by_cuti_key' => $batchDetails->whereNotNull('Cuti_Key')->groupBy('Cuti_Key'),
            'details_by_composite' => $batchDetails->groupBy(
                fn ($d) => "{$d->Source_Type}|{$d->Period_Key}|{$d->Kode_Karyawan}",
            ),
            'scheduler_batches' => $schedulerBatches,
        ];
    }

    private function buildHeatmapCell(Carbon $date, array $ctx, bool $includeMonthly, bool $includeLoyalty): array
    {
        $dueList = $this->collectDueListForDate($date, $ctx, $includeMonthly, $includeLoyalty);

        $totalDue = count($dueList);
        $totalDone = 0;
        foreach ($dueList as $d) {
            if ($d['status'] === 'done') {
                $totalDone++;
            }
        }
        $totalProblem = $totalDue - $totalDone;

        $isFuture = $date->startOfDay()->gt(Carbon::now()->startOfDay());

        // Determine cell status
        if ($isFuture) {
            $cellStatus = 'idle';
            $cellLabel = "Belum jadwal berjalan ({$totalDue} due)";
        } elseif ($totalDue === 0) {
            $cellStatus = 'ok';
            $cellLabel = 'Aman (Tidak ada due)';
        } elseif ($totalDone === $totalDue) {
            $cellStatus = 'ok';
            $cellLabel = "Semua {$totalDue} karyawan sudah dapat";
        } elseif ($totalDone > 0) {
            $cellStatus = 'warn';
            $cellLabel = "Sebagian belum dapat ({$totalDone}/{$totalDue})";
        } else {
            $cellStatus = 'fail';
            $cellLabel = "Belum ada yang dapat (0/{$totalDue})";
        }

        // Scheduler missing check
        $batchesToday = $ctx['scheduler_batches']->get($date->toDateString(), collect());
        $needMonthly =
            $includeMonthly &&
            collect($dueList)->where('source_type', CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL)->isNotEmpty();
        $needLoyalty =
            $includeLoyalty &&
            collect($dueList)->where('source_type', CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL)->isNotEmpty();
        $ranMonthly = $batchesToday
            ->where('Source_Type', CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL)
            ->isNotEmpty();
        $ranLoyalty = $batchesToday
            ->where('Source_Type', CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL)
            ->isNotEmpty();

        $schedulerMissing = ! $isFuture && (($needMonthly && ! $ranMonthly) || ($needLoyalty && ! $ranLoyalty));

        return [
            'date' => $date->toDateString(),
            'day_of_week' => $date->dayOfWeekIso,
            'is_today' => $date->isToday(),
            'status' => $cellStatus,
            'status_label' => $cellLabel,
            'due_count' => $totalDue,
            'done_count' => $totalDone,
            'problem_count' => $totalProblem,
            'scheduler_ran_monthly' => $ranMonthly,
            'scheduler_ran_loyalty' => $ranLoyalty,
            'scheduler_missing' => $schedulerMissing,
            'batch_count' => $batchesToday->count(),
        ];
    }

    /**
     * Untuk 1 tanggal, kumpulkan list karyawan yang due (monthly + loyalty)
     * dan cek status masing-masing.
     */
    private function collectDueListForDate(Carbon $date, array $ctx, bool $includeMonthly, bool $includeLoyalty): array
    {
        $dueList = [];
        $periodKey = $date->format('Y-m');

        foreach ($ctx['karyawans'] as $k) {
            $tglMasuk = Carbon::parse($k->Tgl_Masuk);
            if ($tglMasuk->gt($date)) {
                continue;
            }

            $missingKontrak = $this->isStatusKontrakMissing($k);

            // --- MONTHLY: day-of-month match. Hitung due meski rule tidak match (blocked). ---
            if ($includeMonthly) {
                // Cek apakah hari ini = anniversary day-of-month karyawan
                $base = Carbon::create($date->year, $date->month, 1);
                $dueDay = min($tglMasuk->day, $base->daysInMonth);
                $isMonthlyDueToday = $dueDay === $date->day;

                if ($isMonthlyDueToday) {
                    if ($missingKontrak) {
                        // Tetap masuk dueList sebagai 'blocked' supaya admin sadar
                        $dueList[] = $this->buildBlockedKaryawan(
                            $k,
                            CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL,
                            $periodKey,
                            $date,
                            'MISSING_STATUS_KONTRAK',
                        );
                    } else {
                        $dueDate = $this->calcMonthlyDueDate($k, $date, $ctx['monthly_rules']);
                        if ($dueDate && $dueDate->isSameDay($date)) {
                            $cutiKey = "CUTI_MONTHLY:{$periodKey}:{$k->Kode_Karyawan}:JENIS-".self::ID_CUTI_TAHUNAN;
                            $dueList[] = $this->buildDueKaryawanCheck(
                                $k,
                                $cutiKey,
                                CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL,
                                $periodKey,
                                $date,
                                $ctx,
                            );
                        }
                    }
                }
            }

            // --- LOYALTY: anniversary date match. Hanya untuk PKWTT atau missing kontrak. ---
            if ($includeLoyalty) {
                $base = Carbon::create($date->year, $tglMasuk->month, 1);
                $annivDay = min($tglMasuk->day, $base->daysInMonth);
                $isAnnivToday = $tglMasuk->month === $date->month && $annivDay === $date->day;

                if ($isAnnivToday) {
                    if ($missingKontrak) {
                        $dueList[] = $this->buildBlockedKaryawan(
                            $k,
                            CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL,
                            (string) $date->year,
                            $date,
                            'MISSING_STATUS_KONTRAK',
                        );
                    } elseif ($k->status_kontrak === 'PKWTT') {
                        $loyaltyData = $this->calcLoyaltyDue($k, $date, $ctx['loyalty_rules']);
                        if ($loyaltyData) {
                            $check = $this->buildDueKaryawanCheck(
                                $k,
                                $loyaltyData['cuti_key'],
                                CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL,
                                (string) $date->year,
                                $date,
                                $ctx,
                            );
                            $check['masa_kerja_tahun'] = $loyaltyData['masa_kerja_tahun'];
                            $check['rule_loyalty'] = $loyaltyData['rule_name'];
                            $check['jumlah_loyalty'] = $loyaltyData['tambahan_hari'];
                            $dueList[] = $check;
                        }
                    }
                }
            }
        }

        return $dueList;
    }

    private function isStatusKontrakMissing(object $k): bool
    {
        $sk = $k->status_kontrak ?? null;

        return $sk === null || trim((string) $sk) === '' || trim((string) $sk) === '-';
    }

    /**
     * Build item dueList untuk karyawan yang BLOCKED (data master tidak lengkap),
     * mis. tidak punya status_kontrak. Tetap muncul di tabel "Bermasalah" supaya
     * admin HC sadar dan lengkapi data master.
     */
    private function buildBlockedKaryawan(
        object $k,
        string $sourceType,
        string $periodKey,
        Carbon $date,
        string $reasonCode,
    ): array {
        return [
            'kode_karyawan' => $k->Kode_Karyawan,
            'Kode_Karyawan_Encrypted' => Crypt::encryptString($k->Kode_Karyawan),
            'nama' => $k->Nama,
            'divisi' => $k->divisi ?? null,
            'status_kontrak' => $k->status_kontrak ?? null,
            'tgl_masuk' => $k->Tgl_Masuk,
            'cuti_key' => null,
            'source_type' => $sourceType,
            'source_label' => $sourceType === CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL ? 'Bulanan' : 'Loyalty',
            'period_key' => $periodKey,
            'due_date' => $date->toDateString(),
            'status' => 'blocked',
            'status_tag' => 'blocked',
            'status_label' => 'Tidak bisa diproses: data karyawan tidak lengkap',
            'reason_code' => $reasonCode,
            'message' => self::REASON_MAP[$reasonCode] ?? $reasonCode,
            'log_id' => null,
            'processed_at' => null,
        ];
    }

    /**
     * Hitung due date monthly untuk karyawan pada bulan yang berisi $date.
     * Return null kalau tidak eligible (rule/ref kosong/masa kerja kurang).
     */
    private function calcMonthlyDueDate(object $karyawan, Carbon $date, Collection $rules): ?Carbon
    {
        $rule = $rules->first(fn ($r) => $r->Status_Kontrak == $karyawan->status_kontrak);
        if (! $rule) {
            return null;
        }

        $kolomAcuan = $rule->Ref_Date_Source ?? 'Tgl_Masuk';
        $refDate = $karyawan->$kolomAcuan ?? ($karyawan->Tgl_Masuk ?? null);
        if (! $refDate) {
            return null;
        }

        try {
            $refDateCarbon = Carbon::parse($refDate);
        } catch (\Throwable) {
            return null;
        }

        $periodStart = $date->copy()->startOfMonth();
        $day = min($refDateCarbon->day, $periodStart->daysInMonth);
        $dueDate = Carbon::create($date->year, $date->month, $day)->startOfDay();

        $masaKerjaBulan = $refDateCarbon->diffInMonths($dueDate);
        if ($masaKerjaBulan < ($rule->Bulan_Minimum ?? 0)) {
            return null;
        }

        return $dueDate;
    }

    /**
     * Hitung loyalty due untuk karyawan pada $date (anniversary).
     * Return null kalau tidak eligible.
     */
    private function calcLoyaltyDue(object $karyawan, Carbon $date, Collection $rules): ?array
    {
        try {
            $tglMasuk = Carbon::parse($karyawan->Tgl_Masuk);
        } catch (\Throwable) {
            return null;
        }

        // Anniversary: bulan+hari sama dengan Tgl_Masuk, di tahun $date
        $base = Carbon::create($date->year, $tglMasuk->month, 1);
        $day = min($tglMasuk->day, $base->daysInMonth);
        $annivDate = Carbon::create($date->year, $tglMasuk->month, $day)->startOfDay();

        if (! $annivDate->isSameDay($date)) {
            return null;
        }

        $masaKerjaTahun = $tglMasuk->diffInYears($annivDate);
        if ($masaKerjaTahun <= 0) {
            return null;
        }

        $rule = $rules->first(function ($r) use ($masaKerjaTahun, $karyawan) {
            return $masaKerjaTahun >= ($r->tahun_minimal ?? 0) &&
                ($karyawan->ID_Golongan ?? 0) >= ($r->golongan_min_id ?? 0) &&
                ($karyawan->ID_Golongan ?? 0) <= ($r->golongan_max_id ?? 99);
        });
        if (! $rule) {
            return null;
        }

        return [
            'cuti_key' => "CUTI_LOYALTY:{$date->year}:{$karyawan->Kode_Karyawan}:YEAR-{$masaKerjaTahun}:JENIS-".
                self::ID_CUTI_LOYALTY,
            'masa_kerja_tahun' => $masaKerjaTahun,
            'rule_name' => $rule->nama_rule ?? null,
            'tambahan_hari' => $rule->tambahan_hari ?? null,
        ];
    }

    /**
     * Cek status karyawan untuk Cuti_Key tertentu.
     * Return shape lengkap untuk dipakai modal detail.
     */
    private function buildDueKaryawanCheck(
        object $k,
        string $cutiKey,
        string $sourceType,
        string $periodKey,
        Carbon $date,
        array $ctx,
    ): array {
        $base = [
            'kode_karyawan' => $k->Kode_Karyawan,
            'Kode_Karyawan_Encrypted' => Crypt::encryptString($k->Kode_Karyawan),
            'nama' => $k->Nama,
            'divisi' => $k->divisi ?? null,
            'status_kontrak' => $k->status_kontrak ?? null,
            'tgl_masuk' => $k->Tgl_Masuk,
            'cuti_key' => $cutiKey,
            'source_type' => $sourceType,
            'source_label' => $sourceType === CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL ? 'Bulanan' : 'Loyalty',
            'period_key' => $periodKey,
            'due_date' => $date->toDateString(),
        ];

        // 1. Cek SUCCESS log dulu (paling cepat)
        if ($ctx['logs']->has($cutiKey)) {
            $log = $ctx['logs']->get($cutiKey);

            return array_merge($base, [
                'status' => 'done',
                'status_tag' => 'success',
                'status_label' => 'Sudah dapat',
                'reason_code' => null,
                'message' => null,
                'log_id' => $log->Id_Log,
                'processed_at' => $log->Created_At,
            ]);
        }

        // 2. Cek batch detail (via cuti_key dulu, fallback composite key)
        $details = $ctx['details_by_cuti_key']->get($cutiKey, collect());
        if ($details->isEmpty()) {
            $compositeKey = "{$sourceType}|{$periodKey}|{$k->Kode_Karyawan}";
            $details = $ctx['details_by_composite']->get($compositeKey, collect());
        }

        if ($details->isNotEmpty()) {
            $latest = $details->sortByDesc('Batch_Created_At')->first();
            $statusTag = strtolower(
                str_starts_with((string) $latest->Status, 'SKIPPED') ? 'skipped' : strtolower((string) $latest->Status),
            );

            return array_merge($base, [
                'status' => $latest->Status === CutiProcessBatchService::DETAIL_FAILED ? 'failed' : 'skipped',
                'status_tag' => $statusTag,
                'status_label' => $this->translateStatus($latest->Status, $latest->Reason_Code),
                'reason_code' => $latest->Reason_Code,
                'message' => $latest->Message,
                'log_id' => null,
                'processed_at' => $latest->Batch_Created_At,
                'batch_key' => $latest->Batch_Key,
                'triggered_from' => $latest->Triggered_From,
                'requested_by' => $latest->Requested_By,
            ]);
        }

        // 3. Karyawan due tapi sama sekali tidak ada di batch — scheduler skip dia
        return array_merge($base, [
            'status' => 'not_processed',
            'status_tag' => 'not_processed',
            'status_label' => 'Belum diproses scheduler',
            'reason_code' => 'NOT_PROCESSED',
            'message' => self::REASON_MAP['NOT_PROCESSED'],
            'log_id' => null,
            'processed_at' => null,
        ]);
    }

    private function translateStatus(string $status, ?string $reasonCode): string
    {
        $reason = $reasonCode ? self::REASON_MAP[$reasonCode] ?? $reasonCode : 'alasan tidak tercatat';

        if (str_starts_with($status, 'SKIPPED')) {
            return "Belum dapat: {$reason}";
        }
        if ($status === CutiProcessBatchService::DETAIL_FAILED) {
            return "Gagal: {$reason}";
        }

        return $status;
    }

    // ====================================================================
    // 2 & 3. TREND BAR CHART
    // ====================================================================

    public function trenAkrualBulanan(
        int $months = self::DEFAULT_TREND_MONTHS,
        ?int $endMonth = null,
        ?int $endYear = null,
    ): array {
        return $this->trenInternal(CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL, $months, $endMonth, $endYear);
    }

    public function trenAkrualLoyalty(
        int $months = self::DEFAULT_TREND_MONTHS,
        ?int $endMonth = null,
        ?int $endYear = null,
    ): array {
        return $this->trenInternal(CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL, $months, $endMonth, $endYear);
    }

    private function trenInternal(string $sourceType, int $months, ?int $endMonth = null, ?int $endYear = null): array
    {
        $widgetName = "tren-{$sourceType}";

        return $this->safeRun($widgetName, function () use ($sourceType, $months, $endMonth, $endYear) {
            $isMonthly = $sourceType === CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL;
            $now = Carbon::now();
            $end =
                $endMonth && $endYear ? Carbon::create($endYear, $endMonth, 1)->startOfMonth() : $now->startOfMonth();
            $cutoff = Carbon::parse(CutiProcessBatchService::accrualCutoffDate())->startOfMonth();

            if ($end->gt($now->startOfMonth())) {
                return [
                    'metric' => 0,
                    'items' => [],
                    'meta' => [
                        'source_type' => $sourceType,
                        'months_window' => $months,
                        'cutoff' => $cutoff->toDateString(),
                        'start' => $end->format('Y-m'),
                        'total_expected' => 0,
                        'total_realized' => 0,
                        'total_gap' => 0,
                        'total_blocked' => 0,
                        'coverage_overall' => null,
                        'current_month' => 0,
                        'previous_month' => 0,
                        'delta_percent' => null,
                        'end_month' => $end->month,
                        'end_year' => $end->year,
                        'blocked_by_future' => true,
                    ],
                ];
            }

            if ($end->lt($cutoff)) {
                return [
                    'metric' => 0,
                    'items' => [],
                    'meta' => [
                        'source_type' => $sourceType,
                        'months_window' => $months,
                        'cutoff' => $cutoff->toDateString(),
                        'start' => $end->format('Y-m'),
                        'total_expected' => 0,
                        'total_realized' => 0,
                        'total_gap' => 0,
                        'total_blocked' => 0,
                        'coverage_overall' => null,
                        'current_month' => 0,
                        'previous_month' => 0,
                        'delta_percent' => null,
                        'end_month' => $end->month,
                        'end_year' => $end->year,
                        'blocked_by_cutoff' => true,
                    ],
                ];
            }

            $startCandidate = $end->copy()->subMonths($months - 1);
            $start = $startCandidate->gte($cutoff) ? $startCandidate : $cutoff;

            $ctx = $this->buildHeatmapContext($start, $end->copy()->endOfMonth(), $isMonthly, ! $isMonthly);

            $series = [];
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $series[] = $this->buildTrendBar($cursor->copy(), $sourceType, $ctx);
                $cursor->addMonth();
            }

            $totalExpected = array_sum(array_column($series, 'expected'));
            $totalRealized = array_sum(array_column($series, 'realized'));
            $totalGap = array_sum(array_column($series, 'gap'));
            $totalBlocked = array_sum(array_column($series, 'blocked'));
            $current = end($series) ?: ['realized' => 0, 'expected' => 0];
            $previous = prev($series) ?: ['realized' => 0, 'expected' => 0];
            $deltaPercent =
                ($previous['realized'] ?? 0) > 0
                    ? round((($current['realized'] - $previous['realized']) / $previous['realized']) * 100, 1)
                    : null;
            $coverageOverall = $totalExpected > 0 ? round(($totalRealized / $totalExpected) * 100, 1) : null;

            return [
                'metric' => $totalRealized,
                'items' => $series,
                'meta' => [
                    'source_type' => $sourceType,
                    'months_window' => $months,
                    'cutoff' => $cutoff->toDateString(),
                    'start' => $start->format('Y-m'),
                    'total_expected' => $totalExpected,
                    'total_realized' => $totalRealized,
                    'total_gap' => $totalGap,
                    'total_blocked' => $totalBlocked,
                    'coverage_overall' => $coverageOverall,
                    'current_month' => $current['realized'] ?? 0,
                    'previous_month' => $previous['realized'] ?? 0,
                    'delta_percent' => $deltaPercent,
                    'end_month' => $end->month,
                    'end_year' => $end->year,
                ],
            ];
        });
    }

    /**
     * Hitung 1 baris trend (1 bulan). Kembalikan expected/realized/gap/blocked.
     */
    private function buildTrendBar(Carbon $cursor, string $sourceType, array $ctx): array
    {
        $isMonthly = $sourceType === CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL;
        $periodKey = $cursor->format('Y-m');
        $periodStart = $cursor->copy()->startOfMonth();
        $periodEnd = $cursor->copy()->endOfMonth();

        $expected = 0;
        $blocked = 0;
        $realizedKodes = [];

        foreach ($ctx['karyawans'] as $k) {
            $tglMasuk = Carbon::parse($k->Tgl_Masuk);
            if ($tglMasuk->gt($periodEnd)) {
                continue;
            }

            $missingKontrak = $this->isStatusKontrakMissing($k);

            if ($isMonthly) {
                if ($missingKontrak) {
                    // due day would fall in this period?
                    $day = min($tglMasuk->day, $periodStart->daysInMonth);
                    $wouldDue = Carbon::create($cursor->year, $cursor->month, $day);
                    if ($wouldDue->between($periodStart, $periodEnd)) {
                        $blocked++;
                    }

                    continue;
                }
                $dueDate = $this->calcMonthlyDueDate($k, $cursor, $ctx['monthly_rules']);
                if (! $dueDate || ! $dueDate->between($periodStart, $periodEnd)) {
                    continue;
                }
                $expected++;
                $cutiKey = "CUTI_MONTHLY:{$periodKey}:{$k->Kode_Karyawan}:JENIS-".self::ID_CUTI_TAHUNAN;
                if ($ctx['logs']->has($cutiKey)) {
                    $realizedKodes[$k->Kode_Karyawan] = true;
                }
            } else {
                // Loyalty: anniv jatuh di bulan ini?
                if ($tglMasuk->month !== $cursor->month) {
                    continue;
                }
                if ($missingKontrak) {
                    $blocked++;

                    continue;
                }
                if ($k->status_kontrak !== 'PKWTT') {
                    continue;
                }
                $loyaltyData = $this->calcLoyaltyDue(
                    $k,
                    Carbon::create($cursor->year, $tglMasuk->month, min($tglMasuk->day, $periodStart->daysInMonth)),
                    $ctx['loyalty_rules'],
                );
                if (! $loyaltyData) {
                    continue;
                }
                $expected++;
                if ($ctx['logs']->has($loyaltyData['cuti_key'])) {
                    $realizedKodes[$k->Kode_Karyawan] = true;
                }
            }
        }

        $realized = count($realizedKodes);
        $gap = max(0, $expected - $realized);
        $coverage = $expected > 0 ? round(($realized / $expected) * 100, 1) : null;

        return [
            'period' => $periodKey,
            'label' => $cursor->translatedFormat('M Y'),
            'expected' => $expected,
            'realized' => $realized,
            'gap' => $gap,
            'blocked' => $blocked,
            'coverage_percent' => $coverage,
            // backward compat keys (UI lama):
            'sukses_karyawan' => $realized,
        ];
    }

    /**
     * Detail per bulan: list karyawan eligible bulan itu + status dapat/gap/blocked.
     * Mirror struktur heatmapDetail supaya UI modal bisa pakai template sama.
     */
    public function trendDetail(string $periodKey, string $sourceType): array
    {
        return $this->safeRun('trendDetail', function () use ($periodKey, $sourceType) {
            $isMonthly = $sourceType === CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL;
            $period = Carbon::createFromFormat('Y-m-d', "{$periodKey}-01")->startOfMonth();
            $periodStart = $period->copy()->startOfMonth();
            $periodEnd = $period->copy()->endOfMonth();

            $ctx = $this->buildHeatmapContext($periodStart, $periodEnd, $isMonthly, ! $isMonthly);

            $dueList = [];
            foreach ($ctx['karyawans'] as $k) {
                $tglMasuk = Carbon::parse($k->Tgl_Masuk);
                if ($tglMasuk->gt($periodEnd)) {
                    continue;
                }

                $missingKontrak = $this->isStatusKontrakMissing($k);

                if ($isMonthly) {
                    $day = min($tglMasuk->day, $periodStart->daysInMonth);
                    $wouldDue = Carbon::create($period->year, $period->month, $day);
                    if (! $wouldDue->between($periodStart, $periodEnd)) {
                        continue;
                    }

                    if ($missingKontrak) {
                        $dueList[] = $this->buildBlockedKaryawan(
                            $k,
                            CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL,
                            $periodKey,
                            $wouldDue,
                            'MISSING_STATUS_KONTRAK',
                        );

                        continue;
                    }
                    $dueDate = $this->calcMonthlyDueDate($k, $period, $ctx['monthly_rules']);
                    if (! $dueDate || ! $dueDate->between($periodStart, $periodEnd)) {
                        continue;
                    }
                    $cutiKey = "CUTI_MONTHLY:{$periodKey}:{$k->Kode_Karyawan}:JENIS-".self::ID_CUTI_TAHUNAN;
                    $dueList[] = $this->buildDueKaryawanCheck(
                        $k,
                        $cutiKey,
                        CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL,
                        $periodKey,
                        $dueDate,
                        $ctx,
                    );
                } else {
                    // Loyalty: anniv di bulan ini
                    if ($tglMasuk->month !== $period->month) {
                        continue;
                    }
                    $day = min($tglMasuk->day, $periodStart->daysInMonth);
                    $annivDate = Carbon::create($period->year, $tglMasuk->month, $day);
                    if (! $annivDate->between($periodStart, $periodEnd)) {
                        continue;
                    }

                    if ($missingKontrak) {
                        $dueList[] = $this->buildBlockedKaryawan(
                            $k,
                            CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL,
                            (string) $period->year,
                            $annivDate,
                            'MISSING_STATUS_KONTRAK',
                        );

                        continue;
                    }
                    if ($k->status_kontrak !== 'PKWTT') {
                        continue;
                    }

                    $loyaltyData = $this->calcLoyaltyDue($k, $annivDate, $ctx['loyalty_rules']);
                    if (! $loyaltyData) {
                        continue;
                    }
                    $check = $this->buildDueKaryawanCheck(
                        $k,
                        $loyaltyData['cuti_key'],
                        CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL,
                        (string) $period->year,
                        $annivDate,
                        $ctx,
                    );
                    $check['masa_kerja_tahun'] = $loyaltyData['masa_kerja_tahun'];
                    $check['rule_loyalty'] = $loyaltyData['rule_name'];
                    $check['jumlah_loyalty'] = $loyaltyData['tambahan_hari'];
                    $dueList[] = $check;
                }
            }

            // Grouping
            $grouped = ['done' => [], 'problem' => []];
            foreach ($dueList as $item) {
                $grouped[$item['status'] === 'done' ? 'done' : 'problem'][] = $item;
            }

            // Recovery hint (exclude blocked)
            $recoverable = collect($grouped['problem'])
                ->reject(fn ($i) => ($i['status'] ?? null) === 'blocked')
                ->values();
            $recoveryHint = $recoverable->isNotEmpty()
                ? [
                    'source_type' => $sourceType,
                    'months' => [$periodKey],
                    'kode_karyawan' => $recoverable->pluck('kode_karyawan')->filter()->unique()->values()->all(),
                ]
                : null;

            return [
                'metric' => count($dueList),
                'items' => $dueList,
                'meta' => [
                    'period_key' => $periodKey,
                    'source_type' => $sourceType,
                    'expected' => count($dueList) - count(collect($dueList)->where('status', 'blocked')),
                    'realized' => count($grouped['done']),
                    'gap' => count(collect($grouped['problem'])->reject(fn ($i) => $i['status'] === 'blocked')),
                    'blocked' => count(collect($grouped['problem'])->where('status', 'blocked')),
                    'grouped' => $grouped,
                    'recovery_hint' => $recoveryHint,
                ],
            ];
        });
    }

    /**
     * Monitoring due monthly cuti tahunan untuk 1 bulan.
     * Data ini dipakai card "Anniversary Monthly Cuti": siapa yang seharusnya
     * dapat jatah monthly pada bulan tersebut, siapa yang sudah, dan siapa gap.
     */
    public function monthlyAnniversaryCuti(?int $month = null, ?int $year = null): array
    {
        return $this->safeRun('monthlyAnniversaryCuti', function () use ($month, $year) {
            $now = Carbon::now();
            $targetMonth = $month ?: $now->month;
            $targetYear = $year ?: $now->year;
            $periodKey = sprintf('%04d-%02d', $targetYear, $targetMonth);
            $cutoff = Carbon::parse(CutiProcessBatchService::accrualCutoffDate())->startOfMonth();
            $period = Carbon::create($targetYear, $targetMonth, 1)->startOfMonth();

            if ($period->lt($cutoff)) {
                return [
                    'metric' => 0,
                    'items' => [],
                    'meta' => [
                        'period_key' => $periodKey,
                        'month' => $targetMonth,
                        'year' => $targetYear,
                        'cutoff' => $cutoff->toDateString(),
                        'blocked_by_cutoff' => true,
                        'expected' => 0,
                        'realized' => 0,
                        'gap' => 0,
                        'blocked' => 0,
                        'grouped' => ['done' => [], 'problem' => []],
                        'recovery_hint' => null,
                    ],
                ];
            }

            $detail = $this->trendDetail($periodKey, CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL);
            $items = collect($detail['items'] ?? [])
                ->sortBy(function ($item) {
                    $order = [
                        'failed' => 1,
                        'not_processed' => 2,
                        'skipped' => 3,
                        'blocked' => 4,
                        'done' => 5,
                    ];

                    return ($order[$item['status'] ?? ''] ?? 9).'|'.($item['nama'] ?? '');
                })
                ->values();

            $grouped = [
                'done' => $items->where('status', 'done')->values()->all(),
                'problem' => $items->where('status', '!=', 'done')->values()->all(),
            ];
            $recoverable = collect($grouped['problem'])
                ->reject(fn ($i) => ($i['status'] ?? null) === 'blocked')
                ->values();

            $recoveryHint = $recoverable->isNotEmpty()
                ? [
                    'source_type' => CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL,
                    'months' => [$periodKey],
                    'kode_karyawan' => $recoverable->pluck('kode_karyawan')->filter()->unique()->values()->all(),
                ]
                : null;

            return [
                'metric' => $recoverable->count(),
                'items' => $items->all(),
                'meta' => [
                    'period_key' => $periodKey,
                    'month' => $targetMonth,
                    'year' => $targetYear,
                    'cutoff' => $cutoff->toDateString(),
                    'blocked_by_cutoff' => false,
                    'expected' => $detail['meta']['expected'] ?? 0,
                    'realized' => $detail['meta']['realized'] ?? 0,
                    'gap' => $detail['meta']['gap'] ?? 0,
                    'blocked' => $detail['meta']['blocked'] ?? 0,
                    'grouped' => $grouped,
                    'recovery_hint' => $recoveryHint,
                ],
            ];
        });
    }

    // ====================================================================
    // 4. ANNIVERSARY BULAN INI (LOYALTY WATCH)
    // ====================================================================

    public function anniversaryBulanIni(?int $month = null, ?int $year = null): array
    {
        return $this->safeRun('anniversaryBulanIni', function () use ($month, $year) {
            $now = Carbon::now();
            $targetMonth = $month ?: $now->month;
            $targetYear = $year ?: $now->year;
            $periodKey = sprintf('%04d-%02d', $targetYear, $targetMonth);

            // Scan SEMUA karyawan aktif yg anniv bulan ini (exclude PHL, NOT exclude non-PKWTT)
            // Yang non-PKWTT/blocked tetap muncul agar admin sadar kenapa mereka tidak dapat loyalty
            $rows = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->where('k.Aktif', 'Y')
                ->whereNull('k.Tanggal_Resign')
                ->whereMonth('k.Tgl_Masuk', $targetMonth)
                ->where(function ($q) {
                    $q->whereNotIn('k.ID_Level_Jabatan', self::EXCLUDED_LEVEL_IDS)->orWhereNull('k.ID_Level_Jabatan');
                })
                ->select(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'k.status_kontrak',
                    'k.ID_Level_Jabatan',
                    DB::raw('vds.nama_divisi AS divisi'),
                    'k.Tgl_Masuk',
                )
                ->orderBy('k.Tgl_Masuk')
                ->get();

            $items = $rows
                ->map(function ($r) use ($targetYear, $now) {
                    $tglMasuk = Carbon::parse($r->Tgl_Masuk);

                    $base = Carbon::create($targetYear, $tglMasuk->month, 1);
                    $day = min($tglMasuk->day, $base->daysInMonth);
                    $annivDate = Carbon::create($targetYear, $tglMasuk->month, $day);

                    $masaKerjaTahun = $tglMasuk->diffInYears($annivDate);
                    $missingKontrak = $this->isStatusKontrakMissing($r);
                    $isPKWTT = ($r->status_kontrak ?? null) === 'PKWTT';

                    // Status logic — tambahan: blocked, ineligible_kontrak
                    $status = 'pending';
                    $statusLabel = 'Belum due';
                    $existingLog = null;

                    if ($missingKontrak) {
                        $status = 'blocked';
                        $statusLabel = 'Data tidak lengkap (status kontrak kosong)';
                    } elseif (! $isPKWTT) {
                        $status = 'ineligible';
                        $statusLabel = 'Tidak eligible: bukan PKWTT (status: '.($r->status_kontrak ?: '-').')';
                    } else {
                        // PKWTT — cek apakah sudah dapat loyalty
                        $existingLog = DB::table('N_HRIS_Log_Buku_Cuti')
                            ->where('Cuti_Key', 'like', "CUTI_LOYALTY:{$targetYear}:{$r->Kode_Karyawan}:%")
                            ->orderByDesc('Created_At')
                            ->first();

                        if ($existingLog) {
                            $status = 'done';
                            $statusLabel = 'Sudah dapat';
                        } elseif ($annivDate->lt($now->copy()->startOfDay())) {
                            $status = 'missed';
                            $statusLabel = 'Terlewat (belum dapat)';
                        } elseif ($annivDate->isSameDay($now)) {
                            $status = 'today';
                            $statusLabel = 'Hari ini';
                        }
                    }

                    return [
                        'kode_karyawan' => $r->Kode_Karyawan,
                        'Kode_Karyawan_Encrypted' => Crypt::encryptString($r->Kode_Karyawan),
                        'nama' => $r->Nama,
                        'divisi' => $r->divisi,
                        'status_kontrak' => $r->status_kontrak,
                        'tgl_masuk' => $tglMasuk->toDateString(),
                        'anniv_date' => $annivDate->toDateString(),
                        'masa_kerja_tahun' => $masaKerjaTahun,
                        'status' => $status,
                        'status_label' => $statusLabel,
                        'log_id' => $existingLog?->Id_Log,
                        'log_jumlah' => $existingLog?->Jumlah,
                    ];
                })
                ->sortBy(function ($i) {
                    // Urutan prioritas: missed (urgent) → today → blocked (action) → ineligible → pending → done
                    $order = [
                        'missed' => 1,
                        'today' => 2,
                        'blocked' => 3,
                        'ineligible' => 4,
                        'pending' => 5,
                        'done' => 6,
                    ];

                    return $order[$i['status']] ?? 9;
                })
                ->values();

            $missed = $items->where('status', 'missed');
            $eligibleCount = $items->whereIn('status', ['done', 'missed', 'today', 'pending'])->count();
            $recoveryHint = $missed->isNotEmpty()
                ? [
                    'source_type' => CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL,
                    'months' => [$periodKey],
                    'kode_karyawan' => $missed->pluck('kode_karyawan')->values()->all(),
                ]
                : null;

            return [
                'metric' => $missed->count(),
                'items' => $items,
                'meta' => [
                    'period_key' => $periodKey,
                    'month' => $targetMonth,
                    'year' => $targetYear,
                    'count_eligible' => $eligibleCount,
                    'count_gap' => $missed->count(),
                    'count_done' => $items->where('status', 'done')->count(),
                    'count_missed' => $missed->count(),
                    'count_pending' => $items->where('status', 'pending')->count(),
                    'count_today' => $items->where('status', 'today')->count(),
                    'count_blocked' => $items->where('status', 'blocked')->count(),
                    'count_ineligible' => $items->where('status', 'ineligible')->count(),
                    'recovery_hint' => $recoveryHint,
                ],
            ];
        });
    }

    public function anniversaryDetail(string $kodeKaryawan, ?int $year = null): array
    {
        return $this->safeRun('anniversaryDetail', function () use ($kodeKaryawan, $year) {
            $targetYear = $year ?: Carbon::now()->year;

            $karyawan = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->where('k.Kode_Karyawan', $kodeKaryawan)
                ->select(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'k.Tgl_Masuk',
                    'k.status_kontrak',
                    DB::raw('vds.nama_divisi AS divisi'),
                )
                ->first();

            $logs = DB::table('N_HRIS_Log_Buku_Cuti')
                ->where('Cuti_Key', 'like', "CUTI_LOYALTY:%:{$kodeKaryawan}:%")
                ->orderByDesc('Created_At')
                ->limit(20)
                ->get();

            return [
                'metric' => $logs->count(),
                'items' => $logs,
                'meta' => [
                    'karyawan' => $karyawan,
                    'target_year' => $targetYear,
                ],
            ];
        });
    }

    // ====================================================================
    // 5. SALDO CUTI AKAN AKTIF
    // ====================================================================

    public function saldoAkanAktif(int $days = self::DEFAULT_FUTURE_ACTIVATION_DAYS): array
    {
        return $this->safeRun('saldoAkanAktif', function () use ($days) {
            $days = max(1, min(self::MAX_FUTURE_ACTIVATION_DAYS, $days ?: self::DEFAULT_FUTURE_ACTIVATION_DAYS));
            $today = Carbon::now()->startOfDay();
            $end = $today->copy()->addDays($days)->startOfDay();
            $cutoff = Carbon::parse(CutiProcessBatchService::accrualCutoffDate())->startOfDay();

            $rows = DB::table('HRIS_Buku_Cuti as bc')
                ->join('Karyawan as k', 'bc.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
                ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->where('bc.Flag_Aktif', 'Y')
                ->where('bc.Id_Jenis_Cuti', self::ID_CUTI_TAHUNAN)
                ->where('bc.sisa_cuti', '>=', 0)
                ->whereDate('bc.Activated_At', '>', $today->toDateString())
                ->whereDate('bc.Activated_At', '<=', $end->toDateString())
                ->where('k.Aktif', 'Y')
                ->whereNull('k.Tanggal_Resign')
                ->where(function ($q) {
                    $q->whereNotIn('k.ID_Level_Jabatan', self::EXCLUDED_LEVEL_IDS)->orWhereNull('k.ID_Level_Jabatan');
                })
                ->select(
                    'bc.Kode_Karyawan',
                    'bc.Activated_At',
                    'bc.tanggal_expired',
                    'bc.Periode',
                    DB::raw('SUM(COALESCE(bc.sisa_cuti, 0)) AS current_future_balance'),
                    DB::raw('SUM(COALESCE(bc.jumlah_awal_cuti, 0)) AS current_initial_balance'),
                    DB::raw('COUNT(*) AS wallet_count'),
                    'k.Nama',
                    'k.Tgl_Masuk',
                    'k.status_kontrak',
                    'k.ID_Level_Jabatan',
                    'g.ID_Golongan',
                    DB::raw('vds.nama_divisi AS divisi')
                )
                ->groupBy(
                    'bc.Kode_Karyawan',
                    'bc.Activated_At',
                    'bc.tanggal_expired',
                    'bc.Periode',
                    'k.Nama',
                    'k.Tgl_Masuk',
                    'k.status_kontrak',
                    'k.ID_Level_Jabatan',
                    'g.ID_Golongan',
                    'vds.nama_divisi'
                )
                ->orderBy('bc.Activated_At')
                ->get();

            $rules = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->get();
            $existingActivationKeys = $rows
                ->map(fn ($row) => $this->futureActivationGroupKey($row->Kode_Karyawan, $row->Activated_At))
                ->filter()
                ->flip();
            $rows = $rows->concat(
                $this->buildExpectedFutureActivationRows($rules, $today, $end, $existingActivationKeys)
            );

            $stats = [
                'aman' => 0,
                'menunggu_akrual' => 0,
                'perlu_review' => 0,
                'data_tidak_lengkap' => 0,
                'missing_wallet' => 0,
            ];

            $items = $rows
                ->map(function ($row) use ($rules, $today, $cutoff) {
                    $activationDate = Carbon::parse($row->Activated_At)->startOfDay();
                    $calculation = $this->calculateFutureActivationExpectation($row, $rules, $today, $cutoff);
                    $currentBalance = (float) ($row->current_future_balance ?? 0);
                    $potentialGap = max(0, ($calculation['expected_due_until_today'] ?? 0) - $currentBalance);

                    if (! empty($calculation['data_incomplete'])) {
                        $status = 'DATA_TIDAK_LENGKAP';
                    } elseif ($potentialGap > 0) {
                        $status = 'PERLU_REVIEW';
                    } elseif (($calculation['remaining_scheduled'] ?? 0) > 0) {
                        $status = 'MENUNGGU_AKRUAL';
                    } else {
                        $status = 'AMAN';
                    }

                    return [
                        'kode_karyawan' => $row->Kode_Karyawan,
                        'Kode_Karyawan_Encrypted' => Crypt::encryptString($row->Kode_Karyawan),
                        'nama' => $row->Nama,
                        'divisi' => $row->divisi,
                        'status_kontrak' => $row->status_kontrak,
                        'tgl_masuk' => $row->Tgl_Masuk ? Carbon::parse($row->Tgl_Masuk)->toDateString() : null,
                        'activated_at' => $activationDate->toDateString(),
                        'days_until_active' => $today->diffInDays($activationDate, false),
                        'tanggal_expired' => $row->tanggal_expired
                            ? Carbon::parse($row->tanggal_expired)->toDateString()
                            : null,
                        'periode' => $row->Periode,
                        'wallet_count' => (int) ($row->wallet_count ?? 0),
                        'current_future_balance' => $currentBalance,
                        'current_initial_balance' => (float) ($row->current_initial_balance ?? 0),
                        'expected_due_until_today' => (float) ($calculation['expected_due_until_today'] ?? 0),
                        'expected_by_activation' => (float) ($calculation['expected_by_activation'] ?? 0),
                        'remaining_scheduled' => (float) ($calculation['remaining_scheduled'] ?? 0),
                        'potential_gap_now' => (float) $potentialGap,
                        'status' => $status,
                        'status_label' => $this->futureActivationStatusLabel($status),
                        'message' => $calculation['message'] ?? null,
                        'suspect_periods' => $calculation['suspect_periods'] ?? [],
                        'suspect_due_dates' => $calculation['suspect_due_dates'] ?? [],
                        'pre_cutoff_due_count' => (int) ($calculation['pre_cutoff_due_count'] ?? 0),
                        'candidate_source' => $row->candidate_source ?? 'WALLET',
                        'candidate_source_label' => $row->candidate_source_label ?? 'Wallet future',
                    ];
                })
                ->values();

            foreach ($items as $item) {
                $key = strtolower($item['status']);
                $stats[$key] = ($stats[$key] ?? 0) + 1;
                if ((int) ($item['wallet_count'] ?? 0) === 0) {
                    $stats['missing_wallet']++;
                }
            }

            $sorted = $items
                ->sortBy(function ($item) {
                    $order = [
                        'PERLU_REVIEW' => 1,
                        'DATA_TIDAK_LENGKAP' => 2,
                        'MENUNGGU_AKRUAL' => 3,
                        'AMAN' => 4,
                    ];

                    return sprintf(
                        '%s|%02d|%08.2f|%s',
                        $item['activated_at'],
                        $order[$item['status']] ?? 9,
                        9999 - (float) $item['potential_gap_now'],
                        $item['nama'] ?? ''
                    );
                })
                ->values();

            $reviewItems = $sorted->where('status', 'PERLU_REVIEW')->values();
            $recoveryHint = $reviewItems->isNotEmpty()
                ? [
                    'source_type' => CutiProcessBatchService::SOURCE_MONTHLY_ACCRUAL,
                    'months' => $reviewItems->pluck('suspect_periods')->flatten()->filter()->unique()->values()->all(),
                    'due_dates' => $reviewItems->pluck('suspect_due_dates')->flatten()->filter()->unique()->values()->all(),
                    'kode_karyawan' => $reviewItems->pluck('kode_karyawan')->filter()->unique()->values()->all(),
                ]
                : null;

            return [
                'metric' => $stats['perlu_review'],
                'items' => $sorted->take(self::FUTURE_ACTIVATION_DISPLAY_LIMIT)->values()->all(),
                'meta' => [
                    'days' => $days,
                    'start' => $today->toDateString(),
                    'end' => $end->toDateString(),
                    'cutoff' => $cutoff->toDateString(),
                    'total_candidates' => $items->count(),
                    'displayed' => min($items->count(), self::FUTURE_ACTIVATION_DISPLAY_LIMIT),
                    'display_limit' => self::FUTURE_ACTIVATION_DISPLAY_LIMIT,
                    'stats' => $stats,
                    'recovery_hint' => $recoveryHint,
                    'hint' => 'Periode sebelum cutoff tidak bisa dipastikan otomatis. Silakan cross-check dengan log/manual adjustment admin.',
                ],
            ];
        });
    }

    public function karyawanBerpotensiKonflik(): array
    {
        return $this->safeRun('karyawanBerpotensiKonflik', function () {
            $today = Carbon::now()->startOfDay();
            $rules = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->get();
            $activeKaryawans = $this->getActiveCutiKaryawans(includePhl: false);
            $annualWalletSummary = $this->getAnnualWalletSummary($today);

            $items = collect();

            foreach ($activeKaryawans as $karyawan) {
                $tglMasukStatus = $this->validateDateValue($karyawan->Tgl_Masuk ?? null);
                if (! $tglMasukStatus['valid']) {
                    $items->push($this->buildConflictItem(
                        $karyawan,
                        'DATA_MASTER',
                        'HIGH',
                        'Tanggal masuk perlu dilengkapi',
                        'Tanggal masuk dipakai sebagai dasar akrual bulanan dan loyalty. Lengkapi data ini agar sistem bisa menghitung hak cuti.',
                        'Lengkapi data karyawan',
                        [
                            'reason_code' => 'JOIN_DATE_INVALID',
                            'field' => 'Tgl_Masuk',
                            'field_value' => $karyawan->Tgl_Masuk ?? null,
                        ],
                    ));

                    continue;
                }

                if ($this->isStatusKontrakMissing($karyawan)) {
                    $items->push($this->buildConflictItem(
                        $karyawan,
                        'DATA_MASTER',
                        'HIGH',
                        'Status kontrak belum terisi',
                        'Status kontrak menentukan aturan kelayakan cuti. Isi status kontrak sebelum akrual berjalan.',
                        'Lengkapi status kontrak',
                        ['reason_code' => 'STATUS_KONTRAK_MISSING'],
                    ));

                    continue;
                }

                $rule = $rules->first(fn ($r) => $r->Status_Kontrak == $karyawan->status_kontrak);
                if (! $rule) {
                    $items->push($this->buildConflictItem(
                        $karyawan,
                        'RULE',
                        'HIGH',
                        'Aturan cuti belum tersedia',
                        "Status kontrak {$karyawan->status_kontrak} belum punya aturan kelayakan cuti.",
                        'Cek ketentuan cuti',
                        [
                            'reason_code' => 'RULE_NOT_FOUND',
                            'status_kontrak' => $karyawan->status_kontrak,
                        ],
                    ));

                    continue;
                }

                $refDateRaw = $this->resolveRuleRefDateRaw($karyawan, $rule);
                $refDateStatus = $this->validateDateValue($refDateRaw);
                if (! $refDateStatus['valid']) {
                    $kolomAcuan = $rule->Ref_Date_Source ?? 'Tgl_Masuk';
                    $items->push($this->buildConflictItem(
                        $karyawan,
                        'DATA_MASTER',
                        'MEDIUM',
                        'Tanggal acuan cuti belum siap',
                        "Aturan {$karyawan->status_kontrak} memakai {$kolomAcuan} sebagai acuan, tetapi datanya belum valid.",
                        'Lengkapi tanggal acuan',
                        [
                            'reason_code' => 'REF_DATE_INVALID',
                            'field' => $kolomAcuan,
                            'field_value' => $refDateRaw,
                        ],
                    ));

                    continue;
                }

                $refDate = Carbon::parse($refDateRaw)->startOfDay();
                $delayActivation = (int) ($rule->Delay_Aktivasi ?? 0);
                if ($delayActivation > 0) {
                    $activationDate = $refDate->copy()->addMonthsNoOverflow($delayActivation)->startOfDay();
                    $wallet = $annualWalletSummary->get($karyawan->Kode_Karyawan);

                    if ($activationDate->lte($today) && ! $wallet) {
                        $items->push($this->buildConflictItem(
                            $karyawan,
                            'WALLET',
                            'MEDIUM',
                            'Belum ada dompet cuti tahunan',
                            'Karyawan sudah melewati tanggal aktif cuti, tetapi sistem belum menemukan dompet cuti tahunan.',
                            'Review wallet cuti',
                            [
                                'reason_code' => 'ANNUAL_WALLET_MISSING_AFTER_ACTIVATION',
                                'activated_at' => $activationDate->toDateString(),
                                'ref_date' => $refDate->toDateString(),
                            ],
                        ));
                    }
                }
            }

            $items = $items
                ->concat($this->collectPhlWithCutiWallets($today))
                ->concat($this->collectDuplicateFutureWallets($today))
                ->sortBy(function ($item) {
                    $severityOrder = ['HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
                    $date = $item['activated_at'] ?? $item['target_date'] ?? '9999-12-31';

                    return sprintf(
                        '%02d|%s|%s|%s',
                        $severityOrder[$item['severity']] ?? 9,
                        $date,
                        $item['category'],
                        $item['nama'] ?? ''
                    );
                })
                ->take(60)
                ->values();

            $stats = [
                'total' => $items->count(),
                'data_master' => $items->where('category', 'DATA_MASTER')->count(),
                'rule' => $items->where('category', 'RULE')->count(),
                'wallet' => $items->where('category', 'WALLET')->count(),
                'phl' => $items->where('category', 'PHL')->count(),
                'high' => $items->where('severity', 'HIGH')->count(),
                'medium' => $items->where('severity', 'MEDIUM')->count(),
                'low' => $items->where('severity', 'LOW')->count(),
            ];

            return [
                'metric' => $stats['total'],
                'items' => $items->all(),
                'meta' => [
                    'stats' => $stats,
                    'display_limit' => 60,
                    'hint' => 'Daftar ini hanya menampilkan potensi konflik yang bisa ditindaklanjuti HC. Perbaikan teknis database tetap perlu koordinasi IT.',
                    'generated_for' => $today->toDateString(),
                ],
            ];
        });
    }

    // ====================================================================
    // HELPERS
    // ====================================================================

    private function getActiveCutiKaryawans(bool $includePhl = false): Collection
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->when(! $includePhl, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotIn('k.ID_Level_Jabatan', self::EXCLUDED_LEVEL_IDS)
                        ->orWhereNull('k.ID_Level_Jabatan');
                });
            })
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.Tgl_PKWT',
                'k.Tgl_PKWT_Selesai',
                'k.tgl_jadi_karyawan',
                'k.Tanggal_Akhir_Probation',
                'k.status_kontrak',
                'k.ID_Level_Jabatan',
                'g.ID_Golongan',
                'g.nama_level',
                'g.nama_jabatan',
                'g.nama_golongan',
                DB::raw('vds.nama_divisi AS divisi')
            )
            ->get();
    }

    private function getAnnualWalletSummary(Carbon $today): Collection
    {
        return DB::table('HRIS_Buku_Cuti')
            ->where('Id_Jenis_Cuti', self::ID_CUTI_TAHUNAN)
            ->select('Kode_Karyawan')
            ->selectRaw('COUNT(*) AS total_wallet')
            ->selectRaw(
                "SUM(CASE WHEN Flag_Aktif = 'Y' AND tanggal_expired >= ? THEN 1 ELSE 0 END) AS active_wallet",
                [$today->toDateString()],
            )
            ->selectRaw(
                "SUM(CASE WHEN Flag_Aktif = 'Y' AND tanggal_expired >= ? THEN COALESCE(sisa_cuti, 0) ELSE 0 END) AS active_balance",
                [$today->toDateString()],
            )
            ->groupBy('Kode_Karyawan')
            ->get()
            ->keyBy('Kode_Karyawan');
    }

    private function collectPhlWithCutiWallets(Carbon $today): Collection
    {
        return DB::table('Karyawan as k')
            ->join('HRIS_Buku_Cuti as bc', 'bc.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereIn('k.ID_Level_Jabatan', self::EXCLUDED_LEVEL_IDS)
            ->where('bc.Flag_Aktif', 'Y')
            ->where('bc.sisa_cuti', '!=', 0)
            ->whereDate('bc.tanggal_expired', '>=', $today->toDateString())
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.status_kontrak',
                'k.ID_Level_Jabatan',
                'g.ID_Golongan',
                'g.nama_level',
                'g.nama_jabatan',
                'g.nama_golongan',
                DB::raw('vds.nama_divisi AS divisi'),
                DB::raw('COUNT(*) AS wallet_count'),
                DB::raw('SUM(COALESCE(bc.sisa_cuti, 0)) AS total_sisa_cuti')
            )
            ->groupBy(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.status_kontrak',
                'k.ID_Level_Jabatan',
                'g.ID_Golongan',
                'g.nama_level',
                'g.nama_jabatan',
                'g.nama_golongan',
                'vds.nama_divisi'
            )
            ->get()
            ->map(function ($row) {
                return $this->buildConflictItem(
                    $row,
                    'PHL',
                    'HIGH',
                    'PHL memiliki saldo cuti',
                    'Level PHL tidak memiliki hak cuti. Review saldo ini agar tidak masuk proses cuti.',
                    'Review saldo PHL',
                    [
                        'reason_code' => 'PHL_HAS_CUTI_WALLET',
                        'wallet_count' => (int) ($row->wallet_count ?? 0),
                        'total_sisa_cuti' => (float) ($row->total_sisa_cuti ?? 0),
                    ],
                );
            });
    }

    private function collectDuplicateFutureWallets(Carbon $today): Collection
    {
        return DB::table('HRIS_Buku_Cuti as bc')
            ->join('Karyawan as k', 'bc.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where(function ($q) {
                $q->whereNotIn('k.ID_Level_Jabatan', self::EXCLUDED_LEVEL_IDS)->orWhereNull('k.ID_Level_Jabatan');
            })
            ->where('bc.Flag_Aktif', 'Y')
            ->where('bc.Id_Jenis_Cuti', self::ID_CUTI_TAHUNAN)
            ->where('bc.sisa_cuti', '>=', 0)
            ->whereDate('bc.Activated_At', '>', $today->toDateString())
            ->select(
                'bc.Kode_Karyawan',
                'bc.Activated_At',
                'bc.tanggal_expired',
                'bc.Periode',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.status_kontrak',
                'k.ID_Level_Jabatan',
                'g.ID_Golongan',
                'g.nama_level',
                'g.nama_jabatan',
                'g.nama_golongan',
                DB::raw('vds.nama_divisi AS divisi'),
                DB::raw('COUNT(*) AS wallet_count'),
                DB::raw('SUM(COALESCE(bc.sisa_cuti, 0)) AS total_sisa_cuti')
            )
            ->groupBy(
                'bc.Kode_Karyawan',
                'bc.Activated_At',
                'bc.tanggal_expired',
                'bc.Periode',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.status_kontrak',
                'k.ID_Level_Jabatan',
                'g.ID_Golongan',
                'g.nama_level',
                'g.nama_jabatan',
                'g.nama_golongan',
                'vds.nama_divisi'
            )
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(function ($row) {
                return $this->buildConflictItem(
                    $row,
                    'WALLET',
                    'MEDIUM',
                    'Wallet future ganda',
                    'Ada lebih dari satu dompet future untuk tanggal aktif dan periode yang sama. Ini bisa membingungkan saat admin membaca saldo.',
                    'Review wallet future',
                    [
                        'reason_code' => 'DUPLICATE_FUTURE_WALLET',
                        'activated_at' => Carbon::parse($row->Activated_At)->toDateString(),
                        'periode' => $row->Periode,
                        'wallet_count' => (int) ($row->wallet_count ?? 0),
                        'total_sisa_cuti' => (float) ($row->total_sisa_cuti ?? 0),
                    ],
                );
            });
    }

    private function buildConflictItem(
        object $karyawan,
        string $category,
        string $severity,
        string $title,
        string $message,
        string $actionLabel,
        array $metadata = [],
    ): array {
        return [
            'kode_karyawan' => $karyawan->Kode_Karyawan,
            'Kode_Karyawan_Encrypted' => Crypt::encryptString($karyawan->Kode_Karyawan),
            'nama' => $karyawan->Nama,
            'divisi' => $karyawan->divisi ?? null,
            'status_kontrak' => $karyawan->status_kontrak ?? null,
            'tgl_masuk' => isset($karyawan->Tgl_Masuk) && $this->validateDateValue($karyawan->Tgl_Masuk)['valid']
                ? Carbon::parse($karyawan->Tgl_Masuk)->toDateString()
                : null,
            'level' => $karyawan->nama_level ?? null,
            'jabatan' => $karyawan->nama_jabatan ?? null,
            'golongan' => $karyawan->nama_golongan ?? null,
            'category' => $category,
            'category_label' => $this->conflictCategoryLabel($category),
            'severity' => $severity,
            'severity_label' => $this->conflictSeverityLabel($severity),
            'title' => $title,
            'message' => $message,
            'action_label' => $actionLabel,
            'activated_at' => $metadata['activated_at'] ?? null,
            'target_date' => $metadata['activated_at'] ?? $metadata['ref_date'] ?? null,
            'metadata' => $metadata,
        ];
    }

    private function validateDateValue(mixed $value): array
    {
        if (! $value) {
            return ['valid' => false, 'reason' => 'empty'];
        }

        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return ['valid' => false, 'reason' => 'invalid'];
        }

        if ($date->year < 1970) {
            return ['valid' => false, 'reason' => 'too_old'];
        }

        return ['valid' => true, 'date' => $date];
    }

    private function conflictCategoryLabel(string $category): string
    {
        return match ($category) {
            'DATA_MASTER' => 'Data master',
            'RULE' => 'Ketentuan cuti',
            'WALLET' => 'Wallet cuti',
            'PHL' => 'PHL',
            default => $category,
        };
    }

    private function conflictSeverityLabel(string $severity): string
    {
        return match ($severity) {
            'HIGH' => 'Prioritas tinggi',
            'MEDIUM' => 'Perlu review',
            'LOW' => 'Pantau',
            default => $severity,
        };
    }

    private function calculateFutureActivationExpectation(
        object $row,
        Collection $rules,
        Carbon $today,
        Carbon $cutoff,
    ): array {
        $rule = $rules->first(fn ($r) => $r->Status_Kontrak == $row->status_kontrak);
        if (! $rule) {
            return [
                'data_incomplete' => true,
                'message' => self::REASON_MAP['RULE_NOT_FOUND'] ?? 'Tidak ada aturan kelayakan cuti.',
                'expected_due_until_today' => 0,
                'expected_by_activation' => 0,
                'remaining_scheduled' => 0,
            ];
        }

        $kolomAcuan = $rule->Ref_Date_Source ?? 'Tgl_Masuk';
        $refDateRaw = property_exists($row, $kolomAcuan) ? $row->{$kolomAcuan} : null;
        if (! $refDateRaw && $kolomAcuan === 'Join_Date') {
            $refDateRaw = $row->Tgl_Masuk ?? null;
        }
        if (! $refDateRaw) {
            return [
                'data_incomplete' => true,
                'message' => "Kolom acuan {$kolomAcuan} kosong.",
                'expected_due_until_today' => 0,
                'expected_by_activation' => 0,
                'remaining_scheduled' => 0,
            ];
        }

        try {
            $refDate = Carbon::parse($refDateRaw)->startOfDay();
            $joinDate = $row->Tgl_Masuk ? Carbon::parse($row->Tgl_Masuk)->startOfDay() : $refDate->copy();
            $activationDate = Carbon::parse($row->Activated_At)->startOfDay();
            $walletExpiredDate = $row->tanggal_expired ? Carbon::parse($row->tanggal_expired)->startOfDay() : null;
        } catch (\Throwable) {
            return [
                'data_incomplete' => true,
                'message' => 'Tanggal acuan atau tanggal aktif tidak valid.',
                'expected_due_until_today' => 0,
                'expected_by_activation' => 0,
                'remaining_scheduled' => 0,
            ];
        }

        $expectedDueUntilToday = 0.0;
        $expectedByActivation = 0.0;
        $remainingScheduled = 0.0;
        $suspectPeriods = [];
        $suspectDueDates = [];
        $preCutoffDueCount = 0;

        $cursor = $refDate->copy()->startOfMonth();
        $lastMonth = $activationDate->copy()->startOfMonth();
        while ($cursor->lte($lastMonth)) {
            $dueDate = AturanAkrualCuti::jatuhTempoBulanan($refDate, $cursor);
            if ($dueDate->gt($activationDate)) {
                $cursor->addMonthNoOverflow();

                continue;
            }

            $masaKerjaBulan = $refDate->diffInMonths($dueDate);
            if ($masaKerjaBulan < (int) ($rule->Bulan_Minimum ?? 0)) {
                $cursor->addMonthNoOverflow();

                continue;
            }

            $targetActivatedAt = $dueDate->copy();
            if ($masaKerjaBulan < (int) ($rule->Delay_Aktivasi ?? 0)) {
                $targetActivatedAt = $refDate->copy()->addMonths((int) $rule->Delay_Aktivasi)->startOfDay();
            }

            if (! $targetActivatedAt->isSameDay($activationDate)) {
                $cursor->addMonthNoOverflow();

                continue;
            }

            $expiredDate = $dueDate->copy()->addYear()->endOfYear()->startOfDay();
            if ($walletExpiredDate && ! $expiredDate->isSameDay($walletExpiredDate)) {
                $cursor->addMonthNoOverflow();

                continue;
            }

            if ($row->Periode !== null && (int) $row->Periode !== (int) $dueDate->year) {
                $cursor->addMonthNoOverflow();

                continue;
            }

            $amount = $this->monthlyAccrualAmount($row, $joinDate, $dueDate);
            $expectedByActivation += $amount;

            if ($dueDate->lte($today)) {
                $expectedDueUntilToday += $amount;
                $suspectPeriods[] = $dueDate->format('Y-m');
                $suspectDueDates[] = $dueDate->toDateString();

                if ($dueDate->lt($cutoff)) {
                    $preCutoffDueCount++;
                }
            } else {
                $remainingScheduled += $amount;
            }

            $cursor->addMonthNoOverflow();
        }

        return [
            'data_incomplete' => false,
            'message' => null,
            'expected_due_until_today' => $expectedDueUntilToday,
            'expected_by_activation' => $expectedByActivation,
            'remaining_scheduled' => $remainingScheduled,
            'suspect_periods' => array_values(array_unique($suspectPeriods)),
            'suspect_due_dates' => array_values(array_unique($suspectDueDates)),
            'pre_cutoff_due_count' => $preCutoffDueCount,
        ];
    }

    private function buildExpectedFutureActivationRows(
        Collection $rules,
        Carbon $today,
        Carbon $end,
        Collection $existingActivationKeys,
    ): Collection {
        $maxDelay = max(12, (int) ($rules->max('Delay_Aktivasi') ?? 12));
        $joinDateStart = $today->copy()->subMonthsNoOverflow($maxDelay)->subDays(40)->startOfDay();

        $karyawans = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereNotNull('k.Tgl_Masuk')
            ->where(function ($q) use ($joinDateStart, $end) {
                foreach (['Tgl_Masuk', 'tgl_jadi_karyawan', 'Tgl_PKWT', 'Tanggal_Akhir_Probation'] as $column) {
                    $q->orWhere(function ($qq) use ($column, $joinDateStart, $end) {
                        $qq->whereNotNull("k.{$column}")
                            ->whereDate("k.{$column}", '>=', $joinDateStart->toDateString())
                            ->whereDate("k.{$column}", '<=', $end->toDateString());
                    });
                }
            })
            ->where(function ($q) {
                $q->whereNotIn('k.ID_Level_Jabatan', self::EXCLUDED_LEVEL_IDS)->orWhereNull('k.ID_Level_Jabatan');
            })
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.Tgl_PKWT',
                'k.Tgl_PKWT_Selesai',
                'k.tgl_jadi_karyawan',
                'k.Tanggal_Akhir_Probation',
                'k.status_kontrak',
                'k.ID_Level_Jabatan',
                'g.ID_Golongan',
                DB::raw('vds.nama_divisi AS divisi')
            )
            ->get();

        return $karyawans
            ->map(function ($karyawan) use ($rules, $today, $end, $existingActivationKeys) {
                $rule = $rules->first(fn ($r) => $r->Status_Kontrak == $karyawan->status_kontrak);
                $activationDate = null;
                $candidateSource = 'EXPECTED_NO_WALLET';
                $candidateSourceLabel = 'Belum ada wallet future';

                if (! $rule) {
                    try {
                        $activationDate = Carbon::parse($karyawan->Tgl_Masuk)->addYear()->startOfDay();
                    } catch (\Throwable) {
                        return null;
                    }

                    $candidateSource = 'DATA_INCOMPLETE_POTENTIAL';
                    $candidateSourceLabel = 'Potensi aktif, data belum lengkap';
                } else {
                    $refDateRaw = $this->resolveRuleRefDateRaw($karyawan, $rule);
                    if (! $refDateRaw) {
                        return null;
                    }

                    try {
                        $refDate = Carbon::parse($refDateRaw)->startOfDay();
                    } catch (\Throwable) {
                        return null;
                    }

                    $delayActivation = (int) ($rule->Delay_Aktivasi ?? 0);
                    if ($delayActivation <= 0) {
                        return null;
                    }

                    $activationDate = $refDate->copy()->addMonthsNoOverflow($delayActivation)->startOfDay();
                }

                if (! $activationDate || $activationDate->lte($today) || $activationDate->gt($end)) {
                    return null;
                }

                $groupKey = $this->futureActivationGroupKey($karyawan->Kode_Karyawan, $activationDate);
                if (! $groupKey || $existingActivationKeys->has($groupKey)) {
                    return null;
                }

                return (object) [
                    'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                    'Activated_At' => $activationDate->toDateString(),
                    'tanggal_expired' => null,
                    'Periode' => null,
                    'current_future_balance' => 0,
                    'current_initial_balance' => 0,
                    'wallet_count' => 0,
                    'Nama' => $karyawan->Nama,
                    'Tgl_Masuk' => $karyawan->Tgl_Masuk,
                    'Tgl_PKWT' => $karyawan->Tgl_PKWT ?? null,
                    'Tgl_PKWT_Selesai' => $karyawan->Tgl_PKWT_Selesai ?? null,
                    'tgl_jadi_karyawan' => $karyawan->tgl_jadi_karyawan ?? null,
                    'Tanggal_Akhir_Probation' => $karyawan->Tanggal_Akhir_Probation ?? null,
                    'status_kontrak' => $karyawan->status_kontrak,
                    'ID_Level_Jabatan' => $karyawan->ID_Level_Jabatan,
                    'ID_Golongan' => $karyawan->ID_Golongan,
                    'divisi' => $karyawan->divisi,
                    'candidate_source' => $candidateSource,
                    'candidate_source_label' => $candidateSourceLabel,
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveRuleRefDateRaw(object $row, object $rule): mixed
    {
        $kolomAcuan = $rule->Ref_Date_Source ?? 'Tgl_Masuk';
        $refDateRaw = property_exists($row, $kolomAcuan) ? $row->{$kolomAcuan} : null;

        if (! $refDateRaw && $kolomAcuan === 'Join_Date') {
            return $row->Tgl_Masuk ?? null;
        }

        return $refDateRaw;
    }

    private function futureActivationGroupKey(?string $kodeKaryawan, mixed $activatedAt): ?string
    {
        if (! $kodeKaryawan || ! $activatedAt) {
            return null;
        }

        try {
            return $kodeKaryawan.'|'.Carbon::parse($activatedAt)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function monthlyAccrualAmount(object $row, Carbon $joinDate, Carbon $dueDate): float
    {
        return (float) AturanAkrualCuti::jumlahBulanan(
            $row->ID_Golongan ?? null,
            $row->status_kontrak ?? null,
            AturanAkrualCuti::siklusTahunKerja($joinDate, $dueDate),
        );
    }

    private function futureActivationStatusLabel(string $status): string
    {
        return match ($status) {
            'AMAN' => 'Aman',
            'MENUNGGU_AKRUAL' => 'Menunggu akrual',
            'PERLU_REVIEW' => 'Perlu review',
            'DATA_TIDAK_LENGKAP' => 'Data tidak lengkap',
            default => $status,
        };
    }

    private function safeRun(string $widgetName, \Closure $callback): array
    {
        try {
            $result = $callback();
            $result['error'] = null;
            $result['generated_at'] = Carbon::now()->toIso8601String();

            return $result;
        } catch (\Throwable $e) {
            Log::channel('daily')->error("CutiDashboardService::{$widgetName} failed", [
                'widget' => $widgetName,
                'error_class' => $e::class,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'trace_first' => $e->getFile().':'.$e->getLine(),
            ]);

            return [
                'metric' => 0,
                'items' => [],
                'meta' => [],
                'error' => [
                    'message' => 'Widget gagal dimuat, cek log.',
                    'class' => $e::class,
                ],
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        }
    }

    private function isSystemSchedulerRow(object $row): bool
    {
        return ($row->Batch_Source_Type ?? null) !== CutiProcessBatchService::SOURCE_MANUAL_RECOVERY &&
            ($row->Triggered_From ?? null) === 'COMMAND' &&
            in_array($row->Requested_By ?? null, self::SYSTEM_REQUESTERS, true);
    }

    private function isAdminRecoveryRow(object $row): bool
    {
        return ($row->Batch_Source_Type ?? null) === CutiProcessBatchService::SOURCE_MANUAL_RECOVERY;
    }
}
