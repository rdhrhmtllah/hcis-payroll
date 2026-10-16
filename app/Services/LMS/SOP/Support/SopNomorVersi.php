<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Facades\DB;

/**
 * Nomor versi berikutnya bagi sebuah dokumen SOP — dijawab di SATU tempat.
 *
 * KENAPA KELAS INI ADA. Aturannya pernah ditulis di EMPAT tempat, dan pada
 * 30 Agu 2026 baru tiga di antaranya diperbaiki. Yang keempat — muatan wizard —
 * tetap menghitung MAX tanpa saringan, mengirimkannya ke layar sebagai nomor
 * usulan, dan layar mengirimkannya balik sebagai nomor MANUAL. Akibatnya versi
 * baru pada dokumen yang v01-nya dibatalkan lahir sebagai v02: penomoran
 * otomatis sudah benar, tapi tak pernah dipakai karena nomornya sudah
 * ditentukan di layar.
 *
 * Gejalanya persis yang hendak diperbaiki: nomor resmi melompat dari …/00 ke
 * …/02, menyebut Revisi 01 yang tak pernah diterbitkan kepada siapa pun.
 *
 * ATURANNYA. Hanya versi HIDUP yang mengonsumsi nomor. Draft yang dibatalkan
 * (Flag_Aktif='T') melepaskannya kembali — ia tak pernah menjadi revisi yang
 * diterbitkan. OBSOLETE tidak: versi lama yang tergantikan tetap Flag_Aktif='Y'
 * sehingga nomor yang PERNAH beredar terkunci selamanya.
 *
 * Saringan yang sama menjadi predikat indeks unik terfilter
 * UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup (DDL-2026-062). Keduanya WAJIB
 * menjawab sama; kalau tidak, layar menawarkan nomor yang basis data tolak.
 */
final class SopNomorVersi
{
    /**
     * Nomor berikutnya untuk dokumen ini.
     *
     * Versi PERTAMA bernomor 0 ("v00"), bukan 1: (MAX ?? -1) + 1. Dengan
     * (MAX ?? 0) + 1, nomor 0 tak akan pernah terpakai sama sekali.
     */
    public static function berikutnya(int $idDokumen): int
    {
        $maks = self::kueriHidup($idDokumen)->max('Versi_Ke');

        return $maks === null ? 0 : ((int) $maks) + 1;
    }

    /**
     * Apakah nomor ini sudah dipakai versi yang masih hidup?
     *
     * Dipakai jalur nomor MANUAL. Versi yang dibatalkan tak dihitung memakai —
     * itulah gunanya nomornya dilepas.
     */
    public static function terpakai(int $idDokumen, int $nomor, ?int $kecualiVersi = null): bool
    {
        $q = self::kueriHidup($idDokumen)->where('Versi_Ke', $nomor);

        if ($kecualiVersi !== null) {
            $q->where('Id_SOP_Item_Versi', '<>', $kecualiVersi);
        }

        return $q->exists();
    }

    private static function kueriHidup(int $idDokumen)
    {
        return DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF);
    }
}
