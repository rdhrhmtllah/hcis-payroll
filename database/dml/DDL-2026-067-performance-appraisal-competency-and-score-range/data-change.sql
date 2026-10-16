/* Change ID: DDL-2026-067-performance-appraisal-competency-and-score-range
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: insert competency type <= 4, insert competency <= 24,
   and register the change <= 1.
   TANPA seed pemetaan kode soal (kontrak HC Learn belum pasti, T01/T04),
   TANPA seed requirement (pemetaan band ke level HRIS belum diputuskan HC),
   TANPA seed pita nilai (skala kanonik masih terbuka, T05). */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50140, 'DDL-2026-067 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Competency_Type', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency_Kode_Soal', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency_Requirement', 'U') IS NULL
   OR OBJECT_ID('N_PA_Score_Range', 'U') IS NULL
    THROW 50141, 'DDL-2026-067 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL')
BEGIN
    INSERT INTO N_PA_Competency_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('MANAJERIAL', N'Kompetensi Manajerial', N'Kompetensi manajerial berskor, sumber utama nilai kompetensi.', 1, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency_Type WHERE Kode_Type = 'CORE_VALUE')
BEGIN
    INSERT INTO N_PA_Competency_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('CORE_VALUE', N'Core Value', N'Nilai inti perusahaan, dinilai berangka.', 2, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency_Type WHERE Kode_Type = 'BEHAVIOR')
BEGIN
    INSERT INTO N_PA_Competency_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('BEHAVIOR', N'Behavior (Core Value)', N'Perilaku turunan core value untuk band Pramukarya.', 3, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency_Type WHERE Kode_Type = 'KUALITATIF')
BEGIN
    INSERT INTO N_PA_Competency_Type (Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('KUALITATIF', N'Qualitative Question', N'Butir bertekstual tanpa angka; tidak ikut perhitungan.', 4, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K01')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K01', N'Analytical Thinking', 'Y', 1, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K02')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K02', N'Problem Solving', 'Y', 2, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K03')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K03', N'Decision Making', 'Y', 3, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K04')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K04', N'Planning and Organizing', 'Y', 4, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K05')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K05', N'Achievement Orientation', 'Y', 5, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K06')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K06', N'Customer Focus', 'Y', 6, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K07')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K07', N'Impactful Communication', 'Y', 7, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K08')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K08', N'Change & Adaptability', 'Y', 8, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K09')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K09', N'Teamwork & Collaboration', 'Y', 9, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K10')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K10', N'Business Sense', 'Y', 10, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'K11')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'K11', N'Team Leadership', 'Y', 11, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'MANAJERIAL';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'CV01')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'CV01', N'Focus on Customer', 'Y', 1, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'CORE_VALUE';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'CV02')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'CV02', N'Agility & Improvement', 'Y', 2, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'CORE_VALUE';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'CV03')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'CV03', N'Mutual Growth', 'Y', 3, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'CORE_VALUE';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'CV04')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'CV04', N'Integrity', 'Y', 4, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'CORE_VALUE';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'CV05')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'CV05', N'Leadership', 'Y', 5, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'CORE_VALUE';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'CV06')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'CV06', N'Yes, We Can Spirit', 'Y', 6, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'CORE_VALUE';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'BH01')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'BH01', N'Discipline/Integrity', 'Y', 1, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'BEHAVIOR';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'BH02')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'BH02', N'Diligent/YWC', 'Y', 2, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'BEHAVIOR';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'BH03')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'BH03', N'Empathy/Focus on Customer', 'Y', 3, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'BEHAVIOR';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'QL01')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'QL01', N'Strength', 'T', 1, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'KUALITATIF';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'QL02')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'QL02', N'Development Area', 'T', 2, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'KUALITATIF';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'QL03')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'QL03', N'Initiative', 'T', 3, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'KUALITATIF';
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Competency WHERE Kode_Competency = 'QL04')
BEGIN
    INSERT INTO N_PA_Competency (PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_By)
    SELECT Id_PA_Competency_Type, 'QL04', N'Recommendation', 'T', 4, 'Y', NULL
    FROM N_PA_Competency_Type WHERE Kode_Type = 'KUALITATIF';
END;

IF (SELECT COUNT(*) FROM N_PA_Competency_Type WHERE Flag_Aktif = 'Y') < 4
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50142, 'DDL-2026-067: empat jenis kompetensi tidak lengkap.', 1;
END;

IF (SELECT COUNT(*) FROM N_PA_Competency WHERE Flag_Aktif = 'Y') < 24
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50143, 'DDL-2026-067: dua puluh empat kompetensi tidak lengkap.', 1;
END;

IF (SELECT COUNT(*) FROM N_PA_Competency WHERE Flag_Berskor = 'T') <> 4
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50144, 'DDL-2026-067: butir kualitatif tanpa skor harus tepat empat.', 1;
END;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-067-performance-appraisal-competency-and-score-range'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-067-performance-appraisal-competency-and-score-range',
         'DDL',
         'Master kompetensi, pemetaan kode soal, kebutuhan per level, dan pita nilai',
         'Membuat lima tabel fondasi PA dan seed 4 jenis kompetensi serta 24 kompetensi dari deck. Pemetaan kode soal, kebutuhan per level, dan pita nilai lahir kosong.',
         'database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/forward.sql',
         'docs/changes/DDL-2026-067-performance-appraisal-competency-and-score-range/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Artifact revisi r2: kolom Target_Persen ditambahkan ke N_PA_Score_Range untuk target distribusi grade (forced distribution) pada halaman kalibrasi. Bergantung pada HRIS_Level. Kamus (Type, Competency, Kode_Soal) tidak disnapshot per siklus; aturan (Requirement, Score_Range) disnapshot. Lihat K26.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO