/* =====================================================================
   DML-2026-112 -- VERIFIKASI lembur LONG SHIFT
   Target : Web_HRIS (DEVELOPMENT). Read-only, tidak mengubah data apa pun.

   Jalankan setelah data-change.sql. Skrip menghasilkan 3 tabel:
     1) RINGKASAN      -- jumlah lulus / gagal
     2) PER PERIKSA    -- harapan vs nyata per kolom, LULUS / GAGAL
     3) KONTEKS MENTAH -- satu baris per (karyawan, tanggal) yang diuji

   Kolom yang diperiksa:
     Overtime_Shift      -- hasil blok long shift (MENIT)
     Overtime            -- hasil N_HRIS_FN_CalculateOvertime (MENIT)
     RealLate            -- keterlambatan (MENIT)
     Overtime_Gross      -- Overtime_Real + Overtime_Shift, sebelum potongan telat
     Overtime_Calculated -- setelah potongan telat
     Koefisien_OT_Gross  -- FLOOR(Overtime_Calculated/60), JAM, sebelum plafon harian
     Koefisien_OT        -- setelah plafon N_HRIS_Overtime_Limits (Max_Hours_Workday = 1)

   Kalau hanya ingin melihat keluaran SP apa adanya, jalankan bagian
   "EXEC POLOS" di paling bawah berkas ini.
   ===================================================================== */

SET NOCOUNT ON;

-- Sengaja memakai variabel tabel, bukan tabel temporary, supaya skrip ini
-- sama sekali tidak memuat pernyataan penghapusan objek.
-- Bentuk kolom mengikuti PERSIS urutan result set HRIS_SP_GET_ABSEN_NEW_V3
-- (95 kolom; kolom terakhir tanpa alias berisi @UserID_Cetak).
DECLARE @Hasil TABLE
(
    Kode_Perusahaan                varchar(10),
    UserId                         int,
    Kode_Karyawan                  varchar(20),
    Nama                           varchar(100),
    nama_divisi                    varchar(200),
    nama_sub_divisi                varchar(200),
    nama_level                     varchar(200),
    nama_jabatan                   varchar(200),
    Tanggal                        datetime,
    StatusHari                     varchar(50),
    Id_Shift                       int,
    Nama_Shift                     varchar(50),
    Nama_Hari_Kerja                varchar(50),
    JENIS_SHIFT_DIPILIH            varchar(100),
    Jam_Masuk                      varchar(20),
    Jam_Keluar                     varchar(20),
    CheckIn                        datetime,
    CheckOut                       datetime,
    BCI                            varchar(20),
    ECI                            varchar(20),
    BCO                            varchar(20),
    ECO                            varchar(20),
    ECOLH                          varchar(20),
    Fleksibel                      char(1),
    Flag_COLH                      char(1),
    TLate                          int,
    RealLate                       int,
    RpLate                         float,
    TLateBln                       int,
    RealEarly                      int,
    Overtime                       int,
    Overtime_Before                int,
    Dihitung_Setelah               int,
    Minimal_Lembur                 int,
    Overtime_Real                  int,
    Overtime_shift                 int,
    Overtime_Gross                 int,
    Overtime_Calculated            int,
    Koefisien_OT_Gross             int,
    Koefisien_OT                   int,
    Nominal_OT                     float,
    Uang_Lembur                    float,
    Flag_Lembur                    char(1),
    Flag_Lembur_Awal               char(1),
    Flag_Lembur_Libur              char(1),
    Jenis_Lembur                   varchar(50),
    Flag_Konversi_Lembur           char(1),
    Jenis_Konversi_Lembur          varchar(50),
    Jam_Uang_Makan                 varchar(20),
    Uang_Makan                     float,
    Flag_Sakit_Izin_Cuti           char(1),
    Flag_Libur                     char(1),
    Flag_Alpha                     char(1),
    No_Transaksi_Cuti              varchar(50),
    Tanggal_Cuti_Dari_web          datetime,
    Tanggal_Cuti_Sampai_web        datetime,
    Alasan_Cuti                    varchar(500),
    Jenis_Cuti                     varchar(100),
    Tgl_Izin_Terlambat             datetime,
    Jam_Izin_Terlambat             varchar(20),
    Tgl_Izin_Pulang_Cepat          datetime,
    Jam_Izin_Pulang_Cepat          varchar(20),
    No_Transaksi_Lembur_Awal       varchar(50),
    Tanggal_Lembur_Awal_Dari_web   datetime,
    Tanggal_Lembur_Awal_Sampai_web datetime,
    alasan_Lembur_Awal             varchar(500),
    No_Transaksi_Lembur            varchar(50),
    Tanggal_Lembur_Dari_web        datetime,
    Tanggal_Lembur_Sampai_web      datetime,
    Alasan_Lembur                  varchar(500),
    No_Transaksi_Izin              varchar(50),
    Tanggal_Izin_Dari_web          datetime,
    Tanggal_Izin_Sampai_web        datetime,
    Alasan_Izin                    varchar(500),
    No_Transaksi_Sakit             varchar(50),
    Tanggal_Sakit_Dari_web         datetime,
    Tanggal_Sakit_Sampai_web       datetime,
    Alasan_Sakit                   varchar(500),
    No_Transaksi_PulangCepat       varchar(50),
    Tanggal_PulangCepat_web        datetime,
    Jam_PulangCepat_Sampai_web     varchar(20),
    Alasan_PulangCepat             varchar(500),
    No_Transaksi_Terlambat         varchar(50),
    Tanggal_Terlambat_web          datetime,
    Jam_Terlambat_Sampai_web       varchar(20),
    Alasan_Terlambat               varchar(500),
    Status_Kehadiran               varchar(50),
    Temp_BCO                       datetime,
    Temp_ECO                       datetime,
    Temp_BCI                       datetime,
    Temp_ECI                       datetime,
    Temp_JamMasuk                  datetime,
    Temp_JamKeluar                 datetime,
    Status_Karyawan                varchar(50),
    -- Kolom ke-95 pada result set SP tidak punya alias: nilainya @UserID_Cetak
    -- yang diteruskan apa adanya. Harus tetap dideklarasikan supaya jumlah
    -- kolom INSERT..EXEC cocok.
    UserID_Cetak                   varchar(30)
);

