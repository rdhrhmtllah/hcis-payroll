-- Change ID: DDL-2026-146-system-panduan-manual
-- Manajemen panduan (manual guide) lintas modul, milik SYSTEM.
--
-- N_HRIS_Panduan          : satu dokumen PDF panduan (disimpan di GCS).
-- N_HRIS_Panduan_Halaman  : pemetaan panduan -> halaman (Jenis_page), dengan
--                           halaman mulai di PDF dan role khusus opsional.
--
-- Siapa melihat panduan apa: pengguna yang BISA MEMBUKA halamannya (hak akses
-- halaman yang sudah ada). Id_Role opsional mempersempitnya ke satu role bila
-- satu halaman dipakai beberapa jenis pengguna. Modul & fitur tidak disimpan:
-- keduanya ikut dari Jenis_page (N_HRIS_Grup_Fitur_Map / N_HRIS_Menus).

IF OBJECT_ID('dbo.N_HRIS_Panduan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Panduan (
        Id_Panduan int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_HRIS_Panduan PRIMARY KEY,
        Judul          nvarchar(150) NOT NULL,
        Deskripsi      nvarchar(500) NULL,
        Versi          nvarchar(30)  NULL,          -- mis. "v2 - Sep 2026"
        Path_File      varchar(300)  NOT NULL,      -- path di disk GCS
        Nama_File_Asli nvarchar(255) NULL,
        Ukuran_Byte    bigint        NULL,
        Flag_Aktif     char(1)       NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Created DEFAULT (sysdatetime()),
        Created_By varchar(100) NULL,
        Updated_At datetime2    NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT CK_HRIS_Panduan_Aktif CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;
GO

IF OBJECT_ID('dbo.N_HRIS_Panduan_Halaman', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Panduan_Halaman (
        Id_Panduan_Halaman int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_HRIS_Panduan_Halaman PRIMARY KEY,
        Id_Panduan    int          NOT NULL
            CONSTRAINT FK_HRIS_Panduan_Halaman_Panduan REFERENCES dbo.N_HRIS_Panduan (Id_Panduan),
        Jenis_page    varchar(100) NOT NULL,        -- = N_HRIS_Menus.Jenis_page
        Halaman_Mulai int          NULL,            -- halaman PDF yang dibuka; NULL = halaman 1
        Id_Role       int          NULL,            -- NULL = semua yang bisa membuka halaman
        Urutan_Tampil int          NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Halaman_Urutan DEFAULT (0),
        Flag_Aktif    char(1)      NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Halaman_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Halaman_Created DEFAULT (sysdatetime()),
        Created_By varchar(100) NULL,
        Updated_At datetime2    NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT CK_HRIS_Panduan_Halaman_Aktif CHECK (Flag_Aktif IN ('Y', 'T')),
        CONSTRAINT CK_HRIS_Panduan_Halaman_Mulai CHECK (Halaman_Mulai IS NULL OR Halaman_Mulai >= 1)
    );
END;
GO

-- Pencarian utama: "panduan aktif untuk halaman X"
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Panduan_Halaman_Jenis_Page')
BEGIN
    CREATE INDEX IX_HRIS_Panduan_Halaman_Jenis_Page
        ON dbo.N_HRIS_Panduan_Halaman (Jenis_page, Flag_Aktif, Urutan_Tampil)
        INCLUDE (Id_Panduan, Halaman_Mulai, Id_Role);
END;
GO

-- Satu panduan tidak dipetakan dua kali ke halaman + role yang sama.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_HRIS_Panduan_Halaman_Unik')
BEGIN
    CREATE UNIQUE INDEX UX_HRIS_Panduan_Halaman_Unik
        ON dbo.N_HRIS_Panduan_Halaman (Id_Panduan, Jenis_page, Id_Role);
END;
GO
