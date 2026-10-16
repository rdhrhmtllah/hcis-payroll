-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-100-visitor-daftar-anggota-tamu
--
-- Menambahkan manifes JSON seluruh rombongan, termasuk tamu utama, ke transaksi
-- tamu dan proyeksi yang ditarik aplikasi gerbang. NULL dipertahankan untuk
-- baris lama karena nama anggota lainnya tidak pernah dikumpulkan.
--
-- Seluruh pernyataan idempoten.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Daftar_Anggota_Json nvarchar(max) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json'
)
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD CONSTRAINT CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json
            CHECK (Daftar_Anggota_Json IS NULL OR ISJSON(Daftar_Anggota_Json) = 1);
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Daftar_Anggota_Json nvarchar(max) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json'
)
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif
        ADD CONSTRAINT CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json
            CHECK (Daftar_Anggota_Json IS NULL OR ISJSON(Daftar_Anggota_Json) = 1);
END
GO
