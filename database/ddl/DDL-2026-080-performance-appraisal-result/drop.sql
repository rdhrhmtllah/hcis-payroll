/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50310, 'Manual reset 080 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Result', 'U') IS NOT NULL
    DROP TABLE N_PA_Result;

IF OBJECT_ID('N_PA_Result_Status', 'U') IS NOT NULL
    DROP TABLE N_PA_Result_Status;

IF OBJECT_ID('N_PA_Result_Note_Type', 'U') IS NOT NULL
    DROP TABLE N_PA_Result_Note_Type;
