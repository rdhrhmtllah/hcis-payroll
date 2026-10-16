-- database/ddl/DDL-2026-092-sinkronisasi-skema-production/forward.sql
--
-- Sinkronisasi skema production terhadap development, disusun dari perbandingan
-- READ ONLY pada 10 September 2026 (docs/audit/perbandingan-skema-production-2026-09-10.md).
--
-- SIFAT BERKAS INI
--   * IDEMPOTEN  : setiap pernyataan dibungkus penjaga keberadaan, jadi aman
--                  dijalankan berulang kali. Menjalankan dua kali tidak
--                  menambah apa pun dan tidak melempar galat.
--   * MENAMBAH   : tidak ada DROP, TRUNCATE, DELETE, atau ALTER yang menimpa
--                  data. Tidak ada kolom yang diubah tipenya.
--   * BERURUTAN  : bagian 1..7 harus dijalankan dari atas ke bawah. Tabel dulu,
--                  baru kolom, indeks, default, trigger, lalu foreign key.
--
-- CARA MENJALANKAN
--   Jalankan seluruh berkas dalam satu sesi SSMS/sqlcmd terhadap database
--   production. Berkas memakai pemisah batch GO, jadi jangan dijalankan lewat
--   klien yang tidak mengenal GO.
--
-- URUTAN TERHADAP data-change.sql
--   Jalankan forward.sql SAMPAI SELESAI lebih dulu, baru data-change.sql.
--   Bagian 7 (foreign key) sengaja melewati constraint yang masih punya baris
--   yatim; setelah DML dijalankan, jalankan ulang forward.sql sekali lagi agar
--   foreign key yang tadi dilewati ikut terpasang.
--
-- CATATAN LINGKUP
--   Bagian 5 membuat 44 tabel milik modul yang BELUM dirilis (SOP/DMS, registry
--   API, Pengumpulan Data, RBAC generasi kedua). Tabel itu dibuat kosong dan
--   tidak mengubah perilaku aplikasi yang berjalan sekarang, tapi memasangnya
--   adalah keputusan rilis. Kalau belum mau, lewati bagian 5 dan 7 — bagian
--   1..4 tetap berdiri sendiri.
--
-- TIDAK TERMASUK (sengaja):
--   * 43 tabel N_PA_*        -> kodenya tidak ada di cabang master
--   * 8 tabel HRIS_KANDIDAT_* -> milik aplikasi rekrutmen
--   * N_HRIS_Approval_Request -> production memakai nama kolom berbeda; tabel
--                                ini tidak dirujuk kode mana pun
--   * penyamaan tipe data     -> butuh keputusan arah, lihat laporan §2e

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

/* ==========================================================================
   BAGIAN 1  KOLOM KRITIS — halaman production error tanpa ini
   ========================================================================== */

IF COL_LENGTH('dbo.[N_HRIS_Cuti_Bersama]', N'Flag_Cancelled') IS NULL
    ALTER TABLE dbo.[N_HRIS_Cuti_Bersama] ADD [Flag_Cancelled] char(1) NULL CONSTRAINT [DF_N_HRIS_Cuti_Bersama_Flag_Cancelled] DEFAULT ('T');
GO

IF COL_LENGTH('dbo.[N_HRIS_Cuti_Bersama]', N'Cloud_Task_Name') IS NULL
    ALTER TABLE dbo.[N_HRIS_Cuti_Bersama] ADD [Cloud_Task_Name] varchar(100) NULL;
GO

IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Created_By') IS NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] ADD [Created_By] varchar(200) NULL;
GO

IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Status') IS NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] ADD [Status] char(1) NULL;
GO

IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Updated_By') IS NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] ADD [Updated_By] varchar(200) NULL;
GO

IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Created_At') IS NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] ADD [Created_At] datetime NULL CONSTRAINT [DF_HRIS_Hak_Cuti_Tambahan_Created_At] DEFAULT (getdate());
GO

IF COL_LENGTH('dbo.[HRIS_Hak_Cuti_Tambahan]', N'Updated_At') IS NULL
    ALTER TABLE dbo.[HRIS_Hak_Cuti_Tambahan] ADD [Updated_At] datetime NULL;
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail]', N'Flag_Capped') IS NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail] ADD [Flag_Capped] char(1) NOT NULL CONSTRAINT [DF_KPI_Update_Performance_Detail_Flag_Capped] DEFAULT ('T');
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail]', N'Score_Raw') IS NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail] ADD [Score_Raw] float NULL;
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail]', N'Achievement_Raw') IS NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail] ADD [Achievement_Raw] float NULL;
GO

IF COL_LENGTH('dbo.[Karyawan]', N'Flag_Probation') IS NULL
    ALTER TABLE dbo.[Karyawan] ADD [Flag_Probation] char(1) NULL;
GO

IF COL_LENGTH('dbo.[Karyawan]', N'max_Cuti') IS NULL
    ALTER TABLE dbo.[Karyawan] ADD [max_Cuti] int NULL;
GO

IF COL_LENGTH('dbo.[Karyawan]', N'Tanggal_Akhir_Probation') IS NULL
    ALTER TABLE dbo.[Karyawan] ADD [Tanggal_Akhir_Probation] datetime NULL;
GO

IF COL_LENGTH('dbo.[Karyawan]', N'Timezone') IS NULL
    ALTER TABLE dbo.[Karyawan] ADD [Timezone] int NULL;
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail_Sementara]', N'Score_Raw') IS NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail_Sementara] ADD [Score_Raw] float NULL;
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail_Sementara]', N'Flag_Capped') IS NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail_Sementara] ADD [Flag_Capped] char(1) NOT NULL CONSTRAINT [DF_KPI_Update_Performance_Detail_Sementara_Flag_Capped] DEFAULT ('T');
GO

IF COL_LENGTH('dbo.[KPI_Update_Performance_Detail_Sementara]', N'Achievement_Raw') IS NULL
    ALTER TABLE dbo.[KPI_Update_Performance_Detail_Sementara] ADD [Achievement_Raw] float NULL;
GO


/* ==========================================================================
   BAGIAN 2  KOLOM PELENGKAP — belum dipakai kode, disamakan agar tak menyimpang
   ========================================================================== */

IF COL_LENGTH('dbo.[Whatsapp_Log]', N'Kode_Karyawan_Sender') IS NULL
    ALTER TABLE dbo.[Whatsapp_Log] ADD [Kode_Karyawan_Sender] varchar(200) NULL;
GO

IF COL_LENGTH('dbo.[Transaksi_Cuti_Det]', N'Jenis_Tanggal_Pengajuan') IS NULL
    ALTER TABLE dbo.[Transaksi_Cuti_Det] ADD [Jenis_Tanggal_Pengajuan] varchar(30) NULL CONSTRAINT [DF_Transaksi_Cuti_Det_Jenis_Tanggal_Pengajuan] DEFAULT ('MULTIPLE');
GO

IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Waktu_Mulai_Berlaku') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] ADD [Waktu_Mulai_Berlaku] datetime2(0) NULL;
GO

IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Nama_Pemegang') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] ADD [Nama_Pemegang] varchar(150) NULL;
GO

IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Instansi_Pemegang') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] ADD [Instansi_Pemegang] varchar(150) NULL;
GO

IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Waktu_Berakhir') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] ADD [Waktu_Berakhir] datetime2(0) NULL;
GO

IF COL_LENGTH('dbo.[N_VISITOR_Permit_Efektif]', N'Sumber_Permit') IS NULL
    ALTER TABLE dbo.[N_VISITOR_Permit_Efektif] ADD [Sumber_Permit] varchar(10) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit]', N'Id_Api_Aplikasi') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit] ADD [Id_Api_Aplikasi] int NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit]', N'Referensi_Mitra') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit] ADD [Referensi_Mitra] varchar(100) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit]', N'Sumber_Pengajuan') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit] ADD [Sumber_Pengajuan] varchar(10) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Nama_Tamu') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] ADD [Nama_Tamu] varchar(150) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Jenis_Identitas_Tamu') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] ADD [Jenis_Identitas_Tamu] varchar(20) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Waktu_Mulai_Berlaku') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] ADD [Waktu_Mulai_Berlaku] datetime2(0) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Instansi_Tamu') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] ADD [Instansi_Tamu] varchar(150) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'No_Identitas_Tamu') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] ADD [No_Identitas_Tamu] varchar(100) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail]', N'Waktu_Berakhir') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Permit_Detail] ADD [Waktu_Berakhir] datetime2(0) NULL;
GO

IF COL_LENGTH('dbo.[N_HRIS_Absen_Snapshot]', N'Koefisien_OT_Gross') IS NULL
    ALTER TABLE dbo.[N_HRIS_Absen_Snapshot] ADD [Koefisien_OT_Gross] int NULL;
GO

IF COL_LENGTH('dbo.[KPI_Target_Breakdown]', N'Flag_Aktif') IS NULL
    ALTER TABLE dbo.[KPI_Target_Breakdown] ADD [Flag_Aktif] char(1) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail]', N'Waktu_Mulai_Kunjungan') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail] ADD [Waktu_Mulai_Kunjungan] datetime2(0) NULL;
GO

IF COL_LENGTH('dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail]', N'Waktu_Selesai_Kunjungan') IS NULL
    ALTER TABLE dbo.[N_EMI_VISITOR_Transaksi_Tamu_Detail] ADD [Waktu_Selesai_Kunjungan] datetime2(0) NULL;
GO

IF COL_LENGTH('dbo.[N_SOP_Master_Jenis_Dokumen]', N'Siklus_Review_Bulan') IS NULL
    ALTER TABLE dbo.[N_SOP_Master_Jenis_Dokumen] ADD [Siklus_Review_Bulan] int NULL;
GO

IF COL_LENGTH('dbo.[N_SOP_Master_Jenis_Dokumen]', N'Masa_Berlaku_Bulan') IS NULL
    ALTER TABLE dbo.[N_SOP_Master_Jenis_Dokumen] ADD [Masa_Berlaku_Bulan] int NULL;
GO

IF COL_LENGTH('dbo.[N_HRIS_Master_Entitas]', N'Kode_Sebagai_Pemilik') IS NULL
    ALTER TABLE dbo.[N_HRIS_Master_Entitas] ADD [Kode_Sebagai_Pemilik] varchar(10) NULL;
GO

IF COL_LENGTH('dbo.[N_HRIS_Master_Entitas]', N'Logo_Path') IS NULL
    ALTER TABLE dbo.[N_HRIS_Master_Entitas] ADD [Logo_Path] varchar(255) NULL;
GO

IF COL_LENGTH('dbo.[HRIS_Level]', N'Flag_Harian') IS NULL
    ALTER TABLE dbo.[HRIS_Level] ADD [Flag_Harian] varchar(1) NULL CONSTRAINT [DF_HRIS_Level_Flag_Harian] DEFAULT (NULL);
GO


/* ==========================================================================
   BAGIAN 3  INDEKS pada tabel yang sudah ada di production
   ========================================================================== */

/* HRIS_Approval_Flow di production adalah HEAP tanpa satu pun indeks.
   Sudah diverifikasi read-only sebelum berkas ini dibuat: kolom [id] int NOT NULL,
   4.310 baris, 4.310 nilai unik, 0 NULL — jadi aman dijadikan primary key.
   Pemeriksaan itu diulang di sini supaya tetap aman kalau datanya berubah. */
