/* Change ID: DML-2026-105-pisah-monitoring-dan-verifikator */

/*
    Monitoring dan Verifikator dipecah per domain menjadi empat halaman:

        visitorLaporanPage      -> visitorMonitoringTamuPage   (/monitoring-tamu)
                                   visitorMonitoringPermitPage (/monitoring-permit)
        visitorVerifikatorPage  -> visitorVerifikatorTamuPage   (/verifikator-visitor/tamu)
                                   visitorVerifikatorPermitPage (/verifikator-visitor/permit)

    Hak akses DITURUNKAN DARI DATA, bukan dari daftar user yang ditulis tangan:
    pemegang aktif kunci lama menerima kedua kunci barunya dengan dimensi
    perusahaan/level/divisi miliknya sendiri. Dengan begitu changeset ini tetap
    benar bila audiensnya berubah sebelum dijalankan ke production.

    Baris HRIS_Page_Access kunci LAMA sengaja TIDAK dihapus dan TIDAK
    dinonaktifkan. Dua sebabnya:

      1. Ia satu-satunya catatan siapa yang dulu berhak; menghapusnya membuat
         pemulihan mustahil bila pemecahan ini ternyata perlu dibatalkan.
      2. Menghapusnya tidak menambah keamanan apa pun — rute lamanya sudah tidak
         dijaga kunci itu lagi, melainkan hanya mengalihkan ke halaman baru yang
         menegakkan kuncinya sendiri.

    Yang membuat menu lama hilang dari sidebar adalah N_HRIS_Menus.Is_Show_Sidebar,
    kolom yang memang disaring InertiaShellService. N_HRIS_Menus TIDAK punya
    kolom Flag_Aktif, jadi menonaktifkannya lewat kolom itu mustahil dan
    menghapus barisnya tidak dapat dibatalkan.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Empat baris menu baru ─────────────────────────────────────────────
      Sort: master data 10, verifikator permit 20, verifikator tamu 25,
      monitoring tamu 30, monitoring permit 35, export studio 40. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorMonitoringTamuPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorMonitoringTamuPage', '001', 'Monitoring Tamu', 'bi bi-people-fill', '/monitoring-tamu', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '30', '/monitoring-tamu*', 'self', 'Y', 'Dashboard & daftar lengkap kunjungan tamu');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorMonitoringPermitPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorMonitoringPermitPage', '001', 'Monitoring Permit', 'bi bi-car-front-fill', '/monitoring-permit', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '35', '/monitoring-permit*', 'self', 'Y', 'Dashboard & daftar lengkap permit kendaraan');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorVerifikatorPermitPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorVerifikatorPermitPage', '001', 'Verifikator Permit', 'bi bi-car-front-fill', '/verifikator-visitor/permit', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '20', '/verifikator-visitor/permit*', 'self', 'Y', 'Meja verifikasi permit kendaraan');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorVerifikatorTamuPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorVerifikatorTamuPage', '001', 'Verifikator Tamu', 'bi bi-people-fill', '/verifikator-visitor/tamu', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '25', '/verifikator-visitor/tamu*', 'self', 'Y', 'Meja verifikasi kunjungan tamu');
END;

/* ── 2. Pemetaan ke modul VISITOR (Id_Grup_Fitur = 4) ────────────────────
      Tanpa baris ini menunya tidak pernah muncul di sidebar. */
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT 4, halaman.Jenis_page, halaman.Sort_Order, 'T', 'Y', 'Y'
FROM (VALUES
    ('visitorVerifikatorPermitPage', 20),
    ('visitorVerifikatorTamuPage', 25),
    ('visitorMonitoringTamuPage', 30),
    ('visitorMonitoringPermitPage', 35)
) AS halaman (Jenis_page, Sort_Order)
WHERE NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map ada WHERE ada.Jenis_page = halaman.Jenis_page
);

/* ── 3. Hak akses diturunkan dari pemegang aktif kunci lamanya ───────────
      Ekspektasi development per 2026-09-15: masing-masing 2 baris dari
      visitorLaporanPage, dan sejumlah pemegang visitorVerifikatorPage. */
INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT
    sumber.Kode_Perusahaan,
    sumber.ID_Level,
    sumber.ID_Divisi,
    tujuan.Jenis_page,
    sumber.UserID_Web,
    tujuan.Urutan_Menu,
    'Y',
    SYSDATETIME()
FROM HRIS_Page_Access sumber
INNER JOIN (VALUES
    ('visitorLaporanPage', 'visitorMonitoringTamuPage', 30),
    ('visitorLaporanPage', 'visitorMonitoringPermitPage', 35),
    ('visitorVerifikatorPage', 'visitorVerifikatorPermitPage', 20),
    ('visitorVerifikatorPage', 'visitorVerifikatorTamuPage', 25)
) AS tujuan (Jenis_page_Sumber, Jenis_page, Urutan_Menu)
    ON tujuan.Jenis_page_Sumber = sumber.Jenis_page
WHERE sumber.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1
      FROM HRIS_Page_Access ada
      WHERE ada.Jenis_page = tujuan.Jenis_page
        AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
        AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
        AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
  );

/* ── 4. Menu gabungan lama disembunyikan, bukan dihapus ──────────────────
      URL-nya tetap hidup sebagai pengalihan; yang dicabut hanya pintunya di
      sidebar supaya tidak ada dua jalan ke pekerjaan yang sama. */
UPDATE N_HRIS_Menus
   SET Is_Show_Sidebar = 'T'
 WHERE Jenis_page IN ('visitorLaporanPage', 'visitorVerifikatorPage');

/* ── 5. Pendaftaran ke Database Change Dashboard ─────────────────────────*/
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-105-pisah-monitoring-dan-verifikator')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-105-pisah-monitoring-dan-verifikator', 'DML', 'Pisah Monitoring dan Verifikator Visitor per domain', 'Empat halaman baru (monitoring tamu/permit, verifikator tamu/permit) beserta menu, pemetaan grup fitur, dan hak akses yang diturunkan dari pemegang kunci lama. Menu gabungan lama disembunyikan dari sidebar; URL-nya menjadi pengalihan.', 'database/dml/DML-2026-105-pisah-monitoring-dan-verifikator/data-change.sql', 'docs/changes/DML-2026-105-pisah-monitoring-dan-verifikator/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-monitoring-verifikator-split');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────────*/
SELECT Jenis_page, Nama_Menu, Url_Menu, Sort_Menu, Is_Show_Sidebar
  FROM N_HRIS_Menus
 WHERE Jenis_page LIKE 'visitor%'
 ORDER BY Sort_Menu;

SELECT Jenis_page, COUNT(*) AS baris_akses
  FROM HRIS_Page_Access
 WHERE Flag_Aktif = 'Y'
   AND Jenis_page IN ('visitorLaporanPage', 'visitorVerifikatorPage', 'visitorMonitoringTamuPage', 'visitorMonitoringPermitPage', 'visitorVerifikatorTamuPage', 'visitorVerifikatorPermitPage')
 GROUP BY Jenis_page
 ORDER BY Jenis_page;
