/* Change ID: DDL-2026-075-performance-appraisal-assignment
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-061, 064, 072, 073. Jalankan sesudah semuanya.
   Scope: dua master penugasan, tabel penugasan penilai, dan tabel integrasi
          HC Learn yang memegang token ujian.
   Seed kedua master ada di file ini, bukan di DML, karena foreign key pada
   change yang sama menolak dipasang sebelum masternya terisi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50240, 'DDL-2026-075 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Participant', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Question_Package', 'U') IS NULL
   OR OBJECT_ID('N_HRIS_Organization_Relationship', 'U') IS NULL
   OR OBJECT_ID('Karyawan', 'U') IS NULL
    THROW 50241, 'DDL-2026-075 membutuhkan peserta, master kategori penilai, paket soal siklus, dan relasi organisasi.', 1;
GO

IF OBJECT_ID('N_PA_Assignment_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Status (
        Id_PA_Assignment_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Status_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment_Status
            PRIMARY KEY (Id_PA_Assignment_Status),
        CONSTRAINT UQ_PA_Assignment_Status_Kode
            UNIQUE (Kode_Status),
        CONSTRAINT CK_PA_Assignment_Status_Urutan
            CHECK (Urutan_Alur >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Assignment_Source', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Source (
        Id_PA_Assignment_Source int IDENTITY(1,1) NOT NULL,
        Kode_Sumber varchar(20) NOT NULL,
        Nama_Sumber nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Source_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Source_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment_Source
            PRIMARY KEY (Id_PA_Assignment_Source),
        CONSTRAINT UQ_PA_Assignment_Source_Kode
            UNIQUE (Kode_Sumber)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Status WHERE Kode_Status = 'BELUM_MULAI')
    INSERT INTO N_PA_Assignment_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('BELUM_MULAI', N'Belum Mulai',
            N'Penugasan sudah dibentuk tetapi penilainya belum membuka form asesmen.', 1);

IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Status WHERE Kode_Status = 'BERJALAN')
    INSERT INTO N_PA_Assignment_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('BERJALAN', N'Berjalan',
            N'Penilai sudah membuka form di HC Learn tetapi belum menyetorkan hasilnya.', 2);

IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Status WHERE Kode_Status = 'SELESAI')
    INSERT INTO N_PA_Assignment_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('SELESAI', N'Selesai',
            N'Hasil sudah diterima lewat callback dan tersimpan sebagai payload mentah.', 3);

IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Status WHERE Kode_Status = 'DIBATALKAN')
    INSERT INTO N_PA_Assignment_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('DIBATALKAN', N'Dibatalkan',
            N'Penugasan dicabut sebelum atau sesudah pengisian. Jawaban yang telanjur masuk tetap tersimpan tetapi berhenti ikut dihitung, dan bobot penilai lain dalam kategori yang sama wajib dinormalkan ulang.', 4);
GO

IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Source WHERE Kode_Sumber = 'USULAN')
    INSERT INTO N_PA_Assignment_Source (Kode_Sumber, Nama_Sumber, Deskripsi)
    VALUES ('USULAN', N'Usulan Sistem',
            N'Lahir dari resolver relasi organisasi lalu disetujui admin. Barisnya menyimpan jejak relasi asalnya.');

IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Source WHERE Kode_Sumber = 'MANUAL')
    INSERT INTO N_PA_Assignment_Source (Kode_Sumber, Nama_Sumber, Deskripsi)
    VALUES ('MANUAL', N'Ditunjuk Admin',
            N'Admin menunjuk penilainya sendiri tanpa dasar relasi organisasi. Alasannya wajib ditulis aplikasi.');
GO

IF OBJECT_ID('N_PA_Assignment', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment (
        Id_PA_Assignment int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        Kode_Perusahaan_Penilai varchar(3) NOT NULL,
        Kode_Karyawan_Penilai varchar(255) NOT NULL,
        Nama_Penilai_Saat_Cycle varchar(255) NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        Bobot_Dalam_Kategori decimal(5,2) NOT NULL,
        Sumber_Penugasan varchar(20) NOT NULL
            CONSTRAINT DF_PA_Assignment_Sumber DEFAULT ('USULAN'),
        Organization_Relationship_Id int NULL,
        Alasan_Manual nvarchar(500) NULL,
        Status_Penugasan varchar(20) NOT NULL
            CONSTRAINT DF_PA_Assignment_Status DEFAULT ('BELUM_MULAI'),
        Due_At date NULL,
        Started_At datetime2(0) NULL,
        Finished_At datetime2(0) NULL,
        /* BOBOT KHUSUS: penanda bahwa angka di Bobot_Dalam_Kategori adalah KEPUTUSAN,
           bukan hasil pembagian rata.

           Tanpa penanda ini mesin tidak punya cara membedakan 33,33 yang dibagi rata dari
           33,33 yang sengaja dipilih, sehingga setiap perubahan susunan penilai menimpa
           keduanya. Bobot menentukan bagaimana nilai penilai dijumlahkan menjadi nilai
           akhir seseorang - angka yang diam-diam berubah di situ mengubah hasil penilaian
           tanpa jejak.

           Jejak SIAPA dan KAPAN dipisahkan dari Updated_By/Updated_At: keduanya bergeser
           pada perubahan apa pun (status, tenggat, pembatalan), sehingga tidak bisa
           menjawab "siapa yang memutuskan atasan ini bernilai 60%" setahun kemudian -
           satu-satunya pertanyaan yang benar-benar ditanyakan saat hasil disanggah.

           Berbahasa Indonesia mengikuti Bobot_Dalam_Kategori: ketiganya satu keluarga dan
           dibaca berurutan, dan pengelompokan itu lebih menolong pembaca daripada
           kemurnian bahasa per kolom. */
        Bobot_Kustom char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Bobot_Kustom DEFAULT ('T'),
        Bobot_Diatur_Oleh int NULL,
        Bobot_Diatur_Pada datetime2(0) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment
            PRIMARY KEY (Id_PA_Assignment),
        CONSTRAINT CK_PA_Assignment_Bobot
            CHECK (Bobot_Dalam_Kategori >= 0 AND Bobot_Dalam_Kategori <= 100),
        CONSTRAINT CK_PA_Assignment_Bobot_Kustom
            CHECK (Bobot_Kustom IN ('Y', 'T')),
        /* Bobot khusus WAJIB berjejak waktu. Penanda tanpa jejak adalah klaim "ini
           keputusan" yang tidak bisa ditelusuri siapa pun - persis keadaan yang hendak
           dihindari kolom ini. Bobot_Diatur_Oleh sengaja BOLEH kosong: penetapan lewat
           perintah konsol memang tidak punya pengguna, dan memaksakannya akan membuat
           jalur itu berbohong dengan menunjuk pengguna mana pun yang kebetulan ada. */
        CONSTRAINT CK_PA_Assignment_Bobot_Kustom_Jejak
            CHECK (Bobot_Kustom = 'T' OR Bobot_Diatur_Pada IS NOT NULL),
        CONSTRAINT CK_PA_Assignment_Rentang_Waktu
            CHECK (
                Finished_At IS NULL
                OR Started_At IS NULL
                OR Finished_At >= Started_At
            )
    );
