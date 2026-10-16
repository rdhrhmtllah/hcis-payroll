-- DML-2026-191 — RBAC: Urutan terdepan menu Akses & Peran di seluruh modul
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DML-2026-183.
--
-- Mengatur posisi menu "Akses & Peran" di setiap modul (ESS, KPI, LMS, VISITOR, DMS, SYSTEM)
-- agar berada di urutan pertama (paling atas) pada sub-menu Administrator di sidebar:
--  1. N_HRIS_Menus.Sort_Menu = 1
--  2. N_HRIS_Grup_Fitur_Map.Sort_Order = 1
--
-- Sebelumnya Sort_Menu = 91 dan Sort_Order = NULL, sehingga menu tenggelam di urutan
-- paling bawah setelah 15-20 menu lainnya dan terpotong di layar sidebar.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

-- 1. Set Sort_Menu = 1 di N_HRIS_Menus
UPDATE dbo.N_HRIS_Menus
SET Sort_Menu = '1'
WHERE Jenis_page IN (
    'essAksesPeranPage',
    'kpiAksesPeranPage',
    'lmsAksesPeranPage',
    'visitorAksesPeranPage',
    'dmsAksesPeranPage',
    'systemAksesPeranPage'
);

-- 2. Set Sort_Order = 1 di N_HRIS_Grup_Fitur_Map
UPDATE dbo.N_HRIS_Grup_Fitur_Map
SET Sort_Order = 1
WHERE Jenis_page IN (
    'essAksesPeranPage',
    'kpiAksesPeranPage',
    'lmsAksesPeranPage',
    'visitorAksesPeranPage',
    'dmsAksesPeranPage',
    'systemAksesPeranPage'
);

COMMIT TRANSACTION;

-- Verifikasi urutan menu Akses & Peran di tiap modul
SELECT gf.Kode_Grup_Fitur AS modul, m.Header_Label, m.Nama_Menu, m.Jenis_page, m.Sort_Menu, gfm.Sort_Order AS map_sort_order
FROM dbo.N_HRIS_Menus m
JOIN dbo.N_HRIS_Grup_Fitur_Map gfm ON gfm.Jenis_page = m.Jenis_page
JOIN dbo.N_HRIS_Master_Grup_Fitur gf ON gf.Id_Grup_Fitur = gfm.Id_Grup_Fitur
WHERE m.Jenis_page LIKE '%AksesPeranPage'
ORDER BY gf.Sort_Order, m.Sort_Header, m.Sort_Menu;
