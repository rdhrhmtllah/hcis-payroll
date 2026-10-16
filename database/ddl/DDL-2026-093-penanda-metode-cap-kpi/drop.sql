-- database/ddl/DDL-2026-093-penanda-metode-cap-kpi/drop.sql
--
-- ALAT BANTU MANUAL. Jangan dijalankan sebagai bagian rilis.
--
-- Membalik forward.sql untuk keperluan reset di DEVELOPMENT. Menghapus kolom
-- Metode_Cap MENGHILANGKAN isinya secara permanen — termasuk penanda record yang
-- sudah difinalisasi dengan cap per parameter, yang tidak bisa dipulihkan selain
-- dengan menurunkannya ulang dari jejak Score_Raw di tabel detail.

SET NOCOUNT ON;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = N'IX_KPI_Update_Performance_MetodeCap_Periode'
              AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
    DROP INDEX [IX_KPI_Update_Performance_MetodeCap_Periode] ON dbo.[KPI_Update_Performance];
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints
            WHERE name = N'CK_KPI_Update_Performance_Metode_Cap'
              AND parent_object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
    ALTER TABLE dbo.[KPI_Update_Performance] DROP CONSTRAINT [CK_KPI_Update_Performance_Metode_Cap];
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance]', N'Metode_Cap') IS NOT NULL
    ALTER TABLE dbo.[KPI_Update_Performance] DROP COLUMN [Metode_Cap];
GO

/* selesai */
