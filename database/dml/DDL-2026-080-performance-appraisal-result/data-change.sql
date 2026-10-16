/* Change ID: DDL-2026-080-performance-appraisal-result
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Seed kedua master ada di forward.sql, bukan di sini, karena foreign key pada
   change yang sama menolak dipasang sebelum masternya terisi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50311, 'DDL-2026-080 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Result', 'U') IS NULL
   OR OBJECT_ID('N_PA_Result_Status', 'U') IS NULL
   OR OBJECT_ID('N_PA_Result_Note_Type', 'U') IS NULL
    THROW 50312, 'DDL-2026-080 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-080-performance-appraisal-result'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-080-performance-appraisal-result',
         'DDL',
         'Master status hasil, master jenis catatan, dan tabel hasil PA',
         'Membuat N_PA_Result_Status, N_PA_Result_Note_Type beserta seed-nya, dan N_PA_Result sebagai satu baris hasil per peserta.',
         'database/ddl/DDL-2026-080-performance-appraisal-result/forward.sql',
         'docs/changes/DDL-2026-080-performance-appraisal-result/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-068 dan 072. Nilai KPI disimpan DUA kolom (Nilai_KPI ter-cap dan Nilai_KPI_Sebelum_Cap) karena KpiScoringService::accumulatedScore mengembalikan final_pct dan raw_pct. Final_Score SENGAJA tidak dibatasi 100: pencapaian di atas target tidak dipotong (keputusan user 1 Sep 2026) dan pita grade sudah terbuka ke atas sejak DML-2026-079. Kolom metode nilai DIBATALKAN sebelum dibuat - kolom yang isinya selalu sama tidak menyimpan informasi, dan untuk KPI hanya ada satu metode yaitu YTD. Publikasi ada di sini (Published_At/By), bukan di N_PA_Participant.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
