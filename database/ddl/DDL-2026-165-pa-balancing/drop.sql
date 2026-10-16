/* DDL-2026-165-pa-balancing — bantuan reset MANUAL. Agen tidak menjalankan berkas ini. */
IF OBJECT_ID('N_PA_Balancing_Jejak_Hitung', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Jejak_Hitung;
IF OBJECT_ID('N_PA_Balancing_Kualitatif', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Kualitatif;
IF OBJECT_ID('N_PA_Balancing_Kompetensi', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Kompetensi;
IF OBJECT_ID('N_PA_Balancing_Peserta', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Peserta;
IF OBJECT_ID('N_PA_Balancing_Forum', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Forum;
IF OBJECT_ID('N_PA_Balancing_Sesi', 'U') IS NOT NULL DROP TABLE N_PA_Balancing_Sesi;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-165';
GO
