# DDL-2026-183-pa-master-level

| | |
|---|---|
| Change ID | DDL-2026-183-pa-master-level |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | KPI / Performance Appraisal / Master Data |
| Artefak | `database/ddl/DDL-2026-183-pa-master-level/forward.sql` |
| Desain | `docs/superpowers/specs/2026-10-13-pa-master-level-pa-design.md` |
| Checksum (sha256) | `8fe53e38f9eb046d365476cc6b8ba7b2b71e00a050c1b628dbacd655da495054` |
| Status | TRACKER_PENDING (r1) |

## Isi
Tabel N_PA_Level: master level milik PA. Salinan HRIS_Level (ID sama, Sumber HRIS) dan level khusus PA (ID >= 1.000.000, Sumber PA, wajib ID_Level_HRIS_Dasar). Urutan menggantikan Level_Hierarchy di PA; nama aktif unik.

## Urutan eksekusi
DDL-2026-183 → DML-2026-184 → DDL-2026-185.

## Riwayat development
- 2026-10-13: dijalankan di Web_HRIS dev; putaran kedua no-op.

## User tasks
Review → SQL Tracker (urutan di atas) → production → verifikasi.
