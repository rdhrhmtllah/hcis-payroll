<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FormatTanggalHelper
{
    /**
     * Baris override waktu per $jenis, diingat selama proses berjalan.
     *
     * Yang diingat HANYA baris databasenya, bukan waktunya. Bila tidak ada
     * override, getCurrentTime() tetap memulangkan Carbon::now() yang segar
     * setiap dipanggil, sehingga waktu tetap berjalan seperti biasa.
     *
     * Alasannya: baris ini adalah saklar pengujian manual yang tidak berubah
     * di tengah satu permintaan, tetapi pemanggilnya bisa sangat banyak. Pada
     * layar "cari orang" lembur ia ditanyakan 1.013 kali untuk satu jawaban
     * yang sama — 156 detik terhadap DB dev, hanya untuk membaca ulang satu
     * baris yang tak bergerak.
     *
     * @var array<string, object|null|false>
     */
    private static array $overrideTerbaca = [];

    /** Lupakan yang sudah diingat — untuk uji yang memang mengubah barisnya. */
    public static function lupakanOverride(): void
    {
        static::$overrideTerbaca = [];
    }

    public static function getCurrentTime(string $jenis = 'All')
    {
        if (! array_key_exists($jenis, static::$overrideTerbaca)) {
            static::$overrideTerbaca[$jenis] = DB::table('KPI_Get_Date_Dummy')
                ->where('Flag_Aktif', 'Y')
                ->where('Jenis', $jenis)
                ->orderBy('Id_Get_Date', 'DESC')
                ->first();
        }

        $Tanggal = static::$overrideTerbaca[$jenis];

        if ($Tanggal && ! empty($Tanggal->Tanggal)) {
            $tanggalOnly = Carbon::parse($Tanggal->Tanggal)->format('Y-m-d');
            $jam = ! empty($Tanggal->Jam) ? trim((string) $Tanggal->Jam) : '00:00:00';

            if (preg_match('/^\d{2}:\d{2}$/', $jam)) {
                $jam .= ':00';
            }

            if (! preg_match('/^\d{2}:\d{2}:\d{2}$/', $jam)) {
                $jam = '00:00:00';
            }

            return Carbon::parse($tanggalOnly.' '.$jam, 'Asia/Jakarta');
        }

        return Carbon::now('Asia/Jakarta');
    }

    public static function getCurrentTime2()
    {
        $Tanggal = DB::table('KPI_Get_Date_Dummy2')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_Get_Date2', 'DESC')
            ->first();

        if ($Tanggal && ! empty($Tanggal->Tanggal)) {
            return Carbon::parse($Tanggal->Tanggal);
        }

        return Carbon::now('Asia/Jakarta');
    }

    public static function format($tanggal, bool $withTime = false)
    {
        if (empty($tanggal)) {
            return null;
        }

        try {
            $carbonDate = Carbon::parse($tanggal)->locale('id_ID');

            if ($withTime) {
                return $carbonDate->translatedFormat('d M Y H:i');
            }

            return $carbonDate->translatedFormat('d M Y');
        } catch (\Exception $e) {
            return $tanggal;
        }
    }

    public static function formatDateTransaksi($tanggal, $jam = null)
    {
        if (empty($tanggal)) {
            return null;
        }

        try {

            if (! empty($jam)) {

                $tanggalOnly = Carbon::parse($tanggal)->format('Y-m-d');

                $gabung = $tanggalOnly.' '.$jam;

                $carbon = Carbon::parse($gabung);

                return $carbon->format('Y-m-d H:i');
            }

            $carbonDate = Carbon::parse($tanggal);

            return $carbonDate->format('Y-m-d');

        } catch (\Exception $e) {
            return $tanggal;
        }
    }

    public static function convertDoubleDate($tanggalMulai, $tanggalSelesai, bool $withTime = false)
    {
        // Jika salah satu kosong, langsung kembalikan string kosong
        if (! $tanggalMulai || ! $tanggalSelesai) {
            return '';
        }

        // Parsing ke objek DateTime
        $start = Carbon::parse($tanggalMulai);
        $end = Carbon::parse($tanggalSelesai);

        // Format tanggal & waktu
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        $startDay = $start->format('j');
        $startMonth = (int) $start->format('n');
        $startYear = $start->format('Y');

        $endDay = $end->format('j');
        $endMonth = (int) $end->format('n');
        $endYear = $end->format('Y');

        // Jika tanggal mulai dan selesai sama
        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            $hasil = $startDay.' '.$bulan[$startMonth].' '.$startYear;
        }
        // Jika masih dalam bulan & tahun yang sama
        elseif ($startMonth === $endMonth && $startYear === $endYear) {
            $hasil = $startDay.'–'.$endDay.' '.$bulan[$startMonth].' '.$startYear;
        }
        // Jika masih dalam tahun yang sama tapi beda bulan
        elseif ($startYear === $endYear) {
            $hasil = $startDay.' '.$bulan[$startMonth].' – '.$endDay.' '.$bulan[$endMonth].' '.$startYear;
        }
        // Jika beda tahun
        else {
            $hasil = $startDay.' '.$bulan[$startMonth].' '.$startYear.' – '.$endDay.' '.$bulan[$endMonth].' '.$endYear;
        }

        // Tambahkan waktu jika diminta
        if ($withTime) {
            $startTime = $start->format('H:i');
            $endTime = $end->format('H:i');

            // Jika tanggal sama tapi jam beda
            if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
                $hasil .= " ($startTime - $endTime)";
            } else {
                $hasil .= " $startTime - $endTime";
            }
        }

        return $hasil;
    }

    // public static function formatTanggalMultiple(array $dates)
    // {
    //     if (empty($dates)) {
    //         return '';
    //     }

    //     // Sort tanggal dulu
    //     sort($dates);
    //     // dd($dates);

    //     // Group by year & month
    //     $grouped = [];
    //     foreach ($dates as $d) {
    //         // dd($d);
    //         $time = strtotime($d);
    //         $year = date('Y', $time);
    //         $month = date('m', $time);

    //         $grouped[$year][$month][] = $d;
    //     }

    //     $monthNames = [
    //         '01' => 'Januari',
    //         '02' => 'Februari',
    //         '03' => 'Maret',
    //         '04' => 'April',
    //         '05' => 'Mei',
    //         '06' => 'Juni',
    //         '07' => 'Juli',
    //         '08' => 'Agustus',
    //         '09' => 'September',
    //         '10' => 'Oktober',
    //         '11' => 'November',
    //         '12' => 'Desember'
    //     ];

    //     $resultParts = [];

    //     foreach ($grouped as $year => $months) {

    //         foreach ($months as $month => $dateList) {

    //             // Convert tanggal jadi range jika berurutan
    //             $ranges = [];
    //             $start = null;
    //             $previous = null;

    //             foreach ($dateList as $d) {
    //                 $current = strtotime($d);

    //                 if ($start === null) {
    //                     $start = $current;
    //                 } elseif ($current - $previous != 86400) {
    //                     // break in sequence
    //                     $ranges[] = [$start, $previous];
    //                     $start = $current;
    //                 }

    //                 $previous = $current;
    //             }

    //             // Range terakhir
    //             $ranges[] = [$start, $previous];

    //             // Build string ranges
    //             $formattedRanges = [];
    //             foreach ($ranges as $range) {
    //                 $startDay = date('d', $range[0]);
    //                 $endDay   = date('d', $range[1]);

    //                 if ($range[0] == $range[1]) {
    //                     // Single date
    //                     $formattedRanges[] = $startDay;
    //                 } else {
    //                     // e.g. 01–04
    //                     $formattedRanges[] = $startDay . '–' . $endDay;
    //                 }
    //             }

    //             $monthName = $monthNames[$month];
    //             $resultParts[] = implode(', ', $formattedRanges) . " $monthName $year";
    //         }
    //     }

    //     // Jika beda tahun/bulan → pisahkan dengan " & "
    //     return implode(' & ', $resultParts);
    // }

    public static function formatTanggalMultiple(array $dates)
    {
        if (empty($dates)) {
            return '';
        }

        /**
         * ----------------------------------------------------------
         * 1. NORMALISASI FORMAT TANGGAL → Y-m-d
         * ----------------------------------------------------------
         * Ini penting supaya:
         * 2026-02-01
         * 2026-02-01 00:00:00
         * dianggap sama.
         */
        $normalizedDates = array_map(function ($d) {
            return date('Y-m-d', strtotime($d));
        }, $dates);

        /**
         * ----------------------------------------------------------
         * 2. HILANGKAN DUPLIKAT
         * ----------------------------------------------------------
         */
        $uniqueDates = array_values(array_unique($normalizedDates));

        /**
         * ----------------------------------------------------------
         * 3. SORT TANGGAL
         * ----------------------------------------------------------
         */
        sort($uniqueDates);

        /**
         * ----------------------------------------------------------
         * 4. GROUP BY YEAR & MONTH
         * ----------------------------------------------------------
         */
        $grouped = [];
        foreach ($uniqueDates as $d) {
            $time = strtotime($d);
            $year = date('Y', $time);
            $month = date('m', $time);

            $grouped[$year][$month][] = $d;
        }

        $monthNames = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        ];

        $resultParts = [];

        foreach ($grouped as $year => $months) {
            foreach ($months as $month => $dateList) {

                /**
                 * ----------------------------------------------------------
                 * 5. BENTUK RANGE TANGGAL BERURUTAN
                 * ----------------------------------------------------------
                 */
                $ranges = [];
                $start = null;
                $previous = null;

                foreach ($dateList as $d) {
                    $current = strtotime($d);

                    if ($start === null) {
                        $start = $current;
                    } elseif ($current - $previous != 86400) {
                        $ranges[] = [$start, $previous];
                        $start = $current;
                    }

                    $previous = $current;
                }

                $ranges[] = [$start, $previous];

                /**
                 * ----------------------------------------------------------
                 * 6. FORMAT RANGE → 01–04, 07, 09–10
                 * ----------------------------------------------------------
                 */
                $formattedRanges = [];
                foreach ($ranges as $range) {
                    $startDay = date('d', $range[0]);
                    $endDay = date('d', $range[1]);

                    if ($range[0] == $range[1]) {
                        $formattedRanges[] = $startDay;
                    } else {
                        $formattedRanges[] = $startDay.'–'.$endDay;
                    }
                }

                $monthName = $monthNames[$month];
                $resultParts[] = implode(', ', $formattedRanges)." $monthName $year";
            }
        }

        /**
         * ----------------------------------------------------------
         * 7. GABUNGKAN ANTAR BULAN / TAHUN
         * ----------------------------------------------------------
         */
        return implode(' & ', $resultParts);
    }
}
