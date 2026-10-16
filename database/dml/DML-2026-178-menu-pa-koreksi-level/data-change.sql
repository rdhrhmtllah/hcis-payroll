/* Change ID: DML-2026-178-menu-pa-koreksi-level
   Target: Web_HRIS development; production only through SQL Tracker. Setelah DDL-2026-177.
   Scope: menu "Master · Koreksi Level PA" (PaMasterLevelPaPage) di header Administrator PA,
          Sort_Menu 2; pemetaan grup fitur KPI; akses halaman meniru PaMasterGrupKurvaPage (user 4, 622).
*/
IF DB_NAME() <> 'Web_HRIS'
    THROW 50678, 'DML-2026-178 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT 'PaMasterLevelPaPage', '001', N'Master · Koreksi Level PA', 'bi-person-badge',
       '/performance-appraisal/master/level-pa',
       'administrator_pa', N'Administrator PA', 'bi bi-sliders', '11', 'T', 'self', 'Y', '2'
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterLevelPaPage');

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, 'PaMasterLevelPaPage', 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = 'PaMasterLevelPaPage');

INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At)
SELECT b.Kode_Perusahaan, b.ID_Level, b.ID_Divisi, 'PaMasterLevelPaPage', b.UserID_Web, 'Y', SYSDATETIME()
FROM HRIS_Page_Access b
WHERE b.Jenis_page = 'PaMasterGrupKurvaPage'
  AND b.Flag_Aktif = 'Y'
  AND NOT EXISTS (SELECT 1 FROM HRIS_Page_Access d WHERE d.UserID_Web = b.UserID_Web AND d.Jenis_page = 'PaMasterLevelPaPage');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-178')
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Catatan, Created_By)
    VALUES ('DML-2026-178', 'DML', 'Menu Master Koreksi Level PA',
        'Menu PaMasterLevelPaPage di header Administrator PA (Sort_Menu 2), peta grup fitur KPI, dan akses halaman pemegang Grup Level Kurva.',
        'database/dml/DML-2026-178-menu-pa-koreksi-level/data-change.sql',
        'docs/changes/DML-2026-178-menu-pa-koreksi-level/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'KPI/Performance Appraisal/Menu. Setelah DDL-2026-177.', NULL);

COMMIT TRANSACTION;
GO
