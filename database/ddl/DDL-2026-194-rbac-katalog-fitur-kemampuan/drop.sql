-- Change ID: DDL-2026-194-rbac-katalog-fitur-kemampuan
-- PEMBERSIHAN MANUAL — tidak dijalankan agent.

IF OBJECT_ID('dbo.N_HRIS_Role_Kemampuan', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Role_Kemampuan;

IF OBJECT_ID('dbo.N_HRIS_Fitur_Kemampuan', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Fitur_Kemampuan;

IF OBJECT_ID('dbo.N_HRIS_Fitur', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Fitur;
