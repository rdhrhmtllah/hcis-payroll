/* Change ID: DML-2026-092-tata-ulang-header-menu-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 9 diperbarui, register the change <= 1.
   Dependencies: DML-2026-091 (dua halaman fase sudah terdaftar).

   MEMINDAHKAN HALAMAN ADMIN KE HEADER "ADMINISTRATOR PA".

   Sampai 3 September 2026 header `performance_appraisal` memuat campuran yang membuat
   satu header berisi hal yang SAMA SEKALI BERBEDA tergantung siapa yang membukanya:

     Penilaian Saya          Is_Global_Menu='Y'  -> seluruh 958 karyawan
     Penilaian Tim           Is_Global_Menu='Y'  -> seluruh 958 karyawan
     Performance Appraisal   akses eksplisit     -> 2 orang   (halaman siklus)
     Pemantauan Pengisian    akses eksplisit     -> 2 orang
     Hasil dan Penerbitan    akses eksplisit     -> 2 orang

   Karyawan biasa melihat dua butir; admin melihat lima. Header bernama sama, isi berbeda —
   dan orang yang membandingkan layarnya dengan rekan akan mengira ada yang rusak.

   Ada kejanggalan kedua yang lebih kecil tetapi menular: halaman siklus bernama
   "Performance Appraisal" DI DALAM header bernama "Performance Appraisal". Namanya
   sekaligus diperjelas menjadi "Siklus dan Penyiapan", mengikuti fase yang diwakilinya.

   SESUDAH PERUBAHAN INI:

     Performance Appraisal (global, semua karyawan)
       1. Penilaian Saya
       2. Penilaian Tim

     Administrator PA (akses eksplisit)
       1. Siklus dan Penyiapan     <- pindah, berganti nama
       2. Pemantauan Pengisian     <- pindah
       3. Hasil dan Penerbitan     <- pindah
       4. Jenis Kompetensi
       5. Kompetensi
       6. Aturan Penilai
       7. Bobot Pilar
       8. Pita Nilai
       9. Paket Soal

   URUTANNYA IKUT DIRAPIKAN, dan itu bukan kosmetik. Nomor master sekarang berlubang
   (1,2,4,5,6,8) karena dua halaman dicabut DML-2026-090; lubang itu membuat penyisipan
   berikutnya menebak-nebak. Tiga halaman fase ditaruh lebih dulu karena itulah ALUR
   KERJANYA — menyiapkan, memantau, memutuskan — sedangkan master adalah konfigurasi yang
   jarang disentuh.

   TIDAK ADA HAK YANG BERUBAH. Yang diubah hanya `Header_Code`, `Header_Label`,
   `Nama_Menu`, dan `Sort_Menu`. Baris `HRIS_Page_Access` tidak disentuh sama sekali, jadi
   tidak seorang pun mendapat atau kehilangan akses karena perubahan ini.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50400, 'DML-2026-092 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaHasilPage')
    THROW 50401, 'DML-2026-092 membutuhkan DML-2026-091 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

/* Nilai header admin diambil dari baris yang SUDAH ADA, bukan diketik ulang. Mengetiknya
   ulang berarti dua tempat yang harus sepakat selamanya, dan suatu hari salah satunya
   diubah sendirian. */
DECLARE @headerCode  varchar(100),
        @headerLabel nvarchar(200),
        @headerIcon  varchar(100),
        @sortHeader  int;

SELECT TOP 1
    @headerCode  = Header_Code,
    @headerLabel = Header_Label,
    @headerIcon  = Header_Icon,
    @sortHeader  = Sort_Header
FROM N_HRIS_Menus
WHERE Jenis_page = 'PaMasterKompetensiPage';

IF @headerCode IS NULL
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50402, 'DML-2026-092: header Administrator PA tidak ditemukan.', 1;
END;

/* ---------- 1. Tiga halaman fase pindah header ---------- */
UPDATE N_HRIS_Menus
SET Header_Code  = @headerCode,
    Header_Label = @headerLabel,
    Header_Icon  = @headerIcon,
    Sort_Header  = @sortHeader,
    Nama_Menu    = CASE WHEN Jenis_page = 'PerformanceAppraisalPage'
                        THEN N'Siklus dan Penyiapan' ELSE Nama_Menu END,
    Sort_Menu    = CASE Jenis_page
                        WHEN 'PerformanceAppraisalPage' THEN 1
                        WHEN 'PaPemantauanPage'         THEN 2
                        WHEN 'PaHasilPage'              THEN 3
                   END
