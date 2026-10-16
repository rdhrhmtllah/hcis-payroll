/* Change ID: DML-2026-122-ambang-kompetensi-baku-80
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-096 (kolom Nilai_Ambang), DML-2026-121 (Flag_Radar butir kualitatif).
   Scope: menetapkan ambang nilai (KKM) 80 untuk SELURUH kompetensi berskor.

   ── KENAPA 80, DAN KENAPA RATA ────────────────────────────────────────────────
   Angka ini keputusan HC tanggal 11 September 2026: satu ambang yang sama untuk semua
   kompetensi, bukan ambang per kompetensi. Skalanya 0–100, sama dengan nilai kompetensi
   yang sudah dinormalkan `Skor / Skor_Maksimal * 100`.

   Rata bukan berarti sementara. Kolomnya memang per kompetensi, jadi kalau kelak HC
   membedakan ambang antar kompetensi, yang berubah cukup datanya — tanpa DDL, tanpa
   perubahan kode.

   ── KENAPA BUTIR KUALITATIF DILEWATI ──────────────────────────────────────────
   `Flag_Berskor = 'T'` membawa teks, bukan angka: Strength, Development Area, Initiative,
   Recommendation. Memberi mereka ambang berarti menetapkan standar bagi sesuatu yang tidak
   pernah diukur. `PaMasterWriteService::simpanKompetensi()` menolaknya di jalur aplikasi;
   penyaringan di sini menjaga jalur SQL mengatakan hal yang sama.

   ── KENAPA BARIS NONAKTIF IKUT DIISI ──────────────────────────────────────────
   Kompetensi nonaktif bukan kompetensi yang dihapus — ia bisa diaktifkan lagi. Diisi
   sekarang, ia kembali dengan ambang yang sama seperti tetangganya; dilewati, ia kembali
   sebagai satu-satunya sumbu tanpa KKM dan tidak ada yang tahu kenapa.

   ── KENAPA HANYA YANG MASIH KOSONG ────────────────────────────────────────────
   Penyaring `Nilai_Ambang IS NULL` membuat skrip ini aman diulang, DAN menjaga agar
   menjalankannya ulang kelak tidak menghapus ambang yang sudah disetel HC per kompetensi.
   Tanpa penyaring itu, artefak ini berubah dari "mengisi yang kosong" menjadi
   "mengembalikan semuanya ke 80" — dan perbedaan itu baru terasa setelah pekerjaan
   penyetelan seseorang hilang tanpa jejak.
*/

UPDATE N_PA_Competency
SET Nilai_Ambang = 80.00,
    Updated_At = SYSDATETIME()
WHERE Flag_Berskor = 'Y'
  AND Nilai_Ambang IS NULL;
GO

/* Laporan sesudah pengisian.

   `berskor_tanpa_ambang` WAJIB nol. `kualitatif_berambang` juga wajib nol — kalau tidak,
   penyaring `Flag_Berskor` tidak bekerja dan ada standar yang menempel pada butir teks. */
SELECT
    SUM(CASE WHEN Flag_Berskor = 'Y' AND Nilai_Ambang IS NULL THEN 1 ELSE 0 END) AS berskor_tanpa_ambang,
    SUM(CASE WHEN Flag_Berskor = 'T' AND Nilai_Ambang IS NOT NULL THEN 1 ELSE 0 END) AS kualitatif_berambang,
    SUM(CASE WHEN Nilai_Ambang = 80.00 THEN 1 ELSE 0 END) AS berambang_80,
    COUNT(*) AS total
FROM N_PA_Competency;
GO
