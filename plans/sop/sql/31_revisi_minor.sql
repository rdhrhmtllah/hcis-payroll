/* =====================================================================
   31 — Revisi minor: penyelesaian karyawan dibawa, bukan dibuka lagi

   Dijalankan MANUAL di SSMS.

   KEADAAN SEKARANG. Penyelesaian dicatat per (item x versi):

     ON ISNULL(pr.Id_SOP_Item_Versi,-1) = ISNULL(i.Id_Item_Versi_Aktif,-1)

   Begitu pointer versi pindah, semua baris progres versi lama tak lagi
   cocok, sehingga karyawan otomatis terhitung belum selesai dan
   kewajibannya terbuka lagi. Itu berlaku SAMA untuk perbaikan salah ketik
   maupun perombakan isi — dan memaksa ratusan orang membaca ulang karena
   satu kop surat berubah bukan cuma merepotkan, ia mengikis arti
   "dibuka lagi" itu sendiri.

   KEPUTUSAN PRODUK 2026-08-10. Sifat revisi menjadi bagian dari versinya:
     mayor (default) — semua wajib baca ulang, seperti sekarang
     minor           — yang sudah selesai tetap terhitung selesai

   DEFAULT 'Y' DISENGAJA, dan berlaku untuk seluruh baris lama. Kalau ragu,
   membaca ulang itu murah; melewatkan revisi yang sebenarnya penting
   tidak. Admin yang lupa memilih tak boleh menghasilkan diam-diam
   "tak usah baca".

   KENAPA MENYALIN, BUKAN MELONGGARKAN JOIN. Join (item x versi) dipakai di
   banyak tempat — recompute kepatuhan, monitoring, export. Melonggarkannya
   berarti mengubah arti "selesai" di seluruh modul sekaligus, untuk semua
   revisi, termasuk yang mayor. Menyalin baris menjaga artinya tetap satu,
   dan meninggalkan bukti yang bisa ditunjuk.
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Wajib_Baca_Ulang')
BEGIN
    ALTER TABLE N_SOP_Item_Versi
        ADD Wajib_Baca_Ulang CHAR(1) NOT NULL
            CONSTRAINT DF_SOP_Versi_Baca_Ulang DEFAULT ('Y');
    PRINT '31: kolom Wajib_Baca_Ulang ditambahkan (default Y = mayor).';
END
ELSE
BEGIN
    PRINT '31: kolom Wajib_Baca_Ulang sudah ada — tidak ada perubahan.';
END
GO

/* Bukti per baris: penyelesaian ini DIBAWA dari versi mana.

   Tidak ditaruh di N_SOP_Kepatuhan_Riwayat karena Peristiwa di sana dikunci
   CHECK ke empat nilai (SELESAI / DIBUKA_LAGI / DICABUT / KADALUARSA), dan
   menambah kosakata baru ke ledger kepatuhan demi satu kasus akan mengubah
   arti tabel yang sudah dipakai monitoring dan analitik. Jejak naratifnya
   tetap ada di log audit SOP, yang memang tak berkonstrain. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'N_SOP_Progress' AND COLUMN_NAME = 'Id_Versi_Asal')
BEGIN
    ALTER TABLE N_SOP_Progress ADD Id_Versi_Asal INT NULL;
    PRINT '31: kolom Id_Versi_Asal ditambahkan.';
END
ELSE
BEGIN
    PRINT '31: kolom Id_Versi_Asal sudah ada — tidak ada perubahan.';
END
GO

/* Pemeriksaan pasca-jalan. Seluruh baris lama WAJIB bernilai 'Y': tak ada
   satu pun revisi masa lalu yang boleh berubah sifat secara surut. */
SELECT
    COUNT(*)                                                        AS total_versi,
    SUM(CASE WHEN Wajib_Baca_Ulang = 'Y' THEN 1 ELSE 0 END)         AS mayor,
    SUM(CASE WHEN Wajib_Baca_Ulang <> 'Y' THEN 1 ELSE 0 END)        AS bukan_mayor
FROM N_SOP_Item_Versi;
GO

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila perubahan ini dibatalkan.

     ALTER TABLE N_SOP_Item_Versi DROP CONSTRAINT DF_SOP_Versi_Baca_Ulang;
     ALTER TABLE N_SOP_Item_Versi DROP COLUMN Wajib_Baca_Ulang;
     ALTER TABLE N_SOP_Progress   DROP COLUMN Id_Versi_Asal;

   PERINGATAN. Baris progres yang sudah terlanjur DIBAWA tidak ikut
   terhapus — ia tetap terhitung selesai, hanya kehilangan keterangan dari
   mana asalnya. Kalau itu tak dikehendaki, hapus barisnya lebih dulu:

     DELETE FROM N_SOP_Progress WHERE Id_Versi_Asal IS NOT NULL;

   Jalankan penghapusan itu SEBELUM kolomnya dibuang; sesudahnya, tak ada
   lagi cara membedakan mana yang dibawa dan mana yang dikerjakan sungguhan.
   --------------------------------------------------------------------- */
