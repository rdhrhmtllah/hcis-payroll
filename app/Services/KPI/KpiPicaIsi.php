<?php

namespace App\Services\KPI;

/**
 * Apakah sebuah isian PICA benar-benar mengatakan sesuatu.
 *
 * ── Kenapa penjaga ini ada ──────────────────────────────────────────────────
 *
 * PICA adalah satu-satunya tempat di seluruh modul KPI yang menjelaskan KENAPA
 * sebuah target meleset dan apa yang akan dilakukan. Nilainya bukan pada
 * adanya baris, melainkan pada isinya — dan selama isian apa pun diterima,
 * kolom itu terisi tanpa pernah menjawab apa pun.
 *
 * Diukur di production 16 Sep 2026 pada dbo.KPI_Update_Performance_Det:
 * dari 2.766 baris, **1.167 berisi tiga karakter atau kurang** — '-', '1',
 * 'sda'. Khusus periode Juli 2026, 412 baris kedua sisinya ≤2 byte. Laporan
 * dan export memuat baris-baris itu apa adanya, dan pembacanya tidak
 * mendapat apa pun selain kebisingan yang menutupi baris yang sungguh berisi.
 *
 * ── Ambangnya dan alasannya ────────────────────────────────────────────────
 *
 * Yang dihitung adalah HURUF, bukan panjang string. '1', '123', '---', dan
 * '...' tidak punya satu huruf pun; 'sda' punya tiga. Ambang tiga huruf
 * sengaja rendah: penjaga ini menolak isian yang jelas bukan kalimat, bukan
 * menilai mutu tulisan orang. Menaikkannya berarti mulai menolak singkatan
 * yang sah, dan itu keputusan proses — bukan keputusan yang boleh diambil
 * diam-diam di dalam kode.
 *
 * Penjaga ini dipasang di SETIAP pintu penulisan PICA. Aturan yang hanya ada
 * di satu dari beberapa jalur setara adalah pola cacat yang sudah berulang di
 * repositori ini: jalur yang terlewat menjadi pintu belakang yang tak
 * seorang pun ingat.
 */
final class KpiPicaIsi
{
    /** Jumlah huruf minimum agar sebuah isian dianggap mengatakan sesuatu. */
    public const MIN_HURUF = 3;

    public const PESAN = 'Identifikasi masalah dan tindakan koreksi harus diisi dengan kalimat yang menjelaskan, '
        .'minimal '.self::MIN_HURUF.' huruf. Isian seperti "-", "1", atau tanda baca saja tidak bisa disimpan.';

    /**
     * Isian dianggap bermakna bila memuat minimal MIN_HURUF huruf.
     *
     * Huruf apa pun, bukan hanya A-Z: nama dan istilah non-latin tetap lolos.
     * Angka sengaja TIDAK dihitung — "2024" bukan penjelasan, dan kalaupun
     * angka memang bagian dari kalimat, kalimatnya pasti membawa huruf juga.
     */
    public static function bermakna(?string $teks): bool
    {
        $teks = trim((string) $teks);

        if ($teks === '') {
            return false;
        }

        return mb_strlen((string) preg_replace('/[^\p{L}]/u', '', $teks)) >= self::MIN_HURUF;
    }

    /**
     * Kedua sisi PICA harus bermakna. Satu sisi yang berisi tidak cukup:
     * identifikasi tanpa tindakan adalah keluhan, dan tindakan tanpa
     * identifikasi tidak bisa ditelusuri kembali ke sebabnya.
     */
    public static function pasanganBermakna(?string $identifikasi, ?string $koreksi): bool
    {
        return self::bermakna($identifikasi) && self::bermakna($koreksi);
    }
}
