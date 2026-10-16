/* Change ID: DDL-2026-121-visitor-pisah-tamu-regular-b2b */
/*
    Wajib dijalankan SETELAH forward.sql (menulis kolom Tamu_Kategori).

    1. Status_Kunjungan ke kode baru berbahasa Inggris.
    2. Tamu_Kategori baris lama.
    3. Role ADMIN_TAMU_B2B + jenis approval + config TAMU_B2B.
    4. Anggota ADMIN_TAMU_B2B disalin dari anggota aktif ADMIN_TAMU.
    5. Pengajuan B2B yang menggantung di pool ADMIN_TAMU dipindahkan.
    6. Tiga halaman B2B: menu, peta grup fitur (hibah 'T'), kunci halaman
       diturunkan dari pemegang aktif kunci verifikator tamu.
    7. Pendaftaran ke Database Change Dashboard.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Status ───────────────────────────────────────────────────────── */
UPDATE N_EMI_VISITOR_Transaksi_Tamu SET Status_Kunjungan = 'PENDING_APPROVAL' WHERE Status_Kunjungan = 'MENUNGGU_APPROVAL';
UPDATE N_EMI_VISITOR_Transaksi_Tamu SET Status_Kunjungan = 'SCHEDULED' WHERE Status_Kunjungan = 'TERJADWAL';

UPDATE h
   SET h.Status_Kunjungan = 'WITHDRAWN'
  FROM N_EMI_VISITOR_Transaksi_Tamu h
 WHERE h.Status_Kunjungan = 'DIBATALKAN'
   AND EXISTS (
       SELECT 1 FROM HRIS_Approval_Request ar
        WHERE ar.No_Transaksi = h.No_Transaksi
          AND CAST(ar.Catatan AS nvarchar(200)) = N'Dibatalkan oleh pengaju.'
   );

UPDATE N_EMI_VISITOR_Transaksi_Tamu SET Status_Kunjungan = 'REJECTED' WHERE Status_Kunjungan = 'DIBATALKAN';

UPDATE h
   SET h.Status_Kunjungan = 'REVOKED'
  FROM N_EMI_VISITOR_Transaksi_Tamu h
 WHERE h.Status_Kunjungan = 'SCHEDULED'
   AND EXISTS (
       SELECT 1 FROM N_VISITOR_Tamu_Efektif e
        WHERE e.No_Transaksi = h.No_Transaksi
          AND e.Status_Efektif = 'DIBATALKAN'
   );

/* ── 2. Tamu_Kategori ────────────────────────────────────────────────── */
UPDATE N_EMI_VISITOR_Transaksi_Tamu_Detail
   SET Tamu_Kategori = CASE
           WHEN Waktu_Mulai_Kunjungan IS NOT NULL
            AND Waktu_Selesai_Kunjungan IS NOT NULL
            AND DATEDIFF(SECOND, Waktu_Mulai_Kunjungan, Waktu_Selesai_Kunjungan) > 86400
           THEN 'MITRA'
           ELSE 'VISITOR'
       END
 WHERE Tamu_Kategori IS NULL;

/* ── 3. Role, jenis approval, config ─────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Role_Functional WHERE Role_Code = 'ADMIN_TAMU_B2B')
BEGIN
    INSERT INTO N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
    VALUES ('ADMIN_TAMU_B2B', 'Admin Verifikator Tamu B2B', 'Pool verifikator registrasi kunjungan tamu B2B Visitor', 'Y', SYSDATETIME(), 'System', 4);
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Jenis_Approval WHERE Source_Type = 'ROLE' AND Source_Reference = 'ADMIN_TAMU_B2B')
BEGIN
    INSERT INTO N_HRIS_Jenis_Approval (Nama_Approval, Source_Type, Source_Reference, Auto_Approve, Keterangan, Flag_Aktif, Created_At, Created_By)
    VALUES ('Verifikator Tamu B2B', 'ROLE', 'ADMIN_TAMU_B2B', 'T', 'Approval oleh role ADMIN_TAMU_B2B', 'Y', SYSDATETIME(), 'System');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Approval_Config WHERE Kode_Config = 'TAMU_B2B')
BEGIN
    INSERT INTO N_HRIS_Approval_Config (Kode_Config, Nama_Config, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
    VALUES ('TAMU_B2B', 'TAMU B2B APPROVAL', 'Y', SYSDATETIME(), 'System', 4);
END;

INSERT INTO N_HRIS_Approval_Config_Detail (Id_Approval_Config, Approval_Order, Id_Jenis_Approval, Stop_If_Rejected, Is_Mandatory, Flag_Aktif)
SELECT cfg.Id, 1, j.Id_Jenis_Approval, 'Y', 'Y', 'Y'
  FROM N_HRIS_Approval_Config cfg
 CROSS JOIN N_HRIS_Jenis_Approval j
 WHERE cfg.Kode_Config = 'TAMU_B2B'
   AND j.Source_Type = 'ROLE' AND j.Source_Reference = 'ADMIN_TAMU_B2B'
   AND NOT EXISTS (SELECT 1 FROM N_HRIS_Approval_Config_Detail d WHERE d.Id_Approval_Config = cfg.Id);

/* ── 4. Anggota awal = anggota aktif ADMIN_TAMU ─────────────────────── */
INSERT INTO N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif)
SELECT DISTINCT sumber.UserId_Web, tujuan.Id_Role, SYSDATETIME(), 'DDL-2026-121', 'Y'
  FROM N_HRIS_Role_User sumber
 INNER JOIN N_HRIS_Role_Functional lama ON lama.Id_Role = sumber.Id_Role AND lama.Role_Code = 'ADMIN_TAMU' AND lama.Flag_Aktif = 'Y'
 CROSS JOIN N_HRIS_Role_Functional tujuan
 WHERE sumber.Flag_Aktif = 'Y'
   AND tujuan.Role_Code = 'ADMIN_TAMU_B2B'
   AND NOT EXISTS (
       SELECT 1 FROM N_HRIS_Role_User ada
        WHERE ada.Id_Role = tujuan.Id_Role AND ada.UserId_Web = sumber.UserId_Web AND ada.Flag_Aktif = 'Y'
   );

