/* Change ID: DML-2026-085-menu-admin-performance-appraisal
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 8, N_HRIS_Grup_Fitur_Map <= 8,
                            HRIS_Page_Access <= 16, UPDATE N_HRIS_Menus <= 1,
                            register the change <= 1.
   Dependencies: DML-2026-084 (halaman siklus PA sudah terdaftar).

   Kenapa DELAPAN kunci halaman, bukan satu.

   Jenis_page bersifat unik, jadi satu kunci hanya bisa menjadi satu baris menu. Tetapi
   alasan sebenarnya bukan itu: master PA tidak setara bahayanya. Menyunting nama
   kompetensi menyentuh satu layar; menyunting PITA GRADE mengubah arti nilai semua
   orang pada siklus berikutnya. Satu kunci untuk semuanya berarti siapa pun yang butuh
   yang pertama otomatis mendapat yang kedua.

   Rute admin menerima kunci mana pun sebagai izin masuk; master MANA yang boleh dibuka
   dijaga per master di PaMasterDataController. Dua lapis, dua pertanyaan berbeda:
   "boleh masuk ruangannya" dan "boleh membuka lemari yang mana".

   Kenapa header sendiri.

   Halaman siklus PA sebelumnya menumpang header "Manajemen KPI". Modulnya kini punya
   dua wajah yang berbeda penggunanya - pengelola siklus dan pengurus master - sehingga
   keduanya diberi header sendiri. Halaman siklus DIPINDAHKAN, bukan digandakan.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50370, 'DML-2026-085 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalPage')
    THROW 50371, 'DML-2026-085 membutuhkan DML-2026-084 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

/* ---------- 1. Halaman siklus pindah ke headernya sendiri ---------- */
UPDATE N_HRIS_Menus
SET Header_Code = 'performance_appraisal',
    Header_Label = 'Performance Appraisal',
    Header_Icon = 'bi bi-clipboard2-data',
    Sort_Header = 6
WHERE Jenis_page = 'PerformanceAppraisalPage';

/* ---------- 2. Delapan halaman master ---------- */
INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT v.Jenis_page, '001', v.Nama_Menu, v.Icon_Menu, v.Url_Menu,
       'administrator_pa', 'Administrator Performance Appraisal', 'bi bi-sliders', 11,
       'T', 'self', 'Y', v.Sort_Menu
FROM (VALUES
    ('PaMasterJenisKompetensiPage', 'Jenis Kompetensi',   'bi-tags',            '/performance-appraisal/master/jenis-kompetensi', 1),
    ('PaMasterKompetensiPage',      'Kompetensi',         'bi-diagram-3',       '/performance-appraisal/master/kompetensi',       2),
    ('PaMasterKategoriPenilaiPage', 'Kategori Penilai',   'bi-people',          '/performance-appraisal/master/kategori-penilai', 3),
    ('PaMasterAturanPenilaiPage',   'Aturan Penilai',     'bi-ui-checks-grid',  '/performance-appraisal/master/aturan-penilai',   4),
    ('PaMasterBobotPilarPage',      'Bobot Pilar',        'bi-sliders2',        '/performance-appraisal/master/bobot-pilar',      5),
    ('PaMasterPitaNilaiPage',       'Pita Nilai',         'bi-bar-chart-steps', '/performance-appraisal/master/pita-nilai',       6),
    ('PaMasterKuadranHavPage',      'Kuadran HAV',        'bi-grid-3x3',        '/performance-appraisal/master/kuadran-hav',      7),
    ('PaMasterPaketSoalPage',       'Paket Soal',         'bi-journal-text',    '/performance-appraisal/master/paket-soal',       8)
) AS v(Jenis_page, Nama_Menu, Icon_Menu, Url_Menu, Sort_Menu)
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus m WHERE m.Jenis_page = v.Jenis_page);

