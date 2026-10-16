-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-104-visitor-kategori-permit-akses
--
-- Mengisi penanda tampil untuk kategori yang sudah ada.
--
-- Bawaan 'Y' untuk SEMUA kategori, lalu COKLAT ditutup. Arah pengisiannya
-- sengaja begitu: kategori yang belum pernah dipikirkan lebih baik tetap
-- terlihat dan bisa dicabut admin, daripada hilang diam-diam dari form dan
-- membuat pemohon menyimpulkan permitnya tidak bisa diajukan.

UPDATE N_EMI_VISITOR_Master_Kategori_Permit
   SET Flag_Muncul_Pengajuan = 'Y'
 WHERE Flag_Muncul_Pengajuan IS NULL;
GO

-- COKLAT adalah izin sehari milik tamu. Ia diterbitkan lewat registrasi tamu
-- dan endpoint mitra, tidak pernah lewat form pengajuan permit karyawan.
UPDATE N_EMI_VISITOR_Master_Kategori_Permit
   SET Flag_Muncul_Pengajuan = 'T'
 WHERE Nama_Kategori = 'COKLAT';
GO

-- Verifikasi: tidak boleh ada kategori tanpa penanda, dan COKLAT harus 'T'.
SELECT Nama_Kategori, Flag_Aktif, Flag_Muncul_Pengajuan
  FROM N_EMI_VISITOR_Master_Kategori_Permit
 ORDER BY Nama_Kategori;
GO
