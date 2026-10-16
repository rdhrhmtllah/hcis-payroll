# Feature Mapping: LMS-Registered SOP Monitoring

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopMonitoringPage` ke implementation existing SOP Admin Monitoring. Registry menempatkan page ini di module `LMS`, sedangkan source dan business boundary menggunakan domain `SOP`. Source controller/page/route telah dikelompokkan di bawah `LMS/SOP`; registry mismatch tetap dipertahankan dan tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopMonitoringPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/monitoring
Middleware    : auth, check.jabatan:menuSopMonitoringPage
```

Registry memverifikasi mapping ke `LMS`, tetapi source identifier, controller, frontend, route, service, job/export, database, dan business language menunjukkan domain SOP. Belum ada module SOP aktif pada registry.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Governance / Monitoring     # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Compliance Monitoring   # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

Monitoring berbeda dari SOP Penjadwalan: scheduling membuat/mencabut assignment, sedangkan monitoring membaca coverage, progress, risiko, analitik, dan evidence.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Main controller | `app/Http/Controllers/LMS/SOP/Governance/SopAdminMonitoringController.php` | summary, coverage, package/document analytics, quiz reset, export | `LEGACY_KEEP` |
| People controller | `app/Http/Controllers/LMS/SOP/Governance/SopAdminMonitoringPeopleController.php` | employee list, deep dive, history, read records, attestation, attendance, export | `LEGACY_KEEP` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | `/sop/admin/monitoring*` and extensive API surface | `LEGACY_KEEP` |
| Main page | `resources/js/Pages/LMS/SOP/Governance/Admin/SopMonitoring.vue` | tabs and monitoring dashboard | `SOURCE_DOMAIN_KEEP` |
| Deep dive pages | `SopMonitoringKaryawan.vue`, `SopMonitoringKaryawanPaket.vue` | employee/package detail | `SOURCE_DOMAIN_KEEP` |
| Main components | `resources/js/Pages/LMS/SOP/Governance/Admin/monitoring/` | package, employee, document, analytics, export tabs | `SOURCE_DOMAIN_KEEP` |
| Monitoring service | `app/Services/LMS/SOP/SopMonitoringService.php` | summary, coverage, package/detail queries | `SHARED_SOP_KEEP` |
| People service | `app/Services/LMS/SOP/SopMonitoringPeopleService.php` | employee coverage/history/attestation/read records | `SHARED_SOP_KEEP` |
| Analytics service | `app/Services/LMS/SOP/SopMonitoringAnalyticsService.php` | risk, pulse, trend, aging, heatmap, compliance | `SHARED_SOP_KEEP` |
| Export services | `SopComplianceExportService.php`, `SopExportRinciService.php` | async coverage/detail exports | `SHARED_SOP_KEEP` |
| Related services | `SopProgressReadService.php`, `SopDocumentControlService.php` | progress/document evidence dependencies | `CROSS_FEATURE_DEPENDENCY` |
| Frontend test | `resources/js/Pages/LMS/SOP/Governance/Admin/SopMonitoring.test.js` | tabs and legacy URL guard | `FOCUSED_TEST` |
| Backend/UAT tests | monitoring, ringkasan, document, read-record, export tests | substantial related coverage | `RELATED_TEST` |
| Dedicated full suite | no single full authorization/browser/async suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET  /sop/admin/monitoring
GET  /sop/admin/monitoring/karyawan/{kode}
GET  /sop/admin/monitoring/karyawan/{kode}/paket/{id}

