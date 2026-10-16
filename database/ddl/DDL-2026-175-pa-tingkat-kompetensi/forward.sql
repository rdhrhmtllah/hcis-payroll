/* Change ID: DDL-2026-175-pa-tingkat-kompetensi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-067 (N_PA_Competency), DDL-2026-066 (N_PA_Cycle).
   Scope: tingkat kompetensi + Expected Behaviour per tingkat, matriks kompetensi per level x kompetensi,
          dan salinannya per siklus.
   Dokumentasi: docs/changes/DDL-2026-175-pa-tingkat-kompetensi/README.md
*/

IF OBJECT_ID('N_PA_Competency_Proficiency', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Proficiency (
        Id_PA_Competency_Proficiency int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Competency_Proficiency PRIMARY KEY,
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Competency_Proficiency_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        Tingkat smallint NOT NULL,
        Nama_Tingkat nvarchar(150) NOT NULL,
        Expected_Behaviour nvarchar(4000) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Proficiency_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Proficiency_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency_Proficiency') AND name = 'UX_PA_Competency_Proficiency_Competency_Tingkat')
    CREATE UNIQUE INDEX UX_PA_Competency_Proficiency_Competency_Tingkat
        ON N_PA_Competency_Proficiency (PA_Competency_Id, Tingkat) WHERE Flag_Aktif = 'Y';
GO

IF OBJECT_ID('N_PA_Competency_Level_Requirement', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Level_Requirement (
        Id_PA_Competency_Level_Requirement int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Competency_Level_Requirement PRIMARY KEY,
        ID_Level int NOT NULL,
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Competency_Level_Requirement_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        PA_Competency_Proficiency_Id int NULL
            CONSTRAINT FK_PA_Competency_Level_Requirement_Proficiency REFERENCES N_PA_Competency_Proficiency (Id_PA_Competency_Proficiency),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Level_Requirement_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Level_Requirement_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency_Level_Requirement') AND name = 'UX_PA_Competency_Level_Requirement_Level_Competency')
    CREATE UNIQUE INDEX UX_PA_Competency_Level_Requirement_Level_Competency
        ON N_PA_Competency_Level_Requirement (ID_Level, PA_Competency_Id) WHERE Flag_Aktif = 'Y';
GO

IF OBJECT_ID('N_PA_Cycle_Competency_Level_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Competency_Level_Snapshot (
        Id_PA_Cycle_Competency_Level_Snapshot int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Cycle_Competency_Level_Snapshot PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Competency_Level_Snapshot_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        ID_Level int NOT NULL,
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Competency_Level_Snapshot_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        PA_Competency_Proficiency_Id int NULL,
        Tingkat smallint NULL,
        Nama_Tingkat nvarchar(150) NULL,
        Expected_Behaviour nvarchar(4000) NULL,
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Competency_Level_Snapshot_Snapshot_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Competency_Level_Snapshot') AND name = 'UX_PA_Cycle_Competency_Level_Snapshot_Cycle_Level_Competency')
    CREATE UNIQUE INDEX UX_PA_Cycle_Competency_Level_Snapshot_Cycle_Level_Competency
        ON N_PA_Cycle_Competency_Level_Snapshot (PA_Cycle_Id, ID_Level, PA_Competency_Id);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-175')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-175',
        'DDL',
        'Tingkat kompetensi dan matriks kompetensi per kompetensi Performance Appraisal',
        'N_PA_Competency_Proficiency (tingkat + Expected Behaviour per kompetensi), N_PA_Competency_Level_Requirement (kompetensi dan tingkat yang dituntut per level jabatan, menggantikan penjabaran per jenis), dan N_PA_Cycle_Competency_Level_Snapshot (salinannya per siklus).',
        'database/ddl/DDL-2026-175-pa-tingkat-kompetensi/forward.sql',
        'docs/changes/DDL-2026-175-pa-tingkat-kompetensi/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Master Data. Isi awal matriks lewat DML-2026-176.',
        NULL
    );
END;
GO
