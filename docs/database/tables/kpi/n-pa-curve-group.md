# N_PA_Curve_Group, N_PA_Curve_Group_Level

## Purpose

Master **Grup Level Kurva**: level jabatan mana yang dibandingkan dalam satu kurva grade.
Rata-rata dan simpangan baku dihitung per grup, jadi level kecil (mis. Asisten Manager)
digabung dengan level tetangga supaya kurvanya bermakna. Disalin per siklus ke
`N_PA_Cycle_Curve_Group(_Level)`; level yang belum dipetakan menjadi grupnya sendiri di salinan.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Master Data — Grup Level Kurva
- Change: DDL-2026-120-pa-grade-kurva

## Columns — N_PA_Curve_Group

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Curve_Group | int identity | No | Primary key. |
| Kode_Grup | varchar(40) | No | Kode grup; unik di antara baris aktif. |
| Nama_Grup | nvarchar(200) | No | Nama tampilan. |
| Urutan_Tampil | smallint | No | Urutan tampil. |
| Flag_Aktif | char(1) | No | `Y` aktif; menyimpan ulang konfigurasi menonaktifkan grup lama. |
| Created_At/By, Updated_At/By | | | Jejak. |

## Columns — N_PA_Curve_Group_Level

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Curve_Group_Level | int identity | No | Primary key. |
| PA_Curve_Group_Id | int | No | FK ke `N_PA_Curve_Group`. |
| ID_Level | bigint | No | FK ke `HRIS_Level`; satu level hanya di satu grup aktif (`UX_PA_Curve_Group_Level_Level`). |
| Flag_Aktif | char(1) | No | `Y` aktif. |

## Application usage

- Ditulis hanya oleh `PaGrupKurvaMasterService::simpan()` — seluruh konfigurasi sekaligus.
- Disalin oleh `PaCyclePreparationService::salinGrupKurva()`.
