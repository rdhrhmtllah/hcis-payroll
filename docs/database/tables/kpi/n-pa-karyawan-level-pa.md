# N_PA_Karyawan_Level_PA

Koreksi Level PA per karyawan — level yang dipakai PA bila level HRIS orang itu tidak sesuai pekerjaannya.
HRIS tidak diubah.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Peserta (Level PA) |
| Change ID | DDL-2026-177-pa-level-pa-karyawan |
| Kode | `app/Services/KPI/PerformanceAppraisal/PaLevelPaResolver.php` |

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Karyawan_Level_PA` | `int IDENTITY` | T | | PK |
| `Kode_Perusahaan`, `Kode_Karyawan` | `varchar(3)`, `varchar(255)` | T | | Karyawan |
| `ID_Level_PA` | `bigint` | T | | Level yang dipakai PA (`HRIS_Level.ID_Level`) |
| `ID_Level_HRIS_Saat_Dibuat` | `bigint` | Y | | Level HRIS saat koreksi dibuat; bila berubah → koreksi PERLU DITINJAU dan tidak dipakai |
| `ID_Level_Jabatan_Saat_Dibuat` | `bigint` | Y | | Level-jabatan HRIS saat dibuat (jejak) |
| `Alasan` | `nvarchar(500)` | T | | Wajib |
| `Berlaku_Dari` / `Berlaku_Sampai` | `date` | T / Y | | Masa berlaku; sampai NULL = terbuka |
| `Flag_Aktif` | `char(1)` | T | `Y` | `T` = dicabut |
| `Created_*`, `Updated_*`, `Cancelled_*`, `Alasan_Cabut` | | | | Aktor = `KPI_Users.Id_Users` |

Unik `(Kode_Perusahaan, Kode_Karyawan)` untuk `Flag_Aktif='Y'`. Dibaca saat peserta siklus dibentuk; hasilnya disalin ke
`N_PA_Participant` (`ID_Level_Saat_Cycle` = level PA, `ID_Level_HRIS_Saat_Cycle` = level HRIS asli, `PA_Karyawan_Level_PA_Id`).