END;
GO

IF OBJECT_ID('N_PA_Assignment_Integration', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Integration (
        Id_PA_Assignment_Integration int IDENTITY(1,1) NOT NULL,
        PA_Assignment_Id int NOT NULL,
        PA_Cycle_Question_Package_Id int NULL,
        Id_Ujian_Token varchar(255) NULL,
        Payload_Json nvarchar(max) NULL,
        Started_At datetime2(0) NULL,
        Finished_At datetime2(0) NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Integration_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Integration_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Assignment_Integration
            PRIMARY KEY (Id_PA_Assignment_Integration),
        CONSTRAINT CK_PA_Assignment_Integration_Jendela
            CHECK (
                Finished_At IS NULL
                OR Started_At IS NULL
                OR Finished_At >= Started_At
            )
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Participant')
    ALTER TABLE N_PA_Assignment WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Participant
        FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Penilai')
    ALTER TABLE N_PA_Assignment WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Penilai
        FOREIGN KEY (Kode_Perusahaan_Penilai, Kode_Karyawan_Penilai)
        REFERENCES Karyawan (Kode_Perusahaan, Kode_Karyawan);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Category')
    ALTER TABLE N_PA_Assignment WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Category
        FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Sumber')
    ALTER TABLE N_PA_Assignment WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Sumber
        FOREIGN KEY (Sumber_Penugasan) REFERENCES N_PA_Assignment_Source (Kode_Sumber);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Status_Master')
    ALTER TABLE N_PA_Assignment WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Status_Master
        FOREIGN KEY (Status_Penugasan) REFERENCES N_PA_Assignment_Status (Kode_Status);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Org_Relationship')
    ALTER TABLE N_PA_Assignment WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Org_Relationship
        FOREIGN KEY (Organization_Relationship_Id)
        REFERENCES N_HRIS_Organization_Relationship (Id_Organization_Relationship);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Integration_Assignment')
    ALTER TABLE N_PA_Assignment_Integration WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Integration_Assignment
        FOREIGN KEY (PA_Assignment_Id) REFERENCES N_PA_Assignment (Id_PA_Assignment);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Assignment_Integration_Package')
    ALTER TABLE N_PA_Assignment_Integration WITH CHECK
        ADD CONSTRAINT FK_PA_Assignment_Integration_Package
        FOREIGN KEY (PA_Cycle_Question_Package_Id)
        REFERENCES N_PA_Cycle_Question_Package (Id_PA_Cycle_Question_Package);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Assignment_Peserta_Penilai')
    CREATE UNIQUE INDEX UX_PA_Assignment_Peserta_Penilai
        ON N_PA_Assignment (
            PA_Participant_Id,
            Kode_Perusahaan_Penilai,
            Kode_Karyawan_Penilai,
            PA_Rater_Category_Id
        )
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Assignment_Peserta_Status')
    CREATE INDEX IX_PA_Assignment_Peserta_Status
        ON N_PA_Assignment (PA_Participant_Id, Status_Penugasan, Flag_Aktif)
        INCLUDE (PA_Rater_Category_Id, Bobot_Dalam_Kategori, Finished_At);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Assignment_Penilai_Status')
    CREATE INDEX IX_PA_Assignment_Penilai_Status
        ON N_PA_Assignment (
            Kode_Perusahaan_Penilai,
            Kode_Karyawan_Penilai,
            Status_Penugasan,
            Flag_Aktif
        )
        INCLUDE (PA_Participant_Id, Due_At);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Assignment_Integration_Token')
    CREATE UNIQUE INDEX UX_PA_Assignment_Integration_Token
        ON N_PA_Assignment_Integration (Id_Ujian_Token)
        WHERE Id_Ujian_Token IS NOT NULL;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Assignment_Integration_Aktif')
    CREATE UNIQUE INDEX UX_PA_Assignment_Integration_Aktif
        ON N_PA_Assignment_Integration (PA_Assignment_Id)
        WHERE Flag_Aktif = 'Y';
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Assignment_Status')) <> 10
    THROW 50242, 'DDL-2026-075: kolom Assignment Status tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Assignment_Source')) <> 9
    THROW 50243, 'DDL-2026-075: kolom Assignment Source tidak lengkap.', 1;

