<?php

namespace App\Services\SYSTEM\Audit\Activity\Tracker;

use App\Jobs\SYSTEM\Audit\Activity\FlushActivityEventsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ActivityTracker
{
    protected array $buffer = [];

    protected ?string $currentSessionId = null;

    protected ?int $lastFlushTs = null;

    public function __construct(
        protected IdentityResolver $identity,
        protected RouteMapResolver $routeMap,
        protected UserAgentParser $uaParser,
        protected PayloadSanitizer $sanitizer,
    ) {}

    /* =========================================================
     * SESSION lifecycle (dipanggil oleh listener Login/Logout)
     * ========================================================= */

    /**
     * Insert sesi login baru. Return Session_Id.
     */
    public function startSession(Request $request, array $extra = []): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $ident = $this->identity->resolve();
        if (! $ident['kode_karyawan'] && ! $ident['userid_web']) {
            return null;
        }

        $sessionId = (string) Str::uuid();
        $ua = $this->uaParser->parse($request->header('User-Agent'));
        $ip = $request->ip();
        $now = now();

        $row = [
            'Session_Id' => $sessionId,
            'Kode_Karyawan' => $ident['kode_karyawan'],
            'UserID_Web' => $ident['userid_web'],
            'Username' => $ident['username'],
            'Login_At' => $now,
            'Last_Activity_At' => $now,
            'Logout_At' => null,
            'Logout_Type' => null,
            'Login_Method' => $extra['login_method'] ?? 'password',
            'Login_Status' => $extra['login_status'] ?? 'success',
            'Flag_Mfa' => ! empty($extra['mfa_used']) ? 'Y' : 'T',

            'Ip_Address' => $ip,
            'Ip_Country' => null,
            'Ip_City' => null,
            'Ip_Isp' => null,
            'Geo_Source' => null,
            'Latitude' => null,
            'Longitude' => null,

            'User_Agent_Raw' => $ua['raw'],
            'Browser' => $ua['browser'],
            'Browser_Version' => $ua['browser_version'],
            'Engine' => $ua['engine'],
            'Os' => $ua['os'],
            'Os_Version' => $ua['os_version'],
            'Device_Type' => $ua['device_type'],
            'Flag_Mobile' => $ua['is_mobile'] ? 'Y' : 'T',
            'Flag_Bot' => $ua['is_bot'] ? 'Y' : 'T',

            'Device_Fingerprint' => $extra['fp'] ?? null,
            'Visitor_Id' => $extra['visitor_id'] ?? null,
            'Device_Id' => $extra['device_id'] ?? null,

            'Screen_W' => null,
            'Screen_H' => null,
            'Timezone' => null,
            'Language' => substr((string) $request->header('Accept-Language', ''), 0, 16) ?: null,
            'Platform' => $extra['platform'] ?? 'web',
            'App_Version' => $extra['app_version'] ?? null,
            'Build_Hash' => null,

            'Risk_Score' => null,
            'Risk_Flags' => null,

            'Total_Events' => 0,
            'Total_Pageviews' => 0,
            'Active_Seconds' => 0,

            'Trace_Id' => $request->header('X-Trace-Id'),
            'Notes' => null,
            'Created_At' => $now,
            'Updated_At' => $now,
        ];

        try {
            DB::connection($this->connection())
                ->table('N_HRIS_Activity_Session')
                ->insert($row);
        } catch (\Throwable $e) {
            Log::warning('ActivityTracker startSession failed', ['error' => $e->getMessage()]);

            return null;
        }

        $this->currentSessionId = $sessionId;
        $this->putSessionCookie($sessionId);

        // Catat event login langsung (BUKAN via queue) — event kritis,
        // harus pasti masuk meski queue worker belum jalan / Cloud Tasks
        // belum terkonfigurasi.
        $this->insertAuthEventNow($request, 'auth.login', 'login', 'Login berhasil', $ident);

        return $sessionId;
    }

    public function endSession(string $type = 'manual'): void
    {
        if (! $this->enabled()) {
            return;
        }

        $sessionId = $this->resolveSessionId();
        if (! $sessionId) {
            return;
        }

        try {
            DB::connection($this->connection())
                ->table('N_HRIS_Activity_Session')
                ->where('Session_Id', $sessionId)
                ->whereNull('Logout_At')
                ->update([
                    'Logout_At' => now(),
                    'Logout_Type' => $type,
                    'Updated_At' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('ActivityTracker endSession failed', ['error' => $e->getMessage()]);
        }

        // Catat event logout langsung (BUKAN via queue) — sama alasannya
        // dengan startSession: kritis, jarang, harus pasti masuk.
        $this->insertAuthEventNow(
            request(),
            'auth.logout',
            $type === 'forced' ? 'logout.forced' : 'logout',
            $type === 'forced' ? 'Logout paksa' : 'Logout',
        );

        $this->forgetSessionCookie();
        $this->currentSessionId = null;
    }

    /**
     * Insert event auth langsung ke DB (sync), tidak melalui buffer/queue.
     * Dipakai untuk auth.login, auth.logout, auth.failed yang tidak
     * boleh hilang kalau queue worker belum aktif.
     */
    protected function insertAuthEventNow(
        ?Request $request,
        string $eventType,
        string $action,
        ?string $description = null,
        ?array $identOverride = null,
    ): void {
        try {
            $ident = $identOverride ?? $this->identity->resolve();
            $req = $request ?: request();

            DB::connection($this->connection())->table('N_HRIS_Activity_Event')->insert([
                'Session_Id' => $this->resolveSessionId(),
                'Kode_Karyawan' => $ident['kode_karyawan'] ?? null,
                'UserID_Web' => $ident['userid_web'] ?? null,
                'Event_Type' => $eventType,
                'Id_Grup_Fitur' => null,
                'Id_Menu' => null,
                'Action_Code' => $action,
                'Route_Name' => $req ? optional($req->route())->getName() : null,
                'Method' => $req ? strtoupper($req->method()) : null,
                'Path' => $req ? mb_substr('/'.ltrim($req->path(), '/'), 0, 400) : null,
                'Http_Status' => null,
                'Duration_Ms' => null,
                'Resource_Type' => null,
                'Resource_Id' => null,
                'Description' => $description,
                'Payload_Hash' => null,
                'Ip_Address' => $req ? $req->ip() : null,
                'Trace_Id' => $req ? $req->header('X-Trace-Id') : null,
                'Span_Id' => null,
                'Created_At' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('insertAuthEventNow failed', [
                'event' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /* =========================================================
     * EVENT buffering
     * ========================================================= */

    public function pushEvent(?Request $request, array $data): void
    {
        if (! $this->enabled()) {
            return;
        }

        $request ??= request();
        if (! $request) {
            return;
        }

        if ($this->shouldExclude($request)) {
            return;
        }

        // Dedup naïf: event identik (type+path) dalam N detik
        $dedupSec = (int) (config('activity-tracker.sampling.dedup_seconds') ?: 0);
        $signature = ($data['event_type'] ?? '').'|'.strtolower($request->path()).'|'.($data['action'] ?? '');
        if ($dedupSec > 0 && ! empty($this->buffer)) {
            foreach (array_reverse($this->buffer) as $existing) {
                if (($existing['_sig'] ?? null) === $signature
                    && (time() - ($existing['_ts'] ?? 0)) <= $dedupSec) {
                    return;
                }
            }
        }

        $ident = $this->identity->resolve();
        $mapping = $this->routeMap->resolve($request);

        $row = [
            'Session_Id' => $this->resolveSessionId(),
            'Kode_Karyawan' => $ident['kode_karyawan'],
            'UserID_Web' => $ident['userid_web'],
            'Event_Type' => $data['event_type'] ?? 'pageview',
            'Id_Grup_Fitur' => $data['id_grup_fitur'] ?? $mapping['id_grup_fitur'],
            'Id_Menu' => $data['id_menu'] ?? $mapping['id_menu'],
            'Action_Code' => $data['action'] ?? $mapping['default_action']
                                                      ?? $this->defaultActionFromMethod($request->method()),
            'Route_Name' => optional($request->route())->getName(),
            'Method' => strtoupper($request->method()),
            'Path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 400),
            'Http_Status' => $data['http_status'] ?? null,
            'Duration_Ms' => $data['duration_ms'] ?? null,
            'Resource_Type' => $data['resource_type'] ?? null,
            'Resource_Id' => $data['resource_id'] ?? null,
            'Description' => isset($data['description'])
                                ? mb_substr((string) $data['description'], 0, 400)
                                : null,
            'Payload_Hash' => $data['payload_hash'] ?? null,
            'Ip_Address' => $request->ip(),
            'Trace_Id' => $request->header('X-Trace-Id'),
            'Span_Id' => $data['span_id'] ?? null,
            'Created_At' => now(),
        ];

        $row['_sig'] = $signature;
        $row['_ts'] = time();

        $this->buffer[] = $row;
    }

    /**
     * Helper bagi controller/service:
     *   ActivityTracker::action('cuti.approve',
     *       resource: ['type' => 'Cuti', 'id' => $id],
     *       description: "Approve cuti $kode");
     */
    public function action(string $code, array $resource = [], ?string $description = null, array $extra = []): void
    {
        $this->pushEvent(request(), array_merge([
            'event_type' => 'action',
            'action' => $code,
            'resource_type' => $resource['type'] ?? null,
            'resource_id' => $resource['id'] ?? null,
            'description' => $description,
        ], $extra));
    }

    /**
     * Dipanggil dari middleware->terminate(): kirim buffer ke queue.
     */
    public function flush(): void
    {
        if (! $this->enabled() || empty($this->buffer)) {
            return;
        }

        $events = array_map(function (array $row) {
            unset($row['_sig'], $row['_ts']);
            // datetime jadi string supaya aman serialize
            if ($row['Created_At'] instanceof \DateTimeInterface) {
                $row['Created_At'] = $row['Created_At']->format('Y-m-d H:i:s.v');
            }

            return $row;
        }, $this->buffer);

        $sessionId = $this->resolveSessionId();
        $this->buffer = [];

        try {
            $job = (new FlushActivityEventsJob($events, $sessionId))
                ->onConnection((string) config('activity-tracker.queue.connection'))
                ->onQueue((string) config('activity-tracker.queue.name'));
            dispatch($job);
        } catch (\Throwable $e) {
            Log::warning('ActivityTracker dispatch failed', ['error' => $e->getMessage()]);
        }
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    public function getSanitizer(): PayloadSanitizer
    {
        return $this->sanitizer;
    }

    public function getRouteMap(): RouteMapResolver
    {
        return $this->routeMap;
    }

    public function getIdentity(): IdentityResolver
    {
        return $this->identity;
    }

    public function enabled(): bool
    {
        return (bool) config('activity-tracker.enabled', true);
    }

    public function connection(): string
    {
        return (string) config('activity-tracker.db_connection', config('database.default'));
    }

    public function resolveSessionId(): ?string
    {
        if ($this->currentSessionId) {
            return $this->currentSessionId;
        }
        $cookieName = (string) config('activity-tracker.session_id_cookie', '_act_sid');
        $req = request();
        if ($req && $req->hasCookie($cookieName)) {
            $cookieVal = $req->cookie($cookieName);
            if ($cookieVal) {
                $this->currentSessionId = (string) $cookieVal;

                return $this->currentSessionId;
            }
        }

        return null;
    }

    public function shouldExclude(Request $request): bool
    {
        $path = strtolower(ltrim($request->path(), '/'));
        foreach ((array) config('activity-tracker.exclude_paths', []) as $pattern) {
            if (Str::is($pattern, $path) || Str::is($pattern, '/'.$path)) {
                return true;
            }
        }
        if (strtoupper($request->method()) === 'OPTIONS') {
            return true;
        }

        return false;
    }

    protected function defaultActionFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'other',
        };
    }

    protected function putSessionCookie(string $sessionId): void
    {
        $cookieName = (string) config('activity-tracker.session_id_cookie', '_act_sid');
        cookie()->queue(cookie(
            $cookieName,
            $sessionId,
            (int) config('session.lifetime', 120),
            '/',
            null,
            (bool) config('session.secure', false),
            true,
            false,
            'lax',
        ));
    }

    protected function forgetSessionCookie(): void
    {
        $cookieName = (string) config('activity-tracker.session_id_cookie', '_act_sid');
        cookie()->queue(cookie()->forget($cookieName));
    }
}
