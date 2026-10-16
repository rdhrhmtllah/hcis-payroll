/* Change ID: DML-2026-115-label-menu-bobot-kpi-kompetensi
   Target: Web_HRIS development; production only through SQL Tracker.
   Scope: N_HRIS_Menus — kolom `Nama_Menu` dan `Menu_Subtitle` pada menu master bobot pilar
          (/performance-appraisal/master/bobot-pilar) serta penyelarasan nomor urut Sort_Menu.
          Memperjelas nama menu menjadi "Master · Bobot KPI & Kompetensi" agar lebih eksplisit,
          lazim di dunia HR, dan mudah dipahami pengguna tanpa istilah pilar yang abstrak.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50580, 'DML-2026-115 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Bobot KPI & Kompetensi',
    [Menu_Subtitle] = 'Proporsi pembagian bobot capaian KPI vs evaluasi Kompetensi per level jabatan'
WHERE [Header_Code] = 'administrator_pa'
  AND [Url_Menu] = '/performance-appraisal/master/bobot-pilar';

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
        ('/performance-appraisal/hasil',                    '10'),
        ('/performance-appraisal/hav-map',                  '11'),
        ('/performance-appraisal/distribusi',               '12'),
        ('/performance-appraisal/riwayat',                  '13'),
        ('/performance-appraisal/dashboard',                '14')
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
DECLARE @namaMenu nvarchar(100) = (
    SELECT Nama_Menu FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa'
      AND Url_Menu = '/performance-appraisal/master/bobot-pilar'
);

IF @namaMenu <> 'Master · Bobot KPI & Kompetensi'
    THROW 50581, 'Penyelarasan nama menu Master · Bobot KPI & Kompetensi belum berhasil.', 1;

SELECT Sort_Menu, Nama_Menu, Url_Menu, Menu_Subtitle, Is_Show_Sidebar
FROM N_HRIS_Menus
WHERE Header_Code = 'administrator_pa'
ORDER BY CASE WHEN Is_Show_Sidebar = 'Y' THEN 0 ELSE 1 END, CAST(Sort_Menu AS int);