IF OBJECT_ID('dbo.[HRIS_Approval_Flow]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE object_id = OBJECT_ID('dbo.[HRIS_Approval_Flow]') AND is_primary_key = 1)
   AND NOT EXISTS (SELECT 1 FROM dbo.[HRIS_Approval_Flow] WHERE [id] IS NULL)
   AND NOT EXISTS (SELECT [id] FROM dbo.[HRIS_Approval_Flow] GROUP BY [id] HAVING COUNT(*) > 1)
    ALTER TABLE dbo.[HRIS_Approval_Flow]
        ADD CONSTRAINT [PK_HRIS_Approval_Flow] PRIMARY KEY CLUSTERED ([id]);
GO

IF OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalDetail_Source' AND object_id = OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_ApprovalDetail_Source]
        ON dbo.[HRIS_Approval_Request_Detail] ([Source_View],[Source_Row_Key])
        INCLUDE ([Flag_Approval],[Id_Approval_Request]);
GO

IF OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalDetail_ApproverFilter' AND object_id = OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_ApprovalDetail_ApproverFilter]
        ON dbo.[HRIS_Approval_Request_Detail] ([Processed_By_Kode],[Processed_At])
        INCLUDE ([Flag_Approval],[Id_Approval_Request],[Kode_Karyawan]);
GO

IF OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ApprovalDetail_KaryawanTanggal' AND object_id = OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_ApprovalDetail_KaryawanTanggal]
        ON dbo.[HRIS_Approval_Request_Detail] ([Kode_Karyawan],[Tanggal_Item])
        INCLUDE ([Flag_Approval],[Processed_By_Kode]);
GO

IF OBJECT_ID('dbo.[KPI_Master_Formula]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_formula_nama' AND object_id = OBJECT_ID('dbo.[KPI_Master_Formula]'))
    CREATE NONCLUSTERED INDEX [idx_formula_nama]
        ON dbo.[KPI_Master_Formula] ([Nama_Formula]);
GO

IF OBJECT_ID('dbo.[KPI_Master_Satuan]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_satuan_nama' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan]'))
    CREATE NONCLUSTERED INDEX [idx_satuan_nama]
        ON dbo.[KPI_Master_Satuan] ([Nama_Satuan]);
GO

IF OBJECT_ID('dbo.[KPI_Master_Satuan]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_satuan_simbol' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan]'))
    CREATE NONCLUSTERED INDEX [idx_satuan_simbol]
        ON dbo.[KPI_Master_Satuan] ([Simbol_Satuan]);
GO

IF OBJECT_ID('dbo.[KPI_Master_Satuan_Formula]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_formula_id' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan_Formula]'))
    CREATE NONCLUSTERED INDEX [idx_formula_id]
        ON dbo.[KPI_Master_Satuan_Formula] ([Id_Master_Formula]);
GO

IF OBJECT_ID('dbo.[KPI_Master_Satuan_Formula]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_satuan_id' AND object_id = OBJECT_ID('dbo.[KPI_Master_Satuan_Formula]'))
    CREATE NONCLUSTERED INDEX [idx_satuan_id]
        ON dbo.[KPI_Master_Satuan_Formula] ([Id_Master_Satuan]);
GO

IF OBJECT_ID('dbo.[KPI_Update_Performance]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_Update_Perf_Covering_Filter' AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
    CREATE NONCLUSTERED INDEX [IX_KPI_Update_Perf_Covering_Filter]
        ON dbo.[KPI_Update_Performance] ([Status_Pengajuan],[Flag_Approval],[Flag_Verifikasi],[Status],[Id_User],[Periode]);
GO

IF OBJECT_ID('dbo.[KPI_Update_Performance]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_Update_Perf_User_Periode' AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance]'))
    CREATE NONCLUSTERED INDEX [IX_KPI_Update_Perf_User_Periode]
        ON dbo.[KPI_Update_Performance] ([Id_User],[Periode],[Status_Pengajuan],[Flag_Approval],[Flag_Verifikasi],[Status]);
GO

IF OBJECT_ID('dbo.[KPI_Update_Performance_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_Update_Perf_Det_Join_Pica' AND object_id = OBJECT_ID('dbo.[KPI_Update_Performance_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_KPI_Update_Perf_Det_Join_Pica]
        ON dbo.[KPI_Update_Performance_Detail] ([Id_Update_Performance],[Id_Pengajuan_Det],[Flag_Pica]);
GO

IF OBJECT_ID('dbo.[KPI_User_Akses_Divisi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_User_Akses_Divisi_Id_User' AND object_id = OBJECT_ID('dbo.[KPI_User_Akses_Divisi]'))
    CREATE NONCLUSTERED INDEX [IX_KPI_User_Akses_Divisi_Id_User]
        ON dbo.[KPI_User_Akses_Divisi] ([Id_User]);
GO

IF OBJECT_ID('dbo.[KPI_User_Akses_Level]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_KPI_User_Akses_Level_Id_User' AND object_id = OBJECT_ID('dbo.[KPI_User_Akses_Level]'))
    CREATE NONCLUSTERED INDEX [IX_KPI_User_Akses_Level_Id_User]
        ON dbo.[KPI_User_Akses_Level] ([Id_User]);
GO

IF OBJECT_ID('dbo.[N_EMI_VISITOR_Transaksi_Permit]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_VISITOR_Permit_Requester_Idempotency' AND object_id = OBJECT_ID('dbo.[N_EMI_VISITOR_Transaksi_Permit]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Permit_Requester_Idempotency]
        ON dbo.[N_EMI_VISITOR_Transaksi_Permit] ([Kode_Karyawan_Pemohon],[Id_Api_Aplikasi],[Idempotency_Key])
        WHERE ([Idempotency_Key] IS NOT NULL);
GO

IF OBJECT_ID('dbo.[N_EMI_VISITOR_Transaksi_Permit]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_VISITOR_Permit_Api_Aplikasi' AND object_id = OBJECT_ID('dbo.[N_EMI_VISITOR_Transaksi_Permit]'))
    CREATE NONCLUSTERED INDEX [IX_VISITOR_Permit_Api_Aplikasi]
        ON dbo.[N_EMI_VISITOR_Transaksi_Permit] ([Id_Api_Aplikasi],[Flag_Approval])
        INCLUDE ([Flag_Aktif],[No_Transaksi])
        WHERE ([Id_Api_Aplikasi] IS NOT NULL);
GO

IF OBJECT_ID('dbo.[N_HRIS_Konversi_Lembur_Efektif]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_KonversiLembur_Asal' AND object_id = OBJECT_ID('dbo.[N_HRIS_Konversi_Lembur_Efektif]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_KonversiLembur_Asal]
        ON dbo.[N_HRIS_Konversi_Lembur_Efektif] ([Kode_Perusahaan],[Kode_Karyawan],[Tanggal_Lembur_Asal])
        WHERE ([Flag_Aktif]='Y');
GO

IF OBJECT_ID('dbo.[N_HRIS_Role_Permission]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_RolePermission_Role' AND object_id = OBJECT_ID('dbo.[N_HRIS_Role_Permission]'))
    CREATE NONCLUSTERED INDEX [IX_RolePermission_Role]
        ON dbo.[N_HRIS_Role_Permission] ([Id_Role]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Status_Hari_Karyawan]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_StatusHari_Lookup' AND object_id = OBJECT_ID('dbo.[N_HRIS_Status_Hari_Karyawan]'))
    CREATE NONCLUSTERED INDEX [IX_StatusHari_Lookup]
        ON dbo.[N_HRIS_Status_Hari_Karyawan] ([Kode_Perusahaan],[Kode_Karyawan],[Tgl_Mulai],[Tgl_Selesai],[Hari],[Flag_Aktif]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Transaksi_Approver_Shift]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Approver_Shift_NoTrans' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Approver_Shift]'))
    CREATE NONCLUSTERED INDEX [IX_Transaksi_Approver_Shift_NoTrans]
        ON dbo.[N_HRIS_Transaksi_Approver_Shift] ([No_Transaksi]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Transaksi_Approver_Shift_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Approver_Shift_Detail_NoTrans' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Approver_Shift_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_Transaksi_Approver_Shift_Detail_NoTrans]
        ON dbo.[N_HRIS_Transaksi_Approver_Shift_Detail] ([No_Transaksi]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Transaksi_Shift_Created' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Transaksi_Shift_Created]
        ON dbo.[N_HRIS_Transaksi_Shift] ([Created_By],[Created_At]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Transaksi_Shift_Status' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Transaksi_Shift_Status]
        ON dbo.[N_HRIS_Transaksi_Shift] ([Status],[Flag_Selesai]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_ShiftDetail_NoTrans_Karyawan' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Shift_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_ShiftDetail_NoTrans_Karyawan]
        ON dbo.[N_HRIS_Transaksi_Shift_Detail] ([No_Transaksi],[Kode_Karyawan]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Transaksi_Status_Hari_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_TSHD_NoTransaksi' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Status_Hari_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_TSHD_NoTransaksi]
        ON dbo.[N_HRIS_Transaksi_Status_Hari_Detail] ([No_Transaksi]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Transaksi_Status_Hari_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_TSHD_KodeKaryawan' AND object_id = OBJECT_ID('dbo.[N_HRIS_Transaksi_Status_Hari_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_TSHD_KodeKaryawan]
        ON dbo.[N_HRIS_Transaksi_Status_Hari_Detail] ([Kode_Karyawan]);
GO

IF OBJECT_ID('dbo.[N_LMS_Master_Keterangan_Kelulusan]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_LMS_Master_Keterangan_Kelulusan_Kode' AND object_id = OBJECT_ID('dbo.[N_LMS_Master_Keterangan_Kelulusan]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_LMS_Master_Keterangan_Kelulusan_Kode]
        ON dbo.[N_LMS_Master_Keterangan_Kelulusan] ([Kode_Keterangan]);
GO

IF OBJECT_ID('dbo.[N_LMS_Master_Konten_Berkas]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_LMS_Master_Konten_Berkas_Status_Verifikasi' AND object_id = OBJECT_ID('dbo.[N_LMS_Master_Konten_Berkas]'))
    CREATE NONCLUSTERED INDEX [IX_N_LMS_Master_Konten_Berkas_Status_Verifikasi]
        ON dbo.[N_LMS_Master_Konten_Berkas] ([Status_Verifikasi])
        INCLUDE ([Id_Master_Konten],[Type_File]);
GO

IF OBJECT_ID('dbo.[N_LMS_Penjadwalan]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_LMS_Penjadwalan_Paket_Enroll_Karyawan' AND object_id = OBJECT_ID('dbo.[N_LMS_Penjadwalan]'))
    CREATE NONCLUSTERED INDEX [IX_LMS_Penjadwalan_Paket_Enroll_Karyawan]
        ON dbo.[N_LMS_Penjadwalan] ([Id_Master_Paket],[Waktu_Enroll],[Kode_Karyawan]);
GO

IF OBJECT_ID('dbo.[N_LMS_Penjadwalan_Syarat]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_LMS_Penjadwalan_Syarat_Induk' AND object_id = OBJECT_ID('dbo.[N_LMS_Penjadwalan_Syarat]'))
    CREATE NONCLUSTERED INDEX [IX_LMS_Penjadwalan_Syarat_Induk]
        ON dbo.[N_LMS_Penjadwalan_Syarat] ([Id_Penjadwalan_Det])
        INCLUDE ([Flag_Aktif],[Id_Materi_Det],[Id_Penjadwalan_Detail]);
GO

IF OBJECT_ID('dbo.[N_VISITOR_Permit_Efektif]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir' AND object_id = OBJECT_ID('dbo.[N_VISITOR_Permit_Efektif]'))
    CREATE NONCLUSTERED INDEX [IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir]
        ON dbo.[N_VISITOR_Permit_Efektif] ([Status_Efektif],[Waktu_Berakhir])
        INCLUDE ([No_Transaksi],[Sumber_Permit]);
GO

IF OBJECT_ID('dbo.[Transaksi_Lembur_Det]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Lembur_Det_NoTrans_Kode' AND object_id = OBJECT_ID('dbo.[Transaksi_Lembur_Det]'))
    CREATE NONCLUSTERED INDEX [IX_Transaksi_Lembur_Det_NoTrans_Kode]
        ON dbo.[Transaksi_Lembur_Det] ([No_Transaksi],[Kode_Karyawan],[Tanggal_Lembur]);
GO

IF OBJECT_ID('dbo.[Transaksi_Lembur_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Transaksi_Lembur_Detail_Kode_Tgl' AND object_id = OBJECT_ID('dbo.[Transaksi_Lembur_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_Transaksi_Lembur_Detail_Kode_Tgl]
        ON dbo.[Transaksi_Lembur_Detail] ([Kode_Karyawan],[Tanggal_Lembur_Dari],[Tanggal_Lembur_Sampai]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Async_Tasks]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Async_Tasks_Reference' AND object_id = OBJECT_ID('dbo.[N_HRIS_Async_Tasks]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Async_Tasks_Reference]
        ON dbo.[N_HRIS_Async_Tasks] ([Task_Type],[Reference_Id],[Status]);
GO


/* ==========================================================================
   BAGIAN 4  DEFAULT & CHECK CONSTRAINT
   ========================================================================== */

IF COL_LENGTH('dbo.[Karyawan]', N'Aktif') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.default_constraints dc
                     JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                    WHERE dc.parent_object_id = OBJECT_ID('dbo.[Karyawan]') AND c.name = N'Aktif')
    ALTER TABLE dbo.[Karyawan] ADD CONSTRAINT [DF_Karyawan_Aktif] DEFAULT ('Y') FOR [Aktif];
GO
IF COL_LENGTH('dbo.[Karyawan]', N'Email') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.default_constraints dc
                     JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                    WHERE dc.parent_object_id = OBJECT_ID('dbo.[Karyawan]') AND c.name = N'Email')
    ALTER TABLE dbo.[Karyawan] ADD CONSTRAINT [DF_Karyawan_Email] DEFAULT ('-') FOR [Email];
GO
IF COL_LENGTH('dbo.[Karyawan]', N'Email_Backup') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.default_constraints dc
                     JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                    WHERE dc.parent_object_id = OBJECT_ID('dbo.[Karyawan]') AND c.name = N'Email_Backup')
    ALTER TABLE dbo.[Karyawan] ADD CONSTRAINT [DF_Karyawan_Email_Backup] DEFAULT ('-') FOR [Email_Backup];
GO
IF COL_LENGTH('dbo.[Karyawan]', N'Tgl_Masuk') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.default_constraints dc
                     JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                    WHERE dc.parent_object_id = OBJECT_ID('dbo.[Karyawan]') AND c.name = N'Tgl_Masuk')
    ALTER TABLE dbo.[Karyawan] ADD CONSTRAINT [DF_Karyawan_Tgl_Masuk] DEFAULT (((2000)-(1))-(1)) FOR [Tgl_Masuk];
GO
IF COL_LENGTH('dbo.[HRIS_Approval_Request_Detail]', N'Created_At') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.default_constraints dc
                     JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                    WHERE dc.parent_object_id = OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]') AND c.name = N'Created_At')
    ALTER TABLE dbo.[HRIS_Approval_Request_Detail] ADD CONSTRAINT [DF_HRIS_Approval_Request_Detail_Created_At] DEFAULT (getdate()) FOR [Created_At];
GO
IF COL_LENGTH('dbo.[HRIS_Shift_Kerja_Detail]', N'Flag_Default_Off') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.default_constraints dc
                     JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                    WHERE dc.parent_object_id = OBJECT_ID('dbo.[HRIS_Shift_Kerja_Detail]') AND c.name = N'Flag_Default_Off')
    ALTER TABLE dbo.[HRIS_Shift_Kerja_Detail] ADD CONSTRAINT [DF_HRIS_Shift_Kerja_Detail_Flag_Default_Off] DEFAULT ('T') FOR [Flag_Default_Off];
GO
IF COL_LENGTH('dbo.[HRIS_Page_Access]', N'Flag_Aktif') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.default_constraints dc
                     JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
                    WHERE dc.parent_object_id = OBJECT_ID('dbo.[HRIS_Page_Access]') AND c.name = N'Flag_Aktif')
    ALTER TABLE dbo.[HRIS_Page_Access] ADD CONSTRAINT [DF_HRIS_Page_Access_Flag_Aktif] DEFAULT ('Y') FOR [Flag_Aktif];
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'CK_N_LMS_Master_Konten_Berkas_Status_Verifikasi'
                 AND parent_object_id = OBJECT_ID('dbo.[N_LMS_Master_Konten_Berkas]'))
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_LMS_Master_Konten_Berkas] WHERE NOT (([Status_Verifikasi]='LAMA' OR [Status_Verifikasi]='TERTUNDA_PEMINDAIAN' OR [Status_Verifikasi]='DITOLAK' OR [Status_Verifikasi]='SIAP' OR [Status_Verifikasi]='MENUNGGU')))
    ALTER TABLE dbo.[N_LMS_Master_Konten_Berkas] WITH CHECK
        ADD CONSTRAINT [CK_N_LMS_Master_Konten_Berkas_Status_Verifikasi] CHECK ([Status_Verifikasi]='LAMA' OR [Status_Verifikasi]='TERTUNDA_PEMINDAIAN' OR [Status_Verifikasi]='DITOLAK' OR [Status_Verifikasi]='SIAP' OR [Status_Verifikasi]='MENUNGGU');
GO


/* ==========================================================================
   BAGIAN 5  TABEL BARU (modul belum rilis) — boleh dilewati, lihat catatan
   ========================================================================== */

IF OBJECT_ID('dbo.[N_KPI_Target_Koreksi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_KPI_Target_Koreksi] (
    [Id_Target_Koreksi]                int IDENTITY(1,1) NOT NULL,
    [No_Batch]                         varchar(40) NOT NULL,
    [Id_Pengajuan_Det]                 int NOT NULL,
    [Nilai_Target_Lama]                float NOT NULL,
    [Nilai_Target_Baru]                float NOT NULL,
    [Sebab]                            nvarchar(500) NOT NULL,
    [Id_User_Aktor]                    int NOT NULL,
    [Waktu]                            datetime NOT NULL CONSTRAINT [DF_N_KPI_Target_Koreksi_Waktu] DEFAULT (getdate()),
    CONSTRAINT [PK_N_KPI_Target_Koreksi] PRIMARY KEY CLUSTERED ([Id_Target_Koreksi])
    );
END;
GO
IF OBJECT_ID('dbo.[N_KPI_Target_Koreksi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_KPI_Target_Koreksi_Det' AND object_id = OBJECT_ID('dbo.[N_KPI_Target_Koreksi]'))
    CREATE NONCLUSTERED INDEX [IX_N_KPI_Target_Koreksi_Det]
        ON dbo.[N_KPI_Target_Koreksi] ([Id_Pengajuan_Det],[Waktu]);
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Section]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Section] (
    [Id_SOP_Section]                   int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Nama_Section]                     nvarchar(200) NOT NULL,
    [Deskripsi]                        nvarchar(500) NULL,
    [Urutan]                           int NOT NULL CONSTRAINT [DF_N_SOP_Paket_Section_Urutan] DEFAULT ((0)),
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Section_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Section_Flag_Aktif] DEFAULT ('Y'),
    CONSTRAINT [PK_N_SOP_Paket_Section] PRIMARY KEY CLUSTERED ([Id_SOP_Section])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Section]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Section_Paket' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Section]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Section_Paket]
        ON dbo.[N_SOP_Paket_Section] ([Id_SOP_Paket],[Urutan]);
GO

IF OBJECT_ID('dbo.[N_SOP_Setting]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Setting] (
    [Id_SOP_Setting]                   int IDENTITY(1,1) NOT NULL,
    [Kode_Setting]                     varchar(60) NOT NULL,
    [Nilai]                            nvarchar(500) NULL,
    [Keterangan]                       nvarchar(500) NULL,
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    CONSTRAINT [PK_N_SOP_Setting] PRIMARY KEY CLUSTERED ([Id_SOP_Setting]),
    CONSTRAINT [UQ_N_SOP_Setting_Kode] UNIQUE ([Kode_Setting])
    );
END;
GO

IF OBJECT_ID('dbo.[N_SOP_Log]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Log] (
    [Id_SOP_Log]                       bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NULL,
    [Id_SOP_Paket_Item]                int NULL,
    [Id_SOP_Item_Versi]                int NULL,
    [Aksi]                             varchar(30) NOT NULL,
    [Kode_Karyawan]                    varchar(20) NULL,
    [Keterangan]                       nvarchar(1000) NULL,
    [Meta_JSON]                        nvarchar(max) NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Log_Created_At] DEFAULT (sysdatetime()),
    [Id_SOP_Penjadwalan]               int NULL,
    [Oleh_Id_Users]                    int NULL,
    [Id_SOP_Dokumen]                   int NULL,
    CONSTRAINT [PK_N_SOP_Log] PRIMARY KEY CLUSTERED ([Id_SOP_Log])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Log]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Log_Dokumen' AND object_id = OBJECT_ID('dbo.[N_SOP_Log]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Log_Dokumen]
        ON dbo.[N_SOP_Log] ([Id_SOP_Dokumen],[Created_At])
        INCLUDE ([Aksi],[Id_SOP_Item_Versi],[Keterangan],[Oleh_Id_Users])
        WHERE ([Id_SOP_Dokumen] IS NOT NULL);
