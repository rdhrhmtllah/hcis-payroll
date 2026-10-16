/* Change ID: DML-2026-166-rbac-konversi-role-hak
 * RBAC Fase 2a: isi model baru (DDL-2026-165) dari kondisi sekarang.
 * Idempoten (NOT EXISTS / hanya mengubah yang berbeda); tidak mengubah keputusan akses.
 * Satu batch (tanpa GO). Daftar di bawah WAJIB sama dengan config/authorization.php
 * (tata_kelola.halaman & tata_kelola.peran_admin) — dijaga KonversiRbacTest. */
SET NOCOUNT ON;

DECLARE @tata_kelola TABLE (k varchar(100) PRIMARY KEY);
-- HALAMAN_TATA_KELOLA
INSERT INTO @tata_kelola (k) VALUES ('adminallpagesaccess'), ('superadminmanajemenperanpage'), ('systemauthmanagerpage'), ('menuapprovalmatrixpage'), ('menuapprovalmatrixesspage'), ('menuapprovalmatrixkpipage'), ('menuapprovalmatrixlmspage'), ('menuapprovalmatrixvisitorpage'), ('manajemenaksesdivisi'), ('manajemenakseslevel'), ('activitymonitor');

DECLARE @peran_admin TABLE (k varchar(100) PRIMARY KEY);
-- PERAN_ADMIN
INSERT INTO @peran_admin (k) VALUES ('ESS_MENU_ADMINISTRATOR'), ('ESS_APPROVAL_ADMINISTRATOR'), ('ESS_MEMBERSHIP_ADMINISTRATOR'), ('ESS_MENU_APPROVAL_ADMINISTRATOR'), ('ESS_MENU_ADMINISTRATOR_PHL'), ('ESS_APPROVAL_ADMINISTRATOR_PHL'), ('KPI_SUPER_ADMIN'), ('KPI_ADMIN_PHL'), ('KPI_MENU_ADMINISTRATOR'), ('KPI_APPROVAL_ADMINISTRATOR'), ('KPI_MEMBERSHIP_ADMINISTRATOR'), ('KPI_MENU_APPROVAL_ADMINISTRATOR'), ('LMS_MENU_ADMINISTRATOR'), ('LMS_APPROVAL_ADMINISTRATOR'), ('LMS_MENU_APPROVAL_ADMINISTRATOR'), ('VISITOR_MENU_ADMINISTRATOR'), ('VISITOR_APPROVAL_ADMINISTRATOR'), ('VISITOR_MENU_APPROVAL_ADMINISTRATOR');

DECLARE @view int = (SELECT TOP 1 Id_Klasifikasi_Actions FROM dbo.N_HRIS_Klasifikasi_Aksi WHERE Nama_Aksi = 'VIEW');

/* 1. Jenis_Role — label saja. */
UPDATE rf SET Jenis_Role = 'ADMIN'
FROM dbo.N_HRIS_Role_Functional rf
WHERE UPPER(rf.Role_Code) IN (SELECT k FROM @peran_admin) AND rf.Jenis_Role <> 'ADMIN';

UPDATE rf SET Jenis_Role = 'POOL_APPROVAL'
FROM dbo.N_HRIS_Role_Functional rf
WHERE (UPPER(rf.Role_Code) IN ('ADMIN_PERMIT', 'ADMIN_TAMU', 'ADMIN_TAMU_B2B', 'SHE_ADMIN', 'ROLE_KPI_VERIF')
       OR UPPER(rf.Role_Code) LIKE 'TAMU[_]DEPT[_]%')
  AND UPPER(rf.Role_Code) NOT IN (SELECT k FROM @peran_admin)
  AND rf.Jenis_Role <> 'POOL_APPROVAL';

/* 2. Hak role = halaman yang kini dibuka lewat hibah (sama dengan User::memilikiAksesBaru:
      tanpa saring gfm.Flag_Aktif; halaman tata kelola hanya untuk role admin sah),
      aksi = VIEW + capability default. */
