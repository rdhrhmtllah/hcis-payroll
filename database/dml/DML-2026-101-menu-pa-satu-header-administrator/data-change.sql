/* Change ID: DML-2026-101-menu-pa-satu-header-administrator
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-100 (butir Assignment Penilai dinonaktifkan). Jalankan sesudahnya.
   Scope: N_HRIS_Menus — hanya baris ber-Url_Menu diawali '/performance-appraisal'.

   YANG DISELESAIKAN

   Menu Performance Appraisal tersebar di LIMA header dengan empat Sort_Header berbeda:

       sort=6   performance_appraisal   Penilaian Saya
       sort=11  pa_ringkasan            Dashboard Admin, Monitoring Progress
       sort=12  pa_konfigurasi          Assessment Cycle, Assignment Penilai
       sort=13  pa_hasil                Hasil, Kalibrasi, HAV Map, Distribusi, Riwayat
       sort=14  pa_master               delapan master data

   Admin yang mencari tempat memverifikasi hasil harus menebak antara "Ringkasan", "Hasil",
   dan "Konfigurasi Cycle" — tiga header yang sama masuk akalnya. Namanya pun campur dua
   bahasa: "Assessment Cycle", "Monitoring Progress", "Hasil Performance".

   Sesudah perubahan: SATU header `administrator_pa` berlabel "Administrator PA", mengikuti
   pola yang sudah dipakai modul lain (`administrator_dms`, `administrator_kpi`,
   `administrator_lms`, `administrator_visitor`). Urutannya mengikuti ALUR KERJA admin, bukan
   abjad: dashboard, siklus, pemantauan & verifikasi, kalibrasi, hasil, laporan, lalu master
   data yang disetel sekali di awal.

   KENAPA Sort_Header 11

   7-10 sudah dipakai Administrator HCIS/KPI/LMS/Visitor, dan 11-14 justru milik header PA
   yang sedang dibubarkan. Memakai 11 membuat seluruh kelompok Administrator berdampingan
   tanpa satu pun menu modul lain digeser — perubahan yang tidak menyentuh modul lain sama
   sekali lebih mudah dibatalkan dan lebih mudah ditinjau.

   "PENILAIAN SAYA" SENGAJA TIDAK IKUT PINDAH

   Baris itu (`PerformanceAppraisalSayaPage`, `Is_Global_Menu='Y'`) adalah halaman 958
   karyawan, bukan halaman admin. Memasukkannya ke header Administrator akan menyembunyikannya
   dari orang yang justru paling membutuhkannya — dan karyawan yang tidak menemukan halaman
   penilaiannya tidak akan mengeluh, ia hanya tidak akan pernah mengisi.

   YANG TIDAK DISENTUH

   `Jenis_page`, `Url_Menu`, `Is_Global_Menu`, dan seluruh baris `HRIS_Page_Access` tetap apa
   adanya. `Jenis_page` adalah kunci UNIK yang mengikat menu ke hak akses; mengubahnya berarti
   memutus akses orang yang sudah diberi hak. Yang berubah hanya PENEMPATAN dan NAMA TAMPIL.

   Tidak ada baris menu BARU. Hub "/performance-appraisal/master" sempat dipertimbangkan,
   tetapi baris baru menuntut `Jenis_page` baru plus baris `HRIS_Page_Access` — melebar ke
   RBAC, jauh dari "merapikan menu". Kedelapan butir master di bawah sudah menjangkau
   seluruh isinya.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT. `GO`
   mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan.

   Sesudah dijalankan, cache menu WAJIB dibersihkan
   (`InertiaShellService::flushMenuCache()`), kalau tidak susunan lama tetap tersaji sampai
   cache-nya kedaluwarsa sendiri. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50511, 'DML-2026-101 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Seluruh menu ADMIN pindah ke satu header                         *
 * ------------------------------------------------------------------ *
 * Disaring lewat `Jenis_page`, bukan lewat `Url_Menu LIKE '%...%'`.
 * `PerformanceAppraisalSayaPage` juga ber-URL '/performance-appraisal/saya', sehingga
 * saringan pola akan ikut menyeretnya ke header Administrator — persis yang tidak boleh
 * terjadi. Menyebut kuncinya satu per satu membuat cakupannya tidak bisa meleset. */

UPDATE N_HRIS_Menus
SET Header_Code = 'administrator_pa',
    Header_Label = 'Administrator PA',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 11
WHERE Jenis_page IN (
    'PaDashboardPage', 'PerformanceAppraisalPage', 'PaPemantauanPage', 'PaKalibrasiPage',
    'PaHasilPage', 'PaHavMapPage', 'PaDistribusiPage', 'PaRiwayatPage',
    'PaMasterJenisKompetensiPage', 'PaMasterKompetensiPage', 'PaMasterAturanPenilaiPage',
    'PaMasterBobotPilarPage', 'PaMasterPitaNilaiPage', 'PaMasterPaketSoalPage',
    'PaMasterSyaratKompetensiPage', 'PaMasterIkatanPaketSoalPage'
);

