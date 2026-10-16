-- DML-2026-198 — RBAC: Standarisasi Menu Global Karyawan Mandiri (Self-Service) & Pembersihan Grant
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DML-2026-197.
--
-- 1. Mengubah menu-menu layanan mandiri karyawan (Personal & KPI Individu) menjadi Menu Global (Is_Global_Menu = 'Y'):
--    - FormKPIPage (Input Target KPI Sendiri)
--    - UpdatePerformancePage (Update Capaian KPI Sendiri)
--    - dashboardStaff (Dashboard KPI Individu)
--    - cutiPage (Cuti Pribadi)
--    - IzinPage (Izin Pribadi)
--    - absensiPage (Absensi Pribadi)
--    - selfShift (Shift Pribadi)
--    - konversiLembur (Konversi Lembur Pribadi)
--
-- 2. Menonaktifkan secara aman (soft-delete, Flag_Aktif = 'T') baris grant langsung di HRIS_Page_Access
--    untuk menu-menu di atas, karena hak aksesnya kini diberikan secara otomatis dan terstandarisasi
--    lewat mekanisme MENU_GLOBAL tanpa perlu 1.900+ baris bypass perorangan.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

-- 1. Set Is_Global_Menu = 'Y' pada menu layanan mandiri karyawan
UPDATE dbo.N_HRIS_Menus
SET Is_Global_Menu = 'Y'
WHERE Jenis_page IN (
    'FormKPIPage',
    'UpdatePerformancePage',
    'dashboardStaff',
    'cutiPage',
    'IzinPage',
    'absensiPage',
    'selfShift',
    'konversiLembur'
);

-- 2. Nonaktifkan grant langsung yang kini sudah tercakup oleh Menu Global
UPDATE pa
SET pa.Flag_Aktif = 'T',
    pa.Alasan = ISNULL(pa.Alasan + ' | ', '') + 'DML-2026-198: Tercakup otomatis sebagai Menu Global Karyawan Mandiri'
FROM dbo.HRIS_Page_Access pa
WHERE pa.Flag_Aktif = 'Y'
  AND pa.Jenis_page IN (
      'FormKPIPage',
      'UpdatePerformancePage',
      'dashboardStaff',
      'cutiPage',
      'IzinPage',
      'absensiPage',
      'selfShift',
      'konversiLembur'
  );

COMMIT TRANSACTION;

-- Verifikasi hasil
SELECT 'Sisa grant aktif' AS kategori, COUNT(*) AS jumlah FROM dbo.HRIS_Page_Access WHERE Flag_Aktif = 'Y'
UNION ALL
SELECT 'Grant dinonaktifkan DML-198', COUNT(*) FROM dbo.HRIS_Page_Access WHERE Alasan LIKE '%DML-2026-198%'
UNION ALL
SELECT 'Total Menu Global saat ini', COUNT(*) FROM dbo.N_HRIS_Menus WHERE Is_Global_Menu = 'Y';
