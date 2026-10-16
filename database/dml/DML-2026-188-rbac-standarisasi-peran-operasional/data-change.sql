-- DML-2026-188 — RBAC: Standarisasi peran operasional & pool approval (ESS, KPI)
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DDL-2026-165 (Jenis_Role).
--
-- 1. ADMIN_HC_CUTI_VERIFIKATOR & ROLE_KPI_VERIF adalah alur persetujuan di HRIS_Approval_Request:
--    Ubah Jenis_Role menjadi 'POOL_APPROVAL' agar dikenali sebagai alur approval (bukan menu biasa).
-- 2. HCIS_ADMIN_OPS dan HC_OPS_ADMIN memiliki hak identik:
--    Migrasi pemegang HCIS_ADMIN_OPS (User #601) ke HC_OPS_ADMIN, lalu nonaktifkan HCIS_ADMIN_OPS.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

-- 1. Standarkan Jenis_Role alur approval
UPDATE dbo.N_HRIS_Role_Functional
SET Jenis_Role = 'POOL_APPROVAL'
WHERE Role_Code IN ('ADMIN_HC_CUTI_VERIFIKATOR', 'ROLE_KPI_VERIF')
  AND (Jenis_Role IS NULL OR Jenis_Role <> 'POOL_APPROVAL');

-- 2. Gabungkan HCIS_ADMIN_OPS ke HC_OPS_ADMIN
DECLARE @hc_ops INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'HC_OPS_ADMIN');
DECLARE @hcis_admin_ops INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'HCIS_ADMIN_OPS');

IF @hc_ops IS NOT NULL AND @hcis_admin_ops IS NOT NULL
BEGIN
    -- Migrasi penugasan pemegang HCIS_ADMIN_OPS ke HC_OPS_ADMIN bila belum memegang
    INSERT INTO dbo.N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif, Alasan)
    SELECT ru.UserId_Web, @hc_ops, SYSDATETIME(), 'DML-2026-188', 'Y', 'DML-2026-188: Penggabungan HCIS_ADMIN_OPS ke HC_OPS_ADMIN'
    FROM dbo.N_HRIS_Role_User ru
    WHERE ru.Id_Role = @hcis_admin_ops AND ru.Flag_Aktif = 'Y'
      AND NOT EXISTS (
          SELECT 1 FROM dbo.N_HRIS_Role_User x
          WHERE x.Id_Role = @hc_ops AND x.UserId_Web = ru.UserId_Web AND x.Flag_Aktif = 'Y'
      );

    -- Nonaktifkan penugasan lama di HCIS_ADMIN_OPS
    UPDATE dbo.N_HRIS_Role_User
    SET Flag_Aktif = 'T', Alasan = 'DML-2026-188: Digabungkan ke HC_OPS_ADMIN'
    WHERE Id_Role = @hcis_admin_ops AND Flag_Aktif = 'Y';

    -- Nonaktifkan peran HCIS_ADMIN_OPS
    UPDATE dbo.N_HRIS_Role_Functional
    SET Flag_Aktif = 'T'
    WHERE Id_Role = @hcis_admin_ops AND Flag_Aktif = 'Y';
END;

COMMIT TRANSACTION;

-- Verifikasi peran ESS & KPI
SELECT rf.Id_Role, rf.Role_Code, rf.Role_Name, rf.Jenis_Role, rf.Kode_Tingkat, rf.Flag_Aktif,
       (SELECT COUNT(*) FROM dbo.N_HRIS_Role_User ru WHERE ru.Id_Role = rf.Id_Role AND ru.Flag_Aktif = 'Y') AS pemegang
FROM dbo.N_HRIS_Role_Functional rf
WHERE rf.Role_Code IN ('ADMIN_HC_CUTI_VERIFIKATOR', 'ROLE_KPI_VERIF', 'HC_OPS_ADMIN', 'HCIS_ADMIN_OPS');
