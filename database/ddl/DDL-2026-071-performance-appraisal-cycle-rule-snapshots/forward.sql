/* Change ID: DDL-2026-071-performance-appraisal-cycle-rule-snapshots
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064, 065, 066, 067, 070. Jalankan sesudah semuanya.
   Scope: lima tabel salinan aturan milik satu siklus (lapisan kedua). */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50190, 'DDL-2026-071 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Policy', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Policy_Detail', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
   OR OBJECT_ID('N_PA_Pillar_Weight', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency_Type', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency_Requirement', 'U') IS NULL
   OR OBJECT_ID('N_PA_Score_Range', 'U') IS NULL
   OR OBJECT_ID('N_PA_Score_Type', 'U') IS NULL
   OR OBJECT_ID('N_PA_Score_Usage', 'U') IS NULL
   OR OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
   OR OBJECT_ID('HRIS_Level', 'U') IS NULL
    THROW 50191, 'DDL-2026-071 membutuhkan seluruh tabel fondasi PA dari DDL-064 s/d 070.', 1;
GO

IF OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Rater_Policy_Snapshot (
        Id_PA_Cycle_Rater_Policy_Snapshot int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        ID_Level bigint NOT NULL,
        PA_Rater_Policy_Id int NOT NULL,
        Kode_Policy_Salinan varchar(50) NOT NULL,
        Nama_Policy_Salinan nvarchar(150) NOT NULL,
        Versi_Policy_Salinan int NOT NULL,
        Deskripsi_Policy_Salinan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Rater_Policy_Snap_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Rater_Policy_Snap_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Rater_Policy_Snapshot
            PRIMARY KEY (Id_PA_Cycle_Rater_Policy_Snapshot),
        CONSTRAINT CK_PA_Cycle_Rater_Policy_Snap_Versi
            CHECK (Versi_Policy_Salinan >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Rater_Policy_Detail_Snapshot (
        Id_PA_Cycle_Rater_Policy_Detail_Snapshot int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Rater_Policy_Snapshot_Id int NOT NULL,
        PA_Rater_Policy_Detail_Id int NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        Kode_Kategori_Salinan varchar(40) NOT NULL,
        Nama_Kategori_Salinan nvarchar(100) NOT NULL,
        Organization_Relationship_Type_Id int NULL,
        Kode_Relasi_Salinan varchar(50) NULL,
        Bobot_Persen decimal(5,2) NOT NULL,
        Min_Rater smallint NOT NULL,
        Maks_Rater smallint NULL,
        Min_Rater_Selesai smallint NULL,
        Flag_Izinkan_Manual char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Rater_Det_Snap_Manual DEFAULT ('T'),
        Urutan_Tampil smallint NOT NULL,
        Catatan_Aturan_Salinan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Rater_Det_Snap_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Rater_Det_Snap_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Rater_Policy_Detail_Snapshot
            PRIMARY KEY (Id_PA_Cycle_Rater_Policy_Detail_Snapshot),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Bobot
            CHECK (Bobot_Persen >= 0 AND Bobot_Persen <= 100),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Min
            CHECK (Min_Rater >= 1),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Maks
            CHECK (Maks_Rater IS NULL OR Maks_Rater >= Min_Rater),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Selesai
            CHECK (
                Min_Rater_Selesai IS NULL
                OR (
                    Min_Rater_Selesai >= 1
                    AND (Maks_Rater IS NULL OR Min_Rater_Selesai <= Maks_Rater)
                )
            ),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Pillar_Weight_Snapshot (
        Id_PA_Cycle_Pillar_Weight_Snapshot int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        ID_Level bigint NOT NULL,
        PA_Pillar_Weight_Id int NULL,
        Bobot_KPI decimal(5,2) NOT NULL,
        Bobot_Kompetensi decimal(5,2) NOT NULL,
        Alasan_Perubahan nvarchar(500) NULL,
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Pillar_Snap_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Pillar_Weight_Snapshot
            PRIMARY KEY (Id_PA_Cycle_Pillar_Weight_Snapshot),
        CONSTRAINT CK_PA_Cycle_Pillar_Snap_Non_Negatif
            CHECK (Bobot_KPI >= 0 AND Bobot_Kompetensi >= 0),
        CONSTRAINT CK_PA_Cycle_Pillar_Snap_Total
            CHECK (Bobot_KPI + Bobot_Kompetensi = 100)
    );
END;
GO

IF OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Competency_Requirement_Snapshot (
        Id_PA_Cycle_Competency_Requirement_Snapshot int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        ID_Level bigint NOT NULL,
        PA_Competency_Type_Id int NOT NULL,
        PA_Competency_Requirement_Id int NULL,
        Kode_Type_Salinan varchar(40) NOT NULL,
        Nama_Type_Salinan nvarchar(100) NOT NULL,
        Flag_Wajib char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Comp_Req_Snap_Wajib DEFAULT ('Y'),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Comp_Req_Snap_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Comp_Req_Snap_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Competency_Requirement_Snapshot
            PRIMARY KEY (Id_PA_Cycle_Competency_Requirement_Snapshot)
    );
END;
GO

IF OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Score_Range_Snapshot (
        Id_PA_Cycle_Score_Range_Snapshot int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        PA_Score_Range_Id int NULL,
        Score_Type varchar(20) NOT NULL,
        Score_Usage varchar(20) NOT NULL,
        Kode_Range varchar(40) NOT NULL,
        Nama_Range nvarchar(100) NOT NULL,
        Nilai_Dari decimal(6,2) NOT NULL,
        Nilai_Sampai decimal(6,2) NOT NULL,
        Nilai_Hasil decimal(6,2) NULL,
        Target_Persen decimal(5,2) NULL,
        Warna varchar(20) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Score_Snap_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Score_Snap_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Score_Range_Snapshot
            PRIMARY KEY (Id_PA_Cycle_Score_Range_Snapshot),
        CONSTRAINT CK_PA_Cycle_Score_Snap_Batas
            CHECK (Nilai_Sampai >= Nilai_Dari),
        CONSTRAINT CK_PA_Cycle_Score_Snap_Target
            CHECK (
                Target_Persen IS NULL
                OR (Target_Persen >= 0 AND Target_Persen <= 100)
            ),
        CONSTRAINT CK_PA_Cycle_Score_Snap_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

/* Foreign key: setiap salinan menyimpan jejak asal ke masternya, dan setiap
   salinan terikat ke siklus pemiliknya. */

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Policy_Snap_Cycle')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Cycle
        FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Policy_Snap_Level')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Level
        FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Policy_Snap_Source')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Source
        FOREIGN KEY (PA_Rater_Policy_Id) REFERENCES N_PA_Rater_Policy (Id_PA_Rater_Policy);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Det_Snap_Header')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Detail_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Header
        FOREIGN KEY (PA_Cycle_Rater_Policy_Snapshot_Id)
        REFERENCES N_PA_Cycle_Rater_Policy_Snapshot (Id_PA_Cycle_Rater_Policy_Snapshot);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Det_Snap_Source')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Detail_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Source
        FOREIGN KEY (PA_Rater_Policy_Detail_Id)
        REFERENCES N_PA_Rater_Policy_Detail (Id_PA_Rater_Policy_Detail);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Det_Snap_Category')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Detail_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Category
        FOREIGN KEY (PA_Rater_Category_Id)
        REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Det_Snap_Org_Type')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Detail_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Org_Type
        FOREIGN KEY (Organization_Relationship_Type_Id)
        REFERENCES N_HRIS_Organization_Relationship_Type (Id_Organization_Relationship_Type);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Pillar_Snap_Cycle')
    ALTER TABLE N_PA_Cycle_Pillar_Weight_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Pillar_Snap_Cycle
        FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Pillar_Snap_Level')
    ALTER TABLE N_PA_Cycle_Pillar_Weight_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Pillar_Snap_Level
        FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Pillar_Snap_Source')
    ALTER TABLE N_PA_Cycle_Pillar_Weight_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Pillar_Snap_Source
        FOREIGN KEY (PA_Pillar_Weight_Id) REFERENCES N_PA_Pillar_Weight (Id_PA_Pillar_Weight);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Cycle')
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Cycle
        FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Level')
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Level
        FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Type')
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Type
        FOREIGN KEY (PA_Competency_Type_Id)
        REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Source')
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Source
        FOREIGN KEY (PA_Competency_Requirement_Id)
        REFERENCES N_PA_Competency_Requirement (Id_PA_Competency_Requirement);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Score_Snap_Cycle')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Score_Snap_Cycle
        FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Score_Snap_Source')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Score_Snap_Source
        FOREIGN KEY (PA_Score_Range_Id) REFERENCES N_PA_Score_Range (Id_PA_Score_Range);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Score_Snap_Type')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Score_Snap_Type
        FOREIGN KEY (Score_Type) REFERENCES N_PA_Score_Type (Kode_Type);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Score_Snap_Usage')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Score_Snap_Usage
        FOREIGN KEY (Score_Usage) REFERENCES N_PA_Score_Usage (Kode_Usage);
GO

/* Keunikan: satu salinan aktif per lingkupnya. Disaring pada baris aktif supaya
   salinan bisa dinonaktifkan lalu diganti tanpa menghapus jejaknya. */

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Cycle_Rater_Policy_Snap_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Rater_Policy_Snap_Aktif
        ON N_PA_Cycle_Rater_Policy_Snapshot (PA_Cycle_Id, ID_Level)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Cycle_Rater_Det_Snap_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Rater_Det_Snap_Aktif
        ON N_PA_Cycle_Rater_Policy_Detail_Snapshot
            (PA_Cycle_Rater_Policy_Snapshot_Id, PA_Rater_Category_Id)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Cycle_Pillar_Snap_Cycle_Level')
    CREATE UNIQUE INDEX UX_PA_Cycle_Pillar_Snap_Cycle_Level
        ON N_PA_Cycle_Pillar_Weight_Snapshot (PA_Cycle_Id, ID_Level)
        INCLUDE (Bobot_KPI, Bobot_Kompetensi);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Cycle_Comp_Req_Snap_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Comp_Req_Snap_Aktif
        ON N_PA_Cycle_Competency_Requirement_Snapshot
            (PA_Cycle_Id, ID_Level, PA_Competency_Type_Id)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Cycle_Score_Snap_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Score_Snap_Aktif
        ON N_PA_Cycle_Score_Range_Snapshot (PA_Cycle_Id, Score_Type, Score_Usage, Kode_Range)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Cycle_Score_Snap_Lookup')
    CREATE INDEX IX_PA_Cycle_Score_Snap_Lookup
        ON N_PA_Cycle_Score_Range_Snapshot (PA_Cycle_Id, Score_Type, Score_Usage, Flag_Aktif)
        INCLUDE (Nilai_Dari, Nilai_Sampai, Kode_Range, Nama_Range, Nilai_Hasil, Target_Persen, Warna);
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot')) <> 13
    THROW 50192, 'DDL-2026-071: kolom Cycle Rater Policy Snapshot tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot')) <> 20
    THROW 50193, 'DDL-2026-071: kolom Cycle Rater Policy Detail Snapshot tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot')) <> 11
    THROW 50194, 'DDL-2026-071: kolom Cycle Pillar Weight Snapshot tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot')) <> 13
    THROW 50195, 'DDL-2026-071: kolom Cycle Competency Requirement Snapshot tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot')) <> 18
    THROW 50196, 'DDL-2026-071: kolom Cycle Score Range Snapshot tidak lengkap.', 1;

IF (
    SELECT COUNT(*) FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot')
    )
) <> 18
    THROW 50197, 'DDL-2026-071: jumlah foreign key salinan siklus bukan 18.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50198, 'DDL-2026-071: terdapat FK salinan siklus yang disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot'),
        OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50199, 'DDL-2026-071: terdapat CHECK salinan siklus yang disabled atau tidak trusted.', 1;
GO