/* Hanya kolom MILIK change ini. DDL-2026-082 menambah Cancel_Reason, Cancelled_At, dan
   Cancelled_By secara sah, sehingga jumlah persis 19 gagal sejak saat itu - tidak
   ketahuan sampai syarat no-op dijalankan. */
IF (SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Assignment')
      AND name IN ('Id_PA_Assignment', 'PA_Participant_Id', 'Kode_Perusahaan_Penilai',
                   'Kode_Karyawan_Penilai', 'Nama_Penilai_Saat_Cycle', 'PA_Rater_Category_Id',
                   'Bobot_Dalam_Kategori', 'Sumber_Penugasan', 'Organization_Relationship_Id',
                   'Alasan_Manual', 'Status_Penugasan', 'Due_At', 'Started_At', 'Finished_At',
                   'Bobot_Kustom', 'Bobot_Diatur_Oleh', 'Bobot_Diatur_Pada',
                   'Flag_Aktif', 'Created_At', 'Created_By', 'Updated_At', 'Updated_By')) <> 22
    THROW 50244, 'DDL-2026-075: kolom Assignment tidak lengkap.', 1;

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
               WHERE name = 'CK_PA_Assignment_Bobot_Kustom' AND is_not_trusted = 0)
    THROW 50260, 'DDL-2026-075: CK_PA_Assignment_Bobot_Kustom tidak ada atau tidak trusted.', 1;

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
               WHERE name = 'CK_PA_Assignment_Bobot_Kustom_Jejak' AND is_not_trusted = 0)
    THROW 50261, 'DDL-2026-075: CK_PA_Assignment_Bobot_Kustom_Jejak tidak ada atau tidak trusted.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration')) <> 12
    THROW 50245, 'DDL-2026-075: kolom Assignment Integration tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM N_PA_Assignment_Status WHERE Flag_Aktif = 'Y') <> 4
    THROW 50246, 'DDL-2026-075: empat status penugasan tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM N_PA_Assignment_Source WHERE Flag_Aktif = 'Y') <> 2
    THROW 50247, 'DDL-2026-075: dua sumber penugasan tidak lengkap.', 1;

IF (
    SELECT COUNT(*) FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Assignment'),
        OBJECT_ID('N_PA_Assignment_Integration')
    )
) <> 8
    THROW 50248, 'DDL-2026-075: jumlah foreign key penugasan bukan 8.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Assignment'),
        OBJECT_ID('N_PA_Assignment_Integration')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50249, 'DDL-2026-075: terdapat FK penugasan yang disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Assignment_Status'),
        OBJECT_ID('N_PA_Assignment'),
        OBJECT_ID('N_PA_Assignment_Integration')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50250, 'DDL-2026-075: terdapat CHECK penugasan yang disabled atau tidak trusted.', 1;
GO
