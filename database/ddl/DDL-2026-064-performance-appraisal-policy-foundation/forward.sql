/* Change ID: DDL-2026-064-performance-appraisal-policy-foundation
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: HRIS_Level and DDL-2026-063 Organization Relationship Type.
   Scope: PA policy header, rater-category master, and policy detail. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50094, 'DDL-2026-064 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('HRIS_Level', 'U') IS NULL
   OR COL_LENGTH('HRIS_Level', 'ID_Level') IS NULL
    THROW 50095, 'DDL-2026-064 membutuhkan HRIS_Level.ID_Level.', 1;

IF OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
   OR COL_LENGTH('N_HRIS_Organization_Relationship_Type', 'Id_Organization_Relationship_Type') IS NULL
    THROW 50096, 'DDL-2026-064 membutuhkan master Organization Relationship Type.', 1;
GO

IF OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Rater_Category (
        Id_PA_Rater_Category int IDENTITY(1,1) NOT NULL,
        Kode_Kategori varchar(40) NOT NULL,
        Nama_Kategori nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Category_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Rater_Category_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Rater_Category
            PRIMARY KEY (Id_PA_Rater_Category)
    );
END;
GO

IF OBJECT_ID('N_PA_Rater_Policy', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Rater_Policy (
        Id_PA_Rater_Policy int IDENTITY(1,1) NOT NULL,
        Kode_Policy varchar(50) NOT NULL,
        Nama_Policy nvarchar(150) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        ID_Level bigint NOT NULL,
        Versi int NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Versi DEFAULT (1),
        PA_Rater_Policy_Sumber_Id int NULL,
        Status_Policy varchar(20) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Status DEFAULT ('DRAFT'),
        Berlaku_Dari date NOT NULL,
        Berlaku_Sampai date NULL,
        Catatan_Versi nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Activated_At datetime2(0) NULL,
        Activated_By int NULL,
        CONSTRAINT PK_PA_Rater_Policy
            PRIMARY KEY (Id_PA_Rater_Policy),
        CONSTRAINT CK_PA_Rater_Policy_Versi
            CHECK (Versi >= 1),
        CONSTRAINT CK_PA_Rater_Policy_Date_Range
            CHECK (Berlaku_Sampai IS NULL OR Berlaku_Sampai >= Berlaku_Dari),
        CONSTRAINT CK_PA_Rater_Policy_Source_Different
            CHECK (PA_Rater_Policy_Sumber_Id IS NULL OR PA_Rater_Policy_Sumber_Id <> Id_PA_Rater_Policy)
    );
END;
GO

IF OBJECT_ID('N_PA_Rater_Policy_Detail', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Rater_Policy_Detail (
        Id_PA_Rater_Policy_Detail int IDENTITY(1,1) NOT NULL,
        PA_Rater_Policy_Id int NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        Organization_Relationship_Type_Id int NULL,
        Bobot_Persen decimal(5,2) NOT NULL,
        Min_Rater smallint NOT NULL,
        Maks_Rater smallint NULL,
        Min_Rater_Selesai smallint NULL,
        Flag_Izinkan_Manual char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Detail_Manual DEFAULT ('T'),
        Urutan_Tampil smallint NOT NULL,
        Catatan_Aturan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Detail_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Detail_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Rater_Policy_Detail
            PRIMARY KEY (Id_PA_Rater_Policy_Detail),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Bobot
            CHECK (Bobot_Persen >= 0 AND Bobot_Persen <= 100),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Rater
            CHECK (Min_Rater >= 1),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Maks_Rater
            CHECK (Maks_Rater IS NULL OR Maks_Rater >= Min_Rater),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Selesai
            CHECK (
                Min_Rater_Selesai IS NULL
                OR (
                    Min_Rater_Selesai >= 1
                    AND (Maks_Rater IS NULL OR Min_Rater_Selesai <= Maks_Rater)
                )
            ),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Policy')
      AND name = 'FK_PA_Rater_Policy_Level'
)
BEGIN
    ALTER TABLE N_PA_Rater_Policy WITH CHECK
        ADD CONSTRAINT FK_PA_Rater_Policy_Level
        FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Policy')
      AND name = 'FK_PA_Rater_Policy_Source'
)
BEGIN
    ALTER TABLE N_PA_Rater_Policy WITH CHECK
        ADD CONSTRAINT FK_PA_Rater_Policy_Source
        FOREIGN KEY (PA_Rater_Policy_Sumber_Id)
        REFERENCES N_PA_Rater_Policy (Id_PA_Rater_Policy);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Policy_Detail')
      AND name = 'FK_PA_Rater_Policy_Detail_Policy'
)
BEGIN
    ALTER TABLE N_PA_Rater_Policy_Detail WITH CHECK
        ADD CONSTRAINT FK_PA_Rater_Policy_Detail_Policy
        FOREIGN KEY (PA_Rater_Policy_Id)
        REFERENCES N_PA_Rater_Policy (Id_PA_Rater_Policy);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Policy_Detail')
      AND name = 'FK_PA_Rater_Policy_Detail_Category'
)
BEGIN
    ALTER TABLE N_PA_Rater_Policy_Detail WITH CHECK
        ADD CONSTRAINT FK_PA_Rater_Policy_Detail_Category
        FOREIGN KEY (PA_Rater_Category_Id)
        REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Policy_Detail')
      AND name = 'FK_PA_Rater_Policy_Detail_Org_Type'
)
BEGIN
    ALTER TABLE N_PA_Rater_Policy_Detail WITH CHECK
        ADD CONSTRAINT FK_PA_Rater_Policy_Detail_Org_Type
        FOREIGN KEY (Organization_Relationship_Type_Id)
        REFERENCES N_HRIS_Organization_Relationship_Type
            (Id_Organization_Relationship_Type);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category')
      AND name = 'UX_PA_Rater_Category_Kode'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Rater_Category_Kode
        ON N_PA_Rater_Category (Kode_Kategori);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category')
      AND name = 'IX_PA_Rater_Category_Aktif'
)
BEGIN
    CREATE INDEX IX_PA_Rater_Category_Aktif
        ON N_PA_Rater_Category (Flag_Aktif, Nama_Kategori)
        INCLUDE (Kode_Kategori);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy')
      AND name = 'UX_PA_Rater_Policy_Kode_Versi'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Rater_Policy_Kode_Versi
        ON N_PA_Rater_Policy (Kode_Policy, Versi);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy')
      AND name = 'IX_PA_Rater_Policy_Level_Period'
)
BEGIN
    CREATE INDEX IX_PA_Rater_Policy_Level_Period
        ON N_PA_Rater_Policy (
            ID_Level,
            Flag_Aktif,
            Status_Policy,
            Berlaku_Dari,
            Berlaku_Sampai
        )
        INCLUDE (Kode_Policy, Nama_Policy, Versi);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy')
      AND name = 'IX_PA_Rater_Policy_Source'
)
BEGIN
    CREATE INDEX IX_PA_Rater_Policy_Source
        ON N_PA_Rater_Policy (PA_Rater_Policy_Sumber_Id)
        WHERE PA_Rater_Policy_Sumber_Id IS NOT NULL;
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail')
      AND name = 'UX_PA_Rater_Policy_Detail_Active_Category'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Rater_Policy_Detail_Active_Category
        ON N_PA_Rater_Policy_Detail
            (PA_Rater_Policy_Id, PA_Rater_Category_Id)
        WHERE Flag_Aktif = 'Y';
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail')
      AND name = 'IX_PA_Rater_Policy_Detail_Category'
)
BEGIN
    CREATE INDEX IX_PA_Rater_Policy_Detail_Category
        ON N_PA_Rater_Policy_Detail
            (PA_Rater_Category_Id, Flag_Aktif)
        INCLUDE (PA_Rater_Policy_Id, Bobot_Persen);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail')
      AND name = 'IX_PA_Rater_Policy_Detail_Org_Type'
)
BEGIN
    CREATE INDEX IX_PA_Rater_Policy_Detail_Org_Type
        ON N_PA_Rater_Policy_Detail
            (Organization_Relationship_Type_Id, Flag_Aktif)
        INCLUDE (PA_Rater_Policy_Id, PA_Rater_Category_Id)
        WHERE Organization_Relationship_Type_Id IS NOT NULL;
END;
GO

IF (
    SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy')
      AND name IN (
          'Id_PA_Rater_Policy', 'Kode_Policy', 'Nama_Policy', 'Deskripsi',
          'ID_Level', 'Versi', 'PA_Rater_Policy_Sumber_Id', 'Status_Policy',
          'Berlaku_Dari', 'Berlaku_Sampai', 'Catatan_Versi', 'Flag_Aktif',
          'Created_At', 'Created_By', 'Updated_At', 'Updated_By',
          'Activated_At', 'Activated_By'
      )
) <> 18
    THROW 50097, 'DDL-2026-064: kolom PA Policy tidak lengkap.', 1;

IF (
    SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category')
      AND name IN (
          'Id_PA_Rater_Category', 'Kode_Kategori', 'Nama_Kategori',
          'Deskripsi', 'Flag_Aktif', 'Created_At', 'Created_By',
          'Updated_At', 'Updated_By'
      )
) <> 9
    THROW 50098, 'DDL-2026-064: kolom PA Rater Category tidak lengkap.', 1;

IF (
    SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail')
      AND name IN (
          'Id_PA_Rater_Policy_Detail', 'PA_Rater_Policy_Id', 'PA_Rater_Category_Id',
          'Organization_Relationship_Type_Id', 'Bobot_Persen',
          'Min_Rater', 'Maks_Rater', 'Min_Rater_Selesai',
          'Flag_Izinkan_Manual', 'Urutan_Tampil', 'Catatan_Aturan',
          'Flag_Aktif', 'Created_At', 'Created_By', 'Updated_At', 'Updated_By'
      )
) <> 16
    THROW 50099, 'DDL-2026-064: kolom PA Policy Detail tidak lengkap.', 1;

IF EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Rater_Policy'),
        OBJECT_ID('N_PA_Rater_Policy_Detail')
    )
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50100, 'DDL-2026-064: terdapat FK PA yang disabled atau tidak trusted.', 1;
GO
