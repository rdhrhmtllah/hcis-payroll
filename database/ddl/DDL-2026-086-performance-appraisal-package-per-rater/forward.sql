/* Change ID: DDL-2026-086-performance-appraisal-package-per-rater
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-073 (N_PA_Cycle_Question_Package), DDL-2026-075 (N_PA_Rater_Category).
   Scope: satu kolom dan satu indeks pada N_PA_Cycle_Question_Package.

   MASALAH YANG DISELESAIKAN

   Paket soal sampai sekarang terkunci pada pasangan (siklus, level). Satu level berarti satu
   susunan soal, dan siapa pun yang menilai membaca susunan yang sama.

   HC menegaskan itu bukan yang mereka mau: soal berbeda per (level x kategori penilai).
   Manager yang menilai DIRI SENDIRI membaca susunan lain dari yang dibaca ATASAN-nya, dan
   lain lagi dari yang dibaca BAWAHAN dan PEER. Tanpa kolom ini, kebutuhan itu tidak bisa
   diwakili sama sekali.

   KENAPA NULLABLE, DAN APA ARTINYA NULL

   NULL berarti "berlaku untuk SEMUA kategori penilai" — paket bawaan level itu.

   Ini bukan kompromi malas. Tiga paket yang sudah berjalan di dev lahir sebelum kolom ini
   ada, dan memaksa mereka memilih satu kategori berarti menebak keputusan yang tidak pernah
   dibuat siapa pun. Dengan NULL, keduanya bisa hidup berdampingan: level yang butuh
   pembedaan mengisi baris per kategori, level yang tidak butuh cukup satu baris NULL.

   URUTAN PENCARIANNYA MENGIKAT: cocokkan kategori TEPAT lebih dulu, baru jatuh ke NULL.
   Dibalik, paket bawaan akan selalu menang dan paket khusus kategori tidak pernah terpakai
   walau sudah dibuat — tanpa satu pun pesan.

   KENAPA INDEKS UNIKNYA DIGANTI, BUKAN DITAMBAH

   `UX_PA_Cycle_Question_Package_Aktif` mengunci (siklus, level) dan HARUS dibuang: selama ia
   berdiri, level kedua tidak akan pernah bisa punya paket kategori kedua. SQL Server
   memperlakukan NULL sebagai nilai yang sama di indeks unik, sehingga baris bawaan tetap
   dijamin tunggal per (siklus, level).
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50460, 'DDL-2026-086 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle_Question_Package', 'U') IS NULL
    THROW 50461, 'DDL-2026-086 membutuhkan tabel N_PA_Cycle_Question_Package.', 1;

IF OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
    THROW 50462, 'DDL-2026-086 membutuhkan tabel N_PA_Rater_Category.', 1;
GO

/* ------------------------------------------------------------------ *
 * 1. Kolom kategori penilai                                           *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package') AND name = 'PA_Rater_Category_Id'
)
BEGIN
    ALTER TABLE N_PA_Cycle_Question_Package ADD PA_Rater_Category_Id int NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_CycleQPkg_RaterCategory'
)
BEGIN
    ALTER TABLE N_PA_Cycle_Question_Package
        ADD CONSTRAINT FK_PA_CycleQPkg_RaterCategory
        FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category);
END;
GO

/* ------------------------------------------------------------------ *
 * 2. Indeks unik lama dibuang, yang baru memuat kategorinya           *
 * ------------------------------------------------------------------ *
 * Dibuang LEBIH DULU. Membuat yang baru sementara yang lama masih berdiri tidak menolong:
 * yang lama tetap menolak baris kategori kedua, dan kegagalannya terbaca seperti bug pada
 * kode yang baru ditulis.                                                                */

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package')
      AND name = 'UX_PA_Cycle_Question_Package_Aktif'
)
BEGIN
    DROP INDEX UX_PA_Cycle_Question_Package_Aktif ON N_PA_Cycle_Question_Package;
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package')
      AND name = 'UX_PA_CycleQPkg_Cycle_Level_Rater'
)
BEGIN
    /* Tersaring pada `Flag_Aktif = 'Y'`: paket yang sudah dinonaktifkan boleh berulang,
       sehingga satu pasangan (level, kategori) bisa berganti paket dan riwayat keduanya
       tetap terbaca. */
    CREATE UNIQUE INDEX UX_PA_CycleQPkg_Cycle_Level_Rater
        ON N_PA_Cycle_Question_Package (PA_Cycle_Id, ID_Level, PA_Rater_Category_Id)
        WHERE Flag_Aktif = 'Y';
END;
GO

/* Jalur baca utamanya: "paket mana untuk level dan kategori ini pada siklus ini".
   Dipanggil sekali per penugasan saat formulir dibentuk. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package')
      AND name = 'IX_PA_CycleQPkg_Cycle_Aktif'
)
BEGIN
    CREATE INDEX IX_PA_CycleQPkg_Cycle_Aktif
        ON N_PA_Cycle_Question_Package (PA_Cycle_Id, Flag_Aktif)
        INCLUDE (ID_Level, PA_Rater_Category_Id, Kode_Paket_Sumber, Nama_Paket_Sumber);
END;
GO
