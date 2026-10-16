/* Change ID: DML-2026-189-pa-menu-production
   Target: PRODUCTION (emi_db_web_hr) melalui SQL Tracker. Bergantung pada DDL-2026-187-pa-skema-lengkap-production
          dan DML-2026-188-pa-master-production.
   Scope: 25 butir menu Performance Appraisal (N_HRIS_Menus), peta grup fiturnya (N_HRIS_Grup_Fitur_Map:
          halaman PA ke grup KPI, halaman relasi organisasi milik System Admin ke grup SYSTEM), dan 2 jadwal
          perintah PA (N_HRIS_Scheduler_Configs), serta registri endpoint callback HC Learn untuk PA
          (POST /api/v1/pa/third-party/penilaian/complete; isi DML-2026-102) di N_LMS_Endpoint_Api/Akses_Endpoint
          dan N_HRIS_Api_Endpoint/Akses_Endpoint. Tanpa registri ini setiap kiriman nilai HC Learn dijawab 403.
   Tidak memuat hak akses per pengguna (HRIS_Page_Access): hak diberikan admin production lewat layar.
   Id_Menu / Id_Grup_Map / Id_Scheduler diberikan IDENTITY production (ID development bertabrakan dengan menu production).
   Grup fitur dicari lewat Kode_Grup_Fitur, bukan ID (ID grup SYSTEM development 5, production 11).
   Kolom Section_* (DDL-2026-181) belum ada di production per 14 Okt 2026: bila sudah ada saat dijalankan, bagian
          menu ikut diisi; bila belum, DML-2026-182 mengisinya kelak (aturannya sama).
   IDEMPOTEN: baris yang sudah ada dilewati. Satu batch, satu transaksi.
   Dokumentasi: docs/changes/DML-2026-189-pa-menu-production/README.md
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* Prasyarat: skema PA dan grup fitur tujuan ada. */
IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL OR OBJECT_ID('N_HRIS_Organization_Relationship', 'U') IS NULL
    THROW 50189, 'DML-2026-189-pa-menu-production: skema PA belum ada. Jalankan DDL-2026-187-pa-skema-lengkap-production lebih dulu.', 1;
IF NOT EXISTS (SELECT 1 FROM N_LMS_Api_Aplikasi WHERE Nama_Aplikasi = 'HUMAN CAPITAL LEARNING' AND Flag_Aktif = 'Y')
   OR NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = 'HC Learn' AND Flag_Aktif = 'Y')
    THROW 50189, 'DML-2026-189-pa-menu-production: aplikasi HC Learn tidak ditemukan/aktif di N_LMS_Api_Aplikasi atau N_HRIS_Api_Aplikasi. Dihentikan.', 1;
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'KPI')
   OR NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'SYSTEM')
    THROW 50189, 'DML-2026-189-pa-menu-production: grup fitur KPI atau SYSTEM tidak ditemukan. Dihentikan.', 1;

