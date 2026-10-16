# DML-2026-188 — Standarisasi Peran Operasional & Pool Approval (RBAC)

1. Mengubah `Jenis_Role` peran verifikator yang aktif di `HRIS_Approval_Request`:
   - `ADMIN_HC_CUTI_VERIFIKATOR` (Verifikasi Cuti HC) -> `POOL_APPROVAL`
   - `ROLE_KPI_VERIF` (Verifikasi Penilaian KPI) -> `POOL_APPROVAL`
2. Menggabungkan peran duplikat operasional ESS:
   - `HCIS_ADMIN_OPS` digabung ke `HC_OPS_ADMIN` (pemegang User #601 dimigrasi).
   - `HCIS_ADMIN_OPS` dinonaktifkan (`Flag_Aktif = 'T'`).

## Hasil Akhir

- Peran verifikator cuti & verifikator KPI dikenali otomatis di kategori **Alur Approval** (bukan tercampur di Akses Menu).
- Peran operasional HC ESS menjadi satu (`HC_OPS_ADMIN`).

## Production

Jalankan via SQL Tracker setelah DML-2026-187.