/* ------------------------------------------------------------------ *
 * 2. Nama, subtitle, dan urutan — mengikuti alur kerja                 *
 * ------------------------------------------------------------------ */

UPDATE N_HRIS_Menus SET Nama_Menu = 'Dashboard PA', Sort_Menu = 1,
    Menu_Subtitle = 'Ringkasan seluruh siklus dan capaiannya'
WHERE Jenis_page = 'PaDashboardPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Siklus Penilaian', Sort_Menu = 2,
    Menu_Subtitle = 'Buat siklus, siapkan peserta, kunci, dan buka pengisian'
WHERE Jenis_page = 'PerformanceAppraisalPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Pemantauan & Verifikasi', Sort_Menu = 3,
    Menu_Subtitle = 'Progres pengisian dan keranjang yang menunggu keputusan Anda'
WHERE Jenis_page = 'PaPemantauanPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Kalibrasi Grade', Sort_Menu = 4,
    Menu_Subtitle = 'Menggeser grade akhir dengan sebab dan jejak'
WHERE Jenis_page = 'PaKalibrasiPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Hasil Penilaian', Sort_Menu = 5,
    Menu_Subtitle = 'Nilai akhir per peserta beserta status terbitnya'
WHERE Jenis_page = 'PaHasilPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'HAV Map (9-Box)', Sort_Menu = 6,
    Menu_Subtitle = 'Sebaran performa terhadap kompetensi'
WHERE Jenis_page = 'PaHavMapPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Distribusi Nilai', Sort_Menu = 7,
    Menu_Subtitle = 'Sebaran grade per level dan divisi'
WHERE Jenis_page = 'PaDistribusiPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Riwayat Penilaian', Sort_Menu = 8,
    Menu_Subtitle = 'Jejak siklus yang sudah lewat'
WHERE Jenis_page = 'PaRiwayatPage';

/* Master data di AKHIR: disetel sekali di awal, lalu jarang disentuh. Menaruhnya di atas
   mengubur pekerjaan harian di bawah delapan butir yang tak pernah diklik. Prefiks
   "Master · " membuat kelompoknya terbaca tanpa perlu sub-header. */

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Jenis Kompetensi', Sort_Menu = 9
WHERE Jenis_page = 'PaMasterJenisKompetensiPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Kompetensi', Sort_Menu = 10
WHERE Jenis_page = 'PaMasterKompetensiPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Aturan Penilai', Sort_Menu = 11
WHERE Jenis_page = 'PaMasterAturanPenilaiPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Bobot Pilar', Sort_Menu = 12
WHERE Jenis_page = 'PaMasterBobotPilarPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Pita Nilai', Sort_Menu = 13
WHERE Jenis_page = 'PaMasterPitaNilaiPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Paket Soal', Sort_Menu = 14
WHERE Jenis_page = 'PaMasterPaketSoalPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Dimensi per Level', Sort_Menu = 15
WHERE Jenis_page = 'PaMasterSyaratKompetensiPage';

UPDATE N_HRIS_Menus SET Nama_Menu = 'Master · Paket Soal Baku', Sort_Menu = 16
WHERE Jenis_page = 'PaMasterIkatanPaketSoalPage';

/* ------------------------------------------------------------------ *
 * 3. Verifikasi sebelum COMMIT                                        *
 * ------------------------------------------------------------------ */

DECLARE @admin int = (
    SELECT COUNT(*) FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa' AND Sort_Header = 11
);

IF @admin <> 16
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50512, 'DML-2026-101 dibatalkan: header administrator_pa tidak berisi 16 butir.', 1;
END

/* "Penilaian Saya" WAJIB tetap di headernya sendiri. Kalau ia ikut terseret, karyawan
   kehilangan satu-satunya pintu ke penilaiannya — dan mereka tidak akan melaporkannya. */
DECLARE @saya varchar(64) = (
    SELECT Header_Code FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalSayaPage'
);

IF @saya <> 'performance_appraisal'
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50513, 'DML-2026-101 dibatalkan: Penilaian Saya ikut berpindah header.', 1;
END

/* Urutan harus 1..16 tanpa bolong dan tanpa kembar — dua butir bernomor sama membuat
   urutannya ditentukan tie-break yang tidak deterministik, dan susunannya bisa berbeda
   antar permintaan. */
DECLARE @urut int = (
    SELECT COUNT(DISTINCT Sort_Menu) FROM N_HRIS_Menus WHERE Header_Code = 'administrator_pa'
);

IF @urut <> 16
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50514, 'DML-2026-101 dibatalkan: Sort_Menu kembar atau bolong.', 1;
END

COMMIT TRANSACTION;
GO
