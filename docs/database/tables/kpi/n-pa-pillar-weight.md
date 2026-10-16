# N_PA_Pillar_Weight

Cetak biru pembagian pilar Performance Appraisal: berapa persen nilai akhir seorang
karyawan berasal dari KPI dan berapa persen dari kompetensi. Satu baris per level.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Pillar Weight |
| Change ID | DDL-2026-065-performance-appraisal-pillar-weight |
| Forward SQL | `database/ddl/DDL-2026-065-performance-appraisal-pillar-weight/forward.sql` |
| DML | `database/dml/DDL-2026-065-performance-appraisal-pillar-weight/data-change.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Pillar_Weight` | `int IDENTITY(1,1)` | T | | Primary key |
| `ID_Level` | `bigint` | T | | Level yang diatur. FK ke `HRIS_Level.ID_Level`, unik |
| `Bobot_KPI` | `decimal(5,2)` | T | | Porsi pilar KPI, dalam persen |
| `Bobot_Kompetensi` | `decimal(5,2)` | T | | Porsi pilar kompetensi, dalam persen |
| `Catatan` | `nvarchar(500)` | Y | | Kenapa angka standar ini dipilih |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users`; NULL untuk aktor sistem |
| `Updated_At` | `datetime2(0)` | Y | | |
| `Updated_By` | `int` | Y | | `KPI_Users.Id_Users` |

Tidak ada `Flag_Aktif`. Satu level entah punya bobot atau tidak punya sama sekali;
"bobot nonaktif" tidak punya arti bisnis, dan memasang flag akan memaksa setiap
pencarian bobot menyaring — sekali lupa menyaring, hasilnya bukan error melainkan
nilai akhir yang diam-diam salah.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Pillar_Weight` | Primary key | `Id_PA_Pillar_Weight` |
| `FK_PA_Pillar_Weight_Level` | Foreign key | `ID_Level` → `HRIS_Level.ID_Level` |
| `UX_PA_Pillar_Weight_Level` | Unique index | Satu aturan per level. `INCLUDE (Bobot_KPI, Bobot_Kompetensi)` supaya pencarian bobot tidak menyentuh tabel |
| `CK_PA_Pillar_Weight_Total` | Check | `Bobot_KPI + Bobot_Kompetensi = 100` |
| `CK_PA_Pillar_Weight_Non_Negatif` | Check | Kedua bobot `>= 0` |
| `DF_PA_Pillar_Weight_Created` | Default | `Created_At` = `SYSDATETIME()` |

Batas atas tidak diperiksa terpisah: bila jumlahnya 100 dan keduanya tidak negatif,
masing-masing otomatis tidak melebihi 100.

`Bobot_KPI = 0` diperbolehkan, artinya level itu dinilai murni dari kompetensi.
Itu keputusan bisnis, bukan data salah.

## Kedudukan dalam konfigurasi tiga lapis

Tabel ini lapisan pertama. Isinya boleh berubah kapan saja dan berlaku lintas siklus.

```
N_PA_Pillar_Weight                     cetak biru (tabel ini)
  └─ N_PA_Cycle_Pillar_Weight_Snapshot salinan per (siklus, level)
       └─ N_PA_Participant_Pillar_Override  HANYA bila satu orang menyimpang
```

Perhitungan **tidak** membaca tabel ini. Ia meminta `PaParticipantRuleResolver`, yang
memakai override bila ada dan lapisan kedua bila tidak. Lapisan kedua
menyimpan aturan yang berlaku di satu siklus sekaligus menjadi acuan pembanding
untuk menandai peserta yang menyimpang — cetak biru tidak bisa dipakai sebagai
pembanding karena isinya berubah antar siklus.

Bobot pilar sengaja tidak disimpan di `N_PA_Rater_Policy` karena keduanya membeku di
waktu berbeda. Lihat K23 pada dokumen desain.

## Pemakaian aplikasi

Belum ada. Tidak ada model, service, controller, route, job, atau command yang
menyentuh tabel ini per 2026-08-30. Halaman admin PA belum terdaftar di
`N_HRIS_Grup_Fitur_Map`.

## Keadaan data

Kosong. Angka pembagian pilar per level belum pernah diberikan HC — deck PIC hanya
memuat bobot antar penilai. Tercatat sebagai T11 pada dokumen desain.
