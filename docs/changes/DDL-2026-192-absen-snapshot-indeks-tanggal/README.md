# DDL-2026-192-absen-snapshot-indeks-tanggal

| | |
|---|---|
| Change ID | DDL-2026-192-absen-snapshot-indeks-tanggal |
| Target | Web_HRIS (development); production hanya lewat SQL Tracker |
| Modul | ESS / Attendance / Monitoring |
| Artefak | `database/ddl/DDL-2026-192-absen-snapshot-indeks-tanggal/forward.sql` |
| Drop (manual) | `database/ddl/DDL-2026-192-absen-snapshot-indeks-tanggal/drop.sql` |
| Status | TRACKER_PENDING (r1) |

## Isi
Indeks `IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal` pada `dbo.N_HRIS_Absen_Snapshot`
`(Kode_Perusahaan, Tanggal)` dengan kolom INCLUDE yang dibaca halaman Monitoring Kehadiran
(`MonitoringKehadiranQuery`, `MonitoringHariIniService`, `RekapPeriodeService`).

## Alasan
Semua indeks snapshot yang ada diawali karyawan atau user (`IX_..._Karyawan_Tanggal`,
`IX_..._Read`, `UX_..._User_Tanggal`, `IX_..._Dirty`). Monitoring membaca **semua karyawan
pada satu tanggal / satu rentang**, sehingga tanpa indeks ini setiap muat memindai seluruh
tabel (production 14 Okt 2026: 1,03 juta baris, indeks berkelompok 332 MB; rekap satu
periode terukur 1.327 ms). Kolom INCLUDE = kolom yang dipakai kategori, agregat rekap, dan
saringan shift, supaya pembacaan tidak perlu lookup ke indeks berkelompok.

## Catatan production
- SQL Server **Express** (tanpa `ONLINE = ON`): pembuatan indeks menahan penulisan ke tabel
  beberapa detik. Jalankan di luar jam pemrosesan absensi.
- Perkiraan ukuran ±100 MB (rata-rata ±95 byte/baris). Basis data terpakai 4,6 GB dari batas
  Express 10 GB.
- Idempoten (dijaga `sys.indexes`).

## Riwayat development
- 2026-10-14: dijalankan di Web_HRIS dev atas persetujuan user (indeks 171 ms, 20.402 baris); putaran kedua no-op.

## User tasks
Review → SQL Tracker → production (di luar jam pemrosesan absensi) → verifikasi
`SELECT name FROM sys.indexes WHERE name = 'IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal'`.
