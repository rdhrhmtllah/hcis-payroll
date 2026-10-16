-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-127-visitor-kode-kartu-permit
--
-- Kode acak per kartu permit untuk QR keaslian.
--
-- Sebelumnya QR memuat nomor permit APA ADANYA + tanda tangan HMAC APP_KEY.
-- Dua akibatnya: nomor internal permit ikut tercetak di kartu, dan seluruh
-- kartu yang sudah tercetak mati serentak bila APP_KEY dirotasi.
--
-- Kolom ini menyimpan kode acak 22 karakter (16 byte, base64url). Verifikasi
-- mencarinya langsung, jadi kartu tidak lagi bergantung pada APP_KEY dan kode
-- satu kartu bisa diputar ulang tanpa menyentuh kartu lain.
--
-- NULL diperbolehkan: permit lama diisi DML, dan permit yang belum pernah
-- dicetak kartunya memang belum butuh kode.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Kode_Kartu') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Kode_Kartu varchar(32) NULL;
END
GO

-- Unik, tersaring NULL: dua kartu tidak boleh berbagi kode, sementara permit
-- tanpa kode tetap banyak. Indeks unik biasa akan menolak NULL kedua.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Kode_Kartu' AND object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Permit_Kode_Kartu
        ON N_EMI_VISITOR_Transaksi_Permit (Kode_Kartu)
        WHERE Kode_Kartu IS NOT NULL;
END
GO
