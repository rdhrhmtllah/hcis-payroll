-- DML-2026-196 — RBAC: Registrasi Fitur Bisnis & Kemampuan Seluruh Modul (VISITOR, DMS, KPI, ESS)
-- Target: Web_HRIS. Idempoten (aman dijalankan ulang). Butuh DDL-2026-194.

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @grupEss INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'ESS');
DECLARE @grupKpi INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'KPI');
DECLARE @grupVis INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR');
DECLARE @grupDms INT = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'DMS');

-- ══ 1. REGISTRASI MASTER FITUR BISNIS (N_HRIS_Fitur) ══
MERGE dbo.N_HRIS_Fitur AS target
USING (VALUES
    ('VISITOR.PERMIT', @grupVis, 'Izin Masuk Kendaraan (Permit)', N'Pengajuan dan penerbitan izin masuk kendaraan proyek, vendor, dan operasional.', 'visitorPermitPage', 'Y', 10, 'visitorVerifikatorPermitPage'),
    ('VISITOR.TAMU', @grupVis, 'Kunjungan Tamu & Vendor', N'Registrasi kedatangan tamu reguler, vendor kontraktor, dan rekanan bisnis B2B.', 'visitorTamuPage', 'Y', 9, 'visitorVerifikatorTamuPage'),
    ('DMS.DOKUMEN', @grupDms, 'Manajemen SOP & Dokumen', N'Pengelolaan repositori dokumen fungsional, regulasi publik, builder SOP, dan otomasi siklus dokumen.', 'menuSopSayaPage', 'T', NULL, NULL),
    ('KPI.PERFORMANCE', @grupKpi, 'Penilaian Kinerja & Evaluasi KPI', N'Penyusunan target parameter KPI individu/tim, pembaruan realisasi, dan evaluasi berkala.', 'FormKPIPage', 'Y', 13, 'VerifikasiKPI'),
    ('ESS.ABSENSI', @grupEss, 'Kehadiran & Absensi Karyawan', N'Pencatatan check-in/out harian, perbaikan absensi, dan monitoring kehadiran karyawan.', 'absensiPage', 'T', NULL, NULL),
    ('ESS.LEMBUR', @grupEss, 'Penugasan & Konversi Lembur', N'Pengajuan lembur karyawan, penugasan lembur oleh atasan, dan konversi jam lembur ke cuti.', 'OvertimeManagement', 'Y', 11, 'adminHCCutiManagementPage'),
    ('ESS.SHIFT', @grupEss, 'Pengaturan & Pergantian Shift', N'Pengajuan pergantian jadwal shift, persetujuan atasan, dan administrasi jadwal shift.', 'selfShift', 'Y', 3, 'approvalFlowShift')
) AS source (Kode_Fitur, Id_Grup_Fitur, Nama_Fitur, Deskripsi, Jenis_Page, Flag_Punya_Approval, Id_Approval_Config, Halaman_Antrean_Approval)
ON target.Kode_Fitur = source.Kode_Fitur
WHEN NOT MATCHED THEN
    INSERT (Kode_Fitur, Id_Grup_Fitur, Nama_Fitur, Deskripsi, Jenis_Page, Flag_Punya_Approval, Id_Approval_Config, Halaman_Antrean_Approval, Flag_Aktif, Created_By)
    VALUES (source.Kode_Fitur, source.Id_Grup_Fitur, source.Nama_Fitur, source.Deskripsi, source.Jenis_Page, source.Flag_Punya_Approval, source.Id_Approval_Config, source.Halaman_Antrean_Approval, 'Y', 4)
WHEN MATCHED THEN
    UPDATE SET
        target.Id_Grup_Fitur = source.Id_Grup_Fitur,
        target.Nama_Fitur = source.Nama_Fitur,
        target.Deskripsi = source.Deskripsi,
        target.Jenis_Page = source.Jenis_Page,
        target.Flag_Punya_Approval = source.Flag_Punya_Approval,
        target.Id_Approval_Config = source.Id_Approval_Config,
        target.Halaman_Antrean_Approval = source.Halaman_Antrean_Approval,
        target.Flag_Aktif = 'Y',
        target.Updated_At = sysdatetime(),
        target.Updated_By = 4;

