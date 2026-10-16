/* =============================================================================
   25A — Dua unique index yang selama ini absen di sisi "live"
   =============================================================================

   Konteks
   -------
   Kedua tabel di bawah punya kembaran di sisi DRAFT yang SUDAH dijaga unique
   (mis. UX_N_SOP_Paket_Draft_Item_Versi_Urut). Ketimpangan itu sendiri yang
   menunjukkan aturannya memang dimaksudkan berlaku — hanya belum ditegakkan pada
   tabel live-nya.

   Tanpa index ini, satu-satunya yang mencegah data ganda adalah pemeriksaan
   cek-lalu-tulis di PHP, yang selalu punya jeda di antara kedua langkahnya.

   Prasyarat — SUDAH DIPERIKSA pada basis data dev (Web_HRIS @ team311):
     - N_SOP_Penjadwalan_Detail : 0 pasangan (penjadwalan × karyawan) ganda yang
       sama-sama AKTIF dan belum dicabut. Ada 1 pasangan dengan 2 baris, tetapi
       salah satunya sudah dicabut — itu SAH dan sengaja diizinkan (lihat catatan
       filter di bawah).
     - N_SOP_Item_Versi        : 0 pasangan (item × Versi_Ke) ganda.

   JALANKAN KUERI PEMERIKSAAN DI BAGIAN 0 LEBIH DULU di lingkungan tujuan.
   Bila mengembalikan baris, JANGAN lanjut — bersihkan datanya dulu.

   Catatan SET options
   -------------------
   Index TERFILTER menolak DML dari koneksi yang SET option-nya tak sesuai. Ini
   sudah diuji nyata lewat koneksi aplikasi (PDO_SQLSRV): index dibuat, lalu
   INSERT penugasan baru BERHASIL. Jadi koneksi aplikasi memang cocok.

   Perlu diketahui bila memakai klien lain: SESSIONPROPERTY('ARITHABORT') pada
   koneksi ini melaporkan 0, namun ANSI_WARNINGS ON sudah memberlakukan perilaku
   yang setara pada level kompatibilitas modern — karena itu DML-nya lolos.
   Klien yang menyetel ANSI_WARNINGS OFF akan DITOLAK saat menulis ke tabel ini.

   Sifat: idempoten (dijaga IF NOT EXISTS). Aman dijalankan ulang.
   ============================================================================= */

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET ARITHABORT ON;
SET NUMERIC_ROUNDABORT OFF;
GO

/* -----------------------------------------------------------------------------
   0. PEMERIKSAAN PRASYARAT — jalankan dulu, harus mengembalikan 0 baris keduanya
   ----------------------------------------------------------------------------- */

-- 0a. Penugasan aktif ganda (yang hendak dicegah)
SELECT Id_SOP_Penjadwalan, Kode_Karyawan, COUNT(*) AS jumlah
FROM   N_SOP_Penjadwalan_Detail
WHERE  Flag_Aktif = 'Y' AND Flag_Cabut_Akses = 'T'
GROUP  BY Id_SOP_Penjadwalan, Kode_Karyawan
HAVING COUNT(*) > 1;

-- 0b. Nomor versi kembar pada satu item
SELECT Id_SOP_Paket_Item, Versi_Ke, COUNT(*) AS jumlah
FROM   N_SOP_Item_Versi
GROUP  BY Id_SOP_Paket_Item, Versi_Ke
HAVING COUNT(*) > 1;
GO

/* -----------------------------------------------------------------------------
   1. N_SOP_Penjadwalan_Detail — satu penugasan AKTIF per (batch × karyawan)
   -----------------------------------------------------------------------------
   TERFILTER, dan filternya bukan hiasan.

   Unique polos atas (Id_SOP_Penjadwalan, Kode_Karyawan) akan MERUSAK alur yang
   sah: mencabut akses seseorang lalu menugaskannya kembali memang meninggalkan
   baris lama yang dicabut dan membuat baris baru. Data dev sudah memuat kasus
   seperti itu (paket 5, karyawan C9 — 2 baris, 1 di antaranya dicabut).

   Filter ini hanya mengunci baris yang benar-benar berlaku, sehingga riwayat
   pencabutan tetap utuh sementara penugasan aktif ganda tertutup.

   Terverifikasi dengan membuat index ini sungguhan lalu di-rollback:
     - insert penugasan baru        -> berhasil
     - duplikat aktif               -> ditolak (2601)
     - assign ulang setelah dicabut -> tetap diizinkan
   ----------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UX_N_SOP_PjdD_Aktif'
                 AND object_id = OBJECT_ID('N_SOP_Penjadwalan_Detail'))
BEGIN
    CREATE UNIQUE INDEX UX_N_SOP_PjdD_Aktif
        ON N_SOP_Penjadwalan_Detail (Id_SOP_Penjadwalan, Kode_Karyawan)
        WHERE Flag_Aktif = 'Y' AND Flag_Cabut_Akses = 'T';

    PRINT 'UX_N_SOP_PjdD_Aktif dibuat.';
END
ELSE
    PRINT 'UX_N_SOP_PjdD_Aktif sudah ada — dilewati.';
GO

/* -----------------------------------------------------------------------------
   2. N_SOP_Item_Versi — nomor urut versi unik per item
   -----------------------------------------------------------------------------
   Menyamakan tabel live dengan kembarannya di sisi draft, yang sudah dijaga
   UX_N_SOP_Paket_Draft_Item_Versi_Urut (Id_SOP_Draft_Item, Versi_Ke).

   Tanpa filter: nomor urut versi harus unik pada SEMUA status, termasuk versi
   lama yang sudah tak aktif — justru riwayat itulah yang penomorannya harus
   tetap bermakna.

   Terverifikasi dengan membuat index ini sungguhan lalu di-rollback:
     - nomor versi kembar   -> ditolak (2601)
     - versi berikutnya     -> tetap diizinkan
   ----------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UX_N_SOP_Item_Versi_Urut'
                 AND object_id = OBJECT_ID('N_SOP_Item_Versi'))
BEGIN
    CREATE UNIQUE INDEX UX_N_SOP_Item_Versi_Urut
        ON N_SOP_Item_Versi (Id_SOP_Paket_Item, Versi_Ke);

    PRINT 'UX_N_SOP_Item_Versi_Urut dibuat.';
END
ELSE
    PRINT 'UX_N_SOP_Item_Versi_Urut sudah ada — dilewati.';
GO

/* -----------------------------------------------------------------------------
   3. VERIFIKASI SESUDAH DIJALANKAN
   ----------------------------------------------------------------------------- */
SELECT  t.name AS tabel, i.name AS index_name, i.is_unique, i.has_filter,
        ISNULL(i.filter_definition, '(tanpa filter)') AS filter_definition
FROM    sys.indexes i
JOIN    sys.tables  t ON t.object_id = i.object_id
WHERE   i.name IN ('UX_N_SOP_PjdD_Aktif', 'UX_N_SOP_Item_Versi_Urut');
GO

/* -----------------------------------------------------------------------------
   ROLLBACK (bila diperlukan)
   -----------------------------------------------------------------------------
   DROP INDEX UX_N_SOP_PjdD_Aktif      ON N_SOP_Penjadwalan_Detail;
   DROP INDEX UX_N_SOP_Item_Versi_Urut ON N_SOP_Item_Versi;
   ----------------------------------------------------------------------------- */
