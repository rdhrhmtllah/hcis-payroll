/* Change ID: DML-2026-093-tata-menu-pa-ikut-mockup
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 6 disisipkan dan <= 11 diperbarui,
                            N_HRIS_Grup_Fitur_Map <= 6, HRIS_Page_Access <= 12,
                            register the change <= 1.
   Dependencies: DML-2026-092 (tata ulang header sebelumnya sudah dijalankan).

   MENATA MENU ADMIN MENGIKUTI ALUR MOCKUP.

   Mockup merancang sebelas halaman admin dalam TIGA grup. Yang berjalan sekarang hanya
   sembilan butir dalam SATU grup, dan tujuh di antaranya — yang mockup rancang sebagai
   halaman tersendiri — masih berupa dialog di dalam `siklus.vue` yang berukuran 1.578
   baris.

   Itu sebabnya pengalaman adminnya buruk: sebelas pekerjaan berbeda dijejalkan ke satu
   layar, dijalankan sekali-dua kali setahun, tanpa apa pun yang menunjukkan urutannya.

   Endpoint datanya SUDAH ADA sejak lama dan sudah berkontrak. Yang ditambahkan di sini
   hanya halaman dan menunya.

   SUSUNAN SESUDAH PERUBAHAN

     Performance Appraisal   (global — seluruh karyawan)
       1. Penilaian Saya

     Ringkasan
       1. Dashboard Admin            BARU
       2. Monitoring Progress        pindah + ganti nama

     Konfigurasi Cycle
       1. Assessment Cycle           pindah + ganti nama
       2. Assignment Penilai         BARU
       3. Bobot Penilaian            pindah + ganti nama (dari master Aturan Penilai)
       4. Pengaturan KPI             pindah + ganti nama (dari master Bobot Pilar)

     Hasil
       1. Hasil Performance          pindah + ganti nama
       2. Kalibrasi Grade Akhir      BARU
       3. HAV Map (9-Box)            BARU
       4. Distribusi Performance     BARU
       5. Riwayat Penilaian          BARU

     Master Data
       1. Jenis Kompetensi           pindah
       2. Kompetensi                 pindah
       3. Pita Nilai                 pindah
       4. Paket Soal                 pindah

   EMPAT GRUP, BUKAN TIGA. Mockup tidak menampilkan halaman master sama sekali, tetapi
   keempatnya tetap dibutuhkan. Ditaruh di grup keempat paling bawah: alur mockup dihormati
   utuh, dan yang jarang disentuh tidak mengotori tiga grup yang mockup rancang.

   MENU LEADER DICABUT. Mockup hanya mengenal dua peran — Peserta dan Administrator.
   Halaman dan endpoint-nya TIDAK dihapus dan tetap berfungsi lewat alamat langsung;
   yang dicabut hanya baris menunya. Keputusan pemilik proyek 3 September 2026.

   AKSES DITIRU, TIDAK DIDAFTAR MANUAL. Keenam halaman baru diberikan kepada setiap orang
   yang hari ini memegang `PerformanceAppraisalPage`. Mendaftar manual berarti daftarnya
   berselisih dengan kenyataan begitu ada orang ditambahkan.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50410, 'DML-2026-093 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaHasilPage')
    THROW 50411, 'DML-2026-093 membutuhkan DML-2026-091 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

DECLARE @ikon varchar(100) = 'bi bi-clipboard2-data';

/* ---------- 1. Enam halaman baru ---------- */
DECLARE @baru TABLE (
    Jenis_page varchar(100), Nama_Menu nvarchar(200), Icon_Menu varchar(100),
    Url_Menu nvarchar(300), Header_Code varchar(100), Header_Label nvarchar(200),
    Sort_Header int, Sort_Menu int
);

