<?php

namespace App\Listeners\Activity;

use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class HandleLogin
{
    public function __construct(protected ActivityTracker $tracker) {}

    public function handle(Login $event): void
    {
        try {
            $request = request();
            if (! $request) {
                return;
            }

            $this->tracker->startSession($request, [
                'login_method' => 'password',
                'login_status' => 'success',
                'mfa_used' => false,
                'fp' => $request->header('X-Activity-Fp'),
                'visitor_id' => $request->header('X-Activity-Visitor-Id'),
                'platform' => $request->header('X-Activity-Platform', 'web'),
            ]);

            // flush event auth.login segera (jangan tunggu request berikutnya)
            $this->tracker->flush();
        } catch (\Throwable $e) {
            Log::warning('Activity HandleLogin failed', ['error' => $e->getMessage()]);
        }
    }
}
