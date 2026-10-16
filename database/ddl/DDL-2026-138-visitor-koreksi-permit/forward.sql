-- Change ID: DDL-2026-138-visitor-koreksi-permit
-- Riwayat koreksi data permit oleh verifikator saat review. Satu baris per field.

IF OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi (
        Id_Permit_Koreksi int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_VISITOR_Permit_Koreksi PRIMARY KEY,
        No_Transaksi varchar(50) NOT NULL,
        Kelompok_Koreksi uniqueidentifier NOT NULL,
        Nama_Field varchar(50) NOT NULL,
        Nilai_Lama nvarchar(500) NULL,
        Nilai_Baru nvarchar(500) NULL,
        Alasan nvarchar(500) NOT NULL,
        Created_At datetime2 NOT NULL
            CONSTRAINT DF_VISITOR_Permit_Koreksi_Created_At DEFAULT (sysdatetime()),
        Created_By varchar(100) NOT NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Koreksi_No_Transaksi')
BEGIN
    CREATE INDEX IX_VISITOR_Permit_Koreksi_No_Transaksi
        ON dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi (No_Transaksi)
        INCLUDE (Created_At);
END;
GO