GO
IF OBJECT_ID('dbo.[N_SOP_Log]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Log_Paket' AND object_id = OBJECT_ID('dbo.[N_SOP_Log]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Log_Paket]
        ON dbo.[N_SOP_Log] ([Id_SOP_Paket],[Created_At]);
GO
IF OBJECT_ID('dbo.[N_SOP_Log]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Log_Penjadwalan' AND object_id = OBJECT_ID('dbo.[N_SOP_Log]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Log_Penjadwalan]
        ON dbo.[N_SOP_Log] ([Id_SOP_Penjadwalan],[Created_At])
        WHERE ([Id_SOP_Penjadwalan] IS NOT NULL);
GO

IF OBJECT_ID('dbo.[N_HRIS_Bypass_Cuti_Tutup_Buku]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Bypass_Cuti_Tutup_Buku] (
    [Id_Bypass_Cuti]                   int IDENTITY(1,1) NOT NULL,
    [Kode_Karyawan]                    varchar(50) NOT NULL,
    [Tahun_Target]                     int NOT NULL,
    [Flag_Aktif]                       char(1) NULL CONSTRAINT [DF_N_HRIS_Bypass_Cuti_Tutup_Buku_Flag_Aktif] DEFAULT ('Y'),
    [Keterangan]                       varchar(255) NULL,
    [Created_At]                       datetime NULL CONSTRAINT [DF_N_HRIS_Bypass_Cuti_Tutup_Buku_Created_At] DEFAULT (getdate()),
    [Created_By]                       varchar(50) NULL,
    CONSTRAINT [PK_N_HRIS_Bypass_Cuti_Tutup_Buku] PRIMARY KEY CLUSTERED ([Id_Bypass_Cuti])
    );
END;
GO

IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Kepatuhan_Riwayat] (
    [Id_SOP_Kepatuhan_Riwayat]         bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Penjadwalan_Detail]        int NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Peristiwa]                        varchar(15) NOT NULL,
    [Alasan]                           varchar(15) NULL,
    [Snapshot_JSON]                    nvarchar(max) NULL,
    [Persen_Saat_Itu]                  decimal(5,2) NULL,
    [Waktu_Peristiwa]                  datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Kepatuhan_Riwayat_Waktu_Peristiwa] DEFAULT (sysdatetime()),
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Kepatuhan_Riwayat_Created_At] DEFAULT (sysdatetime()),
    [Sebab]                            varchar(40) NULL,
    [Catatan]                          nvarchar(500) NULL,
    [Aktor_Id]                         int NULL,
    [Aktor_Nama]                       varchar(100) NULL,
    CONSTRAINT [PK_N_SOP_Kepatuhan_Riwayat] PRIMARY KEY CLUSTERED ([Id_SOP_Kepatuhan_Riwayat])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Rwt_Detail' AND object_id = OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Rwt_Detail]
        ON dbo.[N_SOP_Kepatuhan_Riwayat] ([Id_SOP_Penjadwalan_Detail]);
GO
IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Rwt_Detail_Peristiwa' AND object_id = OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Rwt_Detail_Peristiwa]
        ON dbo.[N_SOP_Kepatuhan_Riwayat] ([Id_SOP_Penjadwalan_Detail],[Peristiwa]);
GO
IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Rwt_Karyawan' AND object_id = OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Rwt_Karyawan]
        ON dbo.[N_SOP_Kepatuhan_Riwayat] ([Kode_Karyawan],[Id_SOP_Paket]);
GO
IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Rwt_Waktu' AND object_id = OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Rwt_Waktu]
        ON dbo.[N_SOP_Kepatuhan_Riwayat] ([Waktu_Peristiwa])
        INCLUDE ([Id_SOP_Paket],[Id_SOP_Penjadwalan_Detail],[Kode_Karyawan],[Peristiwa]);
GO
IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SOP_Rwt_Aktor' AND object_id = OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]'))
    CREATE NONCLUSTERED INDEX [IX_SOP_Rwt_Aktor]
        ON dbo.[N_SOP_Kepatuhan_Riwayat] ([Aktor_Id],[Waktu_Peristiwa]);
GO
IF OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SOP_Rwt_Sebab' AND object_id = OBJECT_ID('dbo.[N_SOP_Kepatuhan_Riwayat]'))
    CREATE NONCLUSTERED INDEX [IX_SOP_Rwt_Sebab]
        ON dbo.[N_SOP_Kepatuhan_Riwayat] ([Peristiwa],[Sebab]);
GO

IF OBJECT_ID('dbo.[N_SOP_Coverage_Rollup]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Coverage_Rollup] (
    [Id_SOP_Coverage_Rollup]           int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Id_SOP_Item_Versi]                int NOT NULL,
    [Total_Target]                     int NOT NULL CONSTRAINT [DF_N_SOP_Coverage_Rollup_Total_Target] DEFAULT ((0)),
    [Jumlah_Dibuka]                    int NOT NULL CONSTRAINT [DF_N_SOP_Coverage_Rollup_Jumlah_Dibuka] DEFAULT ((0)),
    [Jumlah_Dikonfirmasi]              int NOT NULL CONSTRAINT [DF_N_SOP_Coverage_Rollup_Jumlah_Dikonfirmasi] DEFAULT ((0)),
    [Persen_Dikonfirmasi]              decimal(5,2) NOT NULL CONSTRAINT [DF_N_SOP_Coverage_Rollup_Persen_Dikonfirmasi] DEFAULT ((0)),
    [Updated_At]                       datetime2(7) NULL,
    CONSTRAINT [PK_N_SOP_Coverage_Rollup] PRIMARY KEY CLUSTERED ([Id_SOP_Coverage_Rollup]),
    CONSTRAINT [UQ_N_SOP_Rollup] UNIQUE ([Id_SOP_Paket],[Id_SOP_Item_Versi])
    );
END;
GO

IF OBJECT_ID('dbo.[N_SOP_Absensi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Absensi] (
    [Id_SOP_Absensi]                   int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Penjadwalan_Detail]        int NOT NULL,
    [Id_SOP_Paket_Item]                int NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Checktime]                        datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Absensi_Checktime] DEFAULT (sysdatetime()),
    [Output]                           varchar(10) NOT NULL,
    [File_Path]                        varchar(1000) NULL,
    [Latitude]                         decimal(10,7) NULL,
    [Longitude]                        decimal(11,7) NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Absensi_Created_At] DEFAULT (sysdatetime()),
    [Akurasi_Meter]                    decimal(9,2) NULL,
    [User_Agent]                       varchar(255) NULL,
    [IP_Address]                       varchar(45) NULL,
    CONSTRAINT [PK_N_SOP_Absensi] PRIMARY KEY CLUSTERED ([Id_SOP_Absensi]),
    CONSTRAINT [UQ_N_SOP_Absensi] UNIQUE ([Id_SOP_Penjadwalan_Detail],[Id_SOP_Paket_Item],[Output])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Absensi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Absensi_Karyawan' AND object_id = OBJECT_ID('dbo.[N_SOP_Absensi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Absensi_Karyawan]
        ON dbo.[N_SOP_Absensi] ([Kode_Karyawan]);
