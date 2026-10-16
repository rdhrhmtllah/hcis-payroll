<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

/**
 * Satu angka agregat.
 *
 * `agregat` adalah ekspresi lengkap yang KAMI tulis, bukan hasil rakitan
 * "agregasi × field" dari pengguna. Grain dataset kepatuhan adalah item, jadi
 * COUNT(*) di sana menghitung item — bukan orang. Bila pengguna boleh merakit
 * sendiri, angkanya salah dan tampak benar.
 */
class Ukuran
{
    /** Ukuran yang boleh dijumlahkan lintas baris pada baris GRAND TOTAL. */
    public const TOTAL_JUMLAH = 'jumlah';

    /**
     * Ukuran yang TIDAK boleh dijumlahkan.
     *
     * Rata-rata, persentase, dan COUNT(DISTINCT) semuanya rusak bila dijumlah:
     * rata-rata dari rata-rata bukan rata-rata, persen dari persen tak berarti,
     * dan satu orang yang muncul di dua kelompok terhitung dua kali. Baris
     * GRAND TOTAL menuliskan '-' untuk ukuran seperti ini, bukan angka yang
     * salah tapi tampak benar.
     */
    public const TOTAL_ABAIKAN = 'abaikan';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $agregat,
        public readonly string $format,
        public readonly bool $sensitif = false,
        public readonly string $totalisasi = self::TOTAL_JUMLAH,
    ) {}
}
