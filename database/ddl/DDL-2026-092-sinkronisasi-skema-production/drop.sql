-- database/ddl/DDL-2026-092-sinkronisasi-skema-production/drop.sql
--
-- ALAT BANTU MANUAL. Jangan dijalankan sebagai bagian rilis.
--
-- Berkas ini membalik forward.sql untuk keperluan reset di DEVELOPMENT. Isinya
-- DROP, jadi menjalankannya di production akan MENGHAPUS objek beserta datanya.
-- Kolom yang di-drop akan menghilangkan isinya secara permanen.
--
-- Urutannya kebalikan forward.sql: foreign key -> trigger -> default -> indeks
-- -> kolom -> tabel.

SET NOCOUNT ON;
GO

/* --- 1. foreign key --- */
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_N_SOP_Dokumen_Versi_Aktif')
    ALTER TABLE dbo.[N_SOP_Dokumen] DROP CONSTRAINT [FK_N_SOP_Dokumen_Versi_Aktif];
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_N_SOP_Item_Versi_Pengesah_Versi')
    ALTER TABLE dbo.[N_SOP_Item_Versi_Pengesah] DROP CONSTRAINT [FK_N_SOP_Item_Versi_Pengesah_Versi];
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_Approval_Request_Detail_Request')
    ALTER TABLE dbo.[HRIS_Approval_Request_Detail] DROP CONSTRAINT [FK_Approval_Request_Detail_Request];
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_Approval_Request_Detail_Jenis')
    ALTER TABLE dbo.[HRIS_Approval_Request_Detail] DROP CONSTRAINT [FK_Approval_Request_Detail_Jenis];
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_MenuCap_Page')
    ALTER TABLE dbo.[N_HRIS_Menu_Capabilities] DROP CONSTRAINT [FK_MenuCap_Page];
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_MenuCap_Aksi')
    ALTER TABLE dbo.[N_HRIS_Menu_Capabilities] DROP CONSTRAINT [FK_MenuCap_Aksi];
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_SOP_Review_Versi')
    ALTER TABLE dbo.[N_SOP_Item_Versi_Review] DROP CONSTRAINT [FK_SOP_Review_Versi];
GO

/* --- 2. trigger --- */

/* --- 3. default constraint --- */
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = N'DF_Karyawan_Aktif')
    ALTER TABLE dbo.[Karyawan] DROP CONSTRAINT [DF_Karyawan_Aktif];
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = N'DF_Karyawan_Email')
    ALTER TABLE dbo.[Karyawan] DROP CONSTRAINT [DF_Karyawan_Email];
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = N'DF_Karyawan_Email_Backup')
    ALTER TABLE dbo.[Karyawan] DROP CONSTRAINT [DF_Karyawan_Email_Backup];
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = N'DF_Karyawan_Tgl_Masuk')
    ALTER TABLE dbo.[Karyawan] DROP CONSTRAINT [DF_Karyawan_Tgl_Masuk];
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = N'DF_HRIS_Approval_Request_Detail_Created_At')
    ALTER TABLE dbo.[HRIS_Approval_Request_Detail] DROP CONSTRAINT [DF_HRIS_Approval_Request_Detail_Created_At];
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = N'DF_HRIS_Shift_Kerja_Detail_Flag_Default_Off')
    ALTER TABLE dbo.[HRIS_Shift_Kerja_Detail] DROP CONSTRAINT [DF_HRIS_Shift_Kerja_Detail_Flag_Default_Off];
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = N'DF_HRIS_Page_Access_Flag_Aktif')
    ALTER TABLE dbo.[HRIS_Page_Access] DROP CONSTRAINT [DF_HRIS_Page_Access_Flag_Aktif];
GO

