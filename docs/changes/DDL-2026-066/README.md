# DDL-2026-066 — Indeks performa modul SOP (gabungan)

| | |
|---|---|
| Change ID | `DDL-2026-066-sop-indeks-performa-gabungan` |
| Tipe | DDL (hanya `CREATE INDEX`) |
| Basis data target | `Web_HRIS` |
| Lingkungan eksekusi agen | development, diverifikasi `SELECT DB_NAME()` |
| Tanggal verifikasi dev | 2026-09-01 |
| Forward | `database/ddl/DDL-2026-066-sop-indeks-performa-gabungan/forward.sql` |
| Drop (alat bantu manual) | `database/ddl/DDL-2026-066-sop-indeks-performa-gabungan/drop.sql` |
| DML | tidak ada |
| Checksum forward.sql (sha256) | `8a7993d87aaa5fe3dfb4006320d879665fa7dcf7c7f0ee83969a7d36ae08462e` |
| **Menggantikan** | `DDL-2026-063`, `DDL-2026-064`, `DDL-2026-065` |

## Mengapa digabung

Ketiga change-id itu lahir dari satu rangkaian audit yang sama dan seluruhnya
menyentuh indeks pada tabel `N_SOP_*`. Merekamnya sebagai tiga entri berarti tiga
kali pencatatan SQL Tracker, tiga checksum, dan tiga eksekusi produksi untuk satu
pekerjaan. Digabung menjadi satu rekaman atas permintaan pemilik project.

**Ketiga change-id lama TIDAK dihapus.** Keduanya tetap ada di repo dan di ledger
sebagai jejak audit — statusnya `development` tetap `DEV_EXECUTED` karena itu
memang yang terjadi; hanya jalur SQL Tracker dan produksinya yang ditandai
digantikan berkas ini.

## Isi

Tujuh indeks, **tidak satu pun pernyataannya diubah** dari berkas asalnya:

| # | Indeks | Tabel | Asal |
|---|---|---|---|
| 1 | `IX_N_SOP_Rwt_Waktu` | `N_SOP_Kepatuhan_Riwayat` | 063 |
| 2 | `IX_N_SOP_Rwt_Detail_Peristiwa` | `N_SOP_Kepatuhan_Riwayat` | 063 |
| 3 | `IX_N_SOP_PjdD_Karyawan_Aktif` | `N_SOP_Penjadwalan_Detail` | 063 |
| 4 | `IX_N_SOP_Prg_Detail_Status` | `N_SOP_Progress` | 063 |
| 5 | `IX_N_SOP_Paket_Publish` | `N_SOP_Master_Paket` | 063 |
| 6 | `IX_N_SOP_Prg_Waktu_Buka` | `N_SOP_Progress` | 064 |
| 7 | `IX_N_SOP_Prg_Item_Status` | `N_SOP_Progress` | 065 |

Setiap pernyataan digerbangi `IF NOT EXISTS` sehingga berkas ini aman dijalankan
ulang, dan satu tahap yang gagal tidak menjatuhkan sisanya.

## Bagaimana berkas ini diverifikasi — dan batasnya

Ketujuh indeks **sudah ada di dev** ketika berkas gabungan ini dibuat, sehingga
menjalankannya di sana hanya membuktikan ia tidak error. Itu tidak cukup untuk
memenuhi syarat "yang direkam ke Tracker identik dengan yang diuji".

Karena itu verifikasinya ditempuh dari arah lain: **definisi ketujuh indeks di
basis data dibandingkan terhadap yang dideklarasikan skrip** — nama tabel, urutan
kolom kunci, kolom `INCLUDE`, dan keberadaan filter. Hasilnya **ketujuhnya cocok
persis**.

Jadi yang terbukti adalah: setiap pernyataan pernah dieksekusi dan diverifikasi di
dev lewat change-id asalnya (063/064/065), dan berkas gabungan ini menggambarkan
kenyataan basis data itu tanpa penyimpangan.

**Yang belum terbukti, dan disebut apa adanya:** jalur `CREATE` pada berkas
gabungan ini belum pernah dijalankan dari keadaan kosong, karena agen dilarang
menghapus indeks untuk mengujinya. Di produksi — tempat ketujuh indeks belum ada —
jalur itulah yang akan berjalan.

## Hasil terukur yang mendasarinya (dev, 2026-09-01)

| Panel | Sebelum | Sesudah |
|---|---|---|
| Pemahaman (`bacaVsEstimasi`) | 1.624 reads, Clustered Index Scan | **28 reads, Index Seek** |
| Titik Henti (`penyerapanVersi`) | 1.144 reads, Clustered Index Scan | **162 reads, Index Seek** |

`IX_N_SOP_Rwt_Waktu` juga terbukti dipakai `peringkatUnit()`.

## Prasyarat sebelum eksekusi produksi

Keempat tabel sasaran — `N_SOP_Kepatuhan_Riwayat`, `N_SOP_Master_Paket`,
`N_SOP_Penjadwalan_Detail`, `N_SOP_Progress` — **harus sudah ada di produksi**.
Modul SOP belum naik produksi saat berkas ini ditulis, jadi kemungkinan besar
belum. `CREATE INDEX` pada tabel yang tidak ada akan gagal; jalankan setelah skema
SOP-nya naik.

Bila tabelnya ada tetapi masih kosong, ketujuh indeks terbentuk seketika tanpa
mengunci apa pun.

## Yang sengaja TIDAK dihapus

`IX_N_SOP_PjdD_Karyawan` dan `IX_N_SOP_Prg_Item` menjadi berlebihan setelah indeks
baru terpasang, tetapi tidak dihapus. Aturan agen melarang `DROP INDEX`, dan lebih
dari itu: keduanya lebih ramping dan mungkin masih dipilih pengoptimal untuk kueri
lain. Pernyataan penghapusannya tersedia di `drop.sql` sebagai catatan, dengan
syarat diamati dulu lewat `sys.dm_db_index_usage_stats` setelah beban nyata.

## Dikerjakan agen

- Merakit `forward.sql` dan `drop.sql` dari ketiga berkas asal tanpa mengubah satu
  pernyataan pun.
- Menjalankan berkas gabungan terhadap `Web_HRIS` (development): 7 batch, nol galat.
- Membandingkan definisi ketujuh indeks di `sys.indexes` terhadap deklarasi skrip —
  ketujuhnya cocok.
- Menandai 063/064/065 sebagai digantikan pada `database/ddl/status.json`, tanpa
  menghapusnya.

## Tersisa untuk pemilik project

1. Tinjau dan setujui `forward.sql`.
2. Pastikan keempat tabel sasaran sudah ada di produksi.
3. Catat ke SQL Tracker dengan isi dan checksum yang identik — **satu rekaman**,
   menggantikan tiga.
4. Eksekusi ke produksi, lalu konfirmasikan agar status `production` dapat
   dinaikkan. Status itu tidak boleh disimpulkan dari hasil development.
