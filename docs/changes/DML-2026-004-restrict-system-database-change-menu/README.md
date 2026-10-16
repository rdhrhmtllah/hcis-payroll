# DML-2026-004-restrict-system-database-change-menu

Membatasi menu Database Change Dashboard agar tidak lagi global. Akses halaman dan endpoint juga memakai middleware `system.admin`.

- Target development: `Web_HRIS`
- Table: `N_HRIS_Menus`
- Predicate: `Jenis_page = systemDatabaseChangeDashboardPage AND Is_Global_Menu = Y`
- Expected affected rows: 1
- SQL Tracker: `PENDING`
- Production: `PENDING`
