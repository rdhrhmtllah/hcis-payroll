/* Change ID: DML-2026-098-akses-relasi-organisasi-admin-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DML-2026-097 (menu + pemetaan `systemRelasiOrganisasiPage`). Jalankan sesudahnya.
   Scope: HRIS_Page_Access — hanya baris untuk `systemRelasiOrganisasiPage`.

   MASALAH YANG DISELESAIKAN

   Halaman relasi organisasi digerbangi `system.admin`, dan pemegang bendera itu hanya SATU
   orang. Sementara yang membutuhkannya untuk pekerjaannya justru admin Performance
   Appraisal: penugasan penilai diturunkan dari tabel relasi ini, dan selama isinya kosong
   penugasan selain penilaian diri tidak bisa terbentuk sama sekali.

   Jadi orang yang menyiapkan siklus tidak bisa memperbaiki hal yang menghalangi siklusnya.

   YANG BERUBAH DI KODE, DAN KENAPA BARIS INI JADI BERARTI

   Rutenya kini memakai gerbang ATAU: `system.admin.atau:systemRelasiOrganisasiPage`.
   System Admin tetap lolos lewat benderanya; selain itu, akses diberikan per halaman lewat
   tabel ini.

   Ini MEMBATALKAN alasan yang tertulis di DML-2026-097 ("kenapa tidak ada baris
   HRIS_Page_Access"). Waktu itu benar: tidak ada yang membaca hak per halaman untuk halaman
   ini, sehingga menyisipkan barisnya hanya memberi kesan hak itu diperiksa. Sekarang ia
   benar-benar diperiksa. DML-097 sengaja TIDAK disunting — ia sudah berjalan di dev dan
   checksumnya tercatat; koreksinya di sini.

   KENAPA PENERIMANYA DITURUNKAN, BUKAN DIDAFTAR

   Id pengguna di dev dan produksi TIDAK sama. Skrip yang menyebut `UserID_Web = 622` akan
   memberi akses kepada orang yang sama sekali lain di produksi — dan kesalahan itu tidak
   menampakkan diri: barisnya tersimpan, halamannya terbuka, hanya untuk orang yang salah.

   Karena itu penerimanya diturunkan dari keadaan: siapa pun yang SUDAH memegang
   `PerformanceAppraisalPage` secara eksplisit. Itu definisi operasional "admin PA" di
   basis data ini.

   YANG SENGAJA TIDAK DILAKUKAN

   Memetakan halaman ini ke grup fitur KPI. Di sistem ini role memberi SELURUH halaman
   grupnya tanpa granularitas — grup KPI mencakup 38 halaman — sehingga pemetaan itu akan
   menyerahkan master "siapa menilai siapa" kepada setiap pemegang role KPI, sekarang maupun
   yang ditambahkan kelak, termasuk role yang namanya menjanjikan cakupan lain
   ("KPI Menu Administrator"). Diputuskan: pemberian per orang.

   Penambahan orang berikutnya TIDAK perlu DML. Layar Page Access sudah bisa melakukannya.

   KENAPA KOLOM PENYERTANYA DISALIN, BUKAN DIISI NILAI BAKU

   `Kode_Perusahaan`, `ID_Level`, dan `ID_Divisi` tidak boleh NULL dan ikut dibaca jalur
   legacy. Mengisinya dengan tebakan menghasilkan baris yang tersimpan rapi tetapi tidak
   pernah cocok saat dibaca — hak yang terlihat ada dan tidak berfungsi. Nilainya disalin
   dari baris `PerformanceAppraisalPage` milik orang yang sama.
*/

