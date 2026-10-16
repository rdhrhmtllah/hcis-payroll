/* Change ID: DDL-2026-184-rbac-cakupan-template */
/* RBAC: template cakupan bernama buatan pengelola akses. Satu template = beberapa
   syarat; syarat berbeda jenis digabung "DAN", sejenis digabung "ATAU". Penugasan
   memakainya lewat N_HRIS_Role_User_Cakupan (Jenis_Cakupan = 'TEMPLATE', Nilai =
   Id_Cakupan_Template). Aditif & idempoten. Tanpa tabel ini fitur template disembunyikan. */
IF OBJECT_ID('dbo.N_HRIS_Cakupan_Template', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Cakupan_Template
    (
        Id_Cakupan_Template int IDENTITY(1,1) NOT NULL,
        Nama nvarchar(100) NOT NULL,
        Keterangan nvarchar(300) NULL,
        Id_Grup_Fitur int NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Cakupan_Template_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Cakupan_Template_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2 NULL,
        Updated_By int NULL,
        CONSTRAINT PK_HRIS_Cakupan_Template PRIMARY KEY (Id_Cakupan_Template),
        CONSTRAINT CK_HRIS_Cakupan_Template_Aktif CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Cakupan_Template_Modul' AND object_id = OBJECT_ID('dbo.N_HRIS_Cakupan_Template'))
    CREATE INDEX IX_HRIS_Cakupan_Template_Modul ON dbo.N_HRIS_Cakupan_Template (Id_Grup_Fitur, Flag_Aktif);
GO
IF OBJECT_ID('dbo.N_HRIS_Cakupan_Template_Syarat', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Cakupan_Template_Syarat
    (
        Id_Cakupan_Template_Syarat int IDENTITY(1,1) NOT NULL,
        Id_Cakupan_Template int NOT NULL,
        Jenis_Cakupan varchar(20) NOT NULL,
        Nilai varchar(100) NULL,
        CONSTRAINT PK_HRIS_Cakupan_Template_Syarat PRIMARY KEY (Id_Cakupan_Template_Syarat),
        CONSTRAINT FK_HRIS_Cakupan_Template_Syarat_Template FOREIGN KEY (Id_Cakupan_Template)
            REFERENCES dbo.N_HRIS_Cakupan_Template (Id_Cakupan_Template)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Cakupan_Template_Syarat_Template' AND object_id = OBJECT_ID('dbo.N_HRIS_Cakupan_Template_Syarat'))
    CREATE INDEX IX_HRIS_Cakupan_Template_Syarat_Template ON dbo.N_HRIS_Cakupan_Template_Syarat (Id_Cakupan_Template);
GO
