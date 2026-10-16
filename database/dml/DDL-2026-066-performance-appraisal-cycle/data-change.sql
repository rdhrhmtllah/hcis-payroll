/* Change ID: DDL-2026-066-performance-appraisal-cycle
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   TANPA sample cycle. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50125, 'DDL-2026-066 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
    THROW 50126, 'DDL-2026-066 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-066-performance-appraisal-cycle'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-066-performance-appraisal-cycle',
         'DDL',
         'Header siklus Performance Appraisal',
         'Membuat N_PA_Cycle sebagai header satu pelaksanaan appraisal dengan tahun, dua rentang tanggal, dan tanggal acuan organisasi. Tanggal boleh kosong selama DRAFT.',
         'database/ddl/DDL-2026-066-performance-appraisal-cycle/forward.sql',
         'docs/changes/DDL-2026-066-performance-appraisal-cycle/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Artifact revisi r2: kolom Tahun ditambahkan dan seluruh tanggal dilonggarkan jadi nullable supaya siklus DRAFT bisa dibuat sebelum jadwalnya ditetapkan. Berdiri sendiri. Tabel snapshot lapisan kedua menyusul dan akan ber-FK ke tabel ini.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
