/* Change ID: DDL-2026-070-performance-appraisal-status-and-axis-masters
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064 (N_PA_Rater_Policy) dan DDL-2026-067 (N_PA_Score_Range).
   Scope: tiga master kamus tertutup dan foreign key dari kolom yang selama ini
          berupa varchar bebas. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50170, 'DDL-2026-070 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Rater_Policy', 'U') IS NULL
   OR COL_LENGTH('N_PA_Rater_Policy', 'Status_Policy') IS NULL
    THROW 50171, 'DDL-2026-070 membutuhkan N_PA_Rater_Policy.Status_Policy dari DDL-2026-064.', 1;

IF OBJECT_ID('N_PA_Score_Range', 'U') IS NULL
   OR COL_LENGTH('N_PA_Score_Range', 'Score_Type') IS NULL
   OR COL_LENGTH('N_PA_Score_Range', 'Score_Usage') IS NULL
    THROW 50172, 'DDL-2026-070 membutuhkan N_PA_Score_Range dari DDL-2026-067.', 1;
GO

IF OBJECT_ID('N_PA_Rater_Policy_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Rater_Policy_Status (
        Id_PA_Rater_Policy_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Rater_Policy_Status_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Rater_Policy_Status
            PRIMARY KEY (Id_PA_Rater_Policy_Status),
        CONSTRAINT UQ_PA_Rater_Policy_Status_Kode
            UNIQUE (Kode_Status),
        CONSTRAINT CK_PA_Rater_Policy_Status_Urutan
            CHECK (Urutan_Alur >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Score_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Score_Type (
        Id_PA_Score_Sumbu int IDENTITY(1,1) NOT NULL,
        Kode_Type varchar(20) NOT NULL,
        Nama_Type nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Score_Type_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Score_Type
            PRIMARY KEY (Id_PA_Score_Sumbu),
        CONSTRAINT UQ_PA_Score_Type_Kode
            UNIQUE (Kode_Type),
        CONSTRAINT CK_PA_Score_Type_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Score_Usage', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Score_Usage (
        Id_PA_Score_Kegunaan int IDENTITY(1,1) NOT NULL,
        Kode_Usage varchar(20) NOT NULL,
        Nama_Usage nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Flag_Pakai_Nilai_Hasil char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Hasil DEFAULT ('T'),
        Flag_Pakai_Target char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Target DEFAULT ('T'),
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Score_Usage_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Score_Usage
            PRIMARY KEY (Id_PA_Score_Kegunaan),
        CONSTRAINT UQ_PA_Score_Usage_Kode
            UNIQUE (Kode_Usage),
        CONSTRAINT CK_PA_Score_Usage_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO
/* Seed di forward.sql, bukan data-change.sql: foreign key pada change yang sama
   menolak dipasang selama masternya masih kosong. */

IF NOT EXISTS (SELECT 1 FROM N_PA_Rater_Policy_Status WHERE Kode_Status = 'DRAFT')
    INSERT INTO N_PA_Rater_Policy_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('DRAFT', N'Draft',
            N'Sedang disusun. Bobot antar kategori belum wajib berjumlah 100 dan policy belum boleh dipakai siklus.', 1);

IF NOT EXISTS (SELECT 1 FROM N_PA_Rater_Policy_Status WHERE Kode_Status = 'ACTIVE')
    INSERT INTO N_PA_Rater_Policy_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('ACTIVE', N'Berlaku',
            N'Sudah diaktifkan dan boleh disalin ke siklus. Total bobot kategori aktif wajib 100 sebelum masuk keadaan ini.', 2);

IF NOT EXISTS (SELECT 1 FROM N_PA_Rater_Policy_Status WHERE Kode_Status = 'INACTIVE')
    INSERT INTO N_PA_Rater_Policy_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('INACTIVE', N'Tidak Berlaku',
            N'Digantikan versi yang lebih baru atau dihentikan pemakaiannya. Siklus lama yang sudah menyalinnya tidak terpengaruh.', 3);
GO

IF NOT EXISTS (SELECT 1 FROM N_PA_Score_Type WHERE Kode_Type = 'KPI')
    INSERT INTO N_PA_Score_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil)
    VALUES ('KPI', N'Nilai KPI', N'Score_Type hasil kerja. Berjalan pada rentang 50 sampai 125 di modul KPI.', 1);

IF NOT EXISTS (SELECT 1 FROM N_PA_Score_Type WHERE Kode_Type = 'KOMPETENSI')
    INSERT INTO N_PA_Score_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil)
    VALUES ('KOMPETENSI', N'Nilai Kompetensi', N'Score_Type hasil penilaian 360 derajat.', 2);

