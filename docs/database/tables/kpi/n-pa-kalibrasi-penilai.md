# N_PA_Kalibrasi_Penilai

## Purpose

Kepala jejak **Kalibrasi Penilai**: satu baris per tindakan simpan ketika seorang penilai
mengubah skor atau jawaban esai yang ia berikan kepada satu orang, sebelum mengirim
penilaiannya. Rinciannya per kompetensi ada di `N_PA_Kalibrasi_Penilai_Detail`, per butir esai
di `N_PA_Kalibrasi_Penilai_Jawaban` (sejak DDL-2026-119 r2). Satu tindakan boleh memuat
keduanya dengan satu `Alasan`.

Berbeda dari kalibrasi forum (grade akhir sebuah hasil, milik `PaCalibrationService`). Kedua
mekanisme sengaja tidak berbagi tabel supaya riwayat audit tidak mencampurnya.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / PA Saya — Tugas Penilaian
- Change: DDL-2026-119-pa-kalibrasi-penilai
- DDL: database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Kalibrasi_Penilai | int identity | No | Primary key. Naik mengikuti urutan penyimpanan dalam satu penugasan. |
| PA_Assignment_Id | int | No | Penugasan (lembar) yang dikalibrasi. FK ke `N_PA_Assignment`. |
| Alasan | nvarchar(500) | No | Alasan penilai; wajib, satu per orang per tindakan. |
| Created_At | datetime2(0) | No | Waktu simpan; bawaan `sysdatetime()`. |
| Created_By | int | Yes | `KPI_Users.Id_Users` dari sesi login. NULL hanya dari jalur tanpa sesi (uji). |

## Indexes and constraints

- `PK_PA_Kalibrasi_Penilai` — kunci utama.
- `FK_PA_Kalibrasi_Penilai_Assignment` — ke `N_PA_Assignment`. Tanpa cascade: penugasan tidak
  pernah dihapus, hanya dinonaktifkan.
- `IX_PA_Kalibrasi_Penilai_Assignment (PA_Assignment_Id, Id_PA_Kalibrasi_Penilai DESC)` —
  melayani "tindakan terbaru per penugasan" yang dibaca wizard dan skor efektif.

Tabel hanya ditambah: tidak ada `Flag_Aktif`, `Updated_*`, maupun `Cancelled_*`. Revisi yang
keliru diperbaiki dengan revisi berikutnya.

## Application usage

- `PaKalibrasiPenilaiService::simpan()` — satu-satunya penulis, di dalam transaksi dengan
  `lockForUpdate` pada baris penugasan.
- `PaKalibrasiPenilaiService::riwayatPeserta()` — riwayat untuk detail hasil admin.
- `PaSkorEfektif::kalibrasiTerbaru()` — skor efektif (lewat rinciannya).
- `PaWizardPenilaianService` — "kalibrasi terakhir" dan hitungan terkalibrasi per langkah.
- `PaVerifyCommand` — membaca ulang secara independen untuk memeriksa hasil.
