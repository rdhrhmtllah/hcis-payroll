/* Change ID: DDL-2026-073-performance-appraisal-cycle-question-package
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   TANPA seed: kedua tabel diisi aplikasi saat admin memilih paket HC Learn. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50228, 'DDL-2026-073 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle_Question_Package', 'U') IS NULL
   OR OBJECT_ID('N_PA_Cycle_Question_Package_Item', 'U') IS NULL
    THROW 50229, 'DDL-2026-073 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-073-performance-appraisal-cycle-question-package'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-073-performance-appraisal-cycle-question-package',
         'DDL',
         'Kontrak paket soal HC Learn per siklus dan level',
         'Membuat N_PA_Cycle_Question_Package dan itemnya sebagai salinan daftar kode soal saat paket dipilih, dipakai membandingkan apa yang seharusnya datang dengan apa yang benar-benar datang di callback.',
         'database/ddl/DDL-2026-073-performance-appraisal-cycle-question-package/forward.sql',
         'docs/changes/DDL-2026-073-performance-appraisal-cycle-question-package/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-066 dan 067. Skor_Maksimal disimpan per butir supaya skala dibaca dari data, bukan diasumsikan (T05 masih terbuka). Butiran per (siklus, level) mengasumsikan satu form untuk semua kategori penilai sesuai deck slide 9.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
