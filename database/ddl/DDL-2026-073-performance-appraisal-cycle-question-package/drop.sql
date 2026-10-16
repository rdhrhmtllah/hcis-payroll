/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   Item dilepas lebih dulu karena merujuk headernya. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50227, 'Manual reset 073 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle_Question_Package_Item', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Question_Package_Item;

IF OBJECT_ID('N_PA_Cycle_Question_Package', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Question_Package;
