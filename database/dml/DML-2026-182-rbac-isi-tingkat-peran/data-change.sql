-- DML-2026-182 — RBAC: isi master tingkat peran, tingkat setiap peran, Super Admin per modul
-- Target: Web_HRIS. Idempoten. Butuh DDL-2026-165 (Jenis_Role, N_HRIS_Role_Hak), DML-2026-166 (Jenis_Role terisi),
-- DDL-2026-181, DML-2026-180. Berhenti bila DML-2026-166 belum dijalankan: tingkat yang terisi dari Jenis_Role
-- bawaan ('AKSES') akan menurunkan semua admin menjadi Pengguna secara permanen.
--
-- 1. Master: SYSTEM_ADMIN 100 (informasi; bendera Is_System_Admin), SUPER_ADMIN 80, ADMIN 60, PENGGUNA 10.
-- 2. Tingkat peran yang belum terisi: *_SUPER_ADMIN bermodul -> SUPER_ADMIN; Jenis_Role ADMIN -> ADMIN;
--    sisanya -> PENGGUNA.
-- 3. Super Admin per modul ESS/LMS/VISITOR/DMS (KPI_SUPER_ADMIN sudah ada). Hak DMS_SUPER_ADMIN disalin
--    dari DMS_ADMIN (DMS bisa sudah BARU, sehingga tak disinkron dari hibah); modul lain disamakan oleh
--    rbac:sinkron-hak. Tanpa penugasan.
-- 4. HCIS_SUPER_ADMIN (lintas modul, DML-2026-180) dinonaktifkan beserta penugasannya — diganti Super Admin
--    per modul.

SET XACT_ABORT ON;

IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional WHERE Jenis_Role = 'ADMIN')
    THROW 51182, 'DML-2026-182: jalankan DML-2026-166 dulu (Jenis_Role belum terisi).', 1;

BEGIN TRANSACTION;

MERGE dbo.N_HRIS_Tingkat_Peran AS t
USING (VALUES
    ('SYSTEM_ADMIN', 'System Admin', 100, 'Y', 'Y', 'Y', N'Bendera KPI_Users.Is_System_Admin. Semua modul, katalog & mode modul, mengangkat Super Admin modul.'),
    ('SUPER_ADMIN', 'Super Admin', 80, 'Y', 'Y', 'Y', N'Per modul: mengangkat/mencabut admin modulnya, mengatur matriks hak (menu, tambah/ubah/hapus).'),
    ('ADMIN', 'Admin', 60, 'T', 'T', 'Y', N'Per modul: menugaskan peran pengguna & grant kepada karyawan dalam cakupannya.'),
    ('PENGGUNA', 'Pengguna', 10, 'T', 'T', 'T', N'Memakai halaman sesuai peran & grant.')
) AS s (Kode_Tingkat, Nama_Tingkat, Peringkat, Boleh_Angkat, Boleh_Ubah_Matriks, Boleh_Tugaskan, Keterangan)
ON t.Kode_Tingkat = s.Kode_Tingkat
WHEN NOT MATCHED THEN
    INSERT (Kode_Tingkat, Nama_Tingkat, Peringkat, Boleh_Angkat, Boleh_Ubah_Matriks, Boleh_Tugaskan, Keterangan)
    VALUES (s.Kode_Tingkat, s.Nama_Tingkat, s.Peringkat, s.Boleh_Angkat, s.Boleh_Ubah_Matriks, s.Boleh_Tugaskan, s.Keterangan);

INSERT INTO dbo.N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_By, Id_Grup_Fitur, Jenis_Role, Kode_Tingkat)
SELECT v.Kode + '_SUPER_ADMIN', 'Super Admin ' + v.Kode, N'Super Admin modul ' + v.Kode + N' (mengangkat admin modul & mengatur matriks hak).',
       'Y', 'DML-2026-182', g.Id_Grup_Fitur, 'ADMIN', 'SUPER_ADMIN'
FROM (VALUES ('ESS'), ('LMS'), ('VISITOR'), ('DMS')) AS v (Kode)
JOIN dbo.N_HRIS_Master_Grup_Fitur g ON g.Kode_Grup_Fitur = v.Kode
WHERE NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Functional r WHERE r.Role_Code = v.Kode + '_SUPER_ADMIN');

UPDATE dbo.N_HRIS_Role_Functional
SET Kode_Tingkat = CASE
        WHEN Role_Code LIKE '%[_]SUPER[_]ADMIN' AND Id_Grup_Fitur IS NOT NULL AND Jenis_Role = 'ADMIN' THEN 'SUPER_ADMIN'
        WHEN Jenis_Role = 'ADMIN' THEN 'ADMIN'
        ELSE 'PENGGUNA' END
WHERE Kode_Tingkat IS NULL;

INSERT INTO dbo.N_HRIS_Role_Hak (Id_Role, Jenis_page, Id_Aksi, Flag_Aktif, Created_By)
SELECT s.Id_Role, h.Jenis_page, h.Id_Aksi, 'Y', 'DML-2026-182'
FROM dbo.N_HRIS_Role_Functional s
JOIN dbo.N_HRIS_Role_Functional a ON a.Role_Code = 'DMS_ADMIN'
JOIN dbo.N_HRIS_Role_Hak h ON h.Id_Role = a.Id_Role AND h.Flag_Aktif = 'Y'
WHERE s.Role_Code = 'DMS_SUPER_ADMIN'
  AND NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Role_Hak x WHERE x.Id_Role = s.Id_Role AND x.Jenis_page = h.Jenis_page AND x.Id_Aksi = h.Id_Aksi);

UPDATE ru SET ru.Flag_Aktif = 'T', ru.Alasan = 'DML-2026-182: Super Admin lintas modul diganti per modul'
FROM dbo.N_HRIS_Role_User ru
JOIN dbo.N_HRIS_Role_Functional r ON r.Id_Role = ru.Id_Role
WHERE r.Role_Code = 'HCIS_SUPER_ADMIN' AND ru.Flag_Aktif = 'Y';

UPDATE dbo.N_HRIS_Role_Functional SET Flag_Aktif = 'T' WHERE Role_Code = 'HCIS_SUPER_ADMIN' AND Flag_Aktif = 'Y';

COMMIT TRANSACTION;
