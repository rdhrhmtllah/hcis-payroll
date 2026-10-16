-- Change ID: DDL-2026-142-visitor-master-binding-id-card (manual cleanup only)
IF OBJECT_ID('dbo.N_EMI_Binding_NFC', 'U') IS NOT NULL
    DROP TABLE dbo.N_EMI_Binding_NFC;
GO
IF OBJECT_ID('dbo.N_EMI_Master_NFC', 'U') IS NOT NULL
    DROP TABLE dbo.N_EMI_Master_NFC;
GO
