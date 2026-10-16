/* Change ID: DML-2026-164-sinkron-prod-data-tertinggal

   Data yang dibawa DML terdahulu tetapi TERBUKTI belum ada di production
   (emi_db_web_hr), diperiksa 2026-10-06 dengan SELECT saja. Satu berkas,
   satu transaksi, idempoten: dijalankan ulang menyentuh 0 baris.

   Asal dan perkiraan baris terdampak di production:
     1. Registry API HRIS (DML-2026-075 bag. 1-3, DDL-2026-079, DDL-2026-102,
        DML-2026-080, DML-2026-143) — ketiga tabel N_HRIS_Api_* masih kosong:
          Endpoint 9, Aplikasi s.d. 4, Akses s.d. 9.
     2. DDL-2026-093 bag. data — Metode_Cap NULL: 75 'PARAMETER' + 198 'TOTAL'.
     3. DML-2026-147 — peta systemPanduanPage menunjuk Id grup 5 (Id dev);
        di production grup itu tidak ada: 1 baris dipindah ke grup SYSTEM.
     4. DML-2026-057 — peta grup menuSopPrioritasPage belum ada: 1 baris.
     5. DML-2026-058 — kategori SOP dicabut: peta 1 baris, hak akses 1 baris.
     6. DML-2026-046 — urutan menu Administrator DMS: 5 baris.
     7. Menu Otomasi SOP (menuSopKebijakanPage) menunjuk URL Setup System: 1 baris.

   KUNCI API: isi variabel di BAGIAN 0 saat eksekusi. Aplikasi yang kuncinya
   dibiarkan '<ISI_SAAT_EKSEKUSI>' DILEWATI (tercetak di PRINT), bagian lain
   tetap jalan; berkas ini bisa dijalankan ulang nanti setelah kuncinya ada.
   HC Learn tidak butuh kunci baru: disalin dari N_LMS_Api_Aplikasi
   'HUMAN CAPITAL LEARNING', pasangan yang sama dengan development. */

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* ===================== BAGIAN 0 — kunci aplikasi ===================== */
DECLARE @WebCareers_Public varchar(100) = '<ISI_SAAT_EKSEKUSI>';
DECLARE @WebCareers_Secret varchar(255) = '<ISI_SAAT_EKSEKUSI>';

DECLARE @Mitra_Nama        varchar(150) = 'B2B';
DECLARE @Mitra_Public      varchar(100) = '<ISI_SAAT_EKSEKUSI>';
DECLARE @Mitra_Secret      varchar(255) = '<ISI_SAAT_EKSEKUSI>';
DECLARE @Mitra_PIC         varchar(20)  = '';

DECLARE @CekKartu_Public   varchar(100) = '<ISI_SAAT_EKSEKUSI>';
DECLARE @CekKartu_Secret   varchar(255) = '<ISI_SAAT_EKSEKUSI>';

DECLARE @Kosong varchar(30) = '<ISI_SAAT_EKSEKUSI>';

/* ===================== Penjaga ===================== */
DECLARE @GrupEss int     = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'ESS');
DECLARE @GrupVisitor int = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR');
DECLARE @GrupSystem int  = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'SYSTEM');
DECLARE @GrupDms int     = (SELECT Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'DMS');

IF @GrupEss IS NULL OR @GrupVisitor IS NULL OR @GrupSystem IS NULL OR @GrupDms IS NULL
    THROW 50164, 'Grup ESS/VISITOR/SYSTEM/DMS tidak lengkap di N_HRIS_Master_Grup_Fitur.', 1;

IF @Mitra_PIC <> '' AND NOT EXISTS (SELECT 1 FROM Karyawan WHERE Kode_Karyawan = @Mitra_PIC)
    THROW 50164, '@Mitra_PIC diisi tetapi tidak ada di Karyawan. Kosongkan atau perbaiki.', 1;

IF EXISTS (
    SELECT p FROM (VALUES (@WebCareers_Public), (@Mitra_Public), (@CekKartu_Public)) v (p)
    WHERE p <> @Kosong GROUP BY p HAVING COUNT(*) > 1)
    THROW 50164, 'Api_Public yang sama dipakai dua aplikasi. Bangkitkan kunci yang berbeda.', 1;

