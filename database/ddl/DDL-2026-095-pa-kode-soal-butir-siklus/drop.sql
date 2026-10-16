/* Change ID: DDL-2026-095-pa-kode-soal-butir-siklus — PEMBATALAN.

   TIDAK DIJALANKAN AGEN. Berkas ini hanya jalan pulang bila perubahannya harus dicabut,
   dan pencabutannya dilakukan manusia lewat SQL Tracker.

   PERHATIAN: mencabut kolom ini MEMBUANG kode soal yang sudah dibekukan bersama paket
   siklus. Sesudahnya, kiriman callback HC Learn tidak bisa dipetakan lagi ke kompetensi
   — persis keadaan yang perubahan ini perbaiki — dan mengisinya ulang hanya mungkin
   selama master binding-nya belum berubah.
*/

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
      AND name = 'IX_PA_CycleQPkgItem_KodeSoal'
)
    DROP INDEX IX_PA_CycleQPkgItem_KodeSoal ON N_PA_Cycle_Question_Package_Item;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
      AND name = 'Kode_Soal_Sumber'
)
    ALTER TABLE N_PA_Cycle_Question_Package_Item DROP COLUMN Kode_Soal_Sumber;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-095';
GO
