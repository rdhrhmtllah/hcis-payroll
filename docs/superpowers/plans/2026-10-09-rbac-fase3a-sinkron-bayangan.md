# RBAC Fase 3a — Sinkron Hak & Mode Bayangan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans.

**Goal:** `N_HRIS_Role_Hak` selalu mengikuti kondisi LAMA selama modul belum BARU (sinkron berkala), sakelar mode per modul (LAMA/BAYANGAN/BARU) dengan pencatatan selisih, dan tab Katalog (System Admin) untuk mengatur mode & melihat selisih. Semua modul tetap LAMA → tidak ada perilaku berubah.

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` §3, §5, §8 (Fase 3). Disetujui user 9 Okt 2026 (termasuk DDL-2026-168 di dev).

## Global Constraints
- Branch `feat/system/rbac-fase3` (dari `feat/system/rbac-fase2`); commit lokal, tanpa push/PR.
- DDL/DML aditif & idempoten; prod via SQL Tracker oleh user.
- Mode default LAMA; tanpa tabel mode = LAMA.
- Sinkron hanya menyentuh modul yang modenya LAMA/BAYANGAN; di BARU, `N_HRIS_Role_Hak` milik admin (Fase 3b).
- Sinkron menonaktifkan (Flag_Aktif='T'), tidak menghapus.

## Tasks
- [ ] **T1 DDL-2026-168**: `N_HRIS_Akses_Mode_Modul` (Id_Grup_Fitur PK, Mode LAMA/BAYANGAN/BARU default LAMA + CHECK, Updated_At, Updated_By); `N_HRIS_Akses_Selisih` (Id bigint PK, Id_User, Jenis_page, Aksi, Keputusan_Lama, Keputusan_Baru, Jumlah, Pertama_At, Terakhir_At; UQ user+halaman+aksi). Jalankan di dev ×2. Uji skema.
- [ ] **T2 `SinkronHakRole`** + perintah `rbac:sinkron-hak` (+ `--kering`): target = aturan DML-2026-166 dihitung di PHP dari config (satu sumber); tambah yang kurang, aktifkan lagi yang nonaktif, nonaktifkan yang tak lagi berlaku; lewati role di modul BARU. Uji: hibah baru → ditambah; hibah dimatikan → dinonaktifkan; idempoten; modul BARU tak disentuh; kesetaraan bolehBaru=boleh tetap setelah sinkron.
- [ ] **T3 DML-2026-169**: jadwal `rbac:sinkron-hak` tiap jam di `N_HRIS_Scheduler_Configs`. Jalankan di dev.
- [ ] **T4 Mode di `KeputusanAkses::boleh`**: modul halaman → mode; LAMA = lama; BAYANGAN = lama + catat selisih (upsert, galat ditelan); BARU = bolehBaru. System Admin tetap boleh. Uji ketiga mode + tanpa tabel.
- [ ] **T5 Tab Katalog** (System Admin): API `GET katalog`, `PUT katalog/{grup}/mode` (alasan wajib, audit MODE), `GET selisih`, `POST sinkron`; Vue `TabKatalog.vue`. Uji akses (admin modul 403), audit, selisih.
- [ ] **T6** Spec + verifikasi + browser.
