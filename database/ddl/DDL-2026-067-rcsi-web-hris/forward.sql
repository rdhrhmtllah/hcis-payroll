/* ============================================================================
   DDL-2026-067  —  Nyalakan READ_COMMITTED_SNAPSHOT pada Web_HRIS
   ----------------------------------------------------------------------------
   JANGAN JALANKAN TANPA MEMBACA docs/changes/DDL-2026-067-rcsi-web-hris/README.md
   lebih dulu. Perubahan ini MEMUTUS SEMUA KONEKSI yang sedang aktif.

   Ringkas alasannya (bukti lengkap ada di README):
     dm_os_wait_stats selama 507 jam mencatat LCK_M_S — kunci PEMBACA —
     12.785 detik pada 8.194 penantian, rata-rata 1,56 detik. Penulisnya
     (LCK_M_X) hanya 428 detik. Pembaca menunggu 30x lebih lama daripada
     penulis: itu tanda tangan RCSI mati, bukan cacat satu endpoint.

   PRASYARAT YANG TIDAK BOLEH DILEWATI. Version store RCSI tinggal di tempdb.
   Saat berkas ini ditulis, tempdb Web_HRIS punya SATU berkas data 264 MB di
   server 2 inti. Menyalakan RCSI tanpa membenahi tempdb memindahkan tekanan,
   bukan menghapusnya. Lihat bagian "Prasyarat" di README.
   ============================================================================ */

/* Langkah 1 — pastikan target BENAR. Berhenti bila bukan Web_HRIS.
   Ini bukan formalitas: host yang sama memuat basis data aplikasi lain. */
IF DB_NAME() <> N'Web_HRIS'
BEGIN
    RAISERROR (N'Dijalankan pada basis data yang salah: %s. Harus Web_HRIS.', 16, 1, DB_NAME());
    RETURN;
END;

/* Langkah 2 — bila sudah menyala, tidak ada yang perlu dikerjakan. */
IF EXISTS (SELECT 1 FROM sys.databases
            WHERE name = N'Web_HRIS' AND is_read_committed_snapshot_on = 1)
BEGIN
    PRINT N'READ_COMMITTED_SNAPSHOT sudah menyala — tidak ada perubahan.';
    RETURN;
END;

/* Langkah 3 — nyalakan.

   WITH ROLLBACK IMMEDIATE dipakai dengan sadar: pernyataan ini menuntut akses
   eksklusif ke basis data, dan tanpa klausa itu ia akan MENGGANTUNG tanpa batas
   menunggu koneksi terakhir pergi — pada basis data yang dipakai bersama,
   itu berarti menggantung selamanya.

   Harganya: setiap transaksi yang sedang berjalan DI-ROLLBACK dan setiap
   koneksi diputus. Jalankan hanya pada jendela pemeliharaan yang diumumkan.

   Bila ingin gagal-cepat alih-alih memutus orang, ganti dengan WITH NO_WAIT:
   pernyataan akan gagal bila ada koneksi lain, dan tidak ada yang terputus. */
ALTER DATABASE [Web_HRIS] SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE;

/* Langkah 4 — buktikan hasilnya, jangan diasumsikan. */
SELECT name                            AS basis_data,
       is_read_committed_snapshot_on   AS rcsi,
       snapshot_isolation_state_desc   AS snapshot_isolasi
  FROM sys.databases
 WHERE name = N'Web_HRIS';
