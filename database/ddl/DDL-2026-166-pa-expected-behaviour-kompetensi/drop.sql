/* Change ID: DDL-2026-166-pa-expected-behaviour-kompetensi — PEMBATALAN.

   TIDAK DIJALANKAN AGEN. Berkas ini hanya jalan pulang bila perubahannya harus dicabut,
   dan pencabutannya dilakukan manusia lewat SQL Tracker.

   PERHATIAN: mencabut `Expected_Behaviour` MEMBUANG seluruh perilaku yang sudah ditulis
   HC. Isinya tidak tersimpan di tempat lain; sesudah dicabut harus diketik ulang.
*/

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'Expected_Behaviour'
)
    ALTER TABLE N_PA_Competency DROP COLUMN Expected_Behaviour;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-166';
GO
