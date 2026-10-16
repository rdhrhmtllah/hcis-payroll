IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail') AND name = 'Score_Raw')
    ALTER TABLE KPI_Update_Performance_Detail ADD Score_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail') AND name = 'Achievement_Raw')
    ALTER TABLE KPI_Update_Performance_Detail ADD Achievement_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail') AND name = 'Flag_Capped')
    ALTER TABLE KPI_Update_Performance_Detail
        ADD Flag_Capped char(1) NOT NULL
        CONSTRAINT DF_KPI_Update_Performance_Detail_Flag_Capped DEFAULT ('T');
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail_Sementara') AND name = 'Score_Raw')
    ALTER TABLE KPI_Update_Performance_Detail_Sementara ADD Score_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail_Sementara') AND name = 'Achievement_Raw')
    ALTER TABLE KPI_Update_Performance_Detail_Sementara ADD Achievement_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail_Sementara') AND name = 'Flag_Capped')
    ALTER TABLE KPI_Update_Performance_Detail_Sementara
        ADD Flag_Capped char(1) NOT NULL
        CONSTRAINT DF_KPI_Update_Performance_Detail_Sementara_Flag_Capped DEFAULT ('T');
GO
