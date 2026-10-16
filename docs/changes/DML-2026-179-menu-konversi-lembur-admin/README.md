# DML-2026-179: Menu Konversi Lembur Admin

Mendaftarkan halaman **Konversi Lembur Admin** (`/admin/konversi-lembur`, `Jenis_page = konversiLemburAdmin`) di header sidebar *Administrator HCIS*, grup fitur ESS (`Id_Grup_Fitur = 1`).

- `Flag_Hibah_Role = 'T'`: halaman dapat membatalkan pengajuan yang sudah diterapkan dan menarik kembali saldo cuti, sehingga tidak dibuka otomatis untuk setiap pemegang role ESS.
- Akses awal disalin dari pemegang eksplisit *Lembur Admin* (`OvertimeManagementAdmin`).
- Idempoten. Rollback: `rollback.sql`. Setelah eksekusi: `InertiaShellService::flushMenuCache()`.

| Lingkungan | Status |
|---|---|
| Development | PENDING (menunggu persetujuan SQL) |
| SQL Tracker | PENDING |
| Production | PENDING |
