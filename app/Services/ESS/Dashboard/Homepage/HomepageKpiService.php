<?php

namespace App\Services\ESS\Dashboard\Homepage;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Services\KPI\KpiEnforcementService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomepageKpiService
{
    public function getTeamComplianceSummary(array $kodeKaryawans, ?int $year = null): array
    {
        $startedAt = microtime(true);
        $codes = collect($kodeKaryawans)->map(fn ($kode) => $this->normalizeKode($kode))->filter()->unique()->values();

        $year = $year && $year > 0 ? $year : (int) Carbon::now('Asia/Jakarta')->format('Y');

        $monthKeys = collect(range(1, 12))->map(fn ($month) => sprintf('%04d-%02d', $year, $month))->values();

        if ($codes->isEmpty()) {
            return [
                'kpi_year' => $year,
                'monthly_avg_score' => 0,
                'ytd_avg_score' => 0,
                'ytd_le_80_count' => 0,
                'ytd_gt_80_count' => 0,
                'performance_available' => false,
                'ytd_period_label' => 'YTD -',
                'parameter_period_label' => '-',
                'performance_period_label' => '-',
                'trend_range_label' => 'Jan-Des '.$year,
                'scheduled_members' => 0,
                'parameter_scheduled_members' => 0,
                'parameter_coverage_percent' => 0,
                'performance_submitted_percent' => 0,
                'performance_submitted_count' => 0,
                'performance_pending_count' => 0,
                'trend' => $monthKeys
                    ->map(function ($monthKey) {
                        return [
                            'month' => Carbon::createFromFormat('Y-m', $monthKey, 'Asia/Jakarta')->translatedFormat(
                                'M',
                            ),
                            'value' => 0,
                        ];
                    })
                    ->all(),
            ];
        }

        $karyawans = DB::table('Karyawan as k')
            // Kode_Karyawan sudah bersih (uppercase+trim) & ber-index → bandingkan
            // kolom apa adanya (sargable/seek); normalisasi cukup di sisi NILAI.
            ->whereIn('k.Kode_Karyawan', $codes->map(fn ($c) => $this->normalizeKode($c))->all())
            ->select('k.Kode_Karyawan', 'k.UserID_Web', 'k.Tgl_Masuk')
            ->get();

        $userIdByCode = $karyawans
            ->mapWithKeys(fn ($row) => [$this->normalizeKode($row->Kode_Karyawan) => $row->UserID_Web])
            ->all();
        $joinDateByCode = $karyawans
            ->mapWithKeys(function ($row) {
                $kode = $this->normalizeKode($row->Kode_Karyawan ?? '');

                return [$kode => $this->resolveJoinDate($row->Tgl_Masuk ?? null)];
            })
            ->all();

        $codes = collect(array_keys($userIdByCode))->values();
        if ($codes->isEmpty()) {
            return [
                'kpi_year' => $year,
                'monthly_avg_score' => 0,
                'ytd_avg_score' => 0,
                'ytd_le_80_count' => 0,
                'ytd_gt_80_count' => 0,
                'performance_available' => false,
                'ytd_period_label' => 'YTD -',
                'parameter_period_label' => '-',
                'performance_period_label' => '-',
                'trend_range_label' => 'Jan-Des '.$year,
                'scheduled_members' => 0,
                'parameter_scheduled_members' => 0,
                'parameter_coverage_percent' => 0,
                'performance_submitted_percent' => 0,
                'performance_submitted_count' => 0,
                'performance_pending_count' => 0,
                'trend' => $monthKeys
                    ->map(function ($monthKey) {
                        return [
                            'month' => Carbon::createFromFormat('Y-m', $monthKey, 'Asia/Jakarta')->translatedFormat(
                                'M',
                            ),
                            'value' => 0,
                        ];
                    })
                    ->all(),
            ];
        }

        $formScheduleMap = $this->resolveYearlyScheduleMap($codes->all(), 'Flag_Form_KPI', $year);
        $performanceScheduleMap = $this->resolveYearlyScheduleMap($codes->all(), 'Flag_Penilaian_KPI', $year);
        // dd($formScheduleMap, $performanceScheduleMap);
        $activePerformanceSchedules = $this->resolveActiveSchedules($codes->all(), 'Flag_Penilaian_KPI');
        // dd($activePerformanceSchedules);
        $activeFormSchedules = $this->resolveActiveSchedules($codes->all(), 'Flag_Form_KPI');
        // dd($activePerformanceSchedules, $activeFormSchedules);

        $performanceRows = $this->loadApprovedPerformanceRows(
            collect($userIdByCode)->filter()->unique()->values()->all(),
            $year,
        );
        $scoresByUserPeriod = [];
        foreach ($performanceRows as $row) {
            $userId = (string) ($row->Id_User ?? '');
            $period = $this->normalizePeriodValue($row->Periode ?? null);
            if ($userId === '' || ! $period || ! str_starts_with($period, $year.'-')) {
                continue;
            }
            // Baris diurutkan dari yang terbaru, jadi yang pertama masuk dipertahankan —
            // sama seperti getLeaderKpiAnnualRows dan getLeaderKpiMemberDetail. Tanpa
            // penjagaan ini justru baris terlama yang menang.
            if (! isset($scoresByUserPeriod[$userId][$period])) {
                $scoresByUserPeriod[$userId][$period] = (float) ($row->Total_Score ?? 0);
            }
        }

        $submittedUserPeriods = [];
        foreach ($performanceRows as $row) {
            $period = $this->normalizePeriodValue($row->Periode ?? null);
            if (! $period || ! str_starts_with($period, $year.'-')) {
                continue;
            }

            $userId = (string) ($row->Id_User ?? '');
            if ($userId === '') {
                continue;
            }

            if (! isset($submittedUserPeriods[$userId])) {
                $submittedUserPeriods[$userId] = [];
            }
            $submittedUserPeriods[$userId][$period] = true;
        }

        $hasEligibleSchedule = function (string $code, array $scheduleMap) use ($joinDateByCode) {
            $joinDate = $joinDateByCode[$code] ?? null;
            $periods = array_keys((array) ($scheduleMap[$code] ?? []));
            foreach ($periods as $period) {
                if ($this->isEligibleForSchedulePeriod($joinDate, (string) $period)) {
                    return true;
                }
            }

            return false;
        };

        $scheduledMembers = $codes
            ->filter(function ($code) use ($formScheduleMap, $performanceScheduleMap, $hasEligibleSchedule) {
                return $hasEligibleSchedule((string) $code, $formScheduleMap) ||
                    $hasEligibleSchedule((string) $code, $performanceScheduleMap);
            })
            ->count();
        // dd($scheduledMembers);

        $parameterScheduledMembers = $codes
            ->filter(function ($code) use ($formScheduleMap, $hasEligibleSchedule) {
                return $hasEligibleSchedule((string) $code, $formScheduleMap);
            })
            ->count();

        $parameterCoveragePercent = 0;

        $performancePeriodByCode = $codes
            ->mapWithKeys(function ($code) use ($activePerformanceSchedules, $joinDateByCode) {
                $schedule = $activePerformanceSchedules[$code] ?? null;
                $period = $this->normalizePeriodValue($schedule->Periode ?? null);
                $joinDate = $joinDateByCode[$code] ?? null;
                if (! $this->isEligibleForSchedulePeriod($joinDate, $period)) {
                    return [$code => null];
                }

                return [$code => $period];
            })
            ->all();
        // dd($schedule->Periode ?? null, $performancePeriodByCode);

        // Anggota tanpa target jatuh tempo tidak punya apa pun untuk diisi, jadi
        // bukan "belum update" — sama dengan halaman Update KPI dan label "Tidak
        // Ada Target" di monitoring. Diklasifikasi sekali per PERIODE.
        $punyaTarget = [];
        foreach (collect($performancePeriodByCode)->filter()->unique() as $period) {
            $idUsers = collect($performancePeriodByCode)
                ->filter(fn ($p) => $p === $period)
                ->keys()
                ->map(fn ($code) => (int) ($userIdByCode[$code] ?? 0))
                ->filter()
                ->values()
                ->all();

            foreach (app(KpiEnforcementService::class)->classifyParameterTargetForPeriod($idUsers, Carbon::parse($period)) as $idUser => $kelas) {
                $punyaTarget[$period][(int) $idUser] = $kelas === 'has';
            }
        }

        $performanceDenominator = $codes->filter(function ($code) use ($performancePeriodByCode, $userIdByCode, $punyaTarget) {
            $period = $performancePeriodByCode[$code] ?? null;

            return ! empty($period) && ($punyaTarget[$period][(int) ($userIdByCode[$code] ?? 0)] ?? false);
        });

        // dd($performancePeriodByCode, $performanceDenominator);

        $performanceSubmittedCount = $performanceDenominator
            ->filter(function ($code) use ($performancePeriodByCode, $userIdByCode, $submittedUserPeriods) {
                $period = $performancePeriodByCode[$code] ?? null;
                $userId = (string) ($userIdByCode[$code] ?? '');

                return $period && $userId !== '' && isset($submittedUserPeriods[$userId][$period]);
            })
            ->count();

        $performancePendingCount = max(0, $performanceDenominator->count() - $performanceSubmittedCount);
        $performanceSubmittedPercent =
            $performanceDenominator->count() > 0
                ? (int) round(($performanceSubmittedCount / $performanceDenominator->count()) * 100)
                : 0;

        $trend = $monthKeys
            ->map(function ($monthKey) use (
                $codes,
                $performanceScheduleMap,
                $userIdByCode,
                $scoresByUserPeriod,
                $joinDateByCode,
            ) {
                $denominatorCodes = $codes->filter(function ($code) use (
                    $performanceScheduleMap,
                    $monthKey,
                    $joinDateByCode,
                ) {
                    if (empty($performanceScheduleMap[$code][$monthKey] ?? null)) {
                        return false;
                    }
                    $joinDate = $joinDateByCode[$code] ?? null;

                    return $this->isEligibleForSchedulePeriod($joinDate, (string) $monthKey);
                });
                $scores = $denominatorCodes
                    ->map(function ($code) use ($monthKey, $userIdByCode, $scoresByUserPeriod) {
                        $userId = (string) ($userIdByCode[$code] ?? '');
                        if ($userId === '' || ! isset($scoresByUserPeriod[$userId][$monthKey])) {
                            return null;
                        }

                        return (float) $scoresByUserPeriod[$userId][$monthKey];
                    })
                    ->filter(fn ($score) => $score !== null)
                    ->values();
                $value = $scores->isNotEmpty() ? (float) round($scores->avg(), 1) : 0;

                return [
                    'month' => Carbon::createFromFormat('Y-m', $monthKey, 'Asia/Jakarta')->translatedFormat('M'),
                    'value' => $value,
                ];
            })
            ->values()
            ->all();

        $activePerformancePeriod = collect($activePerformanceSchedules)
            ->map(fn ($schedule) => $this->normalizePeriodValue($schedule->Periode ?? null))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $activeFormPeriod = collect($activeFormSchedules)
            ->map(fn ($schedule) => $this->normalizePeriodValue($schedule->Periode ?? null))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        // dd($year, $activeFormSchedules, $activePerformanceSchedules, $activeFormPeriod, $activePerformancePeriod);
        $formSubmittedMembers = $this->resolveFormSubmittedMembersForYear($codes->all(), $userIdByCode, $year);
        $formScheduledMembers = $codes
            ->filter(function ($code) use ($formScheduleMap, $hasEligibleSchedule) {
                return $hasEligibleSchedule((string) $code, $formScheduleMap);
            })
            ->count();
        $formSubmittedCount = $codes
            ->filter(function ($code) use ($formScheduleMap, $formSubmittedMembers, $hasEligibleSchedule) {
                return $hasEligibleSchedule((string) $code, $formScheduleMap) && isset($formSubmittedMembers[$code]);
            })
            ->count();
        $formCoveragePercent =
            $formScheduledMembers > 0 ? (int) round(($formSubmittedCount / $formScheduledMembers) * 100) : 0;
        // Coverage parameter harus berdasarkan anggota yang terjadwal Form KPI
        // dan benar-benar sudah submit di KPI_Pengajuan.
        $parameterCoveragePercent = $formCoveragePercent;

        $now = Carbon::now('Asia/Jakarta');
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');
        $ytdEndMonth = $year < $currentYear ? 12 : ($year === $currentYear ? max(1, $currentMonth - 1) : 0);

        $performanceYearlyDenominator = $codes
            ->filter(function ($code) use (
                $performanceScheduleMap,
                $year,
                $ytdEndMonth,
                $currentYear,
                $joinDateByCode,
            ) {
                $months = array_keys((array) ($performanceScheduleMap[$code] ?? []));
                $joinDate = $joinDateByCode[$code] ?? null;
                foreach ($months as $period) {
                    if (! str_starts_with((string) $period, $year.'-')) {
                        continue;
                    }
                    if (! $this->isEligibleForSchedulePeriod($joinDate, (string) $period)) {
                        continue;
                    }
                    if ($year < $currentYear) {
                        return true;
                    }
                    $m = (int) substr((string) $period, 5, 2);
                    if ($ytdEndMonth > 0 && $m <= $ytdEndMonth) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $dominantPeriod = null;
        if ($ytdEndMonth > 0) {
            for ($m = $ytdEndMonth; $m >= 1; $m -= 1) {
                $candidate = sprintf('%04d-%02d', $year, $m);
                $hasSchedule = $performanceYearlyDenominator->contains(function ($code) use (
                    $performanceScheduleMap,
                    $candidate,
                ) {
                    return ! empty($performanceScheduleMap[$code][$candidate] ?? null);
                });
                if ($hasSchedule) {
                    $dominantPeriod = $candidate;
                    break;
                }
            }
        }
        if (! $dominantPeriod) {
            $dominantPeriod = $activePerformancePeriod;
        }

        $monthlyScores = collect($performanceYearlyDenominator)
            ->map(function ($code) use ($dominantPeriod, $userIdByCode, $scoresByUserPeriod) {
                $userId = (string) ($userIdByCode[$code] ?? '');
                if ($userId === '' || ! $dominantPeriod || ! isset($scoresByUserPeriod[$userId][$dominantPeriod])) {
                    return null;
                }

                return (float) $scoresByUserPeriod[$userId][$dominantPeriod];
            })
            ->filter(fn ($score) => $score !== null)
            ->values();
        $monthlyAvgScore = $monthlyScores->isNotEmpty() ? (float) round($monthlyScores->avg(), 1) : 0;

        $ytdPeriods = collect(range(1, $ytdEndMonth))->map(fn ($m) => sprintf('%04d-%02d', $year, $m))->values();
        // YTD metode AKUMULASI (disamakan dgn dashboard): per-anggota Σactual/Σtarget
        // sepanjang ytd_periods → rumus → normalisasi → cap; lalu dirata-ratakan antar anggota.
        $ytdScoresByUser = $this->accumulatedScoresForUsers(
            collect($performanceYearlyDenominator)
                ->map(fn ($code) => (string) ($userIdByCode[$code] ?? ''))
                ->filter()->unique()->values()->all(),
            $year,
            $ytdPeriods->all(),
        );
        $ytdMemberAverages = collect($performanceYearlyDenominator)
            ->map(function ($code) use ($userIdByCode, $ytdScoresByUser) {
                $userId = (string) ($userIdByCode[$code] ?? '');
                if ($userId === '' || ! array_key_exists($userId, $ytdScoresByUser)) {
                    return null;
                }

                return (float) $ytdScoresByUser[$userId];
            })
            ->filter(fn ($avg) => $avg !== null)
            ->values();
        $ytdAvgScore = $ytdMemberAverages->isNotEmpty() ? (float) round($ytdMemberAverages->avg(), 1) : 0;
        $ytdLe80 = $ytdMemberAverages->filter(fn ($avg) => (float) $avg <= 80)->count();
        $ytdGt80 = $ytdMemberAverages->filter(fn ($avg) => (float) $avg > 80)->count();
        $performanceAvailable = $performanceYearlyDenominator->count() > 0 && $ytdEndMonth > 0;
        $ytdPeriodLabel =
            $ytdEndMonth > 0
                ? 'YTD Jan-'.Carbon::createFromDate($year, $ytdEndMonth, 1, 'Asia/Jakarta')->translatedFormat('M Y')
                : 'YTD -';

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::debug('HomepageKpiService team compliance profile', [
            'team_codes' => $codes->count(),
            'year' => $year,
            'scheduled_members' => $scheduledMembers,
            'form_scheduled_members' => $formScheduledMembers,
            'performance_scheduled_members' => $performanceYearlyDenominator->count(),
            'trend_points' => count($trend),
            'duration_ms' => $durationMs,
        ]);

        return [
            'kpi_year' => $year,
            'monthly_avg_score' => $monthlyAvgScore,
            'ytd_avg_score' => $ytdAvgScore,
            'ytd_le_80_count' => (int) $ytdLe80,
            'ytd_gt_80_count' => (int) $ytdGt80,
            'performance_available' => $performanceAvailable,
            'ytd_period_label' => $ytdPeriodLabel,
            'form_scope_label' => 'Tahun '.$year,
            'parameter_period_label' => $this->formatSchedulePeriod($activeFormPeriod),
            'performance_period_label' => $this->formatSchedulePeriod($activePerformancePeriod),
            'trend_range_label' => 'Jan-Des '.$year,
            'scheduled_members' => $scheduledMembers,
            'parameter_scheduled_members' => $parameterScheduledMembers,
            'parameter_coverage_percent' => $parameterCoveragePercent,
            'performance_submitted_percent' => $performanceSubmittedPercent,
            'performance_submitted_count' => $performanceSubmittedCount,
            'performance_pending_count' => $performancePendingCount,
            'trend' => $trend,
            'form_kpi_yearly' => [
                'scheduled_members' => $formScheduledMembers,
                'submitted_count' => $formSubmittedCount,
                'coverage_percent' => $formCoveragePercent,
            ],
            'performance_monthly' => [
                'scheduled_members' => $performanceDenominator->count(),
                'submitted_count' => $performanceSubmittedCount,
                'submitted_percent' => $performanceSubmittedPercent,
                'pending_count' => $performancePendingCount,
            ],
        ];
    }

    public function getLeaderKpiDetailRows(
        Collection $teamMembers,
        string $mode = 'monthly',
        ?string $period = null,
        string $search = '',
        ?int $year = null,
    ): array {
        return $this->getLeaderKpiAnnualRows($teamMembers, $search, $year);
    }

    public function getLeaderKpiAnnualRows(Collection $teamMembers, string $search = '', ?int $year = null, ?string $month = null): array
    {
        $now = Carbon::now('Asia/Jakarta');
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $now = Carbon::createFromFormat('Y-m-d', $month.'-01', 'Asia/Jakarta')->addMonth();
        }
        $year = $year && $year > 0 ? $year : (int) $now->format('Y');
        $scopeMeta = $this->resolveKpiYearScope($year, $now);

        $members = $teamMembers
            ->map(function ($member) {
                return (object) [
                    'kode' => $this->normalizeKode($member->Kode_Karyawan ?? ''),
                    'name' => $member->Nama ?? 'Tanpa Nama',
                    'position' => $member->nama_jabatan ?? '-',
                    'department' => $member->nama_divisi ?? '-',
                    'user_id' => $member->UserID_Web ?? null,
                    'join_date' => $member->Tgl_Masuk ?? null,
                ];
            })
            ->filter(fn ($member) => $member->kode !== '')
            ->values();

        if ($search !== '') {
            $keyword = strtolower(trim($search));
            $members = $members
                ->filter(function ($member) use ($keyword) {
                    return str_contains(strtolower($member->name), $keyword) ||
                        str_contains(strtolower($member->position), $keyword) ||
                        str_contains(strtolower($member->department), $keyword);
                })
                ->values();
        }

        if ($members->isEmpty()) {
            return [
                'rows' => [],
                'meta' => [
                    'mode' => 'annual',
                    'period_label' => 'Tahun '.$year,
                    'kpi_year' => $year,
                    'total_member' => 0,
                    'effective_cutoff_period' => $scopeMeta['effective_cutoff_period'],
                    'calculation_scope' => $scopeMeta['calculation_scope'],
                ],
            ];
        }

        $userIds = $members->pluck('user_id')->filter()->unique()->values()->all();
        $userByCode = $members->mapWithKeys(fn ($m) => [$m->kode => $m->user_id])->all();
        $kodeByUser = $members
            ->filter(fn ($m) => ! empty($m->user_id))
            ->mapWithKeys(fn ($m) => [(string) $m->user_id => $m->kode])
            ->all();
        $formulas = DB::table('KPI_Master_Formula')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Rumus', 'Id_Master_Formula')
            ->all();

        $parameterRows = collect();
        $formRows = collect();
        if (! empty($userIds)) {
            $parameterRows = DB::table('KPI_Pengajuan_Detail as kd')
                ->join('KPI_Pengajuan as kp', 'kd.Id_Pengajuan', '=', 'kp.Id_Pengajuan')
                ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
                ->whereIn('kp.Id_User', $userIds)
                ->where('kp.Flag_Aktif', 'Y')
                ->whereNull('kp.Status')
                ->where('mp.Tahun', (string) $year)
                ->select(
                    'kp.Id_User',
                    'kd.Id_Pengajuan_Detail',
                    'kd.Nama_Parameter',
                    'kd.Bobot',
                    'kd.Id_Master_Formula',
                )
                ->orderBy('kd.Nama_Parameter')
                ->get();

            $formRows = DB::table('KPI_Pengajuan as kp')
                ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
                ->join('KPI_Pengajuan_Detail as kd', 'kp.Id_Pengajuan', '=', 'kd.Id_Pengajuan')
                ->leftJoin('KPI_Approval_Request_Parameter as ar', 'kp.No_Transaksi', '=', 'ar.No_Transaksi')
                ->leftJoin('KPI_Approval_Flow_Parameter as af', 'ar.Flow_Id', '=', 'af.Id_Approval_Flow')
                ->whereIn('kp.Id_User', $userIds)
                ->where('kp.Flag_Aktif', 'Y')
                ->whereNull('kp.Status')
                ->where('mp.Tahun', (string) $year)
                ->select(
                    'kp.Id_User',
                    'kd.Id_Pengajuan_Detail',
                    'kp.Flag_Approval',
                    DB::raw('MIN(af.order_flow) as current_flow'),
                )
                ->groupBy('kp.Id_User', 'kd.Id_Pengajuan_Detail', 'kp.Flag_Approval')
                ->get();
        }

        $paramsByUser = $parameterRows
            ->groupBy('Id_User')
            ->map(function (Collection $rows) {
                return $rows
                    ->keyBy(fn ($row) => (string) $row->Id_Pengajuan_Detail)
                    ->map(function ($row) {
                        return [
                            'id' => (string) $row->Id_Pengajuan_Detail,
                            'name' => $row->Nama_Parameter ?? 'Parameter KPI',
                            'bobot' => (float) ($row->Bobot ?? 0),
                            'formula_id' => (string) ($row->Id_Master_Formula ?? ''),
                        ];
                    })
                    ->all();
            })
            ->all();
        $formByUserParam = $formRows
            ->groupBy('Id_User')
            ->map(function (Collection $rows) {
                return $rows
                    ->keyBy(fn ($row) => (string) $row->Id_Pengajuan_Detail)
                    ->map(function ($row) {
                        $status = strtoupper((string) ($row->Flag_Approval ?? 'T'));

                        return [
                            'status_approval' => $status,
                            'current_flow' => (int) ($row->current_flow ?? 0),
                            'status_label' => $status === 'Y' ? 'Disetujui' : 'Menunggu',
                        ];
                    })
                    ->all();
            })
            ->all();

        $performanceHeaders = $this->loadApprovedPerformanceRows($userIds, $year)
            ->filter(function ($row) use ($year, $scopeMeta) {
                $period = $this->normalizePeriodValue($row->Periode ?? null);
                if (! $period) {
                    return false;
                }
                $periodYear = (int) substr($period, 0, 4);
                $cutoffYear = (int) substr($scopeMeta['effective_cutoff_period'], 0, 4);

                return $periodYear === $year || $periodYear === $cutoffYear;
            })
            ->values();
        $periodScoresByUser = [];
        foreach ($performanceHeaders as $row) {
            $period = $this->normalizePeriodValue($row->Periode ?? null);
            $userKey = (string) ($row->Id_User ?? '');
            if (! $period || $userKey === '') {
                continue;
            }
            if (! isset($periodScoresByUser[$userKey][$period])) {
                $periodScoresByUser[$userKey][$period] = (float) round((float) ($row->Total_Score ?? 0), 1);
            }
        }

        $targetsFullByUser = [];
        if (! empty($scopeMeta['full_year_periods']) && ! empty($userIds)) {
            $targetRows = DB::table('KPI_Pengajuan_Det as pdet')
                ->join('KPI_Pengajuan_Detail as kd', 'pdet.Id_Pengajuan_Detail', '=', 'kd.Id_Pengajuan_Detail')
                ->join('KPI_Pengajuan as kp', 'kd.Id_Pengajuan', '=', 'kp.Id_Pengajuan')
                ->whereIn('kp.Id_User', $userIds)
                ->where('kp.Flag_Aktif', 'Y')
                ->whereNull('kp.Status')
                ->whereIn('pdet.Periode', $scopeMeta['full_year_periods'])
                ->groupBy('kp.Id_User', 'kd.Id_Pengajuan_Detail')
                ->select(
                    'kp.Id_User',
                    'kd.Id_Pengajuan_Detail',
                    DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) as total_target'),
                )
                ->get();
            foreach ($targetRows as $row) {
                $targetsFullByUser[(string) $row->Id_User][(string) $row->Id_Pengajuan_Detail] =
                    (float) ($row->total_target ?? 0);
            }
        }

        $actualCutoffByUser = [];
        $actualFullByUser = [];
        if (! empty($userIds)) {
            $cutoffRows = DB::table('KPI_Update_Performance_Detail as upd')
                ->join('KPI_Update_Performance as up', 'upd.Id_Update_Performance', '=', 'up.Id_Update_Performance')
                ->join('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                ->join('KPI_Pengajuan_Detail as kd', 'pdet.Id_Pengajuan_Detail', '=', 'kd.Id_Pengajuan_Detail')
                ->whereIn('up.Id_User', $userIds)
                ->whereNull('up.Status')
                ->where('up.Status_Pengajuan', 'disetujui')
                ->where('up.Flag_Approval', 'Y')
                ->where('up.Flag_Verifikasi', 'Y')
                ->where('up.Periode', $scopeMeta['effective_cutoff_period'])
                ->groupBy('up.Id_User', 'kd.Id_Pengajuan_Detail')
                ->select('up.Id_User', 'kd.Id_Pengajuan_Detail')
                ->get();
            foreach ($cutoffRows as $row) {
                $actualCutoffByUser[(string) $row->Id_User][(string) $row->Id_Pengajuan_Detail] = true;
            }

            if (! empty($scopeMeta['full_year_periods'])) {
                $actualRows = DB::table('KPI_Update_Performance_Detail as upd')
                    ->join('KPI_Update_Performance as up', 'upd.Id_Update_Performance', '=', 'up.Id_Update_Performance')
                    ->join('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                    ->join('KPI_Pengajuan_Detail as kd', 'pdet.Id_Pengajuan_Detail', '=', 'kd.Id_Pengajuan_Detail')
                    ->whereIn('up.Id_User', $userIds)
                    ->whereNull('up.Status')
                    ->where('up.Status_Pengajuan', 'disetujui')
                    ->where('up.Flag_Approval', 'Y')
                    ->where('up.Flag_Verifikasi', 'Y')
                    ->whereIn('up.Periode', $scopeMeta['full_year_periods'])
                    ->groupBy('up.Id_User', 'kd.Id_Pengajuan_Detail')
                    ->select(
                        'up.Id_User',
                        'kd.Id_Pengajuan_Detail',
                        DB::raw('COALESCE(SUM(upd.Nilai_Actual), 0) as total_actual'),
                    )
                    ->get();
                foreach ($actualRows as $row) {
                    $actualFullByUser[(string) $row->Id_User][(string) $row->Id_Pengajuan_Detail] =
                        (float) ($row->total_actual ?? 0);
                }
            }
        }
        $fullUpdateFlagsByUser = [];
        foreach ($actualFullByUser as $userKey => $rows) {
            $fullUpdateFlagsByUser[(string) $userKey] = collect((array) $rows)
                ->keys()
                ->mapWithKeys(fn ($paramId) => [(string) $paramId => true])
                ->all();
        }

        $formSubmittedMembers = $this->resolveFormSubmittedMembersForYear(
            $members->pluck('kode')->all(),
            $userByCode,
            $year,
        );

        $yearFormScheduleMap = $this->resolveYearlyScheduleMap(
            $members->pluck('kode')->all(),
            'Flag_Form_KPI',
            $year,
        );
        $yearPerformanceScheduleMap = $this->resolveYearlyScheduleMap(
            $members->pluck('kode')->all(),
            'Flag_Penilaian_KPI',
            $year,
        );

        $paramApprovalByUser = $this->resolveParameterApprovalBatch($userIds, $year);
        $perfApprovalByUser = $this->resolvePerformanceApprovalBatch(
            $userIds,
            $year,
            $scopeMeta['effective_cutoff_period'],
        );

        // YTD metode AKUMULASI per anggota (disamakan dgn dashboard).
        $ytdScoresByUser = $this->accumulatedScoresForUsers(
            $members->pluck('user_id')->filter()->unique()->values()->all(),
            $year,
            $scopeMeta['ytd_periods'],
        );

        $rows = $members
            ->map(function ($member) use (
                $ytdScoresByUser,
                $scopeMeta,
                $periodScoresByUser,
                $paramsByUser,
                $formByUserParam,
                $targetsFullByUser,
                $actualFullByUser,
                $fullUpdateFlagsByUser,
                $actualCutoffByUser,
                $formulas,
                $formSubmittedMembers,
                $yearFormScheduleMap,
                $yearPerformanceScheduleMap,
                $paramApprovalByUser,
                $perfApprovalByUser,
            ) {
                $userId = (string) ($member->user_id ?? '');
                $kode = $member->kode;
                $joinDate = $this->resolveJoinDate($member->join_date ?? null);
                $periodScores = $userId !== '' ? (array) ($periodScoresByUser[$userId] ?? []) : [];
                $monthlyScore = (float) round((float) ($periodScores[$scopeMeta['effective_cutoff_period']] ?? 0), 1);
                $ytdScore = (float) ($ytdScoresByUser[$userId] ?? 0.0);
                $paramDefs = collect($paramsByUser[$userId] ?? []);
                $fullCalc = $this->calculateOverallScoreForParameters(
                    $paramDefs,
                    (array) ($targetsFullByUser[$userId] ?? []),
                    (array) ($actualFullByUser[$userId] ?? []),
                    $formulas,
                    (array) ($fullUpdateFlagsByUser[$userId] ?? []),
                );
                $fullYearScore = (float) ($fullCalc['overall_score'] ?? 0);

                $formInfo = (array) ($formByUserParam[$userId] ?? []);
                $formItems = $paramDefs
                    ->map(function ($param) use ($formInfo) {
                        $status = $formInfo[$param['id']] ?? null;

                        return [
                            'id' => $param['id'],
                            'parameter' => $param['name'],
                            'status_approval' => $status['status_approval'] ?? 'T',
                            'status_pengajuan' => strtoupper((string) ($status['status_approval'] ?? 'T')) === 'Y'
                                    ? 'disetujui'
                                    : 'menunggu',
                            'current_flow' => (int) ($status['current_flow'] ?? 0),
                        ];
                    })
                    ->values()
                    ->all();

                $performanceCount = count((array) ($actualCutoffByUser[$userId] ?? []));
                $isPastYear = $scopeMeta['calculation_scope'] === 'past_year';
                $referencePeriod = $isPastYear
                    ? sprintf('%04d-12', (int) $scopeMeta['year'])
                    : $scopeMeta['effective_cutoff_period'];
                $isNewJoiner = ! $this->isEligibleForSchedulePeriod($joinDate, $referencePeriod);

                $paramApproval = $paramApprovalByUser[$userId] ?? ['status' => 'none', 'submitted' => false];
                $perfApproval = $perfApprovalByUser[$userId] ?? [
                    'status' => 'none',
                    'submitted' => false,
                    'hasParameters' => false,
                ];

                return [
                    'id' => $kode,
                    'name' => $member->name,
                    'position' => $member->position,
                    'department' => $member->department,
                    'join_date' => $joinDate ? $joinDate->format('Y-m-d') : null,
                    'monthly_score_current_cutoff' => $monthlyScore,
                    'ytd_score' => $ytdScore,
                    'full_year_score' => $fullYearScore,
                    'monthly_score' => $monthlyScore,
                    'ytd_avg_score' => $ytdScore,
                    'form_kpi_count' => $paramDefs->count(),
                    'performance_kpi_count' => $performanceCount,
                    'form_submitted' => isset($formSubmittedMembers[$kode]),
                    'form_scheduled' => ! empty($yearFormScheduleMap[$kode] ?? []),
                    'cutoff_performance_scheduled' => isset($yearPerformanceScheduleMap[$kode][$scopeMeta['effective_cutoff_period']]),
                    'parameter_approval_status' => $paramApproval['status'],
                    'parameter_submitted' => $paramApproval['submitted'],
                    'performance_approval_status' => $perfApproval['status'],
                    'performance_submitted' => $perfApproval['submitted'],
                    'performance_has_parameters' => $perfApproval['hasParameters'],
                    'performance_has_due_target' => $perfApproval['hasDueTarget'] ?? false,
                    'summary_score' => $isPastYear ? $fullYearScore : $monthlyScore,
                    'summary_label' => $isPastYear ? 'Full Year' : 'Monthly',
                    'score_scope' => $isPastYear ? 'past_year' : 'running_year',
                    'is_new_joiner' => $isNewJoiner,
                    'join_status_label' => $isNewJoiner ? 'Baru bergabung' : null,
                    'form_kpi' => $formItems,
                    'performance_kpi' => [],
                ];
            })
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'meta' => [
                'mode' => 'annual',
                'period_label' => 'Tahun '.$year,
                'kpi_year' => $year,
                'total_member' => count($rows),
                'effective_cutoff_period' => $scopeMeta['effective_cutoff_period'],
                'calculation_scope' => $scopeMeta['calculation_scope'],
                'ytd_end_period' => $scopeMeta['ytd_end_period'],
            ],
        ];
    }

    public function getLeaderKpiMemberDetail(
        object $member,
        string $mode = 'month',
        ?string $period = null,
        ?int $year = null,
    ): array {
        $now = Carbon::now('Asia/Jakarta');
        $year = $year && $year > 0 ? $year : (int) $now->format('Y');
        $scopeMeta = $this->resolveKpiYearScope($year, $now);
        $normalizedMode = in_array($mode, ['month', 'full_year'], true)
            ? $mode
            : (in_array($mode, ['yearly', 'ytd'], true)
                ? 'full_year'
                : 'month');
        $selectedPeriod =
            $normalizedMode === 'month' && preg_match('/^\d{4}-\d{2}$/', (string) $period)
                ? (string) $period
                : $scopeMeta['effective_cutoff_period'];

        $userId = (string) ($member->UserID_Web ?? '');
        $kode = $this->normalizeKode($member->Kode_Karyawan ?? '');
        $joinDate = $this->resolveJoinDate($member->Tgl_Masuk ?? null);
        if ($userId === '') {
            return [
                'id' => $kode,
                'name' => $member->Nama ?? 'Tanpa Nama',
                'position' => $member->nama_jabatan ?? '-',
                'department' => $member->nama_divisi ?? '-',
                'join_date' => $joinDate ? $joinDate->format('Y-m-d') : null,
                'summary' => [
                    'mode' => $normalizedMode,
                    'period' => $selectedPeriod,
                    'monthly_score_current_cutoff' => 0,
                    'ytd_score' => 0,
                    'full_year_score' => 0,
                    'selected_score' => 0,
                    'form_parameter_count' => 0,
                    'performance_item_count' => 0,
                    'is_new_joiner' => false,
                    'join_status_label' => null,
                ],
                'parameter_rows' => [],
                'trend_12_months' => collect(range(1, 12))
                    ->map(function ($month) use ($year) {
                        $period = sprintf('%04d-%02d', $year, $month);

                        return [
                            'period' => $period,
                            'month' => Carbon::createFromFormat('Y-m', $period, 'Asia/Jakarta')->translatedFormat('M'),
                            'score' => 0,
                        ];
                    })
                    ->all(),
                'monthly_score' => 0,
                'ytd_avg_score' => 0,
                'form_kpi_count' => 0,
                'performance_kpi_count' => 0,
                'performance_trend' => [],
            ];
        }

        $formulas = DB::table('KPI_Master_Formula')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Rumus', 'Id_Master_Formula')
            ->all();
        $ranges = $this->loadPerformanceRanges();

        // Pengajuan yang parameternya ditampilkan — lihat resolvePengajuanSumberParameter.
        $sumberPengajuan = $this->resolvePengajuanSumberParameter($userId, $year, $normalizedMode, $selectedPeriod);

        $paramDefs = DB::table('KPI_Pengajuan_Detail as kd')
            ->whereIn('kd.Id_Pengajuan', $sumberPengajuan ?: [0])
            ->select('kd.Id_Pengajuan_Detail', 'kd.Nama_Parameter', 'kd.Bobot', 'kd.Id_Master_Formula')
            ->orderBy('kd.Nama_Parameter')
            ->get()
            ->keyBy(fn ($row) => (string) $row->Id_Pengajuan_Detail);

        $paramIds = $paramDefs->keys()->all();

        $formStatusByParam = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Pengajuan_Detail as kd', 'kp.Id_Pengajuan', '=', 'kd.Id_Pengajuan')
            ->leftJoin('KPI_Approval_Request_Parameter as ar', 'kp.No_Transaksi', '=', 'ar.No_Transaksi')
            ->leftJoin('KPI_Approval_Flow_Parameter as af', 'ar.Flow_Id', '=', 'af.Id_Approval_Flow')
            ->whereIn('kp.Id_Pengajuan', $sumberPengajuan ?: [0])
            ->groupBy('kd.Id_Pengajuan_Detail', 'kp.Flag_Approval')
            ->select('kd.Id_Pengajuan_Detail', 'kp.Flag_Approval', DB::raw('MIN(af.order_flow) as current_flow'))
            ->get()
            ->keyBy(fn ($row) => (string) $row->Id_Pengajuan_Detail);

        $headerRows = $this->loadApprovedPerformanceRows([$userId], $year);
        $periodScores = [];
        $latestUpdateIdByPeriod = [];
        foreach ($headerRows as $row) {
            $periodKey = $this->normalizePeriodValue($row->Periode ?? null);
            if (! $periodKey) {
                continue;
            }
            if (! isset($periodScores[$periodKey])) {
                $periodScores[$periodKey] = (float) round((float) ($row->Total_Score ?? 0), 1);
                $latestUpdateIdByPeriod[$periodKey] = (int) ($row->Id_Update_Performance ?? 0);
            }
        }

        $monthlyScoreCutoff = (float) round((float) ($periodScores[$scopeMeta['effective_cutoff_period']] ?? 0), 1);
        // $ytdValues tetap dipakai utk ytd_submitted_count (jumlah bulan terisi).
        $ytdValues = collect($scopeMeta['ytd_periods'])
            ->map(fn ($periodKey) => $periodScores[$periodKey] ?? null)
            ->filter(fn ($val) => $val !== null)
            ->values();
        // YTD metode AKUMULASI (disamakan dgn dashboard), bukan averaging skor bulanan.
        $ytdScore = (float) ($this->accumulatedScoresForUsers([$userId], $year, $scopeMeta['ytd_periods'])[$userId] ?? 0.0);

        $targetsByParam = [];
        $actualByParam = [];
        $periodsForAggregate = $normalizedMode === 'full_year' ? $scopeMeta['full_year_periods'] : [$selectedPeriod];
        if (! empty($periodsForAggregate) && ! empty($paramIds)) {
            $targetRows = DB::table('KPI_Pengajuan_Det as pdet')
                ->whereIn('pdet.Id_Pengajuan_Detail', $paramIds)
                ->whereIn('pdet.Periode', $periodsForAggregate)
                ->groupBy('pdet.Id_Pengajuan_Detail')
                ->select('pdet.Id_Pengajuan_Detail', DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) as total_target'))
                ->get();
            foreach ($targetRows as $row) {
                $targetsByParam[(string) $row->Id_Pengajuan_Detail] = (float) ($row->total_target ?? 0);
            }

            $actualUpdateIds = collect($periodsForAggregate)
                ->map(fn ($periodKey) => (int) ($latestUpdateIdByPeriod[(string) $periodKey] ?? 0))
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if (! empty($actualUpdateIds)) {
                $actualRows = DB::table('KPI_Update_Performance_Detail as upd')
                    ->join('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                    ->whereIn('upd.Id_Update_Performance', $actualUpdateIds)
                    ->whereIn('pdet.Id_Pengajuan_Detail', $paramIds)
                    ->groupBy('pdet.Id_Pengajuan_Detail')
                    ->select('pdet.Id_Pengajuan_Detail', DB::raw('COALESCE(SUM(upd.Nilai_Actual), 0) as total_actual'))
                    ->get();
                foreach ($actualRows as $row) {
                    $actualByParam[(string) $row->Id_Pengajuan_Detail] = (float) ($row->total_actual ?? 0);
                }
            }
        }

        $fullTargetsByParam = [];
        $fullActualByParam = [];
        if (! empty($scopeMeta['full_year_periods']) && ! empty($paramIds)) {
            $fullTargetRows = DB::table('KPI_Pengajuan_Det as pdet')
                ->whereIn('pdet.Id_Pengajuan_Detail', $paramIds)
                ->whereIn('pdet.Periode', $scopeMeta['full_year_periods'])
                ->groupBy('pdet.Id_Pengajuan_Detail')
                ->select('pdet.Id_Pengajuan_Detail', DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) as total_target'))
                ->get();
            foreach ($fullTargetRows as $row) {
                $fullTargetsByParam[(string) $row->Id_Pengajuan_Detail] = (float) ($row->total_target ?? 0);
            }

            $fullActualUpdateIds = collect($scopeMeta['full_year_periods'])
                ->map(fn ($periodKey) => (int) ($latestUpdateIdByPeriod[(string) $periodKey] ?? 0))
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if (! empty($fullActualUpdateIds)) {
                $fullActualRows = DB::table('KPI_Update_Performance_Detail as upd')
                    ->join('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                    ->whereIn('upd.Id_Update_Performance', $fullActualUpdateIds)
                    ->whereIn('pdet.Id_Pengajuan_Detail', $paramIds)
                    ->groupBy('pdet.Id_Pengajuan_Detail')
                    ->select('pdet.Id_Pengajuan_Detail', DB::raw('COALESCE(SUM(upd.Nilai_Actual), 0) as total_actual'))
                    ->get();
                foreach ($fullActualRows as $row) {
                    $fullActualByParam[(string) $row->Id_Pengajuan_Detail] = (float) ($row->total_actual ?? 0);
                }
            }
        }

        $fullYearCalc = $this->calculateOverallScoreForParameters(
            collect(
                $paramDefs
                    ->map(function ($row) {
                        return [
                            'id' => (string) $row->Id_Pengajuan_Detail,
                            'name' => $row->Nama_Parameter ?? 'Parameter KPI',
                            'bobot' => (float) ($row->Bobot ?? 0),
                            'formula_id' => (string) ($row->Id_Master_Formula ?? ''),
                        ];
                    })
                    ->all(),
            ),
            $fullTargetsByParam,
            $fullActualByParam,
            $formulas,
            collect($fullActualByParam)->keys()->mapWithKeys(fn ($paramId) => [(string) $paramId => true])->all(),
        );
        $fullYearScore = (float) ($fullYearCalc['overall_score'] ?? 0);

        $parameterRows = $paramDefs
            ->map(function ($row, $paramId) use (
                $targetsByParam,
                $actualByParam,
                $formStatusByParam,
                $formulas,
                $ranges,
            ) {
                $key = (string) $paramId;
                $target = (float) ($targetsByParam[$key] ?? 0);
                $actual = (float) ($actualByParam[$key] ?? 0);
                $formula = $formulas[$row->Id_Master_Formula] ?? null;
                $formState = $formStatusByParam->get($key);
                $hasPerformanceUpdate = array_key_exists($key, $actualByParam);
                $hasTarget = array_key_exists($key, $targetsByParam);
                $calc = $hasPerformanceUpdate
                    ? $this->calculateFormulaByExpression($actual, $target, (float) ($row->Bobot ?? 0), $formula)
                    : ['achievement' => 0.0, 'score' => 0.0];

                if (! $hasTarget) {
                    $status = [
                        'label' => 'Periode KPI belum aktif',
                        'color' => '#cbd5e1',
                        'code' => 'NA',
                    ];
                } else {
                    $status = $hasPerformanceUpdate
                        ? $this->resolvePerformanceGrade((float) ($calc['achievement'] ?? 0), $ranges)
                        : [
                            'label' => 'Belum ada penilaian',
                            'color' => '#94a3b8',
                            'code' => 'NA',
                        ];
                }
                $approvalFlag = strtoupper((string) ($formState->Flag_Approval ?? 'T'));

                return [
                    'id' => $key,
                    'parameter' => $row->Nama_Parameter ?? 'Parameter KPI',
                    'bobot' => (float) ($row->Bobot ?? 0),
                    'target' => round($target, 2),
                    'actual' => round($actual, 2),
                    'has_performance_update' => $hasPerformanceUpdate,
                    'achievement' => round((float) ($calc['achievement'] ?? 0), 2),
                    'score' => round((float) ($calc['score'] ?? 0), 2),
                    'status' => $status['label'] ?? 'N/A',
                    'status_color' => $status['color'] ?? '#94a3b8',
                    'status_code' => $status['code'] ?? 'NA',
                    'form_status' => $approvalFlag === 'Y' ? 'Disetujui' : 'Menunggu',
                    'form_status_approval' => $approvalFlag,
                    'current_flow' => (int) ($formState->current_flow ?? 0),
                ];
            })
            ->values()
            ->all();
        // dd($parameterRows);

        $selectedScore =
            $normalizedMode === 'full_year'
                ? $fullYearScore
                : (float) round((float) ($periodScores[$selectedPeriod] ?? 0), 1);

        $fullYearReferencePeriod = ! empty($scopeMeta['full_year_periods'])
            ? (string) end($scopeMeta['full_year_periods'])
            : $scopeMeta['effective_cutoff_period'];
        $isNewJoinerForSelectedPeriod = ! $this->isEligibleForSchedulePeriod(
            $joinDate,
            $normalizedMode === 'full_year' ? $fullYearReferencePeriod : $selectedPeriod,
        );

        $trend12 = collect(range(1, 12))
            ->map(function ($month) use ($year, $periodScores) {
                $periodKey = sprintf('%04d-%02d', $year, $month);

                return [
                    'period' => $periodKey,
                    'month' => Carbon::createFromFormat('Y-m', $periodKey, 'Asia/Jakarta')->translatedFormat('M'),
                    'score' => (float) round((float) ($periodScores[$periodKey] ?? 0), 1),
                ];
            })
            ->values()
            ->all();

        $yearFormScheduleMap = $this->resolveYearlyScheduleMap([$kode], 'Flag_Form_KPI', $year);
        $yearPerformanceScheduleMap = $this->resolveYearlyScheduleMap([$kode], 'Flag_Penilaian_KPI', $year);

        $hasFormSchedule = ! empty($yearFormScheduleMap[$kode] ?? []);
        $isPeriodPerformanceScheduled = isset($yearPerformanceScheduleMap[$kode][$selectedPeriod]);

        $paramApprovalByUser = $this->resolveParameterApprovalBatch([$userId], $year);
        $perfApprovalByUser = $this->resolvePerformanceApprovalBatch([$userId], $year, $selectedPeriod);

        $paramApproval = $paramApprovalByUser[$userId] ?? ['status' => 'none', 'submitted' => false];
        $perfApproval = $perfApprovalByUser[$userId] ?? [
            'status' => 'none',
            'submitted' => false,
            'hasParameters' => false,
        ];

        return [
            'id' => $kode,
            'name' => $member->Nama ?? 'Tanpa Nama',
            'position' => $member->nama_jabatan ?? '-',
            'department' => $member->nama_divisi ?? '-',
            'join_date' => $joinDate ? $joinDate->format('Y-m-d') : null,
            'is_new_joiner' => $isNewJoinerForSelectedPeriod,
            'summary' => [
                'mode' => $normalizedMode,
                'period' => $selectedPeriod,
                'period_label' => $normalizedMode === 'full_year'
                        ? 'Full Year '.$year
                        : 'Periode '.$this->formatSchedulePeriod($selectedPeriod),
                'kpi_year' => $year,
                'monthly_score_current_cutoff' => $monthlyScoreCutoff,
                'ytd_score' => $ytdScore,
                'ytd_submitted_count' => $ytdValues->count(),
                'ytd_grade_label' => $ytdScore > 0 ? $this->resolvePerformanceGrade($ytdScore, $ranges)['label'] : '-',
                'ytd_grade_color' => $ytdScore > 0 ? $this->resolvePerformanceGrade($ytdScore, $ranges)['color'] : '#94a3b8',
                'trend_direction' => $this->buildTrendDirection($trend12),
                'full_year_score' => $fullYearScore,
                'selected_score' => $selectedScore,
                'achievement_grade_label' => $selectedScore > 0 ? $this->resolvePerformanceGrade($selectedScore, $ranges)['label'] : '-',
                'achievement_grade_color' => $selectedScore > 0 ? $this->resolvePerformanceGrade($selectedScore, $ranges)['color'] : '#94a3b8',
                'form_parameter_count' => count($parameterRows),
                'performance_item_count' => count(
                    array_filter($parameterRows, fn ($row) => (bool) ($row['has_performance_update'] ?? false)),
                ),
                'is_new_joiner' => $isNewJoinerForSelectedPeriod,
                'join_status_label' => $isNewJoinerForSelectedPeriod ? 'Baru bergabung' : null,
                'form_scheduled' => $hasFormSchedule,
                'cutoff_performance_scheduled' => $isPeriodPerformanceScheduled,
                'parameter_approval_status' => $paramApproval['status'],
                'parameter_submitted' => $paramApproval['submitted'],
                'performance_approval_status' => $perfApproval['status'],
                'performance_submitted' => $perfApproval['submitted'],
                'performance_has_parameters' => $perfApproval['hasParameters'],
                'performance_has_due_target' => $perfApproval['hasDueTarget'] ?? false,
            ],
            'parameter_rows' => $parameterRows,
            'trend_12_months' => $trend12,
            'monthly_score' => $monthlyScoreCutoff,
            'ytd_avg_score' => $ytdScore,
            'form_kpi_count' => count($parameterRows),
            'performance_kpi_count' => count(
                array_filter($parameterRows, fn ($row) => (bool) ($row['has_performance_update'] ?? false)),
            ),
            'performance_trend' => array_map(function ($item) {
                return [
                    'period' => $item['period'],
                    'label' => Carbon::createFromFormat('Y-m', $item['period'], 'Asia/Jakarta')->translatedFormat(
                        'M Y',
                    ),
                    'score' => $item['score'],
                ];
            }, $trend12),
        ];
    }

    protected function resolveKpiYearScope(int $year, Carbon $now): array
    {
        $currentYear = (int) $now->format('Y');
        $effectiveCutoffPeriod = $now->copy()->subMonthNoOverflow()->format('Y-m');
        $scope = 'running_year';
        if ($year < $currentYear) {
            $scope = 'past_year';
            $effectiveCutoffPeriod = sprintf('%04d-12', $year);
        } elseif ($year > $currentYear) {
            $scope = 'future_year';
            $effectiveCutoffPeriod = sprintf('%04d-01', $year);
        }

        $ytdEndMonth = 0;
        if ($year < $currentYear) {
            $ytdEndMonth = 12;
        } elseif ($year === $currentYear) {
            $ytdEndMonth = max(0, (int) $now->format('m') - 1);
        }

        $ytdPeriods = [];
        if ($ytdEndMonth > 0) {
            $ytdPeriods = collect(range(1, $ytdEndMonth))
                ->map(fn ($month) => sprintf('%04d-%02d', $year, $month))
                ->values()
                ->all();
        }

        $fullYearEndMonth = 0;
        if ($year < $currentYear) {
            $fullYearEndMonth = 12;
        } elseif ($year === $currentYear) {
            $cutoffYear = (int) substr($effectiveCutoffPeriod, 0, 4);
            $fullYearEndMonth = $cutoffYear === $year ? (int) substr($effectiveCutoffPeriod, 5, 2) : 0;
        }

        $fullYearPeriods = [];
        if ($fullYearEndMonth > 0) {
            $fullYearPeriods = collect(range(1, $fullYearEndMonth))
                ->map(fn ($month) => sprintf('%04d-%02d', $year, $month))
                ->values()
                ->all();
        }

        return [
            'year' => $year,
            'calculation_scope' => $scope,
            'effective_cutoff_period' => $effectiveCutoffPeriod,
            'ytd_periods' => $ytdPeriods,
            'ytd_end_period' => $ytdEndMonth > 0 ? sprintf('%04d-%02d', $year, $ytdEndMonth) : null,
            'full_year_periods' => $fullYearPeriods,
        ];
    }

    protected function calculateFormulaByExpression(
        float $actual,
        float $target,
        float $weight,
        ?string $formulaExpression,
    ): array {
        // Terpusat & disamakan ke evaluator kanonik (UpdatePerformanceController) via
        // KpiScoringService → KpiFormulaHelper::evaluate: menjalankan preprocess()
        // sehingga rumus IF/MIN/MAX/ROUND dievaluasi benar (sebelumnya keliru jadi 0),
        // dan target 0 diteruskan apa adanya (ditangani rumus, mis. IF(Actual==0,1,0)=100%).
        return app(\App\Services\KPI\KpiScoringService::class)
            ->scoreParameter($actual, $target, $weight, $formulaExpression);
    }

    protected function calculateOverallScoreForParameters(
        Collection $parameterDefinitions,
        array $targetsByParam,
        array $actualByParam,
        array $formulaMap,
        ?array $performanceUpdateByParam = null,
    ): array {
        $sumScore = 0.0;
        $totalWeight = 0.0;

        foreach ($parameterDefinitions as $param) {
            $paramId = (string) ($param['id'] ?? '');
            if ($paramId === '') {
                continue;
            }

            if (is_array($performanceUpdateByParam) && ! array_key_exists($paramId, $performanceUpdateByParam)) {
                continue;
            }

            if (! array_key_exists($paramId, $targetsByParam) && ! array_key_exists($paramId, $actualByParam)) {
                continue;
            }

            $weight = (float) ($param['bobot'] ?? 0);
            $target = (float) ($targetsByParam[$paramId] ?? 0);
            $actual = (float) ($actualByParam[$paramId] ?? 0);
            $formulaId = (string) ($param['formula_id'] ?? '');
            $formula = $formulaMap[$formulaId] ?? null;
            $calculation = $this->calculateFormulaByExpression($actual, $target, $weight, $formula);

            $sumScore += (float) ($calculation['score'] ?? 0);
            $totalWeight += $weight;
        }

        $overall = $totalWeight > 0 ? round(($sumScore / $totalWeight) * 100, 1) : 0.0;

        return [
            'sum_score' => round($sumScore, 2),
            'total_weight' => round($totalWeight, 2),
            'overall_score' => $overall,
        ];
    }

    /**
     * Skor YTD metode AKUMULASI per user (disamakan dgn dashboard staff/ceo):
     * Σtarget & Σactual per-parameter sepanjang $periods (hanya periode yg punya
     * update ter-approve, via INNER JOIN KPI_Update_Performance), lalu rumus per-param
     * → normalisasi (Σweighted/Σbobot × 100) → cap (config services.kpi) → round 1.
     *
     * User tanpa update ter-approve pada rentang → TIDAK muncul di hasil (caller
     * memperlakukannya sebagai 0/tak tersedia; menghindari min-cap "50" utk data kosong).
     *
     * @param  array<int, string|int>  $userIds
     * @param  array<int, string>  $periods  daftar 'YYYY-MM'
     * @return array<string, float> [userId => skor YTD akumulasi]
     */
    /**
     * Skor YTD akumulasi per user.
     *
     * Perhitungannya dipindah ke KpiAkumulasiYtdService supaya Monitoring KPI
     * dan perintah verifikasi memakai satu implementasi yang sama persis —
     * tiga tempat menampilkan angka YTD, dan tiga salinan rumus adalah cara
     * paling pasti membuat ketiganya lambat laun berselisih.
     *
     * Perilaku lama dipertahankan persis: user yang punya baris realisasi tetapi
     * Σbobot-nya nol tetap muncul sebagai 0.0 (karena itulah yang dilakukan kode
     * sebelumnya), sedangkan user yang TIDAK punya baris sama sekali tetap tidak
     * muncul — pemanggil di atas membedakan keduanya lewat array_key_exists().
     */
    protected function accumulatedScoresForUsers(array $userIds, int $year, array $periods): array
    {
        // Dikembalikan APA ADANYA. Menambahkan kunci 0.0 untuk setiap user yang
        // diminta akan memasukkan anggota TANPA DATA ke dalam rata-rata tim dan
        // hitungan "≤80" — pemanggil di atas sengaja memakai array_key_exists()
        // untuk membedakan "tidak ada data" dari "nilainya nol".
        return app(\App\Services\KPI\KpiAkumulasiYtdService::class)
            ->skorPerUser($userIds, $year, $periods, true);
    }

    protected function resolveFormSubmittedMembersForYear(array $codes, array $userIdByCode, int $year): array
    {
        $userIds = collect($userIdByCode)->filter()->unique()->values()->all();
        if (empty($userIds)) {
            return [];
        }

        $submitted = DB::table('KPI_Pengajuan')
            ->join('KPI_Pengajuan_Detail as kd', 'KPI_Pengajuan.Id_Pengajuan', '=', 'kd.Id_Pengajuan')
            ->join('KPI_Master_Penjadwalan as mp', 'KPI_Pengajuan.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
            ->whereIn('KPI_Pengajuan.Id_User', $userIds)
            ->where('KPI_Pengajuan.Flag_Aktif', 'Y')
            ->whereNull('KPI_Pengajuan.Status')
            // Pengajuan yang ditolak (Flag_Approval = 'T') tidak dihitung sebagai "sudah submit".
            // Pending (null/'N') dan disetujui ('Y') tetap dihitung.
            ->where(function ($q) {
                $q->whereNull('KPI_Pengajuan.Flag_Approval')
                    ->orWhereRaw("UPPER(LTRIM(RTRIM(KPI_Pengajuan.Flag_Approval))) != 'T'");
            })
            ->where('mp.Tahun', (string) $year)
            ->select('KPI_Pengajuan.Id_User')
            ->distinct()
            ->pluck('Id_User')
            ->flip();
        // dd($submitted->toSql(), implode(', ', $submitted->getBindings()), $userIdByCode);

        return collect($userIdByCode)
            ->filter(fn ($userId) => $submitted->has($userId))
            ->mapWithKeys(fn ($userId, $code) => [$code => true])
            ->all();
    }

    public function getSummary(string $kodeKaryawan, ?int $year = null): array
    {
        $targetYear = $this->resolveSummaryYear($year);
        $normalizedKode = $this->normalizeKode($kodeKaryawan);
        $summary = $this->getTeamSummaries([$normalizedKode], true, $targetYear);
        $baseSummary = $summary[$normalizedKode] ?? $this->defaultSummary($targetYear);

        $member = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                'k.ID_Level_Jabatan',
                '=',
                'lvl.ID_Level_Jabatan',
            )
            ->where('k.Kode_Karyawan', $normalizedKode)
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserID_Web',
                'k.Tgl_Masuk',
                DB::raw("COALESCE(lvl.nama_jabatan, '-') as nama_jabatan"),
                DB::raw("COALESCE(div.nama_divisi, '-') as nama_divisi"),
            )
            ->first();

        if (! $member) {
            return $baseSummary;
        }

        try {
            $detail = $this->getLeaderKpiMemberDetail($member, 'month', null, $targetYear);

            return $this->mergePersonalSummaryWithMemberDetail($baseSummary, $detail, $targetYear);
        } catch (\Throwable $e) {
            Log::warning('HomepageKpiService personal KPI detail fallback: '.$e->getMessage(), [
                'kode_karyawan' => $normalizedKode,
                'year' => $targetYear,
            ]);

            return $baseSummary;
        }
    }

    /**
     * TTL cache detail KPI personal (detik). getPersonalDashboardDetail berat
     * (getLeaderKpiMemberDetail + banyak resolve schedule ~36 query) & TIDAK ter-cache
     * sebelumnya -> jalan tiap load endpoint kpi-detail = risiko spike (tiap user, tiap
     * refresh). Cache per (user+year+period+mode). KPI berubah lambat -> 90s aman.
     */
    private const PERSONAL_KPI_DETAIL_CACHE_TTL = 90;

    public function getPersonalDashboardDetail(
        string $kodeKaryawan,
        ?int $year = null,
        ?string $period = null,
        string $mode = 'period',
    ): array {
        $cacheKey = 'homepage.personal.kpi-detail.v1:'.implode(':', [
            $this->normalizeKode($kodeKaryawan),
            $this->resolveSummaryYear($year),
            $period ?? 'def',
            $mode,
        ]);

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            self::PERSONAL_KPI_DETAIL_CACHE_TTL,
            fn () => $this->getPersonalDashboardDetailUncached($kodeKaryawan, $year, $period, $mode),
        );
    }

    public function getPersonalDashboardDetailUncached(
        string $kodeKaryawan,
        ?int $year = null,
        ?string $period = null,
        string $mode = 'period',
    ): array {
        $now = Carbon::now('Asia/Jakarta');
        $normalizedKode = $this->normalizeKode($kodeKaryawan);
        $targetYear = $this->resolveSummaryYear($year);
        $normalizedMode =
            $mode === 'ytd' || in_array(strtolower((string) $period), ['all', 'full_year'], true) ? 'ytd' : 'period';
        $member = $this->resolvePersonalKpiMember($normalizedKode);

        if (! $member) {
            return $this->defaultPersonalDashboardDetail($targetYear, $period, 'Data karyawan tidak ditemukan.');
        }

        $formSchedule = $this->resolvePersonalSchedule($normalizedKode, 'Flag_Form_KPI', $targetYear, false);
        $performanceSchedule = $this->resolvePersonalSchedule(
            $normalizedKode,
            'Flag_Penilaian_KPI',
            $targetYear,
            false,
        );
        $requestedPeriod = $this->composePersonalPeriod($period, $targetYear);
        $selectedPeriod =
            $requestedPeriod ?:
            $this->composePersonalPeriod($performanceSchedule?->Periode ?? null, $targetYear) ?:
            $this->composePersonalPeriod(
                $formSchedule?->Periode ?? ($formSchedule?->Start_Periode ?? null),
                $targetYear,
            ) ?:
            $this->latestPersonalPerformancePeriod((string) ($member->UserID_Web ?? ''), $targetYear) ?:
            sprintf('%04d-%02d', $targetYear, (int) $now->copy()->subMonthNoOverflow()->format('m'));

        $detail = $this->getLeaderKpiMemberDetail(
            $member,
            $normalizedMode === 'ytd' ? 'full_year' : 'month',
            $selectedPeriod,
            $targetYear,
        );

        $userId = (string) ($member->UserID_Web ?? '');
        // dd($formSchedule);
        $activePengajuan = $this->resolvePersonalKpiPengajuan(
            $userId,
            $targetYear,
            (int) ($formSchedule->Id_Master_Penjadwalan ?? 0) ?: null,
        );
        // dd($activePengajuan);
        $hasParameterForPeriod =
            $normalizedMode === 'ytd'
                ? $this->hasParameterTargetForYear((int) ($activePengajuan->Id_Pengajuan ?? 0), $targetYear)
                : $this->hasParameterTargetForPeriod((int) ($activePengajuan->Id_Pengajuan ?? 0), $selectedPeriod);
        $parameterRows = $activePengajuan
            ? $this->normalizePersonalParameterRows((array) ($detail['parameter_rows'] ?? []))
            : [];
        // dd($activePengajuan, $hasParameterForPeriod, $parameterRows);
        $performanceState =
            $normalizedMode === 'ytd'
                ? $this->resolvePersonalFullYearPerformanceState($userId, $targetYear)
                : $this->resolvePersonalPerformanceState(
                    $userId,
                    (int) ($activePengajuan->Id_Pengajuan ?? 0),
                    $selectedPeriod,
                );
        $ytd = $this->buildPersonalYtdSummary($userId, $targetYear, (array) ($detail['trend_12_months'] ?? []));

        $yearFormScheduleMap = $this->resolveYearlyScheduleMap([$normalizedKode], 'Flag_Form_KPI', $targetYear);
        $yearPerformanceScheduleMap = $this->resolveYearlyScheduleMap(
            [$normalizedKode],
            'Flag_Penilaian_KPI',
            $targetYear,
        );

        $hasYearFormSchedule = ! empty($yearFormScheduleMap[$normalizedKode] ?? []);
        $hasYearPerformanceSchedule = ! empty($yearPerformanceScheduleMap[$normalizedKode] ?? []);
        $isPeriodPerformanceScheduled = isset($yearPerformanceScheduleMap[$normalizedKode][$selectedPeriod]);

        $paramApprovalByUser = $this->resolveParameterApprovalBatch([$userId], $targetYear);
        $perfApprovalByUser = $this->resolvePerformanceApprovalBatch([$userId], $targetYear, $selectedPeriod);

        $paramApproval = $paramApprovalByUser[$userId] ?? ['status' => 'none', 'submitted' => false];
        $perfApproval = $perfApprovalByUser[$userId] ?? [
            'status' => 'none',
            'submitted' => false,
            'hasParameters' => false,
        ];

        $parameterSchedule = $this->schedulePayload($formSchedule, $selectedPeriod);
        $performanceSchedule = $this->schedulePayload($performanceSchedule, $selectedPeriod);
        $hasParameterTargets = (bool) $activePengajuan && $hasParameterForPeriod;
        $parameterSubmitted = (bool) ($parameterSchedule['isActive'] ?? false) && $hasParameterTargets;
        $performanceCanSubmit = (bool) ($performanceSchedule['isActive'] ?? false) && $hasParameterTargets;

        if ($performanceState['isSubmitted']) {
            $performanceMessage = 'Sudah mengisi';
        } elseif ($performanceSchedule['isActive'] ?? false) {
            $performanceMessage = 'Belum mengisi';
        } elseif ($ytd['hasData']) {
            $performanceMessage = 'YTD tersedia';
        } else {
            $performanceMessage = 'Belum ada jadwal';
        }

        return [
            'has_data' => (bool) ($parameterSchedule['isActive'] ?? false) ||
                (bool) ($performanceSchedule['isActive'] ?? false) ||
                $hasParameterTargets ||
                $performanceState['isSubmitted'] ||
                $ytd['hasData'],
            'selectedYear' => $targetYear,
            'selectedPeriod' => $normalizedMode === 'ytd' ? 'all' : substr($selectedPeriod, 5, 2),
            'selectedPeriodFull' => $selectedPeriod,
            'selectedMode' => $normalizedMode,
            'yearOptions' => $this->personalKpiYearOptions($normalizedKode, $userId, $targetYear),
            'periodOptions' => $this->personalKpiPeriodOptions($targetYear),
            'parameterSchedule' => $parameterSchedule,
            'performanceSchedule' => $performanceSchedule,
            'parameter' => [
                'isScheduled' => (bool) ($parameterSchedule['isActive'] ?? false) || $hasYearFormSchedule,
                'isScheduleActive' => (bool) ($parameterSchedule['isActive'] ?? false),
                'hasYearSchedule' => $hasYearFormSchedule,
                'approvalStatus' => $paramApproval['status'],
                'isSubmitted' => $parameterSubmitted || $paramApproval['submitted'],
                'hasTargets' => $hasParameterTargets,
                'reason' => $this->resolveParameterEmptyReason(
                    $activePengajuan,
                    $hasParameterTargets,
                    $normalizedMode,
                    $selectedPeriod,
                    $targetYear,
                ),
                'message' => $this->resolveParameterMessage(
                    $activePengajuan,
                    $hasParameterTargets,
                    (bool) ($parameterSchedule['isActive'] ?? false),
                    $normalizedMode,
                    $selectedPeriod,
                    $targetYear,
                ),
                'rows' => $parameterRows,
            ],
            'performance' => [
                'isScheduled' => $normalizedMode === 'ytd' ? $hasYearPerformanceSchedule : $isPeriodPerformanceScheduled,
                'isScheduleActive' => (bool) ($performanceSchedule['isActive'] ?? false),
                'hasYearSchedule' => $hasYearPerformanceSchedule,
                'approvalStatus' => $perfApproval['status'],
                'hasParameters' => $perfApproval['hasParameters'],
                'hasDueTarget' => $perfApproval['hasDueTarget'] ?? false,
                'isSubmitted' => (bool) $performanceState['isSubmitted'] || $perfApproval['submitted'],
                'isDrafted' => (bool) $performanceState['isDrafted'],
                'canSubmit' => $performanceCanSubmit,
                'message' => $performanceMessage,
                'summary' => [
                    'score' => $performanceState['score'],
                    'status' => $performanceState['status'],
                    'transactionNo' => $performanceState['transactionNo'],
                ],
                'rows' => $performanceState['rows'],
            ],
            'ytd' => $ytd,
            'score' => $this->resolvePersonalDisplayScore($normalizedMode, $performanceState, $detail, $ytd),
            'achievement' => $this->resolvePersonalDisplayScore($normalizedMode, $performanceState, $detail, $ytd),
            'grade_label' => $performanceState['status'] ?: ($ytd['score'] > 0 ? 'YTD' : 'Belum ada data'),
            'achievement_grade_label' => (function () use ($performanceState) {
                $score = (float) ($performanceState['score'] ?? 0);
                if ($score <= 0 || ! ($performanceState['isSubmitted'] ?? false)) {
                    return '-';
                }
                $ranges = $this->loadPerformanceRanges();
                $grade = $this->resolvePerformanceGrade($score, $ranges);

                return $grade['label'];
            })(),
            'achievement_grade_color' => (function () use ($performanceState) {
                $score = (float) ($performanceState['score'] ?? 0);
                if ($score <= 0 || ! ($performanceState['isSubmitted'] ?? false)) {
                    return '#94a3b8';
                }
                $ranges = $this->loadPerformanceRanges();
                $grade = $this->resolvePerformanceGrade($score, $ranges);

                return $grade['color'];
            })(),
            'ytd_grade_label' => $ytd['grade_label'] ?? '-',
            'ytd_grade_color' => $ytd['grade_color'] ?? '#94a3b8',
            'period_label' => $normalizedMode === 'ytd' ? 'Full Year '.$targetYear : $this->formatSchedulePeriod($selectedPeriod),
            'parameter_period' => $normalizedMode === 'ytd'
                    ? 'Full Year '.$targetYear
                    : 'Periode '.$this->formatSchedulePeriod($selectedPeriod),
            'performance_period' => $normalizedMode === 'ytd'
                    ? 'Full Year '.$targetYear
                    : 'Periode '.$this->formatSchedulePeriod($selectedPeriod),
            'parameter_submitted' => $parameterSubmitted,
            'parameter_has_targets' => $hasParameterTargets,
            'performance_submitted' => (bool) $performanceState['isSubmitted'],
            'parameter_rows' => $parameterRows,
            'parameters' => $parameterRows,
            'parameter_count' => $hasParameterTargets ? count($parameterRows) : 0,
            'performance_item_count' => count(
                array_filter($parameterRows, fn ($row) => (bool) ($row['has_performance_update'] ?? false)),
            ),
            'trend' => array_map(
                fn ($row) => ['month' => $row['month'], 'score' => $row['score'], 'value' => $row['score']],
                $ytd['trend'],
            ),
            'trend_direction' => $this->buildTrendDirection(
                array_map(fn ($row) => ['score' => $row['score']], $ytd['trend']),
            ),
            'emptyMessage' => ! $hasParameterTargets && ! $performanceState['isSubmitted'] && ! $ytd['hasData']
                    ? 'Belum ada jadwal pengisian KPI atau update performance tahun ini.'
                    : null,
        ];
    }

    /**
     * TTL cache ringkasan KPI tim (detik). getTeamSummaries berat: ~1,2 query/anggota
     * (N+1) -> untuk tim besar (131) = ~156 query / 10 detik. Dipakai 3 hot-path
     * (members, summary/kpi, needs-action). Cache pendek per (tim+detail+year)
     * menghilangkan dampaknya: halaman 2/3, refresh, dan endpoint lain baca cache.
     * KPI berubah lambat (jadwal/submit) -> 90s aman & aktual.
     */
    private const TEAM_SUMMARIES_CACHE_TTL = 90;

    public function getTeamSummaries(array $kodeKaryawans, bool $includeDetail = false, ?int $year = null): array
    {
        $codes = collect($kodeKaryawans)->map(fn ($kode) => $this->normalizeKode($kode))->filter()->unique()->sort()->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $targetYear = $this->resolveSummaryYear($year);
        $cacheKey = 'homepage.kpi.team-summaries.v1:'
            .sha1($codes->implode(',')).':'.($includeDetail ? '1' : '0').':'.$targetYear;

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            self::TEAM_SUMMARIES_CACHE_TTL,
            fn () => $this->getTeamSummariesUncached($kodeKaryawans, $includeDetail, $year),
        );
    }

    public function getTeamSummariesUncached(array $kodeKaryawans, bool $includeDetail = false, ?int $year = null): array
    {
        $codes = collect($kodeKaryawans)->map(fn ($kode) => $this->normalizeKode($kode))->filter()->unique()->values();
        $targetYear = $this->resolveSummaryYear($year);

        if ($codes->isEmpty()) {
            return [];
        }

        try {
            $karyawans = DB::table('Karyawan as k')
                ->whereIn('k.Kode_Karyawan', $codes->map(fn ($c) => $this->normalizeKode($c))->all())
                ->select('k.Kode_Karyawan', 'k.Nama', 'k.UserID_Web', 'k.Tgl_Masuk')
                ->get()
                ->keyBy(fn ($row) => $this->normalizeKode($row->Kode_Karyawan));

            if ($karyawans->isEmpty()) {
                return $codes->mapWithKeys(fn ($kode) => [$kode => $this->defaultSummary($targetYear)])->all();
            }

            $userIds = $karyawans->pluck('UserID_Web')->filter()->unique()->values();

            $joinDateByCode = $karyawans
                ->mapWithKeys(fn ($row, $kode) => [$kode => $this->resolveJoinDate($row->Tgl_Masuk ?? null)])
                ->all();

            $activeFormSchedules = $this->resolveActiveSchedules(
                $karyawans->keys()->all(),
                'Flag_Form_KPI',
                $targetYear,
                $joinDateByCode,
            );
            $activePerformanceSchedules = $this->resolveActiveSchedules(
                $karyawans->keys()->all(),
                'Flag_Penilaian_KPI',
                $targetYear,
                $joinDateByCode,
            );

            // Jadwal tahunan (termasuk yang sudah lewat) — untuk menentukan "ada jadwal atau tidak"
            $yearFormScheduleMap = $this->resolveYearlyScheduleMap(
                $karyawans->keys()->all(),
                'Flag_Form_KPI',
                $targetYear,
            );
            $yearPerformanceScheduleMap = $this->resolveYearlyScheduleMap(
                $karyawans->keys()->all(),
                'Flag_Penilaian_KPI',
                $targetYear,
            );

            // Batch approval status: KPI Parameter & Update Performance
            $paramApprovalByUser = $this->resolveParameterApprovalBatch($userIds->all(), $targetYear);

            // Periode performance aktif PER ORANG. Dulu satu periode (milik jadwal
            // pertama) dipakai untuk semua orang, padahal jadwal perorangan bisa
            // berbeda antar anggota.
            $periodePerformancePerUser = $karyawans
                ->mapWithKeys(fn ($row, $kode) => [
                    (string) ($row->UserID_Web ?? '') => $this->normalizePeriodValue(
                        $activePerformanceSchedules[$kode]->Periode ?? null,
                    ),
                ])
                ->all();
            $perfApprovalByUser = $this->resolvePerformanceApprovalBatch(
                $userIds->all(),
                $targetYear,
                $periodePerformancePerUser,
            );
            $formSubmissions = collect();
            if ($userIds->isNotEmpty()) {
                $formSubmissions = DB::table('KPI_Pengajuan')
                    ->whereIn('Id_User', $userIds->all())
                    ->where('Flag_Aktif', 'Y')
                    ->whereNull('Status')
                    ->select('Id_User', 'Id_Penjadwalan', 'Id_Pengajuan')
                    ->get()
                    ->groupBy(fn ($row) => $row->Id_User.'|'.$row->Id_Penjadwalan);
            }

            $performanceRows = $this->loadApprovedPerformanceRows($userIds->all(), $targetYear)->values();
            $performanceByUser = $performanceRows->groupBy('Id_User');
            $performanceRanges = $this->loadPerformanceRanges();

            // BATCH Id_Pengajuan approved per user. Sebelumnya resolveApprovedPengajuanId()
            // dipanggil PER ANGGOTA di dalam loop (N+1 -> ~155 query / 11,9s untuk tim 131
            // = biang utama getTeamSummaries lambat). Satu query ambil semua, lalu loop
            // pakai lookup. Ambil Id_Pengajuan TERBESAR per user (= orderByDesc lama).
            $approvedPengajuanIdByUser = [];
            if ($userIds->isNotEmpty()) {
                $approvedPengajuanIdByUser = DB::table('KPI_Pengajuan')
                    ->whereIn('Id_User', $userIds->all())
                    ->where('Flag_Aktif', 'Y')
                    ->where('Flag_Approval', 'Y')
                    ->whereNull('Status')
                    ->select('Id_User', DB::raw('MAX(Id_Pengajuan) as Id_Pengajuan'))
                    ->groupBy('Id_User')
                    ->get()
                    ->mapWithKeys(fn ($r) => [(string) $r->Id_User => (int) $r->Id_Pengajuan])
                    ->all();
            }

            $detailByUpdateId = collect();
            if ($includeDetail) {
                $latestUpdateIds = $performanceByUser
                    ->map(fn (Collection $rows) => optional($rows->first())->Id_Update_Performance)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($latestUpdateIds)) {
                    $detailByUpdateId = DB::table('KPI_Update_Performance_Detail as upd')
                        ->join('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                        ->join(
                            'KPI_Pengajuan_Detail as pdetail',
                            'pdet.Id_Pengajuan_Detail',
                            '=',
                            'pdetail.Id_Pengajuan_Detail',
                        )
                        ->whereIn('upd.Id_Update_Performance', $latestUpdateIds)
                        ->select(
                            'upd.Id_Update_Performance',
                            'pdetail.Nama_Parameter',
                            DB::raw('COALESCE(upd.Score, 0) as Score'),
                            DB::raw('COALESCE(upd.Achievement, 0) as Achievement'),
                        )
                        ->orderBy('pdetail.Id_Pengajuan_Detail')
                        ->get()
                        ->groupBy('Id_Update_Performance');
                }
            }

            return $karyawans
                ->mapWithKeys(function ($karyawan, string $kode) use (
                    $activeFormSchedules,
                    $activePerformanceSchedules,
                    $yearFormScheduleMap,
                    $yearPerformanceScheduleMap,
                    $paramApprovalByUser,
                    $perfApprovalByUser,
                    $formSubmissions,
                    $performanceByUser,
                    $performanceRanges,
                    $detailByUpdateId,
                    $approvedPengajuanIdByUser,
                    $includeDetail,
                    $targetYear,
                ) {
                    $formSchedule = $activeFormSchedules[$kode] ?? null;
                    $performanceSchedule = $activePerformanceSchedules[$kode] ?? null;
                    $parameterSubmitted = $formSchedule
                        ? $formSubmissions->has($karyawan->UserID_Web.'|'.$formSchedule->Id_Master_Penjadwalan)
                        : false;

                    $userPerformanceRows = $performanceByUser->get($karyawan->UserID_Web, collect())->values();
                    $latestPerformance = $userPerformanceRows->first();

                    // Submission update performance dicek eksplisit terhadap PERIODE jadwal aktif,
                    // bukan sekadar performance terbaru (menghindari false-positive).
                    // Id_Pengajuan approved dari batch lookup (bukan query per-user lagi).
                    $approvedPengajuanId = $approvedPengajuanIdByUser[(string) $karyawan->UserID_Web] ?? null;
                    $performanceSubmitted = $performanceSchedule
                        ? $this->resolvePerformanceSubmittedForSchedule(
                            $approvedPengajuanId,
                            $performanceSchedule->Periode ?? null,
                        )
                        : false;
                    $performanceDueCount =
                        $performanceSchedule && ! $performanceSubmitted
                            ? $this->countDuePerformanceParameters(
                                $approvedPengajuanId,
                                $performanceSchedule->Periode ?? null,
                            )
                            : 0;

                    $score = $latestPerformance ? round((float) ($latestPerformance->Total_Score ?? 0), 1) : 0.0;
                    $grade = $this->resolvePerformanceGrade($score, $performanceRanges);
                    $trend = $userPerformanceRows
                        ->take(6)
                        ->reverse()
                        ->map(function ($row) {
                            $score = round((float) ($row->Total_Score ?? 0), 1);

                            return [
                                'month' => $this->formatShortMonth($row->Periode ?? ($row->Tanggal ?? null)),
                                'score' => $score,
                                'value' => $score,
                            ];
                        })
                        ->values()
                        ->all();

                    // Calculate YTD parameters for this member
                    $userPeriodScores = [];
                    foreach ($userPerformanceRows as $pRow) {
                        $periodKey = $this->normalizePeriodValue($pRow->Periode ?? null);
                        if ($periodKey) {
                            $userPeriodScores[$periodKey] = (float) round((float) ($pRow->Total_Score ?? 0), 1);
                        }
                    }
                    $scopeMeta = $this->resolveKpiYearScope($targetYear, Carbon::now('Asia/Jakarta'));
                    $ytdValues = collect($scopeMeta['ytd_periods'])
                        ->map(fn ($periodKey) => $userPeriodScores[$periodKey] ?? null)
                        ->filter(fn ($val) => $val !== null)
                        ->values();
                    // YTD metode AKUMULASI (disamakan dgn dashboard), bukan averaging skor bulanan.
                    $ytdScore = (float) ($this->accumulatedScoresForUsers(
                        [(string) $karyawan->UserID_Web],
                        $targetYear,
                        $scopeMeta['ytd_periods'],
                    )[(string) $karyawan->UserID_Web] ?? 0.0);
                    $ytdSubmittedCount = $ytdValues->count();

                    $parameters = [];
                    if (
                        $includeDetail &&
                        $latestPerformance &&
                        $detailByUpdateId->has($latestPerformance->Id_Update_Performance)
                    ) {
                        $parameters = $detailByUpdateId
                            ->get($latestPerformance->Id_Update_Performance, collect())
                            ->map(function ($row) {
                                $score = round((float) ($row->Score ?? ($row->Achievement ?? 0)), 1);

                                return [
                                    'name' => $row->Nama_Parameter ?? 'Parameter KPI',
                                    'score' => $score,
                                ];
                            })
                            ->values()
                            ->all();
                    }

                    // dd($parameters);

                    $pendingItems = $this->buildPendingItems(
                        $formSchedule,
                        $performanceSchedule,
                        $parameterSubmitted,
                        $performanceSubmitted,
                        $performanceDueCount,
                    );

                    // Status terstruktur (dipakai kartu hero personal & panel detail).
                    // hasYearSchedule = ada jadwal (aktif/lewat/mendatang) untuk tahun ini
                    $hasYearFormSchedule = ! empty($yearFormScheduleMap[$kode] ?? []);
                    $hasYearPerformanceSchedule = ! empty($yearPerformanceScheduleMap[$kode] ?? []);

                    // Approval status dari batch query
                    $userId = (string) ($karyawan->UserID_Web ?? '');
                    $paramApproval = $paramApprovalByUser[$userId] ?? ['status' => 'none', 'submitted' => false];
                    $perfApproval = $perfApprovalByUser[$userId] ?? [
                        'status' => 'none',
                        'submitted' => false,
                        'hasParameters' => false,
                    ];

                    $parameterState = [
                        'isScheduled' => $formSchedule !== null || $hasYearFormSchedule,
                        'isScheduleActive' => $formSchedule !== null,
                        'hasYearSchedule' => $hasYearFormSchedule,
                        'isSubmitted' => $parameterSubmitted || $paramApproval['submitted'],
                        'approvalStatus' => $paramApproval['status'],
                        'periodLabel' => $this->formatSchedulePeriod($formSchedule?->Periode),
                    ];
                    $performanceState = [
                        'isScheduled' => $performanceSchedule !== null || $hasYearPerformanceSchedule,
                        'isScheduleActive' => $performanceSchedule !== null,
                        'hasYearSchedule' => $hasYearPerformanceSchedule,
                        'isSubmitted' => $performanceSubmitted || $perfApproval['submitted'],
                        'approvalStatus' => $perfApproval['status'],
                        'hasParameters' => $perfApproval['hasParameters'],
                        'hasDueTarget' => $perfApproval['hasDueTarget'] ?? false,
                        'dueCount' => $performanceDueCount,
                        'periodLabel' => $performanceSchedule
                            ? $this->formatSchedulePeriod($performanceSchedule->Periode)
                            : $this->formatSchedulePeriod($this->currentPerformancePeriodHint()),
                    ];

                    $summary = [
                        'has_data' => $latestPerformance !== null ||
                            $formSchedule !== null ||
                            $performanceSchedule !== null ||
                            $hasYearFormSchedule ||
                            $hasYearPerformanceSchedule ||
                            $paramApproval['submitted'] ||
                            $perfApproval['submitted'],
                        'kpi_year' => $targetYear,
                        'year' => $targetYear,
                        'score' => $score,
                        'current_score' => $score,
                        'grade_label' => $grade['label'],
                        'grade' => $grade['label'],
                        'achievement' => $score,
                        'achievement_grade_label' => $score > 0 ? $grade['label'] : '-',
                        'achievement_grade_color' => $score > 0 ? $grade['color'] : '#94a3b8',
                        'weighted_score' => $score,
                        'ytd_score' => $ytdScore,
                        'ytd_submitted_count' => $ytdSubmittedCount,
                        'ytd_grade_label' => $ytdScore > 0 ? $this->resolvePerformanceGrade($ytdScore, $performanceRanges)['label'] : '-',
                        'ytd_grade_color' => $ytdScore > 0 ? $this->resolvePerformanceGrade($ytdScore, $performanceRanges)['color'] : '#94a3b8',
                        'parameter_scheduled' => $parameterState['isScheduled'],
                        'performance_scheduled' => $performanceState['isScheduled'],
                        'parameter_submitted' => $parameterSubmitted,
                        'performance_submitted' => $performanceSubmitted,
                        'parameter' => $parameterState,
                        'performance' => $performanceState,
                        'period_label' => $this->formatSchedulePeriod($formSchedule?->Periode),
                        'parameter_period' => $this->formatSchedulePeriod($formSchedule?->Periode),
                        'performance_period' => $performanceState['periodLabel'],
                        'rank_label' => '-',
                        'rank_meta' => 'Belum dihitung',
                        'trend_direction' => $this->buildTrendDirection($trend),
                        'deadline_text' => $this->buildDeadlineText(
                            $formSchedule,
                            $performanceSchedule,
                            $parameterSubmitted,
                            $performanceSubmitted,
                            $performanceDueCount,
                        ),
                        'pending_reviews' => 0,
                        'pending_items' => $pendingItems,
                        'warning_items' => $pendingItems,
                        'trend' => $trend,
                        'monthly_achievement' => array_map(
                            fn ($item) => ['month' => $item['month'], 'value' => $item['score']],
                            $trend,
                        ),
                        'parameters' => $parameters,
                        'last_update' => $latestPerformance?->Tanggal
                            ? Carbon::parse($latestPerformance->Tanggal)->format('Y-m-d')
                            : null,
                    ];

                    return [$kode => $summary];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::error('HomepageKpiService error: '.$e->getMessage());

            return $codes->mapWithKeys(fn ($kode) => [$kode => $this->defaultSummary($targetYear)])->all();
        }
    }

    /**
     * Resolusi jadwal KPI yang AKTIF sekarang per karyawan.
     *
     * Aturan (mengikuti FormKpiController/UpdatePerformanceController):
     *  - Jadwal aktif = Jadwal_Mulai <= now <= Jadwal_Akhir.
     *  - Prioritas jadwal perorangan dulu, baru jadwal global.
     *  - Jadwal global hanya berlaku bila karyawan sudah join (Tgl_Masuk) saat
     *    periode pengisian — dicek via $joinDateByCode (jika diberikan).
     *
     * @param  array<string,?\Carbon\Carbon>  $joinDateByCode  Map kode => Tgl_Masuk (Carbon) untuk filter eligibility global.
     */
    protected function resolveActiveSchedules(
        array $kodeKaryawans,
        string $flagColumn,
        ?int $year = null,
        array $joinDateByCode = [],
    ): array {
        $codes = collect($kodeKaryawans)->map(fn ($kode) => $this->normalizeKode($kode))->filter()->unique()->values();

        if ($codes->isEmpty()) {
            return [];
        }

        $now = Carbon::now('Asia/Jakarta');
        $targetYear = $this->resolveSummaryYear($year);

        $peroranganQuery = DB::table('N_KPI_Master_Penjadwalan_Perorangan as det')
            ->join('KPI_Master_Penjadwalan as master', 'det.Id_Master_Penjadwalan', '=', 'master.Id_Master_Penjadwalan')
            ->whereIn('det.Kode_Karyawan', $codes->map(fn ($c) => $this->normalizeKode($c))->all())
            ->where('det.Flag_Aktif', 'Y')
            ->where('master.Flag_Perorangan', 'Y')
            ->where("master.$flagColumn", 'Y')
            ->where('master.Flag_Aktif', 'Y')
            ->where('master.Jadwal_Mulai', '<=', $now)
            ->where('master.Jadwal_Akhir', '>=', $now)
            ->select(
                'det.Kode_Karyawan',
                'det.Start_Periode',
                'master.Id_Master_Penjadwalan',
                'master.Nama_Kegiatan',
                'master.Periode',
                'master.Jadwal_Mulai',
                'master.Jadwal_Akhir',
                'master.Tahun',
                'master.Flag_Perorangan',
            );

        if ($targetYear > 0) {
            $peroranganQuery->where('master.Tahun', (string) $targetYear);
        }

        $perorangan = $peroranganQuery
            ->orderByDesc('master.Id_Master_Penjadwalan')
            ->get()
            ->groupBy(fn ($row) => $this->normalizeKode($row->Kode_Karyawan))
            ->map(fn (Collection $rows) => $rows->first());

        $globalQuery = DB::table('KPI_Master_Penjadwalan')
            ->where($flagColumn, 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->select(
                'Id_Master_Penjadwalan',
                'Nama_Kegiatan',
                'Periode',
                'Jadwal_Mulai',
                'Jadwal_Akhir',
                'Tahun',
                'Flag_Perorangan',
                DB::raw('NULL as Start_Periode'),
            );

        if ($targetYear > 0) {
            $globalQuery->where('Tahun', (string) $targetYear);
        }

        $global = $globalQuery->orderByDesc('Id_Master_Penjadwalan')->first();

        return $codes
            ->mapWithKeys(function ($kode) use ($perorangan, $global, $joinDateByCode) {
                $personal = $perorangan->get($kode);
                if ($personal) {
                    return [$kode => $personal];
                }

                // Jadwal global: hanya berlaku bila karyawan sudah join saat periode pengisian.
                if ($global) {
                    $joinDate = $joinDateByCode[$kode] ?? null;
                    if (! $this->isEligibleForSchedulePeriod($joinDate, (string) ($global->Periode ?? ''))) {
                        return [$kode => null];
                    }
                }

                return [$kode => $global];
            })
            ->all();
    }

    /**
     * Resolusi Id_Pengajuan KPI yang aktif & disetujui untuk seorang user.
     * Dipakai sebagai acuan submission update performance & target jatuh tempo.
     */
    protected function resolveApprovedPengajuanId(?string $userId): ?int
    {
        $userId = trim((string) $userId);
        if ($userId === '') {
            return null;
        }

        $row = DB::table('KPI_Pengajuan')
            ->where('Id_User', $userId)
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->orderByDesc('Id_Pengajuan')
            ->value('Id_Pengajuan');

        return $row ? (int) $row : null;
    }

    /**
     * Apakah update performance untuk periode jadwal tersebut sudah diisi.
     */
    protected function resolvePerformanceSubmittedForSchedule(?int $pengajuanId, ?string $period): bool
    {
        $normalizedPeriod = $this->normalizePeriodValue($period);
        if (! $pengajuanId || ! $normalizedPeriod) {
            return false;
        }

        return DB::table('KPI_Update_Performance')
            ->where('Id_Pengajuan', $pengajuanId)
            ->where('Flag_Aktif', 'Y')
            ->where('Periode', $normalizedPeriod)
            // Pengajuan yang ditolak wajib diisi ulang, jadi belum terhitung "sudah diisi".
            ->where('Status_Pengajuan', '<>', 'ditolak')
            ->exists();
    }

    /**
     * Jumlah parameter yang jatuh tempo (punya target breakdown) pada periode jadwal.
     * Mengikuti logika exact-match periode di UpdatePerformanceController::getDataKpiItems.
     */
    protected function countDuePerformanceParameters(?int $pengajuanId, ?string $period): int
    {
        $normalizedPeriod = $this->normalizePeriodValue($period);
        if (! $pengajuanId || ! $normalizedPeriod) {
            return 0;
        }

        return (int) DB::table('KPI_Pengajuan_Detail as detail')
            ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Detail', '=', 'detail.Id_Pengajuan_Detail')
            ->where('detail.Id_Pengajuan', $pengajuanId)
            ->where('det.Periode', $normalizedPeriod)
            ->distinct()
            ->count('detail.Id_Pengajuan_Detail');
    }

    protected function resolveSummaryYear(?int $year = null): int
    {
        $nowYear = (int) Carbon::now('Asia/Jakarta')->format('Y');
        if (is_int($year) && $year >= 2000 && $year <= 2100) {
            return $year;
        }

        try {
            $now = Carbon::now('Asia/Jakarta');
            $latest = DB::table('KPI_Master_Penjadwalan')
                ->where(function ($query) {
                    $query->where('Flag_Form_KPI', 'Y')->orWhere('Flag_Penilaian_KPI', 'Y');
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('Jadwal_Mulai')->orWhere('Jadwal_Mulai', '<=', $now);
                })
                ->pluck('Tahun')
                ->map(fn ($item) => (int) $item)
                ->filter(fn ($item) => $item >= 2000 && $item <= 2100)
                ->max();

            return $latest ?: $nowYear;
        } catch (\Throwable $e) {
            return $nowYear;
        }
    }

    protected function loadApprovedPerformanceRows(array $userIds, ?int $year = null): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        $query = DB::table('KPI_Update_Performance as up')
            ->whereIn('up.Id_User', $userIds)
            ->whereNull('up.Status')
            ->where('up.Status_Pengajuan', 'disetujui')
            ->where('up.Flag_Approval', 'Y')
            ->where('up.Flag_Verifikasi', 'Y')
            // Tanpa Flag_Aktif, header lama yang sudah digantikan revisi ikut terbaca
            // (penggantian hanya menyetel Flag_Aktif='T', Status tetap NULL).
            ->where('up.Flag_Aktif', 'Y')
            ->select('up.Id_User', 'up.Id_Update_Performance', 'up.Periode', 'up.Total_Score', 'up.Tanggal');

        if (is_int($year) && $year >= 2000 && $year <= 2100) {
            $yearString = (string) $year;
            $query->where(function ($q) use ($yearString) {
                $q->whereRaw('LEFT(LTRIM(RTRIM(CAST(up.Periode as varchar(30)))), 4) = ?', [$yearString])->orWhere(
                    function ($inner) use ($yearString) {
                        $inner
                            ->where(function ($blankPeriod) {
                                $blankPeriod
                                    ->whereNull('up.Periode')
                                    ->orWhereRaw("LTRIM(RTRIM(CAST(up.Periode as varchar(30)))) = ''");
                            })
                            ->whereRaw('YEAR(up.Tanggal) = ?', [(int) $yearString]);
                    },
                );
            });
        }

        return $query->orderBy('up.Id_User')->orderByDesc('up.Tanggal')->orderByDesc('up.Id_Update_Performance')->get();
    }

    protected function resolveYearlyScheduleMap(array $kodeKaryawans, string $flagColumn, int $year): array
    {
        $codes = collect($kodeKaryawans)->map(fn ($kode) => $this->normalizeKode($kode))->filter()->unique()->values();

        if ($codes->isEmpty()) {
            return [];
        }

        $perCodeMonthMap = $codes->mapWithKeys(fn ($code) => [$code => []])->all();

        // dd($codes->count(), $year, $flagColumn);
        $peroranganRows = DB::table('N_KPI_Master_Penjadwalan_Perorangan as det')
            ->join('KPI_Master_Penjadwalan as master', 'det.Id_Master_Penjadwalan', '=', 'master.Id_Master_Penjadwalan')
            ->whereIn('det.Kode_Karyawan', $codes->map(fn ($c) => $this->normalizeKode($c))->all())
            ->where('Tahun', (string) $year)
            ->where('master.Flag_Perorangan', 'Y')
            ->where($flagColumn, 'Y')
            ->select('det.Kode_Karyawan', 'master.Periode')
            ->get();

        foreach ($peroranganRows as $row) {
            $code = $this->normalizeKode($row->Kode_Karyawan ?? '');
            $period = $this->normalizePeriodValue($row->Periode ?? null);
            if ($code === '' || ! $period || ! str_starts_with($period, $year.'-')) {
                continue;
            }

            $perCodeMonthMap[$code][$period] = true;
        }

        $globalRows = DB::table('KPI_Master_Penjadwalan')
            // ->where('Flag_Aktif', 'Y')
            ->where($flagColumn, 'Y')
            ->where(function ($query) {
                $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->select('Periode')
            ->get();
        // dd($globalRows->toSql(), $globalRows->getBindings());

        $globalMonths = [];
        foreach ($globalRows as $row) {
            $period = $this->normalizePeriodValue($row->Periode ?? null);
            if ($period && str_starts_with($period, $year.'-')) {
                $globalMonths[$period] = true;
            }
        }

        foreach ($perCodeMonthMap as $code => $months) {
            foreach ($globalMonths as $period => $flag) {
                if (! isset($perCodeMonthMap[$code][$period])) {
                    $perCodeMonthMap[$code][$period] = $flag;
                }
            }
        }

        // dd($perCodeMonthMap);

        return $perCodeMonthMap;
    }

    protected function loadPerformanceRanges(): Collection
    {
        return DB::table('KPI_Master_Performance')
            ->select('Range_Awal', 'Range_Akhir', 'Keterangan', 'Warna', 'Kode')
            ->orderBy('Range_Awal')
            ->get();
    }

    protected function resolvePerformanceGrade(float $score, Collection $ranges): array
    {
        $match = $ranges->first(function ($range) use ($score) {
            return $score >= (float) ($range->Range_Awal ?? 0) && $score <= (float) ($range->Range_Akhir ?? 0);
        });

        if (! $match) {
            $match = $ranges
                ->filter(fn ($range) => $score >= (float) ($range->Range_Awal ?? 0))
                ->sortByDesc('Range_Awal')
                ->first();
        }

        return [
            'label' => $match->Keterangan ?? 'Belum ada penilaian',
            'color' => $match->Warna ?? '#94a3b8',
            'code' => $match->Kode ?? 'NA',
        ];
    }

    /**
     * Bangun daftar alert KPI (parameter & update performance) yang akurat.
     *
     * Setiap item: ['id','title','description','tone'] dengan tone 'warning'|'info'.
     */
    protected function buildPendingItems(
        ?object $formSchedule,
        ?object $performanceSchedule,
        bool $parameterSubmitted,
        bool $performanceSubmitted,
        int $performanceDueCount = 0,
    ): array {
        $items = [];
        $now = Carbon::now('Asia/Jakarta');

        // ── Parameter KPI ──
        // Bila tidak ada jadwal eligible (sudah difilter di resolveActiveSchedules),
        // tidak memunculkan alert sama sekali.
        if ($formSchedule && ! $parameterSubmitted) {
            $periodLabel = $this->formatSchedulePeriod($formSchedule->Periode ?? null);
            $isPassed =
                ! empty($formSchedule->Jadwal_Akhir) &&
                $now->greaterThan(Carbon::parse($formSchedule->Jadwal_Akhir, 'Asia/Jakarta'));

            if ($isPassed) {
                $items[] = [
                    'id' => 'parameter',
                    'title' => 'KPI Parameter tidak diisi',
                    'description' => 'Periode pengisian '.$periodLabel.' telah berakhir.',
                    'tone' => 'warning',
                ];
            } else {
                $deadline = ! empty($formSchedule->Jadwal_Akhir)
                    ? ' — batas '.Carbon::parse($formSchedule->Jadwal_Akhir)->translatedFormat('d M Y')
                    : '';
                $items[] = [
                    'id' => 'parameter',
                    'title' => 'KPI Parameter belum diisi',
                    'description' => 'Periode '.$periodLabel.$deadline,
                    'tone' => 'warning',
                ];
            }
        }

        // ── Update Performance ──
        if (! $performanceSchedule) {
            // Tidak ada jadwal update performance yang aktif → info netral.
            $items[] = [
                'id' => 'performance',
                'title' => 'Belum ada penjadwalan update performance',
                'description' => 'Periode '.$this->formatSchedulePeriod($this->currentPerformancePeriodHint()),
                'tone' => 'info',
            ];
        } elseif (! $performanceSubmitted && $performanceDueCount === 0) {
            // Tanpa target jatuh tempo tidak ada yang bisa diisi: halaman Update KPI
            // menampilkan "Tidak Ada Target" dan monitoring melabelinya sama. Dulu
            // kartu ini tetap memberi peringatan "belum diisi", menyuruh orang
            // mengisi sesuatu yang tidak ada.
            $items[] = [
                'id' => 'performance',
                'title' => 'Tidak ada target jatuh tempo',
                'description' => 'Tidak ada update performance untuk periode '
                    .$this->formatSchedulePeriod($performanceSchedule->Periode ?? null),
                'tone' => 'info',
            ];
        } elseif (! $performanceSubmitted) {
            $periodLabel = $this->formatSchedulePeriod($performanceSchedule->Periode ?? null);
            $items[] = [
                'id' => 'performance',
                'title' => 'Update performance belum diisi',
                'description' => $performanceDueCount.' parameter jatuh tempo untuk periode '.$periodLabel,
                'tone' => 'warning',
            ];
        }

        return $items;
    }

    /**
     * Periode acuan update performance bila tidak ada jadwal aktif (bulan lalu = target umum).
     */
    protected function currentPerformancePeriodHint(): string
    {
        return Carbon::now('Asia/Jakarta')->subMonthNoOverflow()->format('Y-m');
    }

    protected function buildDeadlineText(
        ?object $formSchedule,
        ?object $performanceSchedule,
        bool $parameterSubmitted,
        bool $performanceSubmitted,
        int $performanceDueCount,
    ): ?string {
        if ($formSchedule && ! $parameterSubmitted && ! empty($formSchedule->Jadwal_Akhir)) {
            return 'Deadline form KPI: '.Carbon::parse($formSchedule->Jadwal_Akhir)->translatedFormat('d M Y');
        }

        // Tanpa target jatuh tempo tidak ada tenggat yang harus dikejar.
        if ($performanceSchedule && ! $performanceSubmitted && $performanceDueCount > 0 && ! empty($performanceSchedule->Jadwal_Akhir)) {
            return 'Deadline update performance: '.
                Carbon::parse($performanceSchedule->Jadwal_Akhir)->translatedFormat('d M Y');
        }

        return null;
    }

    protected function matchPerformancePeriod(?object $latestPerformance, ?object $performanceSchedule): bool
    {
        if (! $latestPerformance) {
            return false;
        }

        if (! $performanceSchedule || empty($performanceSchedule->Periode)) {
            return true;
        }

        $latestPeriod = $this->normalizePeriodValue($latestPerformance->Periode ?? null);
        $schedulePeriod = $this->normalizePeriodValue($performanceSchedule->Periode ?? null);

        return $latestPeriod !== null && $schedulePeriod !== null ? $latestPeriod === $schedulePeriod : true;
    }

    protected function resolveJoinDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value, 'Asia/Jakarta')->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function isEligibleForSchedulePeriod(?Carbon $joinDate, ?string $period): bool
    {
        if (! $joinDate) {
            return true;
        }

        $normalizedPeriod = $this->normalizePeriodValue($period);
        if (! $normalizedPeriod) {
            return true;
        }

        try {
            $periodEnd = Carbon::createFromFormat('Y-m', $normalizedPeriod, 'Asia/Jakarta')->endOfMonth()->endOfDay();

            return $joinDate->lessThanOrEqualTo($periodEnd);
        } catch (\Throwable $e) {
            return true;
        }
    }

    protected function normalizePeriodValue($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m');
        } catch (\Throwable $e) {
            $raw = trim((string) $value);
            if (preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
                return $raw;
            }

            return null;
        }
    }

    protected function formatSchedulePeriod($value): string
    {
        if (! $value) {
            return 'Periode berjalan';
        }

        try {
            return Carbon::parse($value)->translatedFormat('F Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    protected function formatShortMonth($value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->translatedFormat('M');
        } catch (\Throwable $e) {
            $raw = trim((string) $value);
            if (preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
                return Carbon::createFromFormat('Y-m', $raw)->translatedFormat('M');
            }

            return substr($raw, 0, 3);
        }
    }

    protected function buildTrendDirection(array $trend): string
    {
        if (count($trend) < 2) {
            return '0%';
        }

        $first = (float) ($trend[count($trend) - 2]['score'] ?? 0);
        $last = (float) ($trend[count($trend) - 1]['score'] ?? 0);
        $delta = round($last - $first, 1);

        if ($delta > 0) {
            return '+'.$delta;
        }

        return (string) $delta;
    }

    protected function mergePersonalSummaryWithMemberDetail(array $baseSummary, array $detail, int $year): array
    {
        $summary = (array) ($detail['summary'] ?? []);
        $parameterRows = collect($detail['parameter_rows'] ?? [])
            ->map(function ($row) {
                return [
                    'id' => (string) ($row['id'] ?? ''),
                    'name' => (string) ($row['parameter'] ?? ($row['name'] ?? 'Parameter KPI')),
                    'parameter' => (string) ($row['parameter'] ?? ($row['name'] ?? 'Parameter KPI')),
                    'score' => (float) ($row['score'] ?? 0),
                    'achievement' => (float) ($row['achievement'] ?? ($row['score'] ?? 0)),
                    'weight' => (float) ($row['bobot'] ?? ($row['weight'] ?? 0)),
                    'bobot' => (float) ($row['bobot'] ?? ($row['weight'] ?? 0)),
                    'target' => (float) ($row['target'] ?? 0),
                    'actual' => (float) ($row['actual'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'status_color' => (string) ($row['status_color'] ?? '#94a3b8'),
                    'status_code' => (string) ($row['status_code'] ?? ''),
                    'form_status' => (string) ($row['form_status'] ?? ''),
                    'has_performance_update' => (bool) ($row['has_performance_update'] ?? false),
                ];
            })
            ->values()
            ->all();

        $trend = collect($detail['trend_12_months'] ?? [])
            ->map(
                fn ($row) => [
                    'month' => (string) ($row['month'] ?? ''),
                    'period' => (string) ($row['period'] ?? ''),
                    'score' => (float) ($row['score'] ?? 0),
                    'value' => (float) ($row['score'] ?? 0),
                ],
            )
            ->values()
            ->all();

        $periodLabel = (string) ($summary['period_label'] ?? ($baseSummary['period_label'] ?? 'Periode berjalan'));
        $parameterCount = (int) ($summary['form_parameter_count'] ?? count($parameterRows));
        $performanceCount = (int) ($summary['performance_item_count'] ?? 0);

        // CATATAN: status jadwal & submission (parameter_scheduled, performance_scheduled,
        // parameter_submitted, performance_submitted, parameter/performance, pending_items)
        // diambil dari $baseSummary (getTeamSummaries) yang sudah akurat — JANGAN ditimpa di sini.
        // Merge ini hanya menambah data tampilan: skor terpilih, rows parameter, dan tren.
        return array_merge($baseSummary, [
            'has_data' => (bool) (($baseSummary['has_data'] ?? false) || $parameterCount > 0 || count($trend) > 0),
            'kpi_year' => $year,
            'year' => $year,
            'score' => (float) ($summary['selected_score'] ?? ($baseSummary['score'] ?? 0)),
            'current_score' => (float) ($summary['selected_score'] ?? ($baseSummary['current_score'] ?? 0)),
            'achievement' => (float) ($summary['selected_score'] ?? ($baseSummary['achievement'] ?? 0)),
            'weighted_score' => (float) ($summary['selected_score'] ?? ($baseSummary['weighted_score'] ?? 0)),
            'period_label' => $periodLabel,
            'parameters' => $parameterRows,
            'parameter_rows' => $parameterRows,
            'parameter_count' => $parameterCount,
            'performance_item_count' => $performanceCount,
            'trend' => $trend,
            'monthly_achievement' => array_map(
                fn ($row) => ['month' => $row['month'], 'value' => $row['score']],
                $trend,
            ),
        ]);
    }

    protected function resolvePersonalKpiMember(string $kodeKaryawan): ?object
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                'k.ID_Level_Jabatan',
                '=',
                'lvl.ID_Level_Jabatan',
            )
            ->where('k.Kode_Karyawan', $this->normalizeKode($kodeKaryawan))
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserID_Web',
                'k.Tgl_Masuk',
                DB::raw("COALESCE(lvl.nama_jabatan, '-') as nama_jabatan"),
                DB::raw("COALESCE(div.nama_divisi, '-') as nama_divisi"),
            )
            ->first();
    }

    protected function resolvePersonalSchedule(
        string $kodeKaryawan,
        string $flagColumn,
        int $year,
        bool $onlyActive = true,
    ): ?object {
        $now = Carbon::now('Asia/Jakarta');

        $peroranganQuery = DB::table('N_KPI_Master_Penjadwalan_Perorangan as det')
            ->join('KPI_Master_Penjadwalan as master', 'det.Id_Master_Penjadwalan', '=', 'master.Id_Master_Penjadwalan')
            ->where('det.Kode_Karyawan', $this->normalizeKode($kodeKaryawan))
            ->where('master.Flag_Perorangan', 'Y')
            ->where('det.Flag_Aktif', 'Y')
            ->where("master.$flagColumn", 'Y')
            ->where('master.Tahun', (string) $year)
            ->select(
                'master.Id_Master_Penjadwalan',
                'master.Nama_Kegiatan',
                'master.Periode',
                'master.Jadwal_Mulai',
                'master.Jadwal_Akhir',
                'master.Tahun',
                'master.Flag_Perorangan',
                'det.Start_Periode',
            );

        if ($onlyActive) {
            $peroranganQuery->where('master.Jadwal_Mulai', '<=', $now)->where('master.Jadwal_Akhir', '>=', $now);
        }

        $perorangan = $peroranganQuery->orderByDesc('master.Id_Master_Penjadwalan')->first();
        if ($perorangan) {
            return $perorangan;
        }

        if (! $onlyActive) {
            $futurePeroranganQuery = DB::table('N_KPI_Master_Penjadwalan_Perorangan as det')
                ->join(
                    'KPI_Master_Penjadwalan as master',
                    'det.Id_Master_Penjadwalan',
                    '=',
                    'master.Id_Master_Penjadwalan',
                )
                ->where('det.Kode_Karyawan', $this->normalizeKode($kodeKaryawan))
                ->where('master.Flag_Perorangan', 'Y')
                ->where("master.$flagColumn", 'Y')
                ->where('master.Tahun', (string) $year)
                ->where('master.Jadwal_Mulai', '>', $now)
                ->select(
                    'master.Id_Master_Penjadwalan',
                    'master.Nama_Kegiatan',
                    'master.Periode',
                    'master.Jadwal_Mulai',
                    'master.Jadwal_Akhir',
                    'master.Tahun',
                    'master.Flag_Perorangan',
                    'det.Start_Periode',
                )
                ->orderBy('master.Jadwal_Mulai')
                ->orderByDesc('master.Id_Master_Penjadwalan');

            $futurePerorangan = $futurePeroranganQuery->first();
            if ($futurePerorangan) {
                return $futurePerorangan;
            }
        }

        $globalQuery = DB::table('KPI_Master_Penjadwalan')
            ->where($flagColumn, 'Y')
            ->where('Tahun', (string) $year)
            ->where(function ($query) {
                $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->select(
                'Id_Master_Penjadwalan',
                'Nama_Kegiatan',
                'Periode',
                'Jadwal_Mulai',
                'Jadwal_Akhir',
                'Tahun',
                'Flag_Perorangan',
                DB::raw('NULL as Start_Periode'),
            );

        if ($onlyActive) {
            $globalQuery->where('Jadwal_Mulai', '<=', $now)->where('Jadwal_Akhir', '>=', $now);
        }

        $global = $globalQuery->orderByDesc('Id_Master_Penjadwalan')->first();
        if ($global) {
            return $global;
        }

        if (! $onlyActive) {
            $futureGlobal = DB::table('KPI_Master_Penjadwalan')
                ->where($flagColumn, 'Y')
                ->where('Tahun', (string) $year)
                ->where(function ($query) {
                    $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
                })
                ->where('Jadwal_Mulai', '>', $now)
                ->select(
                    'Id_Master_Penjadwalan',
                    'Nama_Kegiatan',
                    'Periode',
                    'Jadwal_Mulai',
                    'Jadwal_Akhir',
                    'Tahun',
                    'Flag_Perorangan',
                    DB::raw('NULL as Start_Periode'),
                )
                ->orderBy('Jadwal_Mulai')
                ->orderByDesc('Id_Master_Penjadwalan')
                ->first();

            if ($futureGlobal) {
                return $futureGlobal;
            }
        }

        return null;
    }

    protected function schedulePayload(?object $schedule, ?string $selectedPeriod = null): array
    {
        // dd($schedule);
        $period = $this->normalizePeriodValue($schedule->Periode ?? ($schedule->Start_Periode ?? $selectedPeriod));
        $isActive = false;

        if ($schedule && ! empty($schedule->Jadwal_Mulai) && ! empty($schedule->Jadwal_Akhir)) {
            try {
                $now = Carbon::now('Asia/Jakarta');
                $start = Carbon::parse($schedule->Jadwal_Mulai, 'Asia/Jakarta');
                $end = Carbon::parse($schedule->Jadwal_Akhir, 'Asia/Jakarta');
                $isActive = $now->betweenIncluded($start, $end);
            } catch (\Throwable $e) {
                $isActive = false;
            }
        }

        return [
            'id' => $schedule ? (int) ($schedule->Id_Master_Penjadwalan ?? 0) : null,
            'isActive' => $isActive,
            'name' => (string) ($schedule->Nama_Kegiatan ?? ''),
            'period' => $period,
            'periodLabel' => $period ? $this->formatSchedulePeriod($period) : '-',
            'startAt' => $schedule?->Jadwal_Mulai,
            'endAt' => $schedule?->Jadwal_Akhir,
        ];
    }

    protected function composePersonalPeriod($period, int $year): ?string
    {
        $value = trim((string) $period);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-(\d{2})$/', $value, $matches) === 1) {
            return sprintf('%04d-%02d', $year, (int) $matches[1]);
        }

        if (preg_match('/^\d{1,2}$/', $value) === 1) {
            $month = (int) $value;
            if ($month >= 1 && $month <= 12) {
                return sprintf('%04d-%02d', $year, $month);
            }
        }

        return $this->normalizePeriodValue($value);
    }

    protected function resolvePersonalKpiPengajuan(string $userId, int $year, ?int $penjadwalanId = null): ?object
    {
        // dd($userId, $year, $penjadwalanId);
        if ($userId === '') {
            return null;
        }

        $baseQuery = fn () => DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
            ->where('kp.Id_User', $userId)
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('mp.Flag_Form_KPI', 'Y')
            ->where('mp.Tahun', (string) $year)
            ->select('kp.*', 'mp.Tahun as kpi_year');

        // Utamakan pengajuan pada penjadwalan yang diminta, tapi jangan mengunci ke
        // sana: kalau ada penjadwalan form-KPI baru (mis. jadwal "TEST") yang belum
        // punya pengajuan, jangan sampai parameter tahunan yang sudah disubmit user
        // ikut hilang. Fallback ke pengajuan aktif tahun tsb.
        if ($penjadwalanId) {
            $scoped = $baseQuery()
                ->where('kp.Id_Penjadwalan', $penjadwalanId)
                ->orderByDesc('kp.Id_Pengajuan')
                ->first();

            if ($scoped) {
                return $scoped;
            }
        }

        return $baseQuery()->orderByDesc('kp.Id_Pengajuan')->first();
    }

    protected function hasParameterTargetForPeriod(int $pengajuanId, ?string $period): bool
    {
        if ($pengajuanId <= 0 || ! $period) {
            return false;
        }

        return DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as detail', 'det.Id_Pengajuan_Detail', '=', 'detail.Id_Pengajuan_Detail')
            ->where('detail.Id_Pengajuan', $pengajuanId)
            ->where('det.Periode', $period)
            ->exists();
    }

    protected function hasParameterTargetForYear(int $pengajuanId, int $year): bool
    {
        if ($pengajuanId <= 0) {
            return false;
        }

        return DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as detail', 'det.Id_Pengajuan_Detail', '=', 'detail.Id_Pengajuan_Detail')
            ->where('detail.Id_Pengajuan', $pengajuanId)
            ->whereRaw('LEFT(det.Periode, 4) = ?', [(string) $year])
            ->exists();
    }

    protected function resolveParameterEmptyReason(
        ?object $pengajuan,
        bool $hasTarget,
        string $mode,
        string $period,
        int $year,
    ): string {
        if ($hasTarget) {
            return 'available';
        }

        if (! $pengajuan) {
            return 'no_parameter_form';
        }

        return $mode === 'ytd' ? 'no_year_target' : 'no_period_target';
    }

    protected function resolveParameterMessage(
        ?object $pengajuan,
        bool $hasTarget,
        bool $isScheduleActive,
        string $mode,
        string $period,
        int $year,
    ): string {
        if ($hasTarget && $isScheduleActive) {
            return 'Sudah mengisi';
        }

        if ($hasTarget) {
            return 'Belum mengisi';
        }

        if (! $pengajuan) {
            return 'Belum ada jadwal';
        }

        return $mode === 'ytd' ? 'Target belum ada' : 'Target belum ada';
    }

    protected function normalizePersonalParameterRows(array $rows): array
    {
        return collect($rows)
            ->map(
                fn ($row) => [
                    'id' => (string) ($row['id'] ?? ''),
                    'name' => (string) ($row['parameter'] ?? ($row['name'] ?? 'Parameter KPI')),
                    'parameter' => (string) ($row['parameter'] ?? ($row['name'] ?? 'Parameter KPI')),
                    'score' => (float) ($row['score'] ?? 0),
                    'achievement' => (float) ($row['achievement'] ?? ($row['score'] ?? 0)),
                    'weight' => (float) ($row['bobot'] ?? ($row['weight'] ?? 0)),
                    'bobot' => (float) ($row['bobot'] ?? ($row['weight'] ?? 0)),
                    'target' => (float) ($row['target'] ?? 0),
                    'actual' => (float) ($row['actual'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'status_color' => (string) ($row['status_color'] ?? '#94a3b8'),
                    'status_code' => (string) ($row['status_code'] ?? ''),
                    'form_status' => (string) ($row['form_status'] ?? ''),
                    'has_performance_update' => (bool) ($row['has_performance_update'] ?? false),
                ],
            )
            ->values()
            ->all();
    }

    protected function resolvePersonalPerformanceState(string $userId, int $pengajuanId, ?string $period): array
    {
        $empty = [
            'isSubmitted' => false,
            'isDrafted' => false,
            'score' => 0,
            'status' => '',
            'transactionNo' => '',
            'rows' => [],
        ];

        if ($userId === '' || ! $period) {
            return $empty;
        }

        $final = DB::table('KPI_Update_Performance')
            ->where('Id_User', $userId)
            ->where('Periode', $period)
            ->whereNull('Status')
            ->where('Status_Pengajuan', 'disetujui')
            ->where('Flag_Approval', 'Y')
            ->where('Flag_Verifikasi', 'Y')
            ->orderByDesc('Id_Update_Performance')
            ->first();

        if ($final) {
            return [
                'isSubmitted' => true,
                'isDrafted' => false,
                'score' => (float) ($final->Total_Score ?? 0),
                'status' => (string) ($final->Status_Pengajuan ?? 'submitted'),
                'transactionNo' => (string) ($final->No_Transaksi ?? ''),
                'rows' => [],
            ];
        }

        if ($pengajuanId <= 0) {
            return $empty;
        }

        $draft = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $pengajuanId)
            ->where('Id_User', $userId)
            ->where('Periode', $period)
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_Update_Performance_Sementara')
            ->first();

        if (! $draft) {
            return $empty;
        }

        return [
            'isSubmitted' => false,
            'isDrafted' => true,
            'score' => (float) ($draft->Total_Score ?? 0),
            'status' => '',
            'transactionNo' => (string) ($draft->No_Transaksi ?? ''),
            'rows' => [],
        ];
    }

    protected function resolvePersonalFullYearPerformanceState(string $userId, int $year): array
    {
        $empty = [
            'isSubmitted' => false,
            'isDrafted' => false,
            'score' => 0,
            'status' => '',
            'transactionNo' => '',
            'rows' => [],
        ];

        if ($userId === '') {
            return $empty;
        }

        $rows = $this->loadApprovedPerformanceRows([$userId], $year)
            ->filter(
                fn ($row) => str_starts_with((string) $this->normalizePeriodValue($row->Periode ?? null), $year.'-'),
            )
            ->values();
        $scores = $rows->map(fn ($row) => (float) ($row->Total_Score ?? 0))->filter(fn ($score) => $score > 0)->values();

        if ($scores->isEmpty()) {
            return $empty;
        }

        $latest = $rows->first();

        return [
            'isSubmitted' => true,
            'isDrafted' => false,
            'score' => round((float) $scores->avg(), 1),
            'status' => 'Full Year',
            'transactionNo' => (string) ($latest->No_Transaksi ?? ''),
            'rows' => [],
        ];
    }

    protected function resolvePersonalDisplayScore(
        string $mode,
        array $performanceState,
        array $detail,
        array $ytd,
    ): float {
        if ($mode === 'ytd') {
            return (float) ($performanceState['score'] ??
                ($ytd['score'] ?? data_get($detail, 'summary.selected_score', 0)));
        }

        $performanceScore = (float) ($performanceState['score'] ?? 0);
        if (($performanceState['isSubmitted'] ?? false) && $performanceScore > 0) {
            return $performanceScore;
        }

        return 0.0;
    }

    protected function buildPersonalYtdSummary(string $userId, int $year, array $fallbackTrend = []): array
    {
        $rows = $this->loadApprovedPerformanceRows([$userId], $year)
            ->filter(
                fn ($row) => str_starts_with((string) $this->normalizePeriodValue($row->Periode ?? null), $year.'-'),
            )
            ->values();
        $scores = $rows->map(fn ($row) => (float) ($row->Total_Score ?? 0))->filter(fn ($score) => $score > 0)->values();
        $trend = collect(range(1, 12))
            ->map(function ($month) use ($year, $rows, $fallbackTrend) {
                $period = sprintf('%04d-%02d', $year, $month);
                $row = $rows->first(fn ($item) => $this->normalizePeriodValue($item->Periode ?? null) === $period);
                $fallback = collect($fallbackTrend)->first(fn ($item) => ($item['period'] ?? '') === $period);

                return [
                    'period' => $period,
                    'month' => Carbon::createFromFormat('Y-m', $period, 'Asia/Jakarta')->translatedFormat('M'),
                    'score' => (float) ($row->Total_Score ?? ($fallback['score'] ?? 0)),
                ];
            })
            ->values()
            ->all();

        // YTD metode AKUMULASI (disamakan dgn dashboard), bukan averaging skor bulanan.
        $scopeMeta = $this->resolveKpiYearScope($year, Carbon::now('Asia/Jakarta'));
        $ytdScore = $scores->isNotEmpty()
            ? (float) ($this->accumulatedScoresForUsers([$userId], $year, $scopeMeta['ytd_periods'])[$userId] ?? 0)
            : 0;
        $ranges = $this->loadPerformanceRanges();
        $ytdGrade = $ytdScore > 0 ? $this->resolvePerformanceGrade($ytdScore, $ranges) : ['label' => '-', 'color' => '#94a3b8', 'code' => 'NA'];

        return [
            'hasData' => $scores->isNotEmpty(),
            'score' => $ytdScore,
            'submittedCount' => $scores->count(),
            'periodLabel' => 'YTD '.$year,
            'trend' => $trend,
            'grade_label' => $ytdGrade['label'],
            'grade_color' => $ytdGrade['color'],
        ];
    }

    protected function latestPersonalPerformancePeriod(string $userId, int $year): ?string
    {
        if ($userId === '') {
            return null;
        }

        $row = $this->loadApprovedPerformanceRows([$userId], $year)->first();

        return $this->normalizePeriodValue($row->Periode ?? null);
    }

    protected function personalKpiYearOptions(string $kodeKaryawan, string $userId, int $selectedYear): array
    {
        $years = collect([$selectedYear, (int) Carbon::now('Asia/Jakarta')->format('Y')]);
        $scheduleYears = DB::table('KPI_Master_Penjadwalan as mp')
            ->leftJoin(
                'N_KPI_Master_Penjadwalan_Perorangan as det',
                'mp.Id_Master_Penjadwalan',
                '=',
                'det.Id_Master_Penjadwalan',
            )
            ->where(function ($query) {
                $query->where('mp.Flag_Form_KPI', 'Y')->orWhere('mp.Flag_Penilaian_KPI', 'Y');
            })
            ->where(function ($query) use ($kodeKaryawan) {
                $query
                    ->where(function ($global) {
                        $global->where('mp.Flag_Perorangan', 'T')->orWhereNull('mp.Flag_Perorangan');
                    })
                    ->orWhere('det.Kode_Karyawan', $this->normalizeKode($kodeKaryawan));
            })
            ->pluck('mp.Tahun')
            ->map(fn ($year) => (int) $year);

        $performanceYears =
            $userId !== ''
                ? DB::table('KPI_Update_Performance')
                    ->where('Id_User', $userId)
                    ->pluck('Periode')
                    ->map(fn ($period) => (int) substr((string) $period, 0, 4))
                : collect();

        return $years
            ->merge($scheduleYears)
            ->merge($performanceYears)
            ->filter(fn ($year) => $year >= 2000 && $year <= 2100)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    protected function personalKpiPeriodOptions(int $year): array
    {
        return collect([
            [
                'value' => 'all',
                'period' => null,
                'label' => 'Full Year',
            ],
        ])
            ->merge(
                collect(range(1, 12))->map(function ($month) use ($year) {
                    $period = sprintf('%04d-%02d', $year, $month);

                    return [
                        'value' => sprintf('%02d', $month),
                        'period' => $period,
                        'label' => Carbon::createFromFormat('Y-m', $period, 'Asia/Jakarta')->translatedFormat('F'),
                    ];
                }),
            )
            ->all();
    }

    protected function defaultPersonalDashboardDetail(int $year, ?string $period, string $message): array
    {
        $selectedPeriod =
            $this->composePersonalPeriod($period, $year) ?:
            sprintf('%04d-%02d', $year, (int) Carbon::now('Asia/Jakarta')->format('m'));

        return [
            'has_data' => false,
            'selectedYear' => $year,
            'selectedPeriod' => substr($selectedPeriod, 5, 2),
            'selectedPeriodFull' => $selectedPeriod,
            'selectedMode' => 'period',
            'yearOptions' => [$year],
            'periodOptions' => $this->personalKpiPeriodOptions($year),
            'parameterSchedule' => $this->schedulePayload(null, $selectedPeriod),
            'performanceSchedule' => $this->schedulePayload(null, $selectedPeriod),
            'parameter' => ['isScheduled' => false, 'isSubmitted' => false, 'message' => $message, 'rows' => []],
            'performance' => [
                'isScheduled' => false,
                'isSubmitted' => false,
                'isDrafted' => false,
                'canSubmit' => false,
                'message' => $message,
                'summary' => ['score' => 0, 'status' => '', 'transactionNo' => ''],
                'rows' => [],
            ],
            'ytd' => [
                'hasData' => false,
                'score' => 0,
                'submittedCount' => 0,
                'periodLabel' => 'YTD '.$year,
                'trend' => [],
            ],
            'emptyMessage' => $message,
        ];
    }

    /**
     * Batch-resolusi status approval KPI_Pengajuan (KPI Parameter) untuk banyak user sekaligus.
     *
     * Returns: [userId => ['status' => 'none'|'pending'|'rejected'|'approved', 'submitted' => bool]]
     */
    protected function resolveParameterApprovalBatch(array $userIds, int $year): array
    {
        $ids = collect($userIds)->filter()->unique()->values()->all();
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
            ->whereIn('kp.Id_User', $ids)
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('mp.Tahun', (string) $year)
            ->where('mp.Flag_Form_KPI', 'Y')
            ->select('kp.Id_User', 'kp.Flag_Approval', 'kp.Status', 'kp.Id_Pengajuan')
            ->orderByDesc('kp.Id_Pengajuan')
            ->get()
            ->groupBy('Id_User')
            ->map(fn ($rows) => $rows->first());

        $result = [];
        foreach ($ids as $userId) {
            $row = $rows->get($userId);
            if (! $row) {
                $result[$userId] = ['status' => 'none', 'submitted' => false];

                continue;
            }

            $flagApproval = strtoupper(trim((string) ($row->Flag_Approval ?? '')));
            $statusPengajuan = strtolower(trim((string) ($row->Status ?? '')));

            if ($flagApproval === 'Y' || $statusPengajuan === 'disetujui') {
                $result[$userId] = ['status' => 'approved', 'submitted' => true];
            } elseif ($flagApproval === 'T' || $statusPengajuan === 'ditolak') {
                $result[$userId] = ['status' => 'rejected', 'submitted' => true];
            } else {
                $result[$userId] = ['status' => 'pending', 'submitted' => true];
            }
        }

        return $result;
    }

    /**
     * Pengajuan yang parameternya ditampilkan untuk satu orang.
     *
     * Dulu daftar ini menggabungkan SEMUA pengajuan "aktif" (termasuk yang belum
     * pernah disetujui) dengan SEMUA pengajuan yang pernah dipakai update
     * performance disetujui — termasuk update yang sudah dinonaktifkan karena
     * digantikan revisi. Setiap revisi parameter menumpuk: di dev, Aisyah
     * mendapat 8 parameter dari 7 pengajuan (bobot total 700%), padahal yang
     * berlaku hanya 1, dan target Oktober dari versi lama ikut tampil.
     *
     *  - month:     pengajuan milik update performance HIDUP periode itu; bila
     *               belum ada, pengajuan yang berlaku sekarang.
     *  - full_year: pengajuan milik update performance HIDUP sepanjang tahun
     *               (tiap bulan bisa berbeda) ditambah yang berlaku sekarang.
     *
     * "Berlaku sekarang" = pengajuan aktif terbaru yang DISETUJUI, sama dengan
     * Update KPI, monitoring, dan auto-cap. Pengajuan yang masih menunggu
     * approval hanya dipakai bila belum ada satu pun yang disetujui, supaya
     * parameter yang baru diajukan tetap terlihat dengan status "Menunggu".
     *
     * @return array<int>
     */
    protected function resolvePengajuanSumberParameter(string $userId, int $year, string $mode, string $selectedPeriod): array
    {
        $dariUpdateHidup = DB::table('KPI_Update_Performance')
            ->where('Id_User', $userId)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->where('Status_Pengajuan', 'disetujui')
            ->where('Flag_Approval', 'Y')
            ->where('Flag_Verifikasi', 'Y')
            ->whereNotNull('Id_Pengajuan')
            ->where('Periode', 'like', $year.'-%')
            ->when($mode === 'month', fn ($q) => $q->where('Periode', $selectedPeriod))
            ->orderByDesc('Id_Update_Performance')
            ->pluck('Id_Pengajuan')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($mode === 'month' && $dariUpdateHidup->isNotEmpty()) {
            return [$dariUpdateHidup->first()];
        }

        $berlaku = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
            ->where('kp.Id_User', $userId)
            ->where('mp.Tahun', (string) $year)
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->orderByRaw("CASE WHEN kp.Flag_Approval = 'Y' THEN 0 ELSE 1 END")
            ->orderByDesc(DB::raw('CAST(kp.Tanggal + kp.Jam AS DATETIME)'))
            ->value('kp.Id_Pengajuan');

        return $dariUpdateHidup
            ->push((int) $berlaku)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Batch-resolusi status approval KPI_Update_Performance untuk banyak user sekaligus.
     *
     * $activePeriod = periode yang ditanyakan: satu periode untuk semua user, atau
     * peta Id_User => periode bila jadwal aktifnya berbeda per orang. Bila periode
     * diketahui, HANYA baris periode itu yang dipakai; tanpa baris, statusnya
     * 'none'. Dulu periode itu cuma didahulukan lalu jatuh ke baris terbaru di
     * tahun yang sama, sehingga orang yang belum mengisi Oktober tetapi sudah
     * disetujui September tampil "Sudah Mengisi ✓" untuk Oktober. Tanpa periode
     * (tak ada jadwal aktif), baris terbaru tetap dipakai seperti sebelumnya.
     *
     * @param  string|array<string,string|null>|null  $activePeriod
     */
    protected function resolvePerformanceApprovalBatch(array $userIds, int $year, string|array|null $activePeriod = null): array
    {
        $ids = collect($userIds)->filter()->unique()->values()->all();
        if (empty($ids)) {
            return [];
        }

        $query = DB::table('KPI_Update_Performance as up')
            ->whereIn('up.Id_User', $ids)
            ->where('up.Flag_Aktif', 'Y')
            ->whereNull('up.Status')
            ->whereRaw('LEFT(up.Periode, 4) = ?', [(string) $year])
            ->select('up.Id_User', 'up.Flag_Approval', 'up.Status_Pengajuan', 'up.Periode', 'up.Id_Update_Performance')
            ->orderByDesc('up.Id_Update_Performance');

        $periodeUntuk = function ($userId) use ($activePeriod): ?string {
            $periode = is_array($activePeriod) ? ($activePeriod[(string) $userId] ?? null) : $activePeriod;

            return $this->normalizePeriodValue($periode);
        };

        $rows = $query->get()->groupBy('Id_User')->map(function ($rows, $userId) use ($periodeUntuk) {
            $periode = $periodeUntuk($userId);

            return $periode === null
                ? $rows->first()
                : $rows->first(fn ($row) => $this->normalizePeriodValue($row->Periode ?? null) === $periode);
        });

        $approvedPengajuanByUser = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
            ->whereIn('kp.Id_User', $ids)
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('kp.Flag_Approval', 'Y')
            ->where('mp.Tahun', (string) $year)
            ->select('kp.Id_User', 'kp.Id_Pengajuan')
            ->orderByDesc('kp.Id_Pengajuan')
            ->get()
            ->groupBy('Id_User')
            ->map(fn ($rows) => $rows->first());

        // BATCH "hasParameters": sebelumnya ada query KPI_Pengajuan_Det per-user di
        // dalam loop (N+1 -> ~131 query untuk tim besar = biang utama 172 query/9,7s).
        // Kumpulkan semua Id_Pengajuan approved, lalu SATU query cek mana yang punya
        // detail di tahun ini -> O(1) query, bukan O(N).
        $approvedPengajuanIds = $approvedPengajuanByUser
            ->map(fn ($p) => (int) ($p->Id_Pengajuan ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $pengajuanWithParams = [];
        if (! empty($approvedPengajuanIds)) {
            $pengajuanWithParams = DB::table('KPI_Pengajuan_Det as det')
                ->join('KPI_Pengajuan_Detail as detail', 'det.Id_Pengajuan_Detail', '=', 'detail.Id_Pengajuan_Detail')
                ->whereIn('detail.Id_Pengajuan', $approvedPengajuanIds)
                ->whereRaw('LEFT(det.Periode, 4) = ?', [(string) $year])
                ->distinct()
                ->pluck('detail.Id_Pengajuan')
                ->map(fn ($id) => (int) $id)
                ->flip() // jadikan lookup O(1): [Id_Pengajuan => index]
                ->all();
        }

        $result = [];
        foreach ($ids as $userId) {
            $row = $rows->get($userId);
            $pengajuan = $approvedPengajuanByUser->get($userId);
            $hasParameters = false;

            if ($pengajuan) {
                $hasParameters = isset($pengajuanWithParams[(int) $pengajuan->Id_Pengajuan]);
            }

            if (! $row) {
                $result[$userId] = ['status' => 'none', 'submitted' => false, 'hasParameters' => $hasParameters];

                continue;
            }

            $flagApproval = strtoupper(trim((string) ($row->Flag_Approval ?? '')));
            $statusPengajuan = strtolower(trim((string) ($row->Status_Pengajuan ?? '')));

            if ($flagApproval === 'Y' || $statusPengajuan === 'disetujui') {
                $result[$userId] = ['status' => 'approved', 'submitted' => true, 'hasParameters' => $hasParameters];
            } elseif ($flagApproval === 'T' || $statusPengajuan === 'ditolak') {
                $result[$userId] = ['status' => 'rejected', 'submitted' => true, 'hasParameters' => $hasParameters];
            } else {
                $result[$userId] = ['status' => 'pending', 'submitted' => true, 'hasParameters' => $hasParameters];
            }
        }

        // hasParameters menjawab "punya target di TAHUN ini"; label status butuh
        // "punya target jatuh tempo di PERIODE yang ditanyakan". Tanpa pembeda ini
        // orang tanpa target bulan itu dilabeli "Belum Mengisi", padahal halaman
        // Update KPI menampilkan "Tidak Ada Target". Satu aturan dengan Update KPI,
        // monitoring, dan auto-cap; diklasifikasi sekali per periode. Tanpa periode,
        // jawabannya mengikuti hasParameters seperti sebelumnya.
        $idPerPeriode = [];
        foreach (array_keys($result) as $userId) {
            $result[$userId]['hasDueTarget'] = $result[$userId]['hasParameters'];
            $periode = $periodeUntuk($userId);
            if ($periode !== null) {
                $idPerPeriode[$periode][] = (int) $userId;
            }
        }

        foreach ($idPerPeriode as $periode => $idUsers) {
            $kelas = app(KpiEnforcementService::class)->classifyParameterTargetForPeriod($idUsers, Carbon::parse($periode));
            foreach ($idUsers as $idUser) {
                $result[(string) $idUser]['hasDueTarget'] = ($kelas[$idUser] ?? 'no_parameter') === 'has';
            }
        }

        return $result;
    }

    protected function normalizeKode(?string $kode): string
    {
        return strtoupper(trim((string) $kode));
    }

    protected function defaultSummary(?int $year = null): array
    {
        $targetYear = $this->resolveSummaryYear($year);

        return [
            'has_data' => false,
            'kpi_year' => $targetYear,
            'year' => $targetYear,
            'score' => 0,
            'current_score' => 0,
            'grade_label' => 'Belum ada data KPI',
            'grade' => 'Belum ada data KPI',
            'achievement' => 0,
            'weighted_score' => 0,
            'parameter_submitted' => false,
            'performance_submitted' => false,
            'period_label' => 'Periode berjalan',
            'parameter_period' => 'Periode berjalan',
            'performance_period' => 'Periode berjalan',
            'rank_label' => '-',
            'rank_meta' => 'Belum dihitung',
            'trend_direction' => '0%',
            'deadline_text' => null,
            'pending_reviews' => 0,
            'pending_items' => [],
            'warning_items' => [],
            'trend' => [],
            'monthly_achievement' => [],
            'parameters' => [],
            'parameter_rows' => [],
            'parameter_count' => 0,
            'performance_item_count' => 0,
            'last_update' => null,
        ];
    }
}
