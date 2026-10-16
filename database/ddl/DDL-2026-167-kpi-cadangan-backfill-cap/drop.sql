-- Change ID: DDL-2026-167-kpi-cadangan-backfill-cap
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Menghapus cadangan berarti
-- backfill yang sudah jalan TIDAK BISA lagi di-rollback.
IF OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan', 'U') IS NOT NULL
    DROP TABLE dbo.N_KPI_Backfill_Cap_Cadangan;
GO
