-- Change ID: DDL-2026-146-system-panduan-manual (manual cleanup only)
IF OBJECT_ID('dbo.N_HRIS_Panduan_Halaman', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Panduan_Halaman;
GO
IF OBJECT_ID('dbo.N_HRIS_Panduan', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Panduan;
GO
