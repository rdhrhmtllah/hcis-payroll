-- Change ID: DDL-2026-181-rbac-tingkat-peran
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Tanpa master, aplikasi memakai tingkat bawaan.
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_HRIS_Role_Functional_Tingkat')
    ALTER TABLE dbo.N_HRIS_Role_Functional DROP CONSTRAINT FK_HRIS_Role_Functional_Tingkat;
GO
IF COL_LENGTH('dbo.N_HRIS_Role_Functional', 'Kode_Tingkat') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Role_Functional DROP COLUMN Kode_Tingkat;
GO
IF OBJECT_ID('dbo.N_HRIS_Tingkat_Peran', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Tingkat_Peran;
GO
