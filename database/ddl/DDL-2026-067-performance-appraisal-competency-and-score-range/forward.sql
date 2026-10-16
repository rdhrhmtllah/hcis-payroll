/* Change ID: DDL-2026-067-performance-appraisal-competency-and-score-range
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: HRIS_Level.
   Scope: master jenis kompetensi, master kompetensi, pemetaan kode soal HC Learn,
          matriks kebutuhan kompetensi per level, dan master pita nilai. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50130, 'DDL-2026-067 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('HRIS_Level', 'U') IS NULL
   OR COL_LENGTH('HRIS_Level', 'ID_Level') IS NULL
    THROW 50131, 'DDL-2026-067 membutuhkan HRIS_Level.ID_Level.', 1;
GO

IF OBJECT_ID('N_PA_Competency_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Type (
        Id_PA_Competency_Type int IDENTITY(1,1) NOT NULL,
        Kode_Type varchar(40) NOT NULL,
        Nama_Type nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Type_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Competency_Type
            PRIMARY KEY (Id_PA_Competency_Type),
        CONSTRAINT CK_PA_Competency_Type_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Competency', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency (
        Id_PA_Competency int IDENTITY(1,1) NOT NULL,
        PA_Competency_Type_Id int NOT NULL,
        Kode_Competency varchar(40) NOT NULL,
        Nama_Competency nvarchar(150) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Flag_Berskor char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Berskor DEFAULT ('Y'),
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Competency
            PRIMARY KEY (Id_PA_Competency),
        CONSTRAINT CK_PA_Competency_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

/* DICABUT pada amandemen r2 — N_PA_Competency_Kode_Soal tidak pernah dibuat.

   Tabel ini lahir dari rancangan awal yang memetakan kode soal langsung ke kompetensi.
   Rancangannya berubah: pemetaan itu kini hidup di N_PA_Cycle_Question_Package_Item
   (DDL-2026-073, diperluas DDL-2026-083 r2), yang membekukannya PER SIKLUS - dan itu yang
   benar, karena paket soal boleh berganti antar siklus sementara hasil lama harus tetap
   terbaca dengan paket asalnya.

   Per 1 September 2026 tabel ini nol baris di dev dan nol pembaca di app/, resources/,
   routes/, maupun tests/. Ia dicabut dari artefak alih-alih dibuat-lalu-di-DROP, karena
   DDL ini BELUM PERNAH masuk production: yang tidak pernah lahir tidak perlu dibunuh, dan
   itu menghindari DROP yang memang dibatasi aturan proyek.

   Dev yang terlanjur punya tabelnya tidak ikut dibersihkan artefak ini. Nol baris dan nol
   pembaca membuatnya tidak berbahaya di sana; membuangnya keputusan terpisah. */

GO

IF OBJECT_ID('N_PA_Competency_Requirement', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Competency_Requirement (
        Id_PA_Competency_Requirement int IDENTITY(1,1) NOT NULL,
        ID_Level bigint NOT NULL,
        PA_Competency_Type_Id int NOT NULL,
        Flag_Wajib char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Requirement_Wajib DEFAULT ('Y'),
        Catatan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Requirement_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Competency_Requirement_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Competency_Requirement
            PRIMARY KEY (Id_PA_Competency_Requirement)
    );
END;
GO

IF OBJECT_ID('N_PA_Score_Range', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Score_Range (
        Id_PA_Score_Range int IDENTITY(1,1) NOT NULL,
        Score_Type varchar(20) NOT NULL,
        Score_Usage varchar(20) NOT NULL,
        Kode_Range varchar(40) NOT NULL,
        Nama_Range nvarchar(100) NOT NULL,
        Nilai_Dari decimal(6,2) NOT NULL,
        Nilai_Sampai decimal(6,2) NOT NULL,
        Nilai_Hasil decimal(6,2) NULL,
        Target_Persen decimal(5,2) NULL,
        Warna varchar(20) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Range_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Score_Range_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Score_Range
            PRIMARY KEY (Id_PA_Score_Range),
        CONSTRAINT CK_PA_Score_Range_Batas
            CHECK (Nilai_Sampai >= Nilai_Dari),
        CONSTRAINT CK_PA_Score_Range_Target
            CHECK (
                Target_Persen IS NULL
                OR (Target_Persen >= 0 AND Target_Persen <= 100)
            ),
        CONSTRAINT CK_PA_Score_Range_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'FK_PA_Competency_Type'
)
BEGIN
    ALTER TABLE N_PA_Competency WITH CHECK
        ADD CONSTRAINT FK_PA_Competency_Type
        FOREIGN KEY (PA_Competency_Type_Id)
        REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Competency_Requirement')
      AND name = 'FK_PA_Competency_Requirement_Level'
)
BEGIN
    ALTER TABLE N_PA_Competency_Requirement WITH CHECK
        ADD CONSTRAINT FK_PA_Competency_Requirement_Level
        FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Competency_Requirement')
      AND name = 'FK_PA_Competency_Requirement_Type'
)
BEGIN
    ALTER TABLE N_PA_Competency_Requirement WITH CHECK
        ADD CONSTRAINT FK_PA_Competency_Requirement_Type
        FOREIGN KEY (PA_Competency_Type_Id)
        REFERENCES N_PA_Competency_Type (Id_PA_Competency_Type);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Competency_Type') AND name = 'UX_PA_Competency_Type_Kode'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Competency_Type_Kode
        ON N_PA_Competency_Type (Kode_Type);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Competency') AND name = 'UX_PA_Competency_Kode'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Competency_Kode
        ON N_PA_Competency (Kode_Competency);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Competency') AND name = 'IX_PA_Competency_Type_Urutan'
)
BEGIN
    CREATE INDEX IX_PA_Competency_Type_Urutan
        ON N_PA_Competency (PA_Competency_Type_Id, Flag_Aktif, Urutan_Tampil)
        INCLUDE (Kode_Competency, Nama_Competency, Flag_Berskor);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Competency_Requirement') AND name = 'UX_PA_Competency_Requirement_Aktif'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Competency_Requirement_Aktif
        ON N_PA_Competency_Requirement (ID_Level, PA_Competency_Type_Id)
        WHERE Flag_Aktif = 'Y';
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'UX_PA_Score_Range_Aktif'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Score_Range_Aktif
        ON N_PA_Score_Range (Score_Type, Score_Usage, Kode_Range)
        WHERE Flag_Aktif = 'Y';
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'IX_PA_Score_Range_Lookup'
)
BEGIN
    CREATE INDEX IX_PA_Score_Range_Lookup
        ON N_PA_Score_Range (Score_Type, Score_Usage, Flag_Aktif)
        INCLUDE (Nilai_Dari, Nilai_Sampai, Kode_Range, Nama_Range, Nilai_Hasil, Target_Persen, Warna);
END;
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Competency_Type')) <> 10
    THROW 50132, 'DDL-2026-067: kolom Competency Type tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Competency')) <> 12
    THROW 50133, 'DDL-2026-067: kolom Competency tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Competency_Requirement')) <> 10
    THROW 50135, 'DDL-2026-067: kolom Competency Requirement tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Score_Range')) <> 16
    THROW 50136, 'DDL-2026-067: kolom Score Range tidak lengkap.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Competency'),
        OBJECT_ID('N_PA_Competency_Requirement')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50137, 'DDL-2026-067: terdapat FK disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Competency_Type'),
        OBJECT_ID('N_PA_Competency'),
        OBJECT_ID('N_PA_Score_Range')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50138, 'DDL-2026-067: terdapat CHECK disabled atau tidak trusted.', 1;
GO
