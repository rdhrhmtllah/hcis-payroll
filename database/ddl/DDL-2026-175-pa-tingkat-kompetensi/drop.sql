/* DDL-2026-175-pa-tingkat-kompetensi — bantuan reset MANUAL. Agen tidak menjalankan berkas ini. */
IF OBJECT_ID('N_PA_Cycle_Competency_Level_Snapshot', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Competency_Level_Snapshot;
IF OBJECT_ID('N_PA_Competency_Level_Requirement', 'U') IS NOT NULL DROP TABLE N_PA_Competency_Level_Requirement;
IF OBJECT_ID('N_PA_Competency_Proficiency', 'U') IS NOT NULL DROP TABLE N_PA_Competency_Proficiency;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-175';
GO
