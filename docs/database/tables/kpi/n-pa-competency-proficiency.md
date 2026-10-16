# N_PA_Competency_Proficiency

Tingkat suatu kompetensi beserta Expected Behaviour-nya. Jumlah tingkat bebas per kompetensi.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Master Data (Kompetensi) |
| Change ID | DDL-2026-175-pa-tingkat-kompetensi |
| Forward SQL | `database/ddl/DDL-2026-175-pa-tingkat-kompetensi/forward.sql` |
| Kode | `app/Services/KPI/PerformanceAppraisal/PaKompetensiMatriksService.php` (`tingkat`, `simpanTingkat`) |
| Endpoint | `GET/PUT /api/v1/performance-appraisal/master/kompetensi/{id}/tingkat` |

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Competency_Proficiency` | `int IDENTITY` | T | | PK |
| `PA_Competency_Id` | `int` | T | | FK `N_PA_Competency` |
| `Tingkat` | `smallint` | T | | Nomor tingkat (1–20, divalidasi backend) |
| `Nama_Tingkat` | `nvarchar(150)` | T | | Mis. "Intermediate" |
| `Expected_Behaviour` | `nvarchar(4000)` | Y | | Perilaku yang diharapkan pada tingkat ini |
| `Flag_Aktif` | `char(1)` | T | `Y` | `T` = dihapus dari layar (dinonaktifkan, bukan dihapus fisik) |
| `Created_At/By`, `Updated_At/By` | `datetime2(0)` / `int` | | | Aktor = `KPI_Users.Id_Users` |

Unik `(PA_Competency_Id, Tingkat)` untuk `Flag_Aktif='Y'`. Tingkat yang masih dituntut matriks tidak bisa dinonaktifkan.
