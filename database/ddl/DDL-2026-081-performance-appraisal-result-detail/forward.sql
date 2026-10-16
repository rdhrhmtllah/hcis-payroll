/* Change ID: DDL-2026-081-performance-appraisal-result-detail
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064, 067, 068, 075, 080. Jalankan sesudah semuanya.
   Scope: empat tabel anak dari N_PA_Result - jejak per kategori penilai, nilai per
          kompetensi, riwayat kalibrasi, dan catatan hasil. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50320, 'DDL-2026-081 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Result', 'U') IS NULL
   OR OBJECT_ID('N_PA_Result_Note_Type', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency', 'U') IS NULL
   OR OBJECT_ID('N_PA_Hav_Quadrant', 'U') IS NULL
   OR OBJECT_ID('N_PA_Assignment', 'U') IS NULL
    THROW 50321, 'DDL-2026-081 membutuhkan hasil, master catatan, kategori penilai, kompetensi, kuadran, dan penugasan.', 1;
GO

/* ------------------------------------------------------------------ *
 * 1. Jejak per kategori penilai — inilah yang menjawab                 *
 *    "kenapa nilai kompetensinya segitu"                               *
 * ------------------------------------------------------------------ */
IF OBJECT_ID('N_PA_Result_Rater_Category', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Rater_Category (
        Id_PA_Result_Rater_Category int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        PA_Rater_Category_Id int NOT NULL,
        Nilai decimal(6,2) NULL,
        Jumlah_Rater_Diharapkan smallint NULL,
        Jumlah_Rater_Mengisi smallint NULL,
        /* Bobot yang BENAR-BENAR dipakai, bukan yang tertulis di aturan. Keduanya
           berbeda ketika satu kategori kosong dan bobotnya dinormalkan ulang ke
           kategori yang terisi. Tanpa kolom ini, redistribusi itu tak bisa diaudit. */
        Bobot_Terpakai decimal(5,2) NULL,
        Calculation_Version int NOT NULL
            CONSTRAINT DF_PA_Result_Rater_Cat_Calc_Version DEFAULT (1),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Rater_Cat_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Rater_Cat_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Rater_Category
            PRIMARY KEY (Id_PA_Result_Rater_Category),
        CONSTRAINT CK_PA_Result_Rater_Cat_Nilai
            CHECK (Nilai IS NULL OR Nilai >= 0),
        CONSTRAINT CK_PA_Result_Rater_Cat_Bobot
            CHECK (Bobot_Terpakai IS NULL OR (Bobot_Terpakai >= 0 AND Bobot_Terpakai <= 100)),
        CONSTRAINT CK_PA_Result_Rater_Cat_Jumlah
            CHECK (
                Jumlah_Rater_Diharapkan IS NULL
                OR Jumlah_Rater_Mengisi IS NULL
                OR Jumlah_Rater_Mengisi <= Jumlah_Rater_Diharapkan
            ),
        CONSTRAINT CK_PA_Result_Rater_Cat_Calc_Version
            CHECK (Calculation_Version >= 1)
    );
END;
GO

/* ------------------------------------------------------------------ *
 * 2. Nilai per kompetensi                                              *
 * ------------------------------------------------------------------ */
IF OBJECT_ID('N_PA_Result_Competency', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Competency (
        Id_PA_Result_Competency int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        PA_Competency_Id int NOT NULL,
        /* KOSONG berarti agregat lintas kategori — itu yang dipakai radar chart.
           Terisi berarti nilai kompetensi ini menurut satu kategori penilai saja. */
        PA_Rater_Category_Id int NULL,
        Nilai decimal(6,2) NULL,
        Jumlah_Penilai smallint NULL,
        Calculation_Version int NOT NULL
            CONSTRAINT DF_PA_Result_Competency_Calc_Version DEFAULT (1),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Competency_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Competency_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Competency PRIMARY KEY (Id_PA_Result_Competency),
        CONSTRAINT CK_PA_Result_Competency_Nilai
            CHECK (Nilai IS NULL OR Nilai >= 0),
        CONSTRAINT CK_PA_Result_Competency_Penilai
            CHECK (Jumlah_Penilai IS NULL OR Jumlah_Penilai >= 0),
        CONSTRAINT CK_PA_Result_Competency_Calc_Version
            CHECK (Calculation_Version >= 1)
    );
END;
GO

/* ------------------------------------------------------------------ *
 * 3. Riwayat kalibrasi — HANYA SISIP                                   *
 *    Tanpa Flag_Aktif dan tanpa Updated_*: riwayat yang bisa            *
 *    dinonaktifkan atau disunting bukan riwayat.                        *
 * ------------------------------------------------------------------ */
IF OBJECT_ID('N_PA_Calibration_History', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Calibration_History (
        Id_PA_Calibration_History int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        Kode_Grade_Sebelum varchar(40) NULL,
        Kode_Grade_Sesudah varchar(40) NOT NULL,
        PA_Hav_Quadrant_Sebelum_Id int NULL,
        PA_Hav_Quadrant_Sesudah_Id int NULL,
        /* Wajib. Baris ini hanya lahir ketika seseorang menimpa hasil mesin, dan
           penimpaan tanpa alasan tidak bisa dipertanggungjawabkan. */
        Alasan nvarchar(500) NOT NULL,
        Performed_By int NOT NULL,
        /* Nama pelakunya DIBEKUKAN di sini, bukan di-join ke daftar user saat dibaca.
           Pola yang sama dengan Nama_Penilai_Saat_Cycle dan seluruh kolom *_Salinan di
           modul ini: yang membaca riwayat setahun kemudian belum tentu punya akses ke
           daftar user, dan user yang keluar dari perusahaan membuat join-nya kosong -
           persis pada baris yang paling perlu dijelaskan. Boleh kosong karena kalibrasi
           bisa juga lahir dari proses tanpa nama. */
        Performed_By_Nama nvarchar(150) NULL,
        Performed_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Calibration_Performed DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_PA_Calibration_History PRIMARY KEY (Id_PA_Calibration_History),
        /* Kalibrasi yang tidak mengubah apa pun adalah baris kosong yang mengotori
           riwayat. Minimal grade atau kuadrannya harus bergeser. */
        CONSTRAINT CK_PA_Calibration_Ada_Perubahan
            CHECK (
                ISNULL(Kode_Grade_Sebelum, '') <> Kode_Grade_Sesudah
                OR ISNULL(PA_Hav_Quadrant_Sebelum_Id, -1) <> ISNULL(PA_Hav_Quadrant_Sesudah_Id, -1)
            ),
        CONSTRAINT CK_PA_Calibration_Alasan
            CHECK (LEN(LTRIM(RTRIM(Alasan))) > 0)
    );
END;
GO

/* Jalur bagi database yang sudah punya tabelnya sejak revisi sebelumnya. Blok CREATE
   di atas hanya berjalan saat tabelnya belum ada, jadi tanpa ALTER ini kolomnya tidak
   pernah sampai ke sana. */
IF COL_LENGTH('N_PA_Calibration_History', 'Performed_By_Nama') IS NULL
    ALTER TABLE N_PA_Calibration_History ADD Performed_By_Nama nvarchar(150) NULL;
GO

/* ------------------------------------------------------------------ *
 * 4. Catatan hasil — Strength dan Development Area                     *
 * ------------------------------------------------------------------ */
IF OBJECT_ID('N_PA_Result_Note', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_Note (
        Id_PA_Result_Note int IDENTITY(1,1) NOT NULL,
        PA_Result_Id int NOT NULL,
        PA_Result_Note_Type_Id int NOT NULL,
        /* Kosong berarti diketik admin; terisi berarti berasal dari jawaban
           kualitatif seorang penilai. Asal-usulnya penting karena keduanya punya
           bobot berbeda saat dibaca karyawan. */
        PA_Assignment_Id int NULL,
        Isi nvarchar(max) NOT NULL,
        Urutan_Tampil smallint NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_Note_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Result_Note PRIMARY KEY (Id_PA_Result_Note),
        CONSTRAINT CK_PA_Result_Note_Isi
            CHECK (LEN(LTRIM(RTRIM(Isi))) > 0),
        CONSTRAINT CK_PA_Result_Note_Urutan
            CHECK (Urutan_Tampil IS NULL OR Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Rater_Cat_Result')
    ALTER TABLE N_PA_Result_Rater_Category WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Rater_Cat_Result
        FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Rater_Cat_Category')
    ALTER TABLE N_PA_Result_Rater_Category WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Rater_Cat_Category
        FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Competency_Result')
    ALTER TABLE N_PA_Result_Competency WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Competency_Result
        FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Competency_Comp')
    ALTER TABLE N_PA_Result_Competency WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Competency_Comp
        FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Competency_Category')
    ALTER TABLE N_PA_Result_Competency WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Competency_Category
        FOREIGN KEY (PA_Rater_Category_Id) REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Calibration_Result')
    ALTER TABLE N_PA_Calibration_History WITH CHECK
        ADD CONSTRAINT FK_PA_Calibration_Result
        FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Calibration_Kuadran_Sebelum')
    ALTER TABLE N_PA_Calibration_History WITH CHECK
        ADD CONSTRAINT FK_PA_Calibration_Kuadran_Sebelum
        FOREIGN KEY (PA_Hav_Quadrant_Sebelum_Id) REFERENCES N_PA_Hav_Quadrant (Id_PA_Hav_Quadrant);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Calibration_Kuadran_Sesudah')
    ALTER TABLE N_PA_Calibration_History WITH CHECK
        ADD CONSTRAINT FK_PA_Calibration_Kuadran_Sesudah
        FOREIGN KEY (PA_Hav_Quadrant_Sesudah_Id) REFERENCES N_PA_Hav_Quadrant (Id_PA_Hav_Quadrant);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Note_Result')
    ALTER TABLE N_PA_Result_Note WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Note_Result
        FOREIGN KEY (PA_Result_Id) REFERENCES N_PA_Result (Id_PA_Result);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Note_Type')
    ALTER TABLE N_PA_Result_Note WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Note_Type
        FOREIGN KEY (PA_Result_Note_Type_Id) REFERENCES N_PA_Result_Note_Type (Id_PA_Result_Note_Type);

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Note_Assignment')
    ALTER TABLE N_PA_Result_Note WITH CHECK
        ADD CONSTRAINT FK_PA_Result_Note_Assignment
        FOREIGN KEY (PA_Assignment_Id) REFERENCES N_PA_Assignment (Id_PA_Assignment);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Result_Rater_Cat_Aktif')
    CREATE UNIQUE INDEX UX_PA_Result_Rater_Cat_Aktif
        ON N_PA_Result_Rater_Category (PA_Result_Id, PA_Rater_Category_Id)
        WHERE Flag_Aktif = 'Y';

/* NULL diperlakukan SAMA oleh unique index SQL Server, sehingga baris agregat
   (kategori kosong) otomatis terbatas satu per (hasil, kompetensi). Itu memang
   yang diinginkan. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PA_Result_Competency_Aktif')
    CREATE UNIQUE INDEX UX_PA_Result_Competency_Aktif
        ON N_PA_Result_Competency (PA_Result_Id, PA_Competency_Id, PA_Rater_Category_Id)
        WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Result_Competency_Agregat')
    CREATE INDEX IX_PA_Result_Competency_Agregat
        ON N_PA_Result_Competency (PA_Result_Id, Flag_Aktif)
        INCLUDE (PA_Competency_Id, PA_Rater_Category_Id, Nilai);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Calibration_Result')
    CREATE INDEX IX_PA_Calibration_Result
        ON N_PA_Calibration_History (PA_Result_Id, Performed_At);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Result_Note_Result')
    CREATE INDEX IX_PA_Result_Note_Result
        ON N_PA_Result_Note (PA_Result_Id, Flag_Aktif)
        INCLUDE (PA_Result_Note_Type_Id, PA_Assignment_Id, Urutan_Tampil);
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result_Rater_Category')) <> 13
    THROW 50322, 'DDL-2026-081: kolom Result Rater Category tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result_Competency')) <> 12
    THROW 50323, 'DDL-2026-081: kolom Result Competency tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Calibration_History')) <> 10
    THROW 50324, 'DDL-2026-081: kolom Calibration History tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result_Note')) <> 11
    THROW 50325, 'DDL-2026-081: kolom Result Note tidak lengkap.', 1;

/* Riwayat kalibrasi WAJIB tidak punya kolom yang memungkinkan pembatalan atau
   penyuntingan. Kalau suatu hari ada yang menambahkannya, penjaga ini menyala. */
IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name IN ('Flag_Aktif', 'Updated_At', 'Updated_By')
)
    THROW 50326, 'DDL-2026-081: riwayat kalibrasi tidak boleh punya Flag_Aktif atau Updated_*.', 1;

IF (
    SELECT COUNT(*) FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Result_Rater_Category'),
        OBJECT_ID('N_PA_Result_Competency'),
        OBJECT_ID('N_PA_Calibration_History'),
        OBJECT_ID('N_PA_Result_Note')
    )
) <> 11
    THROW 50327, 'DDL-2026-081: jumlah foreign key bukan 11.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Result_Rater_Category'),
        OBJECT_ID('N_PA_Result_Competency'),
        OBJECT_ID('N_PA_Calibration_History'),
        OBJECT_ID('N_PA_Result_Note')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50328, 'DDL-2026-081: terdapat FK yang disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id IN (
        OBJECT_ID('N_PA_Result_Rater_Category'),
        OBJECT_ID('N_PA_Result_Competency'),
        OBJECT_ID('N_PA_Calibration_History'),
        OBJECT_ID('N_PA_Result_Note')
    ) AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50329, 'DDL-2026-081: terdapat CHECK yang disabled atau tidak trusted.', 1;
GO
