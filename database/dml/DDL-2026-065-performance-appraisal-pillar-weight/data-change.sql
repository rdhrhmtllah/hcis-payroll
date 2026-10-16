/* Change ID: DDL-2026-065-performance-appraisal-pillar-weight
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   TANPA seed bobot: angka pembagian pilar per level belum diberikan HC (T11).
   N_PA_Pillar_Weight sengaja dibiarkan kosong sampai angkanya turun. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50116, 'DDL-2026-065 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Pillar_Weight', 'U') IS NULL
    THROW 50117, 'DDL-2026-065 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-065-performance-appraisal-pillar-weight'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-065-performance-appraisal-pillar-weight',
         'DDL',
         'Cetak biru bobot pilar KPI vs kompetensi',
         'Membuat N_PA_Pillar_Weight sebagai cetak biru pembagian pilar per level, tanpa seed karena angkanya belum diberikan HC.',
         'database/ddl/DDL-2026-065-performance-appraisal-pillar-weight/forward.sql',
         'docs/changes/DDL-2026-065-performance-appraisal-pillar-weight/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Dependency production: DDL-2026-064 tidak wajib, tabel ini berdiri sendiri di atas HRIS_Level. Bobot pilar SENGAJA dipisah dari N_PA_Rater_Policy (K23).',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
