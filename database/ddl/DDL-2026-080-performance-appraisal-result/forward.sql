/* Change ID: DDL-2026-080-performance-appraisal-result
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-068, 072. Jalankan sesudah keduanya.
   Scope: master status hasil, master jenis catatan, dan tabel hasil per peserta.
   Seed kedua master ada di file ini, bukan di DML, karena foreign key pada change
   yang sama menolak dipasang sebelum masternya terisi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50300, 'DDL-2026-080 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Participant', 'U') IS NULL
   OR OBJECT_ID('N_PA_Hav_Quadrant', 'U') IS NULL
    THROW 50301, 'DDL-2026-080 membutuhkan tabel peserta dan kamus kuadran HAV.', 1;
GO

IF OBJECT_ID('N_PA_Result_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Status (
        Id_PA_Result_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Status_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Status PRIMARY KEY (Id_PA_Result_Status),
        CONSTRAINT UQ_PA_Result_Status_Kode UNIQUE (Kode_Status),
        CONSTRAINT CK_PA_Result_Status_Urutan CHECK (Urutan_Alur >= 1)
    );
END;
GO

IF OBJECT_ID('N_PA_Result_Note_Type', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Note_Type (
        Id_PA_Result_Note_Type int IDENTITY(1,1) NOT NULL,
        Kode_Type varchar(30) NOT NULL,
        Nama_Type nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Type_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Type_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Note_Type PRIMARY KEY (Id_PA_Result_Note_Type),
        CONSTRAINT UQ_PA_Result_Note_Type_Kode UNIQUE (Kode_Type),
        CONSTRAINT CK_PA_Result_Note_Type_Urutan CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM N_PA_Result_Status WHERE Kode_Status = 'BELUM_DIHITUNG')
    INSERT INTO N_PA_Result_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('BELUM_DIHITUNG', N'Belum Dihitung',
            N'Baris hasil sudah ada bersamaan dengan pesertanya, tetapi perhitungan belum pernah dijalankan. Keadaan inilah yang membuat ketiadaan angka punya sebab yang terbaca.', 1);

IF NOT EXISTS (SELECT 1 FROM N_PA_Result_Status WHERE Kode_Status = 'DIHITUNG')
    INSERT INTO N_PA_Result_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('DIHITUNG', N'Dihitung',
            N'Nilai akhir dan grade sudah terbentuk, tetapi belum diterbitkan kepada karyawan.', 2);

IF NOT EXISTS (SELECT 1 FROM N_PA_Result_Status WHERE Kode_Status = 'TERBIT')
    INSERT INTO N_PA_Result_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('TERBIT', N'Terbit',
            N'Hasil sudah dapat dilihat karyawan yang bersangkutan.', 3);

IF NOT EXISTS (SELECT 1 FROM N_PA_Result_Status WHERE Kode_Status = 'TERTAHAN')
    INSERT INTO N_PA_Result_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur)
    VALUES ('TERTAHAN', N'Tertahan',
            N'Cabang, bukan tahap. Penilainya kurang dari ambang minimum, sehingga hasilnya sengaja tidak diberi angka yang tampak wajar padahal datanya bolong. Alasannya wajib ditulis.', 4);
GO

IF NOT EXISTS (SELECT 1 FROM N_PA_Result_Note_Type WHERE Kode_Type = 'STRENGTH')
    INSERT INTO N_PA_Result_Note_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil)
    VALUES ('STRENGTH', N'Kekuatan',
            N'Hal yang menonjol dan layak dipertahankan.', 1);

IF NOT EXISTS (SELECT 1 FROM N_PA_Result_Note_Type WHERE Kode_Type = 'DEVELOPMENT_AREA')
    INSERT INTO N_PA_Result_Note_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil)
    VALUES ('DEVELOPMENT_AREA', N'Area Pengembangan',
            N'Hal yang perlu ditingkatkan pada periode berikutnya.', 2);
GO

IF OBJECT_ID('N_PA_Result', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result (
        Id_PA_Result int IDENTITY(1,1) NOT NULL,
        PA_Participant_Id int NOT NULL,
        Status_Hasil varchar(20) NOT NULL
            CONSTRAINT DF_PA_Result_Status DEFAULT ('BELUM_DIHITUNG'),

        /* Sisi KPI. Dua kolom nilai karena KpiScoringService mengembalikan
           raw_pct dan final_pct; menyimpan keduanya membuat "kenapa 125 padahal
           capaiannya 177" terjawab tanpa menghitung ulang. */
        Nilai_KPI decimal(6,2) NULL,
        Nilai_KPI_Sebelum_Cap decimal(6,2) NULL,
        KPI_Periode_Dari date NULL,
        KPI_Periode_Sampai date NULL,
        Jumlah_KPI_Belum_Disetujui int NULL,

        /* Sisi kompetensi */
        Nilai_Kompetensi decimal(6,2) NULL,
        Jumlah_Rater_Diharapkan smallint NULL,
        Jumlah_Rater_Mengisi smallint NULL,

        /* Gabungan */
        Bobot_KPI_Terpakai decimal(5,2) NULL,
        Bobot_Kompetensi_Terpakai decimal(5,2) NULL,
        Final_Score decimal(6,2) NULL,
        Kode_Grade_Sistem varchar(40) NULL,
        Kode_Grade_Final varchar(40) NULL,
        Flag_Dikalibrasi char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Dikalibrasi DEFAULT ('T'),
        PA_Hav_Quadrant_Id int NULL,

        /* Keadaan */
        Alasan_Tertahan nvarchar(500) NULL,
        Calculation_Version int NOT NULL
            CONSTRAINT DF_PA_Result_Calc_Version DEFAULT (1),
        Calculated_At datetime2(0) NULL,
        Calculated_By int NULL,
        Published_At datetime2(0) NULL,
        Published_By int NULL,

        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,

        CONSTRAINT PK_PA_Result PRIMARY KEY (Id_PA_Result),
        CONSTRAINT CK_PA_Result_Calc_Version CHECK (Calculation_Version >= 1),
        /* Nilai tidak dibatasi di atas: nilai akhir boleh melewati 100 karena
           pencapaian di atas target tidak dipotong, dan pita grade sudah terbuka
           ke atas sejak DML-2026-079. Membatasi di sini akan menciptakan sumber
           kebenaran kedua tentang skala. */
        CONSTRAINT CK_PA_Result_Nilai_Non_Negatif
            CHECK (
                (Nilai_KPI IS NULL OR Nilai_KPI >= 0)
                AND (Nilai_KPI_Sebelum_Cap IS NULL OR Nilai_KPI_Sebelum_Cap >= 0)
                AND (Nilai_Kompetensi IS NULL OR Nilai_Kompetensi >= 0)
                AND (Final_Score IS NULL OR Final_Score >= 0)
            ),
        CONSTRAINT CK_PA_Result_Bobot
            CHECK (
                (Bobot_KPI_Terpakai IS NULL AND Bobot_Kompetensi_Terpakai IS NULL)
                OR (
                    Bobot_KPI_Terpakai IS NOT NULL
                    AND Bobot_Kompetensi_Terpakai IS NOT NULL
                    AND Bobot_KPI_Terpakai >= 0
                    AND Bobot_Kompetensi_Terpakai >= 0
                    AND Bobot_KPI_Terpakai + Bobot_Kompetensi_Terpakai = 100
                )
            ),
        CONSTRAINT CK_PA_Result_Periode
            CHECK (
                KPI_Periode_Dari IS NULL
                OR KPI_Periode_Sampai IS NULL
                OR KPI_Periode_Sampai >= KPI_Periode_Dari
            ),
        CONSTRAINT CK_PA_Result_Rater
            CHECK (
                Jumlah_Rater_Diharapkan IS NULL
                OR Jumlah_Rater_Mengisi IS NULL
                OR Jumlah_Rater_Mengisi <= Jumlah_Rater_Diharapkan
            ),
        CONSTRAINT CK_PA_Result_KPI_Menggantung
            CHECK (Jumlah_KPI_Belum_Disetujui IS NULL OR Jumlah_KPI_Belum_Disetujui >= 0)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Participant')
    ALTER TABLE N_PA_Result WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Participant
        FOREIGN KEY (PA_Participant_Id) REFERENCES N_PA_Participant (Id_PA_Participant);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Status_Master')
    ALTER TABLE N_PA_Result WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Status_Master
        FOREIGN KEY (Status_Hasil) REFERENCES N_PA_Result_Status (Kode_Status);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Hav_Quadrant')
    ALTER TABLE N_PA_Result WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Hav_Quadrant
        FOREIGN KEY (PA_Hav_Quadrant_Id) REFERENCES N_PA_Hav_Quadrant (Id_PA_Hav_Quadrant);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Result_Participant')
    CREATE UNIQUE INDEX UX_PA_Result_Participant
        ON N_PA_Result (PA_Participant_Id)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Result_Status')
    CREATE INDEX IX_PA_Result_Status
        ON N_PA_Result (Status_Hasil, Flag_Aktif)
        INCLUDE (PA_Participant_Id, Final_Score, Kode_Grade_Final, PA_Hav_Quadrant_Id);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Result_Kuadran')
    CREATE INDEX IX_PA_Result_Kuadran
        ON N_PA_Result (PA_Hav_Quadrant_Id, Flag_Aktif)
        INCLUDE (PA_Participant_Id, Final_Score);
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result_Status')) <> 10
    THROW 50302, 'DDL-2026-080: kolom Result Status tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result_Note_Type')) <> 10
    THROW 50303, 'DDL-2026-080: kolom Result Note Type tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result')) <> 29
    THROW 50304, 'DDL-2026-080: kolom Result tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM N_PA_Result_Status WHERE Flag_Aktif = 'Y') <> 4
    THROW 50305, 'DDL-2026-080: empat status hasil tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM N_PA_Result_Note_Type WHERE Flag_Aktif = 'Y') <> 2
    THROW 50306, 'DDL-2026-080: dua jenis catatan hasil tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID('N_PA_Result')) <> 3
    THROW 50307, 'DDL-2026-080: jumlah foreign key hasil bukan 3.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Result')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50308, 'DDL-2026-080: terdapat FK hasil yang disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Result'),
        OBJECT_ID('N_PA_Result_Status'),
        OBJECT_ID('N_PA_Result_Note_Type')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50309, 'DDL-2026-080: terdapat CHECK hasil yang disabled atau tidak trusted.', 1;
GO
