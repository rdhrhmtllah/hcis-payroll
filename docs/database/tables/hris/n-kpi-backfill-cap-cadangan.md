# N_KPI_Backfill_Cap_Cadangan

Cadangan nilai lama untuk backfill cap KPI per parameter, dibuat oleh
`DDL-2026-167-kpi-cadangan-backfill-cap`. Ditulis dan dibaca hanya oleh
`App\Services\KPI\KpiCapParameterBackfillService`.

## Kolom

| Kolom | Tipe | Null | Default | Isi |
|---|---|---|---|---|
| `Id_Cadangan` | `bigint IDENTITY` | tidak | — | PK |
| `Batch_Id` | `varchar(60)` | tidak | — | batch backfill (`kpi-bf-…`) atau rollback (`kpi-bfr-…`) |
| `Jenis` | `char(6)` | tidak | — | `HEADER`, `DETAIL`, `POTONG` |
| `Id_Update_Performance` | `int` | tidak | — | header `KPI_Update_Performance`; untuk `POTONG` = indeks potongan |
| `Id_Update_Performance_Detail` | `int` | ya | — | baris `KPI_Update_Performance_Detail` (hanya `DETAIL`) |
| `Nilai_Lama_Json` | `nvarchar(max)` | ya | — | nilai kolom sebelum diubah |
| `Dipulihkan_At` | `datetime2(0)` | ya | — | waktu rollback memulihkan baris ini |
| `Created_At` | `datetime2(0)` | tidak | `SYSDATETIME()` | waktu dicadangkan |

## Isi `Nilai_Lama_Json`

| Jenis | Kunci |
|---|---|
| `HEADER` | `Total_Score`, `Total_Score_Achievement`, `Total_Score_Raw`, `Total_Score_Achievement_Raw`, `Flag_Capped`, `Metode_Cap`, `Cap_Min_Applied`, `Cap_Max_Applied`, `Id_Performance` |
| `DETAIL` | `Score`, `Achievement`, `Score_Raw`, `Achievement_Raw`, `Flag_Capped` |
| `POTONG` | — (NULL; baris ini hanya penanda) |

## Batasan

- `PK_KPI_Backfill_Cap_Cadangan` pada `Id_Cadangan`.
- `CK_KPI_Backfill_Cap_Jenis`: `Jenis IN ('HEADER','DETAIL','POTONG')`.
- `IX_KPI_Backfill_Cap_Batch (Batch_Id, Jenis, Id_Update_Performance)`.
- `UX_KPI_Backfill_Cap_Potong (Batch_Id, Id_Update_Performance) WHERE Jenis='POTONG'` —
  satu potongan diklaim sekali per batch (kiriman ulang Cloud Tasks tak memproses dua kali).

Tanpa FK ke tabel KPI: cadangan harus tetap ada walau header kelak dibatalkan.
