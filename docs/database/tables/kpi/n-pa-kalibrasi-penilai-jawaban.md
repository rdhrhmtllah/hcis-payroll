# N_PA_Kalibrasi_Penilai_Jawaban

## Purpose

Rincian **Kalibrasi Penilai** untuk jawaban esai: satu baris per butir teks yang diubah pada
satu tindakan simpan (`N_PA_Kalibrasi_Penilai`). Sumber jawaban efektif: untuk tiap
(penugasan, `Kode_Soal_Sumber`), `Jawaban_Sesudah` dari tindakan terbaru menggantikan
`N_PA_Assignment_Score.Jawaban_Teks` asli HC Learn di catatan hasil, detail admin, dan
pratinjau. Teks asli tidak pernah ditimpa.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / PA Saya — Tugas Penilaian
- Change: DDL-2026-119-pa-kalibrasi-penilai (r3)
- DDL: database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Kalibrasi_Penilai_Jawaban | int identity | No | Primary key. |
| PA_Kalibrasi_Penilai_Id | int | No | Tindakan simpan pemiliknya. FK ke `N_PA_Kalibrasi_Penilai`. |
| Kode_Soal_Sumber | varchar(100) | No | Kode soal dari HC Learn — kunci butir di dalam lembar. |
| Id_Master_Soal | int | Yes | (r3) Satu soal esai di dalam bagian itu, dari `Esai[].Id_Master_Soal` callback HC Learn (lihat `N_PA_Assignment_Score_Esai`). NULL = jawaban format lama, satu teks untuk seluruh bagian. |
| PA_Competency_Id | int | Yes | Kompetensi butir bila terpetakan; NULL untuk esai tak terpetakan. FK ke `N_PA_Competency`. |
| Jawaban_Sebelum | nvarchar(max) | No | HTML aman dari jawaban EFEKTIF saat tindakan dibuat — asli HC Learn (di-escape per baris) pada tindakan pertama, revisi sebelumnya pada tindakan berikutnya. |
| Jawaban_Sesudah | nvarchar(max) | No | HTML aman hasil `PaTeksKaya::bersihkan()`; tidak boleh kosong. |

## Indexes and constraints

- `PK_PA_Kalibrasi_Penilai_Jawaban` — kunci utama.
- `FK_PA_Kalibrasi_Penilai_Jawaban_Kepala`, `FK_PA_Kalibrasi_Penilai_Jawaban_Competency`.
- `UX_PA_Kalibrasi_Penilai_Jawaban_Soal (PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber, Id_Master_Soal)`
  — satu tindakan tidak boleh mengubah soal yang sama dua kali. SQL Server menganggap NULL sama
  di indeks unik, jadi jawaban format lama tetap satu per bagian per tindakan.
- `CK_PA_Kalibrasi_Penilai_Jawaban_Terisi` — `Jawaban_Sesudah` tidak boleh kosong atau hanya
  spasi. Butir kosong terbaca "tidak dijawab" oleh perhitungan dan bisa menahan hasil.

## Kenapa berkunci (penugasan, Kode_Soal_Sumber)

Sama alasannya dengan rincian skor: `petakanUlang()` mengganti baris skor, jadi id baris
tidak stabil. Di `N_PA_Assignment_Score`, `Kode_Soal_Sumber` `NOT NULL` dan unik per payload
untuk baris aktif (`UX_PA_Assignment_Score_Butir`). Kompetensi tidak cukup sebagai kunci
karena satu kompetensi bisa punya lebih dari satu butir esai, dan esai bisa tak terpetakan.

## Application usage

- Ditulis hanya oleh `PaKalibrasiPenilaiService::simpan()`.
- Dibaca `PaSkorEfektif::jawabanNaratif()` (satu pintu jawaban efektif) dan
  `PaKalibrasiPenilaiService::riwayatPeserta()`.
