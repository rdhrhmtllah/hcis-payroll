-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-102-visitor-tamu-api-b2b
--
-- Registrasi TAMU B2B lewat API mitra. Cermin persis DDL-2026-079 yang sudah
-- dijalankan pada tabel permit, kini pada tabel transaksi tamu — sebabnya sama:
-- tanpa penanda aplikasi, tidak ada satu pun kolom yang bisa memisahkan tamu
-- milik mitra A dari milik mitra B, dan endpoint status akan menyajikan data
-- satu pelanggan kepada pelanggan lain tanpa menyalakan galat apa pun.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu', 'Sumber_Pengajuan') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu ADD Sumber_Pengajuan varchar(10) NULL;
END
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu', 'Id_Api_Aplikasi') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu ADD Id_Api_Aplikasi int NULL;
END
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu', 'Referensi_Mitra') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu ADD Referensi_Mitra varchar(100) NULL;
END
GO

-- Kunci idempotensi lama hanya unik per Kode_Karyawan_PIC. Seluruh tamu satu
-- mitra memakai satu PIC aplikasi, jadi dua mitra yang berbagi PIC bisa
-- bertabrakan kuncinya dan mitra kedua menerima nomor transaksi milik mitra
-- pertama sebagai "sudah pernah diterima".
--
-- Filter dan sifat uniknya dipertahankan persis; yang berubah hanya penambahan
-- satu kolom kunci. Id_Api_Aplikasi NULL pada registrasi HCIS, dan SQL Server
-- memperlakukan NULL sebagai nilai yang sama di indeks unik, sehingga
-- perlindungan bagi registrasi karyawan tidak berubah sedikit pun.
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
DROP INDEX UX_VISITOR_Tamu_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Tamu;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Tamu_Requester_Idempotency
    ON N_EMI_VISITOR_Transaksi_Tamu (Kode_Karyawan_PIC, Id_Api_Aplikasi, Idempotency_Key)
    WHERE Idempotency_Key IS NOT NULL;
GO

-- Endpoint status menyaring tamu milik aplikasi pemanggil pada setiap polling.
-- Tanpa indeks ini penyaring itu memindai seluruh tabel transaksi tamu.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Tamu_Api_Aplikasi')
CREATE NONCLUSTERED INDEX IX_VISITOR_Tamu_Api_Aplikasi
    ON N_EMI_VISITOR_Transaksi_Tamu (Id_Api_Aplikasi, Flag_Approval)
    INCLUDE (No_Transaksi, Flag_Aktif)
    WHERE Id_Api_Aplikasi IS NOT NULL;
GO
