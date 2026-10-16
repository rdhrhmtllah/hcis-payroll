# DML-2026-094 — Master global PA keluar dari grup Konfigurasi Cycle

| | |
|---|---|
| Change ID | `DML-2026-094-pa-master-global-keluar-dari-konfigurasi-cycle` |
| Jenis | DML |
| Artifact | `database/dml/DML-2026-094-pa-master-global-keluar-dari-konfigurasi-cycle/data-change.sql` |
| Bergantung pada | DML-2026-093 (grup menu PA), DDL-2026-085 (halaman penyiapan) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Grup **Konfigurasi Cycle** memuat dua butir yang namanya menjanjikan setelan per siklus,
tetapi alamatnya menunjuk master global — dan namanya bahkan **tertukar** dengan alamatnya:

| Nama menu | Alamat sebenarnya |
|---|---|
| Bobot Penilaian | `/performance-appraisal/master/aturan-penilai` |
| Pengaturan KPI | `/performance-appraisal/master/bobot-pilar` |

Admin yang membuka "Bobot Penilaian" untuk menyetel siklus 2026 sebenarnya sedang menyunting
cetakan yang dipakai **seluruh** siklus, termasuk yang sudah berjalan — dan tidak ada apa pun
di layar yang mengatakan itu.

## Penyelesaiannya

Setelan per siklus kini punya tempatnya sendiri: halaman penyiapan berlangkah di
`/performance-appraisal/siapkan/{id}`, yang menyunting **salinan** milik siklus. Dua butir
ini dikembalikan ke sifat aslinya dan dipindah ke Master Data dengan nama yang menyebut apa
adanya.

**Tidak ada halaman yang dihapus.** Keduanya tetap dibutuhkan: salinan siklus lahir dari
master ini, dan siklus baru tidak punya sumber salinan kalau masternya hilang.

**Tidak ada hak yang berubah.** `HRIS_Page_Access` tidak disentuh sama sekali.

## Hasil

| Grup | Sebelum | Sesudah |
|---|---|---|
| Konfigurasi Cycle | 4 butir | **2** — Assessment Cycle, Assignment Penilai |
| Master Data | 4 butir | **6** — ditambah Aturan Penilai (Master), Bobot Pilar (Master) |

## Penjaga

Skrip menuntut Konfigurasi Cycle tepat 2, Master Data tepat 6, nol urutan kembar per header,
dan nol halaman admin yang ditandai global. Semuanya lolos saat dijalankan di dev.

## Satu hal tentang batch

Seluruh isi transaksi berada dalam **satu batch** — tidak ada `GO` antara `BEGIN` dan
`COMMIT`. Percobaan pertama gagal justru karena ini: `GO` mengakhiri batch, transaksinya
digulung balik, dan sisa skripnya akan berjalan tanpa perlindungan. Kegagalan di tengah akan
meninggalkan menu setengah berubah.

## WAJIB diikuti

`InertiaShellService::flushMenuCache()` — menu di-cache per perusahaan.
