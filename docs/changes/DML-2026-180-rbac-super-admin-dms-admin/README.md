# DML-2026-180 — Super Admin HCIS & Admin DMS

RBAC (lanjutan Fase 5). Menambah dua peran berjenis `ADMIN`:

- `HCIS_SUPER_ADMIN` — lintas modul, tanpa modul induk (`Id_Grup_Fitur` NULL) agar tidak mewarisi hibah halaman SYSTEM. Kode memberinya wewenang mengelola semua modul selain SYSTEM, termasuk mengangkat/mencabut admin modul (spec §6). Hanya System Admin yang menugaskannya. Tidak bisa mengubah katalog, mode modul, atau hak peran admin.
- `DMS_ADMIN` — admin modul DMS; hak awal = hibah modul DMS saat ini (VIEW + aksi default katalog), aturan sama dengan DML-2026-166.

Tidak membuat penugasan. Idempoten. Target `Web_HRIS`.

Urutan rilis: setelah DDL-2026-165 dan kode branch `feat/system/rbac-super-admin`. Tidak perlu `cache:forget rbac:skema` (tak ada perubahan skema).

Rollback: `UPDATE dbo.N_HRIS_Role_Functional SET Flag_Aktif = 'T' WHERE Role_Code IN ('HCIS_SUPER_ADMIN', 'DMS_ADMIN');`
