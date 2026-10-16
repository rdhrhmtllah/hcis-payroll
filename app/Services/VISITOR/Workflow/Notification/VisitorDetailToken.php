<?php

namespace App\Services\VISITOR\Workflow\Notification;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Penyamaran Id transaksi pada tautan notifikasi Visitor.
 *
 * Tujuannya menghilangkan Id berurutan dari URL — angka berurutan membocorkan
 * volume transaksi dan mengundang pengguna mengutak-atik alamat. Ini BUKAN
 * penjaga akses: yang menjaga tetap penyaringan pemilik di endpoint `show()`,
 * dan itu tidak boleh dicabut dengan alasan tautannya sudah tersamar.
 */
class VisitorDetailToken
{
    /**
     * Bungkus Id menjadi token yang aman dipakai sebagai satu ruas path URL.
     */
    public static function buat(string $domain, int $id): string
    {
        return self::keUrlSafe(Crypt::encryptString(self::normalDomain($domain).':'.$id));
    }

    /**
     * Baca Id dari token, atau dari angka polos.
     *
     * Angka polos tetap diterima demi dua pemakai sah: notifikasi lama yang
     * sudah tersimpan dengan `?detail=<angka>`, dan klik dari tabel di halaman
     * yang mengirim Id dari data yang memang sudah ada di layar.
     *
     * @return int|null null bila tidak terbaca — pemanggil wajib membalas 404,
     *                  bukan meneruskan nilainya ke query
     */
    public static function bacaId(string $domain, string $nilai): ?int
    {
        $nilai = trim($nilai);

        if ($nilai === '') {
            return null;
        }

        if (ctype_digit($nilai)) {
            return (int) $nilai > 0 ? (int) $nilai : null;
        }

        try {
            $isi = Crypt::decryptString(self::dariUrlSafe($nilai));
        } catch (DecryptException) {
            return null;
        }

        $bagian = explode(':', $isi, 2);
        if (count($bagian) !== 2) {
            return null;
        }

        [$domainToken, $id] = $bagian;

        if ($domainToken !== self::normalDomain($domain) || ! ctype_digit($id)) {
            return null;
        }

        return (int) $id > 0 ? (int) $id : null;
    }

    private static function normalDomain(string $domain): string
    {
        return strtolower(trim($domain));
    }

    /**
     * Ciphertext Crypt adalah base64 biasa, yang memuat '+', '/', dan '='.
     * Satu '/' saja memecah ruas path URL dan permintaannya nyasar.
     */
    private static function keUrlSafe(string $ciphertext): string
    {
        return rtrim(strtr($ciphertext, '+/', '-_'), '=');
    }

    private static function dariUrlSafe(string $token): string
    {
        $base64 = strtr($token, '-_', '+/');

        return str_pad($base64, intdiv(strlen($base64) + 3, 4) * 4, '=');
    }
}
