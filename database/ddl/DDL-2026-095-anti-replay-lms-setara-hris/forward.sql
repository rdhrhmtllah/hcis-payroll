-- database/ddl/DDL-2026-095-anti-replay-lms-setara-hris/forward.sql
--
-- Menyetarakan jaminan anti-replay registry LMS dengan kembarannya di HRIS.
--
-- MASALAH YANG DISELESAIKAN
--   Dua registry API pihak ketiga memakai pola gerbang yang sama: cek nonce
--   sudah pernah dipakai, lalu simpan nonce baru. Tetapi tabel penyimpannya
--   TIDAK setara. Dibaca dari katalog Web_HRIS (development) 10 September 2026:
--
--     N_HRIS_Api_Nonce   PK + UQ_N_HRIS_Api_Nonce (Nonce)  UNIK
--                        + IX_N_HRIS_Api_Nonce_Expired (Expired_At)
--                        + IX_N_HRIS_Api_Nonce_Aplikasi (Id_Api_Aplikasi, Expired_At)
--
--     N_LMS_Api_Nonces   PK saja. Tidak ada satu pun indeks pada Nonce.
--
--   Padahal justru N_LMS_Api_Nonces-lah yang dipakai lalu lintas nyata: seluruh
--   callback HCLEARN masuk lewat LmsApiGuard. Tiga akibatnya:
--
--   1. ANTI-REPLAY-NYA BISA DIBALAP. Gerbang memeriksa dengan SELECT lalu
--      menyisipkan dengan INSERT terpisah. Tanpa UNIQUE, dua permintaan
--      bernonce sama yang datang bersamaan sama-sama melihat "belum ada" lalu
--      sama-sama tersimpan — keduanya diproses. Di N_HRIS hal itu mustahil
--      karena database menolak INSERT keduanya.
--
--   2. PEMERIKSAANNYA MEMINDAI SELURUH TABEL, dan makin lama makin lambat
--      karena tabel ini hanya bertambah di antara dua kali prune.
--
--   3. PEMBERSIHNYA JUGA MEMINDAI SELURUH TABEL. hris:api-nonce-prune
--      menghapus WHERE Expired_At < now setiap jam; tanpa indeks pada
--      Expired_At ia harus membaca semuanya untuk menemukan yang kedaluwarsa.
--
--   Sekaligus: N_LMS_Api_Logs tidak punya CHECK pada Signature_Valid, sedangkan
--   N_HRIS_Api_Log punya CK_N_HRIS_Api_Log_Sig. Nilai salah ketik akan tersimpan
--   diam-diam di satu registry dan ditolak di registry lain — untuk kolom yang
--   dipakai sebagai bukti keabsahan, itu perbedaan yang tidak boleh dibiarkan.
--
-- SIFAT
--   Idempoten. Aman dijalankan berulang.
--   Tidak ada DROP, tidak ada DELETE, tidak ada perubahan data.
--
-- CATATAN PENTING TENTANG UNIQUE
--   Indeks UNIQUE hanya dibuat bila data yang ada memang sudah memenuhi syarat
--   (tidak ada Nonce kembar dan tidak ada Nonce NULL). Bila belum, skrip TIDAK
--   gagal: ia membuat indeks biasa supaya manfaat kecepatan tetap didapat, lalu
--   mencetak peringatan berisi jumlah pelanggarnya. Nonce kedaluwarsa boleh
--   dihapus lebih dulu dengan `php artisan hris:api-nonce-prune`, setelah itu
--   skrip ini dijalankan ulang untuk menaikkannya menjadi UNIQUE.
--
--   Alasan tidak menghapus sendiri: pembersihan data bukan pekerjaan berkas DDL,
--   dan sudah ada perintah terjadwal yang memang bertugas untuk itu.

SET NOCOUNT ON;
GO

/* ─────────────────────────────────────────────────────────────────────────────
   BAGIAN 1 — Indeks Expired_At: pembersih berhenti memindai seluruh tabel
   ───────────────────────────────────────────────────────────────────────────*/
IF OBJECT_ID('dbo.N_LMS_Api_Nonces', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.indexes
        WHERE name = N'IX_N_LMS_Api_Nonces_Expired'
          AND object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces')
   )
BEGIN
    CREATE NONCLUSTERED INDEX [IX_N_LMS_Api_Nonces_Expired]
        ON dbo.N_LMS_Api_Nonces (Expired_At);

    PRINT 'IX_N_LMS_Api_Nonces_Expired dibuat.';
END
ELSE
BEGIN
    PRINT 'IX_N_LMS_Api_Nonces_Expired sudah ada / tabel tidak ada — dilewati.';
END
GO

