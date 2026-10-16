<?php

namespace App\Services\ESS\Dashboard\Homepage;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\ESS\Attendance\Processing\KlasifikasiKeterlambatan;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\ESS\Dashboard\Personal\UDashReadModelService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HomepageHcisService
{
    public function __construct(
        protected ShiftRequestService $shiftRequestService,
        protected UDashReadModelService $uDashReadModelService,
        HrisAttendanceSnapshotService $attendanceSnapshotService,
    ) {
        $this->attendanceService = $attendanceSnapshotService;
    }

    public function getSummary(
        string $kodeKaryawan,
        string $userIdAbsen,
        bool $isLeader = false,
        int $pendingApprovals = 0,
    ): array {
        Carbon::setLocale('id');

        $today = Carbon::today();
        $now = Carbon::now();
        $historyStart = $today->copy()->subDays(34);
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $upcomingEnd = $today->copy()->addDays(6);

        $historyRows = $this->loadAbsensiRows($historyStart, $today, $userIdAbsen);
        $monthRows = $historyRows
            ->filter(fn (array $row) => Carbon::parse($row['date'])->betweenIncluded($monthStart, $monthEnd))
            ->values();

        $schedule = collect($this->shiftRequestService->getWeekSchedule($kodeKaryawan, $today->copy()))
            ->map(fn (array $item) => $this->normalizeScheduleItem($item))
            ->values();

        $todaySchedule = $schedule->firstWhere('date', $today->format('Y-m-d'));
        $todayRow = $historyRows->firstWhere('date', $today->format('Y-m-d'));

        $approvedTransactions = collect(
            $this->uDashReadModelService->getApprovedTransactionsForCalendar(
                $kodeKaryawan,
                $historyStart->format('Y-m-d'),
                $upcomingEnd->format('Y-m-d'),
            ) ?? [],
        );

        $approvedMap = $approvedTransactions
            ->groupBy(fn (object $item) => Carbon::parse($item->Tanggal_Item)->format('Y-m-d'))
            ->map(fn (Collection $items) => $items->values());

        $cutiBersamaMap = $this->getCutiBersamaMap($today->copy(), $upcomingEnd->copy());
        $explanationMap = $this->getExplanationMap($kodeKaryawan, $historyStart->copy(), $today->copy());
        $recentTransactions = $this->getRecentTransactions($kodeKaryawan);
        $leaveSummary = $this->getLeaveSummary($kodeKaryawan);
        $todayLogs = $this->getTodayCheckInOutLogs($userIdAbsen, $today->format('Y-m-d'));
        $monthlyStats = $this->buildMonthlyStats($monthRows, $approvedMap, $explanationMap);
        $todayStatus = $this->buildTodayStatus(
            $todayRow,
            $todaySchedule,
            $approvedMap->get($today->format('Y-m-d'), collect()),
            $todayLogs,
            $now,
        );
        $upcomingSchedule = $this->buildUpcomingSchedule($schedule, $approvedMap, $cutiBersamaMap);
        $recentActivity = $this->buildRecentActivity($historyRows, $recentTransactions, $approvedMap, $explanationMap);
        $alerts = $this->buildAlerts(
            $todayStatus,
            $historyRows,
            $recentTransactions,
            $upcomingSchedule,
            $leaveSummary,
            $approvedMap,
            $explanationMap,
            $pendingApprovals,
        );
        $personalInsights = $this->buildPersonalInsights(
            $historyRows,
            $monthRows,
            $recentTransactions,
            $leaveSummary,
            $approvedMap,
            $monthlyStats,
        );

        $leaderSummary = null;
        if ($isLeader) {
            try {
                $leaderSummary = $this->buildLeaderSummary($kodeKaryawan, $pendingApprovals);
            } catch (\Throwable $e) {
                Log::warning('Homepage HCIS gagal membangun leader summary: '.$e->getMessage());
                $leaderSummary = $this->emptyLeaderSummary($pendingApprovals);
            }
        }

        return [
            'today_status' => $todayStatus,
            'alerts' => $alerts,
            'upcoming_schedule' => $upcomingSchedule,
            'recent_activity' => $recentActivity,
            'leave_summary' => $leaveSummary,
            'monthly_stats' => $monthlyStats,
            'personal_insights' => $personalInsights,
            'charts' => $this->buildCharts($historyRows, $monthRows, $upcomingSchedule, $monthlyStats, $leaderSummary),
            'leader_summary' => $leaderSummary,
            'today_logs' => $todayLogs,
        ];
    }

    public function getEmptySummary(bool $isLeader = false, int $pendingApprovals = 0): array
    {
        return [
            'today_status' => [
                'status_code' => 'DATA_TERSEDIA_SEBAGIAN',
                'status_label' => 'Data Parsial',
                'status_tone' => 'secondary',
                'headline' => 'Homepage HCIS sedang menyiapkan data absensi terbaru.',
                'shift_name' => 'DATA MENUNGGU SINKRON',
                'status_hari' => 'UNKNOWN',
                'status_hari_label' => 'Data sedang dilengkapi',
                'is_day_off' => false,
                'jam_masuk' => null,
                'jam_keluar' => null,
                'jam_kerja' => null,
                'check_in' => null,
                'check_out' => null,
                'late_minutes' => 0,
                'running_late_minutes' => 0,
                'device' => null,
                'location' => null,
                'cta' => [
                    [
                        'label' => 'Lihat detail absensi',
                        'route' => '/absensiSales',
                        'icon' => 'bi bi-calendar-check',
                    ],
                    [
                        'label' => 'Ajukan izin',
                        'route' => '/izin',
                        'icon' => 'bi bi-envelope-paper',
                    ],
                ],
            ],
            'alerts' => [
                [
                    'id' => 'hcis-partial-data',
                    'severity' => 'info',
                    'title' => 'Sebagian data HCIS belum lengkap',
                    'message' => 'Dashboard tetap ditampilkan dengan data yang sudah tersedia. Silakan cek modul detail bila ada informasi yang belum muncul.',
                    'route' => '/absensiSales',
                    'action_label' => 'Buka Absensi',
                ],
            ],
            'upcoming_schedule' => [],
            'recent_activity' => [],
            'leave_summary' => [
                'remaining' => 0,
                'debt' => 0,
            ],
            'monthly_stats' => [
                'present_days' => 0,
                'work_days' => 0,
                'attendance_rate' => 0,
                'late_count' => 0,
                'late_minutes' => 0,
                'ontime_count' => 0,
                'izin_count' => 0,
                'sakit_count' => 0,
                'cuti_count' => 0,
                'alpha_count' => 0,
                'early_checkout_count' => 0,
                'overtime_hours' => 0,
            ],
            'personal_insights' => [
                'actionable' => [
                    [
                        'title' => 'Data HCIS belum lengkap',
                        'message' => 'Sebagian insight akan muncul otomatis setelah sinkronisasi absensi dan transaksi selesai.',
                        'confidence' => 'low',
                        'tone' => 'secondary',
                        'route' => '/absensiSales',
                    ],
                ],
                'analytical' => [],
            ],
            'charts' => [
                'attendance_trend' => ['categories' => [], 'series' => []],
                'status_breakdown' => ['labels' => [], 'series' => []],
                'overtime_trend' => ['categories' => [], 'series' => []],
                'shift_mix' => ['categories' => [], 'series' => []],
                'attendance_heatmap' => ['series' => []],
                'checkin_time_trend' => ['categories' => [], 'series' => []],
                'leader_team_chart' => $isLeader ? ['labels' => [], 'series' => []] : null,
            ],
            'leader_summary' => $isLeader ? $this->emptyLeaderSummary($pendingApprovals) : null,
            'today_logs' => [],
            'meta' => [
                'is_fallback' => true,
                'message' => 'Sebagian data HCIS belum berhasil diambil, namun homepage tetap ditampilkan.',
            ],
        ];
    }

    protected function loadAbsensiRows(Carbon $startDate, Carbon $endDate, string $userIdAbsen): Collection
    {
        // $query = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''";
        // $rows = DB::select($query, [
        //     $startDate->format('Y-m-d'),
        //     $endDate->format('Y-m-d'),
        //     $userIdAbsen,
        // ]);
        $sourceReuqest = 'homepage-hcis-service-load-absensi';
        $tglMulai = $startDate->format('Y-m-d');
        $tglSelesai = $endDate->format('Y-m-d');
        $listUserId = $userIdAbsen;
        $response = $this->attendanceService->getAttendance(
            kodePerusahaan: '001',
            tglAwal: $tglMulai,
            tglAkhir: $tglSelesai,
            userIdList: $listUserId,
            executionMode: 'SYNC',

            requestSource: $sourceReuqest,
            requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
            priority: 1,
        );

        if (! $response['success']) {
            throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
        }

        $rows = $response['data'] ?? [];

        return collect($rows)->map(fn (object $row) => $this->normalizeAbsensiRow($row))->values();
    }

