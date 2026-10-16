/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   It destroys PA cycle data and is not a production rollback strategy. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50124, 'Manual reset 066 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle;
