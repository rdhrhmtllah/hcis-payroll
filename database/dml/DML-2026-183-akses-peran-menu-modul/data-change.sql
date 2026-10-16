/* Change ID: DML-2026-183-akses-peran-menu-modul
 * RBAC: menu "Akses & Peran" per modul, di bawah header admin modul masing-masing
 * (sama dengan Menu & Matrix). Super Admin & Admin modul mengelola akses modulnya dari
 * sini; SYSTEM → Akses & Peran khusus System Admin. Gerbang: middleware `akses.peran`
 * dengan konteks modul + sidebar (InertiaShellService) — TANPA hibah (Flag_Hibah_Role
 * 'T') dan TANPA grant. Header (label, ikon, urutan) disalin dari menu Menu & Matrix
 * modul itu. Idempoten. */
SET NOCOUNT ON;

DECLARE @m TABLE (Jenis_page varchar(100), Kode_Modul varchar(20), Url varchar(200), Contoh varchar(100));
INSERT INTO @m VALUES
    ('essAksesPeranPage', 'ESS', '/admin/ess/akses-peran', 'menuApprovalMatrixEssPage'),
    ('kpiAksesPeranPage', 'KPI', '/admin/kpi/akses-peran', 'menuApprovalMatrixKpiPage'),
    ('lmsAksesPeranPage', 'LMS', '/admin/lms/akses-peran', 'menuApprovalMatrixLmsPage'),
    ('visitorAksesPeranPage', 'VISITOR', '/admin/visitor/akses-peran', 'menuApprovalMatrixVisitorPage'),
    ('dmsAksesPeranPage', 'DMS', '/admin/dms/akses-peran', 'menuSopMonitoringPage');

INSERT INTO dbo.N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
SELECT m.Jenis_page, '001', 'Akses & Peran', 'bi bi-shield-lock-fill', m.Url, c.Header_Code, c.Header_Label, c.Header_Icon, c.Sort_Header,
       'T', '91', m.Url + '*', 'modul.akses-peran.page', 'self', 'Y', 'Admin, peran, dan hak akses modul ini'
FROM @m m
CROSS APPLY (SELECT TOP 1 Header_Code, Header_Label, Header_Icon, Sort_Header FROM dbo.N_HRIS_Menus WHERE Jenis_page = m.Contoh) c
WHERE NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Menus x WHERE x.Jenis_page = m.Jenis_page);

INSERT INTO dbo.N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT g.Id_Grup_Fitur, m.Jenis_page, 'T', 'Y', 'Y', 'T'
FROM @m m
JOIN dbo.N_HRIS_Master_Grup_Fitur g ON g.Kode_Grup_Fitur = m.Kode_Modul
WHERE NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = m.Jenis_page);
