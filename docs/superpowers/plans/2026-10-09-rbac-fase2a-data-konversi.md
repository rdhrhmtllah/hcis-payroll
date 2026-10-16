# RBAC Fase 2a — Data & Konversi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Tabel model RBAC baru (role→hak, cakupan & tanggung jawab per penugasan, kolom kedaluwarsa/alasan) terisi otomatis dari kondisi sekarang, plus penilai "aturan BARU" yang terbukti setara dengan aturan LAMA — tanpa mengubah keputusan akses apa pun.

**Architecture:** DDL-2026-165 (aditif) + DML-2026-166 (konversi idempoten, SQL murni untuk SQL Tracker). `KeputusanAkses::bolehBaru()` membaca tabel baru; belum dipakai memutuskan (Fase 3 = bayangan). Bila tabel belum ada, `bolehBaru()` = `boleh()` (LAMA).

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` (4.1, 4.2, 6, 8 — Fase 2). Disetujui user 9 Okt 2026: pembagian 2a/2b, aturan konversi, eksekusi DDL/DML di dev.

## Global Constraints

- Branch `feat/system/rbac-fase2` (dari `feat/system/rbac-fase1`); commit lokal, tanpa push/PR.
- DDL/DML hanya aditif & idempoten; dev dijalankan agen (sudah diizinkan), prod lewat SQL Tracker oleh user.
- Tidak ada keputusan akses yang berubah di Fase 2a.

## Aturan konversi (DML-2026-166)

1. `Jenis_Role`: role di `config('authorization.tata_kelola.peran_admin')` → `ADMIN`; `ADMIN_PERMIT`, `ADMIN_TAMU`, `ADMIN_TAMU_B2B`, `TAMU_DEPT_*`, `SHE_ADMIN`, `ROLE_KPI_VERIF` → `POOL_APPROVAL`; sisanya `AKSES`. Hanya label — tidak memengaruhi hak.
2. `N_HRIS_Role_Hak`: untuk setiap role aktif, setiap halaman yang kini ia buka lewat hibah (join `N_HRIS_Grup_Fitur_Map` `Flag_Hibah_Role='Y'`, **tanpa** saring `gfm.Flag_Aktif` — sama dengan `User::memilikiAksesBaru`), dikurangi halaman tata kelola bila role bukan admin sah (Fase 0). Aksi: VIEW selalu + aksi `N_HRIS_Menu_Capabilities` (`Flag_Aktif='Y'` dan `Is_Active_Default='Y'`).
3. `N_HRIS_Role_User_Cakupan`: per penugasan aktif, dari `N_HRIS_Role_Scope` role-nya: `GLOBAL_ALL`/`SCOPE_HC_ALL_ACTIVE` → `SEMUA`, `SCOPE_GA_PHL` → `PHL`, `DIVISIONAL_FILTER` → `DIVISI`, `LEADER_DYNAMIC_TEAM` → `TIM`. Tanpa baris cakupan = `SEMUA`.
4. `N_HRIS_Role_User_Tanggung_Jawab`: penugasan role ADMIN: `*MENU_APPROVAL*` → AKSES+APPROVAL; `*MENU_ADMINISTRATOR*`/`KPI_ADMIN_PHL` → AKSES; `*APPROVAL_ADMINISTRATOR*` → APPROVAL; `*MEMBERSHIP*` → KEANGGOTAAN; `*SUPER_ADMIN` → ketiganya.
5. Daftar halaman tata kelola & role admin di SQL **disalin** dari config; uji penjaga memastikan keduanya sama.

## Review Focus

1. Role dengan banyak halaman ber-capability → semua aksi default tertulis, VIEW tetap ada (Task 3).
2. Penugasan kedaluwarsa (`Berlaku_Sampai` lewat) → tidak memberi hak di `bolehBaru` (Task 3).
3. DML dijalankan dua kali → tidak ada baris ganda (Task 2).
4. Role nonaktif / penugasan nonaktif → tidak dikonversi (Task 2).
5. Daftar config berubah tanpa memperbarui SQL → uji penjaga gagal (Task 2).

## Tasks

- [ ] **Task 1 — DDL-2026-165** `database/ddl/DDL-2026-165-rbac-role-hak-cakupan/{forward,drop}.sql`, `docs/changes/DDL-2026-165-rbac-role-hak-cakupan/README.md`, entri status.json; jalankan di dev dua kali; verifikasi objek. Uji: `tests/Feature/SYSTEM/Governance/Authorization/SkemaRbacFase2Test.php` (tabel & kolom ada).
- [ ] **Task 2 — DML-2026-166** `database/dml/DML-2026-166-rbac-konversi-role-hak/data-change.sql` + README + entri status.json; uji `KonversiRbacTest`: idempoten (jalankan di transaksi dua kali → jumlah sama), role/penugasan nonaktif tak ikut, daftar SQL = config; jalankan di dev.
- [ ] **Task 3 — `KeputusanAkses::bolehBaru(User, string $halaman, string $aksi = 'VIEW'): bool`** — System Admin ∨ (penugasan aktif & belum kedaluwarsa → `N_HRIS_Role_Hak` aktif cocok halaman+aksi) ∨ (grant langsung aktif & belum kedaluwarsa, aksi VIEW atau aksi apa pun? → VIEW saja di 2a) ∨ (menu global, VIEW). Cache per user per request. Uji: setara `boleh()` untuk semua pemegang role × semua halaman (VIEW); aksi default terbaca; kedaluwarsa ditolak; tabel hilang → sama dengan LAMA.
- [ ] **Task 4 — Spec + verifikasi** (Governance suite, RuteTulis, Unit/SYSTEM, verify-references, pint) dan ringkasan hasil konversi di dev.
