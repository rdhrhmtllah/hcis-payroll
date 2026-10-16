-- database/ddl/DDL-2026-026-sop-dokumen-no-dokumen/drop.sql
-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Menjalankannya membuang nomor urut dokumen yang sebagiannya mungkin sudah
-- tercetak di luar sistem.

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'No_Dokumen')
ALTER TABLE N_SOP_Dokumen DROP COLUMN No_Dokumen;
GO
