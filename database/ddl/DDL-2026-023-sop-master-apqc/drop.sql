-- database/ddl/DDL-2026-023-sop-master-apqc/drop.sql
-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Menjalankannya membuang pemetaan proses APQC dari SELURUH dokumen SOP.

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Dokumen_APQC' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
DROP INDEX IX_SOP_Dokumen_APQC ON N_SOP_Dokumen;
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_APQC')
ALTER TABLE N_SOP_Dokumen DROP CONSTRAINT FK_SOP_Dokumen_APQC;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'APQC_Id')
ALTER TABLE N_SOP_Dokumen DROP COLUMN APQC_Id;
GO
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_APQC_Tipe_Item')
DROP TABLE N_SOP_Master_APQC_Tipe_Item;
GO
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_APQC')
DROP TABLE N_SOP_Master_APQC;
GO