    /**
     * Kenapa sebagian menit keterlambatan tidak ikut terhitung.
     *
     * Kalimatnya menyebut angkanya, bukan istilah sistem: yang membaca adalah
     * karyawan, dan "kena plafon 50 menit" hanya berarti sesuatu bila selisih
     * menitnya ikut disebut.
     *
     * @param  array{kategori:string,mentah_menit:int,tercatat_menit:int,toleransi_menit:int,tak_tercatat_menit:int,kena_plafon:bool,terlambat_sebenarnya:bool}  $k
     */
    protected function sebabKeterlambatanTakTercatat(array $k): string
    {
        if ($k['kena_plafon']) {
            return "Keterlambatan dibatasi maksimal {$k['tercatat_menit']} menit, "
                ."jadi {$k['tak_tercatat_menit']} menit sisanya tidak ikut terhitung.";
        }

        return match ($k['kategori']) {
            KlasifikasiKeterlambatan::TOLERANSI => "Masih dalam toleransi {$k['toleransi_menit']} menit, "
                .'jadi tidak dihitung sebagai keterlambatan.',
            KlasifikasiKeterlambatan::BERIZIN => 'Ada izin terlambat yang disetujui, '
                .'jadi tidak dihitung sebagai keterlambatan.',
            default => 'Tidak dihitung sebagai keterlambatan, sebabnya belum diketahui.',
        };
    }

    protected function normalizeAbsensiRow(object $row): array
    {
        $date = Carbon::parse($this->value($row, ['Tanggal']))->format('Y-m-d');
        $shiftName = trim((string) $this->value($row, ['Nama_Shift', 'Shift_Name'], 'NO SHIFT'));
        $statusHari = strtoupper(trim((string) $this->value($row, ['StatusHari'], 'WORK')));

        return [
            'date' => $date,
            'kode_karyawan' => trim((string) $this->value($row, ['Kode_Karyawan'], '')),
            'name' => $this->value($row, ['Nama']),
            'shift_name' => $shiftName === '' ? 'NO SHIFT' : $shiftName,
            'status' => strtoupper(trim((string) $this->value($row, ['Status'], ''))),
            'status_hari' => $statusHari === '' ? 'WORK' : $statusHari,
            'jam_masuk' => $this->formatPlainTime($this->value($row, ['Jam_Masuk'])),
            'jam_keluar' => $this->formatPlainTime($this->value($row, ['Jam_Keluar'])),
            'check_in' => $this->formatDateTimeValue($this->value($row, ['CheckIn', 'Check_In'])),
            'check_out' => $this->formatDateTimeValue($this->value($row, ['CheckOut', 'Check_Out'])),
            'late_minutes' => (int) round((float) $this->value($row, ['RealLate', 'Real_Late'], 0)),
            'overtime_hours' => (float) round((float) $this->value($row, ['Koefisien_OT', 'KoefisienOT'], 0), 2),

            // late_minutes SENGAJA dibiarkan apa adanya: itu angka resmi milik
            // SP dan sudah dipakai banyak layar. Yang ditambahkan hanya
            // keterangan di sebelahnya — berapa menit orang ini SEBENARNYA
            // terlambat, dan kenapa selisihnya tidak ikut terhitung.
            'keterlambatan' => KlasifikasiKeterlambatan::dari($row),
        ];
    }

    protected function normalizeScheduleItem(array $item): array
    {
        $shift = $item['shift'] ?? null;
        $shiftName = trim((string) ($shift['nama'] ?? 'NO SHIFT'));
        $statusHari = strtoupper(trim((string) ($item['status_hari'] ?? 'WORK')));
        $statusLabel = $item['status_hari_label'] ?? null;
        $isDayOff = (bool) ($item['is_day_off'] ?? false);

        return [
            'date' => $item['date'],
            'day_name' => $item['day_name'] ?? Carbon::parse($item['date'])->translatedFormat('l'),
            'month_short' => $item['month_short'] ?? Carbon::parse($item['date'])->translatedFormat('M'),
            'day_short' => $item['day_short'] ?? Carbon::parse($item['date'])->format('d'),
            'shift_name' => $shiftName === '' ? 'NO SHIFT' : $shiftName,
            'jam_masuk' => $shift['jam_masuk'] ?? null,
            'jam_keluar' => $shift['jam_keluar'] ?? null,
            'jam_kerja' => $this->joinWorkHours($shift['jam_masuk'] ?? null, $shift['jam_keluar'] ?? null),
            'status_hari' => $statusHari,
            'status_hari_label' => $statusLabel,
            'is_day_off' => $isDayOff,
            'source' => $item['source'] ?? null,
            'is_locked' => (bool) ($item['is_locked'] ?? false),
            'flag_approval' => $item['Flag_Approval'] ?? 'N',
        ];
    }

    protected function buildTodayStatus(
        ?array $todayRow,
        ?array $todaySchedule,
        Collection $todayApprovedTransactions,
        array $todayLogs,
        Carbon $now,
    ): array {
        $approvedType = $this->resolveApprovedTypeForDate($todayApprovedTransactions);
        $shiftName = $todaySchedule['shift_name'] ?? ($todayRow['shift_name'] ?? 'NO SHIFT');
        $jamMasuk = $todaySchedule['jam_masuk'] ?? ($todayRow['jam_masuk'] ?? null);
        $jamKeluar = $todaySchedule['jam_keluar'] ?? ($todayRow['jam_keluar'] ?? null);
        $isDayOff = (bool) ($todaySchedule['is_day_off'] ?? false);
        $statusHari = $todaySchedule['status_hari'] ?? ($todayRow['status_hari'] ?? 'WORK');
        $statusHariLabel = $todaySchedule['status_hari_label'] ?? null;

        $checkInDisplay = $todayRow['check_in']['display'] ?? null;
        $checkOutDisplay = $todayRow['check_out']['display'] ?? null;
        $lateMinutes = (int) ($todayRow['late_minutes'] ?? 0);
        $hasCheckIn = $checkInDisplay !== null;
        $hasCheckOut = $checkOutDisplay !== null;
        $runningLate = 0;

        if (! $hasCheckIn && ! $isDayOff && $jamMasuk !== null) {
            $runningLate = $this->calculateRunningLateMinutes($jamMasuk, $now);
        }

        $statusCode = 'BELUM_ABSEN';
        $statusLabel = 'Belum Absen';
        $statusTone = 'danger';
        $headline = 'Hari ini kamu belum melakukan check-in';

        if ($isDayOff || Str::startsWith($statusHari, 'OFF')) {
            $statusCode = 'OFF';
            $statusLabel = 'OFF';
            $statusTone = 'secondary';
            $headline = $statusHariLabel ? "Hari ini {$statusHariLabel}" : 'Hari ini kamu OFF';
        } elseif ($approvedType === 'CUTI') {
            $statusCode = 'CUTI';
            $statusLabel = 'Cuti';
            $statusTone = 'info';
            $headline = 'Hari ini kamu sedang cuti';
        } elseif ($approvedType === 'SAKIT') {
            $statusCode = 'SAKIT';
            $statusLabel = 'Sakit';
            $statusTone = 'danger';
            $headline = 'Hari ini tercatat sakit';
        } elseif ($approvedType === 'IZIN') {
            $statusCode = 'IZIN';
            $statusLabel = 'Izin';
            $statusTone = 'primary';
            $headline = 'Hari ini kamu izin';
        } elseif ($hasCheckIn && $hasCheckOut && $lateMinutes > 0) {
            $statusCode = 'TERLAMBAT';
            $statusLabel = 'Terlambat';
            $statusTone = 'warning';
            $headline = "Check-out sudah tercatat, terlambat {$lateMinutes} menit";
        } elseif ($hasCheckIn && $hasCheckOut) {
            $statusCode = 'SUDAH_CHECK_OUT';
            $statusLabel = 'Sudah Check Out';
            $statusTone = 'success';
            $headline = 'Check-in dan check-out hari ini sudah lengkap';
        } elseif ($hasCheckIn && $lateMinutes > 0) {
            $statusCode = 'TERLAMBAT';
            $statusLabel = 'Terlambat';
            $statusTone = 'warning';
            $headline = "Check-in tercatat, terlambat {$lateMinutes} menit";
        } elseif ($hasCheckIn) {
            $statusCode = 'SUDAH_CHECK_IN';
            $statusLabel = 'Sudah Check In';
            $statusTone = 'success';
            $headline = 'Check-in hari ini sudah tercatat';
        } elseif ($runningLate > 0) {
            $statusCode = 'BELUM_CHECK_IN';
            $statusLabel = 'Belum Check In';
            $statusTone = 'danger';
            $headline = "Belum check-in, keterlambatan berjalan {$runningLate} menit";
        }

        $firstCheckinLog = collect($todayLogs)->firstWhere('type', 'checkin');
        $cta = [
            [
                'label' => 'Lihat detail absensi',
                'route' => '/absensiSales',
                'icon' => 'bi bi-calendar-check',
            ],
            [
                'label' => 'Ajukan izin',
                'route' => '/izin',
                'icon' => 'bi bi-envelope-paper',
            ],
            [
                'label' => 'Ajukan koreksi absensi',
                'route' => '/izin',
                'icon' => 'bi bi-pencil-square',
            ],
        ];

        return [
            'status_code' => $statusCode,
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'headline' => $headline,
            'shift_name' => $shiftName,
            'status_hari' => $statusHari,
            'status_hari_label' => $statusHariLabel,
            'is_day_off' => $isDayOff,
            'jam_masuk' => $jamMasuk,
            'jam_keluar' => $jamKeluar,
            'jam_kerja' => $this->joinWorkHours($jamMasuk, $jamKeluar),
            'check_in' => $checkInDisplay,
            'check_out' => $checkOutDisplay,
            'late_minutes' => $lateMinutes,
            'running_late_minutes' => $runningLate,
            'device' => $firstCheckinLog['machine_alias'] ?? null,
            'location' => $firstCheckinLog['machine_alias'] ?? null,
            'cta' => $cta,
        ];
    }

