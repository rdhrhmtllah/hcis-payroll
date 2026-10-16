<?php

namespace App\Services\ESS\Dashboard\Homepage;

use App\Jobs\ESS\Dashboard\Leadership\LeaderDashboardSyncJob;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\ESS\Attendance\Processing\KlasifikasiKeterlambatan;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\ESS\Dashboard\Personal\UDashReadModelService;
use App\Services\SYSTEM\Notification\NotificationRouteRegistry;
use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\SYSTEM\Notification\NotificationSyncStateService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomepageLeaderDashboardService
{
    private const DASHBOARD_SOURCE = 'LEADER_DASH';

    private const HCIS_SYNC_SCOPE = 'LEADER_DASH_HCIS';

    /**
     * Scope guard waktu revalidasi-on-read leader (saat bell / pusat notifikasi
     * dibuka). Terpisah dari HCIS_SYNC_SCOPE agar tidak mengganggu state sync utama.
     */
    private const REVALIDATE_SYNC_SCOPE = 'LEADER_DASH_REVALIDATE';

    /**
     * Jeda minimum (detik) antar revalidasi-on-read leader.
     */
    private const REVALIDATE_MIN_INTERVAL = 60;

    private const HCIS_ROLLING_LOOKBACK_DAYS = 3;

    // Interval (menit) full re-scan seluruh periode cutoff. Di luar interval ini,
    // sync hanya incremental (hari baru + rolling lookback pendek) supaya kerja job
    // tetap ringan. Full re-scan menangkap koreksi hari lama (alpha/no-checkout yang
    // datanya baru masuk) tapi cukup mahal, jadi dibatasi agar tidak tiap kali.
    private const HCIS_FULL_RESCAN_INTERVAL_MINUTES = 60;

    // Throttle (detik) dispatch job sync background dari endpoint cache-first. Endpoint
    // hanya men-trigger sync ulang maksimal sekali per window ini per leader+tahun,
    // supaya queue tidak dibanjiri saat halaman sering dibuka/di-refresh. Data bisa
    // basi hingga selama window ini — kompromi wajar demi response cepat.
    private const NEEDS_ACTION_SYNC_THROTTLE_SECONDS = 120;

    protected array $lmsTeamMemberCache = [];

    protected bool $needsActionFallbackEnabled = false;

    protected HrisAttendanceSnapshotService $attendanceService;

    /**
     * Ambang SYNC inline absen tim. Default param SP = 300; di sini di-cap kecil
     * supaya saat backlog dingin / tim besar, recalc TIDAK dijalankan inline di
     * request (jatuh ke ASYNC queue). Floor untuk kasus kecil, hard cap mutlak.
     * Tim leader bisa puluhan user -> cap penting agar tidak meledak di request.
     */
    private const ABSEN_MAX_SYNC_FLOOR = 60;

    private const ABSEN_MAX_SYNC_HARD = 300;

    /**
     * TTL pendek (detik) saat hasil absen tim BELUM lengkap (ada tanggal yang
     * masih QUEUED/MISSING/PROCESSING karena masuk ASYNC). Mencegah data kosong
     * "terkunci" 90s di cache; worker queue (tiap menit) akan melengkapinya, lalu
     * load berikutnya dapat data final dan di-cache penuh.
     */
    private const LEADER_ATTENDANCE_PARTIAL_TTL = 20;

    /**
     * TTL cache baris SP absen TIM lintas-request (detik). Endpoint leader (members,
     * calendar/month, heatmap, handle-task) minta SP absen utk TIM+RENTANG yang sama
     * tapi request terpisah -> tanpa cache ini, tiap endpoint SP sendiri (×N anggota =
     * mahal). Cache rows mentah per (tim+rentang) + superset-slice menjadikan SP tim
     * ~1x per load. Pendek karena HARI INI bisa berubah; split-mode tetap SYNC hari ini.
     */
    private const TEAM_ABSEN_ROWS_CACHE_TTL = 60;

    /** Memoize rows SP tim per (listUserId|from|to) dalam 1 request. */
    private array $teamAbsenMemo = [];

    /** Memoize daftar anggota tim per leader dalam 1 request (loadTeamMembers
     *  bisa dipanggil >1x per request: getGlobalSummary + resolveApprovalQueueCount). */
    private array $teamMembersMemo = [];

    /** Penanda apakah panggilan SP absen terakhir mengembalikan data belum lengkap. */
    protected bool $lastAbsenIncomplete = false;

    public function __construct(
        protected UDashReadModelService $uDashReadModelService,
        protected ShiftRequestService $shiftRequestService,
        protected HomepageKpiService $homepageKpiService,
        protected HomepageLmsService $homepageLmsService,
        protected NotificationService $notificationService,
        protected NotificationSyncStateService $notificationSyncStateService,
        protected NotificationRouteRegistry $notificationRouteRegistry,
        HrisAttendanceSnapshotService $attendanceSnapshotService,
    ) {
        $this->attendanceService = $attendanceSnapshotService;
    }

    protected function profileEndpoint(string $label, callable $callback)
    {
        $startedAt = microtime(true);
        $authUser = Auth::user();

        try {
            $result = $callback();
            $app_env = config('app.env', 'production');

            if ($app_env != 'production' || $app_env != 'Production') {
                Log::info('Homepage leader endpoint completed', [
                    'endpoint' => $label,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'status' => 'ok',
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Homepage leader endpoint failed', [
                'user_id_web' => $authUser?->Id_Users,
                'endpoint' => $label,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'status' => 'error',
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function buildOverviewData(string $leaderKode, ?int $teamCount = null): array
    {
        $approvalQueue = 0;
        try {
            $approvalQueue = $this->resolveApprovalQueueCount($leaderKode);
        } catch (\Throwable $e) {
            Log::warning('HomepageLeaderDashboardService overview approval count failed: '.$e->getMessage());
        }

        $membersCount = $teamCount;
        if ($membersCount === null) {
            try {
                $membersCount = $this->loadTeamMembers($leaderKode)->count();
            } catch (\Throwable $e) {
                Log::warning('HomepageLeaderDashboardService overview team count failed: '.$e->getMessage());
                $membersCount = 0;
            }
        }

        $todayLabel = Carbon::now('Asia/Jakarta')->translatedFormat('d M Y');
        $periodLabel = 'Periode berjalan';
        $overviewCutoffRange = null;
        try {
            $overviewCutoffRange = $this->uDashReadModelService->getCutoffRange();
            $periodLabel = 'Cutoff '.$overviewCutoffRange['label'];
        } catch (\Throwable $e) {
            Log::warning('HomepageLeaderDashboardService overview period failed: '.$e->getMessage());
        }

        $kpiMonthLabel = Carbon::now('Asia/Jakarta')->translatedFormat('F Y');
        $summary = [
            'totalMembers' => (int) $membersCount,
            'presentToday' => 0,
            'absentToday' => 0,
            'noCheckoutToday' => 0,
            'leaveToday' => 0,
            'alphaCount' => 0,
            'sickDays' => 0,
            'lateCount' => 0,
            'earlyOutCount' => 0,
            'noCheckoutCount' => 0,
            'leaveDays' => 0,
            'remainingLeave' => 0,
            'attendanceIssues' => 0,
            'hcisCritical' => 0,
            'totalOvertime' => 0,
            'kpiPending' => 0,
            'kpiCompletion' => 0,
            'avgKpiScore' => 0,
            'lowKpiCount' => 0,
            'excellentKpiCount' => 0,
            'totalTrainingAssigned' => 0,
            'totalTrainingCompleted' => 0,
            'totalTrainingPending' => 0,
            'lmsAtRisk' => 0,
            'avgLmsProgress' => 0,
            'approvalQueue' => (int) $approvalQueue,
            'followUpTotal' => (int) $approvalQueue,
            'shiftCoverage' => 0,
            'kpiImprovement' => 0,
        ];

        return [
            'period' => [
                'label' => $periodLabel,
                'kpiMonth' => $kpiMonthLabel,
                'cutoff_start' => $overviewCutoffRange ? $overviewCutoffRange['start_date']->toDateString() : null,
                'cutoff_end' => $overviewCutoffRange ? $overviewCutoffRange['end_date']->toDateString() : null,
                'cutoff_label' => $overviewCutoffRange['label'] ?? null,
            ],
            'todayLabel' => $todayLabel,
            'teamCount' => (int) $membersCount,
            'approvalQueue' => (int) $approvalQueue,
            'summary' => $summary,
            'heroCards' => [
                [
                    'key' => 'members',
                    'section' => 'members',
                    'label' => 'Total Anggota',
                    'value' => (int) $membersCount,
                    'description' => 'daftar tim siap dimuat saat diklik',
                    'icon' => 'bi bi-people-fill',
                    'tone' => 'primary',
                ],
                [
                    'key' => 'approvals',
                    'section' => 'approvals',
                    'label' => 'Antrian Approval',
                    'value' => $approvalQueue,
                    'description' => 'langsung buka panel approval',
                    'icon' => 'bi bi-inboxes-fill',
                    'tone' => 'warning',
                ],
                [
                    'key' => 'lazy',
                    'section' => 'members',
                    'label' => 'Panel Lazy',
                    'value' => 5,
                    'description' => 'shift, heatmap, training, dan detail',
                    'icon' => 'bi bi-lightning-charge-fill',
                    'tone' => 'success',
                ],
                [
                    'key' => 'today',
                    'section' => 'shifts',
                    'label' => 'Hari Ini',
                    'value' => $todayLabel,
                    'description' => 'overview ringkas untuk pembuka halaman',
                    'icon' => 'bi bi-calendar3',
                    'tone' => 'info',
                ],
            ],
            'sectionCards' => [
                [
                    'key' => 'members',
                    'title' => 'Daftar Anggota Tim',
                    'description' => 'Muat daftar anggota, filter, dan detail member hanya saat panel dibuka.',
                    'icon' => 'bi bi-people',
                    'actionLabel' => 'Buka daftar anggota',
                ],
                [
                    'key' => 'shifts',
                    'title' => 'Distribusi Shift',
                    'description' => 'Ambil jadwal shift dan detail coverage ketika card ini diklik.',
                    'icon' => 'bi bi-calendar-event',
                    'actionLabel' => 'Buka shift',
                ],
                [
                    'key' => 'approvals',
                    'title' => 'Queue Approval',
                    'description' => 'Panel approval baru di-fetch saat dibuka untuk menghemat first paint.',
                    'icon' => 'bi bi-check2-square',
                    'actionLabel' => 'Buka approval',
                ],
                [
                    'key' => 'heatmap',
                    'title' => 'Heatmap Issue',
                    'description' => 'Pola issue tim dimuat on-demand agar tidak berat di render awal.',
                    'icon' => 'bi bi-grid-3x3-gap',
                    'actionLabel' => 'Buka heatmap',
                ],
                [
                    'key' => 'training',
                    'title' => 'Training Pending',
                    'description' => 'Daftar LMS pending hanya dimuat ketika panel dibuka.',
                    'icon' => 'bi bi-journal-bookmark',
                    'actionLabel' => 'Buka training',
                ],
                [
                    'key' => 'actions',
                    'title' => 'Action Center',
                    'description' => 'Ringkasan tindak lanjut dipetakan dari data anggota yang sudah diload.',
                    'icon' => 'bi bi-bell-fill',
                    'actionLabel' => 'Lihat tindakan',
                ],
            ],
            'useDemoData' => false,
        ];
    }

    public function getGlobalSummary(string $leaderKode): array
    {
        return $this->profileEndpoint('leader.summary', function () use ($leaderKode) {
            $teamMembers = $this->loadTeamMembers($leaderKode);
            $teamSize = $teamMembers->count();
            $approvalQueue = $this->resolveApprovalQueueCount($leaderKode);

            // Core-light summary: intentionally avoids attendance SP and heavy domain aggregation.
            $summary = [
                'totalMembers' => (int) $teamSize,
                'presentToday' => 0,
                'absentToday' => 0,
                'pendingCheckInToday' => 0,
                'workingToday' => 0,
                'noCheckoutToday' => 0,
                'leaveToday' => 0,
                'noDataToday' => 0,
                'otherToday' => 0,
                'alphaCount' => 0,
                'noCheckinCount' => 0,
                'sickDays' => 0,
                'lateCount' => 0,
                'earlyOutCount' => 0,
                'noCheckoutCount' => 0,
                'leaveDays' => 0,
                'remainingLeave' => 0,
                'attendanceIssues' => 0,
                'hcisCritical' => 0,
                'totalOvertime' => 0,
                'kpiPending' => 0,
                'kpiCompletion' => 0,
                'avgKpiScore' => 0,
                'lowKpiCount' => 0,
                'excellentKpiCount' => 0,
                'totalTrainingAssigned' => 0,
                'totalTrainingCompleted' => 0,
                'totalTrainingPending' => 0,
                'lmsAtRisk' => 0,
                'avgLmsProgress' => 0,
                'approvalQueue' => (int) $approvalQueue,
                'followUpTotal' => (int) $approvalQueue,
                'shiftCoverage' => 0,
                'kpiImprovement' => 0,
            ];

            Log::debug('Leader summary aggregate prepared', [
                'leader_kode' => $leaderKode,
                'team_size' => $teamSize,
                'mode' => 'core-light',
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            ]);

            return $summary;
        });
    }

    public function getHcisSummary(string $leaderKode): array
    {
        return $this->profileEndpoint('leader.summary.hcis', function () use ($leaderKode) {
            $context = $this->buildFullTeamSummaryContext($leaderKode, [
                'attendance' => true,
                'kpi' => false,
                'lms' => false,
                'approval_preview' => false,
                'approval_queue' => false,
                'shift' => false,
            ]);
            $rows = $this->buildSummaryRows(
                $context['teamMembers'],
                $context['attendanceByCode'],
                $context['kpiByCode'],
                $context['lmsByCode'],
            );
            $summary = $this->buildOverviewSummary($rows, $context['shiftSchedules'], $context['approvalQueue']);
            $payload = $this->buildHcisAggregateSummary($rows, $summary);

            Log::debug('Leader HCIS summary aggregate prepared', [
                'leader_kode' => $leaderKode,
                'team_size' => $context['teamMembers']->count(),
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            ]);

            return $payload;
        });
    }

    public function getKpiSummary(string $leaderKode, ?int $year = null): array
    {
        return $this->profileEndpoint('leader.summary.kpi', function () use ($leaderKode, $year) {
            $resolvedYear = $this->normalizeKpiYear($year);

            try {
                $context = $this->buildFullTeamSummaryContext($leaderKode, [
                    'attendance' => false,
                    'kpi' => true,
                    'kpi_year' => $resolvedYear,
                    'lms' => false,
                    'approval_preview' => false,
                    'approval_queue' => false,
                    'shift' => false,
                ]);
                $rows = $this->buildSummaryRows(
                    $context['teamMembers'],
                    $context['attendanceByCode'],
                    $context['kpiByCode'],
                    $context['lmsByCode'],
                );

                $summary = $this->buildOverviewSummary($rows, $context['shiftSchedules'], $context['approvalQueue']);
                $compliance = (array) $this->homepageKpiService->getTeamComplianceSummary(
                    $context['teamMembers']->pluck('Kode_Karyawan')->all(),
                    $resolvedYear,
                );
                $compliance['approval_queue_count'] = $this->resolveKpiApprovalQueueCount(
                    $leaderKode,
                    $context['teamMembers']->pluck('Kode_Karyawan')->all(),
                );

                $payload = $this->buildKpiAggregateSummary($rows, $summary, $compliance);
                Log::debug('Leader KPI summary aggregate prepared', [
                    'leader_kode' => $leaderKode,
                    'team_size' => $context['teamMembers']->count(),
                    'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                ]);

                return $payload;
            } catch (\Throwable $e) {
                Log::error('Leader KPI summary aggregate failed: '.$e->getMessage(), [
                    'leader_kode' => $leaderKode,
                    'year' => $resolvedYear,
                ]);

                return $this->buildKpiSummaryFallback($resolvedYear);
            }
        });
    }

    protected function buildKpiSummaryFallback(int $year): array
    {
        $trend = collect(range(1, 12))
            ->map(function ($month) use ($year) {
                $period = sprintf('%04d-%02d', $year, $month);

                return [
                    'month' => Carbon::createFromFormat('Y-m', $period, 'Asia/Jakarta')->translatedFormat('M'),
                    'value' => 0,
                ];
            })
            ->values()
            ->all();

        return [
            'stats' => [
                'kpiPending' => 0,
                'kpiCompletion' => 0,
                'avgKpiScore' => 0,
                'lowKpiCount' => 0,
                'excellentKpiCount' => 0,
                'kpiImprovement' => 0,
                'scheduledMembers' => 0,
                'parameterCoveragePercent' => 0,
                'performanceSubmittedPercent' => 0,
                'performanceSubmittedCount' => 0,
                'performancePendingCount' => 0,
                'kpiYear' => $year,
                'formScopeLabel' => 'Tahun '.$year,
                'parameterPeriodLabel' => '-',
                'performancePeriodLabel' => '-',
                'monthlyAvgScore' => 0,
                'ytdAvgScore' => 0,
                'ytdLe80Count' => 0,
                'ytdGt80Count' => 0,
                'performanceAvailable' => false,
                'ytdPeriodLabel' => 'YTD -',
                'approvalQueueCount' => 0,
                'trendRangeLabel' => 'Jan-Des '.$year,
                'formKpiYearly' => [
                    'scheduledMembers' => 0,
                    'submittedCount' => 0,
                    'coveragePercent' => 0,
                ],
                'performanceMonthly' => [
                    'scheduledMembers' => 0,
                    'submittedCount' => 0,
                    'submittedPercent' => 0,
                    'pendingCount' => 0,
                ],
            ],
            'trend' => $trend,
        ];
    }

    public function getLmsSummary(string $leaderKode, ?int $year = null): array
    {
        $selectedYear = $this->normalizeLmsYear($year);

        return $this->profileEndpoint('leader.summary.lms', function () use ($leaderKode, $selectedYear) {
            $context = $this->buildFullTeamSummaryContext($leaderKode, [
                'attendance' => false,
                'kpi' => false,
                'lms' => true,
                'lms_year' => $selectedYear,
                'approval_preview' => false,
                'approval_queue' => false,
                'shift' => false,
            ]);
            $rows = $this->buildSummaryRows(
                $context['teamMembers'],
                $context['attendanceByCode'],
                $context['kpiByCode'],
                $context['lmsByCode'],
            );

            $summary = $this->buildOverviewSummary($rows, $context['shiftSchedules'], $context['approvalQueue']);

            $payload = $this->buildLmsAggregateSummary($rows, $summary, $selectedYear);

            Log::debug('Leader LMS summary aggregate prepared', [
                'leader_kode' => $leaderKode,
                'team_size' => $context['teamMembers']->count(),
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            ]);

            return $payload;
        });
    }

    protected function buildFullTeamSummaryContext(string $leaderKode, array $options = []): array
    {
        $flags = array_merge(
            [
                'attendance' => true,
                'kpi' => true,
                'kpi_year' => null,
                'lms' => true,
                'lms_year' => null,
                'approval_preview' => false,
                'approval_queue' => false,
                'shift' => false,
            ],
            $options,
        );

        $teamMembers = $this->loadTeamMembers($leaderKode);
        // dd($teamMembers);
        if ($teamMembers->isEmpty()) {
            return [
                'teamMembers' => collect(),
                'attendanceByCode' => [],
                'kpiByCode' => [],
                'lmsByCode' => [],
                'approvalQueue' => 0,
                'approvalPreviewItems' => [],
                'shiftSchedules' => [],
            ];
        }

        $memberCodes = $teamMembers
            ->pluck('Kode_Karyawan')
            ->map(fn ($kode) => strtoupper(trim((string) $kode)))
            ->filter()
            ->values()
            ->all();
        // dd($memberCodes);

        $attendanceByCode = $flags['attendance'] ? $this->buildTeamAttendanceSummaries($teamMembers) : [];
        $kpiByCode = $flags['kpi']
            ? $this->homepageKpiService->getTeamSummaries(
                $memberCodes,
                false,
                isset($flags['kpi_year']) && is_numeric($flags['kpi_year']) ? (int) $flags['kpi_year'] : null,
            )
            : [];
        $lmsByCode = $flags['lms']
            ? $this->homepageLmsService->getTeamSummaries($memberCodes, false, $flags['lms_year'])
            : [];
        $approvalPreviewItems = $flags['approval_preview']
            ? $this->buildApprovalItems($teamMembers, false, $leaderKode)
            : [];
        $approvalQueue = $flags['approval_queue'] ? $this->resolveApprovalQueueCount($leaderKode) : 0;
        $shiftSchedules = $flags['shift']
            ? $this->buildShiftBoardForDate($teamMembers, Carbon::today('Asia/Jakarta')->startOfDay())
            : [];

        return [
            'teamMembers' => $teamMembers,
            'attendanceByCode' => $attendanceByCode,
            'kpiByCode' => $kpiByCode,
            'lmsByCode' => $lmsByCode,
            'approvalQueue' => $approvalQueue,
            'approvalPreviewItems' => $approvalPreviewItems,
            'shiftSchedules' => $shiftSchedules,
        ];
    }

    protected function buildSummaryRows(
        Collection $teamMembers,
        array $attendanceByCode,
        array $kpiByCode,
        array $lmsByCode,
    ): Collection {
        return collect($teamMembers)
            ->map(function ($member) use ($attendanceByCode, $kpiByCode, $lmsByCode) {
                $kode = strtoupper(trim((string) $member->Kode_Karyawan));

                return [
                    'attendance' => $attendanceByCode[$kode] ?? $this->defaultAttendanceSummary(),
                    'kpi' => $kpiByCode[$kode] ?? $this->defaultKpiSummary(),
                    'lms' => $lmsByCode[$kode] ?? $this->defaultLmsSummary(),
                ];
            })
            ->values();
    }

    protected function buildOverviewSummary(Collection $rows, array $shiftSchedules, int $approvalQueue): array
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        $totalMembers = $rows->count();
        $presentToday = $rows
            ->filter(function ($row) {
                $attendance = (array) ($row['attendance'] ?? []);
                $todayStatus = (string) ($attendance['todayStatus'] ?? '');
                $hasCheckIn = (bool) ($attendance['hasCheckIn'] ?? false);
                $hasCheckOut = (bool) ($attendance['hasCheckOut'] ?? false);

                if (
                    in_array(
                        $todayStatus,
                        ['leave', 'permission', 'sick', 'cuti', 'pending-checkin', 'absent', 'no-checkin', 'no-data'],
                        true,
                    )
                ) {
                    return false;
                }

                return $hasCheckIn ||
                    $hasCheckOut ||
                    in_array($todayStatus, ['present', 'working', 'late', 'no-checkout'], true);
            })
            ->count();
        // Alpha murni (tidak hadir sama sekali) = status 'absent'; 'no-checkin'
        // adalah kondisi terpisah (ada check-out tanpa check-in).
        $absentToday = $rows->filter(fn ($row) => ($row['attendance']['todayStatus'] ?? '') === 'absent')->count();
        $noCheckinToday = $rows
            ->filter(fn ($row) => ($row['attendance']['todayStatus'] ?? '') === 'no-checkin')
            ->count();
        $pendingCheckInToday = $rows
            ->filter(fn ($row) => ($row['attendance']['todayStatus'] ?? '') === 'pending-checkin')
            ->count();
        $workingToday = $rows->filter(fn ($row) => ($row['attendance']['todayStatus'] ?? '') === 'working')->count();
        $noCheckoutToday = $rows
            ->filter(fn ($row) => ($row['attendance']['todayStatus'] ?? '') === 'no-checkout')
            ->count();
        $leaveToday = $rows
            ->filter(
                fn ($row) => in_array(
                    $row['attendance']['todayStatus'] ?? '',
                    ['leave', 'permission', 'sick', 'cuti'],
                    true,
                ),
            )
            ->count();
        $noDataToday = $rows->filter(fn ($row) => ($row['attendance']['todayStatus'] ?? '') === 'no-data')->count();
        // "Bertugas hari ini" = anggota yang punya shift kerja hari ini (bukan OFF /
        // NO SHIFT / tanpa data). Konsisten dgn working_total pada Drawer B (getHcisTodayBreakdown),
        // yang memakai rowIsWorkingShift(). Ini denominator kartu "Status Hari Ini".
        $scheduledToday = $rows->filter(fn ($row) => (bool) ($row['attendance']['isWorkingToday'] ?? false))->count();
        $otherToday = max(
            0,
            $totalMembers -
                ($presentToday +
                    $absentToday +
                    $noCheckinToday +
                    $pendingCheckInToday +
                    $workingToday +
                    $noCheckoutToday +
                    $leaveToday +
                    $noDataToday),
        );

        $alphaCount = $rows->sum(fn ($row) => (int) ($row['attendance']['alphaCount'] ?? 0));
        $noCheckinCount = $rows->sum(fn ($row) => (int) ($row['attendance']['noCheckinCount'] ?? 0));
        $sickDays = $rows->sum(fn ($row) => (int) ($row['attendance']['sickDays'] ?? 0));
        $lateCount = $rows->sum(fn ($row) => (int) ($row['attendance']['lateCount'] ?? 0));
        $earlyOutCount = $rows->sum(fn ($row) => (int) ($row['attendance']['earlyOutCount'] ?? 0));
        $noCheckoutCount = $rows->sum(fn ($row) => (int) ($row['attendance']['noCheckoutCount'] ?? 0));
        $leaveDays = $rows->sum(fn ($row) => (int) ($row['attendance']['leaveDays'] ?? 0));
        $remainingLeave = $rows->sum(fn ($row) => (int) data_get($row, 'leave.remaining', 0));
        $attendanceIssues = $rows->sum(fn ($row) => count((array) ($row['attendance']['issues'] ?? [])));
        $hcisCritical = $absentToday + $noCheckinToday + $noCheckoutToday;
        $totalOvertime = $rows->sum(fn ($row) => (float) data_get($row, 'attendance.overtime.totalHours', 0));

        $kpiPending = $rows->filter(fn ($row) => count((array) data_get($row, 'kpi.pendingItems', [])) > 0)->count();
        $kpiCompletion = $totalMembers ? (int) round((($totalMembers - $kpiPending) / $totalMembers) * 100) : 0;
        $avgKpiScores = $rows
            ->map(fn ($row) => (float) data_get($row, 'kpi.score', 0))
            ->filter(fn ($score) => $score > 0);
        $avgKpiScore = $avgKpiScores->isNotEmpty() ? round($avgKpiScores->avg(), 1) : 0;
        $lowKpiCount = $rows
            ->filter(
                fn ($row) => (float) data_get($row, 'kpi.score', 0) > 0 && (float) data_get($row, 'kpi.score', 0) < 80,
            )
            ->count();
        $excellentKpiCount = $rows->filter(fn ($row) => (float) data_get($row, 'kpi.score', 0) >= 90)->count();

        $totalTrainingAssigned = $rows->sum(
            fn ($row) => (int) data_get($row, 'lms.enrolled_courses', data_get($row, 'lms.total', 0)),
        );
        $totalTrainingCompleted = $rows->sum(
            fn ($row) => (int) data_get($row, 'lms.completed_courses', data_get($row, 'lms.completed', 0)),
        );
        // Belum Selesai = jumlah ORANG yang masih punya training pending (bukan total modul).
        $totalTrainingPending = $rows
            ->filter(function ($row) {
                $enrolled = (int) data_get($row, 'lms.enrolled_courses', data_get($row, 'lms.total', 0));
                $completed = (int) data_get($row, 'lms.completed_courses', data_get($row, 'lms.completed', 0));

                return $enrolled > 0 && $completed < $enrolled;
            })
            ->count();
        // Progress < 80% hanya dihitung dari anggota yang memang punya assignment training (enrolled > 0).
        $lmsAtRisk = $rows
            ->filter(function ($row) {
                $enrolled = (int) data_get($row, 'lms.enrolled_courses', data_get($row, 'lms.total', 0));

                return $enrolled > 0 && (int) data_get($row, 'lms.progress', 0) < 80;
            })
            ->count();
        // Avg Progress = rata-rata progress hanya dari anggota yang punya training (enrolled > 0).
        $rowsWithLms = $rows->filter(
            fn ($row) => (int) data_get($row, 'lms.enrolled_courses', data_get($row, 'lms.total', 0)) > 0,
        );
        $avgLmsProgress = $rowsWithLms->isNotEmpty()
            ? (int) round($rowsWithLms->avg(fn ($row) => (int) data_get($row, 'lms.progress', 0)))
            : 0;

        $shiftRows = collect($shiftSchedules)->where('date', $today);
        $totalRequiredToday = $shiftRows->sum(fn ($shift) => (int) ($shift['minNeed'] ?? 0));
        $totalScheduledToday = $shiftRows->sum(fn ($shift) => count((array) ($shift['memberIds'] ?? [])));
        $shiftCoverage = $totalRequiredToday
            ? (int) min(100, round(($totalScheduledToday / $totalRequiredToday) * 100))
            : 0;

        $months = ['Nov', 'Des', 'Jan', 'Feb', 'Mar', 'Apr'];
        $kpiTrend = collect($months)->map(function ($month) use ($rows) {
            $values = $rows
                ->map(function ($row) use ($month) {
                    $monthly = collect(
                        (array) data_get($row, 'kpi.monthly_achievement', data_get($row, 'kpi.monthlyAchievement', [])),
                    );
                    $match = $monthly->first(fn ($item) => ($item['month'] ?? null) === $month);

                    return (float) ($match['value'] ?? 0);
                })
                ->filter(fn ($value) => $value > 0)
                ->values();

            return $values->isNotEmpty() ? round($values->avg()) : 0;
        });
        $kpiImprovement = $kpiTrend->isNotEmpty()
            ? (float) round((float) $kpiTrend->last() - (float) $kpiTrend->first(), 1)
            : 0;

        $result = [
            'totalMembers' => $totalMembers,
            'scheduledToday' => $scheduledToday,
            'presentToday' => $presentToday,
            'absentToday' => $absentToday,
            'noCheckinToday' => $noCheckinToday,
            'pendingCheckInToday' => $pendingCheckInToday,
            'workingToday' => $workingToday,
            'noCheckoutToday' => $noCheckoutToday,
            'leaveToday' => $leaveToday,
            'noDataToday' => $noDataToday,
            'otherToday' => $otherToday,
            'alphaCount' => $alphaCount,
            'noCheckinCount' => $noCheckinCount,
            'sickDays' => $sickDays,
            'lateCount' => $lateCount,
            'earlyOutCount' => $earlyOutCount,
            'noCheckoutCount' => $noCheckoutCount,
            'leaveDays' => $leaveDays,
            'remainingLeave' => $remainingLeave,
            'attendanceIssues' => $attendanceIssues,
            'hcisCritical' => $hcisCritical,
            'totalOvertime' => $totalOvertime,
            'kpiPending' => $kpiPending,
            'kpiCompletion' => $kpiCompletion,
            'avgKpiScore' => $avgKpiScore,
            'lowKpiCount' => $lowKpiCount,
            'excellentKpiCount' => $excellentKpiCount,
            'totalTrainingAssigned' => $totalTrainingAssigned,
            'totalTrainingCompleted' => $totalTrainingCompleted,
            'totalTrainingPending' => $totalTrainingPending,
            'lmsAtRisk' => $lmsAtRisk,
            'avgLmsProgress' => $avgLmsProgress,
            'approvalQueue' => $approvalQueue,
            'followUpTotal' => $attendanceIssues + $kpiPending + $approvalQueue + $lmsAtRisk,
            'shiftCoverage' => $shiftCoverage,
            'kpiImprovement' => $kpiImprovement,
        ];

        return $result;
    }

    protected function buildHcisAggregateSummary(Collection $rows, array $summary): array
    {
        // Trend di-ANCHOR ke tanggal kalender (7 hari terakhir s/d hari ini), bukan
        // by-weekday. Sebelumnya agregasi pakai dayKey (weekday) dgn urutan tetap
        // Sen..Min, sehingga (a) angka per titik bisa salah kalau window dailyLog
        // memuat weekday yg sama >1x, dan (b) klik titik membuka tanggal yg meleset
        // karena frontend menebak tanggal dari label. Kini tiap titik membawa
        // fullDate eksplisit dan count dihitung per tanggal tsb.
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $trend = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $cursor = $today->copy()->subDays($offset);
            $dateKey = $cursor->toDateString();
            $value = $rows->reduce(function ($total, $row) use ($dateKey) {
                $day = collect((array) data_get($row, 'attendance.dailyLog', []))->first(
                    fn ($item) => (string) ($item['fullDate'] ?? '') === $dateKey,
                );

                $tone = strtolower((string) ($day['tone'] ?? ''));
                $isIssue = in_array($tone, ['danger', 'warning', 'orange'], true);

                return $total + ($isIssue ? 1 : 0);
            }, 0);

            $trend[] = [
                'label' => $cursor->translatedFormat('D'),
                'date' => $dateKey,
                'value' => (int) $value,
            ];
        }

        $result = [
            'stats' => [
                'totalMembers' => (int) ($summary['totalMembers'] ?? 0),
                'scheduledToday' => (int) ($summary['scheduledToday'] ?? 0),
                'presentToday' => (int) ($summary['presentToday'] ?? 0),
                'absentToday' => (int) ($summary['absentToday'] ?? 0),
                'pendingCheckInToday' => (int) ($summary['pendingCheckInToday'] ?? 0),
                'workingToday' => (int) ($summary['workingToday'] ?? 0),
                'leaveToday' => (int) ($summary['leaveToday'] ?? 0),
                'noCheckoutToday' => (int) ($summary['noCheckoutToday'] ?? 0),
                'noDataToday' => (int) ($summary['noDataToday'] ?? 0),
                'otherToday' => (int) ($summary['otherToday'] ?? 0),
                'attendanceIssues' => (int) ($summary['attendanceIssues'] ?? 0),
                'hcisCritical' => (int) ($summary['hcisCritical'] ?? 0),
                'lateCount' => (int) ($summary['lateCount'] ?? 0),
                'noCheckinCount' => (int) ($summary['noCheckinCount'] ?? 0),
                'noCheckoutCount' => (int) ($summary['noCheckoutCount'] ?? 0),
                // Count cutoff-period untuk 5 kartu stat HCIS (scope periode berjalan).
                // alphaCount = Tidak Hadir, earlyOutCount = Pulang Cepat, leaveDays = Izin/Cuti.
                'alphaCount' => (int) ($summary['alphaCount'] ?? 0),
                'earlyOutCount' => (int) ($summary['earlyOutCount'] ?? 0),
                'leaveDays' => (int) ($summary['leaveDays'] ?? 0),
                'totalOvertime' => (float) ($summary['totalOvertime'] ?? 0),
            ],
            'trend' => $trend,
        ];

        return $result;
    }

    protected function buildKpiAggregateSummary(Collection $rows, array $summary, array $compliance = []): array
    {
        $trend = ! empty($compliance['trend']) && is_array($compliance['trend']) ? $compliance['trend'] : [];

        $scheduledMembers = (int) ($compliance['scheduled_members'] ?? 0);
        $parameterCoveragePercent = (int) ($compliance['parameter_coverage_percent'] ?? 0);
        $performanceSubmittedPercent = (int) ($compliance['performance_submitted_percent'] ?? 0);
        $performanceSubmittedCount = (int) ($compliance['performance_submitted_count'] ?? 0);
        $performancePendingCount = (int) ($compliance['performance_pending_count'] ?? 0);
        $monthlyAvgScore = (float) ($compliance['monthly_avg_score'] ?? 0);
        $ytdAvgScore = (float) ($compliance['ytd_avg_score'] ?? 0);
        $ytdLe80Count = (int) ($compliance['ytd_le_80_count'] ?? 0);
        $ytdGt80Count = (int) ($compliance['ytd_gt_80_count'] ?? 0);
        $performanceAvailable = (bool) ($compliance['performance_available'] ?? false);
        $ytdPeriodLabel = (string) ($compliance['ytd_period_label'] ?? 'YTD -');
        $kpiYear = (int) ($compliance['kpi_year'] ?? Carbon::now('Asia/Jakarta')->format('Y'));
        $formScopeLabel = (string) ($compliance['form_scope_label'] ?? 'Tahun '.$kpiYear);
        $parameterPeriodLabel = (string) ($compliance['parameter_period_label'] ?? 'Periode berjalan');
        $performancePeriodLabel = (string) ($compliance['performance_period_label'] ?? 'Periode berjalan');
        $trendRangeLabel = (string) ($compliance['trend_range_label'] ?? 'Jan-Des '.$kpiYear);
        $formKpiYearly = is_array($compliance['form_kpi_yearly'] ?? null) ? $compliance['form_kpi_yearly'] : [];
        $performanceMonthly = is_array($compliance['performance_monthly'] ?? null)
            ? $compliance['performance_monthly']
            : [];
        $approvalQueueCount = (int) ($compliance['approval_queue_count'] ?? 0);

        return [
            'stats' => [
                'kpiPending' => (int) ($summary['kpiPending'] ?? 0),
                'kpiCompletion' => (int) ($summary['kpiCompletion'] ?? 0),
                'avgKpiScore' => (float) ($summary['avgKpiScore'] ?? 0),
                'lowKpiCount' => (int) ($summary['lowKpiCount'] ?? 0),
                'excellentKpiCount' => (int) ($summary['excellentKpiCount'] ?? 0),
                'kpiImprovement' => (float) ($summary['kpiImprovement'] ?? 0),
                'scheduledMembers' => $scheduledMembers,
                'parameterCoveragePercent' => $parameterCoveragePercent,
                'performanceSubmittedPercent' => $performanceSubmittedPercent,
                'performanceSubmittedCount' => $performanceSubmittedCount,
                'performancePendingCount' => $performancePendingCount,
                'kpiYear' => $kpiYear,
                'formScopeLabel' => $formScopeLabel,
                'parameterPeriodLabel' => $parameterPeriodLabel,
                'performancePeriodLabel' => $performancePeriodLabel,
                'monthlyAvgScore' => $monthlyAvgScore,
                'ytdAvgScore' => $ytdAvgScore,
                'ytdLe80Count' => $ytdLe80Count,
                'ytdGt80Count' => $ytdGt80Count,
                'performanceAvailable' => $performanceAvailable,
                'ytdPeriodLabel' => $ytdPeriodLabel,
                'approvalQueueCount' => $approvalQueueCount,
                'trendRangeLabel' => $trendRangeLabel,
                'formKpiYearly' => [
                    'scheduledMembers' => (int) ($formKpiYearly['scheduled_members'] ?? 0),
                    'submittedCount' => (int) ($formKpiYearly['submitted_count'] ?? 0),
                    'coveragePercent' => (int) ($formKpiYearly['coverage_percent'] ?? 0),
                ],
                'performanceMonthly' => [
                    'scheduledMembers' => (int) ($performanceMonthly['scheduled_members'] ?? 0),
                    'submittedCount' => (int) ($performanceMonthly['submitted_count'] ?? 0),
                    'submittedPercent' => (int) ($performanceMonthly['submitted_percent'] ?? 0),
                    'pendingCount' => (int) ($performanceMonthly['pending_count'] ?? 0),
                ],
            ],
            'trend' => $trend,
        ];
    }

    public function getPaginatedKpiDetails(
        string $leaderKode,
        string $mode = 'annual',
        ?string $period = null,
        string $context = 'monthly_avg',
        ?string $month = null,
        ?int $year = null,
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        bool $compact = false,
    ): array {
        return $this->profileEndpoint('leader.kpi.details', function () use (
            $leaderKode,
            $context,
            $month,
            $year,
            $page,
            $perPage,
            $search,
            $compact,
        ) {
            $teamMembers = $this->loadTeamMembers($leaderKode);
            if ($teamMembers->isEmpty()) {
                return [
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => max(1, min(50, $perPage)),
                        'total' => 0,
                        'has_prev' => false,
                        'has_next' => false,
                    ],
                    'meta' => [
                        'mode' => 'annual',
                        'period_label' => '-',
                        'kpi_year' => (int) Carbon::now('Asia/Jakarta')->format('Y'),
                        'total_member' => 0,
                    ],
                ];
            }

            if ($context === 'approval_queue') {
                return $this->getPaginatedKpiApprovalQueueDetails($leaderKode, $teamMembers, $page, $perPage, $search);
            }

            $detail = $this->homepageKpiService->getLeaderKpiAnnualRows($teamMembers, $search, $year, $month);
            $items = collect($detail['rows'] ?? []);
            $monthFilter = $month;
            if ($context === 'ytd_le_80') {
                $items = $items->filter(fn ($row) => (float) data_get($row, 'ytd_score', 0) <= 80)->values();
            } elseif ($context === 'ytd_gt_80') {
                $items = $items->filter(fn ($row) => (float) data_get($row, 'ytd_score', 0) > 80)->values();
            }

            if ($compact) {
                $items = $items
                    ->map(function ($row) {
                        $formItems = collect((array) data_get($row, 'form_kpi', []));

                        $row['form_kpi_count'] = $formItems->count();
                        $row['performance_kpi_count'] = (int) ($row['performance_kpi_count'] ?? 0);
                        $row['form_submitted'] = $formItems->contains(
                            fn ($item) => strtoupper((string) ($item['status_approval'] ?? '')) === 'Y',
                        );

                        unset($row['form_kpi']);

                        return $row;
                    })
                    ->values();
            }

            if ($context === 'form_approved') {
                $items = $items
                    ->sort(function ($a, $b) {
                        $aSubmitted = (bool) ($a['form_submitted'] ?? false);
                        $bSubmitted = (bool) ($b['form_submitted'] ?? false);

                        // Belum submit ditaruh paling atas.
                        if ($aSubmitted !== $bSubmitted) {
                            return $aSubmitted <=> $bSubmitted;
                        }

                        return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                    })
                    ->values();
            } elseif ($context === 'ytd_le_80') {
                $items = $items
                    ->sort(function ($a, $b) {
                        $aScore = (float) ($a['ytd_score'] ?? ($a['ytd_avg_score'] ?? 0));
                        $bScore = (float) ($b['ytd_score'] ?? ($b['ytd_avg_score'] ?? 0));

                        // Yang performanya paling rendah tampil duluan.
                        if ($aScore === $bScore) {
                            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                        }

                        return $aScore <=> $bScore;
                    })
                    ->values();
            } elseif ($context === 'ytd_gt_80') {
                $items = $items
                    ->sort(function ($a, $b) {
                        $aScore = (float) ($a['ytd_score'] ?? ($a['ytd_avg_score'] ?? 0));
                        $bScore = (float) ($b['ytd_score'] ?? ($b['ytd_avg_score'] ?? 0));

                        // Yang performanya paling tinggi tampil duluan.
                        if ($aScore === $bScore) {
                            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                        }

                        return $bScore <=> $aScore;
                    })
                    ->values();
            }

            $paginated = $this->paginateCollection($items, $page, max(1, min(50, $perPage)));

            return [
                'data' => $paginated['data'],
                'pagination' => $paginated['pagination'],
                'meta' => array_merge($detail['meta'] ?? [], [
                    'context' => $context,
                    'month' => $monthFilter,
                ]),
            ];
        });
    }

    public function getKpiMemberDetail(
        string $leaderKode,
        string $memberKode,
        string $mode = 'month',
        ?string $period = null,
        string $context = 'monthly_avg',
        ?string $month = null,
        ?int $year = null,
    ): array {
        return $this->profileEndpoint('leader.kpi.member.detail', function () use (
            $leaderKode,
            $memberKode,
            $mode,
            $period,
            $context,
            $month,
            $year,
        ) {
            $teamMembers = $this->loadTeamMembers($leaderKode);
            $normalizedMemberCode = strtoupper(trim($memberKode));
            $member = $teamMembers->first(
                fn ($item) => strtoupper(trim((string) ($item->Kode_Karyawan ?? ''))) === $normalizedMemberCode,
            );

            if (! $member) {
                throw new \RuntimeException('Anggota tim tidak ditemukan.');
            }

            $normalizedMode = in_array($mode, ['month', 'full_year'], true)
                ? $mode
                : (in_array($mode, ['yearly', 'ytd'], true)
                    ? 'full_year'
                    : 'month');
            $selectedPeriod = $period;
            if (! $selectedPeriod && is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
                $selectedPeriod = $month;
            }

            $row = $this->homepageKpiService->getLeaderKpiMemberDetail(
                $member,
                $normalizedMode,
                $selectedPeriod,
                $year,
            );

            return [
                'data' => $row,
                'meta' => [
                    'mode' => $normalizedMode,
                    'context' => $context,
                    'period_label' => (string) data_get($row, 'summary.period_label', '-'),
                    'kpi_year' => (int) data_get(
                        $row,
                        'summary.kpi_year',
                        (int) Carbon::now('Asia/Jakarta')->format('Y'),
                    ),
                    'member_id' => $normalizedMemberCode,
                    'month' => $selectedPeriod,
                    'effective_cutoff_period' => (string) data_get($row, 'summary.period', ''),
                    'calculation_scope' => (int) ($year ?? Carbon::now('Asia/Jakarta')->format('Y')) <
                        (int) Carbon::now('Asia/Jakarta')->format('Y')
                            ? 'past_year'
                            : 'running_year',
                ],
            ];
        });
    }

    protected function getPaginatedKpiApprovalQueueDetails(
        string $leaderKode,
        Collection $teamMembers,
        int $page,
        int $perPage,
        string $search = '',
    ): array {
        $requesterCodes = $teamMembers
            ->pluck('Kode_Karyawan')
            ->map(fn ($kode) => strtoupper(trim((string) $kode)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($requesterCodes)) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => max(1, min(50, $perPage)),
                    'total' => 0,
                    'has_prev' => false,
                    'has_next' => false,
                ],
                'meta' => [
                    'mode' => 'monthly',
                    'context' => 'approval_queue',
                    'period_label' => 'Antrian Approval KPI',
                    'kpi_year' => (int) Carbon::now('Asia/Jakarta')->format('Y'),
                    'total_member' => 0,
                    'month' => null,
                ],
            ];
        }

        $keyword = trim($search);
        $items = $this->resolveKpiApprovalQueueItems($leaderKode, $requesterCodes, $keyword)
            ->map(function ($row) {
                $type = (string) ($row->queue_type ?? 'form_kpi');

                return [
                    'id' => $type.'-'.(string) ($row->queue_id ?? uniqid()),
                    'queue_type' => $type,
                    'queue_type_label' => $type === 'performance_kpi' ? 'Update Performance' : 'Form KPI',
                    'transaction_no' => $row->transaction_no ?? '-',
                    'updated_at_raw' => $row->updated_at ? Carbon::parse($row->updated_at)->timestamp : 0,
                    'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->format('d M Y H:i') : '-',
                    'flow_order' => (int) ($row->flow_order ?? 0),
                    'kode_karyawan' => $row->kode_karyawan ?? '-',
                    'name' => $row->name ?? 'Tanpa Nama',
                    'position' => $row->position ?? '-',
                    'department' => $row->department ?? '-',
                    'url' => '/kpi-approval',
                ];
            })
            ->sortByDesc(fn ($row) => (int) ($row['updated_at_raw'] ?? 0))
            ->map(function ($row) {
                unset($row['updated_at_raw']);

                return $row;
            })
            ->values();

        $paginated = $this->paginateCollection($items, $page, max(1, min(50, $perPage)));

        return [
            'data' => $paginated['data'],
            'pagination' => $paginated['pagination'],
            'meta' => [
                'mode' => 'monthly',
                'context' => 'approval_queue',
                'period_label' => 'Antrian Approval KPI',
                'kpi_year' => (int) Carbon::now('Asia/Jakarta')->format('Y'),
                'total_member' => (int) $items->count(),
                'month' => null,
            ],
        ];
    }

    protected function resolveKpiApprovalQueueItems(
        string $leaderKode,
        array $requesterCodes = [],
        string $search = '',
    ): Collection {
        $keyword = trim($search);

        // Antrian approval KPI: query forApprover (union 9 view) tiap load dashboard.
        // Cache pendek (90s) per (leader, requesterCodes, keyword) -> tetap aktual, hemat CPU.
        $codesKey = collect($requesterCodes)->map(fn ($k) => strtoupper(trim((string) $k)))
            ->sort()->values()->implode(',');
        $cacheKey = 'leader.kpi-approval-items.v1:'.sha1($leaderKode.'|'.$codesKey.'|'.$keyword);

        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached instanceof Collection) {
            return $cached;
        }

        $result = $this->resolveKpiApprovalQueueItemsUncached($leaderKode, $requesterCodes, $keyword);
        \Illuminate\Support\Facades\Cache::put($cacheKey, $result, self::LEADER_ATTENDANCE_CACHE_TTL);

        return $result;
    }

    protected function resolveKpiApprovalQueueItemsUncached(
        string $leaderKode,
        array $requesterCodes,
        string $keyword,
    ): Collection {
        // Gunakan view N_HRIS_VW_All_Transactions2 — sumber yang sama dengan card antrian
        // sehingga transaksi yang approval request-nya belum di-generate pun tetap muncul.
        // Service forApprover sudah menyertakan kolom karyawan (Karyawan_Nama,
        // nama_divisi, nama_jabatan) -> tidak perlu join Karyawan/div/jbt lagi.
        $query = DB::query()
            ->fromSub((new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)->forApprover($leaderKode), 'vw')
            ->where('vw.Flow_State', 'CURRENT')
            ->whereIn('vw.Dashboard_Status', ['PENDING', 'FUTURE'])
            ->whereIn('vw.Jenis', ['KPI_PARAMETER', 'KPI_PERFORMANCE']);

        if (! empty($requesterCodes)) {
            $query->whereIn('vw.Kode_Karyawan', $requesterCodes);
        }

        if ($keyword !== '') {
            $searchLike = '%'.$keyword.'%';
            $query->where(function ($q) use ($searchLike) {
                $q->where('vw.Karyawan_Nama', 'like', $searchLike)
                    ->orWhere('vw.nama_divisi', 'like', $searchLike)
                    ->orWhere('vw.nama_jabatan', 'like', $searchLike)
                    ->orWhere('vw.No_Transaksi', 'like', $searchLike);
            });
        }

        return $query
            ->select(
                'vw.No_Transaksi as transaction_no',
                'vw.Jenis as jenis',
                'vw.Kode_Karyawan as kode_karyawan',
                'vw.Karyawan_Nama as name',
                'vw.nama_jabatan as position',
                'vw.nama_divisi as department',
                DB::raw('NULL as updated_at'),
                DB::raw('0 as flow_order'),
            )
            ->distinct()
            ->get()
            ->map(function ($row) {
                $jenis = strtoupper(trim((string) ($row->jenis ?? '')));
                $isPerformance = $jenis === 'KPI_PERFORMANCE';

                return (object) [
                    'queue_type' => $isPerformance ? 'performance_kpi' : 'form_kpi',
                    'queue_id' => $row->transaction_no,
                    'transaction_no' => $row->transaction_no,
                    'updated_at' => $row->updated_at,
                    'kode_karyawan' => $row->kode_karyawan,
                    'name' => $row->name,
                    'position' => $row->position,
                    'department' => $row->department,
                    'flow_order' => (int) $row->flow_order,
                ];
            });
    }

    protected function buildLmsAggregateSummary(Collection $rows, array $summary, int $year): array
    {
        $trend = collect(range(1, 12))
            ->map(function (int $month) use ($rows, $year) {
                $period = sprintf('%d-%02d', $year, $month);
                $monthLabel = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->translatedFormat('M');
                $monthlyRows = $rows
                    ->map(function ($row) use ($period) {
                        $monthly = collect((array) data_get($row, 'lms.duration_monthly', []));
                        $item = $monthly->first(fn ($value) => (string) ($value['period'] ?? '') === $period);
                        if (! $item) {
                            return null;
                        }

                        return [
                            'completed_minutes' => (float) ($item['completed_minutes'] ?? 0),
                            // Jumlah training yang dilalui member ini di bulan tsb.
                            'active_trainings' => (int) ($item['active_trainings'] ?? 0),
                            'active' => (float) ($item['completed_minutes'] ?? 0) > 0,
                        ];
                    })
                    ->filter()
                    ->values();

                $sumMinutes = (float) $monthlyRows->sum(fn ($item) => (float) ($item['completed_minutes'] ?? 0));
                $activeMembers = $monthlyRows->filter(fn ($item) => ! empty($item['active']))->count();
                // Rata-rata durasi PER training yang dilalui seluruh tim di bulan ini
                // (konsisten dengan card personal & detail member).
                $totalTrainings = (int) $monthlyRows->sum(fn ($item) => (int) ($item['active_trainings'] ?? 0));
                $avgHours = $totalTrainings > 0 ? $sumMinutes / $totalTrainings / 60 : 0;

                return [
                    'month' => $monthLabel,
                    'period' => $period,
                    'value' => (float) round($avgHours, 2),
                    'avg_hours' => (float) round($avgHours, 2),
                    'completed_minutes' => (float) round($sumMinutes, 2),
                    'active_members' => (int) $activeMembers,
                    'active_trainings' => (int) $totalTrainings,
                ];
            })
            ->values()
            ->all();

        return [
            'stats' => [
                'year' => $year,
                'totalTrainingAssigned' => (int) ($summary['totalTrainingAssigned'] ?? 0),
                'totalTrainingCompleted' => (int) ($summary['totalTrainingCompleted'] ?? 0),
                'totalTrainingPending' => (int) ($summary['totalTrainingPending'] ?? 0),
                'avgLmsProgress' => (int) ($summary['avgLmsProgress'] ?? 0),
                'lmsAtRisk' => (int) ($summary['lmsAtRisk'] ?? 0),
            ],
            'trend' => $trend,
        ];
    }

    public function buildInitialData(string $leaderKode): array
    {
        $teamMembers = $this->loadTeamMembers($leaderKode);
        if ($teamMembers->isEmpty()) {
            return [
                'period' => [
                    'label' => 'Periode berjalan',
                    'kpiMonth' => Carbon::now('Asia/Jakarta')->translatedFormat('F Y'),
                ],
                'todayLabel' => Carbon::now('Asia/Jakarta')->translatedFormat('d M Y'),
                'teamMembers' => [],
                'shiftSchedules' => [],
                'approvalItems' => [],
                'useDemoData' => false,
            ];
        }

        $attendanceByCode = $this->buildTeamAttendanceSummaries($teamMembers);
        $kpiByCode = $this->homepageKpiService->getTeamSummaries($teamMembers->pluck('Kode_Karyawan')->all());
        $lmsByCode = $this->homepageLmsService->getTeamSummaries($teamMembers->pluck('Kode_Karyawan')->all());
        $approvalItems = $this->buildApprovalItems($teamMembers, false, $leaderKode);
        $approvalByCode = collect($approvalItems)->groupBy('kode_karyawan');

        $memberCards = $teamMembers
            ->map(function ($member) use ($attendanceByCode, $kpiByCode, $lmsByCode, $approvalByCode) {
                $kode = strtoupper(trim((string) $member->Kode_Karyawan));
                $attendance = $attendanceByCode[$kode] ?? $this->defaultAttendanceSummary();
                $kpi = $kpiByCode[$kode] ?? $this->defaultKpiSummary();
                $lms = $lmsByCode[$kode] ?? $this->defaultLmsSummary();
                $approvals = $approvalByCode
                    ->get($kode, collect())
                    ->map(function ($item) {
                        $fallbackType = strtoupper(trim((string) ($item['jenis'] ?? 'APPROVAL')));
                        $fallbackStatus = strtoupper(trim((string) ($item['status'] ?? 'PENDING')));

                        return [
                            'id' => $item['id'] ??
                                strtoupper(trim((string) ($item['kode_karyawan'] ?? ''))).
                                    '|'.
                                    trim((string) ($item['no_transaksi'] ?? '')).
                                    '|'.
                                    $fallbackType,
                            'type' => $item['type'] ?? $fallbackType,
                            'tone' => $item['tone'] ?? ($fallbackStatus === 'FUTURE' ? 'info' : 'warning'),
                            'icon' => $item['icon'] ?? $this->resolveApprovalIcon($fallbackType),
                            'title' => $item['title'] ??
                                'Approval '.ucwords(strtolower(str_replace('_', ' ', $fallbackType))),
                            'description' => $item['description'] ??
                                ($item['deskripsi'] ?? 'No transaksi '.trim((string) ($item['no_transaksi'] ?? '-'))),
                            'statusLabel' => $item['statusLabel'] ?? ucfirst(strtolower($fallbackStatus)),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $kode,
                    'kode_karyawan' => $kode,
                    'name' => $member->Nama ?? 'Tanpa Nama',
                    'initials' => $this->buildInitials($member->Nama ?? 'U'),
                    'position' => $member->nama_jabatan ?? '-',
                    'department' => $member->nama_divisi ?? '-',
                    'shift' => [
                        'name' => $attendance['shift_name'],
                        'time' => $attendance['shift_time'],
                    ],
                    'attendance' => $attendance,
                    'kpi' => [
                        'score' => $kpi['score'] ?? 0,
                        'grade' => $kpi['grade_label'] ?? 'Belum ada data',
                        'achievement' => $kpi['achievement'] ?? 0,
                        'ytdScore' => $kpi['ytd_score'] ?? 0,
                        'ytdSubmittedCount' => $kpi['ytd_submitted_count'] ?? 0,
                        'parameterSubmitted' => $kpi['parameter_submitted'] ?? false,
                        'performanceSubmitted' => $kpi['performance_submitted'] ?? false,
                        'parameterPeriod' => $kpi['parameter_period'] ?? 'Periode berjalan',
                        'performancePeriod' => $kpi['performance_period'] ?? 'Periode berjalan',
                        'deadlineText' => $kpi['deadline_text'] ?? '',
                        'pendingItems' => $kpi['pending_items'] ?? [],
                        'monthlyAchievement' => $kpi['monthly_achievement'] ?? [],
                        'trend' => $kpi['trend'] ?? [],
                        'trendDirection' => $kpi['trend_direction'] ?? '0%',
                        'parameters' => [],
                        'parameterScheduled' => (bool) ($kpi['parameter_scheduled'] ?? false),
                        'performanceScheduled' => (bool) ($kpi['performance_scheduled'] ?? false),
                        'parameterApprovalStatus' => $kpi['parameter']['approvalStatus'] ?? 'none',
                        'performanceApprovalStatus' => $kpi['performance']['approvalStatus'] ?? 'none',
                        'performanceHasParameters' => (bool) ($kpi['performance']['hasParameters'] ?? false),
                    ],
                    'lms' => [
                        'completed' => $lms['completed_courses'] ?? 0,
                        'total' => $lms['enrolled_courses'] ?? 0,
                        'progress' => $lms['progress'] ?? 0,
                        'averageScore' => $lms['average_score'] ?? null,
                        'learningGain' => $lms['learning_gain'] ?? null,
                        'trainings' => $lms['trainings'] ?? [],
                        'completedMonthly' => $lms['completed_monthly'] ?? [],
                    ],
                    'overtime' => $attendance['overtime'],
                    'leave' => $this->buildLeaveSummary($kode),
                    'approvals' => $approvals,
                    'follow_up_summary' => $this->buildFollowUpSummary($attendance, $kpi, $lms, count($approvals)),
                ];
            })
            ->values()
            ->all();

        $cutoffRange = $this->uDashReadModelService->getCutoffRange();

        return [
            'period' => [
                'label' => 'Cutoff '.$cutoffRange['label'],
                'kpiMonth' => Carbon::now('Asia/Jakarta')->translatedFormat('F Y'),
                'cutoff_start' => $cutoffRange['start_date']->toDateString(),
                'cutoff_end' => $cutoffRange['end_date']->toDateString(),
                'cutoff_label' => $cutoffRange['label'] ?? null,
            ],
            'todayLabel' => Carbon::now('Asia/Jakarta')->translatedFormat('d M Y'),
            'teamMembers' => $memberCards,
            'shiftSchedules' => [],
            'approvalItems' => array_values($approvalItems),
            'useDemoData' => false,
        ];
    }

    public function getLeaderShiftSchedules(string $leaderKode, ?string $date = null): array
    {
        return $this->profileEndpoint('leader.shift.board', function () use ($leaderKode, $date) {
            $targetDate = Carbon::parse($date ?: Carbon::today('Asia/Jakarta')->toDateString())->startOfDay();
            $teamMembers = $this->loadTeamMembers($leaderKode);
            if ($teamMembers->isEmpty()) {
                return [];
            }

            return $this->buildShiftBoardForDate($teamMembers, $targetDate);
        });
    }

    public function getLeaderShiftDetail(
        string $leaderKode,
        string $shiftDate,
        string $shiftName,
        int $page = 1,
        int $perPage = 8,
    ): array {
        $date = Carbon::parse($shiftDate)->startOfDay();
        $shiftName = trim($shiftName);
        $board = collect($this->buildShiftBoardForDate($this->loadTeamMembers($leaderKode), $date));
        $shift = $board->first(fn ($item) => (string) ($item['name'] ?? '') === $shiftName);

        if (! $shift) {
            return [
                'shift' => null,
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'has_prev' => false,
                    'has_next' => false,
                ],
            ];
        }

        $shiftMemberIds = collect($shift['memberIds'] ?? [])
            ->map(fn ($kode) => strtoupper(trim((string) $kode)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->profileEndpoint('leader.shift.detail', function () use (
            $leaderKode,
            $shiftMemberIds,
            $page,
            $perPage,
            $shift,
        ) {
            $teamMembers = $this->loadTeamMembers($leaderKode)
                ->filter(function ($member) use ($shiftMemberIds) {
                    return in_array(strtoupper(trim((string) $member->Kode_Karyawan)), $shiftMemberIds, true);
                })
                ->values();

            if ($teamMembers->isEmpty()) {
                return [
                    'shift' => $shift,
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'has_prev' => false,
                        'has_next' => false,
                    ],
                ];
            }

            $attendanceByCode = $this->buildLightAttendanceSummaries($teamMembers, Carbon::parse($shift['date']));
            $memberStates = (array) ($shift['memberStates'] ?? []);
            $items = $teamMembers
                ->map(function ($member) use ($attendanceByCode, $memberStates) {
                    $kode = strtoupper(trim((string) $member->Kode_Karyawan));
                    $attendance = $attendanceByCode[$kode] ?? $this->defaultAttendanceSummary();
                    $availabilityStatus = (string) ($memberStates[$kode] ?? 'scheduled');
                    $isAvailable = $availabilityStatus === 'scheduled';

                    return [
                        'id' => $kode,
                        'kode_karyawan' => $kode,
                        'name' => $member->Nama ?? 'Tanpa Nama',
                        'initials' => $this->buildInitials($member->Nama ?? 'U'),
                        'attendance' => [
                            'todayStatus' => $attendance['todayStatus'] ?? 'present',
                            'todayLabel' => $attendance['todayLabel'] ?? 'Hadir',
                        ],
                        'availability' => [
                            'is_available' => $isAvailable,
                            'status' => $availabilityStatus,
                            'label' => match ($availabilityStatus) {
                                'cuti' => 'Cuti',
                                'izin' => 'Izin',
                                'sakit' => 'Sakit',
                                'off' => 'Off Day',
                                default => 'Tersedia',
                            },
                            'reason' => $isAvailable
                                ? null
                                : match ($availabilityStatus) {
                                    'cuti' => 'Sedang cuti',
                                    'izin' => 'Sedang izin',
                                    'sakit' => 'Sedang sakit',
                                    'off' => 'Off day',
                                    default => 'Tidak tersedia',
                                },
                        ],
                    ];
                })
                ->sortBy('name')
                ->values();

            $paginated = $this->paginateCollection($items, $page, $perPage);

            return [
                'shift' => $shift,
                'data' => $paginated['data'],
                'pagination' => $paginated['pagination'],
            ];
        });
    }

    protected function buildShiftBoardForDate(Collection $teamMembers, Carbon $date): array
    {
        $memberCodes = $teamMembers->pluck('Kode_Karyawan')->map(fn ($kode) => strtoupper(trim((string) $kode)))->all();
        $schedulesByMember = $this->shiftRequestService->getWeekScheduleBulk($memberCodes, $date->copy(), 1);

        // Pushdown rentang ke 9 view sumber (forRequestersBetween) menggantikan filter hilir
        // CAST(Tanggal_Item AS DATE) yang non-sargable + memaksa union ter-materialize penuh.
        // Range [00:00:00 .. 23:59:59] menjaga kesetaraan dgn CAST(.. AS DATE) utk kolom DATETIME.
        $approvedFuture = DB::query()
            ->fromSub(
                (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)->forRequestersBetween(
                    $memberCodes,
                    $date->copy()->startOfDay()->toDateTimeString(),
                    $date->copy()->endOfDay()->toDateTimeString(),
                ),
                'tx',
            )
            ->where('Dashboard_Status', 'APPROVED')
            ->select('Kode_Karyawan', 'Jenis', 'Source_View', 'Json_Context', 'Tanggal_Item', 'Deskripsi')
            ->get()
            ->groupBy(
                fn ($item) => strtoupper(trim((string) $item->Kode_Karyawan)).
                    '|'.
                    Carbon::parse($item->Tanggal_Item)->format('Y-m-d'),
            );

        // Build a pre-resolved approval type map for these future approvals
        $approvedFutureMap = collect($approvedFuture)
            ->mapWithKeys(fn ($items, $key) => [$key => $this->resolveApprovedType($items)])
            ->all();

        $grouped = [];

        foreach ($schedulesByMember as $kode => $memberSchedules) {
            foreach ($memberSchedules as $schedule) {
                $shift = $schedule['shift'] ?? null;
                if (! $shift) {
                    continue;
                }

                $scheduleDate = $schedule['date'];
                if ($scheduleDate !== $date->format('Y-m-d')) {
                    continue;
                }

                $name = $shift['nama'] ?? 'Shift';
                $key = $scheduleDate.'|'.$name;

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'date' => $scheduleDate,
                        'label' => Carbon::parse($scheduleDate)->translatedFormat('d M Y'),
                        'name' => $name,
                        'time' => ($shift['jam_masuk'] ?? '--:--').' - '.($shift['jam_keluar'] ?? '--:--'),
                        'minNeed' => 0,
                        'memberIds' => [],
                        'unavailableMemberIds' => [],
                        'memberStates' => [],
                    ];
                }

                $grouped[$key]['minNeed']++;
                $grouped[$key]['memberIds'][] = $kode;

                $approvedType = $approvedFutureMap[$kode.'|'.$scheduleDate] ?? null;
                $state = 'scheduled';
                $unavailable = false;
                if (in_array($approvedType, ['CUTI', 'IZIN', 'SAKIT'], true)) {
                    $state = strtolower($approvedType);
                    $unavailable = true;
                } elseif (! empty($schedule['is_day_off'])) {
                    $state = 'off';
                    $unavailable = true;
                }

                if ($unavailable) {
                    $grouped[$key]['unavailableMemberIds'][] = $kode;
                }

                $grouped[$key]['memberStates'][$kode] = $state;
            }
        }

        return collect($grouped)
            ->map(function (array $shift) {
                $memberCount = count((array) ($shift['memberIds'] ?? []));
                $gapCount = count((array) ($shift['unavailableMemberIds'] ?? []));

                $availableCount = max(0, $memberCount - $gapCount);

                $shift['scheduledCount'] = $memberCount;
                $shift['availableCount'] = $availableCount;
                $shift['unavailableCount'] = $gapCount;
                $shift['availabilityRate'] = $memberCount > 0 ? round(($availableCount / $memberCount) * 100, 1) : 0;
                $shift['hasGap'] = $gapCount > 0;

                // Backward-compatible aliases for existing consumers.
                $shift['memberCount'] = $memberCount;
                $shift['minNeed'] = $memberCount;
                $shift['gapCount'] = $gapCount;
                $shift['coverageCount'] = $availableCount;

                return $shift;
            })
            ->values()
            ->all();
    }

    public function getMemberDetail(
        string $leaderKode,
        string $memberKode,
        ?int $kpiYear = null,
        ?int $lmsYear = null,
    ): array {
        $selectedKpiYear = $this->normalizeKpiYear($kpiYear);
        $selectedLmsYear = $this->normalizeLmsYear($lmsYear ?? $kpiYear);

        return $this->profileEndpoint('leader.member.detail', function () use (
            $leaderKode,
            $memberKode,
            $selectedKpiYear,
            $selectedLmsYear,
        ) {
            $member = $this->loadTeamMembers($leaderKode)->first(
                fn ($item) => strtoupper(trim((string) $item->Kode_Karyawan)) === strtoupper(trim($memberKode)),
            );

            if (! $member) {
                throw new \RuntimeException('Anggota tim tidak ditemukan.');
            }

            $attendance =
                $this->buildTeamAttendanceSummaries(collect([$member]))[strtoupper(trim($memberKode))] ??
                $this->defaultAttendanceSummary();
            $kpi = $this->buildMemberDetailKpiSummary($member, $memberKode, $selectedKpiYear);
            $lms = $this->homepageLmsService->getSummary($memberKode, $selectedLmsYear);
            // Drawer detail anggota: tampilkan pengajuan MILIK anggota ini yang menunggu
            // leader (forRequesters([$member]) + Current_Approver=$leaderKode), BUKAN
            // seluruh antrian approver. Karena itu paksa mode per-anggota.
            $approvals = collect($this->buildApprovalItems(collect([$member]), true, $leaderKode, true))
                ->map(function ($item) {
                    $fallbackType = strtoupper(trim((string) ($item['jenis'] ?? 'APPROVAL')));
                    $fallbackStatus = strtoupper(trim((string) ($item['status'] ?? 'PENDING')));

                    return [
                        'id' => $item['id'] ??
                            strtoupper(trim((string) ($item['kode_karyawan'] ?? ''))).
                                '|'.
                                trim((string) ($item['no_transaksi'] ?? '')).
                                '|'.
                                $fallbackType,
                        'type' => $item['type'] ?? $fallbackType,
                        'tone' => $item['tone'] ?? ($fallbackStatus === 'FUTURE' ? 'info' : 'warning'),
                        'icon' => $item['icon'] ?? $this->resolveApprovalIcon($fallbackType),
                        'title' => $item['title'] ?? 'Approval '.ucwords(strtolower(str_replace('_', ' ', $fallbackType))),
                        'description' => $item['description'] ??
                            ($item['deskripsi'] ?? 'No transaksi '.trim((string) ($item['no_transaksi'] ?? '-'))),
                        'statusLabel' => $item['statusLabel'] ?? ucfirst(strtolower($fallbackStatus)),
                        'status' => strtolower((string) ($item['status'] ?? $fallbackStatus)),
                        'is_expired' => (bool) ($item['is_expired'] ?? false),
                        'expired_at' => $item['expired_at'] ?? null,
                        'url' => $item['url'] ?? null,
                        'meta' => $item['meta'] ?? null,
                        'tanggal_request' => $item['tanggal_request'] ?? null,
                    ];
                })
                ->values()
                ->all();
            $activeApprovalCount = collect($approvals)
                ->filter(fn ($item) => ! ((bool) ($item['is_expired'] ?? false)))
                ->count();
            // dd($kpi['parameters']);

            return [
                'id' => strtoupper(trim($memberKode)),
                'kode_karyawan' => strtoupper(trim($memberKode)),
                'name' => $member->Nama ?? 'Tanpa Nama',
                'initials' => $this->buildInitials($member->Nama ?? 'U'),
                'position' => $member->nama_jabatan ?? '-',
                'department' => $member->nama_divisi ?? '-',
                'shift' => [
                    'name' => $attendance['shift_name'],
                    'time' => $attendance['shift_time'],
                ],
                'attendance' => $attendance,
                'kpi' => [
                    'year' => $selectedKpiYear,
                    'selectedPeriod' => $kpi['selected_period'] ?? '',
                    'score' => $kpi['score'] ?? 0,
                    'grade' => $kpi['grade_label'] ?? 'Belum ada data',
                    'achievement' => $kpi['achievement'] ?? 0,
                    'achievement_grade_label' => $kpi['achievement_grade_label'] ?? '-',
                    'achievement_grade_color' => $kpi['achievement_grade_color'] ?? '#94a3b8',
                    'ytdScore' => (float) ($kpi['ytdScore'] ?? ($kpi['ytd_score'] ?? 0.0)),
                    'ytdSubmittedCount' => (int) ($kpi['ytdSubmittedCount'] ?? ($kpi['ytd_submitted_count'] ?? 0)),
                    'ytd_grade_label' => $kpi['ytd_grade_label'] ?? '-',
                    'ytd_grade_color' => $kpi['ytd_grade_color'] ?? '#94a3b8',
                    'parameterSubmitted' => $kpi['parameter_submitted'] ?? false,
                    'performanceSubmitted' => $kpi['performance_submitted'] ?? false,
                    'parameterPeriod' => $kpi['parameter_period'] ?? 'Periode berjalan',
                    'performancePeriod' => $kpi['performance_period'] ?? 'Periode berjalan',
                    'deadlineText' => $kpi['deadline_text'] ?? '',
                    'pendingItems' => $kpi['pending_items'] ?? [],
                    'monthlyAchievement' => $kpi['monthly_achievement'] ?? [],
                    'parameters' => $kpi['parameters'] ?? [],
                    'parameterScheduled' => (bool) ($kpi['parameter_scheduled'] ?? false),
                    'performanceScheduled' => (bool) ($kpi['performance_scheduled'] ?? false),
                    'parameterApprovalStatus' => $kpi['parameter_approval_status'] ?? 'none',
                    'performanceApprovalStatus' => $kpi['performance_approval_status'] ?? 'none',
                    'performanceHasParameters' => (bool) ($kpi['performance_has_parameters'] ?? false),
                ],
                'lms' => [
                    'year' => $selectedLmsYear,
                    'has_data' => $lms['has_data'] ?? ($lms['enrolled_courses'] ?? 0) > 0,
                    'completed' => $lms['completed_courses'] ?? 0,
                    'total' => $lms['enrolled_courses'] ?? 0,
                    'pending' => $lms['mandatory_pending'] ??
                        max(0, ($lms['enrolled_courses'] ?? 0) - ($lms['completed_courses'] ?? 0)),
                    'progress' => $lms['progress'] ?? 0,
                    'average_score' => $lms['average_score'] ?? null,
                    'learning_gain' => $lms['learning_gain'] ?? null,
                    'learning_hours' => $lms['learning_hours'] ?? 0,
                    'avg_duration_hours' => $lms['avg_duration_hours'] ?? 0,
                    'attempted_courses' => $lms['attempted_courses'] ?? 0,
                    'next_session' => $lms['next_session'] ?? 'Tidak ada training aktif',
                    'next_session_date' => $lms['next_session_date'] ?? '-',
                    'trainings' => $lms['trainings'] ?? [],
                    'learning_progress' => $lms['learning_progress'] ?? [],
                    'completed_monthly' => $lms['completed_monthly'] ?? [],
                    'duration_monthly' => $lms['duration_monthly'] ?? [],
                ],
                'overtime' => $attendance['overtime'],
                'leave' => $this->buildLeaveSummary($memberKode),
                'approvalCount' => $activeApprovalCount,
                'approvals' => $approvals,
                'follow_up_summary' => $this->buildFollowUpSummary($attendance, $kpi, $lms, $activeApprovalCount),
            ];
        });
    }

    protected function buildMemberDetailKpiSummary(object $member, string $memberKode, int $selectedKpiYear): array
    {
        $summary = $this->homepageKpiService->getSummary($memberKode, $selectedKpiYear);
        // dd($member, $memberKode, $selectedKpiYear);
        $kpiDetail = $this->homepageKpiService->getLeaderKpiMemberDetail($member, 'month', null, $selectedKpiYear);
        // dd($summary, $kpiDetail);
        $selectedScore = (float) data_get($kpiDetail, 'summary.selected_score', 0);
        $selectedPeriodLabel = (string) data_get($kpiDetail, 'summary.period_label', 'Periode berjalan');
        $trendRows = collect((array) data_get($kpiDetail, 'trend_12_months', []))
            ->map(function ($item) {
                $period = (string) data_get($item, 'period', '');
                $monthLabel = (string) data_get($item, 'month', '');

                return [
                    'period' => $period,
                    'month' => $monthLabel !== '' ? $monthLabel : $period,
                    'value' => (float) round((float) data_get($item, 'score', 0), 1),
                ];
            })
            ->values()
            ->all();
        $parameterRows = collect((array) data_get($kpiDetail, 'parameter_rows', []))
            ->map(function ($row) {
                return [
                    'name' => (string) data_get($row, 'parameter', 'Parameter KPI'),
                    'score' => (float) data_get($row, 'achievement', data_get($row, 'score', 0)),
                    'achievement' => (float) data_get($row, 'achievement', 0),
                    'target' => (float) data_get($row, 'target', 0),
                    'actual' => (float) data_get($row, 'actual', 0),
                    'hasPerformanceUpdate' => (bool) data_get($row, 'has_performance_update', false),
                    'status' => (string) data_get($row, 'status', 'N/A'),
                    'status_color' => (string) data_get($row, 'status_color', '#94a3b8'),
                    'formStatus' => (string) data_get($row, 'form_status', '-'),
                ];
            })
            ->values()
            ->all();
        // dd($summary, $kpiDetail, $selectedScore, $selectedPeriodLabel, $trendRows, $parameterRows);

        return [
            'has_data' => (bool) data_get($summary, 'has_data', false),
            'score' => round($selectedScore, 1),
            'grade_label' => (string) data_get($summary, 'grade_label', 'Belum ada data KPI'),
            'achievement' => round($selectedScore, 1),
            'achievement_grade_label' => (string) (data_get($kpiDetail, 'summary.achievement_grade_label') ?:
                data_get($summary, 'achievement_grade_label', '-')),
            'achievement_grade_color' => (string) (data_get($kpiDetail, 'summary.achievement_grade_color') ?:
                data_get($summary, 'achievement_grade_color', '#94a3b8')),
            'ytdScore' => (float) data_get($summary, 'ytd_score', 0.0),
            'ytdSubmittedCount' => (int) data_get($summary, 'ytd_submitted_count', 0),
            'ytd_grade_label' => (string) (data_get($kpiDetail, 'summary.ytd_grade_label') ?:
                data_get($summary, 'ytd_grade_label', '-')),
            'ytd_grade_color' => (string) (data_get($kpiDetail, 'summary.ytd_grade_color') ?:
                data_get($summary, 'ytd_grade_color', '#94a3b8')),
            'parameter_submitted' => (bool) data_get($summary, 'parameter_submitted', false),
            'performance_submitted' => (bool) data_get($summary, 'performance_submitted', false),
            'parameter_period' => $selectedPeriodLabel,
            'performance_period' => $selectedPeriodLabel,
            'deadline_text' => (string) data_get($summary, 'deadline_text', ''),
            'pending_items' => (array) data_get($summary, 'pending_items', []),
            'monthly_achievement' => $trendRows,
            'monthlyAchievement' => $trendRows,
            'parameters' => $parameterRows,
            'kpi_year' => $selectedKpiYear,
            'selected_period' => (string) data_get($kpiDetail, 'summary.period', ''),
            'parameter_scheduled' => (bool) data_get($kpiDetail, 'summary.form_scheduled', false),
            'performance_scheduled' => (bool) data_get($kpiDetail, 'summary.cutoff_performance_scheduled', false),
            'parameter_approval_status' => (string) data_get($kpiDetail, 'summary.parameter_approval_status', 'none'),
            'performance_approval_status' => (string) data_get(
                $kpiDetail,
                'summary.performance_approval_status',
                'none',
            ),
            'performance_has_parameters' => (bool) data_get($kpiDetail, 'summary.performance_has_parameters', false),
        ];
    }

    public function getPaginatedApprovalItems(string $leaderKode, int $page = 1, int $perPage = 6): array
    {
        return $this->profileEndpoint('leader.approvals', function () use ($leaderKode, $page, $perPage) {
            $teamMembers = $this->loadTeamMembers($leaderKode);
            $items = collect($this->buildApprovalItems($teamMembers, false, $leaderKode))
                ->sortByDesc('tanggal_request')
                ->values();

            return $this->paginateCollection($items, $page, $perPage);
        });
    }

    public function getNeedsAction(string $leaderKode, int $page = 1, int $perPage = 10, array $filters = []): array
    {
        return $this->profileEndpoint('leader.needs-action', function () use ($leaderKode, $page, $perPage, $filters) {
            $startedAt = microtime(true);
            $now = Carbon::now('Asia/Jakarta');
            $kpiYear = $this->normalizeKpiYear(
                isset($filters['kpi_year']) && is_numeric($filters['kpi_year']) ? (int) $filters['kpi_year'] : null,
            );
            $includeProfile = (bool) ($filters['include_profile'] ?? false);
            $fallbackEnabled = (bool) ($filters['enable_fallback'] ?? $this->needsActionFallbackEnabled);
            $categoryFilter = strtolower(trim((string) ($filters['category'] ?? 'all')));
            if (! in_array($categoryFilter, ['all', 'hcis', 'kpi', 'approval'], true)) {
                $categoryFilter = 'all';
            }
            $search = trim((string) ($filters['search'] ?? ''));
            $profile = [
                'durations_ms' => [],
                'counts' => [],
                'flags' => [
                    'category_filter' => $categoryFilter,
                    'fallback_enabled' => $fallbackEnabled,
                ],
            ];

            $cutoffRange = $this->uDashReadModelService->getCutoffRange($now->copy()->startOfDay());
            $startDate = $cutoffRange['start_date']->copy()->startOfDay();
            $endDate = $cutoffRange['end_date']->copy()->startOfDay();
            if ($endDate->gt($now->copy()->startOfDay())) {
                $endDate = $now->copy()->startOfDay();
            }
            $cutoffLabel =
                (string) ($cutoffRange['label'] ?? $startDate->toDateString().' s/d '.$endDate->toDateString());

            $loadMembersStartedAt = microtime(true);
            // Cache-first: endpoint TIDAK menjalankan sync (HCIS/KPI/Approval) secara
            // sinkron — semuanya mahal. Endpoint hanya memuat anggota tim + membaca
            // notif cache dari DB, lalu men-dispatch job sync di background (throttled
            // + deduped) agar data ter-refresh tanpa membuat user menunggu.
            $teamMembers = $this->loadTeamMembers($leaderKode);
            $syncDispatched = false;
            if ($teamMembers->isNotEmpty()) {
                $syncDispatched = $this->dispatchLeaderDashboardSync($leaderKode, $kpiYear);
            }
            $profile['durations_ms']['load_team_members'] = (int) round(
                (microtime(true) - $loadMembersStartedAt) * 1000,
            );
            $profile['counts']['team_members'] = $teamMembers->count();
            if ($teamMembers->isEmpty()) {
                $emptyPage = $this->paginateCollection(collect(), $page, max(1, min(50, $perPage)));

                $response = [
                    'data' => [
                        'summary' => [
                            'total_active_items' => 0,
                            'hcis_total' => 0,
                            'kpi_total' => 0,
                            'approval_total' => 0,
                            'critical_count' => 0,
                            'high_count' => 0,
                            'medium_count' => 0,
                            'cutoff_label' => $cutoffLabel,
                            'kpi_year' => $kpiYear,
                            'last_generated_at' => $now->toIso8601String(),
                        ],
                        'items' => $emptyPage['data'],
                    ],
                    'pagination' => $emptyPage['pagination'],
                    'meta' => [
                        'priority_order' => ['hcis', 'approval', 'kpi'],
                        'category_filter' => $categoryFilter,
                        'kpi_year' => $kpiYear,
                        'generated_at' => $now->toIso8601String(),
                    ],
                ];
                if ($includeProfile) {
                    $response['meta']['profile'] = $profile;
                }

                return $response;
            }

            $allItems = $this->buildLeaderCachedNeedsActionItems(
                $this->loadActiveLeaderDashboardNotificationRows($leaderKode, 250, $startDate->format('Y-m')),
            );
            $profile['counts']['cached_items_raw'] = $allItems->count();
            $fallbackUsed = false;
            if ($allItems->isEmpty() && $fallbackEnabled) {
                $fallbackUsed = true;
                $allItems = collect();
            }
            $profile['flags']['fallback_used'] = $fallbackUsed;
            $profile['counts']['fallback_items'] = $fallbackUsed ? $allItems->count() : 0;

            $rankStartedAt = microtime(true);
            if ($search !== '') {
                $searchNeedle = strtolower($search);
                $allItems = $allItems
                    ->filter(function (array $item) use ($searchNeedle) {
                        $searchable = strtolower(
                            implode(' ', [
                                (string) ($item['member_name'] ?? ''),
                                (string) ($item['member_department'] ?? ''),
                                (string) ($item['member_position'] ?? ''),
                                (string) ($item['title'] ?? ''),
                                (string) ($item['description'] ?? ''),
                                (string) ($item['period_label'] ?? ''),
                            ]),
                        );

                        return str_contains($searchable, $searchNeedle);
                    })
                    ->values();
            }

            $filteredItems =
                $categoryFilter === 'all'
                    ? $allItems
                    : $allItems
                        ->filter(fn (array $item) => strtolower((string) ($item['category'] ?? '')) === $categoryFilter)
                        ->values();

            $rankedItems = $filteredItems
                ->sort(function (array $a, array $b) {
                    $categoryCompare =
                        $this->resolveNeedsActionCategoryRank((string) ($a['category'] ?? '')) <=>
                        $this->resolveNeedsActionCategoryRank((string) ($b['category'] ?? ''));
                    if ($categoryCompare !== 0) {
                        return $categoryCompare;
                    }

                    $severityCompare =
                        $this->resolveNeedsActionSeverityRank((string) ($a['severity'] ?? '')) <=>
                        $this->resolveNeedsActionSeverityRank((string) ($b['severity'] ?? ''));
                    if ($severityCompare !== 0) {
                        return $severityCompare;
                    }

                    $aUpdated = strtotime((string) ($a['updated_at'] ?? '')) ?: 0;
                    $bUpdated = strtotime((string) ($b['updated_at'] ?? '')) ?: 0;
                    if ($aUpdated !== $bUpdated) {
                        return $bUpdated <=> $aUpdated;
                    }

                    return strcmp((string) ($a['member_name'] ?? ''), (string) ($b['member_name'] ?? ''));
                })
                ->values();
            $profile['durations_ms']['rank_and_filter'] = (int) round((microtime(true) - $rankStartedAt) * 1000);
            $profile['counts']['items_after_filter'] = $rankedItems->count();

            $paginateStartedAt = microtime(true);
            $paginated = $this->paginateCollection($rankedItems, $page, max(1, min(50, $perPage)));
            $profile['durations_ms']['paginate'] = (int) round((microtime(true) - $paginateStartedAt) * 1000);

            $summary = [
                'total_active_items' => $rankedItems->count(),
                'hcis_total' => $rankedItems
                    ->filter(fn ($item) => strtolower((string) ($item['category'] ?? '')) === 'hcis')
                    ->count(),
                'kpi_total' => $rankedItems
                    ->filter(fn ($item) => strtolower((string) ($item['category'] ?? '')) === 'kpi')
                    ->count(),
                'approval_total' => $rankedItems
                    ->filter(fn ($item) => strtolower((string) ($item['category'] ?? '')) === 'approval')
                    ->count(),
                'critical_count' => $rankedItems
                    ->filter(fn ($item) => strtolower((string) ($item['severity'] ?? '')) === 'critical')
                    ->count(),
                'high_count' => $rankedItems
                    ->filter(fn ($item) => strtolower((string) ($item['severity'] ?? '')) === 'high')
                    ->count(),
                'medium_count' => $rankedItems
                    ->filter(fn ($item) => strtolower((string) ($item['severity'] ?? '')) === 'medium')
                    ->count(),
                'cutoff_label' => $cutoffLabel,
                'kpi_year' => $kpiYear,
                'last_generated_at' => $now->toIso8601String(),
                // True bila request ini baru men-trigger sync background (data masih
                // di-refresh). Frontend memakai ini untuk auto-refetch sekali setelah
                // jeda, terutama saat cold-start (cache kosong).
                'sync_pending' => $syncDispatched,
            ];

            $response = [
                'data' => [
                    'summary' => $summary,
                    'items' => $paginated['data'],
                ],
                'pagination' => $paginated['pagination'],
                'meta' => [
                    'priority_order' => ['hcis', 'approval', 'kpi'],
                    'category_filter' => $categoryFilter,
                    'kpi_year' => $kpiYear,
                    'generated_at' => $now->toIso8601String(),
                ],
            ];
            $profile['durations_ms']['total'] = (int) round((microtime(true) - $startedAt) * 1000);
            Log::info('Leader needs-action profile', [
                'leader_kode' => $leaderKode,
                'kpi_year' => $kpiYear,
                'category' => $categoryFilter,
                'team_members' => $profile['counts']['team_members'] ?? 0,
                'durations_ms' => $profile['durations_ms'],
                'counts' => $profile['counts'],
                'flags' => $profile['flags'],
            ]);
            if ($includeProfile) {
                $response['meta']['profile'] = $profile;
            }

            return $response;
        });
    }

    public function getNeedsActionBadge(string $leaderKode, array $filters = []): array
    {
        return $this->profileEndpoint('leader.needs-action-badge', function () use ($leaderKode) {
            $cutoffRange = $this->uDashReadModelService->getCutoffRange(Carbon::now('Asia/Jakarta')->startOfDay());
            $scannedCount = $this->loadActiveLeaderDashboardNotificationRows(
                $leaderKode,
                11,
                $cutoffRange['start_date']->copy()->format('Y-m'),
            )->count();

            $isCapped = $scannedCount > 10;
            $displayCount = min($scannedCount, 10);

            return [
                'count' => $displayCount,
                'label' => $isCapped ? '10+' : (string) $displayCount,
                'has_items' => $scannedCount > 0,
                'is_capped' => $isCapped,
            ];
        });
    }

    protected function syncLeaderDashboardNeedsActionCache(
        string $leaderKode,
        int $kpiYear,
        ?bool $forceFullRescan = null,
    ): array {
        $teamMembers = $this->loadTeamMembers($leaderKode);
        if ($teamMembers->isEmpty()) {
            return ['team_members' => $teamMembers];
        }

        $now = Carbon::now('Asia/Jakarta')->startOfDay();
        $cutoffRange = $this->uDashReadModelService->getCutoffRange($now->copy());
        $startDate = $cutoffRange['start_date']->copy()->startOfDay();
        $endDate = $cutoffRange['end_date']->copy()->startOfDay()->min($now);
        $cachedRows = $this->loadActiveLeaderDashboardNotificationRows($leaderKode, 250, $startDate->format('Y-m'));
        $cutoffLabel =
            (string) ($cutoffRange['label'] ?? $startDate->toDateString().' s/d '.$endDate->toDateString());

        $this->syncLeaderDashboardHcisNotifications(
            $leaderKode,
            $teamMembers,
            $cachedRows,
            $startDate,
            $endDate,
            $cutoffLabel,
            $forceFullRescan,
        );
        $this->syncLeaderDashboardKpiNotifications($leaderKode, $teamMembers, $cachedRows, $kpiYear);
        $this->syncLeaderDashboardApprovalNotifications($leaderKode, $teamMembers, $cachedRows);

        // Flush SEKALI (batch) semua upsert notif yang dibuffer oleh 3 sync di atas.
        $this->flushLeaderActionNotifications($leaderKode);

        return ['team_members' => $teamMembers];
    }

    /**
     * Jalankan sync penuh needs-action (HCIS full re-scan + KPI + Approval) untuk satu
     * leader. Dipakai job async; di sinilah seluruh kerja berat dieksekusi.
     */
    public function runLeaderDashboardSync(string $leaderKode, int $kpiYear): void
    {
        $this->syncLeaderDashboardNeedsActionCache($leaderKode, $kpiYear, true);
    }

    /**
     * Revalidasi-on-read untuk leader: dipanggil saat leader membuka/refresh bell
     * atau pusat notifikasi. Menjalankan sync HCIS incremental yang kini juga
     * me-resolve notif anggota yang issue-nya sudah solved HARI INI (mis. sudah
     * checkout), tanpa membuat notif baru untuk hari yang belum selesai.
     *
     * Ringan (incremental, tanpa full re-scan) & dibatasi throttle
     * REVALIDATE_MIN_INTERVAL detik per leader.
     */
    public function revalidateActiveNotifications(string $leaderKode, int $kpiYear): void
    {
        try {
            $now = Carbon::now('Asia/Jakarta');
            $periodKey = $now->format('Y-m');

            $state = $this->notificationSyncStateService->getState(
                $leaderKode,
                NotificationService::DELIVERY_IN_APP,
                self::REVALIDATE_SYNC_SCOPE,
                $periodKey,
            );

            if (! empty($state?->Last_Synced_At)) {
                try {
                    $lastSynced = Carbon::parse($state->Last_Synced_At);
                    if ($lastSynced->diffInSeconds(Carbon::now()) < self::REVALIDATE_MIN_INTERVAL) {
                        return;
                    }
                } catch (\Throwable) {
                    // Last_Synced_At tidak valid -> lanjut revalidasi.
                }
            }

            // forceFullRescan=false: jalur ringan (incremental). Resolve hari-ini
            // tetap berjalan karena bersumber dari raw_case_dates notif aktif.
            $this->syncLeaderDashboardNeedsActionCache($leaderKode, $kpiYear, false);

            $this->notificationSyncStateService->upsertState(
                $leaderKode,
                NotificationService::DELIVERY_IN_APP,
                self::REVALIDATE_SYNC_SCOPE,
                $periodKey,
                $now->copy()->startOfDay(),
                ['source' => self::DASHBOARD_SOURCE],
            );
        } catch (\Throwable $e) {
            Log::error('HomepageLeaderDashboardService revalidate error: '.$e->getMessage());
        }
    }

    /**
     * Dispatch job sync background dengan throttle + dedup: hanya satu job per
     * leader+tahun yang boleh antri dalam window throttle, supaya banyak request
     * beruntun tidak menumpuk job (selain ShouldBeUnique di job-nya).
     */
    protected function dispatchLeaderDashboardSync(string $leaderKode, int $kpiYear): bool
    {
        $lockKey = 'leader_dash_sync:'.$leaderKode.':'.$kpiYear;
        $ttl = self::NEEDS_ACTION_SYNC_THROTTLE_SECONDS;

        // add() bersifat atomik: true hanya untuk pemanggil pertama dalam window TTL.
        if (! Cache::add($lockKey, 1, $ttl)) {
            return false;
        }

        try {
            LeaderDashboardSyncJob::dispatch($leaderKode, $kpiYear);

            return true;
        } catch (\Throwable $e) {
            // Jika dispatch gagal, lepas lock agar bisa dicoba lagi pada request berikutnya.
            Cache::forget($lockKey);
            Log::warning('Dispatch LeaderDashboardSyncJob failed: '.$e->getMessage(), [
                'leader_kode' => $leaderKode,
                'kpi_year' => $kpiYear,
            ]);

            return false;
        }
    }

    protected function syncLeaderDashboardHcisNotifications(
        string $leaderKode,
        Collection $teamMembers,
        Collection $cachedRows,
        Carbon $startDate,
        Carbon $endDate,
        string $cutoffLabel,
        ?bool $forceFullRescan = null,
    ): void {
        $periodKey = $startDate->format('Y-m');
        $yesterday = Carbon::now('Asia/Jakarta')->startOfDay()->subDay();
        $processEnd = $endDate->copy()->min($yesterday);
        if ($processEnd->lt($startDate)) {
            return;
        }

        $state = $this->notificationSyncStateService->getState(
            $leaderKode,
            NotificationService::DELIVERY_IN_APP,
            self::HCIS_SYNC_SCOPE,
            $periodKey,
        );
        $processedUntil = ! empty($state?->Processed_Until)
            ? Carbon::parse($state->Processed_Until, 'Asia/Jakarta')->startOfDay()
            : null;

        // Tentukan apakah saat ini perlu full re-scan seluruh cutoff (throttled) atau
        // cukup incremental. Full re-scan dilakukan bila belum pernah, atau sudah lewat
        // HCIS_FULL_RESCAN_INTERVAL_MINUTES sejak full re-scan terakhir.
        $stateMeta = $this->decodeNotificationMeta($state?->Meta_JSON ?? null);
        $lastFullRescanAt = ! empty($stateMeta['last_full_rescan_at'])
            ? Carbon::parse((string) $stateMeta['last_full_rescan_at'], 'Asia/Jakarta')
            : null;
        // $forceFullRescan: true = paksa full (dipakai job async), false = paksa
        // incremental (dipakai endpoint agar ringan), null = putuskan via throttle.
        $shouldFullRescan =
            $forceFullRescan ??
            $processedUntil === null ||
                $lastFullRescanAt === null ||
                $lastFullRescanAt->diffInMinutes(Carbon::now('Asia/Jakarta')) >=
                    self::HCIS_FULL_RESCAN_INTERVAL_MINUTES;

        $dates = collect();
        // Cocokkan prefix 'LEADER_HCIS' (tanpa underscore) supaya mencakup type BARU
        // per-member ('LEADER_HCIS') sekaligus type LAMA per-issue ('LEADER_HCIS_*'),
        // sehingga notif per-issue lama tetap ikut diproses/di-prune saat migrasi.
        $activeHcisRows = $cachedRows->filter(fn ($row) => str_starts_with((string) ($row->Type ?? ''), 'LEADER_HCIS'));

        // Kumpulkan semua tanggal dari notif aktif yang ada di DB. Tanggal-tanggal ini
        // harus selalu masuk ke scan window — terlepas dari processEnd — supaya kondisi
        // yang sudah resolved (misal shift dikoreksi jadi OFF) terdeteksi dan notif
        // segera di-resolve. Tanpa ini, notif aktif yang raw_case_dates-nya melewati
        // processEnd tidak pernah di-scan ulang dan tetap muncul di action center
        // padahal kondisi anggota sudah normal.
        $activeCaseDates = collect();
        $activeHcisRows->each(function ($row) use (&$activeCaseDates) {
            $meta = $this->decodeNotificationMeta($row->Meta_JSON ?? null);
            $activeCaseDates = $activeCaseDates->merge((array) ($meta['raw_case_dates'] ?? []));
        });

        if ($processedUntil === null) {
            // Belum pernah sync. Saat full re-scan, proses seluruh cutoff. Saat dipaksa
            // incremental (endpoint), cukup window pendek dulu supaya response ringan —
            // full scan menyusul lewat job async.
            $firstSyncStart = $shouldFullRescan
                ? $startDate->copy()
                : $processEnd
                    ->copy()
                    ->subDays(max(0, self::HCIS_ROLLING_LOOKBACK_DAYS - 1))
                    ->max($startDate);
            $dates = $dates->merge($this->leaderDateRangeStrings($firstSyncStart, $processEnd));
        } else {
            $nextDate = $processedUntil->copy()->addDay()->max($startDate);
            if ($nextDate->lte($processEnd)) {
                $dates = $dates->merge($this->leaderDateRangeStrings($nextDate, $processEnd));
            }
        }

        // Saat full re-scan (throttled): lookback mulai dari awal cutoff supaya seluruh
        // hari di periode ini dikoreksi ulang — menangkap alpha/no-checkout lama yang
        // datanya baru masuk. Di luar itu: lookback pendek (HCIS_ROLLING_LOOKBACK_DAYS)
        // agar endpoint tetap ringan dan tidak men-scan sebulan tiap request.
        $lookbackStart = $shouldFullRescan
            ? $startDate->copy()
            : $processEnd
                ->copy()
                ->subDays(max(0, self::HCIS_ROLLING_LOOKBACK_DAYS - 1))
                ->max($startDate);
        $dates = $dates
            ->merge($this->leaderDateRangeStrings($lookbackStart, $processEnd))
            ->filter(fn ($date) => $date >= $startDate->toDateString() && $date <= $processEnd->toDateString())
            ->unique()
            ->sort()
            ->values();

        // Gabungkan tanggal dari notif aktif yang berada di dalam rentang cutoff tapi
        // mungkin tidak tercakup di window scan di atas (misal tanggal yang tepat sama
        // dengan processEnd atau shift yang baru dikoreksi pada hari sebelumnya).
        // Filter tetap dibatasi oleh cutoff (startDate..endDate) agar tidak keluar periode.
        $extraDates = $activeCaseDates
            ->filter(fn ($date) => $date >= $startDate->toDateString() && $date <= $endDate->toDateString())
            ->diff($dates)
            ->values();
        if ($extraDates->isNotEmpty()) {
            $dates = $dates->merge($extraDates)->unique()->sort()->values();
        }

        // Tanggal HARI INI yang berasal dari notif aktif (raw_case_dates) dipakai
        // HANYA untuk scan→resolve, bukan create. Tujuannya: bila issue member di
        // hari ini sudah solved (mis. leader/anggota sudah checkout), member yang
        // sudah bersih bisa langsung di-resolve tanpa menunggu besok. Create tetap
        // dibatasi processEnd (s/d kemarin) supaya tidak men-generate notif
        // prematur untuk hari yang belum selesai.
        $todayString = Carbon::now('Asia/Jakarta')->toDateString();
        $resolveOnlyDates = $activeCaseDates
            ->filter(fn ($date) => (string) $date === $todayString)
            ->filter(fn ($date) => $date >= $startDate->toDateString() && $date <= $endDate->toDateString())
            ->diff($dates)
            ->unique()
            ->values();

        $scanDates = $dates->merge($resolveOnlyDates)->unique()->sort()->values();

        // Key notif HCIS yang sudah aktif di DB. Dipakai sebagai whitelist saat
        // memproses resolve-only dates (hari ini): member tanpa notif aktif tidak
        // boleh dibuatkan notif baru dari hari yang belum selesai (create prematur).
        // Member yang sudah punya notif aktif tetap disegarkan; yang sudah bersih
        // tidak masuk $items lalu ter-resolve di loop berikutnya.
        $existingHcisKeys = $activeHcisRows
            ->pluck('Notification_Key')
            ->filter()
            ->map(fn ($key) => (string) $key)
            ->values();

        $activeKeys = collect();

        // Loop 1: tanggal create+resolve (s/d kemarin) — boleh membuat notif baru.
        // Loop 2: resolve-only dates (hari ini) — hanya menyegarkan notif yang sudah
        // ada, tidak membuat yang baru. Diproksikan via $allowCreate.
        $processRanges = function (Collection $rangeDates, bool $allowCreate) use (
            $teamMembers,
            $cutoffLabel,
            $leaderKode,
            $periodKey,
            &$activeKeys,
            $existingHcisKeys,
        ): void {
            if ($rangeDates->isEmpty()) {
                return;
            }

            foreach ($this->leaderDateRangesFromStrings($rangeDates) as $range) {
                $items = $this->buildNeedsActionHcisRows($teamMembers, $range['start'], $range['end'], $cutoffLabel, '');

                $items->each(function (array $item) use (
                    $leaderKode,
                    $periodKey,
                    &$activeKeys,
                    $allowCreate,
                    $existingHcisKeys,
                ) {
                    // 1 notif per MEMBER (bukan per jenis issue). Key/type/reference
                    // konstan per member+periode supaya tidak berubah/berkedip walau
                    // jenis issue dominan member berganti antar-sync.
                    $key = $this->leaderNotificationKey(
                        $leaderKode,
                        (string) ($item['member_id'] ?? ''),
                        $periodKey,
                        'HCIS',
                    );

                    // Resolve-only (hari ini) & member belum punya notif aktif: lewati.
                    if (! $allowCreate && ! $existingHcisKeys->contains($key)) {
                        return;
                    }

                    $activeKeys->push($key);
                    $this->upsertLeaderActionNotification($leaderKode, $item, $key, [
                        'type' => 'LEADER_HCIS',
                        'module' => 'ESS',
                        'reference_id' => ($item['member_id'] ?? '').'|hcis|'.$periodKey,
                        'priority' => $this->leaderPriorityForItem($item),
                    ]);
                });
            }
        };

        $processRanges($dates, true);
        $processRanges($resolveOnlyDates, false);

        // Pakai $scanDates (termasuk tanggal hari ini dari notif aktif) sebagai
        // basis tanggal yang sudah diverifikasi, supaya notif member yang semua
        // raw_case_dates-nya sudah dicek & bersih bisa di-resolve — termasuk kasus
        // member yang issue terakhirnya jatuh di hari ini lalu sudah solved.
        $checkedDates = $scanDates->all();
        $activeHcisRows->each(function ($row) use ($leaderKode, $activeKeys, $checkedDates) {
            $key = (string) ($row->Notification_Key ?? '');
            if ($activeKeys->contains($key)) {
                return;
            }

            $meta = $this->decodeNotificationMeta($row->Meta_JSON ?? null);
            $rowDates = collect((array) ($meta['raw_case_dates'] ?? []))->filter()->values();
            if ($rowDates->isEmpty() || $rowDates->every(fn ($date) => in_array((string) $date, $checkedDates, true))) {
                $this->notificationService->resolveByKey($key, $leaderKode);
            }
        });

        $this->notificationSyncStateService->upsertState(
            $leaderKode,
            NotificationService::DELIVERY_IN_APP,
            self::HCIS_SYNC_SCOPE,
            $periodKey,
            $processEnd,
            [
                'source' => self::DASHBOARD_SOURCE,
                'rolling_lookback_days' => self::HCIS_ROLLING_LOOKBACK_DAYS,
                'cutoff_start' => $startDate->toDateString(),
                'cutoff_end' => $endDate->toDateString(),
                'processed_window_end' => $processEnd->toDateString(),
                'checked_dates' => count($checkedDates),
                'full_rescan' => $shouldFullRescan,
                // Perbarui penanda hanya saat full re-scan; selain itu pertahankan nilai
                // lama supaya interval throttle dihitung dari full re-scan terakhir.
                'last_full_rescan_at' => $shouldFullRescan
                    ? Carbon::now('Asia/Jakarta')->toIso8601String()
                    : $stateMeta['last_full_rescan_at'] ?? null,
            ],
        );
    }

    protected function syncLeaderDashboardKpiNotifications(
        string $leaderKode,
        Collection $teamMembers,
        Collection $cachedRows,
        int $kpiYear,
    ): void {
        $periodKey = Carbon::now('Asia/Jakarta')->format('Y-m');
        $items = $this->buildNeedsActionKpiRows($teamMembers, $kpiYear);
        $activeKeys = collect();

        $items->each(function (array $item) use ($leaderKode, $periodKey, $kpiYear, &$activeKeys) {
            $pendingId = $this->normalizeLeaderToken(
                (string) ($item['pending_id'] ?? data_get($item, 'id', 'PENDING')),
            );
            $memberId = (string) ($item['member_id'] ?? '');
            $key = $this->leaderNotificationKey($leaderKode, $memberId, $periodKey, 'KPI_'.$pendingId);
            $activeKeys->push($key);
            $this->upsertLeaderActionNotification(
                $leaderKode,
                array_merge($item, [
                    'kpi_year' => $kpiYear,
                    'pending_id' => $pendingId,
                ]),
                $key,
                [
                    'type' => 'LEADER_KPI_'.$pendingId,
                    'module' => 'KPI',
                    'reference_id' => $memberId.'|'.$pendingId.'|'.$periodKey,
                    'priority' => $this->leaderPriorityForItem($item),
                ],
            );
        });

        $cachedRows
            ->filter(fn ($row) => str_starts_with((string) ($row->Type ?? ''), 'LEADER_KPI_'))
            ->each(function ($row) use ($leaderKode, $activeKeys) {
                $key = (string) ($row->Notification_Key ?? '');
                if (! $activeKeys->contains($key)) {
                    $this->notificationService->resolveByKey($key, $leaderKode);
                }
            });
    }

    protected function syncLeaderDashboardApprovalNotifications(
        string $leaderKode,
        Collection $teamMembers,
        Collection $cachedRows,
    ): void {
        $items = collect($this->buildApprovalItems($teamMembers, true, $leaderKode))
            ->filter(
                fn ($item) => in_array(
                    strtoupper((string) ($item['status'] ?? 'PENDING')),
                    ['PENDING', 'FUTURE', 'EXPIRED'],
                    true,
                ),
            )
            ->values();
        $activeKeys = collect();

        $items->each(function (array $approval) use ($leaderKode, &$activeKeys) {
            $status = $this->normalizeLeaderToken((string) ($approval['status'] ?? 'PENDING'));
            $transactionNo = trim((string) ($approval['no_transaksi'] ?? ($approval['id'] ?? '')));
            if ($transactionNo === '') {
                return;
            }

            $item = [
                'id' => 'approval-'.$transactionNo,
                'member_id' => $approval['memberId'] ?? ($approval['kode_karyawan'] ?? ''),
                'memberId' => $approval['memberId'] ?? ($approval['kode_karyawan'] ?? ''),
                'member_name' => $approval['member_name'] ?? ($approval['memberName'] ?? 'Anggota'),
                'memberName' => $approval['memberName'] ?? ($approval['member_name'] ?? 'Anggota'),
                'member_position' => '-',
                'member_department' => '-',
                'category' => 'approval',
                'severity' => $status === 'EXPIRED' ? 'high' : 'medium',
                'tone' => $approval['tone'] ?? ($status === 'EXPIRED' ? 'danger' : 'warning'),
                'icon' => $approval['icon'] ?? 'bi bi-check2-square',
                'title' => $approval['title'] ?? 'Approval menunggu tindakan',
                'description' => $approval['description'] ?? 'Menunggu tindak lanjut approval.',
                'action_hint' => 'Buka daftar approval untuk menindaklanjuti.',
                'period_label' => $approval['meta'] ?? '',
                'updated_at' => ! empty($approval['tanggal_request'])
                    ? Carbon::parse($approval['tanggal_request'], 'Asia/Jakarta')->toIso8601String()
                    : Carbon::now('Asia/Jakarta')->toIso8601String(),
                'meta' => $approval['meta'] ?? 'Approval',
                'no_transaksi' => $transactionNo,
                'approval_type' => $approval['type'] ?? ($approval['jenis'] ?? 'APPROVAL'),
                'status' => strtolower($status),
                'url' => $approval['url'] ?? null,
            ];
            $key = $this->leaderNotificationKey($leaderKode, $transactionNo, 'APPROVAL', 'APPROVAL_'.$status);
            $activeKeys->push($key);
            $this->upsertLeaderActionNotification($leaderKode, $item, $key, [
                'type' => 'LEADER_APPROVAL_'.$status,
                'module' => 'ESS',
                'reference_id' => $transactionNo,
                'priority' => $status === 'EXPIRED' ? 2 : $this->leaderPriorityForItem($item),
            ]);
        });

        $cachedRows
            ->filter(fn ($row) => str_starts_with((string) ($row->Type ?? ''), 'LEADER_APPROVAL_'))
            ->each(function ($row) use ($leaderKode, $activeKeys) {
                $key = (string) ($row->Notification_Key ?? '');
                if (! $activeKeys->contains($key)) {
                    $this->notificationService->resolveByKey($key, $leaderKode);
                }
            });
    }

    protected function loadActiveLeaderDashboardNotificationRows(
        string $leaderKode,
        int $limit = 250,
        ?string $hcisPeriodKey = null,
    ): Collection {
        $limit = max(1, min(250, $limit));

        return DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $leaderKode)
            ->where('Channel', NotificationService::DELIVERY_IN_APP)
            ->whereRaw("JSON_VALUE(CASE WHEN ISJSON(Meta_JSON) = 1 THEN Meta_JSON ELSE NULL END, '$.source') = ?", [
                self::DASHBOARD_SOURCE,
            ])
            ->where('Is_Read', 'T')
            ->where('Is_Deleted', 'T')
            ->where(function ($query) {
                $query->whereNull('Expired_At')->orWhere('Expired_At', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('Sent_At')->orWhere('Sent_At', '<=', now());
            })
            ->when($hcisPeriodKey, function ($query) use ($hcisPeriodKey) {
                // 'LEADER_HCIS%' mencakup type baru per-member ('LEADER_HCIS') & lama
                // per-issue ('LEADER_HCIS_*'). Notif HCIS difilter ke periode cutoff;
                // notif non-HCIS lolos apa adanya.
                $query->where(function ($query) use ($hcisPeriodKey) {
                    $query->where('Type', 'not like', 'LEADER_HCIS%')->orWhere(function ($query) use ($hcisPeriodKey) {
                        $query
                            ->where('Type', 'like', 'LEADER_HCIS%')
                            ->where('Reference_Id', 'like', '%|'.$hcisPeriodKey);
                    });
                });
            })
            ->orderByRaw('COALESCE(Priority, 3) ASC')
            ->orderBy('Created_At', 'desc')
            ->limit($limit)
            ->get();
    }

    protected function buildLeaderCachedNeedsActionItems(Collection $rows): Collection
    {
        return $rows
            ->map(function ($row) {
                $meta = $this->decodeNotificationMeta($row->Meta_JSON ?? null);
                $category = strtolower((string) ($meta['category'] ?? 'hcis'));
                $memberId = (string) ($meta['member_id'] ?? '');
                $memberName = (string) ($meta['member_name'] ?? 'Anggota');

                return [
                    'id' => (string) ($meta['id'] ?? 'notification-'.$row->Id_Notification),
                    'member_id' => $memberId,
                    'memberId' => $memberId,
                    'member_name' => $memberName,
                    'memberName' => $memberName,
                    'member_position' => (string) ($meta['member_position'] ?? '-'),
                    'member_department' => (string) ($meta['member_department'] ?? '-'),
                    'category' => $category,
                    'severity' => (string) ($meta['severity'] ?? 'medium'),
                    'tone' => (string) ($meta['tone'] ??
                            $this->leaderToneFromSeverity((string) ($meta['severity'] ?? 'medium'))),
                    'icon' => (string) ($meta['icon'] ??
                            ($category === 'kpi' ? 'bi bi-graph-up-arrow' : 'bi bi-person-exclamation')),
                    'title' => (string) ($row->Title ?? ($meta['title'] ?? 'Perlu tindak lanjut')),
                    'description' => (string) ($row->Message ?? ($meta['description'] ?? '')),
                    'action_hint' => (string) ($meta['action_hint'] ?? ''),
                    'period_label' => (string) ($meta['period_label'] ?? ''),
                    'updated_at' => (string) ($meta['updated_at'] ?? ($row->Created_At ?? '')),
                    'meta' => (string) ($meta['meta_label'] ?? ($meta['meta'] ?? '')),
                    'issue_key' => $meta['issue_key'] ?? null,
                    'case_breakdown' => (array) ($meta['case_breakdown'] ?? []),
                    'case_dates' => (array) ($meta['case_dates'] ?? []),
                    'raw_case_dates' => (array) ($meta['raw_case_dates'] ?? []),
                    'url' => (string) ($meta['url'] ?? ''),
                    'no_transaksi' => (string) ($meta['no_transaksi'] ?? ''),
                    'notification_id' => (int) $row->Id_Notification,
                    'notification_key' => $row->Notification_Key ?? null,
                    'dismissible' => true,
                ];
            })
            ->values();
    }

    /**
     * Buffer payload upsert notif Action Center leader. Diisi oleh
     * upsertLeaderActionNotification, lalu di-flush SEKALI (batch) di akhir sync —
     * menggantikan N×(existsDismissed+select+update/insert) yang bikin job 37 detik.
     *
     * @var array<int,array{key:string,payload:array<string,mixed>}>
     */
    private array $leaderActionBuffer = [];

    protected function upsertLeaderActionNotification(
        string $leaderKode,
        array $item,
        string $notificationKey,
        array $overrides,
    ): void {
        if ($notificationKey === '') {
            return;
        }
        // existsDismissedKey TIDAK lagi dicek per-item di sini (N+1). Pengecekan
        // dismissed dilakukan BATCH saat flush.

        $category = strtolower((string) ($item['category'] ?? 'hcis'));
        $meta = [
            'source' => self::DASHBOARD_SOURCE,
            'category' => $category,
            'id' => $item['id'] ?? null,
            'member_id' => $item['member_id'] ?? ($item['memberId'] ?? null),
            'member_name' => $item['member_name'] ?? ($item['memberName'] ?? null),
            'member_position' => $item['member_position'] ?? null,
            'member_department' => $item['member_department'] ?? null,
            'period_key' => $this->leaderPeriodKeyFromReference((string) ($overrides['reference_id'] ?? '')),
            'period_label' => $item['period_label'] ?? null,
            'severity' => $item['severity'] ?? 'medium',
            'tone' => $item['tone'] ?? $this->leaderToneFromSeverity((string) ($item['severity'] ?? 'medium')),
            'icon' => $item['icon'] ?? null,
            'title' => $item['title'] ?? null,
            'description' => $item['description'] ?? null,
            'action_hint' => $item['action_hint'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
            'meta_label' => $item['meta'] ?? null,
            'issue_key' => $item['issue_key'] ?? null,
            'case_breakdown' => $item['case_breakdown'] ?? null,
            'case_dates' => $item['case_dates'] ?? null,
            'raw_case_dates' => $item['raw_case_dates'] ?? null,
            'kpi_year' => $item['kpi_year'] ?? null,
            'pending_id' => $item['pending_id'] ?? null,
            'no_transaksi' => $item['no_transaksi'] ?? null,
            'approval_type' => $item['approval_type'] ?? null,
            'status' => $item['status'] ?? null,
            'url' => $item['url'] ?? null,
        ];

        // Buffer (bukan upsert langsung) -> di-flush batch di flushLeaderActionNotifications().
        $this->leaderActionBuffer[] = [
            'key' => $notificationKey,
            'payload' => [
                'kode_karyawan' => $leaderKode,
                'type' => $overrides['type'],
                'title' => (string) ($item['title'] ?? 'Perlu tindak lanjut'),
                'message' => (string) ($item['description'] ?? ''),
                'module' => $overrides['module'],
                'severity' => $this->leaderNotificationSeverity((string) ($item['severity'] ?? 'medium')),
                'action_url' => '/homepage/leader-dashboard',
                'reference_id' => $overrides['reference_id'],
                'notification_key' => $notificationKey,
                'priority' => $overrides['priority'] ?? $this->leaderPriorityForItem($item),
                'channel' => NotificationService::DELIVERY_IN_APP,
                'meta' => array_filter($meta, fn ($value) => $value !== null),
                'created_by' => 'SYSTEM',
                'expired_at' => null,
                'sent_at' => null,
            ],
        ];
    }

    /**
     * Flush buffer notif Action Center leader dalam BATCH:
     * 1) batch cek dismissed-key -> buang yang sudah di-dismiss user,
     * 2) upsertManyByKey (1 SELECT + bulk insert + update per-id).
     * Menggantikan N+1 (existsDismissed + select + update per item).
     */
    protected function flushLeaderActionNotifications(string $leaderKode): void
    {
        if (empty($this->leaderActionBuffer)) {
            return;
        }

        $buffer = $this->leaderActionBuffer;
        $this->leaderActionBuffer = [];

        $keys = array_values(array_unique(array_map(fn ($b) => $b['key'], $buffer)));
        $dismissed = $this->notificationService->dismissedKeysIn($keys, $leaderKode);

        $payloads = [];
        foreach ($buffer as $b) {
            if (isset($dismissed[$b['key']])) {
                continue; // user sudah dismiss -> jangan buat ulang (identik perilaku lama)
            }
            $payloads[] = $b['payload'];
        }

        $this->notificationService->upsertManyByKey($payloads);
    }

    protected function leaderNotificationKey(string ...$parts): string
    {
        return collect([self::DASHBOARD_SOURCE, ...$parts])
            ->map(fn ($part) => $this->normalizeLeaderToken((string) $part))
            ->implode('|');
    }

    protected function normalizeLeaderToken(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9\-]+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    protected function leaderPriorityForItem(array $item): int
    {
        return match (strtolower((string) ($item['severity'] ?? 'medium'))) {
            'critical' => 1,
            'high' => 2,
            default => 3,
        };
    }

    protected function leaderNotificationSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical', 'high' => 'danger',
            'medium' => 'warning',
            default => 'info',
        };
    }

    protected function leaderToneFromSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => 'danger',
            'high' => 'warning',
            default => 'orange',
        };
    }

    protected function decodeNotificationMeta(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function leaderPeriodKeyFromReference(string $reference): ?string
    {
        $parts = explode('|', $reference);

        return $parts ? (end($parts) ?: null) : null;
    }

    protected function leaderDateRangeStrings(Carbon $startDate, Carbon $endDate): Collection
    {
        $dates = collect();
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $dates->push($cursor->toDateString());
            $cursor->addDay();
        }

        return $dates;
    }

    protected function leaderDateRangesFromStrings(Collection $dates): array
    {
        $ranges = [];
        $start = null;
        $previous = null;

        foreach ($dates->sort()->values() as $date) {
            $current = Carbon::parse($date, 'Asia/Jakarta')->startOfDay();

            if ($start === null) {
                $start = $current->copy();
                $previous = $current->copy();

                continue;
            }

            if ($previous->copy()->addDay()->isSameDay($current)) {
                $previous = $current->copy();

                continue;
            }

            $ranges[] = ['start' => $start->copy(), 'end' => $previous->copy()];
            $start = $current->copy();
            $previous = $current->copy();
        }

        if ($start !== null && $previous !== null) {
            $ranges[] = ['start' => $start->copy(), 'end' => $previous->copy()];
        }

        return $ranges;
    }

    public function getPaginatedTeamMembers(
        string $leaderKode,
        int $page = 1,
        int $perPage = 10,
        array $filters = [],
    ): array {
        return $this->profileEndpoint('leader.members', function () use ($leaderKode, $page, $perPage, $filters) {
            $keyword = strtolower(trim((string) ($filters['search'] ?? '')));
            $kpiYear = isset($filters['kpi_year']) ? $this->normalizeKpiYear((int) $filters['kpi_year']) : null;

            $perPage = max(1, min(10, $perPage));

            // PAGINASI BACKEND: hitung total + ambil HANYA anggota halaman ini dari SQL
            // (offset/limit, urut nama). buildMemberCards lalu cuma memproses 5 anggota
            // (bukan seluruh tim) -> dari ~24s build-131 menjadi ringan. Keyword search
            // tetap jalan di SQL. Urutan = nama (sort issueCount lama dilepas demi paginasi
            // backend, sesuai keputusan: ringan > urut-bermasalah-di-atas).
            $baseQuery = $this->buildTeamMembersQuery($leaderKode, $keyword);
            $total = (int) (clone $baseQuery)->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            $currentPage = min(max(1, $page), $lastPage);
            $offset = ($currentPage - 1) * $perPage;

            $teamMembers = $baseQuery->offset($offset)->limit($perPage)->get();
            $pageItems = collect($this->buildMemberCards($teamMembers, $kpiYear, $leaderKode))
                ->sortByDesc('issueCount') // urut anggota bermasalah di atas, DALAM halaman ini
                ->values();

            return [
                'data' => $pageItems->all(),
                'pagination' => [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'has_prev' => $currentPage > 1,
                    'has_next' => $currentPage < $lastPage,
                ],
            ];
        });
    }

    /** @deprecated digantikan paginasi backend; disimpan untuk referensi filter lama. */
    protected function getPaginatedTeamMembersLegacy(
        string $leaderKode,
        int $page = 1,
        int $perPage = 10,
        array $filters = [],
    ): array {
        return $this->profileEndpoint('leader.members.legacy', function () use ($leaderKode, $page, $perPage, $filters) {
            $keyword = strtolower(trim((string) ($filters['search'] ?? '')));
            $attendance = strtolower(trim((string) ($filters['attendance'] ?? 'all')));
            $kpi = strtolower(trim((string) ($filters['kpi'] ?? 'all')));
            $lms = strtolower(trim((string) ($filters['lms'] ?? 'all')));
            $kpiYear = isset($filters['kpi_year']) ? $this->normalizeKpiYear((int) $filters['kpi_year']) : null;

            $query = $this->buildTeamMembersQuery($leaderKode, $keyword);
            $perPage = max(1, min(10, $perPage));
            $teamMembers = $query->get();
            $items = collect($this->buildMemberCards($teamMembers, $kpiYear, $leaderKode));

            if ($attendance !== 'all') {
                $items = $items->filter(function ($member) use ($attendance) {
                    $todayStatus = strtolower((string) data_get($member, 'attendance.todayStatus', 'present'));

                    if ($attendance === 'late') {
                        return (int) data_get($member, 'attendance.lateCount', 0) > 0;
                    }

                    if ($attendance === 'leave') {
                        return in_array($todayStatus, ['leave', 'permission', 'sick', 'cuti'], true);
                    }

                    if ($attendance === 'absent') {
                        return in_array($todayStatus, ['absent', 'no-checkin'], true);
                    }

                    return $todayStatus === $attendance;
                });
            }

            if ($kpi !== 'all') {
                $items = $items->filter(function ($member) use ($kpi) {
                    $pending = count((array) data_get($member, 'kpi.pendingItems', []));

                    if ($kpi === 'kpi-alert') {
                        return $pending > 0;
                    }

                    if ($kpi === 'complete') {
                        return $pending === 0;
                    }

                    return true;
                });
            }

            if ($lms !== 'all') {
                $items = $items->filter(function ($member) use ($lms) {
                    $progress = (int) data_get($member, 'lms.progress', 0);

                    if ($lms === 'pending') {
                        return $progress < 100;
                    }

                    if ($lms === 'complete') {
                        return $progress >= 100;
                    }

                    return true;
                });
            }

            $items = $items->sortByDesc('issueCount')->values();
            $total = $items->count();
            $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
            $currentPage = min(max(1, $page), $lastPage);
            $pageItems = $items->forPage($currentPage, $perPage)->values();

            return [
                'data' => $pageItems->all(),
                'pagination' => [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'has_prev' => $currentPage > 1,
                    'has_next' => $currentPage < $lastPage,
                ],
            ];
        });
    }

    public function getPaginatedHeatmapRows(
        string $leaderKode,
        int $page = 1,
        int $perPage = 10,
        ?string $startDate = null,
        ?string $endDate = null,
        string $search = '',
        ?int $cutoffYear = null,
        ?int $cutoffMonth = null,
    ): array {
        return $this->profileEndpoint('leader.heatmap', function () use (
            $leaderKode,
            $page,
            $perPage,
            $startDate,
            $endDate,
            $search,
            $cutoffYear,
            $cutoffMonth,
        ) {
            $keyword = strtolower(trim($search));
            $perPage = max(1, min(50, $perPage));
            $today = Carbon::now('Asia/Jakarta')->startOfDay();

            // Resolve rentang tanggal. Prioritas: start/end manual > bulan cutoff terpilih >
            // periode cutoff berjalan. Range cutoff memakai aturan yang sama dgn modul lain
            // (getCutoffRangeForMonth/getCutoffRange) agar konsisten.
            $safeYear = is_int($cutoffYear) && $cutoffYear >= 2000 && $cutoffYear <= 2100 ? $cutoffYear : null;
            $safeMonth = is_int($cutoffMonth) && $cutoffMonth >= 1 && $cutoffMonth <= 12 ? $cutoffMonth : null;

            // Label periode mengikuti aturan tabel (Naming_Source) — sama persis dgn
            // Team Calendar. Default null untuk path start/end manual.
            $cutoffLabel = null;
            if ($startDate && $endDate) {
                $start = Carbon::createFromFormat('Y-m-d', $startDate, 'Asia/Jakarta')->startOfDay();
                $end = Carbon::createFromFormat('Y-m-d', $endDate, 'Asia/Jakarta')->startOfDay();
            } else {
                $cutoffRange =
                    $safeYear !== null && $safeMonth !== null
                        ? $this->uDashReadModelService->getCutoffRangeForMonth($safeYear, $safeMonth)
                        : $this->uDashReadModelService->getCutoffRange($today);

                // Pakai rentang cutoff PENUH (mis. 16 Jun – 15 Jul) — sama persis dgn
                // Team Calendar. Hari masa depan otomatis ber-tone not-started/no-data
                // lewat classifyAttendanceRow, jadi tidak perlu di-cap ke hari ini.
                $start = $cutoffRange['start_date']->copy()->startOfDay();
                $end = $cutoffRange['end_date']->copy()->startOfDay();
                $cutoffLabel = $cutoffRange['label'] ?? null;
            }

            // Cache hasil per (leader+rentang+page+perPage+search). Periode berjalan TTL pendek
            // agar data hari ini segar; periode lampau lebih panjang.
            $isCurrentPeriod = $end->isSameDay($today) || $end->greaterThanOrEqualTo($today);
            $ttl = $isCurrentPeriod ? 60 : 180;
            $cacheKey = sprintf(
                'leader.heatmap:%s:%s:%s:p%d:pp%d:%s',
                $leaderKode,
                $start->toDateString(),
                $end->toDateString(),
                $page,
                $perPage,
                md5($keyword),
            );

            return Cache::remember($cacheKey, $ttl, function () use (
                $leaderKode,
                $keyword,
                $perPage,
                $page,
                $start,
                $end,
                $cutoffLabel,
            ) {
                $query = $this->buildTeamMembersQuery($leaderKode, $keyword);
                $total = (clone $query)->count();
                $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
                $currentPage = min(max(1, $page), $lastPage);
                $teamMembers = $query->forPage($currentPage, $perPage)->get();

                $attendanceByCode = $this->buildHeatmapAttendanceSummaries($teamMembers, $start, $end);

                $items = $teamMembers
                    ->map(function ($member) use ($attendanceByCode) {
                        $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                        $attendance = $attendanceByCode[$kode] ?? $this->defaultAttendanceSummary();
                        $dailyLog = collect((array) ($attendance['dailyLog'] ?? []))->values()->all();
                        $issueCount = collect($dailyLog)
                            ->filter(
                                fn ($item) => in_array(
                                    strtolower((string) ($item['tone'] ?? '')),
                                    ['danger', 'warning', 'orange'],
                                    true,
                                ),
                            )
                            ->count();

                        return [
                            'id' => $kode,
                            'name' => $member->Nama ?? 'Tanpa Nama',
                            'attendance' => [
                                'dailyLog' => $dailyLog,
                            ],
                            'issueCount' => $issueCount,
                            'categoryCounts' => $this->summarizeDailyLogCategories($dailyLog),
                        ];
                    })
                    ->values();

                return [
                    'data' => $items->all(),
                    'pagination' => [
                        'current_page' => $currentPage,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $total,
                        'has_prev' => $currentPage > 1,
                        'has_next' => $currentPage < $lastPage,
                    ],
                    'dateRange' => [
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                        'formatted' => $start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y'),
                        // Label mengikuti Naming_Source dari tabel cutoff (sama dgn Team
                        // Calendar). Fallback ke bulan start bila label tak tersedia.
                        'period_label' => 'Cutoff '.($cutoffLabel ?: $start->translatedFormat('M Y')),
                        // Anchor bulan = bulan START cutoff (konvensi getCutoffRangeForMonth).
                        'cutoff_year' => (int) $start->format('Y'),
                        'cutoff_month' => (int) $start->format('n'),
                    ],
                ];
            });
        });
    }

    /**
     * Agregasi jumlah hari per-klasifikasi dari dailyLog satu anggota.
     * 9 kategori sesuai kebutuhan kartu heatmap: On-time, Telat, Pulang Cepat,
     * Sedang Bekerja, Cuti, Sakit, Alpha, No In (no-checkin), No Out (no-checkout).
     * Status 'leave' (OFF), 'no-data', 'not-started', 'permission' (Izin) tidak dihitung.
     */
    protected function summarizeDailyLogCategories(array $dailyLog): array
    {
        $counts = [
            'on-time' => 0,
            'late' => 0,
            'early-out' => 0,
            'working' => 0,
            'cuti' => 0,
            'sakit' => 0,
            'absent' => 0,
            'no-checkin' => 0,
            'no-checkout' => 0,
        ];

        foreach ($dailyLog as $d) {
            $status = (string) ($d['status'] ?? '');
            switch ($status) {
                case 'present':
                    $counts['on-time']++;
                    break;
                case 'late':
                    $counts['late']++;
                    break;
                case 'early-out':
                    $counts['early-out']++;
                    break;
                case 'late-early-out':
                    // Gabungan: dihitung di dua kategori.
                    $counts['late']++;
                    $counts['early-out']++;
                    break;
                case 'working':
                    $counts['working']++;
                    break;
                case 'cuti':
                    $counts['cuti']++;
                    break;
                case 'sakit':
                case 'sick':
                    $counts['sakit']++;
                    break;
                case 'absent':
                    $counts['absent']++;
                    break;
                case 'no-checkin':
                    $counts['no-checkin']++;
                    break;
                case 'no-checkout':
                    $counts['no-checkout']++;
                    break;
            }
        }

        return $counts;
    }

    public function getPaginatedAttendanceIssues(
        string $leaderKode,
        string $date,
        int $page = 1,
        int $perPage = 10,
    ): array {
        return $this->profileEndpoint('leader.attendance.issues', function () use (
            $leaderKode,
            $date,
            $page,
            $perPage,
        ) {
            $teamMembers = $this->loadTeamMembers($leaderKode);
            $targetDate = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->startOfDay();

            if ($teamMembers->isEmpty()) {
                return [
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => max(1, min(50, $perPage)),
                        'total' => 0,
                        'has_prev' => false,
                        'has_next' => false,
                    ],
                    'meta' => [
                        'date' => $targetDate->toDateString(),
                        'day_label' => $targetDate->translatedFormat('D, d M Y'),
                        'issue_count' => 0,
                    ],
                ];
            }

            $context = $this->loadAttendanceContext($teamMembers, $targetDate, $targetDate, false);
            $rowsByCode = $context['rowsByCode'];
            $approvedTypeMap = $context['approvedTypeMap'] ?? [];
            $now = Carbon::now('Asia/Jakarta');
            $dateKey = $targetDate->toDateString();

            $items = $teamMembers
                ->map(function ($member) use ($rowsByCode, $approvedTypeMap, $targetDate, $now, $dateKey) {
                    $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                    if ($kode === '') {
                        return null;
                    }

                    $memberRows = collect($rowsByCode->get($kode, []))->keyBy('date');
                    $row = $memberRows->get($dateKey);
                    $approvedType = $approvedTypeMap[$kode.'|'.$dateKey] ?? null;
                    $classification = $this->classifyAttendanceRow(
                        $row,
                        $targetDate->copy(),
                        $targetDate->isSameDay($now) ? $now : $targetDate->copy()->endOfDay(),
                    );

                    if ($approvedType === 'CUTI') {
                        $classification = ['status' => 'cuti', 'label' => 'Cuti', 'tone' => 'info'];
                    } elseif ($approvedType === 'SAKIT') {
                        $classification = ['status' => 'sakit', 'label' => 'Sakit', 'tone' => 'info'];
                    } elseif ($approvedType === 'IZIN') {
                        $classification = ['status' => 'permission', 'label' => 'Izin', 'tone' => 'info'];
                    } elseif (str_starts_with(strtoupper((string) ($row['status_hari'] ?? 'WORK')), 'OFF')) {
                        $classification = ['status' => 'leave', 'label' => 'OFF', 'tone' => 'default'];
                    }

                    $issues = $this->buildAttendanceIssueTags($classification, $row);
                    if (empty($issues)) {
                        return null;
                    }

                    $shiftName = trim((string) ($row['shift_name'] ?? 'No Shift'));
                    $shiftTime =
                        ! empty($row['jam_masuk']) && ! empty($row['jam_keluar'])
                            ? $row['jam_masuk'].' - '.$row['jam_keluar']
                            : '-';

                    return [
                        'id' => $kode,
                        'name' => $member->Nama ?? 'Tanpa Nama',
                        'position' => $member->nama_jabatan ?? '-',
                        'shift' => [
                            'name' => $shiftName === '' ? 'No Shift' : $shiftName,
                            'time' => $shiftTime,
                        ],
                        'checkIn' => $row['check_in'] ?? null,
                        'checkOut' => $row['check_out'] ?? null,
                        'issues' => $issues,
                        'issueCount' => count($issues),
                    ];
                })
                ->filter()
                ->sort(function (array $a, array $b) {
                    $issueCompare = (int) ($b['issueCount'] ?? 0) <=> (int) ($a['issueCount'] ?? 0);
                    if ($issueCompare !== 0) {
                        return $issueCompare;
                    }

                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                })
                ->values();

            $paginated = $this->paginateCollection($items, $page, max(1, min(50, $perPage)));

            return [
                'data' => $paginated['data'],
                'pagination' => $paginated['pagination'],
                'meta' => [
                    'date' => $dateKey,
                    'day_label' => $targetDate->translatedFormat('D, d M Y'),
                    'issue_count' => (int) $items->count(),
                ],
            ];
        });
    }

    public function getPaginatedAttendanceStatusRows(
        string $leaderKode,
        int $page = 1,
        int $perPage = 10,
        array $filters = [],
    ): array {
        return $this->profileEndpoint('leader.attendance.statuses', function () use (
            $leaderKode,
            $page,
            $perPage,
            $filters,
        ) {
            $filter = strtolower(trim((string) ($filters['filter'] ?? 'all')));
            $search = strtolower(trim((string) ($filters['search'] ?? '')));
            $today = Carbon::now('Asia/Jakarta')->startOfDay();

            $teamMembers = $this->loadTeamMembers($leaderKode);
            if ($teamMembers->isEmpty()) {
                return [
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => max(1, min(50, $perPage)),
                        'total' => 0,
                        'has_prev' => false,
                        'has_next' => false,
                    ],
                    'meta' => [
                        'date' => $today->toDateString(),
                        'filter' => $filter === '' ? 'all' : $filter,
                        'filters' => [
                            'all' => 0,
                            'present' => 0,
                            'no-checkin' => 0,
                            'pending-checkin' => 0,
                            'working' => 0,
                            'no-checkout' => 0,
                            'late' => 0,
                            'early-out' => 0,
                            'leave' => 0,
                            'no-data' => 0,
                        ],
                    ],
                ];
            }

            $attendanceByCode = $this->buildLightAttendanceSummaries($teamMembers, $today);

            $rows = $teamMembers
                ->map(function ($member) use ($attendanceByCode) {
                    $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                    $attendance = $attendanceByCode[$kode] ?? $this->defaultAttendanceSummary();

                    return [
                        'id' => $kode,
                        'name' => $member->Nama ?? 'Tanpa Nama',
                        'position' => $member->nama_jabatan ?? '-',
                        'department' => $member->nama_divisi ?? '-',
                        'shift' => [
                            'name' => $attendance['shift_name'] ?? 'No Shift',
                            'time' => $attendance['shift_time'] ?? '-',
                        ],
                        'attendance' => [
                            'todayStatus' => $attendance['todayStatus'] ?? 'no-data',
                            'todayLabel' => $attendance['todayLabel'] ?? 'Belum Ada Data',
                            'checkIn' => $attendance['checkIn'] ?? null,
                            'checkOut' => $attendance['checkOut'] ?? null,
                            'hasCheckIn' => (bool) ($attendance['hasCheckIn'] ?? false),
                            'hasCheckOut' => (bool) ($attendance['hasCheckOut'] ?? false),
                            'noCheckinCount' => (int) ($attendance['noCheckinCount'] ?? 0),
                            'lateCount' => (int) ($attendance['lateCount'] ?? 0),
                            'lateMinutes' => (int) ($attendance['lateMinutes'] ?? 0),
                            'earlyOutCount' => (int) ($attendance['earlyOutCount'] ?? 0),
                            'noCheckoutCount' => (int) ($attendance['noCheckoutCount'] ?? 0),
                            'issueCount' => (int) ($attendance['issueCount'] ?? 0),
                        ],
                    ];
                })
                ->values();

            $filterCounts = [
                'all' => (int) $rows->count(),
                'present' => 0,
                'no-checkin' => 0,
                'pending-checkin' => 0,
                'working' => 0,
                'no-checkout' => 0,
                'late' => 0,
                'early-out' => 0,
                'leave' => 0,
                'no-data' => 0,
            ];

            $isPresentBucket = function (array $row): bool {
                $attendance = (array) ($row['attendance'] ?? []);
                $status = strtolower((string) ($attendance['todayStatus'] ?? ''));
                $hasCheckIn = (bool) ($attendance['hasCheckIn'] ?? false);
                $hasCheckOut = (bool) ($attendance['hasCheckOut'] ?? false);

                if (
                    in_array(
                        $status,
                        ['leave', 'permission', 'sick', 'cuti', 'pending-checkin', 'no-checkin', 'no-data'],
                        true,
                    )
                ) {
                    return false;
                }

                return $hasCheckIn ||
                    $hasCheckOut ||
                    in_array($status, ['present', 'working', 'late', 'no-checkout'], true);
            };

            foreach ($rows as $row) {
                $status = strtolower((string) data_get($row, 'attendance.todayStatus', 'no-data'));
                if ($isPresentBucket($row)) {
                    $filterCounts['present']++;
                }

                if ($status === 'no-checkin') {
                    $filterCounts['no-checkin']++;
                }
                if ($status === 'pending-checkin') {
                    $filterCounts['pending-checkin']++;
                }
                if ($status === 'working') {
                    $filterCounts['working']++;
                }
                if ($status === 'no-checkout') {
                    $filterCounts['no-checkout']++;
                }
                if ($status === 'no-data') {
                    $filterCounts['no-data']++;
                }

                if (in_array($status, ['leave', 'permission', 'sick', 'cuti'], true)) {
                    $filterCounts['leave']++;
                }

                if ((int) data_get($row, 'attendance.lateCount', 0) > 0) {
                    $filterCounts['late']++;
                }

                if ((int) data_get($row, 'attendance.earlyOutCount', 0) > 0) {
                    $filterCounts['early-out']++;
                }
            }

            if ($search !== '') {
                $rows = $rows
                    ->filter(function ($row) use ($search) {
                        $name = strtolower((string) ($row['name'] ?? ''));
                        $position = strtolower((string) ($row['position'] ?? ''));
                        $department = strtolower((string) ($row['department'] ?? ''));

                        return str_contains($name, $search) ||
                            str_contains($position, $search) ||
                            str_contains($department, $search);
                    })
                    ->values();
            }

            if ($filter !== 'all') {
                $rows = $rows
                    ->filter(function ($row) use ($filter) {
                        $status = strtolower((string) data_get($row, 'attendance.todayStatus', 'no-data'));

                        if ($filter === 'present') {
                            $attendance = (array) ($row['attendance'] ?? []);
                            $hasCheckIn = (bool) ($attendance['hasCheckIn'] ?? false);
                            $hasCheckOut = (bool) ($attendance['hasCheckOut'] ?? false);

                            if (
                                in_array(
                                    $status,
                                    ['leave', 'permission', 'sick', 'cuti', 'pending-checkin', 'no-checkin', 'no-data'],
                                    true,
                                )
                            ) {
                                return false;
                            }

                            return $hasCheckIn ||
                                $hasCheckOut ||
                                in_array($status, ['present', 'working', 'late', 'no-checkout'], true);
                        }

                        if ($filter === 'late') {
                            return (int) data_get($row, 'attendance.lateCount', 0) > 0;
                        }

                        if ($filter === 'early-out') {
                            return (int) data_get($row, 'attendance.earlyOutCount', 0) > 0;
                        }

                        if ($filter === 'leave') {
                            return in_array($status, ['leave', 'permission', 'sick', 'cuti'], true);
                        }

                        return $status === $filter;
                    })
                    ->values();
            }

            $rows = $rows
                ->sort(function (array $a, array $b) {
                    $issueCompare =
                        (int) data_get($b, 'attendance.issueCount', 0) <=>
                        (int) data_get($a, 'attendance.issueCount', 0);
                    if ($issueCompare !== 0) {
                        return $issueCompare;
                    }

                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                })
                ->values();

            $paginated = $this->paginateCollection($rows, $page, max(1, min(50, $perPage)));

            return [
                'data' => $paginated['data'],
                'pagination' => $paginated['pagination'],
                'meta' => [
                    'date' => $today->toDateString(),
                    'filter' => $filter === '' ? 'all' : $filter,
                    'filters' => $filterCounts,
                ],
            ];
        });
    }

    public function getLeaderCalendarMonthSummary(string $leaderKode, ?int $year = null, ?int $month = null): array
    {
        return $this->profileEndpoint('leader.calendar.month', function () use ($leaderKode, $year, $month) {
            $teamMembers = $this->loadTeamMembers($leaderKode);
            $currentTime = Carbon::now('Asia/Jakarta');
            $today = $currentTime->copy()->startOfDay();
            $safeYear = is_int($year) && $year >= 2000 && $year <= 2100 ? $year : null;
            $safeMonth = is_int($month) && $month >= 1 && $month <= 12 ? $month : null;

            $cutoffRange =
                $safeYear !== null && $safeMonth !== null
                    ? $this->uDashReadModelService->getCutoffRangeForMonth($safeYear, $safeMonth)
                    : $this->uDashReadModelService->getCutoffRange($today);

            $startDate = $cutoffRange['start_date']->copy()->startOfDay();
            $endDate = $cutoffRange['end_date']->copy()->startOfDay();

            if ($teamMembers->isEmpty()) {
                return [
                    'data' => [
                        'days' => [],
                    ],
                    'stats' => [
                        'present_count' => 0,
                        'late_count' => 0,
                        'issue_day_count' => 0,
                        'izin_cuti_count' => 0,
                        'filter_counts' => [
                            'all' => 0,
                            'problem' => 0,
                            'late' => 0,
                            'no-in-out' => 0,
                            'izin-cuti' => 0,
                        ],
                    ],
                    'meta' => [
                        'cutoff_start' => $startDate->toDateString(),
                        'cutoff_end' => $endDate->toDateString(),
                        'cutoff_label' => $cutoffRange['label'] ?? '-',
                        'year' => (int) $startDate->format('Y'),
                        'month' => (int) $startDate->format('m'),
                        'total_members' => 0,
                    ],
                ];
            }

            $context = $this->loadAttendanceContext($teamMembers, $startDate, $endDate, false);
            $rowsByCode = $context['rowsByCode'];
            $approvedTypeMap = $context['approvedTypeMap'] ?? [];
            $rowsByCodeDate = $rowsByCode->map(fn ($rows) => collect($rows)->keyBy('date'));
            $teamMemberCount = (int) $teamMembers->count();
            $days = [];

            $filterCounts = [
                'all' => 0,
                'problem' => 0,
                'late' => 0,
                'no-in-out' => 0,
                'izin-cuti' => 0,
            ];
            $presentCount = 0;
            $lateCount = 0;
            $issueDayCount = 0;
            $izinCutiCount = 0;

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateKey = $cursor->toDateString();
                $statusCounts = [];
                $teamWorkingCount = 0;
                $issueCount = 0;

                foreach ($teamMembers as $member) {
                    $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                    if ($kode === '') {
                        continue;
                    }

                    $memberRows = $rowsByCodeDate->get($kode, collect());
                    $row = $memberRows->get($dateKey);
                    $approvedType = $approvedTypeMap[$kode.'|'.$dateKey] ?? null;
                    $classification = $this->classifyAttendanceRow(
                        $row,
                        $cursor->copy(),
                        $this->resolveAttendanceEvaluationTime($cursor->copy(), $currentTime),
                    );

                    if ($approvedType === 'CUTI') {
                        $classification = ['status' => 'cuti', 'label' => 'Cuti', 'tone' => 'info'];
                    } elseif ($approvedType === 'SAKIT') {
                        $classification = ['status' => 'sick', 'label' => 'Sakit', 'tone' => 'info'];
                    } elseif ($approvedType === 'IZIN') {
                        $classification = ['status' => 'permission', 'label' => 'Izin', 'tone' => 'info'];
                    }

                    $shiftName = strtoupper(trim((string) ($row['shift_name'] ?? 'NO SHIFT')));
                    $statusHari = strtoupper(trim((string) ($row['status_hari'] ?? 'WORK')));
                    $isWorkingShift =
                        ! str_starts_with($statusHari, 'OFF') && ! in_array($shiftName, ['OFF', 'NO SHIFT'], true);
                    if ($isWorkingShift) {
                        $teamWorkingCount++;
                    }

                    $statusKey = strtolower((string) ($classification['status'] ?? 'no-data'));
                    if ($statusKey === '') {
                        $statusKey = 'no-data';
                    }
                    $statusCounts[$statusKey] = (int) ($statusCounts[$statusKey] ?? 0) + 1;

                    $issues = $this->buildAttendanceIssueTags($classification, $row);
                    if (! empty($issues)) {
                        $issueCount++;
                    }
                }

                $hasProblem = $issueCount > 0;
                $hasLate = (int) ($statusCounts['late'] ?? 0) > 0;
                $hasNoInOut = (int) ($statusCounts['no-checkin'] ?? 0) + (int) ($statusCounts['no-checkout'] ?? 0) > 0;
                $hasIzinCuti =
                    (int) ($statusCounts['permission'] ?? 0) +
                        (int) ($statusCounts['sick'] ?? 0) +
                        (int) ($statusCounts['cuti'] ?? 0) >
                    0;

                $primary = $this->resolveTeamCalendarPrimaryStatus($statusCounts, $issueCount, $teamWorkingCount);

                $days[] = [
                    'date' => $dateKey,
                    'day_name' => $cursor->translatedFormat('l'),
                    'day_number' => (int) $cursor->format('d'),
                    'is_today' => $cursor->isSameDay($today),
                    'primary_status_key' => $primary['key'],
                    'primary_status_label' => $primary['label'],
                    'primary_status_tone' => $primary['tone'],
                    'team_working_count' => $teamWorkingCount,
                    'issue_count' => $issueCount,
                    'has_problem' => $hasProblem,
                    'has_late' => $hasLate,
                    'has_no_in_out' => $hasNoInOut,
                    'has_izin_cuti' => $hasIzinCuti,
                ];

                $filterCounts['all']++;
                if ($hasProblem) {
                    $filterCounts['problem']++;
                    $issueDayCount++;
                }
                if ($hasLate) {
                    $filterCounts['late']++;
                    $lateCount++;
                }
                if ($hasNoInOut) {
                    $filterCounts['no-in-out']++;
                }
                if ($hasIzinCuti) {
                    $filterCounts['izin-cuti']++;
                    $izinCutiCount++;
                }
                if ($primary['key'] === 'present') {
                    $presentCount++;
                }

                $cursor->addDay();
            }

            return [
                'data' => [
                    'days' => $days,
                ],
                'stats' => [
                    'present_count' => $presentCount,
                    'late_count' => $lateCount,
                    'issue_day_count' => $issueDayCount,
                    'izin_cuti_count' => $izinCutiCount,
                    'filter_counts' => $filterCounts,
                ],
                'meta' => [
                    'cutoff_start' => $startDate->toDateString(),
                    'cutoff_end' => $endDate->toDateString(),
                    'cutoff_label' => $cutoffRange['label'] ?? '-',
                    'year' => (int) $startDate->format('Y'),
                    'month' => (int) $startDate->format('m'),
                    'total_members' => $teamMemberCount,
                ],
            ];
        });
    }

    public function getLeaderCalendarDayDetail(
        string $leaderKode,
        string $date,
        int $page = 1,
        int $perPage = 10,
        array $filters = [],
    ): array {
        return $this->profileEndpoint('leader.calendar.day-detail', function () use (
            $leaderKode,
            $date,
            $page,
            $perPage,
            $filters,
        ) {
            $targetDate = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->startOfDay();
            $filter = strtolower(trim((string) ($filters['filter'] ?? 'all')));
            $search = strtolower(trim((string) ($filters['search'] ?? '')));
            $currentTime = Carbon::now('Asia/Jakarta');
            $today = $currentTime->copy()->startOfDay();
            $teamMembers = $this->loadTeamMembers($leaderKode);

            if ($teamMembers->isEmpty()) {
                return [
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => max(1, min(50, $perPage)),
                        'total' => 0,
                        'has_prev' => false,
                        'has_next' => false,
                    ],
                    'meta' => [
                        'date' => $targetDate->toDateString(),
                        'day_label' => $targetDate->translatedFormat('D, d M Y'),
                        'team_working_count' => 0,
                        'issue_count' => 0,
                        'filter' => $filter === '' ? 'all' : $filter,
                    ],
                ];
            }

            $context = $this->loadAttendanceContext($teamMembers, $targetDate, $targetDate, false);
            $rowsByCode = $context['rowsByCode'];
            $approvedTypeMap = $context['approvedTypeMap'] ?? [];
            $rowsByCodeDate = $rowsByCode->map(fn ($rows) => collect($rows)->keyBy('date'));
            $dateKey = $targetDate->toDateString();

            $teamWorkingCount = 0;
            $fullIssueCount = 0;

            $rows = $teamMembers
                ->map(function ($member) use (
                    $rowsByCodeDate,
                    $approvedTypeMap,
                    $targetDate,
                    $currentTime,
                    $dateKey,
                    &$teamWorkingCount,
                    &$fullIssueCount,
                ) {
                    $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                    if ($kode === '') {
                        return null;
                    }

                    $memberRows = $rowsByCodeDate->get($kode, collect());
                    $row = $memberRows->get($dateKey);
                    $approvedType = $approvedTypeMap[$kode.'|'.$dateKey] ?? null;
                    $classification = $this->classifyAttendanceRow(
                        $row,
                        $targetDate->copy(),
                        $this->resolveAttendanceEvaluationTime($targetDate->copy(), $currentTime),
                    );

                    if ($approvedType === 'CUTI') {
                        $classification = ['status' => 'cuti', 'label' => 'Cuti', 'tone' => 'info'];
                    } elseif ($approvedType === 'SAKIT') {
                        $classification = ['status' => 'sick', 'label' => 'Sakit', 'tone' => 'info'];
                    } elseif ($approvedType === 'IZIN') {
                        $classification = ['status' => 'permission', 'label' => 'Izin', 'tone' => 'info'];
                    }

                    $shiftName = trim((string) ($row['shift_name'] ?? 'No Shift'));
                    $statusHari = strtoupper(trim((string) ($row['status_hari'] ?? 'WORK')));
                    $shiftNameUpper = strtoupper(trim((string) ($row['shift_name'] ?? 'NO SHIFT')));
                    $isWorkingShift =
                        ! str_starts_with($statusHari, 'OFF') && ! in_array($shiftNameUpper, ['OFF', 'NO SHIFT'], true);
                    if ($isWorkingShift) {
                        $teamWorkingCount++;
                    }

                    $issues = $this->buildAttendanceIssueTags($classification, $row);
                    $issueCount = count($issues);
                    if ($issueCount > 0) {
                        $fullIssueCount++;
                    }

                    return [
                        'id' => $kode,
                        'name' => $member->Nama ?? 'Tanpa Nama',
                        'position' => $member->nama_jabatan ?? '-',
                        'department' => $member->nama_divisi ?? '-',
                        'shift' => [
                            'name' => $shiftName === '' ? 'No Shift' : $shiftName,
                            'time' => ! empty($row['jam_masuk']) && ! empty($row['jam_keluar'])
                                    ? $row['jam_masuk'].' - '.$row['jam_keluar']
                                    : '-',
                        ],
                        'attendance' => [
                            'status' => strtolower((string) ($classification['status'] ?? 'no-data')),
                            'label' => (string) ($classification['label'] ?? 'Belum Ada Data'),
                            'tone' => strtolower((string) ($classification['tone'] ?? 'default')),
                            'check_in' => $row['check_in'] ?? null,
                            'check_out' => $row['check_out'] ?? null,
                            'late_minutes' => (int) ($row['late_minutes'] ?? 0),
                        ],
                        'issues' => $issues,
                        'issue_count' => $issueCount,
                    ];
                })
                ->filter()
                ->values();

            if ($search !== '') {
                $rows = $rows
                    ->filter(function ($item) use ($search) {
                        $name = strtolower((string) ($item['name'] ?? ''));
                        $position = strtolower((string) ($item['position'] ?? ''));
                        $department = strtolower((string) ($item['department'] ?? ''));

                        return str_contains($name, $search) ||
                            str_contains($position, $search) ||
                            str_contains($department, $search);
                    })
                    ->values();
            }

            if ($filter !== 'all') {
                $rows = $rows
                    ->filter(function ($item) use ($filter) {
                        $status = strtolower((string) data_get($item, 'attendance.status', 'no-data'));
                        $issues = (int) data_get($item, 'issue_count', 0);

                        return match ($filter) {
                            'problem' => $issues > 0,
                            'late' => $status === 'late',
                            'no-in-out' => in_array($status, ['no-checkin', 'no-checkout'], true),
                            'izin-cuti' => in_array($status, ['permission', 'sick', 'cuti'], true),
                            default => true,
                        };
                    })
                    ->values();
            }

            $rows = $rows
                ->sort(function (array $a, array $b) {
                    $issueCompare = (int) ($b['issue_count'] ?? 0) <=> (int) ($a['issue_count'] ?? 0);
                    if ($issueCompare !== 0) {
                        return $issueCompare;
                    }

                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                })
                ->values();

            $paginated = $this->paginateCollection($rows, $page, max(1, min(50, $perPage)));

            return [
                'data' => $paginated['data'],
                'pagination' => $paginated['pagination'],
                'meta' => [
                    'date' => $dateKey,
                    'day_label' => $targetDate->translatedFormat('D, d M Y'),
                    'team_working_count' => $teamWorkingCount,
                    'issue_count' => $fullIssueCount,
                    'filter' => $filter === '' ? 'all' : $filter,
                ],
            ];
        });
    }

    /**
     * Daftar kategori HCIS yang valid untuk drawer breakdown.
     * Dipakai bersama oleh period-breakdown & member-category-dates.
     */
    public const HCIS_BREAKDOWN_CATEGORIES = ['absent', 'no-in-out', 'late-early', 'issue', 'izin-cuti'];

    /**
     * Tentukan apakah satu baris (1 anggota pada 1 tanggal) termasuk kategori HCIS.
     * Definisi (dikonfirmasi user):
     * - absent     = alpha murni (status 'absent')
     * - no-in-out  = no-checkin + no-checkout
     * - late-early = terlambat (late) + pulang cepat (early_minutes > 0)
     * - issue      = gabungan semua hari bermasalah (absent+no-checkin+no-checkout+late+early-out)
     * - izin-cuti  = permission + sick + cuti (dari approval map)
     */
    protected function matchesHcisCategory(
        string $category,
        array $classification,
        ?array $row,
        bool $isWorking,
        ?string $approvedType = null,
    ): bool {
        $status = strtolower((string) ($classification['status'] ?? ''));
        $isEarlyOut = (int) ($row['early_minutes'] ?? 0) > 0;
        $isLate = $this->barisTerlambat($row, $status);

        return match ($category) {
            'absent' => $isWorking && $status === 'absent',
            'no-in-out' => in_array($status, ['no-checkin', 'no-checkout'], true),
            'late-early' => $isLate || ($isWorking && $isEarlyOut),
            'issue' => ($isWorking && in_array($status, ['absent', 'no-checkin', 'no-checkout'], true)) ||
                $isLate ||
                ($isWorking && $isEarlyOut),
            'izin-cuti' => in_array($approvedType, ['IZIN', 'SAKIT', 'CUTI'], true) ||
                in_array($status, ['permission', 'sick', 'cuti'], true),
            default => false,
        };
    }

    /**
     * Bangun klasifikasi final untuk 1 baris (memperhitungkan approval map).
     */
    protected function resolveRowClassification(
        ?array $row,
        Carbon $date,
        Carbon $currentTime,
        ?string $approvedType,
    ): array {
        $classification = $this->classifyAttendanceRow(
            $row,
            $date->copy(),
            $this->resolveAttendanceEvaluationTime($date->copy(), $currentTime),
        );

        if ($approvedType === 'CUTI') {
            return ['status' => 'cuti', 'label' => 'Cuti', 'tone' => 'info'];
        }
        if ($approvedType === 'SAKIT') {
            return ['status' => 'sick', 'label' => 'Sakit', 'tone' => 'info'];
        }
        if ($approvedType === 'IZIN') {
            return ['status' => 'permission', 'label' => 'Izin', 'tone' => 'info'];
        }

        return $classification;
    }

    protected function rowIsWorkingShift(?array $row): bool
    {
        $shiftName = strtoupper(trim((string) ($row['shift_name'] ?? 'NO SHIFT')));
        $statusHari = strtoupper(trim((string) ($row['status_hari'] ?? 'WORK')));

        return ! str_starts_with($statusHari, 'OFF') && ! in_array($shiftName, ['OFF', 'NO SHIFT'], true);
    }

    /**
     * DRAWER A — Breakdown 1 kategori HCIS sepanjang periode cutoff berjalan.
     * Mengembalikan list ANGGOTA (bukan per-tanggal): tiap item punya day_count + dates[].
     */
    public function getHcisPeriodBreakdown(
        string $leaderKode,
        string $category,
        int $page = 1,
        int $perPage = 10,
        array $filters = [],
        string $scope = 'period',
    ): array {
        $category = in_array($category, self::HCIS_BREAKDOWN_CATEGORIES, true) ? $category : 'issue';
        $scope = $scope === 'today' ? 'today' : 'period';

        return $this->profileEndpoint('leader.hcis.period-breakdown', function () use (
            $leaderKode,
            $category,
            $page,
            $perPage,
            $filters,
            $scope,
        ) {
            $search = strtolower(trim((string) ($filters['search'] ?? '')));
            $currentTime = Carbon::now('Asia/Jakarta');
            $today = $currentTime->copy()->startOfDay();
            $teamMembers = $this->loadTeamMembers($leaderKode);

            if ($scope === 'today') {
                $startDate = $today->copy();
                $endDate = $today->copy();
                $cutoffLabel = $today->translatedFormat('l, d F Y');
            } else {
                $cutoffRange = $this->uDashReadModelService->getCutoffRange($today);
                $startDate = $cutoffRange['start_date']->copy()->startOfDay();
                $endDate = $cutoffRange['end_date']->copy()->startOfDay();
                // Jangan klasifikasi hari di masa depan (belum terjadi).
                if ($endDate->greaterThan($today)) {
                    $endDate = $today->copy();
                }
                $cutoffLabel = $cutoffRange['label'] ?? '-';
            }

            $emptyMeta = [
                'category' => $category,
                'scope' => $scope,
                'cutoff_start' => $startDate->toDateString(),
                'cutoff_end' => $endDate->toDateString(),
                'cutoff_label' => $cutoffLabel,
            ];

            if ($teamMembers->isEmpty()) {
                return [
                    'data' => [],
                    'pagination' => $this->emptyPagination($perPage),
                    'meta' => $emptyMeta + ['total_members' => 0, 'total_days' => 0],
                ];
            }

            $context = $this->loadAttendanceContext($teamMembers, $startDate, $endDate, false);
            $rowsByCode = $context['rowsByCode'];
            $approvedTypeMap = $context['approvedTypeMap'] ?? [];

            $totalDays = 0;
            $rows = $teamMembers
                ->map(function ($member) use (
                    $rowsByCode,
                    $approvedTypeMap,
                    $currentTime,
                    $today,
                    $category,
                    &$totalDays,
                ) {
                    $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                    if ($kode === '') {
                        return null;
                    }

                    $memberRows = collect($rowsByCode->get($kode, []))->keyBy('date');
                    $dates = [];

                    foreach ($memberRows as $dateKey => $row) {
                        $rowDate = Carbon::parse($dateKey, 'Asia/Jakarta')->startOfDay();
                        if ($rowDate->greaterThan($today)) {
                            continue;
                        }
                        $approvedType = $approvedTypeMap[$kode.'|'.$dateKey] ?? null;
                        $classification = $this->resolveRowClassification($row, $rowDate, $currentTime, $approvedType);
                        $isWorking = $this->rowIsWorkingShift($row);

                        if ($this->matchesHcisCategory($category, $classification, $row, $isWorking, $approvedType)) {
                            $dates[] = $dateKey;
                        }
                    }

                    if (empty($dates)) {
                        return null;
                    }

                    sort($dates);
                    $totalDays += count($dates);

                    return [
                        'id' => $kode,
                        'name' => $member->Nama ?? 'Tanpa Nama',
                        'position' => $member->nama_jabatan ?? '-',
                        'department' => $member->nama_divisi ?? '-',
                        'day_count' => count($dates),
                        'dates' => $dates,
                    ];
                })
                ->filter()
                ->values();

            if ($search !== '') {
                $rows = $rows
                    ->filter(function ($item) use ($search) {
                        return str_contains(strtolower((string) ($item['name'] ?? '')), $search) ||
                            str_contains(strtolower((string) ($item['position'] ?? '')), $search) ||
                            str_contains(strtolower((string) ($item['department'] ?? '')), $search);
                    })
                    ->values();
            }

            $rows = $rows
                ->sort(function (array $a, array $b) {
                    $byCount = (int) ($b['day_count'] ?? 0) <=> (int) ($a['day_count'] ?? 0);
                    if ($byCount !== 0) {
                        return $byCount;
                    }

                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                })
                ->values();

            $paginated = $this->paginateCollection($rows, $page, max(1, min(50, $perPage)));

            return [
                'data' => $paginated['data'],
                'pagination' => $paginated['pagination'],
                'meta' => $emptyMeta + [
                    'total_members' => $rows->count(),
                    'total_days' => $totalDays,
                ],
            ];
        });
    }

    /**
     * DRAWER B — Breakdown status HARI INI (klik kartu "Status Hari Ini").
     * Hanya count per stat; list anggota di-lazy lewat member-category-dates (scope=today).
     */
    public function getHcisTodayBreakdown(string $leaderKode): array
    {
        return $this->profileEndpoint('leader.hcis.today-breakdown', function () use ($leaderKode) {
            $currentTime = Carbon::now('Asia/Jakarta');
            $today = $currentTime->copy()->startOfDay();
            $dateKey = $today->toDateString();
            $teamMembers = $this->loadTeamMembers($leaderKode);

            $stats = [
                'present' => 0, // on-time + late + early-out
                'on-time' => 0,
                'late' => 0,
                'early-out' => 0,
                'pending-checkin' => 0,
                'working' => 0,
                'absent' => 0,
                'no-in-out' => 0, // no-checkin + no-checkout
            ];
            $workingTotal = 0;

            if ($teamMembers->isEmpty()) {
                return [
                    'data' => ['stats' => $stats, 'working_total' => 0],
                    'meta' => ['date' => $dateKey, 'day_label' => $today->translatedFormat('l, d F Y')],
                ];
            }

            $context = $this->loadAttendanceContext($teamMembers, $today, $today, false);
            $rowsByCode = $context['rowsByCode'];
            $approvedTypeMap = $context['approvedTypeMap'] ?? [];

            foreach ($teamMembers as $member) {
                $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                if ($kode === '') {
                    continue;
                }

                $row = collect($rowsByCode->get($kode, []))->firstWhere('date', $dateKey);
                $approvedType = $approvedTypeMap[$kode.'|'.$dateKey] ?? null;
                $classification = $this->resolveRowClassification($row, $today, $currentTime, $approvedType);
                $status = strtolower((string) ($classification['status'] ?? ''));
                $isWorking = $this->rowIsWorkingShift($row);
                if ($isWorking) {
                    $workingTotal++;
                }

                $isLate = $this->barisTerlambat($row, $status);
                $isEarlyOut = $isWorking && (int) ($row['early_minutes'] ?? 0) > 0;
                $hasCheckIn = ! empty($row['check_in']);
                $hasCheckOut = ! empty($row['check_out']);

                // "Hadir" = benar-benar masuk (on-time/late/working/no-checkout/early-out).
                if (
                    in_array($status, ['present', 'working', 'late', 'no-checkout'], true) ||
                    $hasCheckIn ||
                    $hasCheckOut
                ) {
                    $stats['present']++;
                    if ($isLate) {
                        $stats['late']++;
                    } else {
                        $stats['on-time']++;
                    }
                    if ($isEarlyOut) {
                        $stats['early-out']++;
                    }
                }

                if ($status === 'pending-checkin') {
                    $stats['pending-checkin']++;
                }
                if ($status === 'working') {
                    $stats['working']++;
                }
                if ($isWorking && $status === 'absent') {
                    $stats['absent']++;
                }
                if (in_array($status, ['no-checkin', 'no-checkout'], true)) {
                    $stats['no-in-out']++;
                }
            }

            return [
                'data' => ['stats' => $stats, 'working_total' => $workingTotal],
                'meta' => ['date' => $dateKey, 'day_label' => $today->translatedFormat('l, d F Y')],
            ];
        });
    }

    /**
     * Status-key valid untuk breakdown anggota HARI INI (Drawer B accordion).
     */
    public const HCIS_TODAY_STATUS_KEYS = [
        'present',
        'on-time',
        'late',
        'early-out',
        'pending-checkin',
        'working',
        'absent',
        'no-in-out',
    ];

    /**
     * Tentukan apakah 1 anggota cocok status HARI INI. Selaras dengan count di
     * getHcisTodayBreakdown agar list & angka konsisten.
     */
    /**
     * Apakah baris ini terlambat — termasuk yang keterlambatannya dimaafkan.
     *
     * Rumus ini sebelumnya DISALIN di tiga tempat, dan ketiganya hanya melihat
     * late_minutes. Padahal SP menolkan angka itu untuk keterlambatan yang
     * masih dalam toleransi, sehingga atasan membaca "nol terlambat" pada hari
     * yang oleh anggotanya sendiri terlihat "terlambat 10 menit". Bukan angka
     * yang salah, melainkan dua orang membaca kenyataan yang berbeda.
     *
     * Disatukan di sini supaya tidak ada lagi salinan yang tertinggal saat
     * aturannya berubah. late_minutes tidak disentuh: yang bertambah hanya
     * pengakuan bahwa orangnya memang terlambat.
     */
    protected function barisTerlambat(?array $row, string $status): bool
    {
        if ((int) ($row['late_minutes'] ?? 0) > 0 || $status === 'late') {
            return true;
        }

        return (bool) ($row['keterlambatan']['terlambat_sebenarnya'] ?? false);
    }

    protected function matchesHcisTodayStatus(
        string $statusKey,
        array $classification,
        ?array $row,
        bool $isWorking,
    ): bool {
        $status = strtolower((string) ($classification['status'] ?? ''));
        $isLate = $this->barisTerlambat($row, $status);
        $isEarlyOut = $isWorking && (int) ($row['early_minutes'] ?? 0) > 0;
        $hasCheckIn = ! empty($row['check_in']);
        $hasCheckOut = ! empty($row['check_out']);
        $isPresent =
            in_array($status, ['present', 'working', 'late', 'no-checkout'], true) || $hasCheckIn || $hasCheckOut;

        return match ($statusKey) {
            'present' => $isPresent,
            'on-time' => $isPresent && ! $isLate,
            'late' => $isPresent && $isLate,
            'early-out' => $isPresent && $isEarlyOut,
            'pending-checkin' => $status === 'pending-checkin',
            'working' => $status === 'working',
            'absent' => $isWorking && $status === 'absent',
            'no-in-out' => in_array($status, ['no-checkin', 'no-checkout'], true),
            default => false,
        };
    }

    /**
     * ACCORDION Drawer B — Daftar ANGGOTA yang berstatus tertentu HARI INI.
     * Isi tiap item: nama, jabatan, shift, label status, check-in/out.
     */
    public function getHcisTodayMembers(string $leaderKode, string $statusKey): array
    {
        $statusKey = in_array($statusKey, self::HCIS_TODAY_STATUS_KEYS, true) ? $statusKey : 'present';

        return $this->profileEndpoint('leader.hcis.today-members', function () use ($leaderKode, $statusKey) {
            $currentTime = Carbon::now('Asia/Jakarta');
            $today = $currentTime->copy()->startOfDay();
            $dateKey = $today->toDateString();
            $teamMembers = $this->loadTeamMembers($leaderKode);

            if ($teamMembers->isEmpty()) {
                return ['data' => [], 'meta' => ['status' => $statusKey, 'date' => $dateKey]];
            }

            $context = $this->loadAttendanceContext($teamMembers, $today, $today, false);
            $rowsByCode = $context['rowsByCode'];
            $approvedTypeMap = $context['approvedTypeMap'] ?? [];

            $items = [];
            foreach ($teamMembers as $member) {
                $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
                if ($kode === '') {
                    continue;
                }

                $row = collect($rowsByCode->get($kode, []))->firstWhere('date', $dateKey);
                $approvedType = $approvedTypeMap[$kode.'|'.$dateKey] ?? null;
                $classification = $this->resolveRowClassification($row, $today, $currentTime, $approvedType);
                $isWorking = $this->rowIsWorkingShift($row);

                if (! $this->matchesHcisTodayStatus($statusKey, $classification, $row, $isWorking)) {
                    continue;
                }

                $items[] = [
                    'id' => $kode,
                    'name' => $member->Nama ?? 'Tanpa Nama',
                    'position' => $member->nama_jabatan ?? '-',
                    'department' => $member->nama_divisi ?? '-',
                    'status' => strtolower((string) ($classification['status'] ?? 'no-data')),
                    'label' => (string) ($classification['label'] ?? 'Belum Ada Data'),
                    'tone' => strtolower((string) ($classification['tone'] ?? 'default')),
                    'shift_name' => trim((string) ($row['shift_name'] ?? 'No Shift')),
                    'shift_time' => ! empty($row['jam_masuk']) && ! empty($row['jam_keluar'])
                            ? $row['jam_masuk'].' - '.$row['jam_keluar']
                            : '-',
                    'check_in' => $row['check_in'] ?? null,
                    'check_out' => $row['check_out'] ?? null,
                    'late_minutes' => (int) ($row['late_minutes'] ?? 0),
                    'early_minutes' => (int) ($row['early_minutes'] ?? 0),
                ];
            }

            usort($items, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

            return [
                'data' => $items,
                'meta' => ['status' => $statusKey, 'date' => $dateKey, 'total' => count($items)],
            ];
        });
    }

    /**
     * ACCORDION — Daftar tanggal 1 anggota yang cocok kategori (dipakai Drawer A & B).
     * scope: 'period' (cutoff berjalan) atau 'today'.
     */
    public function getHcisMemberCategoryDates(
        string $leaderKode,
        string $kodeKaryawan,
        string $category,
        string $scope = 'period',
    ): array {
        $category = in_array($category, self::HCIS_BREAKDOWN_CATEGORIES, true) ? $category : 'issue';
        $scope = $scope === 'today' ? 'today' : 'period';
        $kodeKaryawan = strtoupper(trim($kodeKaryawan));

        return $this->profileEndpoint('leader.hcis.member-category-dates', function () use (
            $leaderKode,
            $kodeKaryawan,
            $category,
            $scope,
        ) {
            $currentTime = Carbon::now('Asia/Jakarta');
            $today = $currentTime->copy()->startOfDay();
            $teamMembers = $this->loadTeamMembers($leaderKode);

            // Pastikan anggota memang bagian dari tim leader (otorisasi data).
            $member = $teamMembers->first(
                fn ($m) => strtoupper(trim((string) ($m->Kode_Karyawan ?? ''))) === $kodeKaryawan,
            );
            if (! $member) {
                return ['data' => [], 'meta' => ['category' => $category, 'scope' => $scope]];
            }

            if ($scope === 'today') {
                $startDate = $today->copy();
                $endDate = $today->copy();
            } else {
                $cutoffRange = $this->uDashReadModelService->getCutoffRange($today);
                $startDate = $cutoffRange['start_date']->copy()->startOfDay();
                $endDate = $cutoffRange['end_date']->copy()->startOfDay();
                if ($endDate->greaterThan($today)) {
                    $endDate = $today->copy();
                }
            }

            $single = collect([$member]);
            $context = $this->loadAttendanceContext($single, $startDate, $endDate, false);
            $rowsByCode = $context['rowsByCode'];
            $approvedTypeMap = $context['approvedTypeMap'] ?? [];
            $memberRows = collect($rowsByCode->get($kodeKaryawan, []))->keyBy('date');

            $items = [];
            foreach ($memberRows as $dateKey => $row) {
                $rowDate = Carbon::parse($dateKey, 'Asia/Jakarta')->startOfDay();
                if ($rowDate->greaterThan($today)) {
                    continue;
                }
                $approvedType = $approvedTypeMap[$kodeKaryawan.'|'.$dateKey] ?? null;
                $classification = $this->resolveRowClassification($row, $rowDate, $currentTime, $approvedType);
                $isWorking = $this->rowIsWorkingShift($row);

                if (! $this->matchesHcisCategory($category, $classification, $row, $isWorking, $approvedType)) {
                    continue;
                }

                $items[] = [
                    'date' => $dateKey,
                    'day_label' => $rowDate->translatedFormat('D, d M Y'),
                    'status' => strtolower((string) ($classification['status'] ?? 'no-data')),
                    'label' => (string) ($classification['label'] ?? 'Belum Ada Data'),
                    'tone' => strtolower((string) ($classification['tone'] ?? 'default')),
                    'shift_name' => trim((string) ($row['shift_name'] ?? 'No Shift')),
                    'shift_time' => ! empty($row['jam_masuk']) && ! empty($row['jam_keluar'])
                            ? $row['jam_masuk'].' - '.$row['jam_keluar']
                            : '-',
                    'check_in' => $row['check_in'] ?? null,
                    'check_out' => $row['check_out'] ?? null,
                    'late_minutes' => (int) ($row['late_minutes'] ?? 0),
                    'early_minutes' => (int) ($row['early_minutes'] ?? 0),
                ];
            }

            usort($items, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

            return [
                'data' => $items,
                'meta' => [
                    'category' => $category,
                    'scope' => $scope,
                    'kode' => $kodeKaryawan,
                    'name' => $member->Nama ?? 'Tanpa Nama',
                    'total_days' => count($items),
                ],
            ];
        });
    }

    protected function emptyPagination(int $perPage): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => max(1, min(50, $perPage)),
            'total' => 0,
            'has_prev' => false,
            'has_next' => false,
        ];
    }

    public function getPaginatedTrainingPending(string $leaderKode, int $page = 1, int $perPage = 6): array
    {
        return $this->profileEndpoint('leader.training', function () use ($leaderKode, $page, $perPage) {
            $teamMembers = $this->loadTeamMembers($leaderKode);
            $summaries = $this->homepageLmsService->getTeamSummaries($teamMembers->pluck('Kode_Karyawan')->all());

            $items = $teamMembers
                ->map(function ($member) use ($summaries) {
                    $kode = strtoupper(trim((string) $member->Kode_Karyawan));
                    $lms = $summaries[$kode] ?? $this->defaultLmsSummary();

                    return [
                        'id' => $kode,
                        'name' => $member->Nama ?? 'Tanpa Nama',
                        'initials' => $this->buildInitials($member->Nama ?? 'U'),
                        'lms' => [
                            'completed' => $lms['completed_courses'] ?? 0,
                            'total' => $lms['enrolled_courses'] ?? 0,
                            'progress' => $lms['progress'] ?? 0,
                            'averageScore' => $lms['average_score'] ?? null,
                            'learningGain' => $lms['learning_gain'] ?? null,
                            'trainings' => $lms['trainings'] ?? [],
                        ],
                    ];
                })
                ->filter(fn ($member) => (int) data_get($member, 'lms.progress', 0) < 100)
                ->sortBy(fn ($member) => (int) data_get($member, 'lms.progress', 0))
                ->values();

            return $this->paginateCollection($items, $page, $perPage);
        });
    }

    protected function resolveTeamCalendarPrimaryStatus(
        array $statusCounts,
        int $issueCount,
        int $teamWorkingCount,
    ): array {
        if ((int) ($statusCounts['not-started'] ?? 0) > 0) {
            return ['key' => 'not-started', 'label' => 'Belum Dimulai', 'tone' => 'secondary'];
        }

        if ((int) ($statusCounts['absent'] ?? 0) > 0) {
            return ['key' => 'absent', 'label' => 'Tidak Hadir', 'tone' => 'danger'];
        }

        if ((int) ($statusCounts['no-checkin'] ?? 0) > 0) {
            return ['key' => 'no-checkin', 'label' => 'No Check-In', 'tone' => 'danger'];
        }

        if ((int) ($statusCounts['no-checkout'] ?? 0) > 0) {
            return ['key' => 'no-checkout', 'label' => 'No Check-Out', 'tone' => 'warning'];
        }

        if ((int) ($statusCounts['late'] ?? 0) > 0) {
            return ['key' => 'late', 'label' => 'Terlambat', 'tone' => 'warning'];
        }

        if ((int) ($statusCounts['permission'] ?? 0) > 0) {
            return ['key' => 'permission', 'label' => 'Izin', 'tone' => 'info'];
        }

        if ((int) ($statusCounts['sick'] ?? 0) > 0) {
            return ['key' => 'sick', 'label' => 'Sakit', 'tone' => 'info'];
        }

        if ((int) ($statusCounts['cuti'] ?? 0) > 0) {
            return ['key' => 'cuti', 'label' => 'Cuti', 'tone' => 'info'];
        }

        if ((int) ($statusCounts['present'] ?? 0) > 0) {
            return ['key' => 'present', 'label' => 'Hadir', 'tone' => 'success'];
        }

        if ((int) ($statusCounts['working'] ?? 0) > 0) {
            return ['key' => 'working', 'label' => 'Sedang Bekerja', 'tone' => 'working'];
        }

        if ((int) ($statusCounts['pending-checkin'] ?? 0) > 0) {
            return ['key' => 'pending-checkin', 'label' => 'Belum Check-In', 'tone' => 'secondary'];
        }

        if ($teamWorkingCount <= 0 && $issueCount <= 0) {
            return ['key' => 'leave', 'label' => 'Off Day', 'tone' => 'default'];
        }

        return ['key' => 'no-data', 'label' => 'Belum Ada Data', 'tone' => 'default'];
    }

    public function getPaginatedLmsTeamMembers(
        string $leaderKode,
        int $year,
        string $focus = 'lms_not_started',
        int $page = 1,
        int $perPage = 10,
        string $search = '',
    ): array {
        $selectedYear = $this->normalizeLmsYear($year);
        $normalizedFocus = $this->normalizeLmsFocus($focus);
        $normalizedSearch = trim((string) $search);
        $cacheKey =
            strtoupper(trim($leaderKode)).
            '|'.
            $this->buildLmsDrawerCacheKey($selectedYear, $normalizedFocus, $page, $perPage, $normalizedSearch);

        if (isset($this->lmsTeamMemberCache[$cacheKey])) {
            return $this->lmsTeamMemberCache[$cacheKey];
        }

        $result = $this->profileEndpoint('leader.lms.drawer', function () use (
            $leaderKode,
            $selectedYear,
            $normalizedFocus,
            $page,
            $perPage,
            $normalizedSearch,
        ) {
            $teamMembers = $this->buildTeamMembersQuery($leaderKode, strtolower($normalizedSearch))->get();
            $memberCodes = $teamMembers
                ->pluck('Kode_Karyawan')
                ->map(fn ($kode) => strtoupper(trim((string) $kode)))
                ->filter()
                ->values()
                ->all();

            $summaries = $this->homepageLmsService->getTeamSummaries($memberCodes, false, $selectedYear);

            $items = $teamMembers
                ->map(function ($member) use ($summaries) {
                    $kode = strtoupper(trim((string) $member->Kode_Karyawan));
                    $lms = $summaries[$kode] ?? $this->defaultLmsSummary();

                    $avgProgress = (float) ($lms['progress'] ?? 0);
                    $assignedTotal = (int) ($lms['enrolled_courses'] ?? 0);
                    $avgLearningGain = $lms['learning_gain'] !== null ? (float) $lms['learning_gain'] : null;
                    $completedMinutesYear = (float) ($lms['completed_minutes_year'] ?? 0);
                    // Rata-rata durasi PER training yang dilalui — disamakan dengan personal dashboard.
                    $avgDurationHours = (float) ($lms['avg_duration_hours'] ?? 0);

                    return [
                        'id' => $kode,
                        'kode_karyawan' => $kode,
                        'name' => $member->Nama ?? 'Tanpa Nama',
                        'initials' => $this->buildInitials($member->Nama ?? 'U'),
                        'position' => $member->nama_jabatan ?? '-',
                        'department' => $member->nama_divisi ?? '-',
                        'avg_progress' => round($avgProgress, 1),
                        'assigned_total' => $assignedTotal,
                        'avg_learning_gain' => $avgLearningGain !== null ? round($avgLearningGain, 1) : null,
                        'completed_minutes_year' => round($completedMinutesYear, 2),
                        'completed_hours_year' => round($completedMinutesYear / 60, 2),
                        'avg_duration_hours' => round($avgDurationHours, 2),
                    ];
                })
                ->values();

            $sorted = $this->sortLmsDrawerItems($items, $normalizedFocus)->values();
            $paginated = $this->paginateCollection($sorted, max(1, $page), max(1, min(50, $perPage)));

            return [
                'data' => $paginated['data'],
                'pagination' => $paginated['pagination'],
                'meta' => [
                    'year' => $selectedYear,
                    'focus' => $normalizedFocus,
                    'search' => $normalizedSearch,
                    'total_members' => (int) $items->count(),
                    'sorting' => $this->resolveLmsFocusSortingMeta($normalizedFocus),
                ],
            ];
        });

        $this->lmsTeamMemberCache[$cacheKey] = $result;

        return $result;
    }

    protected function getCachedLeaderPayload(string $leaderKode): array
    {
        return $this->buildInitialData($leaderKode);
    }

    protected function buildNeedsActionHcisRows(
        Collection $teamMembers,
        Carbon $startDate,
        Carbon $endDate,
        string $cutoffLabel,
        string $search = '',
        ?int $limit = null,
    ): Collection {
        if ($teamMembers->isEmpty()) {
            return collect();
        }

        $context = $this->loadAttendanceContext($teamMembers, $startDate, $endDate, false, [
            'include_pulang_cepat' => false,
            'minimal_row_map' => true,
        ]);
        $rowsByCode = $context['rowsByCode']->map(fn ($rows) => collect((array) $rows)->keyBy('date'));
        $approvedTypeMap = (array) ($context['approvedTypeMap'] ?? []);
        $currentTime = Carbon::now('Asia/Jakarta');
        $searchNeedle = strtolower(trim($search));
        $items = collect();

        foreach ($teamMembers as $member) {
            $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
            if ($kode === '') {
                continue;
            }

            $memberRows = $rowsByCode->get($kode, collect());
            $issueStats = [
                'no-checkin' => ['count' => 0, 'last_date' => null, 'dates' => []],
                'no-checkout' => ['count' => 0, 'last_date' => null, 'dates' => []],
                'late' => ['count' => 0, 'last_date' => null, 'dates' => []],
                'absent' => ['count' => 0, 'last_date' => null, 'dates' => []],
                'no-data' => ['count' => 0, 'last_date' => null, 'dates' => []],
            ];

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateKey = $cursor->toDateString();
                $row = $memberRows->get($dateKey);
                $approvedType = $approvedTypeMap[$kode.'|'.$dateKey] ?? null;
                $classification = $this->classifyAttendanceRow(
                    $row,
                    $cursor->copy(),
                    $this->resolveAttendanceEvaluationTime($cursor->copy(), $currentTime),
                );

                if ($approvedType === 'CUTI') {
                    $classification = ['status' => 'cuti', 'label' => 'Cuti', 'tone' => 'info'];
                } elseif ($approvedType === 'SAKIT') {
                    $classification = ['status' => 'sick', 'label' => 'Sakit', 'tone' => 'info'];
                } elseif ($approvedType === 'IZIN') {
                    $classification = ['status' => 'permission', 'label' => 'Izin', 'tone' => 'info'];
                } elseif (str_starts_with(strtoupper((string) ($row['status_hari'] ?? 'WORK')), 'OFF')) {
                    $classification = ['status' => 'leave', 'label' => 'OFF', 'tone' => 'default'];
                }

                $statusKey = strtolower((string) ($classification['status'] ?? ''));
                if ($statusKey === 'no-data' && ($cursor->isWeekend() || $cursor->isSameDay($currentTime))) {
                    $cursor->addDay();

                    continue;
                }

                // Gunakan aturan "bermasalah" yang sama dengan kalender mingguan
                // (tone danger/warning/orange) supaya hitungan notif konsisten dengan
                // tampilan kalender. Sebelumnya hanya 4 status hardcoded yang dihitung
                // sehingga Alpha (absent) dan status bermasalah lain terlewat.
                $isIssueDay =
                    $statusKey === 'no-data' ? true : ! empty($this->buildAttendanceIssueTags($classification, $row));

                if ($isIssueDay) {
                    if (! isset($issueStats[$statusKey])) {
                        $issueStats[$statusKey] = ['count' => 0, 'last_date' => null, 'dates' => []];
                    }
                    $issueStats[$statusKey]['count']++;
                    $issueStats[$statusKey]['last_date'] = $dateKey;
                    if (count($issueStats[$statusKey]['dates']) < 5) {
                        $issueStats[$statusKey]['dates'][] = $dateKey;
                    }
                }

                $cursor->addDay();
            }

            // Total semua kejadian bermasalah member ini (lintas jenis issue).
            $totalCount = array_sum(array_map(fn ($s) => (int) ($s['count'] ?? 0), $issueStats));
            if ($totalCount > 0) {
                // Tentukan issue DOMINAN (paling parah) untuk menentukan title/severity
                // card. Urutan prioritas dari paling kritikal. 1 member = 1 card yang
                // merangkum SEMUA issue-nya (tidak lagi 1 card per jenis issue).
                $priority = ['absent', 'no-checkin', 'no-checkout', 'late', 'early-out', 'no-data'];
                $primaryIssue = null;
                foreach ($priority as $key) {
                    if ((int) ($issueStats[$key]['count'] ?? 0) > 0) {
                        $primaryIssue = $key;
                        break;
                    }
                }
                $primaryIssue = $primaryIssue ?? 'no-data';

                $severity = match ($primaryIssue) {
                    'no-checkin', 'absent' => 'critical',
                    'no-checkout' => 'high',
                    'no-data' => 'medium',
                    default => 'medium',
                };
                $tone = match ($severity) {
                    'critical' => 'danger',
                    'high' => 'warning',
                    default => 'orange',
                };

                $caseBreakdown = [
                    'no_checkin' => (int) ($issueStats['no-checkin']['count'] ?? 0),
                    'no_checkout' => (int) ($issueStats['no-checkout']['count'] ?? 0),
                    'late' => (int) ($issueStats['late']['count'] ?? 0),
                    'absent' => (int) ($issueStats['absent']['count'] ?? 0),
                    'no_data' => (int) ($issueStats['no-data']['count'] ?? 0),
                ];
                $caseSummary =
                    'No check-in: '.
                    $caseBreakdown['no_checkin'].
                    ' | No check-out: '.
                    $caseBreakdown['no_checkout'].
                    ' | Terlambat: '.
                    $caseBreakdown['late'].
                    ' | Tidak hadir: '.
                    $caseBreakdown['absent'].
                    ' | Belum ada data: '.
                    $caseBreakdown['no_data'];

                // Judul ringkas: jumlah TOTAL kejadian lintas jenis.
                $title = $totalCount.' kejadian absensi perlu validasi';
                $description = $totalCount.' kejadian absensi perlu validasi pada periode cutoff berjalan.';

                // Gabungkan tanggal dari SEMUA jenis issue (unik, urut), untuk "Contoh tanggal".
                $rawDates = collect($issueStats)
                    ->flatMap(fn ($s) => (array) ($s['dates'] ?? []))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();
                $issueDates = $rawDates
                    ->map(function ($date) {
                        try {
                            return Carbon::parse((string) $date, 'Asia/Jakarta')->translatedFormat('d M');
                        } catch (\Throwable $e) {
                            return (string) $date;
                        }
                    })
                    ->all();
                if (! empty($issueDates)) {
                    $description .= ' Contoh tanggal: '.implode(', ', $issueDates).'.';
                }

                // Tanggal terbaru dari seluruh issue -> updated_at.
                $lastDate = collect($issueStats)->map(fn ($s) => $s['last_date'] ?? null)->filter()->max();
                $updatedAt = $lastDate
                    ? Carbon::parse((string) $lastDate, 'Asia/Jakarta')->endOfDay()->toIso8601String()
                    : Carbon::now('Asia/Jakarta')->toIso8601String();

                $item = [
                    'id' => 'hcis-'.$kode,
                    'member_id' => $kode,
                    'memberId' => $kode,
                    'member_name' => (string) ($member->Nama ?? 'Tanpa Nama'),
                    'memberName' => (string) ($member->Nama ?? 'Tanpa Nama'),
                    'member_position' => (string) ($member->nama_jabatan ?? '-'),
                    'member_department' => (string) ($member->nama_divisi ?? '-'),
                    'category' => 'hcis',
                    'severity' => $severity,
                    'tone' => $tone,
                    'icon' => 'bi bi-person-exclamation',
                    'title' => $title,
                    'description' => $description,
                    'action_hint' => 'Buka detail absensi anggota untuk tindak lanjut.',
                    'period_label' => $cutoffLabel,
                    'updated_at' => $updatedAt,
                    'meta' => 'HCIS Absensi - '.$cutoffLabel,
                    'issue_key' => $primaryIssue,
                    'case_breakdown' => $caseBreakdown,
                    'case_dates' => $issueDates,
                    'raw_case_dates' => $rawDates->all(),
                    'case_summary' => $caseSummary,
                ];

                if ($searchNeedle !== '') {
                    $searchable = strtolower(
                        implode(' ', [
                            (string) ($item['member_name'] ?? ''),
                            (string) ($item['member_department'] ?? ''),
                            (string) ($item['member_position'] ?? ''),
                            (string) ($item['title'] ?? ''),
                            (string) ($item['description'] ?? ''),
                        ]),
                    );
                    if (! str_contains($searchable, $searchNeedle)) {
                        continue;
                    }
                }

                $items->push($item);
                if ($limit !== null && $items->count() >= $limit) {
                    return $items->values();
                }
            }
        }

        return $items->values();
    }

    protected function buildNeedsActionKpiRows(
        Collection $teamMembers,
        int $kpiYear,
        string $search = '',
        ?int $limit = null,
    ): Collection {
        if ($teamMembers->isEmpty()) {
            return collect();
        }

        $memberCodes = $teamMembers
            ->pluck('Kode_Karyawan')
            ->map(fn ($kode) => strtoupper(trim((string) $kode)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (empty($memberCodes)) {
            return collect();
        }

        $summaries = $this->homepageKpiService->getTeamSummaries($memberCodes, false, $kpiYear);
        $searchNeedle = strtolower(trim($search));
        $items = collect();

        foreach ($teamMembers as $member) {
            $kode = strtoupper(trim((string) ($member->Kode_Karyawan ?? '')));
            if ($kode === '') {
                continue;
            }

            $summary = (array) ($summaries[$kode] ?? []);
            $pendingItems = collect((array) ($summary['pending_items'] ?? []))->values();
            if ($pendingItems->isEmpty()) {
                continue;
            }

            $updatedAt = Carbon::now('Asia/Jakarta')->toIso8601String();
            $lastUpdateRaw = $summary['last_update'] ?? null;
            if (! empty($lastUpdateRaw)) {
                try {
                    $updatedAt = Carbon::parse((string) $lastUpdateRaw, 'Asia/Jakarta')->endOfDay()->toIso8601String();
                } catch (\Throwable $e) {
                    $updatedAt = Carbon::now('Asia/Jakarta')->toIso8601String();
                }
            }

            foreach ($pendingItems as $index => $pendingItem) {
                $itemTone = strtolower((string) data_get($pendingItem, 'tone', 'warning'));
                if ($itemTone === 'info') {
                    continue;
                }
                $pendingId = strtolower((string) data_get($pendingItem, 'id', 'pending'));
                $severity = $pendingId === 'performance' ? 'high' : 'medium';
                $tone = $severity === 'high' ? 'warning' : 'orange';
                $periodLabel =
                    $pendingId === 'performance'
                        ? (string) ($summary['performance_period'] ?? 'Periode KPI')
                        : (string) ($summary['parameter_period'] ?? 'Periode KPI');
                $title = (string) data_get($pendingItem, 'title', 'Alert KPI perlu tindak lanjut');
                $description = (string) data_get($pendingItem, 'description', 'Ada item KPI yang belum lengkap.');
                $deadlineText = trim((string) ($summary['deadline_text'] ?? ''));

                if ($deadlineText !== '' && ! str_contains(strtolower($description), strtolower($deadlineText))) {
                    $description .= ' '.$deadlineText;
                }

                $item = [
                    'id' => 'kpi-'.$kode.'-'.$pendingId.'-'.$index,
                    'member_id' => $kode,
                    'memberId' => $kode,
                    'member_name' => (string) ($member->Nama ?? 'Tanpa Nama'),
                    'memberName' => (string) ($member->Nama ?? 'Tanpa Nama'),
                    'member_position' => (string) ($member->nama_jabatan ?? '-'),
                    'member_department' => (string) ($member->nama_divisi ?? '-'),
                    'category' => 'kpi',
                    'severity' => $severity,
                    'tone' => $tone,
                    'icon' => 'bi bi-graph-up-arrow',
                    'title' => $title,
                    'description' => $description,
                    'action_hint' => 'Tinjau status KPI anggota dan lanjutkan submit/approval.',
                    'period_label' => $periodLabel,
                    'updated_at' => $updatedAt,
                    'meta' => 'KPI - '.$periodLabel.' - Tahun '.$kpiYear,
                    'pending_id' => $pendingId,
                ];

                if ($searchNeedle !== '') {
                    $searchable = strtolower(
                        implode(' ', [
                            (string) ($item['member_name'] ?? ''),
                            (string) ($item['member_department'] ?? ''),
                            (string) ($item['member_position'] ?? ''),
                            (string) ($item['title'] ?? ''),
                            (string) ($item['description'] ?? ''),
                            (string) ($item['period_label'] ?? ''),
                        ]),
                    );
                    if (! str_contains($searchable, $searchNeedle)) {
                        continue;
                    }
                }

                $items->push($item);
                if ($limit !== null && $items->count() >= $limit) {
                    return $items->values();
                }
            }
        }

        return $items->values();
    }

    protected function buildNeedsActionFallbackFromExistingRows(
        Collection $hcisItems,
        Collection $kpiItems,
        string $search = '',
    ): Collection {
        $searchNeedle = strtolower(trim($search));
        $baseItems = $hcisItems->concat($kpiItems)->values();
        if ($searchNeedle === '') {
            return $baseItems;
        }

        return $baseItems
            ->filter(function (array $item) use ($searchNeedle) {
                $searchable = strtolower(
                    implode(' ', [
                        (string) ($item['member_name'] ?? ''),
                        (string) ($item['member_department'] ?? ''),
                        (string) ($item['member_position'] ?? ''),
                        (string) ($item['title'] ?? ''),
                        (string) ($item['description'] ?? ''),
                        (string) ($item['period_label'] ?? ''),
                    ]),
                );

                return str_contains($searchable, $searchNeedle);
            })
            ->values();
    }

    protected function resolveNeedsActionCategoryRank(string $category): int
    {
        return match (strtolower(trim($category))) {
            'hcis' => 0,
            'approval' => 1,
            'kpi' => 2,
            default => 9,
        };
    }

    protected function resolveNeedsActionSeverityRank(string $severity): int
    {
        return match (strtolower(trim($severity))) {
            'critical' => 0,
            'high' => 1,
            'medium' => 2,
            default => 9,
        };
    }

    protected function buildMemberCards(
        Collection $teamMembers,
        ?int $kpiYear = null,
        ?string $leaderKode = null,
    ): array {
        if ($teamMembers->isEmpty()) {
            return [];
        }

        // Gunakan summary absensi penuh agar angka ringkasan row konsisten dengan detail accordion.
        $attendanceByCode = $this->buildTeamAttendanceSummaries($teamMembers);
        $memberCodes = $teamMembers->pluck('Kode_Karyawan')->map(fn ($kode) => strtoupper(trim((string) $kode)))->all();
        $requestedKpiYear = $kpiYear !== null ? $this->normalizeKpiYear($kpiYear) : null;
        $kpiByCode = $this->homepageKpiService->getTeamSummaries($memberCodes, false, $requestedKpiYear);
        $lmsByCode = $this->homepageLmsService->getTeamSummaries($memberCodes);
        $approvalCounts = $this->buildApprovalCounts($teamMembers, $leaderKode);

        return $teamMembers
            ->map(function ($member) use ($attendanceByCode, $kpiByCode, $lmsByCode, $approvalCounts) {
                $kode = strtoupper(trim((string) $member->Kode_Karyawan));
                $attendance = $attendanceByCode[$kode] ?? $this->defaultAttendanceSummary();
                $kpi = $kpiByCode[$kode] ?? $this->defaultKpiSummary();
                $lms = $lmsByCode[$kode] ?? $this->defaultLmsSummary();
                $approvalCount = (int) ($approvalCounts[$kode] ?? 0);

                return [
                    'id' => $kode,
                    'kode_karyawan' => $kode,
                    'name' => $member->Nama ?? 'Tanpa Nama',
                    'initials' => $this->buildInitials($member->Nama ?? 'U'),
                    'position' => $member->nama_jabatan ?? '-',
                    'department' => $member->nama_divisi ?? '-',
                    'shift' => [
                        'name' => $attendance['shift_name'],
                        'time' => $attendance['shift_time'],
                    ],
                    'attendance' => $attendance,
                    'kpi' => [
                        'score' => $kpi['score'] ?? 0,
                        'year' => (int) ($kpi['kpi_year'] ?? ($requestedKpiYear ?? Carbon::now('Asia/Jakarta')->year)),
                        'grade' => $kpi['grade_label'] ?? 'Belum ada data',
                        'achievement' => $kpi['achievement'] ?? 0,
                        'achievement_grade_label' => $kpi['achievement_grade_label'] ?? '-',
                        'achievement_grade_color' => $kpi['achievement_grade_color'] ?? '#94a3b8',
                        'ytdScore' => (float) ($kpi['ytd_score'] ?? 0.0),
                        'ytdSubmittedCount' => (int) ($kpi['ytd_submitted_count'] ?? 0),
                        'ytd_grade_label' => $kpi['ytd_grade_label'] ?? '-',
                        'ytd_grade_color' => $kpi['ytd_grade_color'] ?? '#94a3b8',
                        'parameterSubmitted' => $kpi['parameter_submitted'] ?? false,
                        'performanceSubmitted' => $kpi['performance_submitted'] ?? false,
                        'parameterPeriod' => $kpi['parameter_period'] ?? 'Periode berjalan',
                        'performancePeriod' => $kpi['performance_period'] ?? 'Periode berjalan',
                        'deadlineText' => $kpi['deadline_text'] ?? '',
                        'pendingItems' => $kpi['pending_items'] ?? [],
                        'monthlyAchievement' => [],
                        'parameters' => [],
                        'parameterScheduled' => (bool) ($kpi['parameter_scheduled'] ?? ($kpi['form_scheduled'] ?? false)),
                        'performanceScheduled' => (bool) ($kpi['performance_scheduled'] ?? ($kpi['cutoff_performance_scheduled'] ?? false)),
                        'parameterApprovalStatus' => $kpi['parameter_approval_status'] ?? ($kpi['parameter']['approvalStatus'] ?? 'none'),
                        'performanceApprovalStatus' => $kpi['performance_approval_status'] ?? ($kpi['performance']['approvalStatus'] ?? 'none'),
                        'performanceHasParameters' => (bool) ($kpi['performance_has_parameters'] ??
                                ($kpi['performance']['hasParameters'] ?? false)),
                    ],
                    'lms' => [
                        'has_data' => $lms['has_data'] ?? ($lms['enrolled_courses'] ?? 0) > 0,
                        'completed' => $lms['completed_courses'] ?? 0,
                        'total' => $lms['enrolled_courses'] ?? 0,
                        'pending' => $lms['mandatory_pending'] ??
                            max(0, ($lms['enrolled_courses'] ?? 0) - ($lms['completed_courses'] ?? 0)),
                        'progress' => $lms['progress'] ?? 0,
                        'average_score' => $lms['average_score'] ?? null,
                        'learning_gain' => $lms['learning_gain'] ?? null,
                        'learning_hours' => $lms['learning_hours'] ?? 0,
                        'avg_duration_hours' => $lms['avg_duration_hours'] ?? 0,
                        'attempted_courses' => $lms['attempted_courses'] ?? 0,
                        'next_session' => $lms['next_session'] ?? 'Tidak ada training aktif',
                        'next_session_date' => $lms['next_session_date'] ?? '-',
                        'trainings' => $lms['trainings'] ?? [],
                        'learning_progress' => $lms['learning_progress'] ?? [],
                        'completed_monthly' => $lms['completed_monthly'] ?? [],
                        'duration_monthly' => $lms['duration_monthly'] ?? [],
                    ],
                    'overtime' => [
                        'totalHours' => 0,
                        'beforeWorkHours' => 0,
                        'afterWorkHours' => 0,
                        'holidayHours' => 0,
                        'weeklyTrend' => [],
                    ],
                    'approvalCount' => $approvalCount,
                    'follow_up_summary' => $this->buildFollowUpSummary($attendance, $kpi, $lms, $approvalCount),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildApprovalCounts(Collection $teamMembers, ?string $approverKode = null): array
    {
        $memberCodes = $teamMembers->pluck('Kode_Karyawan')->map(fn ($kode) => strtoupper(trim((string) $kode)))->all();
        if (empty($memberCodes)) {
            return [];
        }

        $rows = $this->loadGroupedApprovalQueueRows($memberCodes, $approverKode);
        $activeRows = $rows->filter(fn ($row) => ! $this->isApprovalExpiredRecord($row))->values();

        return $activeRows
            ->groupBy(fn ($row) => strtoupper(trim((string) ($row->Kode_Karyawan ?? ''))))
            ->map(fn (Collection $items) => $items->count())
            ->all();
    }

    protected function paginateCollection(Collection $items, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);
        $offset = ($currentPage - 1) * $perPage;

        $data = $items->slice($offset, $perPage)->values()->all();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'has_prev' => $currentPage > 1,
                'has_next' => $currentPage < $lastPage,
            ],
        ];
    }

    protected function loadTeamMembers(string $leaderKode): Collection
    {
        // Memoize per-request: query 4-join ini dipanggil >1x dalam satu request
        // (getGlobalSummary + resolveApprovalQueueCount). Daftar tim tak berubah
        // dalam 1 request, jadi aman dibagikan.
        $key = strtoupper(trim($leaderKode));
        if (isset($this->teamMembersMemo[$key])) {
            return $this->teamMembersMemo[$key];
        }

        return $this->teamMembersMemo[$key] = $this->buildTeamMembersQuery($leaderKode)->get();
    }

    protected function buildTeamMembersQuery(string $leaderKode, string $keyword = ''): Builder
    {
        $query = DB::table('HRIS_Karyawan_Team as team')
            ->join('Karyawan as k', 'team.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                'k.ID_Level_Jabatan',
                '=',
                'lvl.ID_Level_Jabatan',
            )
            ->where('team.Kode_Karyawan_Team', $leaderKode)
            ->where('team.Kode_Karyawan', '<>', $leaderKode)
            ->where('k.Aktif', 'Y');

        if ($keyword !== '') {
            $query->where(function ($inner) use ($keyword) {
                $inner
                    ->whereRaw('LOWER(k.Nama) like ?', ['%'.$keyword.'%'])
                    ->orWhereRaw('LOWER(lvl.nama_jabatan) like ?', ['%'.$keyword.'%'])
                    ->orWhereRaw('LOWER(div.nama_divisi) like ?', ['%'.$keyword.'%']);
            });
        }

        return $query
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserID_Absen',
                'k.UserID_Web',
                'k.Tgl_Masuk',
                'div.nama_divisi',
                'lvl.nama_jabatan',
            )
            ->orderBy('k.Nama');
    }

    protected function buildLightAttendanceSummaries(Collection $teamMembers, ?Carbon $date = null): array
    {
        $targetDate = ($date ?: Carbon::now('Asia/Jakarta'))->copy()->startOfDay();
        $context = $this->loadAttendanceContext($teamMembers, $targetDate, $targetDate, false);
        $now = Carbon::now('Asia/Jakarta');
        $result = [];

        foreach ($teamMembers as $member) {
            $kode = strtoupper(trim((string) $member->Kode_Karyawan));
            $memberRows = collect($context['rowsByCode']->get($kode, []))->keyBy('date');
            $todayRow = $memberRows->get($targetDate->toDateString());
            $approvedToday = $context['approvedTypeMap'][$kode.'|'.$targetDate->toDateString()] ?? null;

            $todayClassification = $this->classifyAttendanceRow($todayRow, $targetDate, $now);
            $todayStatus = $todayClassification['status'];
            $todayLabel = $todayClassification['label'];

            if ($approvedToday === 'CUTI') {
                $todayStatus = 'cuti';
                $todayLabel = 'Cuti';
            } elseif ($approvedToday === 'SAKIT') {
                $todayStatus = 'sick';
                $todayLabel = 'Sakit';
            } elseif ($approvedToday === 'IZIN') {
                $todayStatus = 'permission';
                $todayLabel = 'Izin';
            } elseif (str_starts_with(strtoupper((string) ($todayRow['status_hari'] ?? 'WORK')), 'OFF')) {
                $todayStatus = 'leave';
                $todayLabel = 'OFF';
            }

            // Alpha (tidak hadir sama sekali) dan no-checkin (ada checkout tanpa
            // checkin) adalah dua kondisi berbeda di classifyAttendanceRow. Jangan
            // dikonflasikan agar count HCIS team cocok dengan modul sumber.
            $earlyOutCount = (int) ($todayRow['early_minutes'] ?? 0) > 0 ? 1 : 0;
            $issueCount =
                (in_array($todayStatus, ['absent', 'no-checkin', 'no-checkout', 'late'], true) ? 1 : 0) +
                ($earlyOutCount > 0 && ! in_array($todayStatus, ['absent', 'no-checkin', 'no-checkout', 'late'], true)
                    ? 1
                    : 0);

            $result[$kode] = [
                'todayStatus' => $todayStatus,
                'todayLabel' => $todayLabel,
                'checkIn' => $todayRow['check_in'] ?? null,
                'checkOut' => $todayRow['check_out'] ?? null,
                'hasCheckIn' => ! empty($todayRow['check_in']),
                'hasCheckOut' => ! empty($todayRow['check_out']),
                'workDays' => 0,
                'alphaCount' => $todayStatus === 'absent' ? 1 : 0,
                'noCheckinCount' => $todayStatus === 'no-checkin' ? 1 : 0,
                'absentCount' => $todayStatus === 'absent' ? 1 : 0,
                'lateCount' => (int) ($todayStatus === 'late' ? 1 : 0),
                'lateMinutes' => (int) ($todayRow['late_minutes'] ?? 0),
                'earlyOutCount' => $earlyOutCount,
                'overtimeHours' => 0,
                'permissionDays' => $todayStatus === 'permission' ? 1 : 0,
                'sickDays' => $todayStatus === 'sick' ? 1 : 0,
                'leaveDays' => in_array($todayStatus, ['leave', 'cuti'], true) ? 1 : 0,
                'noCheckoutCount' => $todayStatus === 'no-checkout' ? 1 : 0,
                'issueCount' => $issueCount,
                'issues' => [],
                'dailyLog' => [],
                'shift_name' => $todayRow['shift_name'] ?? 'No Shift',
                'shift_time' => ! empty($todayRow['jam_masuk']) && ! empty($todayRow['jam_keluar'])
                        ? $todayRow['jam_masuk'].' - '.$todayRow['jam_keluar']
                        : '-',
                'overtime' => [
                    'totalHours' => 0,
                    'beforeWorkHours' => 0,
                    'afterWorkHours' => 0,
                    'holidayHours' => 0,
                    'weeklyTrend' => [],
                ],
            ];
        }

        return $result;
    }

    protected function buildHeatmapAttendanceSummaries(
        Collection $teamMembers,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        $context = $this->loadAttendanceContext($teamMembers, $startDate, $endDate, false);
        $result = [];

        foreach ($teamMembers as $member) {
            $kode = strtoupper(trim((string) $member->Kode_Karyawan));
            $memberRows = collect($context['rowsByCode']->get($kode, []))->keyBy('date');
            $dailyLog = $this->buildDailyLog(
                $memberRows,
                $context['approvedTypeMap'] ?? [],
                $kode,
                $startDate,
                $endDate,
                $context['pulangCepatKeys'] ?? collect(),
                [],
            );
            $todayRow = $memberRows->get($endDate->toDateString());

            $result[$kode] =
                [
                    'todayStatus' => $todayRow ? 'present' : 'absent',
                    'todayLabel' => $todayRow ? 'Hadir' : 'Belum Ada Data',
                    'issues' => [],
                    'dailyLog' => $dailyLog,
                    'shift_name' => $todayRow['shift_name'] ?? 'No Shift',
                    'shift_time' => ! empty($todayRow['jam_masuk']) && ! empty($todayRow['jam_keluar'])
                            ? $todayRow['jam_masuk'].' - '.$todayRow['jam_keluar']
                            : '-',
                    'issueCount' => collect($dailyLog)
                        ->filter(
                            fn ($item) => in_array(
                                strtolower((string) ($item['tone'] ?? '')),
                                ['danger', 'warning', 'orange'],
                                true,
                            ),
                        )
                        ->count(),
                    'overtime' => [
                        'totalHours' => 0,
                        'beforeWorkHours' => 0,
                        'afterWorkHours' => 0,
                        'holidayHours' => 0,
                        'weeklyTrend' => [],
                    ],
                ] + $this->defaultAttendanceSummary();
        }

        return $result;
    }

    /**
     * TTL cache attendance context leader (detik). Method ini dipanggil dari ~15 endpoint
     * dashboard leader, masing-masing menjalankan SP absen + query approval forRequesters
     * (union 9 view) untuk SELURUH anggota tim -> penyumbang CPU utama saat banyak atasan
     * buka dashboard. Cache pendek (90s) supaya data tetap aktual tapi tidak hitung ulang
     * tiap pindah tab.
     */
    // Dinaikkan 90→180: attendance context & item approval boleh basi <=3 mnt; halving
    // frekuensi recompute SP absen/union saat banyak leader buka bersamaan (rush).
    private const LEADER_ATTENDANCE_CACHE_TTL = 180;

    // TTL khusus BADGE count (antrian approval / KPI approval). Dibuat lebih panjang
    // dari attendance karena count union-9-view (forApprover) mahal & dipicu tiap
    // leader buka dashboard; badge boleh telat <=5 mnt. Mengurangi recompute union
    // ~3.3x saat banyak leader buka bersamaan (rush).
    private const LEADER_BADGE_CACHE_TTL = 300;

    protected function loadAttendanceContext(
        Collection $teamMembers,
        Carbon $startDate,
        Carbon $endDate,
        bool $includeOvertime = true,
        array $options = [],
    ): array {
        // Cache key dari input deterministik: daftar member + rentang + flag.
        $memberKey = $teamMembers->pluck('Kode_Karyawan')
            ->map(fn ($k) => strtoupper(trim((string) $k)))
            ->sort()->values()->implode(',');
        $cacheKey = 'leader.attendance-ctx.v1:'.sha1(implode('|', [
            $memberKey,
            $startDate->toDateString(),
            $endDate->toDateString(),
            $includeOvertime ? '1' : '0',
            json_encode($options),
        ]));

        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->auditLeaderAbsenCall('CTX_CACHE_HIT', $startDate->toDateString(), $endDate->toDateString(), $memberKey, '-');

            return $cached;
        }

        $result = $this->loadAttendanceContextUncached(
            $teamMembers, $startDate, $endDate, $includeOvertime, $options,
        );

        // Kalau data absen belum lengkap (sebagian masih diproses queue), cache
        // sebentar saja supaya tidak mengunci data kosong; jika lengkap, cache penuh.
        $ttl = $this->lastAbsenIncomplete
            ? self::LEADER_ATTENDANCE_PARTIAL_TTL
            : self::LEADER_ATTENDANCE_CACHE_TTL;

        \Illuminate\Support\Facades\Cache::put($cacheKey, $result, $ttl);

        return $result;
    }

    /**
     * Ambil absen tim dengan split mode hari-ini-SYNC / masa-lalu-AUTO.
     * Mengembalikan array baris mentah dari SP (digabung).
     */
    protected function fetchTeamAbsensiSplit(
        Carbon $startDate,
        Carbon $endDate,
        string $listUserId,
        int $userCount,
    ): array {
        $this->lastAbsenIncomplete = false;
        // Bandingkan via STRING tanggal (Y-m-d) agar tidak terpengaruh selisih
        // timezone saat parsing.
        $todayStr = Carbon::now('Asia/Jakarta')->toDateString();
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();
        $todayInRange = $todayStr >= $startStr && $todayStr <= $endStr;
        $pastEndStr = $todayInRange
            ? Carbon::parse($todayStr, 'Asia/Jakarta')->subDay()->toDateString()
            : $endStr;

        $merged = [];

        if ($startStr <= $pastEndStr) {
            $pastDays = Carbon::parse($startStr, 'Asia/Jakarta')
                ->diffInDays(Carbon::parse($pastEndStr, 'Asia/Jakarta')) + 1;
            // Ambang = user x hari, di-clamp [floor, hard cap]. Tim besar/rentang
            // lebar -> melebihi cap -> wrapper jatuhkan ke ASYNC (queue), data
            // VALID tetap dibaca; yang dirty diproses worker (tiap menit).
            $maxSync = min(self::ABSEN_MAX_SYNC_HARD, max(self::ABSEN_MAX_SYNC_FLOOR, $userCount * max(1, $pastDays)));
            $merged = $this->fetchTeamAbsensiFromSp($startStr, $pastEndStr, $listUserId, 'AUTO', $maxSync);
        }

        if ($todayInRange) {
            // Hari ini realtime: SYNC. Ambang = jumlah user (1 hari), di-clamp.
            $maxSyncToday = min(self::ABSEN_MAX_SYNC_HARD, max(self::ABSEN_MAX_SYNC_FLOOR, $userCount));
            $todayRows = $this->fetchTeamAbsensiFromSp($todayStr, $todayStr, $listUserId, 'SYNC', $maxSyncToday);
            // Buang baris hari ini dari hasil masa-lalu (jika ada), lalu gabung.
            $merged = array_filter($merged, function ($row) use ($todayStr) {
                $tgl = isset($row->Tanggal) ? Carbon::parse($row->Tanggal)->toDateString() : null;

                return $tgl !== $todayStr;
            });
            $merged = array_merge(array_values($merged), $todayRows);

            // FUTURE (besok..endDate): WAJIB diambil supaya shift terjadwal & transaksi
            // approved (cuti/izin) mendatang anggota tim muncul di kalender/heatmap tim.
            // Mode AUTO (data future stabil). Tanpa ini tanggal > hari ini tampil
            // "OFF/Belum Dijalankan" keliru (sama spt bug homepage personal).
            $futureStartStr = Carbon::parse($todayStr, 'Asia/Jakarta')->addDay()->toDateString();
            if ($futureStartStr <= $endStr) {
                $futureDays = Carbon::parse($futureStartStr, 'Asia/Jakarta')
                    ->diffInDays(Carbon::parse($endStr, 'Asia/Jakarta')) + 1;
                $maxSyncFuture = min(self::ABSEN_MAX_SYNC_HARD, max(self::ABSEN_MAX_SYNC_FLOOR, $userCount * max(1, $futureDays)));
                $futureRows = $this->fetchTeamAbsensiFromSp($futureStartStr, $endStr, $listUserId, 'AUTO', $maxSyncFuture);
                $merged = array_merge($merged, $futureRows);
            }
        }

        // Tandai bila ada tanggal yang belum final (masuk ASYNC / sedang diproses)
        // -> caller akan pakai TTL cache pendek supaya tidak mengunci data kosong.
        foreach ($merged as $row) {
            $status = isset($row->Data_Status) ? strtoupper((string) $row->Data_Status) : 'VALID';
            if (in_array($status, ['MISSING', 'QUEUED', 'PROCESSING', 'STALE', 'ERROR'], true)) {
                $this->lastAbsenIncomplete = true;
                break;
            }
        }

        return $merged;
    }

    /**
     * Panggilan SP absen tim mentah (tanpa mapping). Mengembalikan array baris.
     */
    protected function fetchTeamAbsensiFromSp(
        string $tglMulai,
        string $tglSelesai,
        string $listUserId,
        string $executionMode,
        int $maxSyncUserDate,
    ): array {
        $memoKey = $listUserId.'|'.$tglMulai.'|'.$tglSelesai;

        // 1) Memoize intra-request.
        if (isset($this->teamAbsenMemo[$memoKey])) {
            $this->auditLeaderAbsenCall('MEMO_HIT', $tglMulai, $tglSelesai, $listUserId, $executionMode);

            return $this->teamAbsenMemo[$memoKey];
        }

        // 2) Cache lintas-request: kalau ada rentang tim yang MENCAKUP, iris dari situ.
        //    Hanya untuk mode AUTO (masa lalu, stabil). HARI INI (SYNC) selalu fresh.
        if ($executionMode === 'AUTO') {
            $sliced = $this->sliceFromCachedTeamRows($listUserId, $tglMulai, $tglSelesai);
            if ($sliced !== null) {
                $this->auditLeaderAbsenCall('CACHE_SLICE', $tglMulai, $tglSelesai, $listUserId, $executionMode);

                return $this->teamAbsenMemo[$memoKey] = $sliced;
            }

            // 2b) SINGLE-FLIGHT: saat mount paralel, beberapa endpoint minta SP tim
            //     yang sama berbarengan. Tanpa koordinasi, semua SP sendiri (×N anggota).
            //     Lock terdistribusi: HANYA 1 request yang eksekusi SP; yang lain
            //     MENUNGGU sebentar lalu baca hasil dari cache. Bila gagal dapat lock
            //     & cache tetap kosong (timeout), fallback jalan sendiri (anti-deadlock).
            $rows = $this->fetchTeamRowsSingleFlight($listUserId, $tglMulai, $tglSelesai, $maxSyncUserDate);
            if ($rows !== null) {
                return $this->teamAbsenMemo[$memoKey] = $rows;
            }
        }

        $rows = $this->fetchTeamRowsViaSp($tglMulai, $tglSelesai, $listUserId, $executionMode, $maxSyncUserDate);

        // Simpan ke cache lintas-request hanya untuk AUTO (masa lalu stabil).
        if ($executionMode === 'AUTO') {
            $this->storeTeamRowsCache($listUserId, $tglMulai, $tglSelesai, $rows);
        }

        return $this->teamAbsenMemo[$memoKey] = $rows;
    }

    /**
     * Single-flight untuk SP tim (mode AUTO). Return rows bila berhasil (entah
     * eksekusi sendiri di bawah lock, atau membaca hasil request lain). Return null
     * bila tak bisa koordinasi (caller fallback jalan sendiri).
     */
    protected function fetchTeamRowsSingleFlight(string $listUserId, string $from, string $to, int $maxSyncUserDate): ?array
    {
        $lockKey = 'leader.team-absen-lock.v1:'.sha1($listUserId.'|'.$from.'|'.$to);
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 30); // auto-release 30s

        if ($lock->get()) {
            // Kita pemegang lock -> eksekusi SP, simpan cache, lepas lock.
            try {
                // Re-check cache: mungkin request lain sempat mengisi sebelum kita dapat lock.
                $sliced = $this->sliceFromCachedTeamRows($listUserId, $from, $to);
                if ($sliced !== null) {
                    $this->auditLeaderAbsenCall('CACHE_SLICE', $from, $to, $listUserId, 'AUTO');

                    return $sliced;
                }
                $rows = $this->fetchTeamRowsViaSp($from, $to, $listUserId, 'AUTO', $maxSyncUserDate);
                $this->storeTeamRowsCache($listUserId, $from, $to, $rows);

                return $rows;
            } finally {
                optional($lock)->release();
            }
        }

        // Tidak dapat lock -> request lain sedang eksekusi. Tunggu singkat lalu baca cache.
        for ($i = 0; $i < 25; $i++) { // maks ~25 × 200ms = 5 detik
            usleep(200_000);
            $sliced = $this->sliceFromCachedTeamRows($listUserId, $from, $to);
            if ($sliced !== null) {
                $this->auditLeaderAbsenCall('LOCK_WAIT_HIT', $from, $to, $listUserId, 'AUTO');

                return $sliced;
            }
        }

        // Timeout: hasil tak muncul -> caller fallback jalan sendiri (anti-deadlock).
        return null;
    }

    /** Eksekusi SP absen tim mentah (tanpa cache/lock). */
    protected function fetchTeamRowsViaSp(
        string $tglMulai,
        string $tglSelesai,
        string $listUserId,
        string $executionMode,
        int $maxSyncUserDate,
    ): array {
        $this->auditLeaderAbsenCall('SP_CALL', $tglMulai, $tglSelesai, $listUserId, $executionMode);
        $sourceReuqest = 'homepage-leader-dashboard-service-load-attendance-context';
        $response = $this->attendanceService->getAttendance(
            kodePerusahaan: '001',
            tglAwal: $tglMulai,
            tglAkhir: $tglSelesai,
            userIdList: $listUserId,
            executionMode: $executionMode,
            maxSyncUserDate: $maxSyncUserDate,
            requestSource: $sourceReuqest,
            requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
            priority: 1,
        );

        if (! $response['success']) {
            throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
        }

        return $response['data'] ?? [];
    }

    /** Iris rows tim dari cache rentang yang MENCAKUP [from,to]. Null bila tak ada. */
    protected function sliceFromCachedTeamRows(string $listUserId, string $from, string $to): ?array
    {
        $index = \Illuminate\Support\Facades\Cache::get($this->teamRowsIndexKey($listUserId));
        if (! is_array($index)) {
            return null;
        }
        foreach ($index as $entry) {
            if (($entry['from'] ?? '') <= $from && ($entry['to'] ?? '') >= $to) {
                $cached = \Illuminate\Support\Facades\Cache::get(
                    $this->teamRowsCacheKey($listUserId, $entry['from'], $entry['to']),
                );
                if (is_array($cached)) {
                    return array_values(array_filter($cached, function ($row) use ($from, $to) {
                        $tgl = isset($row->Tanggal) ? Carbon::parse($row->Tanggal)->toDateString() : null;

                        return $tgl !== null && $tgl >= $from && $tgl <= $to;
                    }));
                }
            }
        }

        return null;
    }

    protected function storeTeamRowsCache(string $listUserId, string $from, string $to, array $rows): void
    {
        \Illuminate\Support\Facades\Cache::put(
            $this->teamRowsCacheKey($listUserId, $from, $to),
            $rows,
            self::TEAM_ABSEN_ROWS_CACHE_TTL,
        );
        $indexKey = $this->teamRowsIndexKey($listUserId);
        $index = \Illuminate\Support\Facades\Cache::get($indexKey);
        $index = is_array($index) ? $index : [];
        $index = array_values(array_filter(
            $index,
            fn ($e) => ! (($from <= ($e['from'] ?? '')) && ($to >= ($e['to'] ?? ''))),
        ));
        $index[] = ['from' => $from, 'to' => $to];
        \Illuminate\Support\Facades\Cache::put($indexKey, $index, self::TEAM_ABSEN_ROWS_CACHE_TTL);
    }

    private function teamRowsCacheKey(string $listUserId, string $from, string $to): string
    {
        // listUserId bisa panjang (banyak anggota) -> hash agar key stabil & pendek.
        return 'leader.team-absen-rows.v1:'.sha1($listUserId).':'.$from.':'.$to;
    }

    private function teamRowsIndexKey(string $listUserId): string
    {
        return 'leader.team-absen-rows-index.v1:'.sha1($listUserId);
    }

    /**
     * AUDIT SEMENTARA: catat panggilan SP absen tim (atau cache-hit context) untuk
     * mengukur beban LeaderDashboard per load. Aktif via config homepage_audit.
     * HAPUS setelah analisis. Dikelompokkan req_id per request.
     */
    protected function auditLeaderAbsenCall(string $kind, string $from, string $to, string $listUserId, string $mode): void
    {
        if (! config('homepage_audit.enabled', false)) {
            return;
        }
        $days = (int) (\Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1);
        $userCount = count(array_filter(array_map('trim', explode(',', $listUserId)), 'strlen'));
        \Illuminate\Support\Facades\Log::channel('homepageLog')->info('AUDIT LEADER absen '.$kind, [
            'kind' => $kind,
            'endpoint' => request()?->path(),
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'user_count' => $userCount,
            'user_date' => $days * max(1, $userCount),
            'req_id' => $this->auditLeaderRequestId(),
        ]);
    }

    protected function auditLeaderRequestId(): string
    {
        static $id = null;
        if ($id === null) {
            $id = substr(md5(uniqid('', true)), 0, 8);
        }

        return $id;
    }

    protected function loadAttendanceContextUncached(
        Collection $teamMembers,
        Carbon $startDate,
        Carbon $endDate,
        bool $includeOvertime = true,
        array $options = [],
    ): array {
        $t0 = microtime(true);
        $includePulangCepat = (bool) ($options['include_pulang_cepat'] ?? true);
        $minimalRowMap = (bool) ($options['minimal_row_map'] ?? false);
        $memberCodes = $teamMembers->pluck('Kode_Karyawan')->map(fn ($kode) => strtoupper(trim((string) $kode)))->all();
        $rawUserIds = $teamMembers
            ->pluck('UserID_Absen')
            ->filter()
            ->map(fn ($id) => trim((string) $id))
            ->unique()
            ->values();
        $userIds = $rawUserIds
            ->filter(fn ($id) => preg_match('/^-?\d+$/', $id) === 1)
            ->reject(fn ($id) => (int) $id <= 0)
            ->unique()
            ->values();

        $rowsByCode = collect();
        if ($userIds->isNotEmpty()) {
            $spStartedAt = microtime(true);
            // $rows = DB::select("Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''", [
            //     $startDate->toDateString(),
            //     $endDate->toDateString(),
            //     $userIds->implode(','),
            // ]);
            $listUserId = $userIds->implode(',');
            // Split mode: HARI INI = SYNC (realtime, shift berlangsung), MASA LALU
            // = AUTO (snapshot VALID dibaca langsung; backlog besar -> ASYNC queue,
            // tidak recalc inline di request). Untuk tim besar ini mencegah recalc
            // puluhan-user x hari secara inline (penyebab spike saat leader login).
            $rows = $this->fetchTeamAbsensiSplit($startDate, $endDate, $listUserId, $userIds->count());
            $spMs = (int) round((microtime(true) - $spStartedAt) * 1000);

            $rowsByCode = collect($rows)
                ->map(function ($row) {
                    // Parse via Carbon to stay robust against any datetime format the SP
                    // returns. The previous substr(...,11,5) assumed a fixed
                    // "YYYY-MM-DD HH:MM:SS" layout and silently produced wrong/empty
                    // times for other formats, causing valid check-ins/outs to be
                    // misclassified as "Belum check-out" / "Belum ada data".
                    $checkIn = null;
                    if (! empty($row->CheckIn)) {
                        try {
                            $checkIn = Carbon::parse((string) $row->CheckIn)->format('H:i');
                        } catch (\Throwable $e) {
                            $checkIn = null;
                        }
                    }

                    $checkOut = null;
                    if (! empty($row->CheckOut)) {
                        try {
                            $checkOut = Carbon::parse((string) $row->CheckOut)->format('H:i');
                        } catch (\Throwable $e) {
                            $checkOut = null;
                        }
                    }

                    $dateValue = trim((string) ($row->Tanggal ?? ''));
                    $date = null;
                    if ($dateValue !== '') {
                        $dateCandidate = strlen($dateValue) >= 10 ? substr($dateValue, 0, 10) : $dateValue;
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCandidate) === 1) {
                            $date = $dateCandidate;
                        }
                    }
                    if ($date === null) {
                        try {
                            $date = Carbon::parse($row->Tanggal)->format('Y-m-d');
                        } catch (\Throwable $e) {
                            $date = Carbon::now('Asia/Jakarta')->format('Y-m-d');
                        }
                    }

                    return [
                        'kode_karyawan' => strtoupper(trim((string) ($row->Kode_Karyawan ?? ''))),
                        'date' => $date,
                        'shift_name' => trim((string) ($row->Nama_Shift ?? 'NO SHIFT')),
                        'status_hari' => strtoupper(trim((string) ($row->StatusHari ?? 'WORK'))),
                        'jam_masuk' => isset($row->Jam_Masuk) ? substr((string) $row->Jam_Masuk, 0, 5) : null,
                        'jam_keluar' => isset($row->Jam_Keluar) ? substr((string) $row->Jam_Keluar, 0, 5) : null,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'late_minutes' => (int) round((float) ($row->RealLate ?? 0)),
                        'early_minutes' => (int) round((float) ($row->RealEarly ?? 0)),

                        // Tanpa ini atasan membaca kenyataan yang BERBEDA dari
                        // anggotanya: karyawan melihat dirinya terlambat sekali,
                        // atasannya melihat nol, untuk hari yang sama. Angka
                        // late_minutes tetap milik SP; ini keterangan di sebelahnya.
                        'keterlambatan' => KlasifikasiKeterlambatan::dari($row),
                    ];
                })
                ->groupBy('kode_karyawan')
                ->map(function (Collection $memberRows) {
                    return $memberRows
                        ->groupBy('date')
                        ->map(function (Collection $sameDateRows) {
                            return $sameDateRows
                                ->sortByDesc(fn (array $row) => $this->resolveAttendanceRowQualityScore($row))
                                ->first();
                        })
                        ->sortKeys()
                        ->values()
                        ->all();
                });

            Log::debug('Leader attendance SP profile', [
                'members_total' => count($memberCodes),
                'user_ids_raw' => $rawUserIds->count(),
                'user_ids_valid' => $userIds->count(),
                'rows_count' => count($rows),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'sp_ms' => $spMs,
            ]);
        } else {
            Log::warning('Leader attendance SP skipped: no valid UserID_Absen', [
                'members_total' => count($memberCodes),
                'user_ids_raw' => $rawUserIds->count(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);
        }

        // Pushdown rentang (sargable) menggantikan filter hilir CAST(Tanggal_Item AS DATE).
        // Range [startDate 00:00:00 .. endDate 23:59:59] menjaga kesetaraan utk kolom DATETIME.
        $approvedByCodeDate = DB::query()
            ->fromSub(
                (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)->forRequestersBetween(
                    $memberCodes,
                    $startDate->copy()->startOfDay()->toDateTimeString(),
                    $endDate->copy()->endOfDay()->toDateTimeString(),
                ),
                'tx',
            )
            ->where('Dashboard_Status', 'APPROVED')
            ->select('Kode_Karyawan', 'Jenis', 'Source_View', 'Json_Context', 'Tanggal_Item', 'Deskripsi')
            ->get()
            ->groupBy(
                fn ($item) => strtoupper(trim((string) $item->Kode_Karyawan)).
                    '|'.
                    Carbon::parse($item->Tanggal_Item)->format('Y-m-d'),
            );

        // Pre-resolve approval types into a lookup map to avoid repeated JSON parsing
        $approvedTypeMap = collect($approvedByCodeDate)
            ->mapWithKeys(fn ($items, $key) => [$key => $this->resolveApprovedType($items)])
            ->all();

        $pulangCepatKeys = collect();
        if ($includePulangCepat) {
            $pulangCepatKeys = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as det')
                ->join('Transaksi_Terlambat_Pulang_Cepat as head', 'head.No_Transaksi', '=', 'det.No_Transaksi')
                ->whereIn('det.Kode_Karyawan', $memberCodes)
                ->where('det.Jenis', 'pulangCepat')
                ->where('det.Flag_Approval', 'Y')
                ->whereNull('head.Status')
                ->whereBetween(DB::raw('CAST(COALESCE(det.Tanggal_Hari_Kerja, det.Tanggal_Masuk_Pulang) AS DATE)'), [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ])
                ->selectRaw(
                    "UPPER(LTRIM(RTRIM(det.Kode_Karyawan))) + '|' + CONVERT(varchar(10), CAST(COALESCE(det.Tanggal_Hari_Kerja, det.Tanggal_Masuk_Pulang) AS DATE), 23) as row_key",
                )
                ->pluck('row_key')
                ->flip();
        }

        $overtimeRows = collect();
        if ($includeOvertime && ! empty($memberCodes)) {
            // Ambil lembur sepanjang RENTANG PENUH yang diminta (cutoff), bukan
            // hanya 7 hari terakhir, agar total lembur Leader konsisten dengan
            // total lembur Personal anggota (rekonsiliasi LD-010). Weekly trend
            // tetap menyaring 7 harinya sendiri dari dataset ini.
            $overtimeStartDate = $startDate->copy();

            $overtimeRows = DB::table('Transaksi_Lembur as h')
                ->join('Transaksi_Lembur_Detail as d', function ($join) {
                    $join
                        ->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                        ->on('h.No_Transaksi', '=', 'd.No_Transaksi');
                })
                ->join('Transaksi_Lembur_Det as det', function ($join) {
                    $join
                        ->on('det.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                        ->on('det.No_Transaksi', '=', 'd.No_Transaksi')
                        ->on('det.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                        ->on('det.Urut_Detail', '=', 'd.Urut_Oto');
                })
                ->whereIn('d.Kode_Karyawan', $memberCodes)
                ->whereNull('h.Status')
                ->whereBetween(DB::raw('CAST(det.Tanggal_Lembur AS DATE)'), [
                    $overtimeStartDate->toDateString(),
                    $endDate->toDateString(),
                ])
                ->select(
                    'd.Kode_Karyawan',
                    'det.Tanggal_Lembur',
                    'd.Tanggal_Lembur_Dari',
                    'd.Tanggal_Lembur_Sampai',
                    'd.Jenis',
                )
                ->get()
                ->groupBy(fn ($item) => strtoupper(trim((string) $item->Kode_Karyawan)));
        }

        // Pre-index overtime by date per employee for O(1) lookups in weekly trend
        $overtimeByDate = collect();
        if ($overtimeRows->isNotEmpty()) {
            foreach ($overtimeRows as $kode => $records) {
                $grouped = collect($records)
                    ->groupBy(fn ($item) => Carbon::parse($item->Tanggal_Lembur)->format('Y-m-d'))
                    ->map(fn ($g) => $g->values()->all())
                    ->all();

                $overtimeByDate[$kode] = $grouped;
            }
        }

        $totalMs = (int) round((microtime(true) - $t0) * 1000);
        Log::debug('Leader attendance context profile', [
            'members_total' => count($memberCodes),
            'user_ids_valid' => $userIds->count(),
            'rows_by_code' => $rowsByCode->count(),
            'approved_groups' => $approvedByCodeDate->count(),
            'include_overtime' => $includeOvertime,
            'include_pulang_cepat' => $includePulangCepat,
            'minimal_row_map' => $minimalRowMap,
            'duration_ms' => $totalMs,
        ]);

        return [
            'rowsByCode' => $rowsByCode,
            'approvedByCodeDate' => $approvedByCodeDate,
            'approvedTypeMap' => $approvedTypeMap,
            'pulangCepatKeys' => $pulangCepatKeys,
            'overtimeRows' => $overtimeRows,
            'overtimeByDate' => $overtimeByDate->all(),
        ];
    }

    protected function buildTeamAttendanceSummaries(
        Collection $teamMembers,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        // Range dipakai PENUH sampai akhir cutoff (tidak di-clamp ke hari ini)
        // supaya pengajuan yang sudah di-approve untuk tanggal MENDATANG
        // (cuti/izin/sakit) tetap ikut terhitung — konsisten dengan dashboard
        // personal anggota yang juga memakai full cutoff range. Hari kerja di masa
        // depan tidak akan menjadi Alpha karena classifyAttendanceRow sudah
        // mengembalikan status 'not-started' (bukan 'absent') untuk tanggal > hari ini.
        if (! $startDate || ! $endDate) {
            $cutoffRange = $this->uDashReadModelService->getCutoffRange($today);
            $startDate = $cutoffRange['start_date']->copy();
            $endDate = $cutoffRange['end_date']->copy();
        } else {
            $startDate = $startDate->copy();
            $endDate = $endDate->copy();
        }
        $context = $this->loadAttendanceContext($teamMembers, $startDate, $endDate, true);
        $rowsByCode = $context['rowsByCode'];
        $approvedByCodeDate = $context['approvedByCodeDate'];
        $approvedTypeMap = $context['approvedTypeMap'] ?? [];
        $pulangCepatKeys = $context['pulangCepatKeys'];
        $overtimeRows = $context['overtimeRows'];
        $overtimeByDate = $context['overtimeByDate'] ?? [];
        $now = Carbon::now('Asia/Jakarta');

        $result = [];

        foreach ($teamMembers as $member) {
            $kode = strtoupper(trim((string) $member->Kode_Karyawan));
            $memberRows = collect($rowsByCode->get($kode, []))->keyBy('date');
            $todayRow = $memberRows->get($today->toDateString());

            $workingRows = $memberRows->filter(function ($row) {
                $statusHari = strtoupper((string) ($row['status_hari'] ?? 'WORK'));
                $shiftName = strtoupper(trim((string) ($row['shift_name'] ?? 'NO SHIFT')));

                return ! str_starts_with($statusHari, 'OFF') && $shiftName !== 'OFF' && $shiftName !== 'NO SHIFT';
            });

            $alphaCount = 0;
            $noCheckinCount = 0;
            $lateCount = 0;
            $lateMinutes = 0;
            $permissionDays = 0;
            $sickDays = 0;
            $leaveDays = 0;
            $noCheckoutCount = 0;
            $earlyOutCount = 0;
            $issues = [];

            foreach ($memberRows as $date => $row) {
                $approvedType = $approvedTypeMap[$kode.'|'.$date] ?? null;
                $isWorking =
                    ! str_starts_with(strtoupper((string) ($row['status_hari'] ?? 'WORK')), 'OFF') &&
                    ! in_array(strtoupper(trim((string) ($row['shift_name'] ?? 'NO SHIFT'))), ['OFF', 'NO SHIFT'], true);
                $hasCheckIn = ! empty($row['check_in']);
                $hasCheckOut = ! empty($row['check_out']);
                // Pulang cepat hanya jika benar-benar absen (bukan Alpha atau No-Checkout).
                $isEarlyOut = $hasCheckIn && $hasCheckOut && (int) ($row['early_minutes'] ?? 0) > 0;
                $rowDate = Carbon::parse($date, 'Asia/Jakarta')->startOfDay();
                // Tanggal di masa depan tidak boleh dievaluasi seolah harinya sudah
                // berakhir — kalau tidak, hari kerja mendatang yang belum ada
                // check-in akan salah dihitung sebagai Alpha. Pakai $now apa adanya
                // supaya classifyAttendanceRow mengembalikan 'not-started'.
                $isFutureDate = $rowDate->greaterThan($today);
                $classification = $this->classifyAttendanceRow(
                    $row,
                    $rowDate->copy(),
                    $rowDate->isSameDay($today) ? $now : ($isFutureDate ? $now : $rowDate->copy()->endOfDay()),
                );

                if ($approvedType === 'IZIN') {
                    $permissionDays++;
                } elseif ($approvedType === 'SAKIT') {
                    $sickDays++;
                } elseif ($approvedType === 'CUTI') {
                    $leaveDays++;
                } elseif ($isFutureDate) {
                    // Hari kerja mendatang tanpa approval belum boleh dinilai
                    // (bukan Alpha / No-Checkin) — biarkan terlewat.
                } elseif ($isWorking && ($classification['status'] ?? '') === 'absent') {
                    // Kedua check-in dan check-out tidak ada — ini Alpha murni
                    $alphaCount++;
                    $issues[] = [
                        'id' => 'alpha-'.$date,
                        'tone' => 'danger',
                        'icon' => 'bi bi-person-x-fill',
                        'title' => ($classification['label'] ?? 'Tidak Hadir').
                            ' pada '.
                            Carbon::parse($date)->translatedFormat('d M Y'),
                        'description' => 'Tidak ada check-in dan check-out pada hari kerja.',
                    ];
                } elseif ($isWorking && ($classification['status'] ?? '') === 'no-checkin') {
                    // Hanya check-in yang tidak ada, check-out ada — ini No Checkin
                    $noCheckinCount++;
                    $issues[] = [
                        'id' => 'no-checkin-'.$date,
                        'tone' => 'danger',
                        'icon' => 'bi bi-box-arrow-in-right',
                        'title' => 'Tidak Check In pada '.Carbon::parse($date)->translatedFormat('d M Y'),
                        'description' => 'Ada check-out tapi tidak ada check-in pada hari kerja.',
                    ];
                }

                // Cacah HARI dan jumlah MENIT sengaja tidak lagi memakai syarat
                // yang sama.
                //
                // Harinya dihitung bila orangnya memang terlambat — termasuk
                // yang dimaafkan toleransi — supaya atasan melihat kejadian yang
                // sama dengan anggotanya. Menitnya tetap dari late_minutes:
                // keputusan pemilik 4 Sep 2026, hanya yang MELEWATI toleransi
                // yang dihitung sebagai menit keterlambatan.
                //
                // Akibatnya satu hari bisa terhitung 1 kejadian dengan 0 menit.
                // Itu memang yang dimaksud, bukan kekeliruan.
                if ($this->barisTerlambat($row, strtolower((string) ($classification['status'] ?? '')))) {
                    $lateCount++;
                }

                if ((int) ($row['late_minutes'] ?? 0) > 0) {
                    $lateMinutes += (int) $row['late_minutes'];
                }

                if (($classification['status'] ?? '') === 'no-checkout') {
                    $noCheckoutCount++;
                    $issues[] = [
                        'id' => 'no-checkout-'.$date,
                        'tone' => 'warning',
                        'icon' => 'bi bi-box-arrow-right',
                        'title' => 'Belum Checkout pada '.Carbon::parse($date)->translatedFormat('d M Y'),
                        'description' => 'Ada check-in tapi tidak ada check-out pada hari kerja.',
                    ];
                }

                if ($isEarlyOut) {
                    $earlyOutCount++;
                    $issues[] = [
                        'id' => 'early-out-'.$date,
                        'tone' => 'orange',
                        'icon' => 'bi bi-door-open',
                        'title' => 'Pulang Cepat pada '.Carbon::parse($date)->translatedFormat('d M Y'),
                        'description' => 'Absen pulang lebih awal dari jadwal shift.',
                    ];
                }
            }

            $approvedToday = $approvedTypeMap[$kode.'|'.$today->toDateString()] ?? null;
            $todayClassification = $this->classifyAttendanceRow($todayRow, $today, $now);
            $todayStatus = $todayClassification['status'];
            $todayLabel = $todayClassification['label'];
            if ($approvedToday === 'CUTI') {
                $todayStatus = 'cuti';
                $todayLabel = 'Cuti';
            } elseif ($approvedToday === 'SAKIT') {
                $todayStatus = 'sick';
                $todayLabel = 'Sakit';
            } elseif ($approvedToday === 'IZIN') {
                $todayStatus = 'permission';
                $todayLabel = 'Izin';
            } elseif (str_starts_with(strtoupper((string) ($todayRow['status_hari'] ?? 'WORK')), 'OFF')) {
                $todayStatus = 'leave';
                $todayLabel = 'OFF';
            }

            $memberOvertimeByDate = $overtimeByDate[$kode] ?? [];
            $weeklyTrend = $this->buildWeeklyOvertimeTrend($memberOvertimeByDate, $today);
            // Total lembur cutoff penuh (konsisten dgn personal) — bukan hanya 7 hari trend.
            $overtimeTotals = $this->buildOvertimeBreakdownTotals($memberOvertimeByDate);
            $dailyLog = $this->buildDailyLog(
                $memberRows,
                $approvedTypeMap,
                $kode,
                $today->copy()->subDays(6),
                $today,
                $pulangCepatKeys,
                $memberOvertimeByDate,
            );

            $result[$kode] = [
                'todayStatus' => $todayStatus,
                'todayLabel' => $todayLabel,
                // Konsisten dgn Drawer B (working_total): true bila hari ini anggota
                // punya shift kerja (bukan OFF / NO SHIFT). Dipakai sbg denominator
                // kartu "Status Hari Ini" (anggota bertugas hari ini).
                'isWorkingToday' => $this->rowIsWorkingShift($todayRow),
                'checkIn' => $todayRow['check_in'] ?? null,
                'checkOut' => $todayRow['check_out'] ?? null,
                'hasCheckIn' => ! empty($todayRow['check_in']),
                'hasCheckOut' => ! empty($todayRow['check_out']),
                'workDays' => $workingRows->count(),
                'alphaCount' => $alphaCount,
                'noCheckinCount' => $noCheckinCount,
                'absentCount' => $alphaCount,
                'lateCount' => $lateCount,
                'lateMinutes' => $lateMinutes,
                'earlyOutCount' => $earlyOutCount,
                'overtimeHours' => $overtimeTotals['total'],
                'permissionDays' => $permissionDays,
                'sickDays' => $sickDays,
                'leaveDays' => $leaveDays,
                'noCheckoutCount' => $noCheckoutCount,
                'issueCount' => count($issues),
                'issues' => collect($issues)->take(4)->values()->all(),
                'dailyLog' => $dailyLog,
                'shift_name' => $todayRow['shift_name'] ?? 'No Shift',
                'shift_time' => ! empty($todayRow['jam_masuk']) && ! empty($todayRow['jam_keluar'])
                        ? $todayRow['jam_masuk'].' - '.$todayRow['jam_keluar']
                        : '-',
                'overtime' => [
                    'totalHours' => $overtimeTotals['total'],
                    'beforeWorkHours' => 0,
                    'afterWorkHours' => $overtimeTotals['afterWork'],
                    'holidayHours' => $overtimeTotals['holiday'],
                    'weeklyTrend' => $weeklyTrend,
                ],
            ];
        }

        return $result;
    }

    /**
     * Ambil tanggal merah (hari libur nasional / perayaan) pada rentang minggu.
     *
     * @return array<string, string> Map 'YYYY-MM-DD' => Nama_Hari_Libur
     */
    protected function loadHolidaysByDate(Carbon $startDate, Carbon $endDate): array
    {
        try {
            return DB::table('HRIS_Hari_Libur')
                ->where('Kode_Perusahaan', '001')
                ->whereBetween('Tanggal', [$startDate->toDateString(), $endDate->toDateString()])
                ->get(['Tanggal', 'Nama_Hari_Libur'])
                ->reduce(function (array $carry, $row) {
                    if (empty($row->Tanggal)) {
                        return $carry;
                    }

                    try {
                        $date = Carbon::parse($row->Tanggal)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        return $carry;
                    }

                    $carry[$date] = trim((string) ($row->Nama_Hari_Libur ?? '')) ?: 'Hari Libur';

                    return $carry;
                }, []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function buildDailyLog(
        Collection $memberRows,
        $approvedTypeMap,
        string $kodeKaryawan,
        Carbon $startDate,
        Carbon $endDate,
        $pulangCepatKeys = null,
        $overtimeByDate = [],
        array $holidaysByDate = [],
    ): array {
        $now = Carbon::now('Asia/Jakarta');
        $start = $startDate->copy();
        $items = [];

        if ($pulangCepatKeys === null) {
            $pulangCepatKeys = collect();
        }

        while ($start->lte($endDate)) {
            $date = $start->toDateString();
            $row = $memberRows->get($date);
            $approvedType = $approvedTypeMap[$kodeKaryawan.'|'.$date] ?? null;

            $classification = $this->classifyAttendanceRow(
                $row,
                $start->copy(),
                $this->resolveAttendanceEvaluationTime($start->copy(), $now),
            );

            // Build pengajuan info
            $pengajuan = null;
            if ($approvedType === 'CUTI') {
                $classification = ['status' => 'cuti', 'label' => 'Cuti', 'tone' => 'info'];
                $pengajuan = ['type' => 'CUTI', 'label' => 'Cuti', 'status' => 'APPROVED'];
            } elseif ($approvedType === 'SAKIT') {
                $classification = ['status' => 'sakit', 'label' => 'Sakit', 'tone' => 'info'];
                $pengajuan = ['type' => 'SAKIT', 'label' => 'Sakit', 'status' => 'APPROVED'];
            } elseif ($approvedType === 'IZIN') {
                $classification = ['status' => 'permission', 'label' => 'Izin', 'tone' => 'info'];
                $pengajuan = ['type' => 'IZIN', 'label' => 'Izin', 'status' => 'APPROVED'];
            } elseif (str_starts_with(strtoupper((string) ($row['status_hari'] ?? 'WORK')), 'OFF')) {
                $classification = ['status' => 'leave', 'label' => 'OFF', 'tone' => 'default'];
            }

            // Resolve overtime hours for this day
            $dayOvertimeHours = 0;
            if (is_array($overtimeByDate)) {
                $dayOtRecords = $overtimeByDate[$date] ?? [];
            } elseif ($overtimeByDate instanceof Collection) {
                $dayOtRecords = $overtimeByDate->get($date, []);
            } else {
                $dayOtRecords = [];
            }
            if (! empty($dayOtRecords)) {
                $dayOvertimeHours = round(
                    collect($dayOtRecords)->sum(function ($item) {
                        if (empty($item->Tanggal_Lembur_Dari) || empty($item->Tanggal_Lembur_Sampai)) {
                            return 0;
                        }

                        return max(
                            Carbon::parse($item->Tanggal_Lembur_Dari)->diffInMinutes(
                                Carbon::parse($item->Tanggal_Lembur_Sampai),
                            ),
                            0,
                        ) / 60;
                    }),
                    1,
                );
            }

            $hasCheckIn = ! empty($row['check_in']);
            $hasCheckOut = ! empty($row['check_out']);
            // Pulang cepat hanya jika benar-benar absen (bukan Alpha atau No-Checkout).
            $isEarlyOut = $hasCheckIn && $hasCheckOut && (int) ($row['early_minutes'] ?? 0) > 0;

            if ($isEarlyOut) {
                if ($classification['tone'] === 'success') {
                    $classification['tone'] = 'warning';
                    $classification['label'] = 'Pulang Cepat';
                    $classification['status'] = 'early-out';
                } elseif ($classification['status'] === 'late') {
                    $classification['label'] = 'Telat & P. Cepat';
                    $classification['status'] = 'late-early-out';
                }
            }

            // Build shift time string
            $shiftTime = null;
            if (! empty($row['jam_masuk']) && ! empty($row['jam_keluar'])) {
                $shiftTime = $row['jam_masuk'].' - '.$row['jam_keluar'];
            }

            // Tanggal merah (hari libur nasional/perayaan).
            // Aturan tampilan mini calendar:
            // 1. Tanggal merah + karyawan OFF  -> tandai Hari Libur (merah) + nama perayaan.
            // 2. Tanggal merah + tetap kerja/lembur -> biarkan status kerja normal (tidak di-mark libur),
            //    nama perayaan tetap dilampirkan agar bisa ditampilkan di popover.
            // 3. Tanggal biasa + karyawan OFF -> OFF biasa (tidak berubah).
            $holidayName = $holidaysByDate[$date] ?? null;
            $isHoliday = false;
            if ($holidayName !== null) {
                $statusKey = $classification['status'] ?? '';
                // "Tidak bekerja" = OFF/leave, atau hari yang memang belum/tidak ada aktivitas kerja.
                $isNonWorkingDay = in_array(
                    $statusKey,
                    ['leave', 'not-started', 'no-data'],
                    true,
                ) && ! $hasCheckIn && ! $hasCheckOut;

                if ($isNonWorkingDay) {
                    $isHoliday = true;
                    $classification = [
                        'status' => 'holiday',
                        'label' => 'Hari Libur',
                        'tone' => 'holiday',
                    ];
                }
            }

            $items[] = [
                'day' => $start->translatedFormat('D'),
                'dayKey' => (int) $start->format('N'),
                'date' => $start->translatedFormat('d M'),
                'fullDate' => $date,
                'label' => $classification['label'],
                'tone' => $classification['tone'],
                'status' => $classification['status'] ?? '',
                'isHoliday' => $isHoliday,
                'holidayName' => $holidayName,
                'checkIn' => $row['check_in'] ?? null,
                'checkOut' => $row['check_out'] ?? null,
                'shiftName' => $row['shift_name'] ?? null,
                'shiftTime' => $shiftTime,
                'lateMinutes' => (int) ($row['late_minutes'] ?? 0),
                'isEarlyOut' => $isEarlyOut,
                'earlyMinutes' => (int) ($row['early_minutes'] ?? 0),
                'overtimeHours' => $dayOvertimeHours,
                'pengajuan' => $pengajuan,
            ];

            $start->addDay();
        }

        return $items;
    }

    /**
     * Load weekly calendar data for a specific team member.
     * Returns enriched daily attendance for a Sun–Sat week containing the given date.
     */
    public function getMemberWeeklyCalendar(string $leaderKode, string $memberKode, ?string $weekDate = null): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $anchor = $weekDate ? Carbon::parse($weekDate, 'Asia/Jakarta')->startOfDay() : $today->copy();

        // Calculate Sunday–Saturday for this week
        $weekStart = $anchor->copy()->startOfWeek(Carbon::SUNDAY);
        $weekEnd = $weekStart->copy()->addDays(6); // Saturday

        // Load attendance context only up to today (SP has no future data)
        $dataEnd = $weekEnd->copy()->lte($today) ? $weekEnd->copy() : $today->copy();

        // Find member
        $teamMembers = DB::table('HRIS_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan')
            ->where('kt.Kode_Karyawan_Team', $leaderKode)
            ->where('kt.Kode_Karyawan', $memberKode)
            ->select('k.Kode_Karyawan', 'k.Nama', 'k.UserID_Absen')
            ->get();

        if ($teamMembers->isEmpty()) {
            return [
                'days' => [],
                'week' => [
                    'start' => $weekStart->toDateString(),
                    'end' => $weekEnd->toDateString(),
                    'label' => $weekStart->translatedFormat('d M').' – '.$weekEnd->translatedFormat('d M Y'),
                ],
                'weekNumber' => (int) $anchor->format('W'),
            ];
        }

        $context = $this->loadAttendanceContext($teamMembers, $weekStart, $dataEnd, true);
        $kode = strtoupper(trim($memberKode));
        $memberRows = collect($context['rowsByCode']->get($kode, []))->keyBy('date');
        $approvedTypeMap = $context['approvedTypeMap'] ?? [];
        $pulangCepatKeys = $context['pulangCepatKeys'] ?? collect();
        $memberOvertimeByDate = ($context['overtimeByDate'] ?? [])[$kode] ?? [];

        // Tanggal merah (hari libur nasional/perayaan) untuk minggu ini.
        // Map: 'YYYY-MM-DD' => Nama_Hari_Libur. Dipakai untuk membedakan
        // OFF biasa (off mingguan) dari Hari Libur (tanggal merah).
        $holidaysByDate = $this->loadHolidaysByDate($weekStart, $weekEnd);

        // Build daily log for the FULL week (Sun-Sat), including future days
        $days = $this->buildDailyLog(
            $memberRows,
            $approvedTypeMap,
            $kode,
            $weekStart,
            $weekEnd,
            $pulangCepatKeys,
            $memberOvertimeByDate,
            $holidaysByDate,
        );

        // Determine navigation boundaries
        $canPrev = true; // Can always go back
        $canNext = $weekEnd->lt($today); // Can only go forward if week end is before today

        return [
            'days' => $days,
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
                'label' => $weekStart->translatedFormat('d M').' – '.$weekEnd->translatedFormat('d M Y'),
            ],
            'weekNumber' => (int) $anchor->format('W'),
            'canPrev' => $canPrev,
            'canNext' => $canNext,
            'todayDate' => $today->toDateString(),
        ];
    }

    /**
     * Ambil seluruh log scan mentah (Check In/Out) seorang member pada satu tanggal.
     * Berbeda dengan kalender mingguan yang hanya menampilkan check-in & check-out final,
     * method ini mengembalikan SEMUA waktu scan (checktime) di hari tersebut.
     */
    public function getMemberDayCheckTimeLogs(string $leaderKode, string $memberKode, string $date): array
    {
        $kode = strtoupper(trim($memberKode));
        $targetDate = Carbon::parse($date, 'Asia/Jakarta')->startOfDay();

        // Pastikan member benar-benar bagian dari tim leader ini
        $member = DB::table('HRIS_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan')
            ->where('kt.Kode_Karyawan_Team', $leaderKode)
            ->where('kt.Kode_Karyawan', $kode)
            ->select('k.Kode_Karyawan', 'k.Nama', 'k.UserID_Absen')
            ->first();

        if (! $member) {
            return [
                'authorized' => false,
                'date' => $targetDate->toDateString(),
                'member' => null,
                'logs' => [],
            ];
        }

        // Coba sertakan nama mesin (tabel Machines), tapi jangan gagalkan request bila
        // tabel tersebut tidak tersedia pada koneksi ini — fallback tanpa join.
        $query = DB::table('CHECKINOUT as c')
            ->join('Karyawan as b', 'c.USERID', '=', 'b.UserID_Absen')
            ->where('b.Kode_Karyawan', $kode)
            ->whereDate('c.CHECKTIME', $targetDate->toDateString())
            ->orderBy('c.CHECKTIME', 'asc');

        try {
            $rows = (clone $query)
                ->leftJoin('Machines as m', 'c.sensorid', '=', 'm.MachineNumber')
                ->select('c.CHECKTIME', 'c.CHECKTYPE', 'm.MachineAlias')
                ->get();
        } catch (\Throwable $e) {
            $rows = $query->select('c.CHECKTIME', 'c.CHECKTYPE')->get();
        }

        $logs = $rows
            ->map(function ($row) {
                $time = ! empty($row->CHECKTIME) ? Carbon::parse($row->CHECKTIME)->format('H:i:s') : '-';
                $type = strtoupper(trim((string) ($row->CHECKTYPE ?? '')));

                $typeKey = 'activity';
                $typeLabel = 'Absen';
                if ($type === 'I') {
                    $typeKey = 'checkin';
                    $typeLabel = 'Check In';
                } elseif ($type === 'O') {
                    $typeKey = 'checkout';
                    $typeLabel = 'Check Out';
                }

                return [
                    'time' => $time,
                    'short_time' => substr($time, 0, 5),
                    'type' => $typeKey,
                    'type_label' => $typeLabel,
                    'machine' => $row->MachineAlias ?? null ?: 'Mesin Absen',
                    'checktime' => ! empty($row->CHECKTIME) ? Carbon::parse($row->CHECKTIME)->toDateTimeString() : null,
                ];
            })
            ->values()
            ->all();

        return [
            'authorized' => true,
            'date' => $targetDate->toDateString(),
            'date_label' => $targetDate->translatedFormat('l, d F Y'),
            'member' => [
                'kode' => $member->Kode_Karyawan,
                'nama' => $member->Nama,
            ],
            'logs' => $logs,
            'total' => count($logs),
        ];
    }

    /**
     * Klasifikasi status absensi sisi Leader.
     *
     * CATATAN KONSISTENSI (D-2): aturan di sini harus selaras dengan classifier
     * personal HomepagePersonalDashboardService::buildAttendanceRecordState()
     * agar count HCIS team cocok dengan dashboard masing-masing anggota:
     * - Deteksi OFF: status_hari LIKE 'OFF%' atau shift_name IN ('OFF','NO SHIFT').
     * - Alpha murni (status 'absent'): hari kerja tanpa check-in & check-out
     *   setelah jam mulai shift; dibedakan dari 'no-checkin' (ada check-out saja).
     * - No-checkout: grace 10 menit setelah jam pulang shift sebelum ditandai.
     * - Pulang cepat dihitung terpisah dari kolom SP RealEarly (early_minutes).
     */
    protected function classifyAttendanceRow(?array $row, Carbon $date, Carbon $now): array
    {
        $todayStart = $now->copy()->startOfDay();
        $isFuture = $date->greaterThan($todayStart);

        if (! $row) {
            // Future day with no data → Belum Dimulai
            if ($isFuture) {
                return [
                    'status' => 'not-started',
                    'label' => 'Belum Dimulai',
                    'tone' => 'secondary',
                ];
            }

            return [
                'status' => 'no-data',
                'label' => 'Belum Ada Data',
                'tone' => 'default',
            ];
        }

        $shiftName = strtoupper(trim((string) ($row['shift_name'] ?? 'NO SHIFT')));
        $statusHari = strtoupper(trim((string) ($row['status_hari'] ?? 'WORK')));
        $jamMasuk = $this->parseAttendanceTime($date, $row['jam_masuk'] ?? null);
        $jamKeluar = $this->parseAttendanceTime($date, $row['jam_keluar'] ?? null);

        if ($jamMasuk !== null && $jamKeluar !== null && $jamKeluar->lessThanOrEqualTo($jamMasuk)) {
            $jamKeluar = $jamKeluar->copy()->addDay();
        }

        $hasCheckIn = ! empty($row['check_in']);
        $hasCheckOut = ! empty($row['check_out']);
        $lateMinutes = (int) ($row['late_minutes'] ?? 0);

        if (str_starts_with($statusHari, 'OFF') || in_array($shiftName, ['OFF', 'NO SHIFT'], true)) {
            return [
                'status' => 'leave',
                'label' => 'OFF',
                'tone' => 'default',
            ];
        }

        if (! $hasCheckIn && ! $hasCheckOut) {
            if ($jamMasuk !== null) {
                if (
                    $date->greaterThan($now->copy()->startOfDay()) ||
                    ($date->isSameDay($now) && $now->lessThan($jamMasuk))
                ) {
                    return [
                        'status' => 'not-started',
                        'label' => 'Belum Dimulai',
                        'tone' => 'secondary',
                    ];
                }
            }
        }

        if ($hasCheckIn && $hasCheckOut) {
            if ($lateMinutes > 0) {
                return [
                    'status' => 'late',
                    'label' => 'Terlambat',
                    'tone' => 'warning',
                ];
            }

            return [
                'status' => 'present',
                'label' => 'Hadir',
                'tone' => 'success',
            ];
        }

        if ($hasCheckIn && ! $hasCheckOut) {
            // Selaras dengan classifier personal (buildAttendanceRecordState):
            // "Sedang Bekerja" hanya berlaku untuk HARI INI yang sebenarnya dan
            // belum lewat jam pulang + grace 10 menit; selebihnya = Belum Checkout.
            //
            // PENTING: pakai waktu NYATA (Carbon::now), bukan parameter $now.
            // Untuk tanggal lampau, pemanggil mengirim $now = endOfDay(tanggal itu)
            // sebagai waktu evaluasi sintetis; pada shift malam (jamKeluar
            // menyeberang ke hari berikutnya) waktu sintetis itu masih < jam pulang
            // sehingga hari lampau salah dianggap "Sedang Bekerja" alih-alih
            // Belum Checkout.
            $realNow = Carbon::now('Asia/Jakarta');
            if (
                $jamKeluar !== null &&
                $date->isSameDay($realNow) &&
                $realNow->lessThan($jamKeluar->copy()->addMinutes(10))
            ) {
                return [
                    'status' => 'working',
                    'label' => 'Sedang Bekerja',
                    'tone' => 'working',
                ];
            }

            return [
                'status' => 'no-checkout',
                'label' => 'Belum Checkout',
                'tone' => 'warning',
            ];
        }

        if (! $hasCheckIn && $hasCheckOut) {
            return [
                'status' => 'no-checkin',
                'label' => 'Tidak Check In',
                'tone' => 'danger',
            ];
        }

        if (! $hasCheckIn && ! $hasCheckOut) {
            if ($jamMasuk === null) {
                return [
                    'status' => 'no-data',
                    'label' => 'Belum Ada Data',
                    'tone' => 'default',
                ];
            }

            $minutesSinceStart = $jamMasuk->diffInMinutes($now, false);

            if ($date->greaterThan($now->copy()->startOfDay()) || ($date->isSameDay($now) && $minutesSinceStart < 0)) {
                return [
                    'status' => 'not-started',
                    'label' => 'Belum Dimulai',
                    'tone' => 'secondary',
                ];
            }

            if ($minutesSinceStart < 0) {
                return [
                    'status' => 'not-started',
                    'label' => 'Belum Dimulai',
                    'tone' => 'secondary',
                ];
            }

            // Hari berjalan yang sudah lewat jam masuk tapi belum lewat jam pulang
            // + grace 10 menit BELUM boleh dianggap Alpha — anggota masih punya
            // kesempatan check-in. Selaras dengan classifier personal
            // (buildAttendanceRecordState) agar count Alpha/No-Checkin team
            // konsisten dengan dashboard personal anggota.
            //
            // PENTING: gunakan waktu NYATA (Carbon::now), bukan parameter $now.
            // Untuk tanggal lampau, pemanggil mengirim $now = endOfDay(tanggal itu)
            // sebagai waktu evaluasi sintetis; kalau dipakai di sini, shift malam
            // (jamKeluar menyeberang ke hari berikutnya) akan salah dianggap masih
            // dalam grace dan jatuh ke No-Checkin alih-alih Alpha.
            $realNow = Carbon::now('Asia/Jakarta');
            if (
                $date->isSameDay($realNow) &&
                $jamKeluar !== null &&
                $realNow->lessThan($jamKeluar->copy()->addMinutes(10))
            ) {
                return [
                    'status' => 'no-checkin',
                    'label' => 'Tidak Check In',
                    'tone' => 'danger',
                ];
            }

            return [
                'status' => 'absent',
                'label' => 'Tidak Hadir',
                'tone' => 'danger',
            ];
        }

        if ($jamMasuk === null) {
            return [
                'status' => 'no-data',
                'label' => 'Belum Ada Data',
                'tone' => 'default',
            ];
        }

        $minutesSinceStart = $jamMasuk->diffInMinutes($now, false);

        if ($date->greaterThan($now->copy()->startOfDay()) || ($date->isSameDay($now) && $minutesSinceStart < 0)) {
            return [
                'status' => 'not-started',
                'label' => 'Belum Dimulai',
                'tone' => 'secondary',
            ];
        }

        if ($minutesSinceStart < 0) {
            return [
                'status' => 'not-started',
                'label' => 'Belum Dimulai',
                'tone' => 'secondary',
            ];
        }

        if ($minutesSinceStart > 60) {
            return [
                'status' => 'no-checkin',
                'label' => 'Tidak Check In',
                'tone' => 'danger',
            ];
        }

        return [
            'status' => 'late',
            'label' => 'Terlambat',
            'tone' => 'warning',
        ];
    }

    protected function buildAttendanceIssueTags(array $classification, ?array $row): array
    {
        $tone = strtolower((string) ($classification['tone'] ?? ''));
        if (! in_array($tone, ['danger', 'warning', 'orange'], true)) {
            return [];
        }

        $status = strtolower((string) ($classification['status'] ?? 'issue'));
        $label = trim((string) ($classification['label'] ?? 'Issue'));
        $meta = null;

        if ($status === 'late') {
            $lateMinutes = (int) ($row['late_minutes'] ?? 0);
            if ($lateMinutes > 0) {
                $meta = $lateMinutes.' menit';
            }
        } elseif ($status === 'no-checkout' && ! empty($row['check_in'])) {
            $meta = 'Check-in '.$row['check_in'];
        } elseif ($status === 'no-checkin' && ! empty($row['jam_masuk'])) {
            $meta = 'Shift masuk '.$row['jam_masuk'];
        }

        return [
            [
                'key' => $status !== '' ? $status : 'issue',
                'label' => $label !== '' ? $label : 'Issue',
                'tone' => $tone,
                'meta' => $meta,
            ],
        ];
    }

    protected function resolveAttendanceEvaluationTime(Carbon $date, Carbon $currentTime): Carbon
    {
        if ($date->greaterThan($currentTime->copy()->startOfDay())) {
            return $currentTime->copy();
        }

        if ($date->isSameDay($currentTime)) {
            return $currentTime->copy();
        }

        return $date->copy()->endOfDay();
    }

    protected function resolveAttendanceRowQualityScore(array $row): int
    {
        $score = 0;
        $hasCheckIn = ! empty($row['check_in']);
        $hasCheckOut = ! empty($row['check_out']);
        $shiftName = strtoupper(trim((string) ($row['shift_name'] ?? '')));
        $statusHari = strtoupper(trim((string) ($row['status_hari'] ?? '')));

        if ($hasCheckIn && $hasCheckOut) {
            $score += 100;
        } elseif ($hasCheckIn || $hasCheckOut) {
            $score += 75;
        }

        if (! empty($row['jam_masuk']) || ! empty($row['jam_keluar'])) {
            $score += 20;
        }

        if (! in_array($shiftName, ['', 'NO SHIFT'], true)) {
            $score += 15;
        }

        if (str_starts_with($statusHari, 'OFF') || $shiftName === 'OFF') {
            $score += 10;
        }

        if ((int) ($row['late_minutes'] ?? 0) > 0) {
            $score += 5;
        }

        return $score;
    }

    protected function parseAttendanceTime(Carbon $date, ?string $time): ?Carbon
    {
        $value = trim((string) $time);
        if ($value === '' || $value === '--:--') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        try {
            return Carbon::parse($date->format('Y-m-d').' '.$value, 'Asia/Jakarta');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildWeeklyOvertimeTrend($overtimeByDate, Carbon $today): array
    {
        $trend = [];
        $start = $today->copy()->subDays(6);

        while ($start->lte($today)) {
            $dateKey = $start->toDateString();

            if (is_array($overtimeByDate)) {
                $dayRecords = $overtimeByDate[$dateKey] ?? [];
            } elseif ($overtimeByDate instanceof Collection) {
                $dayRecords = $overtimeByDate->get($dateKey, []);
            } else {
                $dayRecords = [];
            }

            $hours = collect($dayRecords)->sum(function ($item) {
                if (empty($item->Tanggal_Lembur_Dari) || empty($item->Tanggal_Lembur_Sampai)) {
                    return 0;
                }

                return round(
                    max(
                        Carbon::parse($item->Tanggal_Lembur_Dari)->diffInMinutes(
                            Carbon::parse($item->Tanggal_Lembur_Sampai),
                        ),
                        0,
                    ) / 60,
                    1,
                );
            });

            $trend[] = [
                'day' => $start->translatedFormat('D'),
                'hours' => round($hours, 1),
            ];

            $start->addDay();
        }

        return $trend;
    }

    /**
     * Total lembur anggota sepanjang SELURUH rentang yang dimuat (cutoff penuh),
     * dipakai untuk total leader agar konsisten dengan total personal anggota.
     * Perhitungan jam identik dengan personal buildOvertimeSummary &
     * buildWeeklyOvertimeTrend: diffInMinutes(Dari, Sampai) / 60.
     *
     * @param  array<string, array<int, object>>  $overtimeByDate  date => records
     * @return array{total: float, holiday: float, afterWork: float}
     */
    protected function buildOvertimeBreakdownTotals($overtimeByDate): array
    {
        $total = 0.0;
        $holiday = 0.0;
        $afterWork = 0.0;

        $dates = $overtimeByDate instanceof Collection ? $overtimeByDate->all() : (array) $overtimeByDate;
        foreach ($dates as $records) {
            foreach ((array) $records as $item) {
                $dari = $item->Tanggal_Lembur_Dari ?? null;
                $sampai = $item->Tanggal_Lembur_Sampai ?? null;
                if (empty($dari) || empty($sampai)) {
                    continue;
                }
                $hours = round(max(Carbon::parse($dari)->diffInMinutes(Carbon::parse($sampai)), 0) / 60, 1);
                $total += $hours;

                $jenis = strtoupper(trim((string) ($item->Jenis ?? '')));
                if (str_contains($jenis, 'LIBUR') || str_contains($jenis, 'HOLIDAY') || str_contains($jenis, 'OFF')) {
                    $holiday += $hours;
                } else {
                    $afterWork += $hours;
                }
            }
        }

        return [
            'total' => round($total, 1),
            'holiday' => round($holiday, 1),
            'afterWork' => round($afterWork, 1),
        ];
    }

    protected function buildApprovalItems(
        Collection $teamMembers,
        bool $includeExpired = false,
        ?string $approverKode = null,
        ?bool $forceRestrictToMembers = null,
    ): array {
        $memberCodes = $teamMembers->pluck('Kode_Karyawan')->map(fn ($kode) => strtoupper(trim((string) $kode)))->all();
        $approverKode = $approverKode !== null ? strtoupper(trim($approverKode)) : null;
        $hasApprover = $approverKode !== null && $approverKode !== '';

        // Antrian approval berbasis approver: pengajuan harus muncul untuk approver
        // yang sah walau pemohon tidak ada di HRIS_Karyawan_Team. Jika tidak ada
        // approver (konteks lama), tetap dibatasi ke anggota tim.
        if (! $hasApprover && empty($memberCodes)) {
            return [];
        }

        // Default: mode antrian bila ada approver (dedupe per No_Transaksi). Caller bisa
        // MEMAKSA mode per-anggota (mis. drawer detail anggota) untuk melihat pengajuan
        // milik anggota TSB yang menunggu approver ini — forRequesters([anggota]) tetap
        // difilter Current_Approver = approverKode di dalam loadGroupedApprovalQueueRows.
        $restrictToMembers = $forceRestrictToMembers ?? ! $hasApprover;

        $rows = $this->loadGroupedApprovalQueueRows($memberCodes, $approverKode, $restrictToMembers)
            ->sortByDesc(fn ($row) => (string) ($row->Tanggal_Request ?? ($row->Source_Created_At ?? '')))
            ->values();

        // Pemohon di luar tim leader tidak ada di $teamMembers, jadi nama-nya
        // diambil langsung dari master Karyawan supaya tetap tampil benar.
        $outsiderCodes = $rows
            ->pluck('Kode_Karyawan')
            ->map(fn ($kode) => strtoupper(trim((string) $kode)))
            ->reject(fn ($kode) => $kode === '' || in_array($kode, $memberCodes, true))
            ->unique()
            ->values()
            ->all();
        $outsiderNames = empty($outsiderCodes)
            ? collect()
            : DB::table('Karyawan')->whereIn('Kode_Karyawan', $outsiderCodes)->pluck('Nama', 'Kode_Karyawan');

        return $rows
            ->map(function ($item) use ($teamMembers, $outsiderNames) {
                $member = $teamMembers->firstWhere('Kode_Karyawan', $item->Kode_Karyawan);
                if (! $member) {
                    $fallbackName =
                        $outsiderNames->get($item->Kode_Karyawan) ??
                        $outsiderNames->get(strtoupper(trim((string) $item->Kode_Karyawan)));
                    if ($fallbackName !== null) {
                        $member = (object) ['Nama' => $fallbackName];
                    }
                }
                // Antrian didedupe per No_Transaksi -> satu kartu bisa mewakili beberapa
                // karyawan. Kode_Karyawan = wakil (MIN); tampilkan "+N lainnya" agar leader
                // tahu transaksi ini mencakup lebih dari satu orang.
                $jumlahKaryawan = max(1, (int) (data_get($item, 'Jumlah_Karyawan', 1) ?: 1));
                $baseName = $member->Nama ?? null;
                $displayName = $baseName;
                if ($baseName !== null && $jumlahKaryawan > 1) {
                    $displayName = $baseName.' +'.($jumlahKaryawan - 1).' lainnya';
                }

                $type = strtoupper(trim((string) ($item->Jenis ?? '')));
                $isExpired = $this->isApprovalExpiredRecord($item);
                if ($isExpired) {
                    $status = 'EXPIRED';
                } else {
                    $status = strtoupper(trim((string) ($item->Dashboard_Status ?? 'PENDING')));
                }
                $statusLabel = match ($status) {
                    'PENDING' => 'Pending',
                    'FUTURE' => 'Future',
                    'EXPIRED' => 'Expired',
                    'APPROVED' => 'Approved',
                    'REJECTED' => 'Rejected',
                    default => $status !== '' ? ucfirst(strtolower($status)) : 'Pending',
                };
                $tone = match ($status) {
                    'EXPIRED' => 'danger',
                    'FUTURE' => 'info',
                    'APPROVED' => 'success',
                    'REJECTED' => 'danger',
                    default => 'warning',
                };
                $typeLabel = ucwords(strtolower(str_replace('_', ' ', $type !== '' ? $type : 'APPROVAL')));
                $titlePrefix = in_array(
                    $type,
                    [
                        'CUTI',
                        'IZIN',
                        'IZIN_SAKIT',
                        'SAKIT_IZIN',
                        'SAKIT',
                        'KPI_PARAMETER',
                        'KPI_PERFORMANCE',
                        'SHIFT',
                        'KONVERSI_LEMBUR',
                        'STATUS_HARI',
                        'LEMBUR',
                        'pulangCepat',
                        'telat',
                    ],
                    true,
                )
                    ? 'Pengajuan'
                    : 'Approval';
                $title = trim($titlePrefix.' '.$typeLabel);
                $descriptionText = trim((string) ($item->Deskripsi ?? ''));
                $transactionNo = trim((string) ($item->No_Transaksi ?? ''));
                $url = $this->resolveApprovalUrl($type, $item->No_Transaksi);
                $description =
                    $descriptionText !== ''
                        ? $descriptionText
                        : ($transactionNo !== ''
                            ? 'No transaksi '.$transactionNo
                            : 'Menunggu tindak lanjut approval.');
                $requestedAt = null;
                if (! empty($item->Tanggal_Request)) {
                    try {
                        $requestedAt = Carbon::parse($item->Tanggal_Request)->translatedFormat('d M Y H:m');
                    } catch (\Throwable $e) {
                        $requestedAt = null;
                    }
                }

                return [
                    'id' => strtoupper(trim((string) ($item->Kode_Karyawan ?? ''))).
                        '|'.
                        $transactionNo.
                        '|'.
                        ($type !== '' ? $type : 'APPROVAL'),
                    'kode_karyawan' => $item->Kode_Karyawan,
                    'memberId' => strtoupper(trim((string) ($item->Kode_Karyawan ?? ''))),
                    'memberName' => $displayName,
                    'member_name' => $displayName,
                    'jumlah_karyawan' => $jumlahKaryawan,
                    'type' => $type !== '' ? $type : 'APPROVAL',
                    'title' => $title,
                    'description' => $description,
                    'statusLabel' => $statusLabel,
                    'tone' => $tone,
                    'icon' => $this->resolveApprovalIcon($type !== '' ? $type : 'APPROVAL'),
                    'expired_at' => $item->Expired_At ?? null,
                    'meta' => $requestedAt ? 'Pengajuan: '.$requestedAt : 'Tanggal pengajuan tidak tersedia',
                    'jenis' => $type,
                    'no_transaksi' => $item->No_Transaksi,
                    'status' => $status,
                    'deskripsi' => $item->Deskripsi ?? null,
                    'tanggal_request' => $item->Tanggal_Request ?? null,
                    'url' => $url,
                    'is_expired' => $isExpired,
                ];
            })
            ->filter(fn ($item) => $includeExpired || ! ($item['is_expired'] ?? false))
            ->take(24)
            ->values()
            ->all();
    }

    protected function loadGroupedApprovalQueueRows(
        array $memberCodes,
        ?string $approverKode = null,
        bool $restrictToMembers = true,
    ): Collection {
        $approverKode = $approverKode !== null ? strtoupper(trim($approverKode)) : null;
        $hasApprover = $approverKode !== null && $approverKode !== '';

        // Antrian approval ditentukan oleh SIAPA approver-nya (Current_Approver),
        // BUKAN oleh keanggotaan HRIS_Karyawan_Team. Pengajuan (izin, sakit, cuti,
        // terlambat, pulang cepat, shift, KPI) harus muncul untuk approver yang sah
        // walau pemohonnya tidak terdaftar di tim leader tersebut. Filter member
        // hanya dipakai untuk konteks lama (mis. badge per-anggota tim) lewat
        // $restrictToMembers = true.
        if ($restrictToMembers && empty($memberCodes)) {
            return collect();
        }
        if (! $hasApprover && empty($memberCodes)) {
            return collect();
        }

        $allTx = new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService;

        // MODE ANTRIAN (per-approver): sumber tunggal pendingForApprover — filter jenis
        // (hanya SAKIT_IZIN/terlambat/pulangCepat kena jendela umur), Expired_At, dan
        // approver sudah di dalamnya, SELARAS 1:1 dengan badge homepage
        // (countPendingApprovalsV2). Dedupe PER No_Transaksi: satu transaksi (walau
        // mencakup beberapa karyawan) = satu antrian, karena approve-nya sekali per
        // transaksi (resolveApprovalUrl hanya pakai No_Transaksi).
        if (! $restrictToMembers) {
            return DB::query()
                ->fromSub($allTx->pendingForApprover((string) $approverKode), 'tx')
                ->selectRaw(
                    '
                    No_Transaksi,
                    MIN(Kode_Karyawan) as Kode_Karyawan,
                    COUNT(DISTINCT Kode_Karyawan) as Jumlah_Karyawan,
                    MAX(Jenis) as Jenis,
                    MAX(Dashboard_Status) as Dashboard_Status,
                    MAX(Deskripsi) as Deskripsi,
                    MAX(Tanggal_Request) as Tanggal_Request,
                    MAX(Source_Created_At) as Source_Created_At,
                    MAX(Expired_At) as Expired_At,
                    MIN(Is_Item_Expired) as All_Items_Expired
                ',
                )
                // Expired via Flag_Approval=P dianggap benar hanya bila SEMUA item transaksi
                // ber-flag P (MIN=1). MAX salah: MAX(P,Y)=Y, MAX(P,NULL)=P.
                ->groupBy('No_Transaksi')
                ->get();
        }

        // MODE PER-ANGGOTA (badge per anggota tim, buildApprovalCounts): tetap per
        // (Kode_Karyawan, No_Transaksi). Perilaku sengaja dipertahankan.
        return DB::query()
            ->fromSub($allTx->forRequesters($memberCodes), 'tx')
            ->whereIn('Dashboard_Status', ['PENDING', 'FUTURE'])
            ->when($hasApprover, function ($query) use ($approverKode) {
                $query
                    ->where('Flow_State', 'CURRENT')
                    ->where('Current_Approver', $approverKode)
                    ->where(function ($inner) {
                        $inner
                            ->where(
                                'Source_Created_At',
                                '>=',
                                Carbon::now()
                                    ->subDays((int) config('services.hcis.batas_approve_izin'))
                                    ->toDateTimeString(),
                            )
                            ->orWhereNotIn(
                                'Jenis',
                                \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService::JENIS_DIBATASI_UMUR,
                            );
                    });
            })
            ->selectRaw(
                '
                Kode_Karyawan,
                No_Transaksi,
                MAX(Jenis) as Jenis,
                MAX(Dashboard_Status) as Dashboard_Status,
                MAX(Deskripsi) as Deskripsi,
                MAX(Tanggal_Request) as Tanggal_Request,
                MAX(Source_Created_At) as Source_Created_At,
                MAX(Expired_At) as Expired_At,
                MAX(Item_Approval_Status) as Item_Approval_Status,
                MAX(Flow_Approval_Status) as Flow_Approval_Status
            ',
            )
            ->groupBy('Kode_Karyawan', 'No_Transaksi')
            ->get();
    }

    protected function isApprovalExpiredRecord($item): bool
    {
        $status = strtoupper(trim((string) data_get($item, 'Dashboard_Status', '')));
        if (in_array($status, ['EXPIRED', 'VOID'], true)) {
            return true;
        }

        // Ditandai expired oleh sistem (command ExpiredIzin) via Flag_Approval = 'P'
        // pada detail/flow. Ini SUMBER OTORITATIF — bukan heuristik umur request.
        // Mode antrian (dedupe per No_Transaksi) sudah mengagregasi ini jadi
        // All_Items_Expired = MIN(Is_Item_Expired): transaksi baru expired kalau SEMUA
        // itemnya ber-flag 'P'. Bila ada, pakai itu.
        $allItemsExpired = data_get($item, 'All_Items_Expired');
        if ($allItemsExpired !== null) {
            if ((int) $allItemsExpired === 1) {
                return true;
            }
        } else {
            // Mode per-anggota: masih per-baris. Prefer detail (det.Flag_Approval) di atas
            // flow, selaras dgn derivasi Dashboard_Status: ISNULL(det.Flag_Approval, flow.Flag_Approval).
            $flag = strtoupper(trim((string) (
                data_get($item, 'Item_Approval_Status')
                ?: data_get($item, 'Flow_Approval_Status')
            )));
            if ($flag === 'P') {
                return true;
            }
        }

        // Expired_At dari HRIS_Approval_Request (sumber otoritatif). Dianggap expired
        // HANYA bila waktunya sudah lewat. Sebelumnya ada fallback heuristik berbasis
        // selisih hari vs batas_approve_izin yang KELIRU menyembunyikan pengajuan yang
        // masih sah (mis. SHIFT lama yang belum diproses) — heuristik itu dihapus.
        $expiredAtRaw = data_get($item, 'Expired_At');
        if (! empty($expiredAtRaw)) {
            try {
                return Carbon::parse((string) $expiredAtRaw, 'Asia/Jakarta')->isPast();
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    protected function buildShiftSchedules(Collection $teamMembers): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $memberCodes = $teamMembers->pluck('Kode_Karyawan')->map(fn ($kode) => strtoupper(trim((string) $kode)))->all();
        $schedulesByMember = $this->shiftRequestService->getWeekScheduleBulk($memberCodes, $today->copy(), 7);

        // Pushdown rentang (sargable) menggantikan filter hilir CAST(Tanggal_Item AS DATE).
        // Range [today 00:00:00 .. today+6 23:59:59] menjaga kesetaraan utk kolom DATETIME.
        $approvedFuture = DB::query()
            ->fromSub(
                (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)->forRequestersBetween(
                    $memberCodes,
                    $today->copy()->startOfDay()->toDateTimeString(),
                    $today->copy()->addDays(6)->endOfDay()->toDateTimeString(),
                ),
                'tx',
            )
            ->where('Dashboard_Status', 'APPROVED')
            ->select('Kode_Karyawan', 'Jenis', 'Source_View', 'Json_Context', 'Tanggal_Item', 'Deskripsi')
            ->get()
            ->groupBy(
                fn ($item) => strtoupper(trim((string) $item->Kode_Karyawan)).
                    '|'.
                    Carbon::parse($item->Tanggal_Item)->format('Y-m-d'),
            );

        $approvedFutureMap = collect($approvedFuture)
            ->mapWithKeys(fn ($items, $key) => [$key => $this->resolveApprovedType($items)])
            ->all();

        $grouped = [];

        foreach ($schedulesByMember as $kode => $memberSchedules) {
            foreach ($memberSchedules as $schedule) {
                $shift = $schedule['shift'] ?? null;
                if (! $shift) {
                    continue;
                }

                $date = $schedule['date'];
                $name = $shift['nama'] ?? 'Shift';
                $key = $date.'|'.$name;

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'date' => $date,
                        'label' => Carbon::parse($date)->translatedFormat('d M Y'),
                        'name' => $name,
                        'time' => ($shift['jam_masuk'] ?? '--:--').' - '.($shift['jam_keluar'] ?? '--:--'),
                        'minNeed' => 0,
                        'memberIds' => [],
                        'unavailableMemberIds' => [],
                        'memberStates' => [],
                    ];
                }

                $grouped[$key]['minNeed']++;
                $grouped[$key]['memberIds'][] = $kode;

                $approvedType = $approvedFutureMap[$kode.'|'.$date] ?? null;
                $state = 'scheduled';
                $unavailable = false;
                if (in_array($approvedType, ['CUTI', 'IZIN', 'SAKIT'], true)) {
                    $state = strtolower($approvedType);
                    $unavailable = true;
                } elseif (! empty($schedule['is_day_off'])) {
                    $state = 'off';
                    $unavailable = true;
                }

                if ($unavailable) {
                    $grouped[$key]['unavailableMemberIds'][] = $kode;
                }

                $grouped[$key]['memberStates'][$kode] = $state;
            }
        }

        return collect($grouped)->values()->all();
    }

    protected function buildFollowUpSummary(array $attendance, array $kpi, array $lms, int $approvalCount): string
    {
        $parts = [];

        if (! empty($attendance['issues'])) {
            $parts[] = count($attendance['issues']).' issue absensi';
        }
        if (! empty($kpi['pending_items'])) {
            $parts[] = count($kpi['pending_items']).' alert KPI';
        }
        if ($approvalCount > 0) {
            $parts[] = $approvalCount.' approval';
        }

        return empty($parts) ? 'Tidak ada follow up kritikal saat ini.' : implode(', ', $parts).'.';
    }

    protected function normalizeLmsYear(?int $year): int
    {
        $value = (int) ($year ?? Carbon::now('Asia/Jakarta')->year);
        if ($value < 2000 || $value > 2100) {
            return (int) Carbon::now('Asia/Jakarta')->year;
        }

        return $value;
    }

    protected function normalizeKpiYear(?int $year): int
    {
        $value = (int) ($year ?? Carbon::now('Asia/Jakarta')->year);
        if ($value < 2000 || $value > 2100) {
            return (int) Carbon::now('Asia/Jakarta')->year;
        }

        return $value;
    }

    protected function normalizeLmsFocus(?string $focus): string
    {
        $value = strtolower(trim((string) $focus));

        return match ($value) {
            'kpi_param_not_submitted' => 'lms_not_started',
            'avg_progress' => 'progress_above_target',
            'completed', 'progress_above_target', 'ytd_gt_80' => 'progress_above_target',
            'at_risk', 'risk', 'progress_below_target', 'ytd_le_80' => 'progress_below_target',
            'learning_gain' => 'learning_gain',
            'total_training', 'assigned' => 'assigned',
            default => 'lms_not_started',
        };
    }

    protected function buildLmsDrawerCacheKey(
        int $year,
        string $focus,
        int $page,
        int $perPage,
        string $search = '',
    ): string {
        return implode('|', [$year, $focus, max(1, $page), max(1, min(50, $perPage)), strtolower(trim($search))]);
    }

    protected function resolveLmsFocusSortingMeta(string $focus): array
    {
        return match ($focus) {
            'progress_below_target' => [
                'primary' => 'avg_progress asc',
                'priority' => 'avg_progress < 80 first',
                'tie_breaker' => 'assigned_total desc, name asc',
            ],
            'progress_above_target' => [
                'primary' => 'avg_progress desc',
                'priority' => 'avg_progress >= 80 first',
                'tie_breaker' => 'avg_learning_gain desc, name asc',
            ],
            'learning_gain' => [
                'primary' => 'avg_learning_gain desc',
                'priority' => 'non-null learning gain first',
                'tie_breaker' => 'avg_progress desc, name asc',
            ],
            'assigned' => [
                'primary' => 'assigned_total desc',
                'priority' => 'most assigned first',
                'tie_breaker' => 'avg_progress asc, name asc',
            ],
            default => [
                'primary' => 'avg_progress asc',
                'priority' => 'not started (0%) first',
                'tie_breaker' => 'name asc',
            ],
        };
    }

    protected function sortLmsDrawerItems(Collection $items, string $focus): Collection
    {
        return $items
            ->sort(function (array $a, array $b) use ($focus) {
                $progressA = (float) ($a['avg_progress'] ?? 0);
                $progressB = (float) ($b['avg_progress'] ?? 0);
                $assignedA = (int) ($a['assigned_total'] ?? 0);
                $assignedB = (int) ($b['assigned_total'] ?? 0);
                $gainA = $a['avg_learning_gain'] !== null ? (float) $a['avg_learning_gain'] : null;
                $gainB = $b['avg_learning_gain'] !== null ? (float) $b['avg_learning_gain'] : null;

                if ($focus === 'progress_below_target') {
                    $rankA = $progressA < 80 ? 0 : 1;
                    $rankB = $progressB < 80 ? 0 : 1;
                    if ($rankA !== $rankB) {
                        return $rankA <=> $rankB;
                    }
                    if ($progressA !== $progressB) {
                        return $progressA <=> $progressB;
                    }
                    if ($assignedA !== $assignedB) {
                        return $assignedB <=> $assignedA;
                    }
                } elseif ($focus === 'progress_above_target') {
                    $rankA = $progressA >= 80 ? 0 : 1;
                    $rankB = $progressB >= 80 ? 0 : 1;
                    if ($rankA !== $rankB) {
                        return $rankA <=> $rankB;
                    }
                    if ($progressA !== $progressB) {
                        return $progressB <=> $progressA;
                    }
                    if ($gainA !== $gainB) {
                        return ($gainB ?? -INF) <=> ($gainA ?? -INF);
                    }
                } elseif ($focus === 'learning_gain') {
                    $rankA = $gainA === null ? 1 : 0;
                    $rankB = $gainB === null ? 1 : 0;
                    if ($rankA !== $rankB) {
                        return $rankA <=> $rankB;
                    }
                    if ($gainA !== $gainB) {
                        return ($gainB ?? -INF) <=> ($gainA ?? -INF);
                    }
                    if ($progressA !== $progressB) {
                        return $progressB <=> $progressA;
                    }
                } elseif ($focus === 'assigned') {
                    if ($assignedA !== $assignedB) {
                        return $assignedB <=> $assignedA;
                    }
                    if ($progressA !== $progressB) {
                        return $progressA <=> $progressB;
                    }
                } else {
                    $rankA = $progressA <= 0 ? 0 : 1;
                    $rankB = $progressB <= 0 ? 0 : 1;
                    if ($rankA !== $rankB) {
                        return $rankA <=> $rankB;
                    }
                    if ($progressA !== $progressB) {
                        return $progressA <=> $progressB;
                    }
                }

                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            })
            ->values();
    }

    protected function buildLeaveSummary(string $kodeKaryawan): array
    {
        $karyawan = Karyawan::query()->where('Kode_Karyawan', $kodeKaryawan)->first();
        $remaining = $karyawan ? (int) $karyawan->sisa_cuti() : 0;

        $used = DB::table('Transaksi_Cuti_Det as det')
            ->join('Transaksi_Cuti as head', 'det.No_Transaksi', '=', 'head.No_Transaksi')
            ->join('Transaksi_Cuti_Detail as detail', 'det.No_Transaksi', '=', 'detail.No_Transaksi')
            ->join('N_HRIS_Jenis_Cuti as jenis', 'detail.Id_Jenis_Cuti', '=', 'jenis.Id_Jenis_Cuti')
            ->where('det.Kode_Karyawan', $kodeKaryawan)
            ->where('jenis.Memotong_Saldo_Cuti', 'Y')
            ->where('detail.Flag_Approval', 'Y')
            ->whereNull('head.Status')
            ->count();

        return [
            'quota' => $remaining + $used,
            'remaining' => $remaining,
            'used' => $used,
        ];
    }

    protected function resolveApprovedType(Collection $items): ?string
    {
        foreach ($items as $item) {
            $type = strtoupper((string) ($item->Tipe_Transaksi ?? ($item->Jenis ?? '')));
            $subType = strtoupper((string) ($item->Sub_Tipe ?? ''));
            $sourceView = strtoupper((string) ($item->Source_View ?? ''));
            $description = strtoupper((string) ($item->Deskripsi ?? ''));
            $context = json_decode((string) ($item->Json_Context ?? '{}'), true) ?: [];
            $contextText = strtoupper(json_encode($context) ?: '');

            if ($type === 'CUTI') {
                return 'CUTI';
            }

            if (
                in_array($type, ['IZIN', 'SAKIT_IZIN', 'IZIN_SAKIT'], true) ||
                str_contains($sourceView, 'IZIN') ||
                str_contains($sourceView, 'SAKIT') ||
                str_contains($description, 'IZIN') ||
                str_contains($description, 'SAKIT') ||
                str_contains($contextText, 'IZIN') ||
                str_contains($contextText, 'SAKIT')
            ) {
                return str_contains($subType, 'SAKIT') ||
                    str_contains($sourceView, 'SAKIT') ||
                    str_contains($description, 'SAKIT') ||
                    str_contains($contextText, 'SAKIT')
                    ? 'SAKIT'
                    : 'IZIN';
            }
        }

        return null;
    }

    protected function resolveApprovalIcon(string $type): string
    {
        return match ($type) {
            'CUTI' => 'bi bi-airplane',
            'IZIN', 'SAKIT_IZIN', 'TERLAMBAT' => 'bi bi-file-earmark-medical',
            'KONVERSI_LEMBUR', 'LEMBUR' => 'bi bi-clock-history',
            'SHIFT', 'STATUS_HARI' => 'bi bi-calendar-check',
            default => 'bi bi-check2-square',
        };
    }

    protected function buildInitials(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn ($item) => strtoupper(substr($item, 0, 1)))
            ->implode('');
    }

    protected function resolveApprovalQueueCount(string $leaderKode): int
    {
        try {
            // Badge antrian approval: buildApprovalItems memanggil pendingForApprover (union
            // 9 view) + loadTeamMembers, dijalankan tiap mount dashboard tiap leader ->
            // saat banyak leader buka bersamaan = lonjakan CPU. Cache pendek per leader
            // (LEADER_BADGE_CACHE_TTL), KONSISTEN dengan resolveKpiApprovalQueueCount di
            // bawah. Angka tetap aktual (badge boleh telat <= TTL), hemat CPU besar.
            $cacheKey = 'leader.approval-count.v2:'.$leaderKode;

            return (int) \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                self::LEADER_BADGE_CACHE_TTL,
                function () use ($leaderKode) {
                    $teamMembers = $this->loadTeamMembers($leaderKode);

                    // Tidak boleh return 0 saat tim kosong: antrian approval berbasis
                    // approver (Current_Approver), jadi leader bisa punya pengajuan untuk
                    // di-approve walau tidak punya anggota HRIS_Karyawan_Team.
                    // Hitung dari item yang sama persis dengan yang ditampilkan pada list
                    // approval (getPaginatedApprovalItems), supaya badge dan isi list selalu
                    // konsisten (filter approver + expired + cap take(24) ikut sama).
                    return count($this->buildApprovalItems($teamMembers, false, $leaderKode));
                },
            );
        } catch (\Throwable $e) {
            Log::warning('HomepageLeaderDashboardService approval queue count failed: '.$e->getMessage());

            return 0;
        }
    }

    protected function resolveKpiApprovalQueueCount(string $leaderKode, array $teamMemberCodes = []): int
    {
        try {
            // Badge count antrian approval KPI: query forApprover (union 9 view) tiap load
            // dashboard. Cache pendek (90s) per leader -> angka tetap aktual, hemat CPU.
            $cacheKey = 'leader.kpi-approval-count.v1:'.$leaderKode;

            return (int) \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                self::LEADER_BADGE_CACHE_TTL,
                fn () => (int) DB::query()
                    ->fromSub((new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)->forApprover($leaderKode), 'tx')
                    ->where('Flow_State', 'CURRENT')
                    ->whereIn('Dashboard_Status', ['PENDING', 'FUTURE'])
                    ->whereIn('Jenis', ['KPI_PARAMETER', 'KPI_PERFORMANCE'])
                    ->distinct('No_Transaksi')
                    ->count('No_Transaksi'),
            );
        } catch (\Throwable $e) {
            Log::warning('HomepageLeaderDashboardService KPI approval queue count failed: '.$e->getMessage());

            return 0;
        }
    }

    protected function defaultKpiSummary(): array
    {
        return [
            'has_data' => false,
            'score' => 0,
            'grade_label' => 'Belum ada data',
            'achievement' => 0,
            'parameter_submitted' => false,
            'performance_submitted' => false,
            'parameter_period' => 'Periode berjalan',
            'performance_period' => 'Periode berjalan',
            'deadline_text' => '',
            'pending_items' => [],
            'monthly_achievement' => [],
            'monthlyAchievement' => [],
            'parameters' => [],
            'parameter_scheduled' => false,
            'performance_scheduled' => false,
            'parameter_approval_status' => 'none',
            'performance_approval_status' => 'none',
            'performance_has_parameters' => false,
        ];
    }

    protected function defaultLmsSummary(): array
    {
        return [
            'has_data' => false,
            'year' => (int) Carbon::now('Asia/Jakarta')->year,
            'enrolled_courses' => 0,
            'completed_courses' => 0,
            'mandatory_pending' => 0,
            'progress' => 0,
            'average_score' => null,
            'learning_gain' => null,
            'learning_hours' => 0,
            'avg_duration_hours' => 0,
            'attempted_courses' => 0,
            'completed_minutes_year' => 0,
            'next_session' => 'Tidak ada training aktif',
            'next_session_date' => '-',
            'trainings' => [],
            'learning_progress' => [],
            'completed_monthly' => [],
            'completedMonthly' => [],
            'duration_monthly' => [],
        ];
    }

    protected function defaultAttendanceSummary(): array
    {
        return [
            'todayStatus' => 'no-data',
            'todayLabel' => 'Belum Ada Data',
            'isWorkingToday' => false,
            'checkIn' => null,
            'checkOut' => null,
            'hasCheckIn' => false,
            'hasCheckOut' => false,
            'workDays' => 0,
            'alphaCount' => 0,
            'absentCount' => 0,
            'lateCount' => 0,
            'lateMinutes' => 0,
            'earlyOutCount' => 0,
            'overtimeHours' => 0,
            'permissionDays' => 0,
            'sickDays' => 0,
            'leaveDays' => 0,
            'noCheckoutCount' => 0,
            'issueCount' => 0,
            'issues' => [],
            'dailyLog' => [],
            'shift_name' => 'No Shift',
            'shift_time' => '-',
            'overtime' => [
                'totalHours' => 0,
                'beforeWorkHours' => 0,
                'afterWorkHours' => 0,
                'holidayHours' => 0,
                'weeklyTrend' => [],
            ],
        ];
    }

    private function resolveApprovalUrl(?string $type, ?string $noTransaksi = null): string
    {
        $noTransaksi = trim((string) $noTransaksi);

        // Resolusi via NotificationRouteRegistry — sama seperti halaman personal
        // dashboard (homepage.vue card "Pengajuan Saya") agar URL selalu mengarah
        // ke halaman detail transaksi yang bisa langsung di-approve oleh leader.
        if ($noTransaksi !== '') {
            $resolved = $this->notificationRouteRegistry->resolve(
                $noTransaksi,
                NotificationRouteRegistry::STATE_PENDING,
            );
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Fallback berbasis jenis transaksi — selaraskan dengan resolveTransactionRoute
        // di HomepagePersonalDashboardService agar leader dan anggota tim melihat URL
        // yang konsisten.
        $type = strtoupper(trim((string) $type));
        // Fallback: leader sebagai eksekutor → arahkan ke halaman approval
        $baseUrl = match ($type) {
            'CUTI', 'IZIN', 'SAKIT_IZIN', 'IZIN_SAKIT', 'SAKIT',
            'TERLAMBAT', 'PULANGCEPAT', 'TELAT' => '/izinLevelUp',
            'SHIFT', 'STATUS_HARI', 'KONVERSI_LEMBUR' => '/shift-approval',
            'KPI_PARAMETER' => '/kpi-approval',
            'KPI_PERFORMANCE' => '/performance-approval',
            'LEMBUR' => '/lembur-management',
            default => '/homepage',
        };

        if ($baseUrl === null || $noTransaksi === '') {
            return $baseUrl;
        }

        return $baseUrl.'?no_transaksi='.rawurlencode($noTransaksi);
    }
}
