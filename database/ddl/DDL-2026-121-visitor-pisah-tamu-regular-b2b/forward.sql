-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-121-visitor-pisah-tamu-regular-b2b

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tamu_Kategori') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Tamu_Kategori varchar(10) NULL;
END
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Path_Foto_Kendaraan') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Path_Foto_Kendaraan varchar(500) NULL;
END
GO
