# N_PA_Cycle_Rater_Policy_Snapshot

Salinan header Rater Policy milik satu siklus. Satu baris per pasangan siklus dan
level. Membeku saat siklus dikunci.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-071-performance-appraisal-cycle-rule-snapshots |
| Forward SQL | `database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Rater_Policy_Snapshot` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Id` | `int` | T | | FK ke `N_PA_Cycle` |
| `ID_Level` | `bigint` | T | | FK ke `HRIS_Level`; lingkup salinan ini |
| `PA_Rater_Policy_Id` | `int` | T | | FK jejak asal ke versi Policy yang disalin |
| `Kode_Policy_Salinan` | `varchar(50)` | T | | Salinan kode Policy |
| `Nama_Policy_Salinan` | `nvarchar(150)` | T | | Salinan nama untuk tampilan historis |
| `Versi_Policy_Salinan` | `int` | T | | Salinan nomor versi |
| `Deskripsi_Policy_Salinan` | `nvarchar(500)` | Y | | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` memensiunkan salinan yang keliru |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | Kapan disalin |
| `Snapshot_By` | `int` | Y | | Aktor; NULL untuk job sistem |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | Perubahan selama persiapan |

## Kenapa nilainya disalin, bukan cukup foreign key

`PA_Rater_Policy_Id` mencatat **dari mana** salinan ini berasal. Kolom berakhiran
`_Salinan` mencatat **apa yang berlaku saat itu**. Persis di situ bedanya tautan
dengan salinan.

Blueprint di lapisan pertama terus berubah — Policy dapat versi baru, bobotnya
dikoreksi. Siklus yang sudah dikunci tidak boleh ikut berubah, kalau tidak orang yang
naik level di tengah jalan akan mendarat di aturan berbeda dari yang dipakai para
penilai saat mengisi.

## Membeku saat kunci

Tabel ini berhenti boleh diubah begitu `N_PA_Cycle.Status_Cycle` mencapai `LOCKED`.
Itu aturan aplikasi, bukan constraint — tidak ada constraint tingkat baris yang bisa
melihat status tabel lain.

Bandingkan dengan `N_PA_Cycle_Pillar_Weight_Snapshot` yang sengaja **tidak** membeku
(K23): bobot penilai menentukan siapa ditugaskan, bobot pilar tidak.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Rater_Policy_Snapshot` | Primary key | |
| `FK_PA_Cycle_Rater_Policy_Snap_Cycle` | Foreign key | → `N_PA_Cycle` |
| `FK_PA_Cycle_Rater_Policy_Snap_Level` | Foreign key | → `HRIS_Level` |
| `FK_PA_Cycle_Rater_Policy_Snap_Source` | Foreign key | → `N_PA_Rater_Policy`; **NOT NULL**, salinan tanpa asal tidak masuk akal |
| `UX_PA_Cycle_Rater_Policy_Snap_Aktif` | Unique index tersaring | `(PA_Cycle_Id, ID_Level)` `WHERE Flag_Aktif = 'Y'` |
| `CK_PA_Cycle_Rater_Policy_Snap_Versi` | Check | `Versi_Policy_Salinan >= 1` |

Keunikannya disaring pada baris aktif supaya salinan yang keliru bisa dipensiunkan
lalu diganti tanpa menghapus catatan apa yang sempat dipakai.

## Yang sengaja tidak dijaga database

Total bobot 100 untuk seluruh detail aktif di bawah satu salinan melibatkan banyak
baris — gerbang aplikasi, sama seperti di blueprint-nya.

## Kedudukan

Lapisan kedua. Disemai dari `N_PA_Rater_Policy` dan ditunjuk langsung oleh
`N_PA_Participant.PA_Cycle_Rater_Policy_Snapshot_Id`. Penyimpangan per orang ada di
`N_PA_Participant_Rater_Override` (DDL-074) dan normalnya tidak ada.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong. Diisi aplikasi saat siklus disiapkan.
