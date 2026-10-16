/* =====================================================================
   29 — Rujukan karyawan pengesah, di samping snapshot namanya

   Dijalankan MANUAL di SSMS.

   KENAPA BUKAN MENGGANTI SNAPSHOT. Dokumen terkendali mencatat siapa
   mengesahkan PADA SAAT ITU. Kalau nama dan jabatan pengesah ditarik
   lewat join saat dibaca, maka begitu orangnya naik jabatan atau resign,
   SOP yang sudah sah berubah sendiri catatan pengesahannya — persis
   kelas kesalahan yang membuat dokumen terkendali ada.

   Karena itu Disahkan_Oleh dan Jabatan_Pengesah TETAP menjadi kebenaran
   yang ditampilkan. Kolom ini hanya tambahan.

   GUNANYA TIGA. Menandai bahwa pengesah dipilih dari master karyawan
   (bukan diketik lepas); memungkinkan pencarian "SOP yang disahkan si X";
   dan kelak mendeteksi bahwa snapshot sudah berbeda dari data sekarang —
   itu sinyal untuk ditinjau, bukan kesalahan untuk diperbaiki diam-diam.

   BOLEH NULL, DAN ITU DISENGAJA. Pengesah bisa orang di luar HRIS —
   konsultan, direktur entitas lain. NULL di sini adalah catatan yang
   jujur: "tidak terverifikasi terhadap master karyawan".

   Tipe dan panjang mengikuti Kode_Karyawan_Publisher yang sudah ada di
   tabel yang sama, supaya kedua rujukan karyawan di baris versi tidak
   berbeda bentuk tanpa alasan.
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'N_SOP_Item_Versi'
                 AND COLUMN_NAME = 'Kode_Karyawan_Pengesah')
BEGIN
    ALTER TABLE N_SOP_Item_Versi ADD Kode_Karyawan_Pengesah VARCHAR(20) NULL;
    PRINT '29: kolom Kode_Karyawan_Pengesah ditambahkan.';
END
ELSE
BEGIN
    PRINT '29: kolom Kode_Karyawan_Pengesah sudah ada — tidak ada perubahan.';
END
GO

/* TIDAK ADA PENGISIAN MUNDUR. Empat baris yang kini punya Disahkan_Oleh
   berisi masukan uji ("sasdsdasd", "asdasd", "Si Anu"), yang tak merujuk
   siapa pun. Mencocokkannya ke karyawan lewat kemiripan nama justru akan
   mengarang rujukan yang tak pernah dibuat siapa pun — lebih buruk
   daripada membiarkannya NULL, yang setidaknya jujur. */

/* Pemeriksaan pasca-jalan. tanpa_rujukan > 0 itu SAH: baris lama memang
   belum punya rujukan, dan pengesah luar memang tak akan pernah punya. */
SELECT
    COUNT(*)                                                              AS total_versi,
    SUM(CASE WHEN Disahkan_Oleh IS NOT NULL THEN 1 ELSE 0 END)            AS ada_pengesah,
    SUM(CASE WHEN Disahkan_Oleh IS NOT NULL
              AND Kode_Karyawan_Pengesah IS NULL THEN 1 ELSE 0 END)       AS tanpa_rujukan
FROM N_SOP_Item_Versi;
GO

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila perubahan ini dibatalkan.

     ALTER TABLE N_SOP_Item_Versi DROP COLUMN Kode_Karyawan_Pengesah;

   Tak ada catatan pengesahan yang hilang: nama dan jabatan pengesah
   tersimpan sebagai snapshot di kolomnya sendiri, dan memang di situlah
   kebenarannya selama ini.
   --------------------------------------------------------------------- */
