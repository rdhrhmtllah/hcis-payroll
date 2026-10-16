# DDL-2026-168-pa-riwayat-hasil

## Metadata

- **Change ID**: `DDL-2026-168-pa-riwayat-hasil`
- **Target Database**: `Web_HRIS`
- **Type**: DDL
- **Revision**: `r1`
- **Forward Checksum (SHA-256)**: `50b0d9d0c5bc05c5fbb329d1fa966c34d8d2633d9f547eefce9576d92699ffda`
- **Affected Objects**: `N_PA_Result_History` (baru), `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`
- **Paths**: `database/ddl/DDL-2026-168-pa-riwayat-hasil/forward.sql`, `drop.sql`; dokumentasi tabel `docs/database/tables/kpi/n-pa-result-history.md`

## Deskripsi

Admin perlu menjawab "angka hasil ini berubah kapan, oleh siapa, dari berapa ke berapa".
`N_PA_Result` ditimpa di tempat setiap kali dihitung, sehingga KPI, Final, grade, dan
status versi lama hilang. Tabel baru `N_PA_Result_History` memotret baris itu sesudah
setiap hitung, hitung ulang, hitung ulang Balancing, serta kunci/buka kurva.
`N_PA_Result` tidak berubah bentuk. Tidak ada DML; riwayat mulai terisi sejak perubahan
ini berjalan, dan versi lama ditampilkan dari data anak yang sudah ada.

## Eksekusi development

- 2026-10-07, `Web_HRIS @ instance-20240809-031237` (diverifikasi lewat `DB_NAME()` + `@@SERVERNAME`).
- Sebelum: tabel tidak ada, ledger kosong.
- `forward.sql` 3 batch OK; sesudah: tabel 19 kolom sesuai rancangan, indeks ada, ledger 1 baris.
- Dijalankan ulang: no-op.

## Agent completed

- Artefak forward + drop (drop tidak dijalankan), eksekusi dan verifikasi di development, entri `database/ddl/status.json`, dokumentasi tabel.
- Aplikasi: `PaResultHistoryService`, pemanggilan di perhitungan/Balancing/kurva, endpoint riwayat, tab Riwayat Perhitungan; uji `RiwayatHasilTest` dan `RantaiPenuhTest`.

## User tasks

1. Tinjau `forward.sql` dan catat **persis** isinya di SQL Tracker (checksum di atas).
2. Jalankan ke production lewat SQL Tracker **sebelum** kode ini dirilis ke production (kode menulis ke tabel ini saat menghitung), lalu verifikasi tabel ada.
