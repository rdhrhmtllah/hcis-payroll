/* Change ID: DDL-2026-081-performance-appraisal-result-detail
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Tidak ada seed: keempat tabel diisi mesin perhitungan dan admin. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50331, 'DDL-2026-081 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Result_Rater_Category', 'U') IS NULL
   OR OBJECT_ID('N_PA_Result_Competency', 'U') IS NULL
   OR OBJECT_ID('N_PA_Calibration_History', 'U') IS NULL
   OR OBJECT_ID('N_PA_Result_Note', 'U') IS NULL
    THROW 50332, 'DDL-2026-081 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-081-performance-appraisal-result-detail'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-081-performance-appraisal-result-detail',
         'DDL',
         'Empat tabel anak hasil PA: jejak kategori, nilai kompetensi, kalibrasi, catatan',
         'Membuat N_PA_Result_Rater_Category, N_PA_Result_Competency, N_PA_Calibration_History, dan N_PA_Result_Note.',
         'database/ddl/DDL-2026-081-performance-appraisal-result-detail/forward.sql',
         'docs/changes/DDL-2026-081-performance-appraisal-result-detail/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-064, 067, 068, 075, 080. N_PA_Result_Rater_Category menyimpan Bobot_Terpakai - bobot yang BENAR-BENAR dipakai, bukan yang tertulis di aturan; keduanya berbeda saat satu kategori kosong dan bobotnya dinormalkan ulang. N_PA_Result_Competency membolehkan PA_Rater_Category_Id KOSONG yang berarti AGREGAT lintas kategori (dipakai radar chart); unique index memperlakukan NULL sebagai sama sehingga baris agregat otomatis terbatas satu per (hasil, kompetensi). N_PA_Calibration_History SENGAJA tanpa Flag_Aktif dan tanpa Updated_* - riwayat yang bisa dinonaktifkan bukan riwayat - dan ada penjaga skema yang menyala bila kelak ada yang menambahkannya.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
