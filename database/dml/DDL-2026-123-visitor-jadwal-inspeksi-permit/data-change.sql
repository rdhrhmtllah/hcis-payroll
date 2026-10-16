/* Change ID: DDL-2026-123-visitor-jadwal-inspeksi-permit */
/*
    Wajib dijalankan SETELAH forward.sql.

    1. Halaman Agenda Inspeksi: menu, peta grup VISITOR (Flag_Hibah_Role 'T'),
       kunci diturunkan dari pemegang aktif kunci Verifikator Permit.
    2. Shift kerja karyawan (SEMUA environment): Shift 1/2/3. Jamnya bisa
       diubah SHE lewat layar Pengaturan Slot.
    3. Pos + pola slot awal (development saja): master pos "Pos Utama",
       Senin–Jumat 08:00–11:00 di Shift 1, slot 30 menit, 2 kendaraan per slot.
       Production: pos & pola disusun SHE lewat layar Pengaturan Slot.
    3. Pendaftaran ke Database Change Dashboard.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Halaman Agenda Inspeksi ──────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorAgendaInspeksiPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorAgendaInspeksiPage', '001', 'Agenda Inspeksi', 'bi bi-clipboard2-check', '/agenda-inspeksi', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '21', '/agenda-inspeksi*', 'self', 'Y', 'Jadwal inspeksi kendaraan permit & tamu B2B');
END;

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT 4, 'visitorAgendaInspeksiPage', 21, 'T', 'Y', 'Y', 'T'
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'visitorAgendaInspeksiPage');

INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT sumber.Kode_Perusahaan, sumber.ID_Level, sumber.ID_Divisi,
       'visitorAgendaInspeksiPage', sumber.UserID_Web, 21, 'Y', SYSDATETIME()
  FROM HRIS_Page_Access sumber
 WHERE sumber.Jenis_page = 'visitorVerifikatorPermitPage'
   AND sumber.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM HRIS_Page_Access ada
        WHERE ada.Jenis_page = 'visitorAgendaInspeksiPage'
          AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
          AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
          AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
   );

/* ── 2. Shift kerja karyawan ─────────────────────────────────────────── */
INSERT INTO N_VISITOR_Master_Shift_Inspeksi (Nama_Shift, Jam_Mulai, Jam_Selesai, Urutan, Flag_Aktif, Created_By)
SELECT s.Nama, s.Mulai, s.Selesai, s.Urutan, 'Y', 'DDL-2026-123'
  FROM (VALUES
        ('Shift 1', '07:00', '15:00', 1),
        ('Shift 2', '15:00', '23:00', 2),
        ('Shift 3', '23:00', '07:00', 3)
       ) AS s (Nama, Mulai, Selesai, Urutan)
 WHERE NOT EXISTS (SELECT 1 FROM N_VISITOR_Master_Shift_Inspeksi m WHERE m.Nama_Shift = s.Nama);

/* ── 3. Pos & pola awal — HANYA development ─────────────────────────── */
IF DB_NAME() = 'Web_HRIS' AND NOT EXISTS (SELECT 1 FROM N_VISITOR_Master_Pos_Inspeksi)
BEGIN
    INSERT INTO N_VISITOR_Master_Pos_Inspeksi (Nama_Pos, Keterangan_Lokasi, Urutan, Flag_Aktif, Created_By)
    VALUES ('Pos Utama', 'Gerbang depan, sebelah pos satpam', 1, 'Y', 'DDL-2026-123 (dev)');
END;

IF DB_NAME() = 'Web_HRIS' AND NOT EXISTS (SELECT 1 FROM N_VISITOR_Inspeksi_Pola)
BEGIN
    INSERT INTO N_VISITOR_Inspeksi_Pola (Id_Pos_Inspeksi, Id_Shift_Inspeksi, Hari_Minggu, Jam_Mulai, Jam_Selesai, Durasi_Menit, Kuota_Per_Slot, Flag_Aktif, Created_By)
    SELECT p.Id_Pos_Inspeksi, sh.Id_Shift_Inspeksi, h.Hari, '08:00', '11:00', 30, 2, 'Y', 'DDL-2026-123 (dev)'
      FROM (VALUES (1), (2), (3), (4), (5)) AS h (Hari)
     CROSS JOIN N_VISITOR_Master_Pos_Inspeksi p
     CROSS JOIN N_VISITOR_Master_Shift_Inspeksi sh
     WHERE p.Nama_Pos = 'Pos Utama' AND sh.Nama_Shift = 'Shift 1';
END;

/* ── 4. Database Change Dashboard ────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-123-visitor-jadwal-inspeksi-permit')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-123-visitor-jadwal-inspeksi-permit', 'DDL', 'Jadwal inspeksi permit Hijau/Merah', 'Master shift kerja, master pos inspeksi, tabel pola slot, pengecualian tanggal, dan booking inspeksi; halaman Agenda Inspeksi.', 'database/ddl/DDL-2026-123-visitor-jadwal-inspeksi-permit/forward.sql', 'docs/changes/DDL-2026-123-visitor-jadwal-inspeksi-permit/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-jadwal-inspeksi');
END;

COMMIT TRANSACTION;
