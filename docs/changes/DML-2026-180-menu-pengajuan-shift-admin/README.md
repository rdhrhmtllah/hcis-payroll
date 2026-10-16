# DML-2026-180: Menu Pengajuan Shift Admin

Mendaftarkan halaman **Pengajuan Shift Admin** (`/admin/pengajuan-shift`, `Jenis_page = pengajuanShiftAdmin`) di header sidebar *Administrator HCIS*, grup fitur ESS (`Id_Grup_Fitur = 1`). Halaman ini memantau Ganti Shift (karyawan, leader, admin), Tukar Hari, dan Status Hari.

- `Flag_Hibah_Role = 'T'`: halaman dapat membatalkan pengajuan karyawan mana pun, sehingga tidak dibuka otomatis untuk setiap pemegang role ESS.
- Akses awal disalin dari pemegang eksplisit *Shift Admin* (`ShiftManagementAdmin`).
- Idempoten. Rollback: `rollback.sql`. Setelah eksekusi: `InertiaShellService::flushMenuCache()`.
- Amandemen 13 Okt 2026 (sebelum SQL Tracker): versi pertama bernama `selfShiftAdmin` (`/admin/self-shift`) sempat dijalankan di dev; versi itu di-rollback sebelum versi ini dijalankan.

| Lingkungan | Status |
|---|---|
| Development | EXECUTED 2026-10-13 (Id_Menu 165) |
| SQL Tracker | PENDING |
| Production | PENDING |
