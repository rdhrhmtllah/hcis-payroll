/* Change ID: DML-2026-103-jadwalkan-pa-sapu-tertahan
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: tidak ada.
   Scope: N_HRIS_Scheduler_Configs — SATU baris baru.

   YANG DISELESAIKAN

   Perintah `pa:sapu-tertahan` sudah ada dan sudah bekerja, tetapi tidak pernah
   dijadwalkan. Akibatnya ia hanya berjalan bila seseorang menekan tombol "Hitung Ulang
   yang Tertahan" di layar admin — dan tidak ada yang memberi tahu admin bahwa ada yang
   perlu ditekan.

   Yang disapunya: hasil PA yang TERTAHAN karena KPI pesertanya belum disetujui saat
   penilaian selesai. Urutannya memang sering terbalik di lapangan — penilai 360 selesai
   lebih dulu, persetujuan KPI menyusul beberapa hari kemudian. Tanpa penyapu berkala,
   hasil peserta itu menggantung sampai ada yang kebetulan menekan tombolnya, dan yang
   paling mungkin menyadarinya adalah pesertanya sendiri, saat bertanya kenapa nilainya
   belum keluar.

   Peserta yang sebabnya belum hilang akan TERTAHAN lagi — tanpa efek samping selain
   nomor versi yang naik. Yang sudah TERBIT tidak tersentuh sama sekali.

   KENAPA JAM KERJA, BUKAN DINI HARI

   Cron-nya `20 7-19 * * *` — tiap jam pada menit ke-20, pukul 07.00-19.00.

   Hampir semua penyapu lain di tabel ini berjalan tengah malam, dan itu masuk akal untuk
   pekerjaan yang sumbernya juga berubah tengah malam. Yang ini berbeda: pemicunya adalah
   PERSETUJUAN KPI, dan persetujuan hanya terjadi saat orang bekerja. Menjalankannya pukul
   02.00 berarti setiap KPI yang disetujui pukul 09.00 menunggu tujuh belas jam untuk
   sesuatu yang perhitungannya memakan detik.

   Menit ke-20 dipilih supaya tidak berimpit dengan `hris:api-nonce-prune` (menit 15) dan
   `hris:absen-maintenance` (tiap 15 menit, menit 0/15/30/45).

   Beban per jalan kecil: hanya siklus berstatus OPEN/CLOSED yang dilihat, dan hanya
   peserta bertanda TERTAHAN — pada hari biasa jumlahnya nol dan perintahnya langsung
   selesai. `--batas=50` membatasi peserta per siklus sekali jalan, jadi lonjakan pun
   terpotong menjadi beberapa putaran alih-alih satu proses panjang.

   Scheduler membungkusnya dengan `withoutOverlapping(15)` dan `onOneServer()` di
   app/Console/Kernel.php, jadi jadwal per jam tidak bisa menumpuk.

   IDEMPOTEN

   Dijaga NOT EXISTS pada Command yang sama; menjalankannya berulang tidak menggandakan
   baris. Bila barisnya sudah ada tetapi nonaktif, ia diaktifkan kembali beserta cron-nya
   — supaya "sudah pernah dimatikan orang" tidak diam-diam mengalahkan maksud script ini.

   CARA MEMBATALKAN

       UPDATE N_HRIS_Scheduler_Configs
       SET Flag_Aktif = 'T'
       WHERE Command = 'pa:sapu-tertahan';

   Menonaktifkan, bukan menghapus: barisnya menyimpan cron yang sudah disepakati, dan
   menghapusnya membuang keputusan itu bersama datanya.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

DECLARE @Command varchar(255) = 'pa:sapu-tertahan';
DECLARE @Cron varchar(100) = '20 7-19 * * *';
DECLARE @Ket varchar(255) = N'Hitung ulang hasil PA yang tertahan karena KPI-nya menyusul disetujui.';

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Scheduler_Configs WHERE Command = @Command
)
BEGIN
    INSERT INTO N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan)
    VALUES (@Command, @Cron, 'Asia/Jakarta', 'Y', @Ket);
END
ELSE
BEGIN
    UPDATE N_HRIS_Scheduler_Configs
    SET Cron_Expression = @Cron,
        Timezone        = 'Asia/Jakarta',
        Flag_Aktif      = 'Y',
        Keterangan      = @Ket
    WHERE Command = @Command;
END;

COMMIT TRANSACTION;

/* VERIFIKASI — harus mengembalikan tepat satu baris, aktif 'Y'. */
SELECT Id_Scheduler, Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan
FROM N_HRIS_Scheduler_Configs
WHERE Command = 'pa:sapu-tertahan';
