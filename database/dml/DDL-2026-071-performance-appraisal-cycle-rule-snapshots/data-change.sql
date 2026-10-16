/* Change ID: DDL-2026-071-performance-appraisal-cycle-rule-snapshots
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   TANPA seed: kelima tabel diisi aplikasi saat siklus disiapkan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50201, 'DDL-2026-071 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot', 'U') IS NULL
    THROW 50202, 'DDL-2026-071 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-071-performance-appraisal-cycle-rule-snapshots'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-071-performance-appraisal-cycle-rule-snapshots',
         'DDL',
         'Lima salinan aturan milik siklus Performance Appraisal',
         'Membuat lapisan kedua: salinan Rater Policy beserta detailnya, bobot pilar, kebutuhan kompetensi, dan pita nilai untuk satu siklus.',
         'database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/forward.sql',
         'docs/changes/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-064, 065, 066, 067, dan 070; jalankan sesudah semuanya. Empat salinan membeku saat siklus dikunci, kecuali bobot pilar yang sengaja tetap boleh diubah (K23). Tanpa seed.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
