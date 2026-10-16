/* DDL-2026-170-pa-salinan-kompetensi-siklus — bantuan reset MANUAL. Agen tidak menjalankan berkas ini.
   PERHATIAN: mencabut tabel ini membuat siklus kembali membaca atribut kompetensi dari master. */
IF OBJECT_ID('N_PA_Cycle_Competency_Snapshot', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Competency_Snapshot;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-170';
GO
