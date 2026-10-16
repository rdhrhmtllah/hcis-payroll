/*
 * RBAC Fase 0 — laporan dampak (HANYA SELECT, tidak mengubah apa pun).
 *
 * Siapa yang bisa membuka halaman admin, dan lewat jalur apa — meniru persis
 * User::memilikiAksesBaru() (middleware check.jabatan):
 *   GRANT_LANGSUNG : HRIS_Page_Access (Flag_Aktif = 'Y')
 *   HIBAH_ROLE     : N_HRIS_Role_User -> N_HRIS_Role_Functional (aktif)
 *                    -> N_HRIS_Grup_Fitur_Map (Flag_Hibah_Role = 'Y')
 *   GLOBAL_MENU    : N_HRIS_Menus.Is_Global_Menu = 'Y' (semua user)
 *
 * Kolom Kategori:
 *   TATA_KELOLA = halaman yang bisa memberi/mengubah akses orang lain
 *                 (sasaran utama penambalan Fase 0)
 *   ADMIN_MODUL = halaman admin modul lainnya
 *
 * Kolom Hanya_Lewat_Hibah = 'Y' berarti user ini membuka halaman itu SEMATA
 * karena hibah role (tak punya grant langsung) — merekalah yang akan terkunci
 * bila hibah untuk halaman tata kelola dimatikan. Tinjau satu per satu.
 *
 * Tetap_Lewat_Role_Admin = 'Y' bila halaman ITU tetap dicapai lewat hibah
 * role di config authorization.tata_kelola.peran_admin (per halaman, sama
 * dengan HibahTataKelola::saringHibah). Baris TATA_KELOLA dengan
 * Hanya_Lewat_Hibah='Y' DAN Tetap_Lewat_Role_Admin='T' = user yang AKAN
 * kehilangan halaman itu setelah Fase 0 dirilis.
 *
 * Jalankan di prod lewat SQL Tracker / SSMS, kirim hasilnya (dua result set).
 */

