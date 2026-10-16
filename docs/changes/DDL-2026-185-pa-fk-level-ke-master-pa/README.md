# DDL-2026-185-pa-fk-level-ke-master-pa

| | |
|---|---|
| Change ID | DDL-2026-185-pa-fk-level-ke-master-pa |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | KPI / Performance Appraisal / Master Data |
| Artefak | `database/ddl/DDL-2026-185-pa-fk-level-ke-master-pa/forward.sql` |
| Desain | `docs/superpowers/specs/2026-10-13-pa-master-level-pa-design.md` |
| Checksum (sha256) | `956290767c982839c3d3d52af2bebc45c6e05f344c019b19d21f76fa20b4eb4e` |
| Status | TRACKER_PENDING (r1) |

## Isi
Sepuluh FK level PA dipindah dari HRIS_Level ke N_PA_Level (nama constraint tetap, data tidak berubah) + FK baru N_PA_Karyawan_Level_PA.ID_Level_PA. MEMUAT DROP CONSTRAINT; agen mengeksekusinya di development atas izin pemilik proyek (13 Okt 2026).

## Urutan eksekusi
DDL-2026-183 → DML-2026-184 → DDL-2026-185.

## Riwayat development
- 2026-10-13: dijalankan di Web_HRIS dev; putaran kedua no-op.

## User tasks
Review → SQL Tracker (urutan di atas) → production → verifikasi.
