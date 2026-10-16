-- database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/drop.sql
-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Menjalankannya membuang klasifikasi hirarki dan kepemilikan unit dari
-- SELURUH dokumen SOP, dan tidak ada salinannya di tempat lain.

IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'IX_SOP_Dokumen_Pemilik' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
DROP INDEX IX_SOP_Dokumen_Pemilik ON N_SOP_Dokumen;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'IX_SOP_Dokumen_Jenis' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
DROP INDEX IX_SOP_Dokumen_Jenis ON N_SOP_Dokumen;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Jenis')
ALTER TABLE N_SOP_Dokumen DROP CONSTRAINT FK_SOP_Dokumen_Jenis;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Sub_Divisi_Id')
ALTER TABLE N_SOP_Dokumen DROP COLUMN Sub_Divisi_Id;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Divisi_Id')
ALTER TABLE N_SOP_Dokumen DROP COLUMN Divisi_Id;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Jenis_Dokumen_Id')
ALTER TABLE N_SOP_Dokumen DROP COLUMN Jenis_Dokumen_Id;
GO

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_Jenis_Dokumen_Tipe_Item')
DROP TABLE N_SOP_Master_Jenis_Dokumen_Tipe_Item;
GO

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_Jenis_Dokumen')
DROP TABLE N_SOP_Master_Jenis_Dokumen;
GO
