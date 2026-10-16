-- database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-016-sop-jenis-dokumen-kepemilikan/README.md
--
-- Seluruh pernyataan di berkas ini bersifat MENAMBAH. Tidak ada kolom, baris,
-- indeks, atau constraint yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_Jenis_Dokumen')
CREATE TABLE N_SOP_Master_Jenis_Dokumen (
    Id_SOP_Jenis_Dokumen  int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_Jenis_Dokumen PRIMARY KEY,
    Kode_Jenis            varchar(30)   NOT NULL,
    Nama_Jenis            nvarchar(100) NOT NULL,
    Level_Hirarki         int           NOT NULL,
    Deskripsi             nvarchar(500) NULL,
    Urutan                int           NOT NULL DEFAULT 0,
    Flag_Aktif            char(1)       NOT NULL DEFAULT 'Y',
    Created_At            datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By            int           NULL,
    Updated_At            datetime2(0)  NULL,
    Updated_By            int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_Jenis_Dokumen_Kode')
CREATE UNIQUE INDEX UX_SOP_Jenis_Dokumen_Kode
    ON N_SOP_Master_Jenis_Dokumen (Kode_Jenis);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Jenis_Dokumen_Urut')
CREATE INDEX IX_SOP_Jenis_Dokumen_Urut
    ON N_SOP_Master_Jenis_Dokumen (Flag_Aktif, Level_Hirarki, Urutan)
    INCLUDE (Kode_Jenis, Nama_Jenis);
GO

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_Jenis_Dokumen_Tipe_Item')
CREATE TABLE N_SOP_Master_Jenis_Dokumen_Tipe_Item (
    Id_SOP_Jenis_Dokumen_Tipe_Item int          IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_Jenis_Dokumen_Tipe_Item PRIMARY KEY,
    Jenis_Dokumen_Id               int          NOT NULL,
    Tipe_Item                      varchar(12)  NOT NULL,
    Flag_Aktif                     char(1)      NOT NULL DEFAULT 'Y',
    Created_At                     datetime2(0) NOT NULL DEFAULT (SYSDATETIME()),
    Created_By                     int          NULL,
    CONSTRAINT FK_SOP_Jenis_Tipe_Jenis FOREIGN KEY (Jenis_Dokumen_Id)
        REFERENCES N_SOP_Master_Jenis_Dokumen (Id_SOP_Jenis_Dokumen)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_Jenis_Tipe_Pasangan')
CREATE UNIQUE INDEX UX_SOP_Jenis_Tipe_Pasangan
    ON N_SOP_Master_Jenis_Dokumen_Tipe_Item (Jenis_Dokumen_Id, Tipe_Item);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Jenis_Tipe_Cari')
CREATE INDEX IX_SOP_Jenis_Tipe_Cari
    ON N_SOP_Master_Jenis_Dokumen_Tipe_Item (Tipe_Item, Flag_Aktif)
    INCLUDE (Jenis_Dokumen_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Jenis_Dokumen_Id')
ALTER TABLE N_SOP_Dokumen ADD Jenis_Dokumen_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Jenis')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Jenis
    FOREIGN KEY (Jenis_Dokumen_Id) REFERENCES N_SOP_Master_Jenis_Dokumen (Id_SOP_Jenis_Dokumen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Divisi_Id')
ALTER TABLE N_SOP_Dokumen ADD Divisi_Id bigint NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Sub_Divisi_Id')
ALTER TABLE N_SOP_Dokumen ADD Sub_Divisi_Id bigint NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Jenis' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Jenis
    ON N_SOP_Dokumen (Flag_Aktif, Jenis_Dokumen_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Pemilik' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Pemilik
    ON N_SOP_Dokumen (Flag_Aktif, Divisi_Id, Sub_Divisi_Id);
GO
