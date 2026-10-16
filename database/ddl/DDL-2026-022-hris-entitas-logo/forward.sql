-- database/ddl/DDL-2026-022-hris-entitas-logo/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-022-hris-entitas-logo/README.md
--
-- Menambah satu kolom. Tidak ada yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_HRIS_Master_Entitas') AND name = 'Logo_Path')
ALTER TABLE N_HRIS_Master_Entitas ADD Logo_Path varchar(255) NULL;
GO
