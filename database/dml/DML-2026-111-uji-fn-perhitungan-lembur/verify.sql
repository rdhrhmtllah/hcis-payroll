/* =====================================================================
   DML-2026-111 -- VERIFIKASI dbo.N_HRIS_FN_CalculateOvertime
   Target : Web_HRIS (DEVELOPMENT). Read-only, tidak mengubah data apa pun.

   Jalankan setelah data-change.sql. Skrip menghasilkan 3 tabel:
     1) RINGKASAN   -- jumlah lulus / gagal
     2) PER SKENARIO -- harapan vs nyata, LULUS / GAGAL
     3) KONTEKS MENTAH -- CheckIn/CheckOut/estimasi/jam shift per baris

   Kolom yang dibaca:
     Overtime        -> LEMBUR & LEMBUR_LIBUR   (kelompok A, C, D)
     Overtime_Before -> LEMBUR_AWAL             (kelompok B)
   Keduanya dalam MENIT, nilai mentah dari fungsi (tidak diubah lagi oleh SP).

   Kalau hanya ingin melihat keluaran SP apa adanya, jalankan bagian
   "EXEC POLOS" di paling bawah berkas ini.
   ===================================================================== */

SET NOCOUNT ON;

-- Sengaja memakai variabel tabel, bukan tabel temporary, supaya skrip ini
-- sama sekali tidak memuat pernyataan penghapusan objek.
-- Bentuk kolom mengikuti PERSIS urutan result set HRIS_SP_GET_ABSEN_NEW_V3.
-- Panjang varchar sengaja dilebihkan; INSERT..EXEC boleh melebarkan.
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
     @TglAwal = '2026-09-13',
     @TglAkhir = '2026-09-14',
     @UserID = '80111111,81077,81014,81032,81110,81064,81027,81078,83,81015,81035,81150,81080,81145,140,81013,81098,44',
     @Pembeda = NULL,
     @UserID_Cetak = 'UJI_FN';

-- Matriks harapan (lihat data-change.sql untuk rinciannya).
DECLARE @Harap TABLE
(
    Kode          varchar(4),
    Kode_Karyawan varchar(20),
    Tanggal       date,
    Kolom         varchar(20),
    Harap         int,
    Keterangan    varchar(80)
);

INSERT INTO @Harap (Kode, Kode_Karyawan, Tanggal, Kolom, Harap, Keterangan)
VALUES ('A1', 'D12', '2026-09-14', 'Overtime', 180, 'LEMBUR kasus pas'),
       ('A2', 'F20', '2026-09-14', 'Overtime', 135, 'LEMBUR pulang lebih awal = aktual'),
       ('A3', 'F22', '2026-09-14', 'Overtime', 180, 'LEMBUR pulang lewat estimasi = diplafon'),
       ('A4', 'F23', '2026-09-14', 'Overtime', 180, 'LEMBUR jeda sebelum estimasi mulai tak dibayar'),
       ('A5', 'F40', '2026-09-14', 'Overtime', 0, 'LEMBUR pulang sebelum jam keluar shift'),
       ('A6', 'G25', '2026-09-14', 'Overtime', 0, 'LEMBUR tanpa checkout'),
       ('B1', 'H33', '2026-09-14', 'Overtime_Before', 120, 'LEMBUR_AWAL kasus pas'),
       ('B2', 'I27', '2026-09-14', 'Overtime_Before', 80, 'LEMBUR_AWAL datang telat = potong depan'),
       ('B3', 'J31', '2026-09-14', 'Overtime_Before', 120, 'LEMBUR_AWAL dipotong jam masuk shift'),
       ('B4', 'M37', '2026-09-14', 'Overtime_Before', 0, 'LEMBUR_AWAL datang setelah jam masuk'),
       ('C1', 'N42', '2026-09-13', 'Overtime', 240, 'LEMBUR_LIBUR kasus pas'),
       ('C2', 'N43', '2026-09-13', 'Overtime', 210, 'LEMBUR_LIBUR datang telat'),
       ('C3', 'R46', '2026-09-13', 'Overtime', 360, 'LEMBUR_LIBUR FULL AKTUAL tanpa plafon'),
       ('C4', 'R47', '2026-09-13', 'Overtime', 0, 'LEMBUR_LIBUR pulang sebelum estimasi mulai'),
       ('D1', 'R48', '2026-09-14', 'Overtime', 0, 'Penjaga: CheckOut <= CheckIn'),
       ('D2', 'R49', '2026-09-14', 'Overtime', 0, 'Penjaga: estimasi terbalik'),
       ('D3', 'R50', '2026-09-13', 'Overtime', 0, 'Penjaga: LEMBUR di hari OFF'),
       ('D4', 'R52', '2026-09-14', 'Overtime', 0, 'Penjaga: LEMBUR_LIBUR di hari WORK');

