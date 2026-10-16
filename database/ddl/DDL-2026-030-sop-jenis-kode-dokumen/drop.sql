/* Change ID: DDL-2026-030-sop-jenis-kode-dokumen — ROLLBACK

   TIDAK PERNAH dijalankan agen. Menjalankannya menghapus kode jenis dokumen
   seluruh baris master, dan setiap nomor dokumen yang sudah terbit kehilangan
   segmen pertamanya. */

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Master_Jenis_Dokumen') AND name = 'UX_SOP_Jenis_Kode_Jenis_Dokumen')
    DROP INDEX UX_SOP_Jenis_Kode_Jenis_Dokumen ON N_SOP_Master_Jenis_Dokumen;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Kode_Jenis_Dokumen')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen DROP COLUMN Kode_Jenis_Dokumen;
GO
