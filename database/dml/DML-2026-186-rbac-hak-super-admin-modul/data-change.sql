-- DML-2026-186 — RBAC: Hak akses penuh Super Admin modul (ESS, KPI, LMS, VISITOR, DMS)
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DDL-2026-165 (N_HRIS_Role_Hak), DML-2026-182.
--
-- Super Admin di setiap modul (ESS_SUPER_ADMIN, KPI_SUPER_ADMIN, LMS_SUPER_ADMIN,
-- VISITOR_SUPER_ADMIN, DMS_SUPER_ADMIN) berhak penuh atas seluruh halaman operasional &
-- administratif modulnya di N_HRIS_Grup_Fitur_Map dengan seluruh aksi (CREATE, VIEW, EDIT,
-- DELETE, APPROVE, PRINT, SUMMARY, ASSIGN).
-- Halaman pengaturan System Admin (adminAllPagesAccess, superAdminManajemenPeranPage,
-- systemAuthManagerPage) dikecualikan karena khusus System Admin.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

INSERT INTO dbo.N_HRIS_Role_Hak (Id_Role, Jenis_page, Id_Aksi, Flag_Aktif, Created_By)
SELECT DISTINCT r.Id_Role, m.Jenis_page, a.Id_Klasifikasi_Actions, 'Y', 'DML-2026-186'
FROM dbo.N_HRIS_Role_Functional r
JOIN dbo.N_HRIS_Grup_Fitur_Map m
  ON m.Id_Grup_Fitur = r.Id_Grup_Fitur AND m.Flag_Aktif = 'Y'
CROSS JOIN dbo.N_HRIS_Klasifikasi_Aksi a
WHERE r.Role_Code IN ('ESS_SUPER_ADMIN', 'KPI_SUPER_ADMIN', 'LMS_SUPER_ADMIN', 'VISITOR_SUPER_ADMIN', 'DMS_SUPER_ADMIN')
  AND r.Flag_Aktif = 'Y'
  AND LOWER(REPLACE(LTRIM(RTRIM(m.Jenis_page)), ' ', '')) NOT IN (
      'adminallpagesaccess', 'superadminmanajemenperanpage', 'systemauthmanagerpage'
  )
  AND LOWER(m.Jenis_page) NOT LIKE '%aksesperanpage'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Hak x
      WHERE x.Id_Role = r.Id_Role AND x.Id_Aksi = a.Id_Klasifikasi_Actions
        AND LOWER(REPLACE(LTRIM(RTRIM(x.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(m.Jenis_page)), ' ', ''))
  );

COMMIT TRANSACTION;

-- Verifikasi: setiap Super Admin harus memiliki hak halaman lengkap modulnya
SELECT r.Role_Code,
       COUNT(DISTINCT h.Jenis_page) AS jumlah_halaman,
       COUNT(*) AS jumlah_hak
FROM dbo.N_HRIS_Role_Functional r
JOIN dbo.N_HRIS_Role_Hak h ON h.Id_Role = r.Id_Role AND h.Flag_Aktif = 'Y'
WHERE r.Role_Code IN ('ESS_SUPER_ADMIN', 'KPI_SUPER_ADMIN', 'LMS_SUPER_ADMIN', 'VISITOR_SUPER_ADMIN', 'DMS_SUPER_ADMIN')
GROUP BY r.Role_Code
ORDER BY r.Role_Code;
