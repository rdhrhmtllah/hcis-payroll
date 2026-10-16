/* Change ID: DDL-2026-140-pa-esai-per-soal
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-076 (N_PA_Assignment_Score).
   Scope: butir esai per soal dari Esai[] callback HC Learn.
   Rancangan: docs/superpowers/specs/2026-09-26-pa-esai-per-soal-design.md
*/

/* 1. Satu baris per soal esai pada satu baris skor. Tanpa Flag_Aktif: baris anak mengikuti
      Flag_Aktif baris skornya, dan pemetaan ulang menyisipkan anak baru untuk baris skor
      baru. */
IF OBJECT_ID('N_PA_Assignment_Score_Esai', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Assignment_Score_Esai (
        Id_PA_Assignment_Score_Esai int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Assignment_Score_Esai PRIMARY KEY,
        PA_Assignment_Score_Id int NOT NULL
            CONSTRAINT FK_PA_Assignment_Score_Esai_Score
                REFERENCES N_PA_Assignment_Score (Id_PA_Assignment_Score),
        Id_Master_Soal int NULL,
        Urutan_Tampil smallint NOT NULL,
        Pertanyaan nvarchar(max) NULL,
        Jawaban_Teks nvarchar(max) NULL,
        Waktu_Dijawab datetime2(0) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Assignment_Score_Esai_Created_At DEFAULT (sysdatetime()),
        CONSTRAINT CK_PA_Assignment_Score_Esai_Urutan CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Assignment_Score_Esai') AND name = 'UX_PA_Assignment_Score_Esai_Urutan'
)
    CREATE UNIQUE INDEX UX_PA_Assignment_Score_Esai_Urutan
        ON N_PA_Assignment_Score_Esai (PA_Assignment_Score_Id, Urutan_Tampil);
GO

/* 2. Pendaftaran perubahan. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-140')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-140',
        'DDL',
        'Butir esai per soal pada Performance Appraisal',
        'Callback HC Learn kini mengirim Esai[] per bagian: satu entri per soal dengan Id_Master_Soal, Pertanyaan, dan Jawaban_Peserta. Tiap soal disimpan sebagai anak baris N_PA_Assignment_Score, sehingga layar Kalibrasi Penilai bisa menampilkan pertanyaan di atas jawabannya dan esai dikalibrasi per soal.',
        'database/ddl/DDL-2026-140-pa-esai-per-soal/forward.sql',
        'docs/changes/DDL-2026-140-pa-esai-per-soal/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'Skor bagian esai sengaja tidak dipakai (keputusan pemilik proyek 26 September 2026); angka mentahnya tetap di Payload_Json. Dipasangkan dengan DDL-2026-119 r3 (Id_Master_Soal pada N_PA_Kalibrasi_Penilai_Jawaban).',
        NULL
    );
END;
GO