/* ---------- 3. Jalur role: sambungkan kedelapan ke grup fitur KPI ---------- */
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, m.Jenis_page, 'T', 'Y', 'Y'
FROM N_HRIS_Menus m
CROSS JOIN N_HRIS_Master_Grup_Fitur g
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND m.Header_Code = 'administrator_pa'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = m.Jenis_page);

/* ---------- 4. Jalur warisan: akses eksplisit dua user pengembang ---------- *
 * Kode_Perusahaan, ID_Level, dan ID_Divisi disalin dari baris akses milik user itu
 * sendiri, bukan ditulis tetap - supaya barisnya tidak menunjuk divisi yang salah
 * begitu ia pindah.                                                                 */
INSERT INTO HRIS_Page_Access
    (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, m.Jenis_page, a.UserID_Web, 'Y', SYSDATETIME()
FROM N_HRIS_Menus m
CROSS JOIN (
    SELECT b.UserID_Web, b.Kode_Perusahaan, b.ID_Level, b.ID_Divisi
    FROM HRIS_Page_Access b
    WHERE b.UserID_Web IN (4, 622)
      AND b.Id_Page_Access = (SELECT MIN(c.Id_Page_Access) FROM HRIS_Page_Access c WHERE c.UserID_Web = b.UserID_Web)
) AS a
WHERE m.Header_Code = 'administrator_pa'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access d
      WHERE d.UserID_Web = a.UserID_Web AND d.Jenis_page = m.Jenis_page
  );

COMMIT TRANSACTION;
GO

/* ---------- verifikasi ---------- */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = 'administrator_pa') <> 8
    THROW 50372, 'DML-2026-085: jumlah halaman master bukan 8.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus
               WHERE Jenis_page = 'PerformanceAppraisalPage' AND Header_Code = 'performance_appraisal')
    THROW 50373, 'DML-2026-085: halaman siklus belum pindah ke headernya sendiri.', 1;

IF (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map m
    JOIN N_HRIS_Menus n ON n.Jenis_page = m.Jenis_page
    WHERE n.Header_Code = 'administrator_pa' AND m.Flag_Aktif = 'Y') <> 8
    THROW 50374, 'DML-2026-085: peta grup fitur halaman master tidak lengkap.', 1;

/* Nol akses eksplisit berarti kedelapan halaman 403 untuk semua orang. Itu harus
   terlihat, bukan lolos diam-diam. */
IF (SELECT COUNT(*) FROM HRIS_Page_Access a
    JOIN N_HRIS_Menus n ON n.Jenis_page = a.Jenis_page
    WHERE n.Header_Code = 'administrator_pa' AND a.Flag_Aktif = 'Y') = 0
    THROW 50375, 'DML-2026-085: tidak ada akses eksplisit; seluruh halaman master akan 403.', 1;
GO

/* ---------- pencatatan change ---------- */
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-085-menu-admin-performance-appraisal')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-085-menu-admin-performance-appraisal',
         'DML',
         'Header dan delapan halaman master Admin Performance Appraisal',
         'Memindahkan halaman siklus ke header sendiri dan menambah delapan halaman master beserta peta grup fitur dan akses pengembang.',
         'database/dml/DML-2026-085-menu-admin-performance-appraisal/data-change.sql',
         'docs/changes/DML-2026-085-menu-admin-performance-appraisal/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Delapan kunci halaman, bukan satu: master PA tidak setara bahayanya - menyunting nama kompetensi menyentuh satu layar, sedangkan menyunting pita grade mengubah arti nilai semua orang pada siklus berikutnya, sehingga satu kunci untuk semuanya berarti siapa pun yang butuh yang pertama otomatis mendapat yang kedua. Rute admin menerima kunci mana pun sebagai izin masuk; master mana yang boleh dibuka dijaga per master di PaMasterDataController. Halaman siklus DIPINDAHKAN dari header Manajemen KPI ke header Performance Appraisal, bukan digandakan. WAJIB diikuti InertiaShellService::flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