IF NOT EXISTS (SELECT 1 FROM N_PA_Score_Type WHERE Kode_Type = 'FINAL')
    INSERT INTO N_PA_Score_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil)
    VALUES ('FINAL', N'Nilai Akhir', N'Gabungan kedua pilar sesuai bobot pilar per level.', 3);
GO

IF NOT EXISTS (SELECT 1 FROM N_PA_Score_Usage WHERE Kode_Usage = 'HAVMAP')
    INSERT INTO N_PA_Score_Usage (Kode_Usage, Nama_Usage, Deskripsi, Flag_Pakai_Nilai_Hasil, Flag_Pakai_Target, Urutan_Tampil)
    VALUES ('HAVMAP', N'Pita HAV Map', N'Tiga tingkat Tinggi, Sedang, dan Rendah yang menentukan kotak seseorang pada 9-box.', 'T', 'T', 1);

IF NOT EXISTS (SELECT 1 FROM N_PA_Score_Usage WHERE Kode_Usage = 'GRADE')
    INSERT INTO N_PA_Score_Usage (Kode_Usage, Nama_Usage, Deskripsi, Flag_Pakai_Nilai_Hasil, Flag_Pakai_Target, Urutan_Tampil)
    VALUES ('GRADE', N'Pita Grade', N'Batas grade akhir beserta target distribusinya untuk kalibrasi.', 'T', 'Y', 2);

IF NOT EXISTS (SELECT 1 FROM N_PA_Score_Usage WHERE Kode_Usage = 'KONVERSI')
    INSERT INTO N_PA_Score_Usage (Kode_Usage, Nama_Usage, Deskripsi, Flag_Pakai_Nilai_Hasil, Flag_Pakai_Target, Urutan_Tampil)
    VALUES ('KONVERSI', N'Konversi Skala', N'Pemetaan nilai dari satu skala ke skala lain; hasilnya di kolom Nilai_Hasil.', 'Y', 'T', 3);
GO

IF EXISTS (
    SELECT 1 FROM N_PA_Rater_Policy p
    WHERE NOT EXISTS (SELECT 1 FROM N_PA_Rater_Policy_Status s WHERE s.Kode_Status = p.Status_Policy)
)
    THROW 50173, 'DDL-2026-070: terdapat Status_Policy yang tidak terdaftar di master. Perbaiki datanya sebelum foreign key dipasang.', 1;

IF EXISTS (
    SELECT 1 FROM N_PA_Score_Range r
    WHERE NOT EXISTS (SELECT 1 FROM N_PA_Score_Type s WHERE s.Kode_Type = r.Score_Type)
       OR NOT EXISTS (SELECT 1 FROM N_PA_Score_Usage k WHERE k.Kode_Usage = r.Score_Usage)
)
    THROW 50174, 'DDL-2026-070: terdapat Score_Type atau Score_Usage yang tidak terdaftar di master. Perbaiki datanya sebelum foreign key dipasang.', 1;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Policy') AND name = 'FK_PA_Rater_Policy_Status'
)
BEGIN
    ALTER TABLE N_PA_Rater_Policy WITH CHECK
        ADD CONSTRAINT FK_PA_Rater_Policy_Status
        FOREIGN KEY (Status_Policy) REFERENCES N_PA_Rater_Policy_Status (Kode_Status);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'FK_PA_Score_Range_Type'
)
BEGIN
    ALTER TABLE N_PA_Score_Range WITH CHECK
        ADD CONSTRAINT FK_PA_Score_Range_Type
        FOREIGN KEY (Score_Type) REFERENCES N_PA_Score_Type (Kode_Type);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'FK_PA_Score_Range_Usage'
)
BEGIN
    ALTER TABLE N_PA_Score_Range WITH CHECK
        ADD CONSTRAINT FK_PA_Score_Range_Usage
        FOREIGN KEY (Score_Usage) REFERENCES N_PA_Score_Usage (Kode_Usage);
END;
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Status')) <> 10
    THROW 50175, 'DDL-2026-070: kolom Rater Policy Status tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Score_Type')) <> 10
    THROW 50176, 'DDL-2026-070: kolom Score Score_Type tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Score_Usage')) <> 12
    THROW 50177, 'DDL-2026-070: kolom Score Score_Usage tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM N_PA_Rater_Policy_Status WHERE Flag_Aktif = 'Y') <> 3
   OR (SELECT COUNT(*) FROM N_PA_Score_Type WHERE Flag_Aktif = 'Y') <> 3
   OR (SELECT COUNT(*) FROM N_PA_Score_Usage WHERE Flag_Aktif = 'Y') <> 3
    THROW 50178, 'DDL-2026-070: seed ketiga master tidak lengkap.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE name IN ('FK_PA_Rater_Policy_Status','FK_PA_Score_Range_Type','FK_PA_Score_Range_Usage')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50179, 'DDL-2026-070: terdapat FK master yang disabled atau tidak trusted.', 1;
GO
