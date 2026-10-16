/* Change ID: DDL-2026-068-performance-appraisal-hav-quadrant
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: insert kuadran <= 9 dan register the change <= 1.
   Warna sengaja NULL: nilai hex dari mockup PIC belum tersedia. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50154, 'DDL-2026-068 DML hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Hav_Quadrant', 'U') IS NULL
    THROW 50155, 'DDL-2026-068 DML membutuhkan forward.sql yang sudah berhasil.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'STRIVING_STAR')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('STRIVING_STAR', N'Striving Star', 1, 'TINGGI', 'TINGGI', N'Performa dan kompetensi sama-sama tinggi.', 1, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'FUTURE_STAR_A')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('FUTURE_STAR_A', N'Future Star', 2, 'TINGGI', 'SEDANG', N'Performa tinggi dengan kompetensi sedang.', 2, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'FUTURE_STAR_B')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('FUTURE_STAR_B', N'Future Star', 3, 'SEDANG', 'TINGGI', N'Kompetensi tinggi dengan performa sedang.', 3, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'POTENTIAL_CANDIDATE')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('POTENTIAL_CANDIDATE', N'Potential Candidate', 4, 'SEDANG', 'SEDANG', N'Performa dan kompetensi sama-sama sedang.', 4, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'TOP_PERFORMER')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('TOP_PERFORMER', N'Top Performer', 5, 'TINGGI', 'RENDAH', N'Performa tinggi tetapi kompetensi rendah.', 5, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'CONTRIBUTOR')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('CONTRIBUTOR', N'Contributor', 6, 'SEDANG', 'RENDAH', N'Performa sedang dengan kompetensi rendah.', 6, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'RAW_DIAMOND')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('RAW_DIAMOND', N'Raw Diamond', 7, 'RENDAH', 'TINGGI', N'Kompetensi tinggi tetapi performa rendah.', 7, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'PROBLEM_EMPLOYEE')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('PROBLEM_EMPLOYEE', N'Problem Employee', 8, 'RENDAH', 'SEDANG', N'Performa rendah dengan kompetensi sedang.', 8, 'Y', NULL);
END;

IF NOT EXISTS (SELECT 1 FROM N_PA_Hav_Quadrant WHERE Kode_Kuadran = 'DEADWOOD')
BEGIN
    INSERT INTO N_PA_Hav_Quadrant
        (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi,
         Deskripsi, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES
        ('DEADWOOD', N'Deadwood', 9, 'RENDAH', 'RENDAH', N'Performa dan kompetensi sama-sama rendah.', 9, 'Y', NULL);
END;

IF (SELECT COUNT(*) FROM N_PA_Hav_Quadrant WHERE Flag_Aktif = 'Y') <> 9
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50156, 'DDL-2026-068: sembilan kuadran aktif tidak lengkap.', 1;
END;

IF (
    SELECT COUNT(DISTINCT Tingkat_Performa + '|' + Tingkat_Kompetensi)
    FROM N_PA_Hav_Quadrant WHERE Flag_Aktif = 'Y'
) <> 9
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50157, 'DDL-2026-068: kombinasi tingkat tidak menutup seluruh 3x3.', 1;
END;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DDL-2026-068-performance-appraisal-hav-quadrant'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-068-performance-appraisal-hav-quadrant',
         'DDL',
         'Kamus sembilan kuadran HAV Map',
         'Membuat N_PA_Hav_Quadrant dan seed sembilan kotak dari deck PIC, menutup seluruh kombinasi tiga tingkat performa kali tiga tingkat kompetensi.',
         'database/ddl/DDL-2026-068-performance-appraisal-hav-quadrant/forward.sql',
         'docs/changes/DDL-2026-068-performance-appraisal-hav-quadrant/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Berdiri sendiri. Kamus, tidak disnapshot per siklus (K26). Tingkat_Performa/Tingkat_Kompetensi merujuk Kode_Range pada N_PA_Score_Range untuk Score_Usage HAVMAP, tanpa FK karena daftar tingkat masih bisa berkembang.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO