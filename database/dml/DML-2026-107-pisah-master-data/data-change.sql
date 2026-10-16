/* Change ID: DML-2026-107-pisah-master-data */

/*
    Master Data Visitor dipecah per domain, melengkapi pemecahan Monitoring,
    Verifikator (DML-2026-105), dan Export Studio (DML-2026-106):

        visitorMasterDataPage -> visitorMasterDataPermitPage (/admin/visitor/master-data/permit)
                                 visitorMasterDataTamuPage   (/admin/visitor/master-data/tamu)

    Pembagian masternya diturunkan dari pemakaian NYATA di kode:

        Tamu   : Keperluan Tamu
        Permit : Kategori Permit, Durasi Permit, Pertanyaan Verifikasi,
                 Merek/Model, Warna Kendaraan
        Kedua  : Kode Plat Kendaraan, Tipe Kendaraan

    Dua master terakhir memang dipakai registrasi tamu DAN pengajuan permit,
    jadi keduanya muncul di kedua halaman. Barisnya sama persis; yang berbeda
    hanya pintu masuknya. Keputusan user 2026-09-15.

    Sisanya mengikuti DML-2026-105 persis: hak akses diturunkan dari data,
    baris akses kunci lama tidak dihapus, dan menu lama disembunyikan lewat
    Is_Show_Sidebar.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Dua baris menu baru ──────────────────────────────────────────────
      Sort 10 dan 15, di kepala header Administrator Visitor seperti menu
      master data sebelumnya. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorMasterDataPermitPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorMasterDataPermitPage', '001', 'Master Data Permit', 'bi bi-database-gear', '/admin/visitor/master-data/permit', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '10', '/admin/visitor/master-data/permit*', 'self', 'Y', 'Referensi kategori, durasi, kendaraan, dan checklist permit');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorMasterDataTamuPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorMasterDataTamuPage', '001', 'Master Data Tamu', 'bi bi-person-vcard', '/admin/visitor/master-data/tamu', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '15', '/admin/visitor/master-data/tamu*', 'self', 'Y', 'Referensi keperluan tamu, kode plat, dan tipe kendaraan');
END;

/* ── 2. Pemetaan ke modul VISITOR (Id_Grup_Fitur = 4) ────────────────────*/
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT 4, halaman.Jenis_page, halaman.Sort_Order, 'T', 'Y', 'Y'
FROM (VALUES
    ('visitorMasterDataPermitPage', 10),
    ('visitorMasterDataTamuPage', 15)
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
    ('visitorMasterDataPermitPage', 10),
    ('visitorMasterDataTamuPage', 15)
) AS tujuan (Jenis_page, Urutan_Menu) ON 1 = 1
WHERE sumber.Jenis_page = 'visitorMasterDataPage'
  AND sumber.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1
      FROM HRIS_Page_Access ada
      WHERE ada.Jenis_page = tujuan.Jenis_page
        AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
        AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
        AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
  );

/* ── 4. Menu master data gabungan lama disembunyikan, bukan dihapus ──────*/
UPDATE N_HRIS_Menus
   SET Is_Show_Sidebar = 'T'
 WHERE Jenis_page = 'visitorMasterDataPage';

/* ── 5. Pendaftaran ke Database Change Dashboard ─────────────────────────*/
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-107-pisah-master-data')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-107-pisah-master-data', 'DML', 'Pisah Master Data Visitor per domain', 'Dua halaman baru (master data permit/tamu) beserta menu, pemetaan grup fitur, dan hak akses yang diturunkan dari pemegang kunci lama. Master yang dipakai kedua domain muncul di kedua halaman. Menu gabungan disembunyikan; URL-nya menjadi pengalihan.', 'database/dml/DML-2026-107-pisah-master-data/data-change.sql', 'docs/changes/DML-2026-107-pisah-master-data/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-master-data-split');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────────*/
SELECT Jenis_page, Nama_Menu, Url_Menu, Sort_Menu, Is_Show_Sidebar
  FROM N_HRIS_Menus
 WHERE Jenis_page LIKE 'visitorMasterData%'
 ORDER BY Sort_Menu;

SELECT Jenis_page, COUNT(*) AS baris_akses
  FROM HRIS_Page_Access
 WHERE Flag_Aktif = 'Y'
   AND Jenis_page LIKE 'visitorMasterData%'
 GROUP BY Jenis_page
 ORDER BY Jenis_page;
