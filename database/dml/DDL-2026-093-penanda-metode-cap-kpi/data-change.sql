-- database/dml/DDL-2026-093-penanda-metode-cap-kpi/data-change.sql
--
-- OPSIONAL. Mengisi Metode_Cap untuk record yang SUDAH ADA.
-- Jalankan forward.sql lebih dulu.
--
-- Kalau berkas ini tidak dijalankan, semua record lama tetap NULL dan penanda
-- hanya berlaku maju. Itu pilihan yang sah: backfill cap header tetap aman
-- karena NULL memang berarti "belum pernah disentuh cap mana pun". Berkas ini
-- hanya membuat keadaan yang sudah terjadi menjadi tercatat eksplisit.
--
-- DARI MANA NILAINYA DITURUNKAN
--   'PARAMETER' bila baris detail record itu punya Score_Raw yang terisi. Hanya
--               jalur finalisasi metode baru yang menulis kolom itu, jadi
--               keberadaannya adalah bukti langsung.
--   'TOTAL'     bila header ber-Flag_Capped='Y' TETAPI detailnya tidak punya
--               Score_Raw sama sekali — artinya yang dulu dipangkas adalah
--               totalnya.
--   selain itu  dibiarkan NULL. Record yang tidak pernah kena cap tidak boleh
--               diklaim memakai metode mana pun.
--
-- SIFAT BERKAS INI
--   Idempoten: hanya menyentuh baris yang Metode_Cap-nya masih NULL, jadi
--   menjalankannya dua kali tidak mengubah apa pun pada putaran kedua dan tidak
--   akan pernah menimpa penanda yang sudah ditulis aplikasi.
--
--   TIDAK menyentuh Score_Raw, Flag_Capped, Total_Score, maupun Id_Performance.
--   Khususnya Score_Raw di tabel detail: mengisinya dengan nol akan menghapus
--   satu-satunya pembeda yang kita punya sekarang.

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

/* 1. Record yang detailnya membawa jejak cap per parameter. */
UPDATE h
   SET h.[Metode_Cap] = 'PARAMETER'
  FROM dbo.[KPI_Update_Performance] AS h
 WHERE h.[Metode_Cap] IS NULL
   AND EXISTS (
        SELECT 1
          FROM dbo.[KPI_Update_Performance_Detail] AS d
         WHERE d.[Id_Update_Performance] = h.[Id_Update_Performance]
           AND d.[Score_Raw] IS NOT NULL
   );
GO

/* 2. Record yang totalnya pernah dipangkas, tanpa jejak per parameter. */
UPDATE h
   SET h.[Metode_Cap] = 'TOTAL'
  FROM dbo.[KPI_Update_Performance] AS h
 WHERE h.[Metode_Cap] IS NULL
   AND h.[Flag_Capped] = 'Y'
   AND NOT EXISTS (
        SELECT 1
          FROM dbo.[KPI_Update_Performance_Detail] AS d
         WHERE d.[Id_Update_Performance] = h.[Id_Update_Performance]
           AND d.[Score_Raw] IS NOT NULL
   );
GO

/* 3. Laporan hasil — jalankan untuk memverifikasi, tidak mengubah apa pun. */
SELECT ISNULL([Metode_Cap], '(NULL - belum pernah kena cap)') AS Metode_Cap,
       COUNT(*) AS Jumlah
  FROM dbo.[KPI_Update_Performance]
 WHERE [Flag_Aktif] = 'Y'
 GROUP BY [Metode_Cap]
 ORDER BY 1;
GO

/* selesai */
