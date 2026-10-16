/* Change ID: DML-2026-012-register-sop-export-studio-menu */

/* 1. Baris menu. Id_Menu IDENTITY -- tidak masuk daftar kolom.
      Header disalin dari menu SOP lain supaya masuk ke kelompok sidebar yang sama. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'menuSopExportStudioPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('menuSopExportStudioPage', '001', 'Export Studio', 'bi-sliders', '/sop/admin/export-studio', 'administrator_sop', 'Administrator SOP', 'bi bi-gear-fill', '11', 'T', '9', 'self', 'Y', 'Rakit laporan SOP sendiri');
END;

/* 2. Pemetaan ke grup fitur LMS (Id_Grup_Fitur = 3, diverifikasi dari
      N_HRIS_Master_Grup_Fitur pada 2026-08-18). TANPA baris ini menu tidak
      pernah muncul di sidebar dan tidak ada galat apa pun yang menjelaskannya. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopExportStudioPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (3, 'menuSopExportStudioPage', 'T', 'Y', 'Y');
END;

/* 3. Hak akses: setiap pemegang menuSopMonitoringPage yang aktif ikut mendapat
      halaman baru ini. Orang yang hari ini sudah boleh melihat data kepatuhan
      tidak kehilangan apa pun pada hari rilis. */
INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, 'menuSopExportStudioPage', a.UserID_Web, a.Urutan_Menu, 'Y', SYSDATETIME()
FROM HRIS_Page_Access a
WHERE a.Jenis_page = 'menuSopMonitoringPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access b
      WHERE b.Jenis_page = 'menuSopExportStudioPage'
        AND ISNULL(b.UserID_Web, -1) = ISNULL(a.UserID_Web, -1)
        AND ISNULL(b.ID_Level, -1) = ISNULL(a.ID_Level, -1)
        AND ISNULL(b.ID_Divisi, -1) = ISNULL(a.ID_Divisi, -1)
  );

/* 4. Pendaftaran ke Database Change Dashboard. Mengisi database/ddl/status.json
      saja TIDAK membuat perubahan ini terlihat di sana -- keduanya catatan yang
      terpisah. Lihat DML-2026-011. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-012-register-sop-export-studio-menu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-012-register-sop-export-studio-menu', 'DML', 'Register SOP Export Studio menu under LMS', 'Menu sidebar, pemetaan Jenis_page ke grup LMS, dan hak akses untuk pemegang menuSopMonitoringPage.', 'database/dml/DML-2026-012-register-sop-export-studio-menu/data-change.sql', 'docs/changes/DML-2026-012-register-sop-export-studio-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'export-studio');
END;
