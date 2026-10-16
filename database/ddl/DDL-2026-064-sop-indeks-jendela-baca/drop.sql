/* DDL-2026-064 — pembatalan. ALAT BANTU MANUAL, JANGAN DIJALANKAN AGEN. */

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Waktu_Buka')
DROP INDEX IX_N_SOP_Prg_Waktu_Buka ON N_SOP_Progress;
GO
