/* Change ID: DDL-2026-162-sinkron-prod-tabel-kolom

   Tabel dan kolom yang ADA di development (Web_HRIS) tetapi TIDAK ADA di
   production (emi_db_web_hr), diukur 2026-10-06 dari katalog kedua database
   (baca saja). "Tidak ada" dibuktikan dengan pesan galat ('Invalid object
   name'), bukan katalog saja: akun audit tidak melihat tabel tanpa GRANT.

   Hanya objek yang DIPAKAI aplikasi ini. Hasil: satu tabel,
   N_SOP_Item_Versi_Lampiran — definisinya sama dengan DDL-2026-158 (masih
   PENDING di production); cukup jalankan salah satu, keduanya idempoten.
   Tidak ada kolom yang dipakai kode dan hilang di production.

   Definisi dirakit dari katalog development, sama persis dengan dev.
   Jalankan SEBELUM DDL-2026-163 (index tabel ini ada di sana). */

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* 1. dbo.N_SOP_Item_Versi_Lampiran  (asal: DDL-2026-158) */
IF OBJECT_ID(N'dbo.N_SOP_Item_Versi_Lampiran', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Item_Versi_Lampiran] (
        [Id_Lampiran] int IDENTITY(1, 1) NOT NULL,
        [Id_SOP_Item_Versi] int NOT NULL,
        [Id_Master_Konten] int NOT NULL,
        [Urutan] int NOT NULL CONSTRAINT [DF_SOP_Versi_Lampiran_Urutan] DEFAULT (0),
        [Flag_Aktif] char(1) NOT NULL CONSTRAINT [DF_SOP_Versi_Lampiran_Aktif] DEFAULT ('Y'),
        [Created_At] datetime2(0) NOT NULL CONSTRAINT [DF_SOP_Versi_Lampiran_Created] DEFAULT (sysdatetime()),
        [Created_By] int NULL,
        [Updated_At] datetime2(0) NULL,
        [Updated_By] int NULL,
        CONSTRAINT [PK_SOP_Item_Versi_Lampiran] PRIMARY KEY CLUSTERED ([Id_Lampiran]),
        CONSTRAINT [FK_SOP_Versi_Lampiran_Versi] FOREIGN KEY ([Id_SOP_Item_Versi])
            REFERENCES dbo.[N_SOP_Item_Versi] ([Id_SOP_Item_Versi]),
        CONSTRAINT [FK_SOP_Versi_Lampiran_Konten] FOREIGN KEY ([Id_Master_Konten])
            REFERENCES dbo.[N_LMS_Master_Konten] ([Id_Master_Konten])
    );
END;

COMMIT TRANSACTION;

/* VERIFIKASI — hasil HARUS nol baris. */
SELECT x.objek
FROM (VALUES
    (N'tabel N_SOP_Item_Versi_Lampiran', OBJECT_ID(N'dbo.N_SOP_Item_Versi_Lampiran', N'U')),
    (N'PK_SOP_Item_Versi_Lampiran', OBJECT_ID(N'dbo.PK_SOP_Item_Versi_Lampiran', N'PK')),
    (N'FK_SOP_Versi_Lampiran_Versi', OBJECT_ID(N'dbo.FK_SOP_Versi_Lampiran_Versi', N'F')),
    (N'FK_SOP_Versi_Lampiran_Konten', OBJECT_ID(N'dbo.FK_SOP_Versi_Lampiran_Konten', N'F'))
) x (objek, id)
WHERE x.id IS NULL;
