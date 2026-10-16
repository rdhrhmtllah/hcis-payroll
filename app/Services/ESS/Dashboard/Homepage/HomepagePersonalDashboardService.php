<?php

namespace App\Services\ESS\Dashboard\Homepage;

use App\Jobs\ESS\Dashboard\Personal\SyncPersonalNeedsActionJob;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\ESS\Attendance\Processing\KlasifikasiKeterlambatan;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\ESS\Dashboard\Personal\UDashReadModelService;
use App\Services\SYSTEM\Notification\NotificationRouteRegistry;
use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\SYSTEM\Notification\NotificationSyncStateService;
use App\Services\SYSTEM\Notification\WhatsappNotificationMirrorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomepagePersonalDashboardService
{
    private const DASHBOARD_SOURCE = NotificationService::CHANNEL_DASHBOARD_PERSONAL;

    /**
     * Source notifikasi absensi personal (Alpha/No CI/No CO/Pulang Cepat).
     * Sengaja MAIN_NOTIF supaya masuk ke notifikasi utama (bell topbar), bukan
     * Action Center. Action Center hanya menampung source DASH_PERSON
     * (KPI/LMS/Submission). Leader Dashboard tetap pakai LEADER_DASH sendiri.
     */
    private const ATTENDANCE_SOURCE = WhatsappNotificationMirrorService::SOURCE;

    private const ATTENDANCE_SYNC_SCOPE = 'DASH_PERSON_ATTENDANCE';

    /**
     * Scope khusus untuk guard waktu revalidasi-on-read (saat bell / pusat
     * notifikasi dibuka). Terpisah dari ATTENDANCE_SYNC_SCOPE supaya throttle
     * sync periodik (5 menit) tidak ikut memblok revalidasi cepat ini.
     */
    private const REVALIDATE_SYNC_SCOPE = 'DASH_PERSON_REVALIDATE';

    /**
     * Jeda minimum (detik) antar revalidasi-on-read. Revalidasi hanya menyentuh
     * tanggal yang punya notif absensi aktif (1-3 tanggal, termasuk HARI INI),
     * jadi jauh lebih ringan dari full sync — interval pendek cukup aman.
     */
    private const REVALIDATE_MIN_INTERVAL = 60;

    private const ATTENDANCE_ROLLING_LOOKBACK_DAYS = 3;

    /**
     * Window Action Center: berapa hari SETELAH tanggal akhir cutoff item masih
     * boleh tampil. Mis. cutoff 16 Mei–15 Jun + offset 5 => item tampil s/d 20
     * Jun, lalu otomatis reset/fokus ke cutoff berikutnya. Ubah angka ini untuk
     * memperpanjang/perpendek "grace period" Action Center.
     */
    private const ACTION_CENTER_CUTOFF_END_OFFSET_DAYS = 5;

    /**
     * Jeda minimum (detik) antar full-recheck absensi. Guard berbasis
     * Last_Synced_At di N_HRIS_Notification_Sync_State: kalau sync terakhir
     * masih dalam window ini, skip (hindari panggilan SP absensi berulang saat
     * homepage di-refresh). Persisten lintas server, tidak hilang saat cache
     * di-clear. Cukup pendek supaya checktime telat tetap cepat tersusul.
     */
    private const ATTENDANCE_SYNC_MIN_INTERVAL = 300;

    /**
     * Scope + jeda minimum (detik) throttle generasi notif KPI/LMS/submission di
     * Action Center. getNeedsAction dipanggil tiap homepage load; tanpa throttle ia
     * menjalankan getKpiSummary (~5s, 20 query) + getSubmissionItems (~3s, union-9-view)
     * + tulis notif TIAP load = bottleneck. Generasi notif ini tidak perlu real-time
     * (item tetap dibaca dari tabel notif tiap load); cukup di-refresh berkala.
     */
    private const NEEDS_ACTION_SYNC_SCOPE = 'DASH_PERSON_NEEDS_ACTION';

    private const NEEDS_ACTION_SYNC_MIN_INTERVAL = 300;

    protected HrisAttendanceSnapshotService $attendanceService;

    /**
     * Memoize hasil SP absen per (userId|start|end) selama 1 request HTTP.
     * Dalam satu homepage load, summary/composition/hcis-trend memanggil SP absen
     * untuk rentang yang SAMA berkali-kali — memoization ini menghapus panggilan
     * SP duplikat sehingga 1 dashboard = 1x ambil absen per rentang unik.
     * Tidak persisten antar-request: data HARI INI tetap fresh tiap request baru.
     *
     * @var array<string, Collection>
     */
    private array $absensiRowsMemo = [];

    /**
     * Batas user-date yang boleh diproses SYNC inline. Di atas ini wrapper jatuh
     * ke ASYNC (queue) supaya recalc berat tidak membebani thread request.
     * Default param SP = 300; kita perkecil agar saat backlog dingin (mis. setelah
     * restart) request user TIDAK menanggung recalc 30 hari inline.
     */
    private const ABSEN_MAX_SYNC_USER_DATE = 60;

    /**
     * Plafon absolut user-date yang boleh SYNC inline. Di atas ini, sebanyak
     * apa pun yang diminta, paksa ASYNC (queue) untuk melindungi CPU request.
     * Cukup besar untuk menampung 1 user × trend ~6 bulan (~180 hari) penuh,
     * tetapi tetap mencegah ledakan bila listUser banyak.
     */
    private const ABSEN_MAX_SYNC_HARD = 200;

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

    public function buildInitialData(string $kodeKaryawan, string $userIdAbsen, int $pendingApprovals = 0): array
    {
        $attendanceSummary = $this->getAttendanceSummary($kodeKaryawan, $userIdAbsen);
        $kpiSummary = $this->getKpiSummary($kodeKaryawan);
        $lmsSummary = $this->getLmsSummary($kodeKaryawan);

        return [
            'cutoffRange' => $attendanceSummary['cutoffRange'],
            'cutoffPeriodOptions' => $attendanceSummary['cutoffPeriodOptions'],
            'attendanceRecords' => $attendanceSummary['attendanceRecords'],
            'upcomingSchedule' => $this->getUpcomingScheduleSummary($kodeKaryawan, $kpiSummary, $lmsSummary),
            'kpi' => $kpiSummary,
            'lms' => $lmsSummary,
            'hcis' => $this->getHcisSummary($kodeKaryawan, $userIdAbsen, $attendanceSummary),
            'overtime' => $attendanceSummary['overtime'],
            'leave' => $this->getLeaveSummary($kodeKaryawan),
            'submissions' => $this->getSubmissionsSummary($kodeKaryawan),
            'pendingApprovals' => $pendingApprovals,
        ];
    }

    /**
     * Bootstrap agregat homepage (above-the-fold) dalam SATU pass SP absen.
     *
     * Konsolidasi fan-out: sebelumnya attendance-summary, attendance-composition,
     * hcis-summary, dan weekly-calendar masing-masing 1 request HTTP -> memoization
     * SP absen tak lintas request -> 4 pass SP untuk rentang yang sama. Di sini
     * semuanya dilayani dari 1 pass:
     *  - getAttendanceSummary: 1x buildAttendanceBundle(cutoff) -> SP call (memoized)
     *  - getAttendanceCompositionSummary: loadAbsensiRows(cutoff) -> MEMO HIT (0 SP)
     *  - getHcisSummary($summary): trend kini in-memory -> 0 SP
     *  - weekly: difilter in-memory dari attendanceRecords cutoff -> 0 SP
     *
     * Total: 1 pass SP absen per homepage load (vs ~4 sebelumnya).
     */
    public function buildPersonalBootstrap(
        string $kodeKaryawan,
        string $userIdAbsen,
        int $pendingApprovals = 0,
    ): array {
        $summary = $this->getAttendanceSummary($kodeKaryawan, $userIdAbsen);
        $composition = $this->getAttendanceCompositionSummary($kodeKaryawan, $userIdAbsen);
        $hcis = $this->getHcisSummary($kodeKaryawan, $userIdAbsen, $summary);
        $weekly = $this->deriveWeeklyCalendarFromRecords($summary['attendanceRecords'] ?? []);

        return [
            'attendance' => [
                'cutoffRange' => $summary['cutoffRange'] ?? null,
                'cutoffPeriodOptions' => $summary['cutoffPeriodOptions'] ?? [],
                'attendanceRecords' => $summary['attendanceRecords'] ?? [],
                'overtime' => $summary['overtime'] ?? [],
                'pendingApprovals' => $pendingApprovals,
            ],
            'composition' => $composition,
            'hcis' => $hcis,
            'weeklyCalendar' => $weekly,
        ];
    }

    /**
     * Kalender transaksi APPROVED per tanggal, dimemo untuk satu instance.
     *
     * ── Kenapa memo ini ada ──────────────────────────────────────────────────
     *
     * buildPersonalBootstrap() memanggil jalur ini DUA KALI dengan argumen yang
     * persis sama — sekali lewat buildAttendanceBundle(), sekali lagi lewat
     * getAttendanceCompositionSummary(). Terekam di production 9 September 2026:
     * kueri union 9 view sumber itu 198 eksekusi dengan total 883.904 ms waktu
     * DB dalam sehari, dan homepage/personal/bootstrap menyumbang 178 dari 487
     * catatan permintaan lambat (p95 52,6 detik). Satu dari dua panggilan itu
     * murni pengulangan.
     *
     * ── Kenapa memo, bukan cache ─────────────────────────────────────────────
     *
     * getApprovedTransactionsForCalendar() SENGAJA melewati cache begitu rentang
     * menyentuh kemarin/hari ini: atasan yang baru meng-ACC harus langsung
     * terlihat di kalender. Aturan itu benar dan tidak boleh diakali. Yang
     * diperbaiki di sini bukan kesegaran antar-permintaan, melainkan pengulangan
     * DI DALAM satu permintaan — dan di dalam satu permintaan, dua panggilan
     * berargumen sama menurut definisi menjawab pertanyaan yang sama.
     *
     * ── Batasnya ─────────────────────────────────────────────────────────────
     *
     * Memo hidup selama INSTANCE ini, bukan selama proses. Untuk permintaan HTTP
     * itu tepat satu permintaan (service ini tidak diikat singleton). Pemanggil
     * berumur panjang — artisan, worker antrean — yang menahan satu instance
     * lintas iterasi WAJIB memanggil lupakanMemoKalender() di antara iterasi,
     * atau ia akan membaca jawaban milik iterasi sebelumnya.
     *
     * @var array<string,Collection>
     */
    private array $memoKalenderDisetujui = [];

    /** @return Collection<string,Collection> transaksi approved, dikelompokkan per Y-m-d */
    private function kalenderDisetujuiPerTanggal(
        string $kodeKaryawan,
        string $startDate,
        string $endDate,
    ): Collection {
        $kunci = $kodeKaryawan.'|'.$startDate.'|'.$endDate;

        return $this->memoKalenderDisetujui[$kunci] ??= collect(
            $this->uDashReadModelService->getApprovedTransactionsForCalendar(
                $kodeKaryawan,
                $startDate,
                $endDate,
            ) ?? [],
        )
            ->groupBy(fn ($item) => Carbon::parse($item->Tanggal_Item)->format('Y-m-d'))
            ->map(fn (Collection $items) => $items->values());
    }

    /**
     * Buang memo kalender. WAJIB dipanggil oleh proses berumur panjang di antara
     * iterasi; lihat catatan pada $memoKalenderDisetujui.
     */
    public function lupakanMemoKalender(): void
    {
        $this->memoKalenderDisetujui = [];
    }

    /**
     * Kalender mingguan (minggu berjalan) diturunkan in-memory dari
     * attendanceRecords cutoff berjalan -> tak ada panggilan SP absen tambahan.
     * Batas minggu sama dengan getWeeklyCalendar (berbasis Minggu/dayOfWeek).
     */
    protected function deriveWeeklyCalendarFromRecords(array $records): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $startDate = $today->copy()->subDays($today->dayOfWeek);
        $endDate = $startDate->copy()->addDays(6);

        $weekRecords = collect($records)
            ->filter(function ($record) use ($startDate, $endDate) {
                $date = $record['date'] ?? null;

                return $date !== null
                    && $date >= $startDate->toDateString()
                    && $date <= $endDate->toDateString();
            })
            ->values()
            ->all();

        return [
            'weekStart' => $startDate->toDateString(),
            'weekEnd' => $endDate->toDateString(),
            'attendanceRecords' => $weekRecords,
        ];
    }

    public function getAttendanceSummary(string $kodeKaryawan, string $userIdAbsen, ?string $cutoffPeriod = null): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $cutoffRange = $this->resolveCutoffRange($now, $cutoffPeriod);
        $attendanceBundle = $this->buildAttendanceBundle(
            $kodeKaryawan,
            $userIdAbsen,
            $cutoffRange['start_date']->copy(),
            $cutoffRange['end_date']->copy(),
        );

        return [
            'cutoffRange' => [
                'start' => $cutoffRange['start_date']->toDateString(),
                'end' => $cutoffRange['end_date']->toDateString(),
                'label' => $cutoffRange['label'],
                'period' => $cutoffRange['start_date']->format('Y-m'),
            ],
            'cutoffPeriodOptions' => $this->buildCutoffPeriodOptions($cutoffRange),
            'attendanceRecords' => $attendanceBundle['records'],
            'overtime' => $attendanceBundle['overtime_summary'],
        ];
    }

    /**
     * Detail lembur ON-DEMAND (dipanggil hanya saat card lembur dibuka / cutoff diganti).
     * Ringan: hanya memuat absensi 1 cutoff untuk ringkasan; trend 2-cutoff (rentang
     * panjang) dihitung hanya bila $includeTrend=true. Sengaja TERPISAH dari
     * buildAttendanceBundle agar SP trend tidak membebani endpoint absensi lain.
     */
    public function getOvertimeDetail(
        string $kodeKaryawan,
        string $userIdAbsen,
        ?string $cutoffPeriod = null,
        bool $includeTrend = false,
    ): array {
        $now = Carbon::now('Asia/Jakarta');
        $cutoffRange = $this->resolveCutoffRange($now, $cutoffPeriod);
        $startDate = $cutoffRange['start_date']->copy();
        $endDate = $cutoffRange['end_date']->copy();

        $overtime = [];
        try {
            $absensiRows = $this->loadAbsensiRows($startDate, $endDate, $userIdAbsen);
            $overtime = $this->buildOvertimeSummary(
                $absensiRows,
                collect(),
                $kodeKaryawan,
                $userIdAbsen,
            );
        } catch (\Throwable $e) {
            Log::error('HomepagePersonalDashboardService overtime detail error: '.$e->getMessage());
        }

        // Trend di-guard TERPISAH: kegagalan trend tidak boleh menghapus ringkasan
        // (dan sebaliknya). Konsisten dgn perilaku lama (trend dulu ada di dalam
        // try/catch buildAttendanceBundle).
        $monthlyTrend = [];
        if ($includeTrend) {
            try {
                $monthlyTrend = $this->buildOvertimeTwoCutoffTrend($kodeKaryawan, $userIdAbsen, $now);
            } catch (\Throwable $e) {
                Log::error('HomepagePersonalDashboardService overtime trend error: '.$e->getMessage());
            }
        }

        return [
            'cutoffRange' => [
                'start' => $cutoffRange['start_date']->toDateString(),
                'end' => $cutoffRange['end_date']->toDateString(),
                'label' => $cutoffRange['label'],
                'period' => $cutoffRange['start_date']->format('Y-m'),
            ],
            'cutoffPeriodOptions' => $this->buildCutoffPeriodOptions($cutoffRange),
            'overtime' => $overtime,
            'monthlyTrend' => $monthlyTrend,
        ];
    }

    public function getAttendanceCompositionSummary(
        string $kodeKaryawan,
        string $userIdAbsen,
        ?string $cutoffPeriod = null,
    ): array {
        $now = Carbon::now('Asia/Jakarta');
        $cutoffRange = $this->resolveCutoffRange($now, $cutoffPeriod);
        $startDate = $cutoffRange['start_date']->copy();
        $endDate = $cutoffRange['end_date']->copy();

        try {
            $absensiRows = $this->loadAbsensiRows($startDate, $endDate, $userIdAbsen)->keyBy('date');
            $approvedByDate = $this->kalenderDisetujuiPerTanggal(
                $kodeKaryawan,
                $startDate->toDateString(),
                $endDate->toDateString(),
            );

            $stats = $this->emptyAttendanceCompositionStats();
            $cursor = $startDate->copy();

            while ($cursor->lte($endDate)) {
                $date = $cursor->toDateString();
                $row = $absensiRows->get($date);
                $approvedType = $this->resolveApprovedType($approvedByDate->get($date, collect()));
                $state = $this->buildAttendanceRecordState($row, $approvedType, $date);
                $category = $this->classifyAttendanceCompositionRecord($state, $row);
                $stats[$category]++;

                $cursor->addDay();
            }

            return $this->formatAttendanceCompositionPayload($cutoffRange, $stats);
        } catch (\Throwable $e) {
            Log::error('HomepagePersonalDashboardService attendance composition error: '.$e->getMessage());

            return $this->formatAttendanceCompositionPayload($cutoffRange, $this->emptyAttendanceCompositionStats());
        }
    }

    /**
     * TTL MICRO-CACHE Ringkasan HCIS personal (detik). Ringkasan bulan berjalan
     * (mencakup hari ini). Bukan bypass-realtime total lagi: cache SANGAT pendek
     * meredam N re-load/double-fire dalam window tanpa terasa basi (hadir/telat
     * hari ini menyusul <=30s). Trend kini in-memory dari attendanceRecords cutoff
     * -> tak ada SP absen ekstra pada cache miss.
     */
    private const HCIS_SUMMARY_CACHE_TTL = 30;

    public function getHcisSummary(string $kodeKaryawan, string $userIdAbsen, ?array $attendanceSummary = null): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'homepage.hcis-summary.v2:'.strtoupper(trim($kodeKaryawan)),
            self::HCIS_SUMMARY_CACHE_TTL,
            fn () => $this->buildHcisSummaryUncached($kodeKaryawan, $userIdAbsen, $attendanceSummary),
        );
    }

    protected function buildHcisSummaryUncached(string $kodeKaryawan, string $userIdAbsen, ?array $attendanceSummary = null): array
    {
        $summary = $attendanceSummary ?? $this->getAttendanceSummary($kodeKaryawan, $userIdAbsen);
        $records = collect($summary['attendanceRecords'] ?? []);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $todayRecord = $records->firstWhere('date', $today);

        $todayStatus = strtolower((string) ($todayRecord['status'] ?? ''));
        $isPresent =
            $todayRecord &&
            ! ($todayRecord['alpha'] ?? false) &&
            ! ($todayRecord['no_checkin'] ?? false) &&
            ! ($todayRecord['waiting_shift'] ?? false) &&
            ! ($todayRecord['future'] ?? false) &&
            (! empty($todayRecord['check_in']) || in_array($todayStatus, ['present', 'terlambat', 'working'], true));
        $issueRecords = $records->filter(fn ($record) => $this->isAttendanceIssueRecord((array) $record));
        $lateCount = $records->where('late', true)->count();
        $alphaCount = $records->where('alpha', true)->count();
        $noCheckinCount = $records->where('no_checkin', true)->count();
        $noCheckoutCount = $records->where('no_checkout', true)->count();
        $izinCutiCount = $records
            ->filter(fn ($record) => ! empty($record['izin']) || ! empty($record['sakit']) || ! empty($record['cuti']))
            ->count();

        $trend = $this->buildCutoffAttendanceTrend($kodeKaryawan, $userIdAbsen, $summary);

        return [
            'stats' => [
                'totalMembers' => 1,
                'presentToday' => $isPresent ? 1 : 0,
                'absentToday' => ! empty($todayRecord['alpha'] ?? false) ? 1 : 0,
                'pendingCheckInToday' => ! empty($todayRecord['no_checkin'] ?? false) ? 1 : 0,
                'workingToday' => ! empty($todayRecord['check_in'] ?? null) && empty($todayRecord['check_out'] ?? null) ? 1 : 0,
                'noCheckoutToday' => ! empty($todayRecord['no_checkout'] ?? false) ? 1 : 0,
                'leaveToday' => ! empty($todayRecord['cuti'] ?? false) || ! empty($todayRecord['izin'] ?? false) ? 1 : 0,
                'lateCount' => $lateCount,
                'alphaCount' => $alphaCount,
                'noCheckinCount' => $noCheckinCount,
                'noCheckoutCount' => $noCheckoutCount,
                'izinCutiCount' => $izinCutiCount,
                'attendanceIssues' => $issueRecords->count(),
                'hcisCritical' => $records
                    ->filter(
                        fn ($record) => ! empty($record['alpha']) ||
                            ! empty($record['no_checkin']) ||
                            ! empty($record['no_checkout']),
                    )
                    ->count(),
                'totalOvertime' => (float) ($summary['overtime']['total_hours'] ?? 0),
            ],
            'trend' => $trend,
            'cutoffRange' => $summary['cutoffRange'] ?? null,
        ];
    }

    /**
     * TTL cache ringkasan KPI/LMS personal (detik). getKpiSummary berat: getTeamSummaries
     * + post-processing getLeaderKpiMemberDetail (puluhan query) yang TIDAK ter-cache,
     * jalan tiap load. Dipanggil 3 endpoint (upcoming-schedule, kpi-detail, needs-action)
     * -> 36-40 query/endpoint tiap refresh = risiko spike. Cache output akhir per (user+year)
     * memangkasnya jadi ~0 saat warm. KPI/LMS berubah lambat -> 90s aman & aktual.
     */
    private const KPI_LMS_SUMMARY_CACHE_TTL = 90;

    public function getKpiSummary(string $kodeKaryawan, ?int $year = null): array
    {
        $year = $this->normalizeYear($year);
        $cacheKey = 'homepage.personal.kpi-summary.v1:'.strtoupper(trim($kodeKaryawan)).':'.($year ?? 'cur');

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            self::KPI_LMS_SUMMARY_CACHE_TTL,
            fn () => $this->buildKpiSummaryUncached($kodeKaryawan, $year),
        );
    }

    protected function buildKpiSummaryUncached(string $kodeKaryawan, ?int $year): array
    {
        $summaries = $this->homepageKpiService->getTeamSummaries([$kodeKaryawan], true, $year);
        $summary = $summaries[$kodeKaryawan] ?? [];

        if (
            ! empty($summary) &&
            empty($summary['parameters']) &&
            ($summary['parameter_scheduled'] || $summary['parameter_submitted'])
        ) {
            $user = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();
            if ($user && $user->UserID_Web) {
                $detail = $this->homepageKpiService->getLeaderKpiMemberDetail($user, 'full_year', null, $year);
                if (! empty($detail['parameter_rows'])) {
                    $summary['parameters'] = array_map(function ($item) {
                        return [
                            'id' => $item['id'],
                            'name' => $item['parameter'] ?? 'Parameter KPI',
                            'score' => 0,
                            'achievement' => 0,
                        ];
                    }, $detail['parameter_rows']);
                }
            }
        }

        return $summary;
    }

    public function getLmsSummary(string $kodeKaryawan, ?int $year = null): array
    {
        $year = $this->normalizeYear($year);
        $cacheKey = 'homepage.personal.lms-summary.v1:'.strtoupper(trim($kodeKaryawan)).':'.($year ?? 'cur');

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            self::KPI_LMS_SUMMARY_CACHE_TTL,
            fn () => $this->homepageLmsService->getSummary($kodeKaryawan, $year),
        );
    }

    public function getLeaveSummary(string $kodeKaryawan): array
    {
        return $this->buildLeaveSummary($kodeKaryawan);
    }

    /** Item submission per halaman di homepage. Kecil supaya query AllTransactions ringan. */
    private const SUBMISSIONS_PER_PAGE = 4;

    /** Item Action Center per halaman. Paginasi backend supaya tarik data ringan. */
    private const ACTION_CENTER_PER_PAGE = 5;

    /**
     * Ringkasan pengajuan PER HALAMAN. Homepage hanya memuat 4 terbaru di awal
     * (page 1); halaman berikutnya diminta on-demand lewat endpoint paginasi,
     * sehingga interaksi dengan AllTransactionsReadService (union 9 view) per load
     * jauh lebih kecil. Mengembalikan items + meta paginasi.
     */
    public function getSubmissionsSummary(string $kodeKaryawan, int $page = 1, int $perPage = self::SUBMISSIONS_PER_PAGE): array
    {
        return $this->buildSubmissions($kodeKaryawan, max(1, $page), max(1, $perPage));
    }

    /** Maks item submission saat butuh SEMUA (mis. rekonsiliasi notifikasi aktif). */
    private const SUBMISSIONS_ALL_LIMIT = 50;

    /**
     * Daftar item submission (flat) untuk kebutuhan INTERNAL yang perlu memindai
     * semua pengajuan aktif (rekonsiliasi notifikasi), bukan tampilan paginasi.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function getSubmissionItems(string $kodeKaryawan): array
    {
        return $this->buildSubmissions($kodeKaryawan, 1, self::SUBMISSIONS_ALL_LIMIT)['items'] ?? [];
    }

    public function getUpcomingScheduleSummary(
        string $kodeKaryawan,
        ?array $kpiSummary = null,
        ?array $lmsSummary = null,
    ): array {
        return $this->buildUpcomingSchedule(
            $kodeKaryawan,
            $kpiSummary ?? $this->getKpiSummary($kodeKaryawan),
            $lmsSummary ?? $this->getLmsSummary($kodeKaryawan),
        );
    }

    protected function resolveCutoffRange(Carbon $now, ?string $cutoffPeriod = null): array
    {
        if ($cutoffPeriod && preg_match('/^\d{4}-\d{2}$/', $cutoffPeriod)) {
            [$year, $month] = array_map('intval', explode('-', $cutoffPeriod));

            if ($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12) {
                return $this->uDashReadModelService->getCutoffRangeForMonth($year, $month);
            }
        }

        return $this->uDashReadModelService->getCutoffRange($now);
    }

    protected function buildCutoffPeriodOptions(array $selectedRange): array
    {
        $selectedStart = $selectedRange['start_date']->copy()->startOfMonth();
        $currentStart = $this->uDashReadModelService
            ->getCutoffRange(Carbon::now('Asia/Jakarta'))['start_date']->copy()
            ->startOfMonth();

        // Hanya 2 cutoff: bulan ini + 1 bulan sebelumnya. Periode yang sedang
        // dipilih tetap dimasukkan (push) agar dropdown tak kehilangan opsi aktif
        // bila user membuka periode di luar 2 bulan itu (mis. dari link lama).
        $anchors = collect(range(-1, 0))
            ->map(fn (int $offset) => $currentStart->copy()->addMonthsNoOverflow($offset))
            ->push($selectedStart)
            ->unique(fn (Carbon $date) => $date->format('Y-m'))
            ->sortByDesc(fn (Carbon $date) => $date->format('Y-m'))
            ->values();

        return $anchors
            ->map(function (Carbon $anchor) use ($selectedRange) {
                $range = $this->uDashReadModelService->getCutoffRangeForMonth(
                    (int) $anchor->format('Y'),
                    (int) $anchor->format('m'),
                );

                return [
                    'value' => $range['start_date']->format('Y-m'),
                    'label' => (string) ($range['label'] ?? $range['start_date']->translatedFormat('F Y')),
                    'start' => $range['start_date']->toDateString(),
                    'end' => $range['end_date']->toDateString(),
                    'selected' => $range['start_date']->isSameMonth($selectedRange['start_date']),
                ];
            })
            ->values()
            ->all();
    }

    protected function emptyAttendanceCompositionStats(): array
    {
        return [
            'on-time' => 0,
            'late' => 0,
            'early-out' => 0,
            'no-in-out' => 0,
            'izin-cuti' => 0,
            'off' => 0,
        ];
    }

    protected function classifyAttendanceCompositionRecord(array $state, ?array $row): string
    {
        if (! empty($state['izin']) || ! empty($state['sakit']) || ! empty($state['cuti'])) {
            return 'izin-cuti';
        }

        if (! empty($state['no_checkin']) || ! empty($state['no_checkout']) || ! empty($state['alpha'])) {
            return 'no-in-out';
        }

        // Termasuk keterlambatan yang dimaafkan SP.
        //
        // Tanpa ini donut komposisi menempatkan hari yang terlambat-dimaafkan
        // ke ember "Hadir tepat waktu", sementara penghitung kalender di
        // halaman yang sama sudah menyebutnya terlambat. $state['late'] sengaja
        // TIDAK diubah: ia ikut menentukan lencana dan status utama hari itu,
        // dan keputusan kita adalah lencana tetap mengikuti angka resmi SP.
        if (! empty($state['late']) || ! empty($row['keterlambatan']['terlambat_sebenarnya'])) {
            return 'late';
        }

        if (! empty($state['early_out'])) {
            return 'early-out';
        }

        if (
            $this->isPresentAttendanceRecord([
                ...$state,
                'check_in' => $row['check_in'] ?? null,
                'status' => $state['status'] ?? null,
            ])
        ) {
            return 'on-time';
        }

        if (! empty($state['no_shift'])) {
            return 'off';
        }

        return 'off';
    }

    protected function formatAttendanceCompositionPayload(array $cutoffRange, array $counts): array
    {
        $onTimeCount = (int) ($counts['on-time'] ?? 0);
        $lateCount = (int) ($counts['late'] ?? 0);
        $presentCount = $onTimeCount + $lateCount;

        $definitions = [
            [
                'key' => 'present',
                'label' => 'Hadir',
                'tone' => 'success',
                'color' => '#16a34a',
                'filter' => 'all',
                'count' => $presentCount,
            ],
            [
                'key' => 'on-time',
                'label' => 'On-time',
                'tone' => 'success',
                'color' => '#22c55e',
                'filter' => 'on-time',
                'count' => $onTimeCount,
            ],
            [
                'key' => 'late',
                'label' => 'Terlambat',
                'tone' => 'warning',
                'color' => '#f59e0b',
                'filter' => 'late',
                'count' => $lateCount,
            ],
            [
                'key' => 'early-out',
                'label' => 'Pulang Cepat',
                'tone' => 'warning',
                'color' => '#fb923c',
                'filter' => 'early-out',
                'count' => (int) ($counts['early-out'] ?? 0),
            ],
            [
                'key' => 'no-in-out',
                'label' => 'No IN/OUT',
                'tone' => 'danger',
                'color' => '#ef4444',
                'filter' => 'no-in-out',
                'count' => (int) ($counts['no-in-out'] ?? 0),
            ],
            [
                'key' => 'izin-cuti',
                'label' => 'Izin/Cuti',
                'tone' => 'info',
                'color' => '#2563eb',
                'filter' => 'izin-cuti',
                'count' => (int) ($counts['izin-cuti'] ?? 0),
            ],
            [
                'key' => 'off',
                'label' => 'Libur/Off',
                'tone' => 'muted',
                'color' => '#64748b',
                'filter' => 'all',
                'count' => (int) ($counts['off'] ?? 0),
            ],
        ];

        $stats = $definitions;
        $totalDays =
            collect($stats)
                ->whereNotIn('key', ['present', 'on-time', 'late', 'off'])
                ->sum('count') + $presentCount;
        $percentage = $totalDays > 0 ? (int) round(($presentCount / $totalDays) * 100) : 0;

        return [
            'cutoffRange' => [
                'start' => $cutoffRange['start_date']->toDateString(),
                'end' => $cutoffRange['end_date']->toDateString(),
                'label' => (string) ($cutoffRange['label'] ?? $cutoffRange['start_date']->translatedFormat('F Y')),
                'period' => $cutoffRange['start_date']->format('Y-m'),
            ],
            'cutoffPeriodOptions' => $this->buildCutoffPeriodOptions($cutoffRange),
            'stats' => $stats,
            'series' => collect($stats)->pluck('count')->values()->all(),
            'labels' => collect($stats)->pluck('label')->values()->all(),
            'summary' => [
                'totalDays' => $totalDays,
                'presentCount' => $presentCount,
                'percentage' => $percentage,
                'percentLabel' => $percentage.'%',
                'periodLabel' => (string) ($cutoffRange['label'] ?? $cutoffRange['start_date']->translatedFormat('F Y')),
            ],
        ];
    }

    public function getNeedsAction(
        string $kodeKaryawan,
        string $userIdAbsen,
        ?int $kpiYear = null,
        int $page = 1,
    ): array {
        $perPage = self::ACTION_CENTER_PER_PAGE;
        $page = max(1, $page);

        // Deteksi/sync notif hanya di halaman pertama (initial load) — sama seperti
        // sebelumnya untuk load awal. Pindah halaman berikutnya HANYA membaca data
        // supaya tarik data ringan; deteksi tak perlu diulang tiap paging.
        // Generasi/sync notifikasi (berat: getKpiSummary ~20 query + submissions
        // union-9-view + recheck absensi + tulis notif) dipindah KE QUEUE agar
        // tidak membebani request user & CPU DB saat rush pagi. Request ini cukup
        // MEMBACA notifikasi yang sudah ada. Throttle 5 menit/user jadi gerbang
        // dispatch; ditandai SEBELUM dispatch agar tidak dobel. Bila queue mati,
        // notif lama tetap tampil — hanya generasi yang tertunda, bukan error.
        if ($page === 1 && ! $this->needsActionSyncedRecently($kodeKaryawan)) {
            $this->markNeedsActionSynced($kodeKaryawan);

            try {
                \App\Jobs\ESS\Dashboard\Personal\SyncPersonalNeedsActionJob::dispatch($kodeKaryawan, $userIdAbsen, $kpiYear);
            } catch (\Throwable $e) {
                // Dispatch adalah best-effort. Data notifikasi yang sudah tersimpan
                // tetap boleh dibaca meskipun queue/provider belum tersedia.
                Log::channel('homepageLog')->warning('SyncPersonalNeedsActionJob dispatch gagal; lanjut membaca cache.', [
                    'kode_karyawan' => $kodeKaryawan,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $window = $this->currentActionCenterWindow();
        // Hitung total (paginasi) & total belum-dibaca (badge lintas halaman) tanpa
        // menarik semua baris.
        $total = $this->countActiveDashboardNotificationRows($kodeKaryawan, $window);
        $unreadTotal = $this->countActiveDashboardNotificationRows($kodeKaryawan, $window, true);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);
        $offset = ($currentPage - 1) * $perPage;

        // Notifikasi absensi sudah pindah ke notifikasi utama (MAIN_NOTIF), jadi
        // tidak lagi digabung ke Action Center. Hanya KPI/LMS/Submission di sini.
        // Urutan & paginasi ditegakkan di DB (baca-ke-bawah, prioritas, terbaru).
        $rows = $this->loadActiveDashboardNotificationRows($kodeKaryawan, $perPage, $window, $offset);
        $items = $this->buildCachedDashboardActionItems($rows)->values();

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
            'summary' => [
                'total_active_items' => $total,
                'unread_active_items' => $unreadTotal,
            ],
        ];
    }

    public function getNeedsActionBadge(string $kodeKaryawan, string $userIdAbsen, ?int $kpiYear = null): array
    {
        // Sync absensi tetap jalan supaya notifikasi MAIN_NOTIF terbentuk, tapi
        // badge Action Center tidak lagi menghitung absensi (sudah pindah ke bell).
        $this->syncPersonalDashboardAttendanceNotificationsWithState($kodeKaryawan, $userIdAbsen, null, [
            'skip_bootstrap_without_state' => true,
        ]);

        // Badge hanya menghitung item yang BELUM dibaca; item read (abu-abu)
        // tetap tampil di panel tapi tidak menambah angka badge.
        $unread = $this->loadActiveDashboardNotificationRows(
            $kodeKaryawan,
            11,
            $this->currentActionCenterWindow(),
        )->filter(fn ($row) => strtoupper((string) ($row->Is_Read ?? 'T')) !== 'Y');
        $total = min(11, $unread->count());
        $limitedCount = min($total, 10);

        return [
            'count' => $limitedCount,
            'label' => $total > 10 ? '10+' : (string) $limitedCount,
            'has_items' => $total > 0,
            'is_capped' => $total > 10,
        ];
    }

    /**
     * Revalidasi-on-read: dipanggil saat user membuka/refresh bell (notif utama)
     * atau pusat notifikasi. Untuk setiap notifikasi "issue" yang masih aktif,
     * cek kondisi terkini ke sumber datanya; bila sudah solve, resolve notif
     * (Is_Deleted='Y', Is_Read='Y') supaya tidak naik lagi.
     *
     * Berbeda dari sync periodik, revalidasi ini MENYERTAKAN hari ini — sehingga
     * setelah user checkout di hari yang sama, notif "belum checkout" hari ini
     * langsung hilang pada refresh berikutnya (tidak menunggu besok).
     *
     * Hanya menyentuh tanggal yang punya notif absensi aktif (1-3 tanggal), jadi
     * ringan. Dibatasi throttle REVALIDATE_MIN_INTERVAL detik per user.
     */
    public function revalidateActiveNotifications(string $kodeKaryawan, string $userIdAbsen): void
    {
        try {
            $now = Carbon::now('Asia/Jakarta');
            $periodKey = $now->format('Y-m');

            $state = $this->notificationSyncStateService->getState(
                $kodeKaryawan,
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

            $this->revalidateActiveAttendanceNotifications($kodeKaryawan, $userIdAbsen);
            $this->revalidateNonAttendanceNotifications($kodeKaryawan);

            // Simpan WAKTU SEKARANG (bukan startOfDay) supaya throttle benar bekerja:
            // sebelumnya disimpan startOfDay() -> diffInSeconds selalu ribuan detik ->
            // throttle REVALIDATE_MIN_INTERVAL tak pernah aktif -> revalidasi berat
            // jalan tiap panggilan. Dengan now(), revalidasi maks 1x per interval.
            $this->notificationSyncStateService->upsertState(
                $kodeKaryawan,
                NotificationService::DELIVERY_IN_APP,
                self::REVALIDATE_SYNC_SCOPE,
                $periodKey,
                $now->copy(),
                ['source' => self::DASHBOARD_SOURCE],
            );
        } catch (\Throwable $e) {
            Log::error('HomepagePersonalDashboardService revalidate error: '.$e->getMessage());
        }
    }

    /**
     * Cek ulang notif absensi aktif (ALPHA/NO_CI/NO_CO/PULANG_CEPAT) terhadap
     * status absensi terkini. Hanya tanggal yang punya notif aktif yang di-query
     * (mode SYNC, recompute), termasuk HARI INI. Notif yang issue-nya sudah hilang
     * akan di-resolve oleh syncPersonalDashboardAttendanceNotifications().
     */
    protected function revalidateActiveAttendanceNotifications(string $kodeKaryawan, string $userIdAbsen): void
    {
        $cutoffStart = $this->currentCutoffStartDate();
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $dates = $this->loadActiveAttendanceNotificationRows($kodeKaryawan, $cutoffStart)
            ->filter(
                fn ($row) => in_array((string) ($row->Type ?? ''), ['ALPHA', 'NO_CI', 'NO_CO', 'PULANG_CEPAT'], true),
            )
            ->pluck('Reference_Id')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date, 'Asia/Jakarta')->toDateString())
            ->filter(fn ($date) => $date <= $today)
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return;
        }

        $records = collect();
        foreach ($this->dateRangesFromStrings($dates) as $range) {
            $bundle = $this->buildAttendanceBundle($kodeKaryawan, $userIdAbsen, $range['start'], $range['end']);
            $records = $records->merge($bundle['records'] ?? []);
        }

        $this->resolveSolvedAttendanceNotifications($kodeKaryawan, $records, $dates);
    }

    /**
     * Resolve notif absensi untuk TANGGAL yang sudah dicek (termasuk hari ini):
     * untuk tiap tanggal, type yang issue-nya tidak lagi aktif akan di-resolve.
     * Tidak membuat notif baru (create tetap tanggung jawab sync periodik yang
     * mengecualikan hari ini agar tidak men-generate notif prematur).
     */
    protected function resolveSolvedAttendanceNotifications(
        string $kodeKaryawan,
        Collection $records,
        Collection $checkedDates,
    ): void {
        $types = ['ALPHA', 'NO_CI', 'NO_CO', 'PULANG_CEPAT'];
        $recordsByDate = $records->keyBy(fn ($record) => (string) (((array) $record)['date'] ?? ''));

        // Kumpulkan SEMUA key yang perlu di-resolve (notif absen yang issue-nya sudah
        // hilang untuk tanggal ybs), lalu 1x batch UPDATE -> sebelumnya resolveByKey
        // dipanggil per-(tanggal×type) = puluhan UPDATE (biang query cold needs-action).
        $keysToResolve = [];
        $checkedDates->each(function (string $date) use ($kodeKaryawan, $types, $recordsByDate, &$keysToResolve) {
            $record = (array) ($recordsByDate->get($date) ?? []);
            $activeTypes = $this->resolveDashboardNotificationTypes($record);

            foreach (array_diff($types, $activeTypes) as $resolvedType) {
                $keysToResolve[] = NotificationService::dashboardPersonalKey($kodeKaryawan, $date, $resolvedType);
            }
        });

        $this->notificationService->resolveManyByKey($keysToResolve, $kodeKaryawan);
    }

    /**
     * Revalidasi notif non-absensi (KPI/LMS/Submission) terhadap kondisi terkini.
     * Reuse logika sync/resolve yang sudah ada; semuanya ringan (tanpa SP absensi).
     */
    protected function revalidateNonAttendanceNotifications(string $kodeKaryawan): void
    {
        $kpiSummary = $this->getKpiSummary($kodeKaryawan);

        // KPI: resolve hanya bila jadwal sudah tidak aktif ATAU sudah disubmit.
        // Tidak men-sync/membuat notif baru di jalur revalidasi (itu tugas sync).
        if (empty($kpiSummary['parameter']['isScheduleActive']) || ! empty($kpiSummary['parameter_submitted'])) {
            $this->resolvePersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PARAMETER');
        }

        if (empty($kpiSummary['performance']['isScheduleActive']) || ! empty($kpiSummary['performance_submitted'])) {
            $this->resolvePersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PERFORMANCE');
        }

        $lmsSummary = $this->getLmsSummary($kodeKaryawan);
        if ((int) ($lmsSummary['mandatory_pending'] ?? 0) <= 0) {
            $this->resolvePersonalDashboardLmsNotification($kodeKaryawan, $lmsSummary);
        }

        $activeSubmissions = collect($this->getSubmissionItems($kodeKaryawan))
            ->filter(fn ($item) => in_array((string) ($item['status'] ?? ''), ['pending', 'rejected', 'expired'], true))
            ->keyBy(fn ($item) => (string) ($item['no_transaksi'] ?? ($item['id'] ?? '')));

        $this->loadActiveDashboardNotificationRows($kodeKaryawan, 50, $this->currentActionCenterWindow())
            ->filter(fn ($row) => str_starts_with((string) ($row->Type ?? ''), 'SUBMISSION_'))
            ->each(function ($row) use ($kodeKaryawan, $activeSubmissions) {
                $referenceId = (string) ($row->Reference_Id ?? '');
                if (! $activeSubmissions->has($referenceId)) {
                    $this->notificationService->resolveByKey((string) $row->Notification_Key, $kodeKaryawan);
                }
            });
    }

    public function getCalendarDayDetail(string $kodeKaryawan, string $userIdAbsen, string $date): array
    {
        $detail = $this->getAttendanceDayDetail($kodeKaryawan, $userIdAbsen, $date);
        $raw = $this->uDashReadModelService->getAbsenDetailData($kodeKaryawan, $userIdAbsen, $date) ?? [
            'data' => [],
            'checkInOut' => [],
        ];
        $selected = collect($raw['checkInOut'] ?? [])->first() ?? [];
        $selectedCheckIn = ! empty($selected['CheckIn'] ?? null)
            ? Carbon::parse($selected['CheckIn'])->format('H:i')
            : null;
        $selectedCheckOut = ! empty($selected['CheckOut'] ?? null)
            ? Carbon::parse($selected['CheckOut'])->format('H:i')
            : null;

        $detail['raw_logs'] = collect($raw['data'] ?? [])
            ->map(function ($row, int $index) use ($selectedCheckIn, $selectedCheckOut) {
                $time = ! empty($row->CheckTime ?? null) ? Carbon::parse($row->CheckTime)->format('H:i:s') : '-';
                $shortTime = substr($time, 0, 5);
                $isOnline = ($row->SENSORID ?? null) === '18';

                return [
                    'id' => 'raw-'.$index,
                    'checktime' => ! empty($row->CheckTime ?? null)
                        ? Carbon::parse($row->CheckTime)->toDateTimeString()
                        : null,
                    'time' => $time,
                    'is_online' => $isOnline,
                    'keterangan' => $row->Keterangan_Absen ?? ($isOnline ? 'ABSEN ONLINE' : 'ABSEN OFFICE'),
                    'lokasi' => ! $isOnline ? $row->Lokasi_Absen ?? null : null,
                    'is_selected_checkin' => $selectedCheckIn && $shortTime === $selectedCheckIn,
                    'is_selected_checkout' => $selectedCheckOut && $shortTime === $selectedCheckOut,
                ];
            })
            ->values()
            ->all();
        $detail['selected_checkin'] = $selectedCheckIn;
        $detail['selected_checkout'] = $selectedCheckOut;
        $detail['selected_shift'] = [
            'name' => $selected['Nama_Shift'] ?? null,
            'jam_masuk' => ! empty($selected['Jam_Masuk'] ?? null)
                ? Carbon::parse($selected['Jam_Masuk'])->format('H:i')
                : null,
            'jam_keluar' => ! empty($selected['Jam_Keluar'] ?? null)
                ? Carbon::parse($selected['Jam_Keluar'])->format('H:i')
                : null,
        ];

        return $detail;
    }

    public function getWeeklyCalendar(string $kodeKaryawan, string $userIdAbsen, ?string $weekStart = null): array
    {
        $startDate = $weekStart
            ? Carbon::parse($weekStart, 'Asia/Jakarta')->startOfDay()
            : Carbon::now('Asia/Jakarta')->startOfDay();
        $startDate->subDays($startDate->dayOfWeek);
        $endDate = $startDate->copy()->addDays(6);
        $bundle = $this->buildAttendanceBundle($kodeKaryawan, $userIdAbsen, $startDate->copy(), $endDate->copy());

        return [
            'weekStart' => $startDate->toDateString(),
            'weekEnd' => $endDate->toDateString(),
            'attendanceRecords' => $bundle['records'],
        ];
    }

    public function getAttendanceDayDetail(string $kodeKaryawan, string $userIdAbsen, string $date): array
    {
        $targetDate = Carbon::parse($date, 'Asia/Jakarta');
        $cutoffRange = $this->uDashReadModelService->getCutoffRange($targetDate);
        $bundle = $this->buildAttendanceBundle(
            $kodeKaryawan,
            $userIdAbsen,
            $cutoffRange['start_date']->copy(),
            $cutoffRange['end_date']->copy(),
        );

        return $bundle['details'][$targetDate->toDateString()] ?? [
            'date' => $targetDate->toDateString(),
            'note' => 'Detail absensi tidak ditemukan.',
            'flags' => [],
            'timeline' => [],
            'transactions' => [],
        ];
    }

    protected function buildAttendanceBundle(
        string $kodeKaryawan,
        string $userIdAbsen,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        try {
            // Prefetch rentang GABUNGAN sekali (cutoff berjalan + 4 minggu trend),
            // mengisi cache lintas-request. Semua loadAbsensiRows berikutnya (cutoff
            // 30h, trend 28h, weekly 7h, needs 10h) MENGIRIS dari cache ini -> idealnya
            // 1 SP per homepage load, bukan 1 per endpoint.
            $this->prefetchAbsenUnionRange($userIdAbsen);

            $absensiRows = $this->loadAbsensiRows($startDate, $endDate, $userIdAbsen)->keyBy('date');
            $holidaysByDate = DB::table('HRIS_Hari_Libur')
                // Sargable: range setengah-terbuka pada kolom datetime (indeks terpakai)
                // alih-alih CAST(Tanggal AS DATE) yang non-sargable. Semantik sama
                // (inklusif endDate) karena batas atas = endDate+1 tengah malam.
                ->where('Tanggal', '>=', $startDate->toDateString())
                ->where('Tanggal', '<', $endDate->copy()->addDay()->toDateString())
                ->selectRaw('CAST(Tanggal AS DATE) as tanggal, Nama_Hari_Libur')
                ->get()
                ->mapWithKeys(
                    fn ($row) => [
                        Carbon::parse($row->tanggal)->format('Y-m-d') => trim((string) $row->Nama_Hari_Libur),
                    ],
                );
            $approvedByDate = $this->kalenderDisetujuiPerTanggal(
                $kodeKaryawan,
                $startDate->toDateString(),
                $endDate->toDateString(),
            );

            // Untuk panel "Transaksi Terkait": hanya tampilkan transaksi yang SEMUA langkah
            // approval flow-nya sudah disetujui. Transaksi multi-approver yang baru sebagian
            // di-ACC (mis. masih menunggu approver berikutnya) tidak ikut ditampilkan agar
            // tidak terlihat "Disetujui" secara keliru. Tidak memengaruhi pewarnaan kalender.
            $approvedNoTransaksi = $approvedByDate
                ->flatten(1)
                ->pluck('No_Transaksi')
                ->filter()
                ->unique()
                ->values()
                ->all();
            $fullyApprovedMap = $this->uDashReadModelService->getFullyApprovedTransactionMap($approvedNoTransaksi);

            $overtimeByDate = DB::table('Transaksi_Lembur as h')
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
                ->where('d.Kode_Karyawan', $kodeKaryawan)
                ->whereNull('h.Status')
                // Sargable: range setengah-terbuka pada det.Tanggal_Lembur (datetime)
                // menggantikan CAST(... AS DATE) yang non-sargable.
                ->where('det.Tanggal_Lembur', '>=', $startDate->toDateString())
                ->where('det.Tanggal_Lembur', '<', $endDate->copy()->addDay()->toDateString())
                ->select(
                    'det.Tanggal_Lembur',
                    'd.Tanggal_Lembur_Dari',
                    'd.Tanggal_Lembur_Sampai',
                    'd.Alasan',
                    'd.Jenis',
                )
                ->get()
                ->map(function ($row) use ($absensiRows) {
                    $date = Carbon::parse($row->Tanggal_Lembur)->format('Y-m-d');
                    $attendanceRow = $absensiRows->get($date);
                    $row->Koefisien_OT = (float) ($attendanceRow['overtime_hours'] ?? 0);

                    return $row;
                })
                ->groupBy(fn ($row) => Carbon::parse($row->Tanggal_Lembur)->format('Y-m-d'));

            $records = [];
            $details = [];
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $date = $cursor->toDateString();
                $row = $absensiRows->get($date);
                $holidayName = $holidaysByDate->get($date);
                $approvedItems = $approvedByDate->get($date, collect());
                $approvedType = $this->resolveApprovedType($approvedItems);
                $overtimeRows = $overtimeByDate->get($date, collect());
                $recordState = $this->buildAttendanceRecordState($row, $approvedType, $date);
                $timeline = $this->buildTimeline($row);
                $note = $this->buildAttendanceNote($row, $approvedItems, $overtimeRows);
                $transactionSummary = $this->buildTransactionSummary($approvedItems, $overtimeRows, $fullyApprovedMap);

                $flags = $recordState['flags'];
                if ($holidayName) {
                    array_unshift($flags, [
                        'key' => 'hari-libur',
                        'label' => $holidayName,
                        'tone' => 'info',
                        'problem' => false,
                    ]);
                }

                $records[] = [
                    'date' => $date,
                    'status' => $recordState['status'],
                    'check_in' => $row['check_in'] ?? null,
                    'check_out' => $row['check_out'] ?? null,
                    'shift_label' => $holidayName
                        ? 'OFF - '.$holidayName
                        : $row['shift_name'] ?? ($recordState['no_shift'] ? 'OFF' : ''),
                    'holiday_name' => $holidayName,
                    'is_holiday' => ! empty($holidayName),
                    'shift_time' => $row['shift_time'] ?? null,
                    'note' => null,
                    'late' => $recordState['late'],
                    'late_minutes' => (int) ($row['late_minutes'] ?? 0),

                    // Diteruskan EKSPLISIT. Susunan di bawah ini memilih field
                    // satu per satu, tidak menyebar $row, jadi apa pun yang
                    // tidak disebut di sini akan hilang diam-diam sebelum
                    // sampai ke kalender — persis yang terjadi pada percobaan
                    // pertama: sudah ada di hulu, tak pernah tampil di layar.
                    'keterlambatan' => $row['keterlambatan'] ?? null,

                    'no_checkin' => $recordState['no_checkin'],
                    'no_checkout' => $recordState['no_checkout'],
                    'early_out' => $recordState['early_out'],
                    'early_out_minutes' => $recordState['early_out_minutes'],
                    'izin' => $recordState['izin'],
                    'sakit' => $recordState['sakit'],
                    'cuti' => $recordState['cuti'],
                    'no_shift' => $recordState['no_shift'],
                    'alpha' => $recordState['alpha'],
                    'waiting_shift' => $recordState['waiting_shift'],
                    'working' => $recordState['working'],
                    'future' => $recordState['future'],
                    'expired' => false,
                ];

                $details[$date] = [
                    'date' => $date,
                    'note' => $note,
                    'flags' => $flags,
                    'timeline' => $timeline,
                    'transactions' => $transactionSummary,
                ];

                $cursor->addDay();
            }

            return [
                'records' => $records,
                'details' => $details,
                'overtime_summary' => $this->buildOvertimeSummary(
                    $absensiRows,
                    $overtimeByDate,
                    $kodeKaryawan,
                    $userIdAbsen,
                ),
            ];
        } catch (\Throwable $e) {
            Log::error('HomepagePersonalDashboardService attendance error: '.$e->getMessage());

            return [
                'records' => [],
                'details' => [],
                'overtime_summary' => [
                    'period_label' => 'Periode berjalan',
                    'total_hours' => 0,
                    'workday_hours' => 0,
                    'shift_hours' => 0,
                    'before_work_hours' => 0,
                    'after_work_hours' => 0,
                    'holiday_hours' => 0,
                    'active_assignments' => 0,
                    'total_this_month' => 0,
                    'next_assignment_label' => 'Tidak ada assignment aktif.',
                    'assignment_note' => 'Belum ada data lembur aktif.',
                    'monthly_trend' => [],
                ],
            ];
        }
    }

    /**
     * TTL cache absen lintas-request (detik). Komponen homepage = banyak request
     * HTTP terpisah yang minta rentang absen TUMPANG-TINDIH (cutoff 30h, trend 28h,
     * weekly 7h, dst). Memoize hanya hidup 1 request -> request lain ambil SP lagi.
     * Cache server pendek + "superset slicing" (rentang yang sudah di-cache & MENCAKUP
     * rentang diminta -> diiris, tanpa SP baru) menjadikannya efektif ~1x SP per load.
     * Pendek karena HARI INI bisa berubah (check-in/out); split-mode tetap SYNC hari ini.
     */
    private const ABSEN_RANGE_CACHE_TTL = 60;

    /** Guard prefetch rentang gabungan: cukup 1x per (request, user). */
    private array $absenPrefetched = [];

    /**
     * Muat SEKALI rentang absen gabungan (4 minggu trend s/d akhir cutoff berjalan)
     * supaya komponen lain (cutoff, trend, weekly, needs-action) mengiris dari cache
     * lintas-request, bukan memanggil SP sendiri-sendiri. Idempoten per request.
     */
    protected function prefetchAbsenUnionRange(string $userIdAbsen): void
    {
        $listUserId = trim($userIdAbsen);
        if (isset($this->absenPrefetched[$listUserId])) {
            return;
        }
        $this->absenPrefetched[$listUserId] = true;

        $now = Carbon::now('Asia/Jakarta')->startOfDay();
        $cutoff = $this->resolveCutoffRange($now);

        // Awal = paling awal antara (start cutoff) dan (awal minggu 4-minggu-lalu).
        $trendStart = $now->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::ATTENDANCE_TREND_WEEKS - 1);
        $unionStart = $cutoff['start_date']->copy()->startOfDay()->min($trendStart);
        // Akhir = paling akhir antara (end cutoff) dan (akhir minggu ini).
        $unionEnd = $cutoff['end_date']->copy()->startOfDay()->max($now->copy()->endOfWeek(Carbon::SUNDAY));

        // Memuat rentang gabungan -> mengisi cache; pemanggil lain akan CACHE_SLICE.
        $this->loadAbsensiRows($unionStart, $unionEnd, $listUserId);
    }

    protected function loadAbsensiRows(Carbon $startDate, Carbon $endDate, string $userIdAbsen): Collection
    {
        $listUserId = trim($userIdAbsen);
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();
        $memoKey = $listUserId.'|'.$startStr.'|'.$endStr;

        // 1) Memoize intra-request (paling cepat, tanpa serialisasi cache). Aman:
        //    dalam 1 request, hari ini tetap konsisten.
        if (isset($this->absensiRowsMemo[$memoKey])) {
            $this->auditAbsenCall('MEMO_HIT', $startDate, $endDate, $listUserId);

            return $this->absensiRowsMemo[$memoKey];
        }

        // REALTIME WINDOW = KEMARIN + HARI INI (selalu SYNC fresh, TAK PERNAH dari
        // cache). Bagian STABIL (< kemarin) + FUTURE boleh di-cache. Untuk rentang
        // yang MENYENTUH realtime (mis. cutoff berjalan), kita GABUNG: bagian stabil
        // dari cache-slice + bagian realtime SYNC fresh -> tetap hemat tapi 2 hari
        // terakhir selalu akurat.
        $yesterdayStr = Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        $stableEndStr = Carbon::parse($yesterdayStr, 'Asia/Jakarta')->subDay()->toDateString(); // 2 hari lalu
        $touchesRealtime = $endStr >= $yesterdayStr;

        if (! $touchesRealtime) {
            // Rentang seluruhnya STABIL -> cache-slice penuh bila ada.
            $sliced = $this->sliceFromCachedSuperset($listUserId, $startStr, $endStr);
            if ($sliced !== null) {
                $this->auditAbsenCall('CACHE_SLICE', $startDate, $endDate, $listUserId);

                return $this->absensiRowsMemo[$memoKey] = $sliced;
            }
            $this->auditAbsenCall('SP_CALL', $startDate, $endDate, $listUserId);
            $rows = $this->fetchAbsensiRows($startDate, $endDate, $listUserId);
            $this->storeAbsenRangeCache($listUserId, $startStr, $endStr, $rows);

            return $this->absensiRowsMemo[$memoKey] = $rows;
        }

        // Rentang menyentuh realtime: coba ambil bagian STABIL (start..2-hari-lalu)
        // dari cache, lalu SYNC HANYA bagian realtime+future fresh, lalu gabung.
        $stablePart = collect();
        if ($startStr <= $stableEndStr) {
            $stableSlice = $this->sliceFromCachedSuperset($listUserId, $startStr, $stableEndStr);
            if ($stableSlice !== null) {
                $this->auditAbsenCall('CACHE_SLICE', $startDate, Carbon::parse($stableEndStr, 'Asia/Jakarta'), $listUserId);
                $stablePart = $stableSlice;
            } else {
                $this->auditAbsenCall('SP_CALL', $startDate, Carbon::parse($stableEndStr, 'Asia/Jakarta'), $listUserId);
                $stablePart = $this->fetchAbsensiRows($startDate, Carbon::parse($stableEndStr, 'Asia/Jakarta'), $listUserId);
                $this->storeAbsenRangeCache($listUserId, $startStr, $stableEndStr, $stablePart);
            }
        }

        // Bagian REALTIME + FUTURE (kemarin..endDate): SELALU fresh, tanpa cache.
        $freshStartStr = max($startStr, $yesterdayStr);
        $this->auditAbsenCall('SP_CALL', Carbon::parse($freshStartStr, 'Asia/Jakarta'), $endDate, $listUserId);
        $freshPart = $this->fetchAbsensiRows(Carbon::parse($freshStartStr, 'Asia/Jakarta'), $endDate, $listUserId);

        $rows = $stablePart->merge($freshPart)->values();

        return $this->absensiRowsMemo[$memoKey] = $rows;
    }

    /**
     * Cari di cache rentang absen yang MENCAKUP [from,to] untuk user ini, lalu iris
     * baris yang masuk rentang. Return null bila tak ada superset di cache.
     */
    protected function sliceFromCachedSuperset(string $listUserId, string $from, string $to): ?Collection
    {
        $index = \Illuminate\Support\Facades\Cache::get($this->absenRangeIndexKey($listUserId));
        if (! is_array($index)) {
            return null;
        }

        foreach ($index as $entry) {
            // entry: ['from' => Y-m-d, 'to' => Y-m-d]. Superset bila from<=req.from & to>=req.to.
            if (($entry['from'] ?? '') <= $from && ($entry['to'] ?? '') >= $to) {
                $cached = \Illuminate\Support\Facades\Cache::get(
                    $this->absenRangeCacheKey($listUserId, $entry['from'], $entry['to']),
                );
                if ($cached instanceof Collection) {
                    return $cached
                        ->filter(fn ($row) => isset($row['date']) && $row['date'] >= $from && $row['date'] <= $to)
                        ->values();
                }
            }
        }

        return null;
    }

    /** Simpan hasil SP ke cache lintas-request + daftarkan rentang ke index. */
    protected function storeAbsenRangeCache(string $listUserId, string $from, string $to, Collection $rows): void
    {
        \Illuminate\Support\Facades\Cache::put(
            $this->absenRangeCacheKey($listUserId, $from, $to),
            $rows,
            self::ABSEN_RANGE_CACHE_TTL,
        );

        // Index rentang aktif (untuk pencarian superset). TTL sama; entri kedaluwarsa
        // ikut hilang. Buang entri yang sudah tercakup rentang baru agar index ramping.
        $indexKey = $this->absenRangeIndexKey($listUserId);
        $index = \Illuminate\Support\Facades\Cache::get($indexKey);
        $index = is_array($index) ? $index : [];
        $index = array_values(array_filter(
            $index,
            fn ($e) => ! (($from <= ($e['from'] ?? '')) && ($to >= ($e['to'] ?? ''))),
        ));
        $index[] = ['from' => $from, 'to' => $to];
        \Illuminate\Support\Facades\Cache::put($indexKey, $index, self::ABSEN_RANGE_CACHE_TTL);
    }

    private function absenRangeCacheKey(string $listUserId, string $from, string $to): string
    {
        return 'homepage.absen-range.v1:'.$listUserId.':'.$from.':'.$to;
    }

    private function absenRangeIndexKey(string $listUserId): string
    {
        return 'homepage.absen-range-index.v1:'.$listUserId;
    }

    /**
     * AUDIT SEMENTARA: catat tiap pemanggilan SP absen (atau memoize-hit) untuk
     * mengukur berapa banyak panggilan nyata per homepage load, dari endpoint mana,
     * rentang apa. HAPUS setelah analisis selesai.
     */
    protected function auditAbsenCall(string $kind, Carbon $startDate, Carbon $endDate, string $listUserId): void
    {
        if (! config('homepage_audit.enabled', false)) {
            return;
        }
        $days = $startDate->diffInDays($endDate) + 1;
        // Untuk rentang besar (>60 hari), catat JEJAK PEMANGGIL supaya tahu method
        // mana yang minta rentang lebar (kandidat bottleneck).
        $caller = null;
        if ($kind === 'SP_CALL' && $days > 60) {
            $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12))
                ->map(fn ($f) => ($f['class'] ?? '').'::'.($f['function'] ?? ''))
                ->filter(fn ($s) => str_contains($s, 'Homepage') || str_contains($s, 'UDash'))
                ->take(6)
                ->values()
                ->all();
            $caller = $trace;
        }
        \Illuminate\Support\Facades\Log::channel('homepageLog')->info('AUDIT absen '.$kind, [
            'kind' => $kind,
            'endpoint' => request()?->path(),
            'user_id_absen' => $listUserId,
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString(),
            'days' => $days,
            'req_id' => $this->auditRequestId(),
            'caller' => $caller,
        ]);
    }

    /** ID stabil per request HTTP untuk mengelompokkan log audit 1 load. */
    protected function auditRequestId(): string
    {
        static $id = null;
        if ($id === null) {
            $id = substr(md5(uniqid('', true)), 0, 8);
        }

        return $id;
    }

    /**
     * Ambil absen dengan pemisahan mode:
     * - MASA LALU (start..kemarin): mode AUTO. Tanggal VALID dibaca langsung dari
     *   snapshot tanpa recalc; yang MISSING/STALE banyak -> wrapper jatuhkan ke
     *   ASYNC (queue), TIDAK recalc inline di request.
     * - HARI INI (jika termasuk rentang): mode SYNC, hanya 1 hari -> ringan &
     *   realtime (shift berlangsung, check-in/out bisa berubah).
     * Hasil kedua bagian digabung (hari ini menimpa baris snapshot lama bila ada).
     */
    protected function fetchAbsensiRows(Carbon $startDate, Carbon $endDate, string $listUserId): Collection
    {
        // Bandingkan via STRING tanggal (Y-m-d), bukan instant Carbon: parsing
        // tanggal bisa menghasilkan timezone berbeda (UTC vs Asia/Jakarta) yang
        // membuat perbandingan instant meleset 7 jam.
        $today = Carbon::now('Asia/Jakarta');
        $todayStr = $today->toDateString();
        $yesterdayStr = $today->copy()->subDay()->toDateString();
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        // REALTIME WINDOW = KEMARIN + HARI INI: selalu SYNC fresh, TIDAK boleh dari
        // cache (absensi 2 hari terakhir sering masih berubah: check-in/out telat,
        // koreksi, approval). Lihat juga loadAbsensiRows (cache mengecualikan window ini).
        $realtimeStartStr = $yesterdayStr;

        $merged = collect();

        // 1) MASA LALU STABIL (start .. lusa/2 hari lalu): mode AUTO, boleh di-cache.
        $stableEndStr = Carbon::parse($realtimeStartStr, 'Asia/Jakarta')->subDay()->toDateString();
        if ($startStr <= $stableEndStr) {
            $stableEnd = $stableEndStr < $endStr ? $stableEndStr : $endStr;
            $pastDays = Carbon::parse($startStr, 'Asia/Jakarta')
                ->diffInDays(Carbon::parse($stableEnd, 'Asia/Jakarta')) + 1;
            $maxSync = $this->resolveMaxSyncUserDate($listUserId, $pastDays);
            $merged = $this->fetchAbsensiFromSp($startStr, $stableEnd, $listUserId, 'AUTO', $maxSync);
        }

        // 2) REALTIME (kemarin..hari ini, dibatasi rentang yang diminta): SYNC fresh.
        $rtFrom = max($startStr, $realtimeStartStr);
        $rtTo = min($endStr, $todayStr);
        if ($rtFrom <= $rtTo) {
            $rtDays = Carbon::parse($rtFrom, 'Asia/Jakarta')
                ->diffInDays(Carbon::parse($rtTo, 'Asia/Jakarta')) + 1;
            $maxSyncRt = $this->resolveMaxSyncUserDate($listUserId, $rtDays);
            $rtRows = $this->fetchAbsensiFromSp($rtFrom, $rtTo, $listUserId, 'SYNC', $maxSyncRt);
            // Realtime menimpa entri lama (kemarin) dari bagian stabil bila ada.
            $merged = $merged
                ->reject(fn ($row) => ($row['date'] ?? null) >= $rtFrom && ($row['date'] ?? null) <= $rtTo)
                ->merge($rtRows);
        }

        // 3) FUTURE (besok..endDate): mode AUTO (jadwal + approval, stabil), boleh cache.
        $futureStartStr = $today->copy()->addDay()->toDateString();
        if ($futureStartStr <= $endStr) {
            $futureDays = Carbon::parse($futureStartStr, 'Asia/Jakarta')
                ->diffInDays(Carbon::parse($endStr, 'Asia/Jakarta')) + 1;
            $maxSyncFuture = $this->resolveMaxSyncUserDate($listUserId, $futureDays);
            $futureRows = $this->fetchAbsensiFromSp($futureStartStr, $endStr, $listUserId, 'AUTO', $maxSyncFuture);
            $merged = $merged->merge($futureRows);
        }

        return $merged->values();
    }

    /**
     * Hitung ambang SYNC efektif: cukup besar agar SEMUA user-date dalam rentang
     * personal terproses inline (tidak balik kosong), dibatasi plafon keras.
     * jumlahUser dihitung dari listUserId (dipisah koma).
     */
    protected function resolveMaxSyncUserDate(string $listUserId, int $days): int
    {
        $userCount = max(1, count(array_filter(array_map('trim', explode(',', $listUserId)), 'strlen')));
        $needed = $userCount * max(1, $days);

        return min(self::ABSEN_MAX_SYNC_HARD, max(self::ABSEN_MAX_SYNC_USER_DATE, $needed));
    }

    /**
     * Panggilan SP absen mentah + mapping baris. requestedBy di-resolve sekali.
     */
    protected function fetchAbsensiFromSp(
        string $tglMulai,
        string $tglSelesai,
        string $listUserId,
        string $executionMode,
        int $maxSyncUserDate = self::ABSEN_MAX_SYNC_USER_DATE,
    ): Collection {
        $sourceReuqest = 'homepage-personal-dashboard-service-load-absensi';
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

        $rows = $response['data'] ?? [];

        return collect($rows)->map(function ($row) {
            $date = Carbon::parse($row->Tanggal)->format('Y-m-d');
            $jamMasuk = isset($row->Jam_Masuk) ? substr((string) $row->Jam_Masuk, 0, 5) : null;
            $jamKeluar = isset($row->Jam_Keluar) ? substr((string) $row->Jam_Keluar, 0, 5) : null;

            return [
                'date' => $date,
                'shift_name' => trim((string) ($row->Nama_Shift ?? 'NO SHIFT')),
                'status_hari' => strtoupper(trim((string) ($row->StatusHari ?? 'WORK'))),
                'jam_masuk' => $jamMasuk,
                'jam_keluar' => $jamKeluar,
                'shift_time' => $jamMasuk && $jamKeluar ? "{$jamMasuk} - {$jamKeluar}" : null,
                'check_in' => ! empty($row->CheckIn) ? Carbon::parse($row->CheckIn)->format('H:i') : null,
                'check_out' => ! empty($row->CheckOut) ? Carbon::parse($row->CheckOut)->format('H:i') : null,
                'late_minutes' => (int) round((float) ($row->RealLate ?? 0)),
                'early_minutes' => (int) round((float) ($row->RealEarly ?? 0)),
                'overtime_hours' => $this->extractKoefisienOt($row),
                // Komponen lembur MURNI dari SP (menit) — untuk ringkasan akurat per komponen
                'overtime_hk_minutes' => (int) round((float) ($row->Overtime ?? 0)), // LEMBUR / LEMBUR_LIBUR
                'overtime_awal_minutes' => (int) round((float) ($row->Overtime_Before ?? 0)), // LEMBUR_AWAL
                'overtime_shift_minutes' => (int) round((float) ($row->Overtime_Shift ?? 0)), // long shift (otomatis)
                'overtime_calculated_minutes' => (int) round((float) ($row->Overtime_Calculated ?? 0)), // total bersih
                'flag_lembur_libur' => strtoupper((string) ($row->Flag_Lembur_Libur ?? 'T')) === 'Y',
                'keterangan' => $row->Keterangan ?? null,

                // late_minutes di atas adalah angka SP yang SUDAH dimaafkan —
                // nol untuk orang yang datang terlambat tetapi masih dalam
                // toleransi. Tanpa keterangan ini, kalender tidak punya cara
                // membedakannya dari yang benar-benar tepat waktu.
                //
                // Dikirim BERDAMPINGAN, bukan menggantikan: satu tanggal bisa
                // menyandang beberapa keadaan sekaligus (terlambat DAN tanpa
                // check-out), dan keduanya harus tetap terbaca.
                'keterlambatan' => KlasifikasiKeterlambatan::dari($row),
            ];
        });
    }

    protected function isAttendanceIssueRecord(array $record): bool
    {
        return ! empty($record['alpha']) ||
            ! empty($record['no_checkin']) ||
            ! empty($record['no_checkout']) ||
            ! empty($record['early_out']);
    }

    /**
     * Dipanggil dari SyncPersonalNeedsActionJob (background). Public agar bisa
     * dipanggil job. Throttle 5 menit/user ditegakkan DI GERBANG DISPATCH
     * (getNeedsAction) sebelum job di-antrekan, jadi method ini tak perlu cek
     * ulang — hanya berjalan saat memang due.
     */
    public function syncPersonalDashboardNeedsActionCache(
        string $kodeKaryawan,
        string $userIdAbsen,
        ?int $kpiYear = null,
    ): void {
        // Sync absensi self-load baris MAIN_NOTIF-nya sendiri (punya throttle
        // sendiri di dalam method -> aman dipanggil tiap load).
        $this->syncPersonalDashboardAttendanceNotificationsWithState($kodeKaryawan, $userIdAbsen, null);

        // GENERASI notif KPI/LMS/submission = berat (getKpiSummary ~20 query +
        // getSubmissionItems union-9-view + tulis notif).

        $cachedRows = $this->loadActiveDashboardNotificationRows($kodeKaryawan, 50, $this->currentActionCenterWindow());

        $kpiSummary = $this->getKpiSummary($kodeKaryawan, $kpiYear);

        $paramActiveAndNotSubmitted =
            ! empty($kpiSummary['parameter']['isScheduleActive']) && empty($kpiSummary['parameter_submitted']);
        $paramActiveAndNotSubmitted
            ? $this->syncPersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PARAMETER')
            : $this->resolvePersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PARAMETER');

        $perfActiveAndNotSubmitted =
            ! empty($kpiSummary['performance']['isScheduleActive']) && empty($kpiSummary['performance_submitted']);
        $perfActiveAndNotSubmitted
            ? $this->syncPersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PERFORMANCE')
            : $this->resolvePersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PERFORMANCE');

        $lmsSummary = $this->getLmsSummary($kodeKaryawan);
        (int) ($lmsSummary['mandatory_pending'] ?? 0) > 0
            ? $this->syncPersonalDashboardLmsNotification($kodeKaryawan, $lmsSummary)
            : $this->resolvePersonalDashboardLmsNotification($kodeKaryawan, $lmsSummary);

        $activeSubmissions = collect($this->getSubmissionItems($kodeKaryawan))
            ->filter(fn ($item) => in_array((string) ($item['status'] ?? ''), ['pending', 'rejected', 'expired'], true))
            ->keyBy(fn ($item) => (string) ($item['no_transaksi'] ?? ($item['id'] ?? '')));

        $activeSubmissions->take(10)->each(function ($submission) use ($kodeKaryawan) {
            $this->syncPersonalDashboardSubmissionNotification($kodeKaryawan, (array) $submission);
        });

        $cachedRows
            ->filter(fn ($row) => str_starts_with((string) ($row->Type ?? ''), 'SUBMISSION_'))
            ->each(function ($row) use ($kodeKaryawan, $activeSubmissions) {
                $referenceId = (string) ($row->Reference_Id ?? '');
                if (! $activeSubmissions->has($referenceId)) {
                    $this->notificationService->resolveByKey((string) $row->Notification_Key, $kodeKaryawan);
                }
            });

        // Catat waktu sync sukses (throttle berikutnya).
        $this->markNeedsActionSynced($kodeKaryawan);
    }

    protected function syncPersonalDashboardAttendanceNotificationsWithState(
        string $kodeKaryawan,
        string $userIdAbsen,
        ?Collection $cachedRows = null,
        array $options = [],
    ): void {
        $now = Carbon::now('Asia/Jakarta')->startOfDay();
        $yesterday = $now->copy()->subDay();
        $cutoffRange = $this->resolveCutoffRange($now);
        $startDate = $cutoffRange['start_date']->copy()->startOfDay();
        $endDate = $cutoffRange['end_date']->copy()->startOfDay()->min($yesterday);

        if ($endDate->lt($startDate)) {
            return;
        }

        $periodKey = $startDate->format('Y-m');

        $state = $this->notificationSyncStateService->getState(
            $kodeKaryawan,
            NotificationService::DELIVERY_IN_APP,
            self::ATTENDANCE_SYNC_SCOPE,
            $periodKey,
        );

        // Guard waktu: kalau full-recheck periode ini baru saja jalan (< interval
        // minimum), skip total. Mengganti pointer tanggal incremental — karena
        // status absensi bisa berubah surut (checktime telat), kita full-recheck
        // seluruh cutoff tiap sync tapi dibatasi frekuensinya lewat Last_Synced_At.
        if ($this->attendanceSyncedRecently($state)) {
            return;
        }

        // Notifikasi absensi sekarang ber-source MAIN_NOTIF (tidak lagi masuk
        // Action Center), jadi tidak bisa lagi mengandalkan $cachedRows dari
        // loadActiveDashboardNotificationRows(). Ambil baris absensi sendiri.
        $cachedRows = $this->loadActiveAttendanceNotificationRows($kodeKaryawan, $startDate);
        $processedUntil = ! empty($state?->Processed_Until)
            ? Carbon::parse($state->Processed_Until, 'Asia/Jakarta')->startOfDay()
            : null;
        $skipBootstrapWithoutState = $processedUntil === null && ! empty($options['skip_bootstrap_without_state']);

        $attendanceTypes = ['ALPHA', 'NO_CI', 'NO_CO', 'PULANG_CEPAT'];
        $dates = $cachedRows
            ->filter(fn ($row) => in_array((string) ($row->Type ?? ''), $attendanceTypes, true))
            ->pluck('Reference_Id')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date, 'Asia/Jakarta')->toDateString());

        if ($skipBootstrapWithoutState) {
            // Badge-only call tanpa state: jangan bootstrap rentang penuh,
            // cukup recheck tanggal yang sudah punya notif (di-merge lookback).
            $dates = $dates->merge(collect());
        } else {
            // Recheck SELURUH rentang cutoff berjalan (start → kemarin) setiap
            // sync. Data absensi bisa berubah surut (checktime telat masuk dari
            // mesin), jadi window incremental + lookback sempit bikin tanggal
            // alpha yang baru "matang" tidak pernah dilirik lagi setelah pointer
            // lewat. Periode cutoff kecil, full recheck aman & idempotent.
            $dates = $dates->merge($this->dateRangeStrings($startDate, $endDate));
        }

        $lookbackStart = $endDate
            ->copy()
            ->subDays(max(0, self::ATTENDANCE_ROLLING_LOOKBACK_DAYS - 1))
            ->max($startDate);
        $dates = $dates
            ->merge($this->dateRangeStrings($lookbackStart, $endDate))
            ->filter(fn ($date) => $date >= $startDate->toDateString() && $date <= $endDate->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($dates->isNotEmpty()) {
            $records = collect();
            foreach ($this->dateRangesFromStrings($dates) as $range) {
                $bundle = $this->buildAttendanceBundle($kodeKaryawan, $userIdAbsen, $range['start'], $range['end']);
                $records = $records->merge($bundle['records'] ?? []);
            }

            $this->syncPersonalDashboardAttendanceNotifications($kodeKaryawan, $records);
        }

        if ($skipBootstrapWithoutState) {
            return;
        }

        $this->notificationSyncStateService->upsertState(
            $kodeKaryawan,
            NotificationService::DELIVERY_IN_APP,
            self::ATTENDANCE_SYNC_SCOPE,
            $periodKey,
            $endDate,
            [
                'source' => self::DASHBOARD_SOURCE,
                'rolling_lookback_days' => self::ATTENDANCE_ROLLING_LOOKBACK_DAYS,
                'cutoff_start' => $startDate->toDateString(),
                'cutoff_end' => $cutoffRange['end_date']->copy()->toDateString(),
                'processed_window_end' => $endDate->toDateString(),
                'checked_dates' => $dates->count(),
            ],
        );
        // upsertState di atas memperbarui Last_Synced_At -> jadi penanda waktu
        // untuk guard attendanceSyncedRecently() pada sync berikutnya.
    }

    /**
     * True kalau full-recheck absensi terakhir masih dalam jeda minimum.
     * Dipakai untuk membatasi frekuensi panggilan SP absensi tanpa cache
     * terpisah (sumber kebenaran: Last_Synced_At di sync-state).
     */
    protected function attendanceSyncedRecently(?object $state): bool
    {
        if (empty($state?->Last_Synced_At)) {
            return false;
        }

        try {
            $lastSynced = Carbon::parse($state->Last_Synced_At);
        } catch (\Throwable) {
            return false;
        }

        return $lastSynced->diffInSeconds(Carbon::now()) < self::ATTENDANCE_SYNC_MIN_INTERVAL;
    }

    /**
     * Throttle generasi notif Action Center (KPI/LMS/submission). Persisten lintas
     * server via N_HRIS_Notification_Sync_State (tahan cache:clear). Return true
     * bila sync terakhir masih dalam NEEDS_ACTION_SYNC_MIN_INTERVAL.
     */
    protected function needsActionSyncedRecently(string $kodeKaryawan): bool
    {
        $state = $this->notificationSyncStateService->getState(
            $kodeKaryawan,
            NotificationService::DELIVERY_IN_APP,
            self::NEEDS_ACTION_SYNC_SCOPE,
            Carbon::now('Asia/Jakarta')->format('Y-m'),
        );

        if (empty($state?->Last_Synced_At)) {
            return false;
        }

        try {
            $lastSynced = Carbon::parse($state->Last_Synced_At);
        } catch (\Throwable) {
            return false;
        }

        return $lastSynced->diffInSeconds(Carbon::now()) < self::NEEDS_ACTION_SYNC_MIN_INTERVAL;
    }

    /** Tandai waktu sync Action Center sukses (untuk throttle berikutnya). */
    protected function markNeedsActionSynced(string $kodeKaryawan): void
    {
        $this->notificationSyncStateService->upsertState(
            $kodeKaryawan,
            NotificationService::DELIVERY_IN_APP,
            self::NEEDS_ACTION_SYNC_SCOPE,
            Carbon::now('Asia/Jakarta')->format('Y-m'),
            Carbon::now('Asia/Jakarta'),
            ['source' => self::DASHBOARD_SOURCE],
        );
    }

    protected function syncPersonalDashboardAttendanceNotifications(string $kodeKaryawan, Collection $records): void
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $types = ['ALPHA', 'NO_CI', 'NO_CO', 'PULANG_CEPAT'];

        // Kumpulkan key resolve (issue absen yang hilang per tanggal) -> 1 batch UPDATE
        // di akhir, bukan resolveByKey per (record×type) yang jadi puluhan UPDATE cold.
        $keysToResolve = [];
        $records
            ->filter(fn ($record) => ! empty($record['date']) && (string) $record['date'] < $today)
            ->each(function ($record) use ($kodeKaryawan, $types, &$keysToResolve) {
                $record = (array) $record;
                $date = (string) $record['date'];
                $activeTypes = $this->resolveDashboardNotificationTypes($record);

                foreach ($activeTypes as $type) {
                    $key = NotificationService::dashboardPersonalKey($kodeKaryawan, $date, $type);
                    if ($this->notificationService->existsDismissedKey($key, $kodeKaryawan)) {
                        continue;
                    }

                    $payload = $this->buildDashboardAttendanceNotificationPayload($kodeKaryawan, $record, $type, $key);
                    $this->notificationService->upsertByKey($payload);
                }

                foreach (array_diff($types, $activeTypes) as $resolvedType) {
                    $keysToResolve[] = NotificationService::dashboardPersonalKey($kodeKaryawan, $date, $resolvedType);
                }
            });

        $this->notificationService->resolveManyByKey($keysToResolve, $kodeKaryawan);
    }

    protected function dateRangeStrings(Carbon $startDate, Carbon $endDate): Collection
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

    protected function dateRangesFromStrings(Collection $dates): array
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

    protected function loadActiveDashboardNotificationRows(
        string $kodeKaryawan,
        int $limit = 50,
        ?array $window = null,
        int $offset = 0,
    ): Collection {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $window ??= $this->currentActionCenterWindow();

        return $this->baseActiveDashboardNotificationQuery($kodeKaryawan, $window)
            // Item yang sudah dibaca diturunkan ke bawah, lalu prioritas, lalu
            // terbaru duluan. Urutan ini ditegakkan di DB supaya paginasi
            // konsisten lintas halaman (bukan cuma reorder di dalam 1 halaman).
            ->orderByRaw("CASE WHEN UPPER(Is_Read) = 'Y' THEN 1 ELSE 0 END ASC")
            ->orderByRaw('COALESCE(Priority, 3) ASC')
            ->orderBy('Created_At', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Jumlah item Action Center aktif dalam window berjalan. Dipakai paginasi
     * (total/last_page) dan angka badge total (unread lintas semua halaman)
     * tanpa perlu menarik semua baris.
     */
    protected function countActiveDashboardNotificationRows(
        string $kodeKaryawan,
        ?array $window = null,
        bool $unreadOnly = false,
    ): int {
        $window ??= $this->currentActionCenterWindow();

        return (int) $this->baseActiveDashboardNotificationQuery($kodeKaryawan, $window)
            ->when($unreadOnly, fn ($query) => $query->whereRaw("UPPER(ISNULL(Is_Read, 'T')) <> 'Y'"))
            ->count();
    }

    /**
     * Query dasar item Action Center aktif (source dashboard, belum dihapus,
     * belum kedaluwarsa, sudah terkirim, dalam window cutoff). Dipakai bersama
     * oleh loader (paginasi) dan penghitung total.
     */
    protected function baseActiveDashboardNotificationQuery(string $kodeKaryawan, array $window)
    {
        return DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where(function ($query) {
                $query->where('Channel', NotificationService::CHANNEL_DASHBOARD_PERSONAL)->orWhere(function ($query) {
                    $query
                        ->where('Channel', NotificationService::DELIVERY_IN_APP)
                        ->whereRaw(
                            "JSON_VALUE(CASE WHEN ISJSON(Meta_JSON) = 1 THEN Meta_JSON ELSE NULL END, '$.source') = ?",
                            [self::DASHBOARD_SOURCE],
                        );
                });
            })
            // Is_Read tidak difilter: item yang sudah dibaca tetap tampil (abu-
            // abu) selama dalam window cutoff. Is_Deleted='T' = belum dihapus.
            ->where('Is_Deleted', 'T')
            ->where(function ($query) {
                $query->whereNull('Expired_At')->orWhere('Expired_At', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('Sent_At')->orWhere('Sent_At', '<=', now());
            })
            // Fokus pada cutoff berjalan: hanya item yang dibuat dalam window
            // [awal cutoff, akhir cutoff + offset]. Lewat window => tidak tampil.
            ->whereBetween('Created_At', [$window['start'], $window['end']]);
    }

    /**
     * Ambil notifikasi absensi (Alpha/No CI/No CO/Pulang Cepat) milik karyawan
     * yang ber-source MAIN_NOTIF. Dipakai sync/resolve absensi karena baris ini
     * sudah tidak masuk loadActiveDashboardNotificationRows() (Action Center).
     */
    protected function loadActiveAttendanceNotificationRows(
        string $kodeKaryawan,
        ?Carbon $attendanceStartDate = null,
    ): Collection {
        $attendanceTypes = ['ALPHA', 'NO_CI', 'NO_CO', 'PULANG_CEPAT'];

        return DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Channel', NotificationService::DELIVERY_IN_APP)
            ->whereIn('Type', $attendanceTypes)
            ->where(function ($query) {
                // Source kolom baru lebih dulu, fallback ke Meta_JSON untuk baris lama.
                $query->where('source', self::ATTENDANCE_SOURCE)->orWhere(function ($query) {
                    $query
                        ->where(function ($query) {
                            $query->whereNull('source')->orWhere('source', '');
                        })
                        ->whereRaw(
                            "JSON_VALUE(CASE WHEN ISJSON(Meta_JSON) = 1 THEN Meta_JSON ELSE NULL END, '$.source') = ?",
                            [self::ATTENDANCE_SOURCE],
                        );
                });
            })
            ->where('Is_Read', 'T')
            ->where('Is_Deleted', 'T')
            ->where(function ($query) {
                $query->whereNull('Expired_At')->orWhere('Expired_At', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('Sent_At')->orWhere('Sent_At', '<=', now());
            })
            ->when($attendanceStartDate, function ($query) use ($attendanceStartDate) {
                $query->where('Reference_Id', '>=', $attendanceStartDate->toDateString());
            })
            ->orderBy('Created_At', 'desc')
            ->limit(100)
            ->get();
    }

    protected function currentCutoffStartDate(): Carbon
    {
        return $this->resolveCutoffRange(Carbon::now('Asia/Jakarta')->startOfDay())['start_date']->copy()
            ->startOfDay();
    }

    /**
     * Rentang waktu Action Center berjalan: dari awal cutoff sampai akhir cutoff
     * + ACTION_CENTER_CUTOFF_END_OFFSET_DAYS. Saat melewati window ini, item
     * cutoff lama tidak lagi tampil dan fokus pindah ke cutoff berikutnya.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function currentActionCenterWindow(): array
    {
        $cutoffRange = $this->resolveCutoffRange(Carbon::now('Asia/Jakarta')->startOfDay());

        return [
            'start' => $cutoffRange['start_date']->copy()->startOfDay(),
            'end' => $cutoffRange['end_date']->copy()->addDays(self::ACTION_CENTER_CUTOFF_END_OFFSET_DAYS)->endOfDay(),
        ];
    }

    protected function refreshCachedDashboardNotifications(
        string $kodeKaryawan,
        string $userIdAbsen,
        Collection $cachedRows,
        ?int $kpiYear = null,
    ): void {
        $attendanceTypes = ['ALPHA', 'NO_CI', 'NO_CO', 'PULANG_CEPAT'];
        $attendanceDates = $cachedRows
            ->filter(fn ($row) => in_array((string) ($row->Type ?? ''), $attendanceTypes, true))
            ->pluck('Reference_Id')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($attendanceDates->isNotEmpty()) {
            $startDate = Carbon::parse($attendanceDates->min(), 'Asia/Jakarta');
            $endDate = Carbon::parse($attendanceDates->max(), 'Asia/Jakarta');
            $bundle = $this->buildAttendanceBundle($kodeKaryawan, $userIdAbsen, $startDate, $endDate);
            $this->syncPersonalDashboardAttendanceNotifications($kodeKaryawan, collect($bundle['records'] ?? []));
        }

        if (
            $cachedRows->contains(
                fn ($row) => in_array((string) ($row->Type ?? ''), ['KPI_PARAMETER', 'KPI_PERFORMANCE'], true),
            )
        ) {
            $kpiSummary = $this->getKpiSummary($kodeKaryawan, $kpiYear);

            $paramActiveAndNotSubmitted =
                ! empty($kpiSummary['parameter']['isScheduleActive']) && empty($kpiSummary['parameter_submitted']);
            $paramActiveAndNotSubmitted
                ? $this->syncPersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PARAMETER')
                : $this->resolvePersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PARAMETER');

            $perfActiveAndNotSubmitted =
                ! empty($kpiSummary['performance']['isScheduleActive']) && empty($kpiSummary['performance_submitted']);
            $perfActiveAndNotSubmitted
                ? $this->syncPersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PERFORMANCE')
                : $this->resolvePersonalDashboardKpiNotification($kodeKaryawan, $kpiSummary, 'KPI_PERFORMANCE');
        }

        if ($cachedRows->contains(fn ($row) => (string) ($row->Type ?? '') === 'LMS_MANDATORY_PENDING')) {
            $lmsSummary = $this->getLmsSummary($kodeKaryawan);
            (int) ($lmsSummary['mandatory_pending'] ?? 0) > 0
                ? $this->syncPersonalDashboardLmsNotification($kodeKaryawan, $lmsSummary)
                : $this->resolvePersonalDashboardLmsNotification($kodeKaryawan, $lmsSummary);
        }

        $submissionRows = $cachedRows->filter(fn ($row) => str_starts_with((string) ($row->Type ?? ''), 'SUBMISSION_'));
        if ($submissionRows->isNotEmpty()) {
            $activeSubmissions = collect($this->getSubmissionItems($kodeKaryawan))
                ->filter(
                    fn ($item) => in_array((string) ($item['status'] ?? ''), ['pending', 'rejected', 'expired'], true),
                )
                ->keyBy(fn ($item) => (string) ($item['no_transaksi'] ?? ($item['id'] ?? '')));

            $submissionRows->each(function ($row) use ($kodeKaryawan, $activeSubmissions) {
                $referenceId = (string) ($row->Reference_Id ?? '');
                $submission = $activeSubmissions->get($referenceId);

                if ($submission) {
                    $this->syncPersonalDashboardSubmissionNotification($kodeKaryawan, (array) $submission);

                    return;
                }

                $this->notificationService->resolveByKey((string) $row->Notification_Key, $kodeKaryawan);
            });
        }
    }

    protected function buildCachedDashboardActionItems(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $type = (string) ($row->Type ?? '');
            $module = (string) ($row->Module ?? 'HCIS');
            $category = match (true) {
                $module === 'KPI' => 'kpi',
                $module === 'LMS' => 'lms',
                str_starts_with($type, 'SUBMISSION_') => 'submission',
                default => 'hcis',
            };

            $isRead = strtoupper((string) ($row->Is_Read ?? 'T')) === 'Y';

            return [
                'id' => 'notification-'.$row->Id_Notification,
                'category' => $category,
                'module' => $category === 'submission' ? 'Pengajuan' : $module,
                'tone' => $this->notificationSeverityTone((string) ($row->Severity ?? 'info')),
                'icon' => $this->notificationActionIcon($category, $type),
                'title' => (string) ($row->Title ?? 'Notifikasi'),
                'description' => (string) ($row->Message ?? ''),
                'actionLabel' => $category === 'submission' ? 'Status' : ($category === 'hcis' ? 'Cek tanggal' : 'Perhatian'),
                'date' => $category === 'hcis' ? (string) ($row->Reference_Id ?? '') : null,
                'href' => $row->Action_Url ?? null,
                'action_url' => $row->Action_Url ?? null,
                'notification_id' => (int) $row->Id_Notification,
                'notification_key' => $row->Notification_Key ?? null,
                'priority' => (int) ($row->Priority ?? 3),
                // is_read: item yang sudah diklik ditandai read -> ditampilkan
                // abu-abu di frontend, tapi tetap muncul (tidak hilang).
                'is_read' => $isRead,
            ];
        });
    }

    protected function buildTodayNeedsActionItems(string $kodeKaryawan, string $userIdAbsen): Collection
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $bundle = $this->buildAttendanceBundle($kodeKaryawan, $userIdAbsen, $today->copy(), $today->copy());

        return collect($bundle['records'] ?? [])
            ->filter(fn ($record) => $this->isAttendanceIssueRecord((array) $record))
            ->map(function ($record) {
                $record = (array) $record;
                $visual = $this->resolvePersonalActionStatus($record);

                return [
                    'id' => 'hcis-today-'.($record['date'] ?? Carbon::now('Asia/Jakarta')->toDateString()),
                    'category' => 'hcis',
                    'module' => 'HCIS',
                    'tone' => $visual['tone'],
                    'icon' => $visual['icon'],
                    'title' => $visual['title'],
                    'description' => Carbon::parse($record['date'])->translatedFormat('d M Y').' - '.$visual['description'],
                    'actionLabel' => 'Cek tanggal',
                    'date' => $record['date'] ?? null,
                    'priority' => 1,
                    'dismissible' => false,
                ];
            })
            ->values();
    }

    protected function notificationSeverityTone(string $severity): string
    {
        return match (strtolower($severity)) {
            'danger', 'error', 'critical', 'high' => 'danger',
            'warning', 'medium' => 'warning',
            'success' => 'success',
            default => 'info',
        };
    }

    protected function notificationActionIcon(string $category, string $type): string
    {
        if ($category === 'kpi') {
            return $type === 'KPI_PERFORMANCE' ? 'bi bi-graph-up-arrow' : 'bi bi-exclamation-circle';
        }
        if ($category === 'lms') {
            return 'bi bi-journal-bookmark';
        }
        if ($category === 'submission') {
            return 'bi bi-file-earmark-text';
        }

        return match ($type) {
            'ALPHA' => 'bi bi-person-x',
            'NO_CI' => 'bi bi-box-arrow-in-right',
            'NO_CO' => 'bi bi-box-arrow-right',
            'PULANG_CEPAT' => 'bi bi-clock-history',
            default => 'bi bi-fingerprint',
        };
    }

    protected function syncPersonalDashboardNotification(array $payload): ?array
    {
        $kodeKaryawan = (string) ($payload['kode_karyawan'] ?? '');
        $notificationKey = (string) ($payload['notification_key'] ?? '');

        if ($kodeKaryawan === '' || $notificationKey === '') {
            return [];
        }

        if ($this->notificationService->existsDismissedKey($notificationKey, $kodeKaryawan)) {
            return null;
        }

        // Action Center: pertahankan status baca saat re-sync, supaya item yang
        // sudah diklik (abu-abu) tidak balik jadi unread selama issue masih ada.
        $payload['preserve_read'] = true;
        $id = $this->notificationService->upsertByKey($payload);

        return [
            'notification_id' => $id,
            'notification_key' => $notificationKey,
            'priority' => (int) ($payload['priority'] ?? 3),
            'is_read' => $id !== null,
        ];
    }

    protected function resolvePrimaryDashboardNotificationType(array $record): ?string
    {
        if (! empty($record['alpha'])) {
            return 'ALPHA';
        }
        if (! empty($record['no_checkin'])) {
            return 'NO_CI';
        }
        if (! empty($record['no_checkout'])) {
            return 'NO_CO';
        }
        if (! empty($record['early_out'])) {
            return 'PULANG_CEPAT';
        }

        return null;
    }

    protected function syncPersonalDashboardKpiNotification(
        string $kodeKaryawan,
        array $kpiSummary,
        string $type,
    ): ?array {
        $monthKey = $this->currentDashboardMonthKey();
        $key = NotificationService::dashboardPersonalTokenKey($kodeKaryawan, $monthKey, $type);
        $isParameter = $type === 'KPI_PARAMETER';
        $periodLabel = $isParameter
            ? (string) ($kpiSummary['parameter_period'] ?? ($kpiSummary['period_label'] ?? 'Periode berjalan'))
            : (string) ($kpiSummary['performance_period'] ?? ($kpiSummary['period_label'] ?? 'Periode berjalan'));

        return $this->syncPersonalDashboardNotification([
            'kode_karyawan' => $kodeKaryawan,
            'type' => $type,
            'title' => $isParameter ? 'Parameter KPI belum diisi' : 'KPI Performance belum diisi',
            'message' => ($isParameter ? 'Periode KPI: ' : 'Periode performance: ').$periodLabel,
            'module' => 'KPI',
            'severity' => 'warning',
            'action_url' => $isParameter ? '/form-kpi' : '/update-performance',
            'reference_id' => $monthKey,
            'notification_key' => $key,
            'channel' => NotificationService::DELIVERY_IN_APP,
            'meta' => [
                'source' => self::DASHBOARD_SOURCE,
                'category' => 'kpi',
                'period_key' => $monthKey,
            ],
            'created_by' => 'SYSTEM',
            'expired_at' => null,
            'sent_at' => null,
        ]);
    }

    protected function resolvePersonalDashboardKpiNotification(
        string $kodeKaryawan,
        array $kpiSummary,
        string $type,
    ): void {
        $this->notificationService->resolveByKey(
            NotificationService::dashboardPersonalTokenKey($kodeKaryawan, $this->currentDashboardMonthKey(), $type),
            $kodeKaryawan,
        );
    }

    protected function currentDashboardMonthKey(): string
    {
        return Carbon::now('Asia/Jakarta')->format('Y-m');
    }

    protected function syncPersonalDashboardLmsNotification(string $kodeKaryawan, array $lmsSummary): ?array
    {
        $weekKey = $this->currentDashboardWeekKey();
        $pending = (int) ($lmsSummary['mandatory_pending'] ?? 0);

        return $this->syncPersonalDashboardNotification([
            'kode_karyawan' => $kodeKaryawan,
            'type' => 'LMS_MANDATORY_PENDING',
            'title' => "{$pending} training wajib belum selesai",
            'message' => 'Training berikutnya: '.($lmsSummary['next_session'] ?? 'Tidak ada training aktif'),
            'module' => 'LMS',
            'severity' => 'info',
            'action_url' => '/lms/training/catalog',
            'reference_id' => $weekKey,
            'notification_key' => NotificationService::dashboardPersonalTokenKey(
                $kodeKaryawan,
                $weekKey,
                'LMS_MANDATORY_PENDING',
            ),
            'channel' => NotificationService::DELIVERY_IN_APP,
            'meta' => [
                'source' => self::DASHBOARD_SOURCE,
                'category' => 'lms',
                'period_key' => $weekKey,
                'count' => $pending,
                'next_session' => $lmsSummary['next_session'] ?? null,
            ],
            'created_by' => 'SYSTEM',
            'expired_at' => null,
            'sent_at' => null,
        ]);
    }

    protected function resolvePersonalDashboardLmsNotification(string $kodeKaryawan, array $lmsSummary): void
    {
        $this->notificationService->resolveByKey(
            NotificationService::dashboardPersonalTokenKey(
                $kodeKaryawan,
                $this->currentDashboardWeekKey(),
                'LMS_MANDATORY_PENDING',
            ),
            $kodeKaryawan,
        );
    }

    protected function currentDashboardWeekKey(): string
    {
        $now = Carbon::now('Asia/Jakarta');

        return sprintf('%04d-W%02d', $now->isoWeekYear(), $now->isoWeek());
    }

    protected function syncPersonalDashboardSubmissionNotification(string $kodeKaryawan, array $submission): ?array
    {
        $noTransaksi = trim(
            (string) ($submission['no_transaksi'] ?? ($submission['transaction_no'] ?? ($submission['id'] ?? ''))),
        );
        $status = strtoupper(trim((string) ($submission['status'] ?? 'PENDING')));
        $type = 'SUBMISSION_'.$status;
        $key = NotificationService::dashboardPersonalTokenKey($kodeKaryawan, $noTransaksi, $type);

        // Resolve status SUBMISSION lain (selain status saat ini) dalam 1 batch UPDATE.
        $staleKeys = [];
        foreach (['PENDING', 'REJECTED', 'EXPIRED'] as $candidateStatus) {
            if ($candidateStatus === $status) {
                continue;
            }
            $staleKeys[] = NotificationService::dashboardPersonalTokenKey(
                $kodeKaryawan,
                $noTransaksi,
                'SUBMISSION_'.$candidateStatus,
            );
        }
        $this->notificationService->resolveManyByKey($staleKeys, $kodeKaryawan);

        return $this->syncPersonalDashboardNotification([
            'kode_karyawan' => $kodeKaryawan,
            'type' => $type,
            'title' => trim(
                ($submission['title'] ?? 'Pengajuan').
                    ' '.
                    ($submission['statusLabel'] ?? ucfirst(strtolower($status))),
            ),
            'message' => trim(
                ($submission['date'] ?? '-').' - '.($submission['description'] ?? 'Pengajuan diproses'),
            ),
            'module' => 'HCIS',
            'severity' => in_array(strtolower($status), ['rejected', 'expired'], true) ? 'danger' : 'warning',
            'action_url' => $submission['href'] ?? null,
            'reference_id' => $noTransaksi,
            'notification_key' => $key,
            'priority' => strtolower($status) === 'expired' ? 2 : null,
            'channel' => NotificationService::DELIVERY_IN_APP,
            'meta' => [
                'source' => self::DASHBOARD_SOURCE,
                'category' => 'submission',
                'status' => strtolower($status),
                'period_key' => $noTransaksi,
            ],
            'created_by' => 'SYSTEM',
            'expired_at' => null,
            'sent_at' => null,
        ]);
    }

    protected function resolveDashboardNotificationTypes(array $record): array
    {
        $types = [];

        if (! empty($record['alpha'])) {
            $types[] = 'ALPHA';
        }
        if (! empty($record['no_checkin'])) {
            $types[] = 'NO_CI';
        }
        if (! empty($record['no_checkout'])) {
            $types[] = 'NO_CO';
        }
        if (! empty($record['early_out'])) {
            $types[] = 'PULANG_CEPAT';
        }

        return $types;
    }

    protected function buildDashboardAttendanceNotificationPayload(
        string $kodeKaryawan,
        array $record,
        string $type,
        string $notificationKey,
    ): array {
        $date = (string) ($record['date'] ?? Carbon::now('Asia/Jakarta')->toDateString());
        $dateLabel = Carbon::parse($date)->translatedFormat('d M Y');
        $lateMinutes = (int) ($record['late_minutes'] ?? 0);
        $earlyOutMinutes = (int) ($record['early_out_minutes'] ?? 0);

        $copy = match ($type) {
            'ALPHA' => [
                'severity' => 'danger',
                'title' => 'Alpha / tidak hadir',
                'message' => "{$dateLabel} tidak memiliki checktime pada hari kerja.",
            ],
            'NO_CI' => [
                'severity' => 'danger',
                'title' => 'Check-in belum tercatat',
                'message' => "{$dateLabel} memiliki check-out, tetapi check-in tidak terbaca.",
            ],
            'NO_CO' => [
                'severity' => 'warning',
                'title' => 'Check-out belum tercatat',
                'message' => "{$dateLabel} memiliki check-in, tetapi check-out belum terbaca.",
            ],
            'PULANG_CEPAT' => [
                'severity' => 'warning',
                'title' => 'Pulang cepat',
                'message' => "{$dateLabel} terdeteksi pulang cepat".
                    ($earlyOutMinutes > 0 ? " {$earlyOutMinutes} menit." : '.'),
            ],
            default => [
                'severity' => 'warning',
                'title' => 'Issue absensi',
                'message' => "{$dateLabel} membutuhkan pengecekan absensi.",
            ],
        };

        if ($lateMinutes > 0) {
            $copy['message'] .= " Terlambat {$lateMinutes} menit.";
        }

        return [
            'kode_karyawan' => $kodeKaryawan,
            'type' => $type,
            'title' => $copy['title'],
            'message' => $copy['message'],
            'module' => 'HCIS',
            'severity' => $copy['severity'],
            'action_url' => '/homepage',
            'reference_id' => $date,
            'notification_key' => $notificationKey,
            'priority' => 1,
            'channel' => NotificationService::DELIVERY_IN_APP,
            'source' => self::ATTENDANCE_SOURCE,
            'meta' => [
                'source' => self::ATTENDANCE_SOURCE,
                'category' => 'attendance',
                'period_key' => Carbon::parse($date)->format('Y-m'),
                'date' => $date,
            ],
            'created_by' => 'SYSTEM',
            'expired_at' => null,
            'sent_at' => null,
        ];
    }

    /** Jumlah minggu yang ditampilkan di trend persentase kehadiran. */
    private const ATTENDANCE_TREND_WEEKS = 4;

    /**
     * Trend persentase kehadiran PER-MINGGU SEPANJANG CUTOFF BERJALAN (bukan lagi
     * jendela tetap 4 minggu). Disusun SEPENUHNYA dari attendanceRecords cutoff yang
     * sudah di tangan -> 0 panggilan SP absen tambahan. Sejalan dgn "kunci 1 cutoff":
     * hanya minggu yang OVERLAP cutoff yang ditampilkan (tepi minggu di-clamp ke
     * batas cutoff), sehingga TIDAK ada titik ~0% dari minggu di luar cutoff. Ujung
     * dibatasi ke hari ini (tanggal masa depan belum punya data). $userIdAbsen
     * dipertahankan di signature utk kompatibilitas caller.
     */
    protected function buildCutoffAttendanceTrend(
        string $kodeKaryawan,
        string $userIdAbsen,
        array $currentSummary,
    ): array {
        $now = Carbon::now('Asia/Jakarta')->startOfDay();

        $cutoff = $currentSummary['cutoffRange'] ?? null;
        // Rentang cutoff berjalan (fallback: jendela 4 minggu bila cutoffRange kosong).
        $cutoffStart = ! empty($cutoff['start'])
            ? Carbon::parse($cutoff['start'], 'Asia/Jakarta')->startOfDay()
            : $now->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::ATTENDANCE_TREND_WEEKS - 1);
        $cutoffEnd = ! empty($cutoff['end'])
            ? Carbon::parse($cutoff['end'], 'Asia/Jakarta')->startOfDay()
            : $now->copy();

        // Jangan proyeksikan ke masa depan: batasi ujung ke hari ini bila cutoff
        // masih berjalan (tanggal setelah hari ini belum ada absensi -> jadi 0%).
        if ($cutoffEnd->gt($now)) {
            $cutoffEnd = $now->copy();
        }

        // Minggu (Senin) yang OVERLAP cutoff: dari minggu berisi cutoffStart s/d
        // minggu berisi cutoffEnd. Hanya minggu di dalam cutoff -> tak ada 0% palsu.
        $weekStarts = collect();
        $cursor = $cutoffStart->copy()->startOfWeek(Carbon::MONDAY);
        $lastWeek = $cutoffEnd->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lte($lastWeek)) {
            $weekStarts->push($cursor->copy());
            $cursor->addWeek();
        }

        $records = collect($currentSummary['attendanceRecords'] ?? [])->keyBy('date');

        return $weekStarts
            ->map(function (Carbon $weekStart) use ($records, $now, $cutoffStart, $cutoffEnd) {
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
                // Clamp tepi minggu ke dalam cutoff (minggu awal/akhir bisa terpotong).
                $effStart = $weekStart->lt($cutoffStart) ? $cutoffStart->copy() : $weekStart->copy();
                $effEnd = $weekEnd->gt($cutoffEnd) ? $cutoffEnd->copy() : $weekEnd->copy();

                $weekRecords = $records->filter(function ($record) use ($effStart, $effEnd) {
                    $date = $record['date'] ?? null;

                    return $date !== null && $date >= $effStart->toDateString() && $date <= $effEnd->toDateString();
                })->values();

                $range = [
                    'start_date' => $weekStart->copy(),
                    'end_date' => $weekEnd->copy(),
                    // Label pakai tanggal efektif (tepi ter-clamp), mis. minggu pertama
                    // menampilkan tanggal mulai cutoff, bukan Senin di luar cutoff.
                    'label' => $effStart->translatedFormat('d M'),
                    // period unik per minggu (tanggal Senin) sbg identifier chart.
                    'period' => $weekStart->toDateString(),
                ];

                return $this->summarizeCutoffAttendanceTrendPoint($range, $weekRecords, $now);
            })
            ->values()
            ->all();
    }

    protected function summarizeCutoffAttendanceTrendPoint(array $range, Collection $records, Carbon $now): array
    {
        $assessableRecords = $records->filter(fn ($record) => $this->isAssessableAttendanceRecord((array) $record));
        $totalDays = $assessableRecords->count();
        $presentDays = $assessableRecords
            ->filter(fn ($record) => $this->isPresentAttendanceRecord((array) $record))
            ->count();
        $sickDays = $records->filter(fn ($record) => ! empty($record['sakit']))->count();
        $leaveDays = $records->filter(fn ($record) => ! empty($record['cuti']))->count();
        $fulfilledDays = min($totalDays, $presentDays + $sickDays + $leaveDays);
        $percentage = $totalDays > 0 ? round(($fulfilledDays / $totalDays) * 100, 1) : 0.0;

        return [
            'period' => $range['period'] ?? $range['start_date']->format('Y-m'),
            'label' => (string) ($range['label'] ?? $range['start_date']->translatedFormat('M Y')),
            'cutoff_start' => $range['start_date']->toDateString(),
            'cutoff_end' => $range['end_date']->toDateString(),
            'value' => $percentage,
            'percentage' => $percentage,
            'present_days' => $presentDays,
            'fulfilled_days' => $fulfilledDays,
            'total_days' => $totalDays,
            'sick_days' => $sickDays,
            'leave_days' => $leaveDays,
            'late_minutes' => (int) $records->sum(fn ($record) => (int) ($record['late_minutes'] ?? 0)),
        ];
    }

    protected function isAssessableAttendanceRecord(array $record): bool
    {
        return empty($record['no_shift']) &&
            empty($record['future']) &&
            empty($record['waiting_shift']) &&
            empty($record['working']);
    }

    protected function isPresentAttendanceRecord(array $record): bool
    {
        $status = strtolower((string) ($record['status'] ?? ''));

        return empty($record['alpha']) &&
            empty($record['no_checkin']) &&
            (! empty($record['check_in']) || in_array($status, ['present', 'terlambat', 'working'], true));
    }

    protected function resolvePersonalActionStatus(array $record): array
    {
        if (! empty($record['alpha'])) {
            return [
                'tone' => 'danger',
                'icon' => 'bi bi-person-x',
                'title' => 'Alpha / tidak hadir',
                'description' => 'Tidak ada checktime pada hari kerja.',
            ];
        }

        if (! empty($record['no_checkin'])) {
            return [
                'tone' => 'danger',
                'icon' => 'bi bi-box-arrow-in-right',
                'title' => 'Check-in belum tercatat',
                'description' => 'Ada check-out, tetapi check-in tidak terbaca.',
            ];
        }

        if (! empty($record['no_checkout'])) {
            return [
                'tone' => 'warning',
                'icon' => 'bi bi-box-arrow-right',
                'title' => 'Check-out belum tercatat',
                'description' => 'Check-in sudah ada, tetapi check-out belum terbaca.',
            ];
        }

        return [
            'tone' => 'warning',
            'icon' => 'bi bi-clock-history',
            'title' => 'Pulang cepat',
            'description' => 'Ada catatan pulang cepat pada tanggal ini.',
        ];
    }

    protected function normalizeYear(?int $year = null): ?int
    {
        if ($year === null) {
            return null;
        }

        return $year >= 2000 && $year <= 2100 ? $year : null;
    }

    protected function buildAttendanceRecordState(?array $row, ?string $approvedType, ?string $date = null): array
    {
        $timing = $this->resolveAttendanceTiming($row, $date);
        $statusHari = strtoupper((string) ($row['status_hari'] ?? 'WORK'));
        $shiftName = strtoupper(trim((string) ($row['shift_name'] ?? 'NO SHIFT')));
        $checkIn = $row['check_in'] ?? null;
        $checkOut = $row['check_out'] ?? null;
        $late = (int) ($row['late_minutes'] ?? 0) > 0 && ! $timing['is_future'];
        // Pulang cepat diambil murni dari kolom SP RealEarly (sudah bersih: hari
        // libur/OFF bernilai 0). Tidak lagi menghitung manual atau lookup tabel.
        $earlyOutMinutes = (int) ($row['early_minutes'] ?? 0);
        $earlyOut = $earlyOutMinutes > 0 && ! $timing['is_future'];
        $isOff = str_starts_with($statusHari, 'OFF') || $shiftName === 'OFF' || $shiftName === 'NO SHIFT';

        $state = [
            'status' => 'present',
            'late' => $late,
            'no_checkin' => false,
            'no_checkout' => false,
            'early_out' => $earlyOut,
            'early_out_minutes' => $earlyOutMinutes,
            'izin' => false,
            'sakit' => false,
            'cuti' => false,
            'no_shift' => $isOff,
            'alpha' => false,
            'waiting_shift' => false,
            'working' => false,
            'future' => $timing['is_future'],
            'flags' => [],
        ];

        if ($approvedType === 'CUTI') {
            $state['status'] = 'cuti';
            $state['cuti'] = true;
        } elseif ($approvedType === 'SAKIT') {
            $state['status'] = 'izin';
            $state['sakit'] = true;
        } elseif ($approvedType === 'IZIN') {
            $state['status'] = 'izin';
            $state['izin'] = true;
        } elseif ($isOff) {
            $state['status'] = 'off';
        } elseif ($timing['is_future']) {
            $state['status'] = 'future';
        } elseif (! $checkIn && ! $checkOut && $timing['is_today'] && $timing['before_shift_start']) {
            $state['status'] = 'waiting shift';
            $state['waiting_shift'] = true;
        } elseif (! $checkIn && ! $checkOut && $timing['is_today'] && ! $timing['after_shift_end_grace']) {
            $state['status'] = 'no checkin';
            $state['no_checkin'] = true;
        } elseif (! $checkIn && ! $checkOut) {
            $state['status'] = 'alpha';
            $state['alpha'] = true;
        } elseif (! $checkIn && $checkOut) {
            $state['status'] = 'no checkin';
            $state['no_checkin'] = true;
        } elseif ($checkIn && ! $checkOut && (! $timing['is_today'] || $timing['after_shift_end_grace'])) {
            $state['status'] = 'no checkout';
            $state['no_checkout'] = true;
        } elseif ($checkIn && ! $checkOut) {
            $state['status'] = 'working';
            $state['working'] = true;
        } elseif ($late) {
            $state['status'] = 'terlambat';
        }

        if ($state['alpha']) {
            $state['flags'][] = ['key' => 'alpha', 'label' => 'Alpha', 'tone' => 'danger', 'problem' => true];
        }
        if ($state['no_checkin']) {
            $state['flags'][] = [
                'key' => 'no-checkin',
                'label' => 'No Check In',
                'tone' => 'danger',
                'problem' => true,
            ];
        }
        if ($state['no_checkout']) {
            $state['flags'][] = [
                'key' => 'no-checkout',
                'label' => 'No Check Out',
                'tone' => 'warning',
                'problem' => true,
            ];
        }
        if ($state['late']) {
            $state['flags'][] = ['key' => 'late', 'label' => 'Terlambat', 'tone' => 'warning', 'problem' => false];
        }
        if ($state['early_out']) {
            $state['flags'][] = [
                'key' => 'early-out',
                'label' => 'Pulang Cepat',
                'tone' => 'warning',
                'problem' => true,
            ];
        }
        if ($state['cuti']) {
            $state['flags'][] = ['key' => 'cuti', 'label' => 'Cuti', 'tone' => 'info', 'problem' => false];
        }
        if ($state['izin'] || $state['sakit']) {
            $state['flags'][] = [
                'key' => 'izin',
                'label' => $state['sakit'] ? 'Sakit' : 'Izin',
                'tone' => 'info',
                'problem' => false,
            ];
        }
        if ($state['no_shift']) {
            $state['flags'][] = ['key' => 'no-shift', 'label' => 'OFF', 'tone' => 'default', 'problem' => false];
        }

        return $state;
    }

    protected function resolveAttendanceTiming(?array $row, ?string $date = null): array
    {
        $timezone = 'Asia/Jakarta';
        $now = Carbon::now($timezone);
        $targetDate = $date ? Carbon::parse($date, $timezone)->startOfDay() : null;
        $isFuture = $targetDate ? $targetDate->gt($now->copy()->startOfDay()) : false;
        $isToday = $targetDate ? $targetDate->isSameDay($now) : false;
        $start = null;
        $end = null;

        if ($date && ! empty($row['jam_masuk'])) {
            $start = Carbon::parse($date.' '.$row['jam_masuk'], $timezone);
        }

        if ($date && ! empty($row['jam_keluar'])) {
            $end = Carbon::parse($date.' '.$row['jam_keluar'], $timezone);
            if ($start && $end->lte($start)) {
                $end->addDay();
            }
        }

        return [
            'is_future' => $isFuture,
            'is_today' => $isToday,
            'before_shift_start' => $isToday && $start ? $now->lt($start) : false,
            'after_shift_start_grace' => $isToday && $start ? $now->gte($start->copy()->addMinutes(10)) : ! $isToday,
            'after_shift_end_grace' => $isToday && $end ? $now->gte($end->copy()->addMinutes(10)) : ! $isToday,
        ];
    }

    protected function buildTimeline(?array $row): array
    {
        $timeline = [];

        if (! empty($row['jam_masuk'])) {
            $timeline[] = [
                'label' => 'Jam Masuk Shift',
                'time' => $row['jam_masuk'],
                'kind' => 'info',
            ];
        }

        if (! empty($row['check_in'])) {
            $timeline[] = [
                'label' => 'Check In',
                'time' => $row['check_in'],
                'kind' => 'success',
            ];
        }

        if (! empty($row['check_out'])) {
            $timeline[] = [
                'label' => 'Check Out',
                'time' => $row['check_out'],
                'kind' => 'warning',
            ];
        }

        if (! empty($row['jam_keluar'])) {
            $timeline[] = [
                'label' => 'Jam Pulang Shift',
                'time' => $row['jam_keluar'],
                'kind' => 'info',
            ];
        }

        return $timeline;
    }

    protected function buildAttendanceNote(?array $row, Collection $approvedItems, Collection $overtimeRows): string
    {
        if (! empty($row['keterangan'])) {
            return (string) $row['keterangan'];
        }

        $approvedDescription = $approvedItems
            ->map(fn ($item) => trim((string) ($item->Deskripsi ?? '')))
            ->filter()
            ->first();

        if ($approvedDescription) {
            return $approvedDescription;
        }

        $overtimeReason = $overtimeRows->map(fn ($row) => trim((string) ($row->Alasan ?? '')))->filter()->first();

        return $overtimeReason ?: 'Tidak ada catatan tambahan.';
    }

    protected function buildTransactionSummary(
        Collection $approvedItems,
        Collection $overtimeRows,
        array $fullyApprovedMap = [],
    ): array {
        $items = $approvedItems
            // Hanya tampilkan transaksi yang seluruh langkah approval flow-nya sudah disetujui.
            // Map kosong (mis. dependency tidak tersedia) bersikap fail-open: semua tampil.
            ->filter(function ($item) use ($fullyApprovedMap) {
                if (empty($fullyApprovedMap)) {
                    return true;
                }

                $noTransaksi = trim((string) ($item->No_Transaksi ?? ''));

                return $noTransaksi === '' || ! empty($fullyApprovedMap[$noTransaksi]);
            })
            ->values()
            ->map(function ($item, int $index) {
                // Tipe_Transaksi sudah dipetakan rapi di sumber data ("Cuti", "Izin",
                // "Lembur", ...). Pakai itu sebagai judul; fallback ke Jenis mentah.
                $type = strtoupper((string) ($item->Tipe_Transaksi ?? ($item->Jenis ?? 'TRANSAKSI')));
                $title = $this->humanizeTransactionType((string) ($item->Tipe_Transaksi ?? ($item->Jenis ?? '')));
                $noTransaksi = trim((string) ($item->No_Transaksi ?? ''));

                // Keterangan: prioritaskan Deskripsi; lalu sub-tipe (mis. jenis cuti/izin).
                $description = trim((string) ($item->Deskripsi ?? ''));
                if ($description === '') {
                    $subTipe = trim((string) ($item->Sub_Tipe ?? ''));
                    $description = $subTipe !== '' ? ucfirst($subTipe) : 'Pengajuan disetujui.';
                }

                return [
                    'id' => 'approved-'.$index,
                    'type' => $type,
                    'title' => $title,
                    'no_transaksi' => $noTransaksi ?: null,
                    'description' => $description,
                    'status_label' => 'Disetujui',
                    'status_tone' => 'success',
                    'icon' => $this->resolveTransactionIcon($type),
                ];
            })
            ->values();

        $overtimeItems = $overtimeRows->map(function ($row, int $index) {
            $start = ! empty($row->Tanggal_Lembur_Dari) ? Carbon::parse($row->Tanggal_Lembur_Dari)->format('H:i') : '-';
            $end = ! empty($row->Tanggal_Lembur_Sampai)
                ? Carbon::parse($row->Tanggal_Lembur_Sampai)->format('H:i')
                : '-';

            return [
                'id' => 'overtime-'.$index,
                'type' => 'LEMBUR',
                'title' => 'Lembur',
                'no_transaksi' => trim((string) ($row->No_Transaksi ?? '')) ?: null,
                'description' => trim((string) ($row->Jenis ?? 'Lembur'))." {$start} - {$end}",
                // Overtime di-query dengan h.Status IS NULL → assignment lembur aktif/disetujui.
                'status_label' => 'Disetujui',
                'status_tone' => 'success',
                'icon' => 'bi bi-clock-history',
            ];
        });

        return $items->merge($overtimeItems)->values()->all();
    }

    /**
     * Ubah kode/jenis transaksi mentah menjadi judul yang rapi & enak dibaca.
     * Mis. "Terlambat_Pulang_Cepat" -> "Terlambat / Pulang Cepat", "SAKIT_IZIN" -> "Izin".
     */
    protected function humanizeTransactionType(string $raw): string
    {
        $key = strtoupper(trim($raw));

        $map = [
            'CUTI' => 'Cuti',
            'IZIN' => 'Izin',
            'SAKIT_IZIN' => 'Izin',
            'SAKIT' => 'Sakit',
            'LEMBUR' => 'Lembur',
            'KONVERSI_LEMBUR' => 'Konversi Lembur',
            'TERLAMBAT_PULANG_CEPAT' => 'Terlambat / Pulang Cepat',
            'TERLAMBAT' => 'Terlambat',
            'PULANG_CEPAT' => 'Pulang Cepat',
            'SHIFT' => 'Shift',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        if ($key === '') {
            return 'Transaksi';
        }

        // Fallback: ubah SNAKE_CASE / lowercase jadi Title Case ("ban bocor" -> "Ban Bocor").
        return ucwords(strtolower(str_replace('_', ' ', $key)));
    }

    /**
     * Pilih ikon Bootstrap sesuai jenis transaksi terkait (cuti, izin, sakit, lembur, dll).
     */
    protected function resolveTransactionIcon(string $type): string
    {
        $type = strtoupper(trim($type));

        if (str_contains($type, 'CUTI')) {
            return 'bi bi-airplane';
        }
        if (str_contains($type, 'SAKIT')) {
            return 'bi bi-bandaid';
        }
        if (str_contains($type, 'IZIN')) {
            return 'bi bi-card-checklist';
        }
        if (str_contains($type, 'LEMBUR')) {
            return 'bi bi-clock-history';
        }
        if (str_contains($type, 'SHIFT')) {
            return 'bi bi-arrow-left-right';
        }

        return 'bi bi-receipt-cutoff';
    }

    protected function resolveApprovedType(Collection $items): ?string
    {
        foreach ($items as $item) {
            $type = strtoupper((string) ($item->Tipe_Transaksi ?? ($item->Jenis ?? '')));
            $subType = strtoupper((string) ($item->Sub_Tipe ?? ''));

            if ($type === 'CUTI') {
                return 'CUTI';
            }

            if ($type === 'IZIN' || $type === 'SAKIT_IZIN') {
                return str_contains($subType, 'SAKIT') ? 'SAKIT' : 'IZIN';
            }
        }

        return null;
    }

    protected function buildOvertimeSummary(
        Collection $absensiRows,
        Collection $overtimeByDate,
        string $kodeKaryawan,
        string $userIdAbsen,
    ): array {
        // $absensiRows sudah dibatasi tepat 1 periode CUTOFF oleh buildAttendanceBundle
        // (dipanggil dengan cutoffRange start/end), jadi semua agregat di bawah = per cutoff.
        //
        // Definisi (per kesepakatan HC):
        // - Total Lembur     = SUM(Koefisien_OT). Koefisien_OT sudah dalam JAM dan merupakan
        //                      jam lembur yang benar-benar dibayar perusahaan.
        // - Komponen lembur  = di-FLOOR ke jam penuh PER HARI, lalu dijumlahkan. Floor per
        //                      hari mencegah "harapan palsu" (akumulasi pecahan menit yang
        //                      sebenarnya tidak dibayar).
        // - Assignment       = jumlah HARI yang punya lembur (Koefisien_OT > 0), termasuk
        //                      lembur shift otomatis.
        $totalKoefisienHours = 0.0; // SUM(Koefisien_OT) — jam dibayar
        $hkHours = 0; // lembur hari kerja (Overtime non-libur), floor/hari
        $beforeHours = 0; // lembur awal (Overtime_Before), floor/hari
        $shiftHours = 0; // lembur shift (Overtime_Shift), floor/hari
        $holidayHours = 0; // lembur hari libur (Overtime saat Flag_Lembur_Libur), floor/hari
        $assignmentDays = 0; // jumlah hari berlembur

        $floorHours = fn (int $minutes): int => (int) floor(max($minutes, 0) / 60);

        foreach ($absensiRows as $row) {
            $koef = (float) ($row['overtime_hours'] ?? 0); // Koefisien_OT (jam)
            $hk = (int) ($row['overtime_hk_minutes'] ?? 0);
            $awal = (int) ($row['overtime_awal_minutes'] ?? 0);
            $shift = (int) ($row['overtime_shift_minutes'] ?? 0);
            $isLibur = (bool) ($row['flag_lembur_libur'] ?? false);

            $totalKoefisienHours += $koef;
            if ($koef > 0) {
                $assignmentDays++;
            }

            // Floor PER HARI lalu akumulasi.
            $beforeHours += $floorHours($awal);
            $shiftHours += $floorHours($shift);
            if ($isLibur) {
                $holidayHours += $floorHours($hk);
            } else {
                $hkHours += $floorHours($hk);
            }
        }

        $totalKoefisienHours = round($totalKoefisienHours, 1);

        // Trend lembur TIDAK lagi dihitung di sini. buildOvertimeSummary ikut di
        // buildAttendanceBundle yang dipanggil banyak endpoint absensi, jadi trend
        // (rentang panjang = SP mahal) dipindah ke jalur ON-DEMAND khusus lembur
        // (getOvertimeDetail, dipanggil hanya saat card lembur dibuka). Key tetap
        // dikirim [] demi kompatibilitas konsumen lama.
        return [
            'period_label' => 'Periode berjalan',
            'total_hours' => $totalKoefisienHours,
            'workday_hours' => $hkHours, // dipertahankan utk kompatibilitas
            'shift_hours' => $shiftHours,
            'before_work_hours' => $beforeHours,
            'after_work_hours' => $hkHours,
            'holiday_hours' => $holidayHours,
            'active_assignments' => $assignmentDays,
            'total_this_month' => $totalKoefisienHours,
            'next_assignment_label' => $assignmentDays > 0
                    ? "{$assignmentDays} hari penugasan lembur pada periode ini."
                    : 'Tidak ada penugasan lembur.',
            'assignment_note' => 'Total Lembur Didapatkan dari lembur sah perhari; Lembur sah dibulatkan ke bawah per hari.',
            'monthly_trend' => [],
        ];
    }

    protected function resolveOvertimeCoefficientHours(object|array $item): float
    {
        return $this->extractKoefisienOt($item);
    }

    protected function extractKoefisienOt(object|array|null $source): float
    {
        if ($source === null) {
            return 0.0;
        }

        foreach ((array) $source as $key => $value) {
            $normalizedKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', (string) $key));
            if ($normalizedKey === 'koefisienot') {
                $numericValue = str_replace(',', '.', (string) $value);

                return (float) round((float) $numericValue, 2);
            }
        }

        return 0.0;
    }

    protected function buildOvertimeTwoCutoffTrend(string $kodeKaryawan, string $userIdAbsen, Carbon $today): array
    {
        // Trend lembur 2 CUTOFF terakhir (cutoff berjalan + 1 sebelumnya) dengan TITIK
        // PER MINGGU (Senin-Minggu) agar terlihat pergerakan mingguan sepanjang ~2 bulan.
        // Method ini DILEPAS dari buildOvertimeSummary/buildAttendanceBundle dan hanya
        // dipanggil ON-DEMAND (getOvertimeDetail, saat card lembur dibuka) sehingga SP
        // rentang panjang tidak lagi membebani endpoint absensi. Key 'month' tetap
        // dipakai FE untuk label sumbu-x.
        $now = $today->copy()->startOfDay();

        $currentRange = $this->uDashReadModelService->getCutoffRange($now);
        $prevAnchor = $currentRange['start_date']->copy()->startOfMonth()->addMonthsNoOverflow(-1);
        $previousRange = $this->uDashReadModelService->getCutoffRangeForMonth(
            (int) $prevAnchor->format('Y'),
            (int) $prevAnchor->format('m'),
        );

        $spanStart = $previousRange['start_date']->copy()->startOfDay();
        $spanEnd = $currentRange['end_date']->copy()->endOfDay();

        // Bangun titik mingguan kalender yang menutupi seluruh rentang 2 cutoff.
        $weekStarts = collect();
        $cursor = $spanStart->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lessThanOrEqualTo($spanEnd)) {
            $weekStarts->push($cursor->copy());
            $cursor->addWeek();
        }

        $periods = $weekStarts
            ->map(fn (Carbon $ws) => [
                'start_date' => $ws->copy(),
                'end_date' => $ws->copy()->endOfWeek(Carbon::SUNDAY),
                'label' => $ws->translatedFormat('d M'),
            ])
            ->values();

        if ($periods->isEmpty()) {
            return [];
        }

        $start = $periods->first()['start_date']->copy()->startOfDay();
        $end = $periods->last()['end_date']->copy()->endOfDay();

        // Trend dihitung MURNI dari snapshot (SP) — sumber kebenaran tunggal. Komponen
        // diambil per kolom (Overtime / Overtime_Before / Overtime_Shift) sehingga akurat
        // dan menangkap hari shift-only yang tidak punya transaksi pengajuan.
        $attendanceByDate = $this->loadAbsensiRows($start->copy(), $end->copy(), $userIdAbsen)->keyBy('date');

        return $periods
            ->map(function (array $period) use ($attendanceByDate) {
                $periodStart = $period['start_date']->copy()->startOfDay();
                $periodEnd = $period['end_date']->copy()->endOfDay();

                // Komponen di-FLOOR per hari (konsisten dgn buildOvertimeSummary), Total =
                // SUM(Koefisien_OT) — jam dibayar, independen dari penjumlahan komponen.
                $floorHours = fn (int $minutes): int => (int) floor(max($minutes, 0) / 60);
                $beforeHours = 0;
                $afterHours = 0;
                $shiftHours = 0;
                $holidayHours = 0;
                $totalKoefisien = 0.0;
                $assignmentDays = 0;

                foreach ($attendanceByDate as $date => $row) {
                    $tanggal = Carbon::parse($date)->startOfDay();
                    if (! $tanggal->betweenIncluded($periodStart, $periodEnd)) {
                        continue;
                    }

                    $koef = (float) ($row['overtime_hours'] ?? 0);
                    $totalKoefisien += $koef;
                    if ($koef > 0) {
                        $assignmentDays++;
                    }

                    $hk = (int) ($row['overtime_hk_minutes'] ?? 0);
                    $beforeHours += $floorHours((int) ($row['overtime_awal_minutes'] ?? 0));
                    $shiftHours += $floorHours((int) ($row['overtime_shift_minutes'] ?? 0));
                    if ($row['flag_lembur_libur'] ?? false) {
                        $holidayHours += $floorHours($hk);
                    } else {
                        $afterHours += $floorHours($hk);
                    }
                }

                return [
                    'month' => (string) ($period['label'] ?? $periodStart->translatedFormat('M Y')),
                    'cutoff_start' => $periodStart->toDateString(),
                    'cutoff_end' => $periodEnd->toDateString(),
                    'before' => $beforeHours,
                    'after' => $afterHours,
                    'shift' => $shiftHours,
                    'holiday' => $holidayHours,
                    'assignment_days' => $assignmentDays,
                    'total' => round($totalKoefisien, 1),
                ];
            })
            ->all();
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
            'period_label' => 'Tahun berjalan',
        ];
    }

    /**
     * TTL cache submissions homepage (detik). Submission tidak butuh real-time
     * per detik; cache pendek memangkas eksekusi query berat (group by + join 4
     * subquery agregat) yang sebelumnya jalan FRESH tiap homepage dibuka.
     */
    private const SUBMISSIONS_CACHE_TTL = 90;

    /**
     * Window lookback submissions (bulan). Panel "Pengajuan Saya" di homepage adalah
     * RINGKASAN (paginasi 4-terbaru), bukan riwayat lengkap. 2 bulan cukup untuk
     * halaman awal + paginasi, dan membatasi scan union-9-view (tidak memindai riwayat
     * sejak 2004). Filter rentang di-pushdown ke 9 view sumber via forRequesterBetween.
     * Submission lebih lama dari window ini tidak tampil di panel homepage.
     */
    private const SUBMISSIONS_LOOKBACK_MONTHS = 2;

    protected function buildSubmissions(string $kodeKaryawan, int $page = 1, int $perPage = self::SUBMISSIONS_PER_PAGE): array
    {
        $cacheKey = 'homepage.submissions.v2:'.$kodeKaryawan.':p'.$page.':n'.$perPage;

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            self::SUBMISSIONS_CACHE_TTL,
            fn () => $this->buildSubmissionsUncached($kodeKaryawan, $page, $perPage),
        );
    }

    protected function buildSubmissionsUncached(string $kodeKaryawan, int $page = 1, int $perPage = self::SUBMISSIONS_PER_PAGE): array
    {
        $cutiDetails = DB::table('Transaksi_Cuti_Detail as detail')
            ->leftJoin('N_HRIS_Jenis_Cuti as jenis', 'detail.Id_Jenis_Cuti', '=', 'jenis.Id_Jenis_Cuti')
            ->selectRaw(
                '
                detail.No_Transaksi,
                MIN(detail.Tanggal_Cuti_Dari) as Tanggal_Mulai,
                MAX(detail.Tanggal_Cuti_Sampai) as Tanggal_Selesai,
                MAX(COALESCE(jenis.Nama_Jenis_Cuti, jenis.Deskripsi)) as Detail_Type,
                MAX(detail.Alasan) as Detail_Reason
            ',
            )
            ->groupBy('detail.No_Transaksi');

        $sakitIzinDetails = DB::table('Transaksi_Sakit_Izin_Detail')
            ->selectRaw(
                '
                No_Transaksi,
                MIN(Tanggal_Sakit_Izin_Dari) as Tanggal_Mulai,
                MAX(Tanggal_Sakit_Izin_Sampai) as Tanggal_Selesai,
                MAX(Jenis) as Detail_Type,
                MAX(Alasan) as Detail_Reason
            ',
            )
            ->groupBy('No_Transaksi');
        // dd($sakitIzinDetails->toSql(), $sakitIzinDetails->getBindings());

        $terlambatPulangDetails = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
            ->selectRaw(
                '
                No_Transaksi,
                MIN(Tanggal_Masuk_Pulang) as Tanggal_Mulai,
                MAX(Tanggal_Masuk_Pulang) as Tanggal_Selesai,
                MAX(Jenis) as Detail_Type,
                MAX(Jam) as Detail_Time,
                MAX(Alasan) as Detail_Reason
            ',
            )
            ->groupBy('No_Transaksi');

        $approvalStatus = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->selectRaw(
                "
                No_Transaksi,
                MAX(CASE WHEN UPPER(LTRIM(RTRIM(COALESCE(Flag_Approval, '')))) = 'P' THEN 1 ELSE 0 END) as Has_Pending_Flag_Approval,
                MAX(CASE
                    WHEN UPPER(LTRIM(RTRIM(COALESCE(Flow_State, '')))) = 'EXPIRED' THEN 1 ELSE 0
                END) as Has_Expired_Pending_Approval,
                MAX(Expired_At) as Approval_Expired_At
            ",
            )
            ->groupBy('No_Transaksi');

        // Ganti view raksasa dengan service yang sudah memfilter Kode_Karyawan di hulu
        // (output identik view, filter ter-pushdown ke 9 sumber). Lihat AllTransactionsReadService.
        // Rentang dibatasi SUBMISSIONS_LOOKBACK_MONTHS terakhir: homepage hanya butuh 30
        // submission terbaru, jadi memindai seluruh riwayat (sejak 2004) = sia-sia & berat.
        $fromDate = Carbon::now('Asia/Jakarta')
            ->subMonths(self::SUBMISSIONS_LOOKBACK_MONTHS)->format('Y-m-d');
        $toDate = Carbon::now('Asia/Jakarta')->addDay()->format('Y-m-d');

        $txSource = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->forRequesterBetween($kodeKaryawan, $fromDate, $toDate);

        $base = DB::query()
            ->fromSub($txSource, 'tx')
            ->leftJoinSub($approvalStatus, 'approval_status', function ($join) {
                $join->on('approval_status.No_Transaksi', '=', 'tx.No_Transaksi');
            })
            ->leftJoinSub($cutiDetails, 'cuti_detail', function ($join) {
                $join->on('cuti_detail.No_Transaksi', '=', 'tx.No_Transaksi');
            })
            ->leftJoinSub($sakitIzinDetails, 'sakit_izin_detail', function ($join) {
                $join->on('sakit_izin_detail.No_Transaksi', '=', 'tx.No_Transaksi');
            })
            ->leftJoinSub($terlambatPulangDetails, 'terlambat_pulang_detail', function ($join) {
                $join->on('terlambat_pulang_detail.No_Transaksi', '=', 'tx.No_Transaksi');
            });

        // Total distinct No_Transaksi (untuk meta paginasi). Lookback dibatasi
        // SUBMISSIONS_LOOKBACK_MONTHS, jadi count tetap ringan.
        $total = (int) (clone $base)->distinct()->count('tx.No_Transaksi');

        $offset = ($page - 1) * $perPage;

        $items = $base
            // Kode_Karyawan sudah difilter di dalam $txSource (forRequester)
            ->selectRaw(
                '
                tx.No_Transaksi,
                MAX(tx.Jenis) as Jenis,
                MAX(tx.Dashboard_Status) as Dashboard_Status,
                MAX(tx.Deskripsi) as Deskripsi,
                MAX(tx.Source_View) as Source_View,
                MAX(tx.Tanggal_Request) as Tanggal_Request,
                MAX(tx.Source_Created_At) as Source_Created_At,
                MIN(tx.Tanggal_Item) as Tanggal_Mulai,
                MAX(tx.Tanggal_Item) as Tanggal_Selesai,
                MAX(cuti_detail.Tanggal_Mulai) as Cuti_Tanggal_Mulai,
                MAX(cuti_detail.Tanggal_Selesai) as Cuti_Tanggal_Selesai,
                MAX(cuti_detail.Detail_Type) as Cuti_Detail_Type,
                MAX(cuti_detail.Detail_Reason) as Cuti_Detail_Reason,
                MAX(sakit_izin_detail.Tanggal_Mulai) as Sakit_Izin_Tanggal_Mulai,
                MAX(sakit_izin_detail.Tanggal_Selesai) as Sakit_Izin_Tanggal_Selesai,
                MAX(sakit_izin_detail.Detail_Type) as Sakit_Izin_Detail_Type,
                MAX(sakit_izin_detail.Detail_Reason) as Sakit_Izin_Detail_Reason,
                MAX(terlambat_pulang_detail.Tanggal_Mulai) as Terlambat_Pulang_Tanggal_Mulai,
                MAX(terlambat_pulang_detail.Tanggal_Selesai) as Terlambat_Pulang_Tanggal_Selesai,
                MAX(terlambat_pulang_detail.Detail_Type) as Terlambat_Pulang_Detail_Type,
                MAX(terlambat_pulang_detail.Detail_Time) as Terlambat_Pulang_Detail_Time,
                MAX(terlambat_pulang_detail.Detail_Reason) as Terlambat_Pulang_Detail_Reason,
                MAX(COALESCE(approval_status.Has_Pending_Flag_Approval, 0)) as Has_Pending_Flag_Approval,
                MAX(COALESCE(approval_status.Has_Expired_Pending_Approval, 0)) as Has_Expired_Pending_Approval,
                MAX(approval_status.Approval_Expired_At) as Approval_Expired_At
            ',
            )
            ->groupBy('tx.No_Transaksi')
            ->orderByDesc('Tanggal_Request')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($item, int $index) {
                $status = strtoupper((string) ($item->Dashboard_Status ?? 'PENDING'));
                $isExpired = $this->isPersonalSubmissionExpiredRecord($item);
                $statusKey = match ($status) {
                    'APPROVED' => 'approved',
                    'REJECTED', 'VOID' => 'rejected',
                    'EXPIRED' => 'expired',
                    default => 'pending',
                };
                if ($isExpired) {
                    $statusKey = 'expired';
                }

                $requestedAt = ! empty($item->Tanggal_Request)
                    ? Carbon::parse($item->Tanggal_Request, 'Asia/Jakarta')
                    : null;
                $expiryText = $this->resolveSubmissionExpiryText($item, $requestedAt);
                $periodLabel = $this->resolveSubmissionPeriodLabel($item);
                $detailLabel = $this->resolveSubmissionDetailLabel($item);

                return [
                    'id' => (string) ($item->No_Transaksi ?? 'submission-'.$index),
                    'no_transaksi' => (string) ($item->No_Transaksi ?? ''),
                    'title' => strtoupper((string) ($item->Jenis ?? 'Pengajuan')),
                    'date' => $requestedAt ? $requestedAt->translatedFormat('d M Y') : '-',
                    'submittedAt' => $requestedAt ? $requestedAt->toIso8601String() : null,
                    'submittedDateLabel' => $requestedAt ? 'Diajukan '.$requestedAt->translatedFormat('d M Y') : null,
                    'periodLabel' => $periodLabel,
                    'detailLabel' => $detailLabel,
                    'description' => trim((string) ($item->Deskripsi ?? 'Pengajuan diproses')),
                    'status' => $statusKey,
                    'statusLabel' => ucfirst($statusKey),
                    'isExpired' => $isExpired,
                    'expiryNote' => $expiryText,
                    'href' => $this->resolveTransactionRoute(
                        $item->No_Transaksi ?? null,
                        $statusKey,
                        $item->Jenis ?? null,
                    ),
                    'itemDate' => ! empty($item->Tanggal_Mulai)
                        ? Carbon::parse($item->Tanggal_Mulai, 'Asia/Jakarta')->format('Y-m-d')
                        : null,
                ];
            })
            ->values()
            ->all();

        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_prev' => $page > 1,
                'has_next' => $page < $lastPage,
            ],
        ];
    }

    protected function resolveSubmissionPeriodLabel($item): ?string
    {
        $startRaw = data_get($item, 'Tanggal_Mulai');
        $endRaw = data_get($item, 'Tanggal_Selesai');
        $jenis = strtoupper(trim((string) data_get($item, 'Jenis', '')));

        if ($jenis === 'CUTI') {
            $startRaw = data_get($item, 'Cuti_Tanggal_Mulai') ?: $startRaw;
            $endRaw = data_get($item, 'Cuti_Tanggal_Selesai') ?: $endRaw;
        } elseif ($jenis === 'SAKIT_IZIN') {
            $startRaw = data_get($item, 'Sakit_Izin_Tanggal_Mulai') ?: $startRaw;
            $endRaw = data_get($item, 'Sakit_Izin_Tanggal_Selesai') ?: $endRaw;
        } elseif (in_array($jenis, ['TERLAMBAT', 'TERLAMBAT_PULANG_CEPAT'], true)) {
            $startRaw = data_get($item, 'Terlambat_Pulang_Tanggal_Mulai') ?: $startRaw;
            $endRaw = data_get($item, 'Terlambat_Pulang_Tanggal_Selesai') ?: $endRaw;
        }

        $start = $this->parseJakartaDate($startRaw);
        $end = $this->parseJakartaDate($endRaw);

        if (! $start && ! $end) {
            return null;
        }

        if (! $start) {
            return $end->translatedFormat('d M Y');
        }

        if (! $end || $start->isSameDay($end)) {
            return ($start ?: $end)->translatedFormat('d M Y');
        }

        return $start->translatedFormat('d M Y').' - '.$end->translatedFormat('d M Y');
    }

    protected function resolveSubmissionDetailLabel($item): ?string
    {
        $jenis = strtoupper(trim((string) data_get($item, 'Jenis', '')));

        if ($jenis === 'CUTI') {
            $detailType = $this->normalizeSubmissionTypeLabel(data_get($item, 'Cuti_Detail_Type'));

            if (! $detailType) {
                return 'Cuti';
            }

            return str_starts_with(strtoupper($detailType), 'CUTI ') ? $detailType : 'Cuti '.$detailType;
        }

        if ($jenis === 'SAKIT_IZIN') {
            return $this->normalizeSubmissionTypeLabel(data_get($item, 'Sakit_Izin_Detail_Type')) ?: 'Izin/Sakit';
        }

        if (in_array($jenis, ['TERLAMBAT', 'TERLAMBAT_PULANG_CEPAT'], true)) {
            $label = $this->normalizeSubmissionTypeLabel(data_get($item, 'Terlambat_Pulang_Detail_Type')) ?: 'Izin';
            $time = trim((string) data_get($item, 'Terlambat_Pulang_Detail_Time', ''));

            return $time !== '' ? $label.' '.$time : $label;
        }

        return $this->normalizeSubmissionTypeLabel(data_get($item, 'Jenis')) ?: null;
    }

    protected function normalizeSubmissionTypeLabel($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $knownLabel = match ($raw) {
            'sakit' => 'Izin Sakit',
            'izinFull' => 'Izin Pribadi',
            'terlambat' => 'Izin Terlambat',
            'pulangCepat' => 'Izin Pulang Cepat',
            default => null,
        };

        if ($knownLabel) {
            return $knownLabel;
        }

        $label = str_replace(['_', '-'], ' ', $raw);
        if (preg_match('/[a-z][A-Z]/', $label)) {
            $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $label);
        }

        return ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $label))));
    }

    protected function parseJakartaDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value, 'Asia/Jakarta');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Apakah record ini sudah expired?
     *
     * Sumber kebenaran expired ada di DB view N_HRIS_VW_Approval_Request_V2
     * (Flow_State = 'EXPIRED') dan N_HRIS_VW_All_Transactions2 (Dashboard_Status
     * = 'EXPIRED'). Tidak ada lagi kalkulasi PHP — semua sudah ditentukan di DB.
     */
    protected function isPersonalSubmissionExpiredRecord($item): bool
    {
        $status = strtoupper(trim((string) data_get($item, 'Dashboard_Status', '')));

        // Jika transaksi sudah final (disetujui/ditolak), JANGAN anggap expired —
        // flow sudah selesai walau ada langkah yang sempat melewati batas waktu.
        if (in_array($status, ['APPROVED', 'REJECTED'], true)) {
            return false;
        }

        if ((int) data_get($item, 'Has_Expired_Pending_Approval', 0) > 0) {
            return true;
        }

        return in_array($status, ['EXPIRED', 'VOID'], true);
    }

    /**
     * Teks expired / batas approval untuk ditampilkan di frontend.
     *
     * Sumber kebenaran: kolom Approval_Expired_At dari view DB
     * (N_HRIS_VW_Approval_Request_V2.Expired_At), bukan kalkulasi PHP.
     * Jika Expired_At tidak tersedia → tidak ada teks.
     */
    protected function resolveSubmissionExpiryText($item, ?Carbon $requestedAt): ?string
    {
        // Teks expiry hanya relevan untuk transaksi yang BELUM final. Jika sudah
        // APPROVED/REJECTED/VOID, jangan tampilkan "Expired sejak ..." walau
        // Approval_Expired_At sudah lewat (flow sudah selesai sebelum/sesudah batas).
        $status = strtoupper(trim((string) data_get($item, 'Dashboard_Status', '')));
        if (in_array($status, ['APPROVED', 'REJECTED', 'VOID'], true)) {
            return null;
        }

        $approvalExpiredAtRaw = data_get($item, 'Approval_Expired_At');
        if (! empty($approvalExpiredAtRaw)) {
            try {
                $approvalExpiredAt = Carbon::parse((string) $approvalExpiredAtRaw, 'Asia/Jakarta');
            } catch (\Throwable $e) {
                $approvalExpiredAt = null;
            }

            if ($approvalExpiredAt) {
                if ($approvalExpiredAt->isPast()) {
                    return 'Expired sejak '.$approvalExpiredAt->translatedFormat('d M Y');
                }

                return 'Batas approval '.$approvalExpiredAt->translatedFormat('d M Y');
            }
        }

        return null;
    }

    protected function buildUpcomingSchedule(string $kodeKaryawan, array $kpiSummary, array $lmsSummary): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $schedule = collect($this->shiftRequestService->getWeekSchedule($kodeKaryawan, $today->copy()))
            ->take(7)
            ->map(function ($item) {
                $shift = $item['shift'] ?? null;
                $label = $shift['nama'] ?? ($item['status_hari_label'] ?? 'OFF');
                $time = $shift
                    ? ($shift['jam_masuk'] ?? '--:--').' - '.($shift['jam_keluar'] ?? '--:--')
                    : $item['status_hari_label'] ?? 'Tidak ada shift';

                return [
                    'date' => Carbon::parse($item['date'])->translatedFormat('d M'),
                    'label' => $label,
                    'badge' => ! empty($item['is_day_off']) ? 'OFF' : 'Shift',
                    'time' => $time,
                    'tone' => ! empty($item['is_day_off']) ? 'default' : 'indigo',
                    'href' => null,
                ];
            })
            ->values();

        if (! empty($kpiSummary['deadline_text'])) {
            $schedule->push([
                'date' => 'KPI',
                'label' => $kpiSummary['parameter_submitted'] ? 'Update Performance' : 'Form KPI',
                'badge' => 'KPI',
                'time' => $kpiSummary['deadline_text'],
                'tone' => 'amber',
                'href' => $kpiSummary['parameter_submitted'] ? '/update-performance' : '/form-kpi',
            ]);
        }

        if (! empty($lmsSummary['next_session']) && ($lmsSummary['mandatory_pending'] ?? 0) > 0) {
            $schedule->push([
                'date' => 'LMS',
                'label' => $lmsSummary['next_session'],
                'badge' => 'Training',
                'time' => $lmsSummary['next_session_date'] ?? '-',
                'tone' => 'teal',
                'href' => '/lms/training/catalog',
            ]);
        }

        return $schedule->take(7)->values()->all();
    }

    protected function resolveTransactionRoute(
        ?string $noTransaksi,
        ?string $status = null,
        ?string $jenis = null,
    ): ?string {
        $noTransaksi = trim((string) $noTransaksi);
        $status = strtolower(trim((string) $status));
        $state =
            $status === 'approved'
                ? NotificationRouteRegistry::STATE_APPROVED
                : NotificationRouteRegistry::STATE_REJECTED;

        if ($noTransaksi !== '') {
            $resolved = $this->notificationRouteRegistry->resolve($noTransaksi, $state);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $type = strtoupper(trim((string) $jenis));
        $baseUrl = match ($type) {
            'CUTI' => '/cuti',
            'IZIN', 'SAKIT_IZIN', 'TERLAMBAT', 'TERLAMBAT_PULANG_CEPAT' => '/izin',
            'KONVERSI_LEMBUR' => '/konversi-lembur',
            'LEMBUR' => '/lembur-management',
            'SHIFT' => '/self-shift',
            'STATUS_HARI' => '/leader-shift',
            default => null,
        };

        if ($baseUrl === null || $noTransaksi === '') {
            return $baseUrl;
        }

        return $baseUrl.'?no_transaksi='.rawurlencode($noTransaksi);
    }
}
