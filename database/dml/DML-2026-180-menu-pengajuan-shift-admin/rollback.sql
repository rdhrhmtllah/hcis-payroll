/* Rollback: DML-2026-180-menu-pengajuan-shift-admin */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;
DELETE FROM HRIS_Page_Access WHERE Jenis_page = 'pengajuanShiftAdmin';
DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'pengajuanShiftAdmin';
DELETE FROM N_HRIS_Menus WHERE Jenis_page = 'pengajuanShiftAdmin';
COMMIT TRANSACTION;
