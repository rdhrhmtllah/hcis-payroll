/* Change ID: DDL-2026-027-index-visitor-kehadiran */

/*
    Pendaftaran changeset ke Database Change Dashboard.

    Perubahan skemanya murni indeks; tidak ada satu baris data pun yang disentuh.
    Berkas ini hanya mencatat keberadaan changeset agar terlacak di dashboard,
    sama seperti changeset DDL lain di repo ini.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-027-index-visitor-kehadiran')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-027-index-visitor-kehadiran', 'DDL', 'Index for Visitor presence and validity reads',
     'Lima indeks nonclustered untuk pola baca kehadiran tamu (efektif + detail), tanggal kunjungan, dan masa berlaku permit. Tidak menyentuh data.',
     'database/ddl/DDL-2026-027-index-visitor-kehadiran/forward.sql',
     'docs/changes/DDL-2026-027-index-visitor-kehadiran/README.md',
     'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-kehadiran-index');
END;

COMMIT TRANSACTION;
