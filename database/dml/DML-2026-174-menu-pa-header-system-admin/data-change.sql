/* Change ID: DML-2026-174-menu-pa-header-system-admin
   Target: Web_HRIS development; production only through SQL Tracker.
   Scope: N_HRIS_Menus — SATU baris (Jenis_page PaMasterAturanPenilaiPage), kolom header saja.

   Menu "Master · Komposisi Penilai" (/performance-appraisal/master/aturan-penilai) dipindah dari
   header "Administrator PA" ke header BARU "System Admin PA" (Sort_Header 12, tepat sesudah
   Administrator PA). Isinya — jenis relasi per kategori, catatan, dan versi aturan — adalah
   konfigurasi struktural yang diatur sekali; bobot dan kuota harian tetap di Jenis Penilai.
   Pasangan kode: PaJenisPenilaiMatriksService::setelSel kini mengisi jenis relasi otomatis,
   sehingga admin PA tidak bergantung pada menu yang dipindah ini.
   Hak akses halaman (PaMasterAturanPenilaiPage) TIDAK diubah.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50740, 'DML-2026-174 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterAturanPenilaiPage')
    THROW 50741, 'Butir menu PaMasterAturanPenilaiPage tidak ada.', 1;

BEGIN TRANSACTION;

UPDATE N_HRIS_Menus
SET Header_Code = 'system_admin_pa',
    Header_Label = 'System Admin PA',
    Header_Icon = 'bi bi-shield-lock-fill',
    Sort_Header = 12,
    Sort_Menu = 1
WHERE Jenis_page = 'PaMasterAturanPenilaiPage'
  AND (ISNULL(Header_Code, '') <> 'system_admin_pa' OR ISNULL(Sort_Header, -1) <> 12);

COMMIT TRANSACTION;
GO

SELECT Jenis_page, Nama_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Sort_Menu
FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterAturanPenilaiPage';

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
