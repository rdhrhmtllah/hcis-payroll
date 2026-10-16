/* Change ID: DML-2026-159-menu-pa-balancing
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-165-pa-balancing; kode halaman /performance-appraisal/balancing
                 (route performance-appraisal.balancing, check.jabatan:PaHasilPage).
   Scope: N_HRIS_Menus — SATU baris (Jenis_page = 'PaKalibrasiPage').

   Butir menu "Kalibrasi Grade" (tersembunyi sejak halamannya dialihkan) dialihkan menjadi
   "Balancing" dan ditampilkan. Jenis_page tetap PaKalibrasiPage: pemetaan grup fitur (KPI) dan
   pemegang HRIS_Page_Access-nya (UserID_Web 4 dan 622 di dev, 30 Sep 2026) sudah ada, dan sama
   persis dengan pemegang PaHasilPage — kunci rute halaman Balancing.
   Sort_Menu 11: nomor tampil yang kosong di header administrator_pa (12 dipakai HAV Map).
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50591, 'DML-2026-159 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaKalibrasiPage')
    THROW 50592, 'Butir menu PaKalibrasiPage tidak ada; tidak ada yang bisa dialihkan.', 1;

/* Pemegang PaKalibrasiPage harus juga pemegang PaHasilPage; kalau tidak, menunya tampil
   tetapi halamannya menolak (403). */
IF EXISTS (
    SELECT 1 FROM HRIS_Page_Access k
    WHERE k.Jenis_page = 'PaKalibrasiPage' AND k.Flag_Aktif = 'Y' AND k.UserID_Web IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM HRIS_Page_Access h WHERE h.Jenis_page = 'PaHasilPage' AND h.Flag_Aktif = 'Y' AND h.UserID_Web = k.UserID_Web)
)
    THROW 50593, 'Ada pemegang PaKalibrasiPage yang tidak memegang PaHasilPage.', 1;

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Balancing',
    Url_Menu = '/performance-appraisal/balancing',
    Route_Name = 'performance-appraisal.balancing',
    Is_Show_Sidebar = 'Y',
    Sort_Menu = '11',
    Menu_Subtitle = 'Sesi forum penyeimbangan skor kompetensi sesudah Calculate'
WHERE Jenis_page = 'PaKalibrasiPage';

IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa' AND Is_Show_Sidebar = 'Y'
    GROUP BY Kode_Perusahaan, Sort_Menu HAVING COUNT(*) > 1
)
    THROW 50594, 'Ada urutan menu kembar di antara menu yang tampil pada header Administrator PA.', 1;

COMMIT TRANSACTION;
GO

SELECT Jenis_page, Nama_Menu, Url_Menu, Route_Name, Sort_Menu, Is_Show_Sidebar FROM N_HRIS_Menus WHERE Jenis_page = 'PaKalibrasiPage';

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
