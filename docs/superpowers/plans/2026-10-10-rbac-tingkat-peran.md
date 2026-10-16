# RBAC — Master Tingkat Peran & Super Admin per Modul

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Tiap modul dikelola seperti aplikasi tersendiri: Super Admin modul mengangkat admin modulnya dan mengatur matriks hak (menu admin, tambah/ubah/hapus); admin modul menugaskan peran & grant. Tingkatnya didefinisikan satu master (System Admin › Super Admin › Admin › Pengguna) yang dibaca aturan delegasi.

**Architecture:** DDL-2026-181 membuat `N_HRIS_Tingkat_Peran` (kode, nama, peringkat, kemampuan `Boleh_Angkat` / `Boleh_Ubah_Matriks` / `Boleh_Tugaskan`) dan kolom `N_HRIS_Role_Functional.Kode_Tingkat`. DML-2026-182 mengisi master, mengisi tingkat peran dari data sekarang, membuat `{ESS,LMS,VISITOR,DMS}_SUPER_ADMIN`, dan menonaktifkan `HCIS_SUPER_ADMIN` (lintas modul — diganti per modul). `TingkatPeran` membaca master (tanpa tabel: nilai bawaan yang sama). `AturanDelegasi` memakai tingkat pengubah DI MODUL ITU: hanya boleh mengangkat/mengubah yang peringkatnya lebih rendah.

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` §6. Disetujui user 10 Okt 2026 ("ya saya setuju" — Super Admin per modul, matriks hanya Super Admin modul, master Tingkat Peran empat tingkat).

## Global Constraints

- Branch `feat/system/rbac-tingkat-peran` (dari `feat/system/rbac-minor`); commit lokal, tanpa push/PR.
- Mode LAMA: keputusan buka halaman tidak berubah. Yang berubah hanya siapa boleh MENGUBAH akses lewat Akses & Peran.
- System Admin tetap bendera `KPI_Users.Is_System_Admin`; di master hanya baris informasi (tak bisa diubah).
- Halaman SYSTEM_ONLY & tata kelola tetap hanya System Admin (peran admin pembawa halaman itu — admin ESS — tetap hanya System Admin).

## Aturan

| Tingkat | Peringkat | Angkat | Ubah matriks | Tugaskan |
|---|---|---|---|---|
| SYSTEM_ADMIN | 100 | Y | Y | Y |
| SUPER_ADMIN | 80 | Y | Y | Y |
| ADMIN | 60 | T | T | Y |
| PENGGUNA | 10 | T | T | T |

- Tingkat pengubah di modul M = tingkat tertinggi penugasan adminnya (bertanggung jawab AKSES, aktif, belum kedaluwarsa) di M; System Admin = SYSTEM_ADMIN.
- Peran tingkat ≥ ADMIN: butuh `Boleh_Angkat` dan peringkat peran < peringkat pengubah. Peran PENGGUNA: butuh `Boleh_Tugaskan`.
- Matriks: butuh `Boleh_Ubah_Matriks`, peringkat peran < pengubah, bukan peran yang ia pegang.
- Sasaran: tingkat tertinggi karyawan sasaran di M harus < pengubah (admin tak bisa mengubah akses Super Admin modulnya; Super Admin tak saling mengubah).
- Tanggung jawab yang diberikan ⊆ milik pengubah di M.
- Kemampuan SUPER_ADMIN/ADMIN bisa diubah System Admin di tab Katalog (alasan + audit). Tingkat sebuah peran diubah System Admin di tab Peran.

## Review Focus

1. Admin modul (tingkat ADMIN) mencoba mengubah matriks → ditolak; Super Admin modul → boleh.
2. Super Admin di KPI tak bisa mengelola LMS kecuali juga Super Admin LMS; Super Admin di dua modul mengelola keduanya.
3. Admin modul mencoba menugaskan/mencabut peran milik Super Admin modulnya → ditolak.
4. Tanpa DDL-181: perilaku sama dengan nilai bawaan (tak ada 500).
5. HCIS_SUPER_ADMIN tak lagi memberi wewenang apa pun.

## Tasks

- [ ] **Task 1 — DDL-181 + DML-182** (+ README, status.json), jalankan di dev dua kali.
- [ ] **Task 2 — `TingkatPeran` + KeputusanAkses/AturanDelegasi/CakupanKaryawan/benderaKelola** memakai tingkat per modul; hapus logika Super Admin lintas modul. Uji `TingkatPeranTest` (ganti `SuperAdminTest`), sesuaikan uji admin-ubah-matriks.
- [ ] **Task 3 — Layar:** tab Katalog (master tingkat, ubah kemampuan), tab Peran (tingkat peran; System Admin mengubahnya). Endpoint `GET /tingkat`, `PUT /tingkat/{kode}`, `PUT /peran/{id}/tingkat`.
- [ ] **Task 4 — Verifikasi + review** (governance, RuteTulis, Unit, verify-*, pint, DSG, browser).

## Hasil review (opus) — 0 Critical, 4 Important, 8 Minor

- I1 diperbaiki: sidebar tak memilih `Jenis_Role` tanpa DDL-2026-165.
- I2 diperbaiki: matriks peran admin pembawa halaman SYSTEM_ONLY hanya System Admin; aksi baru pada halaman lama ikut diperiksa.
- I3 diperbaiki: DML-2026-182 berhenti (THROW 51182) bila DML-2026-166 belum dijalankan; urutan rilis dicatat; pemegang `KPI_SUPER_ADMIN` prod didaftar sebelum rilis.
- I4 diperbaiki: layar lama Role Manager (`saveUserRoles`, `saveAdminPermissions`) memakai AturanDelegasi untuk non-System Admin.
- M1–M4, M7, M8 diperbaiki (syarat "bisa membuka" diukur dari peringkat; pool approval tak bisa diubah tingkatnya & tanggung jawab dimatikan saat turun; peran tak bisa bertingkat System Admin; backfill Super Admin hanya Jenis_Role ADMIN; uji dipertajam; runbook `rbac:skema`).
- Ruling M5: mengubah matriks sebuah peran ikut mengubah akses SEMUA pemegangnya, termasuk Super Admin lain yang kebetulan memegangnya — sifat bawaan matriks per peran; jejak di audit. Biaya bila salah: Super Admin bisa mengurangi akses rekan setingkat lewat peran bersama.
- Ruling M6: tingkat diambil yang tertinggi, cakupan gabungan semua penugasan admin di modul — mis. Super Admin bercakupan divisi X + Admin bercakupan semua → mengangkat admin di seluruh modul. Memisahkan cakupan per tingkat butuh desain ulang cakupan; dicatat untuk fase berikut. Biaya: penugasan ganda seperti itu harus dihindari System Admin.
