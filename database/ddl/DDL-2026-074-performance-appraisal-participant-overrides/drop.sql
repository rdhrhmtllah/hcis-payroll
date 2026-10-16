/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50238, 'Manual reset 074 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Participant_Rater_Override', 'U') IS NOT NULL
    DROP TABLE N_PA_Participant_Rater_Override;

IF OBJECT_ID('N_PA_Participant_Pillar_Override', 'U') IS NOT NULL
    DROP TABLE N_PA_Participant_Pillar_Override;

IF OBJECT_ID('N_PA_Participant_Requirement_Override', 'U') IS NOT NULL
    DROP TABLE N_PA_Participant_Requirement_Override;
