/* Change ID: DDL-2026-070-performance-appraisal-status-and-axis-masters
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Seed ketiga master ada di forward.sql, bukan di sini, karena foreign key pada
   change yang sama menolak dipasang sebelum masternya terisi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50181, 'DDL-2026-070 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Rater_Policy_Status', 'U') IS NULL
   OR OBJECT_ID('N_PA_Score_Type', 'U') IS NULL
   OR OBJECT_ID('N_PA_Score_Usage', 'U') IS NULL
    THROW 50182, 'DDL-2026-070 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-070-performance-appraisal-status-and-axis-masters'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-070-performance-appraisal-status-and-axis-masters',
         'DDL',
         'Master status policy, sumbu, dan kegunaan pita nilai',
         'Membuat tiga master kamus tertutup lalu memasang foreign key dari Status_Policy, Score_Type, dan Score_Usage yang selama ini berupa varchar bebas.',
         'database/ddl/DDL-2026-070-performance-appraisal-status-and-axis-masters/forward.sql',
         'docs/changes/DDL-2026-070-performance-appraisal-status-and-axis-masters/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-064 dan DDL-2026-067; jalankan sesudah keduanya. Menutup tiga kolom terakhir di lapisan fondasi yang nilainya belum pernah ditetapkan di mana pun.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
