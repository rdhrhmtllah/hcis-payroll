# DML-2026-193: Menu Monitoring Kehadiran

Mendaftarkan halaman **Monitoring Kehadiran** (`/admin/monitoring-kehadiran`,
`Jenis_page = essMonitoringKehadiranPage`) di header sidebar *Administrator HCIS*, grup fitur
ESS (`Id_Grup_Fitur = 1`). Halaman murni baca: tab Hari Ini dan Rekap Periode, data dibatasi
cakupan admin (`FunctionalRole`).

- `Flag_Hibah_Role = 'T'`: halaman memperlihatkan kehadiran seluruh karyawan dalam cakupan,
  jadi tidak dibuka otomatis untuk setiap pemegang role ESS.
- Akses awal disalin dari pemegang eksplisit *Izin Admin* (`IzinPageAdmin`): dev 4 baris aktif
  (3 unik), production 8 baris aktif (dibaca 14 Okt 2026).
- Idempoten. Rollback manual: `rollback.sql`. Setelah eksekusi: `InertiaShellService::flushMenuCache()`
  di semua checkout yang melayani vhost.
- Rute: `routes/ESS/Attendance/Monitoring/MonitoringKehadiran.php`
  (`check.jabatan:essMonitoringKehadiranPage`).

| Lingkungan | Status |
|---|---|
| Development | EXECUTED 2026-10-14 (Id_Menu 167, Id_Grup_Map 533, 3 akses; putaran kedua no-op) |
| SQL Tracker | PENDING |
| Production | PENDING |
