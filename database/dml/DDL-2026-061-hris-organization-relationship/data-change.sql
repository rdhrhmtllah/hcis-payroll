/* Change ID: DDL-2026-061-hris-organization-relationship (bagian DML)
   Scope: lima seed jenis relasi dan satu record database-change.
   Tidak mengubah Karyawan atau tabel appraisal. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50061, 'DDL-2026-061 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
   OR OBJECT_ID('N_HRIS_Organization_Relationship', 'U') IS NULL
    THROW 50062, 'DDL-2026-061: forward DDL belum lengkap.', 1;
GO

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'DIRECT_SUPERVISOR'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan, Flag_Aktif, Created_By)
    VALUES
        ('DIRECT_SUPERVISOR', N'Atasan Langsung', N'Relasi atasan struktural langsung; sumber utama kandidat Atasan 1.', 10, 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'FUNCTIONAL_SUPERVISOR'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan, Flag_Aktif, Created_By)
    VALUES
        ('FUNCTIONAL_SUPERVISOR', N'Atasan Fungsional', N'Relasi atasan matriks atau fungsional; penggunaannya ditentukan oleh Rater Policy.', 20, 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'PEER'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan, Flag_Aktif, Created_By)
    VALUES
        ('PEER', N'Rekan Kerja', N'Relasi peer eksplisit bila dipelihara organisasi; candidate resolver tetap dapat memberi rekomendasi tambahan.', 30, 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'PROJECT_MANAGER'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan, Flag_Aktif, Created_By)
    VALUES
        ('PROJECT_MANAGER', N'Manager Proyek', N'Relasi manager proyek lintas unit; scope akses dan pemakaiannya ditentukan oleh modul konsumen.', 40, 'Y', NULL);
END;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi = 'MATRIX_SUPERVISOR'
)
BEGIN
    INSERT INTO N_HRIS_Organization_Relationship_Type
        (Kode_Relasi, Nama_Relasi, Deskripsi, Urutan, Flag_Aktif, Created_By)
    VALUES
        ('MATRIX_SUPERVISOR', N'Atasan Matrix', N'Relasi atasan pada struktur matrix/cross-functional; tidak menggantikan direct reporting.', 50, 'Y', NULL);
END;

IF (
    SELECT COUNT(*) FROM N_HRIS_Organization_Relationship_Type
    WHERE Kode_Relasi IN ('DIRECT_SUPERVISOR', 'FUNCTIONAL_SUPERVISOR', 'PEER', 'PROJECT_MANAGER', 'MATRIX_SUPERVISOR')
) <> 5
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50063, 'DDL-2026-061: lima seed jenis relasi tidak terbentuk tepat satu per kode.', 1;
END;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-061-hris-organization-relationship'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-061-hris-organization-relationship',
         'DDL',
         'Master dan relasi organisasi employee-to-employee',
         'Membuat fondasi relasi organisasi lintas modul: directional, effective-dated, berprovenance, terikat ke Karyawan, dan mendukung direct/functional/project/matrix. Tanpa bobot appraisal.',
         'database/ddl/DDL-2026-061-hris-organization-relationship/forward.sql',
         'docs/changes/DDL-2026-061-hris-organization-relationship/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'DML seed: database/dml/DDL-2026-061-hris-organization-relationship/data-change.sql. Production wajib melalui SQL Tracker.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
