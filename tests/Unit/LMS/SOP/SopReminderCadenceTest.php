<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\Support\SopReminderCadence;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class SopReminderCadenceTest extends TestCase
{
    public function test_deadline_first_reminder_starts_inside_interval(): void
    {
        $today = Carbon::parse('2026-08-01')->startOfDay();

        $this->assertTrue(SopReminderCadence::isDue($today, 3, null, '2026-08-04', '2026-07-01'));
        $this->assertFalse(SopReminderCadence::isDue($today, 3, null, '2026-08-05', '2026-07-01'));
    }

    public function test_no_deadline_first_reminder_uses_assignment_age(): void
    {
        $today = Carbon::parse('2026-08-10')->startOfDay();

        $this->assertTrue(SopReminderCadence::isDue($today, 3, null, null, '2026-08-07'));
        $this->assertFalse(SopReminderCadence::isDue($today, 3, null, null, '2026-08-08'));
    }

    public function test_subsequent_reminder_uses_last_sent_time(): void
    {
        $today = Carbon::parse('2026-08-10')->startOfDay();

        $this->assertTrue(SopReminderCadence::isDue($today, 3, '2026-08-07 00:25:00', '2026-08-30', '2026-07-01'));
        $this->assertFalse(SopReminderCadence::isDue($today, 3, '2026-08-08 00:25:00', '2026-08-01', '2026-07-01'));
    }
}
