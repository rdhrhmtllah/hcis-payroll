-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-123-visitor-jadwal-inspeksi-permit
-- r2: pos inspeksi menjadi master (N_VISITOR_Master_Pos_Inspeksi); pola,
-- pengecualian, dan booking merujuknya lewat Id_Pos_Inspeksi. Booking juga
-- menyimpan Snapshot_Nama_Pos (nama saat dipesan).
-- r3: shift kerja karyawan (N_VISITOR_Master_Shift_Inspeksi) menjadi wadah
-- jadwal; SHE membuka slot di sela-sela jam kerja tiap shift. Jam jadwal TIDAK
-- dibatasi jam shift — justru sering di luarnya.
-- r4: tiap hari punya ketiga shift; sakelar aktif/nonaktif disimpan per
-- (hari, shift) dan per hari penuh di N_VISITOR_Inspeksi_Hari_Shift.

IF OBJECT_ID('N_VISITOR_Master_Shift_Inspeksi', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Master_Shift_Inspeksi (
        Id_Shift_Inspeksi int IDENTITY(1,1) NOT NULL,
        Nama_Shift varchar(50) NOT NULL,
        -- Jam KERJA karyawan shift ini. Jam_Selesai <= Jam_Mulai berarti
        -- melewati tengah malam (Shift 3: 23:00-07:00).
        Jam_Mulai time(0) NOT NULL,
        Jam_Selesai time(0) NOT NULL,
        Urutan smallint NOT NULL CONSTRAINT DF_N_VISITOR_Master_Shift_Inspeksi_Urutan DEFAULT (0),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Shift_Inspeksi_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Shift_Inspeksi_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Master_Shift_Inspeksi PRIMARY KEY (Id_Shift_Inspeksi)
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Master_Shift_Inspeksi_Nama' AND object_id = OBJECT_ID('N_VISITOR_Master_Shift_Inspeksi'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Master_Shift_Inspeksi_Nama
        ON N_VISITOR_Master_Shift_Inspeksi (Nama_Shift);
END
GO

IF OBJECT_ID('N_VISITOR_Master_Pos_Inspeksi', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Master_Pos_Inspeksi (
        Id_Pos_Inspeksi int IDENTITY(1,1) NOT NULL,
        Nama_Pos varchar(100) NOT NULL,
        Keterangan_Lokasi varchar(250) NULL,
        Urutan smallint NOT NULL CONSTRAINT DF_N_VISITOR_Master_Pos_Inspeksi_Urutan DEFAULT (0),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Pos_Inspeksi_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Pos_Inspeksi_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Master_Pos_Inspeksi PRIMARY KEY (Id_Pos_Inspeksi)
    );
END
GO

-- Dua pos bernama sama membuat pemohon tidak tahu harus ke mana.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Master_Pos_Inspeksi_Nama' AND object_id = OBJECT_ID('N_VISITOR_Master_Pos_Inspeksi'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Master_Pos_Inspeksi_Nama
        ON N_VISITOR_Master_Pos_Inspeksi (Nama_Pos);
END
GO

IF OBJECT_ID('N_VISITOR_Inspeksi_Pola', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Pola (
        Id_Inspeksi_Pola int IDENTITY(1,1) NOT NULL,
        Id_Pos_Inspeksi int NOT NULL,
        Id_Shift_Inspeksi int NOT NULL,
        Hari_Minggu tinyint NOT NULL,
        Jam_Mulai time(0) NOT NULL,
        Jam_Selesai time(0) NOT NULL,
        Durasi_Menit smallint NOT NULL,
        Kuota_Per_Slot smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pola_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pola_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Pola PRIMARY KEY (Id_Inspeksi_Pola)
    );
END
GO

IF OBJECT_ID('N_VISITOR_Inspeksi_Pengecualian', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Pengecualian (
        Id_Inspeksi_Pengecualian int IDENTITY(1,1) NOT NULL,
        Tanggal date NOT NULL,
        Id_Pos_Inspeksi int NULL, -- NULL = semua pos (hanya TUTUP)
        Id_Shift_Inspeksi int NULL, -- slot tambahan boleh tanpa shift
        Jenis varchar(10) NOT NULL,
        Jam_Mulai time(0) NULL,
        Jam_Selesai time(0) NULL,
        Durasi_Menit smallint NULL,
        Kuota_Per_Slot smallint NULL,
        Keterangan varchar(250) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pengecualian_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pengecualian_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Pengecualian PRIMARY KEY (Id_Inspeksi_Pengecualian)
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Inspeksi_Pengecualian_Tanggal' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Pengecualian'))
BEGIN
    CREATE INDEX IX_VISITOR_Inspeksi_Pengecualian_Tanggal
        ON N_VISITOR_Inspeksi_Pengecualian (Tanggal) INCLUDE (Id_Pos_Inspeksi, Jenis, Flag_Aktif);
END
GO

IF OBJECT_ID('N_VISITOR_Inspeksi_Booking', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Booking (
        Id_Inspeksi_Booking int IDENTITY(1,1) NOT NULL,
        No_Transaksi_Permit varchar(50) NOT NULL,
        Id_Pos_Inspeksi int NOT NULL,
        Snapshot_Nama_Pos varchar(100) NOT NULL,
        Id_Shift_Inspeksi int NULL,
        Waktu_Mulai datetime2(0) NOT NULL,
        Waktu_Selesai datetime2(0) NOT NULL,
        Status varchar(20) NOT NULL,
        Alasan varchar(250) NULL,
        Diinspeksi_Oleh varchar(100) NULL,
        Diinspeksi_At datetime2(0) NULL,
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Booking_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Booking PRIMARY KEY (Id_Inspeksi_Booking)
    );
END
GO

-- Satu booking aktif per permit.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Inspeksi_Booking_Aktif' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Booking'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Inspeksi_Booking_Aktif
        ON N_VISITOR_Inspeksi_Booking (No_Transaksi_Permit)
        WHERE Status = 'TERJADWAL';
END
GO

-- Hitungan kuota per slot (pos + jam) dan agenda per rentang waktu.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Inspeksi_Booking_Slot' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Booking'))
BEGIN
    CREATE INDEX IX_VISITOR_Inspeksi_Booking_Slot
        ON N_VISITOR_Inspeksi_Booking (Waktu_Mulai, Id_Pos_Inspeksi, Status)
        INCLUDE (No_Transaksi_Permit, Waktu_Selesai);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Inspeksi_Booking_Permit' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Booking'))
BEGIN
    CREATE INDEX IX_VISITOR_Inspeksi_Booking_Permit
        ON N_VISITOR_Inspeksi_Booking (No_Transaksi_Permit, Status);
END
GO

-- Rujukan ke master pos. Blok terpisah dari CREATE TABLE supaya tetap
-- terpasang pada tabel yang sudah ada.
IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pola_Pos', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pola ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pola_Pos
        FOREIGN KEY (Id_Pos_Inspeksi) REFERENCES N_VISITOR_Master_Pos_Inspeksi (Id_Pos_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pengecualian_Pos', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pengecualian ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pengecualian_Pos
        FOREIGN KEY (Id_Pos_Inspeksi) REFERENCES N_VISITOR_Master_Pos_Inspeksi (Id_Pos_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Booking_Pos', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Booking ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Booking_Pos
        FOREIGN KEY (Id_Pos_Inspeksi) REFERENCES N_VISITOR_Master_Pos_Inspeksi (Id_Pos_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pola_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pola ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pola_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pengecualian_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pengecualian ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pengecualian_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Booking_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Booking ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Booking_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO

-- Sakelar hari & shift. TIDAK ADA BARIS = aktif; baris dengan Flag_Aktif='T'
-- mematikannya. Id_Shift_Inspeksi NULL = SELURUH hari itu dimatikan.
IF OBJECT_ID('N_VISITOR_Inspeksi_Hari_Shift', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Hari_Shift (
        Id_Inspeksi_Hari_Shift int IDENTITY(1,1) NOT NULL,
        Hari_Minggu tinyint NOT NULL,
        Id_Shift_Inspeksi int NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Hari_Shift_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Hari_Shift_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Hari_Shift PRIMARY KEY (Id_Inspeksi_Hari_Shift)
    );
END
GO

-- Satu sakelar per (hari, shift); NULL dianggap sama oleh indeks unik SQL
-- Server, jadi sakelar "seluruh hari" pun hanya satu per hari.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Inspeksi_Hari_Shift' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Hari_Shift'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Inspeksi_Hari_Shift
        ON N_VISITOR_Inspeksi_Hari_Shift (Hari_Minggu, Id_Shift_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Hari_Shift_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Hari_Shift ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Hari_Shift_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO
