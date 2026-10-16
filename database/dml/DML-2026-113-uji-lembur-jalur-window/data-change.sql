/* =====================================================================
   DML-2026-113-uji-lembur-jalur-window
   Target      : Web_HRIS (DEVELOPMENT, host team311) -- JANGAN dijalankan di produksi
   Tujuan      : Menutup jalur yang belum tersentuh DML-2026-111 dan -112:
                 (W) pengambilan CheckIn/CheckOut lewat WINDOW BCI/ECI/BCO/ECO
                     untuk baris CHECKINOUT MENTAH (Hasil dan Untuk_Tanggal NULL),
                     yaitu bentuk data yang sebenarnya dikirim mesin absen,
                 (S) gerbang Flag_Sakit_Izin_Cuti,
                 (X) transaksi lembur yang DIBATALKAN (header Status <> NULL),
                 (AB) LEMBUR_AWAL + LEMBUR pada hari dan orang yang sama,
                 (NA) LEMBUR_AWAL pada shift malam yang menyeberang hari,
                 (M) dua transaksi lembur pada tanggal yang sama (non-determinisme).
   Pelengkap   : DML-2026-111, DML-2026-112
   Cakupan     : 3 baris Transaksi_Lembur, 11 Transaksi_Lembur_Detail,
                 11 Transaksi_Lembur_Det, 1 Transaksi_Sakit_Izin,
                 2 Transaksi_Sakit_Izin_Detail, 2 Transaksi_Sakit_Izin_Det,
                 25 baris CHECKINOUT, 1 UPDATE HRIS_Counter.
   Perusahaan  : 001
   Tanggal uji : 2026-09-14 s/d 2026-09-19

   -------------------------------------------------------------------
   JALUR WINDOW -- yang paling penting di berkas ini
   -------------------------------------------------------------------
   SP mencari CheckIn lewat `Hasil='In' AND Untuk_Tanggal=<tanggal>` LEBIH DULU.
   Bila tidak ketemu, ia jatuh ke `CheckTime BETWEEN @TempBCI AND @TempECI`
   (ISNULL(Hasil,'') <> 'Ex'), ambil yang PALING AWAL. CheckOut serupa lewat
   [@TempBCO, @TempECO], ambil yang PALING AKHIR.

   Di data dev yang nyata, mayoritas baris CHECKINOUT punya Hasil dan
   Untuk_Tanggal NULL (bentuk mentah dari mesin), jadi jalur window inilah
   yang dipakai produksi. DML-111/-112 SELALU mengisi kedua kolom itu,
   sehingga jalur ini belum pernah teruji. Baris uji kelompok W di bawah
   sengaja dibiarkan MENTAH.

   Window shift siang 08:30-16:30 (BCI 05:00, ECI 10:30, BCO 15:30, ECOLH 02:00):
     CheckIn  : [D 05:00, D 10:30]
     CheckOut : [D 15:30, D+1 02:00]   <- default
   TAPI bila ada pengajuan Jenis='LEMBUR', SP MENIMPA batas atasnya:
     @TempECO = Tanggal_Lembur_Sampai + 4 jam
   yang bisa lebih SEMPIT dari default. W3 vs W4 memotret akibatnya.

   Window shift malam EMI-B2 20:00-08:00 (BCI 18:00, ECI 22:00, BCO 07:00, ECOLH 11:00):
     CheckIn  : [D 18:00, D 22:00]
     CheckOut : [D+1 07:00, D+1 11:00]

   -------------------------------------------------------------------
   MATRIKS HARAPAN (menit, kecuali Koefisien_OT* yang dalam JAM)
   -------------------------------------------------------------------
   W1  R53 14 Sep  tap MENTAH 08:25, 19:30            est LEMBUR 16:30-19:30
                   -> Overtime 180. Jalur window bekerja untuk kasus normal.
   W2  S55 14 Sep  tap MENTAH 08:45, 08:50, 12:05, 19:00, 19:20   est 16:30-19:30
                   -> RealLate 15   (CheckIn = tap PALING AWAL 08:45, bukan 08:50)
                      Overtime 170  (CheckOut = tap PALING AKHIR 19:20, bukan 19:00)
                      Overtime_Calculated 155 (170 - telat 15)
                      Tap istirahat 12:05 di luar kedua window, diabaikan.
   W3  S56 14 Sep  tap MENTAH 08:25, 23:45            est LEMBUR 16:30-19:30
                   -> Overtime 0. @TempECO ditimpa jadi 23:30, tap 23:45 JATUH DI LUAR
                      window sehingga CheckOut NULL. Tap pulang yang sah HILANG.
   W4  T57 14 Sep  tap MENTAH 08:25, 23:45 (SAMA PERSIS dengan W3)
                                                      est LEMBUR 16:30-22:00
                   -> Overtime 330. Dengan estimasi yang lebih panjang, @TempECO
                      jadi D+1 02:00 sehingga tap 23:45 TERTANGKAP. Pembanding W3:
                      yang menentukan bukan datanya, melainkan lebar window.
   W5  A3  18 Sep  tap MENTAH 18/19:50, 19/08:05      shift malam EMI-B2
                   -> Overtime_Shift 360. Window lintas hari bekerja.

   S1  P44 17 Sep  long shift penuh + SAKIT disetujui -> Overtime_Shift 0
                   (gerbang Flag_Sakit_Izin_Cuti='T' gagal)
   S2  T36 14 Sep  shift siang + SAKIT disetujui + est LEMBUR 16:30-19:30
                   -> Overtime 180 TETAP DIBAYAR. Flag_Sakit_Izin_Cuti hanya
                      menutup LONG SHIFT dan deteksi alpha; ia TIDAK menyentuh
                      lembur dari pengajuan. Periksa apakah ini memang diinginkan.

   X1  T58 14 Sep  est LEMBUR 16:30-19:30 tapi header Status='Y' (DIBATALKAN)
                   -> Overtime 0. SP menyaring `a.Status is null`.

   AB1 V59 14 Sep  LEMBUR_AWAL 06:30-08:30 + LEMBUR 16:30-19:30, CI 06:30 CO 19:30
                   -> Overtime_Before 120, Overtime 180, Overtime_Real 300,
                      Overtime_Calculated 300, Koefisien_OT_Gross 5

   NA1 A38 16 Sep  EMI-LS2 19:00-07:00, LEMBUR_AWAL 17:00-19:00, CI 16/17:00 CO 17/07:00
                   -> Overtime_Before 120, Overtime_Shift 240,
                      Overtime_Calculated 360 (lembur awal + long shift)

   M1  W60 14 Sep  DUA transaksi LEMBUR di tanggal sama: est 16:30-19:30 DAN
                   est 16:30-21:30, CO 21:30
                   -> Overtime salah satu dari {180, 300}, TIDAK DITENTUKAN.
                      SP mengambil `SELECT @var = kolom FROM ...` tanpa TOP 1 dan
                      tanpa ORDER BY, jadi baris mana yang menang bergantung
                      rencana eksekusi. Ini BUKAN uji lulus/gagal biasa; ia
                      memotret ketidakpastiannya.

   Pra-syarat yang sudah diverifikasi sebelum eksekusi:
     - CHECKINOUT untuk 11 UserID_Absen yang dipakai pada 14-19 Sep 2026 : 0 baris
       (kecuali A3/A38/P44 yang barisnya milik DML-2026-112 pada tanggal LAIN)
     - Tidak ada transaksi lembur/sakit lain untuk karyawan+tanggal ini
     - HRIS_Counter prefix 'IS09-26-' bernilai 2, jadi IS09-26-0003 diambil di sini
       dan counter dinaikkan menjadi 3 supaya generator aplikasi lanjut ke 0004.
     - HRIS_Counter belum punya baris 'LM09-26-', jadi LM09-26-0006..0008 aman
       (generator menyemai diri dari MAX yang ada).
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
-- 1. Header transaksi lembur
--    0006 = normal, 0007 = DIBATALKAN (Status='Y'), 0008 = transaksi kedua
--    pada tanggal yang sama dengan salah satu detail di 0006 (skenario M1).
-- ---------------------------------------------------------------------
INSERT INTO dbo.Transaksi_Lembur
(Kode_Perusahaan, No_Transaksi, Tanggal, Jam, Status, UserID, Updated_At, Updated_By, Flag_Admin, Flag_Selesai,
 Alasan_Pembatalan)
VALUES ('001', 'LM09-26-0006', '2026-09-13', '10:00:00', NULL, 'UJI_FN', '2026-09-13T10:00:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', NULL),
       ('001', 'LM09-26-0007', '2026-09-13', '10:05:00', 'Y', 'UJI_FN', '2026-09-13T10:05:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', 'UJI X1 - transaksi sengaja dibatalkan'),
       ('001', 'LM09-26-0008', '2026-09-13', '10:10:00', NULL, 'UJI_FN', '2026-09-13T10:10:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', NULL);

-- ---------------------------------------------------------------------
-- 2. Detail lembur.
--    Tanggal kerja DITULIS EKSPLISIT, tidak diturunkan dari tanggal
--    estimasi: pada shift malam keduanya bisa berbeda hari.
-- ---------------------------------------------------------------------
DECLARE @Rencana TABLE
                 (
                    No_Transaksi  varchar(30),
                    Kode_Karyawan varchar(15),
                    Jenis         varchar(20),
                    Dari          datetime,
                    Sampai        datetime,
                    Alasan        varchar(300),
                    Tanggal_Kerja datetime
                 );

INSERT INTO @Rencana (No_Transaksi, Kode_Karyawan, Jenis, Dari, Sampai, Alasan, Tanggal_Kerja)
VALUES ('LM09-26-0006', 'R53', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
        'UJI W1 jalur window kasus normal', '2026-09-14T00:00:00'),
       ('LM09-26-0006', 'S55', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
        'UJI W2 tap ganda, paling awal dan paling akhir', '2026-09-14T00:00:00'),
       ('LM09-26-0006', 'S56', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
        'UJI W3 tap pulang jatuh di luar window', '2026-09-14T00:00:00'),
       ('LM09-26-0006', 'T57', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T22:00:00',
        'UJI W4 pembanding W3, window lebih lebar', '2026-09-14T00:00:00'),
       ('LM09-26-0006', 'T36', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
        'UJI S2 lembur di hari sakit disetujui', '2026-09-14T00:00:00'),
       ('LM09-26-0006', 'V59', 'LEMBUR_AWAL', '2026-09-14T06:30:00', '2026-09-14T08:30:00',
        'UJI AB1 lembur awal', '2026-09-14T00:00:00'),
       ('LM09-26-0006', 'V59', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
        'UJI AB1 lembur setelah shift, hari yang sama', '2026-09-14T00:00:00'),
       ('LM09-26-0006', 'A38', 'LEMBUR_AWAL', '2026-09-16T17:00:00', '2026-09-16T19:00:00',
        'UJI NA1 lembur awal pada shift malam', '2026-09-16T00:00:00'),
       ('LM09-26-0006', 'W60', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
        'UJI M1 transaksi pertama di tanggal sama', '2026-09-14T00:00:00'),
       ('LM09-26-0007', 'T58', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T19:30:00',
        'UJI X1 transaksi dibatalkan', '2026-09-14T00:00:00'),
       ('LM09-26-0008', 'W60', 'LEMBUR', '2026-09-14T16:30:00', '2026-09-14T21:30:00',
        'UJI M1 transaksi kedua di tanggal sama', '2026-09-14T00:00:00');

DECLARE @DetailBaru TABLE
                    (
                        Urut_Oto      int,
                        No_Transaksi  varchar(30),
                        Kode_Karyawan varchar(15),
                        Jenis         varchar(20),
                        Dari          datetime
                    );

INSERT INTO dbo.Transaksi_Lembur_Detail
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Jenis, Tanggal_Lembur_Dari, Tanggal_Lembur_Sampai, Alasan, Id_Shift)
    OUTPUT inserted.Urut_Oto, inserted.No_Transaksi, inserted.Kode_Karyawan, inserted.Jenis,
        inserted.Tanggal_Lembur_Dari INTO @DetailBaru
SELECT '001', r.No_Transaksi, r.Kode_Karyawan, r.Jenis, r.Dari, r.Sampai, r.Alasan, NULL
FROM @Rencana r;

INSERT INTO dbo.Transaksi_Lembur_Det
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Urut_Detail, Tanggal_Lembur)
SELECT '001', d.No_Transaksi, d.Kode_Karyawan, d.Urut_Oto, r.Tanggal_Kerja
FROM @DetailBaru d
         JOIN @Rencana r
              ON r.No_Transaksi = d.No_Transaksi
                  AND r.Kode_Karyawan = d.Kode_Karyawan
                  AND r.Jenis = d.Jenis
                  AND r.Dari = d.Dari;

-- ---------------------------------------------------------------------
-- 3. Transaksi sakit yang DISETUJUI (skenario S1 dan S2)
--    Gerbangnya: Jenis='sakit', Flag_Approval='Y', header Status IS NULL.
-- ---------------------------------------------------------------------
INSERT INTO dbo.Transaksi_Sakit_Izin
(Kode_Perusahaan, No_Transaksi, Tanggal, Jam, Status, UserID, Updated_At, Updated_By)
VALUES ('001', 'IS09-26-0003', '2026-09-13', '10:15:00', NULL, 'UJI_FN', '2026-09-13T10:15:00', NULL);

DECLARE @SakitBaru TABLE
                   (
                       Urut_Oto      int,
                       Kode_Karyawan varchar(15)
                   );

INSERT INTO dbo.Transaksi_Sakit_Izin_Detail
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Jenis, Tanggal_Sakit_Izin_Dari, Tanggal_Sakit_Izin_Sampai, Alasan,
 Flag_Approval)
    OUTPUT inserted.Urut_Oto, inserted.Kode_Karyawan INTO @SakitBaru
VALUES ('001', 'IS09-26-0003', 'P44', 'sakit', '2026-09-17T00:00:00', '2026-09-17T00:00:00', 'UJI S1', 'Y'),
       ('001', 'IS09-26-0003', 'T36', 'sakit', '2026-09-14T00:00:00', '2026-09-14T00:00:00', 'UJI S2', 'Y');

INSERT INTO dbo.Transaksi_Sakit_Izin_Det
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Urut_Detail, Tanggal_Sakit_Izin)
SELECT '001',
       'IS09-26-0003',
       s.Kode_Karyawan,
       s.Urut_Oto,
       CASE s.Kode_Karyawan WHEN 'P44' THEN '2026-09-17T00:00:00' ELSE '2026-09-14T00:00:00' END
FROM @SakitBaru s;

-- Counter nomor sakit/izin sudah bernilai 2; 0003 dipakai di atas sehingga
-- counter dinaikkan agar pengajuan nyata berikutnya mendapat 0004, bukan 0003.
UPDATE dbo.HRIS_Counter SET next_val = 3 WHERE prefix = 'IS09-26-' AND next_val = 2;

-- ---------------------------------------------------------------------
-- 4a. CHECKINOUT MENTAH -- Hasil dan Untuk_Tanggal sengaja NULL,
--     meniru bentuk data yang dikirim mesin absen. Inilah yang memaksa SP
--     memakai jalur window. Jangkar pembersihan: Memoinfo 'UJI-WINDOW'.
-- ---------------------------------------------------------------------
INSERT INTO dbo.CHECKINOUT (USERID, CHECKTIME, Hasil, Untuk_Tanggal, Memoinfo)
VALUES
    -- W1 R53 (81066) 14 Sep
    (81066, '2026-09-14T08:25:00', NULL, NULL, 'UJI-WINDOW'),
    (81066, '2026-09-14T19:30:00', NULL, NULL, 'UJI-WINDOW'),

    -- W2 S55 (81021) 14 Sep, lima tap termasuk tap istirahat
    (81021, '2026-09-14T08:45:00', NULL, NULL, 'UJI-WINDOW'),
    (81021, '2026-09-14T08:50:00', NULL, NULL, 'UJI-WINDOW'),
    (81021, '2026-09-14T12:05:00', NULL, NULL, 'UJI-WINDOW'),
    (81021, '2026-09-14T19:00:00', NULL, NULL, 'UJI-WINDOW'),
    (81021, '2026-09-14T19:20:00', NULL, NULL, 'UJI-WINDOW'),

    -- W3 S56 (81081) 14 Sep, tap pulang 23:45 di LUAR window
    (81081, '2026-09-14T08:25:00', NULL, NULL, 'UJI-WINDOW'),
    (81081, '2026-09-14T23:45:00', NULL, NULL, 'UJI-WINDOW'),

    -- W4 T57 (81154) 14 Sep, tap identik dengan W3 tapi window lebih lebar
    (81154, '2026-09-14T08:25:00', NULL, NULL, 'UJI-WINDOW'),
    (81154, '2026-09-14T23:45:00', NULL, NULL, 'UJI-WINDOW'),

    -- W5 A3 (81072) 18 Sep, shift malam, window menyeberang hari
    (81072, '2026-09-18T19:50:00', NULL, NULL, 'UJI-WINDOW'),
    (81072, '2026-09-19T08:05:00', NULL, NULL, 'UJI-WINDOW');

-- ---------------------------------------------------------------------
-- 4b. CHECKINOUT bentuk terolah (Hasil + Untuk_Tanggal terisi) untuk
--     skenario yang tidak sedang menguji jalur window.
--     Jangkar pembersihan: Memoinfo 'UJI-GERBANG'.
-- ---------------------------------------------------------------------
INSERT INTO dbo.CHECKINOUT (USERID, CHECKTIME, Hasil, Untuk_Tanggal, Memoinfo)
VALUES
    -- S1 P44 (81143) 17 Sep, long shift penuh tapi hari itu sakit
    (81143, '2026-09-17T19:50:00', 'In', '2026-09-17T00:00:00', 'UJI-GERBANG'),
    (81143, '2026-09-18T08:00:00', 'Out', '2026-09-17T00:00:00', 'UJI-GERBANG'),

    -- S2 T36 (81055) 14 Sep, lembur di hari sakit disetujui
    (81055, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-GERBANG'),
    (81055, '2026-09-14T19:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-GERBANG'),

    -- X1 T58 (116) 14 Sep, transaksi lemburnya dibatalkan
    (116, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-GERBANG'),
    (116, '2026-09-14T19:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-GERBANG'),

    -- AB1 V59 (117) 14 Sep, lembur awal + lembur setelah shift
    (117, '2026-09-14T06:30:00', 'In', '2026-09-14T00:00:00', 'UJI-GERBANG'),
    (117, '2026-09-14T19:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-GERBANG'),

    -- NA1 A38 (81037) 16 Sep, lembur awal pada shift malam
    (81037, '2026-09-16T17:00:00', 'In', '2026-09-16T00:00:00', 'UJI-GERBANG'),
    (81037, '2026-09-17T07:00:00', 'Out', '2026-09-16T00:00:00', 'UJI-GERBANG'),

    -- M1 W60 (82) 14 Sep, dua transaksi lembur di tanggal yang sama
    (82, '2026-09-14T08:25:00', 'In', '2026-09-14T00:00:00', 'UJI-GERBANG'),
    (82, '2026-09-14T21:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-GERBANG');

COMMIT TRANSACTION;

/* =====================================================================
   VERIFIKASI
   Jalankan database/dml/DML-2026-113-uji-lembur-jalur-window/verify.sql
   (read-only). Untuk keluaran SP apa adanya:

   EXEC dbo.HRIS_SP_GET_ABSEN_NEW_V3
        @KodePerusahaan = '001',
        @TglAwal        = '2026-09-14',
        @TglAkhir       = '2026-09-18',
        @UserID         = '81066,81021,81081,81154,81072,81143,81055,116,117,81037,82',
        @Pembeda        = NULL,
        @UserID_Cetak   = 'UJI_WIN';
   ===================================================================== */

