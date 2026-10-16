/* Target: Web_HRIS development only. Expected inserts: 2 rows. */
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @IdGrupFitur int = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'SYSTEM');
IF @IdGrupFitur IS NULL THROW 51000, 'SYSTEM module not found.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'systemDatabaseChangeDashboardPage')
BEGIN
    INSERT INTO N_HRIS_Menus (
        Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
        Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu,
        Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle
    )
    VALUES (
        'systemDatabaseChangeDashboardPage', '001', 'Database Change Dashboard', 'bi bi-database-gear',
        '/system/database-changes',
        'database_changes', 'Database Changes', 'bi bi-database-gear', 1, 'Y',
        10, '/system/database-changes*', 'system.database-changes.page', 'self', 'Y',
        'Pantau DDL dan DML dari development sampai production'
    );
END;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map
    WHERE Id_Grup_Fitur = @IdGrupFitur AND Jenis_page = 'systemDatabaseChangeDashboardPage'
)
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map (
        Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif
    )
    VALUES (@IdGrupFitur, 'systemDatabaseChangeDashboardPage', 10, 'Y', 'Y', 'Y');
END;

COMMIT TRANSACTION;
