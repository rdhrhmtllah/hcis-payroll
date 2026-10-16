/* Rollback DML-2026-182 — kembalikan nama & grup menu KPI/PA ke keadaan 13 Okt 2026. */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE dbo.N_HRIS_Master_Grup_Fitur
   SET Nama_Grup_Fitur = 'Key Performance Indicator', Subtitle_Grup_Fitur = 'Key Performance Indicator'
 WHERE Kode_Grup_Fitur = 'KPI';

UPDATE dbo.N_HRIS_Menus
   SET Nama_Menu = CASE Jenis_page
           WHEN 'dashboardStaff' THEN 'KPI Performance Individu'
           WHEN 'dashboardCeo' THEN 'KPI Performance Tim'
           WHEN 'FormKPIPage' THEN 'Pengajuan KPI'
           WHEN 'UpdatePerformancePage' THEN 'Update KPI'
           WHEN 'FormKpiTeam' THEN 'Pengajuan KPI Team'
           WHEN 'ApprovalParameterKPI' THEN 'Approval Parameter KPI'
           WHEN 'UpdatePerformanceTeam' THEN 'Update KPI Team'
           WHEN 'ApprovalUpdatePerformance' THEN 'Approval Update KPI'
           WHEN 'PerformanceAppraisalSayaPage' THEN 'Penilaian Saya'
           WHEN 'PerformanceAppraisalLeaderPage' THEN 'Penilaian Tim'
       END,
       Header_Code = CASE WHEN Jenis_page IN ('dashboardStaff', 'dashboardCeo') THEN 'dashboard'
                          WHEN Jenis_page IN ('FormKPIPage', 'UpdatePerformancePage') THEN 'kpi'
                          WHEN Jenis_page IN ('PerformanceAppraisalSayaPage', 'PerformanceAppraisalLeaderPage') THEN 'performance_appraisal'
                          ELSE 'manajemen_kpi' END,
       Header_Label = CASE WHEN Jenis_page IN ('dashboardStaff', 'dashboardCeo') THEN 'Dashboard'
                           WHEN Jenis_page IN ('FormKPIPage', 'UpdatePerformancePage') THEN 'KPI'
                           WHEN Jenis_page IN ('PerformanceAppraisalSayaPage', 'PerformanceAppraisalLeaderPage') THEN 'Performance Appraisal'
                           ELSE 'Manajemen KPI' END
 WHERE Jenis_page IN ('dashboardStaff', 'dashboardCeo', 'FormKPIPage', 'UpdatePerformancePage', 'FormKpiTeam',
                      'ApprovalParameterKPI', 'UpdatePerformanceTeam', 'ApprovalUpdatePerformance',
                      'PerformanceAppraisalSayaPage', 'PerformanceAppraisalLeaderPage');

UPDATE dbo.N_HRIS_Menus
   SET Section_Code = NULL, Section_Label = NULL, Section_Icon = NULL, Sort_Section = NULL
 WHERE Section_Code IN ('key_performance_indicator', 'performance_appraisal', 'administrasi');

COMMIT TRANSACTION;
