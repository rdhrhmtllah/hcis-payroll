-- database/ddl/DDL-2026-014-riwayat-kepatuhan-sebab-aktor/drop.sql
--
-- BANTUAN PEMBERSIHAN MANUAL SAJA. Agen TIDAK PERNAH menjalankan berkas ini.
-- Menjalankannya MENGHAPUS data audit: sebab, catatan, dan identitas pelaku
-- pencabutan yang sudah terlanjur tercatat tidak dapat dipulihkan.

IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'IX_SOP_Rwt_Sebab' AND object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat'))
DROP INDEX IX_SOP_Rwt_Sebab ON N_SOP_Kepatuhan_Riwayat;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'IX_SOP_Rwt_Aktor' AND object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat'))
DROP INDEX IX_SOP_Rwt_Aktor ON N_SOP_Kepatuhan_Riwayat;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Aktor_Nama')
ALTER TABLE N_SOP_Kepatuhan_Riwayat DROP COLUMN Aktor_Nama;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Aktor_Id')
ALTER TABLE N_SOP_Kepatuhan_Riwayat DROP COLUMN Aktor_Id;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Catatan')
ALTER TABLE N_SOP_Kepatuhan_Riwayat DROP COLUMN Catatan;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Sebab')
ALTER TABLE N_SOP_Kepatuhan_Riwayat DROP COLUMN Sebab;
GO
