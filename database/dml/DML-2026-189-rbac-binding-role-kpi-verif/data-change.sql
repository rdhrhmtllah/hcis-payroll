-- DML-2026-189 — RBAC: Binding peran ROLE_KPI_VERIF ke modul KPI
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang).
--
-- ROLE_KPI_VERIF (Verifikator KPI) sebelumnya memiliki Id_Grup_Fitur = NULL sehingga
-- muncul sebagai "Lintas Modul" dan tidak masuk ke dalam manajemen peran modul KPI (/admin/kpi/akses-peran).
-- DML ini mengikat ROLE_KPI_VERIF ke modul KPI (Id_Grup_Fitur = 2).

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @kpi INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'KPI');

IF @kpi IS NOT NULL
BEGIN
    UPDATE dbo.N_HRIS_Role_Functional
    SET Id_Grup_Fitur = @kpi
    WHERE Role_Code = 'ROLE_KPI_VERIF'
      AND (Id_Grup_Fitur IS NULL OR Id_Grup_Fitur <> @kpi);
END;

COMMIT TRANSACTION;

-- Verifikasi: ROLE_KPI_VERIF harus memiliki Id_Grup_Fitur = 2 (KPI)
SELECT rf.Id_Role, rf.Role_Code, rf.Role_Name, g.Kode_Grup_Fitur AS modul, rf.Jenis_Role, rf.Kode_Tingkat
FROM dbo.N_HRIS_Role_Functional rf
LEFT JOIN dbo.N_HRIS_Master_Grup_Fitur g ON g.Id_Grup_Fitur = rf.Id_Grup_Fitur
WHERE rf.Role_Code = 'ROLE_KPI_VERIF';
