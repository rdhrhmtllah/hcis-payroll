# N_PA_Balancing_Peserta

## Purpose

Satu baris per peserta dalam satu sesi, dengan **snapshot Before** hasil saat sesi dibuat dan
status tinjaunya. Semua peserta tercatat, termasuk yang tidak diubah.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Balancing
- Change: DDL-2026-165-pa-balancing (r1)
- DDL: database/ddl/DDL-2026-165-pa-balancing/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
- Aktor audit `*_By`: `KPI_Users.Id_Users` (int), sama dengan seluruh tabel PA.

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Balancing_Peserta | int identity | No | Primary key. |
| PA_Balancing_Sesi_Id | int | No | FK `N_PA_Balancing_Sesi`. |
| PA_Participant_Id | int | No | FK `N_PA_Participant`. |
| PA_Result_Id | int | No | Hasil aktif saat sesi dibuat. FK `N_PA_Result`. |
| Calculation_Version_Sebelum | int | No | Versi hitung saat snapshot. |
| Nilai_KPI_Sebelum, Nilai_Kompetensi_Sebelum, Final_Score_Sebelum | decimal(6,2) | Yes | Snapshot Before. |
| Kode_Grade_Sebelum | varchar(40) | Yes | `Kode_Grade_Final` saat snapshot. |
| PA_Hav_Quadrant_Sebelum_Id | int | Yes | Kotak HAV saat snapshot. |
| Status_Tinjau | varchar(20) | No | `BELUM` (bawaan) / `TANPA_PERUBAHAN` / `DIUBAH` (diturunkan backend dari isian). |
| Alasan | nvarchar(1000) | Yes | Catatan umum untuk peserta ini. |
| Created_* / Updated_* | | | Audit. |

## Indexes and constraints

- `UX_PA_Balancing_Peserta_Sesi_Participant` — satu baris per orang per sesi.
- `IX_PA_Balancing_Peserta_Participant` INCLUDE (sesi, status tinjau) — penjaga "sedang dibahas" dan pembaca nilai berlaku.
- PK, tiga FK tanpa cascade, `DF_PA_Balancing_Peserta_Tinjau`, `DF_PA_Balancing_Peserta_Created_At`.

## Application usage

`PaBalancingSesiService`, `PaBalancingNilaiBerlaku`, `PaBalancingPenjaga`, `PaBalancingKandidatService`, `PaBalancingSimulasiService`, `PaBalancingHitungUlangService`.
