-- database/ddl/DDL-2026-027-hris-master-departemen/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-027-hris-master-departemen/README.md
--
-- Seluruh pernyataan bersifat MENAMBAH. Tidak ada yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Master_Departemen')
CREATE TABLE N_HRIS_Master_Departemen (
    Id_Departemen   int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_HRIS_Departemen PRIMARY KEY,
    Kode_Departemen varchar(10)   NOT NULL,
    Nama_Departemen nvarchar(150) NOT NULL,
    Sub_Divisi_Id   bigint        NULL,
    Urutan          int           NOT NULL DEFAULT 0,
    Flag_Aktif      char(1)       NOT NULL DEFAULT 'Y',
    Created_At      datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By      int           NULL,
    Updated_At      datetime2(0)  NULL,
    Updated_By      int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_HRIS_Departemen_Kode')
CREATE UNIQUE INDEX UX_HRIS_Departemen_Kode ON N_HRIS_Master_Departemen (Kode_Departemen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Departemen_Urut')
CREATE INDEX IX_HRIS_Departemen_Urut ON N_HRIS_Master_Departemen (Flag_Aktif, Urutan)
    INCLUDE (Kode_Departemen, Nama_Departemen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Departemen_Id')
ALTER TABLE N_SOP_Dokumen ADD Departemen_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Departemen')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Departemen
    FOREIGN KEY (Departemen_Id) REFERENCES N_HRIS_Master_Departemen (Id_Departemen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Departemen' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Departemen ON N_SOP_Dokumen (Flag_Aktif, Departemen_Id);
GO
