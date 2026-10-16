/* =====================================================================
   DML-2026-112-uji-lembur-long-shift
   Target      : Web_HRIS (DEVELOPMENT, host team311) -- JANGAN dijalankan di produksi
   Tujuan      : Menyemai data uji untuk blok "PERHITUNGAN LEMBUR LONG SHIFT
                 (LEVEL 1,2,21)" di dbo.HRIS_SP_GET_ABSEN_NEW_V3, termasuk satu
                 skenario yang menggabungkannya dengan pengajuan lembur biasa
                 (dbo.N_HRIS_FN_CalculateOvertime).
   Pelengkap   : DML-2026-111-uji-fn-perhitungan-lembur
   Cakupan     : 1 baris Transaksi_Lembur, 1 Transaksi_Lembur_Detail,
                 1 Transaksi_Lembur_Det, 25 baris CHECKINOUT.
   Perusahaan  : 001
   Tanggal uji : 2026-09-14 s/d 2026-09-17 (cap waktu checkout bisa jatuh di H+1)

   -------------------------------------------------------------------
   RUMUS YANG DIUJI (bukan keluaran N_HRIS_FN_CalculateOvertime)
   -------------------------------------------------------------------
   Gerbang (semua harus benar, kalau tidak Overtime_Shift = 0):
     Flag_LongShift='Y' AND Flag_Sakit_Izin_Cuti='T' AND StatusHari='WORK'
     AND Status_Karyawan='JOIN' AND ID_Level IN (1,2,21) AND CheckOut IS NOT NULL

   Perhitungan:
     Plafon        = Durasi_Lembur (jam, dari HRIS_Waktu_Kerja) x 60
     Titik mulai   = Temp_JamKeluar - Durasi_Lembur jam
     Sudah dijalani= DATEDIFF(MINUTE, Titik mulai, CheckOut)
     Overtime_Shift= MIN(Sudah dijalani, Plafon), dan 0 bila hasilnya negatif

   Catatan shift lintas hari: karena Jam_Keluar < Jam_Masuk, Temp_JamKeluar
   digeser ke H+1. Untuk EMI-B2 (20:00-08:00, Durasi 6) pada tanggal kerja D:
     Temp_JamKeluar = D+1 08:00, titik mulai = D+1 02:00, plafon 360 menit.
   Untuk EMI-LS2 (19:00-07:00, Durasi 4):
     Temp_JamKeluar = D+1 07:00, titik mulai = D+1 03:00, plafon 240 menit.

   -------------------------------------------------------------------
   MATRIKS HARAPAN (menit, kecuali Koefisien_OT yang dalam JAM)
   -------------------------------------------------------------------
   L1  P44 15 Sep EMI-B2  CI 15/19:50 CO 16/08:00 -> Overtime_Shift 360  pas penuh
   L2  A3  14 Sep EMI-B2  CI 14/19:50 CO 15/05:30 -> Overtime_Shift 210  pulang di tengah porsi lembur
   L3  D11 14 Sep EMI-B2  CI 14/19:50 CO 15/10:00 -> Overtime_Shift 360  lewat jam keluar, diplafon
   L4  P44 14 Sep EMI-B2  CI 14/19:50 CO 15/01:00 -> Overtime_Shift 0    pulang sebelum porsi lembur mulai
   L5  A3  17 Sep EMI-B2  CI 17/19:50 CO (-)      -> Overtime_Shift 0    gerbang CheckOut IS NOT NULL
   L6  A38 14 Sep EMI-LS2 CI 14/18:50 CO 15/07:00 -> Overtime_Shift 240  Durasi_Lembur 4 jam
   L6b A38 15 Sep EMI-LS2 CI 15/18:50 CO 16/05:00 -> Overtime_Shift 120  pulang di tengah, plafon 4 jam
   L7  K32 14 Sep EMI-LS1 CI 06:50   CO 19:00     -> Overtime_Shift 0    ID_Level 5 di luar (1,2,21)
   L8  C9  14 Sep EMI-LS1 CI 06:50   CO 19:00     -> Overtime_Shift 0    ID_Level 10 di luar (1,2,21)
   L9  A3  16 Sep EMI-B2  CI 16/20:45 CO 17/08:00 -> Overtime_Shift 360, Overtime_Gross 360,
                                                      Overtime_Calculated 315 (telat 45 menit memotong
                                                      HASIL AKHIR, bukan Overtime_Shift-nya)
   L10 D11 16 Sep (OFF)   CI 16/19:50 CO 17/08:00 -> Overtime_Shift 0    bukan hari WORK
   L11 D11 15 Sep EMI-B2  CI 15/19:50 CO 16/10:00 + pengajuan LEMBUR est 16/08:00-10:00
                                                   -> Overtime 120 DAN Overtime_Shift 360,
                                                      Overtime_Calculated 480 (keduanya dijumlahkan)
   L12 A1  14 Sep EMI-B2  CI 14/19:50 CO 15/08:00 -> Overtime_Shift 0    gerbang Status_Karyawan='JOIN'
                                                      (A1 punya Tanggal_Resign 2025-09-03 walau Aktif='Y',
                                                       jadi SP melaporkannya RESIGN)

   Konteks yang membuat angka di atas benar (diverifikasi dari data dev):
     - Toleransi_Telat = 0 pada EMI-B2/EMI-LS1/EMI-LS2, jadi telat 45 menit pada L9
       tercatat penuh.
     - Tidak ada baris HRIS_Lembur_Per_Karyawan untuk ke-7 karyawan ini, jadi
       Dihitung_Setelah = 0 dan Overtime_Real = Overtime + Overtime_Before.
     - A1 (Frans Bachtiar) punya Tanggal_Resign 2025-09-03 walau Aktif='Y', sehingga SP
       melaporkan Status_Karyawan='RESIGN'. Ia karena itu dipakai sebagai skenario
       gerbang L12, bukan sebagai kasus normal.
     - N_HRIS_Overtime_Limits aktif (Effective_Date 2026-07-25) memberi
       Max_Hours_Workday = 1, sehingga Koefisien_OT pada hari WORK SELALU
       terpotong menjadi 1 jam betapapun besar Overtime_Calculated-nya.
       Koefisien_OT_Gross menyimpan nilai sebelum plafon itu.

   Pra-syarat yang sudah diverifikasi sebelum eksekusi:
     - CHECKINOUT untuk ke-7 UserID_Absen pada 13-20 Sep 2026 : 0 baris
     - Transaksi_Lembur_Det untuk ke-7 karyawan pada rentang itu : 0 baris
     - Transaksi_Cuti_Det untuk ke-7 karyawan pada rentang itu : 0 baris
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
-- 1. Header + detail pengajuan lembur, khusus skenario L11
--    (long shift berbarengan dengan lembur setelah shift)
-- ---------------------------------------------------------------------
INSERT INTO dbo.Transaksi_Lembur
(Kode_Perusahaan, No_Transaksi, Tanggal, Jam, Status, UserID, Updated_At, Updated_By, Flag_Admin, Flag_Selesai,
 Alasan_Pembatalan)
VALUES ('001', 'LM09-26-0005', '2026-09-13', '09:20:00', NULL, 'UJI_FN', '2026-09-13T09:20:00', 'UJI_FN_LEMBUR', 'Y',
        'Y', NULL);

DECLARE @DetailBaru TABLE
                    (
                        Urut_Oto      int,
                        Kode_Karyawan varchar(15)
                    );

-- Tanggal kerja L11 adalah 15 Sep, sedangkan estimasi lemburnya jatuh pada
-- 16 Sep karena shift malam menyeberang hari. Karena itu Tanggal_Lembur pada
-- tabel Det TIDAK boleh diturunkan dari tanggal estimasi; diisi eksplisit.
INSERT INTO dbo.Transaksi_Lembur_Detail
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Jenis, Tanggal_Lembur_Dari, Tanggal_Lembur_Sampai, Alasan, Id_Shift)
    OUTPUT inserted.Urut_Oto, inserted.Kode_Karyawan INTO @DetailBaru
VALUES ('001', 'LM09-26-0005', 'D11', 'LEMBUR', '2026-09-16T08:00:00', '2026-09-16T10:00:00',
        'UJI L11 long shift + pengajuan lembur setelah shift', NULL);

INSERT INTO dbo.Transaksi_Lembur_Det
(Kode_Perusahaan, No_Transaksi, Kode_Karyawan, Urut_Detail, Tanggal_Lembur)
SELECT '001', 'LM09-26-0005', d.Kode_Karyawan, d.Urut_Oto, '2026-09-15T00:00:00'
FROM @DetailBaru d;

-- ---------------------------------------------------------------------
-- 2. CHECKINOUT (USERID = Karyawan.UserID_Absen)
--    Untuk_Tanggal SELALU tanggal kerja, walau CHECKTIME jatuh di H+1 untuk
--    shift malam. Ini yang membuat SP memungutnya lewat jalur
--    Hasil + Untuk_Tanggal, bukan jalur window BCO/ECO.
--    Memoinfo 'UJI-LONGSHIFT' dipakai sebagai jangkar pembersihan.
-- ---------------------------------------------------------------------
INSERT INTO dbo.CHECKINOUT (USERID, CHECKTIME, Hasil, Untuk_Tanggal, Memoinfo)
VALUES
    -- L12 A1 (81090) 14 Sep, gerbang Status_Karyawan: SP melaporkan A1 RESIGN
    (81090, '2026-09-14T19:50:00', 'In', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    (81090, '2026-09-15T08:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),

    -- L2  A3 (81072) 14 Sep, pulang di tengah porsi lembur
    (81072, '2026-09-14T19:50:00', 'In', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    (81072, '2026-09-15T05:30:00', 'Out', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    -- L9  A3 (81072) 16 Sep, masuk telat 45 menit
    (81072, '2026-09-16T20:45:00', 'In', '2026-09-16T00:00:00', 'UJI-LONGSHIFT'),
    (81072, '2026-09-17T08:00:00', 'Out', '2026-09-16T00:00:00', 'UJI-LONGSHIFT'),
    -- L5  A3 (81072) 17 Sep, sengaja tanpa baris Out
    (81072, '2026-09-17T19:50:00', 'In', '2026-09-17T00:00:00', 'UJI-LONGSHIFT'),

    -- L3  D11 (81130) 14 Sep, pulang lewat jam keluar
    (81130, '2026-09-14T19:50:00', 'In', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    (81130, '2026-09-15T10:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    -- L11 D11 (81130) 15 Sep, long shift + pengajuan lembur
    (81130, '2026-09-15T19:50:00', 'In', '2026-09-15T00:00:00', 'UJI-LONGSHIFT'),
    (81130, '2026-09-16T10:00:00', 'Out', '2026-09-15T00:00:00', 'UJI-LONGSHIFT'),
    -- L10 D11 (81130) 16 Sep, hari OFF
    (81130, '2026-09-16T19:50:00', 'In', '2026-09-16T00:00:00', 'UJI-LONGSHIFT'),
    (81130, '2026-09-17T08:00:00', 'Out', '2026-09-16T00:00:00', 'UJI-LONGSHIFT'),

    -- L4  P44 (81143) 14 Sep, pulang sebelum porsi lembur dimulai
    (81143, '2026-09-14T19:50:00', 'In', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    (81143, '2026-09-15T01:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    -- L1  P44 (81143) 15 Sep, plafon 6 jam terpenuhi penuh
    (81143, '2026-09-15T19:50:00', 'In', '2026-09-15T00:00:00', 'UJI-LONGSHIFT'),
    (81143, '2026-09-16T08:00:00', 'Out', '2026-09-15T00:00:00', 'UJI-LONGSHIFT'),

    -- L6  A38 (81037) 14 Sep, plafon 4 jam terpenuhi penuh
    (81037, '2026-09-14T18:50:00', 'In', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    (81037, '2026-09-15T07:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    -- L6b A38 (81037) 15 Sep, pulang di tengah porsi lembur
    (81037, '2026-09-15T18:50:00', 'In', '2026-09-15T00:00:00', 'UJI-LONGSHIFT'),
    (81037, '2026-09-16T05:00:00', 'Out', '2026-09-15T00:00:00', 'UJI-LONGSHIFT'),

    -- L7  K32 (81016) 14 Sep, ID_Level 5
    (81016, '2026-09-14T06:50:00', 'In', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    (81016, '2026-09-14T19:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),

    -- L8  C9 (90) 14 Sep, ID_Level 10
    (90, '2026-09-14T06:50:00', 'In', '2026-09-14T00:00:00', 'UJI-LONGSHIFT'),
    (90, '2026-09-14T19:00:00', 'Out', '2026-09-14T00:00:00', 'UJI-LONGSHIFT');

COMMIT TRANSACTION;

/* =====================================================================
   VERIFIKASI
   Jalankan database/dml/DML-2026-112-uji-lembur-long-shift/verify.sql
   (read-only). Untuk keluaran SP apa adanya:

   EXEC dbo.HRIS_SP_GET_ABSEN_NEW_V3
        @KodePerusahaan = '001',
        @TglAwal        = '2026-09-14',
        @TglAkhir       = '2026-09-17',
        @UserID         = '81090,81072,81130,81143,81037,81016,90',
        @Pembeda        = NULL,
        @UserID_Cetak   = 'UJI_LS';
   ===================================================================== */

/* =====================================================================
   ROLLBACK / PEMBERSIHAN -- SENGAJA DIKOMENTARI.
   Jalankan manual bila data uji sudah tidak diperlukan.

   DELETE FROM dbo.CHECKINOUT
   WHERE Memoinfo = 'UJI-LONGSHIFT'
     AND CHECKTIME >= '2026-09-14' AND CHECKTIME < '2026-09-19';

   DELETE FROM dbo.Transaksi_Lembur_Det
   WHERE Kode_Perusahaan = '001' AND No_Transaksi = 'LM09-26-0005';

   DELETE FROM dbo.Transaksi_Lembur_Detail
   WHERE Kode_Perusahaan = '001' AND No_Transaksi = 'LM09-26-0005';

   DELETE FROM dbo.Transaksi_Lembur
   WHERE Kode_Perusahaan = '001' AND No_Transaksi = 'LM09-26-0005';
   ===================================================================== */
