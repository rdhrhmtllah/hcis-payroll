/* ============================================================================
   SOP 23A - DDL Working Draft Paket SOP

   Tujuan:
   - N_SOP_Master_Paket / Section / Item tetap menjadi data aktif (published).
   - Builder menyimpan seluruh revisi pada tiga tabel draft di bawah.
   - Draf tidak dibaca oleh karyawan maupun proses operasional.

   Prasyarat: SOP 22A (N_SOP_Paket_Versi) sudah dijalankan.
   Aman dijalankan berulang kali; tidak mengubah data paket aktif.
   ============================================================================ */

IF OBJECT_ID('dbo.N_SOP_Paket_Draft', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Paket_Draft (
        Id_SOP_Paket_Draft BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Paket_Draft PRIMARY KEY,
        Id_SOP_Paket INT NOT NULL,
        Id_SOP_Paket_Versi_Basis BIGINT NULL,

        Kode_Paket VARCHAR(100) NULL,
        Nama_Paket NVARCHAR(255) NOT NULL,
        Id_Kategori_SOP INT NOT NULL,
        Kode_Karyawan_Owner VARCHAR(50) NULL,
        Deskripsi NVARCHAR(MAX) NULL,
        Tujuan_Ruang_Lingkup NVARCHAR(MAX) NULL,
        Prioritas VARCHAR(30) NULL,
        Estimasi_Waktu_Baca_Menit INT NULL,
        Mode_Urutan_Item VARCHAR(30) NULL,
        Mode_Distribusi VARCHAR(30) NULL,
        Cover_Path NVARCHAR(1000) NULL,
        Cover_Position VARCHAR(50) NULL,
        Flag_Absensi CHAR(1) NOT NULL DEFAULT ('T'),

        Status VARCHAR(30) NOT NULL DEFAULT ('DRAFT'), 
        Created_By INT NULL,
        Created_At DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
        Updated_By INT NULL,
        Updated_At DATETIME2(0) NULL,
        Published_By INT NULL,
        Tanggal_Publish DATETIME2(0) NULL,
        Row_Version ROWVERSION NOT NULL,

        CONSTRAINT FK_N_SOP_Paket_Draft_Paket
            FOREIGN KEY (Id_SOP_Paket) REFERENCES dbo.N_SOP_Master_Paket (Id_SOP_Paket)
    );


    CREATE UNIQUE INDEX UX_N_SOP_Paket_Draft_Working
        ON dbo.N_SOP_Paket_Draft (Id_SOP_Paket)
        WHERE Status = 'DRAFT';

    CREATE INDEX IX_N_SOP_Paket_Draft_Paket_Status
        ON dbo.N_SOP_Paket_Draft (Id_SOP_Paket, Status, Updated_At DESC);
END;
GO

IF OBJECT_ID('dbo.N_SOP_Paket_Draft_Section', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Paket_Draft_Section (
        Id_SOP_Draft_Section BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Paket_Draft_Section PRIMARY KEY,
        Id_SOP_Paket_Draft BIGINT NOT NULL,
        Id_SOP_Section_Asal INT NULL, 
        Nama_Section NVARCHAR(255) NOT NULL,
        Deskripsi NVARCHAR(MAX) NULL,
        Urutan INT NOT NULL,
        Flag_Aktif CHAR(1) NOT NULL DEFAULT ('Y'),
        Created_By INT NULL,
        Created_At DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
        Updated_By INT NULL,
        Updated_At DATETIME2(0) NULL,
        Row_Version ROWVERSION NOT NULL,

        CONSTRAINT FK_N_SOP_Paket_Draft_Section_Header
            FOREIGN KEY (Id_SOP_Paket_Draft)
            REFERENCES dbo.N_SOP_Paket_Draft (Id_SOP_Paket_Draft)
    );

    CREATE UNIQUE INDEX UX_N_SOP_Paket_Draft_Section_Source
        ON dbo.N_SOP_Paket_Draft_Section (Id_SOP_Paket_Draft, Id_SOP_Section_Asal)
        WHERE Id_SOP_Section_Asal IS NOT NULL;

    CREATE INDEX IX_N_SOP_Paket_Draft_Section_Order
        ON dbo.N_SOP_Paket_Draft_Section (Id_SOP_Paket_Draft, Flag_Aktif, Urutan);
END;
GO

IF OBJECT_ID('dbo.N_SOP_Paket_Draft_Item', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Paket_Draft_Item (
        Id_SOP_Draft_Item BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Paket_Draft_Item PRIMARY KEY,
        Id_SOP_Paket_Draft BIGINT NOT NULL,
        Id_SOP_Paket_Item_Asal INT NULL, 
        Id_SOP_Draft_Section BIGINT NULL,
        Id_SOP_Item_Versi_Target BIGINT NULL, 

        Tipe_Item VARCHAR(30) NOT NULL,
        Judul_Item NVARCHAR(255) NOT NULL,
        Nomor_SOP_Resmi VARCHAR(100) NULL,
        Deskripsi_Item NVARCHAR(MAX) NULL,
        Konten_Teks NVARCHAR(MAX) NULL,
        Mode_Akses VARCHAR(30) NULL,
        URL_Tautan NVARCHAR(2000) NULL,
        Label_Tautan NVARCHAR(255) NULL,
        Jenis_Quiz VARCHAR(50) NULL,
        Nilai_Minimum DECIMAL(5,2) NULL,
        Id_Master_Ujian VARCHAR(200) NULL,
        Nama_Ujian NVARCHAR(255) NULL,
        Flag_Camera CHAR(1) NOT NULL DEFAULT ('T'),
        Urutan INT NOT NULL,
        Flag_Wajib CHAR(1) NOT NULL DEFAULT ('Y'),
        Flag_Highlight CHAR(1) NOT NULL DEFAULT ('T'),
        Flag_Aktif CHAR(1) NOT NULL DEFAULT ('Y'),
        Created_By INT NULL,
        Created_At DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
        Updated_By INT NULL,
        Updated_At DATETIME2(0) NULL,
        Row_Version ROWVERSION NOT NULL,

        CONSTRAINT FK_N_SOP_Paket_Draft_Item_Header
            FOREIGN KEY (Id_SOP_Paket_Draft)
            REFERENCES dbo.N_SOP_Paket_Draft (Id_SOP_Paket_Draft),
        CONSTRAINT FK_N_SOP_Paket_Draft_Item_Section
            FOREIGN KEY (Id_SOP_Draft_Section)
            REFERENCES dbo.N_SOP_Paket_Draft_Section (Id_SOP_Draft_Section)
    );

    CREATE UNIQUE INDEX UX_N_SOP_Paket_Draft_Item_Source
        ON dbo.N_SOP_Paket_Draft_Item (Id_SOP_Paket_Draft, Id_SOP_Paket_Item_Asal)
        WHERE Id_SOP_Paket_Item_Asal IS NOT NULL;

    CREATE INDEX IX_N_SOP_Paket_Draft_Item_Order
        ON dbo.N_SOP_Paket_Draft_Item (Id_SOP_Paket_Draft, Flag_Aktif, Urutan);

    CREATE INDEX IX_N_SOP_Paket_Draft_Item_Target_Versi
        ON dbo.N_SOP_Paket_Draft_Item (Id_SOP_Item_Versi_Target)
        WHERE Id_SOP_Item_Versi_Target IS NOT NULL;
END;
GO
