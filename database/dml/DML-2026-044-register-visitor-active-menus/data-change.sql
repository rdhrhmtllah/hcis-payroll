/* Change ID: DML-2026-044-register-visitor-active-menus */

/*
    Mendaftarkan menu Visitor yang aktif (7 halaman) beserta pemetaannya ke grup
    fitur VISITOR. Skrip ini idempoten: setiap baris hanya ditulis bila belum ada,
    sehingga aman dijalankan ulang.

    Cakupan yang SENGAJA tidak disentuh:
    - HRIS_Page_Access: hak akses per user/level/divisi diatur terpisah lewat
      halaman Menu & Matrix, karena UserID_Web produksi tidak sama dengan dev.
    - visitorApprovalPage (/approvalvisitor, "Approval Permit (Lama)"): halaman
      lama yang sudah digantikan Verifikator Visitor, tidak didaftarkan di sini.

    Id_Menu adalah kolom IDENTITY dan tidak diisi manual; nomornya akan berbeda
    dengan development, dan itu memang tidak dipakai sebagai kunci oleh aplikasi.
    Id_Grup_Fitur juga TIDAK di-hardcode 4, melainkan diresolusi dari
    Kode_Grup_Fitur = 'VISITOR' supaya tidak salah modul bila urutan master
    grup di produksi berbeda.
*/

SET XACT_ABORT ON;
SET NOCOUNT ON;

DECLARE @Id_Grup_Visitor int;
DECLARE @Kode_Perusahaan varchar(10) = '001';

SELECT @Id_Grup_Visitor = Id_Grup_Fitur
FROM N_HRIS_Master_Grup_Fitur
WHERE Kode_Grup_Fitur = 'VISITOR'
  AND Flag_Aktif = 'Y';

IF @Id_Grup_Visitor IS NULL
    THROW 50044, 'Grup fitur VISITOR aktif tidak ditemukan di N_HRIS_Master_Grup_Fitur. Daftarkan grup terlebih dahulu.', 1;

DECLARE @Menu TABLE
(
    Jenis_page           varchar(100) NOT NULL PRIMARY KEY,
    Nama_Menu            varchar(255) NOT NULL,
    Icon_Menu            varchar(255) NULL,
    Url_Menu             varchar(255) NOT NULL,
    Header_Code          varchar(100) NOT NULL,
    Header_Label         varchar(150) NOT NULL,
    Header_Icon          varchar(100) NULL,
    Sort_Header          int NOT NULL,
    Is_Global_Menu       char(1) NOT NULL,
    Sort_Menu            int NOT NULL,
    Active_Match_Pattern varchar(255) NULL,
    Target               varchar(20) NOT NULL,
    Is_Show_Sidebar      char(1) NOT NULL,
    Menu_Subtitle        varchar(150) NULL,
    Sort_Order_Map       int NULL
);

/* Header "Visitor" (Sort_Header 4): halaman transaksi untuk semua karyawan. */
INSERT INTO @Menu
(Jenis_page, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle, Sort_Order_Map)
VALUES
('visitorPermitPage', 'Permit Kendaraan', 'bi bi-car-front-fill', '/transaksipermit', 'visitor', 'Visitor', 'bi bi-people-fill', 4, 'Y', 10, '/transaksipermit*', 'self', 'Y', 'Ajukan & kelola permit kendaraan pribadi', 10),
('visitorTamuPage', 'Kunjungan Tamu', 'bi bi-person-badge', '/transaksitamu', 'visitor', 'Visitor', 'bi bi-people-fill', 4, 'Y', 20, '/transaksitamu*', 'self', 'Y', 'Registrasi & riwayat kunjungan tamu', 20);

