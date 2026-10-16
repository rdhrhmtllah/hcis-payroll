/* Change ID: DML-2026-058-sop-cabut-kategori
   Target: Web_HRIS development only.
   Purpose: Menutup pencabutan Kategori dari modul DMS — membersihkan template
            laporan tersimpan, lalu mematikan menu dan hak akses halamannya.

   PRASYARAT MUTLAK
   ----------------
   DML-2026-057 HARUS sudah dijalankan. Ketiga bloknya menyalin dari baris
   menuSopKategoriPage; skrip ini mematikan baris itu, sehingga menjalankan
   yang ini lebih dulu membuat 057 mandul DAN membuat halaman Prioritas
   Dokumen 403 untuk seluruh organisasi. Tahap 0 di bawah menolak berjalan
   bila 057 belum tertanam.

   YANG TIDAK DILAKUKAN SKRIP INI
   ------------------------------
   Tabel N_SOP_Kategori dan kolom N_SOP_Master_Paket.Id_Kategori_SOP TIDAK
   disentuh — bukan dihapus, bukan dinonaktifkan, bukan dikosongkan. Itu
   keputusan sadar: riwayat paket lama tetap terbaca, dan membiarkannya berdiri
   adalah satu-satunya jalan pulang kalau kategori ternyata dibutuhkan lagi.
   Kode berhenti membacanya; datanya tetap ada.

   SESUDAH MENJALANKAN
   -------------------
       php artisan tinker --execute="App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"

   AMAN DIJALANKAN BERULANG.
*/

SET XACT_ABORT ON;

/* ── 0. Gerbang urutan ─────────────────────────────────────────────────
   Menolak berjalan sebelum Prioritas Dokumen berdiri sendiri. Tanpa gerbang
   ini, urutan yang terbalik menghasilkan kerusakan yang tak melempar galat
   apa pun: menunya hilang dan halamannya menolak semua orang. */
IF NOT EXISTS (
    SELECT 1 FROM dbo.HRIS_Page_Access
     WHERE Jenis_page = 'menuSopPrioritasPage' AND Flag_Aktif = 'Y'
)
    THROW 50058, 'DML-2026-057 belum dijalankan: tak seorang pun memegang menuSopPrioritasPage. Mematikan kunci Kategori sekarang akan membuat halaman Prioritas Dokumen menolak semua orang.', 1;

IF NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map
     WHERE Jenis_page = 'menuSopPrioritasPage' AND Flag_Aktif = 'Y'
)
    THROW 50058, 'DML-2026-057 belum dijalankan: pemetaan grup fitur menuSopPrioritasPage belum ada, jadi menunya tak akan pernah muncul di sidebar.', 1;

PRINT 'Gerbang urutan lolos: Prioritas Dokumen sudah berdiri sendiri.';
GO

/* ── 1. Bersihkan template laporan tersimpan ───────────────────────────
   `kategori` tersimpan sebagai string biasa di dalam larik "kolom" dan
   "dimensi" pada Definisi_Json. Tiga bentuk yang mungkin, dan ketiganya harus
   ditangani — melewatkan satu meninggalkan JSON yang sah tapi menyebut kunci
   yang server sudah tak kenal:

     ["a","kategori","b"]  -> "kategori",   di tengah/awal
     ["a","kategori"]      -> ,"kategori"   di akhir
     ["kategori"]          -> satu-satunya isi

   Urutannya penting: bentuk akhir diproses lebih dulu, kalau tidak bentuk
   tengah akan memakan tanda kutipnya dan meninggalkan koma menggantung.

   Lingkup diperkirakan: 9 baris pada N_HRIS_Report_Template (Module='sop'),
   3 di antaranya masih Flag_Aktif='Y'. */
BEGIN TRANSACTION;

UPDATE dbo.N_HRIS_Report_Template
   SET Definisi_Json = REPLACE(Definisi_Json, '["kategori"]', '[]')
 WHERE Module = 'sop' AND Definisi_Json LIKE '%["kategori"]%';

UPDATE dbo.N_HRIS_Report_Template
   SET Definisi_Json = REPLACE(Definisi_Json, ',"kategori"', '')
 WHERE Module = 'sop' AND Definisi_Json LIKE '%,"kategori"%';

UPDATE dbo.N_HRIS_Report_Template
   SET Definisi_Json = REPLACE(Definisi_Json, '"kategori",', '')
 WHERE Module = 'sop' AND Definisi_Json LIKE '%"kategori",%';