INSERT INTO @Hasil
EXEC dbo.HRIS_SP_GET_ABSEN_NEW_V3
     @KodePerusahaan = '001',
     @TglAwal = '2026-09-14',
     @TglAkhir = '2026-09-17',
     @UserID = '81090,81072,81130,81143,81037,81016,90',
     @Pembeda = NULL,
     @UserID_Cetak = 'UJI_LS';

-- Matriks harapan (lihat data-change.sql untuk rinciannya).
DECLARE @Harap TABLE
(
    Urut          int,
    Kode          varchar(6),
    Kode_Karyawan varchar(20),
    Tanggal       date,
    Kolom         varchar(30),
    Harap         int,
    Keterangan    varchar(90)
);

INSERT INTO @Harap (Urut, Kode, Kode_Karyawan, Tanggal, Kolom, Harap, Keterangan)
VALUES
       (1, 'L1', 'P44', '2026-09-15', 'Overtime_Shift', 360, 'EMI-B2 plafon 6 jam terpenuhi penuh'),
       (2, 'L1b', 'P44', '2026-09-15', 'Koefisien_OT_Gross', 6, 'FLOOR(360/60) sebelum plafon harian'),
       (3, 'L1c', 'P44', '2026-09-15', 'Koefisien_OT', 1, 'dipotong Max_Hours_Workday = 1 jam'),
       (4, 'L2', 'A3', '2026-09-14', 'Overtime_Shift', 210, 'pulang 05:30, di tengah porsi lembur'),
       (5, 'L3', 'D11', '2026-09-14', 'Overtime_Shift', 360, 'pulang 10:00, lewat jam keluar, diplafon'),
       (6, 'L4', 'P44', '2026-09-14', 'Overtime_Shift', 0, 'pulang 01:00, sebelum porsi lembur mulai'),
       (7, 'L5', 'A3', '2026-09-17', 'Overtime_Shift', 0, 'gerbang CheckOut IS NOT NULL'),
       (8, 'L6', 'A38', '2026-09-14', 'Overtime_Shift', 240, 'EMI-LS2 plafon 4 jam terpenuhi penuh'),
       (9, 'L6b', 'A38', '2026-09-15', 'Overtime_Shift', 120, 'pulang 05:00, plafon 4 jam'),
       (10, 'L7', 'K32', '2026-09-14', 'Overtime_Shift', 0, 'ID_Level 5 di luar (1,2,21)'),
       (11, 'L8', 'C9', '2026-09-14', 'Overtime_Shift', 0, 'ID_Level 10 di luar (1,2,21)'),
       (12, 'L9', 'A3', '2026-09-16', 'Overtime_Shift', 360, 'telat TIDAK memotong Overtime_Shift'),
       (13, 'L9b', 'A3', '2026-09-16', 'RealLate', 45, 'masuk 20:45 atas shift 20:00, toleransi 0'),
       (14, 'L9c', 'A3', '2026-09-16', 'Overtime_Gross', 360, 'sebelum potongan telat'),
       (15, 'L9d', 'A3', '2026-09-16', 'Overtime_Calculated', 315, '360 - 45, telat memotong HASIL AKHIR'),
       (16, 'L10', 'D11', '2026-09-16', 'Overtime_Shift', 0, 'hari OFF, bukan WORK'),
       (17, 'L11', 'D11', '2026-09-15', 'Overtime', 120, 'pengajuan LEMBUR estimasi 08:00-10:00'),
       (18, 'L11b', 'D11', '2026-09-15', 'Overtime_Shift', 360, 'long shift tetap jalan berbarengan'),
       (19, 'L11c', 'D11', '2026-09-15', 'Overtime_Calculated', 480, '120 + 360, keduanya dijumlahkan'),
       (20, 'L12', 'A1', '2026-09-14', 'Overtime_Shift', 0, 'gerbang Status_Karyawan JOIN, A1 dilaporkan RESIGN');

