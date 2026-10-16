-- Change ID: DDL-2026-045-bootstrap-visitor-master-data
-- Target database: Web_HRIS. Agent execution is limited to development; production is via SQL Tracker.
-- Dependency for an existing installation: DDL-2026-023-add-visitor-master-soft-status.

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF DB_NAME() <> 'Web_HRIS'
    THROW 50001, 'Wrong database. Expected Web_HRIS.', 1;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_EMI_VISITOR_Master_Vehicle_Type
    (
        Id_Vehicle_Type int IDENTITY(1,1) NOT NULL,
        Nama_Tipe_Kendaraan varchar(50) NOT NULL,
        Flag_Aktif char(1) NULL CONSTRAINT DF_VISITOR_Master_Vehicle_Type_Flag_Aktif DEFAULT ('Y'),
        Akses varchar(20) NULL CONSTRAINT DF_VISITOR_Master_Vehicle_Type_Akses DEFAULT ('ALL'),
        CONSTRAINT PK_VISITOR_Master_Vehicle_Type PRIMARY KEY CLUSTERED (Id_Vehicle_Type)
    );
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Model', 'U') IS NULL
BEGIN
    CREATE TABLE N_EMI_VISITOR_Master_Vehicle_Model
    (
        Id_Vehicle_Model int IDENTITY(1,1) NOT NULL,
        Id_Master_Tipe int NULL,
        Nama_Model_Kendaraan varchar(100) NOT NULL,
        Flag_Aktif char(1) NULL CONSTRAINT DF_VISITOR_Master_Vehicle_Model_Flag_Aktif DEFAULT ('Y'),
        CONSTRAINT PK_VISITOR_Master_Vehicle_Model PRIMARY KEY CLUSTERED (Id_Vehicle_Model),
        CONSTRAINT FK_VISITOR_Master_Vehicle_Model_Type FOREIGN KEY (Id_Master_Tipe)
            REFERENCES N_EMI_VISITOR_Master_Vehicle_Type (Id_Vehicle_Type)
    );
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Color', 'U') IS NULL
BEGIN
    CREATE TABLE N_EMI_VISITOR_Master_Vehicle_Color
    (
        Id_Master_Color int IDENTITY(1,1) NOT NULL,
        Nama_Warna varchar(50) NOT NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_VISITOR_Master_Vehicle_Color_Flag_Aktif DEFAULT ('Y'),
        CONSTRAINT PK_VISITOR_Master_Vehicle_Color PRIMARY KEY CLUSTERED (Id_Master_Color)
    );
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Plat_Kendaraan', 'U') IS NULL
BEGIN
    CREATE TABLE N_EMI_VISITOR_Master_Plat_Kendaraan
    (
        Id_Plat_Kendaraan int IDENTITY(1,1) NOT NULL,
        Kode_Plat varchar(5) NOT NULL,
        Flag_Aktif char(1) NULL CONSTRAINT DF_VISITOR_Master_Plat_Kendaraan_Flag_Aktif DEFAULT ('Y'),
        CONSTRAINT PK_VISITOR_Master_Plat_Kendaraan PRIMARY KEY CLUSTERED (Id_Plat_Kendaraan)
    );
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Purpose', 'U') IS NULL
BEGIN
    CREATE TABLE N_EMI_VISITOR_Master_Purpose
    (
        Id_Master_Purpose int IDENTITY(1,1) NOT NULL,
        Nama_Keperluan varchar(100) NOT NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_VISITOR_Master_Purpose_Flag_Aktif DEFAULT ('Y'),
        CONSTRAINT PK_VISITOR_Master_Purpose PRIMARY KEY CLUSTERED (Id_Master_Purpose)
    );
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Durasi_Permit', 'U') IS NULL
BEGIN
    CREATE TABLE N_EMI_VISITOR_Master_Durasi_Permit
    (
        Id_Durasi_Permit int IDENTITY(1,1) NOT NULL,
        Nama_Durasi varchar(50) NOT NULL,
        Jumlah_Angka int NOT NULL,
        Satuan_Waktu varchar(20) NOT NULL,
        Keterangan_Durasi varchar(20) NULL,
        Urutan_Tampil int NULL CONSTRAINT DF_VISITOR_Master_Durasi_Permit_Urutan_Tampil DEFAULT ((0)),
        Flag_Aktif char(1) NULL CONSTRAINT DF_VISITOR_Master_Durasi_Permit_Flag_Aktif DEFAULT ('Y'),
        CONSTRAINT PK_VISITOR_Master_Durasi_Permit PRIMARY KEY CLUSTERED (Id_Durasi_Permit)
    );
END;
GO

