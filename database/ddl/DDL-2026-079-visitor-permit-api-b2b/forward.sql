-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-079-visitor-permit-api-b2b
--
-- Permit COKLAT yang diajukan aplikasi mitra B2B lewat API. Ia adalah pengajuan
-- permit sungguhan, bukan bentuk data baru: yang membedakannya dari pengajuan
-- karyawan hanya penanda Sumber_Pengajuan, dan dari penanda itulah tiga mesin
-- permit karyawan (kuota per karyawan, tiga foto wajib, checklist verifikasi)
-- dilewati.
--
-- TIDAK ada baris registrasi tamu yang dibuat untuk permit ini. Konsekuensinya
-- tidak ada check-out yang bisa memutusnya lebih awal — satu-satunya yang
-- mematikannya adalah Waktu_Berakhir. Karena itu jendela jam wajib diisi dan
-- panjangnya dibatasi di VisitorPermitApiService.
--
-- Semua kolom NULL: baris lama tidak punya nilainya dan tidak boleh dipaksa
-- mengarang satu. Kewajiban mengisi ditegakkan di validasi pengajuan baru.
--
-- Prasyarat: DDL-2026-077-visitor-permit-coklat sudah dieksekusi (kategori
-- COKLAT, kolom Sumber_Permit dan Waktu_* di N_VISITOR_Permit_Efektif).
--
-- Seluruh pernyataan idempoten.

IF COL_LENGTH('N_HRIS_Api_Aplikasi', 'Kode_Karyawan_PIC') IS NULL
ALTER TABLE N_HRIS_Api_Aplikasi ADD Kode_Karyawan_PIC varchar(20) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Sumber_Pengajuan') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Sumber_Pengajuan varchar(10) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Id_Api_Aplikasi') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Id_Api_Aplikasi int NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Referensi_Mitra') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Referensi_Mitra varchar(100) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Mulai_Berlaku') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Waktu_Mulai_Berlaku datetime2(0) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Berakhir') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Waktu_Berakhir datetime2(0) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Nama_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Nama_Tamu varchar(150) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Instansi_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Instansi_Tamu varchar(150) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'No_Identitas_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD No_Identitas_Tamu varchar(100) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Identitas_Tamu') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Jenis_Identitas_Tamu varchar(20) NULL;
GO

-- Identitas pemegang WAJIB sampai ke proyeksi: itu satu-satunya tabel yang
-- dibaca aplikasi gerbang. Tanpa kolom ini satpam melihat izin atas nama
-- karyawan PIC dan tidak punya petunjuk siapa yang sebenarnya datang.
IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Nama_Pemegang') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Nama_Pemegang varchar(150) NULL;
GO

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Instansi_Pemegang') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Instansi_Pemegang varchar(150) NULL;
GO

-- Keunikan idempotensi diperluas mencakup aplikasi penerbit.
--
-- Sebelumnya kuncinya (Kode_Karyawan_Pemohon, Idempotency_Key) dan hanya unik
-- per karyawan pemohon. Karena SELURUH permit satu mitra memakai satu PIC, dua
-- mitra yang kebetulan berbagi PIC bisa bertabrakan kuncinya — dan mitra kedua
-- akan menerima nomor permit milik mitra pertama sebagai "sudah pernah
-- diterima". Itu kebocoran data antar pelanggan yang tidak menyalakan galat apa
-- pun.
--
-- Filter dan sifat uniknya dipertahankan persis seperti indeks lama; yang
-- berubah hanya penambahan satu kolom kunci. Id_Api_Aplikasi NULL pada permit
-- HCIS, dan SQL Server memperlakukan NULL sebagai nilai yang sama di indeks
-- unik, sehingga perlindungan bagi permit karyawan tidak berubah sedikit pun.
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
DROP INDEX UX_VISITOR_Permit_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Permit;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
    ON N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Id_Api_Aplikasi, Idempotency_Key)
    WHERE Idempotency_Key IS NOT NULL;
GO

-- Endpoint status dan pembatalan menyaring permit milik aplikasi pemanggil pada
-- setiap panggilan. Tanpa indeks ini penyaring itu memindai seluruh tabel
-- transaksi permit setiap kali mitra melakukan polling.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Api_Aplikasi')
CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Api_Aplikasi
    ON N_EMI_VISITOR_Transaksi_Permit (Id_Api_Aplikasi, Flag_Approval)
    INCLUDE (No_Transaksi, Flag_Aktif)
    WHERE Id_Api_Aplikasi IS NOT NULL;
GO
