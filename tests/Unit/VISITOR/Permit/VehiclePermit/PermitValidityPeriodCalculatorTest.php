<?php

namespace Tests\Unit\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\PermitValidityPeriodCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PermitValidityPeriodCalculatorTest extends TestCase
{
    public function test_one_month_period_is_inclusive_from_approval_date(): void
    {
        $result = (new PermitValidityPeriodCalculator)->calculate(
            CarbonImmutable::parse('2026-08-23 14:00:00', 'Asia/Jakarta'),
            1,
        );

        $this->assertSame(['2026-08-23', '2026-09-22'], $result);
    }

    public function test_end_of_month_does_not_overflow(): void
    {
        $result = (new PermitValidityPeriodCalculator)->calculate(
            CarbonImmutable::parse('2026-01-31 09:00:00', 'Asia/Jakarta'),
            1,
        );

        $this->assertSame(['2026-01-31', '2026-02-27'], $result);
    }

    public function test_non_positive_duration_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PermitValidityPeriodCalculator)->calculate(CarbonImmutable::parse('2026-08-23'), 0);
    }
}
