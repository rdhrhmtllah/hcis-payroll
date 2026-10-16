-- Change ID: DDL-2026-164-hris-akses-audit
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Menghapus seluruh jejak audit akses.
IF OBJECT_ID('dbo.N_HRIS_Akses_Audit', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Akses_Audit;
GO