BEGIN TRANSACTION;

/* ===================== BAGIAN 1 — Registry API HRIS ===================== */

/* 1a. Endpoint (pola rute apa adanya, termasuk {param}). */
INSERT INTO N_HRIS_Api_Endpoint (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_At, Flag_Aktif)
SELECT s.Grup, s.Url_Route, s.Method, 'IN', s.Deskripsi, SYSDATETIME(), 'Y'
FROM (VALUES
    (@GrupDms,     '/api/v1/hris/third-party/sop/exam/complete',             'POST', N'Callback hasil kuis SOP (HC Learn)'),
    (@GrupEss,     '/api/v1/hcis/kalender-operasional/tahun/{tahun}',        'GET',  N'Kalender operasional Head Office setahun (Web Careers)'),
    (@GrupEss,     '/api/v1/hcis/kalender-operasional',                      'GET',  N'Kalender operasional Head Office per rentang (Web Careers)'),
    (@GrupVisitor, '/api/v1/hcis/visitor/permit-tamu',                       'POST', N'Ajukan permit COKLAT untuk tamu mitra B2B'),
    (@GrupVisitor, '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}',        'GET',  N'Status permit COKLAT mitra B2B'),
    (@GrupVisitor, '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal',  'POST', N'Pembatalan permit COKLAT oleh mitra B2B'),
    (@GrupVisitor, '/api/v1/hcis/visitor/tamu-b2b',                          'POST', N'Registrasi tamu B2B oleh aplikasi mitra'),
    (@GrupVisitor, '/api/v1/hcis/visitor/tamu-b2b/{no_transaksi}',           'GET',  N'Status registrasi tamu B2B mitra'),
    (@GrupVisitor, '/api/v1/hcis/visitor/kartu-permit/{kode}',               'GET',  N'Status kartu permit (data minimal) untuk layanan cek kartu publik')
) AS s (Grup, Url_Route, Method, Deskripsi)
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Endpoint e WHERE e.Url_Route = s.Url_Route AND e.Method = s.Method);

