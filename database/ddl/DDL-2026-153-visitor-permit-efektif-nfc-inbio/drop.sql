-- Change ID: DDL-2026-153-visitor-permit-efektif-nfc-inbio
-- PEMBERSIHAN MANUAL — tidak dijalankan agent.
IF COL_LENGTH('dbo.N_VISITOR_Permit_Efektif', 'NFC_Inbio') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Permit_Efektif DROP COLUMN NFC_Inbio;
GO
