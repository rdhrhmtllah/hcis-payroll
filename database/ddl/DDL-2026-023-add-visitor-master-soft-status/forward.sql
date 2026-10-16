-- Target: Web_HRIS. Development execution only by the agent; production via SQL Tracker.
-- Change ID: DDL-2026-023-add-visitor-master-soft-status

IF OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Color', 'U') IS NULL
    THROW 50001, 'Table N_EMI_VISITOR_Master_Vehicle_Color was not found.', 1;
GO

IF COL_LENGTH('N_EMI_VISITOR_Master_Vehicle_Color', 'Flag_Aktif') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Master_Vehicle_Color
        ADD Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_VISITOR_Master_Vehicle_Color_Flag_Aktif DEFAULT ('Y') WITH VALUES;
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Purpose', 'U') IS NULL
    THROW 50002, 'Table N_EMI_VISITOR_Master_Purpose was not found.', 1;
GO

IF COL_LENGTH('N_EMI_VISITOR_Master_Purpose', 'Flag_Aktif') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Master_Purpose
        ADD Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_VISITOR_Master_Purpose_Flag_Aktif DEFAULT ('Y') WITH VALUES;
END;
GO
