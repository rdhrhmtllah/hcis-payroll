<?php

namespace App\Http\Controllers\SYSTEM\Audit\Activity;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use App\Services\SYSTEM\Audit\Activity\Tracker\IpMasker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MyActivityController extends Controller
{
    public function __construct(
        protected ActivityTracker $tracker,
        protected IpMasker $ipMasker,
    ) {}

    /**
     * GET /my/activity — halaman Inertia (user view).
     */
    public function index(Request $request)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            abort(403, 'Karyawan tidak ditemukan');
        }

        $summary = $this->buildSummary($ident['kode_karyawan']);

        return Inertia::render('SYSTEM/Audit/Activity/MyActivity', [
            'summary' => $summary,
            'mask_ip' => (bool) config('activity-tracker.ip_mask_for_users', true),
            'gps_optin_key' => '_act_gps_optin',
        ]);
    }

    /**
     * GET /my/activity/timeline — JSON paginated event list.
     */
    public function timeline(Request $request)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $params = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'integer',
            'event_types' => 'nullable|array',
            'event_types.*' => 'string|max:30',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $from = isset($params['from'])
            ? Carbon::parse($params['from'])->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();
        $to = isset($params['to'])
            ? Carbon::parse($params['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($params['per_page'] ?? 50)));

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $base = DB::connection($conn)->table('N_HRIS_Activity_Event as e')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as mgf', 'mgf.Id_Grup_Fitur', '=', 'e.Id_Grup_Fitur')
            ->leftJoin('N_HRIS_Menus as m', 'm.Id_Menu', '=', 'e.Id_Menu')
            ->where('e.Kode_Karyawan', $ident['kode_karyawan'])
            ->whereBetween('e.Created_At', [$from, $to]);

        if (! empty($params['module_ids'])) {
            $base->whereIn('e.Id_Grup_Fitur', $params['module_ids']);
        }
        if (! empty($params['event_types'])) {
            $base->whereIn('e.Event_Type', $params['event_types']);
        }

        $total = (clone $base)->count();

        $rows = $base
            ->select([
                'e.Event_Id',
                'e.Created_At',
                'e.Event_Type',
                'e.Action_Code',
                'e.Description',
                'e.Path',
                'e.Method',
                'e.Http_Status',
                'e.Duration_Ms',
                'e.Session_Id',
                'e.Id_Grup_Fitur',
                'e.Id_Menu',
                'mgf.Kode_Grup_Fitur as Module_Code',
                'mgf.Nama_Grup_Fitur as Module_Name',
                'm.Jenis_page       as Menu_Code',
            ])
            ->orderByDesc('e.Created_At')
            ->forPage($page, $perPage)
            ->get();

        return ResponseHelper::successWithPagination(
            $rows,
            $page,
            $perPage,
            $total,
            'OK',
        );
    }

    /**
     * GET /my/activity/events/{eventId} — detail lengkap satu event
     * (lazy-load saat accordion timeline diklik).
     */
    public function eventDetail(Request $request, int $eventId)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $event = DB::connection($conn)->table('N_HRIS_Activity_Event as e')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as mgf', 'mgf.Id_Grup_Fitur', '=', 'e.Id_Grup_Fitur')
            ->leftJoin('N_HRIS_Menus as m', 'm.Id_Menu', '=', 'e.Id_Menu')
            ->where('e.Event_Id', $eventId)
            ->where('e.Kode_Karyawan', $ident['kode_karyawan'])
            ->first([
                'e.Event_Id', 'e.Session_Id', 'e.Event_Type', 'e.Action_Code',
                'e.Description', 'e.Route_Name', 'e.Method', 'e.Path',
                'e.Http_Status', 'e.Duration_Ms', 'e.Resource_Type', 'e.Resource_Id',
                'e.Payload_Hash', 'e.Ip_Address', 'e.Trace_Id', 'e.Span_Id', 'e.Created_At',
                'e.Id_Grup_Fitur', 'e.Id_Menu',
                'mgf.Kode_Grup_Fitur as Module_Code',
                'mgf.Nama_Grup_Fitur as Module_Name',
                'm.Jenis_page       as Menu_Code',
            ]);

        if (! $event) {
            return ResponseHelper::error('Event tidak ditemukan', 404);
        }

        $mask = (bool) config('activity-tracker.ip_mask_for_users', true);
        $event->Ip_Address = $this->ipMasker->mask($event->Ip_Address, ! $mask);

        // Konteks sesi (perangkat, lokasi) tempat event ini terjadi
        $session = null;
        if ($event->Session_Id) {
            $session = DB::connection($conn)->table('N_HRIS_Activity_Session')
                ->where('Session_Id', $event->Session_Id)
                ->where('Kode_Karyawan', $ident['kode_karyawan'])
                ->first([
                    'Session_Id', 'Login_At', 'Logout_At', 'Logout_Type',
                    'Login_Method', 'Browser', 'Browser_Version', 'Engine',
                    'Os', 'Os_Version', 'Device_Type', 'Flag_Mobile',
                    'Ip_Address', 'Ip_City', 'Ip_Country', 'Ip_Isp',
                    'Geo_Source', 'Latitude', 'Longitude', 'Device_Fingerprint',
                    'Timezone', 'Language', 'Platform', 'App_Version',
                ]);
            if ($session) {
                $session->Ip_Address = $this->ipMasker->mask($session->Ip_Address, ! $mask);
            }
        }

        return ResponseHelper::success([
            'event' => $event,
            'session' => $session,
        ], 'OK');
    }

    /**
     * GET /my/activity/sessions — kartu sesi login.
     */
    public function sessions(Request $request)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $days = (int) $request->input('days', 30);
        $days = max(1, min(180, $days));

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $rows = DB::connection($conn)->table('N_HRIS_Activity_Session')
            ->where('Kode_Karyawan', $ident['kode_karyawan'])
            ->where('Login_At', '>=', now()->subDays($days))
            ->orderByDesc('Login_At')
            ->limit(200)
            ->get([
                'Session_Id', 'Login_At', 'Last_Activity_At', 'Logout_At', 'Logout_Type',
                'Login_Method', 'Login_Status', 'Ip_Address', 'Ip_Country', 'Ip_City',
                'Browser', 'Browser_Version', 'Os', 'Os_Version', 'Device_Type',
                'Flag_Mobile', 'Device_Fingerprint',
                'Total_Events', 'Total_Pageviews', 'Active_Seconds',
                'Risk_Score', 'Risk_Flags',
            ]);

        $mask = (bool) config('activity-tracker.ip_mask_for_users', true);

        $rows->transform(function ($r) use ($mask) {
            $r->Ip_Address = $this->ipMasker->mask($r->Ip_Address, ! $mask);

            return $r;
        });

        return ResponseHelper::success($rows, 'OK');
    }

    /**
     * GET /my/activity/sessions/{sessionId} — detail event sesi tertentu.
     */
    public function sessionDetail(Request $request, string $sessionId)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $session = DB::connection($conn)->table('N_HRIS_Activity_Session')
            ->where('Session_Id', $sessionId)
            ->where('Kode_Karyawan', $ident['kode_karyawan'])
            ->first();

        if (! $session) {
            return ResponseHelper::error('Sesi tidak ditemukan', 404);
        }

        $mask = (bool) config('activity-tracker.ip_mask_for_users', true);
        $session->Ip_Address = $this->ipMasker->mask($session->Ip_Address, ! $mask);

        $events = DB::connection($conn)->table('N_HRIS_Activity_Event as e')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as mgf', 'mgf.Id_Grup_Fitur', '=', 'e.Id_Grup_Fitur')
            ->leftJoin('N_HRIS_Menus as m', 'm.Id_Menu', '=', 'e.Id_Menu')
            ->where('e.Session_Id', $sessionId)
            ->orderByDesc('e.Created_At')
            ->limit(500)
            ->get([
                'e.Event_Id', 'e.Created_At', 'e.Event_Type', 'e.Action_Code',
                'e.Description', 'e.Path', 'e.Method', 'e.Http_Status', 'e.Duration_Ms',
                'mgf.Kode_Grup_Fitur as Module_Code',
                'mgf.Nama_Grup_Fitur as Module_Name',
                'm.Jenis_page       as Menu_Code',
            ]);

        return ResponseHelper::success([
            'session' => $session,
            'events' => $events,
        ], 'OK');
    }

    /**
     * GET /my/activity/devices — perangkat yang pernah dipakai.
     */
    public function devices(Request $request)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $rows = DB::connection($conn)->table('N_HRIS_Activity_Known_Device')
            ->where('Kode_Karyawan', $ident['kode_karyawan'])
            ->orderByDesc('Last_Seen_At')
            ->get();

        return ResponseHelper::success($rows, 'OK');
    }

    /**
     * GET /my/activity/security — feed event security user sendiri.
     */
    public function security(Request $request)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));
        $mask = (bool) config('activity-tracker.ip_mask_for_users', true);

        $events = DB::connection($conn)->table('N_HRIS_Activity_Event')
            ->where('Kode_Karyawan', $ident['kode_karyawan'])
            ->whereIn('Event_Type', ['auth.failed', 'auth.login', 'auth.logout', 'permission_denied'])
            ->where('Created_At', '>=', now()->subDays(30))
            ->orderByDesc('Created_At')
            ->limit(200)
            ->get([
                'Event_Id', 'Created_At', 'Event_Type', 'Description',
                'Ip_Address', 'Action_Code',
            ]);

        $events->transform(function ($r) use ($mask) {
            $r->Ip_Address = $this->ipMasker->mask($r->Ip_Address, ! $mask);

            return $r;
        });

        return ResponseHelper::success($events, 'OK');
    }

    /**
     * POST /my/activity/sessions/{sessionId}/not-me — tandai sesi sbg "bukan saya".
     */
    public function markNotMe(Request $request, string $sessionId)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $affected = DB::connection($conn)->table('N_HRIS_Activity_Session')
            ->where('Session_Id', $sessionId)
            ->where('Kode_Karyawan', $ident['kode_karyawan'])
            ->update([
                'Logout_Type' => 'forced',
                'Logout_At' => DB::raw('COALESCE(Logout_At, SYSUTCDATETIME())'),
                'Notes' => 'Ditandai "bukan saya" oleh user pada '.now()->toDateTimeString(),
                'Updated_At' => now(),
            ]);

        if ($affected === 0) {
            return ResponseHelper::error('Sesi tidak ditemukan', 404);
        }

        $this->tracker->action(
            'security.mark_not_me',
            ['type' => 'ActivitySession', 'id' => $sessionId],
            'Sesi ditandai bukan saya',
        );

        return ResponseHelper::success(null, 'Sesi ditandai sebagai bukan saya');
    }

    /**
     * POST /my/activity/devices/{fingerprint}/label — beri nama perangkat.
     */
    public function labelDevice(Request $request, string $fingerprint)
    {
        $ident = $this->tracker->getIdentity()->resolve();
        if (empty($ident['kode_karyawan'])) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 403);
        }

        $data = $request->validate([
            'label' => 'nullable|string|max:120',
            'trusted' => 'nullable|boolean',
        ]);

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $update = ['Last_Seen_At' => DB::raw('Last_Seen_At')];
        if (array_key_exists('label', $data)) {
            $update['Label'] = $data['label'] ?: null;
        }
        if (array_key_exists('trusted', $data)) {
            $update['Flag_Trusted'] = ! empty($data['trusted']) ? 'Y' : 'T';
        }

        $affected = DB::connection($conn)->table('N_HRIS_Activity_Known_Device')
            ->where('Kode_Karyawan', $ident['kode_karyawan'])
            ->where('Device_Fingerprint', $fingerprint)
            ->update($update);

        if ($affected === 0) {
            return ResponseHelper::error('Perangkat tidak ditemukan', 404);
        }

        return ResponseHelper::success(null, 'Label perangkat diperbarui');
    }

    /* ============================================================ */

    protected function buildSummary(string $kode): array
    {
        $cacheKey = "activity:summary:user:{$kode}";

        return Cache::remember($cacheKey, 60, function () use ($kode) {
            $conn = (string) config('activity-tracker.db_connection', config('database.default'));
            $weekStart = now()->subDays(7);

            $login7d = (int) DB::connection($conn)->table('N_HRIS_Activity_Session')
                ->where('Kode_Karyawan', $kode)
                ->where('Login_At', '>=', $weekStart)
                ->count();

            $sessions = DB::connection($conn)->table('N_HRIS_Activity_Session')
                ->where('Kode_Karyawan', $kode)
                ->where('Login_At', '>=', $weekStart)
                ->get(['Active_Seconds']);
            $activeMinutes = (int) round(($sessions->sum('Active_Seconds')) / 60);

            $topModule = DB::connection($conn)->table('N_HRIS_Activity_Event as e')
                ->leftJoin('N_HRIS_Master_Grup_Fitur as mgf', 'mgf.Id_Grup_Fitur', '=', 'e.Id_Grup_Fitur')
                ->where('e.Kode_Karyawan', $kode)
                ->where('e.Created_At', '>=', $weekStart)
                ->whereNotNull('e.Id_Grup_Fitur')
                ->groupBy('e.Id_Grup_Fitur', 'mgf.Kode_Grup_Fitur', 'mgf.Nama_Grup_Fitur')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(1)
                ->get([
                    'e.Id_Grup_Fitur',
                    'mgf.Kode_Grup_Fitur',
                    'mgf.Nama_Grup_Fitur',
                    DB::raw('COUNT(*) as Hits'),
                ])
                ->first();

            $deviceCount = (int) DB::connection($conn)->table('N_HRIS_Activity_Known_Device')
                ->where('Kode_Karyawan', $kode)
                ->count();

            $failed24h = (int) DB::connection($conn)->table('N_HRIS_Activity_Event')
                ->where('Kode_Karyawan', $kode)
                ->where('Event_Type', 'auth.failed')
                ->where('Created_At', '>=', now()->subDay())
                ->count();

            return [
                'login_7d' => $login7d,
                'active_minutes_7d' => $activeMinutes,
                'top_module' => $topModule,
                'device_count' => $deviceCount,
                'failed_24h' => $failed24h,
            ];
        });
    }
}
