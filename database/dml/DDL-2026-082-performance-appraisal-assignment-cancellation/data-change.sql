/* Change ID: DDL-2026-082-performance-appraisal-assignment-cancellation
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: register the change <= 1.
   Tidak ada seed: ketiga kolomnya diisi aplikasi saat penugasan dibatalkan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50337, 'DDL-2026-082 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF COL_LENGTH('N_PA_Assignment', 'Cancel_Reason') IS NULL
   OR COL_LENGTH('N_PA_Assignment', 'Cancelled_At') IS NULL
   OR COL_LENGTH('N_PA_Assignment', 'Cancelled_By') IS NULL
    THROW 50338, 'DDL-2026-082 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-082-performance-appraisal-assignment-cancellation'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-082-performance-appraisal-assignment-cancellation',
         'DDL',
         'Kolom pembatalan penugasan PA: sebab, waktu, pelaku',
         'Menambah Cancel_Reason, Cancelled_At, dan Cancelled_By pada N_PA_Assignment.',
         'database/ddl/DDL-2026-082-performance-appraisal-assignment-cancellation/forward.sql',
         'docs/changes/DDL-2026-082-performance-appraisal-assignment-cancellation/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bergantung pada DDL-2026-075. Status DIBATALKAN sudah ada di master sejak 075 tetapi tabelnya tak punya tempat menyimpan siapa, kapan, dan kenapa - penugasan yang lenyap tanpa sebab hanya bisa ditebak oleh yang membaca layar. Alasan_Manual SENGAJA tidak dipakai ulang: kolom itu menjelaskan kenapa seseorang DITAMBAHKAN, bukan kenapa dicabut. Dua CHECK berlaku per baris dan tidak mengeraskan nilai bisnis apa pun: alasan tidak boleh spasi, dan waktu pembatalan tak boleh ada tanpa sebab. Bahwa status DIBATALKAN wajib disertai ketiganya dijaga di PaAssignmentBuilderService, bukan di skema, karena daftar statusnya bisa tumbuh.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
