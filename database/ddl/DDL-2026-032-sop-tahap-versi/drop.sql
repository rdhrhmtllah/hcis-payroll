/* Change ID: DDL-2026-032-sop-tahap-versi — ROLLBACK

   TIDAK PERNAH dijalankan agen. Menjalankannya menghapus seluruh jejak tahap
   proses setiap versi; Status teknisnya selamat, tetapi riwayat "siapa menahan
   di Approval berapa lama" hilang tanpa sisa. */

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Item_Versi_Tahap')
    ALTER TABLE N_SOP_Item_Versi DROP CONSTRAINT FK_SOP_Item_Versi_Tahap;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi') AND name = 'IX_SOP_Item_Versi_Tahap')
    DROP INDEX IX_SOP_Item_Versi_Tahap ON N_SOP_Item_Versi;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tahap_Id')
    ALTER TABLE N_SOP_Item_Versi DROP COLUMN Tahap_Id;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi_Transisi')
    DROP TABLE N_SOP_Master_Tahap_Versi_Transisi;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi')
    DROP TABLE N_SOP_Master_Tahap_Versi;
GO
