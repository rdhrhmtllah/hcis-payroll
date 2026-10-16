/* Change ID: DML-2026-044-register-dms-module-and-menus
   Target: Web_HRIS development only.
   Purpose: Registrasi modul mandiri DMS (Document Management System),
            pemindahan 13 menu SOP dari modul LMS ke modul DMS,
            pengelompokan 2 header: DMS dan Administrator DMS,
            serta standarisasi penamaan SOP -> Document / Dokumen.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

-- 1. Pastikan Master Grup Fitur DMS tersedia di N_HRIS_Master_Grup_Fitur
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'DMS')
BEGIN
    INSERT INTO N_HRIS_Master_Grup_Fitur
    (
        Kode_Grup_Fitur,
        Nama_Grup_Fitur,
        Icon_Grup_Fitur,
        Subtitle_Grup_Fitur,
        Sort_Order,
        Landing_Jenis_Page,
        Is_Show_Sidebar,
        Flag_Aktif
    )
    VALUES
    (
        'DMS',
        'Document Management System',
        'bi bi-folder2-open',
        'Document Management System',
        5,
        'menuSopSayaPage',
        'Y',
        'Y'
    );
END
ELSE
BEGIN
    UPDATE N_HRIS_Master_Grup_Fitur
    SET Nama_Grup_Fitur = 'Document Management System',
        Icon_Grup_Fitur = 'bi bi-folder2-open',
        Subtitle_Grup_Fitur = 'Document Management System',
        Sort_Order = 5,
        Landing_Jenis_Page = 'menuSopSayaPage',
        Is_Show_Sidebar = 'Y',
        Flag_Aktif = 'Y'
    WHERE Kode_Grup_Fitur = 'DMS';
END;

DECLARE @IdGrupFiturDMS int = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'DMS');
IF @IdGrupFiturDMS IS NULL THROW 51001, 'DMS module not found.', 1;

-- 2. Update Header, Nama Menu, Subtitle, dan Sort pada N_HRIS_Menus
-- Header 1: DMS
UPDATE N_HRIS_Menus
SET Nama_Menu = 'Functional Document',
    Header_Code = 'dms',
    Header_Label = 'DMS',
    Header_Icon = 'bi bi-folder2-open',
    Sort_Header = 1,
    Sort_Menu = 1,
    Is_Global_Menu = 'Y',
    Menu_Subtitle = 'Dokumen fungsional & SOP penugasan saya'
WHERE Jenis_page = 'menuSopSayaPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Public Document',
    Header_Code = 'dms',
    Header_Label = 'DMS',
    Header_Icon = 'bi bi-folder2-open',
    Sort_Header = 1,
    Sort_Menu = 2,
    Is_Global_Menu = 'Y',
    Menu_Subtitle = 'Repositori seluruh dokumen publik'
WHERE Jenis_page = 'menuSopRepositoryPage';

-- Header 2: Administrator DMS
UPDATE N_HRIS_Menus
SET Nama_Menu = 'Kategori Dokumen',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 1,
    Menu_Subtitle = 'Struktur taksonomi & kelompok dokumen'
WHERE Jenis_page = 'menuSopKategoriPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Jenis Dokumen',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 2,
    Menu_Subtitle = 'Hirarki mutu: Kebijakan sampai Formulir'
WHERE Jenis_page = 'menuSopJenisDokumenPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Master Dokumen',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 3,
    Menu_Subtitle = 'Deklarasi & manajemen siklus dokumen'
WHERE Jenis_page = 'menuSopDokumenPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Master APQC',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 4,
    Menu_Subtitle = 'Klasifikasi proses bisnis dokumen'
WHERE Jenis_page = 'menuSopApqcPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Document Builder',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 5,
    Menu_Subtitle = 'Rancang & terbitkan dokumen'
WHERE Jenis_page = 'menuSopBuilderPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Penjadwalan Dokumen',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 6,
    Menu_Subtitle = 'Jadwal tinjauan & penugasan'
WHERE Jenis_page = 'menuSopPenjadwalanPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Monitoring Dokumen',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 7,
    Menu_Subtitle = 'Pemantauan kepatuhan dokumen'
WHERE Jenis_page = 'menuSopMonitoringPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Otomasi Dokumen',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 8,
    Menu_Subtitle = 'Aturan penugasan & riwayat eksekusi'
WHERE Jenis_page = 'menuSopKebijakanPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Library Konten',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 9,
    Menu_Subtitle = 'Penyimpanan aset & lampiran dokumen'
WHERE Jenis_page = 'menuSopKontenPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Export Studio',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 10,
    Menu_Subtitle = 'Rakit laporan dokumen kustom'
WHERE Jenis_page = 'menuSopExportStudioPage';

UPDATE N_HRIS_Menus
SET Nama_Menu = 'Kebijakan Global Dokumen',
    Header_Code = 'administrator_dms',
    Header_Label = 'Administrator DMS',
    Header_Icon = 'bi bi-gear-fill',
    Sort_Header = 2,
    Sort_Menu = 11,
    Menu_Subtitle = 'Guardrail kepatuhan & jadwal otomasi DMS'
WHERE Jenis_page = 'menuSopSetupSystemPage';

-- 3. Pindahkan mapping halaman di N_HRIS_Grup_Fitur_Map ke modul DMS
UPDATE N_HRIS_Grup_Fitur_Map
SET Id_Grup_Fitur = @IdGrupFiturDMS,
    Sort_Order = CASE Jenis_page
        WHEN 'menuSopSayaPage' THEN 1
        WHEN 'menuSopRepositoryPage' THEN 2
        WHEN 'menuSopKategoriPage' THEN 3
        WHEN 'menuSopJenisDokumenPage' THEN 4
        WHEN 'menuSopDokumenPage' THEN 5
        WHEN 'menuSopApqcPage' THEN 6
        WHEN 'menuSopBuilderPage' THEN 7
        WHEN 'menuSopPenjadwalanPage' THEN 8
        WHEN 'menuSopMonitoringPage' THEN 9
        WHEN 'menuSopKebijakanPage' THEN 10
        WHEN 'menuSopKontenPage' THEN 11
        WHEN 'menuSopExportStudioPage' THEN 12
        WHEN 'menuSopSetupSystemPage' THEN 13
        ELSE Sort_Order
    END,
    Is_Default_Landing = CASE WHEN Jenis_page = 'menuSopSayaPage' THEN 'Y' ELSE 'T' END,
    Is_Show_Sidebar = 'Y',
    Flag_Aktif = 'Y'
WHERE Jenis_page IN (
    'menuSopSayaPage',
    'menuSopRepositoryPage',
    'menuSopKategoriPage',
    'menuSopJenisDokumenPage',
    'menuSopDokumenPage',
    'menuSopApqcPage',
    'menuSopBuilderPage',
    'menuSopPenjadwalanPage',
    'menuSopMonitoringPage',
    'menuSopKebijakanPage',
    'menuSopKontenPage',
    'menuSopExportStudioPage',
    'menuSopSetupSystemPage'
);

-- 4. Daftarkan Functional Role untuk modul DMS di N_HRIS_Role_Functional jika belum ada
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Role_Functional WHERE Role_Code = 'DMS_MENU_ADMINISTRATOR')
BEGIN
    INSERT INTO N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
    VALUES ('DMS_MENU_ADMINISTRATOR', 'DMS Menu Administrator', 'Administrator menu untuk Document Management System', 'Y', SYSDATETIME(), 'SYSTEM', @IdGrupFiturDMS);
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Role_Functional WHERE Role_Code = 'DMS_APPROVAL_ADMINISTRATOR')
BEGIN
    INSERT INTO N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
    VALUES ('DMS_APPROVAL_ADMINISTRATOR', 'DMS Approval Administrator', 'Administrator approval matrix untuk Document Management System', 'Y', SYSDATETIME(), 'SYSTEM', @IdGrupFiturDMS);
END;

-- 5. Daftarkan perubahan ke N_SYSTEM_Database_Change
IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-044-register-dms-module-and-menus'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (
        Kode_Change,
        Jenis_Change,
        Judul_Change,
        Deskripsi_Change,
        Artifact_Path,
        Documentation_Path,
        Target_Development,
        Status_Development,
        Status_SQL_Tracker,
        Status_Production,
        Status_Verification,
        Created_By
    )
    VALUES
    (
        'DML-2026-044-register-dms-module-and-menus',
        'DML',
        'Registrasi modul DMS dan pemindahan menu dokumen SOP dari LMS',
        'Mendaftarkan modul mandiri DMS di N_HRIS_Master_Grup_Fitur, memindahkan 13 menu SOP dari LMS ke DMS di N_HRIS_Grup_Fitur_Map, mengelompokkan 2 header (DMS dan Administrator DMS) di N_HRIS_Menus, serta standarisasi penamaan SOP ke Document/Dokumen.',
        'database/dml/DML-2026-044-register-dms-module-and-menus/data-change.sql',
        'docs/changes/DML-2026-044-register-dms-module-and-menus/README.md',
        'Web_HRIS',
        'DONE',
        'PENDING',
        'PENDING',
        'DEV_DONE',
        'dms-module-registration'
    );
END;

COMMIT TRANSACTION;
