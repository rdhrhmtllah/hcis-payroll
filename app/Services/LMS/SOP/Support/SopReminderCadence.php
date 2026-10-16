<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Carbon;

class SopReminderCadence
{
    public static function isDue(
        Carbon $today,
        int $intervalDays,
        mixed $lastReminderAt,
        mixed $deadlineAt,
        mixed $assignedAt,
    ): bool {
        $intervalDays = max(1, $intervalDays);

        if ($lastReminderAt) {
            return Carbon::parse($lastReminderAt)->addDays($intervalDays)->startOfDay()->lte($today);
        }

        if ($deadlineAt) {
            return Carbon::parse($deadlineAt)->startOfDay()->lte($today->copy()->addDays($intervalDays));
        }

        return $assignedAt
            ? Carbon::parse($assignedAt)->addDays($intervalDays)->startOfDay()->lte($today)
            : false;
    }
}
