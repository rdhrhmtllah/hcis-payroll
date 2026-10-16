/* =====================================================================
   28 — Versi menjadi milik DOKUMEN, bukan item

   Dijalankan MANUAL di SSMS.

   KENAPA SEKARANG. Konsep yang disepakati: Master SOP mengurus SOP UTUH —
   berkas, versi, dan pengesahan — lalu Paket SOP hanya wadah penugasan.
   Artinya admin harus bisa menerbitkan versi pada SOP yang BELUM dipakai
   paket mana pun.

   Hari ini itu mustahil: N_SOP_Item_Versi.Id_SOP_Paket_Item NOT NULL,
   sehingga versi tak bisa ada tanpa item. Kolom itu dijadikan nullable.

   INDEX LAMA HARUS DIBUANG, dan kali ini benar-benar wajib. Sudah dua kali
   pembuangannya ditunda dengan alasan "masih jadi pagar bagi baris
   ber-dokumen NULL" — alasan yang sah saat itu. Sekarang keadaannya
   terbalik: UX_N_SOP_Item_Versi_Urut adalah UNIQUE (Id_SOP_Paket_Item,
   Versi_Ke), dan SQL Server menganggap NULL SETARA di unique index. Begitu
   ada DUA versi tanpa item, baris (NULL, 1) yang kedua ditolak error 2601.
   Menyimpannya berarti fitur ini mustahil dipakai lebih dari sekali.

   Penggantinya sudah berdiri sejak 26B: UX_N_SOP_Item_Versi_Dokumen_Urut
   pada (Id_SOP_Dokumen, Versi_Ke), terfilter. Itulah sumbu yang benar.
   ===================================================================== */

/* 1. Buang unique index lama (kini menghalangi, bukan melindungi). */
IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE object_id = OBJECT_ID('N_SOP_Item_Versi') AND name = 'UX_N_SOP_Item_Versi_Urut')
BEGIN
    DROP INDEX UX_N_SOP_Item_Versi_Urut ON N_SOP_Item_Versi;
    PRINT '28: UX_N_SOP_Item_Versi_Urut dibuang.';
END
GO

/* 2. Id_SOP_Paket_Item jadi nullable — versi boleh milik dokumen saja. */
ALTER TABLE N_SOP_Item_Versi ALTER COLUMN Id_SOP_Paket_Item INT NULL;
GO

PRINT '28: Id_SOP_Paket_Item kini nullable.';
GO

/* Pemeriksaan pasca-jalan. Harapan:
     Id_SOP_Paket_Item          -> IS_NULLABLE = YES
     UX_N_SOP_Item_Versi_Urut   -> tidak ada
     UX_..._Dokumen_Urut        -> ada, unik, terfilter               */
SELECT COLUMN_NAME, IS_NULLABLE
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Id_SOP_Paket_Item';

SELECT name, is_unique, has_filter
  FROM sys.indexes
 WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
   AND name LIKE 'UX_N_SOP_Item_Versi%';
GO

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila konsep ini dibatalkan.

   PERIKSA DULU: baris versi tanpa item harus NOL, kalau tidak ALTER
   gagal dan index lama bisa ditolak duplikat.

     SELECT COUNT(*) FROM N_SOP_Item_Versi WHERE Id_SOP_Paket_Item IS NULL;

     ALTER TABLE N_SOP_Item_Versi ALTER COLUMN Id_SOP_Paket_Item INT NOT NULL;
     CREATE UNIQUE INDEX UX_N_SOP_Item_Versi_Urut
         ON N_SOP_Item_Versi (Id_SOP_Paket_Item, Versi_Ke);
   --------------------------------------------------------------------- */
