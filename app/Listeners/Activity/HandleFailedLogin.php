<?php

namespace App\Listeners\Activity;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleFailedLogin
{
    public function handle(Failed $event): void
    {
        if (! config('activity-tracker.enabled', true)) {
            return;
        }
        if (! config('activity-tracker.capture.failed_auth', true)) {
            return;
        }

        try {
            $request = request();
            $username = is_array($event->credentials ?? null)
                ? (string) ($event->credentials['username'] ?? $event->credentials['email'] ?? '')
                : '';
            $username = mb_substr($username, 0, 100);

            $conn = (string) config('activity-tracker.db_connection', config('database.default'));

            // Cari Kode_Karyawan kalau username cocok (best-effort)
            $kode = null;
            $userIdWeb = null;
            if ($username !== '') {
                try {
                    $row = DB::connection($conn)->table('KPI_Users')
                        ->where('Username', $username)
                        ->select('Id_Users')
                        ->first();
                    if ($row) {
                        $userIdWeb = (int) $row->Id_Users;
                        $kode = DB::connection($conn)->table('Karyawan')
                            ->where('UserID_Web', $userIdWeb)
                            ->value('Kode_Karyawan');
                        $kode = $kode !== null ? trim((string) $kode) : null;
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }

            DB::connection($conn)->table('N_HRIS_Activity_Event')->insert([
                'Session_Id' => null,
                'Kode_Karyawan' => $kode,
                'UserID_Web' => $userIdWeb,
                'Event_Type' => 'auth.failed',
                'Id_Grup_Fitur' => null,
                'Id_Menu' => null,
                'Action_Code' => 'login.failed',
                'Route_Name' => optional($request?->route())->getName(),
                'Method' => strtoupper((string) ($request?->method() ?? 'POST')),
                'Path' => '/'.ltrim((string) ($request?->path() ?? 'login'), '/'),
                'Http_Status' => 401,
                'Duration_Ms' => null,
                'Resource_Type' => null,
                'Resource_Id' => null,
                'Description' => 'Login gagal'.($username !== '' ? " (user: {$username})" : ''),
                'Payload_Hash' => null,
                'Ip_Address' => $request?->ip(),
                'Trace_Id' => $request?->header('X-Trace-Id'),
                'Span_Id' => null,
                'Created_At' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Activity HandleFailedLogin failed', ['error' => $e->getMessage()]);
        }
    }
}
