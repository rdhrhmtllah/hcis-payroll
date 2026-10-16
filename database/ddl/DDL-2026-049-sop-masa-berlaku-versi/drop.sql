/* Change ID: DDL-2026-049-sop-masa-berlaku-versi -- pembersihan manual.
   Tidak boleh dijalankan agen. */

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Review_Berikutnya')
    ALTER TABLE N_SOP_Item_Versi DROP COLUMN Tanggal_Review_Berikutnya;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Masa_Berlaku_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen DROP COLUMN Masa_Berlaku_Bulan;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Siklus_Review_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen DROP COLUMN Siklus_Review_Bulan;
GO

DELETE FROM N_SOP_Master_Tahap_Versi_Syarat
WHERE Kode_Syarat IN ('TANGGAL_BERLAKU', 'TANGGAL_KADALUARSA', 'TANGGAL_REVIEW_BERIKUTNYA');
GO
