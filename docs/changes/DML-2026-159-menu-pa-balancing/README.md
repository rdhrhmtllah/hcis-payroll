# DML-2026-159-menu-pa-balancing

## Metadata

- **Change ID**: `DML-2026-159-menu-pa-balancing`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Affected Objects**: `N_HRIS_Menus` (1 baris, `Jenis_page = 'PaKalibrasiPage'`)
- **Artifact**: `database/dml/DML-2026-159-menu-pa-balancing/data-change.sql`
- **Dependencies**: `DDL-2026-165-pa-balancing`; rute `performance-appraisal.balancing` (`check.jabatan:PaHasilPage`)
- **Rancangan**: `docs/superpowers/specs/2026-09-30-pa-balancing-design.md` §7

## Deskripsi

Butir menu "Kalibrasi Grade" (tersembunyi sejak halamannya dialihkan) dialihkan menjadi
"Balancing" (`/performance-appraisal/balancing`) dan ditampilkan di header Administrator PA
dengan `Sort_Menu = 11`. `Jenis_page` tetap `PaKalibrasiPage`: pemetaan grup fitur KPI
(`N_HRIS_Grup_Fitur_Map.Id_Grup_Map = 251`, `Id_Grup_Fitur = 2`) dan pemegang
`HRIS_Page_Access`-nya sudah ada.

## Dasar registry (Web_HRIS development, 30 Sep 2026)

- `N_HRIS_Master_Grup_Fitur`: `Id_Grup_Fitur = 2`, `Kode_Grup_Fitur = KPI`, aktif.
- `N_HRIS_Grup_Fitur_Map`: `PaKalibrasiPage` → grup 2, aktif.
- Pemegang `PaKalibrasiPage` = pemegang `PaHasilPage` = UserID_Web 4 dan 622 (diperiksa skrip sebagai guard).

## Cakupan

`UPDATE N_HRIS_Menus ... WHERE Jenis_page = 'PaKalibrasiPage'` — perkiraan 1 baris.

## Cara membatalkan

```sql
UPDATE N_HRIS_Menus
SET Nama_Menu = 'Kalibrasi Grade', Url_Menu = '/performance-appraisal/kalibrasi', Route_Name = NULL,
    Is_Show_Sidebar = 'T', Sort_Menu = '12',
    Menu_Subtitle = 'Forum penyeimbangan grade dan penyesuaian kuadran performa'
WHERE Jenis_page = 'PaKalibrasiPage';
```

Lalu `InertiaShellService::flushMenuCache()`.

## Agent completed

- Dieksekusi 30 Sep 2026 ke `Web_HRIS @ instance-20240809-031237`: 1 baris berubah (Kalibrasi Grade → Balancing, Sort 12 → 11, tampil). Guard pemegang akses lolos. `InertiaShellService::flushMenuCache()` dan `route:clear` dijalankan.

## User tasks

1. Tinjau skrip.
2. Rekam di SQL Tracker dan jalankan ke production sesudah DDL-2026-165 dan kode Balancing terbit di lajur yang sama.
3. Verifikasi menu di production.
