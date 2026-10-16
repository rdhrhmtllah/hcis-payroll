# N_PA_Assignment_Score_Esai

## Purpose

Satu baris per **soal esai** pada satu baris `N_PA_Assignment_Score`. Sejak 26 September 2026
callback HC Learn mengirim `Detail[].Esai[]`: setiap soal esai di dalam satu bagian datang
dengan id soal, teks pertanyaan, dan jawaban penilai. Tabel ini menyimpannya supaya layar
Kalibrasi Penilai bisa menampilkan pertanyaan di atas jawabannya, dan esai dikalibrasi per
soal.

Kiriman format lama tidak punya baris di sini; jawabannya tetap satu teks di
`N_PA_Assignment_Score.Jawaban_Teks`.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Integrasi HC Learn dan PA Saya — Kalibrasi Penilai
- Change: DDL-2026-140-pa-esai-per-soal (r1)
- DDL: database/ddl/DDL-2026-140-pa-esai-per-soal/forward.sql
- Rancangan: docs/superpowers/specs/2026-09-26-pa-esai-per-soal-design.md

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Assignment_Score_Esai | int identity | No | Primary key. |
| PA_Assignment_Score_Id | int | No | Baris skor (bagian) pemiliknya. FK ke `N_PA_Assignment_Score`. |
| Id_Master_Soal | int | Yes | `Esai[].Id_Master_Soal` — id soal di bank soal HC Learn; jangkar kalibrasi per soal. |
| Urutan_Tampil | smallint | No | Urutan di `Esai[]`, mulai 1. |
| Pertanyaan | nvarchar(max) | Yes | `Esai[].Pertanyaan`, teks tanpa markup. NULL bila soalnya sudah terhapus dari bank soal HC Learn. |
| Jawaban_Teks | nvarchar(max) | Yes | `Esai[].Jawaban_Peserta` apa adanya, teks polos. |
| Waktu_Dijawab | datetime2(0) | Yes | `Esai[].Waktu_Dijawab`. |
| Created_At | datetime2(0) | No | Bawaan `SYSDATETIME()`. |

Tidak ada `Flag_Aktif`: baris anak mengikuti `Flag_Aktif` baris skornya. Pemetaan ulang
menonaktifkan baris skor lama dan menyisipkan anak baru untuk baris skor baru, jadi pembaca
selalu bergabung lewat baris skor yang aktif.

Penilaian mesin dan koreksi admin HC Learn (`Nilai`, `Rekomendasi_Nilai_Sistem`,
`Alasan_Sistem`, `Sudah_Dikoreksi`, `Catatan_Koreksi`) sengaja tidak disimpan: skor esai tidak
dipakai (keputusan pemilik proyek 26 Sep 2026). Angkanya tetap ada di
`N_PA_Assignment_Payload.Payload_Json`.

## Indexes and constraints

- `PK_PA_Assignment_Score_Esai` — kunci utama.
- `FK_PA_Assignment_Score_Esai_Score` — ke `N_PA_Assignment_Score`. Tanpa cascade.
- `UX_PA_Assignment_Score_Esai_Urutan (PA_Assignment_Score_Id, Urutan_Tampil)` — urutan unik
  per bagian; juga jalur baca utama per baris skor.
- `CK_PA_Assignment_Score_Esai_Urutan` — `Urutan_Tampil >= 1`.
- `DF_PA_Assignment_Score_Esai_Created_At`.

## Application usage

- Ditulis: `PaPayloadMapperService` saat memetakan callback `POST /api/v1/pa/third-party/penilaian/complete`.
- Dibaca: `PaSkorEfektif::jawabanNaratif()` / `timpaJawaban()` (satu pintu), lewat mereka
  `PaWizardPenilaianService`, `PaKalibrasiPenilaiService`, `PaParticipantReadService`, dan
  catatan hasil di `PaResultCalculationService`; pemeriksaan "ada jawaban" di
  `PaResultCalculationService::jenisTerjawabTanpaAngka()` dan `PaAdminDashboardService`.
