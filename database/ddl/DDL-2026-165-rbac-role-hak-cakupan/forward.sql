/* Change ID: DDL-2026-165-rbac-role-hak-cakupan */
/* RBAC Fase 2a: model role -> hak, cakupan & tanggung jawab per penugasan,
   kedaluwarsa/alasan. Aditif & idempoten; tidak mengubah keputusan akses. */
IF OBJECT_ID('dbo.N_HRIS_Role_Hak', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Role_Hak
    (
        Id_Role_Hak int IDENTITY(1,1) NOT NULL,
        Id_Role int NOT NULL,
        Jenis_page varchar(100) NOT NULL,
        Id_Aksi int NOT NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Role_Hak_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Role_Hak_Created DEFAULT (sysdatetime()),
        Created_By varchar(100) NULL,
        Updated_At datetime2 NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_HRIS_Role_Hak PRIMARY KEY (Id_Role_Hak),
        CONSTRAINT UQ_HRIS_Role_Hak UNIQUE (Id_Role, Jenis_page, Id_Aksi)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Role_Hak_Halaman' AND object_id = OBJECT_ID('dbo.N_HRIS_Role_Hak'))
    CREATE INDEX IX_HRIS_Role_Hak_Halaman ON dbo.N_HRIS_Role_Hak (Jenis_page, Id_Aksi) INCLUDE (Id_Role, Flag_Aktif);
GO
IF COL_LENGTH('dbo.N_HRIS_Role_Functional', 'Jenis_Role') IS NULL
    ALTER TABLE dbo.N_HRIS_Role_Functional
        ADD Jenis_Role varchar(20) NOT NULL CONSTRAINT DF_HRIS_Role_Functional_Jenis DEFAULT ('AKSES');
GO
IF COL_LENGTH('dbo.N_HRIS_Role_User', 'Berlaku_Sampai') IS NULL
    ALTER TABLE dbo.N_HRIS_Role_User ADD Berlaku_Sampai datetime NULL;
GO
IF COL_LENGTH('dbo.N_HRIS_Role_User', 'Alasan') IS NULL
    ALTER TABLE dbo.N_HRIS_Role_User ADD Alasan varchar(500) NULL;
GO
IF COL_LENGTH('dbo.HRIS_Page_Access', 'Alasan') IS NULL
    ALTER TABLE dbo.HRIS_Page_Access ADD Alasan varchar(500) NULL;
GO
IF COL_LENGTH('dbo.HRIS_Page_Access', 'Berlaku_Sampai') IS NULL
    ALTER TABLE dbo.HRIS_Page_Access ADD Berlaku_Sampai datetime NULL;
GO
IF OBJECT_ID('dbo.N_HRIS_Role_User_Cakupan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Role_User_Cakupan
    (
        Id_Role_User_Cakupan int IDENTITY(1,1) NOT NULL,
        Id_Role_User int NOT NULL,
        Jenis_Cakupan varchar(20) NOT NULL,
        Nilai varchar(100) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Role_User_Cakupan_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Role_User_Cakupan_Created DEFAULT (sysdatetime()),
        CONSTRAINT PK_HRIS_Role_User_Cakupan PRIMARY KEY (Id_Role_User_Cakupan)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Role_User_Cakupan_Penugasan' AND object_id = OBJECT_ID('dbo.N_HRIS_Role_User_Cakupan'))
    CREATE INDEX IX_HRIS_Role_User_Cakupan_Penugasan ON dbo.N_HRIS_Role_User_Cakupan (Id_Role_User);
GO
IF OBJECT_ID('dbo.N_HRIS_Role_User_Tanggung_Jawab', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Role_User_Tanggung_Jawab
    (
        Id_Role_User_Tanggung_Jawab int IDENTITY(1,1) NOT NULL,
        Id_Role_User int NOT NULL,
        Kode varchar(20) NOT NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Role_User_TJ_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Role_User_TJ_Created DEFAULT (sysdatetime()),
        CONSTRAINT PK_HRIS_Role_User_Tanggung_Jawab PRIMARY KEY (Id_Role_User_Tanggung_Jawab),
        CONSTRAINT UQ_HRIS_Role_User_Tanggung_Jawab UNIQUE (Id_Role_User, Kode)
    );
END;
GO
