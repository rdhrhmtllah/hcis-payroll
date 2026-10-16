/* Change ID: DDL-2026-194-rbac-katalog-fitur-kemampuan */
/* RBAC Standarisasi: Master Fitur Bisnis, Kemampuan Fitur, dan Pemetaan Role Kemampuan.
   Target: Web_HRIS. Idempoten (aman dijalankan ulang). */

SET XACT_ABORT ON;
BEGIN TRANSACTION;

-- 1. Master Fitur Bisnis
IF OBJECT_ID('dbo.N_HRIS_Fitur', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Fitur (
        Kode_Fitur varchar(50) NOT NULL,
        Id_Grup_Fitur int NOT NULL,
        Nama_Fitur varchar(100) NOT NULL,
        Deskripsi nvarchar(255) NULL,
        Jenis_Page varchar(100) NOT NULL,
        Flag_Punya_Approval char(1) NOT NULL CONSTRAINT DF_HRIS_Fitur_Approval DEFAULT ('T'),
        Id_Approval_Config int NULL,
        Halaman_Antrean_Approval varchar(100) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Fitur_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Fitur_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2 NULL,
        Updated_By int NULL,
        CONSTRAINT PK_HRIS_Fitur PRIMARY KEY (Kode_Fitur),
        CONSTRAINT CK_HRIS_Fitur_Approval CHECK (Flag_Punya_Approval IN ('Y', 'T')),
        CONSTRAINT CK_HRIS_Fitur_Aktif CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Fitur_Grup' AND object_id = OBJECT_ID('dbo.N_HRIS_Fitur'))
    CREATE INDEX IX_HRIS_Fitur_Grup ON dbo.N_HRIS_Fitur (Id_Grup_Fitur, Flag_Aktif);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Fitur_Page' AND object_id = OBJECT_ID('dbo.N_HRIS_Fitur'))
    CREATE INDEX IX_HRIS_Fitur_Page ON dbo.N_HRIS_Fitur (Jenis_Page);

-- 2. Master Kemampuan Fitur
IF OBJECT_ID('dbo.N_HRIS_Fitur_Kemampuan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Fitur_Kemampuan (
        Kode_Kemampuan varchar(70) NOT NULL,
        Kode_Fitur varchar(50) NOT NULL,
        Nama_Kemampuan varchar(100) NOT NULL,
        Jenis_Aksi varchar(20) NOT NULL,
        Cakupan_Tersedia varchar(100) NOT NULL CONSTRAINT DF_HRIS_Fitur_Kemampuan_Cakupan DEFAULT ('DIRI'),
        Flag_Wajib_Approval char(1) NOT NULL CONSTRAINT DF_HRIS_Fitur_Kemampuan_WajibAppr DEFAULT ('T'),
        Sort_Order int NOT NULL CONSTRAINT DF_HRIS_Fitur_Kemampuan_Sort DEFAULT (1),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Fitur_Kemampuan_Aktif DEFAULT ('Y'),
        CONSTRAINT PK_HRIS_Fitur_Kemampuan PRIMARY KEY (Kode_Kemampuan),
        CONSTRAINT FK_HRIS_Fitur_Kemampuan_Fitur FOREIGN KEY (Kode_Fitur) REFERENCES dbo.N_HRIS_Fitur (Kode_Fitur),
        CONSTRAINT CK_HRIS_Fitur_Kemampuan_WajibAppr CHECK (Flag_Wajib_Approval IN ('Y', 'T')),
        CONSTRAINT CK_HRIS_Fitur_Kemampuan_Aktif CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Fitur_Kemampuan_Fitur' AND object_id = OBJECT_ID('dbo.N_HRIS_Fitur_Kemampuan'))
    CREATE INDEX IX_HRIS_Fitur_Kemampuan_Fitur ON dbo.N_HRIS_Fitur_Kemampuan (Kode_Fitur, Flag_Aktif);

-- 3. Pemetaan Role ke Kemampuan Fitur
IF OBJECT_ID('dbo.N_HRIS_Role_Kemampuan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Role_Kemampuan (
        Id_Role_Kemampuan int IDENTITY(1,1) NOT NULL,
        Id_Role int NOT NULL,
        Kode_Kemampuan varchar(70) NOT NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Role_Kemampuan_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Role_Kemampuan_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT PK_HRIS_Role_Kemampuan PRIMARY KEY (Id_Role_Kemampuan),
        CONSTRAINT UQ_HRIS_Role_Kemampuan UNIQUE (Id_Role, Kode_Kemampuan),
        CONSTRAINT FK_HRIS_Role_Kemampuan_Role FOREIGN KEY (Id_Role) REFERENCES dbo.N_HRIS_Role_Functional (Id_Role),
        CONSTRAINT FK_HRIS_Role_Kemampuan_Kemampuan FOREIGN KEY (Kode_Kemampuan) REFERENCES dbo.N_HRIS_Fitur_Kemampuan (Kode_Kemampuan),
        CONSTRAINT CK_HRIS_Role_Kemampuan_Aktif CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Role_Kemampuan_Role' AND object_id = OBJECT_ID('dbo.N_HRIS_Role_Kemampuan'))
    CREATE INDEX IX_HRIS_Role_Kemampuan_Role ON dbo.N_HRIS_Role_Kemampuan (Id_Role, Flag_Aktif);

COMMIT TRANSACTION;
