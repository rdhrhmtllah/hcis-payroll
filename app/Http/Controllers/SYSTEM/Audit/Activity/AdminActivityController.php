<?php

namespace App\Http\Controllers\SYSTEM\Audit\Activity;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Audit\Activity\GerbangDiagnostikRealtime;
use App\Services\SYSTEM\Audit\Activity\PembacaKueriTerberat;
use App\Services\SYSTEM\Audit\Activity\PembacaKunciLangsung;
use App\Services\SYSTEM\Audit\Activity\PerekamKueriPerRute;
use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use App\Services\SYSTEM\Audit\Activity\Tracker\IpMasker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminActivityController extends Controller
{
    public function __construct(
        protected ActivityTracker $tracker,
        protected IpMasker $ipMasker,
    ) {}

    /* =========================================================
     * INERTIA: halaman utama
     * ========================================================= */
    public function index(Request $request)
    {
        return Inertia::render('SYSTEM/Audit/Activity/AdminMonitor', [
            'modules' => $this->moduleOptions(),
        ]);
    }

    /* =========================================================
     * KPI cards (DAU, MAU, active now, avg session, login failed 24h)
     * ========================================================= */
    public function kpis(Request $request)
    {
        return ResponseHelper::success(Cache::remember(
            'activity:admin:kpis',
            60,
            fn () => $this->buildKpis(),
        ), 'OK');
    }

    protected function buildKpis(): array
    {
        $conn = $this->conn();
        $now = now();

        // DAU = distinct user yang punya event hari ini
        $dau = (int) DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->where('Created_At', '>=', $now->copy()->startOfDay())
            ->distinct()->count('Kode_Karyawan');

        $dauYesterday = (int) DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->whereBetween('Created_At', [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ])
            ->distinct()->count('Kode_Karyawan');

        $mau = (int) DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->where('Created_At', '>=', $now->copy()->subDays(30))
            ->distinct()->count('Kode_Karyawan');

        $activeNow = (int) DB::connection($conn)->table('N_HRIS_Activity_Session')
            ->whereNull('Logout_At')
            ->where('Last_Activity_At', '>=', $now->copy()->subMinutes(15))
            ->distinct()->count('Kode_Karyawan');

        $failed24h = (int) DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->where('Event_Type', 'auth.failed')
            ->where('Created_At', '>=', $now->copy()->subDay())
            ->count();

        // Avg session menit (30 hari, hanya yang sudah logout)
        $avgRow = DB::connection($conn)->table('N_HRIS_Activity_Session')
            ->whereNotNull('Logout_At')
            ->where('Login_At', '>=', $now->copy()->subDays(30))
            ->selectRaw('AVG(CAST(Active_Seconds AS FLOAT)) as avg_seconds')
            ->first();
        $avgMinutes = $avgRow && $avgRow->avg_seconds
            ? (int) round(((float) $avgRow->avg_seconds) / 60)
            : 0;

        $totalEvents24h = (int) DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->where('Created_At', '>=', $now->copy()->subDay())
            ->count();

        return [
            'dau' => $dau,
            'dau_delta' => $dau - $dauYesterday,
            'mau' => $mau,
            'active_now' => $activeNow,
            'failed_24h' => $failed24h,
            'avg_session_min' => $avgMinutes,
            'total_events_24h' => $totalEvents24h,
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /* =========================================================
     * Heatmap jam × hari (7/30 hari)
     * ========================================================= */
    public function heatmap(Request $request)
    {
        $days = max(1, min(90, (int) $request->input('days', 7)));

        $key = "activity:admin:heatmap:{$days}";
        $rows = Cache::remember($key, 300, function () use ($days) {
            $conn = $this->conn();

            return DB::connection($conn)->table('N_HRIS_Activity_Event')
                ->where('Created_At', '>=', now()->subDays($days))
                ->selectRaw('DATEPART(WEEKDAY, Created_At) as dow, DATEPART(HOUR, Created_At) as hr, COUNT(*) as Hits')
                ->groupByRaw('DATEPART(WEEKDAY, Created_At), DATEPART(HOUR, Created_At)')
                ->get()
                ->map(fn ($r) => [
                    'dow' => (int) $r->dow,   // 1..7 (SQL Server DATEFIRST)
                    'hour' => (int) $r->hr,    // 0..23
                    'hits' => (int) $r->Hits,
                ])
                ->all();
        });

        return ResponseHelper::success($rows, 'OK');
    }

    /* =========================================================
     * Top module ranking (admin view)
     * ========================================================= */
    public function topModules(Request $request)
    {
        $days = max(1, min(90, (int) $request->input('days', 7)));
        $conn = $this->conn();

        $rows = DB::connection($conn)->table('N_HRIS_Activity_Event as e')
            ->join('N_HRIS_Master_Grup_Fitur as mgf', 'mgf.Id_Grup_Fitur', '=', 'e.Id_Grup_Fitur')
            ->where('e.Created_At', '>=', now()->subDays($days))
            ->groupBy('e.Id_Grup_Fitur', 'mgf.Kode_Grup_Fitur', 'mgf.Nama_Grup_Fitur')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(20)
            ->get([
                'e.Id_Grup_Fitur',
                'mgf.Kode_Grup_Fitur',
                'mgf.Nama_Grup_Fitur',
                DB::raw('COUNT(*) as Hits'),
                DB::raw('COUNT(DISTINCT e.Kode_Karyawan) as Users'),
            ]);

        return ResponseHelper::success($rows, 'OK');
    }

    /* =========================================================
     * Tabel utama: list user dengan ringkasan aktivitas
     * server-side pagination + filter
     * ========================================================= */
    public function users(Request $request)
    {
        $params = $request->validate([
            'q' => 'nullable|string|max:120',
            'module_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:200',
            'sort' => 'nullable|in:last_activity,total_events,total_login,kode',
            'dir' => 'nullable|in:asc,desc',
        ]);

        $from = isset($params['from'])
            ? Carbon::parse($params['from'])->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();
        $to = isset($params['to'])
            ? Carbon::parse($params['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(200, max(10, (int) ($params['per_page'] ?? 25)));
        $sort = $params['sort'] ?? 'last_activity';
        $dir = $params['dir'] ?? 'desc';

        $conn = $this->conn();

        // Subquery aggregate: 1 baris per Kode_Karyawan dari event range
        $sub = DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->select([
                'Kode_Karyawan',
                DB::raw('MAX(UserID_Web) as UserID_Web'),
                DB::raw('COUNT(*) as Total_Events'),
                DB::raw("SUM(CASE WHEN Event_Type = 'auth.login' THEN 1 ELSE 0 END) as Total_Login"),
                DB::raw("SUM(CASE WHEN Event_Type = 'auth.failed' THEN 1 ELSE 0 END) as Total_Failed"),
                DB::raw('MAX(Created_At) as Last_Activity_At'),
                DB::raw('COUNT(DISTINCT Ip_Address) as Distinct_Ips'),
                DB::raw('COUNT(DISTINCT Id_Grup_Fitur) as Distinct_Modules'),
            ])
            ->whereNotNull('Kode_Karyawan')
            ->whereBetween('Created_At', [$from, $to]);

        if (! empty($params['module_id'])) {
            $sub->where('Id_Grup_Fitur', (int) $params['module_id']);
        }

        $sub->groupBy('Kode_Karyawan');

        $base = DB::connection($conn)->query()
            ->fromSub($sub, 'agg')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'agg.Kode_Karyawan');

        if (! empty($params['q'])) {
            $q = '%'.$params['q'].'%';
            $base->where(function ($w) use ($q) {
                $w->where('agg.Kode_Karyawan', 'like', $q)
                    ->orWhere('k.Nama', 'like', $q);
            });
        }

        $total = (clone $base)->count();

        $sortCol = match ($sort) {
            'total_events' => 'agg.Total_Events',
            'total_login' => 'agg.Total_Login',
            'kode' => 'agg.Kode_Karyawan',
            default => 'agg.Last_Activity_At',
        };

        $rows = $base
            ->orderBy($sortCol, $dir)
            ->forPage($page, $perPage)
            ->get([
                'agg.Kode_Karyawan',
                'agg.UserID_Web',
                'agg.Total_Events',
                'agg.Total_Login',
                'agg.Total_Failed',
                'agg.Last_Activity_At',
                'agg.Distinct_Ips',
                'agg.Distinct_Modules',
                DB::raw('k.Nama as Nama_Karyawan'),
            ]);

        // Top module per user (efisien: 1 query batch)
        $kodes = $rows->pluck('Kode_Karyawan')->all();
        $topPerUser = [];
        if (! empty($kodes)) {
            $topPerUser = DB::connection($conn)
                ->table('N_HRIS_Activity_Event as e')
                ->leftJoin('N_HRIS_Master_Grup_Fitur as mgf', 'mgf.Id_Grup_Fitur', '=', 'e.Id_Grup_Fitur')
                ->whereIn('e.Kode_Karyawan', $kodes)
                ->whereBetween('e.Created_At', [$from, $to])
                ->whereNotNull('e.Id_Grup_Fitur')
                ->groupBy('e.Kode_Karyawan', 'e.Id_Grup_Fitur', 'mgf.Nama_Grup_Fitur', 'mgf.Kode_Grup_Fitur')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->get([
                    'e.Kode_Karyawan',
                    'mgf.Kode_Grup_Fitur',
                    'mgf.Nama_Grup_Fitur',
                    DB::raw('COUNT(*) as Hits'),
                ])
                ->groupBy('Kode_Karyawan')
                ->map(fn ($g) => $g->first())
                ->all();
        }

        $rows->transform(function ($r) use ($topPerUser) {
            $top = $topPerUser[$r->Kode_Karyawan] ?? null;
            $r->Top_Module_Name = $top->Nama_Grup_Fitur ?? $top->Kode_Grup_Fitur ?? null;

            return $r;
        });

        return ResponseHelper::successWithPagination($rows, $page, $perPage, $total, 'OK');
    }

    /* =========================================================
     * Drill-down per user (admin lihat data lengkap, IP penuh)
     * ========================================================= */
    public function userDetail(Request $request, string $kode)
    {
        $kode = trim($kode);
        $conn = $this->conn();

        $karyawan = DB::connection($conn)->table('Karyawan')
            ->where('Kode_Karyawan', $kode)
            ->selectRaw('Kode_Karyawan, Nama as Nama_Karyawan, UserID_Web')
            ->first();

        if (! $karyawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        $sessions = DB::connection($conn)->table('N_HRIS_Activity_Session')
            ->where('Kode_Karyawan', $kode)
            ->orderByDesc('Login_At')
            ->limit(50)
            ->get([
                'Session_Id', 'Login_At', 'Last_Activity_At', 'Logout_At', 'Logout_Type',
                'Login_Method', 'Login_Status', 'Ip_Address', 'Ip_Country', 'Ip_City',
                'Browser', 'Browser_Version', 'Os', 'Os_Version', 'Device_Type',
                'Flag_Mobile', 'Device_Fingerprint',
                'Total_Events', 'Total_Pageviews', 'Active_Seconds',
                'Risk_Score', 'Risk_Flags',
            ]);

        $events = DB::connection($conn)->table('N_HRIS_Activity_Event as e')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as mgf', 'mgf.Id_Grup_Fitur', '=', 'e.Id_Grup_Fitur')
            ->leftJoin('N_HRIS_Menus as m', 'm.Id_Menu', '=', 'e.Id_Menu')
            ->where('e.Kode_Karyawan', $kode)
            ->where('e.Created_At', '>=', now()->subDays(7))
            ->orderByDesc('e.Created_At')
            ->limit(500)
            ->get([
                'e.Event_Id', 'e.Created_At', 'e.Event_Type', 'e.Action_Code',
                'e.Description', 'e.Path', 'e.Method', 'e.Http_Status', 'e.Duration_Ms',
                'e.Ip_Address', 'e.Session_Id',
                'mgf.Kode_Grup_Fitur as Module_Code',
                'mgf.Nama_Grup_Fitur as Module_Name',
                'm.Jenis_page       as Menu_Code',
            ]);

        $devices = DB::connection($conn)->table('N_HRIS_Activity_Known_Device')
            ->where('Kode_Karyawan', $kode)
            ->orderByDesc('Last_Seen_At')
            ->get();

        return ResponseHelper::success([
            'karyawan' => $karyawan,
            'sessions' => $sessions,
            'events' => $events,
            'devices' => $devices,
        ], 'OK');
    }

    /* =========================================================
     * Anomaly feed: failed bursts, new device, new geo, unusual hour
     * ========================================================= */
    public function anomalies(Request $request)
    {
        $conn = $this->conn();
        $window24h = now()->subDay();
        $window1h = now()->subHour();

        // Failed login bursts (>= 5 dalam 1 jam)
        $failedBursts = DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->where('Event_Type', 'auth.failed')
            ->where('Created_At', '>=', $window1h)
            ->select(
                'Kode_Karyawan',
                'Ip_Address',
                DB::raw('COUNT(*) as Fails'),
                DB::raw('MAX(Created_At) as Last_At'),
            )
            ->groupBy('Kode_Karyawan', 'Ip_Address')
            ->having(DB::raw('COUNT(*)'), '>=', 5)
            ->orderByDesc('Fails')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'type' => 'failed_burst',
                'severity' => 'high',
                'title' => "Login gagal beruntun ({$r->Fails}x)",
                'kode_karyawan' => $r->Kode_Karyawan,
                'ip' => $r->Ip_Address,
                'count' => (int) $r->Fails,
                'at' => $r->Last_At,
            ])
            ->all();

        // Forced logout ("ini bukan saya")
        $forced = DB::connection($conn)->table('N_HRIS_Activity_Session')
            ->where('Logout_Type', 'forced')
            ->where('Updated_At', '>=', $window24h)
            ->orderByDesc('Updated_At')
            ->limit(20)
            ->get(['Session_Id', 'Kode_Karyawan', 'Ip_Address', 'Updated_At'])
            ->map(fn ($r) => [
                'type' => 'mark_not_me',
                'severity' => 'high',
                'title' => 'Sesi ditandai "bukan saya"',
                'kode_karyawan' => $r->Kode_Karyawan,
                'ip' => $r->Ip_Address,
                'session_id' => $r->Session_Id,
                'at' => $r->Updated_At,
            ])
            ->all();

        // Permission denied bursts
        $permDenied = DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->where('Event_Type', 'permission_denied')
            ->where('Created_At', '>=', $window24h)
            ->select(
                'Kode_Karyawan',
                DB::raw('COUNT(*) as Cnt'),
                DB::raw('MAX(Created_At) as Last_At'),
            )
            ->groupBy('Kode_Karyawan')
            ->having(DB::raw('COUNT(*)'), '>=', 10)
            ->orderByDesc('Cnt')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'type' => 'permission_denied_burst',
                'severity' => 'medium',
                'title' => "Akses ditolak {$r->Cnt}x (24j)",
                'kode_karyawan' => $r->Kode_Karyawan,
                'count' => (int) $r->Cnt,
                'at' => $r->Last_At,
            ])
            ->all();

        $all = array_merge($failedBursts, $forced, $permDenied);
        usort($all, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return ResponseHelper::success($all, 'OK');
    }

    /* =========================================================
     * Export XLSX: list user (sesuai filter sama dgn users())
     * ========================================================= */
    public function export(Request $request): StreamedResponse
    {
        $params = $request->validate([
            'q' => 'nullable|string|max:120',
            'module_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = isset($params['from'])
            ? Carbon::parse($params['from'])->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();
        $to = isset($params['to'])
            ? Carbon::parse($params['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $conn = $this->conn();

        $sub = DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->select([
                'Kode_Karyawan',
                DB::raw('MAX(UserID_Web) as UserID_Web'),
                DB::raw('COUNT(*) as Total_Events'),
                DB::raw("SUM(CASE WHEN Event_Type = 'auth.login' THEN 1 ELSE 0 END) as Total_Login"),
                DB::raw("SUM(CASE WHEN Event_Type = 'auth.failed' THEN 1 ELSE 0 END) as Total_Failed"),
                DB::raw('MAX(Created_At) as Last_Activity_At'),
                DB::raw('COUNT(DISTINCT Ip_Address) as Distinct_Ips'),
                DB::raw('COUNT(DISTINCT Id_Grup_Fitur) as Distinct_Modules'),
            ])
            ->whereNotNull('Kode_Karyawan')
            ->whereBetween('Created_At', [$from, $to])
            ->groupBy('Kode_Karyawan');

        if (! empty($params['module_id'])) {
            $sub->where('Id_Grup_Fitur', (int) $params['module_id']);
        }

        $rows = DB::connection($conn)->query()
            ->fromSub($sub, 'agg')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'agg.Kode_Karyawan')
            ->when(! empty($params['q']), function ($q) use ($params) {
                $like = '%'.$params['q'].'%';
                $q->where(function ($w) use ($like) {
                    $w->where('agg.Kode_Karyawan', 'like', $like)
                        ->orWhere('k.Nama', 'like', $like);
                });
            })
            ->orderByDesc('agg.Last_Activity_At')
            ->limit(10000)
            ->get([
                'agg.Kode_Karyawan',
                'agg.UserID_Web',
                DB::raw('k.Nama as Nama_Karyawan'),
                'agg.Total_Events',
                'agg.Total_Login',
                'agg.Total_Failed',
                'agg.Last_Activity_At',
                'agg.Distinct_Ips',
                'agg.Distinct_Modules',
            ]);

        // Catat event export sebelum stream (best-effort)
        try {
            $this->tracker->action(
                'activity.export',
                ['type' => 'AdminActivity', 'id' => 'users'],
                'Export Aktivitas User: '.$rows->count().' baris',
            );
            $this->tracker->flush();
        } catch (\Throwable $e) {
            /* Gagal mencatat jejak audit tidak boleh menggagalkan export — tetapi
               ini JEJAK AUDIT: membuangnya tanpa bekas berarti ada export yang
               terjadi dan tidak tercatat di mana pun. Diturunkan ke warning, bukan
               dihilangkan. */
            Log::warning('Jejak audit export aktivitas gagal dicatat', [
                'kelas' => $e::class,
                'pesan' => $e->getMessage(),
            ]);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Aktivitas User');

        $headers = [
            'Kode Karyawan', 'UserID_Web', 'Nama', 'Total Event',
            'Total Login', 'Total Login Gagal', 'Aktivitas Terakhir',
            'IP Berbeda', 'Modul Berbeda',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $data = $rows->map(fn ($r) => [
            $r->Kode_Karyawan,
            $r->UserID_Web,
            $r->Nama_Karyawan,
            (int) $r->Total_Events,
            (int) $r->Total_Login,
            (int) $r->Total_Failed,
            (string) $r->Last_Activity_At,
            (int) $r->Distinct_Ips,
            (int) $r->Distinct_Modules,
        ])->all();

        $sheet->fromArray($data, null, 'A2');
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'aktivitas_user_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* =========================================================
     * DIAGNOSTIK SISTEM (RequestDiagnostics + DB Health Snapshot)
     * Sumber: N_HRIS_Request_Diagnostics & N_HRIS_DB_Health_Snapshot.
     * Aman bila tabel belum dibuat → provisioned=false.
     * ========================================================= */
    public function diagnosticsSummary(Request $request)
    {
        $hours = max(1, min(168, (int) $request->input('hours', 24)));
        $since = now()->subHours($hours);
        $slowMs = (int) config('request-diagnostics.slow_ms', 3000);

        try {
            $base = fn () => DB::table('N_HRIS_Request_Diagnostics')->where('Created_At', '>=', $since);

            $agg = (clone $base())
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN Http_Status >= 500 THEN 1 ELSE 0 END) as err5xx')
                ->selectRaw('SUM(CASE WHEN ISNULL(Db_Query_Count,0) = 0 AND Total_Ms >= ? THEN 1 ELSE 0 END) as conn_problem', [$slowMs])
                ->selectRaw('SUM(CASE WHEN ISNULL(Db_Query_Ms,0) >= ? THEN 1 ELSE 0 END) as slow_query', [$slowMs])
                ->selectRaw('AVG(CAST(Total_Ms AS FLOAT)) as avg_total')
                ->selectRaw('MAX(Total_Ms) as max_total')
                ->selectRaw('AVG(CAST(NULLIF(Conn_Acquire_Ms, 0) AS FLOAT)) as avg_conn')
                ->first();

            // Snapshot blocking terakhir (Layer 3)
            $blocking = 0;
            $lastBlockingAt = null;
            try {
                $blk = DB::table('N_HRIS_DB_Health_Snapshot')
                    ->where('Captured_At', '>=', $since)
                    ->where('Blocking_Session_Id', '<>', 0)
                    ->selectRaw('COUNT(*) as c, MAX(Captured_At) as last_at')
                    ->first();
                $blocking = (int) ($blk->c ?? 0);
                $lastBlockingAt = $blk->last_at ?? null;
            } catch (\Throwable $e) {
                // tabel snapshot belum ada — abaikan
            }

            return ResponseHelper::success([
                'provisioned' => true,
                'hours' => $hours,
                'total' => (int) ($agg->total ?? 0),
                'err_5xx' => (int) ($agg->err5xx ?? 0),
                'conn_problem' => (int) ($agg->conn_problem ?? 0),
                'slow_query' => (int) ($agg->slow_query ?? 0),
                'avg_total_ms' => (int) round((float) ($agg->avg_total ?? 0)),
                'max_total_ms' => (int) ($agg->max_total ?? 0),
                'avg_conn_acquire_ms' => (int) round((float) ($agg->avg_conn ?? 0)),
                'blocking_events' => $blocking,
                'last_blocking_at' => $lastBlockingAt,
                'slow_ms' => $slowMs,
                'generated_at' => now()->toIso8601String(),
            ], 'OK');
        } catch (\Throwable $e) {
            return ResponseHelper::success([
                'provisioned' => false,
                'message' => 'Tabel N_HRIS_Request_Diagnostics belum tersedia. Jalankan DDL 2026_07_02_request_diagnostics.sql.',
            ], 'OK');
        }
    }

    public function diagnosticsRequests(Request $request)
    {
        $params = $request->validate([
            'hours' => 'nullable|integer|min:1|max:168',
            'kelas' => 'nullable|in:all,conn,query,error',
            'q' => 'nullable|string|max:200',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        $hours = max(1, min(168, (int) ($params['hours'] ?? 24)));
        $since = now()->subHours($hours);
        $slowMs = (int) config('request-diagnostics.slow_ms', 3000);
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(200, max(10, (int) ($params['per_page'] ?? 25)));
        $kelas = $params['kelas'] ?? 'all';

        try {
            $base = DB::table('N_HRIS_Request_Diagnostics')->where('Created_At', '>=', $since);

            if (! empty($params['q'])) {
                $like = '%'.$params['q'].'%';
                $base->where(function ($w) use ($like) {
                    $w->where('Path', 'like', $like)
                        ->orWhere('Kode_Karyawan', 'like', $like)
                        ->orWhere('Slowest_Query_Sql', 'like', $like);
                });
            }

            if ($kelas === 'conn') {
                $base->where('Db_Query_Count', 0)->where('Total_Ms', '>=', $slowMs);
            } elseif ($kelas === 'query') {
                $base->where('Db_Query_Ms', '>=', $slowMs);
            } elseif ($kelas === 'error') {
                $base->where('Http_Status', '>=', 500);
            }

            $total = (clone $base)->count();

            $rows = $base->orderByDesc('Created_At')
                ->forPage($page, $perPage)
                ->get([
                    'Id', 'Trace_Id', 'Kode_Karyawan', 'Path', 'Method', 'Http_Status',
                    'Total_Ms', 'Conn_Acquire_Ms', 'Db_Query_Count', 'Db_Query_Ms',
                    'Slowest_Query_Ms', 'Slowest_Query_Sql', 'Created_At',
                ]);

            $rows->transform(function ($r) use ($slowMs) {
                $r->Kelas = $this->classifyDiag($r, $slowMs);

                return $r;
            });

            return ResponseHelper::successWithPagination($rows, $page, $perPage, $total, 'OK');
        } catch (\Throwable $e) {
            /*
             * Kegagalan di sini dulu dipulangkan sebagai halaman KOSONG berlabel
             * 'OK'. Itu bentuk kebisuan yang paling mahal: admin membaca "tidak
             * ada data" padahal kuerinya gagal — misalnya karena sebuah kolom
             * diagnostik belum ada di production. Statusnya 200, jadi jaring
             * pengaman 5xx pun tidak melihatnya.
             *
             * Bentuk tanggapannya sengaja TIDAK diubah supaya layar tidak rusak;
             * yang berubah hanya dua hal yang selama ini hilang: barisnya masuk
             * log lengkap dengan exception-nya, dan kalimatnya berhenti berkata
             * 'OK' untuk sesuatu yang gagal.
             */
            Log::error('Gagal membaca daftar diagnostik permintaan', [
                'kelas' => $e::class,
                'pesan' => $e->getMessage(),
                'berkas' => $e->getFile().':'.$e->getLine(),
                'sql' => $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : null,
            ]);

            return ResponseHelper::successWithPagination(
                collect(),
                $page,
                $perPage,
                0,
                'Gagal membaca diagnostik permintaan.',
            );
        }
    }

    public function dbHealth(Request $request)
    {
        $hours = max(1, min(72, (int) $request->input('hours', 6)));
        $since = now()->subHours($hours);

        try {
            $rows = DB::table('N_HRIS_DB_Health_Snapshot')
                ->where('Captured_At', '>=', $since)
                ->orderByDesc('Captured_At')
                ->limit(300)
                ->get([
                    'Id', 'Captured_At', 'Session_Id', 'Blocking_Session_Id', 'Status',
                    'Wait_Type', 'Wait_Time_Ms', 'Cpu_Ms', 'Login_Name', 'Host_Name',
                    'Program_Name', 'Context_Info', 'Sql_Text',
                ]);

            return ResponseHelper::success([
                'provisioned' => true,
                'rows' => $rows,
            ], 'OK');
        } catch (\Throwable $e) {
            return ResponseHelper::success([
                'provisioned' => false,
                'rows' => [],
                'message' => 'Tabel N_HRIS_DB_Health_Snapshot belum tersedia / command db:health-snapshot belum aktif.',
            ], 'OK');
        }
    }

    /**
     * Keadaan kunci & permintaan aktif SAAT INI — HANYA di lingkungan pengembangan.
     *
     * Berbeda dari dbHealth() yang membaca tabel rekaman, ini membaca DMV
     * langsung. Karena itu ia digerbangi tiga kunci (lihat
     * GerbangDiagnostikRealtime): di produksi, tiap sampel memakai satu koneksi
     * dari pool yang sama yang sedang habis — ia akan menambah beban tepat pada
     * saat sistemnya paling rapuh.
     *
     * Saat tertutup, yang dipulangkan bukan galat melainkan SEBABNYA, supaya
     * layar bisa mengatakan kenapa ia diam. Pemantau yang diam tanpa sebab
     * menghabiskan waktu orang yang mengira ia sedang bekerja.
     */
    public function diagnosticsLive(
        Request $request,
        GerbangDiagnostikRealtime $gerbang,
        PembacaKunciLangsung $pembaca,
    ) {
        $tertutup = $gerbang->alasanTertutup();

        if ($tertutup !== null) {
            return ResponseHelper::success([
                'aktif' => false,
                'alasan' => $tertutup,
            ], 'Pemantauan realtime tidak aktif.');
        }

        try {
            return ResponseHelper::success(
                ['aktif' => true] + $pembaca->sampel(),
                'OK',
            );
        } catch (\Throwable $e) {
            // Basis data tak terjangkau saat memantau basis data adalah jawaban
            // tersendiri, bukan kegagalan yang perlu disembunyikan.
            return ResponseHelper::success([
                'aktif' => false,
                'alasan' => 'Gagal membaca DMV: '.$e->getMessage(),
            ], 'Pemantauan realtime gagal membaca.');
        }
    }

    /**
     * Peringkat biaya SETIAP kueri — HANYA lingkungan pengembangan.
     *
     * Pelengkap diagnosticsLive(): potret hanya melihat yang sedang berjalan,
     * sehingga kueri cepat-tapi-sering tak pernah tertangkap. Padahal justru
     * gabungan itulah yang menghabiskan CPU dan koneksi.
     */
    public function diagnosticsQueries(
        Request $request,
        GerbangDiagnostikRealtime $gerbang,
        PembacaKueriTerberat $pembaca,
    ) {
        $tertutup = $gerbang->alasanTertutup();
        if ($tertutup !== null) {
            return ResponseHelper::success(['aktif' => false, 'alasan' => $tertutup], 'Tidak aktif.');
        }

        try {
            // Aksi penanda ikut lewat endpoint yang sama supaya gerbangnya
            // hanya perlu dipasang di satu tempat.
            $aksi = (string) $request->input('aksi', '');
            if ($aksi === 'tandai') {
                $pembaca->tandaiDasar();
            } elseif ($aksi === 'hapus') {
                $pembaca->hapusDasar();
            }

            return ResponseHelper::success(
                ['aktif' => true] + $pembaca->ambil(
                    (string) $request->input('urut', 'cpu'),
                    (int) $request->input('limit', 25),
                ),
                'OK',
            );
        } catch (\Throwable $e) {
            return ResponseHelper::success([
                'aktif' => false,
                'alasan' => 'Gagal membaca statistik kueri: '.$e->getMessage(),
            ], 'Gagal membaca.');
        }
    }

    /**
     * Rekaman kueri per RUTE selama satu sesi uji — HANYA lingkungan pengembangan.
     *
     * Menjawab yang tak bisa dijawab dm_exec_query_stats: endpoint mana yang
     * menjalankan kueri itu. Atribusi hanya ada di sisi aplikasi.
     */
    public function diagnosticsRecording(
        Request $request,
        GerbangDiagnostikRealtime $gerbang,
        PerekamKueriPerRute $perekam,
    ) {
        $tertutup = $gerbang->alasanTertutup();
        if ($tertutup !== null) {
            return ResponseHelper::success(['aktif' => false, 'alasan' => $tertutup], 'Tidak aktif.');
        }

        $aksi = (string) $request->input('aksi', '');
        if ($aksi === 'mulai') {
            $perekam->mulai();
        } elseif ($aksi === 'berhenti') {
            $perekam->berhenti();
        } elseif ($aksi === 'hapus') {
            $sesi = $perekam->sesiAktif();
            if ($sesi !== null) {
                $perekam->hapus($sesi);
            }
        }

        return ResponseHelper::success(
            ['aktif' => true, 'merekam' => $perekam->sesiAktif() !== null]
            + $perekam->laporan(),
            'OK',
        );
    }

    /**
     * Klasifikasi baris diagnostik: conn (gagal ambil koneksi) vs query (lambat/lock)
     * vs error (5xx lain).
     */
    protected function classifyDiag($r, int $slowMs): string
    {
        if ((int) ($r->Db_Query_Count ?? 0) === 0 && (int) ($r->Total_Ms ?? 0) >= $slowMs) {
            return 'conn';
        }
        if ((int) ($r->Db_Query_Ms ?? 0) >= $slowMs) {
            return 'query';
        }
        if ((int) ($r->Http_Status ?? 0) >= 500) {
            return 'error';
        }

        return 'other';
    }

    /* =========================================================
     * Helpers
     * ========================================================= */
    protected function conn(): string
    {
        return (string) config('activity-tracker.db_connection', config('database.default'));
    }

    protected function moduleOptions(): array
    {
        return DB::connection($this->conn())->table('N_HRIS_Master_Grup_Fitur')
            ->orderBy('Kode_Grup_Fitur')
            ->get(['Id_Grup_Fitur', 'Kode_Grup_Fitur', 'Nama_Grup_Fitur'])
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Grup_Fitur,
                'kode' => $r->Kode_Grup_Fitur,
                'nama' => $r->Nama_Grup_Fitur ?? $r->Kode_Grup_Fitur,
            ])
            ->all();
    }
}
