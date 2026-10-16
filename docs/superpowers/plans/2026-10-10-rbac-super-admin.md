# RBAC — Super Admin HCIS & Admin DMS

**Goal:** Melengkapi distribusi spec §6: Super Admin lintas modul yang mengelola semua modul (selain SYSTEM) dan mengangkat admin modul; peran admin untuk DMS.

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` §6. Disetujui user 10 Okt 2026.

## Keputusan

- `HCIS_SUPER_ADMIN` (DML-2026-180): `Jenis_Role='ADMIN'`, `Id_Grup_Fitur` NULL — tidak mewarisi hibah halaman SYSTEM (yang dijaga `system.admin`). Hanya System Admin yang menugaskannya.
- Jangkauan: daftar putih `authorization.super_admin.modul` (ESS, KPI, LMS, VISITOR, DMS) — modul baru seperti BUILDER tidak ikut otomatis. Cakupan & tanggung jawab dari penugasan Super Admin-nya; mengubah akses butuh tanggung jawab AKSES.
- Boleh: menugaskan/mencabut peran AKSES & ADMIN modul (bukan Super Admin; peran admin yang membawa halaman SYSTEM_ONLY — di dev kelima peran admin ESS, yang membawa System Auth Manager/Page Access — tetap hanya System Admin), grant, matriks hak peran non-admin di modul BARU. Halaman yang diberikan tetap harus boleh didelegasikan (bukan SYSTEM_ONLY/tata kelola); syarat "admin sendiri bisa membukanya" tidak berlaku bagi Super Admin. Peran admin yang diangkat tidak diperiksa halamannya (peran itulah wewenangnya).
- Tidak boleh: mengangkat Super Admin, katalog/mode modul, hak peran admin, akses diri sendiri.
- `DMS_ADMIN`: admin modul DMS; hak awal = hibah DMS.
- Jalur panas: Super Admin terbaca dalam query admin yang sama (sidebar tetap satu query).

## Uji

`SuperAdminTest` (9): jangkauan modul, angkat/cabut admin modul, peran akses modul mana pun, peran pembawa SYSTEM_ONLY ditolak, tak bisa mengangkat Super Admin, tak bisa mengubah mode, tanpa tanggung jawab AKSES ditolak, admin modul tetap tak bisa mengangkat admin, pilihan peran.

## Hasil review (opus)

- C1 diperbaiki: peran admin pembawa halaman SYSTEM_ONLY tak bisa diangkat/dicabut Super Admin.
- I2–I4 diperbaiki: uji tanggung jawab memakai peran yang sah + pesan; uji grant SYSTEM_ONLY/diri sendiri, hak peran admin, penugasan kedaluwarsa; jangkauan daftar putih.
- Minor diselesaikan (permintaan user 10 Okt): tanggung jawab yang diberikan ⊆ milik Super Admin pemberi; pilihan peran tak menawarkan peran admin pembawa halaman SYSTEM_ONLY; akses Super Admin hanya diubah System Admin (menutup kolusi lewat admin perantara); `kueriPenugasanSuperAdmin` dikunci `Jenis_Role='ADMIN'` & `Id_Grup_Fitur IS NULL`.
- Tetap (keputusan): DML tak menyaring `Flag_Aktif` peta — sama dengan DML-2026-166 dan sinkron hak, yang juga tak menyaringnya.
