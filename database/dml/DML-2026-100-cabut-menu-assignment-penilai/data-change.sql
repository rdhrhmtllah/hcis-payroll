/* Change ID: DML-2026-100-cabut-menu-assignment-penilai
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-093 (butir menu Assignment Penilai). Jalankan sesudahnya.
   Scope: N_HRIS_Menus, N_HRIS_Grup_Fitur_Map, HRIS_Page_Access — hanya baris `PaAssignmentPage`.

   YANG DISELESAIKAN

   Halaman `/performance-appraisal/penilai` dibubarkan. Pekerjaannya pindah ke Tahap 5 wizard
   penyiapan (`/performance-appraisal/siapkan/{cycleId}`), tab "Peserta": tiap baris punya
   tombol "Kelola Penilai" yang membuka penyunting yang SAMA di tempat.

   Sebabnya perjalanan admin. Dulu ia melihat "peserta ini kurang penilai" di Tahap 5, lalu
   dilempar ke halaman lain, harus menemukan orangnya lagi di daftar sana, menyuntingnya, lalu
   kembali — kehilangan tab, saringan level, dan posisi gulirnya. Satu pekerjaan, dua layar.

   Rute dan aksi controller-nya sudah dicabut di kode. Butir menu yang tertinggal akan
   mengajak admin ke halaman yang tidak ada lagi — 404 pada menu yang tampak sah.

   YANG TIDAK DICABUT

   Endpoint API-nya: `usulan-penilai`, `penugasan`, `override/penilai`. Yang dibubarkan
   HALAMANNYA, bukan kemampuannya — penyunting di Tahap 5 memakai endpoint yang sama, dan
   mencabutnya berarti membubarkan fiturnya, bukan memindahkannya.

   KENAPA DINONAKTIFKAN, BUKAN DIHAPUS

   Barisnya disimpan dengan `Flag_Aktif = 'T'`. Menghapusnya membuat riwayat "kapan butir ini
   ada dan kapan hilang" tidak bisa dijawab, dan `Id_Menu` yang terbebas bisa dipakai ulang
   butir lain — sehingga jejak audit lama menunjuk butir yang sama sekali berbeda.

   Pemetaan grup fiturnya justru HARUS dihapus, bukan dinonaktifkan: kueri akses role tidak
   menyaring `Flag_Aktif` pada tabel itu, jadi baris yang dimatikan tetap memberi akses. Itu
   sudah diuji dan dicatat di DML-2026-099.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT. `GO`
   mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50510, 'DML-2026-100 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Butir menu dinonaktifkan                                         *
 * ------------------------------------------------------------------ */

UPDATE N_HRIS_Menus
SET Is_Show_Sidebar = 'T'
WHERE Jenis_page = 'PaAssignmentPage';

/* ------------------------------------------------------------------ *
 * 2. Pemetaan grup fitur DIHAPUS                                      *
 * ------------------------------------------------------------------ */

/* Dihapus, bukan dimatikan — lihat catatan di kepala berkas. */
DELETE FROM N_HRIS_Grup_Fitur_Map
WHERE Jenis_page = 'PaAssignmentPage';

/* ------------------------------------------------------------------ *
 * 3. Hak akses eksplisit dinonaktifkan                                *
 * ------------------------------------------------------------------ */

/* Dinonaktifkan, tidak dihapus: kalau kelak halamannya dihidupkan lagi, siapa yang tadinya
   berhak masih bisa dibaca. Hak atas halaman yang tidak ada tidak berbahaya — tidak ada rute
   yang membacanya. */
UPDATE HRIS_Page_Access
SET Flag_Aktif = 'T'
WHERE Jenis_page = 'PaAssignmentPage';

/* ------------------------------------------------------------------ *
 * 4. Penjaga                                                          *
 * ------------------------------------------------------------------ */

IF EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaAssignmentPage'
)
    THROW 50511, 'Pemetaan grup fitur PaAssignmentPage masih ada; ia tetap memberi akses.', 1;

IF EXISTS (
    SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaAssignmentPage' AND Is_Show_Sidebar = 'Y'
)
    THROW 50512, 'Butir menu PaAssignmentPage masih tampil di sidebar.', 1;

/* Grup "Konfigurasi Cycle" tidak boleh jadi KOSONG: header tanpa butir tampil sebagai judul
   yang tidak bisa diklik apa pun, dan admin akan menyangka isinya gagal dimuat. */
IF NOT EXISTS (
    SELECT 1
    FROM N_HRIS_Menus AS m
    JOIN N_HRIS_Grup_Fitur_Map AS g ON g.Jenis_page = m.Jenis_page AND g.Flag_Aktif = 'Y'
    WHERE m.Header_Code = 'pa_konfigurasi' AND m.Is_Show_Sidebar = 'Y'
)
    THROW 50513, 'Header Konfigurasi Cycle menjadi kosong sesudah pencabutan ini.', 1;

COMMIT TRANSACTION;
GO

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache().

   Tanpa itu butirnya tetap tampil dari cache, dan admin yang mengkliknya mendapat 404 atas
   menu yang terlihat sah. */
