# DML-2026-160-menu-pa-penutupan-siklus

## Metadata

- **Change ID**: `DML-2026-160-menu-pa-penutupan-siklus`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Affected Objects**: `N_HRIS_Menus` (2 baris: `PaPemantauanPage`, `PaKalibrasiPage`)
- **Artifact**: `database/dml/DML-2026-160-menu-pa-penutupan-siklus/data-change.sql`
- **Dependencies**: `DML-2026-159-menu-pa-balancing`; kode halaman Penutupan Siklus (`/performance-appraisal/pemantauan`, `GET /cycles/{id}/penutupan`)
- **Rancangan**: `docs/superpowers/specs/2026-10-02-pa-penutupan-siklus-design.md` §6

## Deskripsi

Halaman Monitoring & Verifikasi menjadi **Penutupan Siklus** dengan lima tahap (pengisian, hitung
& verifikasi, kurva, balancing, rilis & terbit). Menu "Balancing" disembunyikan dari sidebar karena
kini menjadi tahap ke-4; halaman dan hak aksesnya tidak berubah. `PaHasilPage` sudah tersembunyi.

## Cakupan

- `UPDATE N_HRIS_Menus SET Nama_Menu, Menu_Subtitle WHERE Jenis_page = 'PaPemantauanPage'` — 1 baris.
- `UPDATE N_HRIS_Menus SET Is_Show_Sidebar = 'T' WHERE Jenis_page = 'PaKalibrasiPage'` — 1 baris.

## Cara membatalkan

```sql
UPDATE N_HRIS_Menus SET Nama_Menu = 'Monitoring & Verifikasi',
    Menu_Subtitle = 'Progres pengisian evaluasi rater dan antrean verifikasi hasil'
WHERE Jenis_page = 'PaPemantauanPage';
UPDATE N_HRIS_Menus SET Is_Show_Sidebar = 'Y' WHERE Jenis_page = 'PaKalibrasiPage';
```

Lalu `InertiaShellService::flushMenuCache()`.

## Agent completed

- Dieksekusi 2 Okt 2026 ke `Web_HRIS @ instance-20240809-031237` (3 batch): PaPemantauanPage
  "Monitoring & Verifikasi" → "Penutupan Siklus"; PaKalibrasiPage tampil Y → T.
  `InertiaShellService::flushMenuCache()` dijalankan.

## User tasks

1. Tinjau skrip.
2. Rekam di SQL Tracker dan jalankan ke production setelah kode Penutupan Siklus dan DML-2026-159 terbit di lajur yang sama.
3. Verifikasi menu di production.
