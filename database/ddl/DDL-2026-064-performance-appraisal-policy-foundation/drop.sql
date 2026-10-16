/* MANUAL ONLY. Never execute this file automatically.
   It destroys PA policy data and is not a production rollback strategy. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50101, 'Manual reset 064 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Rater_Policy_Detail', 'U') IS NOT NULL
    DROP TABLE N_PA_Rater_Policy_Detail;

IF OBJECT_ID('N_PA_Rater_Policy', 'U') IS NOT NULL
    DROP TABLE N_PA_Rater_Policy;

IF OBJECT_ID('N_PA_Rater_Category', 'U') IS NOT NULL
    DROP TABLE N_PA_Rater_Category;
