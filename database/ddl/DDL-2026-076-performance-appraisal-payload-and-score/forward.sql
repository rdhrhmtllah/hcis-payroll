/* Change ID: DDL-2026-076-performance-appraisal-payload-and-score
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-067, 075. Jalankan sesudah keduanya.
   Scope: bundel mentah hasil HC Learn dan skor per butir hasil proyeksi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50260, 'DDL-2026-076 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Assignment_Integration', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency', 'U') IS NULL
    THROW 50261, 'DDL-2026-076 membutuhkan tabel integrasi penugasan dan master kompetensi.', 1;
GO

IF OBJECT_ID('N_PA_Assignment_Payload', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Payload (
        Id_PA_Assignment_Payload int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Integration_Id int NOT NULL,
        Payload_Json nvarchar(max) NOT NULL,
        Payload_Hash varchar(64) NOT NULL,
        Id_Ujian_Nilai_Akhir varchar(255) NULL,
        Api_Version varchar(20) NULL,
        Diterima_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Payload_Diterima DEFAULT (SYSDATETIME()),
        Flag_Terpeta char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Payload_Terpeta DEFAULT ('T'),
        Mapper_Version int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment_Payload
            PRIMARY KEY (Id_PA_Assignment_Payload),
        CONSTRAINT CK_PA_Assignment_Payload_Hash
            CHECK (LEN(Payload_Hash) = 64),
        CONSTRAINT CK_PA_Assignment_Payload_Terpeta
            CHECK (Flag_Terpeta = 'T' OR Mapper_Version IS NOT NULL),
        CONSTRAINT CK_PA_Assignment_Payload_Mapper
            CHECK (Mapper_Version IS NULL OR Mapper_Version >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Assignment_Score', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Score (
        Id_PA_Assignment_Score int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Payload_Id int NOT NULL,
        PA_Competency_Id int NULL,
        Kode_Soal_Sumber varchar(100) NOT NULL,
        Jenis_Soal_Sumber varchar(100) NULL,
        Skor decimal(6,2) NULL,
        Skor_Maksimal decimal(6,2) NULL,
        Bobot_Sumber decimal(5,2) NULL,
        Jawaban_Teks nvarchar(max) NULL,
        Mapper_Version int NOT NULL,
        Urutan_Tampil smallint NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Score_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Score_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment_Score
            PRIMARY KEY (Id_PA_Assignment_Score),
        CONSTRAINT CK_PA_Assignment_Score_Maksimal
            CHECK (Skor_Maksimal IS NULL OR Skor_Maksimal > 0),
        CONSTRAINT CK_PA_Assignment_Score_Skor
            CHECK (Skor IS NULL OR Skor >= 0),
        CONSTRAINT CK_PA_Assignment_Score_Batas
            CHECK (Skor IS NULL OR Skor_Maksimal IS NULL OR Skor <= Skor_Maksimal),
        CONSTRAINT CK_PA_Assignment_Score_Bobot
            CHECK (Bobot_Sumber IS NULL OR (Bobot_Sumber >= 0 AND Bobot_Sumber <= 100)),
        CONSTRAINT CK_PA_Assignment_Score_Mapper
            CHECK (Mapper_Version >= 1),
        CONSTRAINT CK_PA_Assignment_Score_Urutan
            CHECK (Urutan_Tampil IS NULL OR Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Payload_Integration')
    ALTER TABLE N_PA_Assignment_Payload WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Payload_Integration
        FOREIGN KEY (PA_Assignment_Integration_Id)
        REFERENCES N_PA_Assignment_Integration (Id_PA_Assignment_Integration);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Score_Payload')
    ALTER TABLE N_PA_Assignment_Score WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Score_Payload
        FOREIGN KEY (PA_Assignment_Payload_Id)
        REFERENCES N_PA_Assignment_Payload (Id_PA_Assignment_Payload);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Score_Competency')
    ALTER TABLE N_PA_Assignment_Score WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Score_Competency
        FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Assignment_Payload_Nilai_Akhir')
    CREATE UNIQUE INDEX UX_PA_Assignment_Payload_Nilai_Akhir
        ON N_PA_Assignment_Payload (Id_Ujian_Nilai_Akhir)
        WHERE Id_Ujian_Nilai_Akhir IS NOT NULL;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Assignment_Payload_Integration')
    CREATE INDEX IX_PA_Assignment_Payload_Integration
        ON N_PA_Assignment_Payload (PA_Assignment_Integration_Id, Flag_Terpeta)
        INCLUDE (Diterima_At, Mapper_Version);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Assignment_Payload_Hash')
    CREATE INDEX IX_PA_Assignment_Payload_Hash
        ON N_PA_Assignment_Payload (Payload_Hash);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Assignment_Score_Butir')
    CREATE UNIQUE INDEX UX_PA_Assignment_Score_Butir
        ON N_PA_Assignment_Score (PA_Assignment_Payload_Id, Kode_Soal_Sumber)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Assignment_Score_Agregasi')
    CREATE INDEX IX_PA_Assignment_Score_Agregasi
        ON N_PA_Assignment_Score (PA_Assignment_Payload_Id, Flag_Aktif)
        INCLUDE (PA_Competency_Id, Skor, Skor_Maksimal, Bobot_Sumber);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Assignment_Score_Competency')
    CREATE INDEX IX_PA_Assignment_Score_Competency
        ON N_PA_Assignment_Score (PA_Competency_Id, Flag_Aktif)
        INCLUDE (Skor, Skor_Maksimal);
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Assignment_Payload')) <> 11
    THROW 50262, 'DDL-2026-076: kolom Assignment Payload tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Assignment_Score')) <> 16
    THROW 50263, 'DDL-2026-076: kolom Assignment Score tidak lengkap.', 1;

IF (
    SELECT COUNT(*) FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Assignment_Payload'),
        OBJECT_ID('N_PA_Assignment_Score')
    )
) <> 3
    THROW 50264, 'DDL-2026-076: jumlah foreign key bukan 3.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Assignment_Payload'),
        OBJECT_ID('N_PA_Assignment_Score')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50265, 'DDL-2026-076: terdapat FK yang disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Assignment_Payload'),
        OBJECT_ID('N_PA_Assignment_Score')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50266, 'DDL-2026-076: terdapat CHECK yang disabled atau tidak trusted.', 1;
GO
