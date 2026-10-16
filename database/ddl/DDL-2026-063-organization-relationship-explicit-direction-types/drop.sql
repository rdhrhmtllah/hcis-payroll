/* MANUAL ONLY. Never execute this file automatically.
   This compatibility rollback does not delete relationship types or facts. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50088, 'Manual rollback 063 hanya ditujukan untuk Web_HRIS.', 1;

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Relasi_Setara') IS NOT NULL
   AND COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NULL
BEGIN
    EXEC sys.sp_rename
        'N_HRIS_Organization_Relationship_Type.Flag_Relasi_Setara',
        'Flag_Hubungan_Dua_Arah',
        'COLUMN';
END;

IF EXISTS (
    SELECT 1
    FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
      AND name = 'DF_HRIS_Org_Rel_Type_Relasi_Setara'
)
AND NOT EXISTS (
    SELECT 1
    FROM sys.objects
    WHERE name = 'DF_HRIS_Org_Rel_Type_Dua_Arah'
)
BEGIN
    EXEC sys.sp_rename
        'DF_HRIS_Org_Rel_Type_Relasi_Setara',
        'DF_HRIS_Org_Rel_Type_Dua_Arah',
        'OBJECT';
END;

UPDATE N_HRIS_Organization_Relationship_Type
SET Flag_Aktif = 'T',
    Updated_At = SYSDATETIME(),
    Updated_By = NULL
WHERE Kode_Relasi = 'BAWAHAN'
  AND Flag_Aktif <> 'T';
