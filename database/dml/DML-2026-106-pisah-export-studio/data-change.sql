/* Change ID: DML-2026-106-pisah-export-studio */

/*
    Export Studio Visitor dipecah per dataset, menyusul pemecahan Monitoring dan
    Verifikator (DML-2026-105):

        visitorExportStudioPage -> visitorExportStudioTamuPage   (/visitor/export-studio/tamu)
                                   visitorExportStudioPermitPage (/visitor/export-studio/permit)

    Bentuknya sama persis dengan DML-2026-105, dan alasan-alasannya juga sama:

      - Hak akses DITURUNKAN DARI DATA, bukan daftar user yang ditulis tangan.
      - Baris akses kunci LAMA tidak dihapus: ia satu-satunya catatan siapa yang
        dulu berhak, dan tidak ada lagi rute yang dijaga kunci itu.
      - Menu lama disembunyikan lewat Is_Show_Sidebar, kolom yang memang
        disaring InertiaShellService. N_HRIS_Menus tidak punya Flag_Aktif, dan
        menghapus barisnya tidak dapat dibatalkan.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Dua baris menu baru ──────────────────────────────────────────────
      Sort 40 dan 45, melanjutkan urutan header Administrator Visitor:
      master data 10, verifikator permit 20, verifikator tamu 25,
      monitoring tamu 30, monitoring permit 35. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorExportStudioTamuPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorExportStudioTamuPage', '001', 'Export Studio Tamu', 'bi bi-person-vcard-fill', '/visitor/export-studio/tamu', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '40', '/visitor/export-studio/tamu*', 'self', 'Y', 'Rakit dan unduh laporan kunjungan tamu');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorExportStudioPermitPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorExportStudioPermitPage', '001', 'Export Studio Permit', 'bi bi-car-front-fill', '/visitor/export-studio/permit', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '45', '/visitor/export-studio/permit*', 'self', 'Y', 'Rakit dan unduh laporan permit kendaraan');
END;

/* ── 2. Pemetaan ke modul VISITOR (Id_Grup_Fitur = 4) ────────────────────*/
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT 4, halaman.Jenis_page, halaman.Sort_Order, 'T', 'Y', 'Y'
FROM (VALUES
    ('visitorExportStudioTamuPage', 40),
    ('visitorExportStudioPermitPage', 45)
) AS halaman (Jenis_page, Sort_Order)
WHERE NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map ada WHERE ada.Jenis_page = halaman.Jenis_page
);

/* ── 3. Hak akses diturunkan dari pemegang aktif kunci lamanya ───────────*/
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
    ('visitorExportStudioTamuPage', 40),
    ('visitorExportStudioPermitPage', 45)
) AS tujuan (Jenis_page, Urutan_Menu) ON 1 = 1
WHERE sumber.Jenis_page = 'visitorExportStudioPage'
  AND sumber.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1
      FROM HRIS_Page_Access ada
      WHERE ada.Jenis_page = tujuan.Jenis_page
        AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
        AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
        AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
  );

/* ── 4. Menu studio gabungan lama disembunyikan, bukan dihapus ───────────*/
UPDATE N_HRIS_Menus
   SET Is_Show_Sidebar = 'T'
 WHERE Jenis_page = 'visitorExportStudioPage';

/* ── 5. Pendaftaran ke Database Change Dashboard ─────────────────────────*/
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-106-pisah-export-studio')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-106-pisah-export-studio', 'DML', 'Pisah Export Studio Visitor per dataset', 'Dua halaman baru (export studio tamu/permit) beserta menu, pemetaan grup fitur, dan hak akses yang diturunkan dari pemegang kunci lama. Menu studio gabungan disembunyikan dari sidebar; URL-nya menjadi pengalihan.', 'database/dml/DML-2026-106-pisah-export-studio/data-change.sql', 'docs/changes/DML-2026-106-pisah-export-studio/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-export-studio-split-dataset');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────────*/
SELECT Jenis_page, Nama_Menu, Url_Menu, Sort_Menu, Is_Show_Sidebar
  FROM N_HRIS_Menus
 WHERE Jenis_page LIKE 'visitorExportStudio%'
 ORDER BY Sort_Menu;

SELECT Jenis_page, COUNT(*) AS baris_akses
  FROM HRIS_Page_Access
 WHERE Flag_Aktif = 'Y'
   AND Jenis_page LIKE 'visitorExportStudio%'
 GROUP BY Jenis_page
 ORDER BY Jenis_page;
