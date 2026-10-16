# DDL-2026-191-pa-kelayakan-masa-kerja

| | |
|---|---|
| Change ID | DDL-2026-191-pa-kelayakan-masa-kerja |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | KPI / Performance Appraisal / Cycle & Master Data |
| Artefak | `database/ddl/DDL-2026-191-pa-kelayakan-masa-kerja/forward.sql` |
| Drop (manual) | `database/ddl/DDL-2026-191-pa-kelayakan-masa-kerja/drop.sql` |
| Checksum (sha256) | `1bb71643405047fe2f6e97b7574dca12edb4723516735de71ba070eaa7c3dc27` |
| Status | TRACKER_PENDING (r1) |

## Isi
- Tabel baru `N_PA_Eligibility_Setting` (satu baris): minimal masa kerja peserta di master.
  Lihat `docs/database/tables/kpi/n-pa-eligibility-setting.md`.
- Kolom baru `N_PA_Cycle.Min_Masa_Kerja_Bulan int NULL`: salinan per siklus, diisi dari master
  saat siklus dibuat. NULL untuk siklus yang sudah ada (aturan tidak berlaku sampai diisi).

Tanpa DML: master tanpa baris memakai bawaan 3 bulan di aplikasi, dan barisnya dibuat saat
admin pertama kali menyimpan.

## Urutan eksekusi
Sesudah DDL-2026-187 (N_PA_Cycle harus sudah ada). Idempoten.

## Riwayat development
- 2026-10-14: dijalankan di Web_HRIS dev; putaran kedua no-op.

## User tasks
Review → SQL Tracker → production (sesudah DDL-2026-187) → verifikasi.