/* Header "Administrator Visitor" (Sort_Header 10): halaman pengelola. */
INSERT INTO @Menu
(Jenis_page, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle, Sort_Order_Map)
VALUES
('visitorMasterDataPage', 'Master Data Visitor', 'bi-database-gear', '/admin/visitor/master-data', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', 10, 'T', 10, NULL, 'self', 'Y', 'Kelola referensi operasional tamu dan permit', NULL),
('visitorVerifikatorPage', 'Verifikator Visitor', 'bi bi-patch-check', '/verifikator-visitor', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', 10, 'T', 20, '/verifikator-visitor*', 'self', 'Y', 'Pool verifikator gabungan permit + tamu', 40),
('visitorLaporanPage', 'Laporan Visitor', 'bi bi-bar-chart-line', '/laporanvisitor', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', 10, 'T', 30, '/laporanvisitor*', 'self', 'Y', 'Dashboard & laporan gabungan permit + tamu', 50),
('visitorExportStudioPage', 'Export Studio Visitor', 'bi bi-sliders', '/visitor/export-studio', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', 10, 'T', 40, '/visitor/export-studio*', 'self', 'Y', 'Rakit dan unduh laporan tamu & permit', NULL),
('menuApprovalMatrixVisitorPage', 'Menu & Matrix', 'bi bi-diagram-3', '/admin/visitor/menu-approval-matrix', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', 10, 'T', 90, '/admin/visitor/menu-approval-matrix*', 'self', 'Y', 'Kelola menu, approval, dan keanggotaan modul aktif', NULL);

BEGIN TRANSACTION;

/* 1. Baris menu. Ekspektasi pada database yang belum punya menu Visitor: 7 baris. */
INSERT INTO N_HRIS_Menus
(Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
SELECT
    s.Jenis_page,
    @Kode_Perusahaan,
    s.Nama_Menu,
    s.Icon_Menu,
    s.Url_Menu,
    s.Header_Code,
    s.Header_Label,
    s.Header_Icon,
    s.Sort_Header,
    s.Is_Global_Menu,
    s.Sort_Menu,
    s.Active_Match_Pattern,
    NULL,
    s.Target,
    s.Is_Show_Sidebar,
    s.Menu_Subtitle
FROM @Menu s
WHERE NOT EXISTS (
    SELECT 1
    FROM N_HRIS_Menus m
    WHERE m.Jenis_page = s.Jenis_page
);

/* 2. Pemetaan ke grup fitur VISITOR. Tanpa baris ini menunya tidak pernah muncul. */
INSERT INTO N_HRIS_Grup_Fitur_Map
(Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT
    @Id_Grup_Visitor,
    s.Jenis_page,
    s.Sort_Order_Map,
    'T',
    'Y',
    'Y'
FROM @Menu s
WHERE NOT EXISTS (
    SELECT 1
    FROM N_HRIS_Grup_Fitur_Map g
    WHERE g.Id_Grup_Fitur = @Id_Grup_Visitor
      AND g.Jenis_page = s.Jenis_page
);

/* 3. Pendaftaran ke Database Change Dashboard, hanya bila tabelnya ada. */
IF OBJECT_ID('N_SYSTEM_Database_Change', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-044-register-visitor-active-menus')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-044-register-visitor-active-menus', 'DML', 'Register active Visitor menus', 'Menu dan pemetaan grup fitur untuk 7 halaman Visitor aktif; tanpa HRIS_Page_Access dan tanpa visitorApprovalPage.', 'database/dml/DML-2026-044-register-visitor-active-menus/data-change.sql', 'docs/changes/DML-2026-044-register-visitor-active-menus/README.md', 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'visitor-menu-registration');
END;

COMMIT TRANSACTION;

/* 4. Verifikasi: harus mengembalikan 7 baris, semuanya dengan Map_Aktif = 'Y'. */
SELECT
    m.Id_Menu,
    m.Jenis_page,
    m.Nama_Menu,
    m.Url_Menu,
    m.Header_Label,
    m.Sort_Header,
    m.Sort_Menu,
    g.Id_Grup_Fitur,
    g.Flag_Aktif AS Map_Aktif
FROM N_HRIS_Menus m
INNER JOIN N_HRIS_Grup_Fitur_Map g
        ON g.Jenis_page = m.Jenis_page
       AND g.Id_Grup_Fitur = @Id_Grup_Visitor
WHERE m.Jenis_page IN (
    'visitorPermitPage',
    'visitorTamuPage',
    'visitorMasterDataPage',
    'visitorVerifikatorPage',
    'visitorLaporanPage',
    'visitorExportStudioPage',
    'menuApprovalMatrixVisitorPage'
)
ORDER BY m.Sort_Header, m.Sort_Menu;