/* --- 4. indeks & primary key pada tabel lama --- */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalDetail_Source' AND object_id = OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]'))
    DROP INDEX [IX_ApprovalDetail_Source] ON dbo.[HRIS_Approval_Request_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalDetail_ApproverFilter' AND object_id = OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]'))
    DROP INDEX [IX_ApprovalDetail_ApproverFilter] ON dbo.[HRIS_Approval_Request_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalDetail_KaryawanTanggal' AND object_id = OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]'))
    DROP INDEX [IX_ApprovalDetail_KaryawanTanggal] ON dbo.[HRIS_Approval_Request_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_formula_nama' AND object_id = OBJECT_ID('dbo.[KPI_Master_Formula]'))
    DROP INDEX [idx_formula_nama] ON dbo.[KPI_Master_Formula];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_satuan_nama' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan]'))
    DROP INDEX [idx_satuan_nama] ON dbo.[KPI_Master_Satuan];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_satuan_simbol' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan]'))
    DROP INDEX [idx_satuan_simbol] ON dbo.[KPI_Master_Satuan];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_formula_id' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan_Formula]'))
    DROP INDEX [idx_formula_id] ON dbo.[KPI_Master_Satuan_Formula];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_satuan_id' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan_Formula]'))
    DROP INDEX [idx_satuan_id] ON dbo.[KPI_Master_Satuan_Formula];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_Update_Perf_Covering_Filter' AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
    DROP INDEX [IX_KPI_Update_Perf_Covering_Filter] ON dbo.[KPI_Update_Performance];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_Update_Perf_User_Periode' AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
    DROP INDEX [IX_KPI_Update_Perf_User_Periode] ON dbo.[KPI_Update_Performance];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_Update_Perf_Det_Join_Pica' AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance_Detail]'))
    DROP INDEX [IX_KPI_Update_Perf_Det_Join_Pica] ON dbo.[KPI_Update_Performance_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_User_Akses_Divisi_Id_User' AND object_id = OBJECT_ID('dbo.[KPI_User_Akses_Divisi]'))
    DROP INDEX [IX_KPI_User_Akses_Divisi_Id_User] ON dbo.[KPI_User_Akses_Divisi];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_User_Akses_Level_Id_User' AND object_id = OBJECT_ID('dbo.[KPI_User_Akses_Level]'))
    DROP INDEX [IX_KPI_User_Akses_Level_Id_User] ON dbo.[KPI_User_Akses_Level];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_VISITOR_Permit_Requester_Idempotency' AND object_id = OBJECT_ID('dbo.[N_EMI_VISITOR_Transaksi_Permit]'))
    DROP INDEX [UX_VISITOR_Permit_Requester_Idempotency] ON dbo.[N_EMI_VISITOR_Transaksi_Permit];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_VISITOR_Permit_Api_Aplikasi' AND object_id = OBJECT_ID('dbo.[N_EMI_VISITOR_Transaksi_Permit]'))
    DROP INDEX [IX_VISITOR_Permit_Api_Aplikasi] ON dbo.[N_EMI_VISITOR_Transaksi_Permit];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_KonversiLembur_Asal' AND object_id = OBJECT_ID('dbo.[N_HRIS_Konversi_Lembur_Efektif]'))
    DROP INDEX [UX_KonversiLembur_Asal] ON dbo.[N_HRIS_Konversi_Lembur_Efektif];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_RolePermission_Role' AND object_id = OBJECT_ID('dbo.[N_HRIS_Role_Permission]'))
    DROP INDEX [IX_RolePermission_Role] ON dbo.[N_HRIS_Role_Permission];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_StatusHari_Lookup' AND object_id = OBJECT_ID('dbo.[N_HRIS_Status_Hari_Karyawan]'))
    DROP INDEX [IX_StatusHari_Lookup] ON dbo.[N_HRIS_Status_Hari_Karyawan];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Approver_Shift_NoTrans' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Approver_Shift]'))
    DROP INDEX [IX_Transaksi_Approver_Shift_NoTrans] ON dbo.[N_HRIS_Transaksi_Approver_Shift];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Approver_Shift_Detail_NoTrans' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Approver_Shift_Detail]'))
    DROP INDEX [IX_Transaksi_Approver_Shift_Detail_NoTrans] ON dbo.[N_HRIS_Transaksi_Approver_Shift_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Transaksi_Shift_Created' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift]'))
    DROP INDEX [IX_N_HRIS_Transaksi_Shift_Created] ON dbo.[N_HRIS_Transaksi_Shift];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Transaksi_Shift_Status' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift]'))
    DROP INDEX [IX_N_HRIS_Transaksi_Shift_Status] ON dbo.[N_HRIS_Transaksi_Shift];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_ShiftDetail_NoTrans_Karyawan' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift_Detail]'))
    DROP INDEX [IX_N_HRIS_ShiftDetail_NoTrans_Karyawan] ON dbo.[N_HRIS_Transaksi_Shift_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_TSHD_NoTransaksi' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Status_Hari_Detail]'))
    DROP INDEX [IX_TSHD_NoTransaksi] ON dbo.[N_HRIS_Transaksi_Status_Hari_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_TSHD_KodeKaryawan' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Status_Hari_Detail]'))
    DROP INDEX [IX_TSHD_KodeKaryawan] ON dbo.[N_HRIS_Transaksi_Status_Hari_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_LMS_Master_Keterangan_Kelulusan_Kode' AND object_id = OBJECT_ID('dbo.[N_LMS_Master_Keterangan_Kelulusan]'))
    DROP INDEX [UX_N_LMS_Master_Keterangan_Kelulusan_Kode] ON dbo.[N_LMS_Master_Keterangan_Kelulusan];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_LMS_Master_Konten_Berkas_Status_Verifikasi' AND object_id = OBJECT_ID('dbo.[N_LMS_Master_Konten_Berkas]'))
    DROP INDEX [IX_N_LMS_Master_Konten_Berkas_Status_Verifikasi] ON dbo.[N_LMS_Master_Konten_Berkas];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_LMS_Penjadwalan_Paket_Enroll_Karyawan' AND object_id = OBJECT_ID('dbo.[N_LMS_Penjadwalan]'))
    DROP INDEX [IX_LMS_Penjadwalan_Paket_Enroll_Karyawan] ON dbo.[N_LMS_Penjadwalan];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_LMS_Penjadwalan_Syarat_Induk' AND object_id = OBJECT_ID('dbo.[N_LMS_Penjadwalan_Syarat]'))
    DROP INDEX [IX_LMS_Penjadwalan_Syarat_Induk] ON dbo.[N_LMS_Penjadwalan_Syarat];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir' AND object_id = OBJECT_ID('dbo.[N_VISITOR_Permit_Efektif]'))
    DROP INDEX [IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir] ON dbo.[N_VISITOR_Permit_Efektif];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Lembur_Det_NoTrans_Kode' AND object_id = OBJECT_ID('dbo.[Transaksi_Lembur_Det]'))
    DROP INDEX [IX_Transaksi_Lembur_Det_NoTrans_Kode] ON dbo.[Transaksi_Lembur_Det];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Lembur_Detail_Kode_Tgl' AND object_id = OBJECT_ID('dbo.[Transaksi_Lembur_Detail]'))
    DROP INDEX [IX_Transaksi_Lembur_Detail_Kode_Tgl] ON dbo.[Transaksi_Lembur_Detail];
GO
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Async_Tasks_Reference' AND object_id = OBJECT_ID('dbo.[N_HRIS_Async_Tasks]'))
    DROP INDEX [IX_N_HRIS_Async_Tasks_Reference] ON dbo.[N_HRIS_Async_Tasks];
GO
IF EXISTS (SELECT 1 FROM sys.key_constraints WHERE name = N'PK_HRIS_Approval_Flow')
    ALTER TABLE dbo.[HRIS_Approval_Flow] DROP CONSTRAINT [PK_HRIS_Approval_Flow];
GO

/* --- 5. kolom (MENGHAPUS DATA di kolom tersebut) --- */
IF COL_LENGTH('dbo.[N_HRIS_Cuti_Bersama]', N'Flag_Cancelled') IS NOT NULL
    ALTER TABLE dbo.[N_HRIS_Cuti_Bersama] DROP COLUMN [Flag_Cancelled];
GO
IF COL_LENGTH('dbo.[N_HRIS_Cuti_Bersama]', N'Cloud_Task_Name') IS NOT NULL
    ALTER TABLE dbo.[N_HRIS_Cuti_Bersama] DROP COLUMN [Cloud_Task_Name];
GO
IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Created_By') IS NOT NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] DROP COLUMN [Created_By];
GO
IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Status') IS NOT NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] DROP COLUMN [Status];
GO
IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Updated_By') IS NOT NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] DROP COLUMN [Updated_By];
GO
IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Created_At') IS NOT NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] DROP COLUMN [Created_At];
GO
IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Updated_At') IS NOT NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] DROP COLUMN [Updated_At];
GO
IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail]', N'Flag_Capped') IS NOT NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail] DROP COLUMN [Flag_Capped];
GO
IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail]', N'Score_Raw') IS NOT NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail] DROP COLUMN [Score_Raw];
GO
IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail]', N'Achievement_Raw') IS NOT NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail] DROP COLUMN [Achievement_Raw];
GO
IF COL_LENGTH('dbo.[Karyawan]', N'Flag_Probation') IS NOT NULL
    ALTER TABLE dbo.[Karyawan] DROP COLUMN [Flag_Probation];
