/* Change ID: DDL-2026-062-organization-relationship-simple-type
   Target: Web_HRIS development; production hanya melalui SQL Tracker.
   Dependency: DDL-2026-061-hris-organization-relationship.
   Scope: metadata type sederhana dan context relationship. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50070, 'DDL-2026-062 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
    THROW 50071, 'DDL-2026-062 membutuhkan N_HRIS_Organization_Relationship_Type dari DDL-2026-061.', 1;

IF OBJECT_ID('N_HRIS_Organization_Relationship', 'U') IS NULL
    THROW 50072, 'DDL-2026-062 membutuhkan N_HRIS_Organization_Relationship dari DDL-2026-061.', 1;
GO

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NULL
BEGIN
    ALTER TABLE N_HRIS_Organization_Relationship_Type
        ADD Flag_Hubungan_Dua_Arah char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Dua_Arah DEFAULT ('T') WITH VALUES;
END;
GO

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Pembentuk_Struktur_Organisasi') IS NULL
BEGIN
    ALTER TABLE N_HRIS_Organization_Relationship_Type
        ADD Flag_Pembentuk_Struktur_Organisasi char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Pembentuk_Struktur DEFAULT ('T') WITH VALUES;
END;
GO

IF COL_LENGTH('N_HRIS_Organization_Relationship', 'Jenis_Konteks') IS NULL
BEGIN
    ALTER TABLE N_HRIS_Organization_Relationship
        ADD Jenis_Konteks varchar(30) NULL;
END;

IF COL_LENGTH('N_HRIS_Organization_Relationship', 'Referensi_Konteks') IS NULL
BEGIN
    ALTER TABLE N_HRIS_Organization_Relationship
        ADD Referensi_Konteks varchar(100) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'CK_HRIS_Org_Rel_Context_Pair'
)
BEGIN
    ALTER TABLE N_HRIS_Organization_Relationship WITH CHECK
        ADD CONSTRAINT CK_HRIS_Org_Rel_Context_Pair CHECK (
            (Jenis_Konteks IS NULL AND Referensi_Konteks IS NULL)
            OR
            (Jenis_Konteks IS NOT NULL AND Referensi_Konteks IS NOT NULL)
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'IX_HRIS_Org_Rel_Context_Period'
)
BEGIN
    CREATE INDEX IX_HRIS_Org_Rel_Context_Period
        ON N_HRIS_Organization_Relationship (
            Jenis_Konteks,
            Referensi_Konteks,
            Id_Organization_Relationship_Type,
            Flag_Aktif,
            Berlaku_Dari,
            Berlaku_Sampai
        )
        INCLUDE (
            Kode_Perusahaan_Subjek,
            Kode_Karyawan_Subjek,
            Kode_Perusahaan_Terkait,
            Kode_Karyawan_Terkait,
            Flag_Utama,
            Prioritas
        );
END;
GO

IF (
    SELECT COUNT(*)
    FROM sys.columns
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
      AND name IN ('Flag_Hubungan_Dua_Arah', 'Flag_Pembentuk_Struktur_Organisasi')
) <> 2
    THROW 50073, 'DDL-2026-062: metadata master type tidak lengkap.', 1;

IF (
    SELECT COUNT(*)
    FROM sys.columns
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name IN ('Jenis_Konteks', 'Referensi_Konteks')
) <> 2
    THROW 50074, 'DDL-2026-062: pasangan kolom context tidak lengkap.', 1;
GO
