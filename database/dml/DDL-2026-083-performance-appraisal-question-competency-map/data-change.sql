/* Change ID: DDL-2026-083-performance-appraisal-question-competency-map
   Target: Web_HRIS development; production only through SQL Tracker.
   Artifact revision: r2.
   First-run maximum scope: register the change <= 1.
   Tidak ada seed: pemetaannya diisi admin per paket soal, dari daftar soal HC Learn. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50349, 'DDL-2026-083 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'PA_Result_Note_Type_Id') IS NULL
   OR COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'Jenis_Soal') IS NULL
    THROW 50351, 'DDL-2026-083 DML membutuhkan forward.sql r2 yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-083-performance-appraisal-question-competency-map'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-083-performance-appraisal-question-competency-map',
         'DDL',
         'Peran catatan dan jenis soal pada butir paket soal siklus',
         'Menambah Jenis_Soal dan PA_Result_Note_Type_Id pada N_PA_Cycle_Question_Package_Item.',
         'database/ddl/DDL-2026-083-performance-appraisal-question-competency-map/forward.sql',
         'docs/changes/DDL-2026-083-performance-appraisal-question-competency-map/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'REVISI r2. r1 membuat tabel BARU N_PA_Question_Competency_Map yang MENDUPLIKASI N_PA_Cycle_Question_Package_Item dari DDL-2026-073 - per paket, dengan unique index tersaring yang sama persis. Kembarnya memutus rantai: pemeta payload membaca peta dari tabel baru sedangkan pemeriksa kelengkapan butir membaca tabel lama yang kosong, sehingga penugasan tak pernah ditandai SELESAI, seluruh penilai dilewati saat perhitungan, dan pilar kompetensi kosong tanpa satu pun pesan galat - terbukti pada 1358 baris jejak kategori dengan Jumlah_Rater_Mengisi nol. r2 tidak membuat tabel apa pun, hanya menambah dua kolom yang memang belum ada di tabel lama: Jenis_Soal (tanpa CHECK karena daftarnya milik HC Learn dan bisa tumbuh) dan PA_Result_Note_Type_Id beserta CHECK yang melarang satu butir menjadi nilai kompetensi sekaligus catatan kualitatif. Checksum r1 TIDAK BOLEH dicatat ke SQL Tracker.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
