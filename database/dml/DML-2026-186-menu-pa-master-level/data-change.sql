/* Change ID: DML-2026-186-menu-pa-master-level
   Target: Web_HRIS development; production only through SQL Tracker. Setelah DDL-2026-183/185.
   Scope: menu "Master · Level PA" (PaMasterLevelMasterPage) di header Administrator PA, tepat di
          atas "Master · Koreksi Level PA" (Sort_Menu 2; menu Administrator PA lain bergeser +1);
          pemetaan grup fitur KPI; akses halaman meniru PaMasterLevelPaPage.
*/
IF DB_NAME() <> 'Web_HRIS'
    THROW 50686, 'DML-2026-186 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterLevelMasterPage')
BEGIN
    /* Geser menu Administrator PA mulai urutan 2 supaya Level PA berada tepat di atas Koreksi. */
    UPDATE N_HRIS_Menus
    SET Sort_Menu = Sort_Menu + 1
    WHERE Header_Code = 'administrator_pa'
      AND Sort_Menu >= 2;

    INSERT INTO N_HRIS_Menus
        (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
         Header_Code, Header_Label, Header_Icon, Sort_Header,
         Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
    VALUES ('PaMasterLevelMasterPage', '001', N'Master · Level PA', 'bi-diagram-3',
            '/performance-appraisal/master/level',
            'administrator_pa', N'Administrator PA', 'bi bi-sliders', '11', 'T', 'self', 'Y', 2);
END;

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, 'PaMasterLevelMasterPage', 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = 'PaMasterLevelMasterPage');

INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At)
SELECT b.Kode_Perusahaan, b.ID_Level, b.ID_Divisi, 'PaMasterLevelMasterPage', b.UserID_Web, 'Y', SYSDATETIME()
FROM HRIS_Page_Access b
WHERE b.Jenis_page = 'PaMasterLevelPaPage'
  AND b.Flag_Aktif = 'Y'
  AND NOT EXISTS (SELECT 1 FROM HRIS_Page_Access d WHERE d.UserID_Web = b.UserID_Web AND d.Jenis_page = 'PaMasterLevelMasterPage');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-186')
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Catatan, Created_By)
    VALUES ('DML-2026-186', 'DML', 'Menu Master Level PA',
        'Menu PaMasterLevelMasterPage di header Administrator PA (Sort_Menu 2, menu lain bergeser +1), peta grup fitur KPI, dan akses halaman pemegang Koreksi Level PA.',
        'database/dml/DML-2026-186-menu-pa-master-level/data-change.sql',
        'docs/changes/DML-2026-186-menu-pa-master-level/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'KPI/Performance Appraisal/Menu. Setelah DDL-2026-183/185.', NULL);

COMMIT TRANSACTION;
GO
