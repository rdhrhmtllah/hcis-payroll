/* Change ID: DDL-2026-072-performance-appraisal-participant
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-066 dan DDL-2026-071. Jalankan sesudah keduanya.
   Scope: master status peserta dan tabel peserta siklus. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50210, 'DDL-2026-072 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Rater_Policy_Snapshot', 'U') IS NULL
   OR OBJECT_ID('HRIS_Level', 'U') IS NULL
   OR OBJECT_ID('Karyawan', 'U') IS NULL
    THROW 50211, 'DDL-2026-072 membutuhkan N_PA_Cycle, salinan Rater Policy, HRIS_Level, dan Karyawan.', 1;
GO

IF OBJECT_ID('N_PA_Participant_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant_Status (
        Id_PA_Participant_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Status_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant_Status
            PRIMARY KEY (Id_PA_Participant_Status),
        CONSTRAINT UQ_PA_Participant_Status_Kode
            UNIQUE (Kode_Status),
        CONSTRAINT CK_PA_Participant_Status_Urutan
            CHECK (Urutan_Alur >= 1)
    );
END;
GO

/* Seed di forward.sql: foreign key pada change yang sama menolak dipasang
   selama masternya masih kosong. */

IF NOT EXISTS (SELECT 1 FROM N_PA_Participant_Status WHERE Kode_Status = 'TERTUNDA')
    INSERT INTO N_PA_Participant_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('TERTUNDA', N'Tertunda',
            N'Baris peserta sudah ada tetapi level atau aturan penilainya belum teresolusi. Inilah isi keranjang yang memblokir penguncian siklus.', 1);

IF NOT EXISTS (SELECT 1 FROM N_PA_Participant_Status WHERE Kode_Status = 'DIIKUTKAN')
    INSERT INTO N_PA_Participant_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('DIIKUTKAN', N'Diikutkan',
            N'Kondisi organisasi dan aturan penilai sudah teresolusi; peserta ikut dinilai pada siklus ini.', 2);

IF NOT EXISTS (SELECT 1 FROM N_PA_Participant_Status WHERE Kode_Status = 'DIKECUALIKAN')
    INSERT INTO N_PA_Participant_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('DIKECUALIKAN', N'Dikecualikan',
            N'Sengaja tidak dinilai pada siklus ini. Alasannya wajib ditulis oleh aplikasi.', 3);
GO

IF OBJECT_ID('N_PA_Participant', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Participant (
        Id_PA_Participant int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,
        Nama_Karyawan_Saat_Cycle varchar(255) NOT NULL,
        ID_Level_Jabatan_Saat_Cycle bigint NOT NULL,
        ID_Level_Saat_Cycle bigint NOT NULL,
        Nama_Level_Saat_Cycle varchar(100) NULL,
        ID_Divisi_Sub_Divisi_Saat_Cycle bigint NULL,
        ID_Divisi_Saat_Cycle bigint NULL,
        Nama_Divisi_Saat_Cycle varchar(100) NULL,
        ID_Sub_Divisi_Saat_Cycle bigint NULL,
        Nama_Sub_Divisi_Saat_Cycle varchar(100) NULL,
        Kode_Jabatan_Saat_Cycle varchar(30) NULL,
        Nama_Jabatan_Saat_Cycle varchar(100) NULL,
        Lokasi_Saat_Cycle varchar(15) NULL,
        PA_Cycle_Rater_Policy_Snapshot_Id int NULL,
        Status_Peserta varchar(20) NOT NULL
            CONSTRAINT DF_PA_Participant_Status DEFAULT ('TERTUNDA'),
        Alasan_Dikecualikan nvarchar(500) NULL,
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Participant_Snapshot_At DEFAULT (SYSDATETIME()),
        Snapshot_By int NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Participant_Aktif DEFAULT ('Y'),
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Participant
            PRIMARY KEY (Id_PA_Participant)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Cycle')
    ALTER TABLE N_PA_Participant WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Cycle
        FOREIGN KEY (PA_Cycle_Id) REFERENCES N_PA_Cycle (Id_PA_Cycle);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Karyawan')
    ALTER TABLE N_PA_Participant WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Karyawan
        FOREIGN KEY (Kode_Perusahaan, Kode_Karyawan)
        REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Level')
    ALTER TABLE N_PA_Participant WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Level
        FOREIGN KEY (ID_Level_Saat_Cycle) REFERENCES HRIS_Level (ID_Level);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Rater_Snapshot')
    ALTER TABLE N_PA_Participant WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Rater_Snapshot
        FOREIGN KEY (PA_Cycle_Rater_Policy_Snapshot_Id)
        REFERENCES N_PA_Cycle_Rater_Policy_Snapshot (Id_PA_Cycle_Rater_Policy_Snapshot);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Status')
    ALTER TABLE N_PA_Participant WITH CHECK
        ADD CONSTRAINT FK_PA_Participant_Status
        FOREIGN KEY (Status_Peserta)
        REFERENCES N_PA_Participant_Status (Kode_Status);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Participant_Cycle_Karyawan')
    CREATE UNIQUE INDEX UX_PA_Participant_Cycle_Karyawan
        ON N_PA_Participant (PA_Cycle_Id, Kode_Perusahaan, Kode_Karyawan)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Participant_Cycle_Status')
    CREATE INDEX IX_PA_Participant_Cycle_Status
        ON N_PA_Participant (PA_Cycle_Id, Status_Peserta, Flag_Aktif)
        INCLUDE (ID_Level_Saat_Cycle, Kode_Karyawan, Nama_Karyawan_Saat_Cycle,
                 PA_Cycle_Rater_Policy_Snapshot_Id,
                 ID_Divisi_Saat_Cycle, ID_Sub_Divisi_Saat_Cycle, Lokasi_Saat_Cycle);
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Participant_Status')) <> 10
    THROW 50212, 'DDL-2026-072: kolom Participant Status tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Participant')) <> 24
    THROW 50213, 'DDL-2026-072: kolom Participant tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM N_PA_Participant_Status WHERE Flag_Aktif = 'Y') <> 3
    THROW 50214, 'DDL-2026-072: tiga status peserta tidak lengkap.', 1;

IF (
    SELECT COUNT(*) FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Participant')
) <> 5
    THROW 50215, 'DDL-2026-072: jumlah foreign key peserta bukan 5.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Participant')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50216, 'DDL-2026-072: terdapat FK peserta yang disabled atau tidak trusted.', 1;
GO
