/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   Urutan mengikuti ketergantungan FK: detail sebelum headernya. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50200, 'Manual reset 071 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Rater_Policy_Detail_Snapshot;

IF OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Rater_Policy_Snapshot;

IF OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Pillar_Weight_Snapshot;

IF OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Competency_Requirement_Snapshot;

IF OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Score_Range_Snapshot;
