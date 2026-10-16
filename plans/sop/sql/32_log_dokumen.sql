/* =====================================================================
   32 — Jejak audit bersumbu DOKUMEN

   Dijalankan MANUAL di SSMS.

   KEADAAN SEKARANG. N_SOP_Log punya tiga sumbu: Id_SOP_Paket,
   Id_SOP_Paket_Item, Id_SOP_Item_Versi. Ketiganya lahir ketika versi masih
   wajib menempel pada item di dalam sebuah paket.

   Sejak Fase 2 sumbu identitasnya adalah DOKUMEN: versi milik dokumen, dan
   satu dokumen dipakai banyak paket. Akibatnya dua hal:

     1. Peristiwa tingkat dokumen — ganti nomor SOP, ganti judul, hapus
        dokumen — tak punya kolom untuk digantungkan sama sekali. Ia bukan
        milik paket mana pun, dan belum tentu punya versi.

     2. Riwayat satu dokumen tak bisa ditanyakan langsung. Menyusunnya dari
        Id_SOP_Item_Versi berarti menempuh join ke versi lalu ke dokumen —
        non-sargable, dan tetap kehilangan peristiwa nomor 1.

   Kolom ini menutup keduanya. NULLABLE: seluruh baris lama tak punya
   dokumen dan memang tak boleh dikarang — jejak yang diisi belakangan
   dengan tebakan lebih buruk daripada jejak yang kosong.

   YANG TIDAK DILAKUKAN DDL INI. Tak ada backfill. Perubahan yang terjadi
   sebelum pencatatannya dipasang tidak pernah direkam di mana pun, jadi
   tak ada sumber untuk memulihkannya. Riwayat dimulai dari nol pada hari
   pencatatan dipasang, dan itu harus dikatakan apa adanya kepada admin
   ketimbang menyajikan tabel kosong yang tampak seperti "tak ada
   perubahan".
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'N_SOP_Log' AND COLUMN_NAME = 'Id_SOP_Dokumen')
BEGIN
    ALTER TABLE N_SOP_Log ADD Id_SOP_Dokumen INT NULL;
    PRINT '32: kolom Id_SOP_Dokumen ditambahkan ke N_SOP_Log.';
END
ELSE
BEGIN
    PRINT '32: kolom Id_SOP_Dokumen sudah ada — tidak ada perubahan.';
END
GO

/* Indeks TERFILTER, bukan polos. Mayoritas baris log tak bersumbu dokumen
   (absensi, assign, cabut akses), dan mengindeks NULL sebanyak itu hanya
   memperbesar indeks tanpa satu pun query yang memakainya.

   Created_At DESC ikut jadi kunci: layar riwayat selalu meminta yang
   terbaru lebih dulu, dan tanpa itu setiap pembukaan panel berujung sort.
   Pola yang sama dipakai IX_N_SOP_Log_Paket. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_N_SOP_Log_Dokumen' AND object_id = OBJECT_ID('N_SOP_Log'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Log_Dokumen
        ON N_SOP_Log (Id_SOP_Dokumen, Created_At DESC)
        INCLUDE (Aksi, Keterangan, Oleh_Id_Users, Id_SOP_Item_Versi)
        WHERE Id_SOP_Dokumen IS NOT NULL;
    PRINT '32: IX_N_SOP_Log_Dokumen dibuat (terfilter).';
END
ELSE
BEGIN
    PRINT '32: IX_N_SOP_Log_Dokumen sudah ada — tidak ada perubahan.';
END
GO

/* Pemeriksaan pasca-jalan. Sebelum pencatatannya dipasang, ber_dokumen
   WAJIB 0 — bukan tanda gagal, melainkan tanda tak ada yang dikarang. */
SELECT
    COUNT(*)                                                          AS total_log,
    SUM(CASE WHEN Id_SOP_Dokumen IS NOT NULL THEN 1 ELSE 0 END)       AS ber_dokumen
FROM N_SOP_Log;
GO

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila perubahan ini dibatalkan.

     DROP INDEX IX_N_SOP_Log_Dokumen ON N_SOP_Log;
     ALTER TABLE N_SOP_Log DROP COLUMN Id_SOP_Dokumen;

   PERINGATAN. Membuang kolomnya membuang jejak audit yang sudah terkumpul,
   dan jejak itu TIDAK bisa dibangun ulang dari data lain — nilai lama pada
   metadata dokumen hanya hidup di dalam baris log ini. Salin dulu bila
   masih mungkin dibutuhkan:

     SELECT * INTO N_SOP_Log_Arsip_32 FROM N_SOP_Log WHERE Id_SOP_Dokumen IS NOT NULL;
   --------------------------------------------------------------------- */
