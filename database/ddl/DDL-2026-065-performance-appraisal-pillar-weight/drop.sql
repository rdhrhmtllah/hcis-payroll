/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   It destroys PA pillar weight data and is not a production rollback strategy. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50115, 'Manual reset 065 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Pillar_Weight', 'U') IS NOT NULL
    DROP TABLE N_PA_Pillar_Weight;
