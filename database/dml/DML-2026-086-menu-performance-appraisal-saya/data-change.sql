/* Change ID: DML-2026-086-menu-performance-appraisal-saya
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 1, N_HRIS_Grup_Fitur_Map <= 1,
                            register the change <= 1.
   Dependencies: DML-2026-084 (halaman siklus PA sudah terdaftar).

   SATU BARIS, DAN SENGAJA Is_Global_Menu = 'Y'.

   Ini halaman yang dipakai SELURUH karyawan - 958 orang pada dev per 1 September 2026 -
   dan itu membalik cara aksesnya diberikan. Delapan halaman master di DML-2026-085
   memakai HRIS_Page_Access karena penggunanya sedikit dan harus disebut satu per satu.
   Di sini justru sebaliknya: mendaftarkan 958 baris berarti setiap karyawan baru harus
   ditambahkan lagi, dan yang terlewat TIDAK AKAN MENGELUH - ia hanya tidak pernah
   mengisi penilaiannya, lalu muncul di laporan admin sebagai penilai yang tidak
   merespons. Kegagalan yang menyamar jadi kelalaian orang adalah kegagalan yang paling
   lama tidak diperbaiki.

   Is_Global_Menu='Y' membuka dua jalur sekaligus, dan keduanya sudah diperiksa pada kode
   yang berjalan hari ini:
     - CheckJabatan -> User::memilikiJabatanLegacy() menggabungkan daftar global ke akses
       user, sehingga rutenya lolos untuk semua orang.
     - InertiaShellService::menuIsVisible() mengembalikan true lebih awal untuk baris
       global, sehingga menunya tampil tanpa baris HRIS_Page_Access.

   Sudah ada delapan halaman lain yang memakai pola ini (antara lain menuSopSayaPage dan
   MyActivity), jadi ini mengikuti jalan yang sudah ada, bukan membuat jalan baru.

   Barisnya tetap disambungkan ke grup fitur KPI supaya jalur role tidak menganggapnya
   halaman yatim; aksesnya sendiri sudah dijamin oleh Is_Global_Menu.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50372, 'DML-2026-086 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalPage')
    THROW 50373, 'DML-2026-086 membutuhkan DML-2026-084 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

/* ---------- 1. Halaman peserta ---------- */
INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT 'PerformanceAppraisalSayaPage', '001', 'Penilaian Saya', 'bi-clipboard2-check',
       '/performance-appraisal/saya',
       'performance_appraisal', 'Performance Appraisal', 'bi bi-clipboard2-data', 6,
       'Y', 'self', 'Y', 1
WHERE NOT EXISTS (
    SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalSayaPage'
);

/* ---------- 2. Jalur role ---------- */
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, 'PerformanceAppraisalSayaPage', 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (
      SELECT 1 FROM N_HRIS_Grup_Fitur_Map x
      WHERE x.Jenis_page = 'PerformanceAppraisalSayaPage'
  );

/* ---------- 3. Verifikasi ---------- */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Jenis_page = 'PerformanceAppraisalSayaPage' AND Is_Global_Menu = 'Y'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50374, 'DML-2026-086: halaman peserta tidak terpasang atau tidak global.', 1;
END;

/* Barisnya harus TEPAT satu. Jenis_page tidak punya unique constraint, sehingga
   dijalankan dua kali tanpa penjaga NOT EXISTS akan menghasilkan menu ganda - dan menu
   ganda tidak menimbulkan galat, hanya dua baris identik di sidebar yang membuat orang
   ragu mana yang benar. */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalSayaPage') <> 1
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50375, 'DML-2026-086: baris menu peserta lebih dari satu.', 1;
END;

/* ---------- 4. Catat ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-086-menu-performance-appraisal-saya')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-086-menu-performance-appraisal-saya',
         'DML',
         'Halaman peserta Performance Appraisal',
         'Menambah satu halaman peserta ber-Is_Global_Menu=Y beserta peta grup fitur KPI.',
         'database/dml/DML-2026-086-menu-performance-appraisal-saya/data-change.sql',
         'docs/changes/DML-2026-086-menu-performance-appraisal-saya/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Is_Global_Menu=Y, bukan 958 baris HRIS_Page_Access. Halaman ini dipakai seluruh karyawan, sehingga mendaftarkan aksesnya satu per satu berarti setiap karyawan baru harus ditambahkan lagi - dan yang terlewat tidak akan mengeluh, ia hanya tidak pernah mengisi penilaiannya lalu muncul di laporan admin sebagai penilai yang tidak merespons. Dua jalur yang dibuka Is_Global_Menu sudah diperiksa pada kode yang berjalan: CheckJabatan lewat User::memilikiJabatanLegacy() dan sidebar lewat InertiaShellService::menuIsVisible(). Delapan halaman lain sudah memakai pola ini. WAJIB diikuti InertiaShellService::flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
