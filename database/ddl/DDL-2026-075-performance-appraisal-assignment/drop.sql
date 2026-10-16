/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50251, 'Manual reset 075 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Assignment_Integration', 'U') IS NOT NULL
    DROP TABLE N_PA_Assignment_Integration;

IF OBJECT_ID('N_PA_Assignment', 'U') IS NOT NULL
    DROP TABLE N_PA_Assignment;

IF OBJECT_ID('N_PA_Assignment_Status', 'U') IS NOT NULL
    DROP TABLE N_PA_Assignment_Status;

IF OBJECT_ID('N_PA_Assignment_Source', 'U') IS NOT NULL
    DROP TABLE N_PA_Assignment_Source;