-- ---------------------------------------------------------------------
-- 1) RINGKASAN
-- ---------------------------------------------------------------------
SELECT Total = COUNT(*),
       Lulus = SUM(CASE WHEN p.Nyata = p.Harap THEN 1 ELSE 0 END),
       Gagal = SUM(CASE WHEN p.Nyata = p.Harap THEN 0 ELSE 1 END)
FROM (SELECT h.Harap,
             Nyata = CASE WHEN h.Kolom = 'Overtime_Before' THEN r.Overtime_Before ELSE r.Overtime END
      FROM @Harap h
               LEFT JOIN @Hasil r
                         ON r.Kode_Karyawan = h.Kode_Karyawan
                             AND CAST(r.Tanggal AS date) = h.Tanggal) p;

-- ---------------------------------------------------------------------
-- 2) PER SKENARIO
-- ---------------------------------------------------------------------
SELECT h.Kode,
       h.Kode_Karyawan,
       Nama          = r.Nama,
       Tanggal       = h.Tanggal,
       StatusHari    = r.StatusHari,
       Jenis         = r.Jenis_Lembur,
       Estimasi      = CONVERT(varchar(5), COALESCE(r.Tanggal_Lembur_Dari_web, r.Tanggal_Lembur_Awal_Dari_web), 108)
                           + ' - ' +
                       CONVERT(varchar(5), COALESCE(r.Tanggal_Lembur_Sampai_web, r.Tanggal_Lembur_Awal_Sampai_web), 108),
       CheckIn       = CONVERT(varchar(5), r.CheckIn, 108),
       CheckOut      = CONVERT(varchar(5), r.CheckOut, 108),
       Jam_Shift     = CONVERT(varchar(5), r.Temp_JamMasuk, 108) + ' - ' +
                       CONVERT(varchar(5), r.Temp_JamKeluar, 108),
       Kolom         = h.Kolom,
       Harap_Menit   = h.Harap,
       Nyata_Menit   = CASE WHEN h.Kolom = 'Overtime_Before' THEN r.Overtime_Before ELSE r.Overtime END,
       Hasil         = CASE
                           WHEN r.Kode_Karyawan IS NULL THEN 'BARIS TIDAK ADA'
                           WHEN (CASE WHEN h.Kolom = 'Overtime_Before' THEN r.Overtime_Before ELSE r.Overtime END) =
                                h.Harap THEN 'LULUS'
                           ELSE 'GAGAL' END,
       h.Keterangan
FROM @Harap h
         LEFT JOIN @Hasil r
                   ON r.Kode_Karyawan = h.Kode_Karyawan
                       AND CAST(r.Tanggal AS date) = h.Tanggal
ORDER BY h.Kode;

-- ---------------------------------------------------------------------
-- 3) KONTEKS MENTAH (semua kolom lembur yang relevan)
-- ---------------------------------------------------------------------
SELECT h.Kode,
       r.Kode_Karyawan,
       r.Nama,
       r.Tanggal,
       r.StatusHari,
       r.Jenis_Lembur,
       r.No_Transaksi_Lembur,
       r.Tanggal_Lembur_Dari_web,
       r.Tanggal_Lembur_Sampai_web,
       r.No_Transaksi_Lembur_Awal,
       r.Tanggal_Lembur_Awal_Dari_web,
       r.Tanggal_Lembur_Awal_Sampai_web,
       r.CheckIn,
       r.CheckOut,
       r.Temp_JamMasuk,
       r.Temp_JamKeluar,
       r.Overtime,
       r.Overtime_Before,
       r.Flag_Lembur_Libur,
       r.Flag_Lembur_Awal,
       r.Koefisien_OT
FROM @Harap h
         LEFT JOIN @Hasil r
                   ON r.Kode_Karyawan = h.Kode_Karyawan
                       AND CAST(r.Tanggal AS date) = h.Tanggal
ORDER BY h.Kode;

/* =====================================================================
   EXEC POLOS -- keluaran SP apa adanya, tanpa pembanding.
   Hapus komentar lalu jalankan sendiri bila perlu.

   EXEC dbo.HRIS_SP_GET_ABSEN_NEW_V3
        @KodePerusahaan = '001',
        @TglAwal        = '2026-09-13',
        @TglAkhir       = '2026-09-14',
        @UserID         = '80111111,81077,81014,81032,81110,81064,81027,81078,83,81015,81035,81150,81080,81145,140,81013,81098,44',
        @Pembeda        = NULL,
        @UserID_Cetak   = 'UJI_FN';
   ===================================================================== */
