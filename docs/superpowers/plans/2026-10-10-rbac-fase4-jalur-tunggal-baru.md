# RBAC Fase 4 — Jalur Keputusan Tunggal & Membuka Mode BARU

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Semua keputusan "boleh buka halaman" lewat `KeputusanAkses::boleh`, sehingga mode BARU per modul bisa dibuka dengan aman dan dipilot (DMS) tanpa ada layar yang diam-diam masih memakai aturan lama.

**Architecture:** Pemanggil `memilikiJabatan` di luar `KeputusanAkses` (Visitor, Panduan, supervisor) dan lapis halaman `AuthorizationScopeResolver::canAccessPage` (dipakai `authorize.resource`, `ApprovalMatrixService`, sidebar lama) dialihkan ke `KeputusanAkses::boleh`. Dalam mode LAMA hasilnya sama untuk halaman yang memang diperiksa lewat `canAccessPage` (uji `test_lama_halaman_bergerbang_resolver_tak_berubah`). Di halaman lain resolver lama berbeda dari gerbang route (hanya membaca grup role-nya, peka huruf, tanpa menu global): di dev 4 user mendapat `true` pada 37 pasangan halaman–user — diterima karena kini sama dengan gerbang route; yang terlihat hanya daftar `visibleMenus` di AdminRoleManager. `telusuri` mengikuti mode modul. Kunci `mode_baru_diizinkan` dibuka; BARU hanya bisa dicapai dari BAYANGAN dan didahului sinkron hak terakhir.

**Tech Stack:** Laravel 10, SQL Server, Vue 3 + Inertia, PHPUnit (DatabaseTransactions).

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` (§5, §8 Fase 4, §9). Disetujui user 10 Okt 2026 ("ya lanjut fase 4").

## Global Constraints

- Branch `feat/system/rbac-fase4` (dari `feat/system/rbac-fase3`); commit lokal, tanpa push/PR sampai diminta.
- Tanpa DDL/DML baru. Prod tetap LAMA semua sampai System Admin mengubah mode.
- Mode LAMA: tak ada keputusan akses yang berubah.
- Resolver approval & pool approval tidak diubah (spec §9).
- Jalur panas tetap ±1 query per request di mode LAMA.

## Review Focus

1. Modul BARU: user dengan hak di `N_HRIS_Role_Hak` tapi tanpa hibah → `canAccessPage`, `authorize.resource` (lapis halaman), Visitor, Panduan, supervisor ikut membuka (Task 1).
2. Modul BARU: user dengan hibah tapi hak dicabut dari matriks → semua jalur di atas menolak (Task 1).
3. Telusuri di modul BARU menampilkan jalur hak peran, bukan hibah (Task 2).
4. Pindah ke BARU langsung dari LAMA → ditolak; dari BAYANGAN → sinkron dijalankan dulu; sinkron sedang berjalan → 409 dan mode tak berubah (Task 3).
5. Pemanggil `memilikiJabatan` baru di luar `KeputusanAkses` → uji penjaga gagal (Task 1).

## Tasks

- [ ] **Task 1 — Jalur tunggal.** `VerifikatorVisitorController`, `LaporanVisitorController` (3 titik), `VisitorDashboardController`, `PanduanService::bolehBukaHalaman`, `User::isSupervisor`, `AuthorizationScopeResolver::canAccessPage`, `SopExportStudioController::lingkup` → `KeputusanAkses::boleh`. Uji `JalurKeputusanTunggalTest`: (a) penjaga statis — tak ada `->memilikiJabatan(` di `app/` selain `KeputusanAkses` dan model `User`; (b) modul BARU: hak tanpa hibah → `canAccessPage` & `isSupervisor`-pola (`boleh`) true; hibah tanpa hak → false; (c) mode LAMA: `canAccessPage` = `memilikiJabatan` untuk halaman non-global milik pemegang role.
- [ ] **Task 2 — Telusuri ikut mode.** `KeputusanAkses::telusuri` mengembalikan `mode`, `boleh` = `boleh()`, dan `jalur` menurut aturan yang berlaku: BARU → `HAK_PERAN:<kode>` dari `N_HRIS_Role_Hak` (penugasan belum kedaluwarsa) + grant + menu global; LAMA/BAYANGAN → jalur lama (BAYANGAN juga `jalur_baru`). `AksesPeranQueryService::telusuri` menyaring `HAK_PERAN:` modul admin. UI TabPengguna: label jalur + mode. Uji di `KeputusanAksesBaruTest`/`AksesPeranTest`.
- [ ] **Task 3 — Buka BARU.** `mode_baru_diizinkan` default `true` (env tetap bisa mematikan). `KatalogAksesService::ubahMode` ke BARU: wajib dari BAYANGAN (422), jalankan `SinkronHakRole::jalankan(false, 'UI')` dulu (`SinkronSedangBerjalan` → 409), lalu ubah mode + audit. TabKatalog: BARU aktif hanya dari BAYANGAN. Uji di `KatalogAksesTest` (ganti `test_mode_baru_dikunci_sementara`).
- [ ] **Task 4 — Spec + verifikasi.** Catatan Fase 4 di spec; governance suite, RuteTulis, Unit/SYSTEM, verify-*, pint, DS guard; uji browser Katalog & Telusuri.
