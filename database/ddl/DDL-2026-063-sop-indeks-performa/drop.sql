/* DDL-2026-063 — pembatalan. ALAT BANTU MANUAL, JANGAN DIJALANKAN AGEN.

   Berkas ini ada untuk memulihkan keadaan bila indeks di forward.sql ternyata
   merugikan. Menghapus indeks tidak menghilangkan data, tetapi mengembalikan
   kueri yang dilayaninya ke bentuk scan. */

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

/* ------------------------------------------------------------------------
   INDEKS LAMA YANG TERGANTIKAN — keputusan manusia, bukan bagian pembatalan.

   IX_N_SOP_PjdD_Karyawan (Kode_Karyawan, Status_Paket) pada
   N_SOP_Penjadwalan_Detail tergantikan oleh IX_N_SOP_PjdD_Karyawan_Aktif,
   yang memimpin dengan kolom yang sama lalu menambahkan kedua flag yang
   SELALU ikut disaring di jalur tugasSaya.

   TIDAK dihapus otomatis, dan sengaja: indeks lama mungkin masih melayani
   kueri lain yang belum terlihat dari audit ini. Amati dulu lewat
   sys.dm_db_index_usage_stats sesudah beban nyata berjalan; hapus hanya bila
   user_seeks/user_scans-nya benar-benar nol.

   DROP INDEX IX_N_SOP_PjdD_Karyawan ON N_SOP_Penjadwalan_Detail;
   ------------------------------------------------------------------------ */