/* =====================================================================
   ROLLBACK / PEMBERSIHAN -- SENGAJA DIKOMENTARI.
   Jalankan manual bila data uji sudah tidak diperlukan.

   DELETE FROM dbo.CHECKINOUT
   WHERE Memoinfo IN ('UJI-WINDOW', 'UJI-GERBANG')
     AND CHECKTIME >= '2026-09-14' AND CHECKTIME < '2026-09-20';

   DELETE FROM dbo.Transaksi_Sakit_Izin_Det
   WHERE Kode_Perusahaan = '001' AND No_Transaksi = 'IS09-26-0003';
   DELETE FROM dbo.Transaksi_Sakit_Izin_Detail
   WHERE Kode_Perusahaan = '001' AND No_Transaksi = 'IS09-26-0003';
   DELETE FROM dbo.Transaksi_Sakit_Izin
   WHERE Kode_Perusahaan = '001' AND No_Transaksi = 'IS09-26-0003';

   DELETE FROM dbo.Transaksi_Lembur_Det
   WHERE Kode_Perusahaan = '001'
     AND No_Transaksi IN ('LM09-26-0006','LM09-26-0007','LM09-26-0008');
   DELETE FROM dbo.Transaksi_Lembur_Detail
   WHERE Kode_Perusahaan = '001'
     AND No_Transaksi IN ('LM09-26-0006','LM09-26-0007','LM09-26-0008');
   DELETE FROM dbo.Transaksi_Lembur
   WHERE Kode_Perusahaan = '001'
     AND No_Transaksi IN ('LM09-26-0006','LM09-26-0007','LM09-26-0008');

   -- Counter IS09-26- TIDAK diturunkan kembali: menurunkannya justru
   -- berisiko membuat nomor kembar bila sudah ada pengajuan nyata.
   ===================================================================== */
