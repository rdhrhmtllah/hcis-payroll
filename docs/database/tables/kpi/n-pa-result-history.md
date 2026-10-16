# N_PA_Result_History

## Purpose

Potret **hanya sisip** dari baris `N_PA_Result` sesudah setiap peristiwa yang mengubah
angkanya. `N_PA_Result` ditimpa di tempat, jadi tanpa tabel ini nilai KPI, Final, grade,
dan status versi lama hilang. Dibaca tab **Riwayat Perhitungan** di dialog hasil admin:
kapan dihitung, oleh siapa, dan dari berapa ke berapa.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Hasil
- Change: DDL-2026-168-pa-riwayat-hasil (r1)
- DDL: database/ddl/DDL-2026-168-pa-riwayat-hasil/forward.sql (drop: drop.sql, manual)
- Aktor audit `Created_By`: `KPI_Users.Id_Users` (int), sama dengan `N_PA_Result.Calculated_By`. NULL = proses sistem (job, perintah artisan).

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Result_History | int identity | No | Primary key; urutan kejadian. |
| PA_Result_Id | int | No | FK `N_PA_Result`. |
| Jenis_Peristiwa | varchar(30) | No | `HITUNG` (versi 1), `HITUNG_ULANG`, `BALANCING` (hitung ulang Balancing), `KURVA_DIKUNCI`, `KURVA_DIBUKA`. Divalidasi di `PaResultHistoryService`, tanpa CHECK. |
| Calculation_Version | int | No | Versi hitung saat dipotret. |
| Status_Hasil | varchar(20) | No | Salinan status hasil. |
| Nilai_KPI, Nilai_KPI_Sebelum_Cap, Nilai_Kompetensi, Final_Score | decimal(6,2) | Yes | Salinan angka. |
| Bobot_KPI_Terpakai, Bobot_Kompetensi_Terpakai | decimal(5,2) | Yes | Bobot pilar yang dipakai. |
| Kode_Grade_Sistem, Kode_Grade_Final | varchar(40) | Yes | Salinan grade. |
| Flag_Dikalibrasi | char(1) | No | `Y`/`T`, salinan; tanpa default karena selalu disalin. |
| PA_Hav_Quadrant_Id | int | Yes | Salinan kotak HAV (tanpa FK; nama dibaca saat tampil). |
| Alasan_Tertahan | nvarchar(500) | Yes | Salinan sebab TERTAHAN. |
| Keterangan | nvarchar(500) | Yes | Mis. alasan membuka kurva. |
| Created_At / Created_By | datetime2(0) / int | No / Yes | Waktu peristiwa (jam proses yang sama dengan `Calculated_At`) dan pelakunya. |

## Indexes and constraints

PK; `FK_PA_Result_History_Result` tanpa cascade; `IX_PA_Result_History_Result (PA_Result_Id, Id_PA_Result_History)` untuk membaca lini satu hasil berurutan; `DF_PA_Result_History_Created_At`.

## Yang sengaja tidak dipotret

Kalibrasi (`N_PA_Calibration_History`), terbit dan rilis leader (`N_PA_Result.Published_*`) sudah tercatat di tempatnya dan dibaca dari sana. Catatan kesimpulan kualitatif juga tidak dipotret: `N_PA_Result_Note` menonaktifkan versi lama (`Updated_At/By`) dan menyisipkan yang baru, sehingga lini masa merekonstruksi tiap kali simpan dari rentang hidup barisnya dan hanya menampilkan kategori yang isinya berubah. Versi yang dihitung sebelum tabel ini ada dibangun dari `N_PA_Result_Rater_Category`/`N_PA_Result_Competency` (waktu, pelaku, nilai kompetensi); KPI dan Final-nya dinyatakan tidak tersimpan.

## Application usage

- Ditulis `PaResultHistoryService::potret()`, dipanggil dari `PaResultCalculationService::hitung()` (di dalam transaksinya), `PaBalancingHitungUlangService` (sebab `BALANCING`), dan `PaKurvaService::kunci()/buka()`.
- Dibaca `PaResultHistoryService::lini()` lewat `GET /api/v1/performance-appraisal/participants/{participantId}/hasil/riwayat` (`PaCycleAdminController::riwayatHasil`), ditampilkan `Cycle/components/PaRiwayatPerhitungan.vue`.