/* 1b. Aplikasi. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = 'HC Learn')
BEGIN
    INSERT INTO N_HRIS_Api_Aplikasi (Id_Grup_Fitur, Nama_Aplikasi, Api_Public, Api_Secret, Base_Url, Arah, Created_At, Flag_Aktif)
    SELECT TOP 1 @GrupDms, 'HC Learn', l.Api_Public, l.Api_Secret, NULL, 'IN', SYSDATETIME(), 'Y'
    FROM N_LMS_Api_Aplikasi l
    WHERE l.Nama_Aplikasi = 'HUMAN CAPITAL LEARNING' AND l.Flag_Aktif = 'Y';
    IF @@ROWCOUNT = 0 PRINT 'HC Learn DILEWATI: HUMAN CAPITAL LEARNING aktif tidak ada di N_LMS_Api_Aplikasi.';
END;

IF @WebCareers_Public = @Kosong OR @WebCareers_Secret = @Kosong
    PRINT 'Web Careers DILEWATI: kunci belum diisi.';
ELSE IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = 'Web Careers')
    INSERT INTO N_HRIS_Api_Aplikasi (Id_Grup_Fitur, Nama_Aplikasi, Api_Public, Api_Secret, Base_Url, Arah, Created_At, Flag_Aktif)
    VALUES (@GrupEss, 'Web Careers', @WebCareers_Public, @WebCareers_Secret, NULL, 'IN', SYSDATETIME(), 'Y');

IF @Mitra_Public = @Kosong OR @Mitra_Secret = @Kosong
    PRINT 'Aplikasi mitra B2B DILEWATI: kunci belum diisi.';
ELSE IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = @Mitra_Nama)
    INSERT INTO N_HRIS_Api_Aplikasi (Id_Grup_Fitur, Nama_Aplikasi, Api_Public, Api_Secret, Base_Url, Arah, Kode_Karyawan_PIC, Created_At, Flag_Aktif)
    VALUES (@GrupVisitor, @Mitra_Nama, @Mitra_Public, @Mitra_Secret, NULL, 'IN', NULLIF(@Mitra_PIC, ''), SYSDATETIME(), 'Y');

IF @CekKartu_Public = @Kosong OR @CekKartu_Secret = @Kosong
    PRINT 'Cek Kartu Permit Publik DILEWATI: kunci belum diisi.';
ELSE IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = 'Cek Kartu Permit Publik')
    INSERT INTO N_HRIS_Api_Aplikasi (Id_Grup_Fitur, Nama_Aplikasi, Api_Public, Api_Secret, Base_Url, Arah, Created_At, Flag_Aktif)
    VALUES (@GrupVisitor, 'Cek Kartu Permit Publik', @CekKartu_Public, @CekKartu_Secret, NULL, 'IN', SYSDATETIME(), 'Y');

IF EXISTS (SELECT Api_Public FROM N_HRIS_Api_Aplikasi GROUP BY Api_Public HAVING COUNT(*) > 1)
    THROW 50164, 'Api_Public kembar di N_HRIS_Api_Aplikasi. Dibatalkan.', 1;

/* 1c. Hak akses aplikasi -> endpoint (dicari lewat nama, bukan Id). */
INSERT INTO N_HRIS_Api_Akses_Endpoint (Id_Api_Aplikasi, Id_Api_Endpoint, Created_At, Flag_Aktif)
SELECT a.Id_Api_Aplikasi, e.Id_Api_Endpoint, SYSDATETIME(), 'Y'
FROM (VALUES
    ('HC Learn',                '/api/v1/hris/third-party/sop/exam/complete',            'POST'),
    ('Web Careers',             '/api/v1/hcis/kalender-operasional/tahun/{tahun}',       'GET'),
    ('Web Careers',             '/api/v1/hcis/kalender-operasional',                     'GET'),
    (@Mitra_Nama,               '/api/v1/hcis/visitor/permit-tamu',                      'POST'),
    (@Mitra_Nama,               '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}',       'GET'),
    (@Mitra_Nama,               '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal', 'POST'),
    (@Mitra_Nama,               '/api/v1/hcis/visitor/tamu-b2b',                         'POST'),
    (@Mitra_Nama,               '/api/v1/hcis/visitor/tamu-b2b/{no_transaksi}',          'GET'),
    ('Cek Kartu Permit Publik', '/api/v1/hcis/visitor/kartu-permit/{kode}',              'GET')
) AS s (Nama_Aplikasi, Url_Route, Method)
JOIN N_HRIS_Api_Aplikasi a ON a.Nama_Aplikasi = s.Nama_Aplikasi
JOIN N_HRIS_Api_Endpoint e ON e.Url_Route = s.Url_Route AND e.Method = s.Method
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Akses_Endpoint x
                  WHERE x.Id_Api_Aplikasi = a.Id_Api_Aplikasi AND x.Id_Api_Endpoint = e.Id_Api_Endpoint);

/* ===================== BAGIAN 2 — DDL-2026-093: penanda Metode_Cap ===================== */
UPDATE h
   SET h.Metode_Cap = 'PARAMETER'
  FROM KPI_Update_Performance h
 WHERE h.Metode_Cap IS NULL
   AND EXISTS (SELECT 1 FROM KPI_Update_Performance_Detail d
               WHERE d.Id_Update_Performance = h.Id_Update_Performance AND d.Score_Raw IS NOT NULL);

UPDATE h
   SET h.Metode_Cap = 'TOTAL'
  FROM KPI_Update_Performance h
 WHERE h.Metode_Cap IS NULL
   AND h.Flag_Capped = 'Y'
   AND NOT EXISTS (SELECT 1 FROM KPI_Update_Performance_Detail d
                   WHERE d.Id_Update_Performance = h.Id_Update_Performance AND d.Score_Raw IS NOT NULL);

/* ===================== BAGIAN 3 — DML-2026-147: peta Panduan ke grup SYSTEM ===================== */
UPDATE m
   SET m.Id_Grup_Fitur = @GrupSystem
  FROM N_HRIS_Grup_Fitur_Map m
 WHERE m.Jenis_page = 'systemPanduanPage'
   AND NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Grup_Fitur g WHERE g.Id_Grup_Fitur = m.Id_Grup_Fitur);

