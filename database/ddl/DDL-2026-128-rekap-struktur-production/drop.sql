-- database/ddl/DDL-2026-128-rekap-struktur-production/drop.sql
-- PENDAMPING ROLLBACK — JANGAN DIJALANKAN OTOMATIS.
--
-- Berkas ini ada karena setiap change wajib punya pasangan pembatalnya, bukan
-- karena pembatalan itu diharapkan. forward.sql hanya MENAMBAH objek, jadi
-- membiarkannya berdiri tanpa dipakai tidak merusak apa pun.
--
-- YANG HILANG BILA DIJALANKAN: seluruh isi tujuh tabel, dan data pada sebelas
--
-- kolom yang dibuang. Jalankan DDL-2026-129/drop.sql LEBIH DULU: indeks tak bisa
-- berdiri di atas tabel dan kolom yang sudah tidak ada.

SET NOCOUNT ON;
GO

/* ── BAGIAN 1 — tabel baru ─────────────────────────────────────────────── */
IF OBJECT_ID('dbo.N_VISITOR_Inspeksi_Booking', 'U')      IS NOT NULL DROP TABLE dbo.N_VISITOR_Inspeksi_Booking;
IF OBJECT_ID('dbo.N_VISITOR_Inspeksi_Hari_Shift', 'U')   IS NOT NULL DROP TABLE dbo.N_VISITOR_Inspeksi_Hari_Shift;
IF OBJECT_ID('dbo.N_VISITOR_Inspeksi_Pengecualian', 'U') IS NOT NULL DROP TABLE dbo.N_VISITOR_Inspeksi_Pengecualian;
IF OBJECT_ID('dbo.N_VISITOR_Inspeksi_Pola', 'U')         IS NOT NULL DROP TABLE dbo.N_VISITOR_Inspeksi_Pola;
IF OBJECT_ID('dbo.N_VISITOR_Master_Pos_Inspeksi', 'U')   IS NOT NULL DROP TABLE dbo.N_VISITOR_Master_Pos_Inspeksi;
IF OBJECT_ID('dbo.N_VISITOR_Master_Shift_Inspeksi', 'U') IS NOT NULL DROP TABLE dbo.N_VISITOR_Master_Shift_Inspeksi;
IF OBJECT_ID('dbo.N_VISITOR_Matriks_Tamu', 'U')          IS NOT NULL DROP TABLE dbo.N_VISITOR_Matriks_Tamu;
GO

/* ── BAGIAN 2 — kolom baru (MEMBAWA DATANYA PERGI) ─────────────────────── */
IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu')           IS NOT NULL ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN Tipe_Tamu;
IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tamu_Kategori')       IS NOT NULL ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN Tamu_Kategori;
IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Path_Foto_Kendaraan') IS NOT NULL ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN Path_Foto_Kendaraan;
IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'B2B_Unit')            IS NOT NULL ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN B2B_Unit;
IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Tipe_Tamu')             IS NOT NULL ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Tipe_Tamu;
IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Estimasi_Masuk')  IS NOT NULL ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Waktu_Estimasi_Masuk;
IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Estimasi_Keluar') IS NOT NULL ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Waktu_Estimasi_Keluar;
IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Tamu_Kategori')         IS NOT NULL ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Tamu_Kategori;
IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'B2B_Unit')              IS NOT NULL ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN B2B_Unit;
IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Path_Foto_Kendaraan')   IS NOT NULL ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Path_Foto_Kendaraan;
IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Kode_Kartu')    IS NOT NULL ALTER TABLE N_EMI_VISITOR_Transaksi_Permit DROP COLUMN Kode_Kartu;
GO
