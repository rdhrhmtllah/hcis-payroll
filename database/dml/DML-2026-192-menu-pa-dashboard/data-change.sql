/* Change ID: DML-2026-192-menu-pa-dashboard
   Target: Web_HRIS development; production through SQL Tracker.
   Dependencies: DML-2026-189-pa-menu-production.
   Scope: Restrukturisasi menu PA:
          1. Header baru "Dashboard" di section "Performance Appraisal" (Sort_Header = 8).
          2. Menu baru "Dashboard Performance Individu" (PaDashboardIndividuPage, Url: /performance-appraisal/dashboard-individu).
          3. Pindahkan "Appraisal Tim" menjadi "Dashboard Performance Tim" ke header Dashboard (Sort_Header = 8, Sort_Menu = 2).
          4. Perbarui menu "Appraisal Individu" di header Appraisal (Sort_Header = 9, Sort_Menu = 1) untuk fokus pengisian evaluasi 360°.
          5. Peta grup fitur untuk PaDashboardIndividuPage (grup KPI/PMS).
   Dokumentasi: docs/changes/DML-2026-192-menu-pa-dashboard/README.md
*/

BEGIN TRANSACTION;

    DECLARE @grupId int = (SELECT Id_Grup_Fitur FROM dbo.N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur IN ('KPI', 'PMS'));
    IF @grupId IS NULL
    BEGIN
        THROW 50192, 'DML-2026-192-menu-pa-dashboard: grup fitur KPI atau PMS tidak ditemukan. Dihentikan.', 1;
    END;

    /* 1. Menu baru: Dashboard PA Individu */
    IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Menus WHERE Jenis_page = 'PaDashboardIndividuPage')
    BEGIN
        INSERT INTO dbo.N_HRIS_Menus (
            Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
            Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu,
            Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle,
            Section_Code, Section_Label, Section_Icon, Sort_Section
        )
        VALUES (
            'PaDashboardIndividuPage', '001', N'Dashboard PA Individu', 'bi-speedometer2', '/performance-appraisal/dashboard-individu',
            'dashboard_pa', N'Dashboard', 'bi bi-speedometer2', 8, 'Y',
            1, '/performance-appraisal/dashboard-individu*', NULL, 'self', 'Y', N'Hasil evaluasi 360°, radar kompetensi, 9-box HAV, dan riwayat cycle',
            'performance_appraisal', N'Performance Appraisal', 'bi bi-clipboard2-check', 2
        );
    END
    ELSE
    BEGIN
        UPDATE dbo.N_HRIS_Menus
        SET Nama_Menu = N'Dashboard PA Individu',
            Header_Code = 'dashboard_pa',
            Header_Label = N'Dashboard',
            Sort_Header = 8,
            Sort_Menu = 1
        WHERE Jenis_page = 'PaDashboardIndividuPage';
    END;

    /* 2. Pindahkan Appraisal Tim ke header Dashboard sebagai Dashboard PA Tim */
    UPDATE dbo.N_HRIS_Menus
    SET Nama_Menu = N'Dashboard PA Tim',
        Icon_Menu = 'bi-people-fill',
        Header_Code = 'dashboard_pa',
        Header_Label = N'Dashboard',
        Header_Icon = 'bi bi-speedometer2',
        Sort_Header = 8,
        Sort_Menu = 2,
        Menu_Subtitle = N'Pantau hasil evaluasi dan profil kompetensi anggota tim di bawah supervisi',
        Section_Code = 'performance_appraisal',
        Section_Label = N'Performance Appraisal',
        Section_Icon = 'bi bi-clipboard2-check',
        Sort_Section = 2
    WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';

    /* 3. Perbarui Appraisal Individu agar tetap di header Appraisal (Sort_Header = 9) */
    UPDATE dbo.N_HRIS_Menus
    SET Nama_Menu = N'Appraisal Individu',
        Icon_Menu = 'bi-clipboard2-check-fill',
        Header_Code = 'appraisal',
        Header_Label = N'Appraisal',
        Header_Icon = 'bi bi-clipboard2-data',
        Sort_Header = 9,
        Sort_Menu = 1,
        Menu_Subtitle = N'Lembar evaluasi diri dan pengisian tugas penilaian 360°',
        Section_Code = 'performance_appraisal',
        Section_Label = N'Performance Appraisal',
        Section_Icon = 'bi bi-clipboard2-check',
        Sort_Section = 2
    WHERE Jenis_page = 'PerformanceAppraisalSayaPage';

    /* 4. Pemetaan grup fitur untuk PaDashboardIndividuPage */
    IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PaDashboardIndividuPage' AND Id_Grup_Fitur = @grupId)
    BEGIN
        INSERT INTO dbo.N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
        VALUES (@grupId, 'PaDashboardIndividuPage', 1, 'T', 'Y', 'Y', 'Y');
    END;

    /* 5. Catat ke N_SYSTEM_Database_Change */
    IF NOT EXISTS (SELECT 1 FROM dbo.N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-192')
    BEGIN
        INSERT INTO dbo.N_SYSTEM_Database_Change (
            Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
            Artifact_Path, Documentation_Path,
            Target_Development, Status_Development, Status_SQL_Tracker,
            Status_Production, Status_Verification, Catatan, Created_By
        )
        VALUES (
            'DML-2026-192', 'DML',
            'Restrukturisasi menu Dashboard Performance Appraisal & Appraisal Pengisian',
            'Pemisahan hasil evaluasi ke Header Dashboard (Dashboard Performance Individu & Tim) dan pengisian ke Header Appraisal (Appraisal Individu)',
            'database/dml/DML-2026-192-menu-pa-dashboard/data-change.sql',
            'docs/changes/DML-2026-192-menu-pa-dashboard/README.md',
            'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
            'KPI / Performance Appraisal. Menambahkan PaDashboardIndividuPage dan memindahkan PerformanceAppraisalLeaderPage ke header Dashboard.',
            NULL
        );
    END;

COMMIT TRANSACTION;
GO
