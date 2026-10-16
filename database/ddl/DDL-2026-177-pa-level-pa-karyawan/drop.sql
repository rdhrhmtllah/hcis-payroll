/* DDL-2026-177-pa-level-pa-karyawan — bantuan reset MANUAL. Agen tidak menjalankan berkas ini. */
IF COL_LENGTH('N_PA_Participant', 'PA_Karyawan_Level_PA_Id') IS NOT NULL
BEGIN
    ALTER TABLE N_PA_Participant DROP CONSTRAINT FK_PA_Participant_Karyawan_Level_PA;
    ALTER TABLE N_PA_Participant DROP COLUMN PA_Karyawan_Level_PA_Id;
END;
IF COL_LENGTH('N_PA_Participant', 'Nama_Level_HRIS_Saat_Cycle') IS NOT NULL ALTER TABLE N_PA_Participant DROP COLUMN Nama_Level_HRIS_Saat_Cycle;
IF COL_LENGTH('N_PA_Participant', 'ID_Level_HRIS_Saat_Cycle') IS NOT NULL ALTER TABLE N_PA_Participant DROP COLUMN ID_Level_HRIS_Saat_Cycle;
IF OBJECT_ID('N_PA_Karyawan_Level_PA', 'U') IS NOT NULL DROP TABLE N_PA_Karyawan_Level_PA;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-177';
GO
