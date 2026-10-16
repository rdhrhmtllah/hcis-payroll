/* Change ID: DML-2026-156-visitor-data-tertunda-prod

   HANYA data Visitor yang BELUM ADA di production (emi_db_web_hr), diukur per
   efek pada 2026-09-30 (baca saja). Bagian artifact yang sudah beres di
   production TIDAK diulang:
   - hibah halaman admin sudah dicabut (DML-2026-124 langkah 2, manual 18 Sep);
   - transaksi tamu, detail tamu, dan proyeksi Efektif production KOSONG, jadi
     semua backfill/migrasi status (077, 121, 122, 132) tidak diperlukan;
   - header menu verifikator sudah di keadaan akhir DML-2026-025.

   Grup Visitor dicari lewat role ADMIN_PERMIT, bukan kode/Id (kode berbeda
   antar lingkungan). Semua langkah idempoten; satu transaksi.
   Jalankan SETELAH DDL-2026-154. */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> 'emi_db_web_hr'
    THROW 50156, 'Berkas ini khusus production emi_db_web_hr.', 1;

DECLARE @Grup int = (SELECT MIN(Id_Grup_Fitur) FROM N_HRIS_Role_Functional WHERE Role_Code = 'ADMIN_PERMIT');
IF @Grup IS NULL
    THROW 50156, 'Grup Visitor tidak ditemukan lewat role ADMIN_PERMIT.', 1;

BEGIN TRANSACTION;

/* 1. DML-2026-124 — kode grup mengikuti kode aplikasi ('VISITOR') */
IF EXISTS (SELECT 1 FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR' AND Id_Grup_Fitur <> @Grup)
    THROW 50156, 'Kode VISITOR sudah dipakai grup lain.', 1;
UPDATE N_HRIS_Master_Grup_Fitur SET Kode_Grup_Fitur = 'VISITOR'
 WHERE Id_Grup_Fitur = @Grup AND Kode_Grup_Fitur <> 'VISITOR';

/* 2. DDL-2026-077 / 104 — kategori COKLAT + penanda tampil di form pengajuan */
IF NOT EXISTS (SELECT 1 FROM N_EMI_VISITOR_Master_Kategori_Permit WHERE Nama_Kategori = 'COKLAT')
    INSERT INTO N_EMI_VISITOR_Master_Kategori_Permit (Nama_Kategori, Deskripsi_Akses, Kode_Warna_UI, Flag_Aktif)
    VALUES ('COKLAT', 'Akses terbatas sesuai jadwal kunjungan', '#795548', 'Y');
UPDATE N_EMI_VISITOR_Master_Kategori_Permit SET Flag_Muncul_Pengajuan = 'Y' WHERE Flag_Muncul_Pengajuan IS NULL;
UPDATE N_EMI_VISITOR_Master_Kategori_Permit SET Flag_Muncul_Pengajuan = 'T' WHERE Nama_Kategori = 'COKLAT';

/* 3. Role (DDL-2026-121 dan 122) */
INSERT INTO N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
SELECT r.Kode, r.Nama, r.Ket, 'Y', SYSDATETIME(), 'DML-2026-156', @Grup
  FROM (VALUES
      ('ADMIN_TAMU_B2B', 'Admin Verifikator Tamu B2B', 'Pool verifikator registrasi kunjungan tamu B2B Visitor'),
      ('TAMU_DEPT_GA', 'Departemen GA (Tamu)', 'Melihat jadwal kedatangan tamu Regular'),
      ('TAMU_DEPT_LOGISTIC', 'Departemen Logistic (Tamu)', 'Melihat & mengajukan tamu B2B Logistic'),
      ('TAMU_DEPT_QC_FG', 'Departemen QC FG (Tamu)', 'Melihat jadwal kedatangan tamu B2B Logistic'),
      ('TAMU_DEPT_QC_INC', 'Departemen QC Incoming (Tamu)', 'Melihat jadwal kedatangan tamu B2B Procurement'),
      ('TAMU_DEPT_WAREHOUSE', 'Departemen Warehouse (Tamu)', 'Melihat jadwal kedatangan tamu B2B Procurement'),
      ('TAMU_DEPT_PROCUREMENT', 'Departemen Procurement (Tamu)', 'Melihat & mengajukan tamu B2B Procurement')
  ) AS r (Kode, Nama, Ket)
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Role_Functional ada WHERE ada.Role_Code = r.Kode);