-- ---------------------------------------------------------------------
-- 1) RINGKASAN
-- ---------------------------------------------------------------------
SELECT Total = COUNT(*),
       Lulus = SUM(CASE WHEN p.Nyata = p.Harap THEN 1 ELSE 0 END),
       Gagal = SUM(CASE WHEN p.Nyata = p.Harap THEN 0 ELSE 1 END)
FROM (SELECT h.Harap,
             Nyata = CASE h.Kolom
                   WHEN 'Overtime_Shift' THEN r.Overtime_shift
                   WHEN 'Overtime' THEN r.Overtime
                   WHEN 'Overtime_Before' THEN r.Overtime_Before
                   WHEN 'Overtime_Real' THEN r.Overtime_Real
                   WHEN 'Overtime_Gross' THEN r.Overtime_Gross
                   WHEN 'Overtime_Calculated' THEN r.Overtime_Calculated
                   WHEN 'RealLate' THEN r.RealLate
                   WHEN 'Koefisien_OT' THEN r.Koefisien_OT
                   WHEN 'Koefisien_OT_Gross' THEN r.Koefisien_OT_Gross
               END
      FROM @Harap h
               LEFT JOIN @Hasil r
                         ON r.Kode_Karyawan = h.Kode_Karyawan
                             AND CAST(r.Tanggal AS date) = h.Tanggal) p;

