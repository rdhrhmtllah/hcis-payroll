/* Rollback: DML-2026-193-menu-monitoring-kehadiran — MANUAL. Agen tidak menjalankan berkas ini. */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;
DELETE FROM HRIS_Page_Access WHERE Jenis_page = 'essMonitoringKehadiranPage';
DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'essMonitoringKehadiranPage';
DELETE FROM N_HRIS_Menus WHERE Jenis_page = 'essMonitoringKehadiranPage';
COMMIT TRANSACTION;
