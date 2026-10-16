/* Change ID: DML-2026-095-pa-halaman-syarat-kompetensi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-094. Jalankan sesudahnya.
   Scope: N_HRIS_Menus, N_HRIS_Grup_Fitur_Map, HRIS_Page_Access — baris PA saja.

   MASALAH YANG DISELESAIKAN

   `N_PA_Competency_Requirement` menentukan dimensi apa yang muncul di formulir penilaian
   seseorang — Pramukarya hanya Behavior, Staff ditambah Manajerial dan Core Value, Leaders
   ditambah Qualitative. Tabelnya sudah terisi 33 baris dan sudah DIPAKAI.

   Tetapi tidak ada satu pun layar untuk membacanya, apalagi mengubahnya. Satu-satunya cara
   HC menyesuaikannya adalah meminta orang menulis SQL langsung ke produksi, dan perubahan
   sepenting ini berjalan tanpa jejak siapa yang memutuskannya.

   HALAMAN BARU WAJIB TIGA BARIS

   Menu, pemetaan grup fitur, dan hak akses. Kurang salah satu, halamannya 403 untuk SEMUA
   ORANG — termasuk yang membuatnya. Hak ditiru dari pemegang halaman master kompetensi,
   karena orang yang boleh mengubah daftar kompetensi adalah orang yang sama yang boleh
   menentukan kompetensi mana berlaku di level mana.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi berada dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT.
   `GO` mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50450, 'DML-2026-095 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Butir menu                                                       *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Url_Menu = '/performance-appraisal/master/syarat-kompetensi')
BEGIN
    INSERT INTO N_HRIS_Menus (
        Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
        Header_Code, Header_Label, Header_Icon, Sort_Header,
        Is_Global_Menu, Sort_Menu, Is_Show_Sidebar, Menu_Subtitle
    )
    SELECT
        'PaMasterSyaratKompetensiPage',
        m.Kode_Perusahaan,
        'Dimensi per Level',
        'grid-3x3',
        '/performance-appraisal/master/syarat-kompetensi',
        'pa_master',
        'Master Data',
        m.Header_Icon,
        /* Diambil dari baris acuan, TIDAK ditulis angka. Menebaknya membelah header
           `pa_master` pada dua Sort_Header, dan grupnya tampil dua kali di sidebar. */
        m.Sort_Header,
        'T',
        7,
        'Y',
        'Dimensi apa saja yang dinilai pada tiap level jabatan. Menentukan isi formulir 360.'
    FROM N_HRIS_Menus m
    WHERE m.Url_Menu = '/performance-appraisal/master/kompetensi';
END;

/* ------------------------------------------------------------------ *
 * 2. Pemetaan grup fitur                                              *
 * ------------------------------------------------------------------ *
 * Tanpa baris ini halaman tidak punya grup, dan resolusi menu melewatinya diam-diam.  */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaMasterSyaratKompetensiPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map (Jenis_page, Id_Grup_Fitur, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT DISTINCT 'PaMasterSyaratKompetensiPage', g.Id_Grup_Fitur, 'T', 'Y', 'Y'
    FROM N_HRIS_Grup_Fitur_Map g
    WHERE g.Jenis_page = 'PaMasterKompetensiPage' AND g.Flag_Aktif = 'Y';
END;

/* ------------------------------------------------------------------ *
 * 3. Hak akses — ditiru dari pemegang master kompetensi               *
 * ------------------------------------------------------------------ */

/* Seluruh kolom pengenalnya ikut disalin. Hak di tabel ini bukan sekadar (user, halaman):
   ia juga terikat perusahaan, level, dan divisi. Menyalin sebagian menghasilkan baris yang
   ada tetapi tidak pernah cocok saat diperiksa. */
INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At)
SELECT DISTINCT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, 'PaMasterSyaratKompetensiPage', a.UserID_Web, 'Y', SYSDATETIME()
FROM HRIS_Page_Access a
WHERE a.Jenis_page = 'PaMasterKompetensiPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access x
      WHERE x.UserID_Web = a.UserID_Web
        AND x.Jenis_page = 'PaMasterSyaratKompetensiPage'
  );

/* ------------------------------------------------------------------ *
 * 4. Penjaga                                                          *
 * ------------------------------------------------------------------ */

DECLARE @menu int = (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Url_Menu = '/performance-appraisal/master/syarat-kompetensi');
DECLARE @map  int = (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaMasterSyaratKompetensiPage' AND Flag_Aktif = 'Y');
DECLARE @hak  int = (SELECT COUNT(DISTINCT UserID_Web) FROM HRIS_Page_Access WHERE Jenis_page = 'PaMasterSyaratKompetensiPage' AND Flag_Aktif = 'Y');
DECLARE @acu  int = (SELECT COUNT(DISTINCT UserID_Web) FROM HRIS_Page_Access WHERE Jenis_page = 'PaMasterKompetensiPage' AND Flag_Aktif = 'Y');

IF @menu < 1 THROW 50451, 'Butir menu Dimensi per Level tidak terbentuk.', 1;
IF @map  < 1 THROW 50452, 'Pemetaan grup fitur tidak terbentuk; halaman akan 403 untuk semua.', 1;

/* Jumlah pemegang halaman baru HARUS sama dengan pemegang halaman acuan. Lebih sedikit
   berarti ada yang tertinggal dan akan menemukan menunya tetapi ditolak saat membuka. */
IF @hak <> @acu
BEGIN
    DECLARE @p nvarchar(200) = CONCAT('Hak akses: ', @hak, ' pemegang, seharusnya ', @acu, ' seperti master kompetensi.');
    THROW 50453, @p, 1;
END;

/* Halaman admin tidak boleh global. Satu baris global membuat 958 karyawan melihat menu
   yang hanya bisa dibuka segelintir orang. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Url_Menu = '/performance-appraisal/master/syarat-kompetensi' AND Is_Global_Menu = 'Y'
)
    THROW 50454, 'Halaman Dimensi per Level ditandai global; seharusnya tidak.', 1;

/* Satu Header_Code tidak boleh berjalan pada dua Sort_Header. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'pa_master' AND Url_Menu LIKE '/performance-appraisal%'
    GROUP BY Kode_Perusahaan
    HAVING COUNT(DISTINCT Sort_Header) > 1
)
    THROW 50456, 'Header pa_master terbelah pada dua Sort_Header; grupnya akan tampil dua kali.', 1;

/* Tidak boleh ada urutan kembar di dalam header Master Data. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'pa_master' AND Url_Menu LIKE '/performance-appraisal%'
    GROUP BY Kode_Perusahaan, Sort_Menu
    HAVING COUNT(*) > 1
)
    THROW 50455, 'Ada urutan menu kembar di header Master Data PA.', 1;

COMMIT TRANSACTION;
GO

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
