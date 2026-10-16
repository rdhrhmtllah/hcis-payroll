-- Change ID: DDL-2026-148-system-panduan-sasaran-role
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Menghapus sasaran role panduan.
IF OBJECT_ID('dbo.N_HRIS_Panduan_Role', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Panduan_Role;
GO
