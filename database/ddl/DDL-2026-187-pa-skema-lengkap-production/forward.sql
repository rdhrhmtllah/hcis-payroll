/* Change ID: DDL-2026-187-pa-skema-lengkap-production
   Target: PRODUCTION (emi_db_web_hr) melalui SQL Tracker. JANGAN dijalankan di Web_HRIS development:
           di sana seluruh tabel ini sudah ada (dibangun bertahap oleh DDL PA 064–185), dan setiap
           blok dijaga IF OBJECT_ID(...) IS NULL sehingga tidak akan menimpa apa pun.
   Scope: skema Performance Appraisal LENGKAP dalam bentuk akhirnya — 69 tabel (67 N_PA_*
          + 2 tabel relasi organisasi HRIS yang dirujuk PA), lengkap dengan default, identity, PK,
          UNIQUE, CHECK, FK, dan indeks. Tanpa ALTER: production belum punya satu pun tabel ini,
          jadi setiap tabel langsung dibuat dalam bentuk akhirnya, FK ditulis di dalam CREATE TABLE
          dan tabel diurutkan mengikuti ketergantungannya.
   Dibangkitkan dari katalog Web_HRIS development pada 2026-10-14 00:04, dibandingkan langsung dengan
          katalog production (baca saja). Tipe kolom FK ke tabel yang sudah ada di production
          (Karyawan, HRIS_Level) disamakan dengan tipe kolom rujukannya DI PRODUCTION.
   Dependencies (sudah ada di production): Karyawan, HRIS_Level, N_SYSTEM_Database_Change.
   Data (master, menu, relasi organisasi): DML terpisah, menyusul.
   Dokumentasi: docs/changes/DDL-2026-187-pa-skema-lengkap-production/README.md
*/

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* Prasyarat: tabel rujukan di luar PA harus sudah ada. */
IF OBJECT_ID('Karyawan', 'U') IS NULL OR OBJECT_ID('HRIS_Level', 'U') IS NULL OR OBJECT_ID('N_SYSTEM_Database_Change', 'U') IS NULL
    THROW 50187, 'DDL-2026-187-pa-skema-lengkap-production: tabel prasyarat (Karyawan / HRIS_Level / N_SYSTEM_Database_Change) tidak ditemukan. Dihentikan.', 1;
GO

