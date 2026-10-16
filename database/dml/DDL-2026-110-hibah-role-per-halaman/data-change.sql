/* Change ID: DDL-2026-110-hibah-role-per-halaman — DATA */

/*
    Dua langkah, dan URUTANNYA menentukan.

      1. LENGKAPI dulu kunci halaman per orang untuk domainnya masing-masing.
      2. BARU cabut hibah menyeluruh grup VISITOR.

    Dibalik urutannya, Andre dan Imam kehilangan seluruh menu Visitor di antara
    kedua langkah — mereka hari ini hanya punya SATU kunci eksplisit
    (verifikator domainnya), sisanya selama ini datang dari hibah grup.

    Yang TIDAK disentuh:

      - visitorPermitPage dan visitorTamuPage. Keduanya menu GLOBAL
        (N_HRIS_Menus.Is_Global_Menu='Y'), jadi pengajuan permit dan tamu tetap
        terbuka untuk seluruh karyawan tanpa bergantung pada grup maupun kunci.
      - Struktur sidebar. Seluruh baris peta tetap ada; hanya penanda hibahnya
        yang berubah, dan penyusun sidebar membaca semua baris.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── Sasaran: halaman admin per domain ───────────────────────────────────── */
DECLARE @halaman TABLE (
    Jenis_page varchar(100) NOT NULL,
    Domain varchar(10) NOT NULL,
    Urutan_Menu int NOT NULL
);

INSERT INTO @halaman (Jenis_page, Domain, Urutan_Menu)
VALUES
    ('visitorVerifikatorPermitPage',  'PERMIT', 20),
    ('visitorMonitoringPermitPage',   'PERMIT', 21),
    ('visitorExportStudioPermitPage', 'PERMIT', 22),
    ('visitorMasterDataPermitPage',   'PERMIT', 23),
    ('visitorVerifikatorTamuPage',    'TAMU',   25),
    ('visitorMonitoringTamuPage',     'TAMU',   26),
    ('visitorExportStudioTamuPage',   'TAMU',   27),
    ('visitorMasterDataTamuPage',     'TAMU',   28);

/* ── Penjaga: seluruh halaman sasaran harus benar-benar ada ──────────────── */
IF EXISTS (
    SELECT 1 FROM @halaman h
     WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus m WHERE m.Jenis_page = h.Jenis_page)
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50110, 'Ada Jenis_page sasaran yang tidak terdaftar di N_HRIS_Menus.', 1;
END;

/* ── 1. Kunci halaman per orang, mengikuti ROLE yang ia pegang ───────────── */
/*
    Dimensi perusahaan/level/divisi diambil dari baris akses milik orangnya
    sendiri — nilainya berbeda per orang, dan angka yang dikarang membuat
    barisnya tidak pernah cocok dengan penyaring menu.

    DISTINCT + NOT EXISTS keduanya wajib: (UserId_Web, Id_Role) pada
    N_HRIS_Role_User tidak unik, dan barisnya memang sudah pernah kembar.
*/
INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT
    dimensi.Kode_Perusahaan,
    dimensi.ID_Level,
    dimensi.ID_Divisi,
    h.Jenis_page,
    ru.UserId_Web,
    h.Urutan_Menu,
    'Y',
    SYSDATETIME()
FROM N_HRIS_Role_User ru
INNER JOIN N_HRIS_Role_Functional r
        ON r.Id_Role = ru.Id_Role
       AND r.Flag_Aktif = 'Y'
       AND r.Role_Code IN ('ADMIN_PERMIT', 'ADMIN_TAMU')
INNER JOIN @halaman h
        ON h.Domain = CASE WHEN r.Role_Code = 'ADMIN_PERMIT' THEN 'PERMIT' ELSE 'TAMU' END
CROSS APPLY (
    SELECT TOP 1 pa.Kode_Perusahaan, pa.ID_Level, pa.ID_Divisi
      FROM HRIS_Page_Access pa
     WHERE pa.UserID_Web = ru.UserId_Web
       AND pa.Flag_Aktif = 'Y'
     ORDER BY pa.Id_Page_Access
) AS dimensi
WHERE ru.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access ada
       WHERE ada.Jenis_page = h.Jenis_page
         AND ada.UserID_Web = ru.UserId_Web
         AND ada.Flag_Aktif = 'Y'
  );

