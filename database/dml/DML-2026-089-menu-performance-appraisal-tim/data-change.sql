/* Change ID: DML-2026-089-menu-performance-appraisal-tim
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 1, N_HRIS_Grup_Fitur_Map <= 1,
                            register the change <= 1.
   Dependencies: DML-2026-084 (halaman siklus PA sudah terdaftar).

   SATU BARIS, Is_Global_Menu = 'Y' — DAN DI SINI ALASANNYA BERBEDA DARI DML-2026-086.

   DML-2026-086 memakai global menu karena halamannya memang dipakai SELURUH karyawan.
   Halaman ini tidak: per 2 September 2026 hanya 14 orang yang tercatat sebagai leader di
   `HRIS_Karyawan_Team`, dari 958 karyawan aktif. Jadi global menu di sini adalah
   pertukaran yang disengaja, bukan penerapan pola yang sama.

   Yang dipertukarkan:

     Lewat HRIS_Page_Access, hanya 14 orang melihat menunya - sidebar bersih. Tetapi
     keanggotaan tim BERUBAH, dan tidak ada satu pun mekanisme yang menyambungkan
     perubahan itu ke tabel akses. Leader baru yang tidak didaftarkan tidak akan
     mengeluh: ia hanya tidak pernah melihat timnya, dan itu baru ketahuan ketika
     siklusnya sudah berjalan dan tenggatnya lewat.

     Lewat Is_Global_Menu, 944 orang melihat satu menu yang bagi mereka kosong - dan
     halamannya menjawab dengan kalimat yang jelas ("Anda belum tercatat sebagai leader
     dari siapa pun"), bukan layar galat atau daftar kosong tanpa keterangan. Nol
     pemeliharaan, dan leader baru langsung bisa memakainya.

   Kekosongan yang dijelaskan lebih murah daripada leader yang diam-diam tidak pernah
   melihat timnya. Itu pilihan yang diambil pemilik proyek pada 2 September 2026.

   PENTING: global menu di sini TIDAK berarti datanya global. `PaLeaderService` selalu
   melingkupi dari `Auth::id()` ke `HRIS_Karyawan_Team`, dan `detailAnggota()` menolak
   peserta di luar tim. Menu yang terbuka untuk semua orang tidak membuka satu baris data
   pun milik orang lain; yang menjaganya ada di service, bukan di menu ini.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50380, 'DML-2026-089 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalPage')
    THROW 50381, 'DML-2026-089 membutuhkan DML-2026-084 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

/* ---------- 1. Halaman leader ---------- */
INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT 'PerformanceAppraisalLeaderPage', '001', 'Penilaian Tim', 'bi-people',
       '/performance-appraisal/tim',
       'performance_appraisal', 'Performance Appraisal', 'bi bi-clipboard2-data', 6,
       'Y', 'self', 'Y', 2
WHERE NOT EXISTS (
    SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalLeaderPage'
);

/* ---------- 2. Jalur role ---------- */
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, 'PerformanceAppraisalLeaderPage', 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (
      SELECT 1 FROM N_HRIS_Grup_Fitur_Map x
      WHERE x.Jenis_page = 'PerformanceAppraisalLeaderPage'
  );

/* ---------- 3. Verifikasi ---------- */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Jenis_page = 'PerformanceAppraisalLeaderPage' AND Is_Global_Menu = 'Y'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50382, 'DML-2026-089: halaman leader tidak terpasang atau tidak global.', 1;
END;

/* Barisnya harus TEPAT satu. `Jenis_page` tidak punya unique constraint, sehingga
   dijalankan dua kali tanpa penjaga NOT EXISTS akan menghasilkan menu ganda - dan menu
   ganda tidak menimbulkan galat, hanya dua baris identik di sidebar yang membuat orang
   ragu mana yang benar. */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'PerformanceAppraisalLeaderPage') <> 1
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50383, 'DML-2026-089: baris menu leader lebih dari satu.', 1;
END;

/* Urutannya harus berbeda dari halaman peserta, kalau tidak keduanya bersaing pada
   Sort_Menu yang sama dan urutan sidebar jadi bergantung pada urutan baris - yang tidak
   dijamin. */
IF EXISTS (
    SELECT 1
    FROM N_HRIS_Menus a
    JOIN N_HRIS_Menus b ON b.Header_Code = a.Header_Code AND b.Sort_Menu = a.Sort_Menu
                        AND b.Jenis_page <> a.Jenis_page
    WHERE a.Jenis_page = 'PerformanceAppraisalLeaderPage'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50384, 'DML-2026-089: Sort_Menu leader bentrok dengan halaman lain di header yang sama.', 1;
END;

/* ---------- 4. Catat ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-089-menu-performance-appraisal-tim')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-089-menu-performance-appraisal-tim',
         'DML',
         'Halaman leader Performance Appraisal',
         'Menambah satu halaman leader ber-Is_Global_Menu=Y beserta peta grup fitur KPI.',
         'database/dml/DML-2026-089-menu-performance-appraisal-tim/data-change.sql',
         'docs/changes/DML-2026-089-menu-performance-appraisal-tim/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Is_Global_Menu=Y meski penggunanya hanya 14 dari 958. Berbeda dari DML-2026-086 yang global karena memang dipakai semua orang, di sini global menu adalah pertukaran sadar: akses eksplisit akan membuat leader baru diam-diam tidak pernah melihat timnya karena keanggotaan tim berubah tanpa ada mekanisme yang menyambungkannya ke tabel akses. Halamannya menjawab dengan kalimat jelas bagi yang bukan leader. Menu terbuka TIDAK berarti data terbuka: PaLeaderService melingkupi dari Auth::id() ke HRIS_Karyawan_Team dan menolak peserta di luar tim. WAJIB diikuti InertiaShellService::flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
