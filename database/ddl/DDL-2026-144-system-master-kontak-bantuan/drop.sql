-- Change ID: DDL-2026-144-system-master-kontak-bantuan (manual cleanup only)
IF OBJECT_ID('dbo.N_HRIS_Master_Kontak_Bantuan', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Master_Kontak_Bantuan;
GO