GO
IF COL_LENGTH('dbo.[Karyawan]', N'max_Cuti') IS NOT NULL
    ALTER TABLE dbo.[Karyawan] DROP COLUMN [max_Cuti];
GO
IF COL_LENGTH('dbo.[Karyawan]', N'Tanggal_Akhir_Probation') IS NOT NULL
    ALTER TABLE dbo.[Karyawan] DROP COLUMN [Tanggal_Akhir_Probation];
GO
IF COL_LENGTH('dbo.[Karyawan]', N'Timezone') IS NOT NULL
    ALTER TABLE dbo.[Karyawan] DROP COLUMN [Timezone];
GO
IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail_Sementara]', N'Score_Raw') IS NOT NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail_Sementara] DROP COLUMN [Score_Raw];
GO
IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail_Sementara]', N'Flag_Capped') IS NOT NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail_Sementara] DROP COLUMN [Flag_Capped];
GO
IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail_Sementara]', N'Achievement_Raw') IS NOT NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail_Sementara] DROP COLUMN [Achievement_Raw];
GO
IF COL_LENGTH('dbo.[Whatsapp_Log]', N'Kode_Karyawan_Sender') IS NOT NULL
    ALTER TABLE dbo.[Whatsapp_Log] DROP COLUMN [Kode_Karyawan_Sender];
