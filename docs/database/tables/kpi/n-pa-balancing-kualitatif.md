# N_PA_Balancing_Kualitatif

## Purpose

Ringkasan kualitatif seorang peserta dalam satu sesi, satu baris per kompetensi KUALITATIF
aktif (per 30 Sep 2026: QL01 Strength, QL02 Development Area, QL03 Initiative, QL04
Recommendation). Before = catatan admin aktif saat sesi dibuat; After menjadi catatan admin
baru saat hitung ulang bila berbeda.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Balancing
- Change: DDL-2026-165-pa-balancing (r1)
- DDL: database/ddl/DDL-2026-165-pa-balancing/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
- Aktor audit `*_By`: `KPI_Users.Id_Users` (int), sama dengan seluruh tabel PA.

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Balancing_Kualitatif | int identity | No | Primary key. |
| PA_Balancing_Peserta_Id | int | No | FK `N_PA_Balancing_Peserta`. |
| PA_Competency_Id | int | No | Kompetensi KUALITATIF. FK `N_PA_Competency`. |
| Isi_Sebelum | nvarchar(max) | Yes | HTML aman; NULL bila admin belum menulis. |
| Isi_Sesudah | nvarchar(max) | Yes | HTML aman lewat `PaTeksKaya::bersihkan`. |
| Updated_At / Updated_By | | | Audit. |

## Indexes and constraints

`UX_PA_Balancing_Kualitatif_Peserta_Competency`; PK; dua FK tanpa cascade.

## Application usage

Ditulis `PaBalancingSesiService`; dibaca `PaBalancingNilaiBerlaku::kualitatifBerlaku`; dipakai `PaBalancingHitungUlangService` untuk menulis `N_PA_Result_Note`.
