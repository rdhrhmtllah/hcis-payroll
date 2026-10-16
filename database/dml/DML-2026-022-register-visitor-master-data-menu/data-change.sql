/* Change ID: DML-2026-022-register-visitor-master-data-menu */

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* 1. Menu Master Data Visitor pada header Administrator Visitor yang sudah ada. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorMasterDataPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorMasterDataPage', '001', 'Master Data Visitor', 'bi-database-gear', '/admin/visitor/master-data', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '10', 'self', 'Y', 'Kelola referensi operasional tamu dan permit');
END;

/* 2. Mapping page ke modul VISITOR (Id_Grup_Fitur = 4). */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'visitorMasterDataPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (4, 'visitorMasterDataPage', 'T', 'Y', 'Y');
END;

/* 3. Akses hanya untuk user aktif dengan role VISITOR SHE_ADMIN.
      Dimensi perusahaan/level/divisi diturunkan dari page VISITOR aktif milik
      user yang sama; tidak ada perluasan ke role governance menu. */
INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT
    source.Kode_Perusahaan,
    source.ID_Level,
    source.ID_Divisi,
    'visitorMasterDataPage',
    ru.UserId_Web,
    10,
    'Y',
    SYSDATETIME()
FROM N_HRIS_Role_User ru
JOIN N_HRIS_Role_Functional rf
    ON rf.Id_Role = ru.Id_Role
CROSS APPLY (
    SELECT TOP 1
        a.Kode_Perusahaan,
        a.ID_Level,
        a.ID_Divisi
    FROM HRIS_Page_Access a
    JOIN N_HRIS_Grup_Fitur_Map gm
        ON gm.Jenis_page = a.Jenis_page
       AND gm.Id_Grup_Fitur = 4
       AND gm.Flag_Aktif = 'Y'
    WHERE a.UserID_Web = ru.UserId_Web
      AND a.Flag_Aktif = 'Y'
    ORDER BY
        CASE
            WHEN a.Jenis_page = 'visitorVerifikatorPage' THEN 0
            WHEN a.Jenis_page = 'menuApprovalMatrixVisitorPage' THEN 1
            ELSE 2
        END,
        a.Jenis_page
) source
WHERE rf.Role_Code = 'SHE_ADMIN'
  AND rf.Id_Grup_Fitur = 4
  AND rf.Flag_Aktif = 'Y'
  AND ru.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1
      FROM HRIS_Page_Access existing
      WHERE existing.Jenis_page = 'visitorMasterDataPage'
        AND ISNULL(existing.UserID_Web, -1) = ISNULL(ru.UserId_Web, -1)
        AND ISNULL(existing.ID_Level, -1) = ISNULL(source.ID_Level, -1)
        AND ISNULL(existing.ID_Divisi, -1) = ISNULL(source.ID_Divisi, -1)
  );

/* 4. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-022-register-visitor-master-data-menu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-022-register-visitor-master-data-menu', 'DML', 'Register Visitor Master Data menu and SHE admin access', 'Menu Administrator Visitor, mapping page ke VISITOR, serta akses terbatas untuk role SHE_ADMIN aktif.', 'database/dml/DML-2026-022-register-visitor-master-data-menu/data-change.sql', 'docs/changes/DML-2026-022-register-visitor-master-data-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-master-data');
END;

COMMIT TRANSACTION;
