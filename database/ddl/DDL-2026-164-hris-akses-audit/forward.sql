/* Change ID: DDL-2026-164-hris-akses-audit */
/* RBAC Fase 1: jejak perubahan role/akses (sebelum -> sesudah). Aditif. */
IF OBJECT_ID('dbo.N_HRIS_Akses_Audit', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Akses_Audit
    (
        Id_Akses_Audit bigint IDENTITY(1,1) NOT NULL,
        Waktu datetime2 NOT NULL CONSTRAINT DF_HRIS_Akses_Audit_Waktu DEFAULT (sysdatetime()),
        Oleh_Id_User int NULL,
        Target_Id_User int NULL,
        Subjek_Jenis varchar(20) NOT NULL,
        Subjek_Id varchar(100) NULL,
        Aksi varchar(50) NOT NULL,
        Nilai_Sebelum nvarchar(max) NULL,
        Nilai_Sesudah nvarchar(max) NULL,
        Alasan nvarchar(500) NULL,
        Sumber varchar(30) NOT NULL CONSTRAINT DF_HRIS_Akses_Audit_Sumber DEFAULT ('UI'),
        CONSTRAINT PK_HRIS_Akses_Audit PRIMARY KEY (Id_Akses_Audit)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Akses_Audit_Target' AND object_id = OBJECT_ID('dbo.N_HRIS_Akses_Audit'))
    CREATE INDEX IX_HRIS_Akses_Audit_Target ON dbo.N_HRIS_Akses_Audit (Target_Id_User, Waktu);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Akses_Audit_Waktu' AND object_id = OBJECT_ID('dbo.N_HRIS_Akses_Audit'))
    CREATE INDEX IX_HRIS_Akses_Audit_Waktu ON dbo.N_HRIS_Akses_Audit (Waktu);
GO
