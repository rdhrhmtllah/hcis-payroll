/* Change ID: DML-2026-018-register-sop-jenis-dokumen-menu */

/* 1. Baris menu. Sort_Menu = 2 memang kosong: Kategori SOP = 1 dan Master
      Dokumen = 3, jadi master jenis duduk tepat di antara dua master lain
      dan bukan di ekor daftar. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'menuSopJenisDokumenPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('menuSopJenisDokumenPage', '001', 'Jenis Dokumen SOP', 'bi-diagram-3-fill', '/sop/admin/jenis-dokumen', 'administrator_sop', 'Administrator SOP', 'bi bi-gear-fill', '11', 'T', '2', 'self', 'Y', 'Hirarki mutu: Kebijakan sampai Formulir');
END;

/* 2. Pemetaan ke grup fitur LMS (Id_Grup_Fitur = 3, diverifikasi langsung dari
      N_HRIS_Master_Grup_Fitur pada 2026-08-22). TANPA baris ini menu tidak
      pernah muncul di sidebar dan tidak ada galat apa pun yang menjelaskannya. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopJenisDokumenPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (3, 'menuSopJenisDokumenPage', 'T', 'Y', 'Y');
END;

/* 3. Hak akses diturunkan dari pemegang menuSopKategoriPage, bukan dari
      Monitoring: yang mengelola taksonomi kategori adalah orang yang sama yang
      akan mengelola tingkat hirarki. Menurunkannya dari Monitoring akan memberi
      hak ubah master kepada orang yang tugasnya hanya membaca kepatuhan. */
INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, 'menuSopJenisDokumenPage', a.UserID_Web, a.Urutan_Menu, 'Y', SYSDATETIME()
FROM HRIS_Page_Access a
WHERE a.Jenis_page = 'menuSopKategoriPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access b
      WHERE b.Jenis_page = 'menuSopJenisDokumenPage'
        AND ISNULL(b.UserID_Web, -1) = ISNULL(a.UserID_Web, -1)
        AND ISNULL(b.ID_Level, -1) = ISNULL(a.ID_Level, -1)
        AND ISNULL(b.ID_Divisi, -1) = ISNULL(a.ID_Divisi, -1)
  );

/* 4. Pendaftaran ke Database Change Dashboard. Mengisi database/ddl/status.json
      saja TIDAK membuat perubahan ini terlihat di sana — keduanya catatan yang
      terpisah. Lihat DML-2026-011. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-018-register-sop-jenis-dokumen-menu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-018-register-sop-jenis-dokumen-menu', 'DML', 'Register SOP Jenis Dokumen menu under LMS', 'Menu sidebar, pemetaan Jenis_page ke grup LMS, dan hak akses diturunkan dari pemegang menuSopKategoriPage.', 'database/dml/DML-2026-018-register-sop-jenis-dokumen-menu/data-change.sql', 'docs/changes/DML-2026-018-register-sop-jenis-dokumen-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'jenis-dokumen');
END;
