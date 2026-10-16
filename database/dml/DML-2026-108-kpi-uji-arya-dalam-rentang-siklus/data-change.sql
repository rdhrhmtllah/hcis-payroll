/* Change ID: DML-2026-108-kpi-uji-arya-dalam-rentang-siklus
   Target: Web_HRIS DEVELOPMENT SAJA.

   *** JANGAN DIREKAM KE SQL TRACKER, DAN JANGAN DIJALANKAN DI PRODUCTION. ***

   Ini DATA UJI, bukan perbaikan. Ia menuliskan rencana dan realisasi KPI untuk satu
   karyawan supaya alur Performance Appraisal bisa ditelusuri sampai ujung di lingkungan
   pengembangan. Menjalankannya di production berarti mengarang pencapaian seseorang.

   Scope: KPI_Pengajuan_Det, KPI_Update_Performance, KPI_Update_Performance_Detail —
          hanya baris milik pengajuan KPI karyawan C9 (Arya) tahun 2026.

   YANG DISELESAIKAN

   Hasil PA Arya pada siklus PA-2026-03 berhenti di `TERTAHAN` dengan alasan:

       "Tidak ada KPI disetujui pada rentang periode siklus. Sistem menandai, admin yang
        memutuskan — bukan otomatis nol."

   Alasannya benar dan datanya memang begitu. Siklus menilai Januari–Juni 2026, sedangkan
   seluruh KPI Arya hanya punya satu periode: September 2026 — di luar rentang. Pilar
   kompetensinya sudah lengkap (80,27 dari enam penilai), tetapi pilar KPI-nya kosong, dan
   mesin menolak menggantinya dengan nol.

   KENAPA MENGISI, BUKAN MENGGESER PERIODE SIKLUS

   Menggeser rentang siklus supaya memuat September akan "menyelesaikan" masalah Arya dan
   sekaligus mengubah dasar penilaian 858 peserta lain yang datanya sudah benar. Yang kurang
   satu orang, jadi yang ditambal satu orang.

   YANG DITULIS

   Enam periode Januari–Juni 2026 pada parameter yang sudah ada (bobotnya sudah 100, tidak
   disentuh), masing-masing dengan target dan satu realisasi yang sudah disetujui dan
   diverifikasi — tiga syarat yang dibaca mesin PA: `Flag_Aktif`, `Flag_Approval`, dan
   `Flag_Verifikasi` semuanya 'Y' dengan `Status` NULL.

   Bentuk barisnya DISALIN dari baris September yang sudah ada (Id_Update_Performance 203),
   bukan dikarang: kolom yang sama, penanda yang sama, format `No_Transaksi` yang sama.
   Baris uji yang bentuknya berbeda dari baris sungguhan akan lolos di sini lalu gagal di
   layar KPI, dan yang salah tampak seperti layarnya.

   IDEMPOTEN

   Tiap sisipan dijaga `NOT EXISTS` pada kunci alaminya, jadi menjalankannya dua kali tidak
   menggandakan apa pun.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50550, 'DML-2026-108 adalah data uji dan hanya untuk Web_HRIS pengembangan.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* ------------------------------------------------------------------ *
 * Jangkar — dicari, bukan ditulis sebagai angka                       *
 * ------------------------------------------------------------------ *
 * Id berbeda antar-lingkungan dan antar-pemulihan basis data. Angka yang ditulis tangan
 * akan menempelkan realisasi ke rencana milik orang lain, dan hasilnya tetap tampak wajar. */

DECLARE @kode varchar(50) = 'C9';
DECLARE @tahun varchar(4) = '2026';

DECLARE @user int = (
    SELECT MIN(Id_Users) FROM KPI_Users WHERE LTRIM(RTRIM(Kode_Users)) = @kode
);

IF @user IS NULL
    THROW 50551, 'Karyawan C9 tidak punya akun KPI; tidak ada tempat menempelkan rencananya.', 1;

DECLARE @pengajuan int, @penjadwalan int;

