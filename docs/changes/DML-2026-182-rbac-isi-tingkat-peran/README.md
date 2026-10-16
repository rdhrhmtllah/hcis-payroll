# DML-2026-182 — Isi tingkat peran & Super Admin per modul

Mengisi master `N_HRIS_Tingkat_Peran` (4 baris), mengisi `Kode_Tingkat` setiap peran dari data sekarang (`*_SUPER_ADMIN` bermodul → SUPER_ADMIN, `Jenis_Role='ADMIN'` → ADMIN, sisanya PENGGUNA), membuat `ESS/LMS/VISITOR/DMS_SUPER_ADMIN` (KPI_SUPER_ADMIN sudah ada), menyalin hak `DMS_ADMIN` ke `DMS_SUPER_ADMIN`, dan menonaktifkan `HCIS_SUPER_ADMIN` (Super Admin lintas modul dari DML-2026-180) beserta penugasannya. Tanpa penugasan baru. Idempoten.

Urutan rilis: setelah DDL-2026-165, **DML-2026-166**, DDL-2026-181, DML-2026-180, dan kode branch `feat/system/rbac-tingkat-peran`. DML ini berhenti sendiri (THROW 51182) bila DML-2026-166 belum dijalankan. Sesudahnya: `php artisan cache:forget rbac:skema` dan `php artisan cache:forget rbac:tingkat`.

Sebelum rilis, daftar pemegang `KPI_SUPER_ADMIN` di production: pemegang dengan tanggung jawab AKSES langsung bisa mengangkat admin KPI dan (bila modulnya BARU) mengatur matriks hak KPI.

Rollback: `UPDATE dbo.N_HRIS_Role_Functional SET Flag_Aktif = 'T' WHERE Created_By = 'DML-2026-182';` (master & kolom tingkat boleh tetap).
