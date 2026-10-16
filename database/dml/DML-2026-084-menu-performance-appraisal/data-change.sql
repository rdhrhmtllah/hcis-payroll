/* Change ID: DML-2026-084-menu-performance-appraisal
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 1, N_HRIS_Grup_Fitur_Map <= 1,
                            HRIS_Page_Access <= 2, register the change <= 1.
   Dependencies: rute /performance-appraisal sudah terdaftar di routes/web.php.

   Kenapa tiga tabel sekaligus. Halaman baru yang hanya punya rute akan menjawab 403
   untuk SEMUA orang, termasuk pembuatnya. Yang menentukan akses adalah Jenis_page:
   N_HRIS_Menus mendaftarkan halamannya, N_HRIS_Grup_Fitur_Map menyambungkannya ke grup
   fitur KPI (jalur role), dan HRIS_Page_Access memberi akses eksplisit per user
   (jalur warisan). Melewatkan salah satunya menghasilkan halaman yang ada tetapi tak
   bisa dibuka siapa pun - dan gejalanya menyamar sebagai bug rute.

   Kenapa akses eksplisit masih diberikan. Tidak ada satu pun role aktif yang memuat
   halaman PA, jadi jalur role belum menghasilkan akses bagi siapa pun. Dua user
   pengembang diberi akses eksplisit supaya halamannya bisa diuji; pemberian akses ke
   pengguna sebenarnya adalah keputusan pemilik modul, bukan keputusan skrip ini.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
   Tanpa itu, barisnya sudah ada di database tetapi menu yang dilihat pengguna masih
   yang lama, dan halamannya tetap 403 sampai cache-nya kedaluwarsa sendiri. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50360, 'DML-2026-084 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_HRIS_Menus', 'U') IS NULL
   OR OBJECT_ID('N_HRIS_Grup_Fitur_Map', 'U') IS NULL
   OR OBJECT_ID('HRIS_Page_Access', 'U') IS NULL
    THROW 50361, 'DML-2026-084 membutuhkan tabel menu, peta grup fitur, dan akses halaman.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'KPI' AND Flag_Aktif = 'Y')
    THROW 50362, 'DML-2026-084: grup fitur KPI tidak ada atau tidak aktif.', 1;
GO

BEGIN TRANSACTION;

/* ---------- 1. Halaman ---------- */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalPage')
BEGIN
    INSERT INTO N_HRIS_Menus
        (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
         Header_Code, Header_Label, Header_Icon, Sort_Header,
         Is_Global_Menu, Target, Is_Show_Sidebar)
    VALUES
        ('PerformanceAppraisalPage', '001', 'Performance Appraisal', 'bi-clipboard2-data',
         '/performance-appraisal',
         'manajemen_kpi', 'Manajemen KPI', 'bi bi-people-fill', 5,
         /* BUKAN menu global. Menu global tampil bagi semua orang, dan penilaian 360
            adalah layar pengelola siklus, bukan layar semua karyawan. */
         'T', 'self', 'Y');
END;

/* ---------- 2. Jalur role: sambungkan ke grup fitur KPI ---------- */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map
    WHERE Jenis_page = 'PerformanceAppraisalPage'
)
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
        (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT Id_Grup_Fitur, 'PerformanceAppraisalPage', 'T', 'Y', 'Y'
    FROM N_HRIS_Master_Grup_Fitur
    WHERE Kode_Grup_Fitur = 'KPI';
END;

/* ---------- 3. Jalur warisan: akses eksplisit dua user pengembang ---------- *
 * Kode_Perusahaan, ID_Level, dan ID_Divisi diambil dari baris akses yang sudah
 * dimiliki user itu sendiri, bukan ditulis tetap. Menuliskannya tetap akan membuat
 * barisnya menunjuk divisi yang bukan divisinya begitu ia pindah.                    */
INSERT INTO HRIS_Page_Access
    (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At)
SELECT DISTINCT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi,
       'PerformanceAppraisalPage', a.UserID_Web, 'Y', SYSDATETIME()
FROM HRIS_Page_Access a
WHERE a.UserID_Web IN (4, 622)
  AND a.Id_Page_Access = (
      SELECT MIN(b.Id_Page_Access) FROM HRIS_Page_Access b WHERE b.UserID_Web = a.UserID_Web
  )
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access c
      WHERE c.UserID_Web = a.UserID_Web
        AND c.Jenis_page = 'PerformanceAppraisalPage'
  );

COMMIT TRANSACTION;
GO

/* ---------- verifikasi ---------- */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalPage')
    THROW 50363, 'DML-2026-084: baris menu tidak terbentuk.', 1;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map m
    JOIN N_HRIS_Master_Grup_Fitur g ON g.Id_Grup_Fitur = m.Id_Grup_Fitur
    WHERE m.Jenis_page = 'PerformanceAppraisalPage' AND g.Kode_Grup_Fitur = 'KPI' AND m.Flag_Aktif = 'Y'
)
    THROW 50364, 'DML-2026-084: halaman belum tersambung ke grup fitur KPI.', 1;

IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalPage') <> 1
    THROW 50365, 'DML-2026-084: baris menu lebih dari satu.', 1;

IF (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PerformanceAppraisalPage') <> 1
    THROW 50366, 'DML-2026-084: peta grup fitur lebih dari satu.', 1;

/* Nol baris akses eksplisit bukan kegagalan skrip - itu berarti kedua user memang
   belum punya baris akses apa pun untuk disalin sumbernya. Tetapi harus terlihat,
   karena akibatnya halaman tetap 403 bagi mereka. */
IF (SELECT COUNT(*) FROM HRIS_Page_Access
    WHERE Jenis_page = 'PerformanceAppraisalPage' AND Flag_Aktif = 'Y') = 0
    THROW 50367, 'DML-2026-084: tidak ada satu pun akses eksplisit terbentuk; halaman akan 403 untuk semua orang.', 1;
GO

/* ---------- pencatatan change ---------- */
BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-084-menu-performance-appraisal'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-084-menu-performance-appraisal',
         'DML',
         'Pendaftaran halaman Performance Appraisal ke menu dan akses',
         'Menambah satu baris N_HRIS_Menus, satu baris N_HRIS_Grup_Fitur_Map ke grup KPI, dan akses eksplisit dua user pengembang.',
         'database/dml/DML-2026-084-menu-performance-appraisal/data-change.sql',
         'docs/changes/DML-2026-084-menu-performance-appraisal/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Halaman baru yang hanya punya rute menjawab 403 untuk SEMUA orang; yang menentukan akses adalah Jenis_page melalui tiga tabel sekaligus. Tidak ada role aktif yang memuat halaman PA, sehingga jalur role belum memberi akses kepada siapa pun - akses eksplisit dua user pengembang hanya untuk pengujian, dan pemberian akses ke pengguna sebenarnya adalah keputusan pemilik modul. Kode_Perusahaan/ID_Level/ID_Divisi disalin dari baris akses milik user itu sendiri, bukan ditulis tetap. WAJIB diikuti InertiaShellService::flushMenuCache(); tanpa itu menu yang dilihat pengguna masih yang lama.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