WITH halaman AS (
    SELECT m.Jenis_page, m.Nama_Menu, m.Header_Label, m.Is_Global_Menu,
           CASE WHEN m.Jenis_page IN (
                'adminAllPagesAccess', 'superAdminManajemenPeranPage', 'systemAuthManagerPage',
                'menuApprovalMatrixPage', 'menuApprovalMatrixEssPage', 'menuApprovalMatrixKpiPage', 'menuApprovalMatrixLmsPage',
                'menuApprovalMatrixVisitorPage', 'ManajemenAksesDivisi', 'ManajemenAksesLevel',
                'ActivityMonitor')
                THEN 'TATA_KELOLA' ELSE 'ADMIN_MODUL' END AS Kategori
    FROM N_HRIS_Menus m
    WHERE m.Header_Label LIKE 'Administrator%' OR m.Header_Label LIKE 'System Admin%'
),
jalur AS (
    SELECT pa.UserID_Web AS Id_User, pa.Jenis_page, 'GRANT_LANGSUNG' AS Jalur, CAST(NULL AS varchar(100)) AS Role_Code
    FROM HRIS_Page_Access pa
    WHERE pa.Flag_Aktif = 'Y'
    UNION
    SELECT ru.UserId_Web, gfm.Jenis_page, 'HIBAH_ROLE', rf.Role_Code
    FROM N_HRIS_Role_User ru
    JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role AND rf.Flag_Aktif = 'Y'
    JOIN N_HRIS_Grup_Fitur_Map gfm ON gfm.Id_Grup_Fitur = rf.Id_Grup_Fitur AND gfm.Flag_Hibah_Role = 'Y'
    WHERE ru.Flag_Aktif = 'Y'
),
akses AS (
    SELECT h.Kategori, h.Header_Label, h.Nama_Menu, h.Jenis_page, h.Is_Global_Menu,
           j.Id_User, j.Jalur, j.Role_Code
    FROM halaman h
    JOIN jalur j
      ON LOWER(REPLACE(LTRIM(RTRIM(j.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', ''))
),
per_user AS (
    SELECT a.Kategori, a.Header_Label, a.Nama_Menu, a.Jenis_page, a.Id_User,
           MAX(CASE WHEN a.Jalur = 'GRANT_LANGSUNG' THEN 1 ELSE 0 END) AS Ada_Grant,
           STRING_AGG(CASE WHEN a.Jalur = 'HIBAH_ROLE' THEN a.Role_Code END, ', ') AS Role_Hibah
    FROM akses a
    GROUP BY a.Kategori, a.Header_Label, a.Nama_Menu, a.Jenis_page, a.Id_User
)
SELECT p.Kategori, p.Header_Label, p.Nama_Menu, p.Jenis_page,
       p.Id_User, k.Kode_Karyawan, k.Nama, k.Aktif AS Karyawan_Aktif,
       CASE WHEN u.Is_System_Admin = 'Y' THEN 'Y' ELSE 'T' END AS System_Admin,
       CASE WHEN p.Ada_Grant = 1 THEN 'Y' ELSE 'T' END AS Grant_Langsung,
       p.Role_Hibah,
       CASE WHEN p.Ada_Grant = 0 THEN 'Y' ELSE 'T' END AS Hanya_Lewat_Hibah,
       CASE WHEN EXISTS (
            SELECT 1 FROM N_HRIS_Role_User ru
            JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role AND rf.Flag_Aktif = 'Y'
            JOIN N_HRIS_Grup_Fitur_Map gfm ON gfm.Id_Grup_Fitur = rf.Id_Grup_Fitur AND gfm.Flag_Hibah_Role = 'Y'
            WHERE ru.UserId_Web = p.Id_User AND ru.Flag_Aktif = 'Y'
              AND LOWER(REPLACE(LTRIM(RTRIM(gfm.Jenis_page)), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(p.Jenis_page)), ' ', ''))
              AND UPPER(rf.Role_Code) IN ('ESS_MENU_ADMINISTRATOR','ESS_APPROVAL_ADMINISTRATOR','ESS_MEMBERSHIP_ADMINISTRATOR',
                'ESS_MENU_APPROVAL_ADMINISTRATOR','ESS_MENU_ADMINISTRATOR_PHL','ESS_APPROVAL_ADMINISTRATOR_PHL','KPI_SUPER_ADMIN',
                'KPI_ADMIN_PHL','KPI_MENU_ADMINISTRATOR','KPI_APPROVAL_ADMINISTRATOR','KPI_MEMBERSHIP_ADMINISTRATOR',
                'KPI_MENU_APPROVAL_ADMINISTRATOR','LMS_MENU_ADMINISTRATOR','LMS_APPROVAL_ADMINISTRATOR',
                'LMS_MENU_APPROVAL_ADMINISTRATOR','VISITOR_MENU_ADMINISTRATOR','VISITOR_APPROVAL_ADMINISTRATOR',
                'VISITOR_MENU_APPROVAL_ADMINISTRATOR')
       ) THEN 'Y' ELSE 'T' END AS Tetap_Lewat_Role_Admin
FROM per_user p
JOIN KPI_Users u ON u.Id_Users = p.Id_User
LEFT JOIN Karyawan k ON k.UserID_Web = p.Id_User
ORDER BY p.Kategori DESC, p.Header_Label, p.Nama_Menu, Hanya_Lewat_Hibah DESC, k.Nama;

/* Ringkasan per halaman: berapa user, berapa yang hanya lewat hibah. */
WITH halaman AS (
    SELECT m.Jenis_page, m.Nama_Menu, m.Header_Label, m.Is_Global_Menu
    FROM N_HRIS_Menus m
    WHERE m.Header_Label LIKE 'Administrator%' OR m.Header_Label LIKE 'System Admin%'
),
grant_langsung AS (
    SELECT DISTINCT pa.UserID_Web AS Id_User, LOWER(REPLACE(LTRIM(RTRIM(pa.Jenis_page)), ' ', '')) AS Kunci
    FROM HRIS_Page_Access pa WHERE pa.Flag_Aktif = 'Y'
),
hibah AS (
    SELECT DISTINCT ru.UserId_Web AS Id_User, LOWER(REPLACE(LTRIM(RTRIM(gfm.Jenis_page)), ' ', '')) AS Kunci
    FROM N_HRIS_Role_User ru
    JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role AND rf.Flag_Aktif = 'Y'
    JOIN N_HRIS_Grup_Fitur_Map gfm ON gfm.Id_Grup_Fitur = rf.Id_Grup_Fitur AND gfm.Flag_Hibah_Role = 'Y'
    WHERE ru.Flag_Aktif = 'Y'
)
SELECT h.Header_Label, h.Nama_Menu, h.Jenis_page, h.Is_Global_Menu,
       (SELECT COUNT(*) FROM grant_langsung g WHERE g.Kunci = LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', ''))) AS User_Grant,
       (SELECT COUNT(*) FROM hibah x WHERE x.Kunci = LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', ''))
            AND NOT EXISTS (SELECT 1 FROM grant_langsung g WHERE g.Id_User = x.Id_User AND g.Kunci = x.Kunci)) AS User_Hanya_Hibah
FROM halaman h
ORDER BY User_Hanya_Hibah DESC, h.Header_Label, h.Nama_Menu;
