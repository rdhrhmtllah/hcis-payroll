# N_PA_Balancing_Forum

## Purpose

Anggota forum satu sesi Balancing (mis. Dept Head, Div Head, BOD). Hanya dicatat; anggota
forum tidak login ke sistem.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Balancing
- Change: DDL-2026-165-pa-balancing (r1)
- DDL: database/ddl/DDL-2026-165-pa-balancing/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-30-pa-balancing-design.md
- Aktor audit `*_By`: `KPI_Users.Id_Users` (int), sama dengan seluruh tabel PA.

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Balancing_Forum | int identity | No | Primary key. |
| PA_Balancing_Sesi_Id | int | No | FK `N_PA_Balancing_Sesi`. |
| Kode_Perusahaan / Kode_Karyawan | varchar(3) / varchar(255) | No | Karyawan aktif saat dicatat (tipe sama dengan `Karyawan`). |
| Nama_Karyawan | varchar(255) | No | Salinan nama saat dicatat. |
| Peran | nvarchar(100) | No | Peran di forum. |
| Urutan_Tampil | smallint | No | Mulai 1. |
| Created_At / Created_By | | | Audit. |

## Indexes and constraints

- `PK_PA_Balancing_Forum`, `FK_PA_Balancing_Forum_Sesi`, `IX_PA_Balancing_Forum_Sesi`, `CK_PA_Balancing_Forum_Urutan`, `DF_PA_Balancing_Forum_Created_At`.

## Application usage

Ditulis `PaBalancingSesiService::buat`; dibaca `detail`/`daftar`.
