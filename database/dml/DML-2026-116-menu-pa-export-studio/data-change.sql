/* Change ID: DML-2026-116-menu-pa-export-studio
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: tidak ada DML lain. Bergantung pada KODE yang sudah ada di worktree ini —
                 `PaExportStudioController` dan route `pa.export-studio`
                 (routes/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php),
                 keduanya sudah digerbangi `check.jabatan:PaExportStudioPage`. Tanpa TIGA
                 baris di skrip ini gerbang itu menolak semua orang: `check.jabatan` hanya
                 meloloskan halaman global, halaman ber-role, atau halaman yang tercatat di
                 `HRIS_Page_Access` — dan `PaExportStudioPage` belum satu pun dari ketiganya.
   Scope: N_HRIS_Menus, N_HRIS_Grup_Fitur_Map, HRIS_Page_Access — masing-masing SATU baris
          (N_HRIS_Menus, N_HRIS_Grup_Fitur_Map) atau dua baris (HRIS_Page_Access).

   YANG DISELESAIKAN

   Halaman Export Studio Performance Appraisal (`/performance-appraisal/export-studio`,
   `Jenis_page = 'PaExportStudioPage'`) sudah punya controller, route, dan halaman Vue, tetapi
   tidak muncul di sidebar dan tidak bisa dibuka siapa pun:

       N_HRIS_Menus              0
       N_HRIS_Grup_Fitur_Map     0
       HRIS_Page_Access          0

   KENAPA TIGA BARIS, DAN KENAPA GRUP FITUR MAP TIDAK BOLEH TERLEWAT

   1. N_HRIS_Menus   -- supaya menunya ada di sidebar Administrator PA.
   2. N_HRIS_Grup_Fitur_Map -- TANPA baris ini menu tidak pernah muncul di sidebar walau
      hak aksesnya sudah benar: resolusi menu di `InertiaShellService` menyaring lewat
      pemetaan grup fitur lebih dulu, sebelum hak per halaman diperiksa sama sekali.
   3. HRIS_Page_Access -- supaya peran admin PA benar-benar berhak membukanya. Halaman ini
      TIDAK ditandai global (`Is_Global_Menu = 'T'`), sama seperti seluruh baris lain pada
      header `administrator_pa` -- lihat verifikasi di bawah.

   ID GRUP FITUR: DIAMBIL, BUKAN DITULIS ANGKA

   Ditulis angka `2` di sini akan lolos hari ini (sudah diverifikasi langsung dari
   `N_HRIS_Master_Grup_Fitur` bahwa `Id_Grup_Fitur = 2` = "Key Performance Indicator" adalah
   grup KPI yang aktif), tetapi angka yang ditulis tangan tidak ikut bila id itu berbeda di
   basis data lain. Baris ini DIAMBIL dari pemetaan `PaHasilPage` yang sudah berjalan --
   Id_Grup_Map 248, Id_Grup_Fitur 2, Is_Default_Landing 'T', Is_Show_Sidebar 'Y',
   Flag_Aktif 'Y' -- persis pola setiap halaman administrator_pa lain.

   PENERIMA HRIS_Page_Access: DITURUNKAN, BUKAN DIDAFTAR

   Id pengguna berbeda antara dev dan produksi; menulis `UserID_Web = 622` di sini akan
   memberi akses ke orang yang sama sekali lain di produksi tanpa satu pun galat yang
   menampakkannya. Karena itu penerimanya diturunkan dari SIAPA PUN yang hari ini sudah
   memegang `PaHasilPage` secara eksplisit dan aktif -- pemilik hasil penilaian, yang juga
   wajib bisa mengekspornya. Pada development per hari skrip ini ditulis (2026-09-09) itu
   dua orang: UserID_Web 622 dan 4. Kolom penyertanya (`Kode_Perusahaan`, `ID_Level`,
   `ID_Divisi`) DISALIN dari baris sumber, bukan ditebak: kolom itu tidak boleh NULL dan ikut
   dibaca jalur legacy, dan nilai yang ditebak menghasilkan baris yang tersimpan rapi tetapi
   tidak pernah cocok saat dibaca.

   KENAPA TIDAK DIPETAKAN KE GRUP FITUR SEBAGAI SATU-SATUNYA KUNCI HAK

   Header `administrator_pa` seluruhnya memetakan ke grup KPI yang sama (`Id_Grup_Fitur = 2`,
   38 halaman lain), tetapi hak buka tiap halamannya TETAP diperiksa lewat
   `HRIS_Page_Access` per Jenis_page, bukan lewat kepemilikan grup semata -- itulah sebabnya
   `PaHasilPage` dkk hanya dipegang 2 orang walau puluhan orang memegang role grup KPI.
   Export Studio mengikuti pola yang sama persis: baris Grup_Fitur_Map membuat menunya
   MUNCUL, baris HRIS_Page_Access yang membuatnya bisa DIBUKA.

   IDEMPOTEN

   Ketiga sisipan dijaga NOT EXISTS pada kunci halamannya (menu, map) atau kombinasi
   penerima (page access). Untuk HRIS_Page_Access, "idempoten" berarti MEMBERI SEKALI:
   NOT EXISTS memeriksa keberadaan barisnya, aktif atau tidak -- bukan hanya yang aktif.
   Aplikasi mencabut akses dengan mengubah Flag_Aktif menjadi 'T' (`PageAccessController`),
   bukan menghapus barisnya; menyaring NOT EXISTS pada Flag_Aktif='Y' akan membuat
   menjalankan ulang skrip ini (mis. replay SQL Tracker ke lingkungan lain) menghidupkan
   kembali akses yang sudah sengaja dicabut, tanpa galat maupun jejak yang terlihat.
   Mengaktifkan kembali baris yang dinonaktifkan BUKAN pekerjaan skrip ini -- itu keputusan
   admin lewat UPDATE eksplisit di halaman Page Access.

   CARA MEMBATALKAN

       DELETE FROM HRIS_Page_Access     WHERE Jenis_page = 'PaExportStudioPage';
       DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaExportStudioPage';
       DELETE FROM N_HRIS_Menus          WHERE Jenis_page = 'PaExportStudioPage';

   Aman: tidak ada baris lain yang menunjuk kunci ini.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi dalam SATU batch -- tidak ada `GO` antara BEGIN dan COMMIT. `GO`
   mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan.

   Sesudah dijalankan, cache menu WAJIB dibersihkan
   (`InertiaShellService::flushMenuCache()`), kalau tidak susunan lama tetap tersaji sampai
   cache-nya kedaluwarsa sendiri. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50580, 'DML-2026-116 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

/* Prasyarat: baris acuan (PaHasilPage) harus ada di kedua tabel yang darinya nilai ini
   diturunkan. Tanpa itu skrip ini tidak tahu grup fitur atau penerima mana yang benar. */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaHasilPage' AND Flag_Aktif = 'Y'
)
    THROW 50581, 'DML-2026-116 membutuhkan pemetaan grup fitur PaHasilPage sebagai acuan.', 1;

