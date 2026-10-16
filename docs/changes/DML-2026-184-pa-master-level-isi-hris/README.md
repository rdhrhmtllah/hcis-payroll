# DML-2026-184-pa-master-level-isi-hris

| | |
|---|---|
| Change ID | DML-2026-184-pa-master-level-isi-hris |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | KPI / Performance Appraisal / Master Data |
| Artefak | `database/dml/DML-2026-184-pa-master-level-isi-hris/data-change.sql` |
| Desain | `docs/superpowers/specs/2026-10-13-pa-master-level-pa-design.md` |
| Checksum (sha256) | `f54c8fced0aceb33ecb2ad07ccac0920dc6381ccd369ce9436e0bc4cc9608b69` |
| Status | TRACKER_PENDING (r1) |

## Isi
Menyalin seluruh HRIS_Level ke N_PA_Level dengan ID sama (16 baris di dev). Idempoten. Level HRIS baru berikutnya disalin aplikasi (PaLevelMasterService::sinkronHris).

## Urutan eksekusi
DDL-2026-183 → DML-2026-184 → DDL-2026-185.

## Riwayat development
- 2026-10-13: dijalankan di Web_HRIS dev; putaran kedua no-op.

## User tasks
Review → SQL Tracker (urutan di atas) → production → verifikasi.
