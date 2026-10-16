-- database/ddl/DDL-2026-019-hris-master-entitas/drop.sql
-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Menjalankannya membuang kepemilikan dan ruang lingkup entitas dari SELURUH
-- dokumen SOP, beserta daftar badan hukumnya, dan tidak ada salinannya.

IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'IX_SOP_Dokumen_Lingkup' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
DROP INDEX IX_SOP_Dokumen_Lingkup ON N_SOP_Dokumen;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'IX_SOP_Dokumen_Entitas' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
DROP INDEX IX_SOP_Dokumen_Entitas ON N_SOP_Dokumen;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Entitas_Lingkup')
ALTER TABLE N_SOP_Dokumen DROP CONSTRAINT FK_SOP_Dokumen_Entitas_Lingkup;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Entitas_Pemilik')
ALTER TABLE N_SOP_Dokumen DROP CONSTRAINT FK_SOP_Dokumen_Entitas_Pemilik;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Entitas_Lingkup_Id')
ALTER TABLE N_SOP_Dokumen DROP COLUMN Entitas_Lingkup_Id;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Entitas_Pemilik_Id')
ALTER TABLE N_SOP_Dokumen DROP COLUMN Entitas_Pemilik_Id;
GO

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Master_Entitas')
DROP TABLE N_HRIS_Master_Entitas;
GO