/* ===================== BAGIAN 4 — DML-2026-057 & 058: Prioritas menggantikan Kategori ===================== */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopPrioritasPage')
    INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT @GrupDms, 'menuSopPrioritasPage',
           (SELECT TOP 1 k.Sort_Order FROM N_HRIS_Grup_Fitur_Map k WHERE k.Jenis_page = 'menuSopKategoriPage' AND k.Id_Grup_Fitur = @GrupDms),
           'T', 'Y', 'Y';

UPDATE N_HRIS_Grup_Fitur_Map
   SET Flag_Aktif = 'T'
 WHERE Jenis_page = 'menuSopKategoriPage' AND Flag_Aktif <> 'T';

UPDATE HRIS_Page_Access
   SET Flag_Aktif = 'T', Updated_At = GETDATE()
 WHERE Jenis_page = 'menuSopKategoriPage' AND Flag_Aktif = 'Y';

/* ===================== BAGIAN 5 — DML-2026-046: urutan menu Administrator DMS ===================== */
UPDATE m
   SET m.Sort_Menu = s.Urut
  FROM N_HRIS_Menus m
  JOIN (VALUES
      ('menuSopJenisDokumenPage', 1), ('menuSopPrioritasPage', 2), ('menuSopApqcPage', 3),
      ('menuSopKontenPage', 4), ('menuSopDokumenPage', 5), ('menuSopBuilderPage', 6),
      ('menuSopPenjadwalanPage', 7), ('menuSopKebijakanPage', 8), ('menuSopMonitoringPage', 9),
      ('menuSopExportStudioPage', 10), ('menuSopSetupSystemPage', 11)
  ) AS s (Jenis_page, Urut) ON s.Jenis_page = m.Jenis_page
 WHERE ISNULL(TRY_CAST(m.Sort_Menu AS int), -1) <> s.Urut;

/* ===================== BAGIAN 6 — Otomasi SOP ke rutenya sendiri ===================== */
UPDATE N_HRIS_Menus
   SET Url_Menu = '/sop/admin/kebijakan'
 WHERE Jenis_page = 'menuSopKebijakanPage'
   AND Url_Menu = '/sop/admin/setup-system';

COMMIT TRANSACTION;

/* ===================== VERIFIKASI ===================== */
SELECT a.Nama_Aplikasi, a.Flag_Aktif,
       (SELECT COUNT(*) FROM N_HRIS_Api_Akses_Endpoint x WHERE x.Id_Api_Aplikasi = a.Id_Api_Aplikasi AND x.Flag_Aktif = 'Y') AS Jumlah_Endpoint
  FROM N_HRIS_Api_Aplikasi a;

SELECT COUNT(*) AS Endpoint_Terdaftar FROM N_HRIS_Api_Endpoint WHERE Flag_Aktif = 'Y';

SELECT 'Metode_Cap belum terisi (harus 0)' AS Cek, COUNT(*) AS Jumlah
  FROM KPI_Update_Performance h
 WHERE h.Metode_Cap IS NULL
   AND (h.Flag_Capped = 'Y' OR EXISTS (SELECT 1 FROM KPI_Update_Performance_Detail d
                                       WHERE d.Id_Update_Performance = h.Id_Update_Performance AND d.Score_Raw IS NOT NULL));

SELECT m.Jenis_page, g.Kode_Grup_Fitur, m.Sort_Order, m.Flag_Aktif
  FROM N_HRIS_Grup_Fitur_Map m
  LEFT JOIN N_HRIS_Master_Grup_Fitur g ON g.Id_Grup_Fitur = m.Id_Grup_Fitur
 WHERE m.Jenis_page IN ('systemPanduanPage', 'menuSopPrioritasPage', 'menuSopKategoriPage');

SELECT Jenis_page, Nama_Menu, Url_Menu, Sort_Menu
  FROM N_HRIS_Menus
 WHERE Header_Label = 'Administrator DMS'
 ORDER BY TRY_CAST(Sort_Menu AS int);
