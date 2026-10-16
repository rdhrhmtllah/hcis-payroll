<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Carbon\CarbonInterface;

class PermitValidityPeriodCalculator
{
    /** @return array{0: string, 1: string} */
    public function calculate(CarbonInterface $approvedAt, int $months): array
    {
        if ($months < 1) {
            throw new \InvalidArgumentException('Durasi permit minimal satu bulan.');
        }

        $start = $approvedAt->copy()->startOfDay();
        $expiry = $start->copy()->addMonthsNoOverflow($months)->subDay();

        return [$start->toDateString(), $expiry->toDateString()];
    }
}
