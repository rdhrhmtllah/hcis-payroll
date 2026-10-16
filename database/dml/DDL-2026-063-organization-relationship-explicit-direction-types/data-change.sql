/* Change ID: DDL-2026-063-organization-relationship-explicit-direction-types
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: insert BAWAHAN <= 1, update three active types <= 3,
   normalize inactive legacy types <= 4, register change <= 1. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50089, 'DDL-2026-063 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Relasi_Setara') IS NULL
   OR COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NOT NULL
    THROW 50090, 'DDL-2026-063 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1
    FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'BAWAHAN'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan,
         Flag_Relasi_Setara, Flag_Pembentuk_Struktur_Organisasi,
         Flag_Aktif, Created_By)
    VALUES
        ('BAWAHAN', N'Bawahan',
         N'Related Employee adalah bawahan Subject Employee.',
         20, 'T', 'Y', 'Y', NULL);
END;

UPDATE N_HRIS_Organization_Relationship_Type
SET Nama_Relasi = N'Atasan',
    Deskripsi = N'Related Employee adalah atasan Subject Employee.',
    Urutan = 10,
    Flag_Relasi_Setara = 'T',
    Flag_Pembentuk_Struktur_Organisasi = 'Y',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Relasi = 'ATASAN'
  AND (
      Nama_Relasi <> N'Atasan'
      OR ISNULL(Deskripsi, N'') <> N'Related Employee adalah atasan Subject Employee.'
      OR Urutan <> 10
      OR Flag_Relasi_Setara <> 'T'
      OR Flag_Pembentuk_Struktur_Organisasi <> 'Y'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_HRIS_Organization_Relationship_Type
SET Nama_Relasi = N'Bawahan',
    Deskripsi = N'Related Employee adalah bawahan Subject Employee.',
    Urutan = 20,
    Flag_Relasi_Setara = 'T',
    Flag_Pembentuk_Struktur_Organisasi = 'Y',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Relasi = 'BAWAHAN'
  AND (
      Nama_Relasi <> N'Bawahan'
      OR ISNULL(Deskripsi, N'') <> N'Related Employee adalah bawahan Subject Employee.'
      OR Urutan <> 20
      OR Flag_Relasi_Setara <> 'T'
      OR Flag_Pembentuk_Struktur_Organisasi <> 'Y'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_HRIS_Organization_Relationship_Type
SET Nama_Relasi = N'Rekan Kerja',
    Deskripsi = N'Subject dan Related Employee mempunyai kedudukan setara sebagai peer.',
    Urutan = 30,
    Flag_Relasi_Setara = 'Y',
    Flag_Pembentuk_Struktur_Organisasi = 'T',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Relasi = 'PEER'
  AND (
      Nama_Relasi <> N'Rekan Kerja'
      OR ISNULL(Deskripsi, N'') <> N'Subject dan Related Employee mempunyai kedudukan setara sebagai peer.'
      OR Urutan <> 30
      OR Flag_Relasi_Setara <> 'Y'
      OR Flag_Pembentuk_Struktur_Organisasi <> 'T'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_HRIS_Organization_Relationship_Type
SET Flag_Relasi_Setara = 'T',
    Flag_Pembentuk_Struktur_Organisasi = 'T',
    Flag_Aktif = 'T',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Relasi IN (
        'DIRECT_SUPERVISOR',
        'FUNCTIONAL_SUPERVISOR',
        'PROJECT_MANAGER',
        'MATRIX_SUPERVISOR'
    )
  AND (
      Flag_Relasi_Setara <> 'T'
      OR Flag_Pembentuk_Struktur_Organisasi <> 'T'
      OR Flag_Aktif <> 'T'
  );

IF (
    SELECT COUNT(*)
    FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi IN ('ATASAN', 'BAWAHAN', 'PEER')
      AND Flag_Aktif = 'Y'
) <> 3
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50091, 'DDL-2026-063: tiga type final tidak aktif tepat satu per kode.', 1;
END;

IF EXISTS (
    SELECT 1
    FROM N_HRIS_Organization_Relationship_Type
    WHERE (Kode_Relasi IN ('ATASAN', 'BAWAHAN')
           AND (Flag_Relasi_Setara <> 'T'
                OR Flag_Pembentuk_Struktur_Organisasi <> 'Y'))
       OR (Kode_Relasi = 'PEER'
           AND (Flag_Relasi_Setara <> 'Y'
                OR Flag_Pembentuk_Struktur_Organisasi <> 'T'))
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50092, 'DDL-2026-063: metadata type final tidak sesuai kontrak.', 1;
END;

IF EXISTS (
    SELECT 1
    FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi IN (
        'DIRECT_SUPERVISOR',
        'FUNCTIONAL_SUPERVISOR',
        'PROJECT_MANAGER',
        'MATRIX_SUPERVISOR'
    )
      AND Flag_Aktif <> 'T'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50093, 'DDL-2026-063: legacy type belum seluruhnya nonaktif.', 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-063-organization-relationship-explicit-direction-types'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-063-organization-relationship-explicit-direction-types',
         'DDL',
         'Type eksplisit ATASAN BAWAHAN PEER',
         'Rename metadata menjadi Flag_Relasi_Setara dan aktifkan type eksplisit ATASAN, BAWAHAN, PEER tanpa menghapus legacy type.',
         'database/ddl/DDL-2026-063-organization-relationship-explicit-direction-types/forward.sql',
         'docs/changes/DDL-2026-063-organization-relationship-explicit-direction-types/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Dependency production: DDL-2026-061 dan 062. DML terpisah berada di database/dml/DDL-2026-063-organization-relationship-explicit-direction-types/data-change.sql. Context dan PA tables di luar scope.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
