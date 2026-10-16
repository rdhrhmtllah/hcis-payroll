/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50153, 'Manual reset 068 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Hav_Quadrant', 'U') IS NOT NULL
    DROP TABLE N_PA_Hav_Quadrant;
