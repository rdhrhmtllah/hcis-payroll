-- database/ddl/DDL-2026-094-jejak-bukti-api-pihak-ketiga/forward.sql
--
-- Melengkapi N_LMS_Api_Logs supaya bisa dipakai sebagai BUKTI, bukan sekadar
-- penanda bahwa ada yang mengetuk.
--
-- MASALAH YANG DISELESAIKAN
--   Tabel ini hidup di production (1.639 baris, 17 Apr - 10 Sep 2026, seluruhnya
--   dari konsumen HCLEARN ke /api/v1/lms/third-party/exam/complete) tetapi hanya
--   menyimpan lima hal: aplikasi, endpoint, status, keabsahan tanda tangan, dan
--   waktu. Tidak ada method, payload, isi respons, durasi, maupun id korelasi.
--
--   Lebih menentukan lagi: angka 200 yang tercatat ditulis SEBELUM permintaan
--   diteruskan ke controller, jadi ia berarti "lolos gerbang keamanan", bukan
--   "HCIS membalas 200". Kalau controller-nya kemudian gagal, lognya tetap 200.
--   Sebaran di production membuktikannya: 1.623 x 200, 7 x 422, 5 x 403, 4 x 404,
--   NOL 5xx — karena kegagalan di dalam aplikasi memang tak pernah bisa sampai
--   ke tabel ini.
--
--   Akibatnya saat terjadi sengketa "kami sudah hit tapi tidak masuk", log ini
--   hanya bisa membuktikan permintaannya sampai dan tanda tangannya sah. Apa
--   yang HCIS lakukan sesudahnya tidak terekam di mana pun.
--
-- KOLOM YANG DITAMBAHKAN
--   Http_Method       metode HTTP; tabel ini sebelumnya tidak menyimpannya sama sekali
--   Correlation_Id    id yang juga dikembalikan di header respons, sehingga kedua
--                     pihak bisa menyebut satu baris yang sama saat berselisih
--   Modul             LMS / SOP setelah token berhasil dikenali; NULL bila
--                     permintaan ditolak sebelum asal token bisa ditentukan
--   Durasi_Ms         lama pemrosesan, untuk memisahkan "lambat" dari "gagal"
--   Sumber_Error      GERBANG (ditolak pemeriksaan keamanan) atau APLIKASI
--                     (sudah masuk controller), supaya jelas siapa yang menolak
--   Payload_Hash      SHA-256 body mentah; bukti kedua pihak membahas payload sama
--                     tanpa menyimpan token/kredensial mentah
--   Request_Ringkas   ringkasan body yang SUDAH DIREDaksi dan dipotong
--   Response_Ringkas  ringkasan body respons yang SUDAH DIREDaksi dan dipotong
--   Error_Message     pesan galat bila ada
--
--   Penamaannya sengaja mengikuti N_HRIS_Api_Log supaya kedua registry bisa
--   dibaca berdampingan tanpa menerjemahkan istilah.
--
-- YANG TIDAK DILAKUKAN
--   Tidak menyimpan header, tanda tangan, kunci, maupun body mentah. Yang masuk
--   ke Request_Ringkas sudah diredaksi di sisi aplikasi.
--
-- SIFAT BERKAS INI
--   Idempoten dan MENAMBAH saja. Semua kolom nullable tanpa DEFAULT: 1.639 baris
--   lama tetap apa adanya, dan NULL di sana jujur berarti "tidak pernah direkam",
--   bukan nol atau kosong.

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Http_Method') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Http_Method] varchar(10) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Correlation_Id') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Correlation_Id] varchar(64) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Modul') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Modul] varchar(10) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Durasi_Ms') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Durasi_Ms] int NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Sumber_Error') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Sumber_Error] varchar(20) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Payload_Hash') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Payload_Hash] varchar(64) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Request_Ringkas') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Request_Ringkas] nvarchar(1000) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Response_Ringkas') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Response_Ringkas] nvarchar(2000) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Error_Message') IS NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] ADD [Error_Message] nvarchar(1000) NULL;
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Modul') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                    WHERE name = N'CK_N_LMS_Api_Logs_Modul'
                      AND parent_object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_LMS_Api_Logs]
                    WHERE [Modul] IS NOT NULL AND [Modul] NOT IN ('LMS', 'SOP'))
    ALTER TABLE dbo.[N_LMS_Api_Logs] WITH CHECK
        ADD CONSTRAINT [CK_N_LMS_Api_Logs_Modul]
        CHECK ([Modul] IS NULL OR [Modul] IN ('LMS', 'SOP'));
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Sumber_Error') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                    WHERE name = N'CK_N_LMS_Api_Logs_Sumber_Error'
                      AND parent_object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_LMS_Api_Logs]
                    WHERE [Sumber_Error] IS NOT NULL
                      AND [Sumber_Error] NOT IN ('GERBANG', 'APLIKASI'))
    ALTER TABLE dbo.[N_LMS_Api_Logs] WITH CHECK
        ADD CONSTRAINT [CK_N_LMS_Api_Logs_Sumber_Error]
        CHECK ([Sumber_Error] IS NULL OR [Sumber_Error] IN ('GERBANG', 'APLIKASI'));
GO

/* Pencarian saat sengketa selalu berbentuk "cari baris dengan id ini".
   Difilter agar indeksnya hanya memuat baris yang benar-benar punya id.
   Tidak UNIQUE: correlation ID adalah UUID yang tabrakan praktis mustahil,
   dan memaksanya unik membutuhkan DROP INDEX — yang dilarang dieksekusi
   agent dan tidak sebanding dengan proteksinya. */
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Correlation_Id') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE name = N'IX_N_LMS_Api_Logs_Correlation_Id'
                      AND object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
    CREATE NONCLUSTERED INDEX [IX_N_LMS_Api_Logs_Correlation_Id]
        ON dbo.[N_LMS_Api_Logs] ([Correlation_Id])
        WHERE [Correlation_Id] IS NOT NULL;
GO

/* Penelusuran harian per konsumen: "apa saja yang HCLEARN kirim kemarin". */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = N'IX_N_LMS_Api_Logs_Aplikasi_Waktu'
                  AND object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
    CREATE NONCLUSTERED INDEX [IX_N_LMS_Api_Logs_Aplikasi_Waktu]
        ON dbo.[N_LMS_Api_Logs] ([Id_Api_Aplikasi], [Created_At])
        INCLUDE ([Endpoint], [Status_Code], [Signature_Valid]);
GO

/* selesai */
