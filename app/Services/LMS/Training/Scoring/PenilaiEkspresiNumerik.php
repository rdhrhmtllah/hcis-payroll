<?php

namespace App\Services\LMS\Training\Scoring;

/**
 * Menghitung nilai sebuah ekspresi aritmetika yang berasal dari rumus training.
 *
 * ── Kenapa ini WAJIB punya satu rumah ────────────────────────────────────────
 *
 * Isinya `eval()`. Yang membuatnya aman bukan eval-nya melainkan satu baris di
 * atasnya: sebuah daftar-putih yang menolak apa pun selain digit, titik, empat
 * operator, dan tanda kurung. Tanpa huruf tak ada nama fungsi; tanpa `$` tak
 * ada variabel; tanpa `;` dan backtick tak ada pernyataan kedua maupun shell.
 *
 * Penjaga seperti itu tidak boleh punya dua versi. Sebelum dipusatkan, ia
 * tersalin TIGA KALI — LmsMateriController, AdminTrainingMonitoringController,
 * dan TrainingExecutionService — dan ketiganya kebetulan masih identik. Yang
 * berbahaya bukan keadaan hari ini melainkan bentuknya: siapa pun yang kelak
 * melonggarkan daftar-putih di satu berkas (misalnya menambahkan huruf demi
 * mendukung `ROUND(...)`) melonggarkannya hanya di sana, dan dua salinan
 * lainnya tidak ikut ditinjau. Sebaliknya pula: pengetatan di satu tempat tidak
 * sampai ke dua tempat lain.
 *
 * ── Yang ditolak, dan kenapa hasilnya null ───────────────────────────────────
 *
 * Semua kegagalan — karakter terlarang, ekspresi kosong, kurung tak seimbang,
 * pembagian nol — menghasilkan null, bukan lemparan. Pemanggilnya memakai nilai
 * itu untuk menampilkan "nilai akhir belum tersedia"; sebuah galat di tengah
 * layar monitoring bukan jawaban yang berguna bagi admin yang cuma ingin tahu
 * siapa yang belum selesai.
 */
class PenilaiEkspresiNumerik
{
    /**
     * Karakter yang boleh lolos ke eval(). Dipakai sebagai daftar-putih
     * (negasi), jadi apa pun di luar himpunan ini menggugurkan ekspresinya.
     */
    private const IZIN = '/[^0-9\.\+\-\*\/\(\)]/';

    /**
     * @return float|null null bila ekspresinya ditolak atau tak bisa dihitung
     */
    public static function nilai(string $ekspresi): ?float
    {
        $bersih = preg_replace('/\s+/', '', $ekspresi);

        if ($bersih === '' || preg_match(self::IZIN, $bersih)) {
            return null;
        }

        try {
            $hasil = eval('return '.$bersih.';');
        } catch (\Throwable $e) {
            // ParseError (kurung tak seimbang), DivisionByZeroError, dan
            // apa pun lainnya sama-sama berarti "tidak ada nilai".
            return null;
        }

        return is_numeric($hasil) ? (float) $hasil : null;
    }
}
