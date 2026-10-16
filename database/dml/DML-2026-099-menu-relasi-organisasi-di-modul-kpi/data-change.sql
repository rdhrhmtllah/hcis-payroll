/* Change ID: DML-2026-099-menu-relasi-organisasi-di-modul-kpi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-097 (halaman SYSTEM), DML-2026-098 (gerbang ATAU). Jalankan sesudahnya.
   Scope: N_HRIS_Menus dan N_HRIS_Grup_Fitur_Map — satu butir menu baru di modul KPI.

   YANG DIMINTA

   Butir menu untuk admin PA di header modul KPI, supaya ia tidak perlu turun ke modul SYSTEM
   untuk membuka master relasi organisasi.

   AKIBAT AKSESNYA — DIPUTUSKAN, BUKAN TERSELIP

   Di sistem ini akses role diturunkan dari N_HRIS_Grup_Fitur_Map, tabel yang sama yang
   menentukan menu. Memetakan halaman ke grup KPI karena itu MEMBERIKAN aksesnya kepada
   SETIAP pemegang role KPI — sekarang lima orang, dan siapa pun yang ditambahkan kelak,
   termasuk role yang namanya menjanjikan cakupan lain ("KPI Menu Administrator").

   Itu diuji, bukan didugaan: satu baris pemetaan mengubah pemegang role KPI dari 403 menjadi
   lolos seketika. Tidak ada cara memisahkan "tampil di menu KPI" dari "diberikan ke role
   KPI" lewat tabel ini.

   Konsekuensi itu sudah disampaikan dan diterima. Dicatat di sini supaya orang berikutnya
   yang membaca tidak menyangkanya kelalaian.

   Catatan lanjutan yang MUDAH menipu: `Flag_Aktif = 'T'` pada baris pemetaan menyembunyikan
   menunya TETAPI TIDAK mencabut aksesnya — kueri akses tidak menyaring kolom itu. Jadi
   mematikan baris ini bukan cara mencabut. Yang mencabut adalah MENGHAPUS barisnya.

   KENAPA BUTIR BARU, BUKAN PEMETAAN KEDUA ATAS BUTIR YANG ADA

   Butir SYSTEM ber-`Header_Code = 'master_data'`, sedangkan header Master Data milik modul
   KPI ber-kode `pa_master`. Sidebar mengelompokkan butir menurut KODE header, bukan labelnya.
   Jadi memetakan butir SYSTEM ke grup KPI melahirkan grup "Master Data" KEDUA di dalam modul
   KPI — label sama, isi berbeda, dan admin tidak punya cara tahu yang mana yang mana. Itu
   sudah terlihat saat diuji.

   Butir baru dengan `Header_Code = 'pa_master'` masuk ke grup Master Data yang SUDAH ADA,
   sebagai butir kesembilan sesudah Paket Soal Baku.

   `Jenis_page` unik di N_HRIS_Menus (nol kembar hari ini), dan join menu ke pemetaan memakai
   kolom itu. Butir baru karena itu WAJIB punya kode halaman sendiri: memakai kode yang sama
   akan melipatgandakan barisnya di setiap modul yang memetakannya.

   ALAMATNYA SAMA, DAN ITU DISENGAJA

   Kedua butir menunjuk `/system/organisasi/relasi`. Satu halaman, satu sumber kebenaran.
   Membuat halaman PA sendiri berarti dua layar yang harus dijaga sinkron, dan yang satu
   akan tertinggal.

   Akibatnya System Admin melihat butirnya DUA KALI: sekali di SYSTEM > Master Data, sekali
   di KPI > Master Data. Itu diterima: sysadmin memang mencarinya di SYSTEM, admin PA di KPI.
   Kalau kelak diputuskan satu saja, yang dicabut adalah butir SYSTEM-nya.

   GERBANG RUTENYA IKUT DIPERLUAS DI KODE

   `routes/SYSTEM/Organization/RelasiOrganisasi.php` kini menerima dua kode halaman:
   `system.admin.atau:systemRelasiOrganisasiPage,PaMasterRelasiOrganisasiPage`. Tanpa itu,
   butir menunya tampil tetapi diklik menghasilkan 403 — menu yang mengajak lalu menolak.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi berada dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT.
   `GO` mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50500, 'DML-2026-099 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

/* Prasyarat: header Master Data milik KPI harus sudah ada. Kalau tidak, butirnya akan
   melahirkan header baru yang tampil sendirian di tempat yang tidak diduga. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Header_Code = 'pa_master')
    THROW 50501, 'DML-2026-099 membutuhkan header pa_master (DML-2026-093 dan sesudahnya).', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Butir menu baru di header Master Data milik modul KPI            *
 * ------------------------------------------------------------------ */

