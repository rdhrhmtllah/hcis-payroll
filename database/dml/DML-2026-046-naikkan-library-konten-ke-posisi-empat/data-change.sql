/* Change ID: DML-2026-046-naikkan-library-konten-ke-posisi-empat
   Target: Web_HRIS development only.
   Purpose: Memindahkan menu Library Konten menjadi urutan ke-4 pada Administrator DMS
            (setelah Master APQC dan sebelum Master Dokumen & Document Builder),
            sehingga bahan/aset konten disiapkan terlebih dahulu sebelum perakitan dokumen.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @IdGrupFiturDMS int = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'DMS');
IF @IdGrupFiturDMS IS NULL THROW 51001, 'DMS module not found.', 1;

-- 1. Perbarui Sort_Menu pada N_HRIS_Menus
-- Header 2: Administrator DMS
UPDATE N_HRIS_Menus SET Sort_Menu = 1 WHERE Jenis_page = 'menuSopJenisDokumenPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 2 WHERE Jenis_page = 'menuSopKategoriPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 3 WHERE Jenis_page = 'menuSopApqcPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 4 WHERE Jenis_page = 'menuSopKontenPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 5 WHERE Jenis_page = 'menuSopDokumenPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 6 WHERE Jenis_page = 'menuSopBuilderPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 7 WHERE Jenis_page = 'menuSopPenjadwalanPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 8 WHERE Jenis_page = 'menuSopKebijakanPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 9 WHERE Jenis_page = 'menuSopMonitoringPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 10 WHERE Jenis_page = 'menuSopExportStudioPage';
UPDATE N_HRIS_Menus SET Sort_Menu = 11 WHERE Jenis_page = 'menuSopSetupSystemPage';

-- 2. Perbarui Sort_Order pada N_HRIS_Grup_Fitur_Map
UPDATE N_HRIS_Grup_Fitur_Map
SET Sort_Order = CASE Jenis_page
        WHEN 'menuSopSayaPage' THEN 1
        WHEN 'menuSopRepositoryPage' THEN 2
        WHEN 'menuSopJenisDokumenPage' THEN 3
        WHEN 'menuSopKategoriPage' THEN 4
        WHEN 'menuSopApqcPage' THEN 5
        WHEN 'menuSopKontenPage' THEN 6
        WHEN 'menuSopDokumenPage' THEN 7
        WHEN 'menuSopBuilderPage' THEN 8
        WHEN 'menuSopPenjadwalanPage' THEN 9
        WHEN 'menuSopKebijakanPage' THEN 10
        WHEN 'menuSopMonitoringPage' THEN 11
        WHEN 'menuSopExportStudioPage' THEN 12
        WHEN 'menuSopSetupSystemPage' THEN 13
        ELSE Sort_Order
    END
WHERE Id_Grup_Fitur = @IdGrupFiturDMS;

-- 3. Daftarkan perubahan ke N_SYSTEM_Database_Change
IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-046-naikkan-library-konten-ke-posisi-empat'
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
        'DML-2026-046-naikkan-library-konten-ke-posisi-empat',
        'DML',
        'Penyesuaian posisi menu Library Konten sebelum Master Dokumen dan Builder',
        'Menyesuaikan urutan menu agar aset dan blok isi di Library Konten disiapkan terlebih dahulu (urutan ke-4) sebelum Master Dokumen (ke-5) dan Document Builder (ke-6).',
        'database/dml/DML-2026-046-naikkan-library-konten-ke-posisi-empat/data-change.sql',
        'docs/changes/DML-2026-046-naikkan-library-konten-ke-posisi-empat/README.md',
        'Web_HRIS',
        'DONE',
        'PENDING',
        'PENDING',
        'DEV_DONE',
        'dms-menu-ordering'
    );
END;

COMMIT TRANSACTION;
