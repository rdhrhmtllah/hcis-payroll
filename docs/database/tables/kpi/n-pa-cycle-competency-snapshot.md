# N_PA_Cycle_Competency_Snapshot

Atribut kompetensi yang berlaku bagi satu siklus PA, dibekukan saat siklus dikunci.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Penyiapan & Hasil |
| Change ID | DDL-2026-170-pa-salinan-kompetensi-siklus |
| Forward SQL | `database/ddl/DDL-2026-170-pa-salinan-kompetensi-siklus/forward.sql` |
| Kode | `app/Services/KPI/PerformanceAppraisal/PaKompetensiSiklus.php` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Competency_Snapshot` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Id` | `int` | T | | FK ke `N_PA_Cycle` |
| `PA_Competency_Id` | `int` | T | | FK ke `N_PA_Competency` |
| `Flag_Berskor` | `char(1)` | T | | Salinan master saat dikunci |
| `Flag_Radar` | `char(1)` | T | | Salinan master saat dikunci |
| `Nilai_Ambang` | `decimal(5,2)` | Y | | KKM; NULL berarti tanpa KKM (bukan "pakai master") |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Snapshot_By` | `int` | Y | | `KPI_Users.Id_Users` yang mengunci |

Unik: `(PA_Cycle_Id, PA_Competency_Id)`.

## Aturan baca

Siklus tanpa baris salinan (dikunci sebelum DDL-2026-170) membaca master `N_PA_Competency`.
Pakai `PaKompetensiSiklus::gabung()` + `kolom()`/`sql()`; jangan COALESCE nilainya.
