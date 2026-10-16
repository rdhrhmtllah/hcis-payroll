# DML-2026-186-menu-pa-master-level

| | |
|---|---|
| Change ID | DML-2026-186-menu-pa-master-level |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | KPI / Performance Appraisal / Menu |
| Artefak | `database/dml/DML-2026-186-menu-pa-master-level/data-change.sql` |
| Checksum (sha256) | `42cd5fa885a4addb41e69697cf3aa76ed0fcdcccba64b23ac0ef89471c3c4849` |
| Status | TRACKER_PENDING (r1) |

## Isi
Menu **Master · Level PA** (`PaMasterLevelMasterPage`, `/performance-appraisal/master/level`) di header Administrator PA,
Sort_Menu 2 — tepat di atas Master · Koreksi Level PA; menu Administrator PA lain bergeser +1 (hanya saat menu belum ada).
Peta grup fitur KPI dan akses halaman disalin dari pemegang `PaMasterLevelPaPage`.

## Urutan eksekusi
Setelah DDL-2026-183 → DML-2026-184 → DDL-2026-185.

## Riwayat development
- 2026-10-13: dijalankan di Web_HRIS dev (16 menu bergeser, 1 menu, 1 peta, 2 akses); putaran kedua no-op.

## User tasks
Review → SQL Tracker → production → verifikasi.
