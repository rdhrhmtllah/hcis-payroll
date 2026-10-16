/* Change ID: DDL-2026-126-visitor-tamu-efektif-lengkap */
/*
    Wajib dijalankan SETELAH forward.sql DDL-2026-121, DDL-2026-122, dan
    DDL-2026-126.

    1. Isi kolom baru di N_VISITOR_Tamu_Efektif dari Detail kunjungannya,
       untuk SEMUA baris (status apa pun): kolom ini keterangan kunjungan,
       bukan keadaan palang. Kolom waktu aktual & status tidak disentuh.
    2. Pendaftaran ke Database Change Dashboard.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Isi ulang dari Detail ───────────────────────────────────────── */
UPDATE N_VISITOR_Tamu_Efektif
   SET Waktu_Estimasi_Masuk = d.Waktu_Mulai_Kunjungan,
       Waktu_Estimasi_Keluar = d.Waktu_Selesai_Kunjungan,
       Tamu_Kategori = d.Tamu_Kategori,
       B2B_Unit = d.B2B_Unit,
       Path_Foto_Kendaraan = d.Path_Foto_Kendaraan
  FROM N_VISITOR_Tamu_Efektif
  JOIN N_EMI_VISITOR_Transaksi_Tamu_Detail d
    ON d.No_Transaksi = N_VISITOR_Tamu_Efektif.No_Transaksi;

/* ── 2. Database Change Dashboard ────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-126-visitor-tamu-efektif-lengkap')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-126-visitor-tamu-efektif-lengkap', 'DDL', 'Proyeksi tamu efektif lengkap', 'N_VISITOR_Tamu_Efektif membawa estimasi masuk/keluar, kategori VISITOR/MITRA, unit B2B, dan foto kendaraan.', 'database/ddl/DDL-2026-126-visitor-tamu-efektif-lengkap/forward.sql', 'docs/changes/DDL-2026-126-visitor-tamu-efektif-lengkap/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-tamu-efektif');
END;

COMMIT TRANSACTION;