COMMIT TRANSACTION;
GO

/* Verifikasi: tak boleh ada satu pun template SOP yang masih menyebutnya. */
DECLARE @sisa INT = (
    SELECT COUNT(*) FROM dbo.N_HRIS_Report_Template
     WHERE Module = 'sop' AND Definisi_Json LIKE '%kategori%'
);

IF @sisa > 0
    THROW 50058, 'Masih ada template SOP yang menyebut kategori. Periksa bentuk JSON-nya sebelum melanjutkan.', 1;

PRINT 'Template laporan SOP bersih dari kategori.';
GO

/* ── 2. Matikan baris menu Kategori ────────────────────────────────────
   Dua tempat, dan keduanya perlu: Grup_Fitur_Map menentukan menunya ikut
   dibangun sama sekali, Is_Show_Sidebar menentukan ia tampil. Mematikan satu
   saja meninggalkan yang lain sebagai jalan masuk yang tak terduga.

   Barisnya ditunjuk lewat Jenis_page, bukan lewat Id: Id_Grup_Map dan Id_Menu
   adalah IDENTITY yang berbeda antar environment.

   Lingkup: 1 baris N_HRIS_Grup_Fitur_Map + 1 baris N_HRIS_Menus. */
BEGIN TRANSACTION;

UPDATE dbo.N_HRIS_Grup_Fitur_Map
   SET Flag_Aktif = 'T'
 WHERE Jenis_page = 'menuSopKategoriPage';

UPDATE dbo.N_HRIS_Menus
   SET Is_Show_Sidebar = 'T'
 WHERE Jenis_page = 'menuSopKategoriPage';

COMMIT TRANSACTION;
GO

/* ── 3. Matikan hak akses Kategori ─────────────────────────────────────
   Lingkup diperkirakan: 2 baris (user 4 dan 622) pada HRIS_Page_Access. */
BEGIN TRANSACTION;

UPDATE dbo.HRIS_Page_Access
   SET Flag_Aktif = 'T', Updated_At = GETDATE()
 WHERE Jenis_page = 'menuSopKategoriPage' AND Flag_Aktif = 'Y';

COMMIT TRANSACTION;
GO

/* ── 4. Verifikasi akhir ───────────────────────────────────────────────── */
DECLARE @akses INT = (SELECT COUNT(*) FROM dbo.HRIS_Page_Access WHERE Jenis_page = 'menuSopKategoriPage' AND Flag_Aktif = 'Y');
DECLARE @peta  INT = (SELECT COUNT(*) FROM dbo.N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopKategoriPage' AND Flag_Aktif = 'Y');
DECLARE @prio  INT = (SELECT COUNT(*) FROM dbo.HRIS_Page_Access WHERE Jenis_page = 'menuSopPrioritasPage' AND Flag_Aktif = 'Y');

IF @akses > 0 OR @peta > 0
    THROW 50058, 'Menu atau hak akses Kategori masih aktif sesudah pencabutan.', 1;

IF @prio = 0
    THROW 50058, 'Hak akses Prioritas Dokumen ikut padam. Halaman itu kini menolak semua orang.', 1;

PRINT 'Kategori dicabut. Prioritas Dokumen dipegang '+CAST(@prio AS VARCHAR(10))+' user.';
GO

/* ── 5. Daftarkan perubahan ────────────────────────────────────────────── */
IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
     WHERE Kode_Change = 'DML-2026-058-sop-cabut-kategori'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path, Target_Development,
        Status_Development, Status_SQL_Tracker, Status_Production,
        Status_Verification, Created_By
    )
    VALUES
    (
        'DML-2026-058-sop-cabut-kategori',
        'DML',
        'Cabut menu, hak akses, dan jejak template Kategori dari modul DMS',
        'Membersihkan kunci kategori dari Definisi_Json template laporan SOP tersimpan, lalu mematikan baris menu dan hak akses menuSopKategoriPage. Tabel N_SOP_Kategori dan kolom Id_Kategori_SOP SENGAJA tidak disentuh. Wajib dijalankan SESUDAH DML-2026-057; ada gerbang yang menolak urutan terbalik.',
        'database/dml/DML-2026-058-sop-cabut-kategori/data-change.sql',
        'docs/superpowers/specs/2026-08-28-sop-cabut-kategori-design.md',
        'Web_HRIS',
        'PENDING',
        'PENDING',
        'PENDING',
        'PENDING',
        'sop-cabut-kategori'
    );
END;
GO
