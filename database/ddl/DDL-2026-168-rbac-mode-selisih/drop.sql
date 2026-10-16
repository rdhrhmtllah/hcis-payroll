-- Change ID: DDL-2026-168-rbac-mode-selisih
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Tanpa tabel mode, semua modul kembali LAMA.
IF OBJECT_ID('dbo.N_HRIS_Akses_Selisih', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Akses_Selisih;
GO
IF OBJECT_ID('dbo.N_HRIS_Akses_Mode_Modul', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Akses_Mode_Modul;
GO
