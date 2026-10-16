/* Change ID: DDL-2026-059-sop-kategori-boleh-kosong
   Target: Web_HRIS development only.
   Status: DRAFT — BELUM DIJALANKAN, menunggu persetujuan.

   Purpose: Membuat Id_Kategori_SOP boleh kosong pada dua tabel paket, supaya
            paket baru bisa lahir tanpa kategori setelah Kategori dicabut dari
            modul (lihat docs/superpowers/specs/2026-08-28-sop-cabut-kategori-design.md).

   KENAPA INI TAK TERDUGA
   ----------------------
   Rancangan pencabutan Kategori menjanjikan NOL DDL, dengan alasan kolomnya
   cukup dibiarkan berdiri dan berhenti diisi. Alasan itu keliru: kolomnya
   NOT NULL, tanpa DEFAULT, dan tanpa FK.

       N_SOP_Master_Paket.Id_Kategori_SOP   int  NOT NULL
       N_SOP_Paket_Draft.Id_Kategori_SOP    int  NOT NULL

   Akibatnya "berhenti mengisi" bukan pilihan yang tersedia — INSERT paket baru
   gagal dengan "Cannot insert the value NULL into column 'Id_Kategori_SOP'".
   Ini terlihat hanya saat uji dijalankan, bukan saat kode dibaca.

   KENAPA MELONGGARKAN, BUKAN MENGISI NILAI PENGGANTI
   --------------------------------------------------
   Mengisi sentinel (kategori "Tanpa Kategori", atau id kategori mana pun yang
   kebetulan ada) membuat setiap paket baru MENUNJUK baris master yang tak lagi
   dikelola siapa pun, dan laporan lama akan menghitungnya sebagai kategori
   sungguhan. Melonggarkan NULL menyatakan keadaan yang sebenarnya: paket ini
   memang tak punya kategori.

   SIFAT PERUBAHAN
   ---------------
   Melonggarkan batasan, bukan mengetatkannya. Tidak ada baris yang berubah,
   tidak ada data yang hilang, tidak ada kolom yang dibuang. Seluruh baris yang
   ada sekarang tetap sah sesudahnya.

   YANG TETAP TIDAK DILAKUKAN
   --------------------------
   Kolomnya TIDAK dibuang dan tabel N_SOP_Kategori TIDAK disentuh. Nilai yang
   sudah tersimpan di paket lama tetap di tempatnya.

   PERIKSA DULU
   ------------
   Tak ada FK maupun DEFAULT pada kolom ini (terverifikasi 2026-08-28), jadi
   ALTER COLUMN tidak perlu melepas constraint lebih dulu. Kalau di environment
   lain ternyata ada, ALTER-nya akan gagal dengan galat yang menyebut namanya —
   itu perilaku yang benar, bukan yang harus diakali.
*/

SET XACT_ABORT ON;

/* Indeks yang menyertakan kolom ini akan menghalangi ALTER COLUMN. Ditampilkan
   lebih dulu supaya kegagalannya bisa dibaca sebelum dicoba. */
SELECT i.name AS indeks, OBJECT_NAME(i.object_id) AS tabel
  FROM sys.index_columns ic
  JOIN sys.indexes i ON i.object_id = ic.object_id AND i.index_id = ic.index_id
  JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
 WHERE c.name = 'Id_Kategori_SOP'
   AND OBJECT_NAME(i.object_id) IN ('N_SOP_Master_Paket', 'N_SOP_Paket_Draft');
GO

ALTER TABLE dbo.N_SOP_Master_Paket
    ALTER COLUMN Id_Kategori_SOP INT NULL;
GO

ALTER TABLE dbo.N_SOP_Paket_Draft
    ALTER COLUMN Id_Kategori_SOP INT NULL;
GO

/* Verifikasi */
DECLARE @sisa INT = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE COLUMN_NAME = 'Id_Kategori_SOP'
       AND TABLE_NAME IN ('N_SOP_Master_Paket', 'N_SOP_Paket_Draft')
       AND IS_NULLABLE = 'NO'
);

IF @sisa > 0
    THROW 50059, 'Masih ada kolom Id_Kategori_SOP yang NOT NULL sesudah ALTER.', 1;

PRINT 'Id_Kategori_SOP kini boleh kosong pada N_SOP_Master_Paket dan N_SOP_Paket_Draft.';
GO
