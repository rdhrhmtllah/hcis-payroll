# N_PA_Participant_Requirement_Override

Penyimpangan kebutuhan kompetensi milik **satu** peserta. Inilah tempat kasus seperti
Officer Specialist yang tidak dinilai Kompetensi Manajerial diselesaikan, tanpa
menyentuh aturan Officer yang lain. Normalnya kosong.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Participant |
| Change ID | DDL-2026-074-performance-appraisal-participant-overrides |
| Forward SQL | `database/ddl/DDL-2026-074-performance-appraisal-participant-overrides/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Participant_Requirement_Override` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Participant_Id` | `int` | T | | FK ke `N_PA_Participant` |
| `PA_Competency_Type_Id` | `int` | T | | Jenis kompetensi yang diubah |
| `PA_Cycle_Competency_Requirement_Snapshot_Id` | `int` | Y | | Baris salinan siklus yang digantikan; **NULL berarti jenis ini ditambahkan** |
| `Flag_Dipakai` | `char(1)` | T | `'Y'` | `T` mencabut jenis kompetensi ini untuk orang tersebut |
| `Flag_Wajib` | `char(1)` | Y | | Wajib atau opsional; boleh kosong hanya saat jenisnya dicabut |
| `Alasan_Perubahan` | `nvarchar(500)` | T | | Wajib |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | Mencabutnya mengembalikan orang itu ke aturan siklus |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Tiga bentuk penyimpangan tanpa kolom jenis

| Yang terjadi | Cara membacanya |
|---|---|
| Jenis kompetensi berubah wajib/opsional | `Snapshot_Id` terisi dan `Flag_Dipakai = 'Y'` |
| Jenis dicabut untuk orang ini | `Flag_Dipakai = 'T'` |
| Jenis ditambahkan padahal tidak dituntut levelnya | `Snapshot_Id` NULL |

## Mencabut dan menandai opsional bukan hal yang sama

Keduanya sempat tampak setara, tetapi bedanya nyata di pelaporan. `Flag_Wajib = 'T'`
berarti jenis itu **tetap dinilai** dan tetap masuk perhitungan bila datanya ada, hanya
tidak menahan kelengkapan. `Flag_Dipakai = 'T'` berarti jenis itu **tidak ada** bagi
orang tersebut — tidak dinilai, tidak dihitung, tidak muncul di laporan cakupan.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Participant_Requirement_Override` | Primary key | |
| `FK_PA_Participant_Req_Ovr_Participant` | Foreign key | → `N_PA_Participant` |
| `FK_PA_Participant_Req_Ovr_Type` | Foreign key | → `N_PA_Competency_Type` |
| `FK_PA_Participant_Req_Ovr_Snapshot` | Foreign key | → salinan siklus, nullable |
| `UX_PA_Participant_Req_Ovr_Aktif` | Unique index tersaring | Satu override aktif per (peserta, jenis) |
| `CK_PA_Participant_Req_Ovr_Lengkap` | Check | `Flag_Dipakai = 'T'` **atau** `Flag_Wajib` terisi |

## Yang sengaja tidak dijaga database

Apakah paket soal HC Learn benar-benar menutup jenis yang masih wajib **setelah**
override diterapkan; sifatnya dihitung dan melibatkan
`N_PA_Cycle_Question_Package_Item`. Begitu pula larangan mengubah override setelah
siklus dikunci.

## Kedudukan

Lapisan ketiga, menyimpang dari `N_PA_Cycle_Competency_Requirement_Snapshot`.
Penggabungannya wajib lewat `PaParticipantRuleResolver` (K32).

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.