/* ── 5. Pengajuan B2B menggantung → pool ADMIN_TAMU_B2B ─────────────── */
UPDATE ar
   SET ar.Id_Jenis_Approval = (
           SELECT TOP 1 j.Id_Jenis_Approval FROM N_HRIS_Jenis_Approval j
            WHERE j.Source_Type = 'ROLE' AND j.Source_Reference = 'ADMIN_TAMU_B2B'
       ),
       ar.Group_Reference_Id = (
           SELECT TOP 1 r.Id_Role FROM N_HRIS_Role_Functional r WHERE r.Role_Code = 'ADMIN_TAMU_B2B'
       ),
       ar.update_at = SYSDATETIME()
  FROM HRIS_Approval_Request ar
 INNER JOIN N_HRIS_Role_Functional lama ON lama.Id_Role = ar.Group_Reference_Id AND lama.Role_Code = 'ADMIN_TAMU'
 INNER JOIN N_EMI_VISITOR_Transaksi_Tamu_Detail d ON d.No_Transaksi = ar.No_Transaksi
 WHERE ar.Flag_Approval IS NULL
   AND d.Tipe_Tamu = 'B2B';

/* ── 6. Halaman B2B ──────────────────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorTamuB2BPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorTamuB2BPage', '001', 'Kunjungan Tamu B2B', 'bi bi-truck', '/transaksi-tamu-b2b', 'visitor', 'Visitor', 'bi bi-people-fill', '4', 'T', '21', '/transaksi-tamu-b2b*', 'self', 'Y', 'Registrasi & riwayat tamu B2B berpermit');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorVerifikatorTamuB2BPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorVerifikatorTamuB2BPage', '001', 'Verifikator Tamu B2B', 'bi bi-truck', '/verifikator-visitor/b2b', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '26', '/verifikator-visitor/b2b*', 'self', 'Y', 'Meja verifikasi kunjungan tamu B2B');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorMonitoringTamuB2BPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorMonitoringTamuB2BPage', '001', 'Monitoring Tamu B2B', 'bi bi-truck', '/monitoring-b2b', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '31', '/monitoring-b2b*', 'self', 'Y', 'Dashboard & daftar kunjungan tamu B2B');
END;

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT 4, halaman.Jenis_page, halaman.Sort_Order, 'T', 'Y', 'Y', 'T'
  FROM (VALUES
      ('visitorTamuB2BPage', 21),
      ('visitorVerifikatorTamuB2BPage', 26),
      ('visitorMonitoringTamuB2BPage', 31)
  ) AS halaman (Jenis_page, Sort_Order)
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map ada WHERE ada.Jenis_page = halaman.Jenis_page);

INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT
    sumber.Kode_Perusahaan, sumber.ID_Level, sumber.ID_Divisi,
    tujuan.Jenis_page, sumber.UserID_Web, tujuan.Urutan_Menu, 'Y', SYSDATETIME()
  FROM HRIS_Page_Access sumber
 CROSS JOIN (VALUES
      ('visitorTamuB2BPage', 21),
      ('visitorVerifikatorTamuB2BPage', 26),
      ('visitorMonitoringTamuB2BPage', 31)
  ) AS tujuan (Jenis_page, Urutan_Menu)
 WHERE sumber.Jenis_page = 'visitorVerifikatorTamuPage'
   AND sumber.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM HRIS_Page_Access ada
        WHERE ada.Jenis_page = tujuan.Jenis_page
          AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
          AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
          AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
   );

/* ── 7. Database Change Dashboard ────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-121-visitor-pisah-tamu-regular-b2b')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-121-visitor-pisah-tamu-regular-b2b', 'DDL', 'Pisah Tamu Regular dan Tamu B2B', 'Kolom Tamu_Kategori dan Path_Foto_Kendaraan; status kunjungan berbahasa Inggris; role ADMIN_TAMU_B2B dan config TAMU_B2B; tiga halaman B2B beserta menu, peta grup fitur, dan kunci halaman.', 'database/ddl/DDL-2026-121-visitor-pisah-tamu-regular-b2b/forward.sql', 'docs/changes/DDL-2026-121-visitor-pisah-tamu-regular-b2b/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-tamu-regular-b2b');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────── */
SELECT Status_Kunjungan, COUNT(*) AS jumlah FROM N_EMI_VISITOR_Transaksi_Tamu GROUP BY Status_Kunjungan;
SELECT Tamu_Kategori, COUNT(*) AS jumlah FROM N_EMI_VISITOR_Transaksi_Tamu_Detail GROUP BY Tamu_Kategori;
SELECT r.Role_Code, COUNT(DISTINCT ru.UserId_Web) AS anggota_aktif
  FROM N_HRIS_Role_Functional r
  LEFT JOIN N_HRIS_Role_User ru ON ru.Id_Role = r.Id_Role AND ru.Flag_Aktif = 'Y'
 WHERE r.Role_Code IN ('ADMIN_TAMU', 'ADMIN_TAMU_B2B')
 GROUP BY r.Role_Code;
SELECT Jenis_page, COUNT(*) AS pemegang FROM HRIS_Page_Access
 WHERE Flag_Aktif = 'Y' AND Jenis_page LIKE 'visitor%TamuB2BPage'
 GROUP BY Jenis_page;
