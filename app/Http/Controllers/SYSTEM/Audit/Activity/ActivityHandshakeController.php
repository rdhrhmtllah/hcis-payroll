<?php

namespace App\Http\Controllers\SYSTEM\Audit\Activity;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivityHandshakeController extends Controller
{
    public function __construct(protected ActivityTracker $tracker) {}

    /**
     * POST /api/v1/activity/handshake
     * Dipanggil sekali oleh frontend setelah mount layout (Inertia).
     * Update kolom-kolom sesi yang masih null (fingerprint, screen,
     * timezone, GPS opt-in).
     */
    public function handshake(Request $request)
    {
        if (! Auth::check()) {
            return ResponseHelper::error('Unauthenticated', 401);
        }

        $validated = $request->validate([
            'fp' => 'nullable|string|max:64',
            'visitor_id' => 'nullable|string|max:64',
            'device_id' => 'nullable|string|max:64',
            'screen_w' => 'nullable|integer|min:1|max:30000',
            'screen_h' => 'nullable|integer|min:1|max:30000',
            'timezone' => 'nullable|string|max:64',
            'language' => 'nullable|string|max:16',
            'platform' => 'nullable|string|max:20',
            'app_version' => 'nullable|string|max:40',
            'gps' => 'nullable|array',
            'gps.lat' => 'nullable|numeric|between:-90,90',
            'gps.lng' => 'nullable|numeric|between:-180,180',
        ]);

        $sessionId = $this->tracker->resolveSessionId();
        if (! $sessionId) {
            // Handshake bersifat best-effort. Beberapa entry point UAT/legacy
            // membuat autentikasi tanpa lifecycle ActivityTracker; kondisi ini
            // bukan kegagalan halaman dan tidak boleh menghasilkan console 404.
            return ResponseHelper::success(
                ['session_id' => null, 'skipped' => true],
                'Activity session belum tersedia',
            );
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $update = [
            'Updated_At' => now(),
        ];
        if (! empty($validated['fp'])) {
            $update['Device_Fingerprint'] = $validated['fp'];
        }
        if (! empty($validated['visitor_id'])) {
            $update['Visitor_Id'] = $validated['visitor_id'];
        }
        if (! empty($validated['device_id'])) {
            $update['Device_Id'] = $validated['device_id'];
        }
        if (! empty($validated['screen_w'])) {
            $update['Screen_W'] = (int) $validated['screen_w'];
        }
        if (! empty($validated['screen_h'])) {
            $update['Screen_H'] = (int) $validated['screen_h'];
        }
        if (! empty($validated['timezone'])) {
            $update['Timezone'] = $validated['timezone'];
        }
        if (! empty($validated['language'])) {
            $update['Language'] = $validated['language'];
        }
        if (! empty($validated['platform'])) {
            $update['Platform'] = $validated['platform'];
        }
        if (! empty($validated['app_version'])) {
            $update['App_Version'] = $validated['app_version'];
        }

        if (! empty($validated['gps']['lat']) && ! empty($validated['gps']['lng'])) {
            $update['Latitude'] = (float) $validated['gps']['lat'];
            $update['Longitude'] = (float) $validated['gps']['lng'];
            $update['Geo_Source'] = 'gps';
        }

        try {
            DB::connection($conn)->table('N_HRIS_Activity_Session')
                ->where('Session_Id', $sessionId)
                ->update($update);

            // upsert known device (kalau ada fingerprint)
            if (! empty($validated['fp'])) {
                $ident = $this->tracker->getIdentity()->resolve();
                if (! empty($ident['kode_karyawan'])) {
                    $existing = DB::connection($conn)->table('N_HRIS_Activity_Known_Device')
                        ->where('Kode_Karyawan', $ident['kode_karyawan'])
                        ->where('Device_Fingerprint', $validated['fp'])
                        ->first();

                    if ($existing) {
                        DB::connection($conn)->table('N_HRIS_Activity_Known_Device')
                            ->where('Kode_Karyawan', $ident['kode_karyawan'])
                            ->where('Device_Fingerprint', $validated['fp'])
                            ->update(['Last_Seen_At' => now()]);
                    } else {
                        $session = DB::connection($conn)->table('N_HRIS_Activity_Session')
                            ->where('Session_Id', $sessionId)
                            ->first(['Browser', 'Os']);

                        DB::connection($conn)->table('N_HRIS_Activity_Known_Device')->insert([
                            'Kode_Karyawan' => $ident['kode_karyawan'],
                            'Device_Fingerprint' => $validated['fp'],
                            'UserID_Web' => $ident['userid_web'],
                            'First_Seen_At' => now(),
                            'Last_Seen_At' => now(),
                            'Label' => null,
                            'Flag_Trusted' => 'T',
                            'Last_Browser' => $session->Browser ?? null,
                            'Last_Os' => $session->Os ?? null,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Activity handshake failed', ['error' => $e->getMessage()]);

            return ResponseHelper::error('Handshake failed', 500);
        }

        return ResponseHelper::success(['session_id' => $sessionId], 'OK');
    }
}