/* CATATAN PELAKSANAAN

   Seluruh isi transaksi berada dalam SATU batch — tidak ada `GO` antara BEGIN dan COMMIT.
   `GO` mengakhiri batch, dan transaksi yang dibuka di satu batch tidak bertahan ke batch
   berikutnya: ia digulung balik, lalu sisa skrip berjalan TANPA perlindungan. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50490, 'DML-2026-098 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

/* Prasyarat: halamannya harus sudah dikenal. Tanpa pemetaan grup fitur, baris hak akses
   memberi akses ke halaman yang tidak muncul di menu mana pun. */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map
    WHERE Jenis_page = 'systemRelasiOrganisasiPage' AND Flag_Aktif = 'Y'
)
    THROW 50491, 'DML-2026-098 membutuhkan DML-2026-097 (pemetaan systemRelasiOrganisasiPage).', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Beri akses kepada pemegang PerformanceAppraisalPage eksplisit    *
 * ------------------------------------------------------------------ */

/* Idempoten: yang sudah punya baris aktif dilewati, bukan diduplikasi. Tabel ini tidak
   punya kunci unik pada (user, halaman), jadi menjalankan ulang tanpa penjaga ini akan
   menumpuk baris kembar yang semuanya berlaku. */
INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif)
SELECT
    pa.Kode_Perusahaan,
    pa.ID_Level,
    pa.ID_Divisi,
    'systemRelasiOrganisasiPage',
    pa.UserID_Web,
    'Y'
FROM HRIS_Page_Access AS pa
WHERE pa.Jenis_page = 'PerformanceAppraisalPage'
  AND pa.Flag_Aktif = 'Y'
  AND pa.UserID_Web IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access AS ada
      WHERE ada.UserID_Web = pa.UserID_Web
        AND ada.Jenis_page = 'systemRelasiOrganisasiPage'
        AND ada.Flag_Aktif = 'Y'
  );

/* ------------------------------------------------------------------ *
 * 2. Penjaga                                                          *
 * ------------------------------------------------------------------ */

DECLARE @adminPa int = (
    SELECT COUNT(DISTINCT UserID_Web) FROM HRIS_Page_Access
    WHERE Jenis_page = 'PerformanceAppraisalPage' AND Flag_Aktif = 'Y' AND UserID_Web IS NOT NULL
);

DECLARE @diberi int = (
    SELECT COUNT(DISTINCT UserID_Web) FROM HRIS_Page_Access
    WHERE Jenis_page = 'systemRelasiOrganisasiPage' AND Flag_Aktif = 'Y' AND UserID_Web IS NOT NULL
);

/* Kalau di basis data ini tidak ada satu pun admin PA eksplisit, skripnya tidak berbuat
   apa-apa — dan itu HARUS terdengar, bukan lewat sebagai sukses. Perubahan hak yang tidak
   mengubah apa pun adalah kabar, bukan keberhasilan. */
IF @adminPa = 0
    THROW 50492, 'Tidak ada pemegang PerformanceAppraisalPage eksplisit; tidak ada yang bisa diberi akses. Periksa dulu siapa admin PA di basis data ini.', 1;

IF @diberi < @adminPa
    THROW 50493, 'Jumlah penerima akses lebih sedikit daripada jumlah admin PA; ada yang tidak tersalin.', 1;

/* Tidak boleh ada baris kembar aktif untuk satu orang: keduanya berlaku, dan mencabut satu
   akan terlihat seperti pencabutan yang tidak bekerja. */
IF EXISTS (
    SELECT 1 FROM HRIS_Page_Access
    WHERE Jenis_page = 'systemRelasiOrganisasiPage' AND Flag_Aktif = 'Y'
    GROUP BY UserID_Web
    HAVING COUNT(*) > 1
)
    THROW 50494, 'Ada baris hak akses kembar untuk systemRelasiOrganisasiPage.', 1;

COMMIT TRANSACTION;
GO

/* WAJIB DIIKUTI: InertiaShellService::flushMenuCache().

   Tanpa itu, menu yang sudah ter-cache tetap tidak memuat butirnya, dan admin PA yang
   sudah berhak akan melaporkan "menunya tidak ada" atas hak yang sebenarnya sudah aktif. */
