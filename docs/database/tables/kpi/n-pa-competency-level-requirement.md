# N_PA_Competency_Level_Requirement

Matriks Kompetensi per KOMPETENSI: kompetensi yang dinilai di tiap level jabatan dan tingkat yang dituntut.
Sumber kebenaran "kompetensi wajib per level" sejak DDL-2026-175 (menggantikan penjabaran per jenis).

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Master Data (Matriks Kompetensi) |
| Change ID | DDL-2026-175-pa-tingkat-kompetensi; isi awal DML-2026-176-pa-matriks-kompetensi-awal |
| Kode | `PaKompetensiMatriksService` (`matriks`, `simpanLevel`, `idWajibLevel`, `perLevelJenis`, `perilakuDituntut`) |
| Endpoint | `GET /api/v1/performance-appraisal/master/matriks-kompetensi`, `PUT .../matriks-kompetensi/{levelId}` |
| Halaman | `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/matriks-kompetensi.vue` (`/performance-appraisal/master/syarat-kompetensi`) |

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Competency_Level_Requirement` | `int IDENTITY` | T | | PK |
| `ID_Level` | `int` | T | | `HRIS_Level.ID_Level` |
| `PA_Competency_Id` | `int` | T | | FK `N_PA_Competency` |
| `PA_Competency_Proficiency_Id` | `int` | Y | | FK `N_PA_Competency_Proficiency`; NULL = dinilai, tingkat belum ditentukan |
| `Flag_Aktif` | `char(1)` | T | `Y` | `T` = tidak dinilai lagi di level itu |
| `Created_At/By`, `Updated_At/By` | | | | Aktor = `KPI_Users.Id_Users` |

Unik `(ID_Level, PA_Competency_Id)` untuk baris aktif. Setiap penyimpanan menyelaraskan `N_PA_Competency_Requirement`
(jenis wajib bila ≥1 kompetensinya dituntut). Penjabaran kompetensi wajib: baris aktif ∩ kompetensi aktif ∩ jenis aktif.
