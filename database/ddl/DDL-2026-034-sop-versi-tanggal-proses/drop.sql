/* Change ID: DDL-2026-034-sop-versi-tanggal-proses — ROLLBACK

   TIDAK PERNAH dijalankan agen. Menjalankannya menghapus tanggal permintaan,
   respon, dan akhir approval seluruh versi — jejak waktu yang tak bisa
   direkonstruksi dari mana pun kecuali log, dan log tak menyimpan tanggal yang
   diketik ulang admin. */

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi' AND COLUMN_NAME = 'Cap_Tanggal')
    ALTER TABLE N_SOP_Master_Tahap_Versi DROP COLUMN Cap_Tanggal;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Akhir_Approval')
    ALTER TABLE N_SOP_Item_Versi DROP COLUMN Tanggal_Akhir_Approval;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Respon')
    ALTER TABLE N_SOP_Item_Versi DROP COLUMN Tanggal_Respon;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Permintaan')
    ALTER TABLE N_SOP_Item_Versi DROP COLUMN Tanggal_Permintaan;
GO
