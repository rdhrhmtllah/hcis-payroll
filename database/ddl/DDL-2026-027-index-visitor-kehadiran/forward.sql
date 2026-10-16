-- Target: Web_HRIS. Development execution only by the agent; production via SQL Tracker.
-- Change ID: DDL-2026-027-index-visitor-kehadiran
--
-- Indeks untuk pola baca kehadiran dan masa berlaku Visitor.
--
-- Lima indeks di bawah dipilih dari query yang NYATA dipakai (papan harian,
-- analitik dashboard, laporan, kartu homepage), bukan dari menebak. Tidak ada
-- satu pun kolom di bawah ini yang berindeks sebelumnya, sehingga setiap
-- penyaringan tanggal dan kehadiran selalu memindai tabel.
--
-- CATATAN JUJUR TENTANG DAMPAKNYA: pada development saat ini tabelnya kecil
-- (tamu 74 baris, permit 20, efektif 71/11). Pada ukuran segitu SQL Server akan
-- tetap memilih table scan dan indeks ini TIDAK akan terpakai. Nilainya ada di
-- produksi dan di masa depan; di development ia hanya menambah sedikit beban
-- tulis. Ukuran tabel di produksi tidak diketahui saat changeset ini dibuat.
--
-- Semua CREATE INDEX di bawah idempoten dan tidak menyentuh data.

IF OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu', 'U') IS NULL
    THROW 50001, 'Table N_EMI_VISITOR_Transaksi_Tamu was not found.', 1;
GO

-- 1. Tanggal_Kunjungan adalah kolom yang PALING sering disaring: setiap query
--    papan harian, tren, sebaran jam, dan laporan memakai rentang tanggal.
--    Flag_Aktif dan Status_Kunjungan ikut sebagai INCLUDE, bukan kunci: keduanya
--    berkardinalitas rendah sehingga memimpin dengan salah satunya justru
--    memperburuk selektivitas.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu')
      AND name = 'IX_VISITOR_Tamu_Tanggal_Kunjungan'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Tamu_Tanggal_Kunjungan
        ON N_EMI_VISITOR_Transaksi_Tamu (Tanggal_Kunjungan)
        INCLUDE (Flag_Aktif, Status_Kunjungan, Kode_Karyawan_PIC);
END;
GO

IF OBJECT_ID('N_VISITOR_Tamu_Efektif', 'U') IS NULL
    THROW 50002, 'Table N_VISITOR_Tamu_Efektif was not found.', 1;
GO

-- 2. Sumber kebenaran kehadiran: aplikasi palang di gerbang menulis kedua kolom
--    ini. Urutannya (masuk, keluar) melayani pertanyaan yang paling sering
--    diajukan papan: "sudah masuk DAN belum keluar".
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_VISITOR_Tamu_Efektif')
      AND name = 'IX_VISITOR_Tamu_Efektif_Kehadiran'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Tamu_Efektif_Kehadiran
        ON N_VISITOR_Tamu_Efektif (Waktu_Aktual_Masuk, Waktu_Aktual_Keluar)
        INCLUDE (No_Transaksi, Status_Efektif);
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'U') IS NULL
    THROW 50003, 'Table N_EMI_VISITOR_Transaksi_Tamu_Detail was not found.', 1;
GO

-- 3. Jalur cadangan: catatan kehadiran manual saat palang tidak menandai.
--    Dibaca lewat COALESCE bersama kolom efektif, jadi kedua sisi perlu indeks
--    agar bentuk sargable "(e.X IS NOT NULL OR d.X IS NOT NULL)" dapat memakai
--    keduanya.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu_Detail')
      AND name = 'IX_VISITOR_Tamu_Detail_Kehadiran'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Tamu_Detail_Kehadiran
        ON N_EMI_VISITOR_Transaksi_Tamu_Detail (Waktu_Aktual_Masuk, Waktu_Aktual_Keluar)
        INCLUDE (No_Transaksi);
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit_Detail', 'U') IS NULL
    THROW 50004, 'Table N_EMI_VISITOR_Transaksi_Permit_Detail was not found.', 1;
GO

-- 4. Papan harian menyaring permit yang kedaluwarsa hari ini dan dalam 7 hari.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit_Detail')
      AND name = 'IX_VISITOR_Permit_Detail_Kadaluarsa'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Detail_Kadaluarsa
        ON N_EMI_VISITOR_Transaksi_Permit_Detail (Tanggal_Kadaluarsa)
        INCLUDE (No_Transaksi, Tanggal_Mulai_Berlaku);
END;
GO

IF OBJECT_ID('N_VISITOR_Permit_Efektif', 'U') IS NULL
    THROW 50005, 'Table N_VISITOR_Permit_Efektif was not found.', 1;
GO

-- 5. permitPerKategori() dan permitAkanKadaluarsa() sama-sama menyaring
--    Status_Efektif='AKTIF' lalu rentang Tanggal_Kadaluarsa.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_VISITOR_Permit_Efektif')
      AND name = 'IX_VISITOR_Permit_Efektif_Status_Kadaluarsa'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Efektif_Status_Kadaluarsa
        ON N_VISITOR_Permit_Efektif (Status_Efektif, Tanggal_Kadaluarsa)
        INCLUDE (No_Transaksi, Nama_Kategori_Permit);
END;
GO
