/* Change ID: DDL-2026-082-performance-appraisal-assignment-cancellation
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-075. Jalankan sesudahnya.
   Scope: tiga kolom pembatalan pada N_PA_Assignment.

   Kenapa: status DIBATALKAN sudah ada di master sejak DDL-2026-075, tetapi tabelnya
   tidak punya tempat untuk menyimpan SIAPA yang membatalkan, KAPAN, dan KENAPA.
   Penugasan yang lenyap tanpa sebab adalah persis keluhan yang sudah pernah muncul di
   modul lain: yang membaca layar hanya bisa menebak. Alasan_Manual TIDAK dipakai untuk
   ini - kolom itu menjelaskan kenapa seseorang DITAMBAHKAN, bukan kenapa dicabut, dan
   menumpuknya akan membuat kedua sebab itu tak bisa dibedakan lagi. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50330, 'DDL-2026-082 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Assignment', 'U') IS NULL
    THROW 50331, 'DDL-2026-082 membutuhkan N_PA_Assignment (DDL-2026-075).', 1;
GO

IF COL_LENGTH('N_PA_Assignment', 'Cancel_Reason') IS NULL
    ALTER TABLE N_PA_Assignment ADD Cancel_Reason nvarchar(500) NULL;
GO

IF COL_LENGTH('N_PA_Assignment', 'Cancelled_At') IS NULL
    ALTER TABLE N_PA_Assignment ADD Cancelled_At datetime2(0) NULL;
GO

IF COL_LENGTH('N_PA_Assignment', 'Cancelled_By') IS NULL
    ALTER TABLE N_PA_Assignment ADD Cancelled_By int NULL;
GO

/* Alasan kosong sama tak bergunanya dengan tidak ada alasan. Spasi ditolak di sini
   supaya aplikasi tidak bisa memenuhi syarat "wajib beralasan" dengan menekan spasi. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Assignment_Cancel_Reason')
    ALTER TABLE N_PA_Assignment ADD CONSTRAINT CK_PA_Assignment_Cancel_Reason
        CHECK (Cancel_Reason IS NULL OR LEN(LTRIM(RTRIM(Cancel_Reason))) > 0);
GO

/* Waktu pembatalan tanpa sebab adalah jejak yang tidak menjawab apa pun. Aturan ini
   berlaku per baris, tidak mengeraskan satu pun nilai bisnis, dan karena itu boleh
   menjadi CHECK. Bahwa status DIBATALKAN wajib disertai ketiganya TIDAK dijaga di sini:
   daftar statusnya bisa tumbuh, dan mengeraskan literalnya ke skema melanggar aturan
   proyek. Penjaganya ada di PaAssignmentBuilderService. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Assignment_Cancel_Lengkap')
    ALTER TABLE N_PA_Assignment ADD CONSTRAINT CK_PA_Assignment_Cancel_Lengkap
        CHECK (Cancelled_At IS NULL OR Cancel_Reason IS NOT NULL);
GO

/* -------- verifikasi -------- */
IF COL_LENGTH('N_PA_Assignment', 'Cancel_Reason') IS NULL
   OR COL_LENGTH('N_PA_Assignment', 'Cancelled_At') IS NULL
   OR COL_LENGTH('N_PA_Assignment', 'Cancelled_By') IS NULL
    THROW 50332, 'DDL-2026-082: kolom pembatalan tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Assignment')) <> 22
    THROW 50333, 'DDL-2026-082: jumlah kolom N_PA_Assignment bukan 22.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name IN ('CK_PA_Assignment_Cancel_Reason', 'CK_PA_Assignment_Cancel_Lengkap')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50334, 'DDL-2026-082: CHECK pembatalan disabled atau tidak trusted.', 1;

IF (SELECT COUNT(*) FROM sys.check_constraints
    WHERE name IN ('CK_PA_Assignment_Cancel_Reason', 'CK_PA_Assignment_Cancel_Lengkap')) <> 2
    THROW 50335, 'DDL-2026-082: CHECK pembatalan tidak lengkap.', 1;
GO
