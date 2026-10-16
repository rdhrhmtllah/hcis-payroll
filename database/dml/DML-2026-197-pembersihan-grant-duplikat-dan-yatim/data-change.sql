-- DML-2026-197 — RBAC: Pembersihan Grant Duplikat, Yatim, dan Bypass Tata Kelola (HRIS_Page_Access)
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang).
--
-- Menonaktifkan (soft-delete, Flag_Aktif = 'T') baris-baris grant langsung di HRIS_Page_Access yang:
--  1. Halaman Yatim (Orphan): Jenis_page sudah tidak terdaftar di N_HRIS_Menus.
--  2. Duplikat Resmi: Pengguna sudah memiliki peran aktif di N_HRIS_Role_User yang memuat halaman tersebut di N_HRIS_Role_Hak.
--  3. Bypass Tata Kelola: Grant langsung halaman administratif/tata kelola yang kini dikelola penuh oleh peran admin modul resmi.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

-- 1. Nonaktifkan Halaman Yatim (tidak ada di N_HRIS_Menus)
UPDATE pa
SET pa.Flag_Aktif = 'T',
    pa.Alasan = ISNULL(pa.Alasan + ' | ', '') + 'DML-2026-197: Halaman sudah tidak terdaftar di master menu'
FROM dbo.HRIS_Page_Access pa
WHERE pa.Flag_Aktif = 'Y'
  AND LOWER(REPLACE(LTRIM(RTRIM(pa.Jenis_page)), ' ', '')) NOT IN (
      SELECT LOWER(REPLACE(LTRIM(RTRIM(m.Jenis_page)), ' ', ''))
      FROM dbo.N_HRIS_Menus m
  );

-- 2. Nonaktifkan Grant Duplikat (sudah dicakup oleh peran aktif pengguna)
UPDATE pa
SET pa.Flag_Aktif = 'T',
    pa.Alasan = ISNULL(pa.Alasan + ' | ', '') + 'DML-2026-197: Sudah tercakup oleh peran resmi aktif'
FROM dbo.HRIS_Page_Access pa
JOIN dbo.N_HRIS_Role_User ru ON ru.UserId_Web = pa.UserID_Web AND ru.Flag_Aktif = 'Y'
JOIN dbo.N_HRIS_Role_Hak h ON h.Id_Role = ru.Id_Role AND h.Flag_Aktif = 'Y'
WHERE pa.Flag_Aktif = 'Y'
  AND LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(pa.Jenis_page)), ' ', ''));

-- 3. Nonaktifkan Bypass Tata Kelola / Halaman Pengatur Akses
UPDATE pa
SET pa.Flag_Aktif = 'T',
    pa.Alasan = ISNULL(pa.Alasan + ' | ', '') + 'DML-2026-197: Akses halaman tata kelola dialihkan ke peran admin resmi'
FROM dbo.HRIS_Page_Access pa
WHERE pa.Flag_Aktif = 'Y'
  AND LOWER(REPLACE(LTRIM(RTRIM(pa.Jenis_page)), ' ', '')) IN (
      'adminallpagesaccess',
      'superadminmanajemenperanpage',
      'systemauthmanagerpage',
      'menuapprovalmatrixpage',
      'menuapprovalmatrixesspage',
      'menuapprovalmatrixkpipage',
      'menuapprovalmatrixlmspage',
      'menuapprovalmatrixvisitorpage',
      'manajemenaksesdivisi',
      'manajemenakseslevel',
      'activitymonitor'
  );

COMMIT TRANSACTION;

-- Verifikasi hasil
SELECT 'Sisa grant aktif' AS kategori, COUNT(*) AS jumlah FROM dbo.HRIS_Page_Access WHERE Flag_Aktif = 'Y'
UNION ALL
SELECT 'Grant dinonaktifkan DML-197', COUNT(*) FROM dbo.HRIS_Page_Access WHERE Alasan LIKE '%DML-2026-197%';
