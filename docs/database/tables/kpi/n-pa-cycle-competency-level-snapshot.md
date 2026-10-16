# N_PA_Cycle_Competency_Level_Snapshot

Salinan Matriks Kompetensi per kompetensi untuk satu siklus, termasuk bunyi tingkat dan Expected Behaviour-nya.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Penyiapan Siklus |
| Change ID | DDL-2026-175-pa-tingkat-kompetensi |
| Diisi oleh | `PaCyclePreparationService::salinKompetensi()` → `PaKompetensiMatriksService::salinKeSiklus()` saat siklus dibuat |
| Dibaca oleh | `PaKompetensiMatriksService::perLevelJenisSiklus()` (ikatan paket soal siklus), `perilakuDituntut()` (leader, Balancing, detail hasil, laporan peserta) |

| Kolom | Tipe | Null | Arti |
|---|---|:---:|---|
| `Id_PA_Cycle_Competency_Level_Snapshot` | `int IDENTITY` | T | PK |
| `PA_Cycle_Id` | `int` | T | FK `N_PA_Cycle` |
| `ID_Level` | `int` | T | Level jabatan |
| `PA_Competency_Id` | `int` | T | FK `N_PA_Competency` |
| `PA_Competency_Proficiency_Id` | `int` | Y | Tingkat sumber (jejak) |
| `Tingkat`, `Nama_Tingkat`, `Expected_Behaviour` | | Y | Bunyi saat disalin; NULL = tanpa tingkat |
| `Snapshot_At` / `Snapshot_By` | `datetime2(0)` / `int` | | Aktor = `KPI_Users.Id_Users` |

Unik `(PA_Cycle_Id, ID_Level, PA_Competency_Id)`. Siklus tanpa baris salinan (dibuat sebelum DDL-2026-175) membaca matriks master.
