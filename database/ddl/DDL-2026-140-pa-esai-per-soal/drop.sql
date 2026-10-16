/* Change ID: DDL-2026-140-pa-esai-per-soal — PEMBATALAN.

   TIDAK DIJALANKAN AGEN. Jalan pulang bila perubahannya harus dicabut, dilakukan manusia
   lewat SQL Tracker.

   PERHATIAN: pertanyaan dan jawaban esai per soal ikut terbuang. Isi aslinya masih ada di
   N_PA_Assignment_Payload.Payload_Json dan bisa dipetakan ulang.
*/

IF OBJECT_ID('N_PA_Assignment_Score_Esai', 'U') IS NOT NULL
    DROP TABLE N_PA_Assignment_Score_Esai;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-140';
GO
