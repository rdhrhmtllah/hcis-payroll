/* Change ID: DDL-2026-190-sinkron-prod-non-pa
   Target: PRODUCTION (emi_db_web_hr) melalui SQL Tracker.
   Scope: seluruh perbedaan struktur NON-PA antara development (Web_HRIS) dan production yang DIPAKAI
          kode aplikasi di master, diukur 14 Okt 2026 langsung dari katalog kedua database (prod: baca
          saja). Tabel yang tidak tampak di katalog prod diuji satu per satu dengan SELECT TOP 0
          (galat 208 = benar tidak ada; 229 = ada tetapi akun tidak berizin).
   Isi: 1 tabel baru, 4 kolom baru, 4 kolom dilebarkan, 17 FK, 12 CHECK, 8 indeks disusun ulang
        (DROP_EXISTING, tanpa DROP INDEX terpisah). Tidak ada DROP TABLE/COLUMN, tidak ada penyempitan.
   Performance Appraisal TIDAK di sini (DDL-2026-187). Yang sengaja tidak dibawa: lihat README.
   IDEMPOTEN: setiap langkah dijaga OBJECT_ID / COL_LENGTH / sys.*; aman diulang.
   Dokumentasi: docs/changes/DDL-2026-190-sinkron-prod-non-pa/README.md
*/
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
SET XACT_ABORT ON;
GO

