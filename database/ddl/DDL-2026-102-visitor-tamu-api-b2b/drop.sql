-- Change ID: DDL-2026-102-visitor-tamu-api-b2b
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Tamu_Api_Aplikasi')
DROP INDEX IX_VISITOR_Tamu_Api_Aplikasi ON N_EMI_VISITOR_Transaksi_Tamu;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
DROP INDEX UX_VISITOR_Tamu_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Tamu;
GO

-- Dikembalikan ke bentuk sebelum change ini, bukan dihapus: indeks ini yang
-- menjaga registrasi tamu karyawan dari duplikasi kiriman ulang.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Tamu_Requester_Idempotency
    ON N_EMI_VISITOR_Transaksi_Tamu (Kode_Karyawan_PIC, Idempotency_Key)
    WHERE Idempotency_Key IS NOT NULL;
GO

ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu DROP COLUMN IF EXISTS Referensi_Mitra;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu DROP COLUMN IF EXISTS Id_Api_Aplikasi;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu DROP COLUMN IF EXISTS Sumber_Pengajuan;
GO
