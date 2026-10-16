/* Change ID: DML-2026-024-move-visitor-verification-menus */

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @TargetMenuCount int;
DECLARE @TargetMapCount int;

SELECT @TargetMenuCount = COUNT(*)
FROM N_HRIS_Menus
WHERE (Id_Menu = 80 AND Jenis_page = 'visitorVerifikatorPage')
   OR (Id_Menu = 81 AND Jenis_page = 'visitorLaporanPage');

IF @TargetMenuCount <> 2
    THROW 50001, 'Expected exactly two Visitor verification/report menus.', 1;

SELECT @TargetMapCount = COUNT(*)
FROM N_HRIS_Grup_Fitur_Map
WHERE Id_Grup_Fitur = 4
  AND Flag_Aktif = 'Y'
  AND Jenis_page IN ('visitorVerifikatorPage', 'visitorLaporanPage');

IF @TargetMapCount <> 2
    THROW 50002, 'Both target pages must have an active VISITOR module mapping.', 1;

UPDATE N_HRIS_Menus
SET Header_Code = 'admin_verifikator',
    Header_Label = 'Admin Verifikator',
    Header_Icon = 'bi bi-person-check-fill',
    Sort_Header = 5,
    Sort_Menu = CASE
        WHEN Jenis_page = 'visitorVerifikatorPage' THEN 10
        WHEN Jenis_page = 'visitorLaporanPage' THEN 20
    END
WHERE (Id_Menu = 80 AND Jenis_page = 'visitorVerifikatorPage')
   OR (Id_Menu = 81 AND Jenis_page = 'visitorLaporanPage');

IF @@ROWCOUNT <> 2
    THROW 50003, 'Unexpected number of Visitor menu rows updated.', 1;

IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-024-move-visitor-verification-menus'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-024-move-visitor-verification-menus', 'DML', 'Move Visitor verification menus to Admin Verifikator', 'Memindahkan menu Verifikator Visitor dan Laporan Visitor ke header Admin Verifikator tanpa mengubah route, module mapping, atau page access.', 'database/dml/DML-2026-024-move-visitor-verification-menus/data-change.sql', 'docs/changes/DML-2026-024-move-visitor-verification-menus/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-menu-governance');
END;

COMMIT TRANSACTION;
