<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gerbang paket publik — SATU definisi untuk seluruh modul.
 *
 * Paket PUBLIK bisa melekat pada seseorang lewat dua jalan yang menghasilkan
 * baris berbentuk identik di N_SOP_Penjadwalan_Detail:
 *
 *   MANDIRI            karyawan membuka SOP publik, sistem mendaftarkannya
 *                      sendiri (SopPendaftaranMandiri). Ini MINAT.
 *   MANUAL / AUTO_RULE admin atau aturan menugaskannya dengan sengaja.
 *                      Ini KEWAJIBAN, dan tetap kewajiban walau belum dibuka.
 *
 * Yang memisahkan keduanya adalah kolom `Sumber`, jadi niatnya dibaca dari sana
 * dan tidak ditebak dari apakah karyawannya sudah membuka.
 *
 * KENAPA KELAS INI ADA. Syarat ini sebelumnya tersalin verbatim di lima tempat.
 * Ketika satu salinan diperbaiki, empat sisanya diam-diam melaporkan angka
 * berbeda atas data yang sama — dan tak ada satu pun yang menandainya, karena
 * tiap salinan tetap menjawab dengan angka yang terlihat masuk akal. Aturan
 * yang disalin akan menyimpang pada salinan yang terlewat; satu-satunya
 * pencegahan yang bekerja adalah tidak menyalinnya.
 *
 * Bentuk kueri para pemanggil berbeda hanya pada nama aliasnya, sehingga alias
 * itulah yang jadi parameter.
 */
final class SopGerbangPaket
{
    /**
     * Sumber penugasan yang bermakna KEWAJIBAN.
     *
     * Sengaja daftar-putih, bukan "apa pun selain MANDIRI": sumber baru yang
     * belum dipikirkan sebaiknya jatuh ke perilaku lama yang lebih ketat
     * daripada diam-diam terhitung sebagai kewajiban orang.
     */
    public const SUMBER_KEWAJIBAN = ['MANUAL', 'AUTO_RULE'];

    /**
     * Pasang gerbang pada klausa `where` bersarang.
     *
     * Dipakai sebagai: `$q->where(fn ($w) => SopGerbangPaket::pasang($w, 'mp', 'pj', 'd'));`
     *
     * @param  \Illuminate\Database\Query\Builder  $w  klausa bersarang, bukan kueri utama
     * @param  string  $p  alias N_SOP_Master_Paket
     * @param  string  $pj  alias N_SOP_Penjadwalan
     * @param  string  $d  alias N_SOP_Penjadwalan_Detail
     */
    public static function pasang($w, string $p, string $pj, string $d = 'd'): void
    {
        $w->where("{$p}.Mode_Distribusi", 'TERBATAS')
            ->orWhereIn("{$pj}.Sumber", self::SUMBER_KEWAJIBAN)
            ->orWhere("{$d}.Status_Paket", '<>', SopStatus::STATUS_BELUM)
            ->orWhereExists(function ($sub) use ($d) {
                $sub->select(DB::raw(1))
                    ->from('N_SOP_Progress as pr_cek')
                    ->whereColumn('pr_cek.Id_SOP_Penjadwalan_Detail', "{$d}.Id_SOP_Penjadwalan_Detail")
                    ->whereNotNull('pr_cek.Waktu_Buka');
            });
    }

    /**
     * Gerbang yang sama, dirender sebagai teks SQL.
     *
     * DUA BENTUK, SATU SUMBER — sama seperti yang dilakukan SopDokumenKelompok
     * untuk aturan kelompoknya. Bentuk ini dibutuhkan pemanggil yang merakit SQL
     * mentah dan karena itu tak bisa memakai pasang().
     *
     * Bentuk mentah inilah yang paling berbahaya bila disalin: ia tidak terlihat
     * oleh pencarian yang mencari pola query builder, sehingga salinannya bisa
     * tertinggal berbulan-bulan setelah aturannya berubah. Satu salinan seperti
     * itu memang pernah ada di SopDokumenKelompok::whereWajib(), lengkap dengan
     * komentar yang menjanjikan ia "sengaja sama persis".
     *
     * TIDAK ADA BINDING. Setiap nilai adalah konstanta, bukan masukan pengguna,
     * sehingga teks ini aman ditanam sebagai SQL mentah.
     */
    public static function sql(string $p, string $pj, string $d): string
    {
        $belum = SopStatus::STATUS_BELUM;
        $sumber = "'".implode("', '", self::SUMBER_KEWAJIBAN)."'";

        return "(
                  {$p}.Mode_Distribusi = 'TERBATAS'
               OR {$pj}.Sumber IN ({$sumber})
               OR {$d}.Status_Paket <> '{$belum}'
               OR EXISTS (
                      SELECT 1 FROM N_SOP_Progress AS gp_pr
                      WHERE gp_pr.Id_SOP_Penjadwalan_Detail = {$d}.Id_SOP_Penjadwalan_Detail
                        AND gp_pr.Waktu_Buka IS NOT NULL
                  )
            )";
    }
}