INSERT INTO @baru VALUES
 ('PaDashboardPage',   N'Dashboard Admin',        'bi-speedometer2', '/performance-appraisal/dashboard',   'pa_ringkasan',   N'Ringkasan',         11, 1),
 ('PaAssignmentPage',  N'Assignment Penilai',     'bi-diagram-3',    '/performance-appraisal/penilai',     'pa_konfigurasi', N'Konfigurasi Cycle', 12, 2),
 ('PaKalibrasiPage',   N'Kalibrasi Grade Akhir',  'bi-sliders',      '/performance-appraisal/kalibrasi',   'pa_hasil',       N'Hasil',             13, 2),
 ('PaHavMapPage',      N'HAV Map (9-Box)',        'bi-grid-3x3',     '/performance-appraisal/hav-map',     'pa_hasil',       N'Hasil',             13, 3),
 ('PaDistribusiPage',  N'Distribusi Performance', 'bi-graph-up',     '/performance-appraisal/distribusi',  'pa_hasil',       N'Hasil',             13, 4),
 ('PaRiwayatPage',     N'Riwayat Penilaian',      'bi-clock-history','/performance-appraisal/riwayat',     'pa_hasil',       N'Hasil',             13, 5);

INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT b.Jenis_page, '001', b.Nama_Menu, b.Icon_Menu, b.Url_Menu,
       b.Header_Code, b.Header_Label, @ikon, b.Sort_Header,
       'T', 'self', 'Y', b.Sort_Menu
FROM @baru b
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus m WHERE m.Jenis_page = b.Jenis_page);

/* ---------- 2. Halaman lama: pindah grup, ganti nama, urut ulang ---------- */
DECLARE @pindah TABLE (
    Jenis_page varchar(100), Nama_Menu nvarchar(200),
    Header_Code varchar(100), Header_Label nvarchar(200), Sort_Header int, Sort_Menu int
);

INSERT INTO @pindah VALUES
 ('PaPemantauanPage',            N'Monitoring Progress',  'pa_ringkasan',   N'Ringkasan',         11, 2),
 ('PerformanceAppraisalPage',    N'Assessment Cycle',     'pa_konfigurasi', N'Konfigurasi Cycle', 12, 1),
 ('PaMasterAturanPenilaiPage',   N'Bobot Penilaian',      'pa_konfigurasi', N'Konfigurasi Cycle', 12, 3),
 ('PaMasterBobotPilarPage',      N'Pengaturan KPI',       'pa_konfigurasi', N'Konfigurasi Cycle', 12, 4),
 ('PaHasilPage',                 N'Hasil Performance',    'pa_hasil',       N'Hasil',             13, 1),
 ('PaMasterJenisKompetensiPage', N'Jenis Kompetensi',     'pa_master',      N'Master Data',       14, 1),
 ('PaMasterKompetensiPage',      N'Kompetensi',           'pa_master',      N'Master Data',       14, 2),
 ('PaMasterPitaNilaiPage',       N'Pita Nilai',           'pa_master',      N'Master Data',       14, 3),
 ('PaMasterPaketSoalPage',       N'Paket Soal',           'pa_master',      N'Master Data',       14, 4);

UPDATE m
SET m.Nama_Menu    = p.Nama_Menu,
    m.Header_Code  = p.Header_Code,
    m.Header_Label = p.Header_Label,
    m.Header_Icon  = @ikon,
    m.Sort_Header  = p.Sort_Header,
    m.Sort_Menu    = p.Sort_Menu
FROM N_HRIS_Menus m
JOIN @pindah p ON p.Jenis_page = m.Jenis_page;

/* ---------- 3. Menu Leader dicabut ----------
   Halaman dan endpoint-nya TIDAK dihapus; yang dicabut hanya barisnya dari sidebar. */
DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';
DELETE FROM HRIS_Page_Access WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';
DELETE FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';

/* ---------- 4. Jalur role untuk halaman baru ---------- */
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, b.Jenis_page, 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
CROSS JOIN @baru b
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = b.Jenis_page);

/* ---------- 5. Akses: MENIRU pemegang halaman siklus ---------- */
INSERT INTO HRIS_Page_Access
    (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Created_At, Flag_Aktif)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, b.Jenis_page, a.UserID_Web,
       a.Urutan_Menu, SYSDATETIME(), 'Y'
FROM HRIS_Page_Access a
CROSS JOIN @baru b
WHERE a.Jenis_page = 'PerformanceAppraisalPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access x
      WHERE x.Jenis_page = b.Jenis_page AND x.UserID_Web = a.UserID_Web
  );

/* ---------- 6. Verifikasi ---------- */

