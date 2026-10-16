/* Change ID: DML-2026-137-visitor-gabung-jadwal-monitoring */
/*
    Jadwal Kedatangan Tamu dilebur ke Monitoring Tamu.

    Dua menu menjawab pertanyaan yang sama dengan dua mekanisme izin berbeda -
    Jadwal lewat matriks departemen, Monitoring lewat kunci halaman per orang -
    dan pemakai tidak punya cara menebak mana yang harus dibuka.

    1. Menu Jadwal disembunyikan dari sidebar. TIDAK dihapus: baris menu juga
       menjadi tempat bergantung hibah grup dan riwayat akses, dan
       menghapusnya membuat pemulihan berarti membangun ulang, bukan
       menyalakan kembali. Halamannya sendiri dialihkan 301 ke Monitoring.

    2. Monitoring Tamu dihibahkan lewat ROLE, seperti Jadwal selama ini.
       Isinya disaring matriks di controller, jadi hibah menu yang lebih luas
       TIDAK memperluas data yang terlihat - hanya menu yang bisa dibuka.

    Idempoten: dijalankan ulang menyentuh 0 baris.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Sembunyikan menu Jadwal ──────────────────────────────────────── */
UPDATE N_HRIS_Menus
   SET Is_Show_Sidebar = 'T'
 WHERE Jenis_page = 'visitorJadwalTamuPage'
   AND ISNULL(Is_Show_Sidebar, 'T') <> 'T';

/* ── 2. Monitoring Tamu dihibahkan lewat role ────────────────────────── */
UPDATE N_HRIS_Grup_Fitur_Map
   SET Flag_Hibah_Role = 'Y'
 WHERE Jenis_page = 'visitorMonitoringTamuPage'
   AND Flag_Aktif = 'Y'
   AND ISNULL(Flag_Hibah_Role, 'T') <> 'Y';

/* ── 3. Database Change Dashboard ────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-137-visitor-gabung-jadwal-monitoring')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-137-visitor-gabung-jadwal-monitoring', 'DML', 'Gabungkan Jadwal Kedatangan Tamu ke Monitoring Tamu', 'Menu Jadwal disembunyikan (halaman dialihkan 301), dan Monitoring Tamu dihibahkan lewat role dengan isi disaring matriks.', 'database/dml/DML-2026-137-visitor-gabung-jadwal-monitoring/data-change.sql', 'docs/changes/DML-2026-137-visitor-gabung-jadwal-monitoring/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-gabung-jadwal');
END;

COMMIT TRANSACTION;

/* Verifikasi. */
/*
SELECT Jenis_page, Is_Show_Sidebar FROM N_HRIS_Menus
 WHERE Jenis_page IN ('visitorJadwalTamuPage','visitorMonitoringTamuPage');

SELECT Jenis_page, Flag_Hibah_Role FROM N_HRIS_Grup_Fitur_Map
 WHERE Jenis_page IN ('visitorJadwalTamuPage','visitorMonitoringTamuPage') AND Flag_Aktif = 'Y';
*/
