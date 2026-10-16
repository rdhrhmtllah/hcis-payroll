-- Change ID: DML-2026-078-visitor-expire-tiap-5-menit
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
--
-- visitor:expire-efektif berjalan harian ('5 0 * * *') sejak proyeksi Efektif
-- dibuat, karena masa berlaku permit dan tamu ketika itu murni per-hari.
--
-- Permit COKLAT berlaku dalam hitungan JAM dan berakhir juga saat tamunya
-- tercatat keluar. Dengan ketelitian harian, permit yang jendelanya sudah habis
-- pukul 12:00 tetap berstatus AKTIF di gerbang sampai lewat tengah malam —
-- tanpa satu pun galat yang menyalakannya.
--
-- Konsekuensi yang diterima sadar: permit mati paling lambat 5 menit setelah
-- jam selesai atau setelah tamu tercatat keluar, bukan seketika.
--
-- Idempoten.
--
-- Keterangan dijaga <= 100 karakter: kolomnya varchar(100), dan SQL Server
-- menolak seluruh UPDATE begitu satu nilai melebihinya.

UPDATE N_HRIS_Scheduler_Configs
   SET Cron_Expression = '*/5 * * * *',
       Keterangan = 'Expire Efektif visitor: permit karyawan per hari, permit coklat per jam & saat keluar (tiap 5 mnt)'
 WHERE Command = 'visitor:expire-efektif';
GO
