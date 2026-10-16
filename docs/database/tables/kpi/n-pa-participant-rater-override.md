# N_PA_Participant_Rater_Override

Penyimpangan aturan penilai milik **satu** peserta. Baris hanya lahir ketika admin
mengubah aturan untuk orang itu; normalnya tabel ini kosong.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Participant |
| Change ID | DDL-2026-074-performance-appraisal-participant-overrides |
| Forward SQL | `database/ddl/DDL-2026-074-performance-appraisal-participant-overrides/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Participant_Rater_Override` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Participant_Id` | `int` | T | | FK ke `N_PA_Participant` |
| `PA_Rater_Category_Id` | `int` | T | | Kategori penilai yang diubah |
| `PA_Cycle_Rater_Policy_Detail_Snapshot_Id` | `int` | Y | | Baris salinan siklus yang digantikan; **NULL berarti kategori ini ditambahkan**, bukan diubah |
| `Flag_Dipakai` | `char(1)` | T | `'Y'` | `T` mencabut kategori ini untuk orang tersebut |
| `Bobot_Persen` | `decimal(5,2)` | Y | | Bobot antar kategori, menggantikan bobot salinan siklus |
| `Min_Rater` | `smallint` | Y | | |
| `Maks_Rater` | `smallint` | Y | | NULL berarti **tanpa batas atas**, bukan "warisi" |
| `Min_Rater_Selesai` | `smallint` | Y | | Ambang kelengkapan |
| `Alasan_Perubahan` | `nvarchar(500)` | T | | Wajib — baris ini ada justru karena menyimpang |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | Mencabut override berarti orang itu kembali ke aturan siklus |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa hanya penyimpangan yang disimpan

Rancangan awal menyalin aturan untuk **setiap** peserta — sekitar 6.600 baris per
siklus. Bentuk itu ditinggalkan (K32).

Materialisasi penuh menaruh risikonya di jalur **tulis**: baris sebanyak itu benar
hanya jika penyemainya benar, dan kalau salah, datanya sudah telanjur tersimpan,
tampak wajar, dan membereskannya butuh migrasi data untuk tiap siklus terdampak.
Bentuk jarang memindahkan risikonya ke jalur **baca** — satu resolver yang memilih
override bila ada dan salinan siklus bila tidak. Kalau resolver itu salah, semuanya
salah sekaligus dan kentara, dan perbaikannya deploy kode tanpa menyentuh data.

Bug tulis dibereskan di data; bug baca dibereskan di kode. Asimetri itu yang
memutuskan.

## Tiga bentuk penyimpangan tanpa kolom jenis

| Yang terjadi | Cara membacanya |
|---|---|
| Bobot atau minimum diubah | `Snapshot_Id` terisi dan `Flag_Dipakai = 'Y'` |
| Kategori dicabut untuk orang ini | `Flag_Dipakai = 'T'` |
| Kategori ditambahkan padahal tidak ada di aturan levelnya | `Snapshot_Id` NULL |

Tidak ada master jenis override, jadi tidak ada kolom yang bisa bertentangan dengan
baris yang menampungnya.

## Hanya kolom yang benar-benar berbeda per orang

Aturannya ditetapkan di tingkat skema, bukan per baris: apa pun yang **tidak ada** di
tabel ini selalu datang dari salinan siklus. `Organization_Relationship_Type_Id` dan
`Flag_Izinkan_Manual` sempat ada di draf lalu dibuang — jenis relasi hanya menggerakkan
*rekomendasi* sementara pemilihan penilai memang manual per orang (K10), dan izin
memilih manual adalah setelan tingkat siklus. Membiarkannya lebih buruk daripada
sekadar mubazir: di bawah semantik penggantian penuh, admin yang cuma mengubah bobot
akan mengosongkan sumber kandidat tanpa satu error pun.

## Tidak ada warisi-lewat-NULL

Draf sebelumnya memberi arti "warisi dari salinan" pada kolom kosong. Itu rancu di
tempat yang NULL-nya sudah bermakna: `Maks_Rater` NULL berarti *tanpa batas*, jadi
admin yang melepas plafon menulis NULL dan resolver justru membacanya "warisi".
Menetapkan pembagian kolom di tingkat skema menghapus keraguan itu sepenuhnya.

## Bobot_Dalam_Kategori tidak di sini

Bobot satu penilai **di dalam** satu kategori untuk satu orang adalah milik
`N_PA_Assignment` (K18). Tabel ini memegang bobot **antar** kategori.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Participant_Rater_Override` | Primary key | |
| `FK_PA_Participant_Rater_Ovr_Participant` | Foreign key | → `N_PA_Participant` |
| `FK_PA_Participant_Rater_Ovr_Category` | Foreign key | → `N_PA_Rater_Category` |
| `FK_PA_Participant_Rater_Ovr_Snapshot` | Foreign key | → salinan siklus, nullable |
| `UX_PA_Participant_Rater_Ovr_Aktif` | Unique index tersaring | Satu override aktif per (peserta, kategori) |
| `CK_PA_Participant_Rater_Ovr_Lengkap` | Check | `Flag_Dipakai = 'T'` **atau** bobot dan minimum terisi |
| `CK_..._Bobot` / `_Min` / `_Maks` / `_Selesai` | Check | 0–100; minimum ≥ 1; maksimum ≥ minimum; ambang selesai ≤ maksimum |

`CK_..._Lengkap` menyimpang dari kebiasaan sebelumnya yang menyerahkan aturan
wajib-bersyarat ke aplikasi. Bedanya nyata: literal yang dikeraskan di sini `'Y'`/`'T'`
milik konvensi flag proyek sendiri, bukan daftar nilai bisnis yang bisa tumbuh seperti
`'DIKECUALIKAN'`.

## Yang sengaja tidak dijaga database

Bahwa bobot hasil resolusi seluruh kategori berjumlah 100 melibatkan salinan siklus dan
override sekaligus — gerbang aplikasi. Begitu pula larangan menulis override setelah
siklus dikunci, yang bergantung pada `N_PA_Cycle.Status_Cycle`.

## Resolver adalah kontraknya

`PaParticipantRuleResolver` satu-satunya yang boleh menggabungkan salinan siklus dengan
tabel ini. **Tidak ada kode lain yang boleh membaca tabel salinan siklus untuk keperluan
per orang.** Aturan itulah yang memusatkan risiko baca ke satu tempat yang bisa diuji;
melanggarnya membuat bentuk jarang kehilangan keunggulan utamanya.

## Kedudukan

Lapisan ketiga.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong, dan normalnya memang tetap begitu.
