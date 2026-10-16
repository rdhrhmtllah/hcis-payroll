/* Change ID: DML-2026-021-register-system-master-entitas-menu */

/* 1. Baris menu. Is_Global_Menu = 'T' mengikuti Database Change Dashboard:
      halaman SYSTEM tidak dibagikan ke semua orang. Gerbang sebenarnya bukan
      di sini melainkan middleware `system.admin` (KPI_Users.Is_System_Admin),
      dan baris menu ini hanya menentukan apakah tautannya tampil. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'systemMasterEntitasPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('systemMasterEntitasPage', '001', 'Master Entitas', 'bi bi-buildings-fill', '/system/master-data/entitas', 'master_data', 'Master Data', 'bi bi-database-fill-gear', '2', 'T', '1', '/system/master-data/entitas*', 'system.master-data.entitas.page', 'self', 'Y', 'Badan hukum dalam grup, dipakai lintas modul');
END;

/* 2. Pemetaan ke grup fitur SYSTEM (Id_Grup_Fitur = 5, diverifikasi dari
      N_HRIS_Master_Grup_Fitur pada 2026-08-22). TANPA baris ini menu tidak
      pernah muncul di sidebar dan tidak ada galat apa pun yang menjelaskannya. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'systemMasterEntitasPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (5, 'systemMasterEntitasPage', 'T', 'Y', 'Y');
END;

/* 3. TIDAK ADA baris HRIS_Page_Access yang disisipkan, dan itu disengaja.
      Halaman SYSTEM di repo ini tidak memakai gerbang check.jabatan: grup fitur
      SYSTEM tak dipegang satu pun role di N_HRIS_Role_Functional, dan
      systemDatabaseChangeDashboardPage pun nol baris di HRIS_Page_Access.
      Aksesnya ditentukan flag KPI_Users.Is_System_Admin lewat middleware
      `system.admin`. Menyisipkan grant di sini justru akan membuka master
      lintas modul kepada pemegang menu modul tertentu. */

/* 4. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-021-register-system-master-entitas-menu')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-021-register-system-master-entitas-menu', 'DML', 'Register Master Entitas menu under SYSTEM', 'Menu sidebar dan pemetaan Jenis_page ke grup SYSTEM. Tanpa grant HRIS_Page_Access: gerbangnya middleware system.admin.', 'database/dml/DML-2026-021-register-system-master-entitas-menu/data-change.sql', 'docs/changes/DML-2026-021-register-system-master-entitas-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-entitas');
