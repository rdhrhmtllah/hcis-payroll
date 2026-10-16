/* DDL-2026-183-pa-master-level — bantuan reset MANUAL. Agen tidak menjalankan berkas ini.
   Jalankan drop DDL-2026-185 lebih dulu (FK yang menunjuk tabel ini). */
IF OBJECT_ID('N_PA_Level', 'U') IS NOT NULL DROP TABLE N_PA_Level;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-183';
GO
