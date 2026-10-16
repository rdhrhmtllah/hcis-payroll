# DDL-2026-181 — Master tingkat peran

RBAC: tabel `N_HRIS_Tingkat_Peran` (System Admin › Super Admin › Admin › Pengguna, dengan peringkat dan kemampuan `Boleh_Angkat`, `Boleh_Ubah_Matriks`, `Boleh_Tugaskan`) dan kolom `N_HRIS_Role_Functional.Kode_Tingkat` (FK ke master). Aditif & idempoten. Isi master dan tingkat setiap peran: DML-2026-182.

Tanpa DDL ini aplikasi memakai tingkat bawaan yang sama (`TingkatPeran`), diturunkan dari `Jenis_Role` dan pola nama `*_SUPER_ADMIN`.

## Setelah dijalankan di production

```
php artisan cache:forget rbac:skema
php artisan cache:forget rbac:tingkat
```

Drop (`drop.sql`) hanya pembersihan manual.
