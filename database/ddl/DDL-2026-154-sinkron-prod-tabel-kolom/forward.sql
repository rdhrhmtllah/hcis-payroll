/* Change ID: DDL-2026-154-sinkron-prod-tabel-kolom

   Tabel dan kolom yang ADA di development (Web_HRIS) tetapi TIDAK ADA di
   production (emi_db_web_hr), diukur 2026-09-29 dari katalog kedua database
   (baca saja). "Tidak ada" dibuktikan dengan pesan galat, bukan katalog saja:
   akun audit tidak melihat tabel tanpa GRANT, jadi tiap tabel/kolom diuji ulang
   ('Invalid object name' / 'Invalid column name').

   Hanya objek yang DIPAKAI aplikasi ini. Dikecualikan dengan sengaja:
   - 420+ tabel dev milik aplikasi lain yang berbagi database dev (kandidat,
     careers, desktop, mesin absensi, AMS, dll.) dan tabel cadangan/uji;
   - kolom yang tidak dirujuk kode: Karyawan.max_Cuti, N_HCIS_Tabel_Absen.
     Koefisien_OT_Gross, N_HRIS_Approval_Request.(id, Kode_Karyawan, Flow_Id,
     angka_wa, created_at, update_at), HRIS_KANDIDAT_Users.*;
   - perbedaan tipe/nullable kolom yang sudah ada (lihat README).

   Definisi dirakit dari katalog development, jadi sama persis dengan dev.
   IDEMPOTEN: setiap langkah dijaga OBJECT_ID / COL_LENGTH / nama constraint.
   Jalankan SEBELUM DDL-2026-155 (index tabel baru bergantung pada ini). */

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ============ BAGIAN 1 — TABEL BARU ============ */

/* 1.1 dbo.N_HRIS_Panduan  (asal: DDL-2026-146, 148, 149) */
IF OBJECT_ID(N'dbo.N_HRIS_Panduan', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Panduan] (
        [Id_Panduan] int IDENTITY(1, 1) NOT NULL,
        [Judul] nvarchar(150) NOT NULL,
        [Deskripsi] nvarchar(500) NULL,
        [Versi] nvarchar(30) NULL,
        [Path_File] varchar(300) NOT NULL,
        [Nama_File_Asli] nvarchar(255) NULL,
        [Ukuran_Byte] bigint NULL,
        [Flag_Aktif] char(1) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Aktif] DEFAULT ('Y'),
        [Created_At] datetime2(7) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Created] DEFAULT (sysdatetime()),
        [Created_By] varchar(100) NULL,
        [Updated_At] datetime2(7) NULL,
        [Updated_By] varchar(100) NULL,
        [Id_Versi_Aktif] int NULL,
        [Versi_Aktif_Sejak] datetime2(7) NULL,
        CONSTRAINT [PK_HRIS_Panduan] PRIMARY KEY CLUSTERED ([Id_Panduan]),
        CONSTRAINT [CK_HRIS_Panduan_Aktif] CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y')
    );
END;

/* 1.2 dbo.N_HRIS_Panduan_Versi  (asal: DDL-2026-149) */
IF OBJECT_ID(N'dbo.N_HRIS_Panduan_Versi', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Panduan_Versi] (
        [Id_Versi] int IDENTITY(1, 1) NOT NULL,
        [Id_Panduan] int NOT NULL,
        [No_Versi] int NOT NULL,
        [Label_Versi] nvarchar(30) NULL,
        [Catatan_Perubahan] nvarchar(1000) NULL,
        [Path_File] varchar(300) NOT NULL,
        [Nama_File_Asli] nvarchar(255) NULL,
        [Ukuran_Byte] bigint NULL,
        [Status] varchar(10) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Versi_Status] DEFAULT ('DRAF'),
        [Diterbitkan_At] datetime2(7) NULL,
        [Diterbitkan_By] varchar(100) NULL,
        [Created_At] datetime2(7) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Versi_Created] DEFAULT (sysdatetime()),
        [Created_By] varchar(100) NULL,
        CONSTRAINT [PK_HRIS_Panduan_Versi] PRIMARY KEY CLUSTERED ([Id_Versi]),
        CONSTRAINT [CK_HRIS_Panduan_Versi_No] CHECK ([No_Versi]>=(1)),
        CONSTRAINT [CK_HRIS_Panduan_Versi_Status] CHECK ([Status]='TERBIT' OR [Status]='DRAF')
    );
END;

