/* DDL-2026-191-pa-kelayakan-masa-kerja — bantuan reset MANUAL. Agen tidak menjalankan berkas ini. */
IF COL_LENGTH('N_PA_Cycle', 'Min_Masa_Kerja_Bulan') IS NOT NULL ALTER TABLE N_PA_Cycle DROP COLUMN Min_Masa_Kerja_Bulan;
IF OBJECT_ID('N_PA_Eligibility_Setting', 'U') IS NOT NULL DROP TABLE N_PA_Eligibility_Setting;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-191';
GO
