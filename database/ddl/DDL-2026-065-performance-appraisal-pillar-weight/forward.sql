/* Change ID: DDL-2026-065-performance-appraisal-pillar-weight
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: HRIS_Level.
   Scope: cetak biru bobot pilar KPI vs kompetensi per level. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50110, 'DDL-2026-065 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('HRIS_Level', 'U') IS NULL
   OR COL_LENGTH('HRIS_Level', 'ID_Level') IS NULL
    THROW 50111, 'DDL-2026-065 membutuhkan HRIS_Level.ID_Level.', 1;
GO

IF OBJECT_ID('N_PA_Pillar_Weight', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Pillar_Weight (
        Id_PA_Pillar_Weight int IDENTITY(1,1) NOT NULL,
        ID_Level bigint NOT NULL,
        Bobot_KPI decimal(5,2) NOT NULL,
        Bobot_Kompetensi decimal(5,2) NOT NULL,
        Catatan nvarchar(500) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Pillar_Weight_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Pillar_Weight
            PRIMARY KEY (Id_PA_Pillar_Weight),
        CONSTRAINT CK_PA_Pillar_Weight_Non_Negatif
            CHECK (Bobot_KPI >= 0 AND Bobot_Kompetensi >= 0),
        CONSTRAINT CK_PA_Pillar_Weight_Total
            CHECK (Bobot_KPI + Bobot_Kompetensi = 100)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Pillar_Weight')
      AND name = 'FK_PA_Pillar_Weight_Level'
)
BEGIN
    ALTER TABLE N_PA_Pillar_Weight WITH CHECK
        ADD CONSTRAINT FK_PA_Pillar_Weight_Level
        FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Pillar_Weight')
      AND name = 'UX_PA_Pillar_Weight_Level'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Pillar_Weight_Level
        ON N_PA_Pillar_Weight (ID_Level)
        INCLUDE (Bobot_KPI, Bobot_Kompetensi);
END;
GO

IF (
    SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Pillar_Weight')
      AND name IN (
          'Id_PA_Pillar_Weight', 'ID_Level', 'Bobot_KPI', 'Bobot_Kompetensi',
          'Catatan', 'Created_At', 'Created_By', 'Updated_At', 'Updated_By'
      )
) <> 9
    THROW 50112, 'DDL-2026-065: kolom PA Pillar Weight tidak lengkap.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Pillar_Weight')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50113, 'DDL-2026-065: FK pillar weight disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Pillar_Weight')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50114, 'DDL-2026-065: CHECK pillar weight disabled atau tidak trusted.', 1;
GO
