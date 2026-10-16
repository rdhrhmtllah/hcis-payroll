# N_PA_Rater_Policy_Status

Master status siklus hidup Rater Policy. Tiga baris.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Rater Policy |
| Change ID | DDL-2026-070-performance-appraisal-status-and-axis-masters |
| Forward SQL | `database/ddl/DDL-2026-070-performance-appraisal-status-and-axis-masters/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Rater_Policy_Status` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Status` | `varchar(20)` | T | | Kode bisnis, **UNIQUE**; dirujuk `N_PA_Rater_Policy.Status_Policy` |
| `Nama_Status` | `nvarchar(100)` | T | | Nama tampil |
| `Deskripsi` | `nvarchar(500)` | Y | | Apa yang digerbangi status ini |
| `Urutan_Alur` | `smallint` | T | | Posisi dalam alur |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| # | Kode | Nama | Arti |
|---|---|---|---|
| 1 | `DRAFT` | Draft | Sedang disusun. Total bobot antar kategori belum wajib 100 dan policy belum boleh disalin ke siklus |
| 2 | `ACTIVE` | Berlaku | Sudah diaktifkan. Total bobot kategori aktif wajib 100 sebelum masuk keadaan ini |
| 3 | `INACTIVE` | Tidak Berlaku | Digantikan versi lebih baru atau dihentikan. Siklus lama yang sudah menyalinnya tidak terpengaruh |

### Kenapa hanya tiga

Dokumen konsep sempat menulis empat dengan menyebut `ARCHIVED`, tetapi kata yang
dipakai adalah "misalnya" — daftarnya tidak pernah ditetapkan. Versi yang tergantikan
sudah bernama `INACTIVE`, dan baris yang salah buat ditangani `Flag_Aktif`. Keadaan
keempat yang tidak menggerbangi apa pun justru jadi sumber perbedaan antar dokumen,
yang persis ingin ditutup tabel ini.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Rater_Policy_Status` | Primary key | |
| `UQ_PA_Rater_Policy_Status_Kode` | Unique constraint | `Kode_Status` unik. Bukan sekadar index — inilah yang membuatnya sah jadi sasaran foreign key |
| `CK_PA_Rater_Policy_Status_Urutan` | Check | `Urutan_Alur >= 1` |
| `DF_PA_Rater_Policy_Status_Aktif` / `_Created` | Default | `'Y'`, `SYSDATETIME()` |

## Hubungan

`N_PA_Rater_Policy.Status_Policy` menunjuk ke sini lewat `FK_PA_Rater_Policy_Status`,
dan sasarannya **kolom kode, bukan Id**. Sebuah default constraint tidak boleh memuat
sub-kueri, jadi kunci berbasis Id memaksa mengeraskan nilai identity atau membuang
`DEFAULT ('DRAFT')`. Menunjuk kode juga membuat baris policy terbaca tanpa join.

## Keadaan data

Tiga baris seed aktif.

## Kedudukan

Lapisan pertama, dan **kamus** menurut K26 — tidak disnapshot per siklus.

## Pemakaian aplikasi

Belum ada per 2026-08-30.
