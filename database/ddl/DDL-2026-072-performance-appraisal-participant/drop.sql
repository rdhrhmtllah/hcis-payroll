/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   Peserta dilepas lebih dulu karena ia merujuk master statusnya. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50217, 'Manual reset 072 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Participant', 'U') IS NOT NULL
    DROP TABLE N_PA_Participant;

IF OBJECT_ID('N_PA_Participant_Status', 'U') IS NOT NULL
    DROP TABLE N_PA_Participant_Status;
