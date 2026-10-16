# DDL-2026-063 — Indeks performa modul SOP

| | |
|---|---|
| Change ID | `DDL-2026-063-sop-indeks-performa` |
| Tipe | DDL (hanya `CREATE INDEX`) |
| Basis data target | `Web_HRIS` |
| Lingkungan eksekusi agen | development, diverifikasi `SELECT DB_NAME()` |
| Tanggal eksekusi dev | 2026-09-01 |
| Forward | `database/ddl/DDL-2026-063-sop-indeks-performa/forward.sql` |
| Drop (alat bantu manual) | `database/ddl/DDL-2026-063-sop-indeks-performa/drop.sql` |
| DML | tidak ada |
| Checksum forward.sql (sha256) | `3cc857b97dcaac74c0023b9db0b3216fb16685a546d5940e7dff27d2c97242a2` |
| Spec | `docs/superpowers/specs/2026-09-01-sop-performa-atomicity-design.md` §6f |

## Mengapa sekarang

Modul SOP/DMS belum naik produksi, sehingga tabel `N_SOP_*` praktis kosong dan
menambah indeks berbiaya nol. Menambahkannya nanti, setelah tabel berisi jutaan
baris, menuntut jendela pemeliharaan. Ini keuntungan sekali pakai.

Pengukuran yang mendasarinya (read-only, `sys.indexes` / `sys.partitions`,
2026-09-01): `N_SOP_Progress` 1.156 baris, `N_SOP_Penjadwalan_Detail` 317,
`N_SOP_Kepatuhan_Riwayat` 108. Basis data berjalan dengan
`READ_COMMITTED_SNAPSHOT = OFF`, sehingga setiap kueri yang memindai menahan
pembaca lain.

## Objek yang dibuat

| Indeks | Tabel | Kunci / INCLUDE | Kueri yang dilayani |
|---|---|---|---|
| `IX_N_SOP_Rwt_Waktu` | `N_SOP_Kepatuhan_Riwayat` | `(Waktu_Peristiwa)` INCLUDE `(Peristiwa, Id_SOP_Paket, Kode_Karyawan, Id_SOP_Penjadwalan_Detail)` | `SopMonitoringAnalyticsService::tren()` menyaring rentang `Waktu_Peristiwa`. Tabel ini **sama sekali belum berindeks pada kolom itu**, padahal ia yang tercepat tumbuh (ledger kepatuhan) |
| `IX_N_SOP_Rwt_Detail_Peristiwa` | `N_SOP_Kepatuhan_Riwayat` | `(Id_SOP_Penjadwalan_Detail, Peristiwa)` | Tanda "Diperbarui" di SOP Saya — indeks lama hanya memimpin `Id_SOP_Penjadwalan_Detail` sehingga `Peristiwa` menuntut lookup |
| `IX_N_SOP_PjdD_Karyawan_Aktif` | `N_SOP_Penjadwalan_Detail` | `(Kode_Karyawan, Flag_Aktif, Flag_Cabut_Akses)` INCLUDE `(Id_SOP_Penjadwalan, Status_Paket, Tanggal_Selesai, Created_At, Updated_At)` | `SopProgressReadService::tugasSaya()` — jalur terpanas sisi karyawan. Indeks lama `(Kode_Karyawan, Status_Paket)` tak mencakup kedua flag yang **selalu** ikut disaring |
| `IX_N_SOP_Prg_Detail_Status` | `N_SOP_Progress` | `(Id_SOP_Penjadwalan_Detail, Status)` INCLUDE `(Id_SOP_Paket_Item, Id_SOP_Item_Versi, Waktu_Buka, Waktu_Konfirmasi)` | Agregat kartu SOP Saya |
| `IX_N_SOP_Paket_Publish` | `N_SOP_Master_Paket` | `(Tanggal_Publish)` | Berpasangan dengan perbaikan sargability di `SopPaketService` — sebelumnya `whereDate()` membungkus kolom dalam CAST sehingga indeks apa pun tak terpakai |

## Yang sengaja TIDAK dibuat

`UQ_N_SOP_Progress (Id_SOP_Penjadwalan_Detail, Id_SOP_Paket_Item, Id_SOP_Item_Versi)`
sudah melayani `NOT EXISTS` pada assign set-based dan predikat reconcile dengan
tepat. `UX_N_SOP_PjdD_Aktif` yang terfilter sudah cocok dengan predikat reconcile.
Menambah indeks di sana hanya menambah biaya tulis tanpa pembaca baru.

## Yang sengaja TIDAK dihapus

`IX_N_SOP_PjdD_Karyawan (Kode_Karyawan, Status_Paket)` tergantikan oleh
`IX_N_SOP_PjdD_Karyawan_Aktif`, tetapi tidak dihapus. Aturan agen melarang agen
menjalankan `DROP INDEX`, dan lebih dari itu: indeks lama mungkin masih melayani
kueri yang belum terlihat dari audit ini. Pernyataan penghapusannya tersedia di
`drop.sql` sebagai catatan, dengan syarat diamati dulu lewat
`sys.dm_db_index_usage_stats` setelah beban nyata berjalan.

## Dikerjakan agen

- Menulis `forward.sql` dan `drop.sql`.
- Menjalankan `forward.sql` terhadap `Web_HRIS` (development) setelah memverifikasi
  target dengan `SELECT DB_NAME()`.
- Memverifikasi kelima indeks terbentuk lewat `sys.indexes` — kelimanya `ADA`.
- Mencatat perubahan ini di `database/ddl/status.json`.

## Tersisa untuk pemilik project

1. Tinjau dan setujui `forward.sql`.
2. Catat ke SQL Tracker dengan isi dan checksum yang **identik** dengan yang diuji.
3. Eksekusi ke produksi lewat SQL Tracker, lalu konfirmasikan agar status
   `production` dapat dinaikkan. Status itu **tidak boleh** disimpulkan dari hasil
   development.
4. Verifikasi pasca-produksi, lalu naikkan status `verification`.

## Catatan pembuktian

Efek indeks **tidak dapat dibuktikan di development**: pada tabel berisi puluhan
sampai seribuan baris, pengoptimal akan memilih scan apa pun indeksnya, dan
rencana eksekusi di sini tidak mewakili produksi. Yang dibuktikan agen hanyalah
indeksnya terbentuk dan kueri yang bersangkutan tidak lagi membungkus kolom dalam
fungsi. Pembuktian sesungguhnya menuntut beban nyata di staging atau produksi.