/* ─────────────────────────────────────────────────────────────────────────────
   BAGIAN 2 — Nonce: UNIQUE bila memungkinkan, indeks biasa bila belum
   ───────────────────────────────────────────────────────────────────────────*/
IF OBJECT_ID('dbo.N_LMS_Api_Nonces', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.indexes
        WHERE name IN (N'UQ_N_LMS_Api_Nonces_Nonce', N'IX_N_LMS_Api_Nonces_Nonce')
          AND object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces')
   )
BEGIN
    DECLARE @kembar int = (
        SELECT COUNT(*) FROM (
            SELECT Nonce FROM dbo.N_LMS_Api_Nonces
             WHERE Nonce IS NOT NULL
             GROUP BY Nonce HAVING COUNT(*) > 1
        ) d
    );
    DECLARE @kosong int = (SELECT COUNT(*) FROM dbo.N_LMS_Api_Nonces WHERE Nonce IS NULL);

    IF @kembar = 0 AND @kosong = 0
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX [UQ_N_LMS_Api_Nonces_Nonce]
            ON dbo.N_LMS_Api_Nonces (Nonce);

        PRINT 'UQ_N_LMS_Api_Nonces_Nonce dibuat — anti-replay kini dijamin database.';
    END
    ELSE
    BEGIN
        CREATE NONCLUSTERED INDEX [IX_N_LMS_Api_Nonces_Nonce]
            ON dbo.N_LMS_Api_Nonces (Nonce);

        PRINT 'PERINGATAN: UNIQUE dilewati karena data belum memenuhi syarat.';
        PRINT '  nonce kembar : ' + CAST(@kembar AS varchar(20));
        PRINT '  nonce NULL   : ' + CAST(@kosong AS varchar(20));
        PRINT '  Dibuat indeks biasa IX_N_LMS_Api_Nonces_Nonce sebagai gantinya.';
        PRINT '  Jalankan `php artisan hris:api-nonce-prune` lalu jalankan ulang skrip ini.';
    END
END
ELSE
BEGIN
    PRINT 'Indeks Nonce sudah ada / tabel tidak ada — dilewati.';
END
GO

/* ─────────────────────────────────────────────────────────────────────────────
   BAGIAN 3 — CHECK Signature_Valid pada N_LMS_Api_Logs, setara CK_N_HRIS_Api_Log_Sig
   ───────────────────────────────────────────────────────────────────────────*/
IF OBJECT_ID('dbo.N_LMS_Api_Logs', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.check_constraints
        WHERE name = N'CK_N_LMS_Api_Logs_Sig'
          AND parent_object_id = OBJECT_ID('dbo.N_LMS_Api_Logs')
   )
BEGIN
    DECLARE @langgar int = (
        SELECT COUNT(*) FROM dbo.N_LMS_Api_Logs
         WHERE Signature_Valid IS NOT NULL
           AND Signature_Valid NOT IN ('Y', 'T')
    );

    IF @langgar = 0
    BEGIN
        ALTER TABLE dbo.N_LMS_Api_Logs
            ADD CONSTRAINT [CK_N_LMS_Api_Logs_Sig]
            CHECK (Signature_Valid IS NULL OR Signature_Valid IN ('Y', 'T'));

        PRINT 'CK_N_LMS_Api_Logs_Sig dibuat.';
    END
    ELSE
    BEGIN
        -- Sengaja TIDAK memakai WITH NOCHECK: baris lama yang melanggar berarti
        -- ada penulis yang menyimpan nilai di luar kontrak, dan itu harus
        -- ditemukan lebih dulu, bukan disembunyikan di belakang constraint.
        PRINT 'PERINGATAN: CK_N_LMS_Api_Logs_Sig dilewati, ada '
              + CAST(@langgar AS varchar(20))
              + ' baris dengan Signature_Valid di luar {Y,T,NULL}.';
    END
END
ELSE
BEGIN
    PRINT 'CK_N_LMS_Api_Logs_Sig sudah ada / tabel tidak ada — dilewati.';
END
GO

/* ─────────────────────────────────────────────────────────────────────────────
   VERIFIKASI — jalankan setelah skrip di atas selesai
   ───────────────────────────────────────────────────────────────────────────*/
SELECT i.name AS indeks, i.is_unique,
       STUFF((SELECT ',' + c.name
                FROM sys.index_columns ic
                JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
               WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
               ORDER BY ic.key_ordinal FOR XML PATH('')), 1, 1, '') AS kunci
  FROM sys.indexes i
 WHERE i.object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces')
   AND i.name IS NOT NULL
 ORDER BY i.name;
GO

SELECT name, definition
  FROM sys.check_constraints
 WHERE parent_object_id = OBJECT_ID('dbo.N_LMS_Api_Logs')
 ORDER BY name;
GO
