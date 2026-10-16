-- DML-2026-098-jadwal-cutoff-tanggal-dipaku
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- BAGIAN 1 : matikan baris "cutoff harian" yang tanggalnya dipaku
-- BAGIAN 2 : nyalakan perintah cutoff yang menghitung periodenya sendiri
-- BAGIAN 3 : verifikasi
-- BAGIAN 4 : pengembalian
--
-- ── TEMUAN ───────────────────────────────────────────────────────────────────
--
-- Ada DUA baris penjadwal yang keterangannya sama persis, "HRIS absensi - plan
-- refresh cutoff harian malam". Yang AKTIF bukan yang menghitung periodenya:
--
--   Id 10  AKTIF   hris:absen-plan-custom
--                    --tgl_awal=2026-05-01 --tgl_akhir=2026-05-31
--                    --refresh_mode=MISSING_OR_DIRTY
--                  cron 30 23 * * *
--
--   Id 11  MATI    hris:absen-plan-cutoff
--                    --refresh_mode=FORCE
--                  cron * * * * *
--
-- Baris yang aktif memakai rentang tanggal yang DIPAKU pada Mei 2026. Ia
-- dibuat 13 Mei 2026 dan tidak pernah berubah sejak itu.
--
-- Perintah hris:absen-plan-cutoff justru dibuat untuk keperluan ini: ia
-- memanggil CutoffService::getCurrentPeriod() dan mengambil Start_Date serta
-- End_Date periode yang sedang berjalan. Ia yang dimatikan.
--
-- ── DUA HAL YANG MEMPERBURUKNYA ──────────────────────────────────────────────
--
-- 1. Rentang 1–31 tidak pernah cocok dengan kebijakan cutoff yang berlaku.
--    Kebijakannya "Kebijakan Cutoff Standar 16-15": periode berjalan saat
--    catatan ini ditulis adalah 16 Agu 2026 – 15 Sep 2026. Jadi bukan cuma
--    basi empat bulan, bentuknya pun tidak pernah sesuai.
--
-- 2. Baris yang mati membawa cron `* * * * *` — TIAP MENIT. Selama Flag_Aktif
--    'T' ia tidak berjalan, tetapi siapa pun yang kelak menyalakannya tanpa
--    memperhatikan cron-nya akan menjalankan perencanaan seluruh periode 1.440
--    kali sehari. withoutOverlapping(15) dan onOneServer() mencegah penumpukan,
--    bukan frekuensinya.
--
-- ── APA YANG BELUM DIKETAHUI, DAN JANGAN DIANGGAP TAHU ───────────────────────
--
-- Semua di atas dibaca dari N_HRIS_Scheduler_Configs di basis data
-- PENGEMBANGAN. Konfigurasi production BELUM diperiksa dan bisa berbeda.
-- Langkah pertama sebelum menerapkan apa pun: jalankan BAGIAN 3 di production
-- dalam mode baca saja, dan bandingkan.
--
-- Antrean N_HRIS_Absen_Recalc_Queue di dev juga tidak memuat satu pun baris
-- ber-Request_Source 'SCHEDULED_CUTOFF_DAILY' maupun 'SCHEDULED_ROLLING_7_DAYS'
-- — yang paling mungkin berarti schedule:run memang tidak berjalan di mesin
-- dev, BUKAN bukti bahwa penjadwalnya gagal di tempat lain.
--
-- ── KENAPA MISSING_OR_DIRTY, BUKAN FORCE ─────────────────────────────────────
--
-- Baris Id 11 tertulis --refresh_mode=FORCE. Yang benar-benar berjalan selama
-- ini adalah MISSING_OR_DIRTY (Id 10). FORCE atas periode 31 hari x ~958
-- karyawan aktif setiap malam adalah beban yang jauh lebih besar, dan bukan
-- perilaku yang sedang dipulihkan. Perubahan di bawah ini menyamakannya ke
-- MISSING_OR_DIRTY: yang diperbaiki RENTANG TANGGALNYA, bukan cakupan kerjanya.

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY
    BEGIN TRANSACTION;

    /* =================================================================
       BAGIAN 1 — matikan baris bertanggal dipaku

       Tidak dihapus, dimatikan: kalau ternyata rentang Mei itu memang
       disengaja untuk sesuatu, barisnya masih ada berikut Created_At-nya.
       ================================================================= */
    UPDATE N_HRIS_Scheduler_Configs
    SET Flag_Aktif = 'T',
        Keterangan = N'DINONAKTIFKAN DML-2026-098 - rentang tanggal dipaku pada Mei 2026; digantikan hris:absen-plan-cutoff yang menghitung periodenya sendiri'
    WHERE Command LIKE N'hris:absen-plan-custom%'
      AND Command LIKE N'%tgl_awal=2026-05-01%'
      AND Flag_Aktif = 'Y';

    /* =================================================================
       BAGIAN 2 — nyalakan perintah yang menghitung periodenya sendiri

       Cron disamakan dengan yang selama ini benar-benar berjalan
       (30 23 * * *), BUKAN `* * * * *` yang tertulis di baris itu.
       ================================================================= */
    UPDATE N_HRIS_Scheduler_Configs
    SET Command = N'hris:absen-plan-cutoff --kode_perusahaan=001 --refresh_mode=MISSING_OR_DIRTY --request_source=SCHEDULED_CUTOFF_DAILY --requested_by=laravel-scheduler --priority=4',
        Cron_Expression = N'30 23 * * *',
        Flag_Aktif = 'Y',
        Keterangan = N'HRIS absensi - plan refresh cutoff harian malam (periode dihitung CutoffService)'
    WHERE Command LIKE N'hris:absen-plan-cutoff%';

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;

/* =====================================================================
   BAGIAN 3 — verifikasi (aman dijalankan sendiri, hanya membaca)

   Jalankan ini LEBIH DULU di production untuk membandingkan keadaannya
   sebelum memutuskan apa pun.
   ===================================================================== */
SELECT Id_Scheduler, Command, Cron_Expression, Flag_Aktif, Keterangan, Created_At
FROM N_HRIS_Scheduler_Configs
WHERE Command LIKE N'hris:absen-plan%'
ORDER BY Id_Scheduler;

-- Yang diharapkan sesudah BAGIAN 1 dan 2:
--   hris:absen-plan-rolling   Flag_Aktif = 'Y'   cron 0 12,22 * * *
--   hris:absen-plan-custom    Flag_Aktif = 'T'   (bertanggal Mei, dimatikan)
--   hris:absen-plan-cutoff    Flag_Aktif = 'Y'   cron 30 23 * * *

/* =====================================================================
   BAGIAN 4 — pengembalian

   UPDATE N_HRIS_Scheduler_Configs
   SET Flag_Aktif = 'Y',
       Keterangan = N'HRIS absensi - plan refresh cutoff harian malam'
   WHERE Command LIKE N'hris:absen-plan-custom%'
     AND Command LIKE N'%tgl_awal=2026-05-01%';

   UPDATE N_HRIS_Scheduler_Configs
   SET Command = N'hris:absen-plan-cutoff --kode_perusahaan=001 --refresh_mode=FORCE --request_source=SCHEDULED_CUTOFF_DAILY --requested_by=laravel-scheduler --priority=4',
       Cron_Expression = N'* * * * *',
       Flag_Aktif = 'T',
       Keterangan = N'HRIS absensi - plan refresh cutoff harian malam'
   WHERE Command LIKE N'hris:absen-plan-cutoff%';
   ===================================================================== */
