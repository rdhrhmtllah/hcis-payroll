-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-126-visitor-tamu-efektif-lengkap
--
-- Proyeksi N_VISITOR_Tamu_Efektif (dibaca aplikasi palang) ikut membawa
-- kolom hasil pemisahan Tamu Regular/B2B (DDL-2026-121, DDL-2026-122).
-- Tipe disamakan persis dengan asalnya di N_EMI_VISITOR_Transaksi_Tamu_Detail.
-- Rentang estimasi dinamai Waktu_Estimasi_Masuk/Keluar (asal Detail:
-- Waktu_Mulai/Selesai_Kunjungan) supaya berpasangan dengan Waktu_Aktual_Masuk/
-- Keluar yang ditulis palang dan tidak tertukar saat dibaca.
-- Kolom lama tidak diubah: palang yang ada tetap berjalan.

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Estimasi_Masuk') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Estimasi_Masuk datetime2(0) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Estimasi_Keluar') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Estimasi_Keluar datetime2(0) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Tamu_Kategori') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Tamu_Kategori varchar(10) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'B2B_Unit') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD B2B_Unit varchar(20) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Path_Foto_Kendaraan') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Path_Foto_Kendaraan varchar(500) NULL;
END
GO
