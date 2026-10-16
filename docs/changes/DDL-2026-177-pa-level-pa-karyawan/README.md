# DDL-2026-177-pa-level-pa-karyawan

| | |
|---|---|
| Change ID | DDL-2026-177-pa-level-pa-karyawan |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | KPI / Performance Appraisal / Peserta |
| Forward | `database/ddl/DDL-2026-177-pa-level-pa-karyawan/forward.sql` |
| Drop (manual) | `database/ddl/DDL-2026-177-pa-level-pa-karyawan/drop.sql` |
| Desain | `docs/superpowers/specs/2026-10-09-pa-level-pa-per-karyawan-design.md` |
| Checksum forward (sha256) | `76db14fbb754d3b78f06308f0239fddd880f35cc6aa6b7affe3369ca23243053` |
| Status | TRACKER_PENDING (r1) |

## Isi
- `N_PA_Karyawan_Level_PA` — koreksi Level PA per karyawan (khusus PA). Kolom: level PA (`HRIS_Level.ID_Level`),
  level HRIS & level-jabatan saat dibuat (deteksi basi), alasan (wajib), berlaku dari/sampai, aktif, audit,
  pencabutan. Unik (perusahaan, karyawan) untuk baris aktif.
- `N_PA_Participant` + `ID_Level_HRIS_Saat_Cycle`, `Nama_Level_HRIS_Saat_Cycle`, `PA_Karyawan_Level_PA_Id`
  (jejak level HRIS asli bila peserta memakai koreksi). Semua NULL; peserta lama tetap berarti level HRIS.

Aktor (`Created_By`, `Updated_By`, `Cancelled_By`) = `KPI_Users.Id_Users` (int).

## Pemakaian
`app/Services/KPI/PerformanceAppraisal/PaLevelPaResolver.php` (Tahap 2). Disambungkan ke pembentukan peserta di Tahap 3.

## Riwayat development
- 2026-10-09: forward dijalankan di Web_HRIS dev (7 batch), putaran kedua no-op. Resolver diuji (transaksi + rollback).

## User tasks
Review → SQL Tracker → production → verifikasi.