SELECT @pengajuan = p.Id_Pengajuan, @penjadwalan = p.Id_Penjadwalan
FROM KPI_Pengajuan p
JOIN KPI_Master_Penjadwalan mp ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan
WHERE p.Id_User = @user
  AND mp.Tahun = @tahun
  AND p.Flag_Aktif = 'Y'
  AND p.Flag_Approval = 'Y'
  AND p.Status IS NULL;

IF @pengajuan IS NULL
    THROW 50552, 'Tidak ada pengajuan KPI 2026 yang disetujui untuk C9; rencananya harus ada lebih dulu.', 1;

/* Bulan yang dinilai siklus PA-2026-03, beserta capaian yang ditulis. Angkanya
   sengaja beragam: enam bulan yang seragam membuat rata-rata terlihat benar walaupun
   penjumlahannya salah. */
DECLARE @periode TABLE (Periode varchar(7) PRIMARY KEY, Target float, Actual float);

INSERT INTO @periode (Periode, Target, Actual) VALUES
    ('2026-01', 100, 92),
    ('2026-02', 100, 88),
    ('2026-03', 100, 95),
    ('2026-04', 100, 90),
    ('2026-05', 100, 97),
    ('2026-06', 100, 93);

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Target per bulan                                                 *
 * ------------------------------------------------------------------ *
 * Untuk SETIAP parameter pengajuan itu, bukan hanya yang pertama: bobotnya sudah berjumlah
 * 100 di antara mereka, dan melewatkan satu parameter membuat totalnya timpang.          */

INSERT INTO KPI_Pengajuan_Det (Id_Pengajuan_Detail, Nama_Target, Nilai_Target, Periode)
SELECT pd.Id_Pengajuan_Detail, 'Target uji PA ' + pr.Periode, pr.Target, pr.Periode
FROM KPI_Pengajuan_Detail pd
CROSS JOIN @periode pr
WHERE pd.Id_Pengajuan = @pengajuan
  AND NOT EXISTS (
        SELECT 1 FROM KPI_Pengajuan_Det d
        WHERE d.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail
          AND d.Periode = pr.Periode
      );

/* ------------------------------------------------------------------ *
 * 2. Kepala realisasi, satu per bulan                                 *
 * ------------------------------------------------------------------ *
 * `No_Transaksi` mengikuti pola AKPI-MM-YYYY-NNNN yang sudah dipakai, dan nomornya
 * dilanjutkan dari yang tertinggi pada bulan itu. Nomor yang bertabrakan tidak selalu
 * ditolak basis data, tetapi layar KPI menampilkan dua transaksi bernomor sama tanpa
 * memberi tahu mana yang mana.                                                          */

INSERT INTO KPI_Update_Performance (
    No_Transaksi, Id_Penjadwalan, Id_Pengajuan, Status, Periode,
    Total_Score, Total_Score_Achievement, Total_Bobot, Status_Pengajuan,
    Id_Performance, Id_User_Sender, Jam, Tanggal, Id_User,
    Flag_Approval, Flag_Team, Flag_Aktif, Flag_Verifikasi, Flag_Edit,
    Total_Score_Raw, Total_Score_Achievement_Raw, Flag_Capped
)
SELECT
    'AKPI-' + RIGHT(pr.Periode, 2) + '-' + @tahun + '-'
        + RIGHT('0000' + CAST(
            ISNULL((
                SELECT MAX(CAST(RIGHT(u.No_Transaksi, 4) AS int))
                FROM KPI_Update_Performance u
                WHERE u.No_Transaksi LIKE 'AKPI-' + RIGHT(pr.Periode, 2) + '-' + @tahun + '-%'
            ), 0)
            + ROW_NUMBER() OVER (PARTITION BY RIGHT(pr.Periode, 2) ORDER BY pr.Periode)
          AS varchar(4)), 4),
    @penjadwalan, @pengajuan, NULL, pr.Periode,
    pr.Actual, pr.Actual, 100, 'disetujui',
    1, @user, CONVERT(varchar(8), GETDATE(), 108), GETDATE(), @user,
    'Y', 'Y', 'Y', 'Y', 'Y',
    pr.Actual, pr.Actual, 'T'