GO

IF OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Kuis_Integrasi] (
    [Id_SOP_Kuis_Integrasi]            int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket_Item]                int NOT NULL,
    [Id_SOP_Progress]                  int NOT NULL,
    [Percobaan_Ke]                     int NOT NULL CONSTRAINT [DF_N_SOP_Kuis_Integrasi_Percobaan_Ke] DEFAULT ((1)),
    [Id_Ujian_Token]                   varchar(150) NULL,
    [Payload_Json]                     nvarchar(max) NULL,
    [Waktu_Mulai]                      datetime2(7) NULL,
    [Waktu_Akhir]                      datetime2(7) NULL,
    [Status]                           varchar(12) NOT NULL CONSTRAINT [DF_N_SOP_Kuis_Integrasi_Status] DEFAULT ('DIBUAT'),
    [Skor_Perolehan]                   decimal(5,2) NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Kuis_Integrasi_Created_At] DEFAULT (sysdatetime()),
    [Updated_At]                       datetime2(7) NULL,
    CONSTRAINT [PK_N_SOP_Kuis_Integrasi] PRIMARY KEY CLUSTERED ([Id_SOP_Kuis_Integrasi])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Integrasi_Item' AND object_id = OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Integrasi_Item]
        ON dbo.[N_SOP_Kuis_Integrasi] ([Id_SOP_Paket_Item]);
GO
IF OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Integrasi_Progress' AND object_id = OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Integrasi_Progress]
        ON dbo.[N_SOP_Kuis_Integrasi] ([Id_SOP_Progress]);
GO
IF OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Integrasi_Token' AND object_id = OBJECT_ID('dbo.[N_SOP_Kuis_Integrasi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Integrasi_Token]
        ON dbo.[N_SOP_Kuis_Integrasi] ([Id_Ujian_Token]);
GO

IF OBJECT_ID('dbo.[N_SOP_Aktivitas_Log]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Aktivitas_Log] (
    [Id_SOP_Aktivitas_Log]             bigint IDENTITY(1,1) NOT NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Id_SOP_Paket_Item]                int NULL,
    [Id_SOP_Item_Versi]                int NULL,
    [Jenis_Aktivitas]                  varchar(10) NOT NULL,
    [Waktu_Mulai]                      datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Aktivitas_Log_Waktu_Mulai] DEFAULT (sysdatetime()),
    [Waktu_Selesai]                    datetime2(7) NULL,
    [Durasi_Detik]                     int NOT NULL CONSTRAINT [DF_N_SOP_Aktivitas_Log_Durasi_Detik] DEFAULT ((0)),
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Aktivitas_Log_Created_At] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_N_SOP_Aktivitas_Log] PRIMARY KEY CLUSTERED ([Id_SOP_Aktivitas_Log])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Aktivitas_Log]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Akt_Karyawan' AND object_id = OBJECT_ID('dbo.[N_SOP_Aktivitas_Log]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Akt_Karyawan]
        ON dbo.[N_SOP_Aktivitas_Log] ([Kode_Karyawan],[Id_SOP_Paket]);
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Versi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Versi] (
    [Id_SOP_Paket_Versi]               bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Versi_Ke]                         int NOT NULL,
    [Status]                           varchar(30) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Versi_Status] DEFAULT ('PUBLISHED'),
    [Trigger_Perubahan]                varchar(30) NULL,
    [Ringkasan_Perubahan]              nvarchar(1000) NULL,
    [Snapshot_Hash]                    char(40) NOT NULL,
    [Snapshot_JSON]                    nvarchar(max) NULL,
    [Item_Total]                       int NOT NULL CONSTRAINT [DF_N_SOP_Paket_Versi_Item_Total] DEFAULT ((0)),
    [Item_Wajib]                       int NOT NULL CONSTRAINT [DF_N_SOP_Paket_Versi_Item_Wajib] DEFAULT ((0)),
    [Id_SOP_Item_Versi_Pemicu]         bigint NULL,
    [Published_By]                     int NULL,
    [Kode_Karyawan_Publisher]          varchar(50) NULL,
    [Tanggal_Publish]                  datetime2(0) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Versi_Tanggal_Publish] DEFAULT (sysdatetime()),
    [Created_At]                       datetime2(0) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Versi_Created_At] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_N_SOP_Paket_Versi] PRIMARY KEY CLUSTERED ([Id_SOP_Paket_Versi])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Versi_Paket_Hash' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Versi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Versi_Paket_Hash]
        ON dbo.[N_SOP_Paket_Versi] ([Id_SOP_Paket],[Snapshot_Hash]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Versi_Paket_Tanggal' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Versi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Versi_Paket_Tanggal]
        ON dbo.[N_SOP_Paket_Versi] ([Id_SOP_Paket],[Tanggal_Publish]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_Paket_Versi_Paket_Versi' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Versi]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_Paket_Versi_Paket_Versi]
        ON dbo.[N_SOP_Paket_Versi] ([Id_SOP_Paket],[Versi_Ke]);
GO

IF OBJECT_ID('dbo.[N_SOP_Prioritas]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Prioritas] (
    [Id_SOP_Prioritas]                 bigint IDENTITY(1,1) NOT NULL,
    [Kode]                             varchar(30) NOT NULL,
    [Nama]                             varchar(60) NOT NULL,
    [Urutan]                           int NOT NULL CONSTRAINT [DF_N_SOP_Prioritas_Urutan] DEFAULT ((100)),
    [Warna]                            varchar(20) NOT NULL CONSTRAINT [DF_N_SOP_Prioritas_Warna] DEFAULT ('#94A3B8'),
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Prioritas_Aktif] DEFAULT ('Y'),
    [Created_By]                       bigint NULL,
    [Created_At]                       datetime NULL,
    [Updated_By]                       bigint NULL,
    [Updated_At]                       datetime NULL,
    CONSTRAINT [PK_N_SOP_Prioritas] PRIMARY KEY CLUSTERED ([Id_SOP_Prioritas]),
    CONSTRAINT [UQ_N_SOP_Prioritas_Kode] UNIQUE ([Kode])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Prioritas]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prioritas_Urutan' AND object_id = OBJECT_ID('dbo.[N_SOP_Prioritas]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prioritas_Urutan]
        ON dbo.[N_SOP_Prioritas] ([Flag_Aktif],[Urutan])
        INCLUDE ([Kode],[Nama],[Warna]);
GO

IF OBJECT_ID('dbo.[N_SOP_Kategori]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Kategori] (
    [Id_Kategori_SOP]                  int IDENTITY(1,1) NOT NULL,
    [Nama_Kategori]                    nvarchar(150) NOT NULL,
    [Deskripsi]                        nvarchar(500) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Kategori_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Kategori_Flag_Aktif] DEFAULT ('Y'),
    CONSTRAINT [PK_N_SOP_Kategori] PRIMARY KEY CLUSTERED ([Id_Kategori_SOP])
    );
END;
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Versi_Item]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Versi_Item] (
    [Id_SOP_Paket_Versi_Item]          bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket_Versi]               bigint NOT NULL,
    [Id_SOP_Paket_Item]                int NOT NULL,
    [Id_SOP_Item_Versi]                bigint NULL,
    [Id_SOP_Section]                   int NULL,
    [Urutan]                           int NULL,
    [Tipe_Item]                        varchar(30) NULL,
    [Judul_Item]                       nvarchar(255) NULL,
    [Wajib]                            char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Versi_Item_Wajib] DEFAULT ('T'),
    [Versi_Item]                       int NULL,
    [Status_Item_Versi]                varchar(30) NULL,
    [Ringkasan_Perubahan]              nvarchar(1000) NULL,
    [Created_At]                       datetime2(0) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Versi_Item_Created_At] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_N_SOP_Paket_Versi_Item] PRIMARY KEY CLUSTERED ([Id_SOP_Paket_Versi_Item])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Versi_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Versi_Item_Header' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Versi_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Versi_Item_Header]
        ON dbo.[N_SOP_Paket_Versi_Item] ([Id_SOP_Paket_Versi],[Urutan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Versi_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Versi_Item_Item' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Versi_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Versi_Item_Item]
        ON dbo.[N_SOP_Paket_Versi_Item] ([Id_SOP_Paket_Item],[Id_SOP_Item_Versi]);
GO

IF OBJECT_ID('dbo.[HRIS_Klasifikasi_Aksi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[HRIS_Klasifikasi_Aksi] (
    [Id_Klasifikasi_Actions]           int IDENTITY(1,1) NOT NULL,
    [Nama_Aksi]                        varchar(255) NULL,
    CONSTRAINT [PK_HRIS_Klasifikasi_Aksi] PRIMARY KEY CLUSTERED ([Id_Klasifikasi_Actions])
    );
END;
GO

IF OBJECT_ID('dbo.[N_SOP_Master_Paket]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Master_Paket] (
    [Id_SOP_Paket]                     int IDENTITY(1,1) NOT NULL,
    [Kode_Paket]                       varchar(50) NULL,
    [Nama_Paket]                       nvarchar(200) NOT NULL,
    [Id_Kategori_SOP]                  int NULL,
    [Kode_Karyawan_Owner]              varchar(20) NULL,
    [Deskripsi]                        nvarchar(1000) NULL,
    [Tujuan_Ruang_Lingkup]             nvarchar(2000) NULL,
    [Prioritas]                        varchar(10) NOT NULL CONSTRAINT [DF_N_SOP_Master_Paket_Prioritas] DEFAULT ('NORMAL'),
    [Cover_Path]                       varchar(500) NULL,
    [Estimasi_Waktu_Baca_Menit]        int NULL,
    [Mode_Urutan_Item]                 varchar(12) NOT NULL CONSTRAINT [DF_N_SOP_Master_Paket_Mode_Urutan_Item] DEFAULT ('BEBAS'),
    [Status]                           varchar(12) NOT NULL CONSTRAINT [DF_N_SOP_Master_Paket_Status] DEFAULT ('DRAFT'),
    [Tanggal_Publish]                  datetime2(7) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Master_Paket_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Master_Paket_Flag_Aktif] DEFAULT ('Y'),
    [Flag_Absensi]                     char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Absensi] DEFAULT ('T'),
    [Cover_Position]                   varchar(20) NULL,
    [Mode_Distribusi]                  varchar(10) NULL CONSTRAINT [DF_N_SOP_Master_Paket_Mode_Distribusi] DEFAULT ('TERBATAS'),
    [Versi_Paket_Aktif]                int NULL,
    [Id_SOP_Paket_Versi_Aktif]         bigint NULL,
    CONSTRAINT [PK_N_SOP_Master_Paket] PRIMARY KEY CLUSTERED ([Id_SOP_Paket])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Master_Paket]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Kategori' AND object_id = OBJECT_ID('dbo.[N_SOP_Master_Paket]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Kategori]
        ON dbo.[N_SOP_Master_Paket] ([Id_Kategori_SOP],[Status]);
GO
IF OBJECT_ID('dbo.[N_SOP_Master_Paket]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Publish' AND object_id = OBJECT_ID('dbo.[N_SOP_Master_Paket]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Publish]
        ON dbo.[N_SOP_Master_Paket] ([Tanggal_Publish]);
GO

IF OBJECT_ID('dbo.[HRIS_Page_Access_2]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[HRIS_Page_Access_2] (
    [Id_Page_Access]                   int IDENTITY(1,1) NOT NULL,
    [Kode_Perusahaan]                  varchar(3) NULL,
    [ID_Level]                         int NULL,
    [ID_Divisi]                        int NULL,
    [Jenis_page]                       varchar(50) NULL,
    [UserID_Web]                       int NULL,
    [Urutan_Menu]                      int NULL,
    [Flag_Tour_Selesai]                char(1) NOT NULL CONSTRAINT [DF_PA2_FlagTourSelesai] DEFAULT ('T'),
    [Tour_Last_Step]                   int NOT NULL CONSTRAINT [DF_PA2_TourLastStep] DEFAULT ((0)),
    [Tour_Selesai_At]                  datetime NULL,
    CONSTRAINT [PK_HRIS_Page_Access_2] PRIMARY KEY CLUSTERED ([Id_Page_Access])
    );
END;
GO

IF OBJECT_ID('dbo.[N_HRIS_Api_Aplikasi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Api_Aplikasi] (
    [Id_Api_Aplikasi]                  int IDENTITY(1,1) NOT NULL,
    [Id_Grup_Fitur]                    int NOT NULL,
    [Nama_Aplikasi]                    nvarchar(150) NOT NULL,
    [Api_Public]                       varchar(100) NOT NULL,
    [Api_Secret]                       varchar(255) NOT NULL,
    [Base_Url]                         varchar(500) NULL,
    [Arah]                             varchar(6) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Aplikasi_Arah] DEFAULT ('BOTH'),
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Aplikasi_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Aplikasi_Flag_Aktif] DEFAULT ('Y'),
    [Kode_Karyawan_PIC]                varchar(20) NULL,
    CONSTRAINT [PK_N_HRIS_Api_Aplikasi] PRIMARY KEY CLUSTERED ([Id_Api_Aplikasi]),
    CONSTRAINT [UQ_N_HRIS_Api_Aplikasi_Public] UNIQUE ([Api_Public])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Aplikasi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Aplikasi_Grup' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Aplikasi]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Aplikasi_Grup]
        ON dbo.[N_HRIS_Api_Aplikasi] ([Id_Grup_Fitur]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Submission]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Pengumpulan_Data_Submission] (
    [Id]                               int IDENTITY(1,1) NOT NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Versi_Skema]                      int NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Submission_Versi_Skema] DEFAULT ((1)),
    [Data_Json]                        nvarchar(max) NOT NULL,
    [Snapshot_Lama_Json]               nvarchar(max) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Submission_Flag_Aktif] DEFAULT ('Y'),
    [Submitted_At]                     datetime NOT NULL,
    [Submitted_Ip]                     varchar(45) NULL,
    [Submitted_User_Agent]             varchar(500) NULL,
    [Created_At]                       datetime NOT NULL,
    [Updated_At]                       datetime NULL,
    CONSTRAINT [PK_N_HRIS_Pengumpulan_Data_Submission] PRIMARY KEY CLUSTERED ([Id])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Submission]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Pengumpulan_Submission_Karyawan' AND object_id = OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Submission]'))
    CREATE NONCLUSTERED INDEX [IX_Pengumpulan_Submission_Karyawan]
        ON dbo.[N_HRIS_Pengumpulan_Data_Submission] ([Kode_Karyawan],[Submitted_At]);
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Submission]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Pengumpulan_Submission_Aktif' AND object_id = OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Submission]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_Pengumpulan_Submission_Aktif]
        ON dbo.[N_HRIS_Pengumpulan_Data_Submission] ([Kode_Karyawan])
        WHERE ([Flag_Aktif]='Y');
