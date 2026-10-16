<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Penyamaran Kode_Karyawan untuk segmen URL.
 *
 * Dipakai halaman monitoring per karyawan supaya alamatnya tidak memuat kode
 * polos — bukan karena kodenya rahasia (halamannya sendiri menampilkannya),
 * tapi supaya alamat tidak bisa ditebak berurutan: A4, A5, A6, ...
 *
 * Memakai Crypt (APP_KEY) mengikuti konvensi yang sudah ada di repo untuk
 * Kode_Karyawan (AdminCutiController, KuotaManagementController). Keluaran
 * Crypt beralfabet base64 baku, yang memuat '+', '/', dan '=' — ketiganya
 * bermasalah di segmen path, jadi ditukar ke ragam URL-safe. Penukaran ini
 * dilakukan pada keluaran Crypt, BUKAN dengan base64 lapis kedua, karena
 * melapis ulang akan membengkakkan token ~35% tanpa manfaat apa pun.
 *
 * Token berubah tiap kali dipanggil (IV acak pada Crypt). Itu disengaja:
 * alamat lama tetap bisa dibuka selama APP_KEY tidak dirotasi, tapi alamat
 * yang sama tidak muncul dua kali sehingga tidak menjadi pengenal tetap.
 */
class SopKaryawanToken
{
    public static function encode(string $kode): string
    {
        return rtrim(strtr(Crypt::encryptString($kode), '+/', '-_'), '=');
    }

    /**
     * Sematkan kolom Token pada tiap baris yang punya Kode_Karyawan.
     *
     * Front-end tidak bisa menghitung token sendiri, jadi setiap muatan yang
     * dipakai untuk merakit tautan ke halaman monitoring per karyawan harus
     * melewati sini. Baris tanpa Kode_Karyawan dibiarkan apa adanya.
     *
     * @param  iterable<mixed>  $rows  koleksi/array berisi stdClass atau array
     * @return mixed bentuk yang sama dengan masukan
     */
    public static function sematkan($rows)
    {
        if ($rows instanceof \Illuminate\Support\Collection) {
            return $rows->map(fn ($r) => self::sematkanBaris($r));
        }

        return array_map(fn ($r) => self::sematkanBaris($r), (array) $rows);
    }

    private static function sematkanBaris($baris)
    {
        if (is_array($baris)) {
            if (isset($baris['Kode_Karyawan'])) {
                $baris['Token'] = self::encode((string) $baris['Kode_Karyawan']);
            }

            return $baris;
        }

        if (is_object($baris) && isset($baris->Kode_Karyawan)) {
            $baris->Token = self::encode((string) $baris->Kode_Karyawan);
        }

        return $baris;
    }

    /** @return string|null null bila token tidak sah — pemanggil yang menentukan responsnya. */
    public static function decode(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        $base64 = strtr($token, '-_', '+/');
        $base64 = str_pad($base64, (int) (ceil(strlen($base64) / 4) * 4), '=');

        try {
            return Crypt::decryptString($base64);
        } catch (DecryptException $e) {
            return null;
        }
    }
}
