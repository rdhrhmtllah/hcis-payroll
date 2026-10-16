-- Change ID: DDL-2026-148-system-panduan-sasaran-role
-- Pembatas ROLE opsional per panduan (lanjutan DDL-2026-146).
--
-- Panduan WAJIB dipasang di minimal satu halaman (N_HRIS_Panduan_Halaman).
-- Tabel ini opsional membatasi siapa yang melihatnya di halaman-halaman itu:
--   tanpa baris  -> semua yang bisa membuka halamannya;
--   ada baris    -> hanya pemegang SALAH SATU role di sini (N_HRIS_Role_User aktif).
-- Halaman_Mulai tidak dipakai (halaman mulai diatur per halaman); tetap NULL.
-- Tabel terpisah (bukan baris halaman x role) supaya satu pilihan role berlaku
-- untuk semua halaman panduan tanpa menggandakan pemetaan halaman.

IF OBJECT_ID('dbo.N_HRIS_Panduan_Role', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Panduan_Role (
        Id_Panduan_Role int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_HRIS_Panduan_Role PRIMARY KEY,
        Id_Panduan    int NOT NULL
            CONSTRAINT FK_HRIS_Panduan_Role_Panduan REFERENCES dbo.N_HRIS_Panduan (Id_Panduan),
        Id_Role       int NOT NULL,                 -- = N_HRIS_Role_Functional.Id_Role
        Halaman_Mulai int NULL,                     -- tidak dipakai (halaman mulai diatur per halaman)
        Urutan_Tampil int NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Role_Urutan DEFAULT (0),
        Flag_Aktif    char(1) NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Role_Aktif DEFAULT ('Y'),
        Created_At datetime2 NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Role_Created DEFAULT (sysdatetime()),
        Created_By varchar(100) NULL,
        Updated_At datetime2    NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT CK_HRIS_Panduan_Role_Aktif CHECK (Flag_Aktif IN ('Y', 'T')),
        CONSTRAINT CK_HRIS_Panduan_Role_Mulai CHECK (Halaman_Mulai IS NULL OR Halaman_Mulai >= 1)
    );
END;
GO

-- Pencarian pembatas per role
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Panduan_Role_Role')
BEGIN
    CREATE INDEX IX_HRIS_Panduan_Role_Role
        ON dbo.N_HRIS_Panduan_Role (Id_Role, Flag_Aktif, Urutan_Tampil)
        INCLUDE (Id_Panduan, Halaman_Mulai);
END;
GO

-- Satu panduan tidak mencantumkan role yang sama dua kali.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_HRIS_Panduan_Role_Unik')
BEGIN
    CREATE UNIQUE INDEX UX_HRIS_Panduan_Role_Unik
        ON dbo.N_HRIS_Panduan_Role (Id_Panduan, Id_Role);
END;
GO