IF NOT EXISTS (
    SELECT 1 FROM HRIS_Page_Access WHERE Jenis_page = 'PaHasilPage' AND Flag_Aktif = 'Y'
)
    THROW 50582, 'DML-2026-116 membutuhkan sekurangnya satu pemegang PaHasilPage sebagai acuan penerima.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Butir menu                                                       *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaExportStudioPage')
BEGIN
    INSERT INTO N_HRIS_Menus (
        Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
        Header_Code, Header_Label, Header_Icon, Sort_Header,
        Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target,
        Is_Show_Sidebar, Menu_Subtitle
    )
    SELECT
        'PaExportStudioPage',
        m.Kode_Perusahaan,
        'Export Studio',
        /* Icon senada dengan Export Studio SOP dan Visitor -- keduanya memakai 'bi-sliders'
           sebagai bahasa ikon "rakit laporan sendiri" lintas modul di HCIS. */
        'bi-sliders',
        '/performance-appraisal/export-studio',
        /* Header DIAMBIL dari baris acuan, tidak ditulis angka -- menebaknya membelah satu
           Header_Code pada dua Sort_Header, dan grupnya tampil DUA KALI di sidebar dengan
           nama sama dan isi berbeda, tanpa satu pun galat. */
        m.Header_Code,
        m.Header_Label,
        m.Header_Icon,
        m.Sort_Header,
        'T',
        (SELECT MAX(TRY_CAST(Sort_Menu AS int)) + 1 FROM N_HRIS_Menus WHERE Header_Code = m.Header_Code),
        NULL,
        'pa.export-studio',
        'self',
        'Y',
        'Rakit dan unduh laporan Performance Appraisal sendiri'
    FROM N_HRIS_Menus m
    WHERE m.Jenis_page = 'PaHasilPage';
END;

/* ------------------------------------------------------------------ *
 * 2. Pemetaan grup fitur                                              *
 * ------------------------------------------------------------------ *
 * Tanpa baris ini halaman tidak punya grup, dan resolusi menu melewatinya diam-diam --
 * ia tidak pernah muncul di sidebar walau HRIS_Page_Access di bawah sudah benar.
 * Grupnya DIAMBIL dari pemetaan PaHasilPage, bukan ditulis angka: id grup bisa berbeda
 * antara dev dan production, dan angka yang salah menaruh halaman ini di modul lain. */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaExportStudioPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT DISTINCT g.Id_Grup_Fitur, 'PaExportStudioPage', 'T', 'Y', 'Y'
    FROM N_HRIS_Grup_Fitur_Map g
    WHERE g.Jenis_page = 'PaHasilPage' AND g.Flag_Aktif = 'Y';
