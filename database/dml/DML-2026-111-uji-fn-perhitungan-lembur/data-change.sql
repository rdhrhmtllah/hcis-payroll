/* =====================================================================
   DML-2026-111-uji-fn-perhitungan-lembur
   Target      : Web_HRIS (DEVELOPMENT, host team311) -- JANGAN dijalankan di produksi
   Tujuan      : Menyemai data uji untuk membuktikan dbo.N_HRIS_FN_CalculateOvertime
                 melalui jalur nyata HRIS_SP_GET_ABSEN_NEW_V3.
   Cakupan     : 4 baris Transaksi_Lembur, 18 baris Transaksi_Lembur_Detail,
                 18 baris Transaksi_Lembur_Det, 35 baris CHECKINOUT.
   Perusahaan  : 001
   Tanggal uji : 2026-09-14 (StatusHari WORK, shift 08:30-16:30)
                 2026-09-13 (StatusHari OFF-Default_Shift)

   Pra-syarat yang sudah diverifikasi sebelum eksekusi:
     - CHECKINOUT untuk ke-18 UserID_Absen pada 13-14 Sep 2026 : 0 baris
     - Transaksi_Lembur_Det untuk ke-18 karyawan pada tanggal itu : 0 baris
     - Transaksi_Lembur dengan prefix 'LM09-26-' : 0 baris
       (HRIS_Counter belum punya baris 'LM09-26-', jadi generator aplikasi akan
        menyemai dirinya dari MAX = 0004 dan lanjut ke 0005 -- tidak bentrok)

   Matriks harapan (satuan MENIT, kolom keluaran SP):
     Overtime        -> skenario A (LEMBUR), C (LEMBUR_LIBUR), D (penjaga)
     Overtime_Before -> skenario B (LEMBUR_AWAL)

     A1 D12 LEMBUR      16:30-19:30  CI 08:25 CO 19:30 -> 180  kasus pas
     A2 F20 LEMBUR      16:30-19:30  CI 08:25 CO 18:45 -> 135  pulang awal = aktual
     A3 F22 LEMBUR      16:30-19:30  CI 08:25 CO 21:00 -> 180  plafon estimasi
     A4 F23 LEMBUR      17:30-20:30  CI 08:25 CO 20:30 -> 180  jeda 16:30-17:30 tak dibayar
     A5 F40 LEMBUR      16:30-19:30  CI 08:25 CO 16:00 -> 0    pulang sblm jam keluar
     A6 G25 LEMBUR      16:30-19:30  CI 08:25 CO (-)   -> 0    CheckOut NULL
     B1 H33 LEMBUR_AWAL 06:30-08:30  CI 06:30 CO 16:35 -> 120  kasus pas
     B2 I27 LEMBUR_AWAL 06:30-08:30  CI 07:10 CO 16:35 -> 80   telat = mulai ikut CheckIn
     B3 J31 LEMBUR_AWAL 06:30-09:00  CI 06:30 CO 16:35 -> 120  dipotong jam masuk shift
     B4 M37 LEMBUR_AWAL 06:30-08:30  CI 08:45 CO 16:35 -> 0    datang setelah jam masuk
     C1 N42 LEMBUR_LIBUR 08:00-12:00 CI 08:00 CO 12:00 -> 240  kasus pas
     C2 N43 LEMBUR_LIBUR 08:00-12:00 CI 08:30 CO 12:00 -> 210  datang telat
     C3 R46 LEMBUR_LIBUR 08:00-12:00 CI 08:00 CO 14:00 -> 360  FULL AKTUAL (tanpa plafon)
     C4 R47 LEMBUR_LIBUR 10:00-14:00 CI 07:00 CO 09:00 -> 0    pulang sblm estimasi mulai
     D1 R48 LEMBUR      16:30-19:30  CI 17:00 CO 16:50 -> 0    CheckOut <= CheckIn
                                                                   (PK_CHECKINOUT = USERID+CHECKTIME melarang
                                                                    dua cap waktu identik, jadi CheckOut dibuat
                                                                    lebih AWAL dari CheckIn -- tetap menguji
                                                                    cabang @CheckOut <= @CheckIn)
     D2 R49 LEMBUR      19:30-16:30  CI 08:25 CO 20:00 -> 0    estimasi terbalik
     D3 R50 LEMBUR (hari OFF 13 Sep) CI 08:00 CO 12:00 -> 0    jenis != status hari
     D4 R52 LEMBUR_LIBUR (hari WORK) CI 08:25 CO 19:30 -> 0    jenis != status hari
   ===================================================================== */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> 'Web_HRIS'
    BEGIN
        RAISERROR ('Skrip ini hanya untuk database pengembangan Web_HRIS.', 16, 1);
        RETURN;
    END

