/* Change ID: DDL-2026-001-create-system-database-change */
IF OBJECT_ID('N_SYSTEM_Database_Change', 'U') IS NULL
BEGIN
    CREATE TABLE N_SYSTEM_Database_Change
    (
        Id_Database_Change int IDENTITY(1,1) NOT NULL,
        Kode_Change varchar(100) NOT NULL,
        Jenis_Change varchar(10) NOT NULL,
        Judul_Change varchar(200) NOT NULL,
        Deskripsi_Change varchar(500) NULL,
        Artifact_Path varchar(500) NOT NULL,
        Documentation_Path varchar(500) NULL,
        Target_Development varchar(100) NULL,
        Status_Development varchar(30) NOT NULL CONSTRAINT DF_SYSTEM_Database_Change_Development DEFAULT ('PENDING'),
        Status_SQL_Tracker varchar(30) NOT NULL CONSTRAINT DF_SYSTEM_Database_Change_Tracker DEFAULT ('PENDING'),
        SQL_Tracker_Reference varchar(100) NULL,
        Status_Production varchar(30) NOT NULL CONSTRAINT DF_SYSTEM_Database_Change_Production DEFAULT ('PENDING'),
        Status_Verification varchar(30) NOT NULL CONSTRAINT DF_SYSTEM_Database_Change_Verification DEFAULT ('PENDING'),
        Catatan varchar(1000) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_SYSTEM_Database_Change_Active DEFAULT ('Y'),
        Created_At datetime2 NOT NULL CONSTRAINT DF_SYSTEM_Database_Change_Created DEFAULT (sysdatetime()),
        Created_By varchar(100) NULL,
        Updated_At datetime2 NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_SYSTEM_Database_Change PRIMARY KEY (Id_Database_Change),
        CONSTRAINT UQ_SYSTEM_Database_Change_Code UNIQUE (Kode_Change)
    );
END;
