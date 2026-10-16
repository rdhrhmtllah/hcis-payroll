/* Rollback: DML-2026-168-menu-nfc-monitoring */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DELETE FROM HRIS_Page_Access WHERE Jenis_page = 'visitorNfcMonitoringPage';
DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'visitorNfcMonitoringPage';
DELETE FROM N_HRIS_Menus WHERE Jenis_page = 'visitorNfcMonitoringPage';

COMMIT TRANSACTION;
