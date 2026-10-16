/* Change ID: DML-2026-161-tata-ulang-header-kpi-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   Scope: N_HRIS_Menus — pengelompokan header KPI dan PA, serta peleburan Master Data ke Administrator PA.

   1. Kelompokkan seluruh header KPI berurutan di awal:
      - Dashboard (Sort_Header: 1)
      - KPI (Sort_Header: 3)
      - Manajemen KPI (Sort_Header: 5)
      - Administrator KPI (Sort_Header: 7, sebelumnya 8)
   2. Kelompokkan seluruh header PA berurutan setelah KPI:
      - Performance Appraisal (Sort_Header: 9, sebelumnya 6)
      - Administrator PA (Sort_Header: 11)
   3. Leburkan butir 'Master Data' (pa_master) ke dalam header 'Administrator PA':
      - PaMasterGrupKurvaPage: 'Grup Level Kurva' -> 'Master · Grup Level Kurva' (Sort_Menu: 9)
      - PaMasterRelasiOrganisasiPage: 'Relasi Organisasi' -> 'Master · Relasi Organisasi' (Sort_Menu: 10)
   4. Urutkan alur operasional Administrator PA sesudah seluruh master:
      - Assessment Cycle (Sort_Menu: 11)
      - Penutupan Siklus (Sort_Menu: 12)
      - 9-Box HAV Map (Sort_Menu: 13)
      - Distribusi Performance (Sort_Menu: 14)
      - Riwayat Penilaian (Sort_Menu: 15)
      - Dashboard PA (Sort_Menu: 16)
      - Export Studio (Sort_Menu: 17)
      - Master Paket Soal [Hidden] (Sort_Menu: 18)
      - Hasil & Penerbitan [Hidden] (Sort_Menu: 19)
      - Balancing [Hidden] (Sort_Menu: 20)
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50611, 'DML-2026-161 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

-- 1. Administrator KPI: Sort_Header 8 -> 7 (berkumpul bersama KPI)
UPDATE N_HRIS_Menus
SET Sort_Header = '7'
WHERE Header_Code = 'administrator_kpi'
  AND Sort_Header <> '7';

-- 2. Performance Appraisal: Sort_Header 6 -> 9 (berkumpul bersama PA)
UPDATE N_HRIS_Menus
SET Sort_Header = '9'
WHERE Header_Code = 'performance_appraisal'
  AND Sort_Header <> '9';

-- 3. Leburkan Master Data (pa_master) ke Administrator PA dengan prefix 'Master · '
UPDATE N_HRIS_Menus
SET Nama_Menu = 'Master · Grup Level Kurva',
    Header_Code = 'administrator_pa',
    Header_Label = 'Administrator PA',
    Header_Icon = 'bi bi-sliders',
    Sort_Header = '11',
    Sort_Menu = '9'
WHERE Jenis_page = 'PaMasterGrupKurvaPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Master · Relasi Organisasi',
    Header_Code = 'administrator_pa',
    Header_Label = 'Administrator PA',
    Header_Icon = 'bi bi-sliders',
    Sort_Header = '11',
    Sort_Menu = '10'
WHERE Jenis_page = 'PaMasterRelasiOrganisasiPage';

-- 4. Rapikan urutan menu operasional Administrator PA sesudah menu-menu master (Sort_Menu 1-10)
UPDATE N_HRIS_Menus SET Sort_Menu = '11' WHERE Jenis_page = 'PerformanceAppraisalPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '12' WHERE Jenis_page = 'PaPemantauanPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '13' WHERE Jenis_page = 'PaHavMapPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '14' WHERE Jenis_page = 'PaDistribusiPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '15' WHERE Jenis_page = 'PaRiwayatPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '16' WHERE Jenis_page = 'PaDashboardPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '17' WHERE Jenis_page = 'PaExportStudioPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '18' WHERE Jenis_page = 'PaMasterPaketSoalPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '19' WHERE Jenis_page = 'PaHasilPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '20' WHERE Jenis_page = 'PaKalibrasiPage';

COMMIT TRANSACTION;
GO

SELECT Jenis_page, Nama_Menu, Header_Code, Header_Label, Sort_Header, Sort_Menu, Is_Show_Sidebar
FROM N_HRIS_Menus
WHERE Header_Code IN ('administrator_kpi', 'performance_appraisal', 'administrator_pa', 'pa_master')
ORDER BY CAST(Sort_Header AS INT), CAST(Sort_Menu AS INT);

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
