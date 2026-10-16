/* Change ID: DDL-2026-165-pa-balancing
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-080/081 (N_PA_Result, N_PA_Result_Competency), N_PA_Cycle,
                 N_PA_Participant, N_PA_Competency.
   Scope: sesi Balancing berulang sesudah Calculate.
   Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
*/

IF OBJECT_ID('N_PA_Balancing_Sesi', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Sesi (
        Id_PA_Balancing_Sesi int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Sesi PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Sesi_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        Nama_Sesi nvarchar(200) NOT NULL,
        PIC_User_Id int NOT NULL,
        Status_Sesi varchar(20) NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Status DEFAULT ('TERBUKA'),
        Catatan_Rapat nvarchar(max) NULL,
        Versi_Data int NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Versi DEFAULT (1),
        Final_At datetime2(0) NULL,
        Final_By int NULL,
        Nomor_Hitung_Ulang int NULL,
        Hitung_Ulang_At datetime2(0) NULL,
        Hitung_Ulang_By int NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        Alasan_Batal nvarchar(500) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Sesi_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT CK_PA_Balancing_Sesi_Versi CHECK (Versi_Data >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Sesi') AND name = 'IX_PA_Balancing_Sesi_Cycle_Status')
    CREATE INDEX IX_PA_Balancing_Sesi_Cycle_Status ON N_PA_Balancing_Sesi (PA_Cycle_Id, Status_Sesi);
GO

IF OBJECT_ID('N_PA_Balancing_Forum', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Forum (
        Id_PA_Balancing_Forum int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Forum PRIMARY KEY,
        PA_Balancing_Sesi_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Forum_Sesi REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi),
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,
        Nama_Karyawan varchar(255) NOT NULL,
        Peran nvarchar(100) NOT NULL,
        Urutan_Tampil smallint NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Forum_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT CK_PA_Balancing_Forum_Urutan CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Forum') AND name = 'IX_PA_Balancing_Forum_Sesi')
    CREATE INDEX IX_PA_Balancing_Forum_Sesi ON N_PA_Balancing_Forum (PA_Balancing_Sesi_Id);
GO

IF OBJECT_ID('N_PA_Balancing_Peserta', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Peserta (
        Id_PA_Balancing_Peserta int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Peserta PRIMARY KEY,
        PA_Balancing_Sesi_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Peserta_Sesi REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi),
        PA_Participant_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Peserta_Participant REFERENCES N_PA_Participant (Id_PA_Participant),
        PA_Result_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Peserta_Result REFERENCES N_PA_Result (Id_PA_Result),
        Calculation_Version_Sebelum int NOT NULL,
        Nilai_KPI_Sebelum decimal(6,2) NULL,
        Nilai_Kompetensi_Sebelum decimal(6,2) NULL,
        Final_Score_Sebelum decimal(6,2) NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        Status_Tinjau varchar(20) NOT NULL
            CONSTRAINT DF_PA_Balancing_Peserta_Tinjau DEFAULT ('BELUM'),
        Alasan nvarchar(1000) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Peserta_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Peserta') AND name = 'UX_PA_Balancing_Peserta_Sesi_Participant')
    CREATE UNIQUE INDEX UX_PA_Balancing_Peserta_Sesi_Participant ON N_PA_Balancing_Peserta (PA_Balancing_Sesi_Id, PA_Participant_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Peserta') AND name = 'IX_PA_Balancing_Peserta_Participant')
    CREATE INDEX IX_PA_Balancing_Peserta_Participant ON N_PA_Balancing_Peserta (PA_Participant_Id) INCLUDE (PA_Balancing_Sesi_Id, Status_Tinjau);
GO

IF OBJECT_ID('N_PA_Balancing_Kompetensi', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Kompetensi (
        Id_PA_Balancing_Kompetensi int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Kompetensi PRIMARY KEY,
        PA_Balancing_Peserta_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kompetensi_Peserta REFERENCES N_PA_Balancing_Peserta (Id_PA_Balancing_Peserta),
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kompetensi_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        Bobot decimal(9,6) NOT NULL,
        Nilai_Sebelum decimal(6,2) NOT NULL,
        Nilai_Sesudah decimal(6,2) NOT NULL,
        Alasan nvarchar(1000) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT CK_PA_Balancing_Kompetensi_Bobot CHECK (Bobot >= 0 AND Bobot <= 1),
        CONSTRAINT CK_PA_Balancing_Kompetensi_Nilai CHECK (Nilai_Sebelum >= 0 AND Nilai_Sebelum <= 100
                                                          AND Nilai_Sesudah >= 0 AND Nilai_Sesudah <= 100)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Kompetensi') AND name = 'UX_PA_Balancing_Kompetensi_Peserta_Competency')
    CREATE UNIQUE INDEX UX_PA_Balancing_Kompetensi_Peserta_Competency ON N_PA_Balancing_Kompetensi (PA_Balancing_Peserta_Id, PA_Competency_Id);
GO

IF OBJECT_ID('N_PA_Balancing_Kualitatif', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Kualitatif (
        Id_PA_Balancing_Kualitatif int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Kualitatif PRIMARY KEY,
        PA_Balancing_Peserta_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kualitatif_Peserta REFERENCES N_PA_Balancing_Peserta (Id_PA_Balancing_Peserta),
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Kualitatif_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        Isi_Sebelum nvarchar(max) NULL,
        Isi_Sesudah nvarchar(max) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Kualitatif') AND name = 'UX_PA_Balancing_Kualitatif_Peserta_Competency')
    CREATE UNIQUE INDEX UX_PA_Balancing_Kualitatif_Peserta_Competency ON N_PA_Balancing_Kualitatif (PA_Balancing_Peserta_Id, PA_Competency_Id);
GO

IF OBJECT_ID('N_PA_Balancing_Jejak_Hitung', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Balancing_Jejak_Hitung (
        Id_PA_Balancing_Jejak_Hitung int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Balancing_Jejak_Hitung PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        Nomor_Hitung_Ulang int NOT NULL,
        PA_Participant_Id int NOT NULL
            CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Participant REFERENCES N_PA_Participant (Id_PA_Participant),
        PA_Balancing_Sesi_Id int NULL
            CONSTRAINT FK_PA_Balancing_Jejak_Hitung_Sesi REFERENCES N_PA_Balancing_Sesi (Id_PA_Balancing_Sesi),
        Nilai_Kompetensi_Sebelum decimal(6,2) NULL,
        Nilai_Kompetensi_Sesudah decimal(6,2) NULL,
        Final_Score_Sebelum decimal(6,2) NULL,
        Final_Score_Sesudah decimal(6,2) NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        Kode_Grade_Sesudah varchar(40) NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        PA_Hav_Quadrant_Sesudah_Id int NULL,
        Sebab varchar(20) NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Balancing_Jejak_Hitung_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        CONSTRAINT CK_PA_Balancing_Jejak_Hitung_Nomor CHECK (Nomor_Hitung_Ulang >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Jejak_Hitung') AND name = 'IX_PA_Balancing_Jejak_Hitung_Cycle_Nomor')
    CREATE INDEX IX_PA_Balancing_Jejak_Hitung_Cycle_Nomor ON N_PA_Balancing_Jejak_Hitung (PA_Cycle_Id, Nomor_Hitung_Ulang);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Balancing_Jejak_Hitung') AND name = 'IX_PA_Balancing_Jejak_Hitung_Participant')
    CREATE INDEX IX_PA_Balancing_Jejak_Hitung_Participant ON N_PA_Balancing_Jejak_Hitung (PA_Participant_Id);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-165')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-165',
        'DDL',
        'Balancing berulang pada Performance Appraisal',
        'Enam tabel N_PA_Balancing_*: sesi, forum, peserta (snapshot Before), kompetensi (Before/After berbobot), kualitatif (Before/After teks kaya), dan jejak hitung ulang. Skor kompetensi yang berlaku = After sesi FINAL terbaru; N_PA_Result tidak berubah bentuk.',
        'database/ddl/DDL-2026-165-pa-balancing/forward.sql',
        'docs/changes/DDL-2026-165-pa-balancing/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Balancing. Rancangan disetujui pemilik proyek 30 September 2026.',
        NULL
    );
END;
GO
