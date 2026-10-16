-- Change ID: DDL-2026-103-visitor-permit-coklat-approval
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Peringatan: mengembalikan skema TIDAK mengembalikan datanya. Permit COKLAT
-- yang sudah terbit sebagai transaksi PMT akan kehilangan tautannya ke
-- kunjungan, dan tidak ada cara memulihkannya selain dari cadangan.

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
DROP INDEX IX_VISITOR_Permit_Pemohon_Sumber_Tamu ON N_EMI_VISITOR_Transaksi_Permit;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Transaksi_Tamu')
DROP INDEX UX_VISITOR_Permit_Transaksi_Tamu ON N_EMI_VISITOR_Transaksi_Permit;
GO

ALTER TABLE N_EMI_VISITOR_Transaksi_Permit DROP COLUMN IF EXISTS No_Transaksi_Tamu;
GO
