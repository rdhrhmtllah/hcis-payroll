/* Change ID: DML-2026-121-kompetensi-kualitatif-keluar-dari-radar
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-096 (kolom Flag_Radar pada N_PA_Competency).
   Scope: mengeluarkan kompetensi TAK BERSKOR dari sumbu radar.

   ── KENAPA PERLU ──────────────────────────────────────────────────────────────
   DDL-2026-096 memberi `Flag_Radar` default 'Y', dan default itu mengenai SEMUA baris
   lama — termasuk butir kualitatif (`Flag_Berskor = 'T'`): Strength, Development Area,
   Initiative, Recommendation. Keempatnya membawa teks, bukan angka, sehingga tidak
   pernah punya baris nilai di `N_PA_Result_Competency`.

   Dibiarkan 'Y', mereka menjadi sumbu radar yang nilainya selamanya kosong. Ini persis
   kegagalan yang dulu melahirkan `Flag_Berskor` (lihat "Physical decisions" pada README
   DDL-2026-067): tanpa penanda, pemeta merata-ratakan butir kualitatif sebagai nol dan
   diam-diam menyeret skor kompetensi ke bawah.

   Default 'Y' di DDL tetap pilihan yang benar — ia menjaga agar radar yang sudah ada
   tidak berubah bentuk hanya karena kolomnya lahir. Pengecualiannya justru harus
   dinyatakan di sini, sebagai pernyataan tentang DATA, bukan disembunyikan sebagai
   kecerdasan di dalam default kolom.

   ── SIFATNYA ──────────────────────────────────────────────────────────────────
   Aman diulang: hanya menyentuh baris yang masih 'Y' padahal tak berskor. Harapan pada
   basis data pengembangan adalah 4 baris; kalau angkanya berbeda, kamusnya sudah berubah
   sejak DDL-2026-067 dan itu harus dilihat dulu, bukan dilewati.
*/

UPDATE N_PA_Competency
SET Flag_Radar = 'T',
    Updated_At = SYSDATETIME()
WHERE Flag_Berskor = 'T'
  AND Flag_Radar = 'Y';
GO

/* Laporan sesudah pengisian.

   `kualitatif_di_radar` WAJIB nol — itu satu-satunya angka yang dijamin perubahan ini.
   `berskor_tanpa_ambang` bukan kegagalan: ia menghitung berapa kompetensi yang masuk
   radar tetapi KKM-nya belum ditetapkan HC. Sumbu itu tetap digambar pada jaring nilai,
   hanya tidak punya pasangan pada jaring KKM. */
SELECT
    SUM(CASE WHEN Flag_Berskor = 'T' AND Flag_Radar = 'Y' THEN 1 ELSE 0 END) AS kualitatif_di_radar,
    SUM(CASE WHEN Flag_Berskor = 'Y' AND Flag_Radar = 'Y' THEN 1 ELSE 0 END) AS sumbu_radar,
    SUM(CASE WHEN Flag_Berskor = 'Y' AND Flag_Radar = 'Y' AND Nilai_Ambang IS NULL THEN 1 ELSE 0 END) AS berskor_tanpa_ambang
FROM N_PA_Competency
WHERE Flag_Aktif = 'Y';
GO
