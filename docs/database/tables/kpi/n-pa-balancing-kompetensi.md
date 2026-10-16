# N_PA_Balancing_Kompetensi

## Purpose

Skor per kompetensi berskor seorang peserta dalam satu sesi: Before, After, bobot, alasan.
Satu baris untuk **setiap** kompetensi berskor, termasuk yang tidak diubah. After dari sesi
FINAL terbaru adalah skor kompetensi yang berlaku (spec §4), dibaca hanya lewat
`PaBalancingNilaiBerlaku`.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Balancing
- Change: DDL-2026-165-pa-balancing (r1)
- DDL: database/ddl/DDL-2026-165-pa-balancing/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
- Aktor audit `*_By`: `KPI_Users.Id_Users` (int), sama dengan seluruh tabel PA.

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Balancing_Kompetensi | int identity | No | Primary key. |
| PA_Balancing_Peserta_Id | int | No | FK `N_PA_Balancing_Peserta`. |
| PA_Competency_Id | int | No | FK `N_PA_Competency`. |
| Bobot | decimal(9,6) | No | Porsi tepat kompetensi di Nilai Kompetensi (spec §6.1), 0–1, dibekukan saat sesi dibuat. |
| Nilai_Sebelum | decimal(6,2) | No | Nilai radar yang berlaku saat sesi dibuat, 0–100. |
| Nilai_Sesudah | decimal(6,2) | No | After, 0–100. Awalnya sama dengan Before. |
| Alasan | nvarchar(1000) | Yes | Wajib (divalidasi backend) bila After ≠ Before. |
| Updated_At / Updated_By | | | Audit. |

## Indexes and constraints

- `UX_PA_Balancing_Kompetensi_Peserta_Competency`; PK; dua FK tanpa cascade.
- `CK_PA_Balancing_Kompetensi_Bobot` (0–1) dan `CK_PA_Balancing_Kompetensi_Nilai` (0–100): rentang angka, bukan daftar nilai bisnis.

## Application usage

Ditulis `PaBalancingSesiService`; dibaca `PaBalancingNilaiBerlaku` (satu pintu, dijaga `BalancingSatuPintuTest`), `PaBalancingSimulasiService` (isian awal simulasi); diterapkan `PaResultCalculationService::tahapKompetensi`.
