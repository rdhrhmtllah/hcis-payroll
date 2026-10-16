/* Change ID: DML-2026-094-pa-master-global-keluar-dari-konfigurasi-cycle
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-093 (grup menu PA). Jalankan sesudahnya.
   Scope: N_HRIS_Menus baris PA saja. Tidak menyentuh hak akses sama sekali.

   MASALAH YANG DISELESAIKAN

   Grup "Konfigurasi Cycle" memuat dua butir yang namanya berjanji setelan per siklus,
   tetapi alamatnya menunjuk MASTER GLOBAL — sama untuk semua siklus:

       Bobot Penilaian  ->  /performance-appraisal/master/aturan-penilai
       Pengaturan KPI   ->  /performance-appraisal/master/bobot-pilar

   Namanya bahkan tertukar dengan alamatnya. Admin yang membuka "Bobot Penilaian" untuk
   menyetel siklus 2026 sebenarnya sedang menyunting cetakan yang dipakai SELURUH siklus,
   termasuk yang sudah berjalan — dan tidak ada apa pun di layar yang mengatakan itu.

   PENYELESAIANNYA

   Setelan per siklus kini punya tempatnya sendiri: halaman penyiapan berlangkah di
   `/performance-appraisal/siapkan/{id}`, yang menyunting SALINAN milik siklus. Dua butir
   ini dikembalikan ke sifat aslinya — master global — dan dipindahkan ke grup Master Data
   dengan nama yang menyebut apa adanya.

   TIDAK ADA HALAMAN YANG DIHAPUS. Keduanya tetap dibutuhkan: salinan siklus lahir dari
   master ini, dan siklus baru tidak punya sumber salinan kalau masternya hilang.

   TIDAK ADA HAK YANG BERUBAH. `HRIS_Page_Access` tidak disentuh; yang berubah hanya
   header, nama, dan urutan.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi berada dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT.
   Pemisah `GO` mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke
   batch berikutnya: ia digulung balik, lalu sisa skripnya berjalan TANPA perlindungan.
   Kegagalan di tengah akan meninggalkan menu setengah berubah. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50440, 'DML-2026-094 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Dua master global keluar dari Konfigurasi Cycle                  *
 * ------------------------------------------------------------------ */

UPDATE N_HRIS_Menus
SET Header_Code = 'pa_master',
    Header_Label = 'Master Data',
    Sort_Header = 14,
    Nama_Menu = 'Aturan Penilai (Master)',
    Menu_Subtitle = 'Cetakan yang disalin siklus baru. Menyuntingnya di sini berlaku untuk siklus berikutnya.',
    Sort_Menu = 5
WHERE Url_Menu = '/performance-appraisal/master/aturan-penilai';

UPDATE N_HRIS_Menus
SET Header_Code = 'pa_master',
    Header_Label = 'Master Data',
    Sort_Header = 14,
    Nama_Menu = 'Bobot Pilar (Master)',
    Menu_Subtitle = 'Pembagian KPI lawan Kompetensi bawaan, per level. Salinan siklus lahir dari sini.',
    Sort_Menu = 6
WHERE Url_Menu = '/performance-appraisal/master/bobot-pilar';

/* ------------------------------------------------------------------ *
 * 2. Konfigurasi Cycle dirapatkan tanpa lubang                        *
 * ------------------------------------------------------------------ *
 * Lubang pada urutan membuat penyisipan berikutnya menebak, dan tebakan yang meleset
 * menaruh butir baru di tengah alur yang urutannya bermakna.                            */

UPDATE N_HRIS_Menus SET Sort_Menu = 1 WHERE Url_Menu = '/performance-appraisal';
UPDATE N_HRIS_Menus SET Sort_Menu = 2 WHERE Url_Menu = '/performance-appraisal/penilai';

/* ------------------------------------------------------------------ *
 * 3. Penjaga                                                          *
 * ------------------------------------------------------------------ */

DECLARE @konfigurasi int = (
    SELECT COUNT(*) FROM N_HRIS_Menus
    WHERE Header_Code = 'pa_konfigurasi' AND Url_Menu LIKE '/performance-appraisal%'
);

DECLARE @master int = (
    SELECT COUNT(*) FROM N_HRIS_Menus
    WHERE Header_Code = 'pa_master' AND Url_Menu LIKE '/performance-appraisal%'
);

/* Konfigurasi Cycle harus tersisa TEPAT dua: daftar siklus dan assignment penilai.
   Bobot dan paket soal kini langkah di dalam penyiapan, bukan butir menu. */
IF @konfigurasi <> 2
BEGIN
    DECLARE @p1 nvarchar(200) = CONCAT('Konfigurasi Cycle berisi ', @konfigurasi, ' butir, seharusnya 2.');
    THROW 50441, @p1, 1;
END;

/* Master Data harus menjadi enam: empat yang lama ditambah dua yang pindah. */
IF @master <> 6
BEGIN
    DECLARE @p2 nvarchar(200) = CONCAT('Master Data berisi ', @master, ' butir, seharusnya 6.');
    THROW 50442, @p2, 1;
END;

/* Satu Header_Code tidak boleh berjalan pada DUA Sort_Header. Grup yang selnya terbelah
   akan tampil DUA KALI di sidebar dengan nama yang sama dan isi yang berbeda-beda, dan
   tidak ada satu pun galat yang muncul. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Url_Menu LIKE '/performance-appraisal%'
    GROUP BY Kode_Perusahaan, Header_Code
    HAVING COUNT(DISTINCT Sort_Header) > 1
)
    THROW 50445, 'Ada header PA yang terbelah pada dua Sort_Header; grupnya akan tampil dua kali.', 1;

/* Tidak boleh ada urutan kembar di dalam satu header. Kembar membuat urutan tampilnya
   ditentukan database, dan alur yang urutannya bermakna berubah tanpa ada yang mengubahnya. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Url_Menu LIKE '/performance-appraisal%'
    GROUP BY Header_Code, Sort_Menu
    HAVING COUNT(*) > 1
)
    THROW 50443, 'Ada urutan menu kembar di dalam satu header PA.', 1;

/* Tidak boleh ada halaman admin yang global. Satu baris global membuat 958 karyawan
   melihat menu yang hanya bisa dibuka dua orang. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Url_Menu LIKE '/performance-appraisal%'
      AND Url_Menu <> '/performance-appraisal/saya'
      AND Is_Global_Menu = 'Y'
)
    THROW 50444, 'Ada halaman admin PA yang ditandai global.', 1;

COMMIT TRANSACTION;
GO

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache().
   Menu di-cache per perusahaan; tanpa pembersihan, perubahan ini tidak terlihat sampai
   cache-nya kedaluwarsa sendiri. */
