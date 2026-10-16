# N_PA_Cycle

Header satu pelaksanaan Performance Appraisal. Satu baris menyatakan appraisal apa
yang dijalankan, periode kerja mana yang dinilai, kapan penilaian boleh diisi, dan
kondisi organisasi tanggal berapa yang dipakai.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-066-performance-appraisal-cycle |
| Forward SQL | `database/ddl/DDL-2026-066-performance-appraisal-cycle/forward.sql` |
| DML | `database/dml/DDL-2026-066-performance-appraisal-cycle/data-change.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Cycle` | `varchar(50)` | T | | Kode bisnis, unik dan stabil |
| `Nama_Cycle` | `nvarchar(150)` | T | | Nama tampil, misal "PA Semester 1 2027" |
| `Tahun` | `smallint` | T | | Tahun siklus. Terisi sejak DRAFT, sebelum tanggalnya ditetapkan |
| `Deskripsi` | `nvarchar(500)` | Y | | Lingkup atau catatan operasional |
| `Periode_Dinilai_Dari` | `date` | Y | | Tanggal pertama kinerja yang dinilai |
| `Periode_Dinilai_Sampai` | `date` | Y | | Tanggal terakhir kinerja yang dinilai |
| `Pengisian_Dari` | `datetime2(0)` | Y | | Penilaian mulai boleh diisi |
| `Pengisian_Sampai` | `datetime2(0)` | Y | | Due_At pengisian |
| `Tanggal_Acuan_Organisasi` | `date` | Y | | Tanggal efektif pembacaan organisasi dan kandidat penilai |
| `PA_Cycle_Sumber_Id` | `int` | Y | | Self-FK bila siklus ini salinan siklus lain |
| `Status_Cycle` | `varchar(20)` | T | `'DRAFT'` | FK ke `N_PA_Cycle_Status.Kode_Status` (dipasang DDL-069) |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` mempensiunkan baris salah buat atau dibatalkan |
| `Locked_At` / `Locked_By` | `datetime2(0)` / `int` | Y | | Kapan dan siapa mengunci seluruh salinan siklus |
| `Published_At` / `Published_By` | `datetime2(0)` / `int` | Y | | Kapan dan siapa mempublikasikan hasil |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users`; NULL untuk aktor sistem |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |
| `Min_Masa_Kerja_Bulan` | `int` | Y | | Salinan minimal masa kerja peserta (bulan) dari `N_PA_Eligibility_Setting` saat siklus dibuat (DDL-2026-191). NULL = siklus lama, aturan tidak berlaku. Hanya bisa diubah sebelum peserta dibentuk |

### Kenapa seluruh tanggal boleh kosong

Siklus lahir sebagai DRAFT ketika namanya dan tahunnya sudah diputuskan tetapi
jadwalnya belum. Mockup PIC memperlihatkannya langsung: baris "Performance Appraisal
2027 (Draft)" bertahun 2027 dengan tanggal mulai dan berakhir kosong. Kalau kolomnya
NOT NULL, admin terpaksa mengarang tanggal hanya untuk membuka draf — dan tanggal
karangan tidak bisa dibedakan dari tanggal sungguhan.

Kelengkapan tanggal **wajib** sebelum siklus boleh keluar dari DRAFT. Itu gerbang
aplikasi, bukan constraint kolom, karena syaratnya bergantung pada status.

CHECK rentangnya tetap bekerja pada baris yang tanggalnya terisi: akhir tidak boleh
mendahului awal, tetapi baris yang salah satu ujungnya masih kosong dibiarkan lewat.

### Dua rentang tanggal, bukan jenis siklus

`Periode_Dinilai_*` adalah kerja yang dinilai; `Pengisian_*` adalah kapan penilai
boleh mengisi. Quarter, semester, dan tahunan hanyalah label turunan dari rentang
pertama, bukan jenis yang disimpan (K03).

