-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-122-visitor-matriks-tamu-departemen

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'B2B_Unit') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD B2B_Unit varchar(20) NULL;
END
GO

IF OBJECT_ID('N_VISITOR_Matriks_Tamu', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Matriks_Tamu (
        Id_Matriks_Tamu int IDENTITY(1,1) NOT NULL,
        Role_Id int NOT NULL,
        Kategori_Tamu varchar(20) NOT NULL,
        Flag_Lihat char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Flag_Lihat DEFAULT ('T'),
        Flag_Ajukan char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Flag_Ajukan DEFAULT ('T'),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Matriks_Tamu PRIMARY KEY (Id_Matriks_Tamu),
        CONSTRAINT FK_N_VISITOR_Matriks_Tamu_Role FOREIGN KEY (Role_Id) REFERENCES N_HRIS_Role_Functional (Id_Role)
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Matriks_Tamu_Role_Kategori' AND object_id = OBJECT_ID('N_VISITOR_Matriks_Tamu'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Matriks_Tamu_Role_Kategori
        ON N_VISITOR_Matriks_Tamu (Role_Id, Kategori_Tamu);
END
GO