-- ---------------------------------------------------------------------
-- 2) PER PERIKSA
-- ---------------------------------------------------------------------
SELECT h.Kode,
       h.Kode_Karyawan,
       Nama       = r.Nama,
       Tanggal    = h.Tanggal,
       StatusHari = r.StatusHari,
       Shift      = r.Nama_Shift,
       Jam_Shift  = CONVERT(varchar(16), r.Temp_JamMasuk, 120) + ' -> ' +
                    CONVERT(varchar(16), r.Temp_JamKeluar, 120),
       CheckIn    = CONVERT(varchar(16), r.CheckIn, 120),
       CheckOut   = CONVERT(varchar(16), r.CheckOut, 120),
       Kolom      = h.Kolom,
       Harap      = h.Harap,
       Nyata      = CASE h.Kolom
                   WHEN 'Overtime_Shift' THEN r.Overtime_shift
                   WHEN 'Overtime' THEN r.Overtime
                   WHEN 'Overtime_Before' THEN r.Overtime_Before
                   WHEN 'Overtime_Real' THEN r.Overtime_Real
                   WHEN 'Overtime_Gross' THEN r.Overtime_Gross
                   WHEN 'Overtime_Calculated' THEN r.Overtime_Calculated
                   WHEN 'RealLate' THEN r.RealLate
                   WHEN 'Koefisien_OT' THEN r.Koefisien_OT
                   WHEN 'Koefisien_OT_Gross' THEN r.Koefisien_OT_Gross
               END,
       Hasil      = CASE
                        WHEN r.Kode_Karyawan IS NULL THEN 'BARIS TIDAK ADA'
                        WHEN (CASE h.Kolom
                   WHEN 'Overtime_Shift' THEN r.Overtime_shift
                   WHEN 'Overtime' THEN r.Overtime
                   WHEN 'Overtime_Before' THEN r.Overtime_Before
                   WHEN 'Overtime_Real' THEN r.Overtime_Real
                   WHEN 'Overtime_Gross' THEN r.Overtime_Gross
                   WHEN 'Overtime_Calculated' THEN r.Overtime_Calculated
                   WHEN 'RealLate' THEN r.RealLate
                   WHEN 'Koefisien_OT' THEN r.Koefisien_OT
                   WHEN 'Koefisien_OT_Gross' THEN r.Koefisien_OT_Gross
               END) = h.Harap THEN 'LULUS'
                        ELSE 'GAGAL' END,
       h.Keterangan
FROM @Harap h
         LEFT JOIN @Hasil r
                   ON r.Kode_Karyawan = h.Kode_Karyawan
                       AND CAST(r.Tanggal AS date) = h.Tanggal
ORDER BY h.Urut;

-- ---------------------------------------------------------------------
-- 3) KONTEKS MENTAH (satu baris per karyawan+tanggal yang diuji)
-- ---------------------------------------------------------------------
SELECT DISTINCT r.Kode_Karyawan,
                r.Nama,
                r.Tanggal,
                r.StatusHari,
                r.Nama_Shift,
                r.Temp_JamMasuk,
                r.Temp_JamKeluar,
                r.CheckIn,
                r.CheckOut,
                r.RealLate,
                r.Jenis_Lembur,
                r.No_Transaksi_Lembur,
                r.Overtime,
                r.Overtime_Before,
                r.Overtime_shift,
                r.Overtime_Real,
                r.Overtime_Gross,
                r.Overtime_Calculated,
                r.Koefisien_OT_Gross,
                r.Koefisien_OT,
                r.Flag_Lembur,
                r.Flag_Sakit_Izin_Cuti,
                r.Status_Karyawan
FROM @Harap h
         JOIN @Hasil r
              ON r.Kode_Karyawan = h.Kode_Karyawan
                  AND CAST(r.Tanggal AS date) = h.Tanggal
ORDER BY r.Kode_Karyawan, r.Tanggal;

/* =====================================================================
   EXEC POLOS -- keluaran SP apa adanya, tanpa pembanding.
   Hapus komentar lalu jalankan sendiri bila perlu.

   EXEC dbo.HRIS_SP_GET_ABSEN_NEW_V3
        @KodePerusahaan = '001',
        @TglAwal        = '2026-09-14',
        @TglAkhir       = '2026-09-17',
        @UserID         = '81090,81072,81130,81143,81037,81016,90',
        @Pembeda        = NULL,
        @UserID_Cetak   = 'UJI_LS';
   ===================================================================== */
