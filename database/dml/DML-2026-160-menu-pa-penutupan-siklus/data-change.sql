/* Change ID: DML-2026-160-menu-pa-penutupan-siklus
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-159-menu-pa-balancing; halaman /performance-appraisal/pemantauan kini
                 "Penutupan Siklus" dengan lima tahap (spec 2026-10-02-pa-penutupan-siklus).
   Scope: N_HRIS_Menus — DUA baris (Jenis_page PaPemantauanPage dan PaKalibrasiPage).

   1. PaPemantauanPage: "Monitoring & Verifikasi" → "Penutupan Siklus" (halaman yang sama,
      kini memuat pengisian, hitung & verifikasi, kurva, balancing, rilis & terbit).
   2. PaKalibrasiPage ("Balancing") disembunyikan dari sidebar: Balancing kini tahap ke-4
      Penutupan Siklus. Halaman dan hak aksesnya tidak berubah (tetap bisa dibuka dari tahap).
   PaHasilPage sudah tersembunyi (Is_Show_Sidebar = 'T') dan tidak disentuh.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50601, 'DML-2026-160 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaPemantauanPage')
    THROW 50602, 'Butir menu PaPemantauanPage tidak ada.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaKalibrasiPage')
    THROW 50603, 'Butir menu PaKalibrasiPage tidak ada.', 1;

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Penutupan Siklus',
    Menu_Subtitle = 'Pengisian, hitung & verifikasi, kurva, balancing, rilis & terbit'
WHERE Jenis_page = 'PaPemantauanPage';

UPDATE N_HRIS_Menus
SET Is_Show_Sidebar = 'T'
WHERE Jenis_page = 'PaKalibrasiPage';

COMMIT TRANSACTION;
GO

SELECT Jenis_page, Nama_Menu, Url_Menu, Sort_Menu, Is_Show_Sidebar, Menu_Subtitle
FROM N_HRIS_Menus WHERE Jenis_page IN ('PaPemantauanPage', 'PaKalibrasiPage');

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
