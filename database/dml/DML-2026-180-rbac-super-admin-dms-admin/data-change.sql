-- DML-2026-180 — RBAC: Super Admin HCIS (lintas modul) + Admin DMS
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DDL-2026-165 (Jenis_Role, N_HRIS_Role_Hak).
--
-- 1. HCIS_SUPER_ADMIN: Jenis_Role ADMIN, TANPA modul induk (Id_Grup_Fitur NULL) supaya tidak
--    mewarisi hibah halaman SYSTEM. Wewenangnya (semua modul selain SYSTEM, mengangkat admin
--    modul) ditentukan kode — KeputusanAkses::apakahSuperAdmin. Hanya System Admin yang
--    menugaskannya.
-- 2. DMS_ADMIN: Jenis_Role ADMIN di modul DMS; hak awal = hibah modul DMS saat ini
--    (VIEW + aksi default katalog), aturan yang sama dengan DML-2026-166.
-- Tidak ada penugasan yang dibuat: siapa pemegangnya diputuskan lewat layar Akses & Peran.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'HCIS_SUPER_ADMIN')
    INSERT INTO dbo.N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_By, Id_Grup_Fitur, Jenis_Role)
    VALUES ('HCIS_SUPER_ADMIN', 'Super Admin HCIS',
            N'Lintas modul: mengelola peran, penugasan, dan admin modul di semua modul selain SYSTEM. Tidak mengubah katalog/mode.',
            'Y', 'DML-2026-180', NULL, 'ADMIN');

DECLARE @dms INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'DMS');

IF @dms IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'DMS_ADMIN')
    INSERT INTO dbo.N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_By, Id_Grup_Fitur, Jenis_Role)
    VALUES ('DMS_ADMIN', 'Admin DMS', N'Admin modul DMS (penugasan peran & grant DMS lewat Akses & Peran).',
            'Y', 'DML-2026-180', @dms, 'ADMIN');

DECLARE @view INT = (SELECT Id_Klasifikasi_Actions FROM dbo.N_HRIS_Klasifikasi_Aksi WHERE Nama_Aksi = 'VIEW');

INSERT INTO dbo.N_HRIS_Role_Hak (Id_Role, Jenis_page, Id_Aksi, Flag_Aktif, Created_By)
SELECT DISTINCT r.Id_Role, m.Jenis_page, a.Id_Aksi, 'Y', 'DML-2026-180'
FROM dbo.N_HRIS_Role_Functional r
JOIN dbo.N_HRIS_Grup_Fitur_Map m
  ON m.Id_Grup_Fitur = r.Id_Grup_Fitur AND m.Flag_Hibah_Role = 'Y'
CROSS APPLY (
    SELECT @view AS Id_Aksi
    UNION
    SELECT c.Id_Aksi FROM dbo.N_HRIS_Menu_Capabilities c
    WHERE c.Flag_Aktif = 'Y' AND c.Is_Active_Default = 'Y'
      AND LOWER(REPLACE(LTRIM(RTRIM(c.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(m.Jenis_page)), ' ', ''))
) a
WHERE r.Role_Code = 'DMS_ADMIN'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Hak h
      WHERE h.Id_Role = r.Id_Role AND h.Id_Aksi = a.Id_Aksi
        AND LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(m.Jenis_page)), ' ', ''))
  );

COMMIT TRANSACTION;