### Status dan flag bukan hal yang sama

`Status_Cycle` menyatakan sudah sampai mana siklusnya berjalan. Daftar resminya ada
di `N_PA_Cycle_Status` dan dijaga foreign key sejak DDL-2026-069, jadi status di luar
daftar itu ditolak database. Urutannya
`DRAFT → PREPARED → LOCKED → OPEN → CLOSED → PUBLISHED → ARCHIVED`; `LOCKED`
membekukan salinan sedangkan `OPEN` membuka pengisian — dua peristiwa berbeda walau
bisa terjadi berdekatan.

`Flag_Aktif` mempensiunkan barisnya karena salah buat atau dibatalkan, terlepas dari
sudah sampai mana lifecycle-nya. Itu berbeda dari `ARCHIVED`, yang berarti siklusnya
selesai dengan wajar lalu disimpan sebagai riwayat.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle` | Primary key | `Id_PA_Cycle` |
| `FK_PA_Cycle_Source` | Foreign key | `PA_Cycle_Sumber_Id` → dirinya sendiri |
| `UX_PA_Cycle_Kode` | Unique index | `Kode_Cycle` unik |
| `FK_PA_Cycle_Status` | Foreign key | `Status_Cycle` → `N_PA_Cycle_Status.Kode_Status`, dipasang DDL-2026-069 |
| `CK_PA_Cycle_Tahun` | Check | `Tahun >= 2000`, penjaga salah ketik |
| `CK_PA_Cycle_Periode_Dinilai` | Check | Akhir periode tidak boleh mendahului awal; toleran NULL |
| `CK_PA_Cycle_Pengisian` | Check | Akhir pengisian tidak boleh mendahului awal; toleran NULL |
| `CK_PA_Cycle_Source_Different` | Check | Siklus sumber tidak boleh dirinya sendiri |
| `DF_PA_Cycle_Status` / `DF_PA_Cycle_Aktif` / `DF_PA_Cycle_Created` | Default | `'DRAFT'`, `'Y'`, `SYSDATETIME()` |

Tidak ada index selain PK dan unique kode. Tabel ini isinya beberapa baris per tahun,
jadi index status atau periode hanya menambah biaya tulis tanpa manfaat baca.

`Status_Cycle` tidak diberi CHECK. Pembatasannya lewat foreign key ke tabel acuan,
bukan enum — menambah status berarti menyisipkan baris di master, bukan mengubah
skema. Ini mengikuti aturan proyek untuk daftar nilai bisnis yang masih berkembang.

## Yang sengaja tidak dijaga database

Tumpang tindih periode penilaian dilarang **per karyawan** (K04, penjaga G1). Aturan
itu melibatkan banyak baris dan bergantung pada keanggotaan peserta, jadi tidak ada
CHECK yang bisa menyatakannya. Itu gerbang aplikasi sebelum siklus boleh dikunci.

`Tanggal_Acuan_Organisasi` normalnya tidak melewati waktu penguncian. Ini juga aturan
aplikasi, sebab `Locked_At` baru ditulis belakangan.

Kelengkapan seluruh tanggal sebelum siklus keluar dari DRAFT juga aturan aplikasi,
karena syaratnya bergantung pada `Status_Cycle`.

## Kedudukan dalam konfigurasi tiga lapis

```
N_PA_Rater_Policy / N_PA_Pillar_Weight     cetak biru
N_PA_Cycle_Status                          master status
  └─ N_PA_Cycle                            header siklus (tabel ini)
       └─ N_PA_Cycle_*_Snapshot            salinan per (siklus, level)  [belum dibuat]
            └─ N_PA_Participant_*          baris per orang               [belum dibuat]
```

## Pemakaian aplikasi

Belum ada. Tidak ada model, service, controller, route, job, atau command yang
menyentuh tabel ini per 2026-08-30.

## Keadaan data

Kosong. Belum ada siklus yang dibuat.
