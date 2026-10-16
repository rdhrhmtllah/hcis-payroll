/* =====================================================================
   DDL-2026-118-rekap-skema-production -- ROLLBACK

   BERKAS INI TIDAK BOLEH DIJALANKAN OLEH AGENT.
   Hanya alat bantu pembersihan/reset manual oleh operator manusia.

   PERINGATAN 1: DROP COLUMN MENGHAPUS DATA SECARA PERMANEN. Begitu
   aplikasi mulai menulis ke kolom-kolom ini, menjalankan berkas ini
   membuang isinya tanpa bisa dikembalikan. Ambil backup lebih dulu.

   PERINGATAN 2: forward.sql merangkum SEMBILAN DDL. Membatalkan lewat
   berkas ini berarti membatalkan kesembilannya sekaligus - modul SOP,
   Visitor B2B, permit coklat, kategori permit, hibah role RBAC, dan
   cakupan lokasi Pengumpulan Data. Pastikan itu memang yang dimaksud.

   PERINGATAN 3: ketiga indeks yang di forward.sql dibuat ulang akan
   dikembalikan ke DEFINISI LAMA-nya (dua kunci, tanpa filter). Itu berarti
   perlindungan idempotency per-aplikasi hilang lagi.

   Urutan DIBALIK dari forward.sql: indeks dan constraint dibuang lebih
   dulu, baru kolomnya. Kolom yang masih dirujuk indeks atau constraint
   tidak bisa dibuang.
   ===================================================================== */

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

PRINT '=== ROLLBACK DDL-2026-118 pada database: ' + DB_NAME() + ' ===';
GO

/* ---- 1. Indeks yang dibuat forward.sql ---- */

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
    DROP INDEX IX_VISITOR_Permit_Pemohon_Sumber_Tamu ON dbo.N_EMI_VISITOR_Transaksi_Permit;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Transaksi_Tamu')
    DROP INDEX UX_VISITOR_Permit_Transaksi_Tamu ON dbo.N_EMI_VISITOR_Transaksi_Permit;
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Tamu_Api_Aplikasi')
    DROP INDEX IX_VISITOR_Tamu_Api_Aplikasi ON dbo.N_EMI_VISITOR_Transaksi_Tamu;
GO

/* ---- 2. Tiga indeks yang dibuat ulang: kembalikan ke definisi LAMA ---- */

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
    DROP INDEX UX_VISITOR_Tamu_Requester_Idempotency ON dbo.N_EMI_VISITOR_Transaksi_Tamu;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
    CREATE UNIQUE INDEX UX_VISITOR_Tamu_Requester_Idempotency
        ON dbo.N_EMI_VISITOR_Transaksi_Tamu (Kode_Karyawan_PIC, Idempotency_Key);
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Api_Aplikasi')
    DROP INDEX IX_VISITOR_Permit_Api_Aplikasi ON dbo.N_EMI_VISITOR_Transaksi_Permit;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Api_Aplikasi')
    CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Api_Aplikasi
        ON dbo.N_EMI_VISITOR_Transaksi_Permit (Id_Api_Aplikasi, Flag_Approval)
        INCLUDE (Flag_Aktif, No_Transaksi);
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
    DROP INDEX UX_VISITOR_Permit_Requester_Idempotency ON dbo.N_EMI_VISITOR_Transaksi_Permit;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
    CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
        ON dbo.N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Idempotency_Key);
GO

/* ---- 3. Constraint ---- */

IF OBJECT_ID('dbo.CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role', 'C') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Grup_Fitur_Map DROP CONSTRAINT [CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role];
GO
IF OBJECT_ID('dbo.DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role', 'D') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Grup_Fitur_Map DROP CONSTRAINT [DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role];
GO
IF OBJECT_ID('dbo.CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu', 'C') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif DROP CONSTRAINT [CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu];
GO
IF OBJECT_ID('dbo.DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu', 'D') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif DROP CONSTRAINT [DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu];
GO
IF OBJECT_ID('dbo.CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu', 'C') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail DROP CONSTRAINT [CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu];
GO
IF OBJECT_ID('dbo.DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu', 'D') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail DROP CONSTRAINT [DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu];
GO
IF OBJECT_ID('dbo.CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json', 'C') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif DROP CONSTRAINT [CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json];
GO
IF OBJECT_ID('dbo.CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json', 'C') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail DROP CONSTRAINT [CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json];
GO
IF OBJECT_ID('dbo.FK_N_SOP_Pjd_Rule', 'F') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Penjadwalan DROP CONSTRAINT [FK_N_SOP_Pjd_Rule];
GO