-- ══ 2. REGISTRASI KEMAMPUAN FITUR (N_HRIS_Fitur_Kemampuan) ══
MERGE dbo.N_HRIS_Fitur_Kemampuan AS target
USING (VALUES
    -- VISITOR.PERMIT
    ('VISITOR.PERMIT.LIHAT', 'VISITOR.PERMIT', 'Melihat Pengajuan Permit Kendaraan', 'VIEW', 'DIRI,UNIT,SEMUA', 'T', 1),
    ('VISITOR.PERMIT.AJUKAN', 'VISITOR.PERMIT', 'Mengajukan Permit Kendaraan Baru', 'CREATE', 'DIRI,UNIT', 'T', 2),
    ('VISITOR.PERMIT.VERIFIKASI', 'VISITOR.PERMIT', 'Memverifikasi Permohonan Permit', 'APPROVE', 'UNIT,SEMUA', 'Y', 3),
    ('VISITOR.PERMIT.ADMIN', 'VISITOR.PERMIT', 'Mengelola Master Data Permit', 'EDIT', 'SEMUA', 'T', 4),

    -- VISITOR.TAMU
    ('VISITOR.TAMU.LIHAT', 'VISITOR.TAMU', 'Melihat Jadwal Kedatangan Tamu', 'VIEW', 'DIRI,UNIT,SEMUA', 'T', 1),
    ('VISITOR.TAMU.AJUKAN', 'VISITOR.TAMU', 'Mendaftarkan Kedatangan Tamu / Vendor', 'CREATE', 'DIRI,UNIT', 'T', 2),
    ('VISITOR.TAMU.VERIFIKASI', 'VISITOR.TAMU', 'Memverifikasi Izin Masuk Tamu', 'APPROVE', 'UNIT,SEMUA', 'Y', 3),
    ('VISITOR.TAMU.ADMIN', 'VISITOR.TAMU', 'Mengelola Master Data Tamu & NFC', 'EDIT', 'SEMUA', 'T', 4),

    -- DMS.DOKUMEN
    ('DMS.DOKUMEN.LIHAT', 'DMS.DOKUMEN', 'Membaca Repositori SOP & Dokumen', 'VIEW', 'DIRI,TIM,UNIT,SEMUA', 'T', 1),
    ('DMS.DOKUMEN.AJUKAN', 'DMS.DOKUMEN', 'Menyusun & Mengajukan Draf Dokumen', 'CREATE', 'DIRI,UNIT', 'T', 2),
    ('DMS.DOKUMEN.VERIFIKASI', 'DMS.DOKUMEN', 'Memvalidasi & Mengesahkan Dokumen', 'APPROVE', 'UNIT,SEMUA', 'Y', 3),
    ('DMS.DOKUMEN.ADMIN', 'DMS.DOKUMEN', 'Mengelola Master Klasifikasi & Kebijakan', 'EDIT', 'SEMUA', 'T', 4),

    -- KPI.PERFORMANCE
    ('KPI.PERF.LIHAT', 'KPI.PERFORMANCE', 'Melihat Target & Capaian KPI', 'VIEW', 'DIRI,TIM,UNIT,SEMUA', 'T', 1),
    ('KPI.PERF.AJUKAN', 'KPI.PERFORMANCE', 'Menginput Parameter & Realisasi KPI', 'CREATE', 'DIRI,TIM', 'T', 2),
    ('KPI.PERF.VERIFIKASI', 'KPI.PERFORMANCE', 'Memverifikasi Penilaian Kinerja Tahunan', 'APPROVE', 'TIM,UNIT,SEMUA', 'Y', 3),
    ('KPI.PERF.ADMIN', 'KPI.PERFORMANCE', 'Mengonfigurasi Master Target & Formula KPI', 'EDIT', 'UNIT,SEMUA', 'T', 4),

    -- ESS.ABSENSI
    ('ESS.ABSENSI.LIHAT', 'ESS.ABSENSI', 'Melihat Log Kehadiran & Riwayat Absen', 'VIEW', 'DIRI,TIM,UNIT,SEMUA', 'T', 1),
    ('ESS.ABSENSI.KOREKSI', 'ESS.ABSENSI', 'Mengajukan Perbaikan Check-in/out', 'EDIT', 'DIRI,TIM,UNIT', 'T', 2),
    ('ESS.ABSENSI.ADMIN', 'ESS.ABSENSI', 'Memantau & Mengelola Kehadiran Karyawan', 'EDIT', 'UNIT,SEMUA', 'T', 3),

    -- ESS.LEMBUR
    ('ESS.LEMBUR.LIHAT', 'ESS.LEMBUR', 'Melihat Riwayat Jam Lembur', 'VIEW', 'DIRI,TIM,UNIT,SEMUA', 'T', 1),
    ('ESS.LEMBUR.AJUKAN', 'ESS.LEMBUR', 'Mengajukan Surat Perintah Kerja Lembur', 'CREATE', 'DIRI,TIM', 'T', 2),
    ('ESS.LEMBUR.ADMIN', 'ESS.LEMBUR', 'Mengonfirmasi Konversi Lembur Karyawan', 'EDIT', 'UNIT,SEMUA', 'T', 3),

    -- ESS.SHIFT
    ('ESS.SHIFT.LIHAT', 'ESS.SHIFT', 'Melihat Kalender Jadwal Shift Kerja', 'VIEW', 'DIRI,TIM,UNIT,SEMUA', 'T', 1),
    ('ESS.SHIFT.AJUKAN', 'ESS.SHIFT', 'Mengajukan Permohonan Tukar Shift', 'CREATE', 'DIRI,TIM', 'T', 2),
    ('ESS.SHIFT.ADMIN', 'ESS.SHIFT', 'Mengatur Penugasan & Master Shift Admin', 'EDIT', 'UNIT,SEMUA', 'T', 3)
) AS source (Kode_Kemampuan, Kode_Fitur, Nama_Kemampuan, Jenis_Aksi, Cakupan_Tersedia, Flag_Wajib_Approval, Sort_Order)
ON target.Kode_Kemampuan = source.Kode_Kemampuan
WHEN NOT MATCHED THEN
    INSERT (Kode_Kemampuan, Kode_Fitur, Nama_Kemampuan, Jenis_Aksi, Cakupan_Tersedia, Flag_Wajib_Approval, Sort_Order, Flag_Aktif)
    VALUES (source.Kode_Kemampuan, source.Kode_Fitur, source.Nama_Kemampuan, source.Jenis_Aksi, source.Cakupan_Tersedia, source.Flag_Wajib_Approval, source.Sort_Order, 'Y')
