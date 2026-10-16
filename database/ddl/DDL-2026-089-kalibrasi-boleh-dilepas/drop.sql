/* Change ID: DDL-2026-089-kalibrasi-boleh-dilepas — DROP / pembalikan.
   DOKUMENTASI RESET MANUAL. Dijalankan developer, tidak pernah oleh agent.

   Mengembalikan Kode_Grade_Sesudah dan Performed_By menjadi NOT NULL, dan mencabut
   CK_PA_Calibration_History_Pelaku.

   BACA DULU: pembalikan ini TIDAK SELALU BISA. Sesudah forward.sql berjalan, setiap
   pelepasan kalibrasi menulis satu baris tanpa grade sesudah dan tanpa pelaku, dan NOT NULL
   menolak tabel yang sudah memuatnya. Menghapus baris-baris itu supaya ALTER-nya lolos
   berarti membuang catatan bahwa kalibrasi seseorang pernah dilepas dan kenapa — jadi skrip
   ini BERHENTI dengan pesan alih-alih membersihkan jalannya sendiri.

   Kalau pembalikan memang dikehendaki, nasib baris-baris itu diputuskan manusia lebih dulu,
   di luar skrip ini.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50580, 'DDL-2026-089 drop hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;

DECLARE @adaPelepasan int = (
    SELECT COUNT(*) FROM N_PA_Calibration_History
    WHERE Kode_Grade_Sesudah IS NULL OR Performed_By IS NULL
);

IF @adaPelepasan > 0
    THROW 50582, 'Ada baris pelepasan kalibrasi; pembalikan akan menghapus catatannya. Putuskan nasibnya lebih dulu.', 1;
GO

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name = 'CK_PA_Calibration_History_Pelaku'
)
    ALTER TABLE N_PA_Calibration_History DROP CONSTRAINT CK_PA_Calibration_History_Pelaku;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name = 'Kode_Grade_Sesudah' AND is_nullable = 1
)
    ALTER TABLE N_PA_Calibration_History ALTER COLUMN Kode_Grade_Sesudah VARCHAR(40) NOT NULL;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name = 'Performed_By' AND is_nullable = 1
)
    ALTER TABLE N_PA_Calibration_History ALTER COLUMN Performed_By INT NOT NULL;
GO
