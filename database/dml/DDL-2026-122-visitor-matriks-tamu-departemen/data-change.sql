/* Change ID: DDL-2026-122-visitor-matriks-tamu-departemen */
/*
    Wajib dijalankan SETELAH forward.sql DDL-2026-121 dan DDL-2026-122.

    1. Enam role departemen (grup VISITOR).
    2. Matriks departemen × jenis tamu (lihat / ajukan), sesuai matriks user
       2026-09-18.
    3. B2B_Unit baris lama: API -> PROCUREMENT, HCIS -> LOGISTIC.
    4. Halaman Jadwal Kedatangan Tamu. Dihibahkan lewat role grup VISITOR
       (Flag_Hibah_Role = 'Y'); isinya disaring matriks di server.
    5. Keanggotaan penguji — HANYA di Web_HRIS (development).
    6. Pendaftaran ke Database Change Dashboard.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Role departemen ──────────────────────────────────────────────── */
INSERT INTO N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
SELECT r.Role_Code, r.Role_Name, r.Description, 'Y', SYSDATETIME(), 'System', 4
  FROM (VALUES
      ('TAMU_DEPT_GA', 'Departemen GA (Tamu)', 'Melihat jadwal kedatangan tamu Regular'),
      ('TAMU_DEPT_LOGISTIC', 'Departemen Logistic (Tamu)', 'Melihat & mengajukan tamu B2B Logistic'),
      ('TAMU_DEPT_QC_FG', 'Departemen QC FG (Tamu)', 'Melihat jadwal kedatangan tamu B2B Logistic'),
      ('TAMU_DEPT_QC_INC', 'Departemen QC Incoming (Tamu)', 'Melihat jadwal kedatangan tamu B2B Procurement'),
      ('TAMU_DEPT_WAREHOUSE', 'Departemen Warehouse (Tamu)', 'Melihat jadwal kedatangan tamu B2B Procurement'),
      ('TAMU_DEPT_PROCUREMENT', 'Departemen Procurement (Tamu)', 'Melihat & mengajukan tamu B2B Procurement')
  ) AS r (Role_Code, Role_Name, Description)
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Role_Functional ada WHERE ada.Role_Code = r.Role_Code);

/* ── 2. Matriks ──────────────────────────────────────────────────────── */
INSERT INTO N_VISITOR_Matriks_Tamu (Role_Id, Kategori_Tamu, Flag_Lihat, Flag_Ajukan, Flag_Aktif, Created_By)
SELECT rf.Id_Role, m.Kategori_Tamu, m.Flag_Lihat, m.Flag_Ajukan, 'Y', 'DDL-2026-122'
  FROM (VALUES
      ('TAMU_DEPT_GA', 'REGULAR', 'Y', 'T'),
      ('TAMU_DEPT_LOGISTIC', 'B2B_LOGISTIC', 'Y', 'Y'),
      ('TAMU_DEPT_QC_FG', 'B2B_LOGISTIC', 'Y', 'T'),
      ('TAMU_DEPT_QC_INC', 'B2B_PROCUREMENT', 'Y', 'T'),
      ('TAMU_DEPT_WAREHOUSE', 'B2B_PROCUREMENT', 'Y', 'T'),
      ('TAMU_DEPT_PROCUREMENT', 'B2B_PROCUREMENT', 'Y', 'Y')
  ) AS m (Role_Code, Kategori_Tamu, Flag_Lihat, Flag_Ajukan)
 INNER JOIN N_HRIS_Role_Functional rf ON rf.Role_Code = m.Role_Code
 WHERE NOT EXISTS (
     SELECT 1 FROM N_VISITOR_Matriks_Tamu ada
      WHERE ada.Role_Id = rf.Id_Role AND ada.Kategori_Tamu = m.Kategori_Tamu
 );

/* ── 3. B2B_Unit baris lama ──────────────────────────────────────────── */
UPDATE d
   SET d.B2B_Unit = CASE WHEN h.Id_Api_Aplikasi IS NOT NULL THEN 'PROCUREMENT' ELSE 'LOGISTIC' END
  FROM N_EMI_VISITOR_Transaksi_Tamu_Detail d
 INNER JOIN N_EMI_VISITOR_Transaksi_Tamu h ON h.No_Transaksi = d.No_Transaksi
 WHERE d.Tipe_Tamu = 'B2B'
   AND d.B2B_Unit IS NULL;

/* ── 4. Halaman Jadwal Kedatangan Tamu ───────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorJadwalTamuPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorJadwalTamuPage', '001', 'Jadwal Kedatangan Tamu', 'bi bi-calendar-week', '/jadwal-kedatangan-tamu', 'visitor', 'Visitor', 'bi bi-people-fill', '4', 'T', '22', '/jadwal-kedatangan-tamu*', 'self', 'Y', 'Agenda kedatangan tamu sesuai departemen Anda');
END;

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT 4, 'visitorJadwalTamuPage', 22, 'T', 'Y', 'Y', 'Y'
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'visitorJadwalTamuPage');

/* ── 5. Penguji development ──────────────────────────────────────────────
      Production: anggota role ditetapkan user, BUKAN oleh skrip ini. */
IF DB_NAME() = 'Web_HRIS'
BEGIN
    INSERT INTO N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif)
    SELECT u.UserId_Web, rf.Id_Role, SYSDATETIME(), 'DDL-2026-122 (dev)', 'Y'
      FROM (VALUES
          (624, 'TAMU_DEPT_PROCUREMENT'),
          (624, 'TAMU_DEPT_LOGISTIC'),
          (4, 'TAMU_DEPT_QC_INC'),
          (1885, 'TAMU_DEPT_QC_FG'),
          (1885, 'TAMU_DEPT_GA')
      ) AS u (UserId_Web, Role_Code)
     INNER JOIN N_HRIS_Role_Functional rf ON rf.Role_Code = u.Role_Code
     WHERE NOT EXISTS (
         SELECT 1 FROM N_HRIS_Role_User ada
          WHERE ada.UserId_Web = u.UserId_Web AND ada.Id_Role = rf.Id_Role AND ada.Flag_Aktif = 'Y'
     );
END;

/* ── 6. Database Change Dashboard ────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-122-visitor-matriks-tamu-departemen')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-122-visitor-matriks-tamu-departemen', 'DDL', 'Matriks departemen x jenis tamu', 'Kolom B2B_Unit, tabel N_VISITOR_Matriks_Tamu, enam role departemen, halaman Jadwal Kedatangan Tamu.', 'database/ddl/DDL-2026-122-visitor-matriks-tamu-departemen/forward.sql', 'docs/changes/DDL-2026-122-visitor-matriks-tamu-departemen/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-matriks-tamu');
END;

COMMIT TRANSACTION;
