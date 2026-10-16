# N_PA_Hav_Quadrant

Kamus sembilan kotak HAV Map. Menamai kombinasi tingkat performa dan tingkat
kompetensi.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / HAV Map |
| Change ID | DDL-2026-068-performance-appraisal-hav-quadrant |
| Forward SQL | `database/ddl/DDL-2026-068-performance-appraisal-hav-quadrant/forward.sql` |
| DML | `database/dml/DDL-2026-068-performance-appraisal-hav-quadrant/data-change.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Hav_Quadrant` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Kuadran` | `varchar(40)` | T | | Kode bisnis, unik |
| `Nama_Kuadran` | `nvarchar(100)` | T | | Nama tampil; **tidak unik** |
| `Nomor_Box` | `smallint` | T | | Nomor kotak, unik selama aktif |
| `Tingkat_Performa` | `varchar(20)` | T | | `TINGGI` / `SEDANG` / `RENDAH` |
| `Tingkat_Kompetensi` | `varchar(20)` | T | | `TINGGI` / `SEDANG` / `RENDAH` |
| `Deskripsi` | `nvarchar(500)` | Y | | |
| `Warna` | `varchar(20)` | Y | | |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

### Dua kotak bernama sama

Kotak 2 dan kotak 3 sama-sama bernama "Future Star", tetapi artinya berbeda: yang satu
performa tinggi dengan kompetensi sedang, yang satu kebalikannya. Keduanya baris
terpisah dengan kode `FUTURE_STAR_A` dan `FUTURE_STAR_B`. Karena itu `Kode_Kuadran`
unik sedangkan `Nama_Kuadran` tidak.

### Tingkat disimpan sebagai kode, bukan foreign key

`Tingkat_Performa` dan `Tingkat_Kompetensi` berisi nilai yang juga muncul sebagai
`Kode_Range` di `N_PA_Score_Range` untuk `Score_Usage = 'HAVMAP'`, tetapi **sengaja bukan
foreign key**.

Kuadran hanya menamai kombinasi. Berapa angka yang disebut Tinggi adalah urusan
`N_PA_Score_Range`, dan tabel itu **disnapshot per siklus** sedangkan yang ini tidak.
Menghubungkannya dengan foreign key berarti menyeret kamus masuk ke daur hidup sebuah
aturan.

Tidak ada CHECK yang membatasi ke tiga tingkat. Tiga adalah bentuk hari ini, bukan
hukum — kalau HC pindah ke empat tingkat, CHECK justru jadi penghalang. Kecocokan
nilainya dijaga aplikasi terhadap pita HAVMAP yang aktif.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Hav_Quadrant` | Primary key | |
| `UX_PA_Hav_Quadrant_Kode` | Unique index | `Kode_Kuadran` unik |
| `UX_PA_Hav_Quadrant_Box_Aktif` | Unique index tersaring | `Nomor_Box` unik saat aktif |
| `UX_PA_Hav_Quadrant_Kombinasi_Aktif` | Unique index tersaring | `(Tingkat_Performa, Tingkat_Kompetensi)` unik saat aktif — satu kombinasi satu kotak |
| `IX_PA_Hav_Quadrant_Lookup` | Index | Jalur perhitungan menentukan kotak seseorang |
| `CK_PA_Hav_Quadrant_Nomor_Box` / `CK_PA_Hav_Quadrant_Urutan` | Check | Keduanya `>= 1` |
| `DF_PA_Hav_Quadrant_Aktif` / `DF_PA_Hav_Quadrant_Created` | Default | `'Y'`, `SYSDATETIME()` |

Ketiga keunikan menjaga kesalahan yang berbeda, dan dua di antaranya disaring pada
baris aktif supaya kuadran bisa dipensiunkan lalu diganti tanpa menghapus jejaknya.

## Yang sengaja tidak dijaga database

Bahwa kesembilan baris menutup seluruh petak tiga kali tiga tidak bisa jadi CHECK
karena melibatkan banyak baris. DML yang menjaganya: tepat sembilan baris aktif dan
tepat sembilan kombinasi berbeda, kalau tidak transaksinya dibatalkan.

## Kedudukan

Lapisan pertama, dan **kamus** menurut K26 — tidak disnapshot per siklus. Angka yang
menentukan seseorang masuk kotak mana ada di `N_PA_Score_Range` yang memang
disnapshot; tabel ini hanya menyediakan labelnya.

## Pemakaian aplikasi

Belum ada per 2026-08-30. `N_PA_Result` nanti menunjuk ke sini lewat
`PA_Hav_Quadrant_Id`.

## Keadaan data

Sembilan baris seed, menutup seluruh kombinasi:

| Box | Kode | Nama | Performa | Kompetensi |
|:---:|---|---|---|---|
| 1 | `STRIVING_STAR` | Striving Star | TINGGI | TINGGI |
| 2 | `FUTURE_STAR_A` | Future Star | TINGGI | SEDANG |
| 3 | `FUTURE_STAR_B` | Future Star | SEDANG | TINGGI |
| 4 | `POTENTIAL_CANDIDATE` | Potential Candidate | SEDANG | SEDANG |
| 5 | `TOP_PERFORMER` | Top Performer | TINGGI | RENDAH |
| 6 | `CONTRIBUTOR` | Contributor | SEDANG | RENDAH |
| 7 | `RAW_DIAMOND` | Raw Diamond | RENDAH | TINGGI |
| 8 | `PROBLEM_EMPLOYEE` | Problem Employee | RENDAH | SEDANG |
| 9 | `DEADWOOD` | Deadwood | RENDAH | RENDAH |

Pemetaan ini terkonfirmasi cocok dengan layar detail HAV Map pada mockup PIC.
`Warna` dibiarkan kosong karena nilai hex-nya belum tersedia; mengisinya nanti tidak
membutuhkan DDL.
