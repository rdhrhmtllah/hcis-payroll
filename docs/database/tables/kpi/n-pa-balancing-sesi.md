# N_PA_Balancing_Sesi

## Purpose

Satu baris per sesi Balancing: forum yang meninjau skor kompetensi sejumlah peserta sesudah
Calculate. Sesi berulang (Balancing 1, 2, …); tiap sesi FINAL dihitung ulang sebelum publish.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Balancing
- Change: DDL-2026-165-pa-balancing (r1)
- DDL: database/ddl/DDL-2026-165-pa-balancing/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
- Aktor audit `*_By`: `KPI_Users.Id_Users` (int), sama dengan seluruh tabel PA.

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Balancing_Sesi | int identity | No | Primary key. |
| PA_Cycle_Id | int | No | Siklus. FK `N_PA_Cycle`. |
| Nama_Sesi | nvarchar(200) | No | Nama sesi, mis. "Balancing Staff–SPV". |
| PIC_User_Id | int | No | PIC sesi, `KPI_Users.Id_Users`. |
| Status_Sesi | varchar(20) | No | `TERBUKA` (bawaan) → `DRAFT` → `FINAL`, atau `DIBATALKAN`. Divalidasi backend, tanpa CHECK. |
| Catatan_Rapat | nvarchar(max) | Yes | HTML aman (`PaTeksKaya::bersihkan`). |
| Versi_Data | int | No | Kunci optimistis, bawaan 1, naik tiap simpan/final/batal. |
| Final_At / Final_By | datetime2(0) / int | Yes | Saat sesi difinalkan. |
| Nomor_Hitung_Ulang | int | Yes | Putaran hitung ulang yang memproses sesi ini. |
| Hitung_Ulang_At / Hitung_Ulang_By | datetime2(0) / int | Yes | Saat dihitung ulang. NULL pada sesi FINAL = masih menahan publish. |
| Cancelled_At / Cancelled_By / Alasan_Batal | datetime2(0) / int / nvarchar(500) | Yes | Pembatalan beserta alasannya. |
| Created_At / Created_By / Updated_At / Updated_By | | | Audit. |

## Indexes and constraints

- `PK_PA_Balancing_Sesi`, `FK_PA_Balancing_Sesi_Cycle` (tanpa cascade).
- `IX_PA_Balancing_Sesi_Cycle_Status (PA_Cycle_Id, Status_Sesi)` — penjaga "sesi terbuka" dan "FINAL belum dihitung".
- `CK_PA_Balancing_Sesi_Versi` — `Versi_Data >= 1`. `DF_PA_Balancing_Sesi_Status`, `DF_PA_Balancing_Sesi_Versi`, `DF_PA_Balancing_Sesi_Created_At`.

## Application usage

`PaBalancingSesiService` (tulis/baca), `PaBalancingPenjaga`, `PaBalancingHitungUlangService`,
`PaBalancingNilaiBerlaku`, `PaBalancingController` (`/api/v1/performance-appraisal/.../balancing/...`).
