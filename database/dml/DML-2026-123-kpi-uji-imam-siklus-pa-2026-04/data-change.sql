/* Change ID: DML-2026-123-kpi-uji-imam-siklus-pa-2026-04
   Target: Web_HRIS DEVELOPMENT SAJA.

   *** JANGAN DIREKAM KE SQL TRACKER, DAN JANGAN DIJALANKAN DI PRODUCTION. ***

   Ini DATA UJI, bukan perbaikan. Ia mengarang rencana dan realisasi KPI untuk satu
   karyawan supaya alur Performance Appraisal bisa ditelusuri sampai ujung di lingkungan
   pengembangan. Menjalankannya di production berarti mengarang pencapaian seseorang, dan
   angka karangan itu akan tampak persis seperti angka sungguhan.

   Saudara kandung DML-2026-108 (Arya/C9) dengan satu perbedaan penting yang menentukan
   isinya — lihat "KENAPA LEBIH PANJANG" di bawah.

   Scope: KPI_Pengajuan, KPI_Pengajuan_Detail, KPI_Pengajuan_Det,
          KPI_Update_Performance, KPI_Update_Performance_Detail —
          hanya baris milik karyawan J31 (Imam) tahun 2026.

   ── YANG DISELESAIKAN ─────────────────────────────────────────────────────────
   Hasil PA Imam pada siklus PA-2026-04 (Id 19456, menilai 2026-01-01..2026-09-30)
   berhenti di `TERTAHAN` dengan alasan:

       "Tidak ada KPI disetujui pada rentang periode siklus. Sistem menandai, admin yang
        memutuskan — bukan otomatis nol."

   Alasannya benar dan datanya memang begitu. Pilar kompetensinya sudah lengkap (64,84),
   tetapi pilar KPI-nya kosong, dan mesin menolak menggantinya dengan nol.

   ── KENAPA LEBIH PANJANG DARIPADA DML-2026-108 ────────────────────────────────
   Arya sudah punya pengajuan KPI 2026 yang disetujui; yang kurang hanya periodenya, jadi
   DML-108 cukup menempelkan bulan dan realisasi pada parameter yang sudah ada.

   Imam TIDAK punya satu baris pun di `KPI_Pengajuan` — diperiksa 11 September 2026: nol.
   Jadi seluruh rantainya harus dibuat, dari kepala pengajuan sampai realisasi. Itu juga
   sebabnya berkas ini lebih berhati-hati menyebut dirinya data karangan: yang dibuat bukan
   sekadar melengkapi rencana yang sudah disepakati seseorang, melainkan rencananya juga.

   ── BENTUKNYA DISALIN, BUKAN DIKARANG ─────────────────────────────────────────
   Kolom, penanda, dan format nomor diambil dari pengajuan C9 yang SUDAH terbukti terbaca
   mesin PA (`Id_Master_Formula` 3, `Id_Master_Satuan` 11, `Id_Master_Target` 3,
   `Id_Bsc_Kategori` 17, `Bobot` 100). Baris uji yang bentuknya berbeda dari baris sungguhan
   akan lolos di sini lalu gagal di layar KPI — dan yang salah akan tampak seperti layarnya.

   ── ANGKANYA ─────────────────────────────────────────────────────────────────
   Sembilan bulan (Januari–September 2026), target 100 per bulan, realisasi 88–97 yang
   BERAGAM. Seragam akan membuat rata-rata terlihat benar walaupun penjumlahannya salah —
   dan penjumlahan itulah yang dipakai: KPI YTD adalah AKUMULASI, bukan rata-rata bulanan.
   Total 836 dari 900, sehingga capaiannya sekitar 92,9 — di dalam jepitan 50–125 per
   parameter, jadi ia menguji jalur normal, bukan jalur cap.

   ── IDEMPOTEN ────────────────────────────────────────────────────────────────
   Tiap sisipan dijaga `NOT EXISTS` pada kunci alaminya, jadi menjalankannya dua kali tidak
   menggandakan apa pun.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50560, 'DML-2026-123 adalah data uji dan hanya untuk Web_HRIS pengembangan.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* ------------------------------------------------------------------ *
 * Jangkar — dicari, bukan ditulis sebagai angka                       *
 * ------------------------------------------------------------------ *
 * Id berbeda antar-lingkungan dan antar-pemulihan basis data. Angka yang ditulis tangan
 * akan menempelkan rencana pada orang lain, dan hasilnya tetap tampak wajar. */

