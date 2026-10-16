-- DML-2026-185 — RBAC Tahap B (VISITOR): peran Admin VISITOR tunggal
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DDL-2026-165 (Jenis_Role, N_HRIS_Role_Hak).
--
-- VISITOR_MENU_ADMINISTRATOR dan VISITOR_APPROVAL_ADMINISTRATOR berhak halaman identik
-- (dipecah menurut tanggung jawab, kini cukup satu Admin modul + cakupan). Langkah ini HANYA
-- membuat VISITOR_ADMIN dengan hak salinan VISITOR_MENU_ADMINISTRATOR. Belum ada penugasan
-- yang dipindah dan peran lama tetap aktif (langkah berikutnya, change terpisah).
-- Kode_Tingkat diisi bila kolomnya ada (DDL-2026-181); tanpa kolom itu tingkat diturunkan dari Jenis_Role.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @visitor INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR');

IF @visitor IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'VISITOR_ADMIN')
    INSERT INTO dbo.N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_By, Id_Grup_Fitur, Jenis_Role)
    VALUES ('VISITOR_ADMIN', 'Admin VISITOR',
            N'Admin modul VISITOR: menugaskan peran & memberi akses VISITOR dalam cakupannya. Menggantikan VISITOR Menu/Approval Administrator.',
            'Y', 'DML-2026-185', @visitor, 'ADMIN');

IF COL_LENGTH('dbo.N_HRIS_Role_Functional', 'Kode_Tingkat') IS NOT NULL
    EXEC sp_executesql N'UPDATE dbo.N_HRIS_Role_Functional SET Kode_Tingkat = ''ADMIN''
        WHERE Role_Code = ''VISITOR_ADMIN'' AND (Kode_Tingkat IS NULL OR Kode_Tingkat <> ''ADMIN'')';

INSERT INTO dbo.N_HRIS_Role_Hak (Id_Role, Jenis_page, Id_Aksi, Flag_Aktif, Created_By)
SELECT DISTINCT baru.Id_Role, h.Jenis_page, h.Id_Aksi, 'Y', 'DML-2026-185'
FROM dbo.N_HRIS_Role_Hak h
JOIN dbo.N_HRIS_Role_Functional lama ON lama.Id_Role = h.Id_Role AND lama.Role_Code = 'VISITOR_MENU_ADMINISTRATOR'
CROSS JOIN dbo.N_HRIS_Role_Functional baru
WHERE baru.Role_Code = 'VISITOR_ADMIN'
  AND h.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Hak x
      WHERE x.Id_Role = baru.Id_Role AND x.Id_Aksi = h.Id_Aksi
        AND LOWER(REPLACE(LTRIM(RTRIM(x.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', ''))
  );

COMMIT TRANSACTION;

-- Verifikasi (harus: 1 baris peran ADMIN/ADMIN; jumlah hak = jumlah hak aktif VISITOR_MENU_ADMINISTRATOR)
SELECT r.Id_Role, r.Role_Code, r.Jenis_Role,
       (SELECT COUNT(*) FROM dbo.N_HRIS_Role_Hak h WHERE h.Id_Role = r.Id_Role AND h.Flag_Aktif = 'Y') AS jumlah_hak
FROM dbo.N_HRIS_Role_Functional r
WHERE r.Role_Code IN ('VISITOR_ADMIN', 'VISITOR_MENU_ADMINISTRATOR');
