/* Change ID: DDL-2026-192-absen-snapshot-indeks-tanggal
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: N_HRIS_Absen_Snapshot.
   Scope: indeks diawali tanggal untuk Monitoring Kehadiran (ESS). Semua indeks snapshot
          yang ada diawali karyawan/user, sehingga kueri "semua karyawan pada tanggal X /
          rentang X..Y" memindai seluruh tabel (production 14 Okt 2026: 1,03 juta baris, 332 MB).
   Catatan production: SQL Server Express (tanpa ONLINE = ON). Pembuatan indeks menahan
          penulisan ke tabel ini beberapa detik; jalankan di luar jam pemrosesan absensi.
          Perkiraan ukuran ±100 MB; basis data terpakai 4,6 GB dari batas Express 10 GB.
   Dokumentasi: docs/changes/DDL-2026-192-absen-snapshot-indeks-tanggal/README.md
*/

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
     WHERE object_id = OBJECT_ID('dbo.N_HRIS_Absen_Snapshot')
       AND name = 'IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal
        ON dbo.N_HRIS_Absen_Snapshot (Kode_Perusahaan, Tanggal)
        INCLUDE (Kode_Karyawan, StatusHari, Nama_Shift, Jam_Masuk, CheckIn, CheckOut,
                 RealLate, RealEarly, Status_Kehadiran, Flag_Alpha, Flag_Lembur, Koefisien_OT,
                 Flag_Konversi_Lembur, Flag_Generate_Ulang, Last_Calculated_At,
                 No_Transaksi_Cuti, No_Transaksi_Izin, No_Transaksi_Sakit,
                 No_Transaksi_Terlambat, No_Transaksi_PulangCepat);
END;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-192')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-192',
        'DDL',
        'Indeks tanggal N_HRIS_Absen_Snapshot untuk Monitoring Kehadiran',
        'Indeks IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal (Kode_Perusahaan, Tanggal) dengan kolom INCLUDE yang dibaca tab Hari Ini dan Rekap Periode, agar pembacaan per tanggal/rentang tidak memindai seluruh tabel.',
        'database/ddl/DDL-2026-192-absen-snapshot-indeks-tanggal/forward.sql',
        'docs/changes/DDL-2026-192-absen-snapshot-indeks-tanggal/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'ESS/Attendance/Monitoring. Production: SQL Server Express, jalankan di luar jam pemrosesan absensi.',
        NULL
    );
END;
GO
