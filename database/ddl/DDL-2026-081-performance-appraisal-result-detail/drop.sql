/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50330, 'Manual reset 081 hanya ditujukan untuk Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Result_Note', 'U') IS NOT NULL
    DROP TABLE N_PA_Result_Note;

IF OBJECT_ID('N_PA_Calibration_History', 'U') IS NOT NULL
    DROP TABLE N_PA_Calibration_History;

IF OBJECT_ID('N_PA_Result_Competency', 'U') IS NOT NULL
    DROP TABLE N_PA_Result_Competency;

IF OBJECT_ID('N_PA_Result_Rater_Category', 'U') IS NOT NULL
    DROP TABLE N_PA_Result_Rater_Category;

/* Kolom yang ditambahkan amandemen r3. Dibuang lebih dulu supaya drop ini tetap benar
   bila kelak tabelnya dipertahankan dan hanya kolomnya yang dikembalikan. */
IF OBJECT_ID('N_PA_Calibration_History', 'U') IS NOT NULL
   AND COL_LENGTH('N_PA_Calibration_History', 'Performed_By_Nama') IS NOT NULL
    ALTER TABLE N_PA_Calibration_History DROP COLUMN Performed_By_Nama;
GO
