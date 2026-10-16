# N_PA_Result_Competency

Nilai per kompetensi untuk satu hasil, baik agregat maupun menurut satu kategori penilai
saja.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Result |
| Change ID | DDL-2026-081-performance-appraisal-result-detail |
| Forward SQL | `database/ddl/DDL-2026-081-performance-appraisal-result-detail/forward.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Result_Competency` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Result_Id` | `int` | T | | Hasil yang dirinci |
| `PA_Competency_Id` | `int` | T | | Kompetensi yang dinilai |
| `PA_Rater_Category_Id` | `int` | Y | | **Kosong berarti agregat lintas kategori** |
| `Nilai` | `decimal(6,2)` | Y | | |
| `Jumlah_Penilai` | `smallint` | Y | | Berapa orang yang menyumbang nilai ini |
| `Calculation_Version` | `int` | T | `1` | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kategori kosong berarti agregat

Baris tanpa `PA_Rater_Category_Id` adalah angka gabungan lintas seluruh kategori — itu
yang digambar radar chart. Baris dengan kategori terisi adalah kompetensi yang sama
dilihat oleh satu kategori saja, misalnya "menurut bawahan".

Keduanya hidup berdampingan untuk kompetensi yang sama, dan itu memang disengaja: layar
detail memperlihatkan selisih antara penilaian diri dan penilaian orang lain.

## NULL diperlakukan sama oleh unique index, dan itu dimanfaatkan

`UX_PA_Result_Competency_Aktif` mencakup `(hasil, kompetensi, kategori)` pada baris
aktif. SQL Server memperlakukan NULL sebagai nilai yang **sama** dalam unique index,
sehingga baris agregat otomatis terbatas satu per (hasil, kompetensi) tanpa aturan
tambahan apa pun.

Ini tidak diasumsikan melainkan diuji: menyisipkan baris agregat kedua untuk kompetensi
yang sama ditolak indeks itu.

## Menghitung ulang tidak menghapus

Sama seperti saudaranya: baris lama dinonaktifkan, baris versi berikutnya disisipkan.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Result_Competency` | Primary key | |
| `FK_PA_Result_Competency_Result` | Foreign key | → `N_PA_Result` |
| `FK_PA_Result_Competency_Comp` | Foreign key | → `N_PA_Competency` |
| `FK_PA_Result_Competency_Category` | Foreign key | → `N_PA_Rater_Category`, nullable |
| `UX_PA_Result_Competency_Aktif` | Unique index tersaring | `(hasil, kompetensi, kategori)` saat aktif |
| `IX_PA_Result_Competency_Agregat` | Index | Jalur baca radar chart |
| `CK_PA_Result_Competency_Nilai` | Check | Tidak negatif |
| `CK_PA_Result_Competency_Penilai` | Check | Tidak negatif |
| `CK_PA_Result_Competency_Calc_Version` | Check | Minimal 1 |

## Kedudukan

Anak dari `N_PA_Result`. Sekitar 11.000 baris per siklus.

## Pemakaian aplikasi

Belum ada per 1 September 2026.

## Keadaan data

Kosong.
