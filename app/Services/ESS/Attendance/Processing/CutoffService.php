<?php

namespace App\Services\ESS\Attendance\Processing;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CutoffService
{
    public function getCurrentPeriod($date = null)
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::now();
        $dateString = $targetDate->toDateString();
        $userid = Auth::check() ? Auth::id() : 'guest';

        $sessionKey = 'hcis_cutoff_'.$dateString.'_'.$userid;

        if (session()->has($sessionKey)) {
            return session()->get($sessionKey);
        }

        $config = DB::table('N_HRIS_Cutoff_Configs')
            ->where('Effective_From', '<=', $dateString)
            ->where(function ($query) use ($dateString) {
                $query->whereNull('Effective_To')->orWhere('Effective_To', '>=', $dateString);
            })
            ->orderBy('Effective_From', 'desc')
            ->first();

        if (! $config) {
            throw new \Exception('Config cutoff tidak ditemukan untuk tanggal: '.$dateString);
        }

        $startDay = max(1, min(31, (int) $config->Start_Day));
        $endDay = max(1, min(31, (int) $config->End_Day));

        if ($startDay <= $endDay) {
            $startDate = $this->safeCreateDate((int) $targetDate->year, (int) $targetDate->month, $startDay);
            $endDate = $this->safeCreateDate((int) $targetDate->year, (int) $targetDate->month, $endDay);

            if ($targetDate->lt($startDate)) {
                $base = $targetDate->copy()->subMonthNoOverflow();
                $startDate = $this->safeCreateDate((int) $base->year, (int) $base->month, $startDay);
                $endDate = $this->safeCreateDate((int) $base->year, (int) $base->month, $endDay);
            } elseif ($targetDate->gt($endDate)) {
                $base = $targetDate->copy()->addMonthNoOverflow();
                $startDate = $this->safeCreateDate((int) $base->year, (int) $base->month, $startDay);
                $endDate = $this->safeCreateDate((int) $base->year, (int) $base->month, $endDay);
            }
        } elseif ($targetDate->day >= $startDay) {
            $startDate = $this->safeCreateDate((int) $targetDate->year, (int) $targetDate->month, $startDay);
            $endBase = $targetDate->copy()->addMonthNoOverflow();
            $endDate = $this->safeCreateDate((int) $endBase->year, (int) $endBase->month, $endDay);
        } else {
            $startBase = $targetDate->copy()->subMonthNoOverflow();
            $startDate = $this->safeCreateDate((int) $startBase->year, (int) $startBase->month, $startDay);
            $endDate = $this->safeCreateDate((int) $targetDate->year, (int) $targetDate->month, $endDay);
        }

        $label =
            $config->Naming_Source === 'END_MONTH'
                ? $endDate->translatedFormat('F Y')
                : $startDate->translatedFormat('F Y');

        $result = [
            'Config_Name' => $config->Config_Name,
            'Start_Date' => $startDate->toDateString(),
            'End_Date' => $endDate->toDateString(),
            'Start_Day' => $startDay,
            'End_Day' => $endDay,
            'Label' => $label,
            'Source' => $config->Naming_Source,
        ];

        session()->put($sessionKey, $result);

        return $result;
    }

    public function getPeriodForStartMonth(int $year, int $month)
    {
        $month = max(1, min(12, $month));
        $reference = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $config = DB::table('N_HRIS_Cutoff_Configs')
            ->where('Effective_From', '<=', $reference->copy()->endOfMonth()->toDateString())
            ->where(function ($query) use ($reference) {
                $query->whereNull('Effective_To')->orWhere('Effective_To', '>=', $reference->toDateString());
            })
            ->orderBy('Effective_From', 'desc')
            ->first();

        if (! $config) {
            throw new \Exception('Config cutoff tidak ditemukan untuk bulan: '.$reference->format('Y-m'));
        }

        $startDay = max(1, min(31, (int) $config->Start_Day));
        $endDay = max(1, min(31, (int) $config->End_Day));
        $startDate = $this->safeCreateDate($year, $month, $startDay);
        $endBase = $startDay <= $endDay ? $startDate->copy() : $startDate->copy()->addMonthNoOverflow();
        $endDate = $this->safeCreateDate((int) $endBase->year, (int) $endBase->month, $endDay);
        $label =
            $config->Naming_Source === 'END_MONTH'
                ? $endDate->translatedFormat('F Y')
                : $startDate->translatedFormat('F Y');

        return [
            'Config_Name' => $config->Config_Name,
            'Start_Date' => $startDate->toDateString(),
            'End_Date' => $endDate->toDateString(),
            'Start_Day' => $startDay,
            'End_Day' => $endDay,
            'Label' => $label,
            'Source' => $config->Naming_Source,
        ];
    }

    protected function safeCreateDate(int $year, int $month, int $day): Carbon
    {
        $base = Carbon::createFromDate($year, $month, 1)->startOfDay();

        return $base->day(min(max(1, $day), $base->copy()->endOfMonth()->day));
    }
}
