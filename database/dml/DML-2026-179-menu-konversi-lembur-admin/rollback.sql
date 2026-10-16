/* Rollback: DML-2026-179-menu-konversi-lembur-admin */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;
DELETE FROM HRIS_Page_Access WHERE Jenis_page = 'konversiLemburAdmin';
DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'konversiLemburAdmin';
DELETE FROM N_HRIS_Menus WHERE Jenis_page = 'konversiLemburAdmin';
COMMIT TRANSACTION;
