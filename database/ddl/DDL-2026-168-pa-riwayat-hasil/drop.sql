/* DDL-2026-168-pa-riwayat-hasil — bantuan reset MANUAL. Agen tidak menjalankan berkas ini.
   PERHATIAN: mencabut tabel ini membuang seluruh riwayat angka hasil; tidak tersimpan di tempat lain. */
IF OBJECT_ID('N_PA_Result_History', 'U') IS NOT NULL DROP TABLE N_PA_Result_History;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-168';
GO