GET  /api/v1/sop/admin/monitoring/summary
GET  /api/v1/sop/admin/monitoring/coverage
GET  /api/v1/sop/admin/monitoring/filter-options
GET  /api/v1/sop/admin/monitoring/karyawan*
GET  /api/v1/sop/admin/monitoring/dokumen*
GET  /api/v1/sop/admin/monitoring/risiko
GET  /api/v1/sop/admin/monitoring/pulse
GET  /api/v1/sop/admin/monitoring/analitik/*
GET  /api/v1/sop/admin/monitoring/kepatuhan/*
GET  /api/v1/sop/admin/monitoring/kuis-tertahan
POST /api/v1/sop/admin/monitoring/kuis/{id}/reset
POST /api/v1/sop/admin/monitoring/ingatkan
GET/POST /api/v1/sop/admin/monitoring/export*
GET  /api/v1/sop/admin/monitoring/paket/{id}*
```

## Important findings

1. `menuSopMonitoringPage` is LMS-registered but source/business ownership is SOP. Status is `REGISTRY_SOURCE_MISMATCH_REVIEW`.
2. Monitoring is split across main and people controllers plus multiple service boundaries. It should not be treated as one simple page migration.
3. The monitoring surface includes read-only analytics and mutations such as quiz reset and reminder dispatch; these require separate action authorization and audit review.
4. Employee deep-dive pages are linked from scheduling batch rows and main monitoring tabs.
5. Document monitoring uses shared document control/detail components and must remain consistent with Master Dokumen and Builder contracts.
6. Export is asynchronous and uses Activity Center-backed services; estimate/request/download/status behavior must be preserved.
7. Frontend tab tests exist and backend/UAT coverage is substantial, but no single suite proves full page authorization, all tabs, export lifecycle, reminder/reset mutations, and browser navigation.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/SOP/Governance/Monitoring/SopMonitoringController.php
app/Http/Controllers/LMS/SOP/Governance/Monitoring/SopMonitoringPeopleController.php
app/Services/LMS/SOP/Governance/Monitoring/
routes/LMS/SOP/Governance/Monitoring/SopMonitoring.php
resources/js/Pages/LMS/SOP/Governance/Monitoring/SopMonitoring/
tests/<type>/LMS/SOP/Governance/Monitoring/SopMonitoring/
```

This proposal is blocked until registry/business ownership decides whether SOP becomes a formal module or remains registered under LMS. Current SOP paths are the safe state.

## Dependency and risk checklist

Before any move, verify:

- registry decision for `menuSopMonitoringPage` and absent formal SOP module;
- main/people controller route ownership and all deep links;
- read analytics versus mutation actions (quiz reset/reminder) authorization and audit;
- monitoring, people, analytics, progress, document-control, and export service contracts;
- async export task lifecycle and Activity Center linkage;
- frontend tab state, query URL compatibility, and shared document components;
- focused frontend, backend, UAT, authorization, mutation, export, and browser tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Registry/business ownership must be resolved before any target path is approved. Source files are now grouped under `LMS/SOP`; live LMS mapping and URL contracts remain unchanged.

## Lapis kesehatan dokumen di tab Dashboard (2026-08-27)

Tab Dashboard (`TabRingkasan.vue`) menjadi **dua lapis**. Desain:
`plans/sop/2026-08-27-dashboard-kesehatan-dokumen-design.md`.

```text
Lapis 1  KESEHATAN DOKUMEN   apa yang kita punya, berapa yang sampai ke orang
Lapis 2  KEPATUHAN ORANG     empat bab yang sudah ada, penyebutnya dinyatakan
```

### Satuan lapis dokumen: DOKUMEN, bukan baris paket

`SopDokumenKesehatanService` berangkat dari `N_SOP_Dokumen`. Ini perbedaan
pokok dari `SopDocumentControlService::baseVersiAktif()`, yang berangkat dari
`N_SOP_Item_Versi` lalu JOIN ke `N_SOP_Paket_Item` sehingga:

- satu dokumen yang dipakai tiga paket terhitung tiga kali;
- dokumen yang belum masuk paket mana pun tak punya baris sama sekali;
- yang terbaca adalah `Judul_Item`/`Nomor_SOP_Resmi`, yakni cerminan.

Butiran lama **tetap benar untuk daftar baris register** — satu dokumen memang
muncul di beberapa paket. Yang keliru adalah memakainya sebagai jumlah dokumen.

### Tiga kelompok

```text
WAJIB          ≥1 penugasan aktif, di mana pun          → kepatuhannya diukur
TERBUKA        bukan wajib, ada di ≥1 paket TERBIT      → beredar tanpa kewajiban
BELUM_BEREDAR  tak masuk paket mana pun                 → sah tapi tak sampai
```

Wajib menang; ketiganya saling lepas dan menjumlah tepat ke total. Dihitung
saat dibaca — **tak ada kolom baru, tak ada DDL**.

`Mode_Distribusi` **bukan** penanda wajib: paket `PUBLIK` sengaja tetap boleh
dijadwalkan (keputusan 20 Agu 2026).

### Gerbang paket publik

Klasifikasi mengikuti `terapkanSaringanPaket()` supaya dashboard dan tab
Karyawan tak pernah menyebut dua angka berbeda, **lalu melaporkan yang tersaring
sebagai angka bernama** (`Tersaring_Gerbang_Publik`). Cacat yang sudah tercatat
itu tetap ada, tetapi berhenti bekerja diam-diam.

### Endpoint

```text
GET /api/v1/sop/admin/monitoring/dokumen/kesehatan
    gerbang: menuSopMonitoringPage (yang sudah ada — nol baris RBAC baru)
    saringan: sama dengan endpoint monitoring lain
    balasan: Komposisi · Sebaran · Versi_Menggantung · Antrean
             · Tersaring_Gerbang_Publik · Lead_Time
             · Coverage_Dokumen · Coverage_Paket
```

Berdampingan dengan `/dokumen/ringkasan` yang lama, dan bedanya disengaja:
yang lama berbutir baris paket, yang ini berbutir dokumen.

`Sebaran` menggantikan `Piramid`, `APQC`, dan `Kepemilikan` yang dulu berdiri
sendiri-sendiri. Bentuknya `{Total, Sumbu[], Data{}}`: `Sumbu` adalah katalog
pemilih (jenis · apqc · unit · tipe · paket) dan `Data` memuat barisnya, semua
dalam bentuk baris yang SAMA. Tiap sumbu membawa `Menjumlah` — sumbu paket
bernilai `false` karena satu dokumen boleh dipakai beberapa paket, dan
alasannya ikut sebagai `Catatan`. Medan `Nilai` tiap baris adalah nilai
saringan Master Dokumen yang siap pakai (hashid, bukan id mentah); ia ada
karena tautan lama mengirim id mentah sehingga saringannya batal diam-diam.

### Empat bab kepatuhan tidak dibongkar

Mesin analitiknya (±7.200 baris, 11 endpoint) sudah menghitung dari
`N_SOP_Penjadwalan_Detail` saja — penyebutnya memang sudah "wajib-only". Yang
ditambahkan hanya baris yang **menyatakan** penyebut itu di atas lapis 2.
