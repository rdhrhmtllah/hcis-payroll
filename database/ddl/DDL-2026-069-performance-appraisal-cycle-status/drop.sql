/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   Foreign key dilepas lebih dulu karena N_PA_Cycle merujuk master ini. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50166, 'Manual reset 069 hanya ditujukan untuk Web_HRIS.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Cycle') AND name = 'FK_PA_Cycle_Status'
)
    ALTER TABLE N_PA_Cycle DROP CONSTRAINT FK_PA_Cycle_Status;

IF OBJECT_ID('N_PA_Cycle_Status', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Status;
