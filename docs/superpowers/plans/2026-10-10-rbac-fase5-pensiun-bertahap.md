# RBAC Fase 5 — Pensiun Bertahap Jalur Lama

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Modul BARU benar-benar diatur oleh matriks hak (termasuk aksi tulis), layar lama memberi tahu bahwa pengelolaan pindah ke Akses & Peran, dan System Admin melihat kesiapan tiap modul — tanpa mengubah apa pun untuk modul LAMA.

**Architecture:** Mode LAMA tetap persis seperti sekarang. Di BARU, aksi tulis (request non-GET) di rute `check.jabatan` dinilai dari hak aksi peran (CREATE/EDIT/DELETE/…); grant langsung dan menu global tetap memberi akses penuh halaman (seperti hari ini). Middleware eksplisit `akses:halaman,aksi` untuk rute yang butuh aksi spesifik. Lapis aksi `authorize.resource` di modul BARU memakai `KeputusanAkses`. Kode aturan lama TIDAK dihapus (5d ditunda sampai semua modul prod BARU).

**Tech Stack:** Laravel 10, SQL Server, Vue 3 + Inertia, PHPUnit (DatabaseTransactions).

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` (§5 middleware `akses:halaman,aksi`, §7 banner layar lama, §8 Fase 5). Cakupan 5a–5c disetujui user 10 Okt 2026; 5d (hapus kode lama) ditunda.

## Global Constraints

- Branch `feat/system/rbac-fase5` (dari `feat/system/rbac-fase4`); commit lokal, tanpa push/PR sampai diminta.
- Tanpa DDL/DML. Tidak ada tabel yang dihapus.
- Mode LAMA: tidak ada keputusan yang berubah (GET maupun non-GET).
- Jalur panas GET tidak bertambah query.

## Aturan aksi tulis (BARU)

- Request GET/HEAD/OPTIONS → VIEW (seperti sekarang).
- Request lain → "TULIS": boleh bila System Admin, grant langsung aktif, menu global, atau salah satu peran punya aksi non-VIEW untuk halaman itu. Halaman yang di katalog (`N_HRIS_Menu_Capabilities`) tak punya aksi non-VIEW → cukup VIEW (tak ada yang bisa dicabut).
- BAYANGAN: aturan lama memutuskan; beda dicatat dengan aksi `TULIS`.

## Review Focus

1. Modul LAMA: POST/PUT/DELETE ke rute `check.jabatan` → keputusan identik dengan sebelum Fase 5 (Task 1).
2. Modul BARU: peran yang semua aksi tulisnya dicabut di matriks → GET boleh, POST 403 (Task 1).
3. Modul BARU: pemegang grant langsung atau halaman menu global tetap bisa POST (Task 1).
4. Halaman tanpa aksi tulis di katalog → POST tetap boleh bagi pemegang VIEW (Task 1).
5. Laporan kesiapan tidak mengubah data (sinkron kering) dan bisa dihitung saat sinkron terjadwal berjalan (409 jelas) (Task 3).

## Tasks

- [ ] **Task 1 — 5b aksi tulis.** `KeputusanAkses::bolehTulis(User, halaman)`; grant & menu global = semua aksi di aturan baru; `CheckJabatan` memakai `bolehTulis` untuk request non-GET; middleware `akses` (`EnsureAksesHalaman`, `akses:halaman,aksi`); `AuthorizationScopeResolver::isActionAllowed` → `KeputusanAkses::boleh` bila modul halaman BARU. Uji `AksiTulisTest`.
- [ ] **Task 2 — 5a layar lama.** Banner "Kini dikelola di Akses & Peran" (+ daftar modul BARU) di Admin Role Manager, System Auth Manager, Page Access Setup, Menu & Matrix; `ModeAkses::lupakan()` setelah binding grup fitur berubah (`saveMenuBinding`, create/update/delete grup). Uji: binding baru langsung terbaca `ModeAkses::modulHalaman`.
- [ ] **Task 3 — 5c laporan kesiapan.** `SinkronHakRole::jalankan(kering: true)` dengan rincian per modul; endpoint `GET /api/v1/akses-peran/kesiapan` (System Admin) → per modul: mode, pernah BARU, halaman, penugasan aktif, selisih, beda hak-vs-hibah, status (`LAMA`/`SIAP_BAYANGAN`/`SIAP_BARU`/`BARU`). Tombol "Hitung kesiapan" di tab Katalog. Uji di `KatalogAksesTest`.
- [ ] **Task 4 — Spec + verifikasi.** Catatan Fase 5 di spec; governance suite, RuteTulis, Unit/SYSTEM, verify-*, pint, DS guard; browser (Katalog kesiapan, banner, POST DMS).

## Daftar periksa sebelum modul lain pindah ke BARU (hasil review)

Rute POST di bawah `check.jabatan` yang tampak hanya membaca (pindaian 10 Okt 2026). Di modul
BARU dengan aksi tulis di katalog, rute ini butuh aksi tulis kecuali ditandai
`->defaults('rbac_aksi', 'VIEW')`. Sudah ditandai: DMS (export-studio pratinjau/jalankan,
monitoring export & export/request, kebijakan rules preview). Belum (modulnya masih LAMA):

- KPI: `update-performance/list-Kpi/by-user`, `list-Kpi-by-pass/by-user`, `list-history`,
  `kpi-enforcement/preview`, `preview-detail`, `cancel/preview`, `export/request`.
- ESS: `approval-flow-shift/import/validate`, `import/preview`, `export`, `leader-shift/check-conflicts`,
  `kuota-management/process/recovery/preview`, `karyawan/export-kuota`, `cuti-bersama-adv/preview`,
  `admin/pengumpulan-data/ekspor`, `izinAdmin/cancel/preview`, `lembur-management/validate-draft`,
  `lembur-management/admin/validate-draft`.
- LMS: `lms/admin/monitoring/export/request`.
- VISITOR: `visitor/export-studio/{tamu,permit}/pratinjau`, `jalankan`, `master-data/import/permit/pratinjau`.
- Tata kelola: `system-auth/distribution/preview`, `page-access/sort/validate`,
  `menu-approval-matrix/.../matrix-karyawan/export`, `membership/{grupId}/preview`, `karyawan/filter-search`.
