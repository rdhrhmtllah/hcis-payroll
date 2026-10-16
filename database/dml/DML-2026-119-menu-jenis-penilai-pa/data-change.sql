/* Change ID: DML-2026-119-menu-jenis-penilai-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 1 disisipkan, N_HRIS_Grup_Fitur_Map <= 1,
                            HRIS_Page_Access <= jumlah pemegang PerformanceAppraisalPage,
                            register the change <= 1.
   Dependencies: DML-2026-093 (grup menu Administrator PA sudah tertata).

   HALAMAN YANG SUDAH JADI TAPI TIDAK PUNYA PINTU.

   Master "Jenis Penilai" (`N_PA_Rater_Category` — Atasan, Bawahan, Rekan Sejawat, Diri
   Sendiri) sudah berjalan penuh di kedua sisi: `PaMasterReadService::katalog()` memuat
   slug `kategori-penilai` berikut halaman `PaMasterKategoriPenilaiPage`,
   `PaMasterWriteService::simpanKategoriPenilai()` melayani penyuntingannya, slug-nya sudah
   ada di daftar izin `paApi`, fixture-nya ada, dan ujinya lulus me-mount halamannya.

   Yang tidak pernah ada hanya pintunya: nol baris di `N_HRIS_Menus`, nol di
   `N_HRIS_Grup_Fitur_Map`, nol di `HRIS_Page_Access`. Satu-satunya cara membukanya adalah
   mengetik `/performance-appraisal/master/kategori-penilai` di bilah alamat.

   YANG BISA DIUBAH DI SANA SENGAJA SEMPIT, dan itu sudah ditegakkan server jauh sebelum
   menu ini ada — bukan sesuatu yang perlu dijaga oleh menunya:

     - `dapat_ditambah = false`      -> kategori baru DITOLAK. Kategori tanpa aturan di
                                       setiap level hanya menghasilkan kategori yang tak
                                       pernah muncul.
     - `dapat_dinonaktifkan = false` -> menonaktifkan DITOLAK. Ia memutus aturan penilai
                                       yang sedang dipakai siklus berjalan.
     - `Kode_Kategori` tidak pernah ditulis penyimpannya -> kodenya terkunci. Ia kunci
                                       sambung di salinan siklus, penugasan, dan jejak
                                       hasil; keempat kodenya juga disebut langsung di 14
                                       tempat lintas enam service. Mengubahnya memutus
                                       pembangunan penugasan TANPA satu pun galat terlihat.

   Jadi yang dibuka menu ini hanyalah menyunting NAMA dan DESKRIPSI keempat kategori itu —
   pekerjaan yang selama ini hanya bisa dilakukan lewat basis data.

   AKSESNYA DITIRU, TIDAK DIDAFTAR MANUAL. Halaman ini diberikan kepada setiap orang yang
   hari ini memegang `PerformanceAppraisalPage`. Mendaftar manual berarti daftarnya
   berselisih dengan kenyataan begitu ada orang ditambahkan.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50419, 'DML-2026-119 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterAturanPenilaiPage')
    THROW 50420, 'DML-2026-119 membutuhkan DML-2026-093 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

/* ---------- 1. Baris menu ----------

   Ditaruh TEPAT SEBELUM "Komposisi Penilai" (Sort_Menu 5): jenis penilainya dibaca lebih
   dulu, komposisinya menyusul. Karena itu tetangga-tetangganya yang bernomor >= 5 digeser
   satu, bukan menu baru ini yang diselipkan dengan nomor kembar — nomor kembar membuat
   urutannya ditentukan oleh apa pun yang kebetulan dibaca lebih dulu. */

