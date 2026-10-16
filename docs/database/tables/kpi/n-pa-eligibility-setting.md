# N_PA_Eligibility_Setting

Master aturan kelayakan peserta Performance Appraisal. Satu baris saja; isinya menjadi nilai
awal setiap siklus baru (`N_PA_Cycle.Min_Masa_Kerja_Bulan`). Mengubah master tidak menyentuh
siklus yang sudah ada.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Master Data (Jenis Penilai) |
| Change ID | DDL-2026-191-pa-kelayakan-masa-kerja |
| Forward SQL | `database/ddl/DDL-2026-191-pa-kelayakan-masa-kerja/forward.sql` |
| Drop (manual) | `database/ddl/DDL-2026-191-pa-kelayakan-masa-kerja/drop.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Eligibility_Setting` | `int` | T | | Primary key; selalu 1 (`CK_PA_Eligibility_Setting_Tunggal`) |
| `Min_Masa_Kerja_Bulan` | `int` | T | | Minimal masa kerja (bulan) pada akhir periode yang dinilai. Rentang 0–24 divalidasi aplikasi |
| `Created_At` | `datetime2(0)` | T | `sysdatetime()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` (Auth::id) |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | Idem |

Tanpa baris, aplikasi memakai bawaan 3 bulan (`PaKelayakanMasaKerjaService::BAWAAN_BULAN`);
baris dibuat saat admin pertama kali menyimpan.

## Aturan

Masa kerja diukur dari `Karyawan.Tgl_Masuk` sampai `N_PA_Cycle.Periode_Dinilai_Sampai`.
Karyawan tidak layak bila belum genap N bulan pada akhir hari itu, dan selalu tidak layak bila
masuk sesudah periode berakhir (juga pada N = 0). Hasilnya peserta DIKECUALIKAN dengan alasan
tercatat; admin tetap bisa mengikutkannya kembali secara manual.

## Pemakaian

- Service: `App\Services\KPI\PerformanceAppraisal\PaKelayakanMasaKerjaService` (satu-satunya
  penulis dan pemegang rumus `alasanTidakLayak`).
- Dibaca: `PaCyclePreparationService::nilaiKelayakan` (penyiapan, pratinjau, revisi peserta),
  `PaCycleSetupService::buat` (nilai awal siklus).
- Endpoint master: `GET|PUT /api/v1/performance-appraisal/master/jenis-penilai/kelayakan-masa-kerja`
  (`PaMasterDataController`, hak master `kategori-penilai`).
- Endpoint siklus: `GET|PUT /api/v1/performance-appraisal/cycles/{cycleId}/kelayakan-masa-kerja`
  (`PaCycleAdminController`; PUT hanya sebelum peserta dibentuk).
- Layar: `MasterData/jenis-penilai.vue` dan Langkah 4 `Cycle/components/PaStepPeserta.vue`,
  lewat `components/PaAturanMasaKerja.vue`.
