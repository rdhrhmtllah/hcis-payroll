-- DML-2026-075-registrasi-api-kalender-ho
-- Target: Web_HRIS (development)
-- BELUM DIEKSEKUSI. Setiap bagian dapat dijalankan sendiri, berurutan.
--
-- BAGIAN 1-3 : pendaftaran integrasi Web Careers (wajib, dev dan production)
-- BAGIAN 4   : karyawan Head Office untuk uji (development SAJA)
-- BAGIAN 5   : pengembalian BAGIAN 4
-- BAGIAN 6   : kalender dummy tahun 2027 (development SAJA)
-- BAGIAN 7   : pengembalian BAGIAN 6
--
-- HANYA BAGIAN 1-3 yang boleh masuk SQL Tracker dan production.
-- BAGIAN 4 dan 6 adalah data uji development; membawanya ke production berarti
-- mengarang hari libur perusahaan.

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* =====================================================================
   BAGIAN 1 — Aplikasi konsumen

   Api_Public / Api_Secret DIBANGKITKAN SAAT EKSEKUSI dan tidak pernah
   ditulis ke berkas ini. Isi kedua variabel di bawah sebelum menjalankan,
   lalu serahkan pasangan kuncinya ke Web Careers lewat jalur rahasia,
   bukan lewat repo, chat, atau tiket.
   ===================================================================== */

DECLARE @ApiPublic  varchar(100) = N'<ISI_SAAT_EKSEKUSI>';
DECLARE @ApiSecret  varchar(255) = N'<ISI_SAAT_EKSEKUSI>';
DECLARE @GrupEss    int          = 1;   -- N_HRIS_Master_Grup_Fitur: ESS (diverifikasi 2026-09-04)

IF @ApiPublic = N'<ISI_SAAT_EKSEKUSI>' OR @ApiSecret = N'<ISI_SAAT_EKSEKUSI>'
BEGIN
    RAISERROR('Isi @ApiPublic dan @ApiSecret terlebih dahulu.', 16, 1);
    RETURN;
END

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = N'Web Careers')
BEGIN
    INSERT INTO N_HRIS_Api_Aplikasi
        (Id_Grup_Fitur, Nama_Aplikasi, Api_Public, Api_Secret, Base_Url, Arah, Created_At, Flag_Aktif)
    VALUES
        (@GrupEss, N'Web Careers', @ApiPublic, @ApiSecret, NULL, 'IN', SYSDATETIME(), 'Y');
END

/* =====================================================================
   BAGIAN 2 — Endpoint

   Url_Route WAJIB berisi POLA rute, termasuk "{tahun}" apa adanya.
   HrisApiGuard mencocokkan whitelist ke route()->uri(), sehingga satu baris
   ini melayani semua tahun. Kalau diisi jalur konkret seperti ".../tahun/2026",
   whitelist tidak akan cocok untuk tahun mana pun.
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Endpoint
               WHERE Url_Route = N'/api/v1/hcis/kalender-operasional/tahun/{tahun}' AND Method = 'GET')
BEGIN
    INSERT INTO N_HRIS_Api_Endpoint
        (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_At, Flag_Aktif)
    VALUES
        (@GrupEss, N'/api/v1/hcis/kalender-operasional/tahun/{tahun}', 'GET', 'IN',
         N'Kalender operasional Head Office setahun (Web Careers)', SYSDATETIME(), 'Y');
END

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Endpoint
               WHERE Url_Route = N'/api/v1/hcis/kalender-operasional' AND Method = 'GET')
BEGIN
    INSERT INTO N_HRIS_Api_Endpoint
        (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_At, Flag_Aktif)
    VALUES
        (@GrupEss, N'/api/v1/hcis/kalender-operasional', 'GET', 'IN',
         N'Kalender operasional Head Office per rentang (Web Careers)', SYSDATETIME(), 'Y');
END

/* =====================================================================
   BAGIAN 3 — Hak akses aplikasi terhadap kedua endpoint
   ===================================================================== */

DECLARE @IdAplikasi int =
    (SELECT TOP 1 Id_Api_Aplikasi FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = N'Web Careers');

INSERT INTO N_HRIS_Api_Akses_Endpoint (Id_Api_Aplikasi, Id_Api_Endpoint, Created_At, Flag_Aktif)
SELECT @IdAplikasi, ep.Id_Api_Endpoint, SYSDATETIME(), 'Y'
FROM   N_HRIS_Api_Endpoint ep
WHERE  ep.Method = 'GET'
AND    ep.Url_Route IN (N'/api/v1/hcis/kalender-operasional/tahun/{tahun}',
                        N'/api/v1/hcis/kalender-operasional')
AND    NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Akses_Endpoint ae
                   WHERE ae.Id_Api_Aplikasi = @IdAplikasi
                   AND   ae.Id_Api_Endpoint = ep.Id_Api_Endpoint);