/* ── 2. Cabut hibah menyeluruh untuk halaman ADMIN Visitor ───────────────── */
/*
    Sesudah baris ini, memegang role Visitor tidak lagi membuka seluruh modul;
    yang menentukan adalah kunci halaman per orang yang baru dilengkapi di
    langkah 1.

    Halaman warisan yang tersembunyi dari sidebar (visitorVerifikatorPage,
    visitorLaporanPage, visitorExportStudioPage, visitorMasterDataPage) IKUT
    dicabut hibahnya: ketiganya hanya pengalihan ke halaman domain, dan
    membiarkannya tetap memberi hibah berarti pintu belakang yang membuka
    endpoint lama bagi orang yang domainnya bukan itu.

    visitorApprovalPage juga dicabut — halaman approval lama yang mutasinya
    sudah dipensiunkan (410) dan hanya menambah kebingungan di sidebar.

    menuApprovalMatrixVisitorPage dicabut supaya administrasi menu Visitor
    menjadi pemberian per orang, bukan efek samping memegang role verifikator.
*/
UPDATE N_HRIS_Grup_Fitur_Map
   SET Flag_Hibah_Role = 'T'
 WHERE Id_Grup_Fitur = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR')
   AND Jenis_page NOT IN ('visitorPermitPage', 'visitorTamuPage');

/* ── 3. Sembunyikan menu approval Visitor yang sudah dipensiunkan ────────── */
/*
    visitorApprovalPage adalah halaman approval Visitor generasi lama. Seluruh
    mutasinya sudah dipensiunkan — route-nya membalas 410 — dan halamannya
    sendiri hanya mengalihkan ke meja verifikator. Yang tersisa cuma satu baris
    bernama "Approval Permit (Lama)" di sidebar orang-orang yang kebetulan masih
    memegang kunci eksplisitnya.

    Barisnya TIDAK dihapus, hanya disembunyikan. Menghapusnya memutus baris
    N_HRIS_Grup_Fitur_Map dan HRIS_Page_Access yang menunjuknya, dan bookmark
    lama akan berhenti mengalihkan — padahal pengalihan itulah satu-satunya
    gunanya sekarang.
*/
UPDATE N_HRIS_Menus
   SET Is_Show_Sidebar = 'T'
 WHERE Jenis_page = 'visitorApprovalPage'
   AND Is_Show_Sidebar <> 'T';

/* ── 4. Pendaftaran ke Database Change Dashboard ─────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-110-hibah-role-per-halaman')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-110-hibah-role-per-halaman', 'DDL', 'Pisahkan struktur menu dari hibah akses per grup fitur', 'Kolom Flag_Hibah_Role memisahkan peran N_HRIS_Grup_Fitur_Map sebagai penyusun sidebar dari perannya sebagai pemberi hibah role. Halaman admin Visitor dicabut hibahnya sehingga kunci per orang menjadi otoritas, memisahkan ADMIN_PERMIT dari ADMIN_TAMU.', 'database/ddl/DDL-2026-110-hibah-role-per-halaman/forward.sql', 'docs/changes/DDL-2026-110-hibah-role-per-halaman/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-menu-per-role');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────────── */
SELECT gfm.Jenis_page, gfm.Flag_Hibah_Role, m.Nama_Menu, m.Is_Global_Menu, m.Is_Show_Sidebar
  FROM N_HRIS_Grup_Fitur_Map gfm
  LEFT JOIN N_HRIS_Menus m ON m.Jenis_page = gfm.Jenis_page
 WHERE gfm.Id_Grup_Fitur = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR')
 ORDER BY gfm.Flag_Hibah_Role, gfm.Jenis_page;

SELECT Jenis_page, Nama_Menu, Is_Show_Sidebar, Is_Global_Menu
  FROM N_HRIS_Menus
 WHERE Jenis_page IN ('visitorApprovalPage', 'visitorPermitPage', 'visitorTamuPage');

SELECT k.Nama, pa.Jenis_page
  FROM HRIS_Page_Access pa
  LEFT JOIN Karyawan k ON k.UserID_Web = pa.UserID_Web
 WHERE pa.Flag_Aktif = 'Y'
   AND pa.Jenis_page LIKE 'visitor%'
 ORDER BY k.Nama, pa.Jenis_page;
