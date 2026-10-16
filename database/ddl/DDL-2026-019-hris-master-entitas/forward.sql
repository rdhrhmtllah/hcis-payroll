-- database/ddl/DDL-2026-019-hris-master-entitas/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-019-hris-master-entitas/README.md
--
-- Seluruh pernyataan di berkas ini bersifat MENAMBAH. Tidak ada kolom, baris,
-- indeks, atau constraint yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Master_Entitas')
CREATE TABLE N_HRIS_Master_Entitas (
    Id_Entitas       int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_HRIS_Entitas PRIMARY KEY,
    Kode_Entitas     varchar(10)   NOT NULL,
    Nama_Entitas     nvarchar(150) NOT NULL,
    Nama_Singkat     nvarchar(50)  NULL,
    Warna            varchar(20)   NULL,
    Induk_Entitas_Id int           NULL,
    Urutan           int           NOT NULL DEFAULT 0,
    Flag_Aktif       char(1)       NOT NULL DEFAULT 'Y',
    Created_At       datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By       int           NULL,
    Updated_At       datetime2(0)  NULL,
    Updated_By       int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_HRIS_Entitas_Induk')
ALTER TABLE N_HRIS_Master_Entitas ADD CONSTRAINT FK_HRIS_Entitas_Induk
    FOREIGN KEY (Induk_Entitas_Id) REFERENCES N_HRIS_Master_Entitas (Id_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_HRIS_Entitas_Kode')
CREATE UNIQUE INDEX UX_HRIS_Entitas_Kode
    ON N_HRIS_Master_Entitas (Kode_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Entitas_Urut')
CREATE INDEX IX_HRIS_Entitas_Urut
    ON N_HRIS_Master_Entitas (Flag_Aktif, Urutan)
    INCLUDE (Kode_Entitas, Nama_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Entitas_Pemilik_Id')
ALTER TABLE N_SOP_Dokumen ADD Entitas_Pemilik_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Entitas_Lingkup_Id')
ALTER TABLE N_SOP_Dokumen ADD Entitas_Lingkup_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Entitas_Pemilik')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Entitas_Pemilik
    FOREIGN KEY (Entitas_Pemilik_Id) REFERENCES N_HRIS_Master_Entitas (Id_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Entitas_Lingkup')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Entitas_Lingkup
    FOREIGN KEY (Entitas_Lingkup_Id) REFERENCES N_HRIS_Master_Entitas (Id_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Entitas' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Entitas
    ON N_SOP_Dokumen (Flag_Aktif, Entitas_Pemilik_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Lingkup' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Lingkup
    ON N_SOP_Dokumen (Flag_Aktif, Entitas_Lingkup_Id);
GO
