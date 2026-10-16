# DML-2026-172-menu-pa-sembunyikan-dash-distribusi

## Metadata

- **Change ID**: `DML-2026-172-menu-pa-sembunyikan-dash-distribusi`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Revision**: `r1`
- **DML Checksum (SHA-256)**: `2d3044e528fcc495a020669d328ea28b716075929823b5873c42ca9ff93170b4`
- **Affected Objects**: `N_HRIS_Menus` (3 baris: `PaDashboardPage`, `PaDistribusiPage`, `PaPemantauanPage`)
- **Status**: `TRACKER_PENDING`
- **Paths**: `database/dml/DML-2026-172-menu-pa-sembunyikan-dash-distribusi/data-change.sql`

## Deskripsi

1. Menu "Dash Hidden" (`PaDashboardPage`) dan "Distribusi Hidden" (`PaDistribusiPage`) disembunyikan
   dari sidebar (`Is_Show_Sidebar = 'T'`). Baris, halaman, dan hak aksesnya tidak dihapus.
2. Menu "Monitoring Cycle" (`PaPemantauanPage`) diberi `Active_Match_Pattern`
   `/performance-appraisal/balancing*` (hanya bila kosong), supaya halaman sesi Balancing menyorot
   Monitoring Cycle.

Pasangan kode: `InertiaShellService::satuMenuAktif()` — sebelumnya setiap menu yang URL-nya awalan
alamat halaman ikut aktif, sehingga "Assessment Cycle" (`/performance-appraisal`) selalu menyala di
semua halaman PA. Kini hanya satu menu per modul yang aktif: yang kecocokannya paling spesifik.

## Eksekusi development

- 2026-10-08, `Web_HRIS @ instance-20240809-031237`. Sebelum: ketiga menu `Is_Show_Sidebar = 'Y'`,
  pola kosong. Sesudah: Dash & Distribusi `T`, pola Monitoring terisi. Dijalankan ulang: no-op.
  `flushMenuCache()` dijalankan.

## User tasks

1. Catat `data-change.sql` persis di SQL Tracker (checksum di atas), jalankan ke production, lalu
   jalankan `InertiaShellService::flushMenuCache()` (atau tunggu cache menu kedaluwarsa).