BEGIN TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Header transaksi lembur (Status NULL = belum dibatalkan)
-- ---------------------------------------------------------------------
INSERT INTO dbo.Transaksi_Lembur
(Kode_Perusahaan, No_Transaksi, Tanggal, Jam, Status, UserID, Updated_At, Updated_By, Flag_Admin, Flag_Selesai,
 Alasan_Pembatalan)
VALUES ('001', 'LM09-26-0001', '2026-09-12', '09:00:00', NULL, 'UJI_FN', '2026-09-12T09:00:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', NULL),
       ('001', 'LM09-26-0002', '2026-09-12', '09:05:00', NULL, 'UJI_FN', '2026-09-12T09:05:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', NULL),
       ('001', 'LM09-26-0003', '2026-09-11', '09:10:00', NULL, 'UJI_FN', '2026-09-11T09:10:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', NULL),
       ('001', 'LM09-26-0004', '2026-09-12', '09:15:00', NULL, 'UJI_FN', '2026-09-12T09:15:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', NULL);

-- ---------------------------------------------------------------------
-- 2. Detail estimasi lembur; Urut_Oto identity ditangkap untuk tabel Det
-- ---------------------------------------------------------------------
DECLARE @DetailBaru TABLE
                    (
                        Urut_Oto            int,
                        Kode_Perusahaan     varchar(3),
                        No_Transaksi        varchar(30),
                        Kode_Karyawan       varchar(15),
                        Tanggal_Lembur_Dari datetime
                    );

INSERT INTO dbo.Transaksi_Lembur_Detail
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Jenis, Tanggal_Lembur_Dari, Tanggal_Lembur_Sampai, Alasan, Id_Shift)
    OUTPUT inserted.Urut_Oto, inserted.Kode_Perusahaan, inserted.No_Transaksi, inserted.Kode_Karyawan,
        inserted.Tanggal_Lembur_Dari
        INTO @DetailBaru
