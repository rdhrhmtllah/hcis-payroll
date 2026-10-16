-- DML-2026-195 — RBAC: Registrasi Fitur Percontohan ESS.CUTI dan Pemetaan Role Kemampuan
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DDL-2026-194.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @grupEss INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'ESS');

-- 1. Registrasi Master Fitur Bisnis: ESS.CUTI
IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Fitur WHERE Kode_Fitur = 'ESS.CUTI')
BEGIN
    INSERT INTO dbo.N_HRIS_Fitur (
        Kode_Fitur, Id_Grup_Fitur, Nama_Fitur, Deskripsi, Jenis_Page,
        Flag_Punya_Approval, Id_Approval_Config, Halaman_Antrean_Approval,
        Flag_Aktif, Created_By
    )
    VALUES (
        'ESS.CUTI', @grupEss, 'Pengajuan Cuti Karyawan',
        N'Pengelolaan izin cuti tahunan, cuti besar, dispensasi, serta persetujuan alur cuti staf.',
        'cutiPage', 'Y', NULL, 'cutiPage', 'Y', 4
    );
END;

-- 2. Registrasi Kemampuan Fitur: ESS.CUTI.*
MERGE dbo.N_HRIS_Fitur_Kemampuan AS target
USING (VALUES
    ('ESS.CUTI.LIHAT', 'ESS.CUTI', 'Melihat Hak & Saldo Cuti', 'VIEW', 'DIRI,TIM,UNIT,SEMUA', 'T', 1),
    ('ESS.CUTI.AJUKAN', 'ESS.CUTI', 'Mengajukan Permohonan Cuti', 'CREATE', 'DIRI,TIM', 'T', 2),
    ('ESS.CUTI.BATALKAN', 'ESS.CUTI', 'Membatalkan Permohonan Cuti Draf', 'DELETE', 'DIRI,TIM', 'T', 3),
    ('ESS.CUTI.VERIFIKASI', 'ESS.CUTI', 'Memverifikasi Permohonan Cuti Staf', 'APPROVE', 'TIM,UNIT,SEMUA', 'Y', 4),
    ('ESS.CUTI.ADMIN', 'ESS.CUTI', 'Mengelola Master & Kuota Cuti Karyawan', 'EDIT', 'UNIT,SEMUA', 'T', 5)
) AS source (Kode_Kemampuan, Kode_Fitur, Nama_Kemampuan, Jenis_Aksi, Cakupan_Tersedia, Flag_Wajib_Approval, Sort_Order)
ON target.Kode_Kemampuan = source.Kode_Kemampuan
WHEN NOT MATCHED THEN
    INSERT (Kode_Kemampuan, Kode_Fitur, Nama_Kemampuan, Jenis_Aksi, Cakupan_Tersedia, Flag_Wajib_Approval, Sort_Order, Flag_Aktif)
    VALUES (source.Kode_Kemampuan, source.Kode_Fitur, source.Nama_Kemampuan, source.Jenis_Aksi, source.Cakupan_Tersedia, source.Flag_Wajib_Approval, source.Sort_Order, 'Y');

-- 3. Pemetaan Kemampuan ke Peran:
--    a. ESS_SUPER_ADMIN (Id: 741) -> Seluruh kemampuan ESS.CUTI
--    b. ESS_ADMIN (Id: 940) -> ESS.CUTI.LIHAT, ESS.CUTI.ADMIN
--    c. ADMIN_HC_CUTI_VERIFIKATOR (Id: 8) -> ESS.CUTI.LIHAT, ESS.CUTI.VERIFIKASI

DECLARE @superId INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'ESS_SUPER_ADMIN');
DECLARE @adminId INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'ESS_ADMIN');
DECLARE @verifId INT = (SELECT Id_Role FROM dbo.N_HRIS_Role_Functional WHERE Role_Code = 'ADMIN_HC_CUTI_VERIFIKATOR');

-- Super Admin: Full Akses Kemampuan
INSERT INTO dbo.N_HRIS_Role_Kemampuan (Id_Role, Kode_Kemampuan, Flag_Aktif, Created_By)
SELECT @superId, k.Kode_Kemampuan, 'Y', 4
FROM dbo.N_HRIS_Fitur_Kemampuan k
WHERE k.Kode_Fitur = 'ESS.CUTI'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Kemampuan rk
      WHERE rk.Id_Role = @superId AND rk.Kode_Kemampuan = k.Kode_Kemampuan
  );

-- Admin Modul: Kelola Kuota & Lihat
INSERT INTO dbo.N_HRIS_Role_Kemampuan (Id_Role, Kode_Kemampuan, Flag_Aktif, Created_By)
SELECT @adminId, k.Kode_Kemampuan, 'Y', 4
FROM dbo.N_HRIS_Fitur_Kemampuan k
WHERE k.Kode_Kemampuan IN ('ESS.CUTI.LIHAT', 'ESS.CUTI.ADMIN')
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Kemampuan rk
      WHERE rk.Id_Role = @adminId AND rk.Kode_Kemampuan = k.Kode_Kemampuan
  );

-- Verifikator Cuti: Lihat & Verifikasi
INSERT INTO dbo.N_HRIS_Role_Kemampuan (Id_Role, Kode_Kemampuan, Flag_Aktif, Created_By)
SELECT @verifId, k.Kode_Kemampuan, 'Y', 4
FROM dbo.N_HRIS_Fitur_Kemampuan k
WHERE k.Kode_Kemampuan IN ('ESS.CUTI.LIHAT', 'ESS.CUTI.VERIFIKASI')
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Kemampuan rk
      WHERE rk.Id_Role = @verifId AND rk.Kode_Kemampuan = k.Kode_Kemampuan
  );

COMMIT TRANSACTION;

-- Verifikasi hasil
SELECT f.Kode_Fitur, f.Nama_Fitur, fk.Kode_Kemampuan, fk.Nama_Kemampuan, fk.Jenis_Aksi, rf.Role_Code
FROM dbo.N_HRIS_Fitur f
JOIN dbo.N_HRIS_Fitur_Kemampuan fk ON fk.Kode_Fitur = f.Kode_Fitur
LEFT JOIN dbo.N_HRIS_Role_Kemampuan rk ON rk.Kode_Kemampuan = fk.Kode_Kemampuan AND rk.Flag_Aktif = 'Y'
LEFT JOIN dbo.N_HRIS_Role_Functional rf ON rf.Id_Role = rk.Id_Role
WHERE f.Kode_Fitur = 'ESS.CUTI'
ORDER BY fk.Sort_Order, rf.Role_Code;
