-- DDL-2026-088-indeks-heap-dipindai-berat — PEMBATALAN
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- Membuang kembali kesepuluh indeks yang dibuat forward.sql. Tidak ada baris
-- data yang tersentuh: indeks non-klaster adalah struktur turunan, dan
-- membuangnya mengembalikan tabel ke bentuk semula persis.
--
-- Skrip ini idempoten: menjalankannya dua kali tidak menimbulkan galat.

IF DB_NAME() NOT IN ('Web_HRIS')
BEGIN
    RAISERROR('Skrip ini hanya untuk Web_HRIS. Basis data aktif: %s', 16, 1, DB_NAME());
    SET NOEXEC ON;
END
GO

DROP INDEX IF EXISTS IX_Transaksi_Cuti_Det_NoTransaksi ON dbo.Transaksi_Cuti_Det;
GO
DROP INDEX IF EXISTS IX_Transaksi_Cuti_Det_Karyawan_Tanggal ON dbo.Transaksi_Cuti_Det;
GO
DROP INDEX IF EXISTS IX_N_HRIS_Transaksi_Shift_Det_Induk ON dbo.N_HRIS_Transaksi_Shift_Det;
GO
DROP INDEX IF EXISTS IX_N_HRIS_Transaksi_Shift_Det_NoTransaksi ON dbo.N_HRIS_Transaksi_Shift_Det;
GO
DROP INDEX IF EXISTS IX_HRIS_Page_Access_User_Aktif ON dbo.HRIS_Page_Access;
GO
DROP INDEX IF EXISTS IX_HRIS_Page_Access_Halaman_Aktif ON dbo.HRIS_Page_Access;
GO
DROP INDEX IF EXISTS IX_N_HRIS_Grup_Fitur_Map_Grup_Aktif ON dbo.N_HRIS_Grup_Fitur_Map;
GO
DROP INDEX IF EXISTS IX_N_HRIS_Grup_Fitur_Map_Halaman_Aktif ON dbo.N_HRIS_Grup_Fitur_Map;
GO
DROP INDEX IF EXISTS IX_HRIS_Karyawan_Team_Karyawan ON dbo.HRIS_Karyawan_Team;
GO
DROP INDEX IF EXISTS IX_HRIS_Karyawan_Team_Tim ON dbo.HRIS_Karyawan_Team;
GO

SET NOEXEC OFF;
GO