/* Anggota awal ADMIN_TAMU_B2B = anggota aktif ADMIN_TAMU (DDL-2026-121 langkah 4) */
INSERT INTO N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif)
SELECT DISTINCT s.UserId_Web, b2b.Id_Role, SYSDATETIME(), 'DML-2026-156', 'Y'
  FROM N_HRIS_Role_User s
  JOIN N_HRIS_Role_Functional lama ON lama.Id_Role = s.Id_Role AND lama.Role_Code = 'ADMIN_TAMU' AND lama.Flag_Aktif = 'Y'
 CROSS JOIN N_HRIS_Role_Functional b2b
 WHERE s.Flag_Aktif = 'Y' AND b2b.Role_Code = 'ADMIN_TAMU_B2B'
   AND NOT EXISTS (SELECT 1 FROM N_HRIS_Role_User ada WHERE ada.Id_Role = b2b.Id_Role AND ada.UserId_Web = s.UserId_Web AND ada.Flag_Aktif = 'Y');

/* 4. Approval Tamu B2B (DDL-2026-121 langkah 3) */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Jenis_Approval WHERE Source_Type = 'ROLE' AND Source_Reference = 'ADMIN_TAMU_B2B')
    INSERT INTO N_HRIS_Jenis_Approval (Nama_Approval, Source_Type, Source_Reference, Auto_Approve, Keterangan, Flag_Aktif, Created_At, Created_By)
    VALUES ('Verifikator Tamu B2B', 'ROLE', 'ADMIN_TAMU_B2B', 'T', 'Approval oleh role ADMIN_TAMU_B2B', 'Y', SYSDATETIME(), 'DML-2026-156');
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Approval_Config WHERE Kode_Config = 'TAMU_B2B')
    INSERT INTO N_HRIS_Approval_Config (Kode_Config, Nama_Config, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
    VALUES ('TAMU_B2B', 'TAMU B2B APPROVAL', 'Y', SYSDATETIME(), 'DML-2026-156', @Grup);
INSERT INTO N_HRIS_Approval_Config_Detail (Id_Approval_Config, Approval_Order, Id_Jenis_Approval, Stop_If_Rejected, Is_Mandatory, Flag_Aktif)
SELECT cfg.Id, 1, j.Id_Jenis_Approval, 'Y', 'Y', 'Y'
  FROM N_HRIS_Approval_Config cfg
 CROSS JOIN N_HRIS_Jenis_Approval j
 WHERE cfg.Kode_Config = 'TAMU_B2B' AND j.Source_Type = 'ROLE' AND j.Source_Reference = 'ADMIN_TAMU_B2B'
   AND NOT EXISTS (SELECT 1 FROM N_HRIS_Approval_Config_Detail d WHERE d.Id_Approval_Config = cfg.Id);

/* 5. Menu baru (DDL-2026-121, 122 + DML-2026-137, 123).
      Jadwal Tamu dibuat langsung tersembunyi dari sidebar (keadaan akhir DML-2026-137). */
INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
SELECT m.* FROM (VALUES
    ('visitorTamuB2BPage', '001', 'Kunjungan Tamu B2B', 'bi bi-truck', '/transaksi-tamu-b2b', 'visitor', 'Visitor', 'bi bi-people-fill', '4', 'T', '21', '/transaksi-tamu-b2b*', 'self', 'Y', 'Registrasi & riwayat tamu B2B berpermit'),
    ('visitorVerifikatorTamuB2BPage', '001', 'Verifikator Tamu B2B', 'bi bi-truck', '/verifikator-visitor/b2b', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '26', '/verifikator-visitor/b2b*', 'self', 'Y', 'Meja verifikasi kunjungan tamu B2B'),
    ('visitorMonitoringTamuB2BPage', '001', 'Monitoring Tamu B2B', 'bi bi-truck', '/monitoring-b2b', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '31', '/monitoring-b2b*', 'self', 'Y', 'Dashboard & daftar kunjungan tamu B2B'),
    ('visitorJadwalTamuPage', '001', 'Jadwal Kedatangan Tamu', 'bi bi-calendar-week', '/jadwal-kedatangan-tamu', 'visitor', 'Visitor', 'bi bi-people-fill', '4', 'T', '22', '/jadwal-kedatangan-tamu*', 'self', 'T', 'Agenda kedatangan tamu sesuai departemen Anda'),
    ('visitorAgendaInspeksiPage', '001', 'Agenda Inspeksi', 'bi bi-clipboard2-check', '/agenda-inspeksi', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '21', '/agenda-inspeksi*', 'self', 'Y', 'Jadwal inspeksi kendaraan permit & tamu B2B')
  ) AS m (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus ada WHERE ada.Jenis_page = m.Jenis_page);

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT @Grup, h.Jenis_page, h.Urut, 'T', 'Y', 'Y', h.Hibah
  FROM (VALUES
      ('visitorTamuB2BPage', 21, 'T'),
      ('visitorVerifikatorTamuB2BPage', 26, 'T'),
      ('visitorMonitoringTamuB2BPage', 31, 'T'),
      ('visitorJadwalTamuPage', 22, 'Y'),
      ('visitorAgendaInspeksiPage', 21, 'T')
  ) AS h (Jenis_page, Urut, Hibah)
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map ada WHERE ada.Jenis_page = h.Jenis_page);