    protected function buildAlerts(
        array $todayStatus,
        Collection $historyRows,
        Collection $recentTransactions,
        Collection $upcomingSchedule,
        array $leaveSummary,
        Collection $approvedMap,
        Collection $explanationMap,
        int $pendingApprovals,
    ): array {
        $alerts = [];
        $recentHistoryRows = $historyRows->sortByDesc('date')->values();

        if (in_array($todayStatus['status_code'], ['BELUM_ABSEN', 'BELUM_CHECK_IN'], true)) {
            $alerts[] = [
                'id' => 'today-missing-checkin',
                'severity' => 'danger',
                'title' => 'Hari ini belum check-in',
                'message' => $todayStatus['jam_kerja']
                    ? "Shift hari ini {$todayStatus['jam_kerja']}"
                    : 'Silakan cek status absensi hari ini.',
                'route' => '/absensiSales',
                'action_label' => 'Buka Absensi',
            ];
        }

        if (
            in_array($todayStatus['status_code'], ['SUDAH_CHECK_IN', 'TERLAMBAT'], true) &&
            empty($todayStatus['check_out'])
        ) {
            $alerts[] = [
                'id' => 'today-missing-checkout',
                'severity' => 'warning',
                'title' => 'Check-out hari ini belum tercatat',
                'message' => 'Pastikan check-out dilakukan atau cek apakah ada anomali absensi.',
                'route' => '/absensiSales',
                'action_label' => 'Lihat Detail',
            ];
        }

        $latestMissing = $recentHistoryRows
            ->filter(fn (array $row) => $row['date'] < Carbon::today()->format('Y-m-d'))
            ->first(function (array $row) use ($approvedMap) {
                return $this->isWorkingRow($row) &&
                    empty($row['check_in']) &&
                    $this->resolveApprovedTypeForDate($approvedMap->get($row['date'], collect())) === null;
            });

        if ($latestMissing) {
            $dateLabel = Carbon::parse($latestMissing['date'])->translatedFormat('d M Y');
            $alerts[] = [
                'id' => 'previous-missing-attendance',
                'severity' => 'danger',
                'title' => "Absensi kosong pada {$dateLabel}",
                'message' => 'Silakan cek detail atau ajukan klarifikasi bila diperlukan.',
                'route' => '/absensiSales',
                'action_label' => 'Cek Absensi',
            ];
        }

        $latestLateNoExplanation = $recentHistoryRows->first(function (array $row) use ($explanationMap) {
            return $row['late_minutes'] > 0 && ! ($explanationMap[$row['date']]['has_terlambat'] ?? false);
        });

        if ($latestLateNoExplanation) {
            $alerts[] = [
                'id' => 'late-without-explanation',
                'severity' => 'warning',
                'title' => 'Ada keterlambatan yang belum diajukan alasan',
                'message' => Carbon::parse($latestLateNoExplanation['date'])->translatedFormat('d M Y').
                    " terlambat {$latestLateNoExplanation['late_minutes']} menit.",
                'route' => '/izin',
                'action_label' => 'Ajukan Alasan',
            ];
        }

        $pendingTransaction = $recentTransactions->firstWhere('dashboard_status', 'PENDING');
        if ($pendingTransaction) {
            $alerts[] = [
                'id' => 'pending-transaction',
                'severity' => 'warning',
                'title' => "Pengajuan {$pendingTransaction['jenis_label']} masih menunggu approval",
                'message' => $pendingTransaction['date_label'],
                'route' => $pendingTransaction['route'],
                'action_label' => 'Lihat Pengajuan',
            ];
        }

        $resolvedTransaction = $recentTransactions->first(function (array $item) {
            return in_array($item['dashboard_status'], ['APPROVED', 'REJECTED'], true);
        });
        if ($resolvedTransaction) {
            $alerts[] = [
                'id' => 'resolved-transaction',
                'severity' => $resolvedTransaction['dashboard_status'] === 'APPROVED' ? 'success' : 'danger',
                'title' => "Pengajuan {$resolvedTransaction['jenis_label']} {$resolvedTransaction['status_label']}",
                'message' => $resolvedTransaction['date_label'],
                'route' => $resolvedTransaction['route'],
                'action_label' => 'Lihat Status',
            ];
        }

        $specialUpcoming = $upcomingSchedule
            ->skip(1)
            ->first(
                fn (array $item) => in_array(
                    $item['status_code'],
                    ['SHIFT_MALAM', 'OFF', 'CUTI', 'IZIN', 'SAKIT', 'CUTI_BERSAMA'],
                    true,
                ),
            );
        if ($specialUpcoming) {
            $alerts[] = [
                'id' => 'upcoming-special-shift',
                'severity' => 'info',
                'title' => "{$specialUpcoming['day_label']} {$specialUpcoming['status_label']}",
                'message' => $specialUpcoming['jam_kerja']
                    ? "Jadwal {$specialUpcoming['jam_kerja']}"
                    : 'Ada jadwal khusus dalam beberapa hari ke depan.',
                'route' => '/self-shift',
                'action_label' => 'Lihat Jadwal',
            ];
        }

        if (($leaveSummary['remaining'] ?? 0) > 0 && ($leaveSummary['remaining'] ?? 0) <= 2) {
            $alerts[] = [
                'id' => 'low-leave-balance',
                'severity' => 'warning',
                'title' => 'Sisa cuti mulai menipis',
                'message' => "Sisa cuti kamu tinggal {$leaveSummary['remaining']} hari.",
                'route' => '/cuti',
                'action_label' => 'Lihat Cuti',
            ];
        }

        if (($leaveSummary['debt'] ?? 0) > 0) {
            $alerts[] = [
                'id' => 'leave-debt',
                'severity' => 'danger',
                'title' => 'Masih ada hutang cuti',
                'message' => "Hutang cuti aktif {$leaveSummary['debt']} hari.",
                'route' => '/cuti',
                'action_label' => 'Tinjau Saldo',
            ];
        }

        if ($pendingApprovals > 0) {
            $alerts[] = [
                'id' => 'leader-pending-approvals',
                'severity' => 'primary',
                'title' => "{$pendingApprovals} approval tim menunggu diproses",
                'message' => 'Ada pengajuan bawahan yang perlu ditinjau segera.',
                'route' => '/leader-shift',
                'action_label' => 'Buka Approval',
            ];
        }

        return collect($alerts)->unique('id')->values()->all();
    }

