-- DDL-2026-101-visitor-tipe-tamu-permit-approver
-- Target: Web_HRIS.
-- Backfill baris lama menjadi 'REGULAR'

UPDATE N_EMI_VISITOR_Transaksi_Tamu_Detail
   SET Tipe_Tamu = 'REGULAR'
 WHERE Tipe_Tamu IS NULL;
GO

UPDATE N_VISITOR_Tamu_Efektif
   SET Tipe_Tamu = 'REGULAR'
 WHERE Tipe_Tamu IS NULL;
GO
