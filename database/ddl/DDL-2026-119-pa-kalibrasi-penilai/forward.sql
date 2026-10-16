/* Change ID: DDL-2026-119-pa-kalibrasi-penilai
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064 (N_PA_Rater_Category), DDL-2026-075 (N_PA_Assignment),
                 DDL-2026-067 (N_PA_Competency).
   Scope: urutan pengisian dan penanda boleh-kalibrasi pada N_PA_Rater_Category; tiga tabel
          jejak Kalibrasi Penilai (kepala, rincian skor, rincian jawaban esai).
   Revisi: r2 (18 Sep 2026) menambah N_PA_Kalibrasi_Penilai_Jawaban.
           r3 (26 Sep 2026) menambah Id_Master_Soal pada _Jawaban: esai dikalibrasi per soal.
   Rancangan: docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md
*/

/* 1. Urutan pengisian. NULL = belum diurutkan; nilainya data bisnis, masuk lewat DML. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Urutan_Pengisian'
)
    ALTER TABLE N_PA_Rater_Category ADD Urutan_Pengisian smallint NULL;
GO

/* 2. Penanda boleh-kalibrasi. Bawaan 'T': fitur tertutup sampai admin menyalakannya. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Flag_Boleh_Kalibrasi'
)
    ALTER TABLE N_PA_Rater_Category
        ADD Flag_Boleh_Kalibrasi char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Category_Boleh_Kalibrasi DEFAULT ('T');
GO

/* 3. Dua kategori aktif tidak boleh berebut langkah yang sama. Tersaring IS NOT NULL
      karena SQL Server menganggap NULL sama di indeks unik. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'UX_PA_Rater_Category_Urutan'
)
    CREATE UNIQUE INDEX UX_PA_Rater_Category_Urutan
        ON N_PA_Rater_Category (Urutan_Pengisian)
        WHERE Flag_Aktif = 'Y' AND Urutan_Pengisian IS NOT NULL;
GO

/* 4. Kepala: satu baris per tindakan simpan penilai atas satu orang. */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai (
        Id_PA_Kalibrasi_Penilai int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Kalibrasi_Penilai PRIMARY KEY,
        PA_Assignment_Id int NOT NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Assignment
                REFERENCES N_PA_Assignment (Id_PA_Assignment),
        Alasan nvarchar(500) NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Kalibrasi_Penilai_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai') AND name = 'IX_PA_Kalibrasi_Penilai_Assignment'
)
    CREATE INDEX IX_PA_Kalibrasi_Penilai_Assignment
        ON N_PA_Kalibrasi_Penilai (PA_Assignment_Id, Id_PA_Kalibrasi_Penilai DESC);
GO

/* 5. Rincian: satu baris per kompetensi yang diubah pada satu tindakan. */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai_Detail (
        Id_PA_Kalibrasi_Penilai_Detail int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Kalibrasi_Penilai_Detail PRIMARY KEY,
        PA_Kalibrasi_Penilai_Id int NOT NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Detail_Kepala
                REFERENCES N_PA_Kalibrasi_Penilai (Id_PA_Kalibrasi_Penilai),
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Detail_Competency
                REFERENCES N_PA_Competency (Id_PA_Competency),
        Skor_Sebelum decimal(6,2) NOT NULL,
        Skor_Sesudah decimal(6,2) NOT NULL,
        Skor_Maksimal decimal(6,2) NOT NULL,
        CONSTRAINT CK_PA_Kalibrasi_Penilai_Detail_Rentang
            CHECK (Skor_Maksimal > 0
                   AND Skor_Sesudah >= 0 AND Skor_Sesudah <= Skor_Maksimal
                   AND Skor_Sebelum >= 0)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail') AND name = 'UX_PA_Kalibrasi_Penilai_Detail_Kompetensi'
)
    CREATE UNIQUE INDEX UX_PA_Kalibrasi_Penilai_Detail_Kompetensi
        ON N_PA_Kalibrasi_Penilai_Detail (PA_Kalibrasi_Penilai_Id, PA_Competency_Id);
GO

/* 6. Rincian jawaban esai: satu baris per butir teks yang diubah pada satu tindakan.
      Berkunci kode soal, bukan id baris skor, supaya selamat saat petakanUlang()
      menonaktifkan baris lama — UX_PA_Assignment_Score_Butir menjamin kode soal unik per
      payload untuk baris aktif. Jawaban tidak boleh dikosongkan: butir kosong terbaca
      "tidak dijawab" dan bisa menahan hasil orang yang dinilai.
      Id_Master_Soal (r3) menunjuk satu soal di dalam bagian itu, dari Esai[] callback
      HC Learn. NULL berarti jawaban format lama: satu teks untuk seluruh bagian. */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Jawaban', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai_Jawaban (
        Id_PA_Kalibrasi_Penilai_Jawaban int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Kalibrasi_Penilai_Jawaban PRIMARY KEY,
        PA_Kalibrasi_Penilai_Id int NOT NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Jawaban_Kepala
                REFERENCES N_PA_Kalibrasi_Penilai (Id_PA_Kalibrasi_Penilai),
        Kode_Soal_Sumber varchar(100) NOT NULL,
        Id_Master_Soal int NULL,
        PA_Competency_Id int NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Jawaban_Competency
                REFERENCES N_PA_Competency (Id_PA_Competency),
        Jawaban_Sebelum nvarchar(max) NOT NULL,
        Jawaban_Sesudah nvarchar(max) NOT NULL,
        CONSTRAINT CK_PA_Kalibrasi_Penilai_Jawaban_Terisi
            CHECK (LEN(LTRIM(RTRIM(Jawaban_Sesudah))) > 0)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai_Jawaban') AND name = 'UX_PA_Kalibrasi_Penilai_Jawaban_Soal'
)
    CREATE UNIQUE INDEX UX_PA_Kalibrasi_Penilai_Jawaban_Soal
        ON N_PA_Kalibrasi_Penilai_Jawaban (PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber, Id_Master_Soal);
GO

/* 7. Pendaftaran perubahan. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-119')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-119',
        'DDL',
        'Urutan pengisian dan Kalibrasi Penilai pada Performance Appraisal',
        'Wizard tugas penilaian berjalan per kategori penilai menurut Urutan_Pengisian. Pada kategori ber-Flag_Boleh_Kalibrasi = Y, penilai boleh mengubah skornya per kompetensi dan jawaban esainya sebelum mengirim; setiap perubahan tercatat di N_PA_Kalibrasi_Penilai, _Detail (skor), dan _Jawaban (esai, per soal bila HC Learn mengirim Esai[]) tanpa menyentuh N_PA_Assignment_Score.',
        'database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql',
        'docs/changes/DDL-2026-119-pa-kalibrasi-penilai/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'Kamus hidup atas keputusan pemilik proyek 18 September 2026 (menyimpang dari K26). Kalibrasi berkunci (penugasan, kompetensi) supaya selamat saat petakanUlang() menonaktifkan baris skor lama. Tabel kalibrasi hanya ditambah.',
        NULL
    );
END;
GO
