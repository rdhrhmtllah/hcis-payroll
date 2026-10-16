<?php

namespace App\Services\ESS\Attendance\Leave\Shared;

use Carbon\Carbon;

/**
 * Aturan akrual cuti bulanan: kapan jatuh temponya, di mana posisinya dalam
 * siklus tahun kerja, dan berapa hari yang didapat.
 *
 * ── Kenapa ini WAJIB satu rumah ──────────────────────────────────────────────
 *
 * Aturannya ditulis DUA KALI, di dua tempat yang perannya justru berlawanan:
 *
 *   tambahKuotaCutiBulanan   perintah yang BENAR-BENAR MEMBERI kuotanya
 *   CutiDashboardService     layar yang MENAMPILKAN perkiraannya
 *
 * Selama keduanya salinan terpisah, tidak ada apa pun yang memaksa angka di
 * layar sama dengan angka yang terjadi. Karyawan membaca "bulan depan +1",
 * lalu menerima +2 — atau sebaliknya — dan tak satu pun galat muncul, karena
 * dua-duanya "benar" menurut salinannya masing-masing.
 *
 * Saat dipusatkan ketiganya masih identik, dan aturan jumlah harinya pun sama
 * (satu ditulis sebagai metode, satu lagi inline). Yang diperbaiki bukan
 * perbedaan hari ini melainkan bentuk yang membuat perbedaan besok tidak
 * terdeteksi.
 *
 * ── Bentuk tanggalnya sengaja menjepit ke akhir bulan ────────────────────────
 *
 * min($sourceDate->day, $daysInMonth) ada supaya karyawan yang masuk tanggal 31
 * tetap punya jatuh tempo di Februari. Menghapusnya membuat Carbon melimpah ke
 * bulan berikutnya, dan akrualnya bergeser satu bulan setiap tahun.
 */
class AturanAkrualCuti
{
    /**
     * Tanggal yang sama dengan $sumber pada $tahunTarget, dijepit ke hari
     * terakhir bulan itu bila bulannya lebih pendek.
     */
    public static function tanggalTahunan(Carbon $sumber, int $tahunTarget): Carbon
    {
        $base = Carbon::create($tahunTarget, $sumber->month, 1)->startOfDay();
        $day = min($sumber->day, $base->daysInMonth);

        return Carbon::create($tahunTarget, $sumber->month, $day)->startOfDay();
    }

    /**
     * Jatuh tempo akrual bulanan: hari yang sama dengan $sumber di dalam bulan
     * $awalPeriode, dijepit ke hari terakhir bulan itu.
     */
    public static function jatuhTempoBulanan(Carbon $sumber, Carbon $awalPeriode): Carbon
    {
        $day = min($sumber->day, $awalPeriode->daysInMonth);

        return Carbon::create($awalPeriode->year, $awalPeriode->month, $day)->startOfDay();
    }

    /**
     * Posisi sebuah jatuh tempo di dalam siklus tahun kerja karyawan.
     *
     * Siklusnya bermula pada ulang tahun masuk kerja, bukan 1 Januari — itu
     * sebabnya cycle_start mundur setahun bila ulang tahunnya belum lewat.
     *
     * @return array{cycle_start: Carbon, six_month_point: Carbon,
     *               next_anniversary: Carbon, month_in_cycle: int,
     *               is_second_half: bool}
     */
    public static function siklusTahunKerja(Carbon $tglMasuk, Carbon $jatuhTempo): array
    {
        $cycleStart = self::tanggalTahunan($tglMasuk, $jatuhTempo->year);

        if ($cycleStart->gt($jatuhTempo)) {
            $cycleStart = self::tanggalTahunan($tglMasuk, $jatuhTempo->year - 1);
        }

        $sixMonthPoint = $cycleStart->copy()->addMonths(6);
        $nextAnniversary = $cycleStart->copy()->addYear();

        return [
            'cycle_start' => $cycleStart,
            'six_month_point' => $sixMonthPoint,
            'next_anniversary' => $nextAnniversary,
            'month_in_cycle' => $cycleStart->diffInMonths($jatuhTempo) + 1,
            'is_second_half' => $jatuhTempo->greaterThanOrEqualTo($sixMonthPoint)
                && $jatuhTempo->lt($nextAnniversary),
        ];
    }

    /**
     * Berapa hari yang ditambahkan pada satu akrual bulanan.
     *
     * Satu-satunya pengecualian: golongan 6 ber-PKWTT pada PARUH KEDUA siklus
     * tahun kerjanya mendapat dua hari, bukan satu. Ketiga syaratnya harus
     * terpenuhi bersama-sama.
     */
    public static function jumlahBulanan(?int $idGolongan, ?string $statusKontrak, array $siklus): int
    {
        $paruhKedua = (bool) ($siklus['is_second_half'] ?? false);

        if ((int) $idGolongan === 6 && $statusKontrak === 'PKWTT' && $paruhKedua) {
            return 2;
        }

        return 1;
    }
}
