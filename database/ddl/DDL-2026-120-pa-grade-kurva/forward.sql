/* Change ID: DDL-2026-120-pa-grade-kurva
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064 (N_PA_Score_Range, N_PA_Cycle), DDL-2026-078 (N_PA_Result),
                 HRIS_Level (PK_Level).
   Scope: grade PA dari z-score per grup level, dikunci per siklus.
   Rancangan: docs/superpowers/specs/2026-09-18-pa-grade-kurva-normal-design.md
*/

/* 1. Batas bawah z per grade (NULL = grade terendah / belum diatur). */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Score_Range ADD Batas_Z decimal(5,2) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot ADD Batas_Z decimal(5,2) NULL;
GO

/* 2. Metode grade per siklus. DEFAULT 'PITA' mengisi siklus yang sudah ada tanpa DML;
      siklus baru diset 'KURVA' oleh aplikasi (PaCycleSetupService). */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle') AND name = 'Metode_Grade')
    ALTER TABLE N_PA_Cycle ADD Metode_Grade char(5) NOT NULL
        CONSTRAINT DF_PA_Cycle_Metode_Grade DEFAULT ('PITA');
GO
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Cycle_Metode_Grade')
    ALTER TABLE N_PA_Cycle WITH CHECK ADD CONSTRAINT CK_PA_Cycle_Metode_Grade
        CHECK (Metode_Grade IN ('PITA', 'KURVA'));
GO