END;

/* ------------------------------------------------------------------ *
 * 3. Hak akses -- diturunkan dari pemegang aktif PaHasilPage           *
 * ------------------------------------------------------------------ *
 * Pemilik hasil penilaian (PaHasilPage) adalah orang yang juga wajib bisa mengekspornya.
 *
 * IDEMPOTEN ARTINYA "BERI SEKALI", BUKAN "PASTIKAN SELALU AKTIF". NOT EXISTS di bawah
 * SENGAJA tidak menyaring Flag_Aktif -- ia menyaring keberadaan barisnya, aktif atau
 * tidak. Aplikasi mencabut akses dengan MENGUBAH Flag_Aktif menjadi 'T'
 * (`PageAccessController`), bukan menghapus barisnya. Kalau NOT EXISTS di sini hanya
 * menyaring baris aktif, skenario berikut lolos tanpa galat maupun jejak:
 *
 *   1. Skrip dijalankan -> baris Flag_Aktif='Y' tersisip untuk seseorang.
 *   2. Admin mencabut aksesnya lewat halaman Page Access -> Flag_Aktif berubah jadi 'T'.
 *   3. Skrip dijalankan ULANG (replay lewat SQL Tracker, atau ke lingkungan lain) ->
 *      NOT EXISTS yang menyaring aktif tidak menemukan baris aktif -> menyisipkan baris
 *      BARU ber-Flag_Aktif='Y' -> pencabutan yang disengaja HIDUP KEMBALI.
 *
 * Mengikuti pola DML-2026-026 (register-visitor-export-studio-menu): NOT EXISTS memeriksa
 * SELURUH baris pada kombinasi (UserID_Web, ID_Level, ID_Divisi), bukan hanya yang aktif.
 * Begitu barisnya pernah ada -- aktif maupun sudah dicabut -- skrip ini diam. Mengaktifkan
 * kembali baris yang sengaja dinonaktifkan BUKAN pekerjaan skrip ini; itu keputusan admin,
 * dan kalau memang dikehendaki harus berupa UPDATE eksplisit yang terlihat niatnya, bukan
 * efek samping dari menjalankan ulang DML pendaftaran. */

INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT
    sumber.Kode_Perusahaan,
    sumber.ID_Level,
    sumber.ID_Divisi,
    'PaExportStudioPage',
    sumber.UserID_Web,
    sumber.Urutan_Menu,
    'Y',
    SYSDATETIME()
FROM HRIS_Page_Access sumber
WHERE sumber.Jenis_page = 'PaHasilPage'
  AND sumber.Flag_Aktif = 'Y'
  AND sumber.UserID_Web IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access ada
      WHERE ada.Jenis_page = 'PaExportStudioPage'
        AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
        AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
        AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
  );

/* ------------------------------------------------------------------ *
 * 4. Penjaga                                                          *
 * ------------------------------------------------------------------ */