/* =====================================================================
   BAGIAN 4 — DATA UJI HEAD OFFICE (DEVELOPMENT SAJA)

   JANGAN JALANKAN DI PRODUCTION.

   Master dev nyaris tidak punya nilai Cabang_Untuk_Gaji, sehingga hanya satu
   karyawan yang terdeteksi Head Office dan API sulit diuji. Dua belas baris
   di bawah SELURUHNYA karyawan sintetis berkode UJI#### — tidak ada karyawan
   sungguhan yang disentuh.

   Jalankan SELECT-nya lebih dulu dan simpan hasilnya: itulah nilai lama yang
   dipakai BAGIAN 5. Semua baris ini diketahui bernilai '' (kosong) per
   2026-09-04; verifikasi ulang sebelum eksekusi.
   ===================================================================== */

-- 4a. Rekam nilai lama (jalankan dan simpan hasilnya sebelum UPDATE).
SELECT Kode_Karyawan, ISNULL(Cabang_Untuk_Gaji, '') AS Cabang_Lama, Lokasi, Aktif, Tanggal_Resign
FROM   Karyawan
WHERE  Kode_Karyawan IN ('UJI0003','UJI0006','UJI0009','UJI0012','UJI0015','UJI0018',
                         'UJI0021','UJI0024','UJI0027','UJI0030','UJI0033','UJI0036')
ORDER BY Kode_Karyawan;

-- 4b. Perkiraan baris terdampak: 12. Batalkan bila angkanya berbeda.
UPDATE Karyawan
SET    Cabang_Untuk_Gaji = 'HEAD OFFICE EVO'
WHERE  Kode_Karyawan IN ('UJI0003','UJI0006','UJI0009','UJI0012','UJI0015','UJI0018',
                         'UJI0021','UJI0024','UJI0027','UJI0030','UJI0033','UJI0036')
AND    ISNULL(Cabang_Untuk_Gaji, '') = '';   -- hanya yang memang masih kosong

PRINT CONCAT('Baris terdampak: ', @@ROWCOUNT, ' (diharapkan 12).');

/* =====================================================================
   BAGIAN 5 — PENGEMBALIAN DATA UJI

   Mengembalikan kedua belas baris ke nilai kosong seperti semula. Hanya sah
   bila BAGIAN 4a menunjukkan seluruh Cabang_Lama memang kosong; kalau tidak,
   kembalikan per baris memakai nilai yang tercatat di sana.
   ===================================================================== */

-- UPDATE Karyawan
-- SET    Cabang_Untuk_Gaji = ''
-- WHERE  Kode_Karyawan IN ('UJI0003','UJI0006','UJI0009','UJI0012','UJI0015','UJI0018',
--                          'UJI0021','UJI0024','UJI0027','UJI0030','UJI0033','UJI0036')
-- AND    Cabang_Untuk_Gaji = 'HEAD OFFICE EVO';

/* =====================================================================
   BAGIAN 6 — KALENDER DUMMY 2027 (DEVELOPMENT SAJA)

   JANGAN JALANKAN DI PRODUCTION.

   Tahun 2027 dipilih karena di dev benar-benar kosong: nol hari libur, nol
   cuti bersama, nol data absensi. Dengan begitu 2026 tetap berisi data asli
   dan bisa dipakai membandingkan, sementara 2027 jadi kalender buatan yang
   setiap barisnya menguji satu cabang aturan.

   Semua nama diawali "UJI " supaya mudah dikenali dan dibersihkan.

   Hasil yang diharapkan untuk GET .../tahun/2027 — tepat 6 tanggal:

     2027-01-01  LIBUR_NASIONAL  UJI Tahun Baru 2027
     2027-03-15  LIBUR_NASIONAL  UJI Libur Nasional Contoh   <- menang atas CB-F
     2027-03-16  CUTI_BERSAMA    UJI CB Sekantor Tanpa Filter
     2027-03-17  CUTI_BERSAMA    UJI CB Sekantor Tanpa Filter
     2027-04-05  CUTI_BERSAMA    UJI CB Khusus Head Office
     2027-12-25  LIBUR_NASIONAL  UJI Hari Raya Natal 2027

   Yang SENGAJA tidak muncul: CB-C (cabang lain), CB-D (semua sasaran
   dikecualikan), CB-E (dibatalkan), CB-F (kalah oleh libur nasional).
   ===================================================================== */

-- 6a. Hari libur dummy
INSERT INTO HRIS_Hari_Libur (Kode_Perusahaan, Tanggal, Nama_Hari_Libur)
SELECT '001', v.Tanggal, v.Nama
FROM (VALUES
        (CAST('2027-01-01' AS datetime), 'UJI Tahun Baru 2027'),
        (CAST('2027-03-15' AS datetime), 'UJI Libur Nasional Contoh'),
        (CAST('2027-12-25' AS datetime), 'UJI Hari Raya Natal 2027')
     ) AS v(Tanggal, Nama)
WHERE NOT EXISTS (SELECT 1 FROM HRIS_Hari_Libur h
                  WHERE h.Kode_Perusahaan = '001' AND h.Tanggal = v.Tanggal);