WHEN MATCHED THEN
    UPDATE SET
        target.Nama_Kemampuan = source.Nama_Kemampuan,
        target.Jenis_Aksi = source.Jenis_Aksi,
        target.Cakupan_Tersedia = source.Cakupan_Tersedia,
        target.Flag_Wajib_Approval = source.Flag_Wajib_Approval,
        target.Sort_Order = source.Sort_Order,
        target.Flag_Aktif = 'Y';

-- ══ 3. PEMETAAN PERAN KE KEMAMPUAN (N_HRIS_Role_Kemampuan) ══
-- Helper temp table untuk pemetaan massal
DECLARE @Map TABLE (RoleCode VARCHAR(50), KodeKemampuan VARCHAR(70));

-- 3.1 VISITOR ROLES
INSERT INTO @Map (RoleCode, KodeKemampuan) VALUES
('VISITOR_SUPER_ADMIN', 'VISITOR.PERMIT.LIHAT'),
('VISITOR_SUPER_ADMIN', 'VISITOR.PERMIT.AJUKAN'),
('VISITOR_SUPER_ADMIN', 'VISITOR.PERMIT.VERIFIKASI'),
('VISITOR_SUPER_ADMIN', 'VISITOR.PERMIT.ADMIN'),
('VISITOR_SUPER_ADMIN', 'VISITOR.TAMU.LIHAT'),
('VISITOR_SUPER_ADMIN', 'VISITOR.TAMU.AJUKAN'),
('VISITOR_SUPER_ADMIN', 'VISITOR.TAMU.VERIFIKASI'),
('VISITOR_SUPER_ADMIN', 'VISITOR.TAMU.ADMIN'),

