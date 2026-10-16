/* Change ID: DML-2026-090-cabut-menu-master-struktural-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Menus <= 2 dihapus, N_HRIS_Grup_Fitur_Map <= 2 dihapus,
                            HRIS_Page_Access <= 4 dihapus, register the change <= 1.
   Dependencies: DML-2026-085 (kedelapan halaman master PA sudah terdaftar).

   MENCABUT DUA HALAMAN MASTER DARI ADMIN PA — DAN KENAPA BUKAN DELAPAN-DELAPANNYA.

   Jalur frontend membuat halaman untuk SELURUH master PA. Dua di antaranya seharusnya
   tidak pernah disetel admin PA, dan itu bukan pendapat: kode di `PaMasterWriteService`
   SUDAH menolak menambah maupun menonaktifkan keduanya, lengkap dengan alasannya di
   `alasanTertutup()`. Yang tersisa bagi admin hanya menyunting nama dan deskripsi —
   label kosmetik atas kerangka yang tidak boleh berubah.

     PaMasterKuadranHavPage
       Matriks HAV berukuran tiga kali tiga dan SUDAH PENUH: sembilan kotak untuk sembilan
       kombinasi (RENDAH/SEDANG/TINGGI) x (RENDAH/SEDANG/TINGGI). Kotak kesepuluh tidak
       punya tempat berdiri, dan menonaktifkan satu kotak membuat orang yang jatuh ke sel
       itu tidak masuk kotak mana pun.

     PaMasterKategoriPenilaiPage
       Empat baris, dan `SELF` ditulis langsung di dua berkas service. Kategori baru tanpa
       aturan di setiap level tidak akan pernah muncul; menonaktifkan yang ada memutus
       aturan siklus yang sedang berjalan.

   Enam master lainnya TETAP: jenis kompetensi, kompetensi, aturan penilai, bobot pilar,
   pita nilai, dan paket soal semuanya keputusan bisnis yang sah diubah HC.

   ISINYA TIDAK DIHAPUS, DAN ITU PENTING. Yang dicabut hanya baris menu, peta grup fitur,
   dan akses halamannya; tabel `N_PA_Hav_Quadrant` dan `N_PA_Rater_Category` tetap utuh.

   Jalur bacanya diperiksa satu per satu, bukan diasumsikan:
     - `N_PA_Rater_Category` ikut di `/master/referensi` (kunci `kategori_penilai`).
     - `N_PA_Hav_Quadrant` TIDAK ada di `/master/referensi`. Ia dibaca LANGSUNG oleh enam
       service — kalibrasi, distribusi, leader, peserta, baca peserta, dan perhitungan —
       sehingga nama kuadran tetap sampai ke layar hasil, HAV Map, dan detail peserta lewat
       jalurnya masing-masing.

   Yang hilang hanya kemampuan menyuntingnya lewat UI.

   Perubahan yang sungguh-sungguh dibutuhkan kelak dilakukan lewat DML bernomor, sehingga
   terekam di SQL Tracker — dan itu memang bentuk yang benar untuk perubahan yang mengubah
   arti seluruh riwayat penilaian.

   SESUDAH MENJALANKAN INI, cache menu WAJIB dibuang:
       php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50390, 'DML-2026-090 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

BEGIN TRANSACTION;

DECLARE @halaman TABLE (Jenis_page varchar(100) PRIMARY KEY);
INSERT INTO @halaman (Jenis_page) VALUES ('PaMasterKuadranHavPage'), ('PaMasterKategoriPenilaiPage');

/* ---------- 1. Akses per orang ---------- */
DELETE a
FROM HRIS_Page_Access a
JOIN @halaman h ON h.Jenis_page = a.Jenis_page;

/* ---------- 2. Peta grup fitur ---------- */
DELETE m
FROM N_HRIS_Grup_Fitur_Map m
JOIN @halaman h ON h.Jenis_page = m.Jenis_page;

/* ---------- 3. Baris menu ---------- */
DELETE n
FROM N_HRIS_Menus n
JOIN @halaman h ON h.Jenis_page = n.Jenis_page;

/* ---------- 4. Verifikasi ---------- */
IF EXISTS (SELECT 1 FROM N_HRIS_Menus n JOIN @halaman h ON h.Jenis_page = n.Jenis_page)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50391, 'DML-2026-090: baris menu masih tersisa.', 1;
END;

/* Enam master lainnya WAJIB masih berdiri. Tanpa pemeriksaan ini, penghapusan yang
   kelewat luas akan lolos tanpa suara — dan gejalanya baru muncul ketika admin membuka
   sidebar dan menemukan modulnya kosong. */
IF (SELECT COUNT(*) FROM N_HRIS_Menus WHERE Header_Code = 'administrator_pa') <> 6
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50392, 'DML-2026-090: jumlah halaman Administrator PA bukan 6 sesudah pencabutan.', 1;
END;

/* Isi masternya TIDAK BOLEH ikut terhapus. Halamannya dicabut, datanya tetap dibaca
   layar hasil lewat jalurnya masing-masing (lihat catatan di kepala berkas). */
IF (SELECT COUNT(*) FROM N_PA_Hav_Quadrant) <> 9
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50393, 'DML-2026-090: isi N_PA_Hav_Quadrant berubah; seharusnya tidak tersentuh.', 1;
END;

IF (SELECT COUNT(*) FROM N_PA_Rater_Category) < 4
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50394, 'DML-2026-090: isi N_PA_Rater_Category berkurang; seharusnya tidak tersentuh.', 1;
END;

/* ---------- 5. Catat ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-090-cabut-menu-master-struktural-pa')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-090-cabut-menu-master-struktural-pa',
         'DML',
         'Cabut menu master struktural dari Administrator PA',
         'Mencabut halaman Kuadran HAV dan Kategori Penilai dari menu admin PA. Isi masternya tidak disentuh.',
         'database/dml/DML-2026-090-cabut-menu-master-struktural-pa/data-change.sql',
         'docs/changes/DML-2026-090-cabut-menu-master-struktural-pa/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Dua master yang KODENYA sudah menolak tambah dan nonaktif (PaMasterWriteService::alasanTertutup) tidak seharusnya punya halaman setelan: Kuadran HAV matriks 3x3 sudah penuh, Kategori Penilai punya SELF yang ditulis langsung di dua service. Yang tersisa bagi admin hanya menyunting label kosmetik atas kerangka yang tidak boleh berubah. ISI MASTER TIDAK DIHAPUS. Jalur bacanya diperiksa: N_PA_Rater_Category ikut di /master/referensi; N_PA_Hav_Quadrant TIDAK, ia dibaca langsung oleh enam service (kalibrasi, distribusi, leader, peserta, baca peserta, perhitungan) sehingga nama kuadran tetap sampai ke layar lewat jalurnya sendiri. Enam master lain tetap karena semuanya keputusan bisnis yang sah. Skrip memverifikasi sisa halaman Administrator PA tepat 6 dan isi kedua tabel master tidak berubah. WAJIB diikuti InertiaShellService::flushMenuCache().',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
