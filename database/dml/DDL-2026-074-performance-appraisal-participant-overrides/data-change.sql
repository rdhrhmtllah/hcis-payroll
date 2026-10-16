/* Change ID: DDL-2026-074-performance-appraisal-participant-overrides
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   TANPA seed: ketiga tabel hanya terisi ketika admin menyimpangkan seorang peserta
   dari salinan aturan siklusnya. Normalnya kosong. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50239, 'DDL-2026-074 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Participant_Rater_Override', 'U') IS NULL
   OR OBJECT_ID('N_PA_Participant_Pillar_Override', 'U') IS NULL
   OR OBJECT_ID('N_PA_Participant_Requirement_Override', 'U') IS NULL
    THROW 50240, 'DDL-2026-074 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-074-performance-appraisal-participant-overrides'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-074-performance-appraisal-participant-overrides',
         'DDL',
         'Tiga tabel override per peserta',
         'Membuat lapisan ketiga berbentuk JARANG: baris hanya lahir untuk peserta yang menyimpang dari salinan aturan siklusnya, sehingga jalur bakunya tidak mungkin salah.',
         'database/ddl/DDL-2026-074-performance-appraisal-participant-overrides/forward.sql',
         'docs/changes/DDL-2026-074-performance-appraisal-participant-overrides/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-064, 067, 071, dan 072. Resolusi salinan siklus dengan override HANYA boleh lewat PaParticipantRuleResolver; tidak ada tempat lain yang membaca tabel salinan untuk keperluan per orang.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
