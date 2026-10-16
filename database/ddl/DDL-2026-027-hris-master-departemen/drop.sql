-- database/ddl/DDL-2026-027-hris-master-departemen/drop.sql
-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Menjalankannya membuang kepemilikan departemen seluruh dokumen SOP beserta
-- daftar kode yang menjadi segmen nomor dokumen.

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Dokumen_Departemen' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
DROP INDEX IX_SOP_Dokumen_Departemen ON N_SOP_Dokumen;
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Departemen')
ALTER TABLE N_SOP_Dokumen DROP CONSTRAINT FK_SOP_Dokumen_Departemen;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Departemen_Id')
ALTER TABLE N_SOP_Dokumen DROP COLUMN Departemen_Id;
GO
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Master_Departemen')
DROP TABLE N_HRIS_Master_Departemen;
GO
