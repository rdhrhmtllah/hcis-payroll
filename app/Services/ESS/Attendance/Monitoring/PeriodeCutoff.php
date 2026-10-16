<?php

namespace App\Services\ESS\Attendance\Monitoring;

use Carbon\Carbon;

/**
 * Periode cut-off payroll (bawaan 16 → 15, services.hcis.cutoff_payroll_date).
 * Bila hari mulai > hari akhir, periode menyeberang bulan; tanggal yang sudah
 * melewati hari mulai milik periode yang berakhir bulan depan.
 */
final class PeriodeCutoff
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** @return array{mulai: string, akhir: string, label: string} */
    public static function untuk(Carbon $tanggal, ?int $hariMulai = null, ?int $hariAkhir = null): array
    {
        $hariMulai = max(1, min(31, $hariMulai ?? (int) config('services.hcis.cutoff_payroll_date.start_day', 16)));
        $hariAkhir = max(1, min(31, $hariAkhir ?? (int) config('services.hcis.cutoff_payroll_date.end_day', 15)));
        $t = $tanggal->copy()->startOfDay();

        if ($hariMulai <= $hariAkhir) {
            $bulanMulai = $t->copy()->startOfMonth();
            $bulanAkhir = $bulanMulai->copy();
        } elseif ($t->day >= $hariMulai) {
            $bulanMulai = $t->copy()->startOfMonth();
            $bulanAkhir = $bulanMulai->copy()->addMonthNoOverflow();
        } else {
            $bulanAkhir = $t->copy()->startOfMonth();
            $bulanMulai = $bulanAkhir->copy()->subMonthNoOverflow();
        }

        $mulai = $bulanMulai->copy()->day(min($hariMulai, $bulanMulai->daysInMonth));
        $akhir = $bulanAkhir->copy()->day(min($hariAkhir, $bulanAkhir->daysInMonth));

        return [
            'mulai' => $mulai->toDateString(),
            'akhir' => $akhir->toDateString(),
            'label' => self::label($mulai, $akhir),
        ];
    }

    /**
     * Periode berjalan lalu mundur; dipakai untuk pilihan periode di layar.
     *
     * @return list<array{mulai: string, akhir: string, label: string}>
     */
    public static function daftar(Carbon $acuan, int $jumlah = 12): array
    {
        $hasil = [];
        $periode = self::untuk($acuan);

        for ($i = 0; $i < $jumlah; $i++) {
            $hasil[] = $periode;
            $periode = self::untuk(Carbon::parse($periode['mulai'])->subDay());
        }

        return $hasil;
    }

    private static function label(Carbon $mulai, Carbon $akhir): string
    {
        $kiri = $mulai->day.' '.self::BULAN[$mulai->month - 1];

        if ($mulai->year !== $akhir->year) {
            $kiri .= ' '.$mulai->year;
        }

        return $kiri.' – '.$akhir->day.' '.self::BULAN[$akhir->month - 1].' '.$akhir->year;
    }
}