/* 1.3 dbo.N_HRIS_Panduan_Halaman  (asal: DDL-2026-146, 148) */
IF OBJECT_ID(N'dbo.N_HRIS_Panduan_Halaman', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Panduan_Halaman] (
        [Id_Panduan_Halaman] int IDENTITY(1, 1) NOT NULL,
        [Id_Panduan] int NOT NULL,
        [Jenis_page] varchar(100) NOT NULL,
        [Halaman_Mulai] int NULL,
        [Id_Role] int NULL,
        [Urutan_Tampil] int NOT NULL CONSTRAINT [DF_HRIS_Panduan_Halaman_Urutan] DEFAULT ((0)),
        [Flag_Aktif] char(1) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Halaman_Aktif] DEFAULT ('Y'),
        [Created_At] datetime2(7) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Halaman_Created] DEFAULT (sysdatetime()),
        [Created_By] varchar(100) NULL,
        [Updated_At] datetime2(7) NULL,
        [Updated_By] varchar(100) NULL,
        CONSTRAINT [PK_HRIS_Panduan_Halaman] PRIMARY KEY CLUSTERED ([Id_Panduan_Halaman]),
        CONSTRAINT [CK_HRIS_Panduan_Halaman_Aktif] CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y'),
        CONSTRAINT [CK_HRIS_Panduan_Halaman_Mulai] CHECK ([Halaman_Mulai] IS NULL OR [Halaman_Mulai]>=(1))
    );
END;

/* 1.4 dbo.N_HRIS_Panduan_Role  (asal: DDL-2026-148) */
IF OBJECT_ID(N'dbo.N_HRIS_Panduan_Role', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Panduan_Role] (
        [Id_Panduan_Role] int IDENTITY(1, 1) NOT NULL,
        [Id_Panduan] int NOT NULL,
        [Id_Role] int NOT NULL,
        [Halaman_Mulai] int NULL,
        [Urutan_Tampil] int NOT NULL CONSTRAINT [DF_HRIS_Panduan_Role_Urutan] DEFAULT ((0)),
        [Flag_Aktif] char(1) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Role_Aktif] DEFAULT ('Y'),
        [Created_At] datetime2(7) NOT NULL CONSTRAINT [DF_HRIS_Panduan_Role_Created] DEFAULT (sysdatetime()),
        [Created_By] varchar(100) NULL,
        [Updated_At] datetime2(7) NULL,
        [Updated_By] varchar(100) NULL,
        CONSTRAINT [PK_HRIS_Panduan_Role] PRIMARY KEY CLUSTERED ([Id_Panduan_Role]),
        CONSTRAINT [CK_HRIS_Panduan_Role_Aktif] CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y'),
        CONSTRAINT [CK_HRIS_Panduan_Role_Mulai] CHECK ([Halaman_Mulai] IS NULL OR [Halaman_Mulai]>=(1))
    );
END;

/* 1.5 dbo.N_HRIS_Master_Kontak_Bantuan  (asal: DDL-2026-144) */
IF OBJECT_ID(N'dbo.N_HRIS_Master_Kontak_Bantuan', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Master_Kontak_Bantuan] (
        [Id_Kontak_Bantuan] int IDENTITY(1, 1) NOT NULL,
        [Kode_Grup_Fitur] varchar(20) NOT NULL,
        [Kode_Fitur] varchar(50) NOT NULL,
        [Nama_Kontak] nvarchar(100) NOT NULL,
        [Keterangan] nvarchar(150) NULL,
        [No_HP] varchar(20) NULL,
        [Email] varchar(150) NULL,
        [Jam_Layanan] nvarchar(100) NULL,
        [Urutan_Tampil] int NOT NULL CONSTRAINT [DF_HRIS_Kontak_Bantuan_Urutan] DEFAULT ((0)),
        [Flag_Aktif] char(1) NOT NULL CONSTRAINT [DF_HRIS_Kontak_Bantuan_Aktif] DEFAULT ('Y'),
        [Created_At] datetime2(7) NOT NULL CONSTRAINT [DF_HRIS_Kontak_Bantuan_Created] DEFAULT (sysdatetime()),
        [Created_By] varchar(100) NULL,
        [Updated_At] datetime2(7) NULL,
        [Updated_By] varchar(100) NULL,
        CONSTRAINT [PK_HRIS_Kontak_Bantuan] PRIMARY KEY CLUSTERED ([Id_Kontak_Bantuan]),
        CONSTRAINT [CK_HRIS_Kontak_Bantuan_Aktif] CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y'),
        CONSTRAINT [CK_HRIS_Kontak_Bantuan_Kontak] CHECK ([No_HP] IS NOT NULL OR [Email] IS NOT NULL)
    );
END;

/* 1.6 dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi  (asal: DDL-2026-138) */
IF OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Koreksi] (
        [Id_Permit_Koreksi] int IDENTITY(1, 1) NOT NULL,
        [No_Transaksi] varchar(50) NOT NULL,
        [Kelompok_Koreksi] uniqueidentifier NOT NULL,
        [Nama_Field] varchar(50) NOT NULL,
        [Nilai_Lama] nvarchar(500) NULL,
        [Nilai_Baru] nvarchar(500) NULL,
        [Alasan] nvarchar(500) NOT NULL,
        [Created_At] datetime2(7) NOT NULL CONSTRAINT [DF_VISITOR_Permit_Koreksi_Created_At] DEFAULT (sysdatetime()),
        [Created_By] varchar(100) NOT NULL,
        CONSTRAINT [PK_VISITOR_Permit_Koreksi] PRIMARY KEY CLUSTERED ([Id_Permit_Koreksi])
    );
