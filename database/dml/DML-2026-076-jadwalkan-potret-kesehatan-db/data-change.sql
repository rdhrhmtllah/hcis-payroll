-- DML-2026-076-jadwalkan-potret-kesehatan-db
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- BAGIAN 1 : daftarkan db:health-snapshot ke penjadwal (dev dan production)
-- BAGIAN 2 : pengembalian
--
-- Perintahnya sudah ada di aplikasi sejak lama dan dirancang untuk dijalankan
-- terjadwal ("OUT-OF-BAND (proses terjadwal terpisah)" di docblock-nya), tetapi
-- tidak pernah didaftarkan. Akibatnya N_HRIS_DB_Health_Snapshot hanya berisi 8
-- baris dari percobaan manual 4 September 2026 (Context_Info = 'KORBAN'), dan
-- pertanyaan "transaksi MANA yang mengunci" tak pernah bisa dijawab dengan
-- bukti — hanya dengan penalaran.

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* =====================================================================
   BAGIAN 1 — Pendaftaran penjadwalan

   KENAPA TIAP MENIT.
   Episode blocking yang benar-benar menyakitkan berumur pendek: yang
   tercatat di N_HRIS_Request_Diagnostics berkisar 13,7 dan 17,1 detik.
   Interval yang lebih longgar akan melewatkan sebagian besar di antaranya.

   APA YANG DIBAYAR UNTUK ITU.
   Tiap menit: satu kunci penjadwal (withoutOverlapping/onOneServer, lewat
   cache database) plus SATU kueri DMV. Tidak lebih. Perintahnya BERGERBANG —
   ia menulis HANYA bila ada sesi yang benar-benar terblok atau jumlah
   permintaan aktif melewati ambang (baku 20). Pada sistem yang sedang sehat
   ia tidak menulis satu baris pun dan tidak mencetak apa pun ke
   scheduler.log.

   Ini bukan pemantauan realtime. Pembacaan DMV berulang-ulang yang
   BERBAHAYA di produksi adalah Layer 4 (DbHealthWatch), yang tetap mati dan
   tetap dijaga GerbangDiagnostikRealtime. Yang didaftarkan di sini Layer 3.
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Scheduler_Configs WHERE Command = N'db:health-snapshot')
BEGIN
    INSERT INTO N_HRIS_Scheduler_Configs
        (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
    VALUES
        (N'db:health-snapshot', N'* * * * *', N'Asia/Jakarta', 'Y',
         N'Potret rantai blocking SQL Server (Layer 3). Menulis hanya saat ADA blocking.',
         SYSDATETIME());

    PRINT 'db:health-snapshot didaftarkan.';
END
ELSE
BEGIN
    PRINT 'db:health-snapshot sudah terdaftar; tidak ada perubahan.';
END

-- Verifikasi. Diharapkan tepat 1 baris, Flag_Aktif = 'Y'.
SELECT Id_Scheduler, Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan
FROM   N_HRIS_Scheduler_Configs
WHERE  Command = N'db:health-snapshot';

/* =====================================================================
   BAGIAN 2 — Pengembalian

   Matikan lebih dulu (Flag_Aktif = 'T'); Kernel hanya memuat baris 'Y',
   jadi ini sudah cukup menghentikannya tanpa kehilangan jejak bahwa ia
   pernah didaftarkan. Hapus barisnya hanya bila memang tak diinginkan lagi.
   ===================================================================== */

-- UPDATE N_HRIS_Scheduler_Configs SET Flag_Aktif = 'T' WHERE Command = N'db:health-snapshot';
--
-- DELETE FROM N_HRIS_Scheduler_Configs WHERE Command = N'db:health-snapshot';
