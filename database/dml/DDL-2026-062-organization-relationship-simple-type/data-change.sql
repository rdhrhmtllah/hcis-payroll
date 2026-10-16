/* Change ID: DDL-2026-062-organization-relationship-simple-type
   Target: Web_HRIS development; production hanya melalui SQL Tracker.
   Scope first run: insert ATASAN <= 1, update PEER <= 1,
   deactivate four unused legacy types <= 4, register change <= 1. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50075, 'DDL-2026-062 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NULL
   OR COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Pembentuk_Struktur_Organisasi') IS NULL
    THROW 50076, 'DDL-2026-062 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF EXISTS (
    SELECT 1
    FROM N_HRIS_Organization_Relationship r
    JOIN N_HRIS_Organization_Relationship_Type t
      ON t.Id_Organization_Relationship_Type = r.Id_Organization_Relationship_Type
    WHERE t.Kode_Relasi IN (
        'DIRECT_SUPERVISOR',
        'FUNCTIONAL_SUPERVISOR',
        'PROJECT_MANAGER',
        'MATRIX_SUPERVISOR'
    )
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50077, 'DDL-2026-062: legacy type sudah direferensikan; pemetaan context wajib direview manual.', 1;
END;

IF (
    SELECT COUNT(*)
    FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi IN (
        'DIRECT_SUPERVISOR',
        'FUNCTIONAL_SUPERVISOR',
        'PROJECT_MANAGER',
        'MATRIX_SUPERVISOR'
    )
) <> 4
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50081, 'DDL-2026-062: empat legacy type dependency tidak ditemukan lengkap.', 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'ATASAN'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan,
         Flag_Hubungan_Dua_Arah, Flag_Pembentuk_Struktur_Organisasi,
         Flag_Aktif, Created_By)
    VALUES
        ('ATASAN', N'Atasan',
         N'Related Employee adalah atasan Subject Employee; primary dan context disimpan pada row relationship.',
         10, 'T', 'Y', 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1
    FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'PEER'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan,
         Flag_Hubungan_Dua_Arah, Flag_Pembentuk_Struktur_Organisasi,
         Flag_Aktif, Created_By)
    VALUES
        ('PEER', N'Rekan Kerja',
         N'Subject dan Related Employee mempunyai hubungan peer yang sama maknanya ketika dibaca dari kedua arah.',
         20, 'Y', 'T', 'Y', NULL);
END;

UPDATE N_HRIS_Organization_Relationship_Type
SET Nama_Relasi = N'Atasan',
    Deskripsi = N'Related Employee adalah atasan Subject Employee; primary dan context disimpan pada row relationship.',
    Urutan = 10,
    Flag_Hubungan_Dua_Arah = 'T',
    Flag_Pembentuk_Struktur_Organisasi = 'Y',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Relasi = 'ATASAN'
  AND (
      Nama_Relasi <> N'Atasan'
      OR ISNULL(Deskripsi, N'') <> N'Related Employee adalah atasan Subject Employee; primary dan context disimpan pada row relationship.'
      OR Urutan <> 10
      OR Flag_Hubungan_Dua_Arah <> 'T'
      OR Flag_Pembentuk_Struktur_Organisasi <> 'Y'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_HRIS_Organization_Relationship_Type
SET Nama_Relasi = N'Rekan Kerja',
    Deskripsi = N'Subject dan Related Employee mempunyai hubungan peer yang sama maknanya ketika dibaca dari kedua arah.',
    Urutan = 20,
    Flag_Hubungan_Dua_Arah = 'Y',
    Flag_Pembentuk_Struktur_Organisasi = 'T',
    Flag_Aktif = 'Y',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Relasi = 'PEER'
  AND (
      Nama_Relasi <> N'Rekan Kerja'
      OR ISNULL(Deskripsi, N'') <> N'Subject dan Related Employee mempunyai hubungan peer yang sama maknanya ketika dibaca dari kedua arah.'
      OR Urutan <> 20
      OR Flag_Hubungan_Dua_Arah <> 'Y'
      OR Flag_Pembentuk_Struktur_Organisasi <> 'T'
      OR Flag_Aktif <> 'Y'
  );

UPDATE N_HRIS_Organization_Relationship_Type
SET Flag_Hubungan_Dua_Arah = 'T',
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
      Flag_Hubungan_Dua_Arah <> 'T'
      OR Flag_Pembentuk_Struktur_Organisasi <> 'T'
      OR Flag_Aktif <> 'T'
  );

IF (
    SELECT COUNT(*)
    FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi IN ('ATASAN', 'PEER')
      AND Flag_Aktif = 'Y'
) <> 2
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50078, 'DDL-2026-062: ATASAN dan PEER tidak terbentuk aktif tepat satu per kode.', 1;
END;

IF EXISTS (
    SELECT 1
    FROM N_HRIS_Organization_Relationship_Type
    WHERE (Kode_Relasi = 'ATASAN'
           AND (Flag_Hubungan_Dua_Arah <> 'T'
                OR Flag_Pembentuk_Struktur_Organisasi <> 'Y'))
       OR (Kode_Relasi = 'PEER'
           AND (Flag_Hubungan_Dua_Arah <> 'Y'
                OR Flag_Pembentuk_Struktur_Organisasi <> 'T'))
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50079, 'DDL-2026-062: metadata ATASAN/PEER tidak sesuai kontrak.', 1;
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
    THROW 50080, 'DDL-2026-062: legacy type belum seluruhnya nonaktif.', 1;
END;

IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-062-organization-relationship-simple-type'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-062-organization-relationship-simple-type',
         'DDL',
         'Sederhanakan type dan context Organization Relationship',
         'Menambahkan metadata master dua-arah/pembentuk struktur, context pada row relationship, serta type aktif ATASAN dan PEER tanpa menghapus type lama.',
         'database/ddl/DDL-2026-062-organization-relationship-simple-type/forward.sql',
         'docs/changes/DDL-2026-062-organization-relationship-simple-type/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Dependency production: DDL-2026-061 wajib dijalankan lebih dahulu. DML: database/dml/DDL-2026-062-organization-relationship-simple-type/data-change.sql.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
