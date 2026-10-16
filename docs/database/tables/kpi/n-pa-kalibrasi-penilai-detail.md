# N_PA_Kalibrasi_Penilai_Detail

## Purpose

Rincian **Kalibrasi Penilai**: satu baris per kompetensi yang diubah pada satu tindakan
simpan (`N_PA_Kalibrasi_Penilai`). Sumber skor efektif: untuk tiap (penugasan, kompetensi),
`Skor_Sesudah` dari tindakan terbaru menggantikan skor asli HC Learn dalam perhitungan.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / PA Saya — Tugas Penilaian
- Change: DDL-2026-119-pa-kalibrasi-penilai
- DDL: database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Kalibrasi_Penilai_Detail | int identity | No | Primary key. |
| PA_Kalibrasi_Penilai_Id | int | No | Tindakan simpan pemiliknya. FK ke `N_PA_Kalibrasi_Penilai`. |
| PA_Competency_Id | int | No | Kompetensi yang diubah. FK ke `N_PA_Competency`. |
| Skor_Sebelum | decimal(6,2) | No | Skor EFEKTIF saat tindakan dibuat — skor asli pada tindakan pertama, revisi sebelumnya pada tindakan berikutnya. |
| Skor_Sesudah | decimal(6,2) | No | Skor baru. |
| Skor_Maksimal | decimal(6,2) | No | Skala butir saat itu, disimpan supaya riwayat terbaca tanpa bergantung pada payload. |

## Indexes and constraints

- `PK_PA_Kalibrasi_Penilai_Detail` — kunci utama.
- `FK_PA_Kalibrasi_Penilai_Detail_Kepala`, `FK_PA_Kalibrasi_Penilai_Detail_Competency`.
- `UX_PA_Kalibrasi_Penilai_Detail_Kompetensi (PA_Kalibrasi_Penilai_Id, PA_Competency_Id)` —
  satu tindakan tidak boleh mengubah kompetensi yang sama dua kali.
- `CK_PA_Kalibrasi_Penilai_Detail_Rentang` — `Skor_Maksimal > 0`,
  `0 <= Skor_Sesudah <= Skor_Maksimal`, `Skor_Sebelum >= 0`.

## Kenapa berkunci (penugasan, kompetensi)

`PaKirimanTinjauService::petakanUlang()` menonaktifkan baris `N_PA_Assignment_Score` lama dan
menyisipkan baris baru. Kalibrasi yang menempel ke id baris skor akan hilang tanpa jejak.
`PaBijeksiLabel` menjamin satu bagian ↔ satu kompetensi dalam satu paket, dan perhitungan
memang mengelompokkan skor per kompetensi.

## Application usage

- Ditulis hanya oleh `PaKalibrasiPenilaiService::simpan()`.
- Dibaca `PaSkorEfektif::kalibrasiTerbaru()` (satu pintu skor efektif),
  `PaKalibrasiPenilaiService::riwayatPeserta()`, dan `PaVerifyCommand` (independen).
