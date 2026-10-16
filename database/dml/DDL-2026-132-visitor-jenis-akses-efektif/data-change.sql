/* Change ID: DDL-2026-132-visitor-jenis-akses-efektif */
/*
    Wajib SESUDAH forward.sql.

    Baris akses sementara dikenali dari penanda teks yang dipakai sampai
    sekarang, lalu ditandai lewat kolomnya. Sesudah ini tidak ada lagi
    pembaca yang perlu mencocokkan kalimat.

    Idempoten: dijalankan ulang menyentuh 0 baris.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE N_VISITOR_Permit_Efektif
   SET Jenis_Akses = 'INSPEKSI',
       Updated_At  = GETDATE()
 WHERE Jenis_Akses <> 'INSPEKSI'
   AND (Nama_Durasi = 'Akses Inspeksi'
     OR Nama_Kategori_Permit = 'AKSES INSPEKSI - BELUM DISETUJUI');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-132-visitor-jenis-akses-efektif')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-132-visitor-jenis-akses-efektif', 'DDL', 'Kolom Jenis_Akses pada proyeksi gerbang permit', 'Baris Permit_Efektif tidak lagi berarti sudah disetujui; Jenis_Akses (PERMIT|INSPEKSI) menggantikan pembeda berbasis teks di Nama_Durasi.', 'database/ddl/DDL-2026-132-visitor-jenis-akses-efektif/forward.sql', 'docs/changes/DDL-2026-132-visitor-jenis-akses-efektif/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-jenis-akses');
END;

COMMIT TRANSACTION;

/* Verifikasi: harus 0 — tak ada baris bertanda teks yang belum berkolom. */
/*
SELECT COUNT(*) FROM N_VISITOR_Permit_Efektif
 WHERE Jenis_Akses <> 'INSPEKSI'
   AND (Nama_Durasi = 'Akses Inspeksi' OR Nama_Kategori_Permit = 'AKSES INSPEKSI - BELUM DISETUJUI');
*/
