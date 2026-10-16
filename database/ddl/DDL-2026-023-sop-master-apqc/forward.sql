-- database/ddl/DDL-2026-023-sop-master-apqc/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-023-sop-master-apqc/README.md
--
-- Seluruh pernyataan bersifat MENAMBAH. Tidak ada yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_APQC')
CREATE TABLE N_SOP_Master_APQC (
    Id_SOP_APQC   int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_APQC PRIMARY KEY,
    Kode_Proses   varchar(10)   NOT NULL,
    Nama_Proses   nvarchar(200) NOT NULL,
    Deskripsi     nvarchar(500) NULL,
    Urutan        int           NOT NULL DEFAULT 0,
    Flag_Aktif    char(1)       NOT NULL DEFAULT 'Y',
    Created_At    datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By    int           NULL,
    Updated_At    datetime2(0)  NULL,
    Updated_By    int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_APQC_Kode')
CREATE UNIQUE INDEX UX_SOP_APQC_Kode ON N_SOP_Master_APQC (Kode_Proses);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_APQC_Urut')
CREATE INDEX IX_SOP_APQC_Urut ON N_SOP_Master_APQC (Flag_Aktif, Urutan)
    INCLUDE (Kode_Proses, Nama_Proses);
GO

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_APQC_Tipe_Item')
CREATE TABLE N_SOP_Master_APQC_Tipe_Item (
    Id_SOP_APQC_Tipe_Item int          IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_APQC_Tipe_Item PRIMARY KEY,
    APQC_Id               int          NOT NULL,
    Tipe_Item             varchar(12)  NOT NULL,
    Flag_Aktif            char(1)      NOT NULL DEFAULT 'Y',
    Created_At            datetime2(0) NOT NULL DEFAULT (SYSDATETIME()),
    Created_By            int          NULL,
    CONSTRAINT FK_SOP_APQC_Tipe_APQC FOREIGN KEY (APQC_Id)
        REFERENCES N_SOP_Master_APQC (Id_SOP_APQC)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_APQC_Tipe_Pasangan')
CREATE UNIQUE INDEX UX_SOP_APQC_Tipe_Pasangan
    ON N_SOP_Master_APQC_Tipe_Item (APQC_Id, Tipe_Item);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_APQC_Tipe_Cari')
CREATE INDEX IX_SOP_APQC_Tipe_Cari
    ON N_SOP_Master_APQC_Tipe_Item (Tipe_Item, Flag_Aktif) INCLUDE (APQC_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'APQC_Id')
ALTER TABLE N_SOP_Dokumen ADD APQC_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_APQC')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_APQC
    FOREIGN KEY (APQC_Id) REFERENCES N_SOP_Master_APQC (Id_SOP_APQC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_APQC' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_APQC ON N_SOP_Dokumen (Flag_Aktif, APQC_Id);
GO
