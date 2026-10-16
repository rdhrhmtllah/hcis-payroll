/* Change ID: DML-2026-097-menu-relasi-organisasi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-061 (N_HRIS_Organization_Relationship). Jalankan sesudahnya.
   Scope: N_HRIS_Menus dan N_HRIS_Grup_Fitur_Map — satu halaman SYSTEM.

   MASALAH YANG DISELESAIKAN

   `N_HRIS_Organization_Relationship` berisi nol baris, dan sampai sekarang tidak ada satu pun
   jalur tulis ke sana di seluruh aplikasi: tak ada layar, tak ada perintah, tak ada seed.
   Yang membacanya hanya `PaAssignmentBuilderService`.

   Akibatnya usulan penilai untuk ATASAN, PEER, dan BAWAHAN selalu kosong, dan penugasan
   selain penilaian diri ditolak karena wajib menunjuk relasi asal. Pada siklus berjalan itu
   terlihat sebagai 859 peserta dengan nol penugasan.

   Halaman `/system/organisasi/relasi` menutup lubang itu.

   KENAPA TIDAK ADA BARIS HRIS_Page_Access

   Halaman SYSTEM digerbangi middleware `system.admin`, bukan hak per halaman. Master Entitas
   dan Master Departemen pun nol baris di sana. Menyisipkannya justru menyesatkan: ia
   memberi kesan hak itu diperiksa, padahal tidak ada yang membacanya.

   KENAPA HEADER "MASTER DATA", BUKAN HEADER SENDIRI

   Relasi organisasi adalah master LINTAS MODUL — Performance Appraisal hanya salah satu
   pembacanya. Menaruhnya di header PA akan menyiratkan ia milik PA, dan orang berikutnya yang
   membutuhkannya tidak akan mencarinya di sana.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT. `GO`
   mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50480, 'DML-2026-097 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Butir menu                                                       *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Url_Menu = '/system/organisasi/relasi')
BEGIN
    INSERT INTO N_HRIS_Menus (
        Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
        Header_Code, Header_Label, Header_Icon, Sort_Header,
        Is_Global_Menu, Sort_Menu, Is_Show_Sidebar,
        Active_Match_Pattern, Route_Name, Target, Menu_Subtitle
    )
    SELECT
        'systemRelasiOrganisasiPage',
        m.Kode_Perusahaan,
        'Relasi Organisasi',
        'bi bi-diagram-3-fill',
        '/system/organisasi/relasi',
        m.Header_Code,
        m.Header_Label,
        m.Header_Icon,
        /* Header dan urutannya DIAMBIL dari baris acuan, tidak ditulis angka. Menebaknya
           membelah header pada dua Sort_Header dan grupnya tampil dua kali di sidebar —
           kekeliruan yang persis pernah terjadi pada menu PA. */
        m.Sort_Header,
        'T',
        (SELECT MAX(Sort_Menu) + 1 FROM N_HRIS_Menus WHERE Header_Code = m.Header_Code),
        'Y',
        '/system/organisasi/relasi*',
        'system.organisasi.relasi.page',
        'self',
        'Siapa atasan, bawahan, dan rekan kerja siapa. Sumber calon penilai Performance Appraisal.'
    FROM N_HRIS_Menus m
    WHERE m.Url_Menu = '/system/master-data/departemen';
END;

/* ------------------------------------------------------------------ *
 * 2. Pemetaan grup fitur                                              *
 * ------------------------------------------------------------------ *
 * Tanpa baris ini halaman tidak punya grup, dan resolusi menu melewatinya diam-diam.   */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'systemRelasiOrganisasiPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map (Jenis_page, Id_Grup_Fitur, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT DISTINCT 'systemRelasiOrganisasiPage', g.Id_Grup_Fitur, 'T', 'Y', 'Y'
    FROM N_HRIS_Grup_Fitur_Map g
    WHERE g.Jenis_page = 'systemMasterDepartemenPage' AND g.Flag_Aktif = 'Y';
END;

/* ------------------------------------------------------------------ *
 * 3. Penjaga                                                          *
 * ------------------------------------------------------------------ */

DECLARE @menu int = (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Url_Menu = '/system/organisasi/relasi');
DECLARE @map  int = (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'systemRelasiOrganisasiPage' AND Flag_Aktif = 'Y');

IF @menu < 1 THROW 50481, 'Butir menu Relasi Organisasi tidak terbentuk.', 1;
IF @map  < 1 THROW 50482, 'Pemetaan grup fitur tidak terbentuk; halaman tidak akan muncul di menu.', 1;

/* Satu Header_Code tidak boleh berjalan pada dua Sort_Header: grupnya akan tampil DUA KALI
   di sidebar dengan nama sama dan isi berbeda, tanpa satu pun galat. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'master_data'
    GROUP BY Kode_Perusahaan, Header_Code
    HAVING COUNT(DISTINCT Sort_Header) > 1
)
    THROW 50483, 'Header master_data terbelah pada dua Sort_Header.', 1;

/* Tidak boleh ada urutan kembar dalam satu header. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'master_data'
    GROUP BY Kode_Perusahaan, Sort_Menu
    HAVING COUNT(*) > 1
)
    THROW 50484, 'Ada urutan menu kembar di header Master Data.', 1;

/* Halaman SYSTEM tidak boleh global: ia hanya untuk system admin, dan satu baris global
   membuat seluruh karyawan melihat menu yang tidak bisa mereka buka. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Url_Menu = '/system/organisasi/relasi' AND Is_Global_Menu = 'Y'
)
    THROW 50485, 'Halaman Relasi Organisasi ditandai global; seharusnya tidak.', 1;

COMMIT TRANSACTION;
GO

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
