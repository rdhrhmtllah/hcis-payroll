/* Change ID: DDL-2026-154-sinkron-prod-tabel-kolom — DROP/RESET MANUAL.
   JANGAN dijalankan oleh agen. Hanya untuk membatalkan forward.sql di
   lingkungan yang BELUM berisi data penting. Menghapus data tabel baru. */

IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'User_Bypass_CheckOut') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] DROP COLUMN [User_Bypass_CheckOut];
IF OBJECT_ID(N'dbo.DF_VISITOR_Tamu_Efektif_Flag_ByPass_CheckOut', N'D') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] DROP CONSTRAINT [DF_VISITOR_Tamu_Efektif_Flag_ByPass_CheckOut];
IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'Flag_ByPass_CheckOut') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] DROP COLUMN [Flag_ByPass_CheckOut];
IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'User_Bypass_CheckIn') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] DROP COLUMN [User_Bypass_CheckIn];
IF OBJECT_ID(N'dbo.DF_VISITOR_Tamu_Efektif_Flag_ByPass_CheckIn', N'D') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] DROP CONSTRAINT [DF_VISITOR_Tamu_Efektif_Flag_ByPass_CheckIn];
IF COL_LENGTH(N'dbo.N_VISITOR_Tamu_Efektif', N'Flag_ByPass_CheckIn') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Tamu_Efektif] DROP COLUMN [Flag_ByPass_CheckIn];
IF COL_LENGTH(N'dbo.N_VISITOR_Permit_Efektif', N'NFC_Inbio') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP COLUMN [NFC_Inbio];
IF OBJECT_ID(N'dbo.DF_VISITOR_Permit_Efektif_Jenis_Akses', N'D') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP CONSTRAINT [DF_VISITOR_Permit_Efektif_Jenis_Akses];
IF COL_LENGTH(N'dbo.N_VISITOR_Permit_Efektif', N'Jenis_Akses') IS NOT NULL ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP COLUMN [Jenis_Akses];
IF COL_LENGTH(N'dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', N'Nama_Penanggung_Jawab') IS NOT NULL ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail] DROP COLUMN [Nama_Penanggung_Jawab];
IF COL_LENGTH(N'dbo.HRIS_Hak_Cuti_Tambahan', N'Status') IS NOT NULL ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] DROP COLUMN [Status];
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Versi_Aktif', N'F') IS NOT NULL ALTER TABLE dbo.[N_HRIS_Panduan] DROP CONSTRAINT [FK_HRIS_Panduan_Versi_Aktif];
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Versi_Panduan', N'F') IS NOT NULL ALTER TABLE dbo.[N_HRIS_Panduan_Versi] DROP CONSTRAINT [FK_HRIS_Panduan_Versi_Panduan];
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Halaman_Panduan', N'F') IS NOT NULL ALTER TABLE dbo.[N_HRIS_Panduan_Halaman] DROP CONSTRAINT [FK_HRIS_Panduan_Halaman_Panduan];
IF OBJECT_ID(N'dbo.FK_HRIS_Panduan_Role_Panduan', N'F') IS NOT NULL ALTER TABLE dbo.[N_HRIS_Panduan_Role] DROP CONSTRAINT [FK_HRIS_Panduan_Role_Panduan];
IF OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi', N'U') IS NOT NULL DROP TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Koreksi];
IF OBJECT_ID(N'dbo.N_HRIS_Master_Kontak_Bantuan', N'U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Master_Kontak_Bantuan];
IF OBJECT_ID(N'dbo.N_HRIS_Panduan_Role', N'U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Panduan_Role];
IF OBJECT_ID(N'dbo.N_HRIS_Panduan_Halaman', N'U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Panduan_Halaman];
IF OBJECT_ID(N'dbo.N_HRIS_Panduan_Versi', N'U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Panduan_Versi];
IF OBJECT_ID(N'dbo.N_HRIS_Panduan', N'U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Panduan];
