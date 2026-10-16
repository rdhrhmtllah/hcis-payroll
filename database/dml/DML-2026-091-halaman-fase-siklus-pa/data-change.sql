/* Change ID: DML-2026-091-halaman-fase-siklus-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 2, N_HRIS_Grup_Fitur_Map <= 2,
                            HRIS_Page_Access <= 4, register the change <= 1.
   Dependencies: DML-2026-084 (halaman siklus PA sudah terdaftar).

   MEMECAH PENGELOLAAN SIKLUS MENJADI TIGA FASE.

   Sampai 3 September 2026 seluruh pengelolaan siklus berada di SATU halaman: 1.578 baris
   Vue yang menampung sembilan dialog dan delapan composable — menyiapkan siklus,
   menugaskan penilai, memantau pengisian, menghitung, mengkalibrasi, dan menerbitkan.

   Ketiganya dikerjakan pada WAKTU yang berbeda dan sering oleh ORANG yang berbeda:
   menyiapkan sekali di awal, memantau berminggu-minggu, memutuskan angka sesudah semuanya
   tutup. Yang memantau tidak perlu melihat tombol "kunci siklus" yang tak akan ia sentuh
   sepanjang tahun.

   PEMBAGIANNYA BUKAN KARANGAN. Ia mengikuti pengelompokan yang sudah ada di
   `PaCycleGate::IZIN` — operasi apa yang diizinkan pada status apa:

     Penyiapan   DRAFT, PREPARED      SUNTING_*, BUAT_PENUGASAN, KUNCI_SIKLUS
     Pengisian   LOCKED, OPEN         BUKA_PENGISIAN, TERIMA_PAYLOAD, TUTUP_PENGISIAN
     Hasil       CLOSED, PUBLISHED    HITUNG_HASIL, KALIBRASI, TERBITKAN

   KEPUTUSAN LAMA YANG DIHORMATI, BUKAN DIBATALKAN.

   Berkas rutenya dulu menulis: "memecahnya menjadi beberapa kunci akan melahirkan
   kombinasi hak yang tak seorang pun berniat memberikannya — misalnya orang yang boleh
   menghitung hasil tetapi tak boleh melihat peserta yang dihitungnya."

   Kekhawatiran itu sah dan tetap berlaku. Yang berubah adalah cara mendamaikannya: yang
   berbahaya adalah memecah hak MEMBACA, bukan hak MENULIS. Karena itu seluruh endpoint
   BACA digerbangi ketiga kunci sekaligus (logika salah-satu), sehingga kombinasi yang
   ditakutkan itu tetap mustahil. Yang dipecah hanya hak menulis per fase.

   AKSESNYA UNTUK SEKARANG DIBERIKAN KE ORANG YANG SAMA. Kedua kunci baru diberikan kepada
   setiap orang yang hari ini memegang `PerformanceAppraisalPage`, jadi tidak ada yang
   kehilangan kemampuan apa pun. Yang bertambah adalah KEMAMPUAN MEMBEDAKANNYA: kalau
   kelak HC ingin memisahkan "yang memantau" dari "yang menerbitkan", cukup cabut satu
   baris akses, tanpa menyentuh kode.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50395, 'DML-2026-091 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalPage')
    THROW 50396, 'DML-2026-091 membutuhkan DML-2026-084 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

/* ---------- 1. Dua halaman fase ---------- */
INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT 'PaPemantauanPage', '001', 'Pemantauan Pengisian', 'bi-activity',
       '/performance-appraisal/pemantauan',
       'performance_appraisal', 'Performance Appraisal', 'bi bi-clipboard2-data', 6,
       'T', 'self', 'Y', 3
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaPemantauanPage');

INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT 'PaHasilPage', '001', 'Hasil dan Penerbitan', 'bi-award',
       '/performance-appraisal/hasil',
       'performance_appraisal', 'Performance Appraisal', 'bi bi-clipboard2-data', 6,
       'T', 'self', 'Y', 4
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaHasilPage');

/* ---------- 2. Jalur role ---------- */
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, v.Jenis_page, 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
CROSS JOIN (VALUES ('PaPemantauanPage'), ('PaHasilPage')) AS v(Jenis_page)
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = v.Jenis_page);

/* ---------- 3. Akses: MENIRU yang sudah memegang halaman siklus ----------
   Ditiru, bukan didaftar manual. Menyebut user satu per satu berarti daftar ini
   berselisih dengan kenyataan begitu ada orang ditambahkan ke halaman siklus. */
INSERT INTO HRIS_Page_Access
    (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Created_At, Flag_Aktif)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, v.Jenis_page, a.UserID_Web,
       a.Urutan_Menu, SYSDATETIME(), 'Y'
FROM HRIS_Page_Access a
CROSS JOIN (VALUES ('PaPemantauanPage'), ('PaHasilPage')) AS v(Jenis_page)
WHERE a.Jenis_page = 'PerformanceAppraisalPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access x
      WHERE x.Jenis_page = v.Jenis_page AND x.UserID_Web = a.UserID_Web
  );

/* ---------- 4. Verifikasi ---------- */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page IN ('PaPemantauanPage','PaHasilPage')) <> 2
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50397, 'DML-2026-091: dua halaman fase tidak terpasang tepat.', 1;
END;

/* Tidak seorang pun boleh KEHILANGAN kemampuan. Jumlah pemegang tiap kunci fase wajib
   sama dengan pemegang halaman siklus — kalau kurang, ada yang tertinggal dan ia akan
   menemukan tombolnya hilang tanpa penjelasan. */
DECLARE @siklus int = (SELECT COUNT(*) FROM HRIS_Page_Access
                       WHERE Jenis_page = 'PerformanceAppraisalPage' AND Flag_Aktif = 'Y');

IF (SELECT COUNT(*) FROM HRIS_Page_Access
    WHERE Jenis_page = 'PaPemantauanPage' AND Flag_Aktif = 'Y') <> @siklus
   OR (SELECT COUNT(*) FROM HRIS_Page_Access
       WHERE Jenis_page = 'PaHasilPage' AND Flag_Aktif = 'Y') <> @siklus
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50398, 'DML-2026-091: pemegang kunci fase tidak sama dengan pemegang halaman siklus.', 1;
END;

/* ---------- 5. Catat ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-091-halaman-fase-siklus-pa')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-091-halaman-fase-siklus-pa',
         'DML',
         'Halaman fase siklus PA: pemantauan dan hasil',
         'Menambah dua halaman fase beserta aksesnya, meniru pemegang halaman siklus.',
         'database/dml/DML-2026-091-halaman-fase-siklus-pa/data-change.sql',
         'docs/changes/DML-2026-091-halaman-fase-siklus-pa/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Memecah pengelolaan siklus jadi tiga fase mengikuti PaCycleGate::IZIN, bukan pembagian karangan. Halaman siklus lama 1.578 baris menampung sembilan dialog untuk tiga pekerjaan yang berbeda waktu dan sering berbeda orang. Keputusan lama "satu kunci untuk seluruh berkas" DIHORMATI bukan dibatalkan: yang berbahaya adalah memecah hak MEMBACA, jadi seluruh endpoint baca tetap digerbangi ketiga kunci sekaligus (logika salah-satu) dan kombinasi "boleh menghitung tetapi tak boleh melihat peserta" tetap mustahil; yang dipecah hanya hak MENULIS per fase. Akses ditiru dari pemegang PerformanceAppraisalPage sehingga tidak seorang pun kehilangan kemampuan - skrip menuntut jumlahnya sama persis. WAJIB diikuti flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
