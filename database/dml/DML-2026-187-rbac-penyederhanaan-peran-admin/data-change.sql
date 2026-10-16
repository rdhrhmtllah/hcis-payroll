-- DML-2026-187 — RBAC: Penyederhanaan peran admin kembar (ESS, KPI, LMS, VISITOR, DMS)
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DDL-2026-165, DDL-2026-181, DML-2026-182.
--
-- Menggabungkan peran admin yang terpecah per tanggung jawab (MENU / APPROVAL / MEMBERSHIP / PHL)
-- menjadi 1 peran Admin Modul tunggal per modul:
--  1. DMS: Nonaktifkan DMS_MENU_ADMINISTRATOR & DMS_APPROVAL_ADMINISTRATOR (0 pemegang).
--  2. VISITOR: Migrasi pemegang (Arya #624) ke VISITOR_ADMIN (cakupan SEMUA); nonaktifkan peran lama.
--  3. LMS: Buat LMS_ADMIN (hak dari LMS_MENU_ADMINISTRATOR); nonaktifkan peran lama (0 pemegang).
--  4. KPI: Buat KPI_ADMIN (hak dari KPI_MENU_ADMINISTRATOR); migrasi Iqbal #648 (SEMUA) & Haris #644 (PHL);
--          nonaktifkan KPI_MENU..., KPI_APPROVAL..., KPI_MEMBERSHIP..., KPI_ADMIN_PHL.
--  5. ESS: Buat ESS_ADMIN (hak dari ESS_MENU_ADMINISTRATOR); migrasi Aji #622 (SEMUA) & Dhea #600 (PHL);
--          nonaktifkan ESS_MENU..., ESS_APPROVAL..., ESS_MEMBERSHIP..., ESS_..._PHL.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

-- ══ 1. BUAT PERAN ADMIN MODUL TUNGGAL YANG BELUM ADA ══
DECLARE @ess INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'ESS');
DECLARE @kpi INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'KPI');
DECLARE @lms INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'LMS');

-- 1.1 LMS_ADMIN
IF @lms IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'LMS_ADMIN')
    INSERT INTO dbo.N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_By, Id_Grup_Fitur, Jenis_Role, Kode_Tingkat)
    VALUES ('LMS_ADMIN', 'Admin LMS', N'Admin modul LMS: mengelola kursus, materi, dan penugasan peran LMS.',
            'Y', 'DML-2026-187', @lms, 'ADMIN', 'ADMIN');

-- 1.2 KPI_ADMIN
IF @kpi IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'KPI_ADMIN')
    INSERT INTO dbo.N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_By, Id_Grup_Fitur, Jenis_Role, Kode_Tingkat)
    VALUES ('KPI_ADMIN', 'Admin KPI', N'Admin modul KPI: mengelola periode, target, dan penugasan peran KPI.',
            'Y', 'DML-2026-187', @kpi, 'ADMIN', 'ADMIN');

-- 1.3 ESS_ADMIN
IF @ess IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'ESS_ADMIN')
    INSERT INTO dbo.N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_By, Id_Grup_Fitur, Jenis_Role, Kode_Tingkat)
    VALUES ('ESS_ADMIN', 'Admin ESS', N'Admin modul ESS: mengelola operasional karyawan, cuti, izin, lembur, dan penugasan peran ESS.',
            'Y', 'DML-2026-187', @ess, 'ADMIN', 'ADMIN');

-- Salin hak ke peran admin baru
INSERT INTO dbo.N_HRIS_Role_Hak (Id_Role, Jenis_page, Id_Aksi, Flag_Aktif, Created_By)
SELECT DISTINCT baru.Id_Role, h.Jenis_page, h.Id_Aksi, 'Y', 'DML-2026-187'
FROM dbo.N_HRIS_Role_Hak h
JOIN dbo.N_HRIS_Role_Functional lama ON lama.Id_Role = h.Id_Role
JOIN dbo.N_HRIS_Role_Functional baru ON (
    (lama.Role_Code = 'LMS_MENU_ADMINISTRATOR' AND baru.Role_Code = 'LMS_ADMIN') OR
    (lama.Role_Code = 'KPI_MENU_ADMINISTRATOR' AND baru.Role_Code = 'KPI_ADMIN') OR
    (lama.Role_Code = 'ESS_MENU_ADMINISTRATOR' AND baru.Role_Code = 'ESS_ADMIN')
)
WHERE h.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Hak x
      WHERE x.Id_Role = baru.Id_Role AND x.Id_Aksi = h.Id_Aksi
        AND LOWER(REPLACE(LTRIM(RTRIM(x.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', ''))
  );

-- ══ 2. MIGRASI PEMEGANG KE ADMIN MODUL TUNGGAL ══
DECLARE @visitor_admin_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'VISITOR_ADMIN');
DECLARE @kpi_admin_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'KPI_ADMIN');
DECLARE @ess_admin_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'ESS_ADMIN');

-- Helper tabel penugasan baru
DECLARE @Baru TABLE (Id_Role_User INT, UserId_Web INT, CakupanJenis VARCHAR(20));

-- 2.1 VISITOR: Arya #624 -> VISITOR_ADMIN (SEMUA)
IF @visitor_admin_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @visitor_admin_id AND UserId_Web = 624 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id, INSERTED.UserId_Web, 'SEMUA' INTO @Baru
    VALUES (624, @visitor_admin_id, SYSDATETIME(), 'DML-2026-187', 'Y', 'DML-2026-187: Penyatuan admin modul VISITOR');
END;