/* 01. N_HRIS_Organization_Relationship_Type */
IF OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_HRIS_Organization_Relationship_Type (
        Id_Organization_Relationship_Type int IDENTITY(1,1) NOT NULL,
        Kode_Relasi varchar(50) NOT NULL,
        Nama_Relasi nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan int NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Urutan DEFAULT ((0)),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Flag_Relasi_Setara char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Relasi_Setara DEFAULT ('T'),
        Flag_Pembentuk_Struktur_Organisasi char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Type_Pembentuk_Struktur DEFAULT ('T'),
        CONSTRAINT PK_HRIS_Org_Rel_Type PRIMARY KEY CLUSTERED (Id_Organization_Relationship_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type') AND name = 'IX_HRIS_Org_Rel_Type_Aktif_Urutan')
    CREATE NONCLUSTERED INDEX IX_HRIS_Org_Rel_Type_Aktif_Urutan ON N_HRIS_Organization_Relationship_Type (Flag_Aktif, Urutan) INCLUDE (Kode_Relasi, Nama_Relasi);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship_Type') AND name = 'UX_HRIS_Org_Rel_Type_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_HRIS_Org_Rel_Type_Kode ON N_HRIS_Organization_Relationship_Type (Kode_Relasi);
GO

/* 02. N_HRIS_Organization_Relationship */
IF OBJECT_ID('N_HRIS_Organization_Relationship', 'U') IS NULL
BEGIN
    CREATE TABLE N_HRIS_Organization_Relationship (
        Id_Organization_Relationship int IDENTITY(1,1) NOT NULL,
        Id_Organization_Relationship_Type int NOT NULL,
        Kode_Perusahaan_Subjek varchar(3) NOT NULL,
        Kode_Karyawan_Subjek varchar(15) NOT NULL,
        Kode_Perusahaan_Terkait varchar(3) NOT NULL,
        Kode_Karyawan_Terkait varchar(15) NOT NULL,
        Berlaku_Dari date NOT NULL,
        Berlaku_Sampai date NULL,
        Flag_Utama char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Utama DEFAULT ('T'),
        Prioritas int NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Prioritas DEFAULT ((1)),
        Sumber_Data varchar(50) NOT NULL,
        Referensi_Sumber varchar(255) NULL,
        Catatan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_HRIS_Org_Rel_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        Jenis_Konteks varchar(30) NULL,
        Referensi_Konteks varchar(100) NULL,
        CONSTRAINT PK_HRIS_Org_Rel PRIMARY KEY CLUSTERED (Id_Organization_Relationship),
        CONSTRAINT CK_HRIS_Org_Rel_Context_Pair CHECK ([Jenis_Konteks] IS NULL AND [Referensi_Konteks] IS NULL OR [Jenis_Konteks] IS NOT NULL AND [Referensi_Konteks] IS NOT NULL),
        CONSTRAINT CK_HRIS_Org_Rel_Date_Range CHECK ([Berlaku_Sampai] IS NULL OR [Berlaku_Sampai]>=[Berlaku_Dari]),
        CONSTRAINT CK_HRIS_Org_Rel_Different_Employee CHECK ([Kode_Perusahaan_Subjek]<>[Kode_Perusahaan_Terkait] OR [Kode_Karyawan_Subjek]<>[Kode_Karyawan_Terkait]),
        CONSTRAINT FK_HRIS_Org_Rel_Related FOREIGN KEY (Kode_Perusahaan_Terkait, Kode_Karyawan_Terkait) REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan),
        CONSTRAINT FK_HRIS_Org_Rel_Subject FOREIGN KEY (Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek) REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan),
        CONSTRAINT FK_HRIS_Org_Rel_Type FOREIGN KEY (Id_Organization_Relationship_Type) REFERENCES N_HRIS_Organization_Relationship_Type (Id_Organization_Relationship_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship') AND name = 'IX_HRIS_Org_Rel_Context_Period')
    CREATE NONCLUSTERED INDEX IX_HRIS_Org_Rel_Context_Period ON N_HRIS_Organization_Relationship (Jenis_Konteks, Referensi_Konteks, Id_Organization_Relationship_Type, Flag_Aktif, Berlaku_Dari, Berlaku_Sampai) INCLUDE (Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek, Kode_Perusahaan_Terkait, Kode_Karyawan_Terkait, Flag_Utama, Prioritas);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship') AND name = 'IX_HRIS_Org_Rel_Related_Type_Period')
    CREATE NONCLUSTERED INDEX IX_HRIS_Org_Rel_Related_Type_Period ON N_HRIS_Organization_Relationship (Kode_Perusahaan_Terkait, Kode_Karyawan_Terkait, Id_Organization_Relationship_Type, Flag_Aktif, Berlaku_Dari, Berlaku_Sampai) INCLUDE (Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek, Flag_Utama, Prioritas, Sumber_Data, Referensi_Sumber);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship') AND name = 'IX_HRIS_Org_Rel_Subject_Type_Period')
    CREATE NONCLUSTERED INDEX IX_HRIS_Org_Rel_Subject_Type_Period ON N_HRIS_Organization_Relationship (Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek, Id_Organization_Relationship_Type, Flag_Aktif, Berlaku_Dari, Berlaku_Sampai) INCLUDE (Kode_Perusahaan_Terkait, Kode_Karyawan_Terkait, Flag_Utama, Prioritas, Sumber_Data, Referensi_Sumber);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship') AND name = 'UX_HRIS_Org_Rel_Primary_Open')
    CREATE UNIQUE NONCLUSTERED INDEX UX_HRIS_Org_Rel_Primary_Open ON N_HRIS_Organization_Relationship (Id_Organization_Relationship_Type, Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek) WHERE ([Flag_Aktif]='Y' AND [Flag_Utama]='Y' AND [Berlaku_Sampai] IS NULL);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Organization_Relationship') AND name = 'UX_HRIS_Org_Rel_Type_Subject_Related_Start')
    CREATE UNIQUE NONCLUSTERED INDEX UX_HRIS_Org_Rel_Type_Subject_Related_Start ON N_HRIS_Organization_Relationship (Id_Organization_Relationship_Type, Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek, Kode_Perusahaan_Terkait, Kode_Karyawan_Terkait, Berlaku_Dari);
GO

/* 03. N_PA_Rater_Category */
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
            CONSTRAINT DF_PA_Rater_Category_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Urutan_Pengisian smallint NULL,
        Flag_Boleh_Kalibrasi char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Category_Boleh_Kalibrasi DEFAULT ('T'),
        CONSTRAINT PK_PA_Rater_Category PRIMARY KEY CLUSTERED (Id_PA_Rater_Category)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'IX_PA_Rater_Category_Aktif')
    CREATE NONCLUSTERED INDEX IX_PA_Rater_Category_Aktif ON N_PA_Rater_Category (Flag_Aktif, Nama_Kategori) INCLUDE (Kode_Kategori);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'UX_PA_Rater_Category_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Rater_Category_Kode ON N_PA_Rater_Category (Kode_Kategori);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'UX_PA_Rater_Category_Urutan')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Rater_Category_Urutan ON N_PA_Rater_Category (Urutan_Pengisian) WHERE ([Flag_Aktif]='Y' AND [Urutan_Pengisian] IS NOT NULL);
GO

/* 04. N_PA_Cycle_Status */
IF OBJECT_ID('N_PA_Cycle_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Status (
        Id_PA_Cycle_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Kode_Badge varchar(20) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Status_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Status PRIMARY KEY CLUSTERED (Id_PA_Cycle_Status),
        CONSTRAINT UQ_PA_Cycle_Status_Kode UNIQUE NONCLUSTERED (Kode_Status),
        CONSTRAINT CK_PA_Cycle_Status_Urutan CHECK ([Urutan_Alur]>=(1))
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Status') AND name = 'UX_PA_Cycle_Status_Urutan_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Status_Urutan_Aktif ON N_PA_Cycle_Status (Urutan_Alur) WHERE ([Flag_Aktif]='Y');
GO

/* 05. N_PA_Cycle */
IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle (
        Id_PA_Cycle int IDENTITY(1,1) NOT NULL,
        Kode_Cycle varchar(50) NOT NULL,
        Nama_Cycle nvarchar(150) NOT NULL,
        Tahun smallint NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Periode_Dinilai_Dari date NULL,
        Periode_Dinilai_Sampai date NULL,
        Pengisian_Dari datetime2(0) NULL,
        Pengisian_Sampai datetime2(0) NULL,
        Tanggal_Acuan_Organisasi date NULL,
        PA_Cycle_Sumber_Id int NULL,
        Status_Cycle varchar(20) NOT NULL
            CONSTRAINT DF_PA_Cycle_Status DEFAULT ('DRAFT'),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Aktif DEFAULT ('Y'),
        Locked_At datetime2(0) NULL,
        Locked_By int NULL,
        Published_At datetime2(0) NULL,
        Published_By int NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Langkah_Setup_Aktif varchar(20) NULL,
        Metode_Grade char(5) NOT NULL
            CONSTRAINT DF_PA_Cycle_Metode_Grade DEFAULT ('PITA'),
        CONSTRAINT PK_PA_Cycle PRIMARY KEY CLUSTERED (Id_PA_Cycle),
        CONSTRAINT CK_PA_Cycle_Metode_Grade CHECK ([Metode_Grade]='KURVA' OR [Metode_Grade]='PITA'),
        CONSTRAINT CK_PA_Cycle_Pengisian CHECK ([Pengisian_Dari] IS NULL OR [Pengisian_Sampai] IS NULL OR [Pengisian_Sampai]>=[Pengisian_Dari]),
        CONSTRAINT CK_PA_Cycle_Periode_Dinilai CHECK ([Periode_Dinilai_Dari] IS NULL OR [Periode_Dinilai_Sampai] IS NULL OR [Periode_Dinilai_Sampai]>=[Periode_Dinilai_Dari]),
        CONSTRAINT CK_PA_Cycle_Source_Different CHECK ([PA_Cycle_Sumber_Id] IS NULL OR [PA_Cycle_Sumber_Id]<>[Id_PA_Cycle]),
        CONSTRAINT CK_PA_Cycle_Tahun CHECK ([Tahun]>=(2000)),
        CONSTRAINT FK_PA_Cycle_Source FOREIGN KEY (PA_Cycle_Sumber_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Status FOREIGN KEY (Status_Cycle) REFERENCES N_PA_Cycle_Status (Kode_Status)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle') AND name = 'UX_PA_Cycle_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Kode ON N_PA_Cycle (Kode_Cycle);
GO

/* 06. N_PA_Level */
IF OBJECT_ID('N_PA_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Level (
        ID_Level bigint NOT NULL,
        Nama_Level nvarchar(100) NOT NULL,
        Sumber varchar(10) NOT NULL,
        ID_Level_HRIS_Dasar bigint NULL,
        Urutan decimal(6,2) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Level_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Level_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Level PRIMARY KEY CLUSTERED (ID_Level),
        CONSTRAINT CK_PA_Level_Dasar CHECK ([Sumber]='HRIS' OR [ID_Level_HRIS_Dasar] IS NOT NULL),
        CONSTRAINT CK_PA_Level_Flag_Aktif CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y'),
        CONSTRAINT CK_PA_Level_Rentang_Id CHECK ([Sumber]='HRIS' AND [ID_Level]<(1000000) OR [Sumber]='PA' AND [ID_Level]>=(1000000)),
        CONSTRAINT CK_PA_Level_Sumber CHECK ([Sumber]='PA' OR [Sumber]='HRIS'),
        CONSTRAINT FK_PA_Level_HRIS_Dasar FOREIGN KEY (ID_Level_HRIS_Dasar) REFERENCES HRIS_Level (ID_Level)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Level') AND name = 'UX_PA_Level_Nama_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Level_Nama_Aktif ON N_PA_Level (Nama_Level) WHERE ([Flag_Aktif]='Y');
GO

/* 07. N_PA_Karyawan_Level_PA */
IF OBJECT_ID('N_PA_Karyawan_Level_PA', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Karyawan_Level_PA (
        Id_PA_Karyawan_Level_PA int IDENTITY(1,1) NOT NULL,
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,
        ID_Level_PA bigint NOT NULL,
        ID_Level_HRIS_Saat_Dibuat bigint NULL,
        ID_Level_Jabatan_Saat_Dibuat bigint NULL,
        Alasan nvarchar(500) NOT NULL,
        Berlaku_Dari date NOT NULL,
        Berlaku_Sampai date NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Karyawan_Level_PA_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Karyawan_Level_PA_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        Alasan_Cabut nvarchar(500) NULL,
        CONSTRAINT PK_PA_Karyawan_Level_PA PRIMARY KEY CLUSTERED (Id_PA_Karyawan_Level_PA),
        CONSTRAINT FK_PA_Karyawan_Level_PA_Level FOREIGN KEY (ID_Level_PA) REFERENCES N_PA_Level (ID_Level)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Karyawan_Level_PA') AND name = 'UX_PA_Karyawan_Level_PA_Karyawan_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Karyawan_Level_PA_Karyawan_Aktif ON N_PA_Karyawan_Level_PA (Kode_Perusahaan, Kode_Karyawan) WHERE ([Flag_Aktif]='Y');
GO

/* 08. N_PA_Rater_Policy_Status */
IF OBJECT_ID('N_PA_Rater_Policy_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Rater_Policy_Status (
        Id_PA_Rater_Policy_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Status_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Rater_Policy_Status PRIMARY KEY CLUSTERED (Id_PA_Rater_Policy_Status),
        CONSTRAINT UQ_PA_Rater_Policy_Status_Kode UNIQUE NONCLUSTERED (Kode_Status),
        CONSTRAINT CK_PA_Rater_Policy_Status_Urutan CHECK ([Urutan_Alur]>=(1))
    );
END;
GO

/* 09. N_PA_Rater_Policy */
IF OBJECT_ID('N_PA_Rater_Policy', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Rater_Policy (
        Id_PA_Rater_Policy int IDENTITY(1,1) NOT NULL,
        Kode_Policy varchar(50) NOT NULL,
        Nama_Policy nvarchar(150) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        ID_Level bigint NOT NULL,
        Versi int NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Versi DEFAULT ((1)),
        PA_Rater_Policy_Sumber_Id int NULL,
        Status_Policy varchar(20) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Status DEFAULT ('DRAFT'),
        Berlaku_Dari date NOT NULL,
        Berlaku_Sampai date NULL,
        Catatan_Versi nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Activated_At datetime2(0) NULL,
        Activated_By int NULL,
        CONSTRAINT PK_PA_Rater_Policy PRIMARY KEY CLUSTERED (Id_PA_Rater_Policy),
        CONSTRAINT CK_PA_Rater_Policy_Date_Range CHECK ([Berlaku_Sampai] IS NULL OR [Berlaku_Sampai]>=[Berlaku_Dari]),
        CONSTRAINT CK_PA_Rater_Policy_Source_Different CHECK ([PA_Rater_Policy_Sumber_Id] IS NULL OR [PA_Rater_Policy_Sumber_Id]<>[Id_PA_Rater_Policy]),
        CONSTRAINT CK_PA_Rater_Policy_Versi CHECK ([Versi]>=(1)),
        CONSTRAINT FK_PA_Rater_Policy_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level),
        CONSTRAINT FK_PA_Rater_Policy_Source FOREIGN KEY (PA_Rater_Policy_Sumber_Id) REFERENCES N_PA_Rater_Policy (Id_PA_Rater_Policy),
        CONSTRAINT FK_PA_Rater_Policy_Status FOREIGN KEY (Status_Policy) REFERENCES N_PA_Rater_Policy_Status (Kode_Status)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Policy') AND name = 'IX_PA_Rater_Policy_Level_Period')
    CREATE NONCLUSTERED INDEX IX_PA_Rater_Policy_Level_Period ON N_PA_Rater_Policy (ID_Level, Flag_Aktif, Status_Policy, Berlaku_Dari, Berlaku_Sampai) INCLUDE (Kode_Policy, Nama_Policy, Versi);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Policy') AND name = 'IX_PA_Rater_Policy_Source')
    CREATE NONCLUSTERED INDEX IX_PA_Rater_Policy_Source ON N_PA_Rater_Policy (PA_Rater_Policy_Sumber_Id) WHERE ([PA_Rater_Policy_Sumber_Id] IS NOT NULL);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Policy') AND name = 'UX_PA_Rater_Policy_Kode_Versi')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Rater_Policy_Kode_Versi ON N_PA_Rater_Policy (Kode_Policy, Versi);
GO

/* 10. N_PA_Cycle_Rater_Policy_Snapshot */
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
            CONSTRAINT DF_PA_Cycle_Rater_Policy_Snap_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Rater_Policy_Snapshot PRIMARY KEY CLUSTERED (Id_PA_Cycle_Rater_Policy_Snapshot),
        CONSTRAINT CK_PA_Cycle_Rater_Policy_Snap_Versi CHECK ([Versi_Policy_Salinan]>=(1)),
        CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level),
        CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Source FOREIGN KEY (PA_Rater_Policy_Id) REFERENCES N_PA_Rater_Policy (Id_PA_Rater_Policy)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot') AND name = 'UX_PA_Cycle_Rater_Policy_Snap_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Rater_Policy_Snap_Aktif ON N_PA_Cycle_Rater_Policy_Snapshot (PA_Cycle_Id, ID_Level) WHERE ([Flag_Aktif]='Y');
GO

/* 11. N_PA_Participant_Status */
IF OBJECT_ID('N_PA_Participant_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Status (
        Id_PA_Participant_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Status_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Status PRIMARY KEY CLUSTERED (Id_PA_Participant_Status),
        CONSTRAINT UQ_PA_Participant_Status_Kode UNIQUE NONCLUSTERED (Kode_Status),
        CONSTRAINT CK_PA_Participant_Status_Urutan CHECK ([Urutan_Alur]>=(1))
    );
END;
GO

/* 12. N_PA_Participant */
IF OBJECT_ID('N_PA_Participant', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant (
        Id_PA_Participant int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(15) NOT NULL,
        Nama_Karyawan_Saat_Cycle varchar(255) NOT NULL,
        ID_Level_Jabatan_Saat_Cycle bigint NOT NULL,
        ID_Level_Saat_Cycle bigint NOT NULL,
        Nama_Level_Saat_Cycle varchar(100) NULL,
        ID_Divisi_Sub_Divisi_Saat_Cycle bigint NULL,
        ID_Divisi_Saat_Cycle bigint NULL,
        Nama_Divisi_Saat_Cycle varchar(100) NULL,
        ID_Sub_Divisi_Saat_Cycle bigint NULL,
        Nama_Sub_Divisi_Saat_Cycle varchar(100) NULL,
        Kode_Jabatan_Saat_Cycle varchar(30) NULL,
        Nama_Jabatan_Saat_Cycle varchar(100) NULL,
        Lokasi_Saat_Cycle varchar(15) NULL,
        PA_Cycle_Rater_Policy_Snapshot_Id int NULL,
        Status_Peserta varchar(20) NOT NULL
            CONSTRAINT DF_PA_Participant_Status DEFAULT ('TERTUNDA'),
        Alasan_Dikecualikan nvarchar(500) NULL,
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Snapshot_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Aktif DEFAULT ('Y'),
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        ID_Level_HRIS_Saat_Cycle bigint NULL,
        Nama_Level_HRIS_Saat_Cycle varchar(100) NULL,
        PA_Karyawan_Level_PA_Id int NULL,
        CONSTRAINT PK_PA_Participant PRIMARY KEY CLUSTERED (Id_PA_Participant),
        CONSTRAINT FK_PA_Participant_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Participant_Karyawan FOREIGN KEY (Kode_Perusahaan, Kode_Karyawan) REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan),
        CONSTRAINT FK_PA_Participant_Karyawan_Level_PA FOREIGN KEY (PA_Karyawan_Level_PA_Id) REFERENCES N_PA_Karyawan_Level_PA (Id_PA_Karyawan_Level_PA),
        CONSTRAINT FK_PA_Participant_Level FOREIGN KEY (ID_Level_Saat_Cycle) REFERENCES N_PA_Level (ID_Level),
        CONSTRAINT FK_PA_Participant_Rater_Snapshot FOREIGN KEY (PA_Cycle_Rater_Policy_Snapshot_Id) REFERENCES N_PA_Cycle_Rater_Policy_Snapshot (Id_PA_Cycle_Rater_Policy_Snapshot),
        CONSTRAINT FK_PA_Participant_Status FOREIGN KEY (Status_Peserta) REFERENCES N_PA_Participant_Status (Kode_Status)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Participant') AND name = 'IX_PA_Participant_Cycle_Status')
    CREATE NONCLUSTERED INDEX IX_PA_Participant_Cycle_Status ON N_PA_Participant (PA_Cycle_Id, Status_Peserta, Flag_Aktif) INCLUDE (ID_Level_Saat_Cycle, Kode_Karyawan, Nama_Karyawan_Saat_Cycle, PA_Cycle_Rater_Policy_Snapshot_Id, ID_Divisi_Saat_Cycle, ID_Sub_Divisi_Saat_Cycle, Lokasi_Saat_Cycle);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Participant') AND name = 'UX_PA_Participant_Cycle_Karyawan')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Participant_Cycle_Karyawan ON N_PA_Participant (PA_Cycle_Id, Kode_Perusahaan, Kode_Karyawan) WHERE ([Flag_Aktif]='Y');
GO

/* 13. N_PA_Assignment_Status */
IF OBJECT_ID('N_PA_Assignment_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Status (
        Id_PA_Assignment_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Status_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment_Status PRIMARY KEY CLUSTERED (Id_PA_Assignment_Status),
        CONSTRAINT UQ_PA_Assignment_Status_Kode UNIQUE NONCLUSTERED (Kode_Status),
        CONSTRAINT CK_PA_Assignment_Status_Urutan CHECK ([Urutan_Alur]>=(1))
    );
END;
GO

/* 14. N_PA_Assignment_Source */
IF OBJECT_ID('N_PA_Assignment_Source', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Source (
        Id_PA_Assignment_Source int IDENTITY(1,1) NOT NULL,
        Kode_Sumber varchar(20) NOT NULL,
        Nama_Sumber nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Source_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Source_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment_Source PRIMARY KEY CLUSTERED (Id_PA_Assignment_Source),
        CONSTRAINT UQ_PA_Assignment_Source_Kode UNIQUE NONCLUSTERED (Kode_Sumber)
    );
END;
GO

/* 15. N_PA_Assignment */
IF OBJECT_ID('N_PA_Assignment', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment (
        Id_PA_Assignment int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        Kode_Perusahaan_Penilai varchar(3) NOT NULL,
        Kode_Karyawan_Penilai varchar(15) NOT NULL,
        Nama_Penilai_Saat_Cycle varchar(255) NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        Bobot_Dalam_Kategori decimal(5,2) NOT NULL,
        Sumber_Penugasan varchar(20) NOT NULL
            CONSTRAINT DF_PA_Assignment_Sumber DEFAULT ('USULAN'),
        Organization_Relationship_Id int NULL,
        Alasan_Manual nvarchar(500) NULL,
        Status_Penugasan varchar(20) NOT NULL
            CONSTRAINT DF_PA_Assignment_Status DEFAULT ('BELUM_MULAI'),
        Due_At date NULL,
        Started_At datetime2(0) NULL,
        Finished_At datetime2(0) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Cancel_Reason nvarchar(500) NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        Bobot_Kustom char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Bobot_Kustom DEFAULT ('T'),
        Bobot_Diatur_Oleh int NULL,
        Bobot_Diatur_Pada datetime2(0) NULL,
        Submitted_At datetime2(0) NULL,
        Submitted_By int NULL,
        Submitted_Via varchar(20) NULL,
        CONSTRAINT PK_PA_Assignment PRIMARY KEY CLUSTERED (Id_PA_Assignment),
        CONSTRAINT CK_PA_Assignment_Bobot CHECK ([Bobot_Dalam_Kategori]>=(0) AND [Bobot_Dalam_Kategori]<=(100)),
        CONSTRAINT CK_PA_Assignment_Bobot_Kustom CHECK ([Bobot_Kustom]='T' OR [Bobot_Kustom]='Y'),
        CONSTRAINT CK_PA_Assignment_Bobot_Kustom_Jejak CHECK ([Bobot_Kustom]='T' OR [Bobot_Diatur_Pada] IS NOT NULL),
        CONSTRAINT CK_PA_Assignment_Cancel_Lengkap CHECK ([Cancelled_At] IS NULL OR [Cancel_Reason] IS NOT NULL),
        CONSTRAINT CK_PA_Assignment_Cancel_Reason CHECK ([Cancel_Reason] IS NULL OR len(ltrim(rtrim([Cancel_Reason])))>(0)),
        CONSTRAINT CK_PA_Assignment_Rentang_Waktu CHECK ([Finished_At] IS NULL OR [Started_At] IS NULL OR [Finished_At]>=[Started_At]),
        CONSTRAINT FK_PA_Assignment_Category FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category),
        CONSTRAINT FK_PA_Assignment_Org_Relationship FOREIGN KEY (Organization_Relationship_Id) REFERENCES N_HRIS_Organization_Relationship (Id_Organization_Relationship),
        CONSTRAINT FK_PA_Assignment_Participant FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant),
        CONSTRAINT FK_PA_Assignment_Penilai FOREIGN KEY (Kode_Perusahaan_Penilai, Kode_Karyawan_Penilai) REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan),
        CONSTRAINT FK_PA_Assignment_Status_Master FOREIGN KEY (Status_Penugasan) REFERENCES N_PA_Assignment_Status (Kode_Status),
        CONSTRAINT FK_PA_Assignment_Sumber FOREIGN KEY (Sumber_Penugasan) REFERENCES N_PA_Assignment_Source (Kode_Sumber)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment') AND name = 'IX_PA_Assignment_Penilai_Status')
    CREATE NONCLUSTERED INDEX IX_PA_Assignment_Penilai_Status ON N_PA_Assignment (Kode_Perusahaan_Penilai, Kode_Karyawan_Penilai, Status_Penugasan, Flag_Aktif) INCLUDE (PA_Participant_Id, Due_At);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment') AND name = 'IX_PA_Assignment_Peserta_Status')
    CREATE NONCLUSTERED INDEX IX_PA_Assignment_Peserta_Status ON N_PA_Assignment (PA_Participant_Id, Status_Penugasan, Flag_Aktif) INCLUDE (PA_Rater_Category_Id, Bobot_Dalam_Kategori, Finished_At);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment') AND name = 'UX_PA_Assignment_Peserta_Penilai')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Assignment_Peserta_Penilai ON N_PA_Assignment (PA_Participant_Id, Kode_Perusahaan_Penilai, Kode_Karyawan_Penilai, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 16. N_PA_Cycle_Question_Package */
IF OBJECT_ID('N_PA_Cycle_Question_Package', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Question_Package (
        Id_PA_Cycle_Question_Package int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        ID_Level bigint NOT NULL,
        Id_Master_Ujian varchar(100) NOT NULL,
        Nama_Ujian_Sumber nvarchar(200) NULL,
        Sumber_Kontrak varchar(20) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Package_Sumber DEFAULT ('OTOMATIS'),
        Catatan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Package_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Package_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        PA_Rater_Category_Id int NULL,
        Id_Master_Paket_Ujian varchar(100) NULL,
        Nama_Paket_Ujian_Sumber nvarchar(255) NULL,
        CONSTRAINT PK_PA_Cycle_Question_Package PRIMARY KEY CLUSTERED (Id_PA_Cycle_Question_Package),
        CONSTRAINT FK_PA_Cycle_Question_Package_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Question_Package_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level),
        CONSTRAINT FK_PA_CycleQPkg_RaterCategory FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package') AND name = 'IX_PA_CycleQPkg_Cycle_Aktif')
    CREATE NONCLUSTERED INDEX IX_PA_CycleQPkg_Cycle_Aktif ON N_PA_Cycle_Question_Package (PA_Cycle_Id, Flag_Aktif) INCLUDE (ID_Level, PA_Rater_Category_Id, Id_Master_Ujian, Nama_Ujian_Sumber);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package') AND name = 'UX_PA_CycleQPkg_Cycle_Level_Rater')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_CycleQPkg_Cycle_Level_Rater ON N_PA_Cycle_Question_Package (PA_Cycle_Id, ID_Level, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 17. N_PA_Assignment_Integration */
IF OBJECT_ID('N_PA_Assignment_Integration', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Integration (
        Id_PA_Assignment_Integration int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Id int NOT NULL,
        PA_Cycle_Question_Package_Id int NULL,
        Id_Ujian_Token varchar(255) NULL,
        Payload_Json nvarchar(max) NULL,
        Started_At datetime2(0) NULL,
        Finished_At datetime2(0) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Integration_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Integration_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Api_Ref varchar(64) NULL,
        Short_Token varchar(32) NULL,
        Akses_Otp varchar(32) NULL,
        Link_Ujian nvarchar(500) NULL,
        Process_Id varchar(64) NULL,
        Status_Integrasi varchar(20) NOT NULL
            CONSTRAINT DF_PA_AsgIntg_Status DEFAULT ('ANTRE'),
        Pesan_Gagal nvarchar(500) NULL,
        Waktu_Mulai datetime2(0) NULL,
        Waktu_Akhir datetime2(0) NULL,
        CONSTRAINT PK_PA_Assignment_Integration PRIMARY KEY CLUSTERED (Id_PA_Assignment_Integration),
        CONSTRAINT CK_PA_AsgIntg_Status CHECK ([Status_Integrasi]='GAGAL' OR [Status_Integrasi]='SUDAH_ADA' OR [Status_Integrasi]='BERHASIL' OR [Status_Integrasi]='ANTRE'),
        CONSTRAINT CK_PA_Assignment_Integration_Jendela CHECK ([Finished_At] IS NULL OR [Started_At] IS NULL OR [Finished_At]>=[Started_At]),
        CONSTRAINT FK_PA_Assignment_Integration_Assignment FOREIGN KEY (PA_Assignment_Id) REFERENCES N_PA_Assignment (Id_PA_Assignment),
        CONSTRAINT FK_PA_Assignment_Integration_Package FOREIGN KEY (PA_Cycle_Question_Package_Id) REFERENCES N_PA_Cycle_Question_Package (Id_PA_Cycle_Question_Package)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'IX_PA_AsgIntg_Proses')
    CREATE NONCLUSTERED INDEX IX_PA_AsgIntg_Proses ON N_PA_Assignment_Integration (Process_Id, Status_Integrasi) INCLUDE (PA_Assignment_Id, Api_Ref, Flag_Aktif);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'UX_PA_AsgIntg_ApiRef')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_AsgIntg_ApiRef ON N_PA_Assignment_Integration (Api_Ref) WHERE ([Api_Ref] IS NOT NULL AND [Flag_Aktif]='Y');
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'UX_PA_Assignment_Integration_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Assignment_Integration_Aktif ON N_PA_Assignment_Integration (PA_Assignment_Id) WHERE ([Flag_Aktif]='Y');
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'UX_PA_Assignment_Integration_Token')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Assignment_Integration_Token ON N_PA_Assignment_Integration (Id_Ujian_Token) WHERE ([Id_Ujian_Token] IS NOT NULL);
GO

/* 18. N_PA_Assignment_Payload */
IF OBJECT_ID('N_PA_Assignment_Payload', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Payload (
        Id_PA_Assignment_Payload int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Integration_Id int NOT NULL,
        Payload_Json nvarchar(max) NOT NULL,
        Payload_Hash varchar(64) NOT NULL,
        Id_Ujian_Nilai_Akhir varchar(255) NULL,
        Api_Version varchar(20) NULL,
        Diterima_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Payload_Diterima DEFAULT (sysdatetime()),
        Flag_Terpeta char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Payload_Terpeta DEFAULT ('T'),
        Mapper_Version int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Api_Reference varchar(64) NULL,
        Status_Skor varchar(30) NULL,
        Kode_Akses varchar(50) NULL,
        Flag_Multi char(1) NULL,
        Total_Nilai decimal(9,2) NULL,
        Nama_Tes nvarchar(255) NULL,
        Waktu_Mulai_Akses datetime2(0) NULL,
        Waktu_Selesai_Akses datetime2(0) NULL,
        Ambang_Batas_Nilai decimal(9,2) NULL,
        Status_Kelulusan nvarchar(50) NULL,
        Warna varchar(20) NULL,
        CONSTRAINT PK_PA_Assignment_Payload PRIMARY KEY CLUSTERED (Id_PA_Assignment_Payload),
        CONSTRAINT CK_PA_AsgPayload_Flag_Multi CHECK ([Flag_Multi] IS NULL OR ([Flag_Multi]='T' OR [Flag_Multi]='Y')),
        CONSTRAINT CK_PA_AsgPayload_Status_Skor CHECK ([Status_Skor] IS NULL OR ([Status_Skor]='DITOLAK' OR [Status_Skor]='MENUNGGU_VERIFIKASI' OR [Status_Skor]='FINAL')),
        CONSTRAINT CK_PA_Assignment_Payload_Hash CHECK (len([Payload_Hash])=(64)),
        CONSTRAINT CK_PA_Assignment_Payload_Mapper CHECK ([Mapper_Version] IS NULL OR [Mapper_Version]>=(1)),
        CONSTRAINT CK_PA_Assignment_Payload_Terpeta CHECK ([Flag_Terpeta]='T' OR [Mapper_Version] IS NOT NULL),
        CONSTRAINT FK_PA_Assignment_Payload_Integration FOREIGN KEY (PA_Assignment_Integration_Id) REFERENCES N_PA_Assignment_Integration (Id_PA_Assignment_Integration)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Payload') AND name = 'IX_PA_AsgPayload_ApiRef')
    CREATE NONCLUSTERED INDEX IX_PA_AsgPayload_ApiRef ON N_PA_Assignment_Payload (Api_Reference) INCLUDE (PA_Assignment_Integration_Id, Status_Skor, Diterima_At) WHERE ([Api_Reference] IS NOT NULL);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Payload') AND name = 'IX_PA_Assignment_Payload_Hash')
    CREATE NONCLUSTERED INDEX IX_PA_Assignment_Payload_Hash ON N_PA_Assignment_Payload (Payload_Hash);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Payload') AND name = 'IX_PA_Assignment_Payload_Integration')
    CREATE NONCLUSTERED INDEX IX_PA_Assignment_Payload_Integration ON N_PA_Assignment_Payload (PA_Assignment_Integration_Id, Flag_Terpeta) INCLUDE (Diterima_At, Mapper_Version);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Payload') AND name = 'UX_PA_Assignment_Payload_Nilai_Akhir')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Assignment_Payload_Nilai_Akhir ON N_PA_Assignment_Payload (Id_Ujian_Nilai_Akhir) WHERE ([Id_Ujian_Nilai_Akhir] IS NOT NULL);
GO

/* 19. N_PA_Competency_Type */
IF OBJECT_ID('N_PA_Competency_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Type (
        Id_PA_Competency_Type int IDENTITY(1,1) NOT NULL,
        Kode_Type varchar(40) NOT NULL,
        Nama_Type nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Type_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Competency_Type PRIMARY KEY CLUSTERED (Id_PA_Competency_Type),
        CONSTRAINT CK_PA_Competency_Type_Urutan CHECK ([Urutan_Tampil]>=(1))
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency_Type') AND name = 'UX_PA_Competency_Type_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Competency_Type_Kode ON N_PA_Competency_Type (Kode_Type);
GO

/* 20. N_PA_Competency */
IF OBJECT_ID('N_PA_Competency', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency (
        Id_PA_Competency int IDENTITY(1,1) NOT NULL,
        PA_Competency_Type_Id int NOT NULL,
        Kode_Competency varchar(40) NOT NULL,
        Nama_Competency nvarchar(150) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Flag_Berskor char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Berskor DEFAULT ('Y'),
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Nilai_Ambang decimal(5,2) NULL,
        Flag_Radar char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Radar DEFAULT ('Y'),
        Expected_Behaviour nvarchar(2000) NULL,
        CONSTRAINT PK_PA_Competency PRIMARY KEY CLUSTERED (Id_PA_Competency),
        CONSTRAINT CK_PA_Competency_Ambang CHECK ([Nilai_Ambang] IS NULL OR [Nilai_Ambang]>=(0) AND [Nilai_Ambang]<=(100)),
        CONSTRAINT CK_PA_Competency_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Competency_Type FOREIGN KEY (PA_Competency_Type_Id) REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency') AND name = 'IX_PA_Competency_Type_Urutan')
    CREATE NONCLUSTERED INDEX IX_PA_Competency_Type_Urutan ON N_PA_Competency (PA_Competency_Type_Id, Flag_Aktif, Urutan_Tampil) INCLUDE (Kode_Competency, Nama_Competency, Flag_Berskor);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency') AND name = 'UX_PA_Competency_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Competency_Kode ON N_PA_Competency (Kode_Competency);
GO

/* 21. N_PA_Assignment_Score */
IF OBJECT_ID('N_PA_Assignment_Score', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Score (
        Id_PA_Assignment_Score int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Payload_Id int NOT NULL,
        PA_Competency_Id int NULL,
        Kode_Soal_Sumber varchar(100) NOT NULL,
        Jenis_Soal_Sumber varchar(100) NULL,
        Skor decimal(6,2) NULL,
        Skor_Maksimal decimal(6,2) NULL,
        Bobot_Sumber decimal(5,2) NULL,
        Jawaban_Teks nvarchar(max) NULL,
        Mapper_Version int NOT NULL,
        Urutan_Tampil smallint NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Score_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Score_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Kode_Alat_Tes varchar(50) NULL,
        Nama_Alat_Tes nvarchar(255) NULL,
        Status_Interpretation nvarchar(50) NULL,
        Warna varchar(20) NULL,
        Keterangan nvarchar(500) NULL,
        Jumlah_Soal smallint NULL,
        Jumlah_Terjawab smallint NULL,
        CONSTRAINT PK_PA_Assignment_Score PRIMARY KEY CLUSTERED (Id_PA_Assignment_Score),
        CONSTRAINT CK_PA_AsgScore_Terjawab CHECK ([Jumlah_Soal] IS NULL OR [Jumlah_Terjawab] IS NULL OR [Jumlah_Soal]>=(0) AND [Jumlah_Terjawab]>=(0) AND [Jumlah_Terjawab]<=[Jumlah_Soal]),
        CONSTRAINT CK_PA_Assignment_Score_Batas CHECK ([Skor] IS NULL OR [Skor_Maksimal] IS NULL OR [Skor]<=[Skor_Maksimal]),
        CONSTRAINT CK_PA_Assignment_Score_Bobot CHECK ([Bobot_Sumber] IS NULL OR [Bobot_Sumber]>=(0) AND [Bobot_Sumber]<=(100)),
        CONSTRAINT CK_PA_Assignment_Score_Maksimal CHECK ([Skor_Maksimal] IS NULL OR [Skor_Maksimal]>(0)),
        CONSTRAINT CK_PA_Assignment_Score_Mapper CHECK ([Mapper_Version]>=(1)),
        CONSTRAINT CK_PA_Assignment_Score_Skor CHECK ([Skor] IS NULL OR [Skor]>=(0)),
        CONSTRAINT CK_PA_Assignment_Score_Urutan CHECK ([Urutan_Tampil] IS NULL OR [Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Assignment_Score_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Assignment_Score_Payload FOREIGN KEY (PA_Assignment_Payload_Id) REFERENCES N_PA_Assignment_Payload (Id_PA_Assignment_Payload)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Score') AND name = 'IX_PA_Assignment_Score_Agregasi')
    CREATE NONCLUSTERED INDEX IX_PA_Assignment_Score_Agregasi ON N_PA_Assignment_Score (PA_Assignment_Payload_Id, Flag_Aktif) INCLUDE (PA_Competency_Id, Skor, Skor_Maksimal, Bobot_Sumber);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Score') AND name = 'IX_PA_Assignment_Score_Competency')
    CREATE NONCLUSTERED INDEX IX_PA_Assignment_Score_Competency ON N_PA_Assignment_Score (PA_Competency_Id, Flag_Aktif) INCLUDE (Skor, Skor_Maksimal);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Score') AND name = 'UX_PA_Assignment_Score_Butir')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Assignment_Score_Butir ON N_PA_Assignment_Score (PA_Assignment_Payload_Id, Kode_Soal_Sumber) WHERE ([Flag_Aktif]='Y');
GO

/* 22. N_PA_Assignment_Score_Esai */
IF OBJECT_ID('N_PA_Assignment_Score_Esai', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Score_Esai (
        Id_PA_Assignment_Score_Esai int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Score_Id int NOT NULL,
        Id_Master_Soal int NULL,
        Urutan_Tampil smallint NOT NULL,
        Pertanyaan nvarchar(max) NULL,
        Jawaban_Teks nvarchar(max) NULL,
        Waktu_Dijawab datetime2(0) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Score_Esai_Created_At DEFAULT (sysdatetime()),
        CONSTRAINT PK_PA_Assignment_Score_Esai PRIMARY KEY CLUSTERED (Id_PA_Assignment_Score_Esai),
        CONSTRAINT CK_PA_Assignment_Score_Esai_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Assignment_Score_Esai_Score FOREIGN KEY (PA_Assignment_Score_Id) REFERENCES N_PA_Assignment_Score (Id_PA_Assignment_Score)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Assignment_Score_Esai') AND name = 'UX_PA_Assignment_Score_Esai_Urutan')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Assignment_Score_Esai_Urutan ON N_PA_Assignment_Score_Esai (PA_Assignment_Score_Id, Urutan_Tampil);
GO

/* 23. N_PA_Balancing_Sesi */
IF OBJECT_ID('N_PA_Balancing_Sesi', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Sesi (
        Id_PA_Balancing_Sesi int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        Nama_Sesi nvarchar(200) NOT NULL,
        PIC_User_Id int NOT NULL,
        Status_Sesi varchar(20) NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Status DEFAULT ('TERBUKA'),
        Catatan_Rapat nvarchar(max) NULL,
        Versi_Data int NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Versi DEFAULT ((1)),
        Final_At datetime2(0) NULL,
        Final_By int NULL,
        Nomor_Hitung_Ulang int NULL,
        Hitung_Ulang_At datetime2(0) NULL,
        Hitung_Ulang_By int NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        Alasan_Batal nvarchar(500) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Balancing_Sesi PRIMARY KEY CLUSTERED (Id_PA_Balancing_Sesi),
        CONSTRAINT CK_PA_Balancing_Sesi_Versi CHECK ([Versi_Data]>=(1)),
        CONSTRAINT FK_PA_Balancing_Sesi_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Sesi') AND name = 'IX_PA_Balancing_Sesi_Cycle_Status')
    CREATE NONCLUSTERED INDEX IX_PA_Balancing_Sesi_Cycle_Status ON N_PA_Balancing_Sesi (PA_Cycle_Id, Status_Sesi);
GO

/* 24. N_PA_Balancing_Forum */
IF OBJECT_ID('N_PA_Balancing_Forum', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Forum (
        Id_PA_Balancing_Forum int IDENTITY(1,1) NOT NULL,
        PA_Balancing_Sesi_Id int NOT NULL,
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,
        Nama_Karyawan varchar(255) NOT NULL,
        Peran nvarchar(100) NOT NULL,
        Urutan_Tampil smallint NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Forum_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT PK_PA_Balancing_Forum PRIMARY KEY CLUSTERED (Id_PA_Balancing_Forum),
        CONSTRAINT CK_PA_Balancing_Forum_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Balancing_Forum_Sesi FOREIGN KEY (PA_Balancing_Sesi_Id) REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Forum') AND name = 'IX_PA_Balancing_Forum_Sesi')
    CREATE NONCLUSTERED INDEX IX_PA_Balancing_Forum_Sesi ON N_PA_Balancing_Forum (PA_Balancing_Sesi_Id);
GO

/* 25. N_PA_Balancing_Jejak_Hitung */
IF OBJECT_ID('N_PA_Balancing_Jejak_Hitung', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Jejak_Hitung (
        Id_PA_Balancing_Jejak_Hitung int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        Nomor_Hitung_Ulang int NOT NULL,
        PA_Participant_Id int NOT NULL,
        PA_Balancing_Sesi_Id int NULL,
        Nilai_Kompetensi_Sebelum decimal(6,2) NULL,
        Nilai_Kompetensi_Sesudah decimal(6,2) NULL,
        Final_Score_Sebelum decimal(6,2) NULL,
        Final_Score_Sesudah decimal(6,2) NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        Kode_Grade_Sesudah varchar(40) NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        PA_Hav_Quadrant_Sesudah_Id int NULL,
        Sebab varchar(20) NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Jejak_Hitung_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT PK_PA_Balancing_Jejak_Hitung PRIMARY KEY CLUSTERED (Id_PA_Balancing_Jejak_Hitung),
        CONSTRAINT CK_PA_Balancing_Jejak_Hitung_Nomor CHECK ([Nomor_Hitung_Ulang]>=(1)),
        CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Participant FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant),
        CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Sesi FOREIGN KEY (PA_Balancing_Sesi_Id) REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Jejak_Hitung') AND name = 'IX_PA_Balancing_Jejak_Hitung_Cycle_Nomor')
    CREATE NONCLUSTERED INDEX IX_PA_Balancing_Jejak_Hitung_Cycle_Nomor ON N_PA_Balancing_Jejak_Hitung (PA_Cycle_Id, Nomor_Hitung_Ulang);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Jejak_Hitung') AND name = 'IX_PA_Balancing_Jejak_Hitung_Participant')
    CREATE NONCLUSTERED INDEX IX_PA_Balancing_Jejak_Hitung_Participant ON N_PA_Balancing_Jejak_Hitung (PA_Participant_Id);
GO

/* 26. N_PA_Curve_Group */
IF OBJECT_ID('N_PA_Curve_Group', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Curve_Group (
        Id_PA_Curve_Group int IDENTITY(1,1) NOT NULL,
        Kode_Grup varchar(40) NOT NULL,
        Nama_Grup nvarchar(200) NOT NULL,
        Urutan_Tampil smallint NOT NULL
            CONSTRAINT DF_PA_Curve_Group_Urutan DEFAULT ((1)),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Curve_Group_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Curve_Group_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Curve_Group PRIMARY KEY CLUSTERED (Id_PA_Curve_Group)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Curve_Group') AND name = 'UX_PA_Curve_Group_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Curve_Group_Kode ON N_PA_Curve_Group (Kode_Grup) WHERE ([Flag_Aktif]='Y');
GO

/* 27. N_PA_Cycle_Curve_Group */
IF OBJECT_ID('N_PA_Cycle_Curve_Group', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Group (
        Id_PA_Cycle_Curve_Group int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        PA_Curve_Group_Id int NULL,
        Kode_Grup varchar(40) NOT NULL,
        Nama_Grup nvarchar(200) NOT NULL,
        Urutan_Tampil smallint NOT NULL
            CONSTRAINT DF_PA_Cycle_Curve_Group_Urutan DEFAULT ((1)),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Curve_Group_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Curve_Group_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT PK_PA_Cycle_Curve_Group PRIMARY KEY CLUSTERED (Id_PA_Cycle_Curve_Group),
        CONSTRAINT FK_PA_Cycle_Curve_Group_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Curve_Group_Sumber FOREIGN KEY (PA_Curve_Group_Id) REFERENCES N_PA_Curve_Group (Id_PA_Curve_Group)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Group') AND name = 'UX_PA_Cycle_Curve_Group_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Curve_Group_Kode ON N_PA_Cycle_Curve_Group (PA_Cycle_Id, Kode_Grup) WHERE ([Flag_Aktif]='Y');
GO

/* 28. N_PA_Cycle_Curve */
IF OBJECT_ID('N_PA_Cycle_Curve', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve (
        Id_PA_Cycle_Curve int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        PA_Cycle_Curve_Group_Id int NOT NULL,
        Jumlah_Peserta int NOT NULL,
        Rata_Rata decimal(9,4) NULL,
        Simpangan_Baku decimal(9,4) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Curve_Aktif DEFAULT ('Y'),
        Locked_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Curve_Locked DEFAULT (sysdatetime()),
        Locked_By int NULL,
        Unlocked_At datetime2(0) NULL,
        Unlocked_By int NULL,
        Alasan_Buka nvarchar(500) NULL,
        CONSTRAINT PK_PA_Cycle_Curve PRIMARY KEY CLUSTERED (Id_PA_Cycle_Curve),
        CONSTRAINT CK_PA_Cycle_Curve_Buka CHECK ([Flag_Aktif]='Y' OR [Unlocked_At] IS NOT NULL AND [Alasan_Buka] IS NOT NULL),
        CONSTRAINT CK_PA_Cycle_Curve_Jumlah CHECK ([Jumlah_Peserta]>=(0)),
        CONSTRAINT CK_PA_Cycle_Curve_Sd CHECK ([Simpangan_Baku] IS NULL OR [Simpangan_Baku]>=(0)),
        CONSTRAINT FK_PA_Cycle_Curve_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Curve_Grup FOREIGN KEY (PA_Cycle_Curve_Group_Id) REFERENCES N_PA_Cycle_Curve_Group (Id_PA_Cycle_Curve_Group)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve') AND name = 'IX_PA_Cycle_Curve_Cycle')
    CREATE NONCLUSTERED INDEX IX_PA_Cycle_Curve_Cycle ON N_PA_Cycle_Curve (PA_Cycle_Id, Flag_Aktif);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve') AND name = 'UX_PA_Cycle_Curve_Grup_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Curve_Grup_Aktif ON N_PA_Cycle_Curve (PA_Cycle_Curve_Group_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 29. N_PA_Hav_Quadrant */
IF OBJECT_ID('N_PA_Hav_Quadrant', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Hav_Quadrant (
        Id_PA_Hav_Quadrant int IDENTITY(1,1) NOT NULL,
        Kode_Kuadran varchar(40) NOT NULL,
        Nama_Kuadran nvarchar(100) NOT NULL,
        Nomor_Box smallint NOT NULL,
        Tingkat_Performa varchar(20) NOT NULL,
        Tingkat_Kompetensi varchar(20) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Warna varchar(20) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Hav_Quadrant_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Hav_Quadrant_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Hav_Quadrant PRIMARY KEY CLUSTERED (Id_PA_Hav_Quadrant),
        CONSTRAINT CK_PA_Hav_Quadrant_Nomor_Box CHECK ([Nomor_Box]>=(1)),
        CONSTRAINT CK_PA_Hav_Quadrant_Urutan CHECK ([Urutan_Tampil]>=(1))
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'IX_PA_Hav_Quadrant_Lookup')
    CREATE NONCLUSTERED INDEX IX_PA_Hav_Quadrant_Lookup ON N_PA_Hav_Quadrant (Tingkat_Performa, Tingkat_Kompetensi, Flag_Aktif) INCLUDE (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Warna);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'UX_PA_Hav_Quadrant_Box_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Hav_Quadrant_Box_Aktif ON N_PA_Hav_Quadrant (Nomor_Box) WHERE ([Flag_Aktif]='Y');
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'UX_PA_Hav_Quadrant_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Hav_Quadrant_Kode ON N_PA_Hav_Quadrant (Kode_Kuadran);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'UX_PA_Hav_Quadrant_Kombinasi_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Hav_Quadrant_Kombinasi_Aktif ON N_PA_Hav_Quadrant (Tingkat_Performa, Tingkat_Kompetensi) WHERE ([Flag_Aktif]='Y');
GO

/* 30. N_PA_Result_Status */
IF OBJECT_ID('N_PA_Result_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Status (
        Id_PA_Result_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Status_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Status PRIMARY KEY CLUSTERED (Id_PA_Result_Status),
        CONSTRAINT UQ_PA_Result_Status_Kode UNIQUE NONCLUSTERED (Kode_Status),
        CONSTRAINT CK_PA_Result_Status_Urutan CHECK ([Urutan_Alur]>=(1))
    );
END;
GO

/* 31. N_PA_Result */
IF OBJECT_ID('N_PA_Result', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result (
        Id_PA_Result int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        Status_Hasil varchar(20) NOT NULL
            CONSTRAINT DF_PA_Result_Status DEFAULT ('BELUM_DIHITUNG'),
        Nilai_KPI decimal(6,2) NULL,
        Nilai_KPI_Sebelum_Cap decimal(6,2) NULL,
        KPI_Periode_Dari date NULL,
        KPI_Periode_Sampai date NULL,
        Jumlah_KPI_Belum_Disetujui int NULL,
        Nilai_Kompetensi decimal(6,2) NULL,
        Jumlah_Rater_Diharapkan smallint NULL,
        Jumlah_Rater_Mengisi smallint NULL,
        Bobot_KPI_Terpakai decimal(5,2) NULL,
        Bobot_Kompetensi_Terpakai decimal(5,2) NULL,
        Final_Score decimal(6,2) NULL,
        Kode_Grade_Sistem varchar(40) NULL,
        Kode_Grade_Final varchar(40) NULL,
        Flag_Dikalibrasi char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Dikalibrasi DEFAULT ('T'),
        PA_Hav_Quadrant_Id int NULL,
        Alasan_Tertahan nvarchar(500) NULL,
        Calculation_Version int NOT NULL
            CONSTRAINT DF_PA_Result_Calc_Version DEFAULT ((1)),
        Calculated_At datetime2(0) NULL,
        Calculated_By int NULL,
        Published_At datetime2(0) NULL,
        Published_By int NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        PA_Cycle_Curve_Id int NULL,
        Published_Leader_At datetime2(0) NULL,
        Published_Leader_By int NULL,
        Unpublished_Leader_At datetime2(0) NULL,
        Unpublished_Leader_By int NULL,
        Alasan_Cabut_Leader nvarchar(500) NULL,
        CONSTRAINT PK_PA_Result PRIMARY KEY CLUSTERED (Id_PA_Result),
        CONSTRAINT CK_PA_Result_Bobot CHECK ([Bobot_KPI_Terpakai] IS NULL AND [Bobot_Kompetensi_Terpakai] IS NULL OR [Bobot_KPI_Terpakai] IS NOT NULL AND [Bobot_Kompetensi_Terpakai] IS NOT NULL AND [Bobot_KPI_Terpakai]>=(0) AND [Bobot_Kompetensi_Terpakai]>=(0) AND ([Bobot_KPI_Terpakai]+[Bobot_Kompetensi_Terpakai])=(100)),
        CONSTRAINT CK_PA_Result_Calc_Version CHECK ([Calculation_Version]>=(1)),
        CONSTRAINT CK_PA_Result_KPI_Menggantung CHECK ([Jumlah_KPI_Belum_Disetujui] IS NULL OR [Jumlah_KPI_Belum_Disetujui]>=(0)),
        CONSTRAINT CK_PA_Result_Nilai_Non_Negatif CHECK (([Nilai_KPI] IS NULL OR [Nilai_KPI]>=(0)) AND ([Nilai_KPI_Sebelum_Cap] IS NULL OR [Nilai_KPI_Sebelum_Cap]>=(0)) AND ([Nilai_Kompetensi] IS NULL OR [Nilai_Kompetensi]>=(0)) AND ([Final_Score] IS NULL OR [Final_Score]>=(0))),
        CONSTRAINT CK_PA_Result_Periode CHECK ([KPI_Periode_Dari] IS NULL OR [KPI_Periode_Sampai] IS NULL OR [KPI_Periode_Sampai]>=[KPI_Periode_Dari]),
        CONSTRAINT CK_PA_Result_Rater CHECK ([Jumlah_Rater_Diharapkan] IS NULL OR [Jumlah_Rater_Mengisi] IS NULL OR [Jumlah_Rater_Mengisi]<=[Jumlah_Rater_Diharapkan]),
        CONSTRAINT FK_PA_Result_Cycle_Curve FOREIGN KEY (PA_Cycle_Curve_Id) REFERENCES N_PA_Cycle_Curve (Id_PA_Cycle_Curve),
        CONSTRAINT FK_PA_Result_Hav_Quadrant FOREIGN KEY (PA_Hav_Quadrant_Id) REFERENCES N_PA_Hav_Quadrant (Id_PA_Hav_Quadrant),
        CONSTRAINT FK_PA_Result_Participant FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant),
        CONSTRAINT FK_PA_Result_Status_Master FOREIGN KEY (Status_Hasil) REFERENCES N_PA_Result_Status (Kode_Status)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'IX_PA_Result_Kuadran')
    CREATE NONCLUSTERED INDEX IX_PA_Result_Kuadran ON N_PA_Result (PA_Hav_Quadrant_Id, Flag_Aktif) INCLUDE (PA_Participant_Id, Final_Score);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'IX_PA_Result_Published_Leader')
    CREATE NONCLUSTERED INDEX IX_PA_Result_Published_Leader ON N_PA_Result (PA_Participant_Id) INCLUDE (Published_Leader_At, Status_Hasil) WHERE ([Flag_Aktif]='Y' AND [Published_Leader_At] IS NOT NULL);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'IX_PA_Result_Status')
    CREATE NONCLUSTERED INDEX IX_PA_Result_Status ON N_PA_Result (Status_Hasil, Flag_Aktif) INCLUDE (PA_Participant_Id, Final_Score, Kode_Grade_Final, PA_Hav_Quadrant_Id);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'UX_PA_Result_Participant')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Result_Participant ON N_PA_Result (PA_Participant_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 32. N_PA_Balancing_Peserta */
IF OBJECT_ID('N_PA_Balancing_Peserta', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Peserta (
        Id_PA_Balancing_Peserta int IDENTITY(1,1) NOT NULL,
        PA_Balancing_Sesi_Id int NOT NULL,
        PA_Participant_Id int NOT NULL,
        PA_Result_Id int NOT NULL,
        Calculation_Version_Sebelum int NOT NULL,
        Nilai_KPI_Sebelum decimal(6,2) NULL,
        Nilai_Kompetensi_Sebelum decimal(6,2) NULL,
        Final_Score_Sebelum decimal(6,2) NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        Status_Tinjau varchar(20) NOT NULL
            CONSTRAINT DF_PA_Balancing_Peserta_Tinjau DEFAULT ('BELUM'),
        Alasan nvarchar(1000) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Peserta_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Balancing_Peserta PRIMARY KEY CLUSTERED (Id_PA_Balancing_Peserta),
        CONSTRAINT FK_PA_Balancing_Peserta_Participant FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant),
        CONSTRAINT FK_PA_Balancing_Peserta_Result FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result),
        CONSTRAINT FK_PA_Balancing_Peserta_Sesi FOREIGN KEY (PA_Balancing_Sesi_Id) REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Peserta') AND name = 'IX_PA_Balancing_Peserta_Participant')
    CREATE NONCLUSTERED INDEX IX_PA_Balancing_Peserta_Participant ON N_PA_Balancing_Peserta (PA_Participant_Id) INCLUDE (PA_Balancing_Sesi_Id, Status_Tinjau);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Peserta') AND name = 'UX_PA_Balancing_Peserta_Sesi_Participant')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Balancing_Peserta_Sesi_Participant ON N_PA_Balancing_Peserta (PA_Balancing_Sesi_Id, PA_Participant_Id);
GO

/* 33. N_PA_Balancing_Kompetensi */
IF OBJECT_ID('N_PA_Balancing_Kompetensi', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Kompetensi (
        Id_PA_Balancing_Kompetensi int IDENTITY(1,1) NOT NULL,
        PA_Balancing_Peserta_Id int NOT NULL,
        PA_Competency_Id int NOT NULL,
        Bobot decimal(9,6) NOT NULL,
        Nilai_Sebelum decimal(6,2) NOT NULL,
        Nilai_Sesudah decimal(6,2) NOT NULL,
        Alasan nvarchar(1000) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Balancing_Kompetensi PRIMARY KEY CLUSTERED (Id_PA_Balancing_Kompetensi),
        CONSTRAINT CK_PA_Balancing_Kompetensi_Bobot CHECK ([Bobot]>=(0) AND [Bobot]<=(1)),
        CONSTRAINT CK_PA_Balancing_Kompetensi_Nilai CHECK ([Nilai_Sebelum]>=(0) AND [Nilai_Sebelum]<=(100) AND [Nilai_Sesudah]>=(0) AND [Nilai_Sesudah]<=(100)),
        CONSTRAINT FK_PA_Balancing_Kompetensi_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Balancing_Kompetensi_Peserta FOREIGN KEY (PA_Balancing_Peserta_Id) REFERENCES N_PA_Balancing_Peserta (Id_PA_Balancing_Peserta)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Kompetensi') AND name = 'UX_PA_Balancing_Kompetensi_Peserta_Competency')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Balancing_Kompetensi_Peserta_Competency ON N_PA_Balancing_Kompetensi (PA_Balancing_Peserta_Id, PA_Competency_Id);
GO

/* 34. N_PA_Balancing_Kualitatif */
IF OBJECT_ID('N_PA_Balancing_Kualitatif', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Kualitatif (
        Id_PA_Balancing_Kualitatif int IDENTITY(1,1) NOT NULL,
        PA_Balancing_Peserta_Id int NOT NULL,
        PA_Competency_Id int NOT NULL,
        Isi_Sebelum nvarchar(max) NULL,
        Isi_Sesudah nvarchar(max) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Balancing_Kualitatif PRIMARY KEY CLUSTERED (Id_PA_Balancing_Kualitatif),
        CONSTRAINT FK_PA_Balancing_Kualitatif_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Balancing_Kualitatif_Peserta FOREIGN KEY (PA_Balancing_Peserta_Id) REFERENCES N_PA_Balancing_Peserta (Id_PA_Balancing_Peserta)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Kualitatif') AND name = 'UX_PA_Balancing_Kualitatif_Peserta_Competency')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Balancing_Kualitatif_Peserta_Competency ON N_PA_Balancing_Kualitatif (PA_Balancing_Peserta_Id, PA_Competency_Id);
GO

/* 35. N_PA_Calibration_History */
IF OBJECT_ID('N_PA_Calibration_History', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Calibration_History (
        Id_PA_Calibration_History int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        Kode_Grade_Sesudah varchar(40) NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        PA_Hav_Quadrant_Sesudah_Id int NULL,
        Alasan nvarchar(500) NOT NULL,
        Performed_By int NULL,
        Performed_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Calibration_Performed DEFAULT (sysdatetime()),
        Performed_By_Nama nvarchar(150) NULL,
        CONSTRAINT PK_PA_Calibration_History PRIMARY KEY CLUSTERED (Id_PA_Calibration_History),
        CONSTRAINT CK_PA_Calibration_Ada_Perubahan CHECK (isnull([Kode_Grade_Sebelum],'')<>[Kode_Grade_Sesudah] OR isnull([PA_Hav_Quadrant_Sebelum_Id],(-1))<>isnull([PA_Hav_Quadrant_Sesudah_Id],(-1))),
        CONSTRAINT CK_PA_Calibration_Alasan CHECK (len(ltrim(rtrim([Alasan])))>(0)),
        CONSTRAINT CK_PA_Calibration_History_Pelaku CHECK ([Performed_By] IS NOT NULL OR [Kode_Grade_Sesudah] IS NULL),
        CONSTRAINT FK_PA_Calibration_Kuadran_Sebelum FOREIGN KEY (PA_Hav_Quadrant_Sebelum_Id) REFERENCES N_PA_Hav_Quadrant (Id_PA_Hav_Quadrant),
        CONSTRAINT FK_PA_Calibration_Kuadran_Sesudah FOREIGN KEY (PA_Hav_Quadrant_Sesudah_Id) REFERENCES N_PA_Hav_Quadrant (Id_PA_Hav_Quadrant),
        CONSTRAINT FK_PA_Calibration_Result FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Calibration_History') AND name = 'IX_PA_Calibration_Result')
    CREATE NONCLUSTERED INDEX IX_PA_Calibration_Result ON N_PA_Calibration_History (PA_Result_Id, Performed_At);
GO

/* 36. N_PA_Competency_Proficiency */
IF OBJECT_ID('N_PA_Competency_Proficiency', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Proficiency (
        Id_PA_Competency_Proficiency int IDENTITY(1,1) NOT NULL,
        PA_Competency_Id int NOT NULL,
        Tingkat smallint NOT NULL,
        Nama_Tingkat nvarchar(150) NOT NULL,
        Expected_Behaviour nvarchar(4000) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Proficiency_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Proficiency_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Competency_Proficiency PRIMARY KEY CLUSTERED (Id_PA_Competency_Proficiency),
        CONSTRAINT FK_PA_Competency_Proficiency_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency_Proficiency') AND name = 'UX_PA_Competency_Proficiency_Competency_Tingkat')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Competency_Proficiency_Competency_Tingkat ON N_PA_Competency_Proficiency (PA_Competency_Id, Tingkat) WHERE ([Flag_Aktif]='Y');
GO

/* 37. N_PA_Competency_Level_Requirement */
IF OBJECT_ID('N_PA_Competency_Level_Requirement', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Level_Requirement (
        Id_PA_Competency_Level_Requirement int IDENTITY(1,1) NOT NULL,
        ID_Level int NOT NULL,
        PA_Competency_Id int NOT NULL,
        PA_Competency_Proficiency_Id int NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Level_Requirement_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Level_Requirement_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Competency_Level_Requirement PRIMARY KEY CLUSTERED (Id_PA_Competency_Level_Requirement),
        CONSTRAINT FK_PA_Competency_Level_Requirement_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Competency_Level_Requirement_Proficiency FOREIGN KEY (PA_Competency_Proficiency_Id) REFERENCES N_PA_Competency_Proficiency (Id_PA_Competency_Proficiency)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency_Level_Requirement') AND name = 'UX_PA_Competency_Level_Requirement_Level_Competency')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Competency_Level_Requirement_Level_Competency ON N_PA_Competency_Level_Requirement (ID_Level, PA_Competency_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 38. N_PA_Competency_Requirement */
IF OBJECT_ID('N_PA_Competency_Requirement', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Requirement (
        Id_PA_Competency_Requirement int IDENTITY(1,1) NOT NULL,
        ID_Level bigint NOT NULL,
        PA_Competency_Type_Id int NOT NULL,
        Flag_Wajib char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Requirement_Wajib DEFAULT ('Y'),
        Catatan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Requirement_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Requirement_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Competency_Requirement PRIMARY KEY CLUSTERED (Id_PA_Competency_Requirement),
        CONSTRAINT FK_PA_Competency_Requirement_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level),
        CONSTRAINT FK_PA_Competency_Requirement_Type FOREIGN KEY (PA_Competency_Type_Id) REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Competency_Requirement') AND name = 'UX_PA_Competency_Requirement_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Competency_Requirement_Aktif ON N_PA_Competency_Requirement (ID_Level, PA_Competency_Type_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 39. N_PA_Curve_Group_Level */
IF OBJECT_ID('N_PA_Curve_Group_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Curve_Group_Level (
        Id_PA_Curve_Group_Level int IDENTITY(1,1) NOT NULL,
        PA_Curve_Group_Id int NOT NULL,
        ID_Level bigint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Curve_Group_Level_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Curve_Group_Level_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT PK_PA_Curve_Group_Level PRIMARY KEY CLUSTERED (Id_PA_Curve_Group_Level),
        CONSTRAINT FK_PA_Curve_Group_Level_Grup FOREIGN KEY (PA_Curve_Group_Id) REFERENCES N_PA_Curve_Group (Id_PA_Curve_Group),
        CONSTRAINT FK_PA_Curve_Group_Level_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Curve_Group_Level') AND name = 'UX_PA_Curve_Group_Level_Level')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Curve_Group_Level_Level ON N_PA_Curve_Group_Level (ID_Level) WHERE ([Flag_Aktif]='Y');
GO

/* 40. N_PA_Cycle_Competency_Level_Snapshot */
IF OBJECT_ID('N_PA_Cycle_Competency_Level_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Competency_Level_Snapshot (
        Id_PA_Cycle_Competency_Level_Snapshot int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        ID_Level int NOT NULL,
        PA_Competency_Id int NOT NULL,
        PA_Competency_Proficiency_Id int NULL,
        Tingkat smallint NULL,
        Nama_Tingkat nvarchar(150) NULL,
        Expected_Behaviour nvarchar(4000) NULL,
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Competency_Level_Snapshot_Snapshot_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        CONSTRAINT PK_PA_Cycle_Competency_Level_Snapshot PRIMARY KEY CLUSTERED (Id_PA_Cycle_Competency_Level_Snapshot),
        CONSTRAINT FK_PA_Cycle_Competency_Level_Snapshot_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Cycle_Competency_Level_Snapshot_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Competency_Level_Snapshot') AND name = 'UX_PA_Cycle_Competency_Level_Snapshot_Cycle_Level_Competency')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Competency_Level_Snapshot_Cycle_Level_Competency ON N_PA_Cycle_Competency_Level_Snapshot (PA_Cycle_Id, ID_Level, PA_Competency_Id);
GO

/* 41. N_PA_Cycle_Competency_Requirement_Snapshot */
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
            CONSTRAINT DF_PA_Cycle_Comp_Req_Snap_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Competency_Requirement_Snapshot PRIMARY KEY CLUSTERED (Id_PA_Cycle_Competency_Requirement_Snapshot),
        CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level),
        CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Source FOREIGN KEY (PA_Competency_Requirement_Id) REFERENCES N_PA_Competency_Requirement (Id_PA_Competency_Requirement),
        CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Type FOREIGN KEY (PA_Competency_Type_Id) REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot') AND name = 'UX_PA_Cycle_Comp_Req_Snap_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Comp_Req_Snap_Aktif ON N_PA_Cycle_Competency_Requirement_Snapshot (PA_Cycle_Id, ID_Level, PA_Competency_Type_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 42. N_PA_Cycle_Competency_Snapshot */
IF OBJECT_ID('N_PA_Cycle_Competency_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Competency_Snapshot (
        Id_PA_Cycle_Competency_Snapshot int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        PA_Competency_Id int NOT NULL,
        Flag_Berskor char(1) NOT NULL,
        Flag_Radar char(1) NOT NULL,
        Nilai_Ambang decimal(5,2) NULL,
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Competency_Snapshot_Snapshot_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        CONSTRAINT PK_PA_Cycle_Competency_Snapshot PRIMARY KEY CLUSTERED (Id_PA_Cycle_Competency_Snapshot),
        CONSTRAINT FK_PA_Cycle_Competency_Snapshot_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Cycle_Competency_Snapshot_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Competency_Snapshot') AND name = 'UX_PA_Cycle_Competency_Snapshot_Cycle_Competency')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Competency_Snapshot_Cycle_Competency ON N_PA_Cycle_Competency_Snapshot (PA_Cycle_Id, PA_Competency_Id);
GO

/* 43. N_PA_Cycle_Curve_Band */
IF OBJECT_ID('N_PA_Cycle_Curve_Band', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Band (
        Id_PA_Cycle_Curve_Band int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Curve_Id int NOT NULL,
        Kode_Range varchar(40) NOT NULL,
        Nama_Range nvarchar(200) NOT NULL,
        Batas_Z decimal(5,2) NULL,
        Nilai_Dari decimal(9,4) NOT NULL,
        Nilai_Sampai decimal(9,4) NOT NULL,
        Urutan_Tampil smallint NOT NULL,
        CONSTRAINT PK_PA_Cycle_Curve_Band PRIMARY KEY CLUSTERED (Id_PA_Cycle_Curve_Band),
        CONSTRAINT CK_PA_Cycle_Curve_Band_Rentang CHECK ([Nilai_Sampai]>=[Nilai_Dari]),
        CONSTRAINT FK_PA_Cycle_Curve_Band_Curve FOREIGN KEY (PA_Cycle_Curve_Id) REFERENCES N_PA_Cycle_Curve (Id_PA_Cycle_Curve)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Band') AND name = 'UX_PA_Cycle_Curve_Band_Kode')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Curve_Band_Kode ON N_PA_Cycle_Curve_Band (PA_Cycle_Curve_Id, Kode_Range);
GO

/* 44. N_PA_Cycle_Curve_Group_Level */
IF OBJECT_ID('N_PA_Cycle_Curve_Group_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Group_Level (
        Id_PA_Cycle_Curve_Group_Level int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Curve_Group_Id int NOT NULL,
        PA_Cycle_Id int NOT NULL,
        ID_Level bigint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Curve_Group_Level_Aktif DEFAULT ('Y'),
        CONSTRAINT PK_PA_Cycle_Curve_Group_Level PRIMARY KEY CLUSTERED (Id_PA_Cycle_Curve_Group_Level),
        CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Grup FOREIGN KEY (PA_Cycle_Curve_Group_Id) REFERENCES N_PA_Cycle_Curve_Group (Id_PA_Cycle_Curve_Group),
        CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Group_Level') AND name = 'UX_PA_Cycle_Curve_Group_Level_Level')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Curve_Group_Level_Level ON N_PA_Cycle_Curve_Group_Level (PA_Cycle_Id, ID_Level) WHERE ([Flag_Aktif]='Y');
GO

/* 45. N_PA_Pillar_Weight */
IF OBJECT_ID('N_PA_Pillar_Weight', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Pillar_Weight (
        Id_PA_Pillar_Weight int IDENTITY(1,1) NOT NULL,
        ID_Level bigint NOT NULL,
        Bobot_KPI decimal(5,2) NOT NULL,
        Bobot_Kompetensi decimal(5,2) NOT NULL,
        Catatan nvarchar(500) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Pillar_Weight_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Pillar_Weight PRIMARY KEY CLUSTERED (Id_PA_Pillar_Weight),
        CONSTRAINT CK_PA_Pillar_Weight_Non_Negatif CHECK ([Bobot_KPI]>=(0) AND [Bobot_Kompetensi]>=(0)),
        CONSTRAINT CK_PA_Pillar_Weight_Total CHECK (([Bobot_KPI]+[Bobot_Kompetensi])=(100)),
        CONSTRAINT FK_PA_Pillar_Weight_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Pillar_Weight') AND name = 'UX_PA_Pillar_Weight_Level')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Pillar_Weight_Level ON N_PA_Pillar_Weight (ID_Level) INCLUDE (Bobot_KPI, Bobot_Kompetensi);
GO

/* 46. N_PA_Cycle_Pillar_Weight_Snapshot */
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
            CONSTRAINT DF_PA_Cycle_Pillar_Snap_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Pillar_Weight_Snapshot PRIMARY KEY CLUSTERED (Id_PA_Cycle_Pillar_Weight_Snapshot),
        CONSTRAINT CK_PA_Cycle_Pillar_Snap_Non_Negatif CHECK ([Bobot_KPI]>=(0) AND [Bobot_Kompetensi]>=(0)),
        CONSTRAINT CK_PA_Cycle_Pillar_Snap_Total CHECK (([Bobot_KPI]+[Bobot_Kompetensi])=(100)),
        CONSTRAINT FK_PA_Cycle_Pillar_Snap_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Pillar_Snap_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level),
        CONSTRAINT FK_PA_Cycle_Pillar_Snap_Source FOREIGN KEY (PA_Pillar_Weight_Id) REFERENCES N_PA_Pillar_Weight (Id_PA_Pillar_Weight)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Pillar_Weight_Snapshot') AND name = 'UX_PA_Cycle_Pillar_Snap_Cycle_Level')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Pillar_Snap_Cycle_Level ON N_PA_Cycle_Pillar_Weight_Snapshot (PA_Cycle_Id, ID_Level) INCLUDE (Bobot_KPI, Bobot_Kompetensi);
GO

/* 47. N_PA_Cycle_Pre_Exclusion */
IF OBJECT_ID('N_PA_Cycle_Pre_Exclusion', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Pre_Exclusion (
        Id_PA_Cycle_Pre_Exclusion int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,
        Alasan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_PreExcl_Flag DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_PreExcl_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Pre_Exclusion PRIMARY KEY CLUSTERED (Id_PA_Cycle_Pre_Exclusion),
        CONSTRAINT CK_PA_PreExcl_Flag CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y'),
        CONSTRAINT FK_PA_PreExcl_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Pre_Exclusion') AND name = 'IX_PA_PreExcl_Cycle_Aktif')
    CREATE NONCLUSTERED INDEX IX_PA_PreExcl_Cycle_Aktif ON N_PA_Cycle_Pre_Exclusion (PA_Cycle_Id, Flag_Aktif) INCLUDE (Kode_Perusahaan, Kode_Karyawan, Alasan);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Pre_Exclusion') AND name = 'UX_PA_PreExcl_Cycle_Karyawan')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_PreExcl_Cycle_Karyawan ON N_PA_Cycle_Pre_Exclusion (PA_Cycle_Id, Kode_Perusahaan, Kode_Karyawan) WHERE ([Flag_Aktif]='Y');
GO

/* 48. N_PA_Result_Note_Type */
IF OBJECT_ID('N_PA_Result_Note_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Note_Type (
        Id_PA_Result_Note_Type int IDENTITY(1,1) NOT NULL,
        Kode_Type varchar(30) NOT NULL,
        Nama_Type nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Type_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Note_Type PRIMARY KEY CLUSTERED (Id_PA_Result_Note_Type),
        CONSTRAINT UQ_PA_Result_Note_Type_Kode UNIQUE NONCLUSTERED (Kode_Type),
        CONSTRAINT CK_PA_Result_Note_Type_Urutan CHECK ([Urutan_Tampil]>=(1))
    );
END;
GO

/* 49. N_PA_Cycle_Question_Package_Item */
IF OBJECT_ID('N_PA_Cycle_Question_Package_Item', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Question_Package_Item (
        Id_PA_Cycle_Question_Package_Item int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Question_Package_Id int NOT NULL,
        Id_Jenis_Soal varchar(100) NOT NULL,
        Nama_Bagian_Sumber nvarchar(200) NULL,
        PA_Competency_Id int NOT NULL,
        Bobot_Penilaian decimal(5,2) NULL,
        Skor_Maksimal decimal(6,2) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Item_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Item_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Jenis_Soal varchar(30) NULL,
        PA_Result_Note_Type_Id int NULL,
        Id_Jenis_Indikator varchar(100) NOT NULL
            CONSTRAINT DF_PA_CycleQItem_Indikator DEFAULT (''),
        Nama_Alat_Tes nvarchar(255) NULL,
        Kode_Soal_Sumber varchar(64) NULL,
        CONSTRAINT PK_PA_Cycle_Question_Package_Item PRIMARY KEY CLUSTERED (Id_PA_Cycle_Question_Package_Item),
        CONSTRAINT CK_PA_Cycle_Question_Item_Bobot CHECK ([Bobot_Penilaian] IS NULL OR [Bobot_Penilaian]>=(0) AND [Bobot_Penilaian]<=(100)),
        CONSTRAINT CK_PA_Cycle_Question_Item_Maksimal CHECK ([Skor_Maksimal] IS NULL OR [Skor_Maksimal]>(0)),
        CONSTRAINT CK_PA_Cycle_Question_Item_Peran CHECK ([PA_Competency_Id] IS NULL OR [PA_Result_Note_Type_Id] IS NULL),
        CONSTRAINT CK_PA_Cycle_Question_Item_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Cycle_Question_Item_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Cycle_Question_Item_Header FOREIGN KEY (PA_Cycle_Question_Package_Id) REFERENCES N_PA_Cycle_Question_Package (Id_PA_Cycle_Question_Package),
        CONSTRAINT FK_PA_Cycle_Question_Item_Note_Type FOREIGN KEY (PA_Result_Note_Type_Id) REFERENCES N_PA_Result_Note_Type (Id_PA_Result_Note_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item') AND name = 'IX_PA_Cycle_Question_Item_Cakupan')
    CREATE NONCLUSTERED INDEX IX_PA_Cycle_Question_Item_Cakupan ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Flag_Aktif) INCLUDE (Id_Jenis_Indikator, Id_Jenis_Soal, PA_Competency_Id, Bobot_Penilaian, Skor_Maksimal);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item') AND name = 'IX_PA_CycleQPkgItem_KodeSoal')
    CREATE NONCLUSTERED INDEX IX_PA_CycleQPkgItem_KodeSoal ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Kode_Soal_Sumber) WHERE ([Kode_Soal_Sumber] IS NOT NULL);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item') AND name = 'UX_PA_CycleQItem_Bagian')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_CycleQItem_Bagian ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Id_Jenis_Indikator, Id_Jenis_Soal);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item') AND name = 'UX_PA_CycleQItem_Kompetensi')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_CycleQItem_Kompetensi ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, PA_Competency_Id);
GO

/* 50. N_PA_Rater_Policy_Detail */
IF OBJECT_ID('N_PA_Rater_Policy_Detail', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Rater_Policy_Detail (
        Id_PA_Rater_Policy_Detail int IDENTITY(1,1) NOT NULL,
        PA_Rater_Policy_Id int NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        Organization_Relationship_Type_Id int NULL,
        Bobot_Persen decimal(5,2) NULL,
        Min_Rater smallint NULL,
        Maks_Rater smallint NULL,
        Min_Rater_Selesai smallint NULL,
        Flag_Izinkan_Manual char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Detail_Manual DEFAULT ('T'),
        Urutan_Tampil smallint NOT NULL,
        Catatan_Aturan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Detail_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Detail_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Rater_Policy_Detail PRIMARY KEY CLUSTERED (Id_PA_Rater_Policy_Detail),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Bobot CHECK ([Bobot_Persen] IS NULL OR [Bobot_Persen]>=(0) AND [Bobot_Persen]<=(100)),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Maks_Rater CHECK ([Maks_Rater] IS NULL OR [Min_Rater] IS NULL OR [Maks_Rater]>=[Min_Rater]),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Rater CHECK ([Min_Rater] IS NULL OR [Min_Rater]>=(1)),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Selesai CHECK ([Min_Rater_Selesai] IS NULL OR [Min_Rater_Selesai]>=(1) AND ([Maks_Rater] IS NULL OR [Min_Rater_Selesai]<=[Maks_Rater])),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Sepasang CHECK ([Bobot_Persen] IS NULL AND [Min_Rater] IS NULL OR [Bobot_Persen] IS NOT NULL AND [Min_Rater] IS NOT NULL),
        CONSTRAINT CK_PA_Rater_Policy_Detail_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Rater_Policy_Detail_Category FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category),
        CONSTRAINT FK_PA_Rater_Policy_Detail_Org_Type FOREIGN KEY (Organization_Relationship_Type_Id) REFERENCES N_HRIS_Organization_Relationship_Type (Id_Organization_Relationship_Type),
        CONSTRAINT FK_PA_Rater_Policy_Detail_Policy FOREIGN KEY (PA_Rater_Policy_Id) REFERENCES N_PA_Rater_Policy (Id_PA_Rater_Policy)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail') AND name = 'IX_PA_Rater_Policy_Detail_Category')
    CREATE NONCLUSTERED INDEX IX_PA_Rater_Policy_Detail_Category ON N_PA_Rater_Policy_Detail (PA_Rater_Category_Id, Flag_Aktif) INCLUDE (PA_Rater_Policy_Id, Bobot_Persen);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail') AND name = 'IX_PA_Rater_Policy_Detail_Org_Type')
    CREATE NONCLUSTERED INDEX IX_PA_Rater_Policy_Detail_Org_Type ON N_PA_Rater_Policy_Detail (Organization_Relationship_Type_Id, Flag_Aktif) INCLUDE (PA_Rater_Policy_Id, PA_Rater_Category_Id) WHERE ([Organization_Relationship_Type_Id] IS NOT NULL);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail') AND name = 'UX_PA_Rater_Policy_Detail_Active_Category')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Rater_Policy_Detail_Active_Category ON N_PA_Rater_Policy_Detail (PA_Rater_Policy_Id, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 51. N_PA_Cycle_Rater_Policy_Detail_Snapshot */
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
            CONSTRAINT DF_PA_Cycle_Rater_Det_Snap_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Rater_Policy_Detail_Snapshot PRIMARY KEY CLUSTERED (Id_PA_Cycle_Rater_Policy_Detail_Snapshot),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Bobot CHECK ([Bobot_Persen]>=(0) AND [Bobot_Persen]<=(100)),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Maks CHECK ([Maks_Rater] IS NULL OR [Maks_Rater]>=[Min_Rater]),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Min CHECK ([Min_Rater]>=(1)),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Selesai CHECK ([Min_Rater_Selesai] IS NULL OR [Min_Rater_Selesai]>=(1) AND ([Maks_Rater] IS NULL OR [Min_Rater_Selesai]<=[Maks_Rater])),
        CONSTRAINT CK_PA_Cycle_Rater_Det_Snap_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Category FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category),
        CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Header FOREIGN KEY (PA_Cycle_Rater_Policy_Snapshot_Id) REFERENCES N_PA_Cycle_Rater_Policy_Snapshot (Id_PA_Cycle_Rater_Policy_Snapshot),
        CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Org_Type FOREIGN KEY (Organization_Relationship_Type_Id) REFERENCES N_HRIS_Organization_Relationship_Type (Id_Organization_Relationship_Type),
        CONSTRAINT FK_PA_Cycle_Rater_Det_Snap_Source FOREIGN KEY (PA_Rater_Policy_Detail_Id) REFERENCES N_PA_Rater_Policy_Detail (Id_PA_Rater_Policy_Detail)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot') AND name = 'UX_PA_Cycle_Rater_Det_Snap_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Rater_Det_Snap_Aktif ON N_PA_Cycle_Rater_Policy_Detail_Snapshot (PA_Cycle_Rater_Policy_Snapshot_Id, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 52. N_PA_Score_Type */
IF OBJECT_ID('N_PA_Score_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Score_Type (
        Id_PA_Score_Sumbu int IDENTITY(1,1) NOT NULL,
        Kode_Type varchar(20) NOT NULL,
        Nama_Type nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Score_Type_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Score_Type PRIMARY KEY CLUSTERED (Id_PA_Score_Sumbu),
        CONSTRAINT UQ_PA_Score_Type_Kode UNIQUE NONCLUSTERED (Kode_Type),
        CONSTRAINT CK_PA_Score_Type_Urutan CHECK ([Urutan_Tampil]>=(1))
    );
END;
GO

/* 53. N_PA_Score_Usage */
IF OBJECT_ID('N_PA_Score_Usage', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Score_Usage (
        Id_PA_Score_Kegunaan int IDENTITY(1,1) NOT NULL,
        Kode_Usage varchar(20) NOT NULL,
        Nama_Usage nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Flag_Pakai_Nilai_Hasil char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Hasil DEFAULT ('T'),
        Flag_Pakai_Target char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Target DEFAULT ('T'),
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Score_Usage PRIMARY KEY CLUSTERED (Id_PA_Score_Kegunaan),
        CONSTRAINT UQ_PA_Score_Usage_Kode UNIQUE NONCLUSTERED (Kode_Usage),
        CONSTRAINT CK_PA_Score_Usage_Urutan CHECK ([Urutan_Tampil]>=(1))
    );
END;
GO

/* 54. N_PA_Score_Range */
IF OBJECT_ID('N_PA_Score_Range', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Score_Range (
        Id_PA_Score_Range int IDENTITY(1,1) NOT NULL,
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
            CONSTRAINT DF_PA_Score_Range_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Score_Range_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Batas_Z decimal(5,2) NULL,
        CONSTRAINT PK_PA_Score_Range PRIMARY KEY CLUSTERED (Id_PA_Score_Range),
        CONSTRAINT CK_PA_Score_Range_Batas CHECK ([Nilai_Sampai]>=[Nilai_Dari]),
        CONSTRAINT CK_PA_Score_Range_Target CHECK ([Target_Persen] IS NULL OR [Target_Persen]>=(0) AND [Target_Persen]<=(100)),
        CONSTRAINT CK_PA_Score_Range_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Score_Range_Type FOREIGN KEY (Score_Type) REFERENCES N_PA_Score_Type (Kode_Type),
        CONSTRAINT FK_PA_Score_Range_Usage FOREIGN KEY (Score_Usage) REFERENCES N_PA_Score_Usage (Kode_Usage)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'IX_PA_Score_Range_Lookup')
    CREATE NONCLUSTERED INDEX IX_PA_Score_Range_Lookup ON N_PA_Score_Range (Score_Type, Score_Usage, Flag_Aktif) INCLUDE (Nilai_Dari, Nilai_Sampai, Kode_Range, Nama_Range, Nilai_Hasil, Target_Persen, Warna);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'UX_PA_Score_Range_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Score_Range_Aktif ON N_PA_Score_Range (Score_Type, Score_Usage, Kode_Range) WHERE ([Flag_Aktif]='Y');
GO

/* 55. N_PA_Cycle_Score_Range_Snapshot */
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
            CONSTRAINT DF_PA_Cycle_Score_Snap_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Batas_Z decimal(5,2) NULL,
        CONSTRAINT PK_PA_Cycle_Score_Range_Snapshot PRIMARY KEY CLUSTERED (Id_PA_Cycle_Score_Range_Snapshot),
        CONSTRAINT CK_PA_Cycle_Score_Snap_Batas CHECK ([Nilai_Sampai]>=[Nilai_Dari]),
        CONSTRAINT CK_PA_Cycle_Score_Snap_Target CHECK ([Target_Persen] IS NULL OR [Target_Persen]>=(0) AND [Target_Persen]<=(100)),
        CONSTRAINT CK_PA_Cycle_Score_Snap_Urutan CHECK ([Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Cycle_Score_Snap_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT FK_PA_Cycle_Score_Snap_Source FOREIGN KEY (PA_Score_Range_Id) REFERENCES N_PA_Score_Range (Id_PA_Score_Range),
        CONSTRAINT FK_PA_Cycle_Score_Snap_Type FOREIGN KEY (Score_Type) REFERENCES N_PA_Score_Type (Kode_Type),
        CONSTRAINT FK_PA_Cycle_Score_Snap_Usage FOREIGN KEY (Score_Usage) REFERENCES N_PA_Score_Usage (Kode_Usage)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot') AND name = 'IX_PA_Cycle_Score_Snap_Lookup')
    CREATE NONCLUSTERED INDEX IX_PA_Cycle_Score_Snap_Lookup ON N_PA_Cycle_Score_Range_Snapshot (PA_Cycle_Id, Score_Type, Score_Usage, Flag_Aktif) INCLUDE (Nilai_Dari, Nilai_Sampai, Kode_Range, Nama_Range, Nilai_Hasil, Target_Persen, Warna);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot') AND name = 'UX_PA_Cycle_Score_Snap_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Cycle_Score_Snap_Aktif ON N_PA_Cycle_Score_Range_Snapshot (PA_Cycle_Id, Score_Type, Score_Usage, Kode_Range) WHERE ([Flag_Aktif]='Y');
GO

/* 56. N_PA_Cycle_Setup_Step */
IF OBJECT_ID('N_PA_Cycle_Setup_Step', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Setup_Step (
        Id_PA_Cycle_Setup_Step int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        Kode_Langkah varchar(20) NOT NULL,
        Status_Langkah varchar(10) NOT NULL
            CONSTRAINT DF_PA_SetupStep_Status DEFAULT ('BELUM'),
        Ringkasan nvarchar(400) NULL,
        Disimpan_At datetime2(0) NULL,
        Disimpan_By int NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_SetupStep_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Setup_Step PRIMARY KEY CLUSTERED (Id_PA_Cycle_Setup_Step),
        CONSTRAINT CK_PA_SetupStep_Status CHECK ([Status_Langkah]='SELESAI' OR [Status_Langkah]='DRAFT' OR [Status_Langkah]='BELUM'),
        CONSTRAINT FK_PA_SetupStep_Cycle FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Setup_Step') AND name = 'UX_PA_SetupStep_Cycle_Langkah')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_SetupStep_Cycle_Langkah ON N_PA_Cycle_Setup_Step (PA_Cycle_Id, Kode_Langkah);
GO

/* 57. N_PA_Kalibrasi_Penilai */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai (
        Id_PA_Kalibrasi_Penilai int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Id int NOT NULL,
        Alasan nvarchar(500) NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Kalibrasi_Penilai_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT PK_PA_Kalibrasi_Penilai PRIMARY KEY CLUSTERED (Id_PA_Kalibrasi_Penilai),
        CONSTRAINT FK_PA_Kalibrasi_Penilai_Assignment FOREIGN KEY (PA_Assignment_Id) REFERENCES N_PA_Assignment (Id_PA_Assignment)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai') AND name = 'IX_PA_Kalibrasi_Penilai_Assignment')
    CREATE NONCLUSTERED INDEX IX_PA_Kalibrasi_Penilai_Assignment ON N_PA_Kalibrasi_Penilai (PA_Assignment_Id, Id_PA_Kalibrasi_Penilai DESC);
GO

/* 58. N_PA_Kalibrasi_Penilai_Detail */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai_Detail (
        Id_PA_Kalibrasi_Penilai_Detail int IDENTITY(1,1) NOT NULL,
        PA_Kalibrasi_Penilai_Id int NOT NULL,
        PA_Competency_Id int NOT NULL,
        Skor_Sebelum decimal(6,2) NOT NULL,
        Skor_Sesudah decimal(6,2) NOT NULL,
        Skor_Maksimal decimal(6,2) NOT NULL,
        CONSTRAINT PK_PA_Kalibrasi_Penilai_Detail PRIMARY KEY CLUSTERED (Id_PA_Kalibrasi_Penilai_Detail),
        CONSTRAINT CK_PA_Kalibrasi_Penilai_Detail_Rentang CHECK ([Skor_Maksimal]>(0) AND [Skor_Sesudah]>=(0) AND [Skor_Sesudah]<=[Skor_Maksimal] AND [Skor_Sebelum]>=(0)),
        CONSTRAINT FK_PA_Kalibrasi_Penilai_Detail_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Kalibrasi_Penilai_Detail_Kepala FOREIGN KEY (PA_Kalibrasi_Penilai_Id) REFERENCES N_PA_Kalibrasi_Penilai (Id_PA_Kalibrasi_Penilai)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail') AND name = 'UX_PA_Kalibrasi_Penilai_Detail_Kompetensi')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Kalibrasi_Penilai_Detail_Kompetensi ON N_PA_Kalibrasi_Penilai_Detail (PA_Kalibrasi_Penilai_Id, PA_Competency_Id);
GO

/* 59. N_PA_Kalibrasi_Penilai_Jawaban */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Jawaban', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai_Jawaban (
        Id_PA_Kalibrasi_Penilai_Jawaban int IDENTITY(1,1) NOT NULL,
        PA_Kalibrasi_Penilai_Id int NOT NULL,
        Kode_Soal_Sumber varchar(100) NOT NULL,
        PA_Competency_Id int NULL,
        Jawaban_Sebelum nvarchar(max) NOT NULL,
        Jawaban_Sesudah nvarchar(max) NOT NULL,
        Id_Master_Soal int NULL,
        CONSTRAINT PK_PA_Kalibrasi_Penilai_Jawaban PRIMARY KEY CLUSTERED (Id_PA_Kalibrasi_Penilai_Jawaban),
        CONSTRAINT CK_PA_Kalibrasi_Penilai_Jawaban_Terisi CHECK (len(ltrim(rtrim([Jawaban_Sesudah])))>(0)),
        CONSTRAINT FK_PA_Kalibrasi_Penilai_Jawaban_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Kalibrasi_Penilai_Jawaban_Kepala FOREIGN KEY (PA_Kalibrasi_Penilai_Id) REFERENCES N_PA_Kalibrasi_Penilai (Id_PA_Kalibrasi_Penilai)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai_Jawaban') AND name = 'UX_PA_Kalibrasi_Penilai_Jawaban_Soal')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Kalibrasi_Penilai_Jawaban_Soal ON N_PA_Kalibrasi_Penilai_Jawaban (PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber, Id_Master_Soal);
GO

/* 60. N_PA_Level_Exclusion */
IF OBJECT_ID('N_PA_Level_Exclusion', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Level_Exclusion (
        Id_PA_Level_Exclusion int IDENTITY(1,1) NOT NULL,
        ID_Level int NOT NULL,
        Alasan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_LevelExcl_Flag DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_LevelExcl_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Level_Exclusion PRIMARY KEY CLUSTERED (Id_PA_Level_Exclusion),
        CONSTRAINT CK_PA_LevelExcl_Flag CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y')
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Level_Exclusion') AND name = 'IX_PA_LevelExcl_Aktif')
    CREATE NONCLUSTERED INDEX IX_PA_LevelExcl_Aktif ON N_PA_Level_Exclusion (Flag_Aktif) INCLUDE (ID_Level, Alasan);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Level_Exclusion') AND name = 'UX_PA_LevelExcl_Level')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_LevelExcl_Level ON N_PA_Level_Exclusion (ID_Level) WHERE ([Flag_Aktif]='Y');
GO

/* 61. N_PA_Participant_Pillar_Override */
IF OBJECT_ID('N_PA_Participant_Pillar_Override', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Pillar_Override (
        Id_PA_Participant_Pillar_Override int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        Bobot_KPI decimal(5,2) NOT NULL,
        Bobot_Kompetensi decimal(5,2) NOT NULL,
        Alasan_Perubahan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Pillar_Ovr_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Pillar_Ovr_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Pillar_Override PRIMARY KEY CLUSTERED (Id_PA_Participant_Pillar_Override),
        CONSTRAINT CK_PA_Participant_Pillar_Ovr_Non_Negatif CHECK ([Bobot_KPI]>=(0) AND [Bobot_Kompetensi]>=(0)),
        CONSTRAINT CK_PA_Participant_Pillar_Ovr_Total CHECK (([Bobot_KPI]+[Bobot_Kompetensi])=(100)),
        CONSTRAINT FK_PA_Participant_Pillar_Ovr_Participant FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Participant_Pillar_Override') AND name = 'UX_PA_Participant_Pillar_Ovr_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Participant_Pillar_Ovr_Aktif ON N_PA_Participant_Pillar_Override (PA_Participant_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 62. N_PA_Participant_Rater_Override */
IF OBJECT_ID('N_PA_Participant_Rater_Override', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Rater_Override (
        Id_PA_Participant_Rater_Override int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        PA_Cycle_Rater_Policy_Detail_Snapshot_Id int NULL,
        Flag_Dipakai char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Rater_Ovr_Dipakai DEFAULT ('Y'),
        Bobot_Persen decimal(5,2) NULL,
        Min_Rater smallint NULL,
        Maks_Rater smallint NULL,
        Min_Rater_Selesai smallint NULL,
        Alasan_Perubahan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Rater_Ovr_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Rater_Ovr_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Rater_Override PRIMARY KEY CLUSTERED (Id_PA_Participant_Rater_Override),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Bobot CHECK ([Bobot_Persen] IS NULL OR [Bobot_Persen]>=(0) AND [Bobot_Persen]<=(100)),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Lengkap CHECK ([Flag_Dipakai]='T' OR [Bobot_Persen] IS NOT NULL AND [Min_Rater] IS NOT NULL),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Maks CHECK ([Maks_Rater] IS NULL OR [Min_Rater] IS NULL OR [Maks_Rater]>=[Min_Rater]),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Min CHECK ([Min_Rater] IS NULL OR [Min_Rater]>=(1)),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Selesai CHECK ([Min_Rater_Selesai] IS NULL OR [Min_Rater_Selesai]>=(1) AND ([Maks_Rater] IS NULL OR [Min_Rater_Selesai]<=[Maks_Rater])),
        CONSTRAINT FK_PA_Participant_Rater_Ovr_Category FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category),
        CONSTRAINT FK_PA_Participant_Rater_Ovr_Participant FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant),
        CONSTRAINT FK_PA_Participant_Rater_Ovr_Snapshot FOREIGN KEY (PA_Cycle_Rater_Policy_Detail_Snapshot_Id) REFERENCES N_PA_Cycle_Rater_Policy_Detail_Snapshot (Id_PA_Cycle_Rater_Policy_Detail_Snapshot)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Participant_Rater_Override') AND name = 'UX_PA_Participant_Rater_Ovr_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Participant_Rater_Ovr_Aktif ON N_PA_Participant_Rater_Override (PA_Participant_Id, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 63. N_PA_Participant_Requirement_Override */
IF OBJECT_ID('N_PA_Participant_Requirement_Override', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Requirement_Override (
        Id_PA_Participant_Requirement_Override int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        PA_Competency_Type_Id int NOT NULL,
        PA_Cycle_Competency_Requirement_Snapshot_Id int NULL,
        Flag_Dipakai char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Req_Ovr_Dipakai DEFAULT ('Y'),
        Flag_Wajib char(1) NULL,
        Alasan_Perubahan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Req_Ovr_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Req_Ovr_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Requirement_Override PRIMARY KEY CLUSTERED (Id_PA_Participant_Requirement_Override),
        CONSTRAINT CK_PA_Participant_Req_Ovr_Lengkap CHECK ([Flag_Dipakai]='T' OR [Flag_Wajib] IS NOT NULL),
        CONSTRAINT FK_PA_Participant_Req_Ovr_Participant FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant),
        CONSTRAINT FK_PA_Participant_Req_Ovr_Snapshot FOREIGN KEY (PA_Cycle_Competency_Requirement_Snapshot_Id) REFERENCES N_PA_Cycle_Competency_Requirement_Snapshot (Id_PA_Cycle_Competency_Requirement_Snapshot),
        CONSTRAINT FK_PA_Participant_Req_Ovr_Type FOREIGN KEY (PA_Competency_Type_Id) REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Participant_Requirement_Override') AND name = 'UX_PA_Participant_Req_Ovr_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Participant_Req_Ovr_Aktif ON N_PA_Participant_Requirement_Override (PA_Participant_Id, PA_Competency_Type_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 64. N_PA_Question_Binding_Master */
IF OBJECT_ID('N_PA_Question_Binding_Master', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Question_Binding_Master (
        Id_PA_Question_Binding_Master int IDENTITY(1,1) NOT NULL,
        ID_Level bigint NOT NULL,
        PA_Rater_Category_Id int NULL,
        Id_Master_Ujian varchar(100) NOT NULL,
        Nama_Ujian_Sumber nvarchar(255) NULL,
        Sumber_Kontrak varchar(30) NOT NULL
            CONSTRAINT DF_PA_QBindMaster_Sumber DEFAULT ('HCLEARN'),
        Jumlah_Kompetensi int NULL,
        Jumlah_Bagian int NULL,
        Sidik_Bagian char(64) NULL,
        Diverifikasi_At datetime2(0) NULL,
        Diverifikasi_By int NULL,
        Catatan nvarchar(400) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_QBindMaster_Flag DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_QBindMaster_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Id_Master_Paket_Ujian varchar(100) NULL,
        Nama_Paket_Ujian_Sumber nvarchar(255) NULL,
        Status_Kesehatan varchar(20) NOT NULL
            CONSTRAINT DF_PA_QBindMaster_Kesehatan DEFAULT ('BELUM_DIPERIKSA'),
        Masalah_Json nvarchar(max) NULL,
        CONSTRAINT PK_PA_Question_Binding_Master PRIMARY KEY CLUSTERED (Id_PA_Question_Binding_Master),
        CONSTRAINT CK_PA_QBindMaster_Flag CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y'),
        CONSTRAINT FK_PA_QBindMaster_RaterCategory FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Question_Binding_Master') AND name = 'IX_PA_QBindMaster_Aktif')
    CREATE NONCLUSTERED INDEX IX_PA_QBindMaster_Aktif ON N_PA_Question_Binding_Master (Flag_Aktif) INCLUDE (ID_Level, PA_Rater_Category_Id, Id_Master_Ujian, Sidik_Bagian);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Question_Binding_Master') AND name = 'UX_PA_QBindMaster_Level_Rater')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_QBindMaster_Level_Rater ON N_PA_Question_Binding_Master (ID_Level, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 65. N_PA_Question_Binding_Master_Detail */
IF OBJECT_ID('N_PA_Question_Binding_Master_Detail', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Question_Binding_Master_Detail (
        Id_PA_Question_Binding_Master_Detail int IDENTITY(1,1) NOT NULL,
        PA_Question_Binding_Master_Id int NOT NULL,
        Id_Jenis_Indikator varchar(100) NOT NULL,
        Id_Jenis_Soal varchar(100) NOT NULL,
        Nama_Alat_Tes nvarchar(255) NULL,
        Nama_Bagian_Sumber nvarchar(255) NULL,
        Kode_Soal_Sumber varchar(50) NULL,
        Urutan_Alat_Tes smallint NULL,
        Urutan_Bagian smallint NULL,
        PA_Competency_Id int NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_QBindDetail_Flag DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_QBindDetail_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_QBindDetail PRIMARY KEY CLUSTERED (Id_PA_Question_Binding_Master_Detail),
        CONSTRAINT FK_PA_QBindDetail_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_QBindDetail_Header FOREIGN KEY (PA_Question_Binding_Master_Id) REFERENCES N_PA_Question_Binding_Master (Id_PA_Question_Binding_Master)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Question_Binding_Master_Detail') AND name = 'UX_PA_QBindDetail_Bagian')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_QBindDetail_Bagian ON N_PA_Question_Binding_Master_Detail (PA_Question_Binding_Master_Id, Id_Jenis_Indikator, Id_Jenis_Soal);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Question_Binding_Master_Detail') AND name = 'UX_PA_QBindDetail_Kompetensi')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_QBindDetail_Kompetensi ON N_PA_Question_Binding_Master_Detail (PA_Question_Binding_Master_Id, PA_Competency_Id);
GO

/* 66. N_PA_Result_Competency */
IF OBJECT_ID('N_PA_Result_Competency', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Competency (
        Id_PA_Result_Competency int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        PA_Competency_Id int NOT NULL,
        PA_Rater_Category_Id int NULL,
        Nilai decimal(6,2) NULL,
        Jumlah_Penilai smallint NULL,
        Calculation_Version int NOT NULL
            CONSTRAINT DF_PA_Result_Competency_Calc_Version DEFAULT ((1)),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Competency_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Competency_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Competency PRIMARY KEY CLUSTERED (Id_PA_Result_Competency),
        CONSTRAINT CK_PA_Result_Competency_Calc_Version CHECK ([Calculation_Version]>=(1)),
        CONSTRAINT CK_PA_Result_Competency_Nilai CHECK ([Nilai] IS NULL OR [Nilai]>=(0)),
        CONSTRAINT CK_PA_Result_Competency_Penilai CHECK ([Jumlah_Penilai] IS NULL OR [Jumlah_Penilai]>=(0)),
        CONSTRAINT FK_PA_Result_Competency_Category FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category),
        CONSTRAINT FK_PA_Result_Competency_Comp FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Result_Competency_Result FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result_Competency') AND name = 'IX_PA_Result_Competency_Agregat')
    CREATE NONCLUSTERED INDEX IX_PA_Result_Competency_Agregat ON N_PA_Result_Competency (PA_Result_Id, Flag_Aktif) INCLUDE (PA_Competency_Id, PA_Rater_Category_Id, Nilai);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result_Competency') AND name = 'UX_PA_Result_Competency_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Result_Competency_Aktif ON N_PA_Result_Competency (PA_Result_Id, PA_Competency_Id, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* 67. N_PA_Result_History */
IF OBJECT_ID('N_PA_Result_History', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_History (
        Id_PA_Result_History int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        Jenis_Peristiwa varchar(30) NOT NULL,
        Calculation_Version int NOT NULL,
        Status_Hasil varchar(20) NOT NULL,
        Nilai_KPI decimal(6,2) NULL,
        Nilai_KPI_Sebelum_Cap decimal(6,2) NULL,
        Nilai_Kompetensi decimal(6,2) NULL,
        Bobot_KPI_Terpakai decimal(5,2) NULL,
        Bobot_Kompetensi_Terpakai decimal(5,2) NULL,
        Final_Score decimal(6,2) NULL,
        Kode_Grade_Sistem varchar(40) NULL,
        Kode_Grade_Final varchar(40) NULL,
        Flag_Dikalibrasi char(1) NOT NULL,
        PA_Hav_Quadrant_Id int NULL,
        Alasan_Tertahan nvarchar(500) NULL,
        Keterangan nvarchar(500) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_History_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT PK_PA_Result_History PRIMARY KEY CLUSTERED (Id_PA_Result_History),
        CONSTRAINT FK_PA_Result_History_Result FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result_History') AND name = 'IX_PA_Result_History_Result')
    CREATE NONCLUSTERED INDEX IX_PA_Result_History_Result ON N_PA_Result_History (PA_Result_Id, Id_PA_Result_History);
GO

/* 68. N_PA_Result_Note */
IF OBJECT_ID('N_PA_Result_Note', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Note (
        Id_PA_Result_Note int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        PA_Result_Note_Type_Id int NULL,
        PA_Assignment_Id int NULL,
        Isi nvarchar(max) NOT NULL,
        Urutan_Tampil smallint NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        PA_Competency_Id int NULL,
        CONSTRAINT PK_PA_Result_Note PRIMARY KEY CLUSTERED (Id_PA_Result_Note),
        CONSTRAINT CK_PA_Result_Note_Isi CHECK (len(ltrim(rtrim([Isi])))>(0)),
        CONSTRAINT CK_PA_Result_Note_Urutan CHECK ([Urutan_Tampil] IS NULL OR [Urutan_Tampil]>=(1)),
        CONSTRAINT FK_PA_Result_Note_Assignment FOREIGN KEY (PA_Assignment_Id) REFERENCES N_PA_Assignment (Id_PA_Assignment),
        CONSTRAINT FK_PA_Result_Note_Competency FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency),
        CONSTRAINT FK_PA_Result_Note_Result FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result),
        CONSTRAINT FK_PA_Result_Note_Type FOREIGN KEY (PA_Result_Note_Type_Id) REFERENCES N_PA_Result_Note_Type (Id_PA_Result_Note_Type)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result_Note') AND name = 'IX_PA_Result_Note_Competency')
    CREATE NONCLUSTERED INDEX IX_PA_Result_Note_Competency ON N_PA_Result_Note (PA_Result_Id, PA_Competency_Id);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result_Note') AND name = 'IX_PA_Result_Note_Result')
    CREATE NONCLUSTERED INDEX IX_PA_Result_Note_Result ON N_PA_Result_Note (PA_Result_Id, Flag_Aktif) INCLUDE (PA_Result_Note_Type_Id, PA_Assignment_Id, Urutan_Tampil);
GO

/* 69. N_PA_Result_Rater_Category */
IF OBJECT_ID('N_PA_Result_Rater_Category', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Rater_Category (
        Id_PA_Result_Rater_Category int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        Nilai decimal(6,2) NULL,
        Jumlah_Rater_Diharapkan smallint NULL,
        Jumlah_Rater_Mengisi smallint NULL,
        Bobot_Terpakai decimal(5,2) NULL,
        Calculation_Version int NOT NULL
            CONSTRAINT DF_PA_Result_Rater_Cat_Calc_Version DEFAULT ((1)),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Rater_Cat_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Rater_Cat_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Rater_Category PRIMARY KEY CLUSTERED (Id_PA_Result_Rater_Category),
        CONSTRAINT CK_PA_Result_Rater_Cat_Bobot CHECK ([Bobot_Terpakai] IS NULL OR [Bobot_Terpakai]>=(0) AND [Bobot_Terpakai]<=(100)),
        CONSTRAINT CK_PA_Result_Rater_Cat_Calc_Version CHECK ([Calculation_Version]>=(1)),
        CONSTRAINT CK_PA_Result_Rater_Cat_Jumlah CHECK ([Jumlah_Rater_Diharapkan] IS NULL OR [Jumlah_Rater_Mengisi] IS NULL OR [Jumlah_Rater_Mengisi]<=[Jumlah_Rater_Diharapkan]),
        CONSTRAINT CK_PA_Result_Rater_Cat_Nilai CHECK ([Nilai] IS NULL OR [Nilai]>=(0)),
        CONSTRAINT FK_PA_Result_Rater_Cat_Category FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category),
        CONSTRAINT FK_PA_Result_Rater_Cat_Result FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result_Rater_Category') AND name = 'UX_PA_Result_Rater_Cat_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX UX_PA_Result_Rater_Cat_Aktif ON N_PA_Result_Rater_Category (PA_Result_Id, PA_Rater_Category_Id) WHERE ([Flag_Aktif]='Y');
GO

/* Catatan perubahan di ledger production. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-187')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-187',
        'DDL',
        'Skema Performance Appraisal lengkap untuk production',
        'CREATE 69 tabel (skema PA lengkap, bentuk akhir): N_PA_* dan relasi organisasi HRIS yang dirujuknya, dibangkitkan dari bentuk akhir di development. Menggantikan rangkaian DDL PA bertahap (064–185) untuk production.',
        'database/ddl/DDL-2026-187-pa-skema-lengkap-production/forward.sql',
        'docs/changes/DDL-2026-187-pa-skema-lengkap-production/README.md',
        'Web_HRIS', 'NOT_APPLICABLE', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal. Khusus production; data menyusul lewat DML terpisah.',
        NULL
    );
END;
GO