BEGIN TRANSACTION;

    /* 1. Butir menu — 25 baris */
    INSERT INTO dbo.N_HRIS_Menus (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
    SELECT v.* FROM (VALUES
        ('PerformanceAppraisalSayaPage', '001', N'Appraisal Individu', 'bi-clipboard2-check-fill', '/performance-appraisal/saya', 'appraisal', N'Appraisal', 'bi bi-clipboard2-data', 9, 'Y', 1, NULL, NULL, 'self', 'Y', N'Lembar evaluasi diri, tugas penilaian 360°, dan hasil performa pribadi'),
        ('PerformanceAppraisalLeaderPage', '001', N'Appraisal Tim', 'bi-people-fill', '/performance-appraisal/tim', 'appraisal', N'Appraisal', 'bi bi-clipboard2-data', 9, 'Y', 2, NULL, NULL, 'self', 'Y', N'Pantau progres penilaian dan hasil evaluasi anggota tim di bawah supervisi'),
        ('PaMasterKategoriPenilaiPage', '001', N'Master · Jenis Penilai', 'bi-person-badge-fill', '/performance-appraisal/master/kategori-penilai', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 1, NULL, NULL, 'self', 'Y', N'Empat kategori penilai 360°; kodenya terkunci, nama dan deskripsinya bisa disunting'),
        ('PaMasterLevelMasterPage', '001', N'Master · Level PA', 'bi-diagram-3', '/performance-appraisal/master/level', 'administrator_pa', N'Administrator PA', 'bi bi-sliders', 11, 'T', 2, NULL, NULL, 'self', 'Y', NULL),
        ('PaMasterLevelPaPage', '001', N'Master · Koreksi Level PA', 'bi-person-badge', '/performance-appraisal/master/level-pa', 'administrator_pa', N'Administrator PA', 'bi bi-sliders', 11, 'T', 3, NULL, NULL, 'self', 'Y', NULL),
        ('PaMasterBobotPilarPage', '001', N'Master · Bobot KPI & Kompetensi', 'bi-pie-chart-fill', '/performance-appraisal/master/bobot-pilar', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 4, NULL, NULL, 'self', 'Y', N'Proporsi pembagian bobot capaian KPI vs evaluasi Kompetensi per level jabatan'),
        ('PaMasterJenisKompetensiPage', '001', N'Master · Jenis Kompetensi', 'bi-diagram-2-fill', '/performance-appraisal/master/jenis-kompetensi', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 5, NULL, NULL, 'self', 'Y', N'Master dimensi dan kategori tipe kompetensi (Core Values, Manajerial, dsb.)'),
        ('PaMasterKompetensiPage', '001', N'Master · Kompetensi', 'bi-bullseye', '/performance-appraisal/master/kompetensi', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 6, NULL, NULL, 'self', 'Y', N'Katalog butir kompetensi master dan indikator perilaku'),
        ('PaMasterSyaratKompetensiPage', '001', N'Master · Matriks Kompetensi', 'bi-grid-3x3-gap-fill', '/performance-appraisal/master/syarat-kompetensi', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 7, NULL, NULL, 'self', 'Y', N'Matriks pemetaan dimensi kompetensi wajib per level jabatan'),
        ('PaMasterIkatanPaketSoalPage', '001', N'Master · Matriks Soal', 'bi-diagram-3-fill', '/performance-appraisal/master/ikatan-paket-soal', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 8, NULL, NULL, 'self', 'Y', N'Matriks paket soal per level jabatan dan kategori penilai'),
        ('PaMasterPitaNilaiPage', '001', N'Master · Skala Nilai & Grade', 'bi-bar-chart-steps', '/performance-appraisal/master/pita-nilai', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 9, NULL, NULL, 'self', 'Y', N'Interval rentang skor, skala predikat, dan batasan grade hasil evaluasi'),
        ('PaMasterGrupKurvaPage', '001', N'Master · Grup Level Kurva', 'bi-diagram-2', '/performance-appraisal/master/grup-kurva', 'administrator_pa', N'Administrator PA', 'bi bi-sliders', 11, 'T', 10, NULL, NULL, 'self', 'Y', NULL),
        ('PaMasterRelasiOrganisasiPage', '001', N'Master · Relasi Organisasi', 'people-fill', '/system/organisasi/relasi', 'administrator_pa', N'Administrator PA', 'bi bi-sliders', 11, 'T', 11, NULL, NULL, 'self', 'Y', N'Siapa atasan, bawahan, dan rekan kerja siapa. Sumber calon penilai Performance Appraisal.'),
        ('papemantauanpage', '001', N'Monitoring Cycle', 'bi bi-hourglass-split', '/performance-appraisal/pemantauan', 'administrator-pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 12, '/performance-appraisal/balancing*', NULL, 'self', 'Y', N'Pengisian, hitung & verifikasi, kurva, balancing, rilis & terbit'),
        ('PerformanceAppraisalPage', '001', N'Assessment Cycle', 'bi-grid-fill', '/performance-appraisal', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 12, NULL, NULL, 'self', 'Y', N'Kelola fase persiapan, rater assignment, pengisian, dan status cycle'),
        ('PaHavMapPage', '001', N'9-Box HAV Map', 'bi-grid-3x3', '/performance-appraisal/hav-map', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 14, NULL, NULL, 'self', 'Y', N'Matriks pemetaan talenta 3×3 (Kinerja KPI vs Kompetensi)'),
        ('PaRiwayatPage', '001', N'Riwayat Penilaian', 'bi-clock-history', '/performance-appraisal/riwayat', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 16, NULL, NULL, 'self', 'Y', N'Rekam jejak penilaian multi-tahun, tren kinerja, dan log audit'),
        ('PaExportStudioPage', '001', N'Export Studio', 'bi-sliders', '/performance-appraisal/export-studio', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 18, NULL, 'pa.export-studio', 'self', 'Y', N'Rakit dan unduh laporan Performance Appraisal sendiri'),
        ('PaMasterPaketSoalPage', '001', N'Master · Paket Soal', 'bi-journal-text', '/performance-appraisal/master/paket-soal', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 19, NULL, NULL, 'self', 'T', NULL),
        ('PaHasilPage', '001', N'Hasil & Penerbitan', 'bi-calculator-fill', '/performance-appraisal/hasil', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 20, NULL, NULL, 'self', 'T', N'Kalkulasi nilai akhir peserta dan penerbitan hasil evaluasi'),
        ('PaKalibrasiPage', '001', N'Balancing', 'bi-sliders', '/performance-appraisal/balancing', 'administrator_pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 21, NULL, 'performance-appraisal.balancing', 'self', 'T', N'Sesi forum penyeimbangan skor kompetensi sesudah Calculate'),
        ('padashboardpage', '001', N'Dash Hidden', 'bi bi-speedometer2', '/performance-appraisal/dashboard', 'administrator-pa', N'Administrator PA', 'bi bi-gear-fill', 11, 'T', 98, NULL, NULL, 'self', 'T', N'Pusat komando dan ringkasan eksekutif seluruh siklus'),
        ('padistribusipage', '001', N'Distribusi Hidden', 'bi bi-bar-chart-fill', '/performance-appraisal/distribusi', 'administrator-pa', N'Hidden Menus', 'bi bi-command', 11, 'T', 99, NULL, NULL, 'self', 'T', N'Analisis kurva normal per level jabatan dan sebaran hasil organisasi'),
        ('PaMasterAturanPenilaiPage', '001', N'Master · Komposisi Penilai', 'bi-people-fill', '/performance-appraisal/master/aturan-penilai', 'system_admin_pa', N'System Admin PA', 'bi bi-shield-lock-fill', 12, 'T', 1, NULL, NULL, 'self', 'Y', N'Kebijakan komposisi rater 360 multi-rater dan alokasi bobot'),
        ('systemRelasiOrganisasiPage', '001', N'Relasi Organisasi', 'bi bi-diagram-3-fill', '/system/organisasi/relasi', 'master_data', N'Master Data', 'bi bi-database-fill-gear', 2, 'T', 3, '/system/organisasi/relasi*', 'system.organisasi.relasi.page', 'self', 'Y', N'Siapa atasan, bawahan, dan rekan kerja siapa. Sumber calon penilai Performance Appraisal.')
    ) v (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Route_Name, Target, Is_Show_Sidebar, Menu_Subtitle)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Menus m WHERE m.Jenis_page = v.Jenis_page);

    /* 2. Peta grup fitur — 25 baris */
    INSERT INTO dbo.N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
    SELECT g.Id_Grup_Fitur, v.Jenis_page, v.Sort_Order, v.Is_Default_Landing, v.Is_Show_Sidebar, v.Flag_Aktif, v.Flag_Hibah_Role
    FROM (VALUES
        ('KPI', 'PerformanceAppraisalSayaPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PerformanceAppraisalLeaderPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterKategoriPenilaiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterLevelMasterPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterLevelPaPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterBobotPilarPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterJenisKompetensiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterKompetensiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterSyaratKompetensiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterIkatanPaketSoalPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterPitaNilaiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterGrupKurvaPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterRelasiOrganisasiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaPemantauanPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PerformanceAppraisalPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaHavMapPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaRiwayatPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaExportStudioPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterPaketSoalPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaHasilPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaKalibrasiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaDashboardPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaDistribusiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('KPI', 'PaMasterAturanPenilaiPage', NULL, 'T', 'Y', 'Y', 'Y'),
        ('SYSTEM', 'systemRelasiOrganisasiPage', NULL, 'T', 'Y', 'Y', 'Y')
    ) v (Kode_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
    JOIN dbo.N_HRIS_Master_Grup_Fitur g ON g.Kode_Grup_Fitur = v.Kode_Grup_Fitur
    WHERE NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map m WHERE m.Jenis_page = v.Jenis_page AND m.Id_Grup_Fitur = g.Id_Grup_Fitur);

    /* 3. Bagian menu (hanya bila DDL-2026-181 sudah dijalankan) */
    IF COL_LENGTH('dbo.N_HRIS_Menus', 'Section_Code') IS NOT NULL
        EXEC sp_executesql N'
UPDATE dbo.N_HRIS_Menus SET Section_Code = ''performance_appraisal'', Section_Label = ''Performance Appraisal'', Section_Icon = ''bi bi-clipboard2-check'', Sort_Section = 2 WHERE Jenis_page IN (''PerformanceAppraisalSayaPage'', ''PerformanceAppraisalLeaderPage'') AND Section_Code IS NULL;
UPDATE dbo.N_HRIS_Menus SET Section_Code = ''administrasi'', Section_Label = ''Administrasi'', Section_Icon = ''bi bi-gear-fill'', Sort_Section = 9 WHERE Jenis_page IN (''PaMasterKategoriPenilaiPage'', ''PaMasterLevelMasterPage'', ''PaMasterLevelPaPage'', ''PaMasterBobotPilarPage'', ''PaMasterJenisKompetensiPage'', ''PaMasterKompetensiPage'', ''PaMasterSyaratKompetensiPage'', ''PaMasterIkatanPaketSoalPage'', ''PaMasterPitaNilaiPage'', ''PaMasterGrupKurvaPage'', ''PaMasterRelasiOrganisasiPage'', ''papemantauanpage'', ''PerformanceAppraisalPage'', ''PaHavMapPage'', ''PaRiwayatPage'', ''PaExportStudioPage'', ''PaMasterPaketSoalPage'', ''PaHasilPage'', ''PaKalibrasiPage'', ''padashboardpage'', ''padistribusipage'', ''PaMasterAturanPenilaiPage'') AND Section_Code IS NULL;';

    /* 4. Jadwal perintah PA — 2 baris */
    INSERT INTO dbo.N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
    SELECT v.Command, v.Cron_Expression, v.Timezone, v.Flag_Aktif, v.Keterangan, GETDATE() FROM (VALUES
        ('pa:ingatkan-penilai', '45 1 * * *', 'Asia/Jakarta', 'Y', 'Pengingat in-app bagi penilai PA yang tenggatnya mendekat atau terlewat. Idempoten per hari.'),
        ('pa:sapu-tertahan', '20 7-19 * * *', 'Asia/Jakarta', 'Y', 'Hitung ulang hasil PA yang tertahan karena KPI-nya menyusul disetujui.')
    ) v (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Scheduler_Configs s WHERE s.Command = v.Command);

    /* 5. Registri endpoint callback HC Learn untuk PA (isi DML-2026-102; grup dicari lewat Kode_Grup_Fitur) */
    DECLARE @Url varchar(255) = '/api/v1/pa/third-party/penilaian/complete', @Method varchar(10) = 'POST';
    DECLARE @IdAppLms int = (SELECT TOP 1 Id_Api_Aplikasi FROM N_LMS_Api_Aplikasi WHERE Nama_Aplikasi = 'HUMAN CAPITAL LEARNING' AND Flag_Aktif = 'Y' ORDER BY Id_Api_Aplikasi);
    DECLARE @IdAppHris int = (SELECT TOP 1 Id_Api_Aplikasi FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = 'HC Learn' AND Flag_Aktif = 'Y' ORDER BY Id_Api_Aplikasi);
    DECLARE @IdGrupKpi int = (SELECT TOP 1 Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'KPI' ORDER BY Id_Grup_Fitur);

    IF NOT EXISTS (SELECT 1 FROM N_LMS_Endpoint_Api WHERE Url_Route = @Url AND Method = @Method)
        INSERT INTO N_LMS_Endpoint_Api (Url_Route, Method, Flag_Aktif) VALUES (@Url, @Method, 'Y');
    DECLARE @IdEpLms int = (SELECT TOP 1 Id_Endpoint_Api FROM N_LMS_Endpoint_Api WHERE Url_Route = @Url AND Method = @Method ORDER BY Id_Endpoint_Api);
    IF NOT EXISTS (SELECT 1 FROM N_LMS_Akses_Endpoint WHERE Id_Api_Aplikasi = @IdAppLms AND Id_Endpoint_Api = @IdEpLms)
        INSERT INTO N_LMS_Akses_Endpoint (Id_Api_Aplikasi, Id_Endpoint_Api, Flag_Aktif) VALUES (@IdAppLms, @IdEpLms, 'Y');

    IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Endpoint WHERE Url_Route = @Url AND Method = @Method)
        INSERT INTO N_HRIS_Api_Endpoint (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_By, Created_At, Flag_Aktif)
        VALUES (@IdGrupKpi, @Url, @Method, 'IN', N'Callback hasil penilaian 360 dari HC Learn — nilai per butir soal, bukan satu angka.', NULL, SYSDATETIME(), 'Y');
    DECLARE @IdEpHris int = (SELECT TOP 1 Id_Api_Endpoint FROM N_HRIS_Api_Endpoint WHERE Url_Route = @Url AND Method = @Method ORDER BY Id_Api_Endpoint);
    IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Akses_Endpoint WHERE Id_Api_Aplikasi = @IdAppHris AND Id_Api_Endpoint = @IdEpHris)
        INSERT INTO N_HRIS_Api_Akses_Endpoint (Id_Api_Aplikasi, Id_Api_Endpoint, Created_By, Created_At, Flag_Aktif) VALUES (@IdAppHris, @IdEpHris, NULL, SYSDATETIME(), 'Y');

    /* 6. Catatan di ledger production */
    IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-189')
        INSERT INTO N_SYSTEM_Database_Change (
            Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
            Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Catatan, Created_By
        ) VALUES (
            'DML-2026-189', 'DML', 'Menu, jadwal, dan endpoint callback Performance Appraisal untuk production',
            '25 butir menu PA beserta peta grup fiturnya, 2 jadwal perintah PA, dan registri endpoint callback HC Learn untuk PA. Tanpa hak akses per pengguna.',
            'database/dml/DML-2026-189-pa-menu-production/data-change.sql', 'docs/changes/DML-2026-189-pa-menu-production/README.md',
            'Web_HRIS', 'NOT_APPLICABLE', 'PENDING', 'PENDING', 'PENDING',
            'KPI/Performance Appraisal. Khusus production; bergantung DDL-2026-187 dan DML-2026-188.', NULL
        );

COMMIT TRANSACTION;

/* Verifikasi:
SELECT COUNT(*) FROM dbo.N_HRIS_Menus WHERE Jenis_page IN ('PerformanceAppraisalSayaPage', 'PerformanceAppraisalLeaderPage', 'PaMasterKategoriPenilaiPage', 'PaMasterLevelMasterPage', 'PaMasterLevelPaPage', 'PaMasterBobotPilarPage', 'PaMasterJenisKompetensiPage', 'PaMasterKompetensiPage', 'PaMasterSyaratKompetensiPage', 'PaMasterIkatanPaketSoalPage', 'PaMasterPitaNilaiPage', 'PaMasterGrupKurvaPage', 'PaMasterRelasiOrganisasiPage', 'papemantauanpage', 'PerformanceAppraisalPage', 'PaHavMapPage', 'PaRiwayatPage', 'PaExportStudioPage', 'PaMasterPaketSoalPage', 'PaHasilPage', 'PaKalibrasiPage', 'padashboardpage', 'padistribusipage', 'PaMasterAturanPenilaiPage', 'systemRelasiOrganisasiPage'); -- 25
SELECT g.Kode_Grup_Fitur, COUNT(*) FROM dbo.N_HRIS_Grup_Fitur_Map m JOIN dbo.N_HRIS_Master_Grup_Fitur g ON g.Id_Grup_Fitur = m.Id_Grup_Fitur
 WHERE m.Jenis_page IN ('PerformanceAppraisalSayaPage', 'PerformanceAppraisalLeaderPage', 'PaMasterKategoriPenilaiPage', 'PaMasterLevelMasterPage', 'PaMasterLevelPaPage', 'PaMasterBobotPilarPage', 'PaMasterJenisKompetensiPage', 'PaMasterKompetensiPage', 'PaMasterSyaratKompetensiPage', 'PaMasterIkatanPaketSoalPage', 'PaMasterPitaNilaiPage', 'PaMasterGrupKurvaPage', 'PaMasterRelasiOrganisasiPage', 'papemantauanpage', 'PerformanceAppraisalPage', 'PaHavMapPage', 'PaRiwayatPage', 'PaExportStudioPage', 'PaMasterPaketSoalPage', 'PaHasilPage', 'PaKalibrasiPage', 'padashboardpage', 'padistribusipage', 'PaMasterAturanPenilaiPage', 'systemRelasiOrganisasiPage') GROUP BY g.Kode_Grup_Fitur; -- KPI 24, SYSTEM 1
SELECT Command, Cron_Expression FROM dbo.N_HRIS_Scheduler_Configs WHERE Command LIKE 'pa:%'; -- 2
SELECT e.Url_Route, e.Flag_Aktif, a.Nama_Aplikasi, x.Flag_Aktif FROM N_LMS_Endpoint_Api e JOIN N_LMS_Akses_Endpoint x ON x.Id_Endpoint_Api = e.Id_Endpoint_Api
  JOIN N_LMS_Api_Aplikasi a ON a.Id_Api_Aplikasi = x.Id_Api_Aplikasi WHERE e.Url_Route = '/api/v1/pa/third-party/penilaian/complete'; -- 1 baris
SELECT e.Url_Route, e.Flag_Aktif, a.Nama_Aplikasi, x.Flag_Aktif FROM N_HRIS_Api_Endpoint e JOIN N_HRIS_Api_Akses_Endpoint x ON x.Id_Api_Endpoint = e.Id_Api_Endpoint
  JOIN N_HRIS_Api_Aplikasi a ON a.Id_Api_Aplikasi = x.Id_Api_Aplikasi WHERE e.Url_Route = '/api/v1/pa/third-party/penilaian/complete'; -- 1 baris
*/
