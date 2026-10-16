# N_PA_Participant_Pillar_Override

Pembagian bobot KPI dan kompetensi milik **satu** peserta, ditulis hanya bila berbeda
dari salinan siklus levelnya. Normalnya kosong.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Participant |
| Change ID | DDL-2026-074-performance-appraisal-participant-overrides |
| Forward SQL | `database/ddl/DDL-2026-074-performance-appraisal-participant-overrides/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Participant_Pillar_Override` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Participant_Id` | `int` | T | | FK ke `N_PA_Participant` |
| `Bobot_KPI` | `decimal(5,2)` | T | | |
| `Bobot_Kompetensi` | `decimal(5,2)` | T | | |
| `Alasan_Perubahan` | `nvarchar(500)` | T | | Wajib |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | Mencabutnya mengembalikan orang itu ke bobot siklus |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa dua kolom saja, tanpa jejak salinan

Berbeda dari dua tabel override lainnya, di sini tidak ada `Flag_Dipakai` dan tidak ada
kolom yang menunjuk baris salinan siklus. Pembagian pilar bukan daftar yang butirnya
bisa dicabut atau ditambah — ia satu angka berpasangan yang selalu ada. Orang tanpa KPI
tidak "mencabut" pilar KPI, ia berbobot `0` dan `100`, dan itu tersimpan apa adanya.

Salinan siklus yang digantikan juga tidak perlu ditunjuk: `N_PA_Participant` sudah
membawa level saat siklus, jadi baris asalnya selalu bisa ditemukan tanpa kolom
tambahan yang bisa jadi basi.

## Total harus tepat 100

`CHECK (Bobot_KPI + Bobot_Kompetensi = 100)` ditegakkan database, dan ini satu-satunya
aturan lintas kolom di keluarga override yang bisa dijaga penuh di tingkat baris —
karena kedua angkanya duduk di baris yang sama. Bobot antar kategori penilai tidak bisa
diperlakukan begitu, sebab tersebar di banyak baris dan bercampur dengan salinan siklus.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Participant_Pillar_Override` | Primary key | |
| `FK_PA_Participant_Pillar_Ovr_Participant` | Foreign key | → `N_PA_Participant` |
| `UX_PA_Participant_Pillar_Ovr_Aktif` | Unique index tersaring | Satu override aktif per peserta |
| `CK_PA_Participant_Pillar_Ovr_Non_Negatif` | Check | Kedua bobot ≥ 0 |
| `CK_PA_Participant_Pillar_Ovr_Total` | Check | Jumlahnya tepat 100 |

## Yang sengaja tidak dijaga database

Larangan menulis override setelah siklus dikunci; bergantung pada
`N_PA_Cycle.Status_Cycle` yang tidak terlihat dari baris ini.

## Kedudukan

Lapisan ketiga, menyimpang dari `N_PA_Cycle_Pillar_Weight_Snapshot`. Perhitungan tidak
membaca tabel ini langsung melainkan lewat `PaParticipantRuleResolver` (K32).

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.
