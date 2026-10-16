/* Change ID: DDL-2026-072-performance-appraisal-participant
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Seed tiga status peserta ada di forward.sql karena foreign key pada change yang
   sama menolak dipasang sebelum masternya terisi. Tanpa seed peserta. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50218, 'DDL-2026-072 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Participant', 'U') IS NULL
   OR OBJECT_ID('N_PA_Participant_Status', 'U') IS NULL
    THROW 50219, 'DDL-2026-072 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-072-performance-appraisal-participant'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-072-performance-appraisal-participant',
         'DDL',
         'Peserta siklus dan master statusnya',
         'Membuat N_PA_Participant_Status dengan tiga status dan N_PA_Participant yang membekukan kondisi organisasi karyawan saat siklus disiapkan.',
         'database/ddl/DDL-2026-072-performance-appraisal-participant/forward.sql',
         'docs/changes/DDL-2026-072-performance-appraisal-participant/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Artifact revisi r2: divisi dipecah jadi induk dan anak beserta ID dan namanya, plus Lokasi_Saat_Cycle, supaya monitoring tidak perlu join ke Karyawan yang hidup. Bergantung pada DDL-2026-066 dan 071; jalankan sesudah keduanya. ID_Level_Saat_Cycle NOT NULL dan diresolusi lewat View_Golongan_Sub_Golongan_Level_Jabatan (K19).',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