('VISITOR_ADMIN', 'VISITOR.PERMIT.LIHAT'),
('VISITOR_ADMIN', 'VISITOR.PERMIT.AJUKAN'),
('VISITOR_ADMIN', 'VISITOR.PERMIT.ADMIN'),
('VISITOR_ADMIN', 'VISITOR.TAMU.LIHAT'),
('VISITOR_ADMIN', 'VISITOR.TAMU.AJUKAN'),
('VISITOR_ADMIN', 'VISITOR.TAMU.ADMIN'),

('ADMIN_PERMIT', 'VISITOR.PERMIT.LIHAT'),
('ADMIN_PERMIT', 'VISITOR.PERMIT.VERIFIKASI'),

('ADMIN_TAMU', 'VISITOR.TAMU.LIHAT'),
('ADMIN_TAMU', 'VISITOR.TAMU.VERIFIKASI'),

('ADMIN_TAMU_B2B', 'VISITOR.TAMU.LIHAT'),
('ADMIN_TAMU_B2B', 'VISITOR.TAMU.VERIFIKASI'),

('SHE_ADMIN', 'VISITOR.PERMIT.LIHAT'),
('SHE_ADMIN', 'VISITOR.PERMIT.VERIFIKASI'),

('TAMU_DEPT_GA', 'VISITOR.TAMU.LIHAT'),
('TAMU_DEPT_LOGISTIC', 'VISITOR.TAMU.LIHAT'),
('TAMU_DEPT_LOGISTIC', 'VISITOR.TAMU.AJUKAN'),
('TAMU_DEPT_PROCUREMENT', 'VISITOR.TAMU.LIHAT'),
('TAMU_DEPT_PROCUREMENT', 'VISITOR.TAMU.AJUKAN'),
('TAMU_DEPT_QC_FG', 'VISITOR.TAMU.LIHAT'),
('TAMU_DEPT_QC_INC', 'VISITOR.TAMU.LIHAT'),
('TAMU_DEPT_WAREHOUSE', 'VISITOR.TAMU.LIHAT');

-- 3.2 DMS ROLES
INSERT INTO @Map (RoleCode, KodeKemampuan) VALUES
('DMS_SUPER_ADMIN', 'DMS.DOKUMEN.LIHAT'),
('DMS_SUPER_ADMIN', 'DMS.DOKUMEN.AJUKAN'),
('DMS_SUPER_ADMIN', 'DMS.DOKUMEN.VERIFIKASI'),
('DMS_SUPER_ADMIN', 'DMS.DOKUMEN.ADMIN'),

('DMS_ADMIN', 'DMS.DOKUMEN.LIHAT'),
('DMS_ADMIN', 'DMS.DOKUMEN.ADMIN');

-- 3.3 KPI ROLES
INSERT INTO @Map (RoleCode, KodeKemampuan) VALUES
('KPI_SUPER_ADMIN', 'KPI.PERF.LIHAT'),
('KPI_SUPER_ADMIN', 'KPI.PERF.AJUKAN'),
('KPI_SUPER_ADMIN', 'KPI.PERF.VERIFIKASI'),
('KPI_SUPER_ADMIN', 'KPI.PERF.ADMIN'),

('KPI_ADMIN', 'KPI.PERF.LIHAT'),
('KPI_ADMIN', 'KPI.PERF.AJUKAN'),
('KPI_ADMIN', 'KPI.PERF.ADMIN'),

('ROLE_KPI_VERIF', 'KPI.PERF.LIHAT'),
('ROLE_KPI_VERIF', 'KPI.PERF.VERIFIKASI');

