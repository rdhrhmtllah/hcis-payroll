/* MANUAL ONLY — jangan dijalankan agent.
   Cleanup untuk DDL-2026-062. Data seed/status tidak dibalik di file ini. */

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'IX_HRIS_Org_Rel_Context_Period'
)
    DROP INDEX IX_HRIS_Org_Rel_Context_Period
        ON N_HRIS_Organization_Relationship;
GO

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'CK_HRIS_Org_Rel_Context_Pair'
)
    ALTER TABLE N_HRIS_Organization_Relationship
        DROP CONSTRAINT CK_HRIS_Org_Rel_Context_Pair;
GO

IF COL_LENGTH('N_HRIS_Organization_Relationship', 'Referensi_Konteks') IS NOT NULL
    ALTER TABLE N_HRIS_Organization_Relationship DROP COLUMN Referensi_Konteks;

IF COL_LENGTH('N_HRIS_Organization_Relationship', 'Jenis_Konteks') IS NOT NULL
    ALTER TABLE N_HRIS_Organization_Relationship DROP COLUMN Jenis_Konteks;
GO

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Pembentuk_Struktur_Organisasi') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1 FROM sys.default_constraints
        WHERE parent_object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
          AND name = 'DF_HRIS_Org_Rel_Type_Pembentuk_Struktur'
    )
        ALTER TABLE N_HRIS_Organization_Relationship_Type
            DROP CONSTRAINT DF_HRIS_Org_Rel_Type_Pembentuk_Struktur;

    ALTER TABLE N_HRIS_Organization_Relationship_Type
        DROP COLUMN Flag_Pembentuk_Struktur_Organisasi;
END;
GO

IF COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Flag_Hubungan_Dua_Arah') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1 FROM sys.default_constraints
        WHERE parent_object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
          AND name = 'DF_HRIS_Org_Rel_Type_Dua_Arah'
    )
        ALTER TABLE N_HRIS_Organization_Relationship_Type
            DROP CONSTRAINT DF_HRIS_Org_Rel_Type_Dua_Arah;

    ALTER TABLE N_HRIS_Organization_Relationship_Type
        DROP COLUMN Flag_Hubungan_Dua_Arah;
END;
GO