VALUES
    -- A. LEMBUR (setelah shift), hari kerja 2026-09-14
    ('001', 'LM09-26-0001', 'D12', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00', 'UJI A1 kasus pas', NULL),
    ('001', 'LM09-26-0001', 'F20', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00', 'UJI A2 pulang lebih awal',
     NULL),
    ('001', 'LM09-26-0001', 'F22', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
     'UJI A3 pulang lewat estimasi', NULL),
    ('001', 'LM09-26-0001', 'F23', 'LEMBUR', '2026-09-14T17:30:00', '2026-09-14T20:30:00',
     'UJI A4 estimasi mulai setelah jam keluar', NULL),
    ('001', 'LM09-26-0001', 'F40', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
     'UJI A5 pulang sebelum jam keluar', NULL),
    ('001', 'LM09-26-0001', 'G25', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00', 'UJI A6 tanpa checkout',
     NULL),

    -- B. LEMBUR_AWAL (sebelum shift), hari kerja 2026-09-14
    ('001', 'LM09-26-0002', 'H33', 'LEMBUR_AWAL', '2026-09-14T06:30:00', '2026-09-14T08:30:00', 'UJI B1 kasus pas',
     NULL),
    ('001', 'LM09-26-0002', 'I27', 'LEMBUR_AWAL', '2026-09-14T06:30:00', '2026-09-14T08:30:00',
     'UJI B2 datang telat dari estimasi', NULL),
    ('001', 'LM09-26-0002', 'J31', 'LEMBUR_AWAL', '2026-09-14T06:30:00', '2026-09-14T09:00:00',
     'UJI B3 estimasi selesai melewati jam masuk', NULL),
    ('001', 'LM09-26-0002', 'M37', 'LEMBUR_AWAL', '2026-09-14T06:30:00', '2026-09-14T08:30:00',
     'UJI B4 datang setelah jam masuk', NULL),

    -- C. LEMBUR_LIBUR, hari libur 2026-09-13
    ('001', 'LM09-26-0003', 'N42', 'LEMBUR_LIBUR', '2026-09-13T08:00:00', '2026-09-13T12:00:00', 'UJI C1 kasus pas',
     NULL),
    ('001', 'LM09-26-0003', 'N43', 'LEMBUR_LIBUR', '2026-09-13T08:00:00', '2026-09-13T12:00:00', 'UJI C2 datang telat',
     NULL),
    ('001', 'LM09-26-0003', 'R46', 'LEMBUR_LIBUR', '2026-09-13T08:00:00', '2026-09-13T12:00:00',
     'UJI C3 full aktual tanpa plafon', NULL),
    ('001', 'LM09-26-0003', 'R47', 'LEMBUR_LIBUR', '2026-09-13T10:00:00', '2026-09-13T14:00:00',
     'UJI C4 pulang sebelum estimasi mulai', NULL),

    -- D. Penjaga validasi & pasangan jenis/status hari yang salah
    ('001', 'LM09-26-0004', 'R48', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
     'UJI D1 checkout <= checkin', NULL),
    ('001', 'LM09-26-0004', 'R49', 'LEMBUR', '2026-09-14T19:30:00', '2026-09-14T16:30:00', 'UJI D2 estimasi terbalik',
     NULL),
    ('001', 'LM09-26-0004', 'R50', 'LEMBUR', '2026-09-13T08:00:00', '2026-09-13T12:00:00',
     'UJI D3 jenis LEMBUR di hari OFF', NULL),
    ('001', 'LM09-26-0004', 'R52', 'LEMBUR_LIBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
     'UJI D4 jenis LEMBUR_LIBUR di hari WORK', NULL);

-- ---------------------------------------------------------------------
-- 3. Baris Det (penghubung karyawan <-> tanggal kerja efektif)
--    Urut_Detail = Urut_Oto detail, persis seperti yang ditulis aplikasi.
-- ---------------------------------------------------------------------
INSERT INTO dbo.Transaksi_Lembur_Det
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Urut_Detail, Tanggal_Lembur)
SELECT d.Kode_Perusahaan,
       d.No_Transaksi,
       d.Kode_Karyawan,
       d.Urut_Oto,
       CAST(CAST(d.Tanggal_Lembur_Dari AS date) AS datetime)
FROM @DetailBaru d;

