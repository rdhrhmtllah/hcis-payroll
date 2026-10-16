<?php

namespace App\Services\KPI\PerformanceAppraisal;

/**
 * Apa yang dihitung sebagai KOMPETENSI KUALITATIF — satu definisi untuk seluruh modul.
 *
 * Dua sumber, dan keduanya sah:
 *
 *  1. Kompetensi berjenis `KUALITATIF` (QL01 Strength, QL02 Development Area, ...).
 *  2. Kompetensi yang dijawab dengan ESAI TERSTRUKTUR dari HC Learn — bagian ber-`Flag_Essai`
 *     yang anak jawabannya tersimpan di `N_PA_Assignment_Score_Esai`, walau jenis
 *     kompetensinya Core Value dan ikut berskor (mis. CV03 "Mutual Growth", K14 ujian 100164).
 *
 * Yang kedua sampai 8 Oktober 2026 tidak dikenal: esai K14 masuk utuh (1.140 jawaban di siklus
 * UAT) tetapi tahap Kesimpulan Kualitatif berbunyi "tidak ada jawaban kualitatif" dan dilewati.
 *
 * Yang TETAP bukan kualitatif: komentar bebas (teks jawaban bagian) pada butir berskor tanpa anak
 * esai. Pembedanya keberadaan baris esai, bukan sekadar ada teks — itu yang dulu membuat
 * kotak catatan CV03 muncul lalu ditolak penyimpannya.
 *
 * Pembaca dan penyimpan WAJIB memakai predikat yang sama; kalau tidak, kotak yang ditampilkan
 * akan ditolak saat disimpan.
 */
final class PaKompetensiKualitatif
{
    /** Predikat SQL atas kompetensi (`$k` = alias N_PA_Competency, `$t` = alias N_PA_Competency_Type). */
    public static function sqlKompetensi(string $k = 'k', string $t = 't'): string
    {
        return "({$t}.Kode_Type = 'KUALITATIF' OR EXISTS (SELECT 1 FROM N_PA_Assignment_Score_Esai kq_e"
            .' JOIN N_PA_Assignment_Score kq_s ON kq_s.Id_PA_Assignment_Score = kq_e.PA_Assignment_Score_Id'
            ." WHERE kq_s.PA_Competency_Id = {$k}.Id_PA_Competency))";
    }

    /**
     * Predikat SQL atas SATU baris skor: kompetensinya berjenis KUALITATIF, atau baris itu
     * sendiri punya anak esai. Komentar pada butir berskor tanpa esai tidak ikut.
     */
    public static function sqlBarisSkor(string $sc = 'sc', string $t = 't'): string
    {
        return "({$t}.Kode_Type = 'KUALITATIF' OR EXISTS (SELECT 1 FROM N_PA_Assignment_Score_Esai kq_e"
            ." WHERE kq_e.PA_Assignment_Score_Id = {$sc}.Id_PA_Assignment_Score))";
    }
}
