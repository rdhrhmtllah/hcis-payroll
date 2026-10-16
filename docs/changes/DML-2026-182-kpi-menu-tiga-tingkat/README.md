# DML-2026-182: Susunan menu PMS 3 tingkat (usulan LnD)

Butuh DDL-2026-181. Mengisi bagian, grup, dan nama menu modul KPI sesuai usulan LnD; keputusan user 13 Okt 2026: menu admin jadi bagian sendiri "Administrasi", Individual Development Plan belum ditampilkan.

| Bagian | Grup | Menu (`Jenis_page`) |
|---|---|---|
| Key Performance Indicator (1) | Dashboard | Dashboard KPI Individu (`dashboardStaff`), Dashboard KPI Tim (`dashboardCeo`) |
| | KPI Individu | Input Parameter KPI (`FormKPIPage`), Update Performance KPI (`UpdatePerformancePage`) |
| | KPI Tim | Input Parameter KPI Tim (`FormKpiTeam`), Approval Parameter KPI Tim (`ApprovalParameterKPI`), Update Performance KPI Tim (`UpdatePerformanceTeam`), Approval Performance KPI Tim (`ApprovalUpdatePerformance`) |
| Performance Appraisal (2) | Appraisal | Appraisal Individu (`PerformanceAppraisalSayaPage`), Appraisal Tim (`PerformanceAppraisalLeaderPage`) |
| Administrasi (9) | Administrator KPI / Administrator PA / System Admin PA | 34 menu admin (29 tampil + 5 tersembunyi dari sidebar), nama tidak berubah |

Nama tampilan modul KPI → "Performance Management System" (`Kode_Grup_Fitur` tetap `KPI`).

- Baris terdampak (dev, dijalankan 13 Okt 2026): 1 `N_HRIS_Master_Grup_Fitur`, 44 `N_HRIS_Menus` (10 menu pengguna + 34 menu admin, 5 di antaranya tersembunyi dari sidebar).
- Idempoten. Rollback: `rollback.sql` (mengembalikan nama/grup lama dan mengosongkan kolom bagian).
- Setelah dijalankan: `InertiaShellService::flushMenuCache()` di setiap checkout.

## Amandemen r2 (14 Okt 2026, sebelum SQL Tracker)

Production memakai `Header_Code = 'administrator-kpi'` (tanda hubung) untuk 9 menu Administrator KPI; r1 hanya mencocokkan `administrator_kpi` sehingga 9 menu itu akan tertinggal tanpa bagian. r2 mencocokkan `REPLACE(Header_Code, '-', '_')`. Hitungan baca-saja: production 34 menu admin (r1: 25), dev 35 tanpa selisih (r1 = r2 di dev, tak perlu dijalankan ulang).

Keadaan production 14 Okt 2026 (baca-saja): DDL-2026-181 sudah ada; DML ini belum dijalankan. Label production sudah berbeda dari dev sebelum DML ini (grup `KPI Tim` dengan kode `manajemen-kpi`, menu `Appraisal Individu/Tim` di grup `Appraisal`), jadi `rollback.sql` — yang mengembalikan ke keadaan dev 13 Okt — di production menghasilkan label dev (`Manajemen KPI`, `Penilaian Saya/Tim`), bukan label production sebelumnya. Untuk production, cukup bagian kosongkan + nama modul bila ingin batal.