-- ---------------------------------------------------------------------
-- 4. CHECKINOUT (USERID = Karyawan.UserID_Absen)
--    Untuk_Tanggal diisi eksplisit supaya SP mengambilnya lewat jalur
--    Hasil + Untuk_Tanggal, bukan jalur window BCI/ECI/BCO/ECO.
--    Memoinfo 'UJI-FN-LEMBUR' dipakai sebagai jangkar pembersihan.
-- ---------------------------------------------------------------------
INSERT INTO dbo.CHECKINOUT (USERID, CHECKTIME, Hasil, Untuk_Tanggal, Memoinfo)
VALUES
    -- A1 D12
    (80111111, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (80111111, '2026-09-14T19:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- A2 F20
    (81077, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81077, '2026-09-14T18:45:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- A3 F22
    (81014, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81014, '2026-09-14T21:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- A4 F23
    (81032, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81032, '2026-09-14T20:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- A5 F40
    (81110, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81110, '2026-09-14T16:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- A6 G25 (sengaja tanpa baris Out)
    (81064, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),

    -- B1 H33
    (81027, '2026-09-14T06:30:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81027, '2026-09-14T16:35:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- B2 I27
    (81078, '2026-09-14T07:10:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81078, '2026-09-14T16:35:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- B3 J31
    (83, '2026-09-14T06:30:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (83, '2026-09-14T16:35:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- B4 M37
    (81015, '2026-09-14T08:45:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81015, '2026-09-14T16:35:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),

    -- C1 N42
    (81035, '2026-09-13T08:00:00', 'In', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    (81035, '2026-09-13T12:00:00', 'Out', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    -- C2 N43
    (81150, '2026-09-13T08:30:00', 'In', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    (81150, '2026-09-13T12:00:00', 'Out', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    -- C3 R46
    (81080, '2026-09-13T08:00:00', 'In', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    (81080, '2026-09-13T14:00:00', 'Out', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    -- C4 R47
    (81145, '2026-09-13T07:00:00', 'In', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    (81145, '2026-09-13T09:00:00', 'Out', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),

    -- D1 R48 (checkout lebih awal dari checkin)
    (140, '2026-09-14T17:00:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (140, '2026-09-14T16:50:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- D2 R49
    (81013, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (81013, '2026-09-14T20:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    -- D3 R50 (hari OFF)
    (81098, '2026-09-13T08:00:00', 'In', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    (81098, '2026-09-13T12:00:00', 'Out', '2026-09-13T00:00:00', 'UJI-FN-LEMBUR'),
    -- D4 R52 (hari WORK)
    (44, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR'),
    (44, '2026-09-14T19:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-FN-LEMBUR');

COMMIT TRANSACTION;

/* =====================================================================
   VERIFIKASI -- jalankan terpisah setelah skrip di atas selesai.

   EXEC dbo.HRIS_SP_GET_ABSEN_NEW_V3
        @KodePerusahaan = '001',
        @TglAwal        = '2026-09-13',
        @TglAkhir       = '2026-09-14',
        @UserID         = '80111111,81077,81014,81032,81110,81064,81027,81078,83,81015,81035,81150,81080,81145,140,81013,81098,44',
        @Pembeda        = NULL,
        @UserID_Cetak   = 'UJI_FN';

   Baca kolom Overtime (A/C/D) dan Overtime_Before (B) -- keduanya MENIT --
   lalu bandingkan dengan matriks harapan di kepala berkas ini.
   ===================================================================== */

/* =====================================================================
   ROLLBACK / PEMBERSIHAN -- SENGAJA DIKOMENTARI.
   Jalankan manual bila data uji sudah tidak diperlukan.

   DELETE FROM dbo.CHECKINOUT
   WHERE Memoinfo = 'UJI-FN-LEMBUR'
     AND CHECKTIME >= '2026-09-13' AND CHECKTIME < '2026-09-15';

   DELETE FROM dbo.Transaksi_Lembur_Det
   WHERE Kode_Perusahaan = '001'
     AND No_Transaksi IN ('LM09-26-0001','LM09-26-0002','LM09-26-0003','LM09-26-0004');

   DELETE FROM dbo.Transaksi_Lembur_Detail
   WHERE Kode_Perusahaan = '001'
     AND No_Transaksi IN ('LM09-26-0001','LM09-26-0002','LM09-26-0003','LM09-26-0004');

   DELETE FROM dbo.Transaksi_Lembur
   WHERE Kode_Perusahaan = '001'
     AND No_Transaksi IN ('LM09-26-0001','LM09-26-0002','LM09-26-0003','LM09-26-0004');
   ===================================================================== */
