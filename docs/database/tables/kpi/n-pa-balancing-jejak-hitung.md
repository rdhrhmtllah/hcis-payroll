# N_PA_Balancing_Jejak_Hitung

## Purpose

Jejak setiap hitung ulang Balancing, **hanya sisip**: satu baris per orang yang berubah atau
ikut sesi, dengan Final, grade, dan kotak HAV sebelum/sesudah beserta sebabnya.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Balancing
- Change: DDL-2026-165-pa-balancing (r1)
- DDL: database/ddl/DDL-2026-165-pa-balancing/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
- Aktor audit `*_By`: `KPI_Users.Id_Users` (int), sama dengan seluruh tabel PA.

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Balancing_Jejak_Hitung | int identity | No | Primary key. |
| PA_Cycle_Id | int | No | FK `N_PA_Cycle`. |
| Nomor_Hitung_Ulang | int | No | Putaran ke-n dalam siklus, mulai 1. |
| PA_Participant_Id | int | No | FK `N_PA_Participant`. |
| PA_Balancing_Sesi_Id | int | Yes | Sesi pemicu; NULL bila orangnya hanya tergeser kurva. |
| Nilai_Kompetensi_Sebelum/_Sesudah, Final_Score_Sebelum/_Sesudah | decimal(6,2) | Yes | |
| Kode_Grade_Sebelum/_Sesudah | varchar(40) | Yes | |
| PA_Hav_Quadrant_Sebelum_Id/_Sesudah_Id | int | Yes | |
| Sebab | varchar(20) | No | `BALANCING` / `KURVA` / `KEDUANYA` / `TANPA_PERUBAHAN`. |
| Created_At / Created_By | | | Audit. |

## Indexes and constraints

`IX_PA_Balancing_Jejak_Hitung_Cycle_Nomor`, `IX_PA_Balancing_Jejak_Hitung_Participant`, `CK_PA_Balancing_Jejak_Hitung_Nomor`; PK; tiga FK tanpa cascade.

## Application usage

Ditulis `PaBalancingHitungUlangService`; dibaca `PaBalancingController::riwayatPeserta`.
