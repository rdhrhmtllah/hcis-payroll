/* Change ID: DDL-2026-038-sop-versi-review — ROLLBACK

   TIDAK PERNAH dijalankan agen. Menjalankannya menghapus seluruh catatan review
   beserta lampirannya — isi yang diketik manusia dan tak ada di tempat lain.
   Berkas di GCS tidak ikut terhapus dan akan menjadi yatim. */

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Item_Versi_Review_Lampiran')
    DROP TABLE N_SOP_Item_Versi_Review_Lampiran;
GO
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Item_Versi_Review')
    DROP TABLE N_SOP_Item_Versi_Review;
GO
