/*
| Pembalik DDL-2026-091.
|
| Membuang tabel N_PA_Level_Exclusion berikut kedua indeksnya.
|
| YANG PERLU DIKETAHUI SEBELUM MENJALANKANNYA: kode yang membaca tabel ini
| (`PaLevelDikecualikan`) akan kehilangan sumbernya. Membalik DDL ini TANPA
| mengembalikan konstanta `LEVEL_FREELANCE` di ketiga service berarti FREELANCE — dan level
| lain yang sudah terlanjur dikecualikan admin — mendadak ikut dinilai pada penyiapan siklus
| berikutnya. Jadi pembalikan ini hanya masuk akal bersama pembalikan kodenya, bukan
| sendirian.
|
| Isi tabelnya TIDAK dicadangkan ke mana pun oleh skrip ini. Bila sudah ada pengecualian
| yang dibuat admin, catat dulu ID_Level dan Alasan-nya sebelum menjalankan ini — daftar itu
| satu-satunya tempat jawabannya tersimpan.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Level_Exclusion') AND name = 'IX_PA_LevelExcl_Aktif'
)
    DROP INDEX IX_PA_LevelExcl_Aktif ON N_PA_Level_Exclusion;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Level_Exclusion') AND name = 'UX_PA_LevelExcl_Level'
)
    DROP INDEX UX_PA_LevelExcl_Level ON N_PA_Level_Exclusion;

IF OBJECT_ID('N_PA_Level_Exclusion', 'U') IS NOT NULL
    DROP TABLE N_PA_Level_Exclusion;

DELETE FROM N_SYSTEM_Database_Change
WHERE Kode_Change = 'DDL-2026-091-pa-level-dikecualikan';

COMMIT TRANSACTION;
GO
