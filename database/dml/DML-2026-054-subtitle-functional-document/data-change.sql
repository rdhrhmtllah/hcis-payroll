/* Change ID: DML-2026-054-subtitle-functional-document
   Target: Web_HRIS development only.
   Purpose: Membereskan satu subtitle menu yang tertinggal saat standardisasi
            kosakata SOP -> Document/Dokumen pada DML-2026-044.

            Seluruh Nama_Menu dan dua belas Menu_Subtitle lain sudah memakai
            kosakata baru. Hanya menu Functional Document yang subtitle-nya
            masih berbunyi "Dokumen fungsional & SOP penugasan saya", sehingga
            sidebar menyebut dua kosakata sekaligus pada satu baris yang sama.

            DML-2026-044 sudah DEV_DONE dan checksum-nya terkunci, jadi
            pembetulan dilakukan lewat perubahan terpisah ini, bukan dengan
            menyunting artefak lama.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @IdGrupFiturDMS int = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'DMS');
IF @IdGrupFiturDMS IS NULL THROW 51001, 'DMS module not found.', 1;

-- 1. Perbaiki subtitle menu Functional Document
--    Lingkup: tepat 1 baris pada N_HRIS_Menus (Jenis_page unik per menu).
UPDATE N_HRIS_Menus
SET Menu_Subtitle = 'Dokumen fungsional & penugasan saya'
WHERE Jenis_page = 'menuSopSayaPage';

IF @@ROWCOUNT <> 1
    THROW 50054, 'Menu Functional Document tidak ditemukan atau ganda - perubahan dibatalkan.', 1;

-- 2. Daftarkan perubahan ke N_SYSTEM_Database_Change
IF NOT EXISTS (
    SELECT 1
    FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-054-subtitle-functional-document'
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
        'DML-2026-054-subtitle-functional-document',
        'DML',
        'Subtitle menu Functional Document ikut memakai kosakata DMS',
        'Mengganti Menu_Subtitle menu Functional Document dari "Dokumen fungsional & SOP penugasan saya" menjadi "Dokumen fungsional & penugasan saya". Satu-satunya label menu yang masih menyebut SOP setelah DML-2026-044.',
        'database/dml/DML-2026-054-subtitle-functional-document/data-change.sql',
        'docs/changes/DML-2026-054-subtitle-functional-document/README.md',
        'Web_HRIS',
        'PENDING',
        'PENDING',
        'PENDING',
        'PENDING',
        'dms-vocabulary-alignment'
    );
END;

COMMIT TRANSACTION;
