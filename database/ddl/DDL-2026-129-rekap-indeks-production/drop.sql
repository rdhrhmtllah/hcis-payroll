-- database/ddl/DDL-2026-129-rekap-indeks-production/drop.sql
-- PENDAMPING ROLLBACK — JANGAN DIJALANKAN OTOMATIS.
--
-- Berkas ini ada karena setiap change wajib punya pasangan pembatalnya, bukan
-- karena pembatalan itu diharapkan. forward.sql hanya MENAMBAH objek, jadi
-- membiarkannya berdiri tanpa dipakai tidak merusak apa pun.
--
-- TIDAK ADA DATA YANG HILANG: indeks tidak menyimpan baris, hanya rencana kueri
--
-- yang kembali ke keadaan sebelumnya. Tiga DROP INDEX di forward.sql (bawaan
-- DDL-2026-118) TIDAK dibatalkan: indeks yang digantikannya memang kedaluwarsa.

SET NOCOUNT ON;
GO

/* ── BAGIAN 3 — indeks baru ────────────────────────────────────────────── */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_SOP_Dokumen_Jenis' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_SOP_Dokumen_Jenis ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_SOP_Dokumen_Pemilik' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_SOP_Dokumen_Pemilik ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_SOP_Dokumen_Entitas' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_SOP_Dokumen_Entitas ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_SOP_Dokumen_Lingkup' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_SOP_Dokumen_Lingkup ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_SOP_Dokumen_APQC' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_SOP_Dokumen_APQC ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_SOP_Dokumen_Departemen' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_SOP_Dokumen_Departemen ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_SOP_Dokumen_Nonaktif_At' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_N_SOP_Dokumen_Nonaktif_At ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_SOP_Dokumen_Tipe' AND object_id=OBJECT_ID('dbo.N_SOP_Dokumen')) DROP INDEX IX_N_SOP_Dokumen_Tipe ON dbo.N_SOP_Dokumen;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='UQ_N_SOP_Assignment_Rule_Paket' AND object_id=OBJECT_ID('dbo.N_SOP_Assignment_Rule')) DROP INDEX UQ_N_SOP_Assignment_Rule_Paket ON dbo.N_SOP_Assignment_Rule;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='UQ_NHRIS_RMA_PageAksi_Aktif' AND object_id=OBJECT_ID('dbo.N_HRIS_Role_Menu_Access')) DROP INDEX UQ_NHRIS_RMA_PageAksi_Aktif ON dbo.N_HRIS_Role_Menu_Access;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_Transaksi_Cuti_Det_NoTransaksi' AND object_id=OBJECT_ID('dbo.Transaksi_Cuti_Det')) DROP INDEX IX_Transaksi_Cuti_Det_NoTransaksi ON dbo.Transaksi_Cuti_Det;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_Transaksi_Cuti_Det_Karyawan_Tanggal' AND object_id=OBJECT_ID('dbo.Transaksi_Cuti_Det')) DROP INDEX IX_Transaksi_Cuti_Det_Karyawan_Tanggal ON dbo.Transaksi_Cuti_Det;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_HRIS_Transaksi_Shift_Det_Induk' AND object_id=OBJECT_ID('dbo.N_HRIS_Transaksi_Shift_Det')) DROP INDEX IX_N_HRIS_Transaksi_Shift_Det_Induk ON dbo.N_HRIS_Transaksi_Shift_Det;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_HRIS_Transaksi_Shift_Det_NoTransaksi' AND object_id=OBJECT_ID('dbo.N_HRIS_Transaksi_Shift_Det')) DROP INDEX IX_N_HRIS_Transaksi_Shift_Det_NoTransaksi ON dbo.N_HRIS_Transaksi_Shift_Det;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_HRIS_Page_Access_User_Aktif' AND object_id=OBJECT_ID('dbo.HRIS_Page_Access')) DROP INDEX IX_HRIS_Page_Access_User_Aktif ON dbo.HRIS_Page_Access;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_HRIS_Page_Access_Halaman_Aktif' AND object_id=OBJECT_ID('dbo.HRIS_Page_Access')) DROP INDEX IX_HRIS_Page_Access_Halaman_Aktif ON dbo.HRIS_Page_Access;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_HRIS_Grup_Fitur_Map_Grup_Aktif' AND object_id=OBJECT_ID('dbo.N_HRIS_Grup_Fitur_Map')) DROP INDEX IX_N_HRIS_Grup_Fitur_Map_Grup_Aktif ON dbo.N_HRIS_Grup_Fitur_Map;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_HRIS_Grup_Fitur_Map_Halaman_Aktif' AND object_id=OBJECT_ID('dbo.N_HRIS_Grup_Fitur_Map')) DROP INDEX IX_N_HRIS_Grup_Fitur_Map_Halaman_Aktif ON dbo.N_HRIS_Grup_Fitur_Map;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_HRIS_Karyawan_Team_Karyawan' AND object_id=OBJECT_ID('dbo.HRIS_Karyawan_Team')) DROP INDEX IX_HRIS_Karyawan_Team_Karyawan ON dbo.HRIS_Karyawan_Team;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_HRIS_Karyawan_Team_Tim' AND object_id=OBJECT_ID('dbo.HRIS_Karyawan_Team')) DROP INDEX IX_HRIS_Karyawan_Team_Tim ON dbo.HRIS_Karyawan_Team;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_HRIS_Request_Diagnostics_Created_Penutup' AND object_id=OBJECT_ID('dbo.N_HRIS_Request_Diagnostics')) DROP INDEX IX_N_HRIS_Request_Diagnostics_Created_Penutup ON dbo.N_HRIS_Request_Diagnostics;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_HRIS_Request_Diagnostics_Id' AND object_id=OBJECT_ID('dbo.N_HRIS_Request_Diagnostics')) DROP INDEX IX_N_HRIS_Request_Diagnostics_Id ON dbo.N_HRIS_Request_Diagnostics;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_N_LMS_Api_Nonces_Nonce' AND object_id=OBJECT_ID('dbo.N_LMS_Api_Nonces')) DROP INDEX IX_N_LMS_Api_Nonces_Nonce ON dbo.N_LMS_Api_Nonces;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VISITOR_Tamu_Api_Aplikasi' AND object_id=OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Tamu')) DROP INDEX IX_VISITOR_Tamu_Api_Aplikasi ON dbo.N_EMI_VISITOR_Transaksi_Tamu;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_VISITOR_Permit_Transaksi_Tamu' AND object_id=OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Permit')) DROP INDEX UX_VISITOR_Permit_Transaksi_Tamu ON dbo.N_EMI_VISITOR_Transaksi_Permit;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_VISITOR_Permit_Pemohon_Sumber_Tamu' AND object_id=OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Permit')) DROP INDEX IX_VISITOR_Permit_Pemohon_Sumber_Tamu ON dbo.N_EMI_VISITOR_Transaksi_Permit;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_VISITOR_Permit_Kode_Kartu' AND object_id=OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Permit')) DROP INDEX UX_VISITOR_Permit_Kode_Kartu ON dbo.N_EMI_VISITOR_Transaksi_Permit;
GO
