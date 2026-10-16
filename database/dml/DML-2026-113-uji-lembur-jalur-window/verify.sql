/* =====================================================================
   DML-2026-113 -- VERIFIKASI jalur window, gerbang, dan kombinasi lembur
   Target : Web_HRIS (DEVELOPMENT). Read-only, tidak mengubah data apa pun.

   Jalankan setelah data-change.sql. Skrip menghasilkan 4 tabel:
     1) RINGKASAN      -- jumlah lulus / gagal
     2) PER PERIKSA    -- harapan vs nyata per kolom, LULUS / GAGAL
     3) NON-DETERMINISTIK -- skenario M1, dilaporkan apa adanya
     4) KONTEKS MENTAH -- satu baris per (karyawan, tanggal) yang diuji

   Kolom semu 'CheckOut_Terisi' bernilai 1 bila CheckOut tidak NULL, 0 bila NULL.
   Dipakai untuk membuktikan bahwa W3 kehilangan tap pulangnya sedangkan W4 tidak.

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
     @TglAkhir = '2026-09-18',
     @UserID = '81066,81021,81081,81154,81072,81143,81055,116,117,81037,82',
     @Pembeda = NULL,
     @UserID_Cetak = 'UJI_WIN';

-- Matriks harapan (lihat data-change.sql untuk rinciannya).
DECLARE @Harap TABLE
(
    Urut          int,
    Kode          varchar(6),
    Kode_Karyawan varchar(20),
    Tanggal       date,
    Kolom         varchar(30),
    Harap         int,
    Keterangan    varchar(95)
);

INSERT INTO @Harap (Urut, Kode, Kode_Karyawan, Tanggal, Kolom, Harap, Keterangan)
VALUES
       (1, 'W1', 'R53', '2026-09-14', 'Overtime', 180, 'jalur window kasus normal'),
       (2, 'W2', 'S55', '2026-09-14', 'RealLate', 15, 'CheckIn = tap PALING AWAL 08:45, bukan 08:50'),
       (3, 'W2b', 'S55', '2026-09-14', 'Overtime', 170, 'CheckOut = tap PALING AKHIR 19:20, bukan 19:00'),
       (4, 'W2c', 'S55', '2026-09-14', 'Overtime_Calculated', 155, '170 dikurangi telat 15'),
       (5, 'W3', 'S56', '2026-09-14', 'CheckOut_Terisi', 0, 'tap 23:45 di luar window, CheckOut hilang'),
       (6, 'W3b', 'S56', '2026-09-14', 'Overtime', 0, 'akibatnya lembur tidak terhitung sama sekali'),
       (7, 'W4', 'T57', '2026-09-14', 'CheckOut_Terisi', 1, 'tap 23:45 SAMA, window lebih lebar, tertangkap'),
       (8, 'W4b', 'T57', '2026-09-14', 'Overtime', 330, 'diplafon di estimasi 22:00'),
       (9, 'W5', 'A3', '2026-09-18', 'Overtime_Shift', 360, 'window lintas hari pada shift malam'),
       (10, 'S1', 'P44', '2026-09-17', 'Flag_SIC_Ya', 1, 'sakit disetujui terdeteksi'),
       (11, 'S1b', 'P44', '2026-09-17', 'Overtime_Shift', 0, 'long shift tertutup gerbang sakit'),
       (12, 'S2', 'T36', '2026-09-14', 'Flag_SIC_Ya', 1, 'sakit disetujui terdeteksi'),
       (13, 'S2b', 'T36', '2026-09-14', 'Overtime', 180, 'lembur pengajuan TETAP dibayar walau sakit'),
       (14, 'X1', 'T58', '2026-09-14', 'Overtime', 0, 'header Status=Y, transaksi diabaikan SP'),
       (15, 'AB1', 'V59', '2026-09-14', 'Overtime_Before', 120, 'lembur awal'),
       (16, 'AB1b', 'V59', '2026-09-14', 'Overtime', 180, 'lembur setelah shift, hari yang sama'),
       (17, 'AB1c', 'V59', '2026-09-14', 'Overtime_Real', 300, 'keduanya dijumlahkan'),
       (18, 'AB1d', 'V59', '2026-09-14', 'Overtime_Calculated', 300, 'tanpa potongan telat'),
       (19, 'AB1e', 'V59', '2026-09-14', 'Koefisien_OT_Gross', 5, 'FLOOR(300/60) sebelum plafon harian'),
       (20, 'NA1', 'A38', '2026-09-16', 'Overtime_Before', 120, 'lembur awal pada shift malam'),
       (21, 'NA1b', 'A38', '2026-09-16', 'Overtime_Shift', 240, 'long shift tetap jalan'),
       (22, 'NA1c', 'A38', '2026-09-16', 'Overtime_Calculated', 360, '120 + 240'),
       (23, 'M1', 'W60', '2026-09-14', 'Overtime_180_atau_300', 1, 'nilainya salah satu dari 180 atau 300');

-- ---------------------------------------------------------------------
-- 1) RINGKASAN
-- ---------------------------------------------------------------------
SELECT Total = COUNT(*),
       Lulus = SUM(CASE WHEN p.Nyata = p.Harap THEN 1 ELSE 0 END),
       Gagal = SUM(CASE WHEN p.Nyata = p.Harap THEN 0 ELSE 1 END)
FROM (SELECT h.Harap,
             Nyata = CASE h.Kolom
                   WHEN 'Overtime' THEN r.Overtime
                   WHEN 'Overtime_Before' THEN r.Overtime_Before
                   WHEN 'Overtime_Shift' THEN r.Overtime_shift
                   WHEN 'Overtime_Real' THEN r.Overtime_Real
                   WHEN 'Overtime_Gross' THEN r.Overtime_Gross
                   WHEN 'Overtime_Calculated' THEN r.Overtime_Calculated
                   WHEN 'RealLate' THEN r.RealLate
                   WHEN 'Koefisien_OT' THEN r.Koefisien_OT
                   WHEN 'Koefisien_OT_Gross' THEN r.Koefisien_OT_Gross
                   WHEN 'CheckOut_Terisi' THEN CASE WHEN r.CheckOut IS NULL THEN 0 ELSE 1 END
                   WHEN 'Flag_SIC_Ya' THEN CASE WHEN r.Flag_Sakit_Izin_Cuti = 'Y' THEN 1 ELSE 0 END
                   WHEN 'Overtime_180_atau_300' THEN CASE WHEN r.Overtime IN (180, 300) THEN 1 ELSE 0 END
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
       CheckIn    = CONVERT(varchar(16), r.CheckIn, 120),
       CheckOut   = CONVERT(varchar(16), r.CheckOut, 120),
       Window_In  = CONVERT(varchar(16), r.Temp_BCI, 120) + ' .. ' + CONVERT(varchar(16), r.Temp_ECI, 120),
       Window_Out = CONVERT(varchar(16), r.Temp_BCO, 120) + ' .. ' + CONVERT(varchar(16), r.Temp_ECO, 120),
       Kolom      = h.Kolom,
       Harap      = h.Harap,
       Nyata      = CASE h.Kolom
                   WHEN 'Overtime' THEN r.Overtime
                   WHEN 'Overtime_Before' THEN r.Overtime_Before
                   WHEN 'Overtime_Shift' THEN r.Overtime_shift
                   WHEN 'Overtime_Real' THEN r.Overtime_Real
                   WHEN 'Overtime_Gross' THEN r.Overtime_Gross
                   WHEN 'Overtime_Calculated' THEN r.Overtime_Calculated
                   WHEN 'RealLate' THEN r.RealLate
                   WHEN 'Koefisien_OT' THEN r.Koefisien_OT
                   WHEN 'Koefisien_OT_Gross' THEN r.Koefisien_OT_Gross
                   WHEN 'CheckOut_Terisi' THEN CASE WHEN r.CheckOut IS NULL THEN 0 ELSE 1 END
                   WHEN 'Flag_SIC_Ya' THEN CASE WHEN r.Flag_Sakit_Izin_Cuti = 'Y' THEN 1 ELSE 0 END
                   WHEN 'Overtime_180_atau_300' THEN CASE WHEN r.Overtime IN (180, 300) THEN 1 ELSE 0 END
               END,
       Hasil      = CASE
                        WHEN r.Kode_Karyawan IS NULL THEN 'BARIS TIDAK ADA'
                        WHEN (CASE h.Kolom
                   WHEN 'Overtime' THEN r.Overtime
                   WHEN 'Overtime_Before' THEN r.Overtime_Before
                   WHEN 'Overtime_Shift' THEN r.Overtime_shift
                   WHEN 'Overtime_Real' THEN r.Overtime_Real
                   WHEN 'Overtime_Gross' THEN r.Overtime_Gross
                   WHEN 'Overtime_Calculated' THEN r.Overtime_Calculated
                   WHEN 'RealLate' THEN r.RealLate
                   WHEN 'Koefisien_OT' THEN r.Koefisien_OT
                   WHEN 'Koefisien_OT_Gross' THEN r.Koefisien_OT_Gross
                   WHEN 'CheckOut_Terisi' THEN CASE WHEN r.CheckOut IS NULL THEN 0 ELSE 1 END
                   WHEN 'Flag_SIC_Ya' THEN CASE WHEN r.Flag_Sakit_Izin_Cuti = 'Y' THEN 1 ELSE 0 END
                   WHEN 'Overtime_180_atau_300' THEN CASE WHEN r.Overtime IN (180, 300) THEN 1 ELSE 0 END
               END) = h.Harap THEN 'LULUS'
                        ELSE 'GAGAL' END,
       h.Keterangan
FROM @Harap h
         LEFT JOIN @Hasil r
                   ON r.Kode_Karyawan = h.Kode_Karyawan
                       AND CAST(r.Tanggal AS date) = h.Tanggal
ORDER BY h.Urut;

-- ---------------------------------------------------------------------
-- 3) NON-DETERMINISTIK -- skenario M1 dilaporkan apa adanya
-- ---------------------------------------------------------------------
SELECT Skenario            = 'M1',
       r.Kode_Karyawan,
       r.Nama,
       r.Tanggal,
       Estimasi_Yang_Menang = CONVERT(varchar(16), r.Tanggal_Lembur_Dari_web, 120) + ' - ' +
                              CONVERT(varchar(16), r.Tanggal_Lembur_Sampai_web, 120),
       No_Transaksi_Menang  = r.No_Transaksi_Lembur,
       r.CheckOut,
       r.Overtime,
       Jumlah_Transaksi     = (SELECT COUNT(*)
                               FROM dbo.Transaksi_Lembur_Det d
                                        JOIN dbo.Transaksi_Lembur_Detail dt
                                             ON dt.Kode_Perusahaan = d.Kode_Perusahaan
                                                 AND dt.No_Transaksi = d.No_Transaksi
                                                 AND dt.Urut_Oto = d.Urut_Detail
                                        JOIN dbo.Transaksi_Lembur hh
                                             ON hh.Kode_Perusahaan = d.Kode_Perusahaan
                                                 AND hh.No_Transaksi = d.No_Transaksi
                               WHERE d.Kode_Perusahaan = '001'
                                 AND d.Kode_Karyawan = r.Kode_Karyawan
                                 AND CAST(d.Tanggal_Lembur AS date) = CAST(r.Tanggal AS date)
                                 AND hh.Status IS NULL
                                 AND dt.Jenis <> 'LEMBUR_AWAL')
FROM @Hasil r
WHERE r.Kode_Karyawan = 'W60'
  AND CAST(r.Tanggal AS date) = '2026-09-14';

-- ---------------------------------------------------------------------
-- 4) KONTEKS MENTAH
-- ---------------------------------------------------------------------
SELECT DISTINCT r.Kode_Karyawan,
                r.Nama,
                r.Tanggal,
                r.StatusHari,
                r.Nama_Shift,
                r.Temp_JamMasuk,
                r.Temp_JamKeluar,
                r.Temp_BCI,
                r.Temp_ECI,
                r.Temp_BCO,
                r.Temp_ECO,
                r.CheckIn,
                r.CheckOut,
                r.RealLate,
                r.Jenis_Lembur,
                r.No_Transaksi_Lembur,
                r.No_Transaksi_Lembur_Awal,
                r.Overtime,
                r.Overtime_Before,
                r.Overtime_shift,
                r.Overtime_Real,
                r.Overtime_Calculated,
                r.Koefisien_OT_Gross,
                r.Koefisien_OT,
                r.Flag_Sakit_Izin_Cuti,
                r.Flag_Alpha,
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
        @TglAkhir       = '2026-09-18',
        @UserID         = '81066,81021,81081,81154,81072,81143,81055,116,117,81037,82',
        @Pembeda        = NULL,
        @UserID_Cetak   = 'UJI_WIN';
   ===================================================================== */
