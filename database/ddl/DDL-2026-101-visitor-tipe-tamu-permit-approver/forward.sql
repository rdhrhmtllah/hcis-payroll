-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-101-visitor-tipe-tamu-permit-approver

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Tipe_Tamu varchar(20) NULL CONSTRAINT DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu DEFAULT 'REGULAR';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu'
)
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD CONSTRAINT CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu
            CHECK (Tipe_Tamu IS NULL OR Tipe_Tamu IN ('REGULAR', 'B2B'));
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Tipe_Tamu varchar(20) NULL CONSTRAINT DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu DEFAULT 'REGULAR';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu'
)
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif
        ADD CONSTRAINT CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu
            CHECK (Tipe_Tamu IS NULL OR Tipe_Tamu IN ('REGULAR', 'B2B'));
END
GO
