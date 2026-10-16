/* Change ID: DDL-2026-064-performance-appraisal-policy-foundation
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: insert categories <= 4, update categories <= 4,
   and register the change <= 1. No sample policy/detail rows. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50102, 'DDL-2026-064 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Policy', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Policy_Detail', 'U') IS NULL
    THROW 50103, 'DDL-2026-064 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_PA_Rater_Category WHERE Kode_Kategori = 'ATASAN'
)
BEGIN
    INSERT INTO N_PA_Rater_Category
        (Kode_Kategori, Nama_Kategori, Deskripsi, Flag_Aktif, Created_By)
    VALUES
        ('ATASAN', N'Atasan',
         N'Rater menilai sebagai atasan participant.', 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1 FROM N_PA_Rater_Category WHERE Kode_Kategori = 'BAWAHAN'
)
BEGIN
    INSERT INTO N_PA_Rater_Category
        (Kode_Kategori, Nama_Kategori, Deskripsi, Flag_Aktif, Created_By)
    VALUES
        ('BAWAHAN', N'Bawahan',
         N'Rater menilai sebagai bawahan participant.', 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1 FROM N_PA_Rater_Category WHERE Kode_Kategori = 'PEER'
)
BEGIN
    INSERT INTO N_PA_Rater_Category
        (Kode_Kategori, Nama_Kategori, Deskripsi, Flag_Aktif, Created_By)
    VALUES
        ('PEER', N'Rekan Sejawat',
         N'Rater menilai sebagai rekan sejawat participant.', 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1 FROM N_PA_Rater_Category WHERE Kode_Kategori = 'SELF'
)
BEGIN
    INSERT INTO N_PA_Rater_Category
        (Kode_Kategori, Nama_Kategori, Deskripsi, Flag_Aktif, Created_By)
    VALUES
        ('SELF', N'Diri Sendiri',
         N'Participant menilai dirinya sendiri.', 'Y', NULL);
END;

UPDATE N_PA_Rater_Category
SET Nama_Kategori = N'Atasan',
    Deskripsi = N'Rater menilai sebagai atasan participant.',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Kategori = 'ATASAN'
  AND (
      Nama_Kategori <> N'Atasan'
      OR Deskripsi <> N'Rater menilai sebagai atasan participant.'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_PA_Rater_Category
SET Nama_Kategori = N'Bawahan',
    Deskripsi = N'Rater menilai sebagai bawahan participant.',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Kategori = 'BAWAHAN'
  AND (
      Nama_Kategori <> N'Bawahan'
      OR Deskripsi <> N'Rater menilai sebagai bawahan participant.'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_PA_Rater_Category
SET Nama_Kategori = N'Rekan Sejawat',
    Deskripsi = N'Rater menilai sebagai rekan sejawat participant.',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Kategori = 'PEER'
  AND (
      Nama_Kategori <> N'Rekan Sejawat'
      OR Deskripsi <> N'Rater menilai sebagai rekan sejawat participant.'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_PA_Rater_Category
SET Nama_Kategori = N'Diri Sendiri',
    Deskripsi = N'Participant menilai dirinya sendiri.',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Kategori = 'SELF'
  AND (
      Nama_Kategori <> N'Diri Sendiri'
      OR Deskripsi <> N'Participant menilai dirinya sendiri.'
      OR Flag_Aktif <> 'Y'
  );

IF (
    SELECT COUNT(*)
    FROM N_PA_Rater_Category
    WHERE Kode_Kategori IN ('ATASAN', 'BAWAHAN', 'PEER', 'SELF')
      AND Flag_Aktif = 'Y'
) <> 4
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50104, 'DDL-2026-064: empat kategori PA tidak aktif tepat satu per kode.', 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-064-performance-appraisal-policy-foundation'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-064-performance-appraisal-policy-foundation',
         'DDL',
         'Fondasi PA Policy Category dan Detail',
         'Membuat tiga tabel blueprint Performance Appraisal dan seed kategori ATASAN BAWAHAN PEER SELF tanpa sample policy/detail.',
         'database/ddl/DDL-2026-064-performance-appraisal-policy-foundation/forward.sql',
         'docs/changes/DDL-2026-064-performance-appraisal-policy-foundation/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Dependency production: DDL-2026-061, 062, dan 063. DML: database/dml/DDL-2026-064-performance-appraisal-policy-foundation/data-change.sql. Artifact revisi r2 dengan prefix N_PA_*.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
