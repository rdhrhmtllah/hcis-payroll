/* Change ID: DML-2026-105-menu-penilaian-tim-leader
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-093 dan DML-2026-101. WAJIB dijalankan SESUDAH keduanya —
                 DML-2026-093 MENGHAPUS baris yang skrip ini pasang kembali, jadi urutan
                 yang terbalik membuat perubahan ini lenyap tanpa satu pun galat.
   Scope: N_HRIS_Menus dan N_HRIS_Grup_Fitur_Map — masing-masing SATU baris.

   YANG DISELESAIKAN

   Halaman `PerformanceAppraisalLeaderPage` (/performance-appraisal/tim) tidak bisa dicapai
   siapa pun. Ia bukan sekadar tak punya butir menu: `check.jabatan` menolak siapa saja yang
   mengetik alamatnya, karena kuncinya nol baris di ketiga tempat —

       N_HRIS_Menus              0
       N_HRIS_Grup_Fitur_Map     0
       HRIS_Page_Access          0

   INI PEMULIHAN, BUKAN PENDAFTARAN YANG TERLUPA

   Barisnya pernah ada. `DML-2026-089` memasangnya 2 September 2026, lalu `DML-2026-093`
   mencabutnya 3 September 2026 — sengaja, atas keputusan pemilik proyek, karena mockup
   yang dipakai hanya mengenal dua peran: Peserta dan Administrator. Halaman dan
   endpointnya tidak pernah dihapus; yang dicabut hanya barisnya dari sidebar.

   Skrip ini membalikkan pencabutan itu, juga atas keputusan pemilik proyek (6 September
   2026), sesudah halaman Leader disepakati sebagai DASHBOARD yang hanya menampilkan hasil
   yang sudah diterbitkan — peran ketiga yang tidak ada di mockup lama.

   Karena itu urutannya mengikat: `DML-2026-093` menghapus tanpa syarat
   (`DELETE ... WHERE Jenis_page = 'PerformanceAppraisalLeaderPage'`), sehingga menjalankan
   yang ini lebih dulu berarti pekerjaannya dibatalkan diam-diam.

   Halamannya sendiri sudah jadi dan sudah berpenjaga: `PaLeaderService` hanya menampilkan
   angka untuk hasil ber-status TERBIT, tidak pernah menyingkap nilai per penilai, dan
   menyembunyikan rincian per kategori bila pengisinya kurang dari tiga orang. Yang kurang
   hanya pintunya.

   KENAPA GLOBAL, BUKAN DIBERIKAN SATU PER SATU

   Akses di HCIS bergranularitas nol: role menunjuk GRUP FITUR, dan grup fitur menjangkau
   SELURUH halaman di dalamnya. "Leader" bukan sebuah role melainkan keadaan — siapa saja
   yang kebetulan punya bawahan. Memetakannya ke role KPI justru memberikannya kepada admin
   HC, bukan kepada para atasan.

   Sisa pilihannya dua: menandainya global, atau menuliskan pemiliknya satu per satu di
   `HRIS_Page_Access`. Yang kedua terdengar lebih rapi dan justru lebih berbahaya: daftarnya
   menua diam-diam. Orang yang baru diangkat menjadi atasan tidak akan mengeluh halamannya
   hilang — ia hanya tidak akan pernah membukanya, dan tidak ada yang tahu. Itu kekeliruan
   yang sama yang membuat `PerformanceAppraisalSayaPage` ditandai global.

   Yang ditanggung global: karyawan tanpa bawahan melihat satu butir menu tambahan. Halaman
   itu menyambutnya dengan keterangan yang sudah ada di layar ("Anda belum memiliki scope
   tim"), bukan dengan galat maupun layar kosong. Data orang lain tetap tidak terlihat —
   penjaganya keanggotaan di `PaLeaderService::detailAnggota()`, bukan gerbang rutenya.

   HEADER DIAMBIL, BUKAN DITULIS

   Header dan Sort_Header disalin dari baris `PerformanceAppraisalSayaPage`. Menebak angkanya
   membelah satu Header_Code pada dua Sort_Header, dan grupnya tampil DUA KALI di sidebar
   dengan nama sama dan isi berbeda — tanpa satu pun galat. Kekeliruan itu pernah terjadi
   pada menu PA, dan penjaga di bawah menolak bila terulang.

   IDEMPOTEN

   Kedua sisipan dijaga NOT EXISTS pada kunci halamannya.

   CARA MEMBATALKAN

       DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';
       DELETE FROM N_HRIS_Menus          WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';

   Keduanya aman: tidak ada baris lain yang menunjuk kunci ini, dan halamannya kembali ke
   keadaan semula — ada tetapi tak tercapai.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT. `GO`
   mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan.

   Sesudah dijalankan, cache menu WAJIB dibersihkan
   (`InertiaShellService::flushMenuCache()`), kalau tidak susunan lama tetap tersaji sampai
   cache-nya kedaluwarsa sendiri. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50515, 'DML-2026-105 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Butir menu                                                       *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalLeaderPage')
BEGIN
    INSERT INTO N_HRIS_Menus (
        Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
        Header_Code, Header_Label, Header_Icon, Sort_Header,
        Is_Global_Menu, Sort_Menu, Is_Show_Sidebar,
        Active_Match_Pattern, Route_Name, Target, Menu_Subtitle
    )
    SELECT
        'PerformanceAppraisalLeaderPage',
        m.Kode_Perusahaan,
        'Penilaian Tim',
        'bi-people-fill',
        '/performance-appraisal/tim',
        /* Header DIAMBIL dari baris acuan, tidak ditulis angka — lihat catatan di atas. */
        m.Header_Code,
        m.Header_Label,
        m.Header_Icon,
        m.Sort_Header,
        'Y',
        (SELECT MAX(Sort_Menu) + 1 FROM N_HRIS_Menus WHERE Header_Code = m.Header_Code),
        'Y',
        NULL,
        NULL,
        'self',
        'Hasil penilaian anggota tim Anda yang sudah diterbitkan'
    FROM N_HRIS_Menus m
    WHERE m.Jenis_page = 'PerformanceAppraisalSayaPage';