/* Nilai header DIBACA dari baris yang sudah ada, tidak dikarang. Satu Header_Code yang
   berjalan pada dua Sort_Header membuat grupnya tampil DUA KALI di sidebar dengan nama sama
   dan isi berbeda, tanpa satu pun galat. Cacat itu pernah terjadi di DML-2026-094. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterRelasiOrganisasiPage')
BEGIN
    INSERT INTO N_HRIS_Menus (
        Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
        Header_Code, Header_Label, Header_Icon, Sort_Header,
        Is_Global_Menu, Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle
    )
    SELECT TOP 1
        'PaMasterRelasiOrganisasiPage',
        acuan.Kode_Perusahaan,
        'Relasi Organisasi',
        'people-fill',
        '/system/organisasi/relasi',
        acuan.Header_Code,
        acuan.Header_Label,
        acuan.Header_Icon,
        acuan.Sort_Header,
        'T',
        /* Sesudah butir terakhir yang ada, bukan angka tetap: plafon yang dikarang akan
           menabrak urutan kembar begitu ada butir baru lain di header ini. */
        (SELECT MAX(Sort_Menu) + 1 FROM N_HRIS_Menus WHERE Header_Code = 'pa_master'),
        'self',
        'Y',
        'Siapa atasan, bawahan, dan rekan kerja siapa. Sumber calon penilai Performance Appraisal.'
    FROM N_HRIS_Menus AS acuan
    WHERE acuan.Header_Code = 'pa_master'
    ORDER BY acuan.Sort_Menu;
END

/* ------------------------------------------------------------------ *
 * 2. Pemetaan ke grup fitur KPI                                       *
 * ------------------------------------------------------------------ */

/* Grup dan sifat barisnya ditiru dari butir pa_master yang sudah ada, supaya butir baru
   berperilaku sama dengan tetangganya. */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaMasterRelasiOrganisasiPage'
)
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map (Jenis_page, Id_Grup_Fitur, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT TOP 1
        'PaMasterRelasiOrganisasiPage',
        g.Id_Grup_Fitur,
        'T',
        'Y',
        'Y'
    FROM N_HRIS_Grup_Fitur_Map AS g
    WHERE g.Jenis_page = 'PaMasterIkatanPaketSoalPage' AND g.Flag_Aktif = 'Y';
END

/* ------------------------------------------------------------------ *
 * 3. Penjaga                                                          *
 * ------------------------------------------------------------------ */

DECLARE @menu int = (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterRelasiOrganisasiPage');
DECLARE @map  int = (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaMasterRelasiOrganisasiPage' AND Flag_Aktif = 'Y');

IF @menu <> 1 THROW 50502, 'Butir menu PA untuk relasi organisasi tidak terbentuk tunggal.', 1;
IF @map  <> 1 THROW 50503, 'Pemetaan grup fitur tidak terbentuk tunggal; menunya tidak akan muncul.', 1;

/* Harus masuk ke grup KPI, bukan grup lain: kalau acuannya bergeser, butirnya akan muncul di
   modul yang sama sekali lain dan tampak seperti menu yang hilang. */
IF NOT EXISTS (
    SELECT 1
    FROM N_HRIS_Grup_Fitur_Map AS gfm
    JOIN N_HRIS_Master_Grup_Fitur AS gf ON gf.Id_Grup_Fitur = gfm.Id_Grup_Fitur
    WHERE gfm.Jenis_page = 'PaMasterRelasiOrganisasiPage' AND gf.Kode_Grup_Fitur = 'KPI'
)
    THROW 50504, 'Butir baru tidak terpetakan ke grup fitur KPI.', 1;

/* Satu Header_Code tidak boleh berjalan pada dua Sort_Header. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'pa_master'
    GROUP BY Kode_Perusahaan, Header_Code
    HAVING COUNT(DISTINCT Sort_Header) > 1
)
    THROW 50505, 'Header pa_master terbelah pada dua Sort_Header.', 1;

/* Tidak boleh ada urutan kembar dalam satu header: dua butir pada posisi sama tampil dengan
   urutan yang berubah-ubah antar permintaan. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'pa_master'
    GROUP BY Kode_Perusahaan, Sort_Menu
    HAVING COUNT(*) > 1
)
    THROW 50506, 'Ada urutan menu kembar di header pa_master.', 1;

/* Tidak boleh global: halaman ini bukan untuk semua karyawan, dan satu baris global membuat
   seluruh karyawan melihat menu yang mereka tidak bisa buka. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Jenis_page = 'PaMasterRelasiOrganisasiPage' AND Is_Global_Menu = 'Y'
)
    THROW 50507, 'Butir baru ditandai global; seharusnya tidak.', 1;

COMMIT TRANSACTION;
GO

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache().

   Dan WAJIB dipasangkan dengan perubahan kode pada gerbang rutenya
   (`system.admin.atau:systemRelasiOrganisasiPage,PaMasterRelasiOrganisasiPage`). Menjalankan
   DML ini tanpa perubahan kode itu menghasilkan menu yang tampil lalu menolak dengan 403. */
