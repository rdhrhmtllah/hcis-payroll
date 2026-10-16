# N_PA_Cycle_Competency_Requirement_Snapshot

Salinan kebutuhan jenis kompetensi yang berlaku di satu siklus. Satu baris per
gabungan siklus, level, dan jenis kompetensi. Inilah acuan untuk memeriksa apakah
paket soal HC Learn yang dipilih sudah menutup seluruh kebutuhan.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-071-performance-appraisal-cycle-rule-snapshots |
| Forward SQL | `database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Competency_Requirement_Snapshot` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Id` | `int` | T | | FK ke `N_PA_Cycle` |
| `ID_Level` | `bigint` | T | | FK ke `HRIS_Level` |
| `PA_Competency_Type_Id` | `int` | T | | FK ke `N_PA_Competency_Type` |
| `PA_Competency_Requirement_Id` | `int` | Y | | FK jejak asal; NULL bila diisi manual |
| `Kode_Type_Salinan` | `varchar(40)` | T | | Salinan MANAJERIAL / CORE_VALUE / BEHAVIOR / KUALITATIF |
| `Nama_Type_Salinan` | `nvarchar(100)` | T | | Salinan nama untuk histori |
| `Flag_Wajib` | `char(1)` | T | `'Y'` | `T` berarti jenis itu boleh ada tetapi tidak diwajibkan |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Snapshot_By` | `int` | Y | | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Untuk apa

Saat admin memilih paket soal di HC Learn, HCIS menarik daftar kode soalnya lalu
mencocokkannya dengan baris di sini. Kalau ada jenis berlabel wajib yang tidak
tertutup paket, ketidakcocokan itu ketahuan **saat pemilihan**, bukan nanti saat
hasil callback datang dengan variabel kurang.

Jaring kedua tetap perlu di sisi callback, karena paket di HC Learn bisa disunting
setelah dipilih. Yang kurang membuat peserta masuk keranjang tertahan; yang datang
tapi tidak dikenali disimpan dengan `Id_Competency` NULL supaya kelihatan.

## Kenapa per level, bukan per band

Deck mengelompokkan dimensi ke tiga band — Pramukarya, Staff, Leaders — tetapi konsep
band tidak ada di database mana pun dan harus dikarang. Aturan yang sama dinyatakan
langsung per level; satu band cuma berarti baris yang sama diulang untuk level yang
termasuk di dalamnya. Alasan selengkapnya ada di dokumen blueprint-nya,
`n-pa-competency-requirement.md`.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Competency_Requirement_Snapshot` | Primary key | |
| `FK_PA_Cycle_Comp_Req_Snap_Cycle` | Foreign key | → `N_PA_Cycle` |
| `FK_PA_Cycle_Comp_Req_Snap_Level` | Foreign key | → `HRIS_Level` |
| `FK_PA_Cycle_Comp_Req_Snap_Type` | Foreign key | → `N_PA_Competency_Type` |
| `FK_PA_Cycle_Comp_Req_Snap_Source` | Foreign key | → `N_PA_Competency_Requirement`, nullable |
| `UX_PA_Cycle_Comp_Req_Snap_Aktif` | Unique index tersaring | `(PA_Cycle_Id, ID_Level, PA_Competency_Type_Id)` `WHERE Flag_Aktif = 'Y'` |

## Kedudukan

Lapisan kedua, membeku saat kunci. Inilah nilai baku yang dibaca setiap peserta di
levelnya. Kasus seperti Officer Specialist yang tidak dinilai Kompetensi Manajerial
diselesaikan dengan satu baris di `N_PA_Participant_Requirement_Override` (DDL-074),
tanpa menyentuh baris ini dan tanpa mengubah aturan Officer yang lain.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong. Blueprint-nya pun masih kosong karena level HRIS mana yang termasuk
Pramukarya, Staff, dan Leaders belum pernah dinyatakan HC.