-- 3.4 ESS ROLES (ABSENSI, LEMBUR, SHIFT)
INSERT INTO @Map (RoleCode, KodeKemampuan) VALUES
('ESS_SUPER_ADMIN', 'ESS.ABSENSI.LIHAT'),
('ESS_SUPER_ADMIN', 'ESS.ABSENSI.KOREKSI'),
('ESS_SUPER_ADMIN', 'ESS.ABSENSI.ADMIN'),
('ESS_SUPER_ADMIN', 'ESS.LEMBUR.LIHAT'),
('ESS_SUPER_ADMIN', 'ESS.LEMBUR.AJUKAN'),
('ESS_SUPER_ADMIN', 'ESS.LEMBUR.ADMIN'),
('ESS_SUPER_ADMIN', 'ESS.SHIFT.LIHAT'),
('ESS_SUPER_ADMIN', 'ESS.SHIFT.AJUKAN'),
('ESS_SUPER_ADMIN', 'ESS.SHIFT.ADMIN'),

('ESS_ADMIN', 'ESS.ABSENSI.LIHAT'),
('ESS_ADMIN', 'ESS.ABSENSI.ADMIN'),
('ESS_ADMIN', 'ESS.LEMBUR.LIHAT'),
('ESS_ADMIN', 'ESS.LEMBUR.ADMIN'),
('ESS_ADMIN', 'ESS.SHIFT.LIHAT'),
('ESS_ADMIN', 'ESS.SHIFT.ADMIN'),

('HC_OPS_ADMIN', 'ESS.ABSENSI.LIHAT'),
('HC_OPS_ADMIN', 'ESS.ABSENSI.KOREKSI'),
('HC_OPS_ADMIN', 'ESS.ABSENSI.ADMIN'),
('HC_OPS_ADMIN', 'ESS.LEMBUR.LIHAT'),
('HC_OPS_ADMIN', 'ESS.LEMBUR.ADMIN'),
('HC_OPS_ADMIN', 'ESS.SHIFT.LIHAT'),
('HC_OPS_ADMIN', 'ESS.SHIFT.ADMIN'),

('GA_PHL_ADMIN', 'ESS.ABSENSI.LIHAT'),
('GA_PHL_ADMIN', 'ESS.ABSENSI.KOREKSI'),
('GA_PHL_ADMIN', 'ESS.ABSENSI.ADMIN'),

('HCIS_LEADER', 'ESS.ABSENSI.LIHAT'),
('HCIS_LEADER', 'ESS.LEMBUR.LIHAT'),
('HCIS_LEADER', 'ESS.LEMBUR.AJUKAN'),
('HCIS_LEADER', 'ESS.SHIFT.LIHAT'),
('HCIS_LEADER', 'ESS.SHIFT.AJUKAN');

-- Masukkan mapping ke N_HRIS_Role_Kemampuan
INSERT INTO dbo.N_HRIS_Role_Kemampuan (Id_Role, Kode_Kemampuan, Flag_Aktif, Created_By)
SELECT rf.Id_Role, m.KodeKemampuan, 'Y', 4
FROM @Map m
JOIN dbo.N_HRIS_Role_Functional rf ON rf.Role_Code = m.RoleCode AND rf.Flag_Aktif = 'Y'
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Role_Kemampuan rk
    WHERE rk.Id_Role = rf.Id_Role AND rk.Kode_Kemampuan = m.KodeKemampuan
);

COMMIT TRANSACTION;

-- Verifikasi hasil
SELECT f.Kode_Fitur, f.Nama_Fitur, COUNT(DISTINCT fk.Kode_Kemampuan) AS jml_kemampuan, COUNT(rk.Id_Role_Kemampuan) AS jml_pemetaan
FROM dbo.N_HRIS_Fitur f
JOIN dbo.N_HRIS_Fitur_Kemampuan fk ON fk.Kode_Fitur = f.Kode_Fitur
LEFT JOIN dbo.N_HRIS_Role_Kemampuan rk ON rk.Kode_Kemampuan = fk.Kode_Kemampuan AND rk.Flag_Aktif = 'Y'
GROUP BY f.Kode_Fitur, f.Nama_Fitur
ORDER BY f.Kode_Fitur;
