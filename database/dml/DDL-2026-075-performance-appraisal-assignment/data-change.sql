/* Change ID: DDL-2026-075-performance-appraisal-assignment
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Seed kedua master ada di forward.sql, bukan di sini, karena foreign key pada
   change yang sama menolak dipasang sebelum masternya terisi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50252, 'DDL-2026-075 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Assignment_Status', 'U') IS NULL
   OR OBJECT_ID('N_PA_Assignment_Source', 'U') IS NULL
   OR OBJECT_ID('N_PA_Assignment', 'U') IS NULL
   OR OBJECT_ID('N_PA_Assignment_Integration', 'U') IS NULL
    THROW 50253, 'DDL-2026-075 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-075-performance-appraisal-assignment'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-075-performance-appraisal-assignment',
         'DDL',
         'Penugasan penilai PA dan tabel integrasi HC Learn',
         'Membuat master status dan sumber penugasan, tabel N_PA_Assignment sebagai penugasan satu penilai untuk satu peserta, dan N_PA_Assignment_Integration yang memegang token ujian HC Learn.',
         'database/ddl/DDL-2026-075-performance-appraisal-assignment/forward.sql',
         'docs/changes/DDL-2026-075-performance-appraisal-assignment/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-061, 064, 072, dan 073; jalankan sesudah semuanya. Token ujian sengaja TIDAK menjadi kolom N_PA_Assignment melainkan tabel integrasi tersendiri, mengikuti N_LMS_Penjadwalan_Integrasi dan N_SOP_Penjadwalan_Integrasi. Jalur sumber USULAN belum dapat diuji sungguhan karena N_HRIS_Organization_Relationship masih kosong sampai endpoint konverter K21 dibuat.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
