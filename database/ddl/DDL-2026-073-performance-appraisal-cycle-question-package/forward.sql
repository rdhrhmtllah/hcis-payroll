/* Change ID: DDL-2026-073-performance-appraisal-cycle-question-package
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-066 dan DDL-2026-067. Jalankan sesudah keduanya.
   Scope: paket soal HC Learn yang dipilih per (siklus, level) beserta salinan
          daftar kode soalnya sebagai kontrak harapan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50220, 'DDL-2026-073 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency', 'U') IS NULL
   OR OBJECT_ID('HRIS_Level', 'U') IS NULL
    THROW 50221, 'DDL-2026-073 membutuhkan N_PA_Cycle, N_PA_Competency, dan HRIS_Level.', 1;
GO

IF OBJECT_ID('N_PA_Cycle_Question_Package', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Question_Package (
        Id_PA_Cycle_Question_Package int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        ID_Level bigint NOT NULL,
        Kode_Paket_Sumber varchar(100) NOT NULL,
        Nama_Paket_Sumber nvarchar(200) NULL,
        Sumber_Kontrak varchar(20) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Package_Sumber DEFAULT ('OTOMATIS'),
        Catatan nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Package_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Package_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Question_Package
            PRIMARY KEY (Id_PA_Cycle_Question_Package)
    );
END;
GO

IF OBJECT_ID('N_PA_Cycle_Question_Package_Item', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Question_Package_Item (
        Id_PA_Cycle_Question_Package_Item int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Question_Package_Id int NOT NULL,
        Kode_Soal varchar(100) NOT NULL,
        Nama_Soal_Sumber nvarchar(200) NULL,
        PA_Competency_Id int NULL,
        Bobot_Penilaian decimal(5,2) NULL,
        Skor_Maksimal decimal(6,2) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Item_Aktif DEFAULT ('Y'),
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Question_Item_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Question_Package_Item
            PRIMARY KEY (Id_PA_Cycle_Question_Package_Item),
        CONSTRAINT CK_PA_Cycle_Question_Item_Bobot
            CHECK (
                Bobot_Penilaian IS NULL
                OR (Bobot_Penilaian >= 0 AND Bobot_Penilaian <= 100)
            ),
        CONSTRAINT CK_PA_Cycle_Question_Item_Maksimal
            CHECK (Skor_Maksimal IS NULL OR Skor_Maksimal > 0),
        CONSTRAINT CK_PA_Cycle_Question_Item_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Package_Cycle')
    ALTER TABLE N_PA_Cycle_Question_Package WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Question_Package_Cycle
        FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Package_Level')
    ALTER TABLE N_PA_Cycle_Question_Package WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Question_Package_Level
        FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Item_Header')
    ALTER TABLE N_PA_Cycle_Question_Package_Item WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Question_Item_Header
        FOREIGN KEY (PA_Cycle_Question_Package_Id)
        REFERENCES N_PA_Cycle_Question_Package (Id_PA_Cycle_Question_Package);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Item_Competency')
    ALTER TABLE N_PA_Cycle_Question_Package_Item WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Question_Item_Competency
        FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Cycle_Question_Package_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Question_Package_Aktif
        ON N_PA_Cycle_Question_Package (PA_Cycle_Id, ID_Level)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Cycle_Question_Item_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Question_Item_Aktif
        ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Kode_Soal)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Cycle_Question_Item_Cakupan')
    CREATE INDEX IX_PA_Cycle_Question_Item_Cakupan
        ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Flag_Aktif)
        INCLUDE (Kode_Soal, PA_Competency_Id, Bobot_Penilaian, Skor_Maksimal);
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package')) <> 12
    THROW 50222, 'DDL-2026-073: kolom Cycle Question Package tidak lengkap.', 1;

/* Yang dihitung hanya kolom MILIK change ini, bukan seluruh isi tabel.
   Menuntut jumlah persis membuat penjaga ini menyala setiap kali change berikutnya
   menambah kolom secara sah - dan itu benar-benar terjadi: DDL-2026-083 r2 menambah
   Jenis_Soal dan PA_Result_Note_Type_Id, sehingga penjaga ini gagal sejak saat itu
   tanpa ada yang menyadarinya sampai syarat no-op dijalankan. */
IF (SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
      AND name IN ('Id_PA_Cycle_Question_Package_Item', 'PA_Cycle_Question_Package_Id',
                   'Kode_Soal', 'Nama_Soal_Sumber', 'PA_Competency_Id', 'Bobot_Penilaian',
                   'Skor_Maksimal', 'Urutan_Tampil', 'Flag_Aktif', 'Snapshot_At',
                   'Snapshot_By', 'Updated_At', 'Updated_By')) <> 13
    THROW 50223, 'DDL-2026-073: kolom Cycle Question Package Item tidak lengkap.', 1;

/* Hanya FK MILIK change ini, disebut namanya. Menghitung seluruh FK pada tabel itu
   membuat penjaga menyala ketika change berikutnya menambah FK secara sah -
   DDL-2026-083 r2 menambah FK_PA_Cycle_Question_Item_Note_Type, dan sejak itu
   penghitungan 4 selalu gagal. */
IF (
    SELECT COUNT(*) FROM sys.foreign_keys
    WHERE name IN (
        'FK_PA_Cycle_Question_Package_Cycle',
        'FK_PA_Cycle_Question_Package_Level',
        'FK_PA_Cycle_Question_Item_Header',
        'FK_PA_Cycle_Question_Item_Competency'
    )
) <> 4
    THROW 50224, 'DDL-2026-073: foreign key kontrak paket tidak lengkap.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Cycle_Question_Package'),
        OBJECT_ID('N_PA_Cycle_Question_Package_Item')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50225, 'DDL-2026-073: terdapat FK kontrak paket yang disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50226, 'DDL-2026-073: terdapat CHECK kontrak paket yang disabled atau tidak trusted.', 1;
GO
