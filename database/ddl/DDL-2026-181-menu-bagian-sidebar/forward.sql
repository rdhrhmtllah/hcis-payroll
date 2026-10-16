/* Change ID: DDL-2026-181-menu-bagian-sidebar — tingkat "bagian" (opsional) di atas grup menu sidebar. IDEMPOTEN. */
SET NOCOUNT ON;
SET XACT_ABORT ON;

IF COL_LENGTH('dbo.N_HRIS_Menus', 'Section_Code') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD Section_Code varchar(100) NULL;
IF COL_LENGTH('dbo.N_HRIS_Menus', 'Section_Label') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD Section_Label varchar(150) NULL;
IF COL_LENGTH('dbo.N_HRIS_Menus', 'Section_Icon') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD Section_Icon varchar(100) NULL;
IF COL_LENGTH('dbo.N_HRIS_Menus', 'Sort_Section') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD Sort_Section int NULL;