/* Kunci halaman: B2B diturunkan dari pemegang Verifikator Tamu (121),
   Agenda Inspeksi dari pemegang Verifikator Permit (123). */
INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT s.Kode_Perusahaan, s.ID_Level, s.ID_Divisi, t.Jenis_page, s.UserID_Web, t.Urut, 'Y', SYSDATETIME()
  FROM HRIS_Page_Access s
  JOIN (VALUES
      ('visitorVerifikatorTamuPage', 'visitorTamuB2BPage', 21),
      ('visitorVerifikatorTamuPage', 'visitorVerifikatorTamuB2BPage', 26),
      ('visitorVerifikatorTamuPage', 'visitorMonitoringTamuB2BPage', 31),
      ('visitorVerifikatorPermitPage', 'visitorAgendaInspeksiPage', 21)
  ) AS t (Sumber, Jenis_page, Urut) ON t.Sumber = s.Jenis_page
 WHERE s.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM HRIS_Page_Access ada
        WHERE ada.Jenis_page = t.Jenis_page
          AND ISNULL(ada.UserID_Web, -1) = ISNULL(s.UserID_Web, -1)
          AND ISNULL(ada.ID_Level, -1) = ISNULL(s.ID_Level, -1)
          AND ISNULL(ada.ID_Divisi, -1) = ISNULL(s.ID_Divisi, -1));

/* 6. DML-2026-137 — Monitoring Tamu dihibahkan lewat role (isinya disaring matriks) */
UPDATE N_HRIS_Grup_Fitur_Map SET Flag_Hibah_Role = 'Y'
 WHERE Jenis_page = 'visitorMonitoringTamuPage' AND Flag_Aktif = 'Y' AND ISNULL(Flag_Hibah_Role, 'T') <> 'Y';

/* 7. Matriks tamu (DDL-2026-122 + DML-2026-136) */
INSERT INTO N_VISITOR_Matriks_Tamu (Role_Id, Kategori_Tamu, Flag_Lihat, Flag_Ajukan, Flag_Aktif, Created_By)
SELECT rf.Id_Role, m.Kategori, m.Lihat, m.Ajukan, 'Y', 'DML-2026-156'
  FROM (VALUES
      ('TAMU_DEPT_GA', 'REGULAR', 'Y', 'T'),
      ('TAMU_DEPT_LOGISTIC', 'B2B_LOGISTIC', 'Y', 'Y'),
      ('TAMU_DEPT_QC_FG', 'B2B_LOGISTIC', 'Y', 'T'),
      ('TAMU_DEPT_QC_INC', 'B2B_PROCUREMENT', 'Y', 'T'),
      ('TAMU_DEPT_WAREHOUSE', 'B2B_PROCUREMENT', 'Y', 'T'),
      ('TAMU_DEPT_PROCUREMENT', 'B2B_PROCUREMENT', 'Y', 'Y'),
      ('ADMIN_TAMU', 'REGULAR', 'Y', 'T'),
      ('ADMIN_TAMU', 'B2B_PROCUREMENT', 'Y', 'T'),
      ('ADMIN_TAMU', 'B2B_LOGISTIC', 'Y', 'T'),
      ('ADMIN_TAMU_B2B', 'B2B_PROCUREMENT', 'Y', 'T'),
      ('ADMIN_TAMU_B2B', 'B2B_LOGISTIC', 'Y', 'T')
  ) AS m (Role_Code, Kategori, Lihat, Ajukan)
  JOIN N_HRIS_Role_Functional rf ON rf.Role_Code = m.Role_Code AND rf.Flag_Aktif = 'Y'
 WHERE NOT EXISTS (SELECT 1 FROM N_VISITOR_Matriks_Tamu ada WHERE ada.Role_Id = rf.Id_Role AND ada.Kategori_Tamu = m.Kategori);

/* 8. DDL-2026-123 — shift kerja untuk jadwal inspeksi.
      Pos & pola inspeksi disusun SHE lewat layar Pengaturan Slot (DML-2026-134
      menyusul setelah pos "Area Parkir Motor/Mobil" dan "Klinik SHE" dibuat). */
