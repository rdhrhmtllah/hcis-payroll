-- Change ID: DDL-2026-165-rbac-role-hak-cakupan
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Menghapus model RBAC Fase 2 beserta datanya.
IF OBJECT_ID('dbo.N_HRIS_Role_User_Tanggung_Jawab', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Role_User_Tanggung_Jawab;
GO
IF OBJECT_ID('dbo.N_HRIS_Role_User_Cakupan', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Role_User_Cakupan;
GO
IF OBJECT_ID('dbo.N_HRIS_Role_Hak', 'U') IS NOT NULL DROP TABLE dbo.N_HRIS_Role_Hak;
GO
IF COL_LENGTH('dbo.HRIS_Page_Access', 'Berlaku_Sampai') IS NOT NULL ALTER TABLE dbo.HRIS_Page_Access DROP COLUMN Berlaku_Sampai;
GO
IF COL_LENGTH('dbo.HRIS_Page_Access', 'Alasan') IS NOT NULL ALTER TABLE dbo.HRIS_Page_Access DROP COLUMN Alasan;
GO
IF COL_LENGTH('dbo.N_HRIS_Role_User', 'Alasan') IS NOT NULL ALTER TABLE dbo.N_HRIS_Role_User DROP COLUMN Alasan;
GO
IF COL_LENGTH('dbo.N_HRIS_Role_User', 'Berlaku_Sampai') IS NOT NULL ALTER TABLE dbo.N_HRIS_Role_User DROP COLUMN Berlaku_Sampai;
GO
IF COL_LENGTH('dbo.N_HRIS_Role_Functional', 'Jenis_Role') IS NOT NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Role_Functional DROP CONSTRAINT DF_HRIS_Role_Functional_Jenis;
    ALTER TABLE dbo.N_HRIS_Role_Functional DROP COLUMN Jenis_Role;
END;
GO
