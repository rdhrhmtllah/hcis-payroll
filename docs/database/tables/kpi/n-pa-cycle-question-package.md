# N_PA_Cycle_Question_Package

Paket soal HC Learn yang dipilih untuk satu pasangan siklus dan level. Header dari
kontrak harapan; isinya ada di `N_PA_Cycle_Question_Package_Item`.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-073-performance-appraisal-cycle-question-package |
| Forward SQL | `database/ddl/DDL-2026-073-performance-appraisal-cycle-question-package/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Question_Package` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Id` | `int` | T | | FK ke `N_PA_Cycle` |
| `ID_Level` | `bigint` | T | | FK ke `HRIS_Level`; lingkup paket ini |
| `Kode_Paket_Sumber` | `varchar(100)` | T | | Pengenal paket di HC Learn |
| `Nama_Paket_Sumber` | `nvarchar(200)` | Y | | Nama versi HC Learn, untuk pengenalan admin |
| `Sumber_Kontrak` | `varchar(20)` | T | `'OTOMATIS'` | `OTOMATIS` bila isinya ditarik dari API, `MANUAL` bila dipetakan admin |
| `Catatan` | `nvarchar(500)` | Y | | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` memensiunkan pilihan paket yang diganti |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | Kapan kontraknya ditarik |
| `Snapshot_By` | `int` | Y | | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Untuk apa

HC Learn memiliki soalnya, HCIS memiliki standarnya. Saat admin memilih paket, HCIS
menarik daftar kode soalnya, menerjemahkannya lewat `N_PA_Competency_Kode_Soal`, lalu
langsung bisa menyatakan apakah paket itu menutup seluruh yang dituntut
`N_PA_Cycle_Competency_Requirement_Snapshot` untuk level tersebut.

**Salinannya yang jadi intinya.** Paket bisa disunting di dalam HC Learn *setelah*
dipilih, jadi memvalidasi saat pemilihan saja tidak cukup. Kontrak yang tersimpan
memungkinkan callback membandingkan apa yang benar-benar datang dengan apa yang
dijanjikan.

## Sumber_Kontrak membedakan tarikan otomatis dari pemetaan tangan

Kalau HC Learn menolak membuka isi paket lewat `options`, admin memetakan kode soalnya
sekali per paket lewat halaman HCIS. Kolom ini mencatat mana yang mana, sehingga mutu
kontraknya kelihatan alih-alih diasumsikan.

## Asumsi butiran yang bisa berubah

Butirannya per (siklus, level), **bukan** per kategori penilai. Ini mengikuti deck
slide 9 yang menyatakan seluruh dimensi digabung dalam **satu** form asesmen.

Inilah satu-satunya asumsi di rancangan ini yang bisa memaksa perubahan bentuk: kalau
HC Learn ternyata membutuhkan paket berbeda untuk Self dibanding Bawahan, tabel ini
perlu kolom kategori penilai dan keunikannya ikut berubah.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Question_Package` | Primary key | |
| `FK_PA_Cycle_Question_Package_Cycle` | Foreign key | → `N_PA_Cycle` |
| `FK_PA_Cycle_Question_Package_Level` | Foreign key | → `HRIS_Level` |
| `UX_PA_Cycle_Question_Package_Aktif` | Unique index tersaring | `(PA_Cycle_Id, ID_Level)` `WHERE Flag_Aktif = 'Y'` |
| `DF_PA_Cycle_Question_Package_Sumber` / `_Aktif` / `_At` | Default | `'OTOMATIS'`, `'Y'`, `SYSDATETIME()` |

Keunikannya disaring pada baris aktif supaya paket yang salah pilih bisa dipensiunkan
lalu diganti, tanpa menghapus catatan paket mana yang sempat dipakai.

## Yang sengaja tidak dijaga database

Apakah paketnya benar-benar menutup jenis kompetensi yang wajib melibatkan tiga tabel
dan sifatnya dihitung, bukan dibatasi. Begitu pula aturan bahwa paket tidak boleh
diganti lagi setelah siklus dikunci.

## Kedudukan

Lapisan kedua.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong. Diisi aplikasi saat admin memilih paket.
