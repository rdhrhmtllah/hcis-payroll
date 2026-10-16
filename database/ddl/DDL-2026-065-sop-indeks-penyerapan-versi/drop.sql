/* DDL-2026-065 — pembatalan. ALAT BANTU MANUAL, JANGAN DIJALANKAN AGEN. */

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Item_Status')
DROP INDEX IX_N_SOP_Prg_Item_Status ON N_SOP_Progress;
GO

/* ------------------------------------------------------------------------
   INDEKS LAMA YANG MENJADI BERLEBIHAN — keputusan manusia.

   IX_N_SOP_Prg_Item (Id_SOP_Paket_Item) menjadi awalan dari indeks baru di
   atas, sehingga secara teori tergantikan. TIDAK dihapus otomatis: ia lebih
   ramping dan mungkin masih dipilih pengoptimal untuk kueri lain. Amati
   sys.dm_db_index_usage_stats setelah beban nyata sebelum memutuskan.

   DROP INDEX IX_N_SOP_Prg_Item ON N_SOP_Progress;
   ------------------------------------------------------------------------ */