/* Empat grup admin, dengan jumlah yang tepat. Grup yang jumlahnya meleset berarti ada
   halaman tertinggal di grup lamanya — dan yang tertinggal tidak akan mengeluh, ia hanya
   muncul di tempat yang salah. */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = 'pa_ringkasan') <> 2
   OR (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = 'pa_konfigurasi') <> 4
   OR (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = 'pa_hasil') <> 5
   OR (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = 'pa_master') <> 4
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50412, 'DML-2026-093: jumlah halaman per grup tidak sesuai (2/4/5/4).', 1;
END;

/* Grup lama harus KOSONG — kalau masih terisi, pemindahannya belum tuntas. */
IF EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Header_Code = 'administrator_pa')
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50413, 'DML-2026-093: masih ada halaman tertinggal di grup administrator_pa.', 1;
END;

/* Grup karyawan menyisakan Penilaian Saya saja, dan ia wajib global. */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = 'performance_appraisal') <> 1
   OR EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Header_Code = 'performance_appraisal' AND Is_Global_Menu <> 'Y')
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50414, 'DML-2026-093: grup karyawan bukan berisi tepat satu halaman global.', 1;
END;

/* Tak satu pun halaman admin boleh global — yang diam-diam global akan tampil bagi 958
   orang yang tidak bisa memakainya. */
IF EXISTS (SELECT 1 FROM N_HRIS_Menus
           WHERE Header_Code IN ('pa_ringkasan','pa_konfigurasi','pa_hasil','pa_master')
             AND Is_Global_Menu = 'Y')
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50415, 'DML-2026-093: ada halaman admin ber-Is_Global_Menu=Y.', 1;
END;

/* Urutan dalam tiap grup wajib unik — urutan kembar membuat susunan sidebar bergantung
   pada urutan baris, yang tidak dijamin. */
IF EXISTS (
    SELECT Header_Code FROM N_HRIS_Menus
    WHERE Header_Code IN ('pa_ringkasan','pa_konfigurasi','pa_hasil','pa_master')
    GROUP BY Header_Code, Sort_Menu HAVING COUNT(*) > 1
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50416, 'DML-2026-093: ada urutan menu kembar dalam satu grup.', 1;
END;

/* Tidak seorang pun kehilangan kemampuan: keenam halaman baru wajib dipegang orang yang
   sama banyaknya dengan pemegang halaman siklus. */
DECLARE @siklus int = (SELECT COUNT(*) FROM HRIS_Page_Access
                       WHERE Jenis_page = 'PerformanceAppraisalPage' AND Flag_Aktif = 'Y');

IF EXISTS (
    SELECT b.Jenis_page FROM @baru b
    WHERE (SELECT COUNT(*) FROM HRIS_Page_Access x
           WHERE x.Jenis_page = b.Jenis_page AND x.Flag_Aktif = 'Y') <> @siklus
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50417, 'DML-2026-093: pemegang halaman baru tidak sama dengan pemegang halaman siklus.', 1;
END;

/* ---------- 7. Catat ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-093-tata-menu-pa-ikut-mockup')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-093-tata-menu-pa-ikut-mockup',
         'DML',
         'Tata menu admin PA mengikuti alur mockup',
         'Menambah enam halaman, menata sebelas butir menjadi empat grup, mencabut menu Leader.',
         'database/dml/DML-2026-093-tata-menu-pa-ikut-mockup/data-change.sql',
         'docs/changes/DML-2026-093-tata-menu-pa-ikut-mockup/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Mockup merancang sebelas halaman admin dalam tiga grup; yang berjalan hanya sembilan butir dalam satu grup, dan tujuh di antaranya masih berupa dialog di dalam siklus.vue yang berukuran 1.578 baris. Itu sebab pengalaman adminnya buruk. Endpoint datanya SUDAH ADA dan berkontrak - yang ditambahkan hanya halaman dan menunya. Empat grup bukan tiga: mockup tidak menampilkan master sama sekali, tetapi keempatnya tetap dibutuhkan dan ditaruh di grup terbawah supaya tiga grup rancangan mockup tidak terkotori. Menu Leader DICABUT mengikuti mockup yang hanya mengenal dua peran; halaman dan endpoint-nya tidak dihapus dan tetap berfungsi lewat alamat langsung. Akses ditiru dari pemegang PerformanceAppraisalPage. Skrip menuntut jumlah per grup 2/4/5/4, grup lama kosong, grup karyawan tepat satu dan global, nol halaman admin global, nol urutan kembar, dan jumlah pemegang halaman baru sama dengan pemegang halaman siklus. WAJIB diikuti flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