DECLARE @menu int = (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'PaExportStudioPage');
DECLARE @map  int = (SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaExportStudioPage' AND Flag_Aktif = 'Y');

IF @menu < 1 THROW 50583, 'Butir menu Export Studio PA tidak terbentuk; baris acuan PaHasilPage mungkin tidak ada.', 1;
IF @map  < 1 THROW 50584, 'Pemetaan grup fitur tidak terbentuk; halaman tidak akan muncul di menu.', 1;

DECLARE @pemegangHasil int = (
    SELECT COUNT(DISTINCT UserID_Web) FROM HRIS_Page_Access
    WHERE Jenis_page = 'PaHasilPage' AND Flag_Aktif = 'Y' AND UserID_Web IS NOT NULL
);

/* SENGAJA "pernah tercatat", BUKAN "aktif sekarang". Kalau baris ini disyaratkan aktif,
   menjalankan ulang skrip sesudah seorang admin men-nonaktifkan PaExportStudioPage-nya
   sendiri (Flag_Aktif='T', lewat PageAccessController -- tidak dihapus) akan meledak
   dengan THROW 50586 walau tidak ada yang salah: pencabutan itu memang harus bertahan.
   Yang diperiksa di sini murni "apakah langkah 3 pernah menyalin baris untuk orang ini",
   supaya tetap sinkron dengan idempotensi INSERT di atas yang juga tidak menyaring
   Flag_Aktif. */
DECLARE @tercatatStudio int = (
    SELECT COUNT(DISTINCT h.UserID_Web)
    FROM HRIS_Page_Access h
    WHERE h.Jenis_page = 'PaHasilPage' AND h.Flag_Aktif = 'Y' AND h.UserID_Web IS NOT NULL
      AND EXISTS (
          SELECT 1 FROM HRIS_Page_Access s
          WHERE s.Jenis_page = 'PaExportStudioPage' AND s.UserID_Web = h.UserID_Web
      )
);

/* Kalau tidak ada satu pun pemegang PaHasilPage eksplisit, skripnya tidak berbuat apa-apa
   pada langkah 3 -- dan itu HARUS terdengar, bukan lewat sebagai sukses senyap. */
IF @pemegangHasil = 0
    THROW 50585, 'Tidak ada pemegang PaHasilPage eksplisit; tidak ada yang bisa diberi akses Export Studio.', 1;

IF @tercatatStudio < @pemegangHasil
    THROW 50586, 'Ada pemegang PaHasilPage aktif yang belum pernah tercatat di HRIS_Page_Access untuk PaExportStudioPage; ada yang tidak tersalin.', 1;

/* Halaman ini SENGAJA tidak global -- kalau baris menu berubah jadi global secara tidak
   sengaja di masa depan, baris HRIS_Page_Access di atas jadi mubazir tanpa kelihatan. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Jenis_page = 'PaExportStudioPage' AND Is_Global_Menu = 'Y'
)
    THROW 50587, 'Halaman Export Studio PA tertanda global; ini menyalahi pola seluruh halaman administrator_pa lain.', 1;

/* Satu Header_Code tidak boleh berjalan pada dua Sort_Header: grupnya akan tampil DUA KALI
   di sidebar dengan nama sama dan isi berbeda, tanpa satu pun galat. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa'
    GROUP BY Kode_Perusahaan, Header_Code
    HAVING COUNT(DISTINCT Sort_Header) > 1
)
    THROW 50588, 'Header administrator_pa terbelah pada dua Sort_Header.', 1;

/* Tidak boleh ada urutan kembar di antara menu yang TAMPIL di sidebar dalam satu header --
   baris yang sengaja disembunyikan (Is_Show_Sidebar = 'T') boleh berbagi nomor lama. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa' AND Is_Show_Sidebar = 'Y'
    GROUP BY Kode_Perusahaan, Sort_Menu
    HAVING COUNT(*) > 1
)
    THROW 50589, 'Ada urutan menu kembar di antara menu yang tampil pada header Administrator PA.', 1;

/* Tidak boleh ada baris hak akses kembar AKTIF untuk satu orang: keduanya berlaku, dan
   mencabut satu (Flag_Aktif='T') akan terlihat seperti pencabutan yang tidak bekerja
   karena baris aktif satunya tetap meloloskannya.

   SENGAJA hanya menghitung baris AKTIF, bukan seluruh riwayat baris. Riwayat "satu baris
   lama ber-Flag_Aktif='T' plus satu baris baru ber-Flag_Aktif='Y' untuk kombinasi
   Level/Divisi yang berbeda" itu wajar (mis. orangnya pindah level/divisi lalu diberi
   baris baru) dan BUKAN cacat -- menghitungnya sebagai kembar akan menolak keadaan yang
   sah setiap kali skrip lain menyalin ulang dari sumber yang sudah punya riwayat begitu. */
IF EXISTS (
    SELECT 1 FROM HRIS_Page_Access
    WHERE Jenis_page = 'PaExportStudioPage' AND Flag_Aktif = 'Y'
    GROUP BY UserID_Web
    HAVING COUNT(*) > 1
)
    THROW 50590, 'Ada baris hak akses kembar untuk PaExportStudioPage.', 1;

COMMIT TRANSACTION;
GO

/* VERIFIKASI -- baris menu baru, beserta hak aksesnya. */
SELECT Jenis_page, Nama_Menu, Url_Menu, Header_Code, Sort_Header, Sort_Menu, Is_Global_Menu, Route_Name
FROM N_HRIS_Menus
WHERE Jenis_page = 'PaExportStudioPage';

SELECT Jenis_page, UserID_Web, Kode_Perusahaan, ID_Level, ID_Divisi, Flag_Aktif
FROM HRIS_Page_Access
WHERE Jenis_page = 'PaExportStudioPage'
ORDER BY UserID_Web;

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache(), lalu `php artisan route:clear`.

   Tanpa itu, menu yang sudah ter-cache tetap tidak memuat butirnya, dan pemegang hak yang
   sudah aktif akan melaporkan "menunya tidak ada" atas hak yang sebenarnya sudah benar. */
