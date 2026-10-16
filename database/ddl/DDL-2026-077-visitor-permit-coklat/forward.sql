-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-077-visitor-permit-coklat
--
-- Permit COKLAT adalah izin akses tamu yang berlaku dalam hitungan JAM, terbit
-- otomatis dari registrasi tamu. Seluruh kolom masa berlaku permit yang ada
-- bertipe `date`, sehingga jam yang ditulis ke sana hilang tanpa galat.
--
-- Kolom waktu di bawah ditambahkan secara ADITIF: kolom `date` yang lama TETAP
-- diisi turunan tanggalnya oleh VisitorEfektifService. Aplikasi gate pihak
-- ketiga yang menarik N_VISITOR_Permit_Efektif langsung lewat koneksi SQL
-- Server karena itu tetap membaca permit coklat walau belum diperbarui — hanya
-- di ketelitian hari. Tanpa keputusan ini, fitur ini menuntut rilis serentak
-- dengan aplikasi di luar repo ini.
--
-- N_EMI_VISITOR_Transaksi_Permit_Detail sengaja TIDAK disentuh: permit coklat
-- tidak pernah membuat baris transaksi permit, ia lahir langsung sebagai
-- proyeksi dari registrasi tamu. Kolom waktu di sana tidak akan pernah ditulis
-- siapa pun.
--
-- Semua kolom NULL: baris lama tidak punya jendela jam dan tidak boleh dipaksa
-- mengarang satu. Kewajiban mengisi ditegakkan di validasi pengajuan baru.
--
-- Seluruh pernyataan idempoten.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Waktu_Mulai_Kunjungan') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Waktu_Mulai_Kunjungan datetime2(0) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Waktu_Selesai_Kunjungan') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Waktu_Selesai_Kunjungan datetime2(0) NULL;
GO

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Waktu_Mulai_Berlaku') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Waktu_Mulai_Berlaku datetime2(0) NULL;
GO

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Waktu_Berakhir') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Waktu_Berakhir datetime2(0) NULL;
GO

-- 'KARYAWAN' / 'TAMU'. Nullable, dan baris lama di-backfill lewat data-change.sql.
-- Setiap penyaring WAJIB memakai ISNULL(Sumber_Permit,'KARYAWAN'): perbandingan
-- langsung terhadap NULL menghasilkan UNKNOWN, dan baris yang belum ter-backfill
-- akan diam-diam lolos dari sapuan kedaluwarsa.
IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Sumber_Permit') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Sumber_Permit varchar(10) NULL;
GO

-- Sapuan kedaluwarsa berjalan tiap 5 menit dan menyaring permit tamu yang masih
-- AKTIF berdasarkan Waktu_Berakhir. Tanpa indeks ini sapuan itu memindai seluruh
-- tabel setiap 5 menit.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir')
CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir
    ON N_VISITOR_Permit_Efektif (Status_Efektif, Waktu_Berakhir)
    INCLUDE (No_Transaksi, Sumber_Permit);
GO