DECLARE @kode varchar(50) = 'J31';
DECLARE @tahun varchar(4) = '2026';

DECLARE @user int = (
    SELECT MIN(Id_Users) FROM KPI_Users WHERE LTRIM(RTRIM(Kode_Users)) = @kode
);

IF @user IS NULL
    THROW 50561, 'Karyawan J31 tidak punya akun KPI; tidak ada tempat menempelkan rencananya.', 1;

/* Penjadwalan diambil dari yang SUDAH terbukti dibaca mesin PA, bukan dari `MIN(Id)` di
   antara seluruh jadwal 2026. Basis dev memuat puluhan jadwal 2026 sisa percobaan; memilih
   yang salah menghasilkan rencana yang tersimpan rapi tetapi tak pernah terbaca, dan
   layarnya tetap berkata "Tidak ada KPI disetujui" tanpa ada yang tahu kenapa. */
DECLARE @penjadwalan int = (
    SELECT MIN(p.Id_Penjadwalan)
    FROM KPI_Pengajuan p
    JOIN KPI_Master_Penjadwalan mp ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan
    WHERE mp.Tahun = @tahun
      AND p.Flag_Aktif = 'Y' AND p.Flag_Approval = 'Y' AND p.Status IS NULL
);

IF @penjadwalan IS NULL
    THROW 50562, 'Tidak ada penjadwalan KPI 2026 yang sudah terbukti terbaca mesin PA.', 1;

DECLARE @periode TABLE (Periode varchar(7) PRIMARY KEY, Target float, Actual float);

INSERT INTO @periode (Periode, Target, Actual) VALUES
    ('2026-01', 100, 92),
    ('2026-02', 100, 88),
    ('2026-03', 100, 95),
    ('2026-04', 100, 90),
    ('2026-05', 100, 97),
    ('2026-06', 100, 93),
    ('2026-07', 100, 91),
    ('2026-08', 100, 94),
    ('2026-09', 100, 96);

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Kepala pengajuan                                                 *
 * ------------------------------------------------------------------ *
 * `No_Transaksi` mengikuti pola PKPI<MM>-<YY>-<NNNN> yang sudah dipakai, nomornya
 * dilanjutkan dari yang tertinggi pada bulan itu. Nomor yang bertabrakan tidak selalu
 * ditolak basis data, tetapi layar KPI menampilkan dua transaksi bernomor sama tanpa
 * memberi tahu mana yang mana.
 *
 * TRY_CAST, BUKAN CAST — dan ini bukan kehati-hatian berlebihan. SQL Server tidak menjamin
 * `CAST` di daftar SELECT hanya dijalankan pada baris yang lolos `WHERE`; optimizernya
 * boleh memindai lalu mengonversi lebih dulu. Basis dev memuat `SEED-ERL-AKPI-2026-01`
 * peninggalan penyemai lain, yang empat karakter terakhirnya '6-01', dan versi pertama
 * berkas ini memang gagal karenanya — padahal barisnya tidak pernah cocok dengan LIKE-nya.
 * TRY_CAST memulangkan NULL untuk ekor yang bukan angka, dan MAX mengabaikan NULL.       */

IF NOT EXISTS (
    SELECT 1 FROM KPI_Pengajuan p
    JOIN KPI_Master_Penjadwalan mp ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan
    WHERE p.Id_User = @user AND mp.Tahun = @tahun
      AND p.Flag_Aktif = 'Y' AND p.Flag_Approval = 'Y' AND p.Status IS NULL
)
BEGIN
    INSERT INTO KPI_Pengajuan (
        No_Transaksi, Id_Penjadwalan, Id_User, Tanggal, Jam,
        Flag_Approval, Status, Flag_Aktif, Id_User_Sender, Flag_Team
    )
    SELECT
        'PKPI09-' + RIGHT(@tahun, 2) + '-'
            + RIGHT('0000' + CAST(
                ISNULL((
                    SELECT MAX(TRY_CAST(RIGHT(x.No_Transaksi, 4) AS int))
                    FROM KPI_Pengajuan x
                    WHERE x.No_Transaksi LIKE 'PKPI09-' + RIGHT(@tahun, 2) + '-%'
                ), 0) + 1
              AS varchar(4)), 4),
        @penjadwalan, @user, '2026-09-01 00:00:00', '08:00:00',
        'Y', NULL, 'Y', NULL, 'Y';
END;