INSERT INTO N_VISITOR_Master_Shift_Inspeksi (Nama_Shift, Jam_Mulai, Jam_Selesai, Urutan, Flag_Aktif, Created_By)
SELECT s.Nama, s.Mulai, s.Selesai, s.Urutan, 'Y', 'DML-2026-156'
  FROM (VALUES ('Shift 1', '07:00', '15:00', 1), ('Shift 2', '15:00', '23:00', 2), ('Shift 3', '23:00', '07:00', 3)) AS s (Nama, Mulai, Selesai, Urutan)
 WHERE NOT EXISTS (SELECT 1 FROM N_VISITOR_Master_Shift_Inspeksi m WHERE m.Nama_Shift = s.Nama);

/* 9. Penjadwal expire proyeksi Efektif, tiap 5 menit (nilai akhir DML-2026-078).
      Barisnya belum pernah ada di production; DML-2026-078 hanya meng-UPDATE. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Scheduler_Configs WHERE Command = 'visitor:expire-efektif')
    INSERT INTO N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
    VALUES ('visitor:expire-efektif', '*/5 * * * *', 'Asia/Jakarta', 'Y',
            'Expire Efektif visitor: permit karyawan per hari, permit coklat per jam & saat keluar (tiap 5 mnt)', GETDATE());

/* 10. Database Change Dashboard */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-156-visitor-data-tertunda-prod')
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES ('DML-2026-156-visitor-data-tertunda-prod', 'DML', 'Data Visitor tertunda untuk production',
            'Hanya efek yang belum ada di production: kode grup VISITOR, kategori COKLAT, Tamu B2B, role departemen, matriks tamu, menu Jadwal/Agenda, shift inspeksi, penjadwal expire.',
            'database/dml/DML-2026-156-visitor-data-tertunda-prod/data-change.sql', 'docs/changes/DML-2026-156-visitor-data-tertunda-prod/README.md',
            'Web_HRIS', 'N/A', 'PENDING', 'PENDING', 'PENDING', 'visitor-data-prod');

COMMIT TRANSACTION;
GO

/* 11. DML-2026-150 — kontak PIC modul Pusat Bantuan. Butuh tabel dari DDL-2026-154. */
IF OBJECT_ID(N'dbo.N_HRIS_Master_Kontak_Bantuan', N'U') IS NULL
BEGIN
    PRINT 'LEWATI kontak bantuan: jalankan DDL-2026-154 dulu, lalu jalankan ulang bagian ini.';
    RETURN;
END;
INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
SELECT k.Grup, k.Fitur, k.Nama, k.Ket, NULL, k.Email, NULL, 1, 'Y', 'DML-2026-150'
  FROM (VALUES
      ('ESS', 'UMUM', 'PIC HCIS', 'Admin HCIS Absensi dan Perizinan', 'hc.ops@evomanufacturingindonesia.co.id'),
      ('KPI', 'UMUM', 'PIC KPI', 'Admin KPI', 'learning.development@evomanufacturingindonesia.co.id'),
      ('LMS', 'UMUM', 'PIC LMS', 'Admin LMS', 'learning.development@evomanufacturingindonesia.co.id'),
      ('SYSTEM', 'IT', 'PIC IT HCIS', 'Admin IT HCIS', 'it.hcis@evomanufacturingindonesia.co.id')
  ) AS k (Grup, Fitur, Nama, Ket, Email)
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan ada WHERE ada.Kode_Grup_Fitur = k.Grup AND ada.Kode_Fitur = k.Fitur AND ada.Email = k.Email);
GO

/* Verifikasi (baca saja). */
SELECT Id_Grup_Fitur, Kode_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR';
SELECT Nama_Kategori, Flag_Aktif, Flag_Muncul_Pengajuan FROM N_EMI_VISITOR_Master_Kategori_Permit ORDER BY Id_Kategori_Permit;
SELECT m.Jenis_page, m.Is_Show_Sidebar, g.Flag_Hibah_Role,
       (SELECT COUNT(*) FROM HRIS_Page_Access pa WHERE pa.Jenis_page = m.Jenis_page AND pa.Flag_Aktif = 'Y') AS pemegang_kunci
  FROM N_HRIS_Menus m LEFT JOIN N_HRIS_Grup_Fitur_Map g ON g.Jenis_page = m.Jenis_page
 WHERE m.Jenis_page IN ('visitorTamuB2BPage','visitorVerifikatorTamuB2BPage','visitorMonitoringTamuB2BPage','visitorJadwalTamuPage','visitorAgendaInspeksiPage','visitorMonitoringTamuPage');
SELECT Command, Cron_Expression, Flag_Aktif FROM N_HRIS_Scheduler_Configs WHERE Command = 'visitor:expire-efektif';
