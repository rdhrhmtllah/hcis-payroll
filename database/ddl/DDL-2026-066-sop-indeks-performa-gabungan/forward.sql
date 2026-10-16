/* DDL-2026-066 — indeks performa modul SOP (GABUNGAN)
   Target: Web_HRIS

   Menggantikan DDL-2026-063, 064, dan 065 sebagai SATU rekaman SQL Tracker.
   Isinya identik dengan gabungan ketiganya, tanpa satu pun pernyataan diubah.
   Setiap pernyataan digerbangi IF NOT EXISTS sehingga aman dijalankan ulang.
*/

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Rwt_Waktu')
CREATE NONCLUSTERED INDEX IX_N_SOP_Rwt_Waktu
    ON N_SOP_Kepatuhan_Riwayat (Waktu_Peristiwa)
    INCLUDE (Peristiwa, Id_SOP_Paket, Kode_Karyawan, Id_SOP_Penjadwalan_Detail);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Rwt_Detail_Peristiwa')
CREATE NONCLUSTERED INDEX IX_N_SOP_Rwt_Detail_Peristiwa
    ON N_SOP_Kepatuhan_Riwayat (Id_SOP_Penjadwalan_Detail, Peristiwa);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_PjdD_Karyawan_Aktif')
CREATE NONCLUSTERED INDEX IX_N_SOP_PjdD_Karyawan_Aktif
    ON N_SOP_Penjadwalan_Detail (Kode_Karyawan, Flag_Aktif, Flag_Cabut_Akses)
    INCLUDE (Id_SOP_Penjadwalan, Status_Paket, Tanggal_Selesai, Created_At, Updated_At);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Detail_Status')
CREATE NONCLUSTERED INDEX IX_N_SOP_Prg_Detail_Status
    ON N_SOP_Progress (Id_SOP_Penjadwalan_Detail, Status)
    INCLUDE (Id_SOP_Paket_Item, Id_SOP_Item_Versi, Waktu_Buka, Waktu_Konfirmasi);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Paket_Publish')
CREATE NONCLUSTERED INDEX IX_N_SOP_Paket_Publish
    ON N_SOP_Master_Paket (Tanggal_Publish);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Waktu_Buka')
CREATE NONCLUSTERED INDEX IX_N_SOP_Prg_Waktu_Buka
    ON N_SOP_Progress (Waktu_Buka)
    INCLUDE (Id_SOP_Paket_Item, Kode_Karyawan, Durasi_Aktif_Detik)
    WHERE Waktu_Buka IS NOT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Item_Status')
CREATE NONCLUSTERED INDEX IX_N_SOP_Prg_Item_Status
    ON N_SOP_Progress (Id_SOP_Paket_Item, Status)
    INCLUDE (Id_SOP_Item_Versi, Kode_Karyawan);
GO

