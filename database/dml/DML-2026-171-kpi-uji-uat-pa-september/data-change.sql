/* Change ID: DML-2026-171-kpi-uji-uat-pa-september
   Target: Web_HRIS DEVELOPMENT SAJA.

   *** JANGAN DIREKAM KE SQL TRACKER, DAN JANGAN DIJALANKAN DI PRODUCTION. ***

   DATA UJI untuk UAT Performance Appraisal 8 Oktober 2026 (periode dinilai
   2026-01-01 s.d. 2026-09-30). Tidak mengarang KPI baru: 28 karyawan sungguhan di dev sudah
   punya KPI 2026 disetujui Jan-Sep. Yang dilakukan berkas ini hanya menyiapkan panggung demo
   "isi KPI September" dari akun Ridho (H45):

     Lengkap s.d. September (TIDAK disentuh): A4 Aji, J31 Imam, K32 Iqbal, E14 Dhea, F18 Erick.
     Belum September (disiapkan di sini)    : E15 Dimas, W60 Satria, R51 Natasha,
                                              F40 Fernando, I28 Hendra.

   Untuk lima orang "belum September":
     1. Realisasi September yang aktif DINONAKTIFKAN (Flag_Aktif 'T' + alasan pembatalan),
        tidak dihapus. Jan-Agu tetap disetujui, target September tetap ada.
     2. Jadwal Update Performance PERORANGAN periode 2026-09, aktif 8-31 Okt 2026, hanya
        untuk mereka (bentuk baris sama dengan PenjadwalanKpiController::store).
     3. Masuk roster tim KPI Ridho (KPI_Karyawan_Team, leader H45) -> menu Update
        Performance Team.
     4. Approver langkah 1 = H45 (KPI_Approval_Flow_Performance). Langkah 2 (ROLE_KPI_VERIF)
        sudah dipegang H45.

   Scope: KPI_Master_Penjadwalan (+1), N_KPI_Master_Penjadwalan_Perorangan (+5),
          KPI_Update_Performance (5 baris dinonaktifkan), KPI_Karyawan_Team (+<=5),
          KPI_Approval_Flow_Performance (+<=5).

   IDEMPOTEN: setiap langkah dijaga NOT EXISTS / kondisi aktif, jadi aman dijalankan ulang.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50710, 'DML-2026-171 adalah data uji dan hanya untuk Web_HRIS pengembangan.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @orang TABLE (Kode varchar(50) PRIMARY KEY);
INSERT INTO @orang (Kode) VALUES ('E15'), ('W60'), ('R51'), ('F40'), ('I28');

DECLARE @namaJadwal varchar(200) = 'UAT PA - Update Performance September 2026';
DECLARE @alasan varchar(300) = 'Data uji UAT PA 8 Okt 2026: realisasi September dikosongkan untuk demo pengisian.';

IF (SELECT COUNT(*) FROM Karyawan k JOIN @orang o ON o.Kode = k.Kode_Karyawan
    WHERE k.Aktif = 'Y' AND k.UserID_Web IS NOT NULL) <> 5
    THROW 50711, 'Salah satu dari lima karyawan tidak aktif atau tidak punya akun.', 1;

BEGIN TRANSACTION;

/* 1. Jadwal perorangan Update Performance September ------------------------------- */
IF NOT EXISTS (
    SELECT 1 FROM KPI_Master_Penjadwalan
    WHERE Nama_Kegiatan = @namaJadwal AND Tahun = '2026' AND Periode = '2026-09' AND Flag_Arsip = 'T'
)
    INSERT INTO KPI_Master_Penjadwalan (
        Nama_Kegiatan, Deskripsi, Jadwal_Mulai, Jadwal_Akhir, Tahun, Periode,
        Flag_Perorangan, Id_User, Jam, Tanggal,
        Flag_Form_KPI, Flag_Update_KPI, Flag_Penilaian_KPI, Flag_Batas_Verifikasi,
        Flag_Aktif, Flag_Arsip
    )
    VALUES (
        @namaJadwal, 'Jadwal uji UAT Performance Appraisal: pengisian realisasi September 2026.',
        '2026-10-08 00:00:00', '2026-10-31 23:59:59', '2026', '2026-09',
        'Y', 4, CONVERT(varchar(8), GETDATE(), 108), CAST(GETDATE() AS date),
        NULL, NULL, 'Y', NULL,
        'Y', 'T'
    );

