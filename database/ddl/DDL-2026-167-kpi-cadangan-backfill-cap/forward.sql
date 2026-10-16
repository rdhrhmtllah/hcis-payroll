-- Change ID: DDL-2026-167-kpi-cadangan-backfill-cap
-- Target: Web_HRIS. Cadangan nilai lama untuk backfill cap KPI per parameter
-- (endpoint backfill-cap-parameter) + penanda potongan agar kiriman ulang
-- Cloud Tasks tidak memproses/menghitung dua kali.
IF OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_KPI_Backfill_Cap_Cadangan (
        Id_Cadangan                   bigint IDENTITY(1,1) NOT NULL,
        Batch_Id                      varchar(60)   NOT NULL,
        Jenis                         char(6)       NOT NULL,
        Id_Update_Performance         int           NOT NULL,
        Id_Update_Performance_Detail  int           NULL,
        Nilai_Lama_Json               nvarchar(max) NULL,
        Dipulihkan_At                 datetime2(0)  NULL,
        Created_At                    datetime2(0)  NOT NULL CONSTRAINT DF_KPI_Backfill_Cap_Created DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_KPI_Backfill_Cap_Cadangan PRIMARY KEY (Id_Cadangan),
        CONSTRAINT CK_KPI_Backfill_Cap_Jenis CHECK (Jenis IN ('HEADER', 'DETAIL', 'POTONG'))
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan') AND name = 'IX_KPI_Backfill_Cap_Batch')
    CREATE INDEX IX_KPI_Backfill_Cap_Batch
        ON dbo.N_KPI_Backfill_Cap_Cadangan (Batch_Id, Jenis, Id_Update_Performance);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan') AND name = 'UX_KPI_Backfill_Cap_Potong')
    CREATE UNIQUE INDEX UX_KPI_Backfill_Cap_Potong
        ON dbo.N_KPI_Backfill_Cap_Cadangan (Batch_Id, Id_Update_Performance)
        WHERE Jenis = 'POTONG';
GO