    protected function buildUpcomingSchedule(
        Collection $schedule,
        Collection $approvedMap,
        Collection $cutiBersamaMap,
    ): Collection {
        return $schedule
            ->take(7)
            ->map(function (array $item) use ($approvedMap, $cutiBersamaMap) {
                $date = $item['date'];
                $approvedType = $this->resolveApprovedTypeForDate($approvedMap->get($date, collect()));
                $cutiBersama = $cutiBersamaMap->get($date);
                $statusCode = 'SHIFT';
                $statusLabel = $item['shift_name'];
                $tone = 'success';
                $icon = 'bi bi-sun';

                if ($cutiBersama) {
                    $statusCode = 'CUTI_BERSAMA';
                    $statusLabel = 'Cuti Bersama';
                    $tone = 'info';
                    $icon = 'bi bi-calendar-heart';
                } elseif ($item['is_day_off']) {
                    $statusCode = 'OFF';
                    $statusLabel = $item['status_hari_label'] ?: 'OFF';
                    $tone = 'secondary';
                    $icon = 'bi bi-moon-stars';
                } elseif ($approvedType === 'CUTI') {
                    $statusCode = 'CUTI';
                    $statusLabel = 'Cuti';
                    $tone = 'info';
                    $icon = 'bi bi-briefcase';
                } elseif ($approvedType === 'IZIN') {
                    $statusCode = 'IZIN';
                    $statusLabel = 'Izin';
                    $tone = 'primary';
                    $icon = 'bi bi-envelope-paper';
                } elseif ($approvedType === 'SAKIT') {
                    $statusCode = 'SAKIT';
                    $statusLabel = 'Sakit';
                    $tone = 'danger';
                    $icon = 'bi bi-heart-pulse';
                } elseif (Str::contains(strtoupper($item['shift_name']), 'MALAM')) {
                    $statusCode = 'SHIFT_MALAM';
                    $statusLabel = $item['shift_name'];
                    $tone = 'dark';
                    $icon = 'bi bi-moon-stars';
                } elseif (Str::contains(strtoupper($item['shift_name']), 'SIANG')) {
                    $tone = 'primary';
                    $icon = 'bi bi-brightness-high';
                } elseif (Str::contains(strtoupper($item['shift_name']), 'SORE')) {
                    $tone = 'warning';
                    $icon = 'bi bi-sunset';
                }

                return [
                    'date' => $date,
                    'date_label' => Carbon::parse($date)->translatedFormat('d M'),
                    'day_label' => Carbon::parse($date)->translatedFormat('l'),
                    'shift_name' => $item['shift_name'],
                    'jam_kerja' => $item['jam_kerja'],
                    'status_code' => $statusCode,
                    'status_label' => $statusLabel,
                    'status_tone' => $tone,
                    'icon' => $icon,
                    'notes' => $cutiBersama['label'] ?? null,
                    'is_today' => Carbon::parse($date)->isToday(),
                ];
            })
            ->values();
    }

    protected function buildRecentActivity(
        Collection $historyRows,
        Collection $recentTransactions,
        Collection $approvedMap,
        Collection $explanationMap,
    ): array {
        $items = [];
        $recentHistoryRows = $historyRows->sortByDesc('date')->take(14)->values();

        foreach ($recentHistoryRows as $row) {
            $dateLabel = Carbon::parse($row['date'])->translatedFormat('d M');
            $approvedType = $this->resolveApprovedTypeForDate($approvedMap->get($row['date'], collect()));

            if ($this->isWorkingRow($row) && empty($row['check_in']) && $approvedType === null) {
                $items[] = [
                    'date' => $row['date'],
                    'date_label' => $dateLabel,
                    'title' => 'Tidak ada check-in',
                    'subtitle' => 'Perlu dicek atau ajukan klarifikasi absensi.',
                    'tone' => 'danger',
                    'route' => '/absensiSales',
                ];
            }

            if ($row['late_minutes'] > 0) {
                $items[] = [
                    'date' => $row['date'],
                    'date_label' => $dateLabel,
                    'title' => "Terlambat {$row['late_minutes']} menit",
                    'subtitle' => $explanationMap[$row['date']]['has_terlambat'] ?? false
                            ? 'Sudah ada pengajuan alasan keterlambatan.'
                            : 'Belum ada pengajuan alasan keterlambatan.',
                    'tone' => 'warning',
                    'route' => '/izin',
                ];
            }

            // Terlambat yang TIDAK ikut terhitung tetap perlu terlihat.
            //
            // Tanpa butir ini, orang yang datang 07:10 dari jadwal 07:00 dengan
            // toleransi 30 menit tidak meninggalkan jejak apa pun di layar —
            // sama persis dengan yang datang tepat waktu. Nadanya sengaja
            // 'info', bukan 'warning': ini keterangan, bukan pelanggaran, dan
            // angka resmi di butir atas tidak berubah.
            $k = $row['keterlambatan'] ?? null;

            if ($k && $k['terlambat_sebenarnya'] && $k['tak_tercatat_menit'] > 0) {
                $items[] = [
                    'date' => $row['date'],
                    'date_label' => $dateLabel,
                    'title' => "Terlambat {$k['mentah_menit']} menit, tercatat {$k['tercatat_menit']}",
                    'subtitle' => $this->sebabKeterlambatanTakTercatat($k),
                    'tone' => 'info',
                    'route' => '/absensiSales',
                ];
            }

            if ($this->isEarlyCheckout($row) && ! ($explanationMap[$row['date']]['has_pulang_cepat'] ?? false)) {
                $items[] = [
                    'date' => $row['date'],
                    'date_label' => $dateLabel,
                    'title' => 'Pulang cepat terdeteksi',
                    'subtitle' => 'Belum ada pengajuan pulang cepat untuk tanggal ini.',
                    'tone' => 'warning',
                    'route' => '/izin',
                ];
            }

            if ($approvedType === 'CUTI') {
                $items[] = [
                    'date' => $row['date'],
                    'date_label' => $dateLabel,
                    'title' => 'Cuti tercatat',
                    'subtitle' => 'Hari ini masuk daftar cuti yang disetujui.',
                    'tone' => 'info',
                    'route' => '/cuti',
                ];
            } elseif ($approvedType === 'IZIN') {
                $items[] = [
                    'date' => $row['date'],
                    'date_label' => $dateLabel,
                    'title' => 'Izin tercatat',
                    'subtitle' => 'Hari ini masuk daftar izin yang disetujui.',
                    'tone' => 'primary',
                    'route' => '/izin',
                ];
            }

            if (($row['overtime_hours'] ?? 0) > 0) {
                $items[] = [
                    'date' => $row['date'],
                    'date_label' => $dateLabel,
                    'title' => "Lembur {$row['overtime_hours']} jam",
                    'subtitle' => 'Aktivitas lembur tercatat pada hari ini.',
                    'tone' => 'success',
                    'route' => '/overtime',
                ];
            }
        }

        foreach ($recentTransactions->take(4) as $transaction) {
            $items[] = [
                'date' => $transaction['request_date'],
                'date_label' => Carbon::parse($transaction['request_date'])->translatedFormat('d M'),
                'title' => "{$transaction['jenis_label']} {$transaction['status_label']}",
                'subtitle' => $transaction['date_label'],
                'tone' => $transaction['status_tone'],
                'route' => $transaction['route'],
            ];
        }

        return collect($items)
            ->sortByDesc('date')
            ->unique(fn (array $item) => $item['date'].'|'.$item['title'])
            ->take(5)
            ->values()
            ->all();
    }

    protected function getLeaveSummary(string $kodeKaryawan): array
    {
        $karyawan = Karyawan::where('Kode_Karyawan', $kodeKaryawan)->first();

        return [
            'remaining' => $karyawan ? (int) $karyawan->sisa_cuti() : 0,
            'debt' => $karyawan ? (int) $karyawan->hutang_cuti() : 0,
        ];
    }

