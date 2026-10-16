# Feature Mapping: VISITOR Reporting Export Studio

Status: `IMPLEMENTATION_IN_PROGRESS`
Tanggal verifikasi registry: 2026-08-24
Environment registry: development `Web_HRIS` pada `team311.dyndns.info:1433`

## Registry basis

```text
Module        : VISITOR
Id            : 4
Nama          : Visitor Management System
Page mapping  : visitorLaporanPage
URL           : /laporanvisitor
Flag_Aktif    : Y
Authorization : auth + check.jabatan:visitorLaporanPage
Navigation    : administrator_visitor / Administrator Visitor
```

Registry database tidak memiliki kolom hierarchy submodule/feature. Placement
teknis mengikuti boundary existing yang sudah melayani page tersebut:

```text
Module    : VISITOR
Submodule : Workflow
Feature   : Reporting / Export Studio
Layers    : Controller, Request, Service/Dataset, Job, Route, Page, Test
```

## Data contract

- `tamu`: `N_VISITOR_Tamu_Efektif`, satu baris per transaksi tamu efektif.
- `permit`: `N_VISITOR_Permit_Efektif`, satu baris per permit efektif.
- Kedua dataset hanya memuat transaksi approved/auto-approved yang sudah masuk
  proyeksi lifecycle. Pending/rejected dari tabel transaksi mentah tidak masuk.
- Nomor HP dan nomor identitas tamu tersedia dan ditandai sensitif. NIK/nomor HP
  pemohon Permit tidak dipublikasikan.
- Module template dipatri sebagai `visitor`; route dan payload harus memakai
  dataset yang sama agar template Tamu dan Permit tidak bercampur.

## Compatibility boundary

Monitoring harian dan endpoint `/laporanvisitor-harian` dipertahankan. Endpoint
legacy `/laporanvisitor-tamu` dan `/laporanvisitor-permit` tidak dihapus dalam
task ini, tetapi tidak lagi menjadi tab utama. Mesin bersama
`SYSTEM/Application/Reports` dan komponen `SYSTEM/ReportStudio` dipakai tanpa
perubahan.

## Database dependency

Feature memakai tabel template lintas modul dari
`DDL-2026-009` dan proyeksi lifecycle dari `DDL-2026-020`. Keduanya sudah ada di
development; SQL Tracker/production masih harus diverifikasi sebelum rollout.
Metadata navigasi page ditempatkan pada header existing Administrator Visitor
melalui `DML-2026-025`; DML-2026-024 salah target dan tidak boleh dipromosikan.
Koreksi tersebut tidak mengubah data laporan, route, atau authorization.
