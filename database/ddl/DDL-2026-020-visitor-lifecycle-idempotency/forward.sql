-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-020-visitor-lifecycle-idempotency

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Aktual_Masuk') IS NULL
ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Aktual_Masuk datetime2 NULL;
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Aktual_Keluar') IS NULL
ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Aktual_Keluar datetime2 NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Idempotency_Key') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Idempotency_Key varchar(100) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit') AND name = 'UX_VISITOR_Permit_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
ON N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Idempotency_Key)
WHERE Idempotency_Key IS NOT NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu', 'Idempotency_Key') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu ADD Idempotency_Key varchar(100) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu') AND name = 'UX_VISITOR_Tamu_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Tamu_Requester_Idempotency
ON N_EMI_VISITOR_Transaksi_Tamu (Kode_Karyawan_PIC, Idempotency_Key)
WHERE Idempotency_Key IS NOT NULL;
GO
