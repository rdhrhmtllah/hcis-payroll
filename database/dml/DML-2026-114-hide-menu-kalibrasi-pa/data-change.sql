/* Change ID: DML-2026-114-hide-menu-kalibrasi-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   Scope: N_HRIS_Menus — kolom `Is_Show_Sidebar` dan `Sort_Menu` pada header `administrator_pa`.
          Menyembunyikan menu Kalibrasi Grade (/performance-appraisal/kalibrasi) karena
          alur kalibrasi telah terintegrasi langsung ke dalam halaman Pemantauan & Verifikasi.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50570, 'DML-2026-114 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* 1. Sembunyikan menu Kalibrasi Grade */
UPDATE [N_HRIS_Menus]
SET [Is_Show_Sidebar] = 'T'
WHERE [Header_Code] = 'administrator_pa'
  AND [Url_Menu] = '/performance-appraisal/kalibrasi';

/* 2. Rapatkan nomor urut menu yang tampil */
;WITH urutan(Url_Menu, Sort_Menu) AS (
    SELECT v.Url_Menu, v.Sort_Menu
    FROM (VALUES
        ('/performance-appraisal/master/jenis-kompetensi',  '1'),
        ('/performance-appraisal/master/kompetensi',        '2'),
        ('/performance-appraisal/master/syarat-kompetensi', '3'),
        ('/performance-appraisal/master/ikatan-paket-soal', '4'),
        ('/performance-appraisal/master/aturan-penilai',    '5'),
        ('/performance-appraisal/master/bobot-pilar',       '6'),
        ('/performance-appraisal/master/pita-nilai',        '7'),
        ('/performance-appraisal',                          '8'),
        ('/performance-appraisal/pemantauan',               '9'),
        ('/performance-appraisal/hav-map',                  '10'),
        ('/performance-appraisal/distribusi',               '11'),
        ('/performance-appraisal/riwayat',                  '12'),
        ('/performance-appraisal/dashboard',                '13')
    ) AS v(Url_Menu, Sort_Menu)
)
UPDATE m
SET m.Sort_Menu = u.Sort_Menu
FROM [N_HRIS_Menus] m
JOIN urutan u ON u.Url_Menu = m.Url_Menu
WHERE m.[Header_Code] = 'administrator_pa';

COMMIT TRANSACTION;
GO

/* Penjaga */
DECLARE @isKalibrasiHidden char(1) = (
    SELECT Is_Show_Sidebar FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa'
      AND Url_Menu = '/performance-appraisal/kalibrasi'
);

IF @isKalibrasiHidden <> 'T'
    THROW 50571, 'Menu Kalibrasi Grade belum tersembunyi.', 1;

SELECT Sort_Menu, Nama_Menu, Url_Menu, Is_Show_Sidebar
FROM N_HRIS_Menus
WHERE Header_Code = 'administrator_pa'
ORDER BY CASE WHEN Is_Show_Sidebar = 'Y' THEN 0 ELSE 1 END, CAST(Sort_Menu AS int);
