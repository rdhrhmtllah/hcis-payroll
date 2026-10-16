# N_PA_Score_Range

Master pita nilai. Satu tabel melayani tiga kegunaan dari nilai yang sama: konversi
antar skala, pita tiga tingkat untuk HAV Map, dan pita grade beserta target
distribusinya.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Score Range |
| Change ID | DDL-2026-067-performance-appraisal-competency-and-score-range (revisi r2) |
| Forward SQL | `database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Score_Range` | `int IDENTITY(1,1)` | T | | Primary key |
| `Score_Type` | `varchar(20)` | T | | FK ke `N_PA_Score_Type.Kode_Type`: `KPI`, `KOMPETENSI`, `FINAL` |
| `Score_Usage` | `varchar(20)` | T | | FK ke `N_PA_Score_Usage.Kode_Usage`: `HAVMAP`, `GRADE`, `KONVERSI` |
| `Kode_Range` | `varchar(40)` | T | | `TINGGI`/`SEDANG`/`RENDAH` untuk HAVMAP; `A`/`B+`/`B`/`C+`/`C` untuk GRADE |
| `Nama_Range` | `nvarchar(100)` | T | | Nama tampil |
| `Nilai_Dari` | `decimal(6,2)` | T | | Batas bawah, **inklusif** |
| `Nilai_Sampai` | `decimal(6,2)` | T | | Batas atas, **eksklusif** |
| `Nilai_Hasil` | `decimal(6,2)` | Y | | Hasil konversi, dipakai bila `Score_Usage = 'KONVERSI'` |
| `Target_Persen` | `decimal(5,2)` | Y | | Target distribusi grade untuk kalibrasi; NULL untuk pita non-grade |
| `Warna` | `varchar(20)` | Y | | Untuk tampilan HAV Map |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

### Batas atas eksklusif

`Nilai_Dari <= x < Nilai_Sampai`. Kalau batas atas dibuat inklusif, pita KPI yang ada
sekarang — di bawah 75, lalu 75–89, lalu 90–105 — meninggalkan 89,5 di celah yang
bukan milik pita mana pun. Batas setengah terbuka membuat pita bersambung rapat tanpa
celah maupun tumpang tindih.

### Target_Persen untuk forced distribution

Halaman Kalibrasi membandingkan porsi aktual tiap grade dengan targetnya — pada mockup
A 10%, B+ 25%, B 40%, C+ 20%, C 5%. Target itu sifat dari pita grade-nya sendiri, jadi
kolom dan bukan tabel: grade A berarti "skor 86 ke atas **dan** menyasar 10% populasi".

Jumlah target seluruh grade aktif harus 100. Itu aturan lintas baris sehingga tidak
bisa dijaga CHECK — gerbang aplikasi, sama seperti total bobot di
`N_PA_Rater_Policy_Detail`.

## Score_Type dan Score_Usage punya master sendiri

Dokumen ini sempat menyimpulkan keduanya cukup `varchar` karena daftarnya ditentukan
desain dan tidak akan tumbuh. Kesimpulan itu **dibatalkan** oleh DDL-2026-070 (K31).

Risikonya memang bukan pertumbuhan, melainkan nilai tetap yang dieja berbeda antar
sesi: `COMPETENCY` alih-alih `KOMPETENSI` akan diterima `varchar` lalu diam-diam tidak
cocok dengan apa pun. Keduanya kini ber-foreign key ke `N_PA_Score_Type` dan
`N_PA_Score_Usage`.

`N_PA_Score_Usage` juga menyatakan kolom opsional mana milik kegunaan mana:
`Nilai_Hasil` hanya untuk `KONVERSI`, `Target_Persen` hanya untuk `GRADE`.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Score_Range` | Primary key | |
| `FK_PA_Score_Range_Type` | Foreign key | `Score_Type` → `N_PA_Score_Type.Kode_Type`, dipasang DDL-2026-070 |
| `FK_PA_Score_Range_Usage` | Foreign key | `Score_Usage` → `N_PA_Score_Usage.Kode_Usage`, dipasang DDL-2026-070 |
| `UX_PA_Score_Range_Aktif` | Unique index tersaring | `(Score_Type, Score_Usage, Kode_Range)` `WHERE Flag_Aktif = 'Y'` |
| `IX_PA_Score_Range_Lookup` | Index | `(Score_Type, Score_Usage, Flag_Aktif)` dengan `INCLUDE` seluruh kolom nilai — jalur "nilai sekian masuk pita mana" |
| `CK_PA_Score_Range_Batas` | Check | `Nilai_Sampai >= Nilai_Dari` |
| `CK_PA_Score_Range_Target` | Check | `Target_Persen` NULL atau 0–100 |
| `CK_PA_Score_Range_Urutan` | Check | `Urutan_Tampil >= 1` |
| `DF_PA_Score_Range_Aktif` / `DF_PA_Score_Range_Created` | Default | `'Y'`, `SYSDATETIME()` |

## Yang sengaja tidak dijaga database

Pita dalam satu `(Score_Type, Score_Usage)` tidak boleh tumpang tindih. Aturan itu melibatkan
banyak baris sehingga tidak ada CHECK yang bisa menyatakannya — gerbang aplikasi.

## Kedudukan

Lapisan pertama, dan **aturan** menurut K26 — punya salinan siklus
`N_PA_Cycle_Score_Range_Snapshot` yang belum dibuat. Berhenti di lapisan dua saja,
tanpa lapisan per orang: dua orang bernilai sama harus mendapat grade sama.

## Pemakaian aplikasi

Belum ada per 2026-08-30.

## Keadaan data

Kosong. Skala kanonik dan batas pitanya masih terbuka (T05) — deck menampilkan skor
pada skala 1–5 sedangkan mockup memakai persen, sementara HC Learn mengembalikan 0–100
dan KPI berjalan pada 50–125.
