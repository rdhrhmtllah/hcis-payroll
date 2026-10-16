/* Change ID: DML-2026-173-menu-pa-urutan-master
   Target: Web_HRIS development; production only through SQL Tracker.
   Scope: N_HRIS_Menus — DELAPAN baris menu master Administrator PA, hanya kolom Sort_Menu.

   Urutan baru (permintaan user 8 Okt 2026), mengikuti alur penyiapan: siapa menilai dan
   bobotnya dulu, lalu kompetensi, soal, dan skala:
     1 Jenis Penilai · 2 Komposisi Penilai · 3 Bobot KPI & Kompetensi · 4 Jenis Kompetensi ·
     5 Kompetensi · 6 Matriks Kompetensi · 7 Matriks Soal · 8 Skala Nilai & Grade.
   Menu lain (Grup Level Kurva 9, Relasi Organisasi 10, Assessment Cycle 11, dst.) tidak disentuh.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50730, 'DML-2026-173 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @urutan TABLE (Jenis_page varchar(100) PRIMARY KEY, Sort_Menu int);
INSERT INTO @urutan VALUES
    ('PaMasterKategoriPenilaiPage', 1),
    ('PaMasterAturanPenilaiPage', 2),
    ('PaMasterBobotPilarPage', 3),
    ('PaMasterJenisKompetensiPage', 4),
    ('PaMasterKompetensiPage', 5),
    ('PaMasterSyaratKompetensiPage', 6),
    ('PaMasterIkatanPaketSoalPage', 7),
    ('PaMasterPitaNilaiPage', 8);

IF (SELECT COUNT(*) FROM N_HRIS_Menus m JOIN @urutan u ON u.Jenis_page = m.Jenis_page) <> 8
    THROW 50731, 'Tidak semua delapan menu master PA ditemukan.', 1;

BEGIN TRANSACTION;

UPDATE m SET Sort_Menu = u.Sort_Menu
FROM N_HRIS_Menus m JOIN @urutan u ON u.Jenis_page = m.Jenis_page
WHERE ISNULL(m.Sort_Menu, -1) <> u.Sort_Menu;

COMMIT TRANSACTION;
GO

SELECT Jenis_page, Nama_Menu, Sort_Header, Sort_Menu
FROM N_HRIS_Menus WHERE Header_Label = 'Administrator PA' AND Is_Show_Sidebar = 'Y'
ORDER BY Sort_Menu;

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
