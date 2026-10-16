-- Change ID: DDL-2026-077-visitor-permit-coklat (DML)
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
--
-- 1) Kategori COKLAT. Dipakai setiap permit tamu; Deskripsi_Akses dibaca
--    aplikasi gate untuk menentukan area yang boleh dimasuki. Nilainya
--    ditetapkan user pada 2026-09-08: batas aksesnya mengikuti jadwal
--    kunjungan, bukan area fisik tertentu.
--
-- 2) Backfill Sumber_Permit baris permit yang sudah ada. Wajib dijalankan
--    bersama forward.sql: penyaring sapuan memang sudah dilindungi
--    ISNULL(Sumber_Permit,'KARYAWAN'), tetapi laporan yang memisah permit
--    karyawan dari permit tamu akan salah tanpa backfill ini.
--
-- Keduanya idempoten.

IF NOT EXISTS (SELECT 1 FROM N_EMI_VISITOR_Master_Kategori_Permit WHERE Nama_Kategori = 'COKLAT')
INSERT INTO N_EMI_VISITOR_Master_Kategori_Permit (Nama_Kategori, Deskripsi_Akses, Kode_Warna_UI, Flag_Aktif)
VALUES ('COKLAT', 'Akses terbatas sesuai jadwal kunjungan', '#795548', 'Y');
GO

UPDATE N_VISITOR_Permit_Efektif SET Sumber_Permit = 'KARYAWAN' WHERE Sumber_Permit IS NULL;
GO
