/* Target: Web_HRIS development only. Expected affected rows: 1. */
UPDATE N_HRIS_Menus
SET Is_Global_Menu = 'T'
WHERE Jenis_page = 'systemDatabaseChangeDashboardPage'
  AND Is_Global_Menu = 'Y';
