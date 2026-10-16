# DDL-2026-184 — Template cakupan (RBAC)

Template cakupan bernama yang dibuat pengelola akses di layar Akses & Peran, misalnya "Payroll Jakarta" = Divisi Keuangan **dan** Lokasi Jakarta.

- `N_HRIS_Cakupan_Template`: nama, keterangan, modul (`Id_Grup_Fitur`, NULL = lintas modul), status aktif, pembuat/pengubah (`Id_Users` KPI_Users, sama dengan `Auth::id()`).
- `N_HRIS_Cakupan_Template_Syarat`: syarat template (`Jenis_Cakupan`, `Nilai`). Syarat berbeda jenis digabung DAN, sejenis digabung ATAU. Nilai NULL = milik pemegang.

Penugasan memakai template lewat tabel yang sudah ada `N_HRIS_Role_User_Cakupan` dengan `Jenis_Cakupan = 'TEMPLATE'` dan `Nilai = Id_Cakupan_Template`, jadi tidak ada perubahan pada tabel penugasan. Template nonaktif atau terhapus = tidak mencakup siapa pun (gagal tertutup).

Aditif & idempoten. Tanpa DDL ini aplikasi menyembunyikan fitur template (`SkemaRbac::punya('template_cakupan')`).

## Setelah dijalankan di production

```
php artisan cache:forget rbac:skema
```

Drop (`drop.sql`) hanya pembersihan manual.
