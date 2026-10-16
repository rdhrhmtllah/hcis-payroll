# DML-2026-176-pa-matriks-kompetensi-awal

| | |
|---|---|
| Change ID | DML-2026-176-pa-matriks-kompetensi-awal |
| Target | Web_HRIS (development); production lewat SQL Tracker, SESUDAH DDL-2026-175 |
| Data change | `database/dml/DML-2026-176-pa-matriks-kompetensi-awal/data-change.sql` |
| Checksum (sha256) | `e3e769fd6a95dd9f5b1f851bd814c04fe4f67ace1ce78ee2036cfb70af6d5dd1` |
| Status | TRACKER_PENDING (r1) |

Mengisi `N_PA_Competency_Level_Requirement` dari `N_PA_Competency_Requirement` aktif × kompetensi aktif
jenisnya, tingkat NULL. Idempoten (NOT EXISTS).

## Riwayat development
- 2026-10-08: 203 baris di Web_HRIS dev; putaran kedua 0 baris.
- Catatan dev: saat dijalankan, master dev sedang diubah sementara untuk UAT esai (CV01/CV02 aktif, CV04
  nonaktif), sehingga baris dev mengikuti keadaan itu. Production menurunkan dari keadaan master production.

## User tasks
Review → SQL Tracker → production → verifikasi jumlah baris.
