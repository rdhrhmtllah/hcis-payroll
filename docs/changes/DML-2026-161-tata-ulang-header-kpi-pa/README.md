# DML-2026-161-tata-ulang-header-kpi-pa

## Metadata

- **Change ID**: `DML-2026-161-tata-ulang-header-kpi-pa`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Affected Objects**: `N_HRIS_Menus` (24 baris)
- **Artifact**: `database/dml/DML-2026-161-tata-ulang-header-kpi-pa/data-change.sql`
- **Dependencies**: `DML-2026-160-menu-pa-penutupan-siklus`

## Deskripsi

Menata ulang pengelompokan header pada modul KPI agar terpartisi rapi berdasarkan domain (seluruh KPI di depan, disusul seluruh Performance Appraisal). Menghilangkan header terpisah "Master Data" (`pa_master`) dan melebur kedua butirnya langsung ke dalam header "Administrator PA" dengan awalan/prefix `Master · ` agar selaras dengan master-master PA lainnya.

## Struktur Urutan Header Modul KPI

1. **Dashboard** (`Sort_Header = 1`) — KPI Performance Individu, KPI Performance Tim
2. **KPI** (`Sort_Header = 3`) — Pengajuan KPI, Update KPI
3. **Manajemen KPI** (`Sort_Header = 5`) — Pengajuan & Approval Parameter/Update KPI Tim
4. **Administrator KPI** (`Sort_Header = 7`, sebelumnya 8) — Seluruh konfigurasi & administrasi KPI
5. **Performance Appraisal** (`Sort_Header = 9`, sebelumnya 6) — Penilaian Saya, Penilaian Tim
6. **Administrator PA** (`Sort_Header = 11`) — Master PA (1-10) diikuti alur operasional siklus, pemantauan, dan analitik (11-17)

## Peleburan Master Data ke Administrator PA

- `PaMasterGrupKurvaPage`: "Grup Level Kurva" → `Master · Grup Level Kurva` (`Sort_Menu = 9`)
- `PaMasterRelasiOrganisasiPage`: "Relasi Organisasi" → `Master · Relasi Organisasi` (`Sort_Menu = 10`)

## Cara membatalkan

```sql
UPDATE N_HRIS_Menus SET Sort_Header = '8' WHERE Header_Code = 'administrator_kpi';
UPDATE N_HRIS_Menus SET Sort_Header = '6' WHERE Header_Code = 'performance_appraisal';
UPDATE N_HRIS_Menus SET Nama_Menu = 'Grup Level Kurva', Header_Code = 'pa_master', Header_Label = 'Master Data', Sort_Header = '14', Sort_Menu = '10' WHERE Jenis_page = 'PaMasterGrupKurvaPage';
UPDATE N_HRIS_Menus SET Nama_Menu = 'Relasi Organisasi', Header_Code = 'pa_master', Header_Label = 'Master Data', Sort_Header = '14', Sort_Menu = '9' WHERE Jenis_page = 'PaMasterRelasiOrganisasiPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '9' WHERE Jenis_page = 'PerformanceAppraisalPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '10' WHERE Jenis_page = 'PaPemantauanPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '12' WHERE Jenis_page = 'PaHavMapPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '13' WHERE Jenis_page = 'PaDistribusiPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '14' WHERE Jenis_page = 'PaRiwayatPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '15' WHERE Jenis_page = 'PaDashboardPage';
UPDATE N_HRIS_Menus SET Sort_Menu = '16' WHERE Jenis_page = 'PaExportStudioPage';
```

Lalu `InertiaShellService::flushMenuCache()`.

## Agent completed

- Skrip DML disiapkan dan diuji ke `Web_HRIS @ instance-20240809-031237`.
- `InertiaShellService::flushMenuCache()` dijalankan.

## User tasks

1. Tinjau skrip.
2. Rekam di SQL Tracker dan jalankan ke production.
3. Verifikasi menu sidebar dan topbar di production.