DECLARE @Expected TABLE
(
    Table_Name sysname NOT NULL,
    Column_Name sysname NOT NULL,
    Type_Name sysname NOT NULL,
    Max_Length smallint NOT NULL,
    Is_Nullable bit NOT NULL,
    Is_Identity bit NOT NULL
);

INSERT INTO @Expected (Table_Name, Column_Name, Type_Name, Max_Length, Is_Nullable, Is_Identity)
VALUES
('N_EMI_VISITOR_Master_Vehicle_Type', 'Id_Vehicle_Type', 'int', 4, 0, 1),
('N_EMI_VISITOR_Master_Vehicle_Type', 'Nama_Tipe_Kendaraan', 'varchar', 50, 0, 0),
('N_EMI_VISITOR_Master_Vehicle_Type', 'Flag_Aktif', 'char', 1, 1, 0),
('N_EMI_VISITOR_Master_Vehicle_Type', 'Akses', 'varchar', 20, 1, 0),
('N_EMI_VISITOR_Master_Vehicle_Model', 'Id_Vehicle_Model', 'int', 4, 0, 1),
('N_EMI_VISITOR_Master_Vehicle_Model', 'Id_Master_Tipe', 'int', 4, 1, 0),
('N_EMI_VISITOR_Master_Vehicle_Model', 'Nama_Model_Kendaraan', 'varchar', 100, 0, 0),
('N_EMI_VISITOR_Master_Vehicle_Model', 'Flag_Aktif', 'char', 1, 1, 0),
('N_EMI_VISITOR_Master_Vehicle_Color', 'Id_Master_Color', 'int', 4, 0, 1),
('N_EMI_VISITOR_Master_Vehicle_Color', 'Nama_Warna', 'varchar', 50, 0, 0),
('N_EMI_VISITOR_Master_Vehicle_Color', 'Flag_Aktif', 'char', 1, 0, 0),
('N_EMI_VISITOR_Master_Plat_Kendaraan', 'Id_Plat_Kendaraan', 'int', 4, 0, 1),
('N_EMI_VISITOR_Master_Plat_Kendaraan', 'Kode_Plat', 'varchar', 5, 0, 0),
('N_EMI_VISITOR_Master_Plat_Kendaraan', 'Flag_Aktif', 'char', 1, 1, 0),
('N_EMI_VISITOR_Master_Purpose', 'Id_Master_Purpose', 'int', 4, 0, 1),
('N_EMI_VISITOR_Master_Purpose', 'Nama_Keperluan', 'varchar', 100, 0, 0),
('N_EMI_VISITOR_Master_Purpose', 'Flag_Aktif', 'char', 1, 0, 0),
('N_EMI_VISITOR_Master_Durasi_Permit', 'Id_Durasi_Permit', 'int', 4, 0, 1),
('N_EMI_VISITOR_Master_Durasi_Permit', 'Nama_Durasi', 'varchar', 50, 0, 0),
('N_EMI_VISITOR_Master_Durasi_Permit', 'Jumlah_Angka', 'int', 4, 0, 0),
('N_EMI_VISITOR_Master_Durasi_Permit', 'Satuan_Waktu', 'varchar', 20, 0, 0),
('N_EMI_VISITOR_Master_Durasi_Permit', 'Keterangan_Durasi', 'varchar', 20, 1, 0),
('N_EMI_VISITOR_Master_Durasi_Permit', 'Urutan_Tampil', 'int', 4, 1, 0),
('N_EMI_VISITOR_Master_Durasi_Permit', 'Flag_Aktif', 'char', 1, 1, 0);

IF EXISTS
(
    SELECT 1
    FROM @Expected expected
    LEFT JOIN sys.tables tbl ON tbl.name = expected.Table_Name
    LEFT JOIN sys.columns col ON col.object_id = tbl.object_id AND col.name = expected.Column_Name
    LEFT JOIN sys.types typ ON typ.user_type_id = col.user_type_id
    WHERE col.column_id IS NULL
       OR typ.name <> expected.Type_Name
       OR col.max_length <> expected.Max_Length
       OR col.is_nullable <> expected.Is_Nullable
       OR col.is_identity <> expected.Is_Identity
)
    THROW 50002, 'Visitor master schema does not match the reviewed Web_HRIS baseline.', 1;

IF NOT EXISTS
(
    SELECT 1
    FROM sys.foreign_key_columns fkc
    WHERE fkc.parent_object_id = OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Model')
      AND COL_NAME(fkc.parent_object_id, fkc.parent_column_id) = 'Id_Master_Tipe'
      AND fkc.referenced_object_id = OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Type')
      AND COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) = 'Id_Vehicle_Type'
)
    THROW 50003, 'Vehicle model to vehicle type foreign key is missing.', 1;
GO