FROM @periode pr
WHERE NOT EXISTS (
        SELECT 1 FROM KPI_Update_Performance u
        WHERE u.Id_Pengajuan = @pengajuan AND u.Periode = pr.Periode
      );

/* ------------------------------------------------------------------ *
 * 3. Capaian per parameter                                            *
 * ------------------------------------------------------------------ *
 * Inilah satu-satunya baris yang benar-benar dibaca mesin PA: ia menjumlahkan
 * `Nilai_Actual` di sini dan `Nilai_Target` di langkah 1, lalu menghitung sendiri.     */

INSERT INTO KPI_Update_Performance_Detail (
    Id_Update_Performance, Id_Pengajuan_Det, Kode_Karyawan,
    Nilai_Actual, Score, Achievement, Flag_Pica, Flag_Capped
)
SELECT up.Id_Update_Performance, d.Id_Pengajuan_Det, @kode,
       pr.Actual, pr.Actual, pr.Actual, 'T', 'T'
FROM @periode pr
JOIN KPI_Update_Performance up
    ON up.Id_Pengajuan = @pengajuan AND up.Periode = pr.Periode
JOIN KPI_Pengajuan_Detail pd
    ON pd.Id_Pengajuan = @pengajuan
JOIN KPI_Pengajuan_Det d
    ON d.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail AND d.Periode = pr.Periode
WHERE NOT EXISTS (
        SELECT 1 FROM KPI_Update_Performance_Detail x
        WHERE x.Id_Update_Performance = up.Id_Update_Performance
          AND x.Id_Pengajuan_Det = d.Id_Pengajuan_Det
      );

COMMIT TRANSACTION;
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ *
 * Yang diperiksa bukan "berapa baris tersisip", melainkan apakah mesin PA sekarang bisa
 * melihatnya — dengan penyaring yang sama persis dengan yang dipakai perhitungan.       */

DECLARE @user2 int = (SELECT MIN(Id_Users) FROM KPI_Users WHERE LTRIM(RTRIM(Kode_Users)) = 'C9');

DECLARE @terbaca int = (
    SELECT COUNT(*)
    FROM KPI_Pengajuan_Detail pd
    JOIN KPI_Pengajuan p
        ON p.Id_Pengajuan = pd.Id_Pengajuan
       AND p.Flag_Aktif = 'Y' AND p.Flag_Approval = 'Y' AND p.Status IS NULL
    JOIN KPI_Master_Penjadwalan mp
        ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan AND mp.Tahun = '2026'
    JOIN KPI_Pengajuan_Det pdet
        ON pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail
       AND pdet.Periode IN ('2026-01','2026-02','2026-03','2026-04','2026-05','2026-06')
    JOIN KPI_Update_Performance_Detail upd
        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
    JOIN KPI_Update_Performance up
        ON up.Id_Update_Performance = upd.Id_Update_Performance
       AND up.Flag_Aktif = 'Y' AND up.Flag_Approval = 'Y'
       AND up.Flag_Verifikasi = 'Y' AND up.Status IS NULL
    WHERE p.Id_User = @user2
);

IF @terbaca = 0
    THROW 50553, 'Mesin PA masih tidak melihat satu pun KPI Arya pada rentang siklus; salah satu penanda persetujuan belum terpenuhi.', 1;

PRINT CONCAT('baris KPI yang terbaca mesin PA pada Jan-Jun 2026: ', @terbaca);

/* VERIFIKASI — target dan capaian per bulan. */
SELECT pdet.Periode, pdet.Nilai_Target, upd.Nilai_Actual, up.No_Transaksi,
       up.Flag_Approval, up.Flag_Verifikasi
FROM KPI_Pengajuan_Detail pd
JOIN KPI_Pengajuan p ON p.Id_Pengajuan = pd.Id_Pengajuan
JOIN KPI_Pengajuan_Det pdet ON pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail
LEFT JOIN KPI_Update_Performance_Detail upd ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
LEFT JOIN KPI_Update_Performance up ON up.Id_Update_Performance = upd.Id_Update_Performance
WHERE p.Id_User = @user2
ORDER BY pdet.Periode;
