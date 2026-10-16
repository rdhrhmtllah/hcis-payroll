-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.

IF COL_LENGTH('N_EMI_VISITOR_Master_Vehicle_Color', 'Flag_Aktif') IS NOT NULL
BEGIN
    DECLARE @VehicleColorDefault sysname;

    SELECT @VehicleColorDefault = dc.name
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
        AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Color')
      AND c.name = 'Flag_Aktif';

    IF @VehicleColorDefault IS NOT NULL
        EXEC('ALTER TABLE N_EMI_VISITOR_Master_Vehicle_Color DROP CONSTRAINT ' + QUOTENAME(@VehicleColorDefault));

    ALTER TABLE N_EMI_VISITOR_Master_Vehicle_Color DROP COLUMN Flag_Aktif;
END;
GO

IF COL_LENGTH('N_EMI_VISITOR_Master_Purpose', 'Flag_Aktif') IS NOT NULL
BEGIN
    DECLARE @PurposeDefault sysname;

    SELECT @PurposeDefault = dc.name
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
        AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID('N_EMI_VISITOR_Master_Purpose')
      AND c.name = 'Flag_Aktif';

    IF @PurposeDefault IS NOT NULL
        EXEC('ALTER TABLE N_EMI_VISITOR_Master_Purpose DROP CONSTRAINT ' + QUOTENAME(@PurposeDefault));

    ALTER TABLE N_EMI_VISITOR_Master_Purpose DROP COLUMN Flag_Aktif;
END;
GO
