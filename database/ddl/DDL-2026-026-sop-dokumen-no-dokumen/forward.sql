-- database/ddl/DDL-2026-026-sop-dokumen-no-dokumen/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-026-sop-dokumen-no-dokumen/README.md
--
-- Menambah satu kolom. Tidak ada yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'No_Dokumen')
ALTER TABLE N_SOP_Dokumen ADD No_Dokumen int NULL;
GO
