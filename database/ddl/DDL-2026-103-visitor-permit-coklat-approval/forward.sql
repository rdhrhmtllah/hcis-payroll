-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-103-visitor-permit-coklat-approval
--
-- Permit COKLAT tamu kini melewati alur persetujuan yang sama dengan permit
-- kendaraan lainnya, bukan lagi ditulis langsung ke proyeksi gerbang sebagai
-- baris AKTIF. Konsekuensinya permit itu harus punya TRANSAKSI sendiri
-- (PMT...), dan transaksi itu harus bisa menunjuk kunjungan yang melahirkannya.
--
-- Tanpa kolom penghubung, satu-satunya cara mengaitkan keduanya adalah menyamakan
-- nomor transaksi permit dengan nomor transaksi tamu — persis yang dilakukan
-- rancangan lama. Itulah yang membuat permitnya tidak bisa punya alur approval
-- sendiri: alur approval berkunci No_Transaksi, sehingga satu nomor yang dipakai
-- dua dokumen membuat persetujuan tamu dan persetujuan permit saling menimpa.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'No_Transaksi_Tamu') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD No_Transaksi_Tamu varchar(50) NULL;
END
GO

-- Satu kunjungan tepat satu permit.
--
-- Registrasi tamu disinkronkan ulang setiap kali datanya disunting, dan tanpa
-- kunci unik setiap penyuntingan berpeluang melahirkan permit kedua untuk
-- kunjungan yang sama: dua izin gerbang atas satu tamu, salah satunya tidak
-- pernah dicabut ketika tamunya pulang.
--
-- Predikatnya menyertakan Flag_Aktif = 'Y' supaya permit yang dinonaktifkan
-- tidak ikut menahan kunci. Sifat terfilternya juga berarti permit karyawan
-- (No_Transaksi_Tamu NULL) tidak tersentuh sama sekali.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Transaksi_Tamu')
CREATE UNIQUE INDEX UX_VISITOR_Permit_Transaksi_Tamu
    ON N_EMI_VISITOR_Transaksi_Permit (No_Transaksi_Tamu)
    WHERE No_Transaksi_Tamu IS NOT NULL AND Flag_Aktif = 'Y';
GO

-- Jenis kendaraan tamu, teks bebas.
--
-- Permit karyawan menurunkan jenis kendaraannya dari master model, sedangkan
-- registrasi tamu hanya punya teks ("Mobil", "Motor") tanpa master apa pun.
-- Rancangan lama menyalin teks itu langsung ke proyeksi gerbang; sekarang
-- proyeksinya dibangun dari transaksi permit, jadi teksnya harus punya tempat
-- di sana. Tanpa kolom ini satpam kehilangan satu-satunya petunjuk kendaraan
-- apa yang datang — tanpa satu pun galat.
IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail ADD Jenis_Kendaraan_Tamu varchar(50) NULL;
END
GO

-- Kuota permit per karyawan menyaring permit turunan tamu supaya PIC yang
-- mendaftarkan dua tamu tidak kehabisan jatah permit kendaraannya sendiri.
-- Penyaring itu berjalan di setiap pembukaan layar permit, jadi ia butuh
-- indeksnya sendiri; UX di atas tidak bisa melayaninya karena tidak memuat
-- Kode_Karyawan_Pemohon.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Pemohon_Sumber_Tamu
    ON N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Flag_Aktif)
    INCLUDE (No_Transaksi, No_Transaksi_Tamu, Flag_Approval);
GO
