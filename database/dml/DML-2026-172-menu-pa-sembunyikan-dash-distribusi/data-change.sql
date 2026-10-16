/* Change ID: DML-2026-172-menu-pa-sembunyikan-dash-distribusi
   Target: Web_HRIS development; production only through SQL Tracker.
   Scope: N_HRIS_Menus — TIGA baris (Jenis_page PaDashboardPage, PaDistribusiPage, PaPemantauanPage).

   1. PaDashboardPage ("Dash Hidden") dan PaDistribusiPage ("Distribusi Hidden") disembunyikan
      dari sidebar (Is_Show_Sidebar = 'T'), sama seperti Balancing pada DML-2026-160. Baris,
      halaman, dan hak aksesnya TIDAK dihapus: halaman tetap bisa dibuka dari tautan di dalam
      modul PA, dan menunya bisa ditampilkan lagi dengan membalik nilai ini.
   2. PaPemantauanPage ("Monitoring Cycle") diberi Active_Match_Pattern
      '/performance-appraisal/balancing*'. Balancing adalah tahap ke-4 Monitoring Cycle dan
      menunya tersembunyi, sehingga tanpa pola ini halaman sesi Balancing menyorot menu induk
      "Assessment Cycle". Hanya diisi bila kolomnya masih kosong — pola yang sudah diatur
      orang lain tidak ditimpa.

   Pasangan kode: InertiaShellService::satuMenuAktif() — satu menu aktif per modul, yang
   kecocokannya paling spesifik.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50720, 'DML-2026-172 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaPemantauanPage')
    THROW 50721, 'Butir menu PaPemantauanPage tidak ada.', 1;

UPDATE N_HRIS_Menus
SET Is_Show_Sidebar = 'T'
WHERE Jenis_page IN ('PaDashboardPage', 'PaDistribusiPage')
  AND ISNULL(Is_Show_Sidebar, 'Y') <> 'T';

UPDATE N_HRIS_Menus
SET Active_Match_Pattern = '/performance-appraisal/balancing*'
WHERE Jenis_page = 'PaPemantauanPage'
  AND NULLIF(LTRIM(RTRIM(Active_Match_Pattern)), '') IS NULL;

COMMIT TRANSACTION;
GO

SELECT Jenis_page, Nama_Menu, Url_Menu, Is_Show_Sidebar, Active_Match_Pattern
FROM N_HRIS_Menus WHERE Jenis_page IN ('PaDashboardPage', 'PaDistribusiPage', 'PaPemantauanPage');

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
