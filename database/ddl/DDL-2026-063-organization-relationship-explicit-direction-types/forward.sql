/* Change ID: DDL-2026-063-organization-relationship-explicit-direction-types
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-061 and DDL-2026-062.
   Scope: rename the relationship-type equality flag. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50082, 'DDL-2026-063 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
    THROW 50083, 'DDL-2026-063 membutuhkan master Organization Relationship Type.', 1;

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NOT NULL
   AND COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Relasi_Setara') IS NOT NULL
    THROW 50084, 'DDL-2026-063 menemukan nama kolom lama dan baru bersamaan.', 1;
GO

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Relasi_Setara') IS NULL
BEGIN
    IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NULL
        THROW 50085, 'DDL-2026-063 tidak menemukan kolom metadata dependency 062.', 1;

    EXEC sys.sp_rename
        'N_HRIS_Organization_Relationship_Type.Flag_Hubungan_Dua_Arah',
        'Flag_Relasi_Setara',
        'COLUMN';
END;
GO

IF EXISTS (
    SELECT 1
    FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
      AND name = 'DF_HRIS_Org_Rel_Type_Dua_Arah'
)
AND NOT EXISTS (
    SELECT 1
    FROM sys.objects
    WHERE name = 'DF_HRIS_Org_Rel_Type_Relasi_Setara'
)
BEGIN
    EXEC sys.sp_rename
        'DF_HRIS_Org_Rel_Type_Dua_Arah',
        'DF_HRIS_Org_Rel_Type_Relasi_Setara',
        'OBJECT';
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
      AND name = 'Flag_Relasi_Setara'
      AND TYPE_NAME(user_type_id) = 'char'
      AND max_length = 1
      AND is_nullable = 0
)
    THROW 50086, 'DDL-2026-063: Flag_Relasi_Setara tidak sesuai char(1) NOT NULL.', 1;

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NOT NULL
    THROW 50087, 'DDL-2026-063: nama kolom lama masih tersedia.', 1;
GO
