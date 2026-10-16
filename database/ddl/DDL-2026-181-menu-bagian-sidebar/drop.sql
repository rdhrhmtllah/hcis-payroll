/* Pembersihan manual DDL-2026-181 — JANGAN dijalankan agen. Jalankan rollback DML-2026-182 lebih dulu. */
SET NOCOUNT ON;
IF COL_LENGTH('dbo.N_HRIS_Menus', 'Sort_Section') IS NOT NULL ALTER TABLE dbo.N_HRIS_Menus DROP COLUMN Sort_Section;
IF COL_LENGTH('dbo.N_HRIS_Menus', 'Section_Icon') IS NOT NULL ALTER TABLE dbo.N_HRIS_Menus DROP COLUMN Section_Icon;
IF COL_LENGTH('dbo.N_HRIS_Menus', 'Section_Label') IS NOT NULL ALTER TABLE dbo.N_HRIS_Menus DROP COLUMN Section_Label;
IF COL_LENGTH('dbo.N_HRIS_Menus', 'Section_Code') IS NOT NULL ALTER TABLE dbo.N_HRIS_Menus DROP COLUMN Section_Code;
