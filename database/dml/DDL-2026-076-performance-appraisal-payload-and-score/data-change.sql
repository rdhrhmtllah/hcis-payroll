/* Change ID: DDL-2026-076-performance-appraisal-payload-and-score
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Tidak ada seed: kedua tabel diisi callback HC Learn dan pemeta, bukan data acuan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50268, 'DDL-2026-076 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Assignment_Payload', 'U') IS NULL
   OR OBJECT_ID('N_PA_Assignment_Score', 'U') IS NULL
    THROW 50269, 'DDL-2026-076 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-076-performance-appraisal-payload-and-score'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-076-performance-appraisal-payload-and-score',
         'DDL',
         'Bundel mentah hasil HC Learn dan skor per butir',
         'Membuat N_PA_Assignment_Payload yang menyimpan balasan callback apa adanya, dan N_PA_Assignment_Score yang menyimpan hasil proyeksi pemeta per butir soal.',
         'database/ddl/DDL-2026-076-performance-appraisal-payload-and-score/forward.sql',
         'docs/changes/DDL-2026-076-performance-appraisal-payload-and-score/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-067 dan 075; jalankan sesudah keduanya. Revisi r2 mengganti acuan payload dari penugasan langsung menjadi baris integrasi asal agar callback token lama tetap memakai paket soal lamanya; penugasan tetap dapat diturunkan lewat integrasi tanpa dua sumber kebenaran. Sisip-saja berlaku pada ISI payload, bukan seluruh barisnya: Flag_Terpeta dan Mapper_Version memang berubah setelah baris lahir. Skor TIDAK membawa PA_Assignment_Id karena penugasannya sudah melekat melalui payload dan integrasi. Memetakan ulang tidak menghapus apa pun: baris lama dinonaktifkan, baris baru disisipkan. Status_Interpretation dibuang karena "belum dikenali" sudah terbaca dari PA_Competency_Id NULL. Menutup prasyarat ketiga N_PA_Result.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
