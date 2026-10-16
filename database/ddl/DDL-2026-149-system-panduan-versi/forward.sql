-- Change ID: DDL-2026-149-system-panduan-versi
-- Versioning berkas panduan (lanjutan DDL-2026-146).
--
-- N_HRIS_Panduan_Versi : satu baris per unggahan berkas. Berkas lama TIDAK
--                        dihapus, supaya versi lama bisa dilihat dan diaktifkan
--                        lagi (rollback).
--   Status DRAF   : diunggah, belum pernah terbit, hanya admin yang bisa melihat.
--   Status TERBIT : pernah terbit. Yang tampil ke pengguna hanya versi yang
--                   ditunjuk N_HRIS_Panduan.Id_Versi_Aktif.
--
-- N_HRIS_Panduan.Path_File / Nama_File_Asli / Ukuran_Byte / Versi tetap
-- mencerminkan versi aktif, supaya penyajian berkas ke pengguna tidak berubah.
-- Versi_Aktif_Sejak = kapan versi aktif mulai berlaku (terbit atau rollback),
-- dipakai tanda "Diperbarui" di Pusat Bantuan.

IF OBJECT_ID('dbo.N_HRIS_Panduan_Versi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Panduan_Versi (
        Id_Versi          int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_HRIS_Panduan_Versi PRIMARY KEY,
        Id_Panduan        int NOT NULL
            CONSTRAINT FK_HRIS_Panduan_Versi_Panduan REFERENCES dbo.N_HRIS_Panduan (Id_Panduan),
        No_Versi          int NOT NULL,                -- 1, 2, 3, ... per panduan
        Label_Versi       nvarchar(30)   NULL,         -- mis. "v3 - Okt 2026"
        Catatan_Perubahan nvarchar(1000) NULL,         -- tampil ke pengguna saat baru diperbarui
        Path_File         varchar(300)   NOT NULL,     -- path di disk GCS
        Nama_File_Asli    nvarchar(255)  NULL,
        Ukuran_Byte       bigint         NULL,
        Status            varchar(10)    NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Versi_Status DEFAULT ('DRAF'),
        Diterbitkan_At    datetime2      NULL,         -- pertama kali terbit
        Diterbitkan_By    varchar(100)   NULL,
        Created_At        datetime2      NOT NULL
            CONSTRAINT DF_HRIS_Panduan_Versi_Created DEFAULT (sysdatetime()),
        Created_By        varchar(100)   NULL,
        CONSTRAINT CK_HRIS_Panduan_Versi_Status CHECK (Status IN ('DRAF', 'TERBIT')),
        CONSTRAINT CK_HRIS_Panduan_Versi_No CHECK (No_Versi >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_HRIS_Panduan_Versi_No')
BEGIN
    CREATE UNIQUE INDEX UX_HRIS_Panduan_Versi_No
        ON dbo.N_HRIS_Panduan_Versi (Id_Panduan, No_Versi);
END;
GO

IF COL_LENGTH('dbo.N_HRIS_Panduan', 'Id_Versi_Aktif') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Panduan ADD Id_Versi_Aktif int NULL;
END;
GO

IF COL_LENGTH('dbo.N_HRIS_Panduan', 'Versi_Aktif_Sejak') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Panduan ADD Versi_Aktif_Sejak datetime2 NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_HRIS_Panduan_Versi_Aktif')
BEGIN
    ALTER TABLE dbo.N_HRIS_Panduan
        ADD CONSTRAINT FK_HRIS_Panduan_Versi_Aktif
        FOREIGN KEY (Id_Versi_Aktif) REFERENCES dbo.N_HRIS_Panduan_Versi (Id_Versi);
END;
GO