    protected function buildMonthlyStats(
        Collection $monthRows,
        Collection $approvedMap,
        Collection $explanationMap,
    ): array {
        $workingRows = $monthRows->filter(fn (array $row) => $this->isWorkingRow($row));
        $presentRows = $workingRows->filter(fn (array $row) => ! empty($row['check_in']));

        // Terlambat dinilai dari kenyataan, bukan dari angka yang sudah
        // dimaafkan SP. Kedua saringan di bawah memakai penilaian yang SAMA:
        // kalau hanya $lateRows yang diperbaiki, hari yang dimaafkan akan
        // terhitung dua kali — sekali sebagai on-time, sekali sebagai
        // terlambat — dan jumlahnya melampaui hari hadir.
        $benarTerlambat = fn (array $row) => (int) $row['late_minutes'] > 0
            || (bool) ($row['keterlambatan']['terlambat_sebenarnya'] ?? false);

        $ontimeRows = $presentRows->reject($benarTerlambat);
        $lateRows = $workingRows->filter($benarTerlambat);

        $countByApprovedType = [
            'IZIN' => 0,
            'SAKIT' => 0,
            'CUTI' => 0,
        ];

        foreach ($workingRows as $row) {
            $approvedType = $this->resolveApprovedTypeForDate($approvedMap->get($row['date'], collect()));
            if ($approvedType && array_key_exists($approvedType, $countByApprovedType)) {
                $countByApprovedType[$approvedType]++;
            }
        }

        $alphaCount = $workingRows
            ->filter(function (array $row) use ($approvedMap) {
                return empty($row['check_in']) &&
                    $this->resolveApprovedTypeForDate($approvedMap->get($row['date'], collect())) === null;
            })
            ->count();

        return [
            'present_days' => $presentRows->count(),
            'work_days' => $workingRows->count(),
            'attendance_rate' => $workingRows->count() > 0 ? (int) round(($presentRows->count() / $workingRows->count()) * 100) : 0,
            'late_count' => $lateRows->count(),

            // Tetap dari late_minutes, dan itu SENGAJA. Keputusan pemilik
            // 4 Sep 2026: hanya keterlambatan yang MELEWATI toleransi dihitung
            // sebagai menit. Baris yang dimaafkan ikut masuk $lateRows tetapi
            // menitnya nol, jadi jumlahnya tidak berubah — satu hari bisa
            // terhitung 1 kejadian dengan 0 menit.
            'late_minutes' => (int) $lateRows->sum('late_minutes'),
            'ontime_count' => $ontimeRows->count(),
            'izin_count' => $countByApprovedType['IZIN'],
            'sakit_count' => $countByApprovedType['SAKIT'],
            'cuti_count' => $countByApprovedType['CUTI'],
            'alpha_count' => $alphaCount,
            'early_checkout_count' => $monthRows
                ->filter(function (array $row) use ($explanationMap) {
                    return $this->isEarlyCheckout($row) &&
                        ! ($explanationMap[$row['date']]['has_pulang_cepat'] ?? false);
                })
                ->count(),
            'overtime_hours' => (float) round($monthRows->sum('overtime_hours'), 2),
        ];
    }

    protected function buildPersonalInsights(
        Collection $historyRows,
        Collection $monthRows,
        Collection $recentTransactions,
        array $leaveSummary,
        Collection $approvedMap,
        array $monthlyStats,
    ): array {
        $actionable = [];
        $analytical = [];

        $missingCount = $monthRows
            ->filter(function (array $row) use ($approvedMap) {
                return $this->isWorkingRow($row) &&
                    empty($row['check_in']) &&
                    $this->resolveApprovedTypeForDate($approvedMap->get($row['date'], collect())) === null;
            })
            ->count();

        if ($missingCount > 0) {
            $actionable[] = [
                'title' => 'Masih ada absensi kosong yang perlu diklarifikasi',
                'message' => "{$missingCount} hari kerja bulan ini belum punya check-in atau status pengganti.",
                'confidence' => 'high',
                'tone' => 'danger',
                'route' => '/absensiSales',
            ];
        }

        if (($leaveSummary['debt'] ?? 0) > 0) {
            $actionable[] = [
                'title' => 'Hutang cuti masih aktif',
                'message' => "Saldo cuti kamu masih minus {$leaveSummary['debt']} hari.",
                'confidence' => 'high',
                'tone' => 'danger',
                'route' => '/cuti',
            ];
        } elseif (($leaveSummary['remaining'] ?? 0) > 0 && ($leaveSummary['remaining'] ?? 0) <= 2) {
            $actionable[] = [
                'title' => 'Sisa cuti hampir habis',
                'message' => "Sisa cuti tinggal {$leaveSummary['remaining']} hari.",
                'confidence' => 'high',
                'tone' => 'warning',
                'route' => '/cuti',
            ];
        }

        $latestPending = $recentTransactions->firstWhere('dashboard_status', 'PENDING');
        if ($latestPending) {
            $actionable[] = [
                'title' => "Pengajuan {$latestPending['jenis_label']} masih diproses",
                'message' => $latestPending['date_label'],
                'confidence' => 'medium',
                'tone' => 'warning',
                'route' => $latestPending['route'],
            ];
        }

        $lateByWeekday = $monthRows
            ->filter(fn (array $row) => (int) $row['late_minutes'] > 0)
            ->groupBy(fn (array $row) => Carbon::parse($row['date'])->translatedFormat('l'))
            ->map(fn (Collection $rows) => $rows->count())
            ->sortDesc();

        if ($lateByWeekday->isNotEmpty()) {
            $weekday = (string) $lateByWeekday->keys()->first();
            $count = (int) $lateByWeekday->first();
            $analytical[] = [
                'title' => 'Hari paling rawan terlambat',
                'message' => "{$weekday} muncul paling sering dengan {$count} kejadian terlambat bulan ini.",
                'confidence' => 'medium',
                'tone' => 'warning',
            ];
        }

        $recentCheckins = $historyRows
            ->filter(fn (array $row) => ! empty($row['check_in']))
            ->sortByDesc('date')
            ->take(14)
            ->values();

        $last7 = $recentCheckins->take(7);
        $prev7 = $recentCheckins->slice(7, 7);
        if ($last7->count() >= 3 && $prev7->count() >= 3) {
            $lastAvg = $this->averageCheckinMinute($last7);
            $prevAvg = $this->averageCheckinMinute($prev7);
            if ($lastAvg !== null && $prevAvg !== null) {
                $diff = $prevAvg - $lastAvg;
                $trendText =
                    $diff > 5
                        ? 'lebih cepat dibanding minggu sebelumnya'
                        : ($diff < -5
                            ? 'lebih lambat dibanding minggu sebelumnya'
                            : 'relatif stabil dibanding minggu sebelumnya');

                $analytical[] = [
                    'title' => 'Tren jam check-in',
                    'message' => 'Rata-rata check-in 7 hari terakhir '.$trendText.'.',
                    'confidence' => abs($diff) > 5 ? 'medium' : 'low',
                    'tone' => $diff < -5 ? 'danger' : 'success',
                ];
            }
        }

        $presentDays = max(1, (int) ($monthlyStats['present_days'] ?? 0));
        $punctualRatio = (int) round(((int) ($monthlyStats['ontime_count'] ?? 0) / $presentDays) * 100);
        $analytical[] = [
            'title' => 'Proporsi hadir tepat waktu',
            'message' => "{$punctualRatio}% dari hari hadir bulan ini tercatat tepat waktu.",
            'confidence' => $presentDays >= 5 ? 'high' : 'medium',
            'tone' => $punctualRatio >= 80 ? 'success' : 'warning',
        ];

        $dominantShift = $historyRows
            ->filter(fn (array $row) => $this->isWorkingRow($row))
            ->groupBy('shift_name')
            ->map(fn (Collection $rows) => $rows->count())
            ->sortDesc()
            ->keys()
            ->first();

        if ($dominantShift) {
            $analytical[] = [
                'title' => 'Pola shift dominan',
                'message' => "{$dominantShift} paling sering muncul pada ritme kerja kamu belakangan ini.",
                'confidence' => 'medium',
                'tone' => 'primary',
            ];
        }

        if (empty($actionable) && empty($analytical)) {
            $actionable[] = [
                'title' => 'Belum ada cukup data bulan ini',
                'message' => 'Insight personal akan muncul setelah data absensi dan transaksi makin lengkap.',
                'confidence' => 'low',
                'tone' => 'secondary',
                'route' => '/absensiSales',
            ];
        }

        return [
            'actionable' => collect($actionable)->take(4)->values()->all(),
            'analytical' => collect($analytical)->take(4)->values()->all(),
        ];
    }

