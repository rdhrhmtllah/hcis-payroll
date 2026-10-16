/* DDL-2026-066 — pembatalan. ALAT BANTU MANUAL, JANGAN DIJALANKAN AGEN.

   Menghapus ketujuh indeks tidak menghilangkan data, tetapi mengembalikan
   kueri yang dilayaninya ke bentuk pindaian penuh.
*/

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Rwt_Waktu')
DROP INDEX IX_N_SOP_Rwt_Waktu ON N_SOP_Kepatuhan_Riwayat;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Rwt_Detail_Peristiwa')
DROP INDEX IX_N_SOP_Rwt_Detail_Peristiwa ON N_SOP_Kepatuhan_Riwayat;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_PjdD_Karyawan_Aktif')
DROP INDEX IX_N_SOP_PjdD_Karyawan_Aktif ON N_SOP_Penjadwalan_Detail;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Detail_Status')
DROP INDEX IX_N_SOP_Prg_Detail_Status ON N_SOP_Progress;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Paket_Publish')
DROP INDEX IX_N_SOP_Paket_Publish ON N_SOP_Master_Paket;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Waktu_Buka')
DROP INDEX IX_N_SOP_Prg_Waktu_Buka ON N_SOP_Progress;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Item_Status')
DROP INDEX IX_N_SOP_Prg_Item_Status ON N_SOP_Progress;
GO

/* ------------------------------------------------------------------------
   INDEKS LAMA YANG TERGANTIKAN — keputusan manusia, bukan bagian pembatalan.

   IX_N_SOP_PjdD_Karyawan (Kode_Karyawan, Status_Paket) tergantikan
   IX_N_SOP_PjdD_Karyawan_Aktif; IX_N_SOP_Prg_Item (Id_SOP_Paket_Item)
   menjadi awalan IX_N_SOP_Prg_Item_Status. Keduanya TIDAK dihapus otomatis:
   amati sys.dm_db_index_usage_stats setelah beban nyata, hapus hanya bila
   user_seeks/user_scans-nya benar-benar nol.

   DROP INDEX IX_N_SOP_PjdD_Karyawan ON N_SOP_Penjadwalan_Detail;
   DROP INDEX IX_N_SOP_Prg_Item ON N_SOP_Progress;
   ------------------------------------------------------------------------ */
