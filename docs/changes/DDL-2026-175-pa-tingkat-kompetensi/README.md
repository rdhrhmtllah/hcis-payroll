# DDL-2026-175-pa-tingkat-kompetensi

| | |
|---|---|
| Change ID | DDL-2026-175-pa-tingkat-kompetensi |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | KPI / Performance Appraisal / Master Data |
| Forward | `database/ddl/DDL-2026-175-pa-tingkat-kompetensi/forward.sql` |
| Drop (manual) | `database/ddl/DDL-2026-175-pa-tingkat-kompetensi/drop.sql` |
| DML terkait | DML-2026-176-pa-matriks-kompetensi-awal |
| Desain | `docs/superpowers/specs/2026-10-08-pa-tingkat-kompetensi-design.md` |
| Checksum forward (sha256) | `a35c4d2b50f8dcf6c34d472da201212534bab4e3082fdb9b6c261a0dc122759d` |
| Status | TRACKER_PENDING (r1) |

## Isi
- `N_PA_Competency_Proficiency` — tingkat per kompetensi (nomor, nama, Expected Behaviour). Unik (kompetensi, tingkat) untuk baris aktif.
- `N_PA_Competency_Level_Requirement` — matriks level jabatan × kompetensi + tingkat dituntut (NULL = dinilai, tingkat belum ditentukan). Unik (level, kompetensi) untuk baris aktif.
- `N_PA_Cycle_Competency_Level_Snapshot` — salinan matriks + bunyi tingkat per siklus, dibuat bersama salinan syarat saat siklus dibuat.

Audit actor (`Created_By`, `Updated_By`, `Snapshot_By`) = `KPI_Users.Id_Users` (int), sama dengan tabel PA lain.

## Pemakaian
`app/Services/KPI/PerformanceAppraisal/PaKompetensiMatriksService.php` (satu pintu); endpoint
`GET/PUT /api/v1/performance-appraisal/master/matriks-kompetensi[/{levelId}]` dan
`GET/PUT /api/v1/performance-appraisal/master/kompetensi/{id}/tingkat`; halaman
`MasterData/matriks-kompetensi.vue` dan dialog `PaKompetensiTingkatDialog.vue`.
`N_PA_Competency_Requirement` (per jenis) diselaraskan otomatis setiap matriks disimpan.

## Riwayat development
- 2026-10-08: forward dijalankan di Web_HRIS dev, putaran kedua no-op.

## Agent completed
Artefak, dokumentasi, eksekusi dev + bukti idempoten.

## User tasks
Review → catat di SQL Tracker → jalankan di production → verifikasi.