/* ---- 4. Kolom, kebalikan Bagian 10 -> 1 ---- */

IF COL_LENGTH('dbo.N_HRIS_Pengumpulan_Data_Config', 'Cakupan_Lokasi') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Pengumpulan_Data_Config DROP COLUMN [Cakupan_Lokasi];
GO
IF COL_LENGTH('dbo.N_HRIS_Grup_Fitur_Map', 'Flag_Hibah_Role') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Grup_Fitur_Map DROP COLUMN [Flag_Hibah_Role];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Master_Kategori_Permit', 'Flag_Muncul_Pengajuan') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Master_Kategori_Permit DROP COLUMN [Flag_Muncul_Pengajuan];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail DROP COLUMN [Jenis_Kendaraan_Tamu];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'No_Transaksi_Tamu') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit DROP COLUMN [No_Transaksi_Tamu];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Referensi_Mitra') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu DROP COLUMN [Referensi_Mitra];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Id_Api_Aplikasi') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu DROP COLUMN [Id_Api_Aplikasi];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Sumber_Pengajuan') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu DROP COLUMN [Sumber_Pengajuan];
GO
IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Tipe_Tamu') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif DROP COLUMN [Tipe_Tamu];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN [Tipe_Tamu];
GO
IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif DROP COLUMN [Daftar_Anggota_Json];
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json') IS NOT NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN [Daftar_Anggota_Json];
GO

/* Kolom Bagian 3 selain Kode_Karyawan_PIC SUDAH ADA di production sebelum
   DDL-2026-118, jadi membuangnya BUKAN rollback - itu merusak keadaan
   sebelumnya. Sengaja hanya Kode_Karyawan_PIC yang dibuang di sini. */
IF COL_LENGTH('dbo.N_HRIS_Api_Aplikasi', 'Kode_Karyawan_PIC') IS NOT NULL
    ALTER TABLE dbo.N_HRIS_Api_Aplikasi DROP COLUMN [Kode_Karyawan_PIC];
GO

IF COL_LENGTH('dbo.N_SOP_Absensi', 'IP_Address') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Absensi DROP COLUMN [IP_Address];
GO
IF COL_LENGTH('dbo.N_SOP_Absensi', 'User_Agent') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Absensi DROP COLUMN [User_Agent];
GO
IF COL_LENGTH('dbo.N_SOP_Absensi', 'Akurasi_Meter') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Absensi DROP COLUMN [Akurasi_Meter];
GO
IF COL_LENGTH('dbo.N_SOP_Progress', 'Id_Versi_Asal') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Progress DROP COLUMN [Id_Versi_Asal];
GO
IF COL_LENGTH('dbo.N_SOP_Progress', 'Id_Ujian_Token_Hasil') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Progress DROP COLUMN [Id_Ujian_Token_Hasil];
GO
IF COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Id_SOP_Assignment_Rule') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Penjadwalan DROP COLUMN [Id_SOP_Assignment_Rule];
GO
IF COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Keterangan') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Penjadwalan DROP COLUMN [Keterangan];
GO
IF COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Nama_Batch') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Penjadwalan DROP COLUMN [Nama_Batch];
GO
IF COL_LENGTH('dbo.N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Master_Paket DROP COLUMN [Id_SOP_Paket_Versi_Aktif];
GO
IF COL_LENGTH('dbo.N_SOP_Master_Paket', 'Versi_Paket_Aktif') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Master_Paket DROP COLUMN [Versi_Paket_Aktif];
GO
