# DML-2026-192-menu-pa-dashboard

| | |
|---|---|
| Change ID | DML-2026-192-menu-pa-dashboard |
| Target | Web_HRIS development; production through SQL Tracker |
| Modul | KPI / Performance Appraisal / Menu & Navigation |
| Artefak | `database/dml/DML-2026-192-menu-pa-dashboard/data-change.sql` |
| Dependencies | DML-2026-189-pa-menu-production |
| Status | DRAFT |

## Ringkasan Perubahan
Restrukturisasi navigasi Performance Appraisal di sidebar (Section `Performance Appraisal`):
1. **Header Baru "Dashboard"** (`Sort_Header = 8`, `Header_Code = 'dashboard_pa'`):
   - Menu `Dashboard PA Individu` (`PaDashboardIndividuPage`, URL: `/performance-appraisal/dashboard-individu`): Halaman khusus hasil penilaian individu, radar 360°, 9-box HAV map, dan riwayat cycle.
   - Menu `Dashboard PA Tim` (`PerformanceAppraisalLeaderPage`, URL: `/performance-appraisal/tim`): Pindahan menu Appraisal Tim ke header Dashboard agar status dan hasil tim berada di area dashboard.
2. **Header "Appraisal"** (`Sort_Header = 9`, `Header_Code = 'appraisal'`):
   - Menu `Appraisal Individu` (`PerformanceAppraisalSayaPage`, URL: `/performance-appraisal/saya`): Murni lembar evaluasi diri dan wizard pengisian tugas penilaian 360°.
3. **Pemetaan Grup Fitur**:
   - `PaDashboardIndividuPage` dipetakan ke grup KPI/PMS sebagai `Is_Global_Menu = 'Y'`.
