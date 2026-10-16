-- database/ddl/DDL-2026-093-penanda-metode-cap-kpi/forward.sql
--
-- Penanda eksplisit METODE CAP pada header KPI_Update_Performance.
--
-- MASALAH YANG DISELESAIKAN
--   Sejak 28 Agustus 2026 penjepitan skor pindah dari total akhir ke achievement
--   tiap parameter (50-125), dan jejaknya turun ke baris detail
--   (Score_Raw / Achievement_Raw / Flag_Capped, lihat DDL-2026-045).
--
--   Akibatnya `KPI_Update_Performance.Flag_Capped = 'Y'` bermakna GANDA:
--     - pada record lama  = "TOTAL-nya yang dipangkas"
--     - pada record baru  = "ada PARAMETER-nya yang dipangkas"
--
--   Sampai sekarang keduanya hanya bisa dibedakan secara TIDAK LANGSUNG, dengan
--   memeriksa apakah baris detailnya punya Score_Raw. Itu menyulitkan saat
--   backfill cap header dijalankan nanti, karena backfill harus tahu record mana
--   yang sudah memakai metode baru dan tidak boleh disentuh.
--
-- ISI KOLOM
--   'PARAMETER' -> record difinalisasi dengan cap per parameter (metode baru).
--                  Detailnya membawa Score_Raw / Achievement_Raw / Flag_Capped.
--   'TOTAL'     -> total header dijepit, baik oleh mekanisme cap total yang lama
--                  maupun oleh KpiCapBackfillService.
--   NULL        -> belum pernah disentuh mekanisme cap mana pun. Ini keadaan
--                  wajar untuk record lama yang totalnya kebetulan di dalam
--                  rentang, jadi NULL bukan penanda "belum diisi".
--
-- SIFAT BERKAS INI
--   Idempoten dan MENAMBAH saja. Nullable tanpa DEFAULT: sengaja, supaya record
--   lama tidak diklaim sebagai metode mana pun oleh DDL ini. Penetapan nilainya
--   untuk record lama ada di data-change.sql yang terpisah dan opsional.

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance]', N'Metode_Cap') IS NULL
    ALTER TABLE dbo.[KPI_Update_Performance] ADD [Metode_Cap] varchar(12) NULL;
GO

/* Jaga isinya tetap dua nilai yang dikenal. Dipasang hanya bila tidak ada baris
   yang melanggar, supaya berkas ini tetap aman dijalankan berulang kali dan
   tidak pernah gagal gara-gara data. */
IF COL_LENGTH('dbo.[KPI_Update_Performance]', N'Metode_Cap') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                    WHERE name = N'CK_KPI_Update_Performance_Metode_Cap'
                      AND parent_object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
   AND NOT EXISTS (SELECT 1 FROM dbo.[KPI_Update_Performance]
                    WHERE [Metode_Cap] IS NOT NULL
                      AND [Metode_Cap] NOT IN ('PARAMETER', 'TOTAL'))
    ALTER TABLE dbo.[KPI_Update_Performance] WITH CHECK
        ADD CONSTRAINT [CK_KPI_Update_Performance_Metode_Cap]
        CHECK ([Metode_Cap] IS NULL OR [Metode_Cap] IN ('PARAMETER', 'TOTAL'));
GO

/* Backfill cap membaca header per periode lalu menyaring yang belum bermetode
   PARAMETER. Indeks ini melayani penyaringan itu; tanpa isi Metode_Cap pun ia
   tetap kecil karena hanya menyimpan baris aktif. */
IF OBJECT_ID('dbo.[KPI_Update_Performance]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE name = N'IX_KPI_Update_Performance_MetodeCap_Periode'
                      AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
    CREATE NONCLUSTERED INDEX [IX_KPI_Update_Performance_MetodeCap_Periode]
        ON dbo.[KPI_Update_Performance] ([Flag_Aktif], [Metode_Cap], [Periode])
        INCLUDE ([Total_Score], [Flag_Capped]);
GO

/* selesai */
