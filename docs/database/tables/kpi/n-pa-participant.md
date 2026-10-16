# N_PA_Participant

Satu baris untuk satu karyawan yang diikutkan atau secara eksplisit dikecualikan dari
sebuah siklus, dengan kondisi organisasinya dibekukan saat siklus disiapkan.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Participant |
| Change ID | DDL-2026-072-performance-appraisal-participant (revisi r2) |
| Forward SQL | `database/ddl/DDL-2026-072-performance-appraisal-participant/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Participant` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Id` | `int` | T | | FK ke `N_PA_Cycle` |
| `Kode_Perusahaan` | `varchar(3)` | T | | Bagian pertama identitas karyawan |
| `Kode_Karyawan` | `varchar(255)` | T | | Bagian kedua; bersama-sama FK komposit ke `Karyawan` |
| `Nama_Karyawan_Saat_Cycle` | `varchar(255)` | T | | Salinan `Karyawan.Nama` |
| `ID_Level_Jabatan_Saat_Cycle` | `bigint` | T | | Salinan `Karyawan.ID_Level_Jabatan` — nilai mentahnya |
| `ID_Level_Saat_Cycle` | `bigint` | T | | **Turunan**; FK ke `HRIS_Level` |
| `Nama_Level_Saat_Cycle` | `varchar(100)` | Y | | Salinan `nama_level` dari view golongan |
| `ID_Divisi_Sub_Divisi_Saat_Cycle` | `bigint` | Y | | Salinan mentah `Karyawan.ID_Divisi_Sub_Divisi` |
| `ID_Divisi_Saat_Cycle` | `bigint` | Y | | Divisi **induk**, turunan lewat `View_Divisi_Sub_Divisi` |
| `Nama_Divisi_Saat_Cycle` | `varchar(100)` | Y | | |
| `ID_Sub_Divisi_Saat_Cycle` | `bigint` | Y | | Sub divisi, anak dari yang di atas |
| `Nama_Sub_Divisi_Saat_Cycle` | `varchar(100)` | Y | | |
| `Kode_Jabatan_Saat_Cycle` | `varchar(30)` | Y | | Salinan `Karyawan.Kode_Jabatan` |
| `Nama_Jabatan_Saat_Cycle` | `varchar(100)` | Y | | |
| `Lokasi_Saat_Cycle` | `varchar(15)` | Y | | Salinan `Karyawan.Lokasi`; di dev DISTRIBUTOR atau INDUK |
| `PA_Cycle_Rater_Policy_Snapshot_Id` | `int` | Y | | Aturan penilai yang berlaku; kosong hanya saat `TERTUNDA` atau `DIKECUALIKAN` |
| `Status_Peserta` | `varchar(20)` | T | `'TERTUNDA'` | FK ke `N_PA_Participant_Status.Kode_Status` |
| `Alasan_Dikecualikan` | `nvarchar(500)` | Y | | Wajib diisi aplikasi saat status `DIKECUALIKAN` |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | Kapan kondisinya dibekukan |
| `Snapshot_By` | `int` | Y | | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Level tidak ada di Karyawan — ini rantainya

`Karyawan` **tidak punya kolom `ID_Level` sama sekali**. Nilainya diresolusi dua
lompatan (K19):

```
Karyawan.ID_Level_Jabatan
  -> View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level
       -> HRIS_Level.ID_Level
```

Terukur di development: view itu 1:1 dengan `HRIS_Level_Jabatan` tanpa fan-out, nol
`ID_Level` NULL, dan menutup 958 dari 958 karyawan aktif.

Jangan pakai `divisi_level_by_Kode_Karyawan` walaupun lebih pendek. View itu membuang
`Kode_Perusahaan` dan hanya berkunci `Kode_Karyawan` — aman sekarang karena tidak ada
kode kembar, tetapi identitas karyawan di HCIS memang komposit dan memakainya menanam
jebakan.

### Kenapa mentah dan turunan disimpan dua-duanya

`ID_Level_Jabatan_Saat_Cycle` yang benar-benar melekat pada orangnya;
`ID_Level_Saat_Cycle` yang dipakai memilih Policy. Menyimpan turunannya saja
menghilangkan kemampuan memeriksa ulang resolusinya belakangan; menyimpan mentahnya
saja memaksa setiap kueri berikutnya menyusuri view lagi.

### Kenapa NOT NULL padahal resolusinya bisa gagal

Kalau resolusi gagal, barisnya jangan dibuat setengah jadi. Kegagalan itulah yang
mengisi keranjang, dan penjaga G10 memblokir penguncian sampai bersih.

## Divisi dipecah induk dan anak — dan namanya terbalik dari mockup

Layar Monitoring menyaring peserta per Departemen, Divisi, Jabatan, dan Lokasi, lalu
mengelompokkan heatmap-nya per Departemen. Menyimpan divisi sebagai satu nama gabungan
membuat pengelompokan per induk mustahil, dan monitoring terpaksa join ke `Karyawan`
yang hidup — yang membatalkan gunanya pembekuan, karena orang yang pindah divisi di
tengah siklus ikut berpindah di laporan historis.

**Perhatikan jebakan penamaannya.** Di mockup, Departemen adalah induk dan Divisi
anaknya (Human Capital → HC Learning & Development). Di HCIS justru terbalik:

```
nama_divisi          induk, 20 nilai: SALES & DISTRIBUTION, HC-GA, IT, ...
  nama_sub_divisi    anaknya
    N_HRIS_Master_Departemen   menggantung DI BAWAH sub-divisi
