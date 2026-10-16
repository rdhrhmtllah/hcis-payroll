/* ============================================================================
   SOP 26A - DDL Master Dokumen SOP

   Tujuan:
   - N_SOP_Dokumen menjadi entitas dokumen terkendali (nomor resmi, judul,
     tipe, pointer versi aktif).
   - N_SOP_Item_Versi mendapat Id_SOP_Dokumen: kepemilikan versi berpindah dari
     item ke dokumen (pemindahan datanya di command sop:dokumen-backfill).
   - N_SOP_Paket_Item mendapat Id_SOP_Dokumen: item jadi RUJUKAN ke dokumen.

   FASE 1: kedua kolom rujukan NULLABLE dan belum dibaca kode mana pun.
   Kolom lama (Id_SOP_Paket_Item di versi) SENGAJA dibiarkan utuh — ia masih
   jadi sumber kebenaran sampai Fase 2 selesai. Tak ada data yang dihapus.

   Prasyarat: SOP 25A sudah dijalankan.
   Aman dijalankan berulang kali.
   ============================================================================ */

IF OBJECT_ID('dbo.N_SOP_Dokumen', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Dokumen (
        Id_SOP_Dokumen BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Dokumen PRIMARY KEY,

        Nomor_SOP_Resmi VARCHAR(100) NULL,
        Judul NVARCHAR(255) NOT NULL,
        Tipe VARCHAR(30) NOT NULL,          -- DOKUMEN | FORM | VIDEO | KUIS
        Deskripsi NVARCHAR(MAX) NULL,

        /* Versi yang sedang berlaku. SENGAJA TANPA FOREIGN KEY: relasinya
           melingkar (dokumen -> versi -> dokumen) dan akan mengunci urutan
           insert. Konsistensinya dijaga kode + command sop:dokumen-verify,
           sama seperti N_SOP_Paket_Item.Id_Item_Versi_Aktif hari ini. */
        Id_Versi_Aktif BIGINT NULL,

        Flag_Aktif CHAR(1) NOT NULL DEFAULT ('Y'),
        Created_By INT NULL,
        Created_At DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
        Updated_By INT NULL,
        Updated_At DATETIME2(0) NULL,
        Row_Version ROWVERSION NOT NULL
    );
END
GO

/* Register dokumen disaring per tipe & status aktif. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Dokumen_Tipe' AND object_id = OBJECT_ID('dbo.N_SOP_Dokumen'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Dokumen_Tipe
        ON dbo.N_SOP_Dokumen (Flag_Aktif, Tipe) INCLUDE (Judul, Nomor_SOP_Resmi, Id_Versi_Aktif);
END
GO

/* ---- Kolom rujukan: versi milik dokumen ---- */
IF COL_LENGTH('dbo.N_SOP_Item_Versi', 'Id_SOP_Dokumen') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Item_Versi ADD Id_SOP_Dokumen BIGINT NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Item_Versi_Dokumen')
BEGIN
    ALTER TABLE dbo.N_SOP_Item_Versi WITH CHECK
        ADD CONSTRAINT FK_N_SOP_Item_Versi_Dokumen
        FOREIGN KEY (Id_SOP_Dokumen) REFERENCES dbo.N_SOP_Dokumen (Id_SOP_Dokumen);
END
GO

/* Non-unique di Fase 1. Unique (Id_SOP_Dokumen, Versi_Ke) di-pasang pada Fase 2
   bersamaan dengan pencabutan UX_N_SOP_Item_Versi_Urut (Id_SOP_Paket_Item,
   Versi_Ke) — memasangnya sekarang akan menggagalkan 26A bila ada data tak
   terduga, padahal Fase 1 harus bisa dijalankan tanpa risiko. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Item_Versi_Dokumen' AND object_id = OBJECT_ID('dbo.N_SOP_Item_Versi'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Item_Versi_Dokumen
        ON dbo.N_SOP_Item_Versi (Id_SOP_Dokumen, Versi_Ke);
END
GO

/* ---- Kolom rujukan: item menunjuk dokumen ---- */
IF COL_LENGTH('dbo.N_SOP_Paket_Item', 'Id_SOP_Dokumen') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Item ADD Id_SOP_Dokumen BIGINT NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Paket_Item_Dokumen')
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Item WITH CHECK
        ADD CONSTRAINT FK_N_SOP_Paket_Item_Dokumen
        FOREIGN KEY (Id_SOP_Dokumen) REFERENCES dbo.N_SOP_Dokumen (Id_SOP_Dokumen);
END
GO

/* "Paket mana saja yang memakai dokumen ini" — pertanyaan inti halaman Master
   Dokumen (Fase 5) dan penentu fan-out saat publish (Fase 3). */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Paket_Item_Dokumen' AND object_id = OBJECT_ID('dbo.N_SOP_Paket_Item'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Paket_Item_Dokumen
        ON dbo.N_SOP_Paket_Item (Id_SOP_Dokumen, Flag_Aktif) INCLUDE (Id_SOP_Paket, Id_Item_Versi_Aktif);
END
GO

/* =============================================================================
   ROLLBACK (bila diperlukan)
   =============================================================================
   Fase 1 SEPENUHNYA ADITIF dan bisa dibalik bersih: ia hanya MENAMBAH satu tabel,
   dua kolom nullable, dua FK, dan tiga index. Tak satu pun kolom lama diubah,
   dipindah, atau dihapus — Id_SOP_Paket_Item di N_SOP_Item_Versi tetap jadi
   sumber kebenaran sampai Fase 2 selesai, dan tak ada kode yang membaca kolom
   baru. Karena itu menjalankan blok di bawah mengembalikan skema PERSIS ke
   keadaan sebelum 26A, tanpa kehilangan satu baris data pun milik SOP.

   Yang HILANG hanyalah hasil sop:dokumen-backfill (isi N_SOP_Dokumen dan kedua
   kolom rujukan) — dan itu bisa dibuat ulang kapan saja dengan menjalankan
   command-nya lagi, karena ia turunan murni dari data yang sudah ada.

   Urutan WAJIB begini: FK dulu (kolom tak bisa di-drop selagi jadi kaki FK),
   lalu index (index tak bisa di-drop setelah kolomnya hilang), lalu kolom, lalu
   tabelnya (tabel tak bisa di-drop selagi masih ada FK yang menunjuknya).

   -- 1. FK
   ALTER TABLE dbo.N_SOP_Paket_Item  DROP CONSTRAINT FK_N_SOP_Paket_Item_Dokumen;
   ALTER TABLE dbo.N_SOP_Item_Versi  DROP CONSTRAINT FK_N_SOP_Item_Versi_Dokumen;

   -- 2. Index pada kolom rujukan
   DROP INDEX IX_N_SOP_Paket_Item_Dokumen ON dbo.N_SOP_Paket_Item;
   DROP INDEX IX_N_SOP_Item_Versi_Dokumen ON dbo.N_SOP_Item_Versi;

   -- 3. Kolom rujukan
   ALTER TABLE dbo.N_SOP_Paket_Item  DROP COLUMN Id_SOP_Dokumen;
   ALTER TABLE dbo.N_SOP_Item_Versi  DROP COLUMN Id_SOP_Dokumen;

   -- 4. Index & tabel dokumen
   DROP INDEX IX_N_SOP_Dokumen_Tipe ON dbo.N_SOP_Dokumen;
   DROP TABLE dbo.N_SOP_Dokumen;
   ============================================================================= */
