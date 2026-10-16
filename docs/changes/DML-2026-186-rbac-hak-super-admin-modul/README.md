# DML-2026-186 — Hak akses penuh Super Admin modul (RBAC)

Mengisi seluruh hak akses halaman operasional dan administratif modul kepada Super Admin tiap modul (`ESS_SUPER_ADMIN`, `KPI_SUPER_ADMIN`, `LMS_SUPER_ADMIN`, `VISITOR_SUPER_ADMIN`, `DMS_SUPER_ADMIN`) di tabel `N_HRIS_Role_Hak` dengan seluruh aksi (CREATE, VIEW, EDIT, DELETE, APPROVE, PRINT, SUMMARY, ASSIGN).

## Latar Belakang

Pada DML-2026-182, peran `ESS_SUPER_ADMIN`, `LMS_SUPER_ADMIN`, dan `VISITOR_SUPER_ADMIN` baru dibuatkan role-nya tetapi hak halamannya di `N_HRIS_Role_Hak` belum diisi (masih 0 halaman). Super Admin modul berhak penuh atas seluruh halaman di modulnya.

## Objek Terdampak

- `dbo.N_HRIS_Role_Hak` (INSERT ~821 baris untuk 5 peran Super Admin modul)

## Eksekusi & Idempotensi

Skrip menggunakan `WHERE NOT EXISTS` sehingga idempoten dan aman dijalankan ulang. Halaman pengaturan khusus System Admin (`adminAllPagesAccess`, `superAdminManajemenPeranPage`, `systemAuthManagerPage`) dikecualikan.

## Production

Jalankan via SQL Tracker setelah DML-2026-182.
