-- DML-2026-190 — RBAC: Penugasan lengkap Dept Head & PIC Admin seluruh modul
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DML-2026-182, DML-2026-187.
--
-- Memasang pejabat Dept Head (Super Admin) & PIC Pengelola (Admin Modul) di seluruh 5 modul
-- agar hierarki delegasi wewenang terdistribusi merata dan siap dioperasikan:
--  1. ESS: Frans Bachtiar (#594) -> ESS_SUPER_ADMIN (Cakupan: SEMUA, Tanggung Jawab: AKSES)
--  2. VISITOR: Dimas (#603) -> VISITOR_SUPER_ADMIN (Cakupan: SEMUA, Tanggung Jawab: AKSES)
--  3. DMS: Diva Mona Lisa (#595) -> DMS_ADMIN (Cakupan: SEMUA, Tanggung Jawab: AKSES)
--  4. LMS: Erick (#631) -> LMS_SUPER_ADMIN (Cakupan: SEMUA, Tanggung Jawab: AKSES)
--  5. LMS: Amelia (#683) -> LMS_ADMIN (Cakupan: SEMUA, Tanggung Jawab: AKSES)

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @ess_sa_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'ESS_SUPER_ADMIN');
DECLARE @vis_sa_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'VISITOR_SUPER_ADMIN');
DECLARE @dms_adm_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'DMS_ADMIN');
DECLARE @lms_sa_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'LMS_SUPER_ADMIN');
DECLARE @lms_adm_id INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'LMS_ADMIN');

DECLARE @Baru TABLE (Id_Role_User INT);

-- 1. ESS: Frans Bachtiar (#594) -> ESS_SUPER_ADMIN
IF @ess_sa_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @ess_sa_id AND UserId_Web = 594 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id INTO @Baru
    VALUES (594, @ess_sa_id, SYSDATETIME(), 'DML-2026-190', 'Y', 'DML-2026-190: Penugasan Dept Head ESS');
END;

-- 2. VISITOR: Dimas (#603) -> VISITOR_SUPER_ADMIN
IF @vis_sa_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @vis_sa_id AND UserId_Web = 603 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id INTO @Baru
    VALUES (603, @vis_sa_id, SYSDATETIME(), 'DML-2026-190', 'Y', 'DML-2026-190: Penugasan Dept Head VISITOR');
END;

-- 3. DMS: Diva Mona Lisa (#595) -> DMS_ADMIN
IF @dms_adm_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @dms_adm_id AND UserId_Web = 595 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id INTO @Baru
    VALUES (595, @dms_adm_id, SYSDATETIME(), 'DML-2026-190', 'Y', 'DML-2026-190: Penugasan PIC Admin DMS');
END;

-- 4. LMS: Erick (#631) -> LMS_SUPER_ADMIN
IF @lms_sa_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @lms_sa_id AND UserId_Web = 631 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id INTO @Baru
    VALUES (631, @lms_sa_id, SYSDATETIME(), 'DML-2026-190', 'Y', 'DML-2026-190: Penugasan Dept Head LMS');
END;

-- 5. LMS: Amelia (#683) -> LMS_ADMIN
IF @lms_adm_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User WHERE Id_Role = @lms_adm_id AND UserId_Web = 683 AND Flag_Aktif = 'Y'
)
BEGIN
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    OUTPUT INSERTED.Id INTO @Baru
    VALUES (683, @lms_adm_id, SYSDATETIME(), 'DML-2026-190', 'Y', 'DML-2026-190: Penugasan PIC Admin LMS');
END;

-- Isi cakupan SEMUA untuk penugasan baru
INSERT INTO dbo.N_HRIS_Role_User_Cakupan (Id_Role_User, Jenis_Cakupan, Nilai, Flag_Aktif)
SELECT b.Id_Role_User, 'SEMUA', NULL, 'Y'
FROM @Baru b
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User_Cakupan c
    WHERE c.Id_Role_User = b.Id_Role_User AND c.Jenis_Cakupan = 'SEMUA'
);

-- Isi tanggung jawab AKSES untuk penugasan baru
INSERT INTO dbo.N_HRIS_Role_User_Tanggung_Jawab (Id_Role_User, Kode, Flag_Aktif)
SELECT b.Id_Role_User, 'AKSES', 'Y'
FROM @Baru b
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_User_Tanggung_Jawab tj
    WHERE tj.Id_Role_User = b.Id_Role_User AND tj.Kode = 'AKSES'
);

COMMIT TRANSACTION;

-- Verifikasi: daftar administrator per modul
SELECT g.Kode_Grup_Fitur AS modul, rf.Kode_Tingkat, rf.Role_Code, k.Nama, k.Kode_Karyawan
FROM dbo.N_HRIS_Role_User ru
JOIN dbo.N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role
JOIN dbo.N_HRIS_Master_Grup_Fitur g ON g.Id_Grup_Fitur = rf.Id_Grup_Fitur
JOIN dbo.Karyawan k ON k.UserID_Web = ru.UserId_Web
WHERE ru.Flag_Aktif = 'Y' AND rf.Flag_Aktif = 'Y' AND rf.Jenis_Role = 'ADMIN'
ORDER BY g.Kode_Grup_Fitur, rf.Kode_Tingkat DESC, k.Nama;
