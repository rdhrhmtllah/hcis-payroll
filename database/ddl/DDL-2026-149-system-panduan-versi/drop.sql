-- Change ID: DDL-2026-149-system-panduan-versi
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Berkas versi lama di GCS tidak ikut terhapus.
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_HRIS_Panduan_Versi_Aktif')
    ALTER TABLE dbo.N_HRIS_Panduan DROP CONSTRAINT FK_HRIS_Panduan_Versi_Aktif;
GO
IF COL_LENGTH('dbo.N_HRIS_Panduan', 'Versi_Aktif_Sejak') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Panduan DROP COLUMN Versi_Aktif_Sejak;
GO
IF COL_LENGTH('dbo.N_HRIS_Panduan', 'Id_Versi_Aktif') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Panduan DROP COLUMN Id_Versi_Aktif;
GO
IF OBJECT_ID('dbo.N_HRIS_Panduan_Versi', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Panduan_Versi;
GO
