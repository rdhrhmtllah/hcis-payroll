-- DML-2026-099-penjadwal-perintah-hilang
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- BAGIAN 0 : gerbang basis data
-- BAGIAN 1 : keadaan sebelum
-- BAGIAN 2 : nonaktifkan dua entri yang perintahnya tidak ada di mana pun
-- BAGIAN 3 : verifikasi
-- BAGIAN 4 : yang HARUS dijalankan di production lebih dulu
--
-- ── TEMUAN ───────────────────────────────────────────────────────────────────
--
-- Jadwal HCIS hidup di N_HRIS_Scheduler_Configs, dan Console\Kernel
-- mendaftarkannya apa adanya. Laravel tidak memeriksa keberadaan perintahnya
-- saat MENDAFTAR; kegagalannya baru muncul ketika cron menyala, sebagai
-- "Command ... is not defined" yang mendarat di storage/logs/scheduler.log dan
-- tidak ke mana-mana lagi.
--
-- Dari 20 entri aktif pada Web_HRIS (2026-09-08), tiga menunjuk perintah yang
-- tidak ada. Dua di antaranya tidak ada di branch MANA PUN:
--
--   sop:recert-scan          cron 15 1 * * *   dibuat 2026-07-24
--       Perintahnya DIHAPUS oleh commit 179cf07a bersama pencabutan masa
--       berlaku dokumen SOP. Barisnya tertinggal hidup, sehingga tabel ini
--       menyatakan "resertifikasi berjalan tiap malam 01:15" — padahal mesinnya
--       sudah tidak ada.
--
--   talent-pool:kedaluwarsa  cron 0 1 * * *    dibuat 2026-07-28
--       Tidak pernah ada di riwayat app/Console pada branch mana pun. Dijadwalkan
--       untuk perintah yang belum pernah ditulis.
--
-- ── YANG SENGAJA TIDAK DISENTUH ──────────────────────────────────────────────
--
--   hcis:kalender            cron 15 2 * * *   dibuat 2026-09-05
--       Baru tiga hari, dan keterangannya menyebut sinkron kalender operasional
--       HO — pekerjaan yang sedang berjalan. Ini tampak MENDAHULUI kodenya, bukan
--       tertinggal darinya. Menonaktifkannya berarti mematikan sesuatu yang
--       sebentar lagi hidup; yang benar adalah menanyakannya, bukan menebak.
--
--   pa:ingatkan-penilai, pa:sapu-tertahan
--       Keduanya ADA di branch feat/performance-apprisal. Wajar tidak ditemukan
--       dari checkout master; keduanya hidup begitu PA rilis.
--
-- ── KENAPA DINONAKTIFKAN, BUKAN DIHAPUS ──────────────────────────────────────
--
-- Flag_Aktif = 'T' menghentikan pendaftarannya tanpa membuang catatannya.
-- Barisnya tetap bisa dibaca beserta cron dan keterangannya, dan bila ternyata
-- perintahnya memang akan ditulis, menghidupkannya kembali cukup satu UPDATE.
-- Menghapus baris membuang informasi yang tidak bisa dikembalikan.
--
-- ── APA YANG TIDAK DILAKUKAN DI SINI ─────────────────────────────────────────
--
-- Tidak ada DELETE. Tidak ada perubahan skema. Yang berubah hanya kolom
-- Flag_Aktif pada dua baris, dan hanya bila perintahnya memang masih tidak ada.

/* ═══ BAGIAN 0 : gerbang basis data ═══════════════════════════════════════ */

IF DB_NAME() NOT IN ('Web_HRIS')
BEGIN
    RAISERROR('Skrip ini hanya untuk Web_HRIS. Basis data aktif: %s', 16, 1, DB_NAME());
    SET NOEXEC ON;
END
GO

/* ═══ BAGIAN 1 : keadaan sebelum ══════════════════════════════════════════ */

SELECT Id_Scheduler, Command, Cron_Expression, Flag_Aktif, Created_At, Keterangan
FROM dbo.N_HRIS_Scheduler_Configs
WHERE Command IN ('sop:recert-scan', 'talent-pool:kedaluwarsa')
ORDER BY Command;
GO

/* ═══ BAGIAN 2 : nonaktifkan ══════════════════════════════════════════════
 *
 * Dibatasi pada Flag_Aktif = 'Y' supaya menjalankannya dua kali tidak menyentuh
 * baris yang sudah nonaktif, dan supaya jumlah baris terpengaruh langsung
 * memberi tahu apakah keadaannya sesuai dugaan.
 */

UPDATE dbo.N_HRIS_Scheduler_Configs
SET Flag_Aktif = 'T'
WHERE Command IN ('sop:recert-scan', 'talent-pool:kedaluwarsa')
  AND Flag_Aktif = 'Y';
GO

/* ═══ BAGIAN 3 : verifikasi ═══════════════════════════════════════════════
 * Kedua baris harus Flag_Aktif = 'T', dan tidak ada baris lain yang berubah.
 */

SELECT Command, Flag_Aktif
FROM dbo.N_HRIS_Scheduler_Configs
WHERE Command IN ('sop:recert-scan', 'talent-pool:kedaluwarsa')
ORDER BY Command;
GO

SELECT COUNT(*) AS entri_aktif
FROM dbo.N_HRIS_Scheduler_Configs
WHERE Flag_Aktif = 'Y';
GO

SET NOEXEC OFF;
GO

/* ═══ BAGIAN 4 : yang HARUS dijalankan di production LEBIH DULU ═══════════
 *
 * Isi tabel ini berbeda antar lingkungan: entri yang yatim di pengembangan bisa
 * sah di produksi, dan sebaliknya. Jalankan pemeriksa di SANA lebih dulu:
 *
 *     php artisan hris:penjadwal-periksa --abaikan=pa:
 *
 * Perintah itu hanya membaca. Terapkan skrip ini hanya untuk entri yang di
 * production pun dilaporkan menunjuk perintah yang tidak ada.
 */