/* ===== 1. TABEL BARU ===== */
/* 1.1 dbo.N_KPI_Backfill_Cap_Cadangan — cadangan backfill cap KPI (asal DDL-2026-167; dipakai KpiCapParameterBackfillService) */
IF OBJECT_ID(N'dbo.N_KPI_Backfill_Cap_Cadangan', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_KPI_Backfill_Cap_Cadangan (
        [Id_Cadangan] bigint IDENTITY(1, 1) NOT NULL,
        [Batch_Id] varchar(60) NOT NULL,
        [Jenis] char(6) NOT NULL,
        [Id_Update_Performance] int NOT NULL,
        [Id_Update_Performance_Detail] int NULL,
        [Nilai_Lama_Json] nvarchar(max) NULL,
        [Dipulihkan_At] datetime2(0) NULL,
        [Created_At] datetime2(0) NOT NULL CONSTRAINT [DF_KPI_Backfill_Cap_Created] DEFAULT (sysdatetime()),
        CONSTRAINT [PK_KPI_Backfill_Cap_Cadangan] PRIMARY KEY CLUSTERED ([Id_Cadangan]),
        CONSTRAINT [CK_KPI_Backfill_Cap_Jenis] CHECK ([Jenis]='POTONG' OR [Jenis]='DETAIL' OR [Jenis]='HEADER')
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_KPI_Backfill_Cap_Cadangan') AND name = N'IX_KPI_Backfill_Cap_Batch')
    CREATE NONCLUSTERED INDEX [IX_KPI_Backfill_Cap_Batch] ON dbo.N_KPI_Backfill_Cap_Cadangan ([Batch_Id],[Jenis],[Id_Update_Performance]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_KPI_Backfill_Cap_Cadangan') AND name = N'UX_KPI_Backfill_Cap_Potong')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_KPI_Backfill_Cap_Potong] ON dbo.N_KPI_Backfill_Cap_Cadangan ([Batch_Id],[Id_Update_Performance]) WHERE ([Jenis]='POTONG');
GO

/* ===== 2. KOLOM BARU (semua NULL, tanpa default) ===== */
/* dbo.N_HRIS_Menus.Section_Code: DDL-2026-181: bagian menu sidebar */
IF COL_LENGTH(N'dbo.N_HRIS_Menus', N'Section_Code') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD [Section_Code] varchar(100) NULL;
GO
IF COL_LENGTH(N'dbo.N_HRIS_Menus', N'Section_Label') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD [Section_Label] varchar(150) NULL;
GO
IF COL_LENGTH(N'dbo.N_HRIS_Menus', N'Section_Icon') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD [Section_Icon] varchar(100) NULL;
GO
IF COL_LENGTH(N'dbo.N_HRIS_Menus', N'Sort_Section') IS NULL
    ALTER TABLE dbo.N_HRIS_Menus ADD [Sort_Section] int NULL;
GO

/* ===== 3. KOLOM DILEBARKAN (dev lebih lebar; pelebaran varchar tidak mengubah data, nullability & collation tetap) ===== */
/* dbo.Absen_SN.Output: varchar(5) -> varchar(10) */
IF COL_LENGTH(N'dbo.Absen_SN', N'Output') < 10
    ALTER TABLE dbo.Absen_SN ALTER COLUMN [Output] varchar(10) COLLATE SQL_Latin1_General_CP1_CI_AS NULL;
GO
/* dbo.KPI_Pengajuan.Alasan_Reject: varchar(300) -> varchar(500) */
IF COL_LENGTH(N'dbo.KPI_Pengajuan', N'Alasan_Reject') < 500
    ALTER TABLE dbo.KPI_Pengajuan ALTER COLUMN [Alasan_Reject] varchar(500) COLLATE SQL_Latin1_General_CP1_CI_AS NULL;
GO
/* dbo.N_HCIS_Tabel_Absen.No_Transaksi_Lembur_Awal: varchar(30) -> varchar(200) */
IF COL_LENGTH(N'dbo.N_HCIS_Tabel_Absen', N'No_Transaksi_Lembur_Awal') < 200
    ALTER TABLE dbo.N_HCIS_Tabel_Absen ALTER COLUMN [No_Transaksi_Lembur_Awal] varchar(200) COLLATE SQL_Latin1_General_CP1_CI_AS NULL;
GO
/* dbo.N_SOP_Penjadwalan_Integrasi.Id_Master_Ujian: varchar(50) -> varchar(200) */
IF COL_LENGTH(N'dbo.N_SOP_Penjadwalan_Integrasi', N'Id_Master_Ujian') < 200
    ALTER TABLE dbo.N_SOP_Penjadwalan_Integrasi ALTER COLUMN [Id_Master_Ujian] varchar(200) COLLATE SQL_Latin1_General_CP1_CI_AS NOT NULL;
GO

/* ===== 4. FOREIGN KEY (data production sudah dicek: 0 baris yatim pada 14 Okt 2026) ===== */
IF OBJECT_ID(N'dbo.FK_N_SOP_Run_Rule', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Assignment_Rule', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Automation_Run WITH CHECK ADD CONSTRAINT [FK_N_SOP_Run_Rule] FOREIGN KEY ([Id_SOP_Assignment_Rule]) REFERENCES dbo.N_SOP_Assignment_Rule ([Id_SOP_Assignment_Rule]);
GO
IF OBJECT_ID(N'dbo.FK_N_SOP_Pjd_Rule', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Assignment_Rule', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Penjadwalan WITH CHECK ADD CONSTRAINT [FK_N_SOP_Pjd_Rule] FOREIGN KEY ([Id_SOP_Assignment_Rule]) REFERENCES dbo.N_SOP_Assignment_Rule ([Id_SOP_Assignment_Rule]);
GO
IF OBJECT_ID(N'dbo.FK_N_SOP_Paket_Draft_Paket', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Master_Paket', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Paket_Draft WITH CHECK ADD CONSTRAINT [FK_N_SOP_Paket_Draft_Paket] FOREIGN KEY ([Id_SOP_Paket]) REFERENCES dbo.N_SOP_Master_Paket ([Id_SOP_Paket]);
GO
IF OBJECT_ID(N'dbo.FK_N_SOP_Paket_Draft_Section_Header', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Paket_Draft', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Paket_Draft_Section WITH CHECK ADD CONSTRAINT [FK_N_SOP_Paket_Draft_Section_Header] FOREIGN KEY ([Id_SOP_Paket_Draft]) REFERENCES dbo.N_SOP_Paket_Draft ([Id_SOP_Paket_Draft]);
GO
IF OBJECT_ID(N'dbo.FK_N_VISITOR_Inspeksi_Pola_Pos', N'F') IS NULL AND OBJECT_ID(N'dbo.N_VISITOR_Master_Pos_Inspeksi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Inspeksi_Pola WITH CHECK ADD CONSTRAINT [FK_N_VISITOR_Inspeksi_Pola_Pos] FOREIGN KEY ([Id_Pos_Inspeksi]) REFERENCES dbo.N_VISITOR_Master_Pos_Inspeksi ([Id_Pos_Inspeksi]);
GO
IF OBJECT_ID(N'dbo.FK_N_VISITOR_Inspeksi_Pengecualian_Pos', N'F') IS NULL AND OBJECT_ID(N'dbo.N_VISITOR_Master_Pos_Inspeksi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Inspeksi_Pengecualian WITH CHECK ADD CONSTRAINT [FK_N_VISITOR_Inspeksi_Pengecualian_Pos] FOREIGN KEY ([Id_Pos_Inspeksi]) REFERENCES dbo.N_VISITOR_Master_Pos_Inspeksi ([Id_Pos_Inspeksi]);
GO
IF OBJECT_ID(N'dbo.FK_N_VISITOR_Inspeksi_Booking_Pos', N'F') IS NULL AND OBJECT_ID(N'dbo.N_VISITOR_Master_Pos_Inspeksi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Inspeksi_Booking WITH CHECK ADD CONSTRAINT [FK_N_VISITOR_Inspeksi_Booking_Pos] FOREIGN KEY ([Id_Pos_Inspeksi]) REFERENCES dbo.N_VISITOR_Master_Pos_Inspeksi ([Id_Pos_Inspeksi]);
GO
IF OBJECT_ID(N'dbo.FK_N_SOP_Paket_Draft_Item_Header', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Paket_Draft', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Paket_Draft_Item WITH CHECK ADD CONSTRAINT [FK_N_SOP_Paket_Draft_Item_Header] FOREIGN KEY ([Id_SOP_Paket_Draft]) REFERENCES dbo.N_SOP_Paket_Draft ([Id_SOP_Paket_Draft]);
GO
IF OBJECT_ID(N'dbo.FK_N_SOP_Paket_Draft_Item_Section', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Paket_Draft_Section', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Paket_Draft_Item WITH CHECK ADD CONSTRAINT [FK_N_SOP_Paket_Draft_Item_Section] FOREIGN KEY ([Id_SOP_Draft_Section]) REFERENCES dbo.N_SOP_Paket_Draft_Section ([Id_SOP_Draft_Section]);
GO
IF OBJECT_ID(N'dbo.FK_N_VISITOR_Inspeksi_Pola_Shift', N'F') IS NULL AND OBJECT_ID(N'dbo.N_VISITOR_Master_Shift_Inspeksi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Inspeksi_Pola WITH CHECK ADD CONSTRAINT [FK_N_VISITOR_Inspeksi_Pola_Shift] FOREIGN KEY ([Id_Shift_Inspeksi]) REFERENCES dbo.N_VISITOR_Master_Shift_Inspeksi ([Id_Shift_Inspeksi]);
GO
IF OBJECT_ID(N'dbo.FK_N_VISITOR_Inspeksi_Pengecualian_Shift', N'F') IS NULL AND OBJECT_ID(N'dbo.N_VISITOR_Master_Shift_Inspeksi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Inspeksi_Pengecualian WITH CHECK ADD CONSTRAINT [FK_N_VISITOR_Inspeksi_Pengecualian_Shift] FOREIGN KEY ([Id_Shift_Inspeksi]) REFERENCES dbo.N_VISITOR_Master_Shift_Inspeksi ([Id_Shift_Inspeksi]);
GO
IF OBJECT_ID(N'dbo.FK_N_SOP_Paket_Draft_Item_Versi_Item', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Paket_Draft_Item', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Paket_Draft_Item_Versi WITH CHECK ADD CONSTRAINT [FK_N_SOP_Paket_Draft_Item_Versi_Item] FOREIGN KEY ([Id_SOP_Draft_Item]) REFERENCES dbo.N_SOP_Paket_Draft_Item ([Id_SOP_Draft_Item]);
GO
IF OBJECT_ID(N'dbo.FK_N_VISITOR_Inspeksi_Booking_Shift', N'F') IS NULL AND OBJECT_ID(N'dbo.N_VISITOR_Master_Shift_Inspeksi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Inspeksi_Booking WITH CHECK ADD CONSTRAINT [FK_N_VISITOR_Inspeksi_Booking_Shift] FOREIGN KEY ([Id_Shift_Inspeksi]) REFERENCES dbo.N_VISITOR_Master_Shift_Inspeksi ([Id_Shift_Inspeksi]);
GO
IF OBJECT_ID(N'dbo.FK_N_VISITOR_Inspeksi_Hari_Shift_Shift', N'F') IS NULL AND OBJECT_ID(N'dbo.N_VISITOR_Master_Shift_Inspeksi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_VISITOR_Inspeksi_Hari_Shift WITH CHECK ADD CONSTRAINT [FK_N_VISITOR_Inspeksi_Hari_Shift_Shift] FOREIGN KEY ([Id_Shift_Inspeksi]) REFERENCES dbo.N_VISITOR_Master_Shift_Inspeksi ([Id_Shift_Inspeksi]);
GO
IF OBJECT_ID(N'dbo.FK_SOP_ARP_Rule', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Assignment_Rule', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Assignment_Rule_Paket WITH CHECK ADD CONSTRAINT [FK_SOP_ARP_Rule] FOREIGN KEY ([Id_SOP_Assignment_Rule]) REFERENCES dbo.N_SOP_Assignment_Rule ([Id_SOP_Assignment_Rule]);
GO
IF OBJECT_ID(N'dbo.FK_SOP_ARP_Paket', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Master_Paket', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Assignment_Rule_Paket WITH CHECK ADD CONSTRAINT [FK_SOP_ARP_Paket] FOREIGN KEY ([Id_SOP_Paket]) REFERENCES dbo.N_SOP_Master_Paket ([Id_SOP_Paket]);
GO
IF OBJECT_ID(N'dbo.FK_N_SOP_Progress_Versi', N'F') IS NULL AND OBJECT_ID(N'dbo.N_SOP_Item_Versi', N'U') IS NOT NULL
    ALTER TABLE dbo.N_SOP_Progress WITH CHECK ADD CONSTRAINT [FK_N_SOP_Progress_Versi] FOREIGN KEY ([Id_SOP_Item_Versi]) REFERENCES dbo.N_SOP_Item_Versi ([Id_SOP_Item_Versi]);
GO

/* ===== 5. CHECK (data production sudah dicek: 0 pelanggaran) ===== */
IF OBJECT_ID(N'dbo.CK_N_SOP_Run_Type', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Automation_Run WITH CHECK ADD CONSTRAINT [CK_N_SOP_Run_Type] CHECK ([Tipe_Run]='RECERT' OR [Tipe_Run]='REMINDER' OR [Tipe_Run]='RULE_SCAN' OR [Tipe_Run]='RULE_APPLY');
GO
IF OBJECT_ID(N'dbo.CK_N_SOP_Run_Trigger', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Automation_Run WITH CHECK ADD CONSTRAINT [CK_N_SOP_Run_Trigger] CHECK ([Trigger_Run]='MANUAL' OR [Trigger_Run]='SCHEDULED' OR [Trigger_Run]='ACTIVATION');
GO
IF OBJECT_ID(N'dbo.CK_N_SOP_Run_Status', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Automation_Run WITH CHECK ADD CONSTRAINT [CK_N_SOP_Run_Status] CHECK ([Status_Run]='SKIPPED' OR [Status_Run]='FAILED' OR [Status_Run]='SUCCESS' OR [Status_Run]='RUNNING' OR [Status_Run]='QUEUED');
GO
IF OBJECT_ID(N'dbo.CK_N_SOP_Run_Result_JSON', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Automation_Run WITH CHECK ADD CONSTRAINT [CK_N_SOP_Run_Result_JSON] CHECK ([Hasil_JSON] IS NULL OR isjson([Hasil_JSON])=(1));
GO
IF OBJECT_ID(N'dbo.CK_N_SOP_Prioritas_Aktif', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Prioritas WITH CHECK ADD CONSTRAINT [CK_N_SOP_Prioritas_Aktif] CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y');
GO
IF OBJECT_ID(N'dbo.CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json', N'C') IS NULL
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif WITH CHECK ADD CONSTRAINT [CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json] CHECK ([Daftar_Anggota_Json] IS NULL OR isjson([Daftar_Anggota_Json])=(1));
GO
IF OBJECT_ID(N'dbo.CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu', N'C') IS NULL
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail WITH CHECK ADD CONSTRAINT [CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu] CHECK ([Tipe_Tamu] IS NULL OR ([Tipe_Tamu]='B2B' OR [Tipe_Tamu]='REGULAR'));
GO
IF OBJECT_ID(N'dbo.CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu', N'C') IS NULL
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif WITH CHECK ADD CONSTRAINT [CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu] CHECK ([Tipe_Tamu] IS NULL OR ([Tipe_Tamu]='B2B' OR [Tipe_Tamu]='REGULAR'));
GO
IF OBJECT_ID(N'dbo.CK_SOP_ARP_Override', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Assignment_Rule_Paket WITH CHECK ADD CONSTRAINT [CK_SOP_ARP_Override] CHECK ([Flag_Override_Jadwal]='T' OR [Flag_Override_Jadwal]='Y');
GO
IF OBJECT_ID(N'dbo.CK_SOP_ARP_FlagAktif', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Assignment_Rule_Paket WITH CHECK ADD CONSTRAINT [CK_SOP_ARP_FlagAktif] CHECK ([Flag_Aktif]='T' OR [Flag_Aktif]='Y');
GO
IF OBJECT_ID(N'dbo.CK_SOP_ARP_DeadlineMod', N'C') IS NULL
    ALTER TABLE dbo.N_SOP_Assignment_Rule_Paket WITH CHECK ADD CONSTRAINT [CK_SOP_ARP_DeadlineMod] CHECK ([Deadline_Mode] IS NULL OR ([Deadline_Mode]='ABSOLUTE' OR [Deadline_Mode]='RELATIVE' OR [Deadline_Mode]='NONE'));
GO
IF OBJECT_ID(N'dbo.CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role', N'C') IS NULL
    ALTER TABLE dbo.N_HRIS_Grup_Fitur_Map WITH CHECK ADD CONSTRAINT [CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role] CHECK ([Flag_Hibah_Role]='T' OR [Flag_Hibah_Role]='Y');
GO

/* ===== 6. INDEKS DISUSUN ULANG (DROP_EXISTING: definisi lama diganti dalam satu pernyataan) ===== */
/* dbo.N_SOP_Automation_Run.IX_N_SOP_Run_History: urutan DESC pada kolom Created_At */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Automation_Run') AND name = N'IX_N_SOP_Run_History') AND (EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.N_SOP_Automation_Run') AND ix.name = N'IX_N_SOP_Run_History' AND COL_NAME(ic.object_id, ic.column_id) = N'Created_At' AND ic.is_descending_key = 0))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Run_History] ON dbo.N_SOP_Automation_Run ([Created_At] DESC,[Tipe_Run],[Status_Run]) WITH (DROP_EXISTING = ON);
GO
/* dbo.N_KPI_Target_Koreksi.IX_N_KPI_Target_Koreksi_Det: urutan DESC pada kolom Waktu */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_KPI_Target_Koreksi') AND name = N'IX_N_KPI_Target_Koreksi_Det') AND (EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.N_KPI_Target_Koreksi') AND ix.name = N'IX_N_KPI_Target_Koreksi_Det' AND COL_NAME(ic.object_id, ic.column_id) = N'Waktu' AND ic.is_descending_key = 0))
    CREATE NONCLUSTERED INDEX [IX_N_KPI_Target_Koreksi_Det] ON dbo.N_KPI_Target_Koreksi ([Id_Pengajuan_Det],[Waktu] DESC) WITH (DROP_EXISTING = ON);
GO
/* dbo.N_EMI_VISITOR_Transaksi_Permit.UX_VISITOR_Permit_Requester_Idempotency: kunci unik per aplikasi API (dev: Pemohon/PIC + Id_Api_Aplikasi + Idempotency_Key) */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Permit') AND name = N'UX_VISITOR_Permit_Requester_Idempotency') AND (NOT EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Permit') AND ix.name = N'UX_VISITOR_Permit_Requester_Idempotency' AND COL_NAME(ic.object_id, ic.column_id) = N'Id_Api_Aplikasi'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Permit_Requester_Idempotency] ON dbo.N_EMI_VISITOR_Transaksi_Permit ([Kode_Karyawan_Pemohon],[Id_Api_Aplikasi],[Idempotency_Key]) WHERE ([Idempotency_Key] IS NOT NULL) WITH (DROP_EXISTING = ON);
GO
/* dbo.Transaksi_Lembur_Det.IX_Transaksi_Lembur_Det_NoTrans_Kode: urutan DESC pada kolom Tanggal_Lembur */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.Transaksi_Lembur_Det') AND name = N'IX_Transaksi_Lembur_Det_NoTrans_Kode') AND (EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.Transaksi_Lembur_Det') AND ix.name = N'IX_Transaksi_Lembur_Det_NoTrans_Kode' AND COL_NAME(ic.object_id, ic.column_id) = N'Tanggal_Lembur' AND ic.is_descending_key = 0))
    CREATE NONCLUSTERED INDEX [IX_Transaksi_Lembur_Det_NoTrans_Kode] ON dbo.Transaksi_Lembur_Det ([No_Transaksi],[Kode_Karyawan],[Tanggal_Lembur] DESC) WITH (DROP_EXISTING = ON);
GO
/* dbo.N_EMI_VISITOR_Transaksi_Tamu.UX_VISITOR_Tamu_Requester_Idempotency: kunci unik per aplikasi API (dev: Pemohon/PIC + Id_Api_Aplikasi + Idempotency_Key) */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Tamu') AND name = N'UX_VISITOR_Tamu_Requester_Idempotency') AND (NOT EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Tamu') AND ix.name = N'UX_VISITOR_Tamu_Requester_Idempotency' AND COL_NAME(ic.object_id, ic.column_id) = N'Id_Api_Aplikasi'))
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Tamu_Requester_Idempotency] ON dbo.N_EMI_VISITOR_Transaksi_Tamu ([Kode_Karyawan_PIC],[Id_Api_Aplikasi],[Idempotency_Key]) WHERE ([Idempotency_Key] IS NOT NULL) WITH (DROP_EXISTING = ON);
GO
/* dbo.N_SOP_Paket_Draft.IX_N_SOP_Paket_Draft_Paket_Status: urutan DESC pada kolom Updated_At */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Paket_Draft') AND name = N'IX_N_SOP_Paket_Draft_Paket_Status') AND (EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.N_SOP_Paket_Draft') AND ix.name = N'IX_N_SOP_Paket_Draft_Paket_Status' AND COL_NAME(ic.object_id, ic.column_id) = N'Updated_At' AND ic.is_descending_key = 0))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Paket_Status] ON dbo.N_SOP_Paket_Draft ([Id_SOP_Paket],[Status],[Updated_At] DESC) WITH (DROP_EXISTING = ON);
GO
/* dbo.N_SOP_Paket_Versi.IX_N_SOP_Paket_Versi_Paket_Tanggal: urutan DESC pada kolom Tanggal_Publish */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Paket_Versi') AND name = N'IX_N_SOP_Paket_Versi_Paket_Tanggal') AND (EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.N_SOP_Paket_Versi') AND ix.name = N'IX_N_SOP_Paket_Versi_Paket_Tanggal' AND COL_NAME(ic.object_id, ic.column_id) = N'Tanggal_Publish' AND ic.is_descending_key = 0))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Versi_Paket_Tanggal] ON dbo.N_SOP_Paket_Versi ([Id_SOP_Paket],[Tanggal_Publish] DESC) WITH (DROP_EXISTING = ON);
GO
/* dbo.N_SOP_Paket_Draft_Item_Versi.IX_N_SOP_Paket_Draft_Item_Versi_Status: urutan DESC pada kolom Versi_Ke */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Paket_Draft_Item_Versi') AND name = N'IX_N_SOP_Paket_Draft_Item_Versi_Status') AND (EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.indexes ix ON ix.object_id = ic.object_id AND ix.index_id = ic.index_id WHERE ix.object_id = OBJECT_ID(N'dbo.N_SOP_Paket_Draft_Item_Versi') AND ix.name = N'IX_N_SOP_Paket_Draft_Item_Versi_Status' AND COL_NAME(ic.object_id, ic.column_id) = N'Versi_Ke' AND ic.is_descending_key = 0))
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Paket_Draft_Item_Versi_Status] ON dbo.N_SOP_Paket_Draft_Item_Versi ([Id_SOP_Draft_Item],[Status],[Versi_Ke] DESC) WITH (DROP_EXISTING = ON);
GO

/* ===== 7. Catatan di ledger production ===== */
IF OBJECT_ID(N'dbo.N_SYSTEM_Database_Change', N'U') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-190')
    INSERT INTO dbo.N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Catatan, Created_By
    ) VALUES (
        'DDL-2026-190', 'DDL', 'Sinkron struktur non-PA development ke production',
        '1 tabel, 4 kolom, 4 pelebaran kolom, 17 FK, 12 CHECK, 8 indeks disusun ulang; dari perbandingan katalog 14 Okt 2026.',
        'database/ddl/DDL-2026-190-sinkron-prod-non-pa/forward.sql', 'docs/changes/DDL-2026-190-sinkron-prod-non-pa/README.md',
        'Web_HRIS', 'NOT_APPLICABLE', 'PENDING', 'PENDING', 'PENDING',
        'Non-PA. Menggantikan sisa DDL-2026-128/129/154/155/167/181 untuk production.', NULL
    );
GO
