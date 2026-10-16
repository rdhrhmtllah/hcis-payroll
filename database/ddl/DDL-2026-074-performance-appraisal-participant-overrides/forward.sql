/* Change ID: DDL-2026-074-performance-appraisal-participant-overrides
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064, 067, 071, 072. Jalankan sesudah semuanya.
   Scope: tiga tabel override per peserta. JARANG - baris hanya lahir ketika
          seorang peserta menyimpang dari salinan aturan siklusnya. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50230, 'DDL-2026-074 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Participant', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency_Type', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Rater_Policy_Detail_Snapshot', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Competency_Requirement_Snapshot', 'U') IS NULL
    THROW 50231, 'DDL-2026-074 membutuhkan peserta, master kategori/jenis, dan salinan siklus terkait.', 1;
GO

IF OBJECT_ID('N_PA_Participant_Rater_Override', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Rater_Override (
        Id_PA_Participant_Rater_Override int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        PA_Cycle_Rater_Policy_Detail_Snapshot_Id int NULL,
        Flag_Dipakai char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Rater_Ovr_Dipakai DEFAULT ('Y'),
        Bobot_Persen decimal(5,2) NULL,
        Min_Rater smallint NULL,
        Maks_Rater smallint NULL,
        Min_Rater_Selesai smallint NULL,
        Alasan_Perubahan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Rater_Ovr_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Rater_Ovr_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Rater_Override
            PRIMARY KEY (Id_PA_Participant_Rater_Override),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Lengkap
            CHECK (
                Flag_Dipakai = 'T'
                OR (Bobot_Persen IS NOT NULL AND Min_Rater IS NOT NULL)
            ),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Bobot
            CHECK (Bobot_Persen IS NULL OR (Bobot_Persen >= 0 AND Bobot_Persen <= 100)),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Min
            CHECK (Min_Rater IS NULL OR Min_Rater >= 1),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Maks
            CHECK (Maks_Rater IS NULL OR Min_Rater IS NULL OR Maks_Rater >= Min_Rater),
        CONSTRAINT CK_PA_Participant_Rater_Ovr_Selesai
            CHECK (
                Min_Rater_Selesai IS NULL
                OR (
                    Min_Rater_Selesai >= 1
                    AND (Maks_Rater IS NULL OR Min_Rater_Selesai <= Maks_Rater)
                )
            )
    );
END;
GO

IF OBJECT_ID('N_PA_Participant_Pillar_Override', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Pillar_Override (
        Id_PA_Participant_Pillar_Override int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        Bobot_KPI decimal(5,2) NOT NULL,
        Bobot_Kompetensi decimal(5,2) NOT NULL,
        Alasan_Perubahan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Pillar_Ovr_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Pillar_Ovr_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Pillar_Override
            PRIMARY KEY (Id_PA_Participant_Pillar_Override),
        CONSTRAINT CK_PA_Participant_Pillar_Ovr_Non_Negatif
            CHECK (Bobot_KPI >= 0 AND Bobot_Kompetensi >= 0),
        CONSTRAINT CK_PA_Participant_Pillar_Ovr_Total
            CHECK (Bobot_KPI + Bobot_Kompetensi = 100)
    );
END;
GO

IF OBJECT_ID('N_PA_Participant_Requirement_Override', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Requirement_Override (
        Id_PA_Participant_Requirement_Override int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        PA_Competency_Type_Id int NOT NULL,
        PA_Cycle_Competency_Requirement_Snapshot_Id int NULL,
        Flag_Dipakai char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Req_Ovr_Dipakai DEFAULT ('Y'),
        Flag_Wajib char(1) NULL,
        Alasan_Perubahan nvarchar(500) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Req_Ovr_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Req_Ovr_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Requirement_Override
            PRIMARY KEY (Id_PA_Participant_Requirement_Override),
        CONSTRAINT CK_PA_Participant_Req_Ovr_Lengkap
            CHECK (Flag_Dipakai = 'T' OR Flag_Wajib IS NOT NULL)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Rater_Ovr_Participant')
    ALTER TABLE N_PA_Participant_Rater_Override WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Rater_Ovr_Participant
        FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Rater_Ovr_Category')
    ALTER TABLE N_PA_Participant_Rater_Override WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Rater_Ovr_Category
        FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Rater_Ovr_Snapshot')
    ALTER TABLE N_PA_Participant_Rater_Override WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Rater_Ovr_Snapshot
        FOREIGN KEY (PA_Cycle_Rater_Policy_Detail_Snapshot_Id)
        REFERENCES N_PA_Cycle_Rater_Policy_Detail_Snapshot (Id_PA_Cycle_Rater_Policy_Detail_Snapshot);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Pillar_Ovr_Participant')
    ALTER TABLE N_PA_Participant_Pillar_Override WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Pillar_Ovr_Participant
        FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Req_Ovr_Participant')
    ALTER TABLE N_PA_Participant_Requirement_Override WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Req_Ovr_Participant
        FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Req_Ovr_Type')
    ALTER TABLE N_PA_Participant_Requirement_Override WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Req_Ovr_Type
        FOREIGN KEY (PA_Competency_Type_Id) REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Req_Ovr_Snapshot')
    ALTER TABLE N_PA_Participant_Requirement_Override WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Req_Ovr_Snapshot
        FOREIGN KEY (PA_Cycle_Competency_Requirement_Snapshot_Id)
        REFERENCES N_PA_Cycle_Competency_Requirement_Snapshot (Id_PA_Cycle_Competency_Requirement_Snapshot);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Participant_Rater_Ovr_Aktif')
    CREATE UNIQUE INDEX UX_PA_Participant_Rater_Ovr_Aktif
        ON N_PA_Participant_Rater_Override (PA_Participant_Id, PA_Rater_Category_Id)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Participant_Pillar_Ovr_Aktif')
    CREATE UNIQUE INDEX UX_PA_Participant_Pillar_Ovr_Aktif
        ON N_PA_Participant_Pillar_Override (PA_Participant_Id)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Participant_Req_Ovr_Aktif')
    CREATE UNIQUE INDEX UX_PA_Participant_Req_Ovr_Aktif
        ON N_PA_Participant_Requirement_Override (PA_Participant_Id, PA_Competency_Type_Id)
        WHERE Flag_Aktif = 'Y';
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Participant_Rater_Override')) <> 15
    THROW 50232, 'DDL-2026-074: kolom Participant Rater Override tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Participant_Pillar_Override')) <> 10
    THROW 50233, 'DDL-2026-074: kolom Participant Pillar Override tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Participant_Requirement_Override')) <> 12
    THROW 50234, 'DDL-2026-074: kolom Participant Requirement Override tidak lengkap.', 1;

IF (
    SELECT COUNT(*) FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Participant_Rater_Override'),
        OBJECT_ID('N_PA_Participant_Pillar_Override'),
        OBJECT_ID('N_PA_Participant_Requirement_Override')
    )
) <> 7
    THROW 50235, 'DDL-2026-074: jumlah foreign key override bukan 7.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Participant_Rater_Override'),
        OBJECT_ID('N_PA_Participant_Pillar_Override'),
        OBJECT_ID('N_PA_Participant_Requirement_Override')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50236, 'DDL-2026-074: terdapat FK override yang disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Participant_Rater_Override'),
        OBJECT_ID('N_PA_Participant_Pillar_Override'),
        OBJECT_ID('N_PA_Participant_Requirement_Override')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50237, 'DDL-2026-074: terdapat CHECK override yang disabled atau tidak trusted.', 1;
GO