GO

IF OBJECT_ID('dbo.[N_HRIS_Api_Endpoint]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Api_Endpoint] (
    [Id_Api_Endpoint]                  int IDENTITY(1,1) NOT NULL,
    [Id_Grup_Fitur]                    int NOT NULL,
    [Url_Route]                        varchar(300) NOT NULL,
    [Method]                           varchar(10) NOT NULL,
    [Arah]                             varchar(3) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Endpoint_Arah] DEFAULT ('IN'),
    [Deskripsi]                        nvarchar(500) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Endpoint_Created_At] DEFAULT (sysdatetime()),
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Endpoint_Flag_Aktif] DEFAULT ('Y'),
    CONSTRAINT [PK_N_HRIS_Api_Endpoint] PRIMARY KEY CLUSTERED ([Id_Api_Endpoint]),
    CONSTRAINT [UQ_N_HRIS_Api_Endpoint_Route] UNIQUE ([Url_Route],[Method])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Endpoint]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Endpoint_Grup' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Endpoint]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Endpoint_Grup]
        ON dbo.[N_HRIS_Api_Endpoint] ([Id_Grup_Fitur]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Status]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Pengumpulan_Data_Status] (
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Status]                           varchar(20) NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Status_Status] DEFAULT ('BELUM'),
    [Jumlah_Tunda]                     int NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Status_Jumlah_Tunda] DEFAULT ((0)),
    [Terakhir_Tunda_At]                datetime NULL,
    [Is_Dikecualikan]                  char(1) NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Status_Is_Dikecualikan] DEFAULT ('T'),
    [Dikecualikan_Oleh]                varchar(50) NULL,
    [Dikecualikan_At]                  datetime NULL,
    [Alasan_Pengecualian]              varchar(500) NULL,
    [Dibuka_Kunci_Oleh]                varchar(50) NULL,
    [Dibuka_Kunci_At]                  datetime NULL,
    [Alasan_Buka_Kunci]                varchar(500) NULL,
    [Draft_Json]                       nvarchar(max) NULL,
    [Draft_Langkah]                    int NULL,
    [Draft_Updated_At]                 datetime NULL,
    [Selesai_At]                       datetime NULL,
    [Created_At]                       datetime NOT NULL,
    [Updated_At]                       datetime NULL,
    [Terakhir_Tampil_At]               datetime NULL,
    [Terakhir_Tampil_Sesi]             varchar(64) NULL,
    CONSTRAINT [PK_N_HRIS_Pengumpulan_Data_Status] PRIMARY KEY CLUSTERED ([Kode_Karyawan])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Status]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Pengumpulan_Status_Filter' AND object_id = OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Status]'))
    CREATE NONCLUSTERED INDEX [IX_Pengumpulan_Status_Filter]
        ON dbo.[N_HRIS_Pengumpulan_Data_Status] ([Status],[Is_Dikecualikan])
        INCLUDE ([Jumlah_Tunda],[Selesai_At]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Api_Akses_Endpoint]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Api_Akses_Endpoint] (
    [Id_Akses_Endpoint]                int IDENTITY(1,1) NOT NULL,
    [Id_Api_Aplikasi]                  int NOT NULL,
    [Id_Api_Endpoint]                  int NOT NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Akses_Endpoint_Created_At] DEFAULT (sysdatetime()),
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Akses_Endpoint_Flag_Aktif] DEFAULT ('Y'),
    CONSTRAINT [PK_N_HRIS_Api_Akses_Endpoint] PRIMARY KEY CLUSTERED ([Id_Akses_Endpoint]),
    CONSTRAINT [UQ_N_HRIS_Api_Akses] UNIQUE ([Id_Api_Aplikasi],[Id_Api_Endpoint])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Akses_Endpoint]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Akses_Endpoint' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Akses_Endpoint]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Akses_Endpoint]
        ON dbo.[N_HRIS_Api_Akses_Endpoint] ([Id_Api_Endpoint]);
GO

IF OBJECT_ID('dbo.[HRIS_Role_Menu_Access]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[HRIS_Role_Menu_Access] (
    [Id_Role_Menu_Access]              int IDENTITY(1,1) NOT NULL,
    [Id_Page_Access]                   int NULL,
    [Id_Aksi]                          int NULL,
    [Flag_Diizinkan]                   char(1) NULL,
    [Flag_Access_Konten]               char(1) NULL,
    CONSTRAINT [PK_HRIS_Role_Menu_Access] PRIMARY KEY CLUSTERED ([Id_Role_Menu_Access])
    );
END;
GO

IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Config]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Pengumpulan_Data_Config] (
    [Id]                               int IDENTITY(1,1) NOT NULL,
    [Is_Aktif]                         char(1) NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Config_Is_Aktif] DEFAULT ('Y'),
    [Judul_Pengumuman]                 varchar(200) NOT NULL,
    [Teks_Pengumuman]                  varchar(1000) NOT NULL,
    [Label_Tombol_Isi]                 varchar(100) NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Config_Label_Tombol_Isi] DEFAULT ('Isi sekarang'),
    [Label_Tombol_Tunda]               varchar(100) NOT NULL CONSTRAINT [DF_N_HRIS_Pengumpulan_Data_Config_Label_Tombol_Tunda] DEFAULT ('Nanti dulu'),
    [Updated_Oleh]                     varchar(50) NULL,
    [Created_At]                       datetime NOT NULL,
    [Updated_At]                       datetime NULL,
    CONSTRAINT [PK_N_HRIS_Pengumpulan_Data_Config] PRIMARY KEY CLUSTERED ([Id])
    );
END;
GO

IF OBJECT_ID('dbo.[N_SOP_Item_Versi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Item_Versi] (
    [Id_SOP_Item_Versi]                int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket_Item]                int NULL,
    [Versi_Ke]                         int NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Versi_Ke] DEFAULT ((1)),
    [Status]                           varchar(12) NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Status] DEFAULT ('DRAFT'),
    [Ringkasan_Perubahan]              nvarchar(2000) NULL,
    [Tanggal_Berlaku]                  date NULL,
    [Tanggal_Kadaluarsa]               date NULL,
    [Tanggal_Review_Berikutnya]        date NULL,
    [Kode_Karyawan_Publisher]          varchar(20) NULL,
    [Tanggal_Publish]                  datetime2(7) NULL,
    [Disahkan_Oleh]                    nvarchar(150) NULL,
    [Jabatan_Pengesah]                 nvarchar(150) NULL,
    [Tanggal_Pengesahan]               date NULL,
    [Nomor_Pengesahan]                 varchar(100) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Flag_Aktif] DEFAULT ('Y'),
    [Id_Master_Konten]                 int NULL,
    [Id_Master_Ujian]                  varchar(200) NULL,
    [Id_SOP_Dokumen]                   bigint NULL,
    [Nama_Ujian]                       nvarchar(255) NULL,
    [Kode_Karyawan_Pengesah]           varchar(20) NULL,
    [Wajib_Baca_Ulang]                 char(1) NOT NULL CONSTRAINT [DF_SOP_Versi_Baca_Ulang] DEFAULT ('Y'),
    [Tahap_Id]                         int NULL,
    [Tanggal_Permintaan]               date NULL,
    [Tanggal_Respon]                   date NULL,
    [Tanggal_Akhir_Approval]           date NULL,
    [Tanggal_Obsolete]                 date NULL,
    CONSTRAINT [PK_N_SOP_Item_Versi] PRIMARY KEY CLUSTERED ([Id_SOP_Item_Versi])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Item_Versi_Dokumen' AND object_id = OBJECT_ID('dbo.[N_SOP_Item_Versi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Item_Versi_Dokumen]
        ON dbo.[N_SOP_Item_Versi] ([Id_SOP_Dokumen],[Versi_Ke]);
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Versi_Item' AND object_id = OBJECT_ID('dbo.[N_SOP_Item_Versi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Versi_Item]
        ON dbo.[N_SOP_Item_Versi] ([Id_SOP_Paket_Item],[Status]);
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SOP_Item_Versi_Tahap' AND object_id = OBJECT_ID('dbo.[N_SOP_Item_Versi]'))
    CREATE NONCLUSTERED INDEX [IX_SOP_Item_Versi_Tahap]
        ON dbo.[N_SOP_Item_Versi] ([Tahap_Id]);
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup' AND object_id = OBJECT_ID('dbo.[N_SOP_Item_Versi]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup]
        ON dbo.[N_SOP_Item_Versi] ([Id_SOP_Dokumen],[Versi_Ke])
        WHERE ([Id_SOP_Dokumen] IS NOT NULL AND [Flag_Aktif]='Y');
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_Item_Versi_Satu_Published' AND object_id = OBJECT_ID('dbo.[N_SOP_Item_Versi]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_Item_Versi_Satu_Published]
        ON dbo.[N_SOP_Item_Versi] ([Id_SOP_Dokumen])
        WHERE ([Id_SOP_Dokumen] IS NOT NULL AND [Status]='PUBLISHED');
GO

IF OBJECT_ID('dbo.[HRIS_Role_Konten_Access]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[HRIS_Role_Konten_Access] (
    [Id_Role_Konten_Access]            int IDENTITY(1,1) NOT NULL,
    [Id_Page_Access]                   int NULL,
    [Id_Jenis_Indikator]               int NULL,
    [Kategori]                         varchar(50) NULL,
    [Flag_Diizinkan]                   char(1) NULL,
    [Id_Grup_Fitur]                    int NULL,
    [Id_Jenis_Soal]                    int NULL,
    CONSTRAINT [PK_HRIS_Role_Konten_Access] PRIMARY KEY CLUSTERED ([Id_Role_Konten_Access])
    );
END;
GO

IF OBJECT_ID('dbo.[N_HRIS_Api_Nonce]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Api_Nonce] (
    [Id_Api_Nonce]                     bigint IDENTITY(1,1) NOT NULL,
    [Nonce]                            varchar(100) NOT NULL,
    [Id_Api_Aplikasi]                  int NOT NULL,
    [Expired_At]                       datetime2(7) NOT NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Nonce_Created_At] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_N_HRIS_Api_Nonce] PRIMARY KEY CLUSTERED ([Id_Api_Nonce]),
    CONSTRAINT [UQ_N_HRIS_Api_Nonce] UNIQUE ([Nonce])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Nonce]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Nonce_Aplikasi' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Nonce]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Nonce_Aplikasi]
        ON dbo.[N_HRIS_Api_Nonce] ([Id_Api_Aplikasi],[Expired_At]);
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Nonce]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Nonce_Expired' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Nonce]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Nonce_Expired]
        ON dbo.[N_HRIS_Api_Nonce] ([Expired_At]);
GO

IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Integrasi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Penjadwalan_Integrasi] (
    [Id_SOP_Penjadwalan_Integrasi]     int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Penjadwalan_Detail]        int NOT NULL,
    [Id_SOP_Paket_Item]                int NOT NULL,
    [Id_SOP_Item_Versi]                int NOT NULL,
    [Id_Master_Ujian]                  varchar(200) NOT NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Id_Ujian_Token]                   varchar(100) NULL,
    [Payload_Json]                     nvarchar(max) NULL,
    [Waktu_Mulai]                      datetime2(7) NULL,
    [Waktu_Akhir]                      datetime2(7) NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Integrasi_Created_At] DEFAULT (sysdatetime()),
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Integrasi_Flag_Aktif] DEFAULT ('Y'),
    CONSTRAINT [PK_N_SOP_Penjadwalan_Integrasi] PRIMARY KEY CLUSTERED ([Id_SOP_Penjadwalan_Integrasi]),
    CONSTRAINT [UQ_N_SOP_Penjadwalan_Integrasi] UNIQUE ([Id_SOP_Penjadwalan_Detail],[Id_SOP_Paket_Item],[Id_SOP_Item_Versi])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Integrasi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Penj_Integrasi_Detail' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan_Integrasi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Penj_Integrasi_Detail]
        ON dbo.[N_SOP_Penjadwalan_Integrasi] ([Id_SOP_Penjadwalan_Detail],[Id_SOP_Paket_Item]);
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Integrasi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Penj_Integrasi_Token' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan_Integrasi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Penj_Integrasi_Token]
        ON dbo.[N_SOP_Penjadwalan_Integrasi] ([Id_Ujian_Token]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Audit]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Pengumpulan_Data_Audit] (
    [Id]                               int IDENTITY(1,1) NOT NULL,
    [Aksi]                             varchar(40) NOT NULL,
    [Kode_Karyawan_Target]             varchar(20) NULL,
    [Dilakukan_Oleh]                   varchar(50) NOT NULL,
    [Nilai_Sebelum]                    varchar(200) NULL,
    [Nilai_Sesudah]                    varchar(200) NULL,
    [Keterangan]                       varchar(500) NULL,
    [Ip_Address]                       varchar(45) NULL,
    [Created_At]                       datetime NOT NULL,
    CONSTRAINT [PK_N_HRIS_Pengumpulan_Data_Audit] PRIMARY KEY CLUSTERED ([Id])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Audit]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Pengumpulan_Audit_Target' AND object_id = OBJECT_ID('dbo.[N_HRIS_Pengumpulan_Data_Audit]'))
    CREATE NONCLUSTERED INDEX [IX_Pengumpulan_Audit_Target]
        ON dbo.[N_HRIS_Pengumpulan_Data_Audit] ([Kode_Karyawan_Target],[Created_At]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Api_Log]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Api_Log] (
    [Id_Api_Log]                       bigint IDENTITY(1,1) NOT NULL,
    [Id_Api_Aplikasi]                  int NULL,
    [Id_Grup_Fitur]                    int NULL,
    [Arah]                             varchar(3) NULL,
    [Sumber_Error]                     varchar(12) NULL,
    [Endpoint]                         varchar(300) NULL,
    [Http_Method]                      varchar(10) NULL,
    [Status_Code]                      int NULL,
    [Signature_Valid]                  char(1) NULL,
    [Correlation_Id]                   varchar(100) NULL,
    [Durasi_Ms]                        int NULL,
    [Payload_Hash]                     varchar(128) NULL,
    [Request_Ringkas]                  nvarchar(max) NULL,
    [Response_Ringkas]                 nvarchar(max) NULL,
    [Error_Message]                    nvarchar(1000) NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_HRIS_Api_Log_Created_At] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_N_HRIS_Api_Log] PRIMARY KEY CLUSTERED ([Id_Api_Log])
    );
END;
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Log]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Log_Aplikasi' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Log]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Log_Aplikasi]
        ON dbo.[N_HRIS_Api_Log] ([Id_Api_Aplikasi],[Created_At]);
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Log]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Log_Correlation' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Log]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Log_Correlation]
        ON dbo.[N_HRIS_Api_Log] ([Correlation_Id]);
GO
IF OBJECT_ID('dbo.[N_HRIS_Api_Log]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_HRIS_Api_Log_Created' AND object_id = OBJECT_ID('dbo.[N_HRIS_Api_Log]'))
    CREATE NONCLUSTERED INDEX [IX_N_HRIS_Api_Log_Created]
        ON dbo.[N_HRIS_Api_Log] ([Created_At]);
GO

IF OBJECT_ID('dbo.[N_SOP_Item_Versi_Berkas]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Item_Versi_Berkas] (
    [Id_SOP_Item_Versi_Berkas]         int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Item_Versi]                int NOT NULL,
    [Media_Key]                        varchar(64) NOT NULL,
    [Nama_Berkas]                      nvarchar(300) NULL,
    [Type_File]                        varchar(150) NULL,
    [Ukuran_Byte]                      bigint NULL,
    [Storage_Provider]                 varchar(10) NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Berkas_Storage_Provider] DEFAULT ('gcs'),
    [File_Path]                        varchar(1000) NULL,
    [Provider_Media_Id]                varchar(150) NULL,
    [Status_Render]                    varchar(12) NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Berkas_Status_Render] DEFAULT ('PENDING'),
    [Total_Halaman]                    int NULL,
    [Durasi_Detik]                     int NULL,
    [Error_Message]                    nvarchar(2000) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Berkas_Flag_Aktif] DEFAULT ('Y'),
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Item_Versi_Berkas_Created_At] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_N_SOP_Item_Versi_Berkas] PRIMARY KEY CLUSTERED ([Id_SOP_Item_Versi_Berkas])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Item_Versi_Berkas]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Berkas_Versi' AND object_id = OBJECT_ID('dbo.[N_SOP_Item_Versi_Berkas]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Berkas_Versi]
        ON dbo.[N_SOP_Item_Versi_Berkas] ([Id_SOP_Item_Versi]);
GO

IF OBJECT_ID('dbo.[N_HRIS_Master_Konversi_Lembur]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_HRIS_Master_Konversi_Lembur] (
    [Id]                               int IDENTITY(1,1) NOT NULL,
    [Kode_Konversi]                    varchar(10) NULL,
    [Jam_Minimal]                      int NOT NULL,
    [Jumlah_Hari_Off]                  int NOT NULL,
    [Aktif]                            char(1) NULL CONSTRAINT [DF_N_HRIS_Master_Konversi_Lembur_Aktif] DEFAULT ('Y'),
    CONSTRAINT [PK_N_HRIS_Master_Konversi_Lembur] PRIMARY KEY CLUSTERED ([Id])
    );
END;
GO

IF OBJECT_ID('dbo.[N_SOP_Favorit]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Favorit] (
    [Id_SOP_Favorit]                   int IDENTITY(1,1) NOT NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Favorit_Created] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_N_SOP_Favorit] PRIMARY KEY CLUSTERED ([Id_SOP_Favorit]),
    CONSTRAINT [UQ_N_SOP_Favorit] UNIQUE ([Kode_Karyawan],[Id_SOP_Paket])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Favorit]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Favorit_Karyawan' AND object_id = OBJECT_ID('dbo.[N_SOP_Favorit]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Favorit_Karyawan]
        ON dbo.[N_SOP_Favorit] ([Kode_Karyawan],[Created_At]);
GO

IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Progress] (
    [Id_SOP_Progress]                  int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Penjadwalan_Detail]        int NOT NULL,
    [Id_SOP_Paket_Item]                int NOT NULL,
    [Id_SOP_Item_Versi]                int NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Tipe_Item]                        varchar(12) NOT NULL,
    [Status]                           varchar(12) NOT NULL CONSTRAINT [DF_N_SOP_Progress_Status] DEFAULT ('BELUM'),
    [Halaman_Terakhir]                 int NULL,
    [Halaman_Dibaca_JSON]              nvarchar(max) NULL,
    [Persen_Selesai]                   decimal(5,2) NULL,
    [Durasi_Aktif_Detik]               int NOT NULL CONSTRAINT [DF_N_SOP_Progress_Durasi_Aktif_Detik] DEFAULT ((0)),
    [Waktu_Buka]                       datetime2(7) NULL,
    [Waktu_Konfirmasi]                 datetime2(7) NULL,
    [Skor_Quiz]                        decimal(5,2) NULL,
    [Jumlah_Percobaan]                 int NOT NULL CONSTRAINT [DF_N_SOP_Progress_Jumlah_Percobaan] DEFAULT ((0)),
    [Status_Lulus]                     char(1) NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Progress_Created_At] DEFAULT (sysdatetime()),
    [Updated_At]                       datetime2(7) NULL,
    [Konfirmasi_IP]                    varchar(45) NULL,
    [Konfirmasi_User_Agent]            varchar(255) NULL,
    [Id_Ujian_Token_Hasil]             varchar(100) NULL,
    [Id_Versi_Asal]                    int NULL,
    CONSTRAINT [PK_N_SOP_Progress] PRIMARY KEY CLUSTERED ([Id_SOP_Progress]),
    CONSTRAINT [UQ_N_SOP_Progress] UNIQUE ([Id_SOP_Penjadwalan_Detail],[Id_SOP_Paket_Item],[Id_SOP_Item_Versi])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prg_Detail' AND object_id = OBJECT_ID('dbo.[N_SOP_Progress]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prg_Detail]
        ON dbo.[N_SOP_Progress] ([Id_SOP_Penjadwalan_Detail]);
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prg_Detail_Status' AND object_id = OBJECT_ID('dbo.[N_SOP_Progress]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prg_Detail_Status]
        ON dbo.[N_SOP_Progress] ([Id_SOP_Penjadwalan_Detail],[Status])
        INCLUDE ([Id_SOP_Item_Versi],[Id_SOP_Paket_Item],[Waktu_Buka],[Waktu_Konfirmasi]);
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prg_Item' AND object_id = OBJECT_ID('dbo.[N_SOP_Progress]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prg_Item]
        ON dbo.[N_SOP_Progress] ([Id_SOP_Paket_Item]);
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prg_Item_Status' AND object_id = OBJECT_ID('dbo.[N_SOP_Progress]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prg_Item_Status]
        ON dbo.[N_SOP_Progress] ([Id_SOP_Paket_Item],[Status])
        INCLUDE ([Id_SOP_Item_Versi],[Kode_Karyawan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prg_Karyawan' AND object_id = OBJECT_ID('dbo.[N_SOP_Progress]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prg_Karyawan]
        ON dbo.[N_SOP_Progress] ([Kode_Karyawan],[Status]);
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prg_Versi' AND object_id = OBJECT_ID('dbo.[N_SOP_Progress]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prg_Versi]
        ON dbo.[N_SOP_Progress] ([Id_SOP_Item_Versi],[Status]);