GO
IF COL_LENGTH('dbo.[Transaksi_Cuti_Det]', N'Jenis_Tanggal_Pengajuan') IS NOT NULL
    ALTER TABLE dbo.[Transaksi_Cuti_Det] DROP COLUMN [Jenis_Tanggal_Pengajuan];
GO
IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Waktu_Mulai_Berlaku') IS NOT NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP COLUMN [Waktu_Mulai_Berlaku];
GO
IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Nama_Pemegang') IS NOT NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP COLUMN [Nama_Pemegang];
GO
IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Instansi_Pemegang') IS NOT NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP COLUMN [Instansi_Pemegang];
GO
IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Waktu_Berakhir') IS NOT NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP COLUMN [Waktu_Berakhir];
GO
IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Sumber_Permit') IS NOT NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] DROP COLUMN [Sumber_Permit];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit]', N'Id_Api_Aplikasi') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit] DROP COLUMN [Id_Api_Aplikasi];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit]', N'Referensi_Mitra') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit] DROP COLUMN [Referensi_Mitra];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit]', N'Sumber_Pengajuan') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit] DROP COLUMN [Sumber_Pengajuan];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Nama_Tamu') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] DROP COLUMN [Nama_Tamu];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Jenis_Identitas_Tamu') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] DROP COLUMN [Jenis_Identitas_Tamu];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Waktu_Mulai_Berlaku') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] DROP COLUMN [Waktu_Mulai_Berlaku];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Instansi_Tamu') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] DROP COLUMN [Instansi_Tamu];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'No_Identitas_Tamu') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] DROP COLUMN [No_Identitas_Tamu];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Waktu_Berakhir') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] DROP COLUMN [Waktu_Berakhir];
GO
IF COL_LENGTH('dbo.[N_HRIS_Absen_Snapshot]', N'Koefisien_OT_Gross') IS NOT NULL
    ALTER TABLE dbo.[N_HRIS_Absen_Snapshot] DROP COLUMN [Koefisien_OT_Gross];
GO
IF COL_LENGTH('dbo.[KPI_Target_Breakdown]', N'Flag_Aktif') IS NOT NULL
    ALTER TABLE dbo.[KPI_Target_Breakdown] DROP COLUMN [Flag_Aktif];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail]', N'Waktu_Mulai_Kunjungan') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail] DROP COLUMN [Waktu_Mulai_Kunjungan];
GO
IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail]', N'Waktu_Selesai_Kunjungan') IS NOT NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail] DROP COLUMN [Waktu_Selesai_Kunjungan];
GO
IF COL_LENGTH('dbo.[N_SOP_Master_Jenis_Dokumen]', N'Siklus_Review_Bulan') IS NOT NULL
    ALTER TABLE dbo.[N_SOP_Master_Jenis_Dokumen] DROP COLUMN [Siklus_Review_Bulan];
GO
IF COL_LENGTH('dbo.[N_SOP_Master_Jenis_Dokumen]', N'Masa_Berlaku_Bulan') IS NOT NULL
    ALTER TABLE dbo.[N_SOP_Master_Jenis_Dokumen] DROP COLUMN [Masa_Berlaku_Bulan];
