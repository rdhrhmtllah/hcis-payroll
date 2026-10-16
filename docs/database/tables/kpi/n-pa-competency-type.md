# N_PA_Competency_Type

Master jenis kompetensi Performance Appraisal. Empat baris: MANAJERIAL, CORE_VALUE,
BEHAVIOR, KUALITATIF.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Competency |
| Change ID | DDL-2026-067-performance-appraisal-competency-and-score-range |
| Forward SQL | `database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/forward.sql` |
| DML | `database/dml/DDL-2026-067-performance-appraisal-competency-and-score-range/data-change.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Competency_Type` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Type` | `varchar(40)` | T | | Kode bisnis, unik |
| `Nama_Type` | `nvarchar(100)` | T | | Nama tampil |
| `Deskripsi` | `nvarchar(500)` | Y | | Penjelasan jenis |
| `Urutan_Tampil` | `smallint` | T | | Urutan tampil, mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` mempensiunkan jenis tanpa menghapusnya |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users`; NULL untuk aktor sistem |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa ini tabel, bukan varchar

Jenis kompetensi awalnya dirancang sebagai kolom `varchar` di `N_PA_Competency`.
Dijadikan master atas permintaan user karena daftarnya dikurasi HC dan wajar
bertambah atau berkurang. Ini juga menyeragamkannya dengan `N_PA_Rater_Category`
yang sudah lebih dulu jadi master dengan alasan sama.

Bandingkan dengan `Score_Type` dan `Score_Usage` di `N_PA_Score_Range` yang **tetap**
`varchar`: keduanya ditentukan oleh desain, bukan dikurasi HC. Menambah salah satunya
berarti merancang ulang, bukan menambah data.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Competency_Type` | Primary key | `Id_PA_Competency_Type` |
| `UX_PA_Competency_Type_Kode` | Unique index | `Kode_Type` unik |
| `CK_PA_Competency_Type_Urutan` | Check | `Urutan_Tampil >= 1` |
| `DF_PA_Competency_Type_Aktif` / `_Created` | Default | `'Y'`, `SYSDATETIME()` |

## Kedudukan

Lapisan pertama, dan **kamus** menurut K26 — tidak disnapshot per siklus. Arti sebuah
jenis tidak boleh berbeda antar siklus.

## Pemakaian aplikasi

Belum ada per 2026-08-30.

## Keadaan data

Empat baris seed aktif: `MANAJERIAL`, `CORE_VALUE`, `BEHAVIOR`, `KUALITATIF`.