/* 3. Master grup level kurva. */
IF OBJECT_ID('N_PA_Curve_Group', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Curve_Group (
        Id_PA_Curve_Group int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Curve_Group PRIMARY KEY,
        Kode_Grup varchar(40) NOT NULL,
        Nama_Grup nvarchar(200) NOT NULL,
        Urutan_Tampil smallint NOT NULL CONSTRAINT DF_PA_Curve_Group_Urutan DEFAULT (1),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Curve_Group_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Curve_Group_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Curve_Group') AND name = 'UX_PA_Curve_Group_Kode')
    CREATE UNIQUE INDEX UX_PA_Curve_Group_Kode ON N_PA_Curve_Group (Kode_Grup) WHERE Flag_Aktif = 'Y';
GO
IF OBJECT_ID('N_PA_Curve_Group_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Curve_Group_Level (
        Id_PA_Curve_Group_Level int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Curve_Group_Level PRIMARY KEY,
        PA_Curve_Group_Id int NOT NULL
            CONSTRAINT FK_PA_Curve_Group_Level_Grup REFERENCES N_PA_Curve_Group (Id_PA_Curve_Group),
        ID_Level bigint NOT NULL
            CONSTRAINT FK_PA_Curve_Group_Level_Level REFERENCES HRIS_Level (ID_Level),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Curve_Group_Level_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Curve_Group_Level_Created DEFAULT (sysdatetime()),
        Created_By int NULL
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Curve_Group_Level') AND name = 'UX_PA_Curve_Group_Level_Level')
    CREATE UNIQUE INDEX UX_PA_Curve_Group_Level_Level ON N_PA_Curve_Group_Level (ID_Level) WHERE Flag_Aktif = 'Y';
GO

/* 4. Salinan grup per siklus. */
IF OBJECT_ID('N_PA_Cycle_Curve_Group', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Group (
        Id_PA_Cycle_Curve_Group int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve_Group PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        PA_Curve_Group_Id int NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Sumber REFERENCES N_PA_Curve_Group (Id_PA_Curve_Group),
        Kode_Grup varchar(40) NOT NULL,
        Nama_Grup nvarchar(200) NOT NULL,
        Urutan_Tampil smallint NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Urutan DEFAULT (1),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Created DEFAULT (sysdatetime()),
        Created_By int NULL
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Group') AND name = 'UX_PA_Cycle_Curve_Group_Kode')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Group_Kode ON N_PA_Cycle_Curve_Group (PA_Cycle_Id, Kode_Grup) WHERE Flag_Aktif = 'Y';
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Group_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Group_Level (
        Id_PA_Cycle_Curve_Group_Level int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve_Group_Level PRIMARY KEY,
        PA_Cycle_Curve_Group_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Grup REFERENCES N_PA_Cycle_Curve_Group (Id_PA_Cycle_Curve_Group),
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        ID_Level bigint NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Level REFERENCES HRIS_Level (ID_Level),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Level_Aktif DEFAULT ('Y')
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Group_Level') AND name = 'UX_PA_Cycle_Curve_Group_Level_Level')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Group_Level_Level ON N_PA_Cycle_Curve_Group_Level (PA_Cycle_Id, ID_Level) WHERE Flag_Aktif = 'Y';
GO

/* 5. Hasil kunci: satu baris per grup per penguncian, hanya ditambah dan dinonaktifkan. */
IF OBJECT_ID('N_PA_Cycle_Curve', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve (
        Id_PA_Cycle_Curve int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        PA_Cycle_Curve_Group_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Grup REFERENCES N_PA_Cycle_Curve_Group (Id_PA_Cycle_Curve_Group),
        Jumlah_Peserta int NOT NULL,
        Rata_Rata decimal(9,4) NULL,
        Simpangan_Baku decimal(9,4) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Aktif DEFAULT ('Y'),
        Locked_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Locked DEFAULT (sysdatetime()),
        Locked_By int NULL,
        Unlocked_At datetime2(0) NULL,
        Unlocked_By int NULL,
        Alasan_Buka nvarchar(500) NULL,
        CONSTRAINT CK_PA_Cycle_Curve_Jumlah CHECK (Jumlah_Peserta >= 0),
        CONSTRAINT CK_PA_Cycle_Curve_Sd CHECK (Simpangan_Baku IS NULL OR Simpangan_Baku >= 0),
        CONSTRAINT CK_PA_Cycle_Curve_Buka CHECK (Flag_Aktif = 'Y' OR (Unlocked_At IS NOT NULL AND Alasan_Buka IS NOT NULL))
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve') AND name = 'UX_PA_Cycle_Curve_Grup_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Grup_Aktif ON N_PA_Cycle_Curve (PA_Cycle_Curve_Group_Id) WHERE Flag_Aktif = 'Y';
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve') AND name = 'IX_PA_Cycle_Curve_Cycle')
    CREATE INDEX IX_PA_Cycle_Curve_Cycle ON N_PA_Cycle_Curve (PA_Cycle_Id, Flag_Aktif);
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Band', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Band (
        Id_PA_Cycle_Curve_Band int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve_Band PRIMARY KEY,
        PA_Cycle_Curve_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Band_Curve REFERENCES N_PA_Cycle_Curve (Id_PA_Cycle_Curve),
        Kode_Range varchar(40) NOT NULL,
        Nama_Range nvarchar(200) NOT NULL,
        Batas_Z decimal(5,2) NULL,
        Nilai_Dari decimal(9,4) NOT NULL,
        Nilai_Sampai decimal(9,4) NOT NULL,
        Urutan_Tampil smallint NOT NULL,
        CONSTRAINT CK_PA_Cycle_Curve_Band_Rentang CHECK (Nilai_Sampai >= Nilai_Dari)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Band') AND name = 'UX_PA_Cycle_Curve_Band_Kode')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Band_Kode ON N_PA_Cycle_Curve_Band (PA_Cycle_Curve_Id, Kode_Range);
GO

/* 6. Kurva yang memberi grade sebuah hasil. */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'PA_Cycle_Curve_Id')
    ALTER TABLE N_PA_Result ADD PA_Cycle_Curve_Id int NULL
        CONSTRAINT FK_PA_Result_Cycle_Curve REFERENCES N_PA_Cycle_Curve (Id_PA_Cycle_Curve);
GO

/* 7. Pendaftaran perubahan. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-120')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification,
        Catatan, Created_By
    )
    VALUES (
        'DDL-2026-120', 'DDL', 'Grade Performance Appraisal dari kurva normal per grup level',
        'Grade sistem PA ditentukan dari z-score per grup level: master pita grade menyimpan Batas_Z, master N_PA_Curve_Group mengelompokkan level, keduanya disalin per siklus; kunci kurva membekukan rata-rata, simpangan baku, dan pita absolut di N_PA_Cycle_Curve(_Band). Siklus lama tetap Metode_Grade PITA.',
        'database/ddl/DDL-2026-120-pa-grade-kurva/forward.sql',
        'docs/changes/DDL-2026-120-pa-grade-kurva/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'Metode_Grade DEFAULT PITA (siklus lama); siklus baru diset KURVA oleh aplikasi. Kurva hanya ditambah dan dinonaktifkan.',
        NULL
    );
END;
GO
