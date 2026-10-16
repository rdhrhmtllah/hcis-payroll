# DDL-2026-023 - Soft status master Visitor

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-023-add-visitor-master-soft-status` |
| Target | `Web_HRIS` development pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Deskripsi | Menambahkan status aktif/nonaktif agar master warna kendaraan dan keperluan tamu tidak perlu dihapus secara fisik. |
| Owning module | `VISITOR` (`Id_Grup_Fitur=4`) |
| Page mapping | `visitorMasterDataPage`, `/admin/visitor/master-data` |
| Verifikasi ownership | Development `Web_HRIS`, 2026-08-24 |
| Objek | `N_EMI_VISITOR_Master_Vehicle_Color.Flag_Aktif`, `N_EMI_VISITOR_Master_Purpose.Flag_Aktif` |
| Forward | `database/ddl/DDL-2026-023-add-visitor-master-soft-status/forward.sql` |
| Drop manual | `database/ddl/DDL-2026-023-add-visitor-master-soft-status/drop.sql` |
| DML | `database/dml/DDL-2026-023-add-visitor-master-soft-status/data-change.sql` |
| Checksum SHA-256 | `e3f010bfb218acfb30c55566fcc597a5c34d4a8ca5e1e16e5d58a268167563e2` |

## Dampak dan desain

- Kedua kolom menggunakan `char(1) NOT NULL` dengan nilai awal/default `Y`, sama dengan pola status master Visitor existing.
- Seluruh 7 warna kendaraan dan 5 keperluan existing tetap tersimpan dan tetap aktif setelah forward DDL.
- Nonaktif berarti tidak ditawarkan dan tidak diterima pada transaksi baru; histori tetap dapat membaca label master lama.
- Tidak ada index baru karena kedua tabel berukuran sangat kecil dan filter status tidak memerlukan optimasi terpisah.
- Log mutasi aplikasi `VISITOR_MASTER_DATA_MUTATED` tetap mencatat operasi, master, ID entitas, dan user pelaku. Perubahan ini tidak menambahkan tabel audit baru.

## Agent completed

- Memverifikasi koneksi live sebagai `APP_ENV=local`, database `Web_HRIS`.
- Memverifikasi ownership module VISITOR dan mapping aktif `visitorMasterDataPage`.
- Memverifikasi schema serta jumlah awal: 7 warna kendaraan dan 5 keperluan tamu, keduanya belum memiliki `Flag_Aktif`.
- Menyiapkan forward DDL, drop manual, dan companion DML kosong.
- Menjalankan exact forward DDL ber-checksum di development `Web_HRIS`.
- Memverifikasi dua kolom `char(1) NOT NULL DEFAULT ('Y')`, dua default constraint, 7 warna aktif, dan 5 keperluan aktif.
- Mengaktifkan status pada catalog Master Data serta filter dan tombol aktif/nonaktif.
- Memfilter master nonaktif dari form Permit/Tamu dan menambahkan validasi backend untuk transaksi baru.
- Mempertahankan query histori dan Verifikator tanpa filter status agar snapshot/referensi transaksi lama tetap terbaca.

## User tasks

- Mencatat exact forward SQL dan checksum yang sama ke SQL Tracker.
- Menjalankan perubahan secara manual ke production melalui SQL Tracker.
- Memverifikasi schema dan perilaku production sebelum status akhir diubah menjadi `COMPLETED`.
