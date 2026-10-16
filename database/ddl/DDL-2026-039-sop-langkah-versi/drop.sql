/* Change ID: DDL-2026-039-sop-langkah-versi — ROLLBACK
   TIDAK PERNAH dijalankan agen. Menghapus Jenis akan meleburkan rencana dan
   realisasi pengesah menjadi satu daftar yang tak bisa dipisahkan lagi. */
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi_Syarat')
    DROP TABLE N_SOP_Master_Tahap_Versi_Syarat;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi_Pengesah') AND name = 'IX_SOP_Pengesah_Jenis')
    DROP INDEX IX_SOP_Pengesah_Jenis ON N_SOP_Item_Versi_Pengesah;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi_Pengesah' AND COLUMN_NAME = 'Jenis')
BEGIN
    ALTER TABLE N_SOP_Item_Versi_Pengesah DROP CONSTRAINT DF_SOP_Pengesah_Jenis;
    ALTER TABLE N_SOP_Item_Versi_Pengesah DROP COLUMN Jenis;
END;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi' AND COLUMN_NAME = 'Kelompok')
    ALTER TABLE N_SOP_Master_Tahap_Versi DROP COLUMN Kelompok;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi' AND COLUMN_NAME = 'Langkah')
    ALTER TABLE N_SOP_Master_Tahap_Versi DROP COLUMN Langkah;
GO
