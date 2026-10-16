-- Change ID: DDL-2026-158-sop-versi-lampiran-form
-- Target: Web_HRIS. Form kosong (lampiran unduhan) per versi dokumen FORM.
IF OBJECT_ID('dbo.N_SOP_Item_Versi_Lampiran', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Item_Versi_Lampiran (
        Id_Lampiran        int IDENTITY(1,1) NOT NULL,
        Id_SOP_Item_Versi  int          NOT NULL,
        Id_Master_Konten   int          NOT NULL,
        Urutan             int          NOT NULL CONSTRAINT DF_SOP_Versi_Lampiran_Urutan DEFAULT (0),
        Flag_Aktif         char(1)      NOT NULL CONSTRAINT DF_SOP_Versi_Lampiran_Aktif DEFAULT ('Y'),
        Created_At         datetime2(0) NOT NULL CONSTRAINT DF_SOP_Versi_Lampiran_Created DEFAULT (SYSDATETIME()),
        Created_By         int          NULL,
        Updated_At         datetime2(0) NULL,
        Updated_By         int          NULL,
        CONSTRAINT PK_SOP_Item_Versi_Lampiran PRIMARY KEY (Id_Lampiran),
        CONSTRAINT FK_SOP_Versi_Lampiran_Versi FOREIGN KEY (Id_SOP_Item_Versi)
            REFERENCES dbo.N_SOP_Item_Versi (Id_SOP_Item_Versi),
        CONSTRAINT FK_SOP_Versi_Lampiran_Konten FOREIGN KEY (Id_Master_Konten)
            REFERENCES dbo.N_LMS_Master_Konten (Id_Master_Konten)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_SOP_Item_Versi_Lampiran') AND name = 'IX_SOP_Versi_Lampiran_Versi')
    CREATE INDEX IX_SOP_Versi_Lampiran_Versi
        ON dbo.N_SOP_Item_Versi_Lampiran (Id_SOP_Item_Versi, Flag_Aktif, Urutan);
GO
