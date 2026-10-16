/* Change ID: DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi — PEMBATALAN.

   TIDAK DIJALANKAN AGEN. Berkas ini hanya jalan pulang bila perubahannya harus dicabut,
   dan pencabutannya dilakukan manusia lewat SQL Tracker.

   PERHATIAN: mencabut `Nilai_Ambang` MEMBUANG seluruh KKM yang sudah ditetapkan HC.
   Angka itu tidak tersimpan di tempat lain mana pun — ia tidak ikut disalin ke siklus
   dan tidak ikut tercatat pada hasil — sehingga sesudah dicabut, satu-satunya cara
   mengembalikannya adalah mengetiknya ulang satu per satu.

   Mencabut `Flag_Radar` mengembalikan radar ke perilaku lama: SETIAP kompetensi menjadi
   sumbu lagi, termasuk butir kualitatif yang tidak punya angka.
*/

IF EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'DF_PA_Competency_Radar'
)
    ALTER TABLE N_PA_Competency DROP CONSTRAINT DF_PA_Competency_Radar;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'Flag_Radar'
)
    ALTER TABLE N_PA_Competency DROP COLUMN Flag_Radar;
GO

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'CK_PA_Competency_Ambang'
)
    ALTER TABLE N_PA_Competency DROP CONSTRAINT CK_PA_Competency_Ambang;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'Nilai_Ambang'
)
    ALTER TABLE N_PA_Competency DROP COLUMN Nilai_Ambang;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-096';
GO
