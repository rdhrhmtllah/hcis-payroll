/* Change ID: DDL-2026-061-hris-organization-relationship
   Target: Web_HRIS development; production hanya melalui SQL Tracker.
   Scope: dua tabel organisasi baru. Tidak menyentuh tabel appraisal.
   Seluruh pernyataan forward bersifat menambah. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50060, 'DDL-2026-061 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_HRIS_Organization_Relationship_Type (
        Id_Organization_Relationship_Type int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_HRIS_Org_Rel_Type PRIMARY KEY,
        Kode_Relasi varchar(50) NOT NULL,
        Nama_Relasi nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan int NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Urutan DEFAULT (0),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
      AND name = 'UX_HRIS_Org_Rel_Type_Kode'
)
BEGIN
    CREATE UNIQUE INDEX UX_HRIS_Org_Rel_Type_Kode
        ON N_HRIS_Organization_Relationship_Type (Kode_Relasi);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type')
      AND name = 'IX_HRIS_Org_Rel_Type_Aktif_Urutan'
)
BEGIN
    CREATE INDEX IX_HRIS_Org_Rel_Type_Aktif_Urutan
        ON N_HRIS_Organization_Relationship_Type (Flag_Aktif, Urutan)
        INCLUDE (Kode_Relasi, Nama_Relasi);
END;
GO

IF OBJECT_ID('N_HRIS_Organization_Relationship', 'U') IS NULL
BEGIN
    CREATE TABLE N_HRIS_Organization_Relationship (
        Id_Organization_Relationship int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_HRIS_Org_Rel PRIMARY KEY,
        Id_Organization_Relationship_Type int NOT NULL,
        Kode_Perusahaan_Subjek varchar(3) NOT NULL,
        Kode_Karyawan_Subjek varchar(255) NOT NULL,
        Kode_Perusahaan_Terkait varchar(3) NOT NULL,
        Kode_Karyawan_Terkait varchar(255) NOT NULL,
        Berlaku_Dari date NOT NULL,
        Berlaku_Sampai date NULL,
        Flag_Utama char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Utama DEFAULT ('T'),
        Prioritas int NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Prioritas DEFAULT (1),
        Sumber_Data varchar(50) NOT NULL,
        Referensi_Sumber varchar(255) NULL,
        Catatan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        CONSTRAINT FK_HRIS_Org_Rel_Type FOREIGN KEY (Id_Organization_Relationship_Type)
            REFERENCES N_HRIS_Organization_Relationship_Type (Id_Organization_Relationship_Type),
        CONSTRAINT FK_HRIS_Org_Rel_Subject FOREIGN KEY (Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek)
            REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan),
        CONSTRAINT FK_HRIS_Org_Rel_Related FOREIGN KEY (Kode_Perusahaan_Terkait, Kode_Karyawan_Terkait)
            REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan),
        CONSTRAINT CK_HRIS_Org_Rel_Date_Range
            CHECK (Berlaku_Sampai IS NULL OR Berlaku_Sampai >= Berlaku_Dari),
        CONSTRAINT CK_HRIS_Org_Rel_Different_Employee
            CHECK (
                Kode_Perusahaan_Subjek <> Kode_Perusahaan_Terkait
                OR Kode_Karyawan_Subjek <> Kode_Karyawan_Terkait
            )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'UX_HRIS_Org_Rel_Type_Subject_Related_Start'
)
BEGIN
    CREATE UNIQUE INDEX UX_HRIS_Org_Rel_Type_Subject_Related_Start
        ON N_HRIS_Organization_Relationship (
            Id_Organization_Relationship_Type,
            Kode_Perusahaan_Subjek,
            Kode_Karyawan_Subjek,
            Kode_Perusahaan_Terkait,
            Kode_Karyawan_Terkait,
            Berlaku_Dari
        );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'UX_HRIS_Org_Rel_Primary_Open'
)
BEGIN
    CREATE UNIQUE INDEX UX_HRIS_Org_Rel_Primary_Open
        ON N_HRIS_Organization_Relationship (
            Id_Organization_Relationship_Type,
            Kode_Perusahaan_Subjek,
            Kode_Karyawan_Subjek
        )
        WHERE Flag_Aktif = 'Y'
          AND Flag_Utama = 'Y'
          AND Berlaku_Sampai IS NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'IX_HRIS_Org_Rel_Subject_Type_Period'
)
BEGIN
    CREATE INDEX IX_HRIS_Org_Rel_Subject_Type_Period
        ON N_HRIS_Organization_Relationship (
            Kode_Perusahaan_Subjek,
            Kode_Karyawan_Subjek,
            Id_Organization_Relationship_Type,
            Flag_Aktif,
            Berlaku_Dari,
            Berlaku_Sampai
        )
        INCLUDE (
            Kode_Perusahaan_Terkait,
            Kode_Karyawan_Terkait,
            Flag_Utama,
            Prioritas,
            Sumber_Data,
            Referensi_Sumber
        );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship')
      AND name = 'IX_HRIS_Org_Rel_Related_Type_Period'
)
BEGIN
    CREATE INDEX IX_HRIS_Org_Rel_Related_Type_Period
        ON N_HRIS_Organization_Relationship (
            Kode_Perusahaan_Terkait,
            Kode_Karyawan_Terkait,
            Id_Organization_Relationship_Type,
            Flag_Aktif,
            Berlaku_Dari,
            Berlaku_Sampai
        )
        INCLUDE (
            Kode_Perusahaan_Subjek,
            Kode_Karyawan_Subjek,
            Flag_Utama,
            Prioritas,
            Sumber_Data,
            Referensi_Sumber
        );
END;
GO
