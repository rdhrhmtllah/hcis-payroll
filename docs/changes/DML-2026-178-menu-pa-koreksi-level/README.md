# DML-2026-178-menu-pa-koreksi-level

| | |
|---|---|
| Change ID | DML-2026-178-menu-pa-koreksi-level |
| Target | Web_HRIS (development); production lewat SQL Tracker, SESUDAH DDL-2026-177 |
| Data change | `database/dml/DML-2026-178-menu-pa-koreksi-level/data-change.sql` |
| Checksum (sha256) | `2186ffe00b29bccdf0e5843bcfe01483ad8d04e6ad6781a47d4532b8f68b0eee` |
| Status | TRACKER_PENDING (r1) |

Menu `PaMasterLevelPaPage` "Master · Koreksi Level PA" (`/performance-appraisal/master/level-pa`) di header
Administrator PA, Sort_Menu 2; peta grup fitur KPI (`N_HRIS_Grup_Fitur_Map`); akses halaman disalin dari pemegang
`PaMasterGrupKurvaPage` (dev: UserID_Web 4, 622). Idempoten.

Basis registry: grup fitur KPI di `N_HRIS_Master_Grup_Fitur` (Web_HRIS dev, diverifikasi 2026-10-09 lewat pemetaan halaman PA lain).

## Riwayat development
- 2026-10-09: dijalankan; ulang = no-op (1 menu, 1 peta, 2 akses). `InertiaShellService::flushMenuCache()`.

## User tasks
Review → SQL Tracker → production → `flushMenuCache` / cache:clear → verifikasi menu tampil.