WHERE Jenis_page IN ('PerformanceAppraisalPage', 'PaPemantauanPage', 'PaHasilPage');

/* ---------- 2. Master dirapikan urutannya, lubangnya ditutup ---------- */
UPDATE N_HRIS_Menus
SET Sort_Menu  = CASE Jenis_page
                     WHEN 'PaMasterJenisKompetensiPage' THEN 4
                     WHEN 'PaMasterKompetensiPage'      THEN 5
                     WHEN 'PaMasterAturanPenilaiPage'   THEN 6
                     WHEN 'PaMasterBobotPilarPage'      THEN 7
                     WHEN 'PaMasterPitaNilaiPage'       THEN 8
                     WHEN 'PaMasterPaketSoalPage'       THEN 9
                 END
WHERE Jenis_page IN ('PaMasterJenisKompetensiPage', 'PaMasterKompetensiPage',
                     'PaMasterAturanPenilaiPage', 'PaMasterBobotPilarPage',
                     'PaMasterPitaNilaiPage', 'PaMasterPaketSoalPage');

/* ---------- 3. Verifikasi ---------- */

/* Header karyawan menyisakan TEPAT dua halaman, dan keduanya harus global. Kalau ada
   halaman berakses-eksplisit tertinggal di sana, campurannya belum benar-benar terurai. */
IF (SELECT COUNT(*) FROM N_HRIS_Menus
    WHERE Header_Code = 'performance_appraisal') <> 2
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50403, 'DML-2026-092: header karyawan bukan berisi tepat 2 halaman.', 1;
END;

IF EXISTS (SELECT 1 FROM N_HRIS_Menus
           WHERE Header_Code = 'performance_appraisal' AND Is_Global_Menu <> 'Y')
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50404, 'DML-2026-092: masih ada halaman non-global di header karyawan.', 1;
END;

/* Header admin berisi sembilan, dan tak satu pun boleh global — halaman admin yang
   diam-diam global akan tampil bagi 958 orang yang tidak bisa memakainya. */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = @headerCode) <> 9
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50405, 'DML-2026-092: header Administrator PA bukan berisi 9 halaman.', 1;
END;

IF EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Header_Code = @headerCode AND Is_Global_Menu = 'Y')
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50406, 'DML-2026-092: ada halaman admin ber-Is_Global_Menu=Y.', 1;
END;

/* Urutan 1..9 tanpa lubang dan tanpa kembar. Urutan kembar membuat susunan sidebar
   bergantung pada urutan baris, yang tidak dijamin. */
IF (SELECT COUNT(DISTINCT Sort_Menu) FROM N_HRIS_Menus WHERE Header_Code = @headerCode) <> 9
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50407, 'DML-2026-092: urutan menu Administrator PA kembar atau kosong.', 1;
END;

/* ---------- 4. Catat ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-092-tata-ulang-header-menu-pa')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-092-tata-ulang-header-menu-pa',
         'DML',
         'Tata ulang header menu PA: admin dipisahkan dari karyawan',
         'Memindahkan tiga halaman fase ke header Administrator PA dan merapikan urutannya.',
         'database/dml/DML-2026-092-tata-ulang-header-menu-pa/data-change.sql',
         'docs/changes/DML-2026-092-tata-ulang-header-menu-pa/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Header performance_appraisal dulu memuat CAMPURAN: dua halaman global untuk 958 karyawan bersama tiga halaman admin berakses-eksplisit untuk 2 orang. Satu header berisi hal berbeda tergantung pembukanya, dan orang yang membandingkan layarnya dengan rekan akan mengira ada yang rusak. Tiga halaman fase dipindah ke header Administrator PA; halaman siklus sekaligus diganti nama dari "Performance Appraisal" (yang bersarang di header bernama sama) menjadi "Siklus dan Penyiapan". Urutan dirapikan 1..9 tanpa lubang - nomor master sempat berlubang 1,2,4,5,6,8 karena DML-2026-090 mencabut dua halaman, dan lubang membuat penyisipan berikutnya menebak. Fase ditaruh lebih dulu karena itu ALUR KERJA; master menyusul karena konfigurasi jarang disentuh. TIDAK ADA HAK YANG BERUBAH: HRIS_Page_Access tidak disentuh sama sekali. Skrip menuntut header karyawan tepat 2 dan semuanya global, header admin tepat 9 dan tak satu pun global, serta urutan 1..9 tanpa kembar. WAJIB diikuti flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
