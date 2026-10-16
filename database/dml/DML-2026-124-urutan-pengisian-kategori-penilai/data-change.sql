/* Change ID: DML-2026-124-urutan-pengisian-kategori-penilai
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-119-pa-kalibrasi-penilai.
   Scope: nilai awal N_PA_Rater_Category.Urutan_Pengisian. Dikonfirmasi pemilik proyek
          18 September 2026: SELF 1, ATASAN 2, PEER 3, BAWAHAN 4.

   Satu pernyataan UPDATE ... CASE: indeks unik UX_PA_Rater_Category_Urutan diperiksa per
   pernyataan, sehingga menukar posisi di dalam satu pernyataan tidak menabraknya.
   Penyaring "Urutan_Pengisian IS NULL" membuatnya aman diulang, dan NOT EXISTS membuatnya
   tidak mencampurkan urutan bawaan ke atas urutan yang sudah ditata admin lewat layar.
*/

UPDATE N_PA_Rater_Category
SET Urutan_Pengisian = CASE Kode_Kategori
        WHEN 'SELF' THEN 1
        WHEN 'ATASAN' THEN 2
        WHEN 'PEER' THEN 3
        WHEN 'BAWAHAN' THEN 4
    END,
    Updated_At = sysdatetime()
WHERE Kode_Kategori IN ('SELF', 'ATASAN', 'PEER', 'BAWAHAN')
  AND Urutan_Pengisian IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM N_PA_Rater_Category x
      WHERE x.Urutan_Pengisian IS NOT NULL AND x.Flag_Aktif = 'Y'
  );
GO

SELECT Kode_Kategori, Urutan_Pengisian, Flag_Boleh_Kalibrasi
FROM N_PA_Rater_Category
ORDER BY Urutan_Pengisian;
GO
