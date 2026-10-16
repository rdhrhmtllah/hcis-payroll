/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50267, 'Manual reset 076 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Assignment_Score', 'U') IS NOT NULL
    DROP TABLE N_PA_Assignment_Score;

IF OBJECT_ID('N_PA_Assignment_Payload', 'U') IS NOT NULL
    DROP TABLE N_PA_Assignment_Payload;
