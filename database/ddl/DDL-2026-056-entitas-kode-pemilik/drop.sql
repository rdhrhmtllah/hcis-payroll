/* Change ID: DDL-2026-056-entitas-kode-pemilik
   Berkas bantu pembersihan manual. JANGAN dijalankan oleh agent.

   PERHATIAN. Menjatuhkan kolom ini mengembalikan segmen pemilik ke
   Kode_Entitas, sehingga dokumen milik holding kembali tercetak "EHG".
   Nomor_SOP_Resmi yang SUDAH tersimpan tidak ikut berubah sendiri — ia baru
   ditulis ulang saat segarkanNomorResmi() berjalan. Jalankan
   `php artisan sop:segarkan-nomor` sesudahnya, atau daftar dan detail akan
   menyebut nomor yang berbeda dari yang tersimpan. */

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_HRIS_Master_Entitas' AND COLUMN_NAME = 'Kode_Sebagai_Pemilik')
    ALTER TABLE N_HRIS_Master_Entitas DROP COLUMN Kode_Sebagai_Pemilik;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-056-entitas-kode-pemilik';
GO