;WITH halaman AS (
    SELECT DISTINCT rf.Id_Role, gfm.Jenis_page
    FROM dbo.N_HRIS_Role_Functional rf
    JOIN dbo.N_HRIS_Grup_Fitur_Map gfm ON gfm.Id_Grup_Fitur = rf.Id_Grup_Fitur AND gfm.Flag_Hibah_Role = 'Y'
    WHERE rf.Flag_Aktif = 'Y'
      AND (LOWER(REPLACE(LTRIM(RTRIM(gfm.Jenis_page)), ' ', '')) NOT IN (SELECT k FROM @tata_kelola)
           OR UPPER(rf.Role_Code) IN (SELECT k FROM @peran_admin))
),
aksi AS (
    SELECT h.Id_Role, h.Jenis_page, @view AS Id_Aksi FROM halaman h
    UNION
    SELECT h.Id_Role, h.Jenis_page, mc.Id_Aksi
    FROM halaman h
    JOIN dbo.N_HRIS_Menu_Capabilities mc
      ON mc.Jenis_page = h.Jenis_page AND mc.Flag_Aktif = 'Y' AND mc.Is_Active_Default = 'Y'
)
INSERT INTO dbo.N_HRIS_Role_Hak (Id_Role, Jenis_page, Id_Aksi, Created_By)
SELECT a.Id_Role, a.Jenis_page, a.Id_Aksi, 'DML-2026-166'
FROM aksi a
WHERE NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Hak x
                  WHERE x.Id_Role = a.Id_Role AND x.Jenis_page = a.Jenis_page AND x.Id_Aksi = a.Id_Aksi);

/* 3. Cakupan per penugasan aktif dari N_HRIS_Role_Scope. Nilai NULL = milik pemegang
      sendiri (divisi/tim-nya). Tanpa baris = SEMUA. */
INSERT INTO dbo.N_HRIS_Role_User_Cakupan (Id_Role_User, Jenis_Cakupan, Nilai)
SELECT DISTINCT ru.Id, m.Jenis, NULL
FROM dbo.N_HRIS_Role_User ru
JOIN dbo.N_HRIS_Role_Scope rs ON rs.Id_Role = ru.Id_Role AND rs.Flag_Aktif = 'Y'
JOIN dbo.N_HRIS_Scope_Definition sd ON sd.Id_Scope = rs.Id_Scope
CROSS APPLY (SELECT CASE sd.Scope_Code
                        WHEN 'GLOBAL_ALL' THEN 'SEMUA'
                        WHEN 'SCOPE_HC_ALL_ACTIVE' THEN 'SEMUA'
                        WHEN 'SCOPE_GA_PHL' THEN 'PHL'
                        WHEN 'DIVISIONAL_FILTER' THEN 'DIVISI'
                        WHEN 'LEADER_DYNAMIC_TEAM' THEN 'TIM'
                    END AS Jenis) m
WHERE ru.Flag_Aktif = 'Y' AND m.Jenis IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_User_Cakupan c
                  WHERE c.Id_Role_User = ru.Id AND c.Jenis_Cakupan = m.Jenis);

/* 4. Tanggung jawab penugasan role ADMIN. */
INSERT INTO dbo.N_HRIS_Role_User_Tanggung_Jawab (Id_Role_User, Kode)
SELECT DISTINCT ru.Id, t.Kode
FROM dbo.N_HRIS_Role_User ru
JOIN dbo.N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role
CROSS APPLY (VALUES ('AKSES'), ('APPROVAL'), ('KEANGGOTAAN')) t(Kode)
WHERE ru.Flag_Aktif = 'Y' AND rf.Jenis_Role = 'ADMIN'
  AND (   (t.Kode = 'AKSES' AND (UPPER(rf.Role_Code) LIKE '%MENU[_]%' OR UPPER(rf.Role_Code) LIKE '%SUPER[_]ADMIN' OR UPPER(rf.Role_Code) = 'KPI_ADMIN_PHL'))
       OR (t.Kode = 'APPROVAL' AND (UPPER(rf.Role_Code) LIKE '%APPROVAL%' OR UPPER(rf.Role_Code) LIKE '%SUPER[_]ADMIN'))
       OR (t.Kode = 'KEANGGOTAAN' AND (UPPER(rf.Role_Code) LIKE '%MEMBERSHIP%' OR UPPER(rf.Role_Code) LIKE '%SUPER[_]ADMIN')))
  AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_User_Tanggung_Jawab x WHERE x.Id_Role_User = ru.Id AND x.Kode = t.Kode);