END;

/* 1.7 Foreign key — setelah SEMUA tabel ada: Panduan <-> Panduan_Versi saling merujuk. */
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Versi_Aktif', N'F') IS NULL
    ALTER TABLE dbo.[N_HRIS_Panduan] ADD CONSTRAINT [FK_HRIS_Panduan_Versi_Aktif] FOREIGN KEY ([Id_Versi_Aktif]) REFERENCES dbo.[N_HRIS_Panduan_Versi] ([Id_Versi]);
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Versi_Panduan', N'F') IS NULL
    ALTER TABLE dbo.[N_HRIS_Panduan_Versi] ADD CONSTRAINT [FK_HRIS_Panduan_Versi_Panduan] FOREIGN KEY ([Id_Panduan]) REFERENCES dbo.[N_HRIS_Panduan] ([Id_Panduan]);
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Halaman_Panduan', N'F') IS NULL
    ALTER TABLE dbo.[N_HRIS_Panduan_Halaman] ADD CONSTRAINT [FK_HRIS_Panduan_Halaman_Panduan] FOREIGN KEY ([Id_Panduan]) REFERENCES dbo.[N_HRIS_Panduan] ([Id_Panduan]);
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Role_Panduan', N'F') IS NULL
    ALTER TABLE dbo.[N_HRIS_Panduan_Role] ADD CONSTRAINT [FK_HRIS_Panduan_Role_Panduan] FOREIGN KEY ([Id_Panduan]) REFERENCES dbo.[N_HRIS_Panduan] ([Id_Panduan]);

/* ============ BAGIAN 2 — KOLOM BARU DI TABEL YANG SUDAH ADA ============ */

/* 2.1 dbo.HRIS_Hak_Cuti_Tambahan.Status — asal: DDL-2026-092 */
IF COL_LENGTH(N'dbo.HRIS_Hak_Cuti_Tambahan', N'Status') IS NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] ADD [Status] char(1) NULL;

/* 2.2 dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail.Nama_Penanggung_Jawab — asal: DDL-2026-135 */
IF COL_LENGTH(N'dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', N'Nama_Penanggung_Jawab') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail] ADD [Nama_Penanggung_Jawab] varchar(150) NULL;

/* 2.3 dbo.N_VISITOR_Permit_Efektif.Jenis_Akses — asal: DDL-2026-132 */
IF COL_LENGTH(N'dbo.N_VISITOR_Permit_Efektif', N'Jenis_Akses') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] ADD [Jenis_Akses] varchar(20) NOT NULL CONSTRAINT [DF_VISITOR_Permit_Efektif_Jenis_Akses] DEFAULT ('PERMIT');

/* 2.4 dbo.N_VISITOR_Permit_Efektif.NFC_Inbio — asal: DDL-2026-153 */
IF COL_LENGTH(N'dbo.N_VISITOR_Permit_Efektif', N'NFC_Inbio') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] ADD [NFC_Inbio] varchar(30) NULL;

/* 2.5 dbo.N_VISITOR_Tamu_Efektif.Flag_ByPass_CheckIn — TANPA artifact DDL; dipakai VisitorKehadiranQueryService sejak 476693b3 (23 Sep 2026) -> kueri kehadiran gagal di production sampai kolom ini ada. */
IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'Flag_ByPass_CheckIn') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] ADD [Flag_ByPass_CheckIn] char(1) NULL CONSTRAINT [DF_VISITOR_Tamu_Efektif_Flag_ByPass_CheckIn] DEFAULT ('T');

/* 2.6 dbo.N_VISITOR_Tamu_Efektif.User_Bypass_CheckIn — TANPA artifact DDL; dipakai VisitorKehadiranQueryService sejak 476693b3 (23 Sep 2026) -> kueri kehadiran gagal di production sampai kolom ini ada. */
IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'User_Bypass_CheckIn') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] ADD [User_Bypass_CheckIn] varchar(100) NULL;

/* 2.7 dbo.N_VISITOR_Tamu_Efektif.Flag_ByPass_CheckOut — TANPA artifact DDL; dipakai VisitorKehadiranQueryService sejak 476693b3 (23 Sep 2026) -> kueri kehadiran gagal di production sampai kolom ini ada. */
IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'Flag_ByPass_CheckOut') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] ADD [Flag_ByPass_CheckOut] char(1) NULL CONSTRAINT [DF_VISITOR_Tamu_Efektif_Flag_ByPass_CheckOut] DEFAULT ('T');

/* 2.8 dbo.N_VISITOR_Tamu_Efektif.User_Bypass_CheckOut — TANPA artifact DDL; dipakai VisitorKehadiranQueryService sejak 476693b3 (23 Sep 2026) -> kueri kehadiran gagal di production sampai kolom ini ada. */
IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'User_Bypass_CheckOut') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] ADD [User_Bypass_CheckOut] varchar(100) NULL;

COMMIT TRANSACTION;
