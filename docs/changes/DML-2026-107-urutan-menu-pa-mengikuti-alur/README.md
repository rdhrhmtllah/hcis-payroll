# DML-2026-107 — Urutan menu PA mengikuti urutan kerja & Hide legacy Paket Soal

| | |
|---|---|
| Change ID | `DML-2026-107-urutan-menu-pa-mengikuti-alur` |
| Revisi | `r2` (2026-09-07) |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-107-urutan-menu-pa-mengikuti-alur/data-change.sql` |
| SHA256 Checksum | `0ceb47bc53ff0c69e9c073c72a5baccc383b609613b6e613b1b16603d9b828e9` |
| Bergantung pada | DML-2026-089, DML-2026-099, DML-2026-101 (pendaftaran menunya) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

1. **Urutan menu adalah kebalikan dari urutan kerja.** Dashboard — layar yang baru punya isi
   **sesudah** semuanya selesai — berada di paling atas, dan tujuh menu master yang harus
   diisi **pertama** berada di paling bawah.
2. **Menu legacy `Master · Paket Soal` (/performance-appraisal/master/paket-soal) membingungkan.**
   Bank soal kini langsung terintegrasi dan dikelola terpusat dari HC Learn, dan pemetaan default
   per level telah dicover oleh `Master · Paket Soal Baku` (/performance-appraisal/master/ikatan-paket-soal).
   Menu standalone `Master · Paket Soal` di sidebar disembunyikan (`Is_Show_Sidebar = 'T'`).

| | Sebelum | Sesudah (r2) |
|---|---|---|
| 1 | Dashboard PA | Master · Jenis Kompetensi |
| 2 | Siklus Penilaian | Master · Kompetensi |
| 3 | Pemantauan & Verifikasi | Master · Dimensi per Level |
| 4 | Kalibrasi Grade | Master · Aturan Penilai |
| 5 | Hasil Penilaian | Master · Bobot Pilar |
| 6 | HAV Map (9-Box) | Master · Pita Nilai |
| 7 | Distribusi Nilai | Master · Paket Soal Baku |
| 8 | Riwayat Penilaian | Siklus Penilaian |
| 9 | Master · Jenis Kompetensi | Pemantauan & Verifikasi |
| 10 | Master · Kompetensi | Hasil Penilaian |
| 11 | Master · Aturan Penilai | Distribusi Nilai |
| 12 | Master · Bobot Pilar | HAV Map (9-Box) |
| 13 | Master · Pita Nilai | Kalibrasi Grade |
| 14 | Master · Paket Soal | Riwayat Penilaian |
| 15 | Master · Dimensi per Level | **Dashboard PA** |
| 16 | Master · Paket Soal Baku | *(Master · Paket Soal di-hide dari sidebar)* |

## Riwayat Revisi

- **r1 (2026-09-07)**: Pengurutan awal 16 menu PA.
- **r2 (2026-09-07)**: Menyembunyikan menu `/performance-appraisal/master/paket-soal` (`Is_Show_Sidebar = 'T'`) atas instruksi pemilik karena bank soal sudah dicover HC Learn, dan merapatkan nomor urut 15 menu aktif sidebar menjadi 1–15 tanpa jeda.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Ke-15 menu aktif sidebar terurut 1–15, `Is_Show_Sidebar = 'T'` untuk `paket-soal`, kedua penjaga lolos, dan `php artisan cache:clear` dijalankan.

