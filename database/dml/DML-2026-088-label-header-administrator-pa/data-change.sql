/* Change ID: DML-2026-088-label-header-administrator-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: UPDATE N_HRIS_Menus <= 8, register the change <= 1.
   Dependencies: DML-2026-085 (header administrator_pa sudah ada).

   Memendekkan label header dari "Administrator Performance Appraisal" menjadi
   "Administrator PA" atas permintaan jalur frontend.

   KENAPA HEADER_CODE TIDAK IKUT BERUBAH

   Yang diubah hanya Header_LABEL — teks yang dibaca manusia. `Header_Code`
   ('administrator_pa') tetap, karena ia kunci pengelompokan yang dipakai
   InertiaShellService untuk merakit sidebar, dan mengubahnya akan memecah kedelapan
   halaman master menjadi dua header berbeda selama cache belum dibuang.

   Label didenormalisasi PER BARIS MENU di tabel ini - tidak ada tabel header terpisah -
   sehingga satu perubahan label menyentuh kedelapan barisnya sekaligus. Itu sebabnya
   plafon di atas delapan, bukan satu.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50379, 'DML-2026-088 hanya boleh dijalankan pada Web_HRIS.', 1;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Header_Code = 'administrator_pa')
    THROW 50380, 'DML-2026-088 membutuhkan DML-2026-085 yang sudah dijalankan.', 1;
GO

BEGIN TRANSACTION;

UPDATE N_HRIS_Menus
SET Header_Label = 'Administrator PA'
WHERE Header_Code = 'administrator_pa'
  AND Header_Label <> 'Administrator PA';

/* ---------- Verifikasi ---------- */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa' AND Header_Label <> 'Administrator PA'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50381, 'DML-2026-088: masih ada baris berlabel lama.', 1;
END;

/* Kedelapan halaman master harus TETAP satu header. Label yang berubah sebagian
   memecah sidebar menjadi dua kelompok yang tampak seperti dua modul berbeda. */
IF (SELECT COUNT(DISTINCT Header_Label) FROM N_HRIS_Menus WHERE Header_Code = 'administrator_pa') <> 1
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50382, 'DML-2026-088: header administrator_pa terpecah lebih dari satu label.', 1;
END;

/* ---------- Pencatatan change ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-088-label-header-administrator-pa')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-088-label-header-administrator-pa',
         'DML',
         'Label header Administrator PA dipendekkan',
         'Header_Label "Administrator Performance Appraisal" menjadi "Administrator PA" pada kedelapan halaman master.',
         'database/dml/DML-2026-088-label-header-administrator-pa/data-change.sql',
         'docs/changes/DML-2026-088-label-header-administrator-pa/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Hanya Header_Label yang berubah; Header_Code tetap administrator_pa karena ia kunci pengelompokan sidebar di InertiaShellService, dan mengubahnya memecah kedelapan halaman master jadi dua header selama cache belum dibuang. Label didenormalisasi per baris menu sehingga satu perubahan menyentuh delapan baris. Penjaga menolak keadaan terpecah (lebih dari satu label untuk satu Header_Code). WAJIB diikuti InertiaShellService::flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
