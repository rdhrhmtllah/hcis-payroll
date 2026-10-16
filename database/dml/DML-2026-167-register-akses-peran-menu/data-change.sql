/* Change ID: DML-2026-167-register-akses-peran-menu
 * RBAC Fase 2b: menu "Akses & Peran" di grup SYSTEM. Idempoten.
 * Gerbang sebenarnya middleware `akses.peran` (System Admin atau pemegang role
 * ADMIN), dan sidebar memakai aturan yang sama (InertiaShellService) — jadi
 * TANPA hibah role (Flag_Hibah_Role = 'T') dan TANPA grant HRIS_Page_Access. */
SET NOCOUNT ON;

IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Menus WHERE Jenis_page = 'systemAksesPeranPage')
BEGIN
    INSERT INTO dbo.N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('systemAksesPeranPage', '001', 'Akses & Peran', 'bi bi-shield-lock-fill', '/system/akses-peran', 'akses_peran', 'Akses & Peran', 'bi bi-shield-lock-fill', '1', 'T', '1', '/system/akses-peran*', 'system.akses-peran.page', 'self', 'Y', 'Peran, penugasan, dan jejak perubahan akses');
END;

DECLARE @system int = (SELECT TOP 1 Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'SYSTEM');
IF @system IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'systemAksesPeranPage')
BEGIN
    INSERT INTO dbo.N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
    VALUES (@system, 'systemAksesPeranPage', 'T', 'Y', 'Y', 'T');
END;
