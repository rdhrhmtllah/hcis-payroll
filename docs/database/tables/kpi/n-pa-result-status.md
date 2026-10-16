# N_PA_Result_Status

Kamus tertutup status satu baris hasil.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Result |
| Change ID | DDL-2026-080-performance-appraisal-result |
| Forward SQL | `database/ddl/DDL-2026-080-performance-appraisal-result/forward.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Result_Status` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Status` | `varchar(20)` | T | | `UNIQUE`; inilah yang ditunjuk FK |
| `Nama_Status` | `nvarchar(100)` | T | | Label layar |
| `Deskripsi` | `nvarchar(500)` | Y | | |
| `Urutan_Alur` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| Kode | Urutan | Arti |
|---|:---:|---|
| `BELUM_DIHITUNG` | 1 | Baris hasil sudah ada bersama pesertanya, perhitungan belum pernah dijalankan |
| `DIHITUNG` | 2 | Nilai akhir dan grade sudah terbentuk, belum diterbitkan |
| `TERBIT` | 3 | Sudah dapat dilihat karyawan yang bersangkutan |
| `TERTAHAN` | 4 | Penilainya kurang dari ambang minimum |

## Baris dibuat sejak awal, bukan saat perhitungan

Satu baris hasil lahir bersamaan dengan pesertanya, berstatus `BELUM_DIHITUNG`. Kalau
barisnya baru muncul setelah perhitungan berhasil, **ketiadaan baris jadi bermakna
ganda** — belum dihitung, tidak memenuhi syarat, atau memang tidak diikutkan.

Dengan barisnya selalu ada, status inilah yang menjawab, dan layar monitoring cukup
menghitung per status tanpa membandingkan dua daftar.

## `TERTAHAN` cabang, bukan tahap

Urutannya 4 tetapi ia tidak datang sesudah `TERBIT`. Peserta yang penilainya kurang dari
ambang `Min_Rater` berhenti di sana alih-alih diberi angka yang tampak wajar padahal
datanya bolong. Alasannya wajib ditulis di `N_PA_Result.Alasan_Tertahan`.

## Constraint

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Result_Status` | Primary key | |
| `UQ_PA_Result_Status_Kode` | Unique | Sasaran FK; menunjuk kode bukan Id supaya `DEFAULT ('BELUM_DIHITUNG')` bekerja |
| `CK_PA_Result_Status_Urutan` | Check | `Urutan_Alur >= 1` |

## Kedudukan

Kamus lapisan hasil. Tidak punya salinan siklus — makna status tidak boleh berbeda
antar siklus (K26).

## Keadaan data

Empat baris, semuanya aktif.
