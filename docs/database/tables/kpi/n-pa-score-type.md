# N_PA_Score_Type

Master sumbu nilai. Tiga baris: KPI, KOMPETENSI, FINAL. Menutup kolom
`N_PA_Score_Range.Score_Type` yang sebelumnya `varchar` bebas.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Score Range |
| Change ID | DDL-2026-070-performance-appraisal-status-and-axis-masters |
| Forward SQL | `database/ddl/DDL-2026-070-performance-appraisal-status-and-axis-masters/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Score_Sumbu` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Type` | `varchar(20)` | T | | Kode bisnis, **UNIQUE**; dirujuk `N_PA_Score_Range.Score_Type` |
| `Nama_Type` | `nvarchar(100)` | T | | Nama tampil |
| `Deskripsi` | `nvarchar(500)` | Y | | |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| # | Kode | Nama | Arti |
|---|---|---|---|
| 1 | `KPI` | Nilai KPI | Score_Type hasil kerja. Berjalan pada rentang 50–125 di modul KPI |
| 2 | `KOMPETENSI` | Nilai Kompetensi | Score_Type hasil penilaian 360 derajat |
| 3 | `FINAL` | Nilai Akhir | Gabungan kedua pilar sesuai bobot pilar per level |

## Kenapa ini tabel, padahal daftarnya tidak akan tumbuh

Dokumentasi DDL-2026-067 sempat menyimpulkan sebaliknya: karena ketiga nilai
ditentukan oleh desain dan bukan dikurasi HC, tabel acuan dianggap tidak menambah
apa-apa. Alasan itu menjawab pertanyaan yang salah.

Risikonya bukan daftarnya bertambah, melainkan **nilai yang tetap dieja berbeda**.
`COMPETENCY` alih-alih `KOMPETENSI` akan diterima `varchar` tanpa keluhan, lalu diam-
diam tidak cocok dengan apa pun saat dicari. Tabel acuan berharga tiga baris dan
menghapus kemungkinan itu sepenuhnya — uji pada change-nya menunjukkan `COMPETENCY`
ditolak. Ini tercatat sebagai K31.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Score_Type` | Primary key | |
| `UQ_PA_Score_Type_Kode` | Unique constraint | `Kode_Type` unik; inilah yang membuatnya sah jadi sasaran foreign key |
| `CK_PA_Score_Type_Urutan` | Check | `Urutan_Tampil >= 1` |
| `DF_PA_Score_Type_Aktif` / `DF_PA_Score_Type_Created` | Default | `'Y'`, `SYSDATETIME()` |

## Hubungan

`N_PA_Score_Range.Score_Type` menunjuk ke sini lewat `FK_PA_Score_Range_Type`, dengan
sasaran kolom kode agar baris pita nilai tetap terbaca tanpa join.

## Kedudukan

Lapisan pertama, dan **kamus** menurut K26 — tidak disnapshot per siklus.

## Pemakaian aplikasi

Belum ada per 2026-08-30.

## Keadaan data

Tiga baris seed aktif.
