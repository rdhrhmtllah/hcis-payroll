/* ============================================================================
   DDL-2026-067  —  PEMBALIK: matikan kembali READ_COMMITTED_SNAPSHOT
   ----------------------------------------------------------------------------
   Sesuai database/ddl/README.md, berkas ini DOKUMENTASI untuk reset manual oleh
   developer. Agent TIDAK BOLEH menjalankannya.

   Kapan ini dipakai. Dua gejala yang membenarkan pembalikan, dan keduanya
   terukur — bukan perasaan:

     1. tempdb tumbuh tak terkendali. Pantau
        sys.dm_db_file_space_usage.version_store_reserved_page_count.
        Version store yang terus naik tanpa turun berarti ada transaksi yang
        dibiarkan terbuka lama; itu menahan versi lama supaya tetap hidup.

     2. Muncul galat 3960 ("Snapshot isolation transaction aborted due to
        update conflict"). Di bawah READ COMMITTED biasa ini mustahil, jadi
        kemunculannya berarti ada kode yang bergantung pada perilaku kunci lama.

   Mematikan RCSI menuntut akses eksklusif yang sama seperti menyalakannya, dan
   memutus koneksi dengan cara yang sama.
   ============================================================================ */

IF DB_NAME() <> N'Web_HRIS'
BEGIN
    RAISERROR (N'Dijalankan pada basis data yang salah: %s. Harus Web_HRIS.', 16, 1, DB_NAME());
    RETURN;
END;

ALTER DATABASE [Web_HRIS] SET READ_COMMITTED_SNAPSHOT OFF WITH ROLLBACK IMMEDIATE;

SELECT name                          AS basis_data,
       is_read_committed_snapshot_on AS rcsi
  FROM sys.databases
 WHERE name = N'Web_HRIS';