```

Jadi "Departemen" pada mockup adalah `nama_divisi` di database ini. Memasangnya dengan
cara yang tampak wajar menghasilkan filter yang kelihatan jalan tetapi mengelompokkan
level yang salah.

Sumbernya `View_Divisi_Sub_Divisi`, terukur menutup 958 dari 958 karyawan aktif.

## Identitas karyawan dijaga foreign key komposit

`Karyawan` punya primary key `(Kode_Perusahaan, Kode_Karyawan)`, jadi identitas
pesertanya ditegakkan database, bukan sekadar dipercaya. Kunci satu kolom akan jadi
cacat terpendam begitu ada perusahaan kedua.

## Nama disalin, pengenal dipertahankan

Kolom berakhiran `_Saat_Cycle` ada supaya laporan historis tetap terbaca benar setelah
orangnya berganti nama, pindah divisi, atau naik jabatan. Pengenalnya tetap disimpan
untuk penelusuran.

## Publikasi tidak di sini

Sketsa awal menaruh `Flag_Terbit` pada peserta. Yang diterbitkan adalah **hasilnya**,
dan sebagian peserta bisa tertahan sementara yang lain terbit, jadi keadaan itu milik
`N_PA_Result.Status_Hasil`.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Participant` | Primary key | |
| `FK_PA_Participant_Cycle` | Foreign key | → `N_PA_Cycle` |
| `FK_PA_Participant_Karyawan` | Foreign key komposit | `(Kode_Perusahaan, Kode_Karyawan)` → `Karyawan` |
| `FK_PA_Participant_Level` | Foreign key | `ID_Level_Saat_Cycle` → `HRIS_Level` |
| `FK_PA_Participant_Rater_Snapshot` | Foreign key | → `N_PA_Cycle_Rater_Policy_Snapshot` |
| `FK_PA_Participant_Status` | Foreign key | `Status_Peserta` → master status, menunjuk kode bukan Id supaya `DEFAULT ('TERTUNDA')` tetap bekerja |
| `UX_PA_Participant_Cycle_Karyawan` | Unique index tersaring | Satu karyawan satu baris aktif per siklus |
| `IX_PA_Participant_Cycle_Status` | Index | `(PA_Cycle_Id, Status_Peserta, Flag_Aktif)` dengan `INCLUDE` level, divisi induk, sub divisi, dan lokasi — jalur keranjang sekaligus monitoring, sehingga pengelompokannya tidak menyentuh tabel |

## Yang sengaja tidak dijaga database

`Alasan_Dikecualikan` wajib saat status `DIKECUALIKAN`. CHECK tingkat baris sebenarnya
bisa menyatakannya, tetapi hanya dengan mengeraskan literal `'DIKECUALIKAN'` ke dalam
skema — persis yang dilarang aturan proyek untuk daftar nilai bisnis, dan persis yang
ingin dihindari dengan membuat master statusnya.

Siapa yang layak masuk siklus — hanya yang aktif, jenis kontrak, batas tanggal resign
— aturan persiapan, bukan constraint. Begitu pula keharusan peserta `DIIKUTKAN`
menunjuk salinan aturan penilai.

## Kedudukan

Lapisan ketiga bagian pertama, dan satu-satunya yang berisi baris untuk **setiap**
peserta. Tiga tabel penyimpangan yang menggantung di bawahnya —
`N_PA_Participant_Rater_Override`, `_Pillar_Override`, `_Requirement_Override`
(DDL-074) — normalnya kosong: barisnya lahir hanya saat aturan satu orang diubah.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.