DECLARE @pengajuan int = (
    SELECT MIN(p.Id_Pengajuan)
    FROM KPI_Pengajuan p
    JOIN KPI_Master_Penjadwalan mp ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan
    WHERE p.Id_User = @user AND mp.Tahun = @tahun
      AND p.Flag_Aktif = 'Y' AND p.Flag_Approval = 'Y' AND p.Status IS NULL
);

IF @pengajuan IS NULL
    THROW 50563, 'Kepala pengajuan gagal tersisip; langkah berikutnya akan menempel ke ketiadaan.', 1;

/* ------------------------------------------------------------------ *
 * 2. Parameter — SATU, berbobot 100                                   *
 * ------------------------------------------------------------------ *
 * Ketetapan bisnisnya: Total_Bobot KPI selalu 100. Satu parameter berbobot 100 memenuhinya
 * tanpa perlu membagi-bagi angka yang tidak punya dasar. Mesin PA memeriksa jumlah bobot
 * ini dan menahan hasilnya kalau bukan 100.                                             */

IF NOT EXISTS (SELECT 1 FROM KPI_Pengajuan_Detail WHERE Id_Pengajuan = @pengajuan)
BEGIN
    INSERT INTO KPI_Pengajuan_Detail (
        Id_Pengajuan, Nama_Parameter, Id_Bsc_Kategori, Id_Master_Formula,
        Id_Master_Satuan, Id_Master_Target, Bobot, Deskripsi, Kode_Karyawan
    )
    VALUES (
        @pengajuan, 'Pencapaian Output & Efisiensi Operasional', 17, 3,
        11, 3, 100, 'Data uji alur Performance Appraisal — bukan KPI sungguhan.', @kode
    );
END;

/* ------------------------------------------------------------------ *
 * 3. Target per bulan                                                 *
 * ------------------------------------------------------------------ *
 * Untuk SETIAP parameter pengajuan itu, bukan hanya yang pertama: bobotnya berjumlah 100 di
 * antara mereka, dan melewatkan satu parameter membuat totalnya timpang.                */

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
 * 4. Kepala realisasi, satu per bulan                                 *
 * ------------------------------------------------------------------ */

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
                SELECT MAX(TRY_CAST(RIGHT(u.No_Transaksi, 4) AS int))
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
 * 5. Capaian per parameter                                            *
 * ------------------------------------------------------------------ *
 * Inilah satu-satunya baris yang benar-benar dibaca mesin PA: ia menjumlahkan
 * `Nilai_Actual` di sini dan `Nilai_Target` di langkah 3, lalu menghitung sendiri.      */

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
 * melihatnya — dengan penyaring yang SAMA PERSIS dengan yang dipakai perhitungan.
 * Menghitung baris hasil sisipan hanya membuktikan INSERT-nya jalan, bukan bahwa yang
 * disisipkan itu terbaca.                                                               */

DECLARE @u int = (SELECT MIN(Id_Users) FROM KPI_Users WHERE LTRIM(RTRIM(Kode_Users)) = 'J31');

SELECT
    COUNT(*) AS baris_terbaca_mesin_pa,
    SUM(pdet.Nilai_Target) AS total_target,
    SUM(upd.Nilai_Actual) AS total_actual,
    CAST(SUM(upd.Nilai_Actual) * 100.0 / NULLIF(SUM(pdet.Nilai_Target), 0) AS decimal(6,2)) AS capaian_persen
FROM KPI_Pengajuan_Detail pd
JOIN KPI_Pengajuan p
    ON p.Id_Pengajuan = pd.Id_Pengajuan
   AND p.Flag_Aktif = 'Y' AND p.Flag_Approval = 'Y' AND p.Status IS NULL
JOIN KPI_Master_Penjadwalan mp
    ON mp.Id_Master_Penjadwalan = p.Id_Penjadwalan AND mp.Tahun = '2026'
JOIN KPI_Pengajuan_Det pdet
    ON pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail
   AND pdet.Periode IN ('2026-01','2026-02','2026-03','2026-04','2026-05',
                        '2026-06','2026-07','2026-08','2026-09')
JOIN KPI_Update_Performance_Detail upd
    ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
JOIN KPI_Update_Performance up
    ON up.Id_Update_Performance = upd.Id_Update_Performance
   AND up.Flag_Aktif = 'Y' AND up.Flag_Approval = 'Y'
   AND up.Flag_Verifikasi = 'Y' AND up.Status IS NULL
WHERE p.Id_User = @u;
GO