DECLARE @jadwal int = (
    SELECT MIN(Id_Master_Penjadwalan) FROM KPI_Master_Penjadwalan
    WHERE Nama_Kegiatan = @namaJadwal AND Tahun = '2026' AND Periode = '2026-09' AND Flag_Arsip = 'T'
);

INSERT INTO N_KPI_Master_Penjadwalan_Perorangan (Id_Master_Penjadwalan, Kode_Karyawan, Keterangan, Start_Periode, Flag_Aktif)
SELECT @jadwal, o.Kode, @alasan, NULL, 'Y'
FROM @orang o
WHERE NOT EXISTS (
    SELECT 1 FROM N_KPI_Master_Penjadwalan_Perorangan x
    WHERE x.Id_Master_Penjadwalan = @jadwal AND x.Kode_Karyawan = o.Kode
);

/* 2. Nonaktifkan realisasi September yang aktif ------------------------------------ */
UPDATE up SET
    Flag_Aktif = 'T',
    Cancel_Reason = @alasan,
    Cancelled_By = 'DML-2026-171',
    Cancelled_By_Nama = 'Data uji UAT PA',
    Cancelled_At = GETDATE()
FROM KPI_Update_Performance up
JOIN KPI_Pengajuan p ON p.Id_Pengajuan = up.Id_Pengajuan
JOIN KPI_Master_Penjadwalan mp ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan AND mp.Tahun = '2026'
JOIN Karyawan k ON k.UserID_Web = p.Id_User
JOIN @orang o ON o.Kode = k.Kode_Karyawan
WHERE up.Periode = '2026-09' AND up.Flag_Aktif = 'Y'
  AND p.Flag_Aktif = 'Y' AND p.Flag_Approval = 'Y' AND p.Status IS NULL;

/* 3. Roster tim KPI Ridho ----------------------------------------------------------- */
INSERT INTO KPI_Karyawan_Team (Kode_Karyawan_Leader, Kode_Karyawan_Team, create_at, Id_User, Flag_Aktif)
SELECT 'H45', o.Kode, GETDATE(), 4, 'Y'
FROM @orang o
WHERE NOT EXISTS (
    SELECT 1 FROM KPI_Karyawan_Team t
    WHERE t.Kode_Karyawan_Leader = 'H45' AND t.Kode_Karyawan_Team = o.Kode AND t.Flag_Aktif = 'Y'
);

/* 4. Approver langkah 1 = H45 ------------------------------------------------------- */
INSERT INTO KPI_Approval_Flow_Performance (Id_Divisi, Id_Level, Kode_Karyawan_Approver, Kode_Karyawan_Requester, order_flow, Status)
SELECT NULL, NULL, 'H45', o.Kode, 1, NULL
FROM @orang o
WHERE NOT EXISTS (
    SELECT 1 FROM KPI_Approval_Flow_Performance f WHERE f.Kode_Karyawan_Requester = o.Kode
);

COMMIT TRANSACTION;
GO

/* Penjaga: yang dibaca mesin PA sekarang (penyaring SAMA dengan perhitungan). ----------- */
SELECT k.Kode_Karyawan, k.Nama,
    (SELECT STRING_AGG(u.Periode, ',') WITHIN GROUP (ORDER BY u.Periode)
     FROM KPI_Update_Performance u
     JOIN KPI_Pengajuan p ON p.Id_Pengajuan = u.Id_Pengajuan AND p.Flag_Aktif = 'Y' AND p.Flag_Approval = 'Y' AND p.Status IS NULL
     JOIN KPI_Master_Penjadwalan mp ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan AND mp.Tahun = '2026'
     WHERE p.Id_User = k.UserID_Web AND u.Flag_Aktif = 'Y' AND u.Flag_Approval = 'Y'
       AND u.Flag_Verifikasi = 'Y' AND u.Status IS NULL AND u.Periode BETWEEN '2026-01' AND '2026-09') AS periode_terbaca
FROM Karyawan k
WHERE k.Kode_Karyawan IN ('A4','J31','K32','E14','F18','E15','W60','R51','F40','I28')
ORDER BY k.Kode_Karyawan;
GO
