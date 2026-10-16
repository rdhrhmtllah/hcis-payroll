/* Change ID: DML-2026-107-urutan-menu-pa-mengikuti-alur
   Revision: r2
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-089, DML-2026-099, DML-2026-101 (pendaftaran menu PA).
   Scope: N_HRIS_Menus — kolom `Sort_Menu` dan `Is_Show_Sidebar` pada header `administrator_pa`.

   YANG DISELESAIKAN

   1. Menyembunyikan menu legacy 'Master · Paket Soal' (/performance-appraisal/master/paket-soal)
      dengan Is_Show_Sidebar = 'T' karena bank soal dikelola terpusat dari HC Learn dan
      pemetaan default per level telah dicover oleh 'Master · Paket Soal Baku'
      (/performance-appraisal/master/ikatan-paket-soal).
   2. Mengurutkan ulang 15 menu aktif administrator_pa mengikuti alur kerja nyata:
      Master (7 menu) -> Siklus -> Pemantauan -> Hasil -> Distribusi -> HAV Map -> Kalibrasi -> Riwayat -> Dashboard.

   URUTAN BARU MENGIKUTI KETERGANTUNGAN DATA, BUKAN SELERA

   Tujuh menu master didahulukan, dan di antara mereka pun berurutan: jenis kompetensi
   sebelum kompetensi (induk sebelum anak), dimensi per level sesudah kompetensi (ia
   menunjuk jenisnya), aturan penilai dan bobot pilar sebelum pita nilai, dan paket soal default.
   Sesudah itu urutannya mengikuti status siklus: siklus dibuat, dipantau, dihitung,
   disebar, dikalibrasi, lalu ditutup dengan riwayat dan dashboard.

   SESUDAH DIJALANKAN

   Menu disajikan dari cache. Jalankan `php artisan cache:clear`, atau tunggu cache-nya
   kedaluwarsa, kalau tidak urutan lama masih terlihat walaupun datanya sudah benar.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50545, 'DML-2026-107 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

-- 1. Sembunyikan menu paket-soal legacy dari sidebar
UPDATE N_HRIS_Menus
SET Is_Show_Sidebar = 'T'
WHERE Header_Code = 'administrator_pa'
  AND Url_Menu = '/performance-appraisal/master/paket-soal'
  AND Is_Show_Sidebar <> 'T';

-- 2. Urutkan 15 menu aktif
;WITH urutan(Url_Menu, Sort_Menu) AS (
    SELECT v.Url_Menu, v.Sort_Menu
    FROM (VALUES
        /* --- Fase A: master, diisi sekali dan dipakai semua siklus ---------- */
        ('/performance-appraisal/master/jenis-kompetensi',  1),
        ('/performance-appraisal/master/kompetensi',        2),
        ('/performance-appraisal/master/syarat-kompetensi', 3),
        ('/performance-appraisal/master/aturan-penilai',    4),
        ('/performance-appraisal/master/bobot-pilar',       5),
        ('/performance-appraisal/master/pita-nilai',        6),
        ('/performance-appraisal/master/ikatan-paket-soal', 7),

        /* --- Fase B-C: siklus dibuat dan disiapkan -------------------------- */
        ('/performance-appraisal',                          8),

        /* --- Fase D-E: dikunci, dibuka, lalu dipantau ----------------------- */
        ('/performance-appraisal/pemantauan',               9),

        /* --- Fase F: ditutup dan dihitung ----------------------------------- */
        ('/performance-appraisal/hasil',                    10),

        /* --- Fase G: dibaca, dikalibrasi, diterbitkan ----------------------- */
        ('/performance-appraisal/distribusi',               11),
        ('/performance-appraisal/hav-map',                  12),
        ('/performance-appraisal/kalibrasi',                13),

        /* --- Fase H: sesudah terbit ----------------------------------------- */
        ('/performance-appraisal/riwayat',                  14),
        ('/performance-appraisal/dashboard',                15)
    ) AS v(Url_Menu, Sort_Menu)
)
UPDATE m
SET m.Sort_Menu = u.Sort_Menu
FROM N_HRIS_Menus m
JOIN urutan u ON u.Url_Menu = m.Url_Menu
WHERE m.Header_Code = 'administrator_pa'
  AND m.Sort_Menu <> u.Sort_Menu;

COMMIT TRANSACTION;
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ */

DECLARE @terlewat int = (
    SELECT COUNT(*) FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa'
      AND Is_Show_Sidebar = 'Y'
      AND Url_Menu NOT IN (
        '/performance-appraisal/master/jenis-kompetensi',
        '/performance-appraisal/master/kompetensi',
        '/performance-appraisal/master/syarat-kompetensi',
        '/performance-appraisal/master/aturan-penilai',
        '/performance-appraisal/master/bobot-pilar',
        '/performance-appraisal/master/pita-nilai',
        '/performance-appraisal/master/ikatan-paket-soal',
        '/performance-appraisal',
        '/performance-appraisal/pemantauan',
        '/performance-appraisal/hasil',
        '/performance-appraisal/distribusi',
        '/performance-appraisal/hav-map',
        '/performance-appraisal/kalibrasi',
        '/performance-appraisal/riwayat',
        '/performance-appraisal/dashboard'
      )
);

IF @terlewat > 0
    THROW 50546, 'Ada menu administrator PA di sidebar yang tidak punya nomor urut baru; ia akan terlempar ke posisi lamanya.', 1;

DECLARE @kembar int = (
    SELECT COUNT(*) FROM (
        SELECT Sort_Menu
        FROM N_HRIS_Menus
        WHERE Header_Code = 'administrator_pa' AND Is_Show_Sidebar = 'Y'
        GROUP BY Sort_Menu
        HAVING COUNT(*) > 1
    ) AS k
);

IF @kembar > 0
    THROW 50547, 'Ada nomor urut kembar di menu administrator PA; urutannya akan berubah-ubah antar permintaan.', 1;

/* VERIFIKASI — susunan sidebar sesudah perubahan. */
SELECT Sort_Menu, Nama_Menu, Url_Menu, Is_Show_Sidebar
FROM N_HRIS_Menus
WHERE Header_Code = 'administrator_pa'
ORDER BY CASE WHEN Is_Show_Sidebar = 'Y' THEN 0 ELSE 1 END, CAST(Sort_Menu AS int);
