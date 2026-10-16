-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Change ID: DDL-2026-027-index-visitor-kehadiran
--
-- Menghapus kelima indeks yang dibuat forward.sql. Tidak ada data yang hilang:
-- indeks adalah struktur baca, bukan penyimpan fakta.

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu')
      AND name = 'IX_VISITOR_Tamu_Tanggal_Kunjungan'
)
    DROP INDEX IX_VISITOR_Tamu_Tanggal_Kunjungan ON N_EMI_VISITOR_Transaksi_Tamu;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_VISITOR_Tamu_Efektif')
      AND name = 'IX_VISITOR_Tamu_Efektif_Kehadiran'
)
    DROP INDEX IX_VISITOR_Tamu_Efektif_Kehadiran ON N_VISITOR_Tamu_Efektif;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu_Detail')
      AND name = 'IX_VISITOR_Tamu_Detail_Kehadiran'
)
    DROP INDEX IX_VISITOR_Tamu_Detail_Kehadiran ON N_EMI_VISITOR_Transaksi_Tamu_Detail;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit_Detail')
      AND name = 'IX_VISITOR_Permit_Detail_Kadaluarsa'
)
    DROP INDEX IX_VISITOR_Permit_Detail_Kadaluarsa ON N_EMI_VISITOR_Transaksi_Permit_Detail;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_VISITOR_Permit_Efektif')
      AND name = 'IX_VISITOR_Permit_Efektif_Status_Kadaluarsa'
)
    DROP INDEX IX_VISITOR_Permit_Efektif_Status_Kadaluarsa ON N_VISITOR_Permit_Efektif;
GO