UPDATE N_HRIS_Menus
SET Sort_Menu = Sort_Menu + 1
WHERE Header_Code = 'administrator_pa'
  AND Sort_Header = (SELECT TOP 1 Sort_Header FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterAturanPenilaiPage')
  AND Sort_Menu >= (SELECT TOP 1 Sort_Menu FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterAturanPenilaiPage');

INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu, Menu_Subtitle)
SELECT
    'PaMasterKategoriPenilaiPage',
    m.Kode_Perusahaan,
    N'Master · Jenis Penilai',
    'bi-person-badge-fill',
    '/performance-appraisal/master/kategori-penilai',
    m.Header_Code,
    m.Header_Label,
    m.Header_Icon,
    m.Sort_Header,
    'T',
    'self',
    'Y',
    m.Sort_Menu - 1,
    N'Empat kategori penilai 360°; kodenya terkunci, nama dan deskripsinya bisa disunting'
FROM N_HRIS_Menus AS m
WHERE m.Jenis_page = 'PaMasterAturanPenilaiPage'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterKategoriPenilaiPage');

/* ---------- 2. Pemetaan grup fitur ----------
   Mengikuti grup yang sama dengan tetangganya, bukan angka yang ditulis tangan. */

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT DISTINCT g.Id_Grup_Fitur, 'PaMasterKategoriPenilaiPage', 'T', 'Y', 'Y'
FROM N_HRIS_Grup_Fitur_Map AS g
WHERE g.Jenis_page = 'PaMasterAturanPenilaiPage'
  AND g.Flag_Aktif = 'Y'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaMasterKategoriPenilaiPage');

/* ---------- 3. Hak akses, DITIRU dari pemegang PerformanceAppraisalPage ---------- */

INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, 'PaMasterKategoriPenilaiPage', a.UserID_Web, a.Urutan_Menu, 'Y'
FROM HRIS_Page_Access AS a
WHERE a.Jenis_page = 'PerformanceAppraisalPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
        SELECT 1 FROM HRIS_Page_Access AS b
        WHERE b.Jenis_page = 'PaMasterKategoriPenilaiPage'
          AND b.UserID_Web = a.UserID_Web
  );

/* ---------- 4. Penjaga cakupan ---------- */

DECLARE @menu int = (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterKategoriPenilaiPage');
DECLARE @map  int = (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaMasterKategoriPenilaiPage');
DECLARE @akses int = (SELECT COUNT(*) FROM HRIS_Page_Access WHERE Jenis_page = 'PaMasterKategoriPenilaiPage' AND Flag_Aktif = 'Y');
DECLARE @pemegang int = (SELECT COUNT(DISTINCT UserID_Web) FROM HRIS_Page_Access WHERE Jenis_page = 'PerformanceAppraisalPage' AND Flag_Aktif = 'Y');

IF @menu <> 1 OR @map < 1 OR @akses <> @pemegang
BEGIN
    ROLLBACK TRANSACTION;
    THROW 51419, 'DML-2026-119 dibatalkan: cakupan tidak sesuai harapan (menu/map/akses).', 1;
END;

COMMIT TRANSACTION;
GO

/* ---------- 5. Pendaftaran perubahan ---------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-119-menu-jenis-penilai-pa'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-119-menu-jenis-penilai-pa',
         'DML',
         'Menu Master Jenis Penilai PA',
         'Halaman master kategori penilai (PaMasterKategoriPenilaiPage) sudah berjalan penuh di backend dan frontend tetapi tidak punya satu pun baris menu, pemetaan grup fitur, maupun hak akses - hanya bisa dibuka dengan mengetik URL-nya. DML ini memberinya pintu.',
         'database/dml/DML-2026-119-menu-jenis-penilai-pa/data-change.sql',
         'database/dml/DML-2026-119-menu-jenis-penilai-pa/data-change.sql',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Yang dibuka hanya penyuntingan NAMA dan DESKRIPSI keempat kategori. Penambahan, penonaktifan, dan perubahan Kode_Kategori sudah ditolak server sejak awal (dapat_ditambah=false, dapat_dinonaktifkan=false, penyimpannya tidak pernah menulis kodenya). WAJIB diikuti flushMenuCache().',
         'performance-appraisal');
END;
GO
