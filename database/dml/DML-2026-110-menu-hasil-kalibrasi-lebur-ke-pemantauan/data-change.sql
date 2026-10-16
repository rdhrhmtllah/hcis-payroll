/* Change ID: DML-2026-110-menu-hasil-kalibrasi-lebur-ke-pemantauan
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-107 (urutan menu PA). WAJIB sesudahnya — skrip ini merapatkan
                 nomor urut yang ditetapkan di sana.
   Scope: N_HRIS_Menus — kolom `Is_Show_Sidebar` dan `Sort_Menu` pada header
          `administrator_pa`. Tidak ada baris yang dibuat maupun dihapus.

   YANG DISELESAIKAN

   Empat pekerjaan yang dulu berdiri sebagai menu sendiri kini menjadi tab di halaman
   Pemantauan, karena ketiganya memang satu perjalanan: siklus yang sedang berjalan.

       Hasil Penilaian   -> tab "Hasil & Verifikasi"  (satuan: peserta)
       Distribusi Nilai  -> tab "Kalibrasi"           (satuan: populasi)
       HAV Map (9-Box)   -> tab "Kalibrasi"
       Kalibrasi Grade   -> tab "Kalibrasi"

   Sidebar administrator menyusut dari 15 menjadi 11 baris.

   DISEMBUNYIKAN, BUKAN DIHAPUS

   `Is_Show_Sidebar = 'T'`, sama seperti perlakuan `Master · Paket Soal` pada DML-2026-107 r2.
   Barisnya tetap ada dan URL-nya tetap hidup, sehingga tautan lama, bookmark, dan tautan di
   dalam pemberitahuan tidak mati. Menghapus barisnya akan mematikan semuanya sekaligus, dan
   yang menekan tautan mati tidak diberi tahu apa pun selain halaman yang tidak ada.

   Tab "Kalibrasi" sendiri untuk sementara masih MENGANTAR ke ketiga halaman itu, bukan
   memuat isinya. Justru karena itu menyembunyikan menunya aman: jalannya tetap ada, hanya
   pintunya yang berpindah ke satu tempat.

   RIWAYAT TIDAK IKUT

   `Riwayat Penilaian` tetap berdiri sendiri. Ia jejak audit — dibuka ketika seseorang
   mempertanyakan hasil yang sudah selesai — bukan pekerjaan yang sedang berjalan, dan
   menaruhnya di dalam layar pemantauan siklus aktif justru menyembunyikannya dari saat ia
   paling dicari.

   SESUDAH DIJALANKAN

   Menu disajikan dari cache. Jalankan `php artisan cache:clear`, kalau tidak susunan lama
   masih terlihat walaupun datanya sudah benar.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50570, 'DML-2026-110 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* --- 1. Empat menu yang sudah lebur, disembunyikan ------------------------- */

UPDATE N_HRIS_Menus
SET Is_Show_Sidebar = 'T'
WHERE Header_Code = 'administrator_pa'
  AND Is_Show_Sidebar <> 'T'
  AND Url_Menu IN (
        '/performance-appraisal/hasil',
        '/performance-appraisal/distribusi',
        '/performance-appraisal/hav-map',
        '/performance-appraisal/kalibrasi'
      );

/* --- 2. Sisa 11 menu dirapatkan 1..11 -------------------------------------- *
 * Nomor yang berlubang tidak salah secara teknis - urutannya tetap benar - tetapi baris
 * berikutnya yang ditambahkan orang lain akan menebak nomor dari yang terlihat, dan
 * tebakan itu bertabrakan dengan lubang yang tidak ia ketahui ada.                     */

;WITH urutan(Url_Menu, Sort_Menu) AS (
    SELECT v.Url_Menu, v.Sort_Menu
    FROM (VALUES
        /* Fase A: master, diisi sekali dan dipakai semua siklus */
        ('/performance-appraisal/master/jenis-kompetensi',  1),
        ('/performance-appraisal/master/kompetensi',        2),
        ('/performance-appraisal/master/syarat-kompetensi', 3),
        ('/performance-appraisal/master/aturan-penilai',    4),
        ('/performance-appraisal/master/bobot-pilar',       5),
        ('/performance-appraisal/master/pita-nilai',        6),
        ('/performance-appraisal/master/ikatan-paket-soal', 7),

        /* Fase B-C: siklus dibuat, disiapkan, lalu dikunci dari Langkah 5 */
        ('/performance-appraisal',                          8),

        /* Fase D-G: seluruh siklus berjalan, kini satu halaman tiga tab */
        ('/performance-appraisal/pemantauan',               9),

        /* Fase H: sesudah terbit */
        ('/performance-appraisal/riwayat',                 10),
        ('/performance-appraisal/dashboard',               11)
    ) AS v(Url_Menu, Sort_Menu)
)
UPDATE m
SET m.Sort_Menu = u.Sort_Menu
FROM N_HRIS_Menus m
JOIN urutan u ON u.Url_Menu = m.Url_Menu
WHERE m.Header_Code = 'administrator_pa'
  AND m.Sort_Menu <> u.Sort_Menu;

COMMIT TRANSACTION;
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ */

DECLARE @masihTampil int = (
    SELECT COUNT(*) FROM N_HRIS_Menus
    WHERE Header_Code = 'administrator_pa'
      AND Is_Show_Sidebar = 'Y'
      AND Url_Menu IN (
            '/performance-appraisal/hasil',
            '/performance-appraisal/distribusi',
            '/performance-appraisal/hav-map',
            '/performance-appraisal/kalibrasi'
          )
);

IF @masihTampil > 0
    THROW 50571, 'Masih ada menu yang sudah lebur tetapi tetap tampil di sidebar.', 1;

/* Barisnya HARUS tetap ada. Penjaga ini yang membedakan "disembunyikan" dari "dihapus" —
   dan bedanya baru terasa saat seseorang menekan tautan lama enam bulan kemudian. */
DECLARE @hilang int = (
    4 - (SELECT COUNT(*) FROM N_HRIS_Menus
         WHERE Header_Code = 'administrator_pa'
           AND Url_Menu IN (
                '/performance-appraisal/hasil',
                '/performance-appraisal/distribusi',
                '/performance-appraisal/hav-map',
                '/performance-appraisal/kalibrasi'
              ))
);

IF @hilang <> 0
    THROW 50572, 'Baris menu yang lebur tidak lagi lengkap; ia seharusnya disembunyikan, bukan dihapus.', 1;

DECLARE @kembar int = (
    SELECT COUNT(*) FROM (
        SELECT Sort_Menu
        FROM N_HRIS_Menus
        WHERE Header_Code = 'administrator_pa' AND Is_Show_Sidebar = 'Y'
        GROUP BY Sort_Menu
        HAVING COUNT(*) > 1
    ) AS k
);

IF @kembar > 0
    THROW 50573, 'Ada nomor urut kembar di menu administrator PA; urutannya akan berubah-ubah antar permintaan.', 1;

/* VERIFIKASI — susunan sidebar sesudah perubahan, beserta yang disembunyikan. */
SELECT Sort_Menu, Nama_Menu, Url_Menu, Is_Show_Sidebar
FROM N_HRIS_Menus
WHERE Header_Code = 'administrator_pa'
ORDER BY CASE WHEN Is_Show_Sidebar = 'Y' THEN 0 ELSE 1 END, CAST(Sort_Menu AS int);