-- 6b. Cuti bersama dummy. Scheduled_At dan Cloud_Task_Name sengaja NULL dan
--     Flag_Executed = 'T': tidak ada penjadwal maupun job yang memindai tabel
--     ini, jadi tak satu pun baris ini akan memotong saldo cuti siapa pun.
INSERT INTO N_HRIS_Cuti_Bersama
    (Nama_Cuti_Bersama, Tanggal_Mulai, Tanggal_Selesai, Jumlah_Hari, Potong_Ke_Tipe_Cuti,
     Flag_Aktif, Flag_Executed, Flag_Cancelled, Selected_Dates,
     Filter_Cabang, Filter_Divisi, Filter_Sub_Divisi, Filter_Kode_Karyawan,
     Keterangan, Created_At, Created_By)
SELECT v.Nama, v.Mulai, v.Selesai, v.Hari, 'CUTI_TAHUNAN',
       v.Aktif, 'T', v.Batal, v.Tanggal,
       v.Cabang, NULL, NULL, v.Kode,
       'Data uji API kalender operasional HO (DML-2026-075)', SYSDATETIME(), 'DML-2026-075'
FROM (VALUES
        -- CB-A: tanpa filter sama sekali = berlaku sekantor, termasuk HO
        ('UJI CB Sekantor Tanpa Filter', CAST('2027-03-16' AS date), CAST('2027-03-17' AS date), 2.00,
         'Y', 'T', '["2027-03-16","2027-03-17"]', NULL, NULL),
        -- CB-B: menyasar cabang Head Office secara eksplisit
        ('UJI CB Khusus Head Office', CAST('2027-04-05' AS date), CAST('2027-04-05' AS date), 1.00,
         'Y', 'T', '["2027-04-05"]', '["HEAD OFFICE EVO"]', NULL),
        -- CB-C: hanya cabang lain -> HO TIDAK tutup
        ('UJI CB Khusus Cabang EMI', CAST('2027-04-06' AS date), CAST('2027-04-06' AS date), 1.00,
         'Y', 'T', '["2027-04-06"]', '["EMI"]', NULL),
        -- CB-D: menyasar 2 karyawan HO, tapi keduanya dikecualikan di 6c
        ('UJI CB Sasaran Dikecualikan Semua', CAST('2027-04-07' AS date), CAST('2027-04-07' AS date), 1.00,
         'Y', 'T', '["2027-04-07"]', NULL, '["UJI0003","UJI0006"]'),
        -- CB-E: dibatalkan -> tidak pernah terbit
        ('UJI CB Dibatalkan', CAST('2027-04-08' AS date), CAST('2027-04-08' AS date), 1.00,
         'Y', 'Y', '["2027-04-08"]', NULL, NULL),
        -- CB-F: jatuh pada tanggal yang juga libur nasional -> kalah, tidak terbit
        ('UJI CB Bentrok Libur Nasional', CAST('2027-03-15' AS date), CAST('2027-03-15' AS date), 1.00,
         'Y', 'T', '["2027-03-15"]', NULL, NULL)
     ) AS v(Nama, Mulai, Selesai, Hari, Aktif, Batal, Tanggal, Cabang, Kode)
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Cuti_Bersama c WHERE c.Nama_Cuti_Bersama = v.Nama);

-- 6c. Pengecualian untuk CB-D: seluruh sasarannya dikecualikan, sehingga tidak
--     ada satu pun karyawan HO yang tersasar-dan-tidak-dikecualikan.
INSERT INTO N_HRIS_Cuti_Bersama_Exclusion (Id_Cuti_Bersama, Kode_Karyawan, Alasan, Created_At, Created_By)
SELECT c.Id_Cuti_Bersama, v.Kode, 'Data uji: seluruh sasaran dikecualikan', SYSDATETIME(), 'DML-2026-075'
FROM   N_HRIS_Cuti_Bersama c
CROSS JOIN (VALUES ('UJI0003'), ('UJI0006')) AS v(Kode)
WHERE  c.Nama_Cuti_Bersama = 'UJI CB Sasaran Dikecualikan Semua'
AND    NOT EXISTS (SELECT 1 FROM N_HRIS_Cuti_Bersama_Exclusion e
                   WHERE e.Id_Cuti_Bersama = c.Id_Cuti_Bersama AND e.Kode_Karyawan = v.Kode);

/* =====================================================================
   BAGIAN 7 — PENGEMBALIAN KALENDER DUMMY

   Semua data dummy dikenali dari awalan nama "UJI " dan tahun 2027.
   ===================================================================== */

-- DELETE FROM N_HRIS_Cuti_Bersama_Exclusion
-- WHERE Id_Cuti_Bersama IN (SELECT Id_Cuti_Bersama FROM N_HRIS_Cuti_Bersama
--                           WHERE Nama_Cuti_Bersama LIKE 'UJI CB %');
--
-- DELETE FROM N_HRIS_Cuti_Bersama WHERE Nama_Cuti_Bersama LIKE 'UJI CB %';
--
-- DELETE FROM HRIS_Hari_Libur
-- WHERE Kode_Perusahaan = '001' AND YEAR(Tanggal) = 2027 AND Nama_Hari_Libur LIKE 'UJI %';
