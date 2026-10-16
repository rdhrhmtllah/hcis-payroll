/* Change ID: DML-2026-026-register-visitor-export-studio-menu */

/*
    Export Studio Visitor dipisah dari halaman Laporan menjadi halaman sendiri.

    Perubahan ini MURNI PENAMBAHAN. `visitorLaporanPage` beserta seluruh baris
    aksesnya sengaja tidak disentuh: halaman Monitoring mewarisi URL dan kunci
    itu, sehingga tidak ada seorang pun yang bisa kehilangan akses.

    Catatan batas: baris N_HRIS_Grup_Fitur_Map wajib ada supaya menunya muncul,
    dan baris itu memberi akses kepada siapa pun pemegang role grup VISITOR.
    Pemisahan yang benar-benar mengikat hanya ada di jalur HRIS_Page_Access.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* 1. Menu Export Studio pada header Administrator Visitor yang sudah ada.
      Sort_Menu 40: master data 10, verifikator 20, laporan 30, studio 40. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorExportStudioPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorExportStudioPage', '001', 'Export Studio Visitor', 'bi bi-sliders', '/visitor/export-studio', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '40', '/visitor/export-studio*', 'self', 'Y', 'Rakit dan unduh laporan tamu & permit');
END;

/* 2. Mapping page ke modul VISITOR (Id_Grup_Fitur = 4). */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'visitorExportStudioPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (4, 'visitorExportStudioPage', 'T', 'Y', 'Y');
END;

/* 3. Akses diberikan PERSIS kepada pemegang aktif visitorLaporanPage, dengan
      dimensi perusahaan/level/divisi milik mereka sendiri. Diturunkan dari data,
      bukan dari daftar user yang ditulis tangan, supaya tetap benar bila
      audiensnya berubah sebelum changeset ini dijalankan ke produksi.
      Ekspektasi pada development per 2026-08-25: 2 baris (user 624 dan 4). */
INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT
    sumber.Kode_Perusahaan,
    sumber.ID_Level,
    sumber.ID_Divisi,
    'visitorExportStudioPage',
    sumber.UserID_Web,
    40,
    'Y',
    SYSDATETIME()
FROM HRIS_Page_Access sumber
WHERE sumber.Jenis_page = 'visitorLaporanPage'
  AND sumber.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1
      FROM HRIS_Page_Access ada
      WHERE ada.Jenis_page = 'visitorExportStudioPage'
        AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
        AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
        AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
  );

/* 4. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-026-register-visitor-export-studio-menu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-026-register-visitor-export-studio-menu', 'DML', 'Register Visitor Export Studio as its own page', 'Menu, pemetaan grup fitur, dan hak akses visitorExportStudioPage; murni penambahan, visitorLaporanPage tidak disentuh.', 'database/dml/DML-2026-026-register-visitor-export-studio-menu/data-change.sql', 'docs/changes/DML-2026-026-register-visitor-export-studio-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-export-studio-split');
END;

COMMIT TRANSACTION;
