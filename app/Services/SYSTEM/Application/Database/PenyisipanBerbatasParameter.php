<?php

namespace App\Services\SYSTEM\Application\Database;

use Illuminate\Support\Facades\DB;

/**
 * Penyisipan massal yang tidak bisa menembus batas 2.100 parameter SQL Server.
 *
 * ── Kenapa dipusatkan ────────────────────────────────────────────────────────
 *
 * SQL Server menolak statement dengan lebih dari 2.100 parameter, dan sebuah
 * INSERT massal memakai (jumlah kolom x jumlah baris) parameter. Gagalnya tidak
 * menyebut "terlalu banyak parameter" dengan jelas — ia muncul sebagai galat
 * driver yang menyamar jadi masalah lain.
 *
 * Metode insertWithSqlServerLimit() sudah ada DUA salinan identik, di
 * SystemAuthController dan AdminRoleManagerController. Di tempat lain
 * perhitungannya ditulis tangan sebagai konstanta:
 *
 *     foreach (array_chunk($barisBaru, 250) as $potongan)   // 8 kolom x 250 = 2.000
 *     foreach (array_chunk($barisBaru, 150) as $potongan)   // 9-10 kolom
 *
 * Konstanta itu BENAR hari ini — keduanya diperiksa dan memang di bawah batas.
 * Yang rapuh adalah caranya: angkanya benar hanya selama jumlah kolomnya tidak
 * berubah. Menambah satu kolom ke payload HRIS_Page_Access membuat 250 x 9 =
 * 2.250, dan tidak ada yang mengingatkan sampai admin pertama yang menyimpan
 * lebih dari 250 baris mendapat galat driver.
 *
 * Menghitung ukuran potongan DARI barisnya membuat aritmetika itu tak bisa basi.
 *
 * ── Kenapa 2.000, bukan 2.100 ────────────────────────────────────────────────
 *
 * Batas kerasnya 2.100, tetapi sebagian dipakai hal lain pada statement yang
 * sama. Margin seratus parameter dipertahankan seperti pada kode asal.
 */
class PenyisipanBerbatasParameter
{
    /** Batas keras SQL Server. Bukan angka yang boleh ditawar. */
    public const BATAS_KERAS = 2100;

    /** Batas kerja, menyisakan margin untuk parameter lain pada statement yang sama. */
    public const BATAS_AMAN = 2000;

    /**
     * Berapa baris per potongan agar (kolom x baris) tetap di bawah batas.
     *
     * Murni: bisa diuji tanpa basis data, dan itu yang membuat aritmetikanya
     * benar-benar terjaga.
     *
     * @param  array<int, mixed>  $baris
     */
    public static function ukuranPotongan(array $baris, int $batasAman = self::BATAS_AMAN): int
    {
        if ($baris === []) {
            return 1;
        }

        $pertama = reset($baris);
        $jumlahKolom = is_array($pertama) ? max(1, count($pertama)) : 1;

        return max(1, intdiv(max(1, $batasAman), $jumlahKolom));
    }

    /**
     * Sisipkan seluruh baris, dipotong sesuai lebar barisnya.
     *
     * @param  array<int, mixed>  $baris
     */
    public static function sisipkan(string $tabel, array $baris, int $batasAman = self::BATAS_AMAN): void
    {
        if ($baris === []) {
            return;
        }

        foreach (array_chunk($baris, self::ukuranPotongan($baris, $batasAman)) as $potongan) {
            DB::table($tabel)->insert($potongan);
        }
    }
}
