/* Change ID: DML-2026-147-system-register-panduan-menu */

/* 1. Baris menu Manajemen Panduan di header baru "Aplikasi" SYSTEM
      (Sort_Header 3, setelah Database Changes dan Master Data). */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'systemPanduanPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('systemPanduanPage', '001', 'Manajemen Panduan', 'bi bi-book-half', '/system/panduan', 'aplikasi', 'Aplikasi', 'bi bi-app-indicator', '3', 'T', '1', '/system/panduan*', 'system.panduan.page', 'self', 'Y', 'Manual PDF per halaman aplikasi');
END;

/* 2. Pemetaan ke grup fitur SYSTEM (Id_Grup_Fitur = 5). */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'systemPanduanPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (5, 'systemPanduanPage', 'T', 'Y', 'Y');
END;

/* 3. TIDAK ADA baris HRIS_Page_Access: halaman SYSTEM digerbangi middleware
      `system.admin` (KPI_Users.Is_System_Admin), sama seperti DML-2026-029. */

/* 4. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-147-system-register-panduan-menu')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-147-system-register-panduan-menu', 'DML', 'Register Manajemen Panduan menu under SYSTEM', 'Menu sidebar dan pemetaan Jenis_page ke grup SYSTEM. Tanpa grant HRIS_Page_Access: gerbangnya middleware system.admin.', 'database/dml/DML-2026-147-system-register-panduan-menu/data-change.sql', 'docs/changes/DML-2026-147-system-register-panduan-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'manajemen-panduan');
