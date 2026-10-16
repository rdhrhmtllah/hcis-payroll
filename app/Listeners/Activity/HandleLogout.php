<?php

namespace App\Listeners\Activity;

use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class HandleLogout
{
    public function __construct(protected ActivityTracker $tracker) {}

    public function handle(Logout $event): void
    {
        try {
            $this->tracker->endSession('manual');
            $this->tracker->flush();
        } catch (\Throwable $e) {
            Log::warning('Activity HandleLogout failed', ['error' => $e->getMessage()]);
        }
    }
}
