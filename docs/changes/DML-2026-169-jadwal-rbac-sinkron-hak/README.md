# DML-2026-169 — Jadwal `rbac:sinkron-hak`

RBAC Fase 3a. Menambah baris `N_HRIS_Scheduler_Configs` untuk `rbac:sinkron-hak` setiap jam (menit ke-17, Asia/Jakarta). Perintahnya menyamakan `N_HRIS_Role_Hak` dengan hibah role untuk modul LAMA/BAYANGAN (tak pernah menghapus; modul BARU tidak disentuh), dan mencatat ringkasannya di `N_HRIS_Akses_Audit` bila ada perubahan.

Urutan rilis: setelah kode Fase 3a dan DDL-2026-165. Idempoten.

Rollback: `UPDATE dbo.N_HRIS_Scheduler_Configs SET Flag_Aktif = 'T' WHERE Command = 'rbac:sinkron-hak';`
