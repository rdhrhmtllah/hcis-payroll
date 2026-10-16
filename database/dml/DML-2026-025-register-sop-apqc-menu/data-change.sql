/* Change ID: DML-2026-025-register-sop-apqc-menu */

/* 1. Baris menu di header Administrator SOP. Sort_Menu = 3 menempatkannya
      tepat setelah Jenis Dokumen (2) dan menggeser tak satu pun baris lain,
      karena Master Dokumen memakai 3 -- keduanya boleh bernomor sama dan
      diurutkan lanjut oleh nama. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'menuSopApqcPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('menuSopApqcPage', '001', 'Master APQC', 'bi-bounding-box', '/sop/admin/apqc', 'administrator_sop', 'Administrator SOP', 'bi bi-gear-fill', '11', 'T', '3', 'self', 'Y', 'Klasifikasi proses bisnis dokumen');
END;

/* 2. Pemetaan ke grup fitur LMS (Id_Grup_Fitur = 3, diverifikasi 2026-08-22).
      TANPA baris ini menu tidak pernah muncul di sidebar dan tidak ada galat
      apa pun yang menjelaskannya. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopApqcPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (3, 'menuSopApqcPage', 'T', 'Y', 'Y');
END;

/* 3. Hak akses diturunkan dari pemegang menuSopJenisDokumenPage: yang mengelola
      master jenis adalah orang yang sama yang akan mengelola klasifikasi proses. */
INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, 'menuSopApqcPage', a.UserID_Web, a.Urutan_Menu, 'Y', SYSDATETIME()
FROM HRIS_Page_Access a
WHERE a.Jenis_page = 'menuSopJenisDokumenPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access b
      WHERE b.Jenis_page = 'menuSopApqcPage'
        AND ISNULL(b.UserID_Web, -1) = ISNULL(a.UserID_Web, -1)
        AND ISNULL(b.ID_Level, -1) = ISNULL(a.ID_Level, -1)
        AND ISNULL(b.ID_Divisi, -1) = ISNULL(a.ID_Divisi, -1)
  );

/* 4. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-025-register-sop-apqc-menu')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-025-register-sop-apqc-menu', 'DML', 'Register Master APQC menu under LMS', 'Menu sidebar Administrator SOP, pemetaan Jenis_page ke grup LMS, dan hak akses diturunkan dari pemegang menuSopJenisDokumenPage.', 'database/dml/DML-2026-025-register-sop-apqc-menu/data-change.sql', 'docs/changes/DML-2026-025-register-sop-apqc-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-apqc');