GO
IF OBJECT_ID('dbo.[N_SOP_Progress]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Prg_Waktu_Buka' AND object_id = OBJECT_ID('dbo.[N_SOP_Progress]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Prg_Waktu_Buka]
        ON dbo.[N_SOP_Progress] ([Waktu_Buka])
        INCLUDE ([Durasi_Aktif_Detik],[Id_SOP_Paket_Item],[Kode_Karyawan])
        WHERE ([Waktu_Buka] IS NOT NULL);
GO

IF OBJECT_ID('dbo.[N_SOP_Assignment_Rule]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Assignment_Rule] (
    [Id_SOP_Assignment_Rule]           int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NULL,
    [Nama_Aturan]                      nvarchar(150) NOT NULL,
    [Status_Aturan]                    varchar(16) NOT NULL CONSTRAINT [DF_N_SOP_Rule_Status] DEFAULT ('DRAFT'),
    [Draft_Config_JSON]                nvarchar(max) NOT NULL,
    [Draft_Hash]                       char(64) NOT NULL,
    [Active_Config_JSON]               nvarchar(max) NULL,
    [Active_Hash]                      char(64) NULL,
    [Last_Apply_Status]                varchar(20) NOT NULL CONSTRAINT [DF_N_SOP_Rule_Apply] DEFAULT ('IDLE'),
    [Last_Apply_Error]                 nvarchar(1000) NULL,
    [Last_Applied_At]                  datetime2(7) NULL,
    [Last_Scanned_At]                  datetime2(7) NULL,
    [Activated_At]                     datetime2(7) NULL,
    [Deactivated_At]                   datetime2(7) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Rule_Created] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Rule_Flag] DEFAULT ('Y'),
    CONSTRAINT [PK_N_SOP_Assignment_Rule] PRIMARY KEY CLUSTERED ([Id_SOP_Assignment_Rule])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Assignment_Rule]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Rule_Status' AND object_id = OBJECT_ID('dbo.[N_SOP_Assignment_Rule]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Rule_Status]
        ON dbo.[N_SOP_Assignment_Rule] ([Status_Aturan],[Flag_Aktif],[Id_SOP_Paket]);
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Draft]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Draft] (
    [Id_SOP_Paket_Draft]               bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Id_SOP_Paket_Versi_Basis]         bigint NULL,
    [Kode_Paket]                       varchar(100) NULL,
    [Nama_Paket]                       nvarchar(255) NOT NULL,
    [Id_Kategori_SOP]                  int NULL,
    [Kode_Karyawan_Owner]              varchar(50) NULL,
    [Deskripsi]                        nvarchar(max) NULL,
    [Tujuan_Ruang_Lingkup]             nvarchar(max) NULL,
    [Prioritas]                        varchar(30) NULL,
    [Estimasi_Waktu_Baca_Menit]        int NULL,
    [Mode_Urutan_Item]                 varchar(30) NULL,
    [Mode_Distribusi]                  varchar(30) NULL,
    [Cover_Path]                       nvarchar(1000) NULL,
    [Cover_Position]                   varchar(50) NULL,
    [Flag_Absensi]                     char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Flag_Absensi] DEFAULT ('T'),
    [Status]                           varchar(30) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Status] DEFAULT ('DRAFT'),
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(0) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(0) NULL,
    [Published_By]                     int NULL,
    [Tanggal_Publish]                  datetime2(0) NULL,
    [Row_Version]                      timestamp NOT NULL,
    CONSTRAINT [PK_N_SOP_Paket_Draft] PRIMARY KEY CLUSTERED ([Id_SOP_Paket_Draft])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Draft_Paket_Status' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Paket_Status]
        ON dbo.[N_SOP_Paket_Draft] ([Id_SOP_Paket],[Status],[Updated_At]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_Paket_Draft_Working' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_Paket_Draft_Working]
        ON dbo.[N_SOP_Paket_Draft] ([Id_SOP_Paket])
        WHERE ([Status]='DRAFT');
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Section]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Draft_Section] (
    [Id_SOP_Draft_Section]             bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket_Draft]               bigint NOT NULL,
    [Id_SOP_Section_Asal]              int NULL,
    [Nama_Section]                     nvarchar(255) NOT NULL,
    [Deskripsi]                        nvarchar(max) NULL,
    [Urutan]                           int NOT NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Section_Flag_Aktif] DEFAULT ('Y'),
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(0) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Section_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(0) NULL,
    [Row_Version]                      timestamp NOT NULL,
    CONSTRAINT [PK_N_SOP_Paket_Draft_Section] PRIMARY KEY CLUSTERED ([Id_SOP_Draft_Section])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Section]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Draft_Section_Order' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Section]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Section_Order]
        ON dbo.[N_SOP_Paket_Draft_Section] ([Id_SOP_Paket_Draft],[Flag_Aktif],[Urutan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Section]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_Paket_Draft_Section_Source' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Section]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_Paket_Draft_Section_Source]
        ON dbo.[N_SOP_Paket_Draft_Section] ([Id_SOP_Paket_Draft],[Id_SOP_Section_Asal])
        WHERE ([Id_SOP_Section_Asal] IS NOT NULL);
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Draft_Item] (
    [Id_SOP_Draft_Item]                bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket_Draft]               bigint NOT NULL,
    [Id_SOP_Paket_Item_Asal]           int NULL,
    [Id_SOP_Draft_Section]             bigint NULL,
    [Id_SOP_Item_Versi_Target]         bigint NULL,
    [Tipe_Item]                        varchar(30) NOT NULL,
    [Judul_Item]                       nvarchar(255) NOT NULL,
    [Nomor_SOP_Resmi]                  varchar(100) NULL,
    [Deskripsi_Item]                   nvarchar(max) NULL,
    [Konten_Teks]                      nvarchar(max) NULL,
    [Mode_Akses]                       varchar(30) NULL,
    [URL_Tautan]                       nvarchar(2000) NULL,
    [Label_Tautan]                     nvarchar(255) NULL,
    [Jenis_Quiz]                       varchar(50) NULL,
    [Nilai_Minimum]                    decimal(5,2) NULL,
    [Id_Master_Ujian]                  varchar(200) NULL,
    [Nama_Ujian]                       nvarchar(255) NULL,
    [Flag_Camera]                      char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Flag_Camera] DEFAULT ('T'),
    [Urutan]                           int NOT NULL,
    [Flag_Wajib]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Flag_Wajib] DEFAULT ('Y'),
    [Flag_Highlight]                   char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Flag_Highlight] DEFAULT ('T'),
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Flag_Aktif] DEFAULT ('Y'),
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(0) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(0) NULL,
    [Row_Version]                      timestamp NOT NULL,
    [Flag_Watermark]                   char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Flag_Watermark] DEFAULT ('Y'),
    [Id_SOP_Dokumen]                   bigint NULL,
    CONSTRAINT [PK_N_SOP_Paket_Draft_Item] PRIMARY KEY CLUSTERED ([Id_SOP_Draft_Item])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Draft_Item_Dokumen' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Item_Dokumen]
        ON dbo.[N_SOP_Paket_Draft_Item] ([Id_SOP_Dokumen])
        WHERE ([Id_SOP_Dokumen] IS NOT NULL);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Draft_Item_Order' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Item_Order]
        ON dbo.[N_SOP_Paket_Draft_Item] ([Id_SOP_Paket_Draft],[Flag_Aktif],[Urutan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Draft_Item_Target_Versi' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Item_Target_Versi]
        ON dbo.[N_SOP_Paket_Draft_Item] ([Id_SOP_Item_Versi_Target])
        WHERE ([Id_SOP_Item_Versi_Target] IS NOT NULL);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_Paket_Draft_Item_Source' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_Paket_Draft_Item_Source]
        ON dbo.[N_SOP_Paket_Draft_Item] ([Id_SOP_Paket_Draft],[Id_SOP_Paket_Item_Asal])
        WHERE ([Id_SOP_Paket_Item_Asal] IS NOT NULL);
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item_Versi]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Draft_Item_Versi] (
    [Id_SOP_Draft_Item_Versi]          bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Draft_Item]                bigint NOT NULL,
    [Id_SOP_Item_Versi_Asal]           bigint NULL,
    [Versi_Ke]                         int NOT NULL,
    [Status]                           varchar(30) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Versi_Status] DEFAULT ('DRAFT'),
    [Id_Master_Konten]                 bigint NULL,
    [Id_Master_Ujian]                  varchar(200) NULL,
    [Ringkasan_Perubahan]              nvarchar(1000) NULL,
    [Tanggal_Berlaku]                  date NULL,
    [Tanggal_Kadaluarsa]               date NULL,
    [Tanggal_Review_Berikutnya]        date NULL,
    [Disahkan_Oleh]                    nvarchar(255) NULL,
    [Jabatan_Pengesah]                 nvarchar(255) NULL,
    [Tanggal_Pengesahan]               date NULL,
    [Nomor_Pengesahan]                 nvarchar(255) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(0) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Draft_Item_Versi_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(0) NULL,
    [Row_Version]                      timestamp NOT NULL,
    CONSTRAINT [PK_N_SOP_Paket_Draft_Item_Versi] PRIMARY KEY CLUSTERED ([Id_SOP_Draft_Item_Versi])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Draft_Item_Versi_Status' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item_Versi]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Item_Versi_Status]
        ON dbo.[N_SOP_Paket_Draft_Item_Versi] ([Id_SOP_Draft_Item],[Status],[Versi_Ke]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item_Versi]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_Paket_Draft_Item_Versi_Urut' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Draft_Item_Versi]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_Paket_Draft_Item_Versi_Urut]
        ON dbo.[N_SOP_Paket_Draft_Item_Versi] ([Id_SOP_Draft_Item],[Versi_Ke]);
GO

IF OBJECT_ID('dbo.[N_SOP_Paket_Item]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Paket_Item] (
    [Id_SOP_Paket_Item]                int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Tipe_Item]                        varchar(12) NOT NULL,
    [Judul_Item]                       nvarchar(300) NOT NULL,
    [Nomor_SOP_Resmi]                  varchar(60) NULL,
    [Deskripsi_Item]                   nvarchar(1000) NULL,
    [Konten_Teks]                      nvarchar(max) NULL,
    [Mode_Akses]                       varchar(12) NULL,
    [URL_Tautan]                       varchar(1000) NULL,
    [Label_Tautan]                     nvarchar(300) NULL,
    [HC_Learn_Ref]                     varchar(100) NULL,
    [Jenis_Quiz]                       varchar(10) NULL,
    [Nilai_Minimum]                    decimal(5,2) NULL,
    [Urutan]                           int NOT NULL CONSTRAINT [DF_N_SOP_Paket_Item_Urutan] DEFAULT ((0)),
    [Flag_Wajib]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Item_Flag_Wajib] DEFAULT ('Y'),
    [Flag_Highlight]                   char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Item_Flag_Highlight] DEFAULT ('T'),
    [Id_Item_Versi_Aktif]              int NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Item_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Item_Flag_Aktif] DEFAULT ('Y'),
    [Id_SOP_Section]                   int NULL,
    [Id_Master_Ujian]                  varchar(200) NULL,
    [Nama_Ujian]                       nvarchar(200) NULL,
    [Flag_Camera]                      char(1) NULL,
    [Flag_Watermark]                   char(1) NOT NULL CONSTRAINT [DF_N_SOP_Paket_Item_Flag_Watermark] DEFAULT ('Y'),
    [Id_SOP_Dokumen]                   bigint NULL,
    CONSTRAINT [PK_N_SOP_Paket_Item] PRIMARY KEY CLUSTERED ([Id_SOP_Paket_Item])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Item_Paket' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Item_Paket]
        ON dbo.[N_SOP_Paket_Item] ([Id_SOP_Paket],[Urutan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Item_Section' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Item_Section]
        ON dbo.[N_SOP_Paket_Item] ([Id_SOP_Section],[Urutan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Item_VersiAktif' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Item_VersiAktif]
        ON dbo.[N_SOP_Paket_Item] ([Id_Item_Versi_Aktif]);
GO
IF OBJECT_ID('dbo.[N_SOP_Paket_Item]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Paket_Item_Dokumen' AND object_id = OBJECT_ID('dbo.[N_SOP_Paket_Item]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Item_Dokumen]
        ON dbo.[N_SOP_Paket_Item] ([Id_SOP_Dokumen],[Flag_Aktif])
        INCLUDE ([Id_Item_Versi_Aktif],[Id_SOP_Paket]);
GO

IF OBJECT_ID('dbo.[N_SOP_Assignment_Rule_Paket]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Assignment_Rule_Paket] (
    [Id_SOP_Assignment_Rule_Paket]     int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Assignment_Rule]           int NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Urutan]                           int NOT NULL CONSTRAINT [DF_SOP_ARP_Urutan] DEFAULT ((1)),
    [Flag_Override_Jadwal]             char(1) NOT NULL CONSTRAINT [DF_SOP_ARP_Override] DEFAULT ('T'),
    [Deadline_Mode]                    varchar(10) NULL,
    [Deadline_Hari]                    int NULL,
    [Tanggal_Deadline]                 date NULL,
    [Flag_Reminder]                    char(1) NULL,
    [Irama_Reminder_Hari]              int NULL,
    [Last_Apply_Status]                varchar(20) NOT NULL CONSTRAINT [DF_SOP_ARP_ApplyStatus] DEFAULT ('IDLE'),
    [Last_Apply_Error]                 nvarchar(1000) NULL,
    [Last_Applied_At]                  datetime2(7) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_SOP_ARP_CreatedAt] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_SOP_ARP_FlagAktif] DEFAULT ('Y'),
    CONSTRAINT [PK_SOP_Assignment_Rule_Paket] PRIMARY KEY CLUSTERED ([Id_SOP_Assignment_Rule_Paket])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Assignment_Rule_Paket]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SOP_Assignment_Rule_Paket_Rule' AND object_id = OBJECT_ID('dbo.[N_SOP_Assignment_Rule_Paket]'))
    CREATE NONCLUSTERED INDEX [IX_SOP_Assignment_Rule_Paket_Rule]
        ON dbo.[N_SOP_Assignment_Rule_Paket] ([Id_SOP_Assignment_Rule],[Urutan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Assignment_Rule_Paket]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_SOP_Assignment_Rule_Paket_Paket' AND object_id = OBJECT_ID('dbo.[N_SOP_Assignment_Rule_Paket]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_SOP_Assignment_Rule_Paket_Paket]
        ON dbo.[N_SOP_Assignment_Rule_Paket] ([Id_SOP_Paket])
        WHERE ([Flag_Aktif]='Y');
GO

IF OBJECT_ID('dbo.[N_SOP_Penjadwalan]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Penjadwalan] (
    [Id_SOP_Penjadwalan]               int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Paket]                     int NOT NULL,
    [Sumber]                           varchar(12) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Sumber] DEFAULT ('MANUAL'),
    [Tipe_Tenggat]                     varchar(16) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Tipe_Tenggat] DEFAULT ('SAMPAI_DICABUT'),
    [Tanggal_Deadline]                 datetime2(7) NULL,
    [Flag_Reminder]                    char(1) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Flag_Reminder] DEFAULT ('T'),
    [Irama_Reminder_Hari]              int NULL,
    [Flag_Cabut_Akses]                 char(1) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Flag_Cabut_Akses] DEFAULT ('T'),
    [Tanggal_Assign]                   datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Tanggal_Assign] DEFAULT (sysdatetime()),
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Flag_Aktif] DEFAULT ('Y'),
    [Nama_Batch]                       nvarchar(150) NULL,
    [Keterangan]                       nvarchar(500) NULL,
    [Id_SOP_Assignment_Rule]           int NULL,
    CONSTRAINT [PK_N_SOP_Penjadwalan] PRIMARY KEY CLUSTERED ([Id_SOP_Penjadwalan])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Pjd_Deadline' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Pjd_Deadline]
        ON dbo.[N_SOP_Penjadwalan] ([Flag_Cabut_Akses],[Tanggal_Deadline]);
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Pjd_Paket' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Pjd_Paket]
        ON dbo.[N_SOP_Penjadwalan] ([Id_SOP_Paket]);
GO

IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Penjadwalan_Detail] (
    [Id_SOP_Penjadwalan_Detail]        int IDENTITY(1,1) NOT NULL,
    [Id_SOP_Penjadwalan]               int NOT NULL,
    [Kode_Karyawan]                    varchar(20) NOT NULL,
    [Status_Paket]                     varchar(10) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Detail_Status_Paket] DEFAULT ('BELUM'),
    [Flag_Cabut_Akses]                 char(1) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Detail_Flag_Cabut_Akses] DEFAULT ('T'),
    [Tanggal_Selesai]                  datetime2(7) NULL,
    [Created_By]                       int NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Detail_Created_At] DEFAULT (sysdatetime()),
    [Updated_By]                       int NULL,
    [Updated_At]                       datetime2(7) NULL,
    [Flag_Aktif]                       char(1) NOT NULL CONSTRAINT [DF_N_SOP_Penjadwalan_Detail_Flag_Aktif] DEFAULT ('Y'),
    [Id_SOP_Assignment_Rule]           int NULL,
    [Terakhir_Diingatkan_At]           datetime2(7) NULL,
    [Jumlah_Reminder]                  int NOT NULL CONSTRAINT [DF_N_SOP_PjdD_Reminder_Count] DEFAULT ((0)),
    CONSTRAINT [PK_N_SOP_Penjadwalan_Detail] PRIMARY KEY CLUSTERED ([Id_SOP_Penjadwalan_Detail])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_PjdD_Karyawan' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_PjdD_Karyawan]
        ON dbo.[N_SOP_Penjadwalan_Detail] ([Kode_Karyawan],[Status_Paket]);
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_PjdD_Karyawan_Aktif' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_PjdD_Karyawan_Aktif]
        ON dbo.[N_SOP_Penjadwalan_Detail] ([Kode_Karyawan],[Flag_Aktif],[Flag_Cabut_Akses])
        INCLUDE ([Created_At],[Id_SOP_Penjadwalan],[Status_Paket],[Tanggal_Selesai],[Updated_At]);
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_PjdD_Pjd' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_PjdD_Pjd]
        ON dbo.[N_SOP_Penjadwalan_Detail] ([Id_SOP_Penjadwalan]);
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_PjdD_Rule' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_PjdD_Rule]
        ON dbo.[N_SOP_Penjadwalan_Detail] ([Id_SOP_Assignment_Rule],[Flag_Cabut_Akses],[Kode_Karyawan])
        WHERE ([Id_SOP_Assignment_Rule] IS NOT NULL);
GO
IF OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_N_SOP_PjdD_Aktif' AND object_id = OBJECT_ID('dbo.[N_SOP_Penjadwalan_Detail]'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_N_SOP_PjdD_Aktif]
        ON dbo.[N_SOP_Penjadwalan_Detail] ([Id_SOP_Penjadwalan],[Kode_Karyawan])
        WHERE ([Flag_Aktif]='Y' AND [Flag_Cabut_Akses]='T');
GO

IF OBJECT_ID('dbo.[N_SOP_Automation_Run]', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Automation_Run] (
    [Id_SOP_Automation_Run]            bigint IDENTITY(1,1) NOT NULL,
    [Id_SOP_Assignment_Rule]           int NULL,
    [Tipe_Run]                         varchar(20) NOT NULL,
    [Trigger_Run]                      varchar(16) NOT NULL,
    [Status_Run]                       varchar(16) NOT NULL CONSTRAINT [DF_N_SOP_Run_Status] DEFAULT ('QUEUED'),
    [Config_Hash]                      char(64) NULL,
    [Total_Ditemukan]                  int NOT NULL CONSTRAINT [DF_N_SOP_Run_Found] DEFAULT ((0)),
    [Total_Ditambahkan]                int NOT NULL CONSTRAINT [DF_N_SOP_Run_Added] DEFAULT ((0)),
    [Total_Dipertahankan]              int NOT NULL CONSTRAINT [DF_N_SOP_Run_Kept] DEFAULT ((0)),
    [Total_Dicabut]                    int NOT NULL CONSTRAINT [DF_N_SOP_Run_Released] DEFAULT ((0)),
    [Total_Dilewati]                   int NOT NULL CONSTRAINT [DF_N_SOP_Run_Skipped] DEFAULT ((0)),
    [Hasil_JSON]                       nvarchar(max) NULL,
    [Error_Message]                    nvarchar(2000) NULL,
    [Requested_By]                     int NULL,
    [Queued_At]                        datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Run_Queued] DEFAULT (sysdatetime()),
    [Started_At]                       datetime2(7) NULL,
    [Completed_At]                     datetime2(7) NULL,
    [Created_At]                       datetime2(7) NOT NULL CONSTRAINT [DF_N_SOP_Run_Created] DEFAULT (sysdatetime()),
    [Id_SOP_Paket]                     int NULL,
    [Id_Run_Induk]                     int NULL,
    CONSTRAINT [PK_N_SOP_Automation_Run] PRIMARY KEY CLUSTERED ([Id_SOP_Automation_Run])
    );
END;
GO
IF OBJECT_ID('dbo.[N_SOP_Automation_Run]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_N_SOP_Run_History' AND object_id = OBJECT_ID('dbo.[N_SOP_Automation_Run]'))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Run_History]
        ON dbo.[N_SOP_Automation_Run] ([Created_At],[Tipe_Run],[Status_Run]);
GO
IF OBJECT_ID('dbo.[N_SOP_Automation_Run]', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SOP_Automation_Run_Induk' AND object_id = OBJECT_ID('dbo.[N_SOP_Automation_Run]'))
    CREATE NONCLUSTERED INDEX [IX_SOP_Automation_Run_Induk]
        ON dbo.[N_SOP_Automation_Run] ([Id_Run_Induk])
        WHERE ([Id_Run_Induk] IS NOT NULL);
GO


/* ==========================================================================
   BAGIAN 6  TRIGGER
   ========================================================================== */

IF OBJECT_ID('dbo.[KPI_Approval_Request_Performance]','U') IS NOT NULL
    EXEC sp_executesql N'CREATE OR ALTER TRIGGER KPI_Approval_Request_Performance_trg_UpdateTimestamp
ON KPI_Approval_Request_Performance
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE KPI_Approval_Request_Performance
    SET update_at = GETDATE()
    FROM KPI_Approval_Request_Performance t
    INNER JOIN inserted i ON t.Id_Approval_Request = i.Id_Approval_Request;
END';
GO

IF OBJECT_ID('dbo.[KPI_Karyawan_Team]','U') IS NOT NULL
    EXEC sp_executesql N'CREATE OR ALTER TRIGGER KPI_Karyawan_Team_trg_UpdateTimestamp
ON KPI_Karyawan_Team
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE KPI_Karyawan_Team
    SET update_at = GETDATE()
    FROM KPI_Karyawan_Team t
    INNER JOIN inserted i ON t.Id_Karyawan_Team = i.Id_Karyawan_Team;
END';
GO

IF OBJECT_ID('dbo.[KPI_Approval_Request_Parameter]','U') IS NOT NULL
    EXEC sp_executesql N'CREATE OR ALTER TRIGGER KPI_Approval_Request_trg_UpdateTimestamp
ON KPI_Approval_Request_Parameter
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE KPI_Approval_Request_Parameter
    SET update_at = GETDATE()
    FROM KPI_Approval_Request_Parameter t
    INNER JOIN inserted i ON t.Id_Approval_Request = i.Id_Approval_Request;
END';
GO


/* ==========================================================================
   BAGIAN 7  FOREIGN KEY — dilewati otomatis bila masih ada baris yatim
   ========================================================================== */

/* N_SOP_Dokumen.Id_Versi_Aktif -> N_SOP_Item_Versi.Id_SOP_Item_Versi */
IF OBJECT_ID('dbo.[N_SOP_Dokumen]','U') IS NOT NULL AND OBJECT_ID('dbo.[N_SOP_Item_Versi]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_N_SOP_Dokumen_Versi_Aktif')
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Dokumen] a
                    WHERE a.[Id_Versi_Aktif] IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Item_Versi] b WHERE b.[Id_SOP_Item_Versi] = a.[Id_Versi_Aktif]))
    ALTER TABLE dbo.[N_SOP_Dokumen] WITH CHECK
        ADD CONSTRAINT [FK_N_SOP_Dokumen_Versi_Aktif] FOREIGN KEY ([Id_Versi_Aktif])
        REFERENCES dbo.[N_SOP_Item_Versi] ([Id_SOP_Item_Versi]);
GO

/* N_SOP_Item_Versi_Pengesah.Id_SOP_Item_Versi -> N_SOP_Item_Versi.Id_SOP_Item_Versi */
IF OBJECT_ID('dbo.[N_SOP_Item_Versi_Pengesah]','U') IS NOT NULL AND OBJECT_ID('dbo.[N_SOP_Item_Versi]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_N_SOP_Item_Versi_Pengesah_Versi')
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Item_Versi_Pengesah] a
                    WHERE a.[Id_SOP_Item_Versi] IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Item_Versi] b WHERE b.[Id_SOP_Item_Versi] = a.[Id_SOP_Item_Versi]))
    ALTER TABLE dbo.[N_SOP_Item_Versi_Pengesah] WITH CHECK
        ADD CONSTRAINT [FK_N_SOP_Item_Versi_Pengesah_Versi] FOREIGN KEY ([Id_SOP_Item_Versi])
        REFERENCES dbo.[N_SOP_Item_Versi] ([Id_SOP_Item_Versi]);
GO

/* HRIS_Approval_Request_Detail.Id_Approval_Request -> HRIS_Approval_Request.id */
IF OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]','U') IS NOT NULL AND OBJECT_ID('dbo.[HRIS_Approval_Request]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_Approval_Request_Detail_Request')
   AND NOT EXISTS (SELECT 1 FROM dbo.[HRIS_Approval_Request_Detail] a
                    WHERE a.[Id_Approval_Request] IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM dbo.[HRIS_Approval_Request] b WHERE b.[id] = a.[Id_Approval_Request]))
    ALTER TABLE dbo.[HRIS_Approval_Request_Detail] WITH CHECK
        ADD CONSTRAINT [FK_Approval_Request_Detail_Request] FOREIGN KEY ([Id_Approval_Request])
        REFERENCES dbo.[HRIS_Approval_Request] ([id]);
GO

/* HRIS_Approval_Request_Detail.Id_Jenis_Approval -> N_HRIS_Jenis_Approval.Id_Jenis_Approval */
IF OBJECT_ID('dbo.[HRIS_Approval_Request_Detail]','U') IS NOT NULL AND OBJECT_ID('dbo.[N_HRIS_Jenis_Approval]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_Approval_Request_Detail_Jenis')
   AND NOT EXISTS (SELECT 1 FROM dbo.[HRIS_Approval_Request_Detail] a
                    WHERE a.[Id_Jenis_Approval] IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Jenis_Approval] b WHERE b.[Id_Jenis_Approval] = a.[Id_Jenis_Approval]))
    ALTER TABLE dbo.[HRIS_Approval_Request_Detail] WITH CHECK
        ADD CONSTRAINT [FK_Approval_Request_Detail_Jenis] FOREIGN KEY ([Id_Jenis_Approval])
        REFERENCES dbo.[N_HRIS_Jenis_Approval] ([Id_Jenis_Approval]);
GO

/* N_HRIS_Menu_Capabilities.Jenis_page -> N_HRIS_Menus.Jenis_page */
IF OBJECT_ID('dbo.[N_HRIS_Menu_Capabilities]','U') IS NOT NULL AND OBJECT_ID('dbo.[N_HRIS_Menus]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_MenuCap_Page')
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Menu_Capabilities] a
                    WHERE a.[Jenis_page] IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Menus] b WHERE b.[Jenis_page] = a.[Jenis_page]))
    ALTER TABLE dbo.[N_HRIS_Menu_Capabilities] WITH CHECK
        ADD CONSTRAINT [FK_MenuCap_Page] FOREIGN KEY ([Jenis_page])
        REFERENCES dbo.[N_HRIS_Menus] ([Jenis_page]);
GO

/* N_HRIS_Menu_Capabilities.Id_Aksi -> HRIS_Klasifikasi_Aksi.Id_Klasifikasi_Actions */
IF OBJECT_ID('dbo.[N_HRIS_Menu_Capabilities]','U') IS NOT NULL AND OBJECT_ID('dbo.[HRIS_Klasifikasi_Aksi]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_MenuCap_Aksi')
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Menu_Capabilities] a
                    WHERE a.[Id_Aksi] IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM dbo.[HRIS_Klasifikasi_Aksi] b WHERE b.[Id_Klasifikasi_Actions] = a.[Id_Aksi]))
    ALTER TABLE dbo.[N_HRIS_Menu_Capabilities] WITH CHECK
        ADD CONSTRAINT [FK_MenuCap_Aksi] FOREIGN KEY ([Id_Aksi])
        REFERENCES dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions]);
GO

/* N_SOP_Item_Versi_Review.Id_SOP_Item_Versi -> N_SOP_Item_Versi.Id_SOP_Item_Versi */
IF OBJECT_ID('dbo.[N_SOP_Item_Versi_Review]','U') IS NOT NULL AND OBJECT_ID('dbo.[N_SOP_Item_Versi]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_SOP_Review_Versi')
   AND NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Item_Versi_Review] a
                    WHERE a.[Id_SOP_Item_Versi] IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Item_Versi] b WHERE b.[Id_SOP_Item_Versi] = a.[Id_SOP_Item_Versi]))
    ALTER TABLE dbo.[N_SOP_Item_Versi_Review] WITH CHECK
        ADD CONSTRAINT [FK_SOP_Review_Versi] FOREIGN KEY ([Id_SOP_Item_Versi])
        REFERENCES dbo.[N_SOP_Item_Versi] ([Id_SOP_Item_Versi]);
GO


/* selesai */
