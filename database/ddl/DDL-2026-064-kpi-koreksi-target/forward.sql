IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_KPI_Target_Koreksi')
CREATE TABLE N_KPI_Target_Koreksi (
    Id_Target_Koreksi   int IDENTITY(1,1) NOT NULL,
    No_Batch            varchar(40)   NOT NULL,
    Id_Pengajuan_Det    int           NOT NULL,
    Nilai_Target_Lama   float         NOT NULL,
    Nilai_Target_Baru   float         NOT NULL,
    Sebab               nvarchar(500) NOT NULL,
    Id_User_Aktor       int           NOT NULL,
    Waktu               datetime      NOT NULL CONSTRAINT DF_N_KPI_Target_Koreksi_Waktu DEFAULT (GETDATE()),
    CONSTRAINT PK_N_KPI_Target_Koreksi PRIMARY KEY (Id_Target_Koreksi)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_KPI_Target_Koreksi_Det')
    CREATE INDEX IX_N_KPI_Target_Koreksi_Det
        ON N_KPI_Target_Koreksi (Id_Pengajuan_Det, Waktu DESC);
GO
