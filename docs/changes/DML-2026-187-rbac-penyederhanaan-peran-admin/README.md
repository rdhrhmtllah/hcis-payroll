# DML-2026-187 — Penyederhanaan Peran Admin Kembar (RBAC)

Menggabungkan peran admin yang sebelumnya dipecah per tanggung jawab (`_MENU_ADMINISTRATOR`, `_APPROVAL_ADMINISTRATOR`, `_MEMBERSHIP_ADMINISTRATOR`, dan `_PHL`) menjadi 1 peran **Admin Modul** tunggal per modul:

- **DMS**: Nonaktifkan `DMS_MENU_ADMINISTRATOR` & `DMS_APPROVAL_ADMINISTRATOR` (0 pemegang).
- **VISITOR**: Migrasi Arya (#624) ke `VISITOR_ADMIN` (cakupan SEMUA); nonaktifkan `VISITOR_MENU_ADMINISTRATOR` & `VISITOR_APPROVAL_ADMINISTRATOR`.
- **LMS**: Buat `LMS_ADMIN` (hak disalin dari `LMS_MENU_ADMINISTRATOR`); nonaktifkan peran lama (0 pemegang).
- **KPI**: Buat `KPI_ADMIN` (hak disalin dari `KPI_MENU_ADMINISTRATOR`); migrasi Iqbal #648 (cakupan SEMUA) & Haris #644 (cakupan PHL); nonaktifkan 4 peran admin lama.
- **ESS**: Buat `ESS_ADMIN` (hak disalin dari `ESS_MENU_ADMINISTRATOR`); migrasi Aji #622 (cakupan SEMUA) & Dhea #600 (cakupan PHL); nonaktifkan 5 peran admin lama.

## Hasil Akhir

Setiap modul kini memiliki struktur RBAC yang konsisten dan bersih:
- `[MODUL]_SUPER_ADMIN` (Tingkat: Super Admin)
- `[MODUL]_ADMIN` (Tingkat: Admin)
- Peran operasional / pool approval (Tingkat: Pengguna)

## Eksekusi & Idempotensi

Skrip idempoten menggunakan `NOT EXISTS` dan aman dijalankan ulang.

## Production

Jalankan via SQL Tracker setelah DML-2026-185 dan DML-2026-186.
Deploy `config/authorization.php` bersamaan.
