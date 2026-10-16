# DML-2026-189 — Binding peran ROLE_KPI_VERIF ke modul KPI (RBAC)

Mengikat peran `ROLE_KPI_VERIF` (Verifikator KPI) ke modul KPI (`Id_Grup_Fitur = 2`).

## Latar Belakang

Sebelumnya `N_HRIS_Role_Functional.Id_Grup_Fitur` untuk `ROLE_KPI_VERIF` bernilai `NULL`, sehingga tampil sebagai peran "Lintas Modul" dan tidak muncul di halaman manajemen peran modul KPI (`/admin/kpi/akses-peran`). Padahal peran ini adalah pool verifikator penilaian kinerja khusus modul KPI.

## Objek Terdampak

- `dbo.N_HRIS_Role_Functional` (UPDATE `Id_Grup_Fitur = 2` untuk `ROLE_KPI_VERIF`)

## Production

Jalankan via SQL Tracker setelah DML-2026-188.