GO
IF COL_LENGTH('dbo.[N_HRIS_Master_Entitas]', N'Kode_Sebagai_Pemilik') IS NOT NULL
    ALTER TABLE dbo.[N_HRIS_Master_Entitas] DROP COLUMN [Kode_Sebagai_Pemilik];
GO
IF COL_LENGTH('dbo.[N_HRIS_Master_Entitas]', N'Logo_Path') IS NOT NULL
    ALTER TABLE dbo.[N_HRIS_Master_Entitas] DROP COLUMN [Logo_Path];
GO
IF COL_LENGTH('dbo.[HRIS_Level]', N'Flag_Harian') IS NOT NULL
    ALTER TABLE dbo.[HRIS_Level] DROP COLUMN [Flag_Harian];
GO

/* --- 6. tabel baru (urutan dibalik agar FK antar-tabel aman) --- */
IF OBJECT_ID('dbo.[N_SOP_Automation_Run]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Automation_Run];
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Penjadwalan_Detail];
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Penjadwalan];
GO
IF OBJECT_ID('dbo.[N_SOP_Assignment_Rule_Paket]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Assignment_Rule_Paket];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Item]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Item];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item_Versi]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Draft_Item_Versi];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Draft_Item];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Section]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Draft_Section];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Draft];
GO
IF OBJECT_ID('dbo.[N_SOP_Assignment_Rule]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Assignment_Rule];
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Progress];
GO
IF OBJECT_ID('dbo.[N_SOP_Favorit]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Favorit];
GO
IF OBJECT_ID('dbo.[N_HRIS_Master_Konversi_Lembur]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Master_Konversi_Lembur];
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi_Berkas]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Item_Versi_Berkas];
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Log]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Api_Log];
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Audit]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Pengumpulan_Data_Audit];
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Integrasi]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Penjadwalan_Integrasi];
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Nonce]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Api_Nonce];
GO
IF OBJECT_ID('dbo.[HRIS_Role_Konten_Access]','U') IS NOT NULL DROP TABLE dbo.[HRIS_Role_Konten_Access];
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Item_Versi];
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Config]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Pengumpulan_Data_Config];
GO
IF OBJECT_ID('dbo.[HRIS_Role_Menu_Access]','U') IS NOT NULL DROP TABLE dbo.[HRIS_Role_Menu_Access];
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Akses_Endpoint]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Api_Akses_Endpoint];
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Status]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Pengumpulan_Data_Status];
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Endpoint]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Api_Endpoint];
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Submission]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Pengumpulan_Data_Submission];
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Aplikasi]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Api_Aplikasi];
GO
IF OBJECT_ID('dbo.[HRIS_Page_Access_2]','U') IS NOT NULL DROP TABLE dbo.[HRIS_Page_Access_2];
GO
IF OBJECT_ID('dbo.[N_SOP_Master_Paket]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Master_Paket];
GO
IF OBJECT_ID('dbo.[HRIS_Klasifikasi_Aksi]','U') IS NOT NULL DROP TABLE dbo.[HRIS_Klasifikasi_Aksi];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Versi_Item]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Versi_Item];
GO
IF OBJECT_ID('dbo.[N_SOP_Kategori]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Kategori];
GO
IF OBJECT_ID('dbo.[N_SOP_Prioritas]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Prioritas];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Versi]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Versi];
GO
IF OBJECT_ID('dbo.[N_SOP_Aktivitas_Log]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Aktivitas_Log];
GO
IF OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Kuis_Integrasi];
GO
IF OBJECT_ID('dbo.[N_SOP_Absensi]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Absensi];
GO
IF OBJECT_ID('dbo.[N_SOP_Coverage_Rollup]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Coverage_Rollup];
GO
IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Kepatuhan_Riwayat];
GO
IF OBJECT_ID('dbo.[N_HRIS_Bypass_Cuti_Tutup_Buku]','U') IS NOT NULL DROP TABLE dbo.[N_HRIS_Bypass_Cuti_Tutup_Buku];
GO
IF OBJECT_ID('dbo.[N_SOP_Log]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Log];
GO
IF OBJECT_ID('dbo.[N_SOP_Setting]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Setting];
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Section]','U') IS NOT NULL DROP TABLE dbo.[N_SOP_Paket_Section];
GO
IF OBJECT_ID('dbo.[N_KPI_Target_Koreksi]','U') IS NOT NULL DROP TABLE dbo.[N_KPI_Target_Koreksi];
GO

/* selesai */
