/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   Foreign key dilepas lebih dulu karena tabel lain merujuk ketiga master ini. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50180, 'Manual reset 070 hanya ditujukan untuk Web_HRIS.', 1;

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Rater_Policy_Status')
    ALTER TABLE N_PA_Rater_Policy DROP CONSTRAINT FK_PA_Rater_Policy_Status;

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Score_Range_Type')
    ALTER TABLE N_PA_Score_Range DROP CONSTRAINT FK_PA_Score_Range_Type;

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Score_Range_Usage')
    ALTER TABLE N_PA_Score_Range DROP CONSTRAINT FK_PA_Score_Range_Usage;

IF OBJECT_ID('N_PA_Rater_Policy_Status', 'U') IS NOT NULL DROP TABLE N_PA_Rater_Policy_Status;
IF OBJECT_ID('N_PA_Score_Type', 'U') IS NOT NULL DROP TABLE N_PA_Score_Type;
IF OBJECT_ID('N_PA_Score_Usage', 'U') IS NOT NULL DROP TABLE N_PA_Score_Usage;
