# RBAC Fase 3b — Tombol Ubah Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans.

**Goal:** Layar Akses & Peran bisa mengubah akses: tugaskan/cabut peran (cakupan, tanggung jawab, kedaluwarsa, alasan), grant pengecualian (alasan, kedaluwarsa), ubah matriks hak peran (hanya modul BARU) — semua lewat aturan anti-eskalasi di server, pratinjau dampak, dan audit sebelum→sesudah.

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` §6, §7. Rancangan 3b disetujui user 9 Okt 2026.

## Global Constraints
- Branch `feat/system/rbac-fase3`; commit lokal, tanpa push/PR. Tanpa DDL baru.
- Jalur panas (gerbang/sidebar) tak boleh bertambah kueri skema; flag skema RBAC di satu cache.
- Kedaluwarsa (`Berlaku_Sampai`) berlaku juga di aturan LAMA — hanya baris bertanggal yang terpengaruh.
- Aturan anti-eskalasi (dicek server): bukan diri sendiri; admin modul hanya peran modulnya (bukan ADMIN), untuk karyawan dalam cakupannya, dengan cakupan ⊆ miliknya; peran ADMIN & halaman tata kelola/SYSTEM_ONLY hanya System Admin; ubah matriks hanya modul BARU.

## Tasks
- [ ] T1 `SkemaRbac` (flag skema di satu cache) + kedaluwarsa di `memilikiAksesBaru`, `HibahTataKelola::halamanLewatPeranAdmin`, `telusuri`; tampilkan penanda kedaluwarsa. Uji: penugasan & grant kedaluwarsa ditolak aturan lama; tanpa tanggal tetap.
- [ ] T2 `CakupanKaryawan` (SEMUA/PHL/DIVISI/DEPARTEMEN/LOKASI/TIM; ENTITAS & tak dikenal = tidak) + `AturanDelegasi`. Uji tiap aturan.
- [ ] T3 `PengelolaanAksesService` + endpoint: tugaskan, cabut, grant, cabut grant, ubah hak, pratinjau; bendera `bisa_*` di respons baca. Uji API + audit + cache.
- [ ] T4 UI: dialog tugaskan/cabut/grant, editor matriks (modul BARU), pratinjau dampak.
- [ ] T5 Verifikasi + browser + review.
