# DML-2026-191 — Urutan Terdepan Menu Akses & Peran di Seluruh Modul (RBAC)

Mengatur posisi menu "Akses & Peran" di setiap modul (`ESS`, `KPI`, `LMS`, `VISITOR`, `DMS`, `SYSTEM`) agar berada di urutan pertama (paling atas) pada sub-menu Administrator di sidebar aplikasi.

## Latar Belakang

Sebelumnya pada DML-2026-183, menu `Akses & Peran` didaftarkan dengan `Sort_Menu = '91'` dan `N_HRIS_Grup_Fitur_Map.Sort_Order = NULL`. Akibatnya menu ini berada di urutan paling bawah setelah 15-20 menu lainnya, sehingga terpotong di layar sidebar dan sulit ditemukan oleh admin modul.

## Objek Terdampak

- `dbo.N_HRIS_Menus` (UPDATE `Sort_Menu = '1'` untuk 6 menu `*AksesPeranPage`)
- `dbo.N_HRIS_Grup_Fitur_Map` (UPDATE `Sort_Order = 1` untuk 6 menu `*AksesPeranPage`)

## Production

Jalankan via SQL Tracker setelah DML-2026-190.
Bersihkan cache sidebar setelah eksekusi:
```bash
php artisan cache:forget shell.menu-rows.v1:001
```
