-- Change ID: DDL-2026-184-rbac-cakupan-template
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Penugasan bercakupan TEMPLATE menjadi
-- tidak mencakup siapa pun (gagal tertutup) setelah tabel dihapus.
IF OBJECT_ID('dbo.N_HRIS_Cakupan_Template_Syarat', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Cakupan_Template_Syarat;
GO
IF OBJECT_ID('dbo.N_HRIS_Cakupan_Template', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Cakupan_Template;
GO