END;

/* ------------------------------------------------------------------ *
 * 2. Pemetaan grup fitur                                              *
 * ------------------------------------------------------------------ *
 * Tanpa baris ini halaman tidak punya grup, dan resolusi menu melewatinya diam-diam.
 * Grupnya DIAMBIL dari halaman "Penilaian Saya", bukan ditulis angka: id grup berbeda
 * antara dev dan production, dan angka yang salah menaruh halaman ini di modul lain.  */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PerformanceAppraisalLeaderPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map (Jenis_page, Id_Grup_Fitur, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT DISTINCT 'PerformanceAppraisalLeaderPage', g.Id_Grup_Fitur, 'T', 'Y', 'Y'
    FROM N_HRIS_Grup_Fitur_Map g
    WHERE g.Jenis_page = 'PerformanceAppraisalSayaPage' AND g.Flag_Aktif = 'Y';
END;

/* ------------------------------------------------------------------ *
 * 3. Penjaga                                                          *
 * ------------------------------------------------------------------ */

DECLARE @menu int = (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalLeaderPage');
DECLARE @map  int = (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PerformanceAppraisalLeaderPage' AND Flag_Aktif = 'Y');

IF @menu < 1 THROW 50516, 'Butir menu Penilaian Tim tidak terbentuk; baris acuan Penilaian Saya mungkin tidak ada.', 1;
IF @map  < 1 THROW 50517, 'Pemetaan grup fitur tidak terbentuk; halaman tidak akan muncul di menu.', 1;

/* Tanpa ini halaman tetap tak tercapai walau menunya terlihat: `check.jabatan` hanya
   melewatkan halaman global, halaman ber-role, atau halaman yang ditulis di
   HRIS_Page_Access — dan tak satu pun dari dua yang belakangan berlaku di sini. */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Jenis_page = 'PerformanceAppraisalLeaderPage' AND Is_Global_Menu = 'Y'
)
    THROW 50518, 'Halaman Penilaian Tim tidak ditandai global; ia akan tetap ditolak check.jabatan.', 1;

/* Satu Header_Code tidak boleh berjalan pada dua Sort_Header: grupnya akan tampil DUA KALI
   di sidebar dengan nama sama dan isi berbeda, tanpa satu pun galat. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'performance_appraisal'
    GROUP BY Kode_Perusahaan, Header_Code
    HAVING COUNT(DISTINCT Sort_Header) > 1
)
    THROW 50519, 'Header performance_appraisal terbelah pada dua Sort_Header.', 1;

/* Tidak boleh ada urutan kembar dalam satu header. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'performance_appraisal'
    GROUP BY Kode_Perusahaan, Sort_Menu
    HAVING COUNT(*) > 1
)
    THROW 50520, 'Ada urutan menu kembar di header Performance Appraisal.', 1;

COMMIT TRANSACTION;
GO

/* VERIFIKASI — harus mengembalikan dua baris "Penilaian Saya" lalu "Penilaian Tim",
   keduanya Is_Global_Menu = 'Y' pada header yang sama. */
SELECT Jenis_page, Nama_Menu, Url_Menu, Header_Code, Sort_Header, Sort_Menu, Is_Global_Menu
FROM N_HRIS_Menus
WHERE Header_Code = 'performance_appraisal'
ORDER BY Sort_Menu;

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(). */
