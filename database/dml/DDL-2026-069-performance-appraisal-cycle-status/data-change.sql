/* Change ID: DDL-2026-069-performance-appraisal-cycle-status
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Seed tujuh status berada di forward.sql, bukan di sini, karena foreign key pada
   change yang sama menolak dipasang sebelum masternya terisi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50167, 'DDL-2026-069 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle_Status', 'U') IS NULL
    THROW 50168, 'DDL-2026-069 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-069-performance-appraisal-cycle-status'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-069-performance-appraisal-cycle-status',
         'DDL',
         'Master status siklus Performance Appraisal',
         'Membuat N_PA_Cycle_Status dengan tujuh status, lalu memasang foreign key dari N_PA_Cycle.Status_Cycle ke master tersebut.',
         'database/ddl/DDL-2026-069-performance-appraisal-cycle-status/forward.sql',
         'docs/changes/DDL-2026-069-performance-appraisal-cycle-status/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-066 dan harus dijalankan sesudahnya. FK menunjuk kolom kode, bukan Id, supaya default DRAFT tetap bekerja dan baris tetap terbaca manusia.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