-- 2.2 KPI: Iqbal #648 -> KPI_ADMIN (SEMUA)
IF @kpi_admin_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @kpi_admin_id AND UserId_Web = 648 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id, INSERTED.UserId_Web, 'SEMUA' INTO @Baru
    VALUES (648, @kpi_admin_id, SYSDATETIME(), 'DML-2026-187', 'Y', 'DML-2026-187: Penyatuan admin modul KPI');
END;

-- 2.3 KPI: Haris #644 -> KPI_ADMIN (Cakupan PHL)
IF @kpi_admin_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @kpi_admin_id AND UserId_Web = 644 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id, INSERTED.UserId_Web, 'PHL' INTO @Baru
    VALUES (644, @kpi_admin_id, SYSDATETIME(), 'DML-2026-187', 'Y', 'DML-2026-187: Penyatuan admin modul KPI (PHL)');
END;

-- 2.4 ESS: Aji #622 -> ESS_ADMIN (SEMUA)
IF @ess_admin_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @ess_admin_id AND UserId_Web = 622 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id, INSERTED.UserId_Web, 'SEMUA' INTO @Baru
    VALUES (622, @ess_admin_id, SYSDATETIME(), 'DML-2026-187', 'Y', 'DML-2026-187: Penyatuan admin modul ESS');
END;

-- 2.5 ESS: Dhea #600 -> ESS_ADMIN (Cakupan PHL)
IF @ess_admin_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @ess_admin_id AND UserId_Web = 600 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id, INSERTED.UserId_Web, 'PHL' INTO @Baru
    VALUES (600, @ess_admin_id, SYSDATETIME(), 'DML-2026-187', 'Y', 'DML-2026-187: Penyatuan admin modul ESS (PHL)');
END;

-- Isi cakupan untuk penugasan baru
INSERT INTO dbo.N_HRIS_Role_User_Cakupan (Id_Role_User, Jenis_Cakupan, Nilai, Flag_Aktif)
SELECT b.Id_Role_User, b.CakupanJenis, NULL, 'Y'
FROM @Baru b
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User_Cakupan c
    WHERE c.Id_Role_User = b.Id_Role_User AND c.Jenis_Cakupan = b.CakupanJenis
);

-- Isi tanggung jawab AKSES untuk penugasan baru
INSERT INTO dbo.N_HRIS_Role_User_Tanggung_Jawab (Id_Role_User, Kode, Flag_Aktif)
SELECT b.Id_Role_User, 'AKSES', 'Y'
FROM @Baru b
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User_Tanggung_Jawab tj
    WHERE tj.Id_Role_User = b.Id_Role_User AND tj.Kode = 'AKSES'
);

-- ══ 3. NONAKTIFKAN PENUGASAN LAMA ══
UPDATE ru
SET ru.Flag_Aktif = 'T', ru.Alasan = 'DML-2026-187: Digabungkan ke Admin modul tunggal'
FROM dbo.N_HRIS_Role_User ru
JOIN dbo.N_HRIS_Role_Functional r ON r.Id_Role = ru.Id_Role
WHERE r.Role_Code IN (
    'VISITOR_MENU_ADMINISTRATOR', 'VISITOR_APPROVAL_ADMINISTRATOR',
    'KPI_MENU_ADMINISTRATOR', 'KPI_APPROVAL_ADMINISTRATOR', 'KPI_MEMBERSHIP_ADMINISTRATOR', 'KPI_ADMIN_PHL',
    'ESS_MENU_ADMINISTRATOR', 'ESS_APPROVAL_ADMINISTRATOR', 'ESS_MEMBERSHIP_ADMINISTRATOR',
    'ESS_Menu_Administrator_PHL', 'ESS_Approval_Administrator_PHL'
) AND ru.Flag_Aktif = 'Y';

-- ══ 4. NONAKTIFKAN PERAN LAMA DI KATALOG ══
UPDATE dbo.N_HRIS_Role_Functional
SET Flag_Aktif = 'T'
WHERE Role_Code IN (
    'DMS_MENU_ADMINISTRATOR', 'DMS_APPROVAL_ADMINISTRATOR',
    'VISITOR_MENU_ADMINISTRATOR', 'VISITOR_APPROVAL_ADMINISTRATOR',
    'LMS_MENU_ADMINISTRATOR', 'LMS_APPROVAL_ADMINISTRATOR',
    'KPI_MENU_ADMINISTRATOR', 'KPI_APPROVAL_ADMINISTRATOR', 'KPI_MEMBERSHIP_ADMINISTRATOR', 'KPI_ADMIN_PHL',
    'ESS_MENU_ADMINISTRATOR', 'ESS_APPROVAL_ADMINISTRATOR', 'ESS_MEMBERSHIP_ADMINISTRATOR',
    'ESS_Menu_Administrator_PHL', 'ESS_Approval_Administrator_PHL'
) AND Flag_Aktif = 'Y';

COMMIT TRANSACTION;

-- Verifikasi peran admin aktif per modul
SELECT rf.Role_Code, rf.Role_Name, g.Kode_Grup_Fitur AS modul,
       (SELECT COUNT(*) FROM dbo.N_HRIS_Role_User ru WHERE ru.Id_Role = rf.Id_Role AND ru.Flag_Aktif = 'Y') AS pemegang
FROM dbo.N_HRIS_Role_Functional rf
JOIN dbo.N_HRIS_Master_Grup_Fitur g ON g.Id_Grup_Fitur = rf.Id_Grup_Fitur
WHERE rf.Flag_Aktif = 'Y' AND rf.Jenis_Role = 'ADMIN'
ORDER BY g.Kode_Grup_Fitur, rf.Role_Code;
