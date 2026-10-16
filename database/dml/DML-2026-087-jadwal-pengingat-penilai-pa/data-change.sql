/* Change ID: DML-2026-087-jadwal-pengingat-penilai-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Scheduler_Configs <= 1, register the change <= 1.
   Dependencies: perintah pa:ingatkan-penilai sudah ada di kode.

   KENAPA BARIS TABEL, BUKAN SUNTINGAN KernEL.php

   Penjadwal proyek ini digerakkan tabel: N_HRIS_Scheduler_Configs dibaca
   App\Console\Kernel::schedule() dan didaftarkan satu per satu. Dua belas perintah lain
   sudah hidup dengan cara ini. Menuliskan jadwal ke dalam kode berarti membuat jalur
   kedua yang harus dijaga sama persis, dan yang mematikannya kelak harus tahu ada dua
   tempat.

   KENAPA PERINTAH, BUKAN DIPICU KEJADIAN

   Penugasan diberitahukan sekali saat dibentuk. Sesudah itu tidak ada kejadian apa pun
   yang bisa memicu pengingat - yang berubah hanyalah TANGGAL, dan hanya sesuatu yang
   berjalan menurut waktu yang bisa memperhatikannya.

   KENAPA 01:45

   Mengikuti tetangganya: sop:rule-scan 00:10, sop:reminder-scan 00:30, talent-pool
   01:00, sop:recert-scan 01:15. Menit 45 belum terpakai, sehingga pemindai-pemindai ini
   tidak berebut basis data pada menit yang sama. Pemberitahuan in-app bertahan sampai
   dibaca, jadi menjalankannya dini hari tidak menunda apa pun bagi penerimanya - ia
   membacanya saat membuka HCIS pagi harinya.

   AMAN DIJALANKAN BERULANG

   Kunci pemberitahuannya memuat tanggal, jadi perintah yang berjalan dua kali dalam
   sehari tidak menghasilkan dua pemberitahuan. Kernel juga sudah memasang
   withoutOverlapping(15) dan onOneServer() untuk seluruh baris di tabel ini.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50376, 'DML-2026-087 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

BEGIN TRANSACTION;

INSERT INTO N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan)
SELECT 'pa:ingatkan-penilai', '45 1 * * *', 'Asia/Jakarta', 'Y',
       'Pengingat in-app bagi penilai PA yang tenggatnya mendekat atau terlewat. Idempoten per hari.'
WHERE NOT EXISTS (
    SELECT 1 FROM N_HRIS_Scheduler_Configs WHERE Command = 'pa:ingatkan-penilai'
);

/* ---------- Verifikasi ---------- */
IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Scheduler_Configs
    WHERE Command = 'pa:ingatkan-penilai' AND Flag_Aktif = 'Y'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50377, 'DML-2026-087: jadwal pengingat PA tidak terpasang.', 1;
END;

/* Tepat satu. Command tidak punya unique constraint, dan dua baris identik berarti
   perintahnya berjalan dua kali tiap malam - tidak menggandakan pemberitahuan (kuncinya
   ber-tanggal), tetapi menggandakan beban basis data tanpa ada yang menyadarinya. */
IF (SELECT COUNT(*) FROM N_HRIS_Scheduler_Configs WHERE Command = 'pa:ingatkan-penilai') <> 1
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50378, 'DML-2026-087: jadwal pengingat PA lebih dari satu baris.', 1;
END;

/* ---------- Pencatatan change ---------- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change
               WHERE Kode_Change = 'DML-2026-087-jadwal-pengingat-penilai-pa')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-087-jadwal-pengingat-penilai-pa',
         'DML',
         'Jadwal harian pengingat penilai Performance Appraisal',
         'Mendaftarkan pa:ingatkan-penilai ke N_HRIS_Scheduler_Configs pada 01:45 Asia/Jakarta.',
         'database/dml/DML-2026-087-jadwal-pengingat-penilai-pa/data-change.sql',
         'docs/changes/DML-2026-087-jadwal-pengingat-penilai-pa/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Penjadwal proyek ini digerakkan tabel, bukan kode: Kernel::schedule() membaca N_HRIS_Scheduler_Configs dan mendaftarkan tiap baris dengan withoutOverlapping(15) dan onOneServer(). Menuliskan jadwal ke dalam kode akan membuat jalur kedua yang harus dijaga sama persis. Slot 01:45 dipilih karena menit 0/10/15/30 sudah dipakai pemindai lain pada jam yang sama. Perintahnya idempoten per hari - kunci pemberitahuannya memuat tanggal - sehingga dijalankan dua kali dalam sehari tidak menggandakan pemberitahuan.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
