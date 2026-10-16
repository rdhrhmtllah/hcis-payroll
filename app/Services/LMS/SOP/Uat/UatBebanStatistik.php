<?php

namespace App\Services\LMS\SOP\Uat;

/**
 * Statistik latensi uji beban.
 *
 * DIPISAH JADI KELAS SENDIRI karena murni fungsi — bisa diuji tanpa jaringan,
 * tanpa basis data, tanpa menunggu satu menit. Salah hitung persentil di sini
 * akan merusak seluruh kesimpulan uji beban tanpa meninggalkan jejak apa pun:
 * angkanya tetap terlihat masuk akal.
 */
class UatBebanStatistik
{
    /**
     * Persentil dengan interpolasi linier. Daftar kosong = 0.0.
     *
     * Masukan DIURUTKAN di sini, bukan diandaikan sudah urut. Hasil pengukuran
     * datang sesuai urutan selesainya permintaan, yang justru acak — dan
     * persentil dari data tak urut adalah angka yang tak berarti apa-apa
     * sekaligus tak terlihat salah.
     */
    public static function persentil(array $ms, float $p): float
    {
        $nilai = array_values(array_map('floatval', $ms));
        if ($nilai === []) {
            return 0.0;
        }

        sort($nilai);
        $posisi = $p * (count($nilai) - 1);
        $bawah = (int) floor($posisi);
        $atas = (int) ceil($posisi);

        if ($bawah === $atas) {
            return $nilai[$bawah];
        }

        return $nilai[$bawah] + ($nilai[$atas] - $nilai[$bawah]) * ($posisi - $bawah);
    }

    /** @return array{n:int,p50:float,p95:float,maks:float} */
    public static function ringkas(array $ms): array
    {
        return [
            'n' => count($ms),
            'p50' => self::persentil($ms, 0.50),
            'p95' => self::persentil($ms, 0.95),
            'maks' => $ms === [] ? 0.0 : (float) max(array_map('floatval', $ms)),
        ];
    }
}
