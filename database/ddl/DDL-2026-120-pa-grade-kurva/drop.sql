/* Change ID: DDL-2026-120-pa-grade-kurva — PEMBATALAN. TIDAK DIJALANKAN AGEN.
   PERHATIAN: membuang seluruh jejak kurva; grade hasil yang sudah diberi dari kurva tetap
   tersimpan, tetapi asal angkanya tidak lagi bisa dijelaskan. */
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Cycle_Curve')
    ALTER TABLE N_PA_Result DROP CONSTRAINT FK_PA_Result_Cycle_Curve;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'PA_Cycle_Curve_Id')
    ALTER TABLE N_PA_Result DROP COLUMN PA_Cycle_Curve_Id;
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Band', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve_Band;
GO
IF OBJECT_ID('N_PA_Cycle_Curve', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve;
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Group_Level', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve_Group_Level;
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Group', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve_Group;
GO
IF OBJECT_ID('N_PA_Curve_Group_Level', 'U') IS NOT NULL DROP TABLE N_PA_Curve_Group_Level;
GO
IF OBJECT_ID('N_PA_Curve_Group', 'U') IS NOT NULL DROP TABLE N_PA_Curve_Group;
GO
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Cycle_Metode_Grade')
    ALTER TABLE N_PA_Cycle DROP CONSTRAINT CK_PA_Cycle_Metode_Grade;
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = 'DF_PA_Cycle_Metode_Grade')
    ALTER TABLE N_PA_Cycle DROP CONSTRAINT DF_PA_Cycle_Metode_Grade;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle') AND name = 'Metode_Grade')
    ALTER TABLE N_PA_Cycle DROP COLUMN Metode_Grade;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot DROP COLUMN Batas_Z;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Score_Range DROP COLUMN Batas_Z;
GO
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-120';
GO
