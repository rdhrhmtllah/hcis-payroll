<?php

namespace App\Http\Controllers\ESS\Dashboard\Homepage;

use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Leave\Admin\cutiBersamaService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\ESS\Dashboard\Homepage\HomepageHcisService;
use App\Services\ESS\Dashboard\Homepage\HomepageKpiService;
use App\Services\ESS\Dashboard\Homepage\HomepageLeaderDashboardService;
use App\Services\ESS\Dashboard\Homepage\HomepageLmsService;
use App\Services\ESS\Dashboard\Homepage\HomepagePersonalDashboardService;
use App\Services\ESS\Dashboard\Homepage\HomepageRoleService;
use App\Services\ESS\Dashboard\Homepage\HomepageVisitorService;
use App\Services\ESS\Dashboard\Personal\UDashReadModelService;
use App\Services\SYSTEM\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class HomepageController extends Controller
{
    public function __construct(
        protected UDashReadModelService $uDashReadModelService,
        protected CutiBersamaService $cutiBersamaService,
        protected HomepageRoleService $homepageRoleService,
        protected NotificationService $notificationService,
        protected HomepageHcisService $homepageHcisService,
        protected HomepageVisitorService $homepageVisitorService,
        protected HomepageLmsService $homepageLmsService,
        protected HomepageKpiService $homepageKpiService,
        protected HomepagePersonalDashboardService $homepagePersonalDashboardService,
        protected HomepageLeaderDashboardService $homepageLeaderDashboardService,
        /* Dipakai getCalendarData(). Sebelumnya TIDAK pernah disuntikkan, jadi
           $this->attendanceService tak pernah ada dan kedua rute kalender —
           homepage/calendar-data dan uDash/getCalendarData — selalu menjawab
           500 untuk siapa pun. Kegagalannya tertelan catch generik dan hanya
           tampak sebagai "Tidak bisa mengambil data kalender", sehingga tak
           pernah terbaca sebagai cacat pemrograman. */
        protected HrisAttendanceSnapshotService $attendanceService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        $userIdAbsen = $user->karyawan->UserID_Absen ?? ($user->Username ?? null);
        $namaKaryawan = $user->karyawan->Nama ?? 'User';

        $roleDetails = $this->homepageRoleService->getRoleDetails($kodeKaryawan);
        $isLeader = $roleDetails['is_leader'];
        $role = $roleDetails['role'];
        $teamCount = $roleDetails['team_count'];
        $pendingApprovals = 0;

        if ($isLeader && $kodeKaryawan) {
            try {
                $pendingApprovals = $this->uDashReadModelService->countPendingApprovalsV2($kodeKaryawan) ?? 0;
            } catch (\Throwable $e) {
                Log::warning('Homepage initial pending approval count failed: '.$e->getMessage(), [
                    'kode_karyawan' => $kodeKaryawan,
                ]);
            }
        }

        $notifications = $kodeKaryawan ? $this->notificationService->listUnread($kodeKaryawan) : collect();
        $initialDashboard = null;

        if ($kodeKaryawan && $userIdAbsen) {
            // Defer heavy data loading to client-side API requests to prevent slow page transitions
            $initialDashboard = null;
        }

        $greeting = [
            'nama' => $namaKaryawan,
            'jabatan' => $user->karyawan->level->nama_jabatan ?? '',
            'divisi' => $user->karyawan->division->Nama_Divisi ?? '',
            'is_leader' => $isLeader,
            'team_count' => $teamCount,
        ];

        return Inertia::render('ESS/Dashboard/Homepage/homepage', [
            'user' => [
                'id' => $user->Id_Users ?? null,
                'name' => $namaKaryawan,
                'username' => $user->Username ?? null,
                'department' => $user->karyawan->division->Nama_Divisi ?? '',
                'job_title' => $user->karyawan->level->nama_jabatan ?? '',
                'nik' => $user->karyawan->No_Induk_Karyawan ?? null,
            ],
            'role' => $role,
            'greeting' => $greeting,
            'notifications' => $notifications,
            'initialDashboard' => $initialDashboard,
            'tourProgress' => $this->loadTourProgress($user->Id_Users ?? null, 'homepage', 3),
        ]);
    }

    private function loadTourProgress($userId, string $tourKey = 'homepage', int $stepVersion = 1): array
    {
        $default = [
            'status' => 'not_started',
            'current_step' => null,
            'completed_steps' => [],
            'skipped_at_step' => null,
            'started_at' => null,
            'completed_at' => null,
            'updated_at' => null,
            'step_version' => $stepVersion,
        ];

        if (empty($userId)) {
            return $default;
        }

        try {
            $raw = DB::table('KPI_Users')->where('Id_Users', $userId)->value('tour_progress');
        } catch (\Throwable $e) {
            Log::warning('Homepage tour progress load failed: '.$e->getMessage(), ['user_id' => $userId]);

            return $default;
        }

        if (empty($raw)) {
            return $default;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $default;
        }

        $progress = $decoded['tours'][$tourKey] ?? null;
        if (! $progress && $tourKey === 'homepage' && isset($decoded['status'])) {
            $progress = $decoded;
        }

        return array_merge($default, is_array($progress) ? $progress : []);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * API: Get unread notifications for polling
     */
    /**
     * Revalidasi notifikasi "issue" yang masih aktif terhadap kondisi data
     * terkini sebelum daftar notifikasi dibaca. Notif yang sudah solved akan
     * di-resolve (hilang dari bell/pusat notifikasi). Mencakup personal dan —
     * bila user seorang leader — notif leader dashboard. Throttle & error
     * handling ada di dalam service; di sini cukup picu dengan aman.
     */
    protected function revalidateActiveNotificationsForUser($user, string $kodeKaryawan): void
    {
        try {
            $userIdAbsen = $user->karyawan->UserID_Absen ?? ($user->Username ?? null);
            if ($userIdAbsen) {
                $this->homepagePersonalDashboardService->revalidateActiveNotifications($kodeKaryawan, $userIdAbsen);
            }

            $roleDetails = $this->homepageRoleService->getRoleDetails($kodeKaryawan);
            if (! empty($roleDetails['is_leader'])) {
                $this->homepageLeaderDashboardService->revalidateActiveNotifications(
                    $kodeKaryawan,
                    (int) Carbon::now('Asia/Jakarta')->format('Y'),
                );
            }
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->warning('revalidateActiveNotificationsForUser failed: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);
        }
    }

    public function getUnreadNotifications(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['data' => []]);
        }

        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        if (! $kodeKaryawan) {
            return response()->json(['data' => []]);
        }

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:50'],
            'module' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // POLLING RINGAN: endpoint ini ditembak topbar tiap 60 detik di SEMUA halaman
        // dan merupakan penyumbang 5xx TERBESAR saat DB jenuh (tiap poll gagal jadi 500
        // + browser terus retry = memperparah badai koneksi). Dua proteksi:
        //  1) MICRO-CACHE per user (TTL 20s) → mayoritas poll TIDAK menyentuh DB.
        //  2) FAIL-SOFT → saat DB error, balas last-known-good / kosong dengan HTTP 200,
        //     JANGAN biarkan exception naik jadi 500. Endpoint polling tak boleh 500.
        // Tetap TIDAK menjalankan revalidasi (berat); itu dipindah ke aksi jarang.
        $cacheKey = $this->unreadCacheKey($kodeKaryawan, $validated);
        $lkgKey = 'notif.unread.lkg:'.$kodeKaryawan.':'.md5(json_encode($validated));

        try {
            $payload = Cache::remember($cacheKey, 20, function () use ($kodeKaryawan, $validated) {
                return [
                    'data' => $this->notificationService->listUnread($kodeKaryawan, $validated),
                    'count' => $this->notificationService->countUnread($kodeKaryawan, $validated),
                ];
            });
            // Simpan salinan "last-known-good" (TTL lebih panjang, tanpa versi) sebagai
            // jaring pengaman fail-soft ketika DB sedang tak bisa memberi koneksi.
            Cache::put($lkgKey, $payload, 300);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->warning('getUnreadNotifications degraded: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);
            $payload = Cache::get($lkgKey, ['data' => [], 'count' => 0]);
        }

        return response()->json($payload);
    }

    /**
     * Kunci cache unread berversi per user. Versi di-bump (bustUnreadCache) saat status
     * baca berubah agar mark-as-read/dismiss langsung tercermin di poll berikutnya tanpa
     * perlu enumerasi kunci (kompatibel dengan cache store 'file').
     */
    private function unreadCacheKey(string $kodeKaryawan, array $options): string
    {
        $version = (int) Cache::get('notif.unread.ver:'.$kodeKaryawan, 1);

        return 'notif.unread.v1:'.$kodeKaryawan.':'.$version.':'.md5(json_encode($options));
    }

    private function bustUnreadCache(string $kodeKaryawan): void
    {
        $version = (int) Cache::get('notif.unread.ver:'.$kodeKaryawan, 1);
        Cache::forever('notif.unread.ver:'.$kodeKaryawan, $version + 1);
    }

    /**
     * API: Get all notifications (read/unread) for polling
     */
    public function getAllNotifications(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['data' => []]);
        }

        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        if (! $kodeKaryawan) {
            return response()->json(['data' => []]);
        }

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:50'],
            'module' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_read' => ['nullable', 'boolean'],
        ]);

        $options = $validated;
        if ($request->has('is_read')) {
            $options['is_read'] = filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN);
        }

        $notifications = $this->notificationService->listNotifications($kodeKaryawan, $options);

        return response()->json([
            'data' => $notifications,
        ]);
    }

    /**
     * API: Get notifications with pagination and search
     */
    public function paginateNotifications(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['data' => []], 401);
        }

        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        if (! $kodeKaryawan) {
            return response()->json(['data' => []], 422);
        }

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:50'],
            'module' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:150'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_read' => ['nullable', 'boolean'],
        ]);

        $options = $validated;
        if ($request->has('is_read')) {
            $options['is_read'] = filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->revalidateActiveNotificationsForUser($user, $kodeKaryawan);

        $paginator = $this->notificationService->paginateNotifications($kodeKaryawan, $options);

        return response()->json($paginator);
    }

    /**
     * API: Mark a specific notification as read
     */
    public function markNotificationRead(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false], 401);
        }

        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        if (! $kodeKaryawan) {
            return response()->json(['success' => false], 422);
        }

        $success = $this->notificationService->markAsRead((int) $id, $kodeKaryawan);
        if ($success) {
            $this->bustUnreadCache($kodeKaryawan);
        }

        return response()->json(['success' => $success]);
    }

    /**
     * API: Manual dismiss / Mark Done untuk notifikasi milik user login.
     */
    public function dismissNotification(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false], 401);
        }

        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        if (! $kodeKaryawan) {
            return response()->json(['success' => false], 422);
        }

        $success = $this->notificationService->dismiss((int) $id, $kodeKaryawan);
        if ($success) {
            $this->bustUnreadCache($kodeKaryawan);
        }

        return response()->json(['success' => $success]);
    }

    /**
     * API: Mark all notifications as read
     */
    public function markAllNotificationsRead(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false], 401);
        }

        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        if (! $kodeKaryawan) {
            return response()->json(['success' => false]);
        }

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:50'],
            'module' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $count = $this->notificationService->markAllAsRead($kodeKaryawan, $validated);
        if ($count > 0) {
            $this->bustUnreadCache($kodeKaryawan);
        }

        return response()->json(['success' => true, 'updated_count' => $count]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    public function getCalendarData(Request $request)
    {
        try {
            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $bulan = $request->bulan ?? Carbon::now()->month;
            $tahun = $request->tahun ?? Carbon::now()->year;

            $cutoffRange = $this->uDashReadModelService->getCutoffRangeForMonth((int) $tahun, (int) $bulan);
            $startDate = $cutoffRange['start_date']->format('Y-m-d');
            $endDate = $cutoffRange['end_date']->format('Y-m-d');

            $sourceReuqest = 'homepage-controller';
            $tglMulai = $startDate;
            $tglSelesai = $endDate;
            $listUserId = $UserID_Absen;
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

            $absen_data = collect(
                // DB::select("exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''", [
                //     $startDate,
                //     $endDate,
                //     $UserID_Absen,
                // ]),
                $resultData,
            );

            $approvedTransactions = collect(
                $this->uDashReadModelService->getApprovedTransactionsForCalendar(
                    $Kode_Karyawan,
                    $startDate,
                    $endDate,
                ) ?? [],
            );
            $approvedByDate = $approvedTransactions
                ->groupBy(fn (object $item) => Carbon::parse($item->Tanggal_Item)->format('Y-m-d'))
                ->map(fn ($items) => collect($items)->values());

            $pulangCepatDates = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('b.Kode_Karyawan', $Kode_Karyawan)
                ->where('b.Jenis', 'pulangCepat')
                ->where('b.Flag_Approval', 'Y')
                ->whereNull('a.Status')
                ->whereBetween(DB::raw('CAST(COALESCE(b.Tanggal_Hari_Kerja, b.Tanggal_Masuk_Pulang) AS DATE)'), [
                    $startDate,
                    $endDate,
                ])
                ->selectRaw('CAST(COALESCE(b.Tanggal_Hari_Kerja, b.Tanggal_Masuk_Pulang) AS DATE) as tanggal')
                ->get()
                ->mapWithKeys(fn (object $row) => [Carbon::parse($row->tanggal)->format('Y-m-d') => true]);

            $events = [];
            $shiftColors = [
                'PAGI' => '#10B981',
                'SIANG' => '#3B82F6',
                'SORE' => '#F59E0B',
                'MALAM' => '#6366F1',
                'OFF' => '#94A3B8',
                'NO SHIFT' => '#94A3B8',
            ];

            $statusColors = [
                'HADIR' => '#10B981',
                'TERLAMBAT' => '#F59E0B',
                'PULANG CEPAT' => '#EF4444',
                'IZIN' => '#8B5CF6',
                'SAKIT' => '#EC4899',
                'CUTI' => '#14B8A6',
                'OFF' => '#94A3B8',
                'ALPHA' => '#EF4444',
            ];

            foreach ($absen_data as $item) {
                $tanggal = Carbon::parse($item->Tanggal)->format('Y-m-d');
                $shiftType = strtoupper(trim((string) ($item->Nama_Shift ?? 'NO SHIFT')));
                $statusHari = strtoupper(trim((string) ($item->StatusHari ?? 'WORK')));
                $jamMasuk = $item->Jam_Masuk ?? '00:00';
                $jamKeluar = $item->Jam_Keluar ?? '00:00';

                $hasCheckIn = ! is_null($item->CheckIn) && $item->CheckIn !== '';
                $hasCheckOut = ! is_null($item->CheckOut) && $item->CheckOut !== '';
                $realLate = (float) ($item->RealLate ?? 0);
                $approvedType = $this->resolveCalendarApprovedType($approvedByDate->get($tanggal, collect()));
                $isDayOff = str_starts_with($statusHari, 'OFF') || $shiftType === 'OFF';

                $statusLabel = $isDayOff ? 'OFF' : 'HADIR';
                $statusColor = $statusColors[$statusLabel] ?? $statusColors['HADIR'];

                if ($approvedType === 'CUTI') {
                    $statusLabel = 'CUTI';
                    $statusColor = $statusColors['CUTI'];
                } elseif ($approvedType === 'SAKIT') {
                    $statusLabel = 'SAKIT';
                    $statusColor = $statusColors['SAKIT'];
                } elseif ($approvedType === 'IZIN') {
                    $statusLabel = 'IZIN';
                    $statusColor = $statusColors['IZIN'];
                } elseif ($hasCheckIn && $realLate > 0) {
                    $statusLabel = 'TERLAMBAT';
                    $statusColor = $statusColors['TERLAMBAT'];
                } elseif ($pulangCepatDates->has($tanggal)) {
                    $statusLabel = 'PULANG CEPAT';
                    $statusColor = $statusColors['PULANG CEPAT'];
                } elseif (! $hasCheckIn && ! $isDayOff && $shiftType !== 'NO SHIFT') {
                    $statusLabel = 'ALPHA';
                    $statusColor = $statusColors['ALPHA'];
                }

                $shiftColor = $shiftColors[$shiftType] ?? $shiftColors['NO SHIFT'];

                $events[] = [
                    'id' => uniqid(),
                    'title' => $shiftType,
                    'start' => $tanggal,
                    'end' => $tanggal,
                    'allDay' => true,
                    'backgroundColor' => $shiftColor,
                    'borderColor' => $shiftColor,
                    'textColor' => '#FFFFFF',
                    'extendedProps' => [
                        'shift_type' => $shiftType,
                        'shift_color' => $shiftColor,
                        'jam_masuk' => $jamMasuk,
                        'jam_keluar' => $jamKeluar,
                        'status' => $statusLabel,
                        'status_color' => $statusColor,
                        'check_in' => $hasCheckIn ? Carbon::parse($item->CheckIn)->format('H:i:s') : null,
                        'check_out' => $hasCheckOut ? Carbon::parse($item->CheckOut)->format('H:i:s') : null,
                        'real_late' => $realLate,
                        'keterangan' => $item->Keterangan ?? '',
                    ],
                ];
            }

            $lemburData = DB::table('Transaksi_Lembur as h')
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
                ->where('d.Kode_Karyawan', $Kode_Karyawan)
                ->whereNull('h.Status')
                // Sargable: range setengah-terbuka (indeks terpakai) menggantikan
                // CAST(det.Tanggal_Lembur AS DATE) yang non-sargable.
                ->where('det.Tanggal_Lembur', '>=', $startDate)
                ->where('det.Tanggal_Lembur', '<', Carbon::parse($endDate)->addDay()->toDateString())
                ->select(
                    'h.No_Transaksi',
                    'd.Tanggal_Lembur_Dari',
                    'd.Tanggal_Lembur_Sampai',
                    'det.Tanggal_Lembur',
                    'd.Alasan',
                    'd.Jenis',
                )
                ->get();

            foreach ($lemburData as $lembur) {
                $tanggalLembur = Carbon::parse($lembur->Tanggal_Lembur)->format('Y-m-d');
                $durasiJam = 0;

                try {
                    $durasiJam = (int) floor(
                        max(
                            Carbon::parse($lembur->Tanggal_Lembur_Dari)->diffInMinutes(
                                Carbon::parse($lembur->Tanggal_Lembur_Sampai),
                            ),
                            0,
                        ) / 60,
                    );
                } catch (\Throwable $e) {
                    $durasiJam = 0;
                }

                $events[] = [
                    'id' => uniqid(),
                    'title' => $durasiJam > 0 ? "LEMBUR ({$durasiJam} jam)" : 'LEMBUR',
                    'start' => $tanggalLembur,
                    'end' => $tanggalLembur,
                    'allDay' => true,
                    'backgroundColor' => '#FF6B6B',
                    'borderColor' => '#FF6B6B',
                    'textColor' => '#FFFFFF',
                    'extendedProps' => [
                        'event_type' => 'lembur',
                        'jam_mulai' => $lembur->Tanggal_Lembur_Dari
                            ? Carbon::parse($lembur->Tanggal_Lembur_Dari)->format('H:i:s')
                            : null,
                        'jam_selesai' => $lembur->Tanggal_Lembur_Sampai
                            ? Carbon::parse($lembur->Tanggal_Lembur_Sampai)->format('H:i:s')
                            : null,
                        'koefisien_ot' => $durasiJam,
                        'jenis_lembur' => $lembur->Jenis,
                        'alasan' => $lembur->Alasan,
                    ],
                ];
            }

            return response()->json([
                'status' => 200,
                'data' => $events,
                'cutoff' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'label' => $cutoffRange['label'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data kalender getCalendarData: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data kalender',
                ],
                500,
            );
        }
    }

    protected function resolveCalendarApprovedType($items): ?string
    {
        $items = collect($items);
        if ($items->isEmpty()) {
            return null;
        }

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

    /**
     * Display the dedicated Leader Dashboard.
     */
    public function leaderDashboard()
    {
        $user = Auth::user();
        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;

        $roleDetails = $this->homepageRoleService->getRoleDetails($kodeKaryawan);
        $isLeader = $roleDetails['is_leader'];

        if (! $isLeader) {
            return redirect()->route('homepage.index')->with('error', 'Anda tidak memiliki akses ke halaman ini.');
        }

        $greeting = [
            'nama' => $user->karyawan->Nama ?? 'User',
            'jabatan' => $user->karyawan->level->nama_jabatan ?? '',
            'divisi' => $user->karyawan->division->Nama_Divisi ?? '',
            'is_leader' => true,
        ];

        $payload = $this->homepageLeaderDashboardService->buildOverviewData(
            $kodeKaryawan,
            (int) ($roleDetails['team_count'] ?? 0),
        );

        return Inertia::render(
            'ESS/Dashboard/Homepage/LeaderDashboard',
            array_merge(
                [
                    'overview' => $payload,
                    'period' => $payload['period'] ?? null,
                    'todayLabel' => $payload['todayLabel'] ?? '',
                    'useDemoData' => false,
                ],
                [
                    'user' => [
                        'id' => $user->Id_Users ?? null,
                        'name' => $user->karyawan->Nama ?? 'User',
                        'username' => $user->Username ?? null,
                    ],
                    'greeting' => $greeting,
                    'tourProgress' => $this->loadTourProgress($user->Id_Users ?? null, 'leader-dashboard', 1),
                ],
            ),
        );
    }

    public function getAttendanceDayDetail(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = Auth::user();
        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        $userIdAbsen = $user->karyawan->UserID_Absen ?? ($user->Username ?? null);

        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $date = $request->string('date')->toString();

        // Detail tanggal KEMARIN/HARI INI = MICRO-CACHE (20s): check-in/out & koreksi
        // 2 hari terakhir tetap menyusul cepat, tapi klik beruntun tak mengulang
        // scan cutoff. Tanggal lampau stabil -> TTL panjang (120s).
        $yesterday = \Carbon\Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        $isRealtimeDate = $date >= $yesterday;

        try {
            $detailFn = fn () => $this->homepagePersonalDashboardService->getAttendanceDayDetail($kodeKaryawan, $userIdAbsen, $date);
            $detail = Cache::remember(
                "homepage_day_detail_{$kodeKaryawan}_{$date}",
                $isRealtimeDate ? 20 : 120,
                $detailFn,
            );

            return response()->json(['data' => $detail]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getAttendanceDayDetail error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
                'date' => $date,
            ]);

            return response()->json(['message' => 'Gagal memuat detail absensi.'], 500);
        }
    }

    /**
     * Endpoint agregat above-the-fold: attendance + composition + hcis + weekly
     * dalam SATU request -> 1 pass SP absen (via memoization + trend in-memory).
     * Menggantikan 4 request terpisah (attendance-summary, attendance-composition,
     * hcis-summary, weekly-calendar) di initial load. Below-the-fold tetap lazy.
     */
    public function getPersonalBootstrap(Request $request)
    {
        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            $roleDetails = $this->homepageRoleService->getRoleDetails($kodeKaryawan);
            $pendingApprovals = ! empty($roleDetails['is_leader'])
                ? (int) ($this->uDashReadModelService->countPendingApprovalsV2($kodeKaryawan) ?? 0)
                : 0;

            $bootstrap = $this->homepagePersonalDashboardService->buildPersonalBootstrap(
                $kodeKaryawan,
                $userIdAbsen,
                $pendingApprovals,
            );

            return response()->json(['data' => $bootstrap]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalBootstrap error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat data dashboard.'], 500);
        }
    }

    public function getPersonalAttendanceSummary(Request $request)
    {
        $validated = $request->validate([
            'cutoff_period' => ['nullable', 'date_format:Y-m'],
        ]);

        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            $roleDetails = $this->homepageRoleService->getRoleDetails($kodeKaryawan);
            $pendingApprovals = ! empty($roleDetails['is_leader'])
                ? (int) ($this->uDashReadModelService->countPendingApprovalsV2($kodeKaryawan) ?? 0)
                : 0;
            $summary = $this->homepagePersonalDashboardService->getAttendanceSummary(
                $kodeKaryawan,
                $userIdAbsen,
                $validated['cutoff_period'] ?? null,
            );

            return response()->json([
                'data' => [
                    'cutoffRange' => $summary['cutoffRange'] ?? null,
                    'cutoffPeriodOptions' => $summary['cutoffPeriodOptions'] ?? [],
                    'attendanceRecords' => $summary['attendanceRecords'] ?? [],
                    'overtime' => $summary['overtime'] ?? [],
                    'pendingApprovals' => $pendingApprovals,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalAttendanceSummary error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat ringkasan absensi.'], 500);
        }
    }

    public function getPersonalAttendanceComposition(Request $request)
    {
        $validated = $request->validate([
            'cutoff_period' => ['nullable', 'date_format:Y-m'],
        ]);

        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $period = $validated['cutoff_period'] ?? null;

        // Periode BERJALAN (mencakup hari ini) pakai MICRO-CACHE (20s) alih-alih
        // bypass total: komposisi (telat/izin/hadir) tetap menyusul cepat saat
        // absensi hari ini ter-update, tapi re-load/double-fire beruntun tak
        // mengulang query. Periode LAMPAU stabil -> TTL lebih panjang (90s).
        $currentPeriod = \Carbon\Carbon::now('Asia/Jakarta')->format('Y-m');
        $isRealtimePeriod = $period === null || $period === $currentPeriod;

        try {
            $compositionFn = fn () => $this->homepagePersonalDashboardService->getAttendanceCompositionSummary(
                $kodeKaryawan,
                $userIdAbsen,
                $period,
            );
            $composition = Cache::remember(
                "homepage_attendance_composition_{$kodeKaryawan}_{$userIdAbsen}_".($period ?? $currentPeriod),
                $isRealtimePeriod ? 20 : 90,
                $compositionFn,
            );

            return response()->json(['data' => $composition]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalAttendanceComposition error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat komposisi absensi.'], 500);
        }
    }

    public function getPersonalOvertimeDetail(Request $request)
    {
        $validated = $request->validate([
            'cutoff_period' => ['nullable', 'date_format:Y-m'],
            'include_trend' => ['nullable', 'boolean'],
        ]);

        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $period = $validated['cutoff_period'] ?? null;
        $includeTrend = (bool) ($validated['include_trend'] ?? false);

        // Periode BERJALAN (mencakup hari ini) pakai micro-cache pendek; periode
        // LAMPAU stabil -> TTL lebih panjang. Trend 2-cutoff dicache terpisah (key
        // menyertakan flag trend) karena hanya dihitung saat card dibuka.
        $currentPeriod = \Carbon\Carbon::now('Asia/Jakarta')->format('Y-m');
        $isRealtimePeriod = $period === null || $period === $currentPeriod;

        try {
            $detailFn = fn () => $this->homepagePersonalDashboardService->getOvertimeDetail(
                $kodeKaryawan,
                $userIdAbsen,
                $period,
                $includeTrend,
            );
            $detail = Cache::remember(
                "homepage_overtime_detail_{$kodeKaryawan}_{$userIdAbsen}_".($period ?? $currentPeriod).'_'.($includeTrend ? 't' : 'f'),
                $isRealtimePeriod ? 20 : 90,
                $detailFn,
            );

            return response()->json(['data' => $detail]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalOvertimeDetail error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat detail lembur.'], 500);
        }
    }

    public function getPersonalWeeklyCalendar(Request $request)
    {
        $validated = $request->validate([
            'week_start' => ['nullable', 'date_format:Y-m-d'],
        ]);

        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            return response()->json([
                'data' => $this->homepagePersonalDashboardService->getWeeklyCalendar(
                    $kodeKaryawan,
                    $userIdAbsen,
                    $validated['week_start'] ?? null,
                ),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalWeeklyCalendar error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat kalender mingguan.'], 500);
        }
    }

    public function getPersonalHcisSummary(Request $request)
    {
        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            return response()->json([
                'data' => $this->homepagePersonalDashboardService->getHcisSummary($kodeKaryawan, $userIdAbsen),
                'meta' => [
                    'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                    'source_scope' => 'personal',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalHcisSummary error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat ringkasan HCIS.'], 500);
        }
    }

    public function getPersonalNeedsAction(Request $request)
    {
        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $validated = $request->validate([
            'kpi_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json(
                $this->homepagePersonalDashboardService->getNeedsAction(
                    $kodeKaryawan,
                    $userIdAbsen,
                    isset($validated['kpi_year']) ? (int) $validated['kpi_year'] : null,
                    isset($validated['page']) ? (int) $validated['page'] : 1,
                ),
            );
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalNeedsAction error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat data perlu tindakan.'], 500);
        }
    }

    public function getPersonalNeedsActionBadge(Request $request)
    {
        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $validated = $request->validate([
            'kpi_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            return response()->json([
                'data' => $this->homepagePersonalDashboardService->getNeedsActionBadge(
                    $kodeKaryawan,
                    $userIdAbsen,
                    isset($validated['kpi_year']) ? (int) $validated['kpi_year'] : null,
                ),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalNeedsActionBadge error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat badge notifikasi.'], 500);
        }
    }

    public function getLeaderNeedsActionBadge(Request $request)
    {
        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            return response()->json([
                'data' => $this->homepageLeaderDashboardService->getNeedsActionBadge($kodeKaryawan),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getLeaderNeedsActionBadge error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat badge leader.'], 500);
        }
    }

    public function getPersonalKpiSummary(Request $request)
    {
        [$kodeKaryawan] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            return response()->json([
                'data' => $this->homepagePersonalDashboardService->getKpiSummary(
                    $kodeKaryawan,
                    isset($validated['year']) ? (int) $validated['year'] : null,
                ),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalKpiSummary error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat ringkasan KPI.'], 500);
        }
    }

    public function getPersonalKpiDetail(Request $request)
    {
        [$kodeKaryawan] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period' => ['nullable', 'regex:/^(\d{4}-\d{2}|\d{1,2}|all|full_year)$/i'],
            'mode' => ['nullable', 'in:period,ytd'],
        ]);

        try {
            return response()->json([
                'data' => $this->homepageKpiService->getPersonalDashboardDetail(
                    $kodeKaryawan,
                    isset($validated['year']) ? (int) $validated['year'] : null,
                    $validated['period'] ?? null,
                    $validated['mode'] ?? 'period',
                ),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalKpiDetail error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat detail KPI.'], 500);
        }
    }

    public function getPersonalLmsSummary(Request $request)
    {
        [$kodeKaryawan] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            return response()->json([
                'data' => $this->homepagePersonalDashboardService->getLmsSummary(
                    $kodeKaryawan,
                    isset($validated['year']) ? (int) $validated['year'] : null,
                ),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalLmsSummary error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat ringkasan LMS.'], 500);
        }
    }

    public function getPersonalLeaveSummary(Request $request)
    {
        [$kodeKaryawan] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            return response()->json(['data' => $this->homepagePersonalDashboardService->getLeaveSummary($kodeKaryawan)]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalLeaveSummary error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat ringkasan cuti.'], 500);
        }
    }

    public function getPersonalSubmissionsSummary(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$kodeKaryawan] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            $result = $this->homepagePersonalDashboardService->getSubmissionsSummary(
                $kodeKaryawan,
                (int) ($validated['page'] ?? 1),
            );

            return response()->json([
                'data' => $result['items'] ?? [],
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalSubmissionsSummary error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat ringkasan pengajuan.'], 500);
        }
    }

    public function getPersonalUpcomingSchedule(Request $request)
    {
        [$kodeKaryawan] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            return response()->json([
                'data' => $this->homepagePersonalDashboardService->getUpcomingScheduleSummary($kodeKaryawan),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalUpcomingSchedule error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat jadwal mendatang.'], 500);
        }
    }

    public function getPersonalCalendarDayDetail(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        [$kodeKaryawan, $userIdAbsen] = $this->resolvePersonalEmployeeContext();
        if (! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        try {
            return response()->json([
                'data' => $this->homepagePersonalDashboardService->getCalendarDayDetail(
                    $kodeKaryawan,
                    $userIdAbsen,
                    $request->string('date')->toString(),
                ),
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getPersonalCalendarDayDetail error: '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat detail kalender.'], 500);
        }
    }

    public function getLeaderMemberDetail(Request $request, string $kodeKaryawan)
    {
        $user = Auth::user();
        $leaderKode = $user->karyawan->Kode_Karyawan ?? null;

        if (! $leaderKode) {
            return response()->json(['message' => 'Data leader tidak ditemukan.'], 422);
        }

        $exists = DB::table('HRIS_Karyawan_Team')
            ->where('Kode_Karyawan_Team', $leaderKode)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->exists();

        if (! $exists) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke anggota ini.'], 403);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'kpi_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'lms_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $selectedKpiYear = isset($validated['kpi_year'])
            ? (int) $validated['kpi_year']
            : (isset($validated['year'])
                ? (int) $validated['year']
                : null);
        $selectedLmsYear = isset($validated['lms_year'])
            ? (int) $validated['lms_year']
            : (isset($validated['year'])
                ? (int) $validated['year']
                : null);

        try {
            $detail = Cache::remember(
                "homepage_leader_member_detail_{$leaderKode}_{$kodeKaryawan}_".
                    'kpi'.
                    ($selectedKpiYear ?? 'current').
                    '_lms'.
                    ($selectedLmsYear ?? 'current').
                    '_legacy'.
                    (isset($validated['year']) ? (int) $validated['year'] : 'none').
                    '_'.
                    Carbon::today()->format('Y-m-d'),
                30,
                function () use ($leaderKode, $kodeKaryawan, $selectedKpiYear, $selectedLmsYear) {
                    return $this->homepageLeaderDashboardService->getMemberDetail(
                        $leaderKode,
                        $kodeKaryawan,
                        $selectedKpiYear,
                        $selectedLmsYear,
                    );
                },
            );
            $kpiYearMeta = (int) data_get(
                $detail,
                'kpi.year',
                $selectedKpiYear ?? (int) Carbon::now('Asia/Jakarta')->format('Y'),
            );
            $lmsYearMeta = (int) data_get(
                $detail,
                'lms.year',
                $selectedLmsYear ?? (int) Carbon::now('Asia/Jakarta')->format('Y'),
            );

            return response()->json([
                'data' => $detail,
                'meta' => [
                    'kpi_year' => $kpiYearMeta,
                    'lms_year' => $lmsYearMeta,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getLeaderMemberDetail error: '.$e->getMessage(), [
                'leader_kode' => $user->karyawan->Kode_Karyawan ?? null,
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat detail anggota tim.'], 500);
        }
    }

    public function getLeaderApprovalItems(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 6);

        try {
            $result = $this->homepageLeaderDashboardService->getPaginatedApprovalItems($leaderKode, $page, $perPage);

            return response()->json($this->withLeaderResponseMeta($result));
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getLeaderApprovalItems error: '.$e->getMessage(), [
                'leader_kode' => $leaderKode,
            ]);

            return response()->json(['message' => 'Gagal memuat antrian approval.'], 500);
        }
    }

    public function getLeaderSummary()
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        try {
            $summary = $this->homepageLeaderDashboardService->getGlobalSummary($leaderKode);

            return response()->json([
                'data' => [
                    'summary' => $summary,
                ],
                'meta' => [
                    'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                    'source_scope' => 'full_team',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getLeaderSummary error: '.$e->getMessage(), [
                'leader_kode' => $leaderKode,
            ]);

            return response()->json(['message' => 'Gagal memuat ringkasan tim.'], 500);
        }
    }

    public function getLeaderNeedsAction(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'kpi_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'category' => ['nullable', 'string', 'in:all,hcis,kpi,approval'],
            'search' => ['nullable', 'string', 'max:100'],
            'profile' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->homepageLeaderDashboardService->getNeedsAction(
                $leaderKode,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 10),
                [
                    'kpi_year' => isset($validated['kpi_year']) ? (int) $validated['kpi_year'] : null,
                    'category' => $validated['category'] ?? 'all',
                    'search' => trim((string) ($validated['search'] ?? '')),
                    'include_profile' => (bool) ($validated['profile'] ?? false),
                ],
            );

            return response()->json($this->withLeaderResponseMeta($result));
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getLeaderNeedsAction error: '.$e->getMessage(), [
                'leader_kode' => $leaderKode,
            ]);

            return response()->json(['message' => 'Gagal memuat data perlu tindakan tim.'], 500);
        }
    }

    public function getLeaderSummaryHcis()
    {
        // dd('test');
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        // dd($leaderKode);

        $payload = $this->homepageLeaderDashboardService->getHcisSummary($leaderKode);

        return response()->json([
            'data' => $payload,
            'meta' => [
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                'source_scope' => 'full_team',
            ],
        ]);
    }

    public function getLeaderSummaryKpi(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $payload = $this->homepageLeaderDashboardService->getKpiSummary(
            $leaderKode,
            isset($validated['year']) ? (int) $validated['year'] : null,
        );
        $effectiveKpiYear = (int) data_get($payload, 'stats.kpiYear', (int) Carbon::now('Asia/Jakarta')->format('Y'));

        return response()->json([
            'data' => $payload,
            'meta' => [
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                'source_scope' => 'full_team',
                'kpi_year' => $effectiveKpiYear,
            ],
        ]);
    }

    public function getLeaderKpiDetails(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:annual,monthly,yearly,ytd,month,full_year'],
            'context' => [
                'nullable',
                'string',
                'in:monthly_avg,ytd_avg,form_approved,approval_queue,ytd_le_80,ytd_gt_80,trend_month',
            ],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'compact' => ['nullable', 'boolean'],
        ]);

        $result = $this->homepageLeaderDashboardService->getPaginatedKpiDetails(
            $leaderKode,
            $validated['mode'] ?? 'annual',
            $validated['period'] ?? null,
            $validated['context'] ?? 'monthly_avg',
            $validated['month'] ?? null,
            isset($validated['year']) ? (int) $validated['year'] : null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10),
            trim((string) ($validated['q'] ?? '')),
            (bool) ($validated['compact'] ?? false),
        );
        $effectiveKpiYear = (int) data_get(
            $result,
            'meta.kpi_year',
            isset($validated['year']) ? (int) $validated['year'] : (int) Carbon::now('Asia/Jakarta')->format('Y'),
        );
        $result['meta'] = array_merge((array) ($result['meta'] ?? []), ['kpi_year' => $effectiveKpiYear]);

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderKpiMemberDetail(Request $request, string $kodeKaryawan)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:annual,monthly,yearly,ytd,month,full_year'],
            'context' => [
                'nullable',
                'string',
                'in:monthly_avg,ytd_avg,form_approved,approval_queue,ytd_le_80,ytd_gt_80,trend_month',
            ],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $result = $this->homepageLeaderDashboardService->getKpiMemberDetail(
            $leaderKode,
            $kodeKaryawan,
            $validated['mode'] ?? 'month',
            $validated['period'] ?? null,
            $validated['context'] ?? 'monthly_avg',
            $validated['month'] ?? null,
            isset($validated['year']) ? (int) $validated['year'] : null,
        );
        $effectiveKpiYear = (int) data_get(
            $result,
            'meta.kpi_year',
            (int) data_get($result, 'data.summary.kpi_year', (int) Carbon::now('Asia/Jakarta')->format('Y')),
        );
        $result['meta'] = array_merge((array) ($result['meta'] ?? []), ['kpi_year' => $effectiveKpiYear]);

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderSummaryLms(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $payload = $this->homepageLeaderDashboardService->getLmsSummary(
            $leaderKode,
            isset($validated['year']) ? (int) $validated['year'] : null,
        );

        return response()->json([
            'data' => $payload,
            'meta' => [
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                'source_scope' => 'full_team',
            ],
        ]);
    }

    public function getLeaderLmsTeamMembers(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'focus' => [
                'required',
                'string',
                'in:lms_not_started,kpi_param_not_submitted,progress_below_target,progress_above_target,learning_gain,assigned,avg_progress,total_training,completed,at_risk,risk',
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->homepageLeaderDashboardService->getPaginatedLmsTeamMembers(
            $leaderKode,
            (int) $validated['year'],
            (string) $validated['focus'],
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10),
            trim((string) ($validated['search'] ?? '')),
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderMembers(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'attendance' => ['nullable', 'string', 'max:30'],
            'kpi' => ['nullable', 'string', 'max:30'],
            'lms' => ['nullable', 'string', 'max:30'],
            'kpi_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        $result = $this->homepageLeaderDashboardService->getPaginatedTeamMembers($leaderKode, $page, $perPage, [
            'search' => $validated['search'] ?? '',
            'attendance' => $validated['attendance'] ?? 'all',
            'kpi' => $validated['kpi'] ?? 'all',
            'lms' => $validated['lms'] ?? 'all',
            'kpi_year' => isset($validated['kpi_year']) ? (int) $validated['kpi_year'] : null,
        ]);

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderHeatmap(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'cutoff_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'cutoff_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $search = trim((string) ($validated['search'] ?? ''));
        $cutoffYear = isset($validated['cutoff_year']) ? (int) $validated['cutoff_year'] : null;
        $cutoffMonth = isset($validated['cutoff_month']) ? (int) $validated['cutoff_month'] : null;

        $result = $this->homepageLeaderDashboardService->getPaginatedHeatmapRows(
            $leaderKode,
            $page,
            $perPage,
            $startDate,
            $endDate,
            $search,
            $cutoffYear,
            $cutoffMonth,
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderAttendanceIssues(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $date = $validated['date'];
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        $result = $this->homepageLeaderDashboardService->getPaginatedAttendanceIssues(
            $leaderKode,
            $date,
            $page,
            $perPage,
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderAttendanceStatuses(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'filter' => [
                'nullable',
                'string',
                'in:all,present,no-checkin,pending-checkin,working,no-checkout,late,early-out,leave,no-data',
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->homepageLeaderDashboardService->getPaginatedAttendanceStatusRows(
            $leaderKode,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10),
            [
                'filter' => $validated['filter'] ?? 'all',
                'search' => $validated['search'] ?? '',
            ],
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderCalendarMonthSummary(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $result = $this->homepageLeaderDashboardService->getLeaderCalendarMonthSummary(
            $leaderKode,
            isset($validated['year']) ? (int) $validated['year'] : null,
            isset($validated['month']) ? (int) $validated['month'] : null,
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderCalendarDayDetail(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'filter' => ['nullable', 'string', 'in:all,problem,late,no-in-out,izin-cuti'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->homepageLeaderDashboardService->getLeaderCalendarDayDetail(
            $leaderKode,
            $validated['date'],
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10),
            [
                'filter' => $validated['filter'] ?? 'all',
                'search' => $validated['search'] ?? '',
            ],
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    /**
     * DRAWER A — breakdown 1 kategori HCIS sepanjang periode cutoff berjalan.
     */
    public function getLeaderHcisPeriodBreakdown(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:absent,no-in-out,late-early,issue,izin-cuti'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'scope' => ['nullable', 'string', 'in:period,today'],
        ]);

        $result = $this->homepageLeaderDashboardService->getHcisPeriodBreakdown(
            $leaderKode,
            $validated['category'],
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10),
            ['search' => $validated['search'] ?? ''],
            $validated['scope'] ?? 'period',
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    /**
     * DRAWER B — breakdown status hari ini (klik kartu "Status Hari Ini").
     */
    public function getLeaderHcisTodayBreakdown(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $result = $this->homepageLeaderDashboardService->getHcisTodayBreakdown($leaderKode);

        return response()->json($this->withLeaderResponseMeta($result));
    }

    /**
     * ACCORDION Drawer B — daftar anggota berstatus tertentu hari ini.
     */
    public function getLeaderHcisTodayMembers(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:present,on-time,late,early-out,pending-checkin,working,absent,no-in-out',
            ],
        ]);

        $result = $this->homepageLeaderDashboardService->getHcisTodayMembers($leaderKode, $validated['status']);

        return response()->json($this->withLeaderResponseMeta($result));
    }

    /**
     * ACCORDION — daftar tanggal 1 anggota yang cocok kategori (Drawer A & B).
     */
    public function getLeaderHcisMemberCategoryDates(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50'],
            'category' => ['required', 'string', 'in:absent,no-in-out,late-early,issue,izin-cuti'],
            'scope' => ['nullable', 'string', 'in:period,today'],
        ]);

        $result = $this->homepageLeaderDashboardService->getHcisMemberCategoryDates(
            $leaderKode,
            $validated['kode'],
            $validated['category'],
            $validated['scope'] ?? 'period',
        );

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderTrainingPending(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 6);

        $result = $this->homepageLeaderDashboardService->getPaginatedTrainingPending($leaderKode, $page, $perPage);

        return response()->json($this->withLeaderResponseMeta($result));
    }

    public function getLeaderShiftSchedules(Request $request)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'shift_name' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $date = $validated['date'] ?? Carbon::today('Asia/Jakarta')->format('Y-m-d');
        if (! empty($validated['shift_name'])) {
            $page = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 8);

            $data = $this->homepageLeaderDashboardService->getLeaderShiftDetail(
                $leaderKode,
                $date,
                $validated['shift_name'],
                $page,
                $perPage,
            );

            return response()->json(
                $this->withLeaderResponseMeta($data, [
                    'date' => $date,
                    'shift_name' => $validated['shift_name'],
                ]),
            );
        }

        $data = $this->homepageLeaderDashboardService->getLeaderShiftSchedules($leaderKode, $date);

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => count($data),
                'date' => $date,
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                'source_scope' => 'full_team',
            ],
        ]);
    }

    /**
     * API: Get weekly calendar data for a specific team member.
     * Accepts ?week_date=YYYY-MM-DD to navigate to a specific week.
     */
    public function getLeaderMemberWeeklyCalendar(Request $request, string $kodeKaryawan)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'week_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $weekDate = $validated['week_date'] ?? null;

        try {
            $cacheKey =
                "leader_member_weekly_cal_{$leaderKode}_{$kodeKaryawan}_".
                ($weekDate ?? Carbon::today('Asia/Jakarta')->toDateString());

            $result = Cache::remember($cacheKey, 60, function () use ($leaderKode, $kodeKaryawan, $weekDate) {
                return $this->homepageLeaderDashboardService->getMemberWeeklyCalendar(
                    $leaderKode,
                    $kodeKaryawan,
                    $weekDate,
                );
            });

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getLeaderMemberWeeklyCalendar error: '.$e->getMessage(), [
                'leader_kode' => $leaderKode,
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat kalender mingguan.'], 500);
        }
    }

    public function getLeaderMemberDayCheckTimes(Request $request, string $kodeKaryawan)
    {
        $leaderKode = $this->resolveLeaderKodeOrAbort();
        if (! $leaderKode) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke halaman ini.'], 403);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $result = $this->homepageLeaderDashboardService->getMemberDayCheckTimeLogs(
                $leaderKode,
                $kodeKaryawan,
                $validated['date'],
            );

            if (! ($result['authorized'] ?? false)) {
                return response()->json(['message' => 'Member tidak ditemukan dalam tim Anda.'], 404);
            }

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            Log::channel('homepageLog')->error('getLeaderMemberDayCheckTimes error: '.$e->getMessage(), [
                'leader_kode' => $leaderKode,
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return response()->json(['message' => 'Gagal memuat riwayat absensi.'], 500);
        }
    }

    protected function resolveLeaderKodeOrAbort(): ?string
    {
        $user = Auth::user();
        $kodeKaryawan = $user->karyawan->Kode_Karyawan ?? null;
        if (! $kodeKaryawan) {
            return null;
        }

        $roleDetails = $this->homepageRoleService->getRoleDetails($kodeKaryawan);
        if (! ($roleDetails['is_leader'] ?? false)) {
            return null;
        }

        return $kodeKaryawan;
    }

    protected function withLeaderResponseMeta(array $payload, array $meta = []): array
    {
        $payload['meta'] = array_merge(
            (array) ($payload['meta'] ?? []),
            [
                'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                'source_scope' => 'full_team',
            ],
            $meta,
        );

        return $payload;
    }

    protected function resolvePersonalEmployeeContext(): array
    {
        $user = Auth::user();

        return [$user->karyawan->Kode_Karyawan ?? null, $user->karyawan->UserID_Absen ?? ($user->Username ?? null)];
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
