# N_PA_Competency_Requirement

Matriks kebutuhan kompetensi: jenis kompetensi apa yang wajib dinilai pada tiap level
jabatan. Ini acuan untuk memeriksa apakah paket soal HC Learn yang dipilih admin sudah
menutup seluruh kebutuhan.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Competency |
| Change ID | DDL-2026-067-performance-appraisal-competency-and-score-range |
| Forward SQL | `database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Competency_Requirement` | `int IDENTITY(1,1)` | T | | Primary key |
| `ID_Level` | `bigint` | T | | FK ke `HRIS_Level.ID_Level` |
| `PA_Competency_Type_Id` | `int` | T | | FK ke `N_PA_Competency_Type` |
| `Flag_Wajib` | `char(1)` | T | `'Y'` | `T` berarti jenis itu boleh ada tetapi tidak diwajibkan |
| `Catatan` | `nvarchar(500)` | Y | | Alasan kebutuhan ini ditetapkan |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa per level, bukan per band

Deck mengelompokkan dimensi ke tiga band — Pramukarya, Staff, dan Leaders. Konsep
band itu **tidak ada** di database mana pun dan harus dikarang, yang berarti minimal
tiga tabel tambahan: master band, pemetaan level ke band, dan pemetaan band ke jenis.

Aturan yang sama bisa dinyatakan langsung per level: satu band cuma berarti baris yang
sama diulang untuk level-level yang termasuk di dalamnya. Butirannya lebih halus, jadi
tidak ada yang hilang, dan kalau ternyata kebutuhan berbeda per level di dalam satu
band, bentuk ini sudah siap.

Ini **tidak** menyatukan dua matriks yang sengaja dipisah desain: yang ini kebutuhan
kompetensi, sedangkan matriks di `N_PA_Rater_Policy_Detail` adalah bobot penilai. Dua
hal berbeda yang kebetulan sama-sama berbicara tentang level.

## Kedudukan

Lapisan pertama, dan **aturan** menurut K26 — punya salinan siklus
`N_PA_Cycle_Competency_Requirement_Snapshot` (DDL-071). Penyimpangan per orang tidak
disalin melainkan ditulis hanya bila ada, di `N_PA_Participant_Requirement_Override`
(DDL-074).

Mengubah kebutuhan mengubah apa yang dinilai seseorang, jadi hasil siklus lama harus
tetap dapat dibaca dengan aturan yang berlaku saat itu.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Competency_Requirement` | Primary key | |
| `FK_PA_Competency_Requirement_Level` | Foreign key | → `HRIS_Level` |
| `FK_PA_Competency_Requirement_Type` | Foreign key | → `N_PA_Competency_Type` |
| `UX_PA_Competency_Requirement_Aktif` | Unique index tersaring | `(ID_Level, PA_Competency_Type_Id)` `WHERE Flag_Aktif = 'Y'` |
| `DF_PA_Competency_Requirement_Wajib` / `_Aktif` / `_Created` | Default | `'Y'`, `'Y'`, `SYSDATETIME()` |

## Pemakaian aplikasi

Belum ada per 2026-08-30.

## Keadaan data

Kosong. Matriks band di deck sudah diketahui, tetapi **level HRIS mana yang termasuk
Pramukarya, Staff, dan Leaders belum pernah dinyatakan HC**. Menebaknya berarti
menaruh keputusan bisnis ke dalam seed.
