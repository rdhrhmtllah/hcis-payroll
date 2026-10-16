-- database/ddl/DDL-2026-009-create-report-template-tables/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-009-create-report-template-tables/README.md

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Report_Template')
CREATE TABLE N_HRIS_Report_Template (
    Id_Report_Template      int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_Report_Template PRIMARY KEY,
    Module                  varchar(30)   NOT NULL,
    Kode                    varchar(60)   NOT NULL,
    Nama                    nvarchar(150) NOT NULL,
    Deskripsi               nvarchar(500) NULL,
    Dataset_Key             varchar(40)   NOT NULL,
    Mode                    varchar(20)   NOT NULL,
    Definisi_Json           nvarchar(max) NOT NULL,
    Versi_Definisi          int           NOT NULL DEFAULT 1,
    Tingkat_Bagi            varchar(20)   NOT NULL,
    Kode_Karyawan_Pemilik   varchar(50)   NOT NULL,
    Kode_Template_Asal      varchar(60)   NULL,
    Flag_Aktif              char(1)       NOT NULL DEFAULT 'Y',
    Created_At              datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By              varchar(100)  NULL,
    Updated_At              datetime2(0)  NULL,
    Updated_By              varchar(100)  NULL,
    CONSTRAINT CK_Report_Template_DefinisiJson CHECK (ISJSON(Definisi_Json) = 1)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_Report_Template_Kode')
CREATE UNIQUE INDEX UX_Report_Template_Kode
    ON N_HRIS_Report_Template (Module, Kode);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Report_Template_Pemilik')
CREATE INDEX IX_Report_Template_Pemilik
    ON N_HRIS_Report_Template (Module, Kode_Karyawan_Pemilik, Flag_Aktif);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Report_Template_Bagi')
CREATE INDEX IX_Report_Template_Bagi
    ON N_HRIS_Report_Template (Module, Tingkat_Bagi, Flag_Aktif)
    INCLUDE (Dataset_Key);
GO

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Report_Template_Akses')
CREATE TABLE N_HRIS_Report_Template_Akses (
    Id_Report_Template_Akses int          IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_Report_Template_Akses PRIMARY KEY,
    Report_Template_Id       int          NOT NULL,
    Tipe_Sasaran             varchar(20)  NOT NULL,
    Nilai_Sasaran            varchar(100) NOT NULL,
    Flag_Aktif               char(1)      NOT NULL DEFAULT 'Y',
    Created_At               datetime2(0) NOT NULL DEFAULT (SYSDATETIME()),
    Created_By               varchar(100) NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_Report_Template_Akses_Sasaran')
CREATE UNIQUE INDEX UX_Report_Template_Akses_Sasaran
    ON N_HRIS_Report_Template_Akses (Report_Template_Id, Tipe_Sasaran, Nilai_Sasaran);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Report_Template_Akses_Cari')
CREATE INDEX IX_Report_Template_Akses_Cari
    ON N_HRIS_Report_Template_Akses (Tipe_Sasaran, Nilai_Sasaran, Flag_Aktif);
GO
