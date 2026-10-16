/* ============================================================================
   SOP 22A - DDL Versi Paket SOP
   Jalankan manual oleh DBA/admin database.
   Tidak berisi INSERT/UPDATE/DELETE data bisnis.
   ============================================================================ */

IF OBJECT_ID('dbo.N_SOP_Paket_Versi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Paket_Versi (
        Id_SOP_Paket_Versi BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Paket_Versi PRIMARY KEY,
        Id_SOP_Paket INT NOT NULL,
        Versi_Ke INT NOT NULL,
        Status VARCHAR(30) NOT NULL
            DEFAULT ('PUBLISHED'),
        Trigger_Perubahan VARCHAR(30) NULL,
        Ringkasan_Perubahan NVARCHAR(1000) NULL,
        Snapshot_Hash CHAR(40) NOT NULL,
        Snapshot_JSON NVARCHAR(MAX) NULL,
        Item_Total INT NOT NULL
            DEFAULT (0),
        Item_Wajib INT NOT NULL
            DEFAULT (0),
        Id_SOP_Item_Versi_Pemicu BIGINT NULL,
        Published_By INT NULL,
        Kode_Karyawan_Publisher VARCHAR(50) NULL,
        Tanggal_Publish DATETIME2(0) NOT NULL
            DEFAULT (SYSDATETIME()),
        Created_At DATETIME2(0) NOT NULL
            DEFAULT (SYSDATETIME())
    );

    CREATE UNIQUE INDEX UX_N_SOP_Paket_Versi_Paket_Versi
        ON dbo.N_SOP_Paket_Versi (Id_SOP_Paket, Versi_Ke);

    CREATE INDEX IX_N_SOP_Paket_Versi_Paket_Tanggal
        ON dbo.N_SOP_Paket_Versi (Id_SOP_Paket, Tanggal_Publish DESC);

    CREATE INDEX IX_N_SOP_Paket_Versi_Paket_Hash
        ON dbo.N_SOP_Paket_Versi (Id_SOP_Paket, Snapshot_Hash);
END;
GO

IF OBJECT_ID('dbo.N_SOP_Paket_Versi_Item', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Paket_Versi_Item (
        Id_SOP_Paket_Versi_Item BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Paket_Versi_Item PRIMARY KEY,
        Id_SOP_Paket_Versi BIGINT NOT NULL,
        Id_SOP_Paket_Item INT NOT NULL,
        Id_SOP_Item_Versi BIGINT NULL,
        Id_SOP_Section INT NULL,
        Urutan INT NULL,
        Tipe_Item VARCHAR(30) NULL,
        Judul_Item NVARCHAR(255) NULL,
        Wajib CHAR(1) NOT NULL
            DEFAULT ('T'),
        Versi_Item INT NULL,
        Status_Item_Versi VARCHAR(30) NULL,
        Ringkasan_Perubahan NVARCHAR(1000) NULL,
        Created_At DATETIME2(0) NOT NULL
            DEFAULT (SYSDATETIME()),
        CONSTRAINT FK_N_SOP_Paket_Versi_Item_Header
            FOREIGN KEY (Id_SOP_Paket_Versi)
            REFERENCES dbo.N_SOP_Paket_Versi (Id_SOP_Paket_Versi)
    );

    CREATE INDEX IX_N_SOP_Paket_Versi_Item_Header
        ON dbo.N_SOP_Paket_Versi_Item (Id_SOP_Paket_Versi, Urutan);

    CREATE INDEX IX_N_SOP_Paket_Versi_Item_Item
        ON dbo.N_SOP_Paket_Versi_Item (Id_SOP_Paket_Item, Id_SOP_Item_Versi);
END;
GO

IF COL_LENGTH('dbo.N_SOP_Master_Paket', 'Versi_Paket_Aktif') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket
        ADD Versi_Paket_Aktif INT NULL;
END;
GO

IF COL_LENGTH('dbo.N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket
        ADD Id_SOP_Paket_Versi_Aktif BIGINT NULL;
END;
GO
