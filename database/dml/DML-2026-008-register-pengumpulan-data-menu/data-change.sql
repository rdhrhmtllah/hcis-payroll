-- database/dml/DML-2026-008-register-pengumpulan-data-menu/data-change.sql
-- Jalankan manual di SQL Server (Web_HRIS - sesuaikan environment target).
-- Desain: docs/superpowers/specs/2026-08-14-pengumpulan-data-karyawan-design.md
--
-- Struktur menu HCIS yang berlaku (hasil pembacaan langsung ke DB, 14 Agt 2026):
--   * Grup fitur hanya ada 4: ESS(1), KPI(2), LMS(3), VISITOR(4).
--   * "Administrator HCIS" BUKAN grup, melainkan Header_Label di dalam grup ESS.
--   * Id_Menu dan Id_Grup_Map keduanya IDENTITY -> jangan diisi manual.
--   * Sebuah menu tampil bila punya baris di N_HRIS_Menus DAN N_HRIS_Grup_Fitur_Map.
--   * Is_Global_Menu = 'Y' -> tampil untuk semua orang tanpa pemberian akses.
--     Is_Global_Menu = 'T' -> tampil hanya bila diberi akses lewat HRIS_Page_Access.
--
-- Blok A aman dijalankan SEKARANG. Blok B menunggu fase 4 selesai.

-- =========================================================================
-- BLOK A -- Halaman karyawan  (/pengumpulan-data)  [JALANKAN SEKARANG]
-- =========================================================================
-- Is_Global_Menu = 'Y' karena SELURUH karyawan aktif memang wajib mengisi.
-- Memakai 'T' berarti harus memberi akses satu per satu ke ratusan orang lewat
-- HRIS_Page_Access -- pekerjaan sia-sia untuk halaman yang targetnya semua orang.
-- Karyawan yang dikecualikan diatur di N_HRIS_Pengumpulan_Data_Status, bukan di menu.

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'pengumpulanDataPage')
INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
VALUES
    ('pengumpulanDataPage', '001', 'Pembaruan Data', 'bi-clipboard-check', '/pengumpulan-data',
     'kehadiran', 'Personal', 'bi bi-person-fill-gear', 2,
     'Y', NULL, NULL, NULL, 'self', 'Y', NULL);

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'pengumpulanDataPage')
INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
VALUES
    (1, 'pengumpulanDataPage', NULL, 'T', 'Y', 'Y');

-- =========================================================================
-- BLOK B -- Halaman admin  (/admin/pengumpulan-data)  [TUNGGU FASE 4]
-- =========================================================================
-- JANGAN dijalankan sebelum fase 4 selesai: rutenya belum ada, jadi menunya
-- akan menuntun orang ke 404. Blok ini sengaja dibungkus IF 1 = 0 supaya
-- tidak ikut tereksekusi kalau berkas ini dijalankan utuh tanpa sengaja.
-- Hapus baris "IF 1 = 0" dan BEGIN/END-nya saat fase 4 siap.

IF 1 = 0
BEGIN
    IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'adminPengumpulanDataPage')
    INSERT INTO N_HRIS_Menus
        (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
         Header_Code, Header_Label, Header_Icon, Sort_Header,
         Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
        ('adminPengumpulanDataPage', '001', 'Pembaruan Data Admin', 'bi-people-fill', '/admin/pengumpulan-data',
         'administrator_hcis', 'Administrator HCIS', 'bi bi-gear-fill', 7,
         'T', NULL, NULL, NULL, 'self', 'Y', NULL);

    IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'adminPengumpulanDataPage')
    INSERT INTO N_HRIS_Grup_Fitur_Map
        (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
        (1, 'adminPengumpulanDataPage', NULL, 'T', 'Y', 'Y');
END

-- Setelah Blok B dijalankan, berikan aksesnya lewat halaman /page-access
-- (menu "Page Access Setup"), BUKAN dengan INSERT tangan ke HRIS_Page_Access --
-- tabel itu berbaris per kombinasi ID_Level / ID_Divisi / UserID_Web dan salah
-- isi berarti orang yang salah melihat data seluruh karyawan.

-- =========================================================================
-- VERIFIKASI
-- =========================================================================
SELECT m.Jenis_page, m.Nama_Menu, m.Url_Menu, m.Header_Label,
       m.Is_Global_Menu, gm.Flag_Aktif AS Map_Aktif
FROM N_HRIS_Menus m
LEFT JOIN N_HRIS_Grup_Fitur_Map gm ON gm.Jenis_page = m.Jenis_page
WHERE m.Jenis_page IN ('pengumpulanDataPage', 'adminPengumpulanDataPage');

-- Menu baru TIDAK langsung tampak: InertiaShellService menyimpan baris menu di
-- cache. Kosongkan cache aplikasi setelah menjalankan berkas ini, atau tunggu
-- TTL-nya habis.
--   php artisan cache:clear

-- =========================================================================
-- PEMBATALAN (bila perlu mundur)
-- =========================================================================
-- DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page IN ('pengumpulanDataPage', 'adminPengumpulanDataPage');
-- DELETE FROM N_HRIS_Menus          WHERE Jenis_page IN ('pengumpulanDataPage', 'adminPengumpulanDataPage');

-- =========================================================================
-- CATATAN TEMUAN (bukan bagian dari fitur ini)
-- =========================================================================
-- 'superAdminManajemenPeranPage' punya DUA baris di N_HRIS_Grup_Fitur_Map,
-- sehingga menu "Super Admin Setup" muncul dobel di sidebar. Perbaikannya:
--   DELETE FROM N_HRIS_Grup_Fitur_Map
--   WHERE Id_Grup_Map = (SELECT MAX(Id_Grup_Map) FROM N_HRIS_Grup_Fitur_Map
--                        WHERE Jenis_page = 'superAdminManajemenPeranPage');
-- Sengaja TIDAK dijalankan di sini -- di luar lingkup, dan penghapusan baris
-- menu pantas diputuskan sadar, bukan menumpang berkas fitur lain.