    protected function buildCharts(
        Collection $historyRows,
        Collection $monthRows,
        Collection $upcomingSchedule,
        array $monthlyStats,
        ?array $leaderSummary,
    ): array {
        $trendRows = $historyRows->sortByDesc('date')->take(14)->sortBy('date')->values();

        $attendanceTrend = [
            'categories' => $trendRows
                ->map(fn (array $row) => Carbon::parse($row['date'])->translatedFormat('d M'))
                ->all(),
            'series' => [
                [
                    'name' => 'Kehadiran',
                    'data' => $trendRows
                        ->map(fn (array $row) => ! empty($row['check_in']) ? 100 : ($this->isWorkingRow($row) ? 0 : null))
                        ->all(),
                ],
                [
                    'name' => 'Terlambat (menit)',
                    'data' => $trendRows->map(fn (array $row) => (int) $row['late_minutes'])->all(),
                ],
            ],
        ];

        $statusBreakdown = [
            'labels' => ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Cuti', 'Alpha'],
            'series' => [
                max(0, (int) ($monthlyStats['present_days'] ?? 0) - (int) ($monthlyStats['late_count'] ?? 0)),
                (int) ($monthlyStats['late_count'] ?? 0),
                (int) ($monthlyStats['izin_count'] ?? 0),
                (int) ($monthlyStats['sakit_count'] ?? 0),
                (int) ($monthlyStats['cuti_count'] ?? 0),
                (int) ($monthlyStats['alpha_count'] ?? 0),
            ],
        ];

        $weekGroups = $monthRows
            ->groupBy(fn (array $row) => Carbon::parse($row['date'])->startOfWeek()->format('Y-m-d'))
            ->sortKeys();

        $overtimeTrend = [
            'categories' => $weekGroups
                ->keys()
                ->map(fn (string $date) => Carbon::parse($date)->translatedFormat('d M'))
                ->values()
                ->all(),
            'series' => [
                [
                    'name' => 'Lembur',
                    'data' => $weekGroups
                        ->map(fn (Collection $rows) => (float) round($rows->sum('overtime_hours'), 2))
                        ->values()
                        ->all(),
                ],
            ],
        ];

        $shiftMixMap = $historyRows
            ->filter(fn (array $row) => $this->isWorkingRow($row))
            ->groupBy('shift_name')
            ->map(fn (Collection $rows) => $rows->count())
            ->sortDesc();

        $shiftMix = [
            'categories' => $shiftMixMap->keys()->take(6)->values()->all(),
            'series' => [
                [
                    'name' => 'Jumlah Hari',
                    'data' => $shiftMixMap->values()->take(6)->values()->all(),
                ],
            ],
        ];

        $attendanceHeatmap = [
            'series' => $historyRows
                ->sortByDesc('date')
                ->take(28)
                ->sortBy('date')
                ->groupBy(fn (array $row) => 'Pekan '.Carbon::parse($row['date'])->weekOfMonth)
                ->map(function (Collection $rows, string $weekLabel) {
                    $dayMap = $rows->keyBy(fn (array $row) => Carbon::parse($row['date'])->translatedFormat('l'));
                    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

                    return [
                        'name' => $weekLabel,
                        'data' => collect($days)
                            ->map(function (string $dayName) use ($dayMap) {
                                $row = $dayMap->get($dayName);
                                $value = 0;
                                if ($row) {
                                    $value = ! empty($row['check_in']) ? 2 : ($this->isWorkingRow($row) ? 0 : 1);
                                }

                                return ['x' => $dayName, 'y' => $value];
                            })
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];

        $checkinTrendRows = $historyRows
            ->filter(fn (array $row) => ! empty($row['check_in']))
            ->sortByDesc('date')
            ->take(14)
            ->sortBy('date')
            ->values();

        $checkinTimeTrend = [
            'categories' => $checkinTrendRows
                ->map(fn (array $row) => Carbon::parse($row['date'])->translatedFormat('d M'))
                ->all(),
            'series' => [
                [
                    'name' => 'Jam Check In',
                    'data' => $checkinTrendRows
                        ->map(fn (array $row) => $this->timeToMinutes($row['check_in']['display'] ?? null))
                        ->all(),
                ],
            ],
        ];

        $leaderTeamChart = null;
        if ($leaderSummary) {
            $leaderTeamChart = [
                'labels' => ['Belum Absen', 'Terlambat', 'Izin', 'Sakit', 'Cuti', 'OFF', 'Hadir'],
                'series' => [
                    (int) ($leaderSummary['counts']['belum_absen'] ?? 0),
                    (int) ($leaderSummary['counts']['terlambat'] ?? 0),
                    (int) ($leaderSummary['counts']['izin'] ?? 0),
                    (int) ($leaderSummary['counts']['sakit'] ?? 0),
                    (int) ($leaderSummary['counts']['cuti'] ?? 0),
                    (int) ($leaderSummary['counts']['off'] ?? 0),
                    (int) ($leaderSummary['counts']['hadir'] ?? 0),
                ],
            ];
        }

        return [
            'attendance_trend' => $attendanceTrend,
            'status_breakdown' => $statusBreakdown,
            'overtime_trend' => $overtimeTrend,
            'shift_mix' => $shiftMix,
            'attendance_heatmap' => $attendanceHeatmap,
            'checkin_time_trend' => $checkinTimeTrend,
            'leader_team_chart' => $leaderTeamChart,
        ];
    }

    protected function buildLeaderSummary(string $kodeKaryawan, int $pendingApprovals): array
    {
        $teamMembers = DB::table('HRIS_Karyawan_Team as t')
            ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('t.Kode_Karyawan_Team', $kodeKaryawan)
            ->where('k.Aktif', 'Y')
            ->select('k.Kode_Karyawan', 'k.Nama', 'k.UserID_Absen')
            ->get();

        if ($teamMembers->isEmpty()) {
            return [
                'pending_approvals' => $pendingApprovals,
                'team_count' => 0,
                'counts' => [
                    'belum_absen' => 0,
                    'terlambat' => 0,
                    'izin' => 0,
                    'sakit' => 0,
                    'cuti' => 0,
                    'off' => 0,
                    'hadir' => 0,
                    'lembur' => 0,
                ],
                'members' => [],
            ];
        }

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $userIds = $teamMembers->pluck('UserID_Absen')->filter()->map(fn ($id) => trim((string) $id))->all();

        $resultData = [];
        if (! empty($userIds)) {
            $quotedUserIds = implode(',', $userIds);
            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001','{$monthStart->format('Y-m-d')}','{$today->format(
            //     'Y-m-d',
            // )}','{$quotedUserIds}', '', ''";
            // $resultData = DB::select($query);
            $sourceReuqest = 'homepage-hcis-service-leader-summary';
            $tglMulai = $monthStart->format('Y-m-d');
            $tglSelesai = $today->format('Y-m-d');
            $listUserId = $quotedUserIds;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $resultData = $response['data'] ?? [];
        }

        $rowsByMember = collect($resultData)
            ->map(fn (object $row) => $this->normalizeAbsensiRow($row))
            ->groupBy(fn (array $row) => $row['kode_karyawan'] ?: $row['name']);

        // Kode_Karyawan di-pushdown ke hulu (forRequesters). Filter tanggal HARI INI
        // tetap di hilir tapi sargable: >= today AND < today+1. Ini setara persis dengan
        // whereDate('Tanggal_Item', today) lama (menangkap seluruh hari, termasuk view KPI
        // bertipe datetime yang punya komponen jam) -> hasil tidak berubah.
        $todayStr = $today->format('Y-m-d');
        $tomorrowStr = $today->copy()->addDay()->format('Y-m-d');

        $txApproved = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->forRequesters($teamMembers->pluck('Kode_Karyawan')->all());

        $approvedTransactions = collect(
            DB::query()
                ->fromSub($txApproved, 'tx')
                ->where('Dashboard_Status', 'APPROVED')
                ->where('Tanggal_Item', '>=', $todayStr)
                ->where('Tanggal_Item', '<', $tomorrowStr)
                ->select('Kode_Karyawan', 'Jenis', 'Tanggal_Item', 'Source_View')
                ->get(),
        )->groupBy('Kode_Karyawan');

        $todayLembur = DB::table('Transaksi_Lembur as h')
            ->join('Transaksi_Lembur_Detail as d', function ($join) {
                $join->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')->on('h.No_Transaksi', '=', 'd.No_Transaksi');
            })
            ->join('Transaksi_Lembur_Det as det', function ($join) {
                $join
                    ->on('det.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('det.No_Transaksi', '=', 'd.No_Transaksi')
                    ->on('det.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                    ->on('det.Urut_Detail', '=', 'd.Urut_Oto');
            })
            ->whereIn('d.Kode_Karyawan', $teamMembers->pluck('Kode_Karyawan'))
            ->whereNull('h.Status')
            ->whereDate('det.Tanggal_Lembur', $today->format('Y-m-d'))
            ->pluck('d.Kode_Karyawan')
            ->unique();

        $members = $teamMembers
            ->map(function ($member) use ($rowsByMember, $approvedTransactions, $todayLembur) {
                $memberRows = $rowsByMember->get($member->Kode_Karyawan, $rowsByMember->get($member->Nama, collect()));
                $todayRow = $memberRows->firstWhere('date', Carbon::today()->format('Y-m-d'));
                $approvedToday = $approvedTransactions->get($member->Kode_Karyawan, collect());
                $approvedType = strtoupper((string) optional($approvedToday->first())->Jenis ?? '');

                $status = 'Hadir';
                if ($todayRow && Str::startsWith($todayRow['status_hari'], 'OFF')) {
                    $status = 'OFF';
                } elseif ($approvedType === 'CUTI') {
                    $status = 'Cuti';
                } elseif ($approvedType === 'SAKIT_IZIN') {
                    $status = 'Izin/Sakit';
                } elseif (! $todayRow) {
                    $status = 'Belum Absen';
                } elseif ($todayRow && empty($todayRow['check_in']) && (int) ($todayRow['late_minutes'] ?? 0) === 0) {
                    $status = 'Belum Absen';
                } elseif ($todayRow && (int) ($todayRow['late_minutes'] ?? 0) > 0) {
                    $status = 'Terlambat';
                }

                if ($approvedType === 'SAKIT_IZIN') {
                    $sourceView = strtoupper((string) optional($approvedToday->first())->Source_View ?? '');
                    if (Str::contains($sourceView, 'SAKIT')) {
                        $status = 'Sakit';
                    } else {
                        $status = 'Izin';
                    }
                }

                $workRows = $memberRows->filter(fn (array $row) => $this->isWorkingRow($row));
                $presentCount = $workRows->filter(fn (array $row) => ! empty($row['check_in']))->count();
                $todayShift = $todayRow['shift_name'] ?? 'NO SHIFT';
                $todayJamMasuk = $todayRow['jam_masuk'] ?? '--:--';
                $todayJamKeluar = $todayRow['jam_keluar'] ?? '--:--';

                if (! $todayRow) {
                    $todayShift = 'DATA BELUM ADA';
                }

                return [
                    'kode_karyawan' => $member->Kode_Karyawan,
                    'nama' => $member->Nama,
                    'status_hari_ini' => $status,
                    'shift_hari_ini' => $todayShift,
                    'jam_masuk_hari_ini' => $todayJamMasuk,
                    'jam_keluar_hari_ini' => $todayJamKeluar,
                    'label_hadir' => "{$presentCount}/{$workRows->count()} Hadir",
                    'total_keterlambatan_menit' => (int) $memberRows->sum('late_minutes'),
                    'is_lembur_today' => $todayLembur->contains($member->Kode_Karyawan),
                ];
            })
            ->sortBy(function (array $member) {
                $priority = [
                    'Belum Absen' => 0,
                    'Terlambat' => 1,
                    'Izin' => 2,
                    'Sakit' => 3,
                    'Cuti' => 4,
                    'OFF' => 5,
                    'Hadir' => 6,
                ];

                return $priority[$member['status_hari_ini']] ?? 99;
            })
            ->values();

        $counts = [
            'belum_absen' => $members->where('status_hari_ini', 'Belum Absen')->count(),
            'terlambat' => $members->where('status_hari_ini', 'Terlambat')->count(),
            'izin' => $members->where('status_hari_ini', 'Izin')->count(),
            'sakit' => $members->where('status_hari_ini', 'Sakit')->count(),
            'cuti' => $members->where('status_hari_ini', 'Cuti')->count(),
            'off' => $members->where('status_hari_ini', 'OFF')->count(),
            'hadir' => $members->where('status_hari_ini', 'Hadir')->count(),
            'lembur' => $members->where('is_lembur_today', true)->count(),
        ];

        return [
            'pending_approvals' => $pendingApprovals,
            'team_count' => $members->count(),
            'counts' => $counts,
            'members' => $members->take(8)->values()->all(),
        ];
    }

    /**
     * Window lookback "Transaksi Terbaru" (bulan). Panel hanya menampilkan 10 transaksi
     * terbaru (orderByDesc Tanggal_Request + limit 10), bukan riwayat lengkap. Membatasi
     * rentang men-PUSHDOWN filter tanggal ke 9 view sumber via forRequesterBetween,
     * menghindari pemindaian seluruh riwayat sejak 2004 (forRequester polos = biang CPU).
     */
    private const RECENT_TX_LOOKBACK_MONTHS = 6;

    protected function getRecentTransactions(string $kodeKaryawan): Collection
    {
        $fromDate = Carbon::now('Asia/Jakarta')
            ->subMonths(self::RECENT_TX_LOOKBACK_MONTHS)->format('Y-m-d');
        $toDate = Carbon::now('Asia/Jakarta')->addDay()->format('Y-m-d');

        $txSource = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->forRequesterBetween($kodeKaryawan, $fromDate, $toDate);

        $items = DB::query()
            ->fromSub($txSource, 'tx')
            ->whereIn('Jenis', ['CUTI', 'SAKIT_IZIN', 'TERLAMBAT', 'KONVERSI_LEMBUR', 'SHIFT', 'STATUS_HARI'])
            ->selectRaw(
                '
                No_Transaksi,
                MAX(Jenis) as Jenis,
                MAX(Dashboard_Status) as Dashboard_Status,
                MAX(Deskripsi) as Deskripsi,
                MAX(Source_View) as Source_View,
                MAX(Tanggal_Request) as Tanggal_Request,
                MIN(Tanggal_Item) as Tanggal_Mulai,
                MAX(Tanggal_Item) as Tanggal_Selesai
            ',
            )
            ->groupBy('No_Transaksi')
            ->orderByDesc('Tanggal_Request')
            ->limit(10)
            ->get();

        return collect($items)
            ->map(function (object $item) {
                $status = strtoupper((string) ($item->Dashboard_Status ?? 'UNKNOWN'));
                $statusLabel = match ($status) {
                    'APPROVED' => 'disetujui',
                    'REJECTED', 'VOID' => 'ditolak',
                    'PENDING', 'FUTURE' => 'menunggu approval',
                    default => 'diproses',
                };

                $statusTone = match ($status) {
                    'APPROVED' => 'success',
                    'REJECTED', 'VOID' => 'danger',
                    'PENDING', 'FUTURE' => 'warning',
                    default => 'primary',
                };

                $jenis = strtoupper((string) ($item->Jenis ?? 'LAINNYA'));
                $jenisLabel = match ($jenis) {
                    'CUTI' => 'cuti',
                    'SAKIT_IZIN' => 'izin',
                    'TERLAMBAT' => 'koreksi keterlambatan',
                    'KONVERSI_LEMBUR' => 'lembur',
                    'SHIFT' => 'shift',
                    'STATUS_HARI' => 'status hari',
                    default => strtolower($jenis),
                };

                $route = match ($jenis) {
                    'CUTI' => '/cuti',
                    'SAKIT_IZIN', 'TERLAMBAT' => '/izin',
                    'KONVERSI_LEMBUR' => '/overtime',
                    'SHIFT', 'STATUS_HARI' => '/self-shift',
                    default => '/homepage',
                };

                $start = $item->Tanggal_Mulai ? Carbon::parse($item->Tanggal_Mulai)->translatedFormat('d M Y') : null;
                $end = $item->Tanggal_Selesai ? Carbon::parse($item->Tanggal_Selesai)->translatedFormat('d M Y') : null;
                $dateLabel = $start && $end && $start !== $end ? "{$start} - {$end}" : ($start ?: '-');

                return [
                    'no_transaksi' => $item->No_Transaksi,
                    'jenis' => $jenis,
                    'jenis_label' => $jenisLabel,
                    'dashboard_status' => $status,
                    'status_label' => $statusLabel,
                    'status_tone' => $statusTone,
                    'route' => $route,
                    'request_date' => $item->Tanggal_Request
                        ? Carbon::parse($item->Tanggal_Request)->format('Y-m-d H:i:s')
                        : Carbon::now()->format('Y-m-d H:i:s'),
                    'date_label' => $dateLabel,
                    'deskripsi' => $item->Deskripsi,
                ];
            })
            ->values();
    }

    protected function getTodayCheckInOutLogs(string $userIdAbsen, string $today): array
    {
        try {
            $logs = DB::table('CHECKINOUT as c')
                ->leftJoin('Machines as m', 'c.sensorid', '=', 'm.MachineNumber')
                ->whereRaw('c.USERID = (SELECT TOP 1 USERID FROM USERINFO WHERE Badgenumber = ?)', [$userIdAbsen])
                ->whereDate('c.CHECKTIME', $today)
                ->orderBy('c.CHECKTIME', 'asc')
                ->select('c.CHECKTIME', 'c.CHECKTYPE', 'm.MachineAlias')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Homepage HCIS gagal mengambil log CHECKINOUT dengan alias mesin: '.$e->getMessage());

            try {
                $logs = DB::table('CHECKINOUT as c')
                    ->whereRaw('c.USERID = (SELECT TOP 1 USERID FROM USERINFO WHERE Badgenumber = ?)', [$userIdAbsen])
                    ->whereDate('c.CHECKTIME', $today)
                    ->orderBy('c.CHECKTIME', 'asc')
                    ->selectRaw('c.CHECKTIME, c.CHECKTYPE, NULL as MachineAlias')
                    ->get();
            } catch (\Throwable $fallbackException) {
                Log::warning(
                    'Homepage HCIS gagal mengambil log CHECKINOUT fallback: '.$fallbackException->getMessage(),
                );

                return [];
            }
        }

        return $logs
            ->map(function (object $log) {
                $type = strtoupper(trim((string) ($log->CHECKTYPE ?? '')));
                $machineAlias = $log->MachineAlias ?? 'Mesin Absen';

                return [
                    'time' => Carbon::parse($log->CHECKTIME)->format('H:i'),
                    'machine_alias' => $machineAlias,
                    'activity' => match ($type) {
                        'I' => 'Check In',
                        'O' => 'Check Out',
                        default => 'Absensi',
                    },
                    'type' => match ($type) {
                        'I' => 'checkin',
                        'O' => 'checkout',
                        default => 'activity',
                    },
                    'status' => 'Tercatat',
                ];
            })
            ->values()
            ->all();
    }

    protected function emptyLeaderSummary(int $pendingApprovals): array
    {
        return [
            'pending_approvals' => $pendingApprovals,
            'team_count' => 0,
            'counts' => [
                'belum_absen' => 0,
                'terlambat' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'off' => 0,
                'hadir' => 0,
                'lembur' => 0,
            ],
            'members' => [],
        ];
    }

    protected function getExplanationMap(string $kodeKaryawan, Carbon $startDate, Carbon $endDate): Collection
    {
        $rows = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as d')
            ->join('Transaksi_Terlambat_Pulang_Cepat as h', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status')
            ->whereBetween(DB::raw('CAST(COALESCE(d.Tanggal_Hari_Kerja, d.Tanggal_Masuk_Pulang) AS DATE)'), [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            ])
            ->selectRaw(
                "
                CAST(COALESCE(d.Tanggal_Hari_Kerja, d.Tanggal_Masuk_Pulang) AS DATE) as tanggal,
                MAX(CASE WHEN LOWER(d.Jenis) = 'terlambat' THEN 1 ELSE 0 END) as has_terlambat,
                MAX(CASE WHEN LOWER(d.Jenis) = 'pulangcepat' THEN 1 ELSE 0 END) as has_pulang_cepat
            ",
            )
            ->groupBy(DB::raw('CAST(COALESCE(d.Tanggal_Hari_Kerja, d.Tanggal_Masuk_Pulang) AS DATE)'))
            ->get();

        return $rows->mapWithKeys(function (object $row) {
            $dateKey = Carbon::parse($row->tanggal)->format('Y-m-d');

            return [
                $dateKey => [
                    'has_terlambat' => (bool) $row->has_terlambat,
                    'has_pulang_cepat' => (bool) $row->has_pulang_cepat,
                ],
            ];
        });
    }

    protected function getCutiBersamaMap(Carbon $startDate, Carbon $endDate): Collection
    {
        $rows = DB::table('N_HRIS_Cuti_Bersama')
            ->where('Flag_Aktif', 'Y')
            ->whereDate('Tanggal_Mulai', '<=', $endDate->format('Y-m-d'))
            ->whereDate('Tanggal_Selesai', '>=', $startDate->format('Y-m-d'))
            ->select('Nama_Cuti_Bersama', 'Tanggal_Mulai', 'Tanggal_Selesai')
            ->get();

        $map = collect();
        foreach ($rows as $row) {
            $period = Carbon::parse($row->Tanggal_Mulai);
            $end = Carbon::parse($row->Tanggal_Selesai);

            while ($period->lte($end)) {
                $map->put($period->format('Y-m-d'), [
                    'label' => $row->Nama_Cuti_Bersama ?: 'Cuti Bersama',
                ]);
                $period->addDay();
            }
        }

        return $map;
    }

    protected function resolveApprovedTypeForDate(Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return null;
        }

        $types = $items
            ->map(function ($item) {
                $type = strtoupper((string) ($item->Tipe_Transaksi ?? ($item->Jenis ?? '')));
                $subType = strtoupper((string) ($item->Sub_Tipe ?? ''));

                if ($type === 'CUTI') {
                    return 'CUTI';
                }

                if ($type === 'IZIN' || $type === 'SAKIT_IZIN') {
                    return Str::contains($subType, 'SAKIT') ? 'SAKIT' : 'IZIN';
                }

                return null;
            })
            ->filter();

        return $types->first();
    }

    protected function isWorkingRow(array $row): bool
    {
        $statusHari = strtoupper((string) ($row['status_hari'] ?? 'WORK'));
        $shiftName = strtoupper((string) ($row['shift_name'] ?? 'NO SHIFT'));

        if (Str::startsWith($statusHari, 'OFF')) {
            return false;
        }

        return ! in_array($shiftName, ['OFF', 'NO SHIFT', ''], true);
    }

    protected function isEarlyCheckout(array $row): bool
    {
        if (empty($row['check_out']) || empty($row['jam_keluar'])) {
            return false;
        }

        try {
            $checkout = Carbon::parse($row['check_out']['raw']);
            $scheduled = Carbon::createFromFormat('Y-m-d H:i', $row['date'].' '.$row['jam_keluar']);
        } catch (\Throwable) {
            return false;
        }

        return $checkout
            ->copy()
            ->seconds(0)
            ->lt($scheduled->copy()->seconds(0));
    }

    protected function averageCheckinMinute(Collection $rows): ?float
    {
        $minutes = $rows
            ->map(fn (array $row) => $this->timeToMinutes($row['check_in']['display'] ?? null))
            ->filter(fn ($value) => $value !== null)
            ->values();

        if ($minutes->isEmpty()) {
            return null;
        }

        return (float) round($minutes->avg(), 2);
    }

    protected function calculateRunningLateMinutes(string $jamMasuk, Carbon $now): int
    {
        try {
            $scheduled = Carbon::createFromFormat('Y-m-d H:i', $now->format('Y-m-d').' '.$jamMasuk);
        } catch (\Throwable) {
            return 0;
        }

        return $now->gt($scheduled) ? $scheduled->diffInMinutes($now) : 0;
    }

    protected function joinWorkHours(?string $jamMasuk, ?string $jamKeluar): ?string
    {
        if (! $jamMasuk || ! $jamKeluar) {
            return null;
        }

        return "{$jamMasuk} - {$jamKeluar}";
    }

    protected function formatDateTimeValue(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $carbon = Carbon::parse($value);

            return [
                'raw' => $carbon->format('Y-m-d H:i:s'),
                'display' => $carbon->format('H:i'),
            ];
        } catch (\Throwable) {
            $plain = $this->formatPlainTime($value);
            if ($plain === null) {
                return null;
            }

            return [
                'raw' => (string) $value,
                'display' => $plain,
            ];
        }
    }

    protected function formatPlainTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}/', $stringValue) === 1) {
            return substr($stringValue, 0, 5);
        }

        try {
            return Carbon::parse($stringValue)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function timeToMinutes(?string $time): ?int
    {
        if (! $time || preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    protected function value(object $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (property_exists($row, $key) && $row->{$key} !== null) {
                return $row->{$key};
            }
        }

        return $default;
    }
}
