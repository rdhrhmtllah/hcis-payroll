<?php

namespace App\Services\KPI;

use Carbon\Carbon;

/**
 * Filter rentang Karyawan.Tgl_Masuk untuk monitoring & export KPI.
 *
 * Satu rumah supaya layar monitoring, hitungan "pilih semua", dan isi berkas
 * export menyaring dengan aturan yang persis sama. Rentang inklusif di kedua
 * ujung; batas atas dibandingkan dengan "< hari berikutnya" supaya Tgl_Masuk
 * yang membawa jam tetap ikut dan indeks kolomnya tetap terpakai.
 */
class KpiFilterTglMasuk
{
    /**
     * Urai nilai mentah [dari, sampai] (Y-m-d). Masukan yang tak lengkap atau
     * tak terurai = tanpa filter (null), bukan galat. Urutan terbalik ditukar.
     *
     * @return array{0:string,1:string}|null
     */
    public static function rentang($raw): ?array
    {
        if (! is_array($raw) || count($raw) !== 2) {
            return null;
        }

        $tgl = [];
        foreach (array_values($raw) as $v) {
            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            [$y, $m, $d] = array_map('intval', explode('-', $v));
            if (! checkdate($m, $d, $y)) {
                return null;
            }
            $tgl[] = Carbon::createFromFormat('!Y-m-d', $v);
        }
        if ($tgl[0]->gt($tgl[1])) {
            $tgl = [$tgl[1], $tgl[0]];
        }

        return [$tgl[0]->toDateString(), $tgl[1]->toDateString()];
    }

    /** Terapkan rentang ke builder ber-alias `k` (Karyawan). Null = tanpa filter. */
    public static function terapkan($query, ?array $rentang)
    {
        if ($rentang === null) {
            return $query;
        }

        return $query
            ->where('k.Tgl_Masuk', '>=', $rentang[0])
            ->where('k.Tgl_Masuk', '<', Carbon::parse($rentang[1])->addDay()->toDateString());
    }
}
