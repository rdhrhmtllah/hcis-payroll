/* Change ID: DDL-2026-119-pa-kalibrasi-penilai — PEMBATALAN.

   TIDAK DIJALANKAN AGEN. Jalan pulang bila perubahannya harus dicabut, dilakukan manusia
   lewat SQL Tracker.

   PERHATIAN: mencabut ketiga tabel MEMBUANG seluruh jejak Kalibrasi Penilai. Hasil yang
   sudah dihitung dari skor terkalibrasi tetap tersimpan, tetapi asal angkanya tidak lagi
   bisa dijelaskan — dan menghitung ulang sesudahnya memakai skor asli HC Learn.
*/

IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Jawaban', 'U') IS NOT NULL
    DROP TABLE N_PA_Kalibrasi_Penilai_Jawaban;
GO

IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail', 'U') IS NOT NULL
    DROP TABLE N_PA_Kalibrasi_Penilai_Detail;
GO

IF OBJECT_ID('N_PA_Kalibrasi_Penilai', 'U') IS NOT NULL
    DROP TABLE N_PA_Kalibrasi_Penilai;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'UX_PA_Rater_Category_Urutan'
)
    DROP INDEX UX_PA_Rater_Category_Urutan ON N_PA_Rater_Category;
GO

IF EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'DF_PA_Rater_Category_Boleh_Kalibrasi'
)
    ALTER TABLE N_PA_Rater_Category DROP CONSTRAINT DF_PA_Rater_Category_Boleh_Kalibrasi;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Flag_Boleh_Kalibrasi'
)
    ALTER TABLE N_PA_Rater_Category DROP COLUMN Flag_Boleh_Kalibrasi;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Urutan_Pengisian'
)
    ALTER TABLE N_PA_Rater_Category DROP COLUMN Urutan_Pengisian;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-119';
GO
