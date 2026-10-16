/* DDL-2026-169-pa-waktu-kirim-penilai — bantuan reset MANUAL. Agen tidak menjalankan berkas ini.
   PERHATIAN: mencabut kolom ini membuang catatan waktu dan pelaku pengiriman penilai. */
IF COL_LENGTH('N_PA_Assignment', 'Submitted_Via') IS NOT NULL ALTER TABLE N_PA_Assignment DROP COLUMN Submitted_Via;
IF COL_LENGTH('N_PA_Assignment', 'Submitted_By') IS NOT NULL ALTER TABLE N_PA_Assignment DROP COLUMN Submitted_By;
IF COL_LENGTH('N_PA_Assignment', 'Submitted_At') IS NOT NULL ALTER TABLE N_PA_Assignment DROP COLUMN Submitted_At;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-169';
GO
