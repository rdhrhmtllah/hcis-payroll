/* Change ID: DML-2026-182-kpi-menu-tiga-tingkat — susunan menu PMS usulan LnD (butuh DDL-2026-181). IDEMPOTEN. */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE dbo.N_HRIS_Master_Grup_Fitur
   SET Nama_Grup_Fitur = 'Performance Management System', Subtitle_Grup_Fitur = 'Performance Management System'
 WHERE Kode_Grup_Fitur = 'KPI';

UPDATE dbo.N_HRIS_Menus
   SET Section_Code = 'key_performance_indicator', Section_Label = 'Key Performance Indicator',
       Section_Icon = 'bi bi-graph-up-arrow', Sort_Section = 1,
       Nama_Menu = CASE Jenis_page
           WHEN 'dashboardStaff' THEN 'Dashboard KPI Individu'
           WHEN 'dashboardCeo' THEN 'Dashboard KPI Tim'
           WHEN 'FormKPIPage' THEN 'Input Parameter KPI'
           WHEN 'UpdatePerformancePage' THEN 'Update Performance KPI'
           WHEN 'FormKpiTeam' THEN 'Input Parameter KPI Tim'
           WHEN 'ApprovalParameterKPI' THEN 'Approval Parameter KPI Tim'
           WHEN 'UpdatePerformanceTeam' THEN 'Update Performance KPI Tim'
           WHEN 'ApprovalUpdatePerformance' THEN 'Approval Performance KPI Tim'
       END,
       Header_Code = CASE WHEN Jenis_page IN ('dashboardStaff', 'dashboardCeo') THEN 'dashboard'
                          WHEN Jenis_page IN ('FormKPIPage', 'UpdatePerformancePage') THEN 'kpi_individu'
                          ELSE 'kpi_tim' END,
       Header_Label = CASE WHEN Jenis_page IN ('dashboardStaff', 'dashboardCeo') THEN 'Dashboard'
                           WHEN Jenis_page IN ('FormKPIPage', 'UpdatePerformancePage') THEN 'KPI Individu'
                           ELSE 'KPI Tim' END
 WHERE Jenis_page IN ('dashboardStaff', 'dashboardCeo', 'FormKPIPage', 'UpdatePerformancePage',
                      'FormKpiTeam', 'ApprovalParameterKPI', 'UpdatePerformanceTeam', 'ApprovalUpdatePerformance');

UPDATE dbo.N_HRIS_Menus
   SET Section_Code = 'performance_appraisal', Section_Label = 'Performance Appraisal',
       Section_Icon = 'bi bi-clipboard2-check', Sort_Section = 2,
       Nama_Menu = CASE Jenis_page WHEN 'PerformanceAppraisalSayaPage' THEN 'Appraisal Individu' ELSE 'Appraisal Tim' END,
       Header_Code = 'appraisal', Header_Label = 'Appraisal'
 WHERE Jenis_page IN ('PerformanceAppraisalSayaPage', 'PerformanceAppraisalLeaderPage');

UPDATE mn
   SET Section_Code = 'administrasi', Section_Label = 'Administrasi', Section_Icon = 'bi bi-gear-fill', Sort_Section = 9
  FROM dbo.N_HRIS_Menus mn
 WHERE REPLACE(mn.Header_Code, '-', '_') IN ('administrator_kpi', 'administrator_pa', 'system_admin_pa')
   AND EXISTS (SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map m WHERE m.Jenis_page = mn.Jenis_page AND m.Id_Grup_Fitur = 2);

COMMIT TRANSACTION;

/* Verifikasi:
SELECT Section_Label, Header_Label, COUNT(*) FROM dbo.N_HRIS_Menus WHERE Section_Code IS NOT NULL GROUP BY Section_Label, Header_Label;
-- Key Performance Indicator: Dashboard 2, KPI Individu 2, KPI Tim 4; Performance Appraisal: Appraisal 2; Administrasi: 29
*/
