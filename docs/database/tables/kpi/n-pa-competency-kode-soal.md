# N_PA_Competency_Kode_Soal

Pemetaan kode soal HC Learn ke kompetensi HCIS. Inilah kamus penerjemah yang dipakai
saat hasil callback masuk.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Competency |
| Change ID | DDL-2026-067-performance-appraisal-competency-and-score-range |
| Forward SQL | `database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Competency_Kode_Soal` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Competency_Id` | `int` | T | | FK ke `N_PA_Competency` |
| `Kode_Soal` | `varchar(100)` | T | | Kode dari HC Learn, unik selama aktif |
| `Nama_Soal_Sumber` | `nvarchar(200)` | Y | | Nama versi HC Learn, supaya admin mengenali barisnya |
| `Sumber_Pemetaan` | `varchar(20)` | T | `'MANUAL'` | `OTOMATIS` bila dikenali sistem, `MANUAL` bila dipetakan admin |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` memensiunkan pemetaan tanpa menghapus jejaknya |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa tabel anak, bukan kolom

Satu kompetensi bisa datang dengan kode berbeda dari paket HC Learn yang berbeda.
Kalau pemetaannya cuma satu kolom di `N_PA_Competency`, paket kedua yang menamainya
lain langsung tidak dikenali.

Kebalikannya tidak berlaku: satu kode **harus** menunjuk tepat satu kompetensi, kalau
tidak butir yang sama akan dihitung sebagai dua hal berbeda. Karena itu keunikannya
ada di `Kode_Soal`, disaring pada baris aktif saja — supaya pemetaan yang salah bisa
dipensiunkan lalu diganti tanpa menghapus catatan apa yang dulu dipakai.

## Kenapa tidak disnapshot per siklus

Ini kamus, bukan aturan (K26). Isinya menyatakan bahwa kode `FRUITS` berarti K01.
Arti itu tidak boleh berbeda antar siklus — kode mentah yang sama berarti kompetensi
berbeda di dua siklus membuat hasilnya tidak bisa dibandingkan. Pemetaan yang keliru
diperbaiki secara global.

Jejaknya tetap aman karena `N_PA_Assignment_Score` menyimpan `Kode_Soal_Sumber`
mentah **dan** `Id_Competency` hasil terjemahannya, jadi tiap baris skor membawa
sendiri terjemahan yang dipakainya saat itu.

## Beban kerja manual mengecil sendiri

Tabel ini tumbuh sekali per **kode baru**, bukan sekali per paket. Setelah `FRUITS`
dipetakan ke K01, semua paket berikutnya yang memakai kode itu langsung dikenali.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Competency_Kode_Soal` | Primary key | |
| `FK_PA_Competency_Kode_Soal_Competency` | Foreign key | → `N_PA_Competency` |
| `UX_PA_Competency_Kode_Soal_Aktif` | Unique index tersaring | `Kode_Soal` unik `WHERE Flag_Aktif = 'Y'` |
| `IX_PA_Competency_Kode_Soal_Competency` | Index | `(PA_Competency_Id, Flag_Aktif)` `INCLUDE (Kode_Soal)` |
| `DF_PA_Competency_Kode_Soal_Sumber` / `_Aktif` / `_Created` | Default | `'MANUAL'`, `'Y'`, `SYSDATETIME()` |

## Pemakaian aplikasi

Belum ada per 2026-08-30.

## Keadaan data

Kosong. Kontrak HC Learn untuk PA belum pasti — contoh payload yang ada berasal dari
modul rekrutmen, di mana kunci sambungnya `Kode_Soal`. Tercatat sebagai T01 dan T04.
