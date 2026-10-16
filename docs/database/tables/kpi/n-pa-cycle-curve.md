# N_PA_Cycle_Curve, N_PA_Cycle_Curve_Band (+ N_PA_Cycle_Curve_Group, _Level)

## Purpose

Kurva grade yang **dikunci** untuk satu siklus bermetode `KURVA`: satu baris per grup level per
penguncian, berisi jumlah peserta, rata-rata (μ), dan simpangan baku sampel (σ), beserta pita
nilai absolut hasilnya (μ + z·σ). `N_PA_Result.PA_Cycle_Curve_Id` menunjuk kurva yang memberi
grade setiap hasil. `N_PA_Cycle_Curve_Group(_Level)` adalah salinan grup level per siklus.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Hasil — Kurva Grade
- Change: DDL-2026-120-pa-grade-kurva

## Columns — N_PA_Cycle_Curve

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Cycle_Curve | int identity | No | Primary key. |
| PA_Cycle_Id | int | No | FK ke `N_PA_Cycle`. |
| PA_Cycle_Curve_Group_Id | int | No | FK ke grup salinan siklus. |
| Jumlah_Peserta | int | No | n hasil `DIHITUNG` saat dikunci. |
| Rata_Rata | decimal(9,4) | Yes | μ; NULL bila n = 0. |
| Simpangan_Baku | decimal(9,4) | Yes | σ sampel; NULL bila n < 2. |
| Flag_Aktif | char(1) | No | `Y` = kurva berlaku; satu aktif per grup. |
| Locked_At/By | | | Waktu dan pelaku kunci. |
| Unlocked_At/By, Alasan_Buka | | Yes | Wajib terisi bila `Flag_Aktif = 'T'` (`CK_PA_Cycle_Curve_Buka`). |

## Columns — N_PA_Cycle_Curve_Band

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Cycle_Curve_Band | int identity | No | Primary key. |
| PA_Cycle_Curve_Id | int | No | FK ke `N_PA_Cycle_Curve`. |
| Kode_Range, Nama_Range | | No | Grade. |
| Batas_Z | decimal(5,2) | Yes | Batas z salinan saat dikunci; NULL = grade terendah. |
| Nilai_Dari, Nilai_Sampai | decimal(9,4) | No | Pita absolut; bawah inklusif, atas eksklusif. |
| Urutan_Tampil | smallint | No | Urutan (z turun). |

## Application usage

- Ditulis hanya oleh `PaKurvaService::kunci()` / `buka()`.
- Dibaca `PaKurvaService` (pratinjau, `gradeBeku`) dan `PaVerifyCommand` (independen).
