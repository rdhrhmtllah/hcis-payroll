/* Change ID: DML-2026-102-endpoint-callback-pa-terpisah
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: tidak ada.
   Scope: DUA registri, satu baris baru di masing-masing tabelnya:
     - N_LMS_Endpoint_Api + N_LMS_Akses_Endpoint        (daftar putih gerbang pa.api.guard)
     - N_HRIS_Api_Endpoint + N_HRIS_Api_Akses_Endpoint  (penyaring dokumen OpenAPI)

   YANG DISELESAIKAN

   Hasil penilaian 360 dari HC Learn kini masuk lewat alamatnya sendiri:

       POST /api/v1/pa/third-party/penilaian/complete

   Sebelumnya ia menumpang /api/v1/lms/third-party/exam/complete bersama Training dan SOP
   Kuis. Karena satu alamat melayani tiga modul, LmsApiGuard harus MENEBAK asal setiap
   kiriman dari tokennya: tiga kueri ke tiga tabel integrasi pada setiap callback, ditambah
   satu cabang untuk token yang cocok di lebih dari satu modul — cabang yang ada persis
   karena penulisnya tahu tebakan itu bisa salah. Di atasnya, penyaring bersama menuntut
   `Score`, yang penilaian 360 tidak pernah punya.

   Gerbang `pa.api.guard` memeriksa daftar putih yang SAMA dengan gerbang LMS. Tanpa kedua
   baris di bawah, seluruh kiriman PA dijawab 403 "Endpoint not authorized for this
   application" — tanda tangannya sah, tetapi alamatnya belum terdaftar.

   KENAPA MENULIS KE TABEL BER-PREFIX N_LMS_

   Ini pengecualian sadar terhadap aturan "perubahan PA hanya menyentuh N_PA_*", dan
   alasannya bukan kemudahan.

   N_LMS_Endpoint_Api dan N_LMS_Akses_Endpoint bukan data bisnis LMS melainkan REGISTRI
   GERBANG API — daftar alamat yang boleh dipanggil aplikasi luar. Nonce anti-replay pun
   satu ruang dengan alasan yang lebih keras lagi: bila PA punya tabel nonce sendiri, sebuah
   permintaan bertanda tangan sah yang sudah dipakai di gerbang LMS masih bisa diputar ulang
   ke gerbang PA. Dua ruang nonce membuat perlindungan replay lebih LEMAH daripada satu.

   Kredensial aplikasinya juga satu (HUMAN CAPITAL LEARNING). Memaksa HC Learn memegang
   kunci kedua hanya karena alamatnya bertambah satu adalah beban tanpa hasil.

   Tidak ada baris LMS atau SOP yang diubah, dinonaktifkan, atau dihapus. Yang terjadi hanya
   penambahan dua baris.

   KENAPA DUA REGISTRI

   Ini bukan salah tulis. HCIS punya DUA daftar endpoint pihak ketiga yang hidup
   berdampingan, dan keduanya harus diisi:

     N_LMS_*    dibaca LmsApiGuard dan PaApiGuard untuk MENGIZINKAN permintaan. Tanpa
                baris di sini, kiriman dijawab 403 walau tanda tangannya sah.
     N_HRIS_*   dibaca OpenApiRegistryFilter untuk MENYARING dokumen yang dilihat tiap
                konsumen. Tanpa baris di sini, endpointnya tidak pernah muncul di
                spesifikasi yang dibaca HC Learn — dan penjaga ApiDocsTest "dokumen
                tidak menjanjikan endpoint di luar registry" berubah MERAH, karena YAML
                menjanjikan sesuatu yang registry-nya tidak kenal.

   Percobaan pertama hanya mengisi yang pertama; penjaga itulah yang menangkapnya. Nama
   aplikasinya pun berbeda di kedua tabel — 'HUMAN CAPITAL LEARNING' di N_LMS_, 'HC Learn'
   di N_HRIS_ — jadi keduanya dicari, bukan ditebak.

   IDEMPOTEN

   Kedua sisipan dijaga NOT EXISTS, jadi menjalankannya berulang aman. Itu bukan kerapian
   belaka: script daftar putih paling sering dijalankan ulang justru saat orang tidak yakin
   apakah yang pertama berhasil.

   CARA MEMBATALKAN

   Nonaktifkan, jangan hapus — baris akses yang dihapus tidak meninggalkan jejak bahwa ia
   pernah ada:

       UPDATE ae SET Flag_Aktif = 'T'
       FROM N_LMS_Akses_Endpoint ae
       JOIN N_LMS_Endpoint_Api ep ON ep.Id_Endpoint_Api = ae.Id_Endpoint_Api
       WHERE ep.Url_Route = '/api/v1/pa/third-party/penilaian/complete';

       UPDATE ae SET Flag_Aktif = 'T'
       FROM N_HRIS_Api_Akses_Endpoint ae
       JOIN N_HRIS_Api_Endpoint ep ON ep.Id_Api_Endpoint = ae.Id_Api_Endpoint
       WHERE ep.Url_Route = '/api/v1/pa/third-party/penilaian/complete';

   Mencabut yang N_HRIS_ saja sudah cukup untuk menghilangkannya dari dokumen; mencabut
   yang N_LMS_ saja menutup pintunya tapi dokumennya tetap menjanjikan. Cabut keduanya.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

DECLARE @Url varchar(255) = '/api/v1/pa/third-party/penilaian/complete';
DECLARE @Method varchar(10) = 'POST';

/* Nama aplikasinya dicari, bukan ditulis sebagai angka: Id_Api_Aplikasi bisa berbeda
   antara development dan production, dan angka yang salah di sini memberi akses kepada
   aplikasi yang keliru — diam-diam, karena tidak ada yang gagal. */
DECLARE @IdApp int = (
    SELECT TOP 1 Id_Api_Aplikasi
    FROM N_LMS_Api_Aplikasi
    WHERE Nama_Aplikasi = 'HUMAN CAPITAL LEARNING'
      AND Flag_Aktif = 'Y'
    ORDER BY Id_Api_Aplikasi
);

IF @IdApp IS NULL
BEGIN
    /* Berhenti dengan sebab yang jelas. Melanjutkan tanpa aplikasi berarti mendaftarkan
       alamat yang tak seorang pun boleh memanggilnya — terlihat berhasil, tidak berguna. */
    RAISERROR('Aplikasi HUMAN CAPITAL LEARNING tidak ditemukan atau tidak aktif. Baris akses tidak dibuat.', 16, 1);
    ROLLBACK TRANSACTION;
    RETURN;
END;

-- 1. Daftarkan alamatnya.
IF NOT EXISTS (
    SELECT 1 FROM N_LMS_Endpoint_Api WHERE Url_Route = @Url AND Method = @Method
)
BEGIN
    INSERT INTO N_LMS_Endpoint_Api (Url_Route, Method, Flag_Aktif)
    VALUES (@Url, @Method, 'Y');
END
ELSE
BEGIN
    -- Sudah ada tetapi mungkin sempat dinonaktifkan.
    UPDATE N_LMS_Endpoint_Api
    SET Flag_Aktif = 'Y'
    WHERE Url_Route = @Url AND Method = @Method AND Flag_Aktif <> 'Y';
END;

DECLARE @IdEndpoint int = (
    SELECT TOP 1 Id_Endpoint_Api
    FROM N_LMS_Endpoint_Api
    WHERE Url_Route = @Url AND Method = @Method
    ORDER BY Id_Endpoint_Api
);

-- 2. Beri akses kepada aplikasi HC Learn.
IF NOT EXISTS (
    SELECT 1 FROM N_LMS_Akses_Endpoint
    WHERE Id_Api_Aplikasi = @IdApp AND Id_Endpoint_Api = @IdEndpoint
)
BEGIN
    INSERT INTO N_LMS_Akses_Endpoint (Id_Api_Aplikasi, Id_Endpoint_Api, Flag_Aktif)
    VALUES (@IdApp, @IdEndpoint, 'Y');
END
ELSE
BEGIN
    UPDATE N_LMS_Akses_Endpoint
    SET Flag_Aktif = 'Y'
    WHERE Id_Api_Aplikasi = @IdApp AND Id_Endpoint_Api = @IdEndpoint AND Flag_Aktif <> 'Y';
END;

-- ---------------------------------------------------------------------------
-- 3. Registri kedua: penyaring dokumen OpenAPI.
--    Nama aplikasinya BERBEDA dari tabel N_LMS_ di atas ('HC Learn', bukan
--    'HUMAN CAPITAL LEARNING'), jadi ia dicari sendiri — bukan diwarisi.
-- ---------------------------------------------------------------------------

DECLARE @IdAppHris int = (
    SELECT TOP 1 Id_Api_Aplikasi
    FROM N_HRIS_Api_Aplikasi
    WHERE Nama_Aplikasi = 'HC Learn'
      AND Flag_Aktif = 'Y'
    ORDER BY Id_Api_Aplikasi
);

IF @IdAppHris IS NULL
BEGIN
    RAISERROR('Aplikasi HC Learn tidak ditemukan di N_HRIS_Api_Aplikasi. Endpoint PA tidak akan muncul di dokumen OpenAPI.', 16, 1);
    ROLLBACK TRANSACTION;
    RETURN;
END;

/* Id_Grup_Fitur WAJIB dan tanpa nilai bawaan — percobaan pertama melewatkannya dan
   seluruh transaksi ter-rollback (XACT_ABORT ON), jadi tidak ada yang setengah jadi.

   Diisi 2 = Key Performance Indicator, karena Performance Appraisal memang submodul
   KPI. Tetangganya (callback SOP) memakai 0, yang tidak menunjuk grup mana pun di
   N_HRIS_Master_Grup_Fitur — penampung, bukan keputusan. Kolom ini deskriptif belaka:
   tidak ada foreign key, dan tidak satu pun kode membacanya (OpenApiRegistryFilter
   menyaring lewat tabel akses, bukan lewat grup). Karena itu memilih yang benar di
   sini tidak berisiko, dan meninggalkan 0 hanya menambah satu lagi baris yang harus
   ditebak artinya oleh orang berikutnya. */
DECLARE @IdGrupKpi int = (
    SELECT TOP 1 Id_Grup_Fitur
    FROM N_HRIS_Master_Grup_Fitur
    WHERE Nama_Grup_Fitur = 'Key Performance Indicator'
    ORDER BY Id_Grup_Fitur
);

IF @IdGrupKpi IS NULL
BEGIN
    RAISERROR('Grup fitur Key Performance Indicator tidak ditemukan.', 16, 1);
    ROLLBACK TRANSACTION;
    RETURN;
END;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Api_Endpoint WHERE Url_Route = @Url AND Method = @Method
)
BEGIN
    INSERT INTO N_HRIS_Api_Endpoint (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Flag_Aktif)
    VALUES (@IdGrupKpi, @Url, @Method, 'IN',
        N'Callback hasil penilaian 360 dari HC Learn — nilai per butir soal, bukan satu angka.',
        'Y');
END
ELSE
BEGIN
    UPDATE N_HRIS_Api_Endpoint
    SET Flag_Aktif = 'Y'
    WHERE Url_Route = @Url AND Method = @Method AND Flag_Aktif <> 'Y';
END;

DECLARE @IdEndpointHris int = (
    SELECT TOP 1 Id_Api_Endpoint
    FROM N_HRIS_Api_Endpoint
    WHERE Url_Route = @Url AND Method = @Method
    ORDER BY Id_Api_Endpoint
);

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Api_Akses_Endpoint
    WHERE Id_Api_Aplikasi = @IdAppHris AND Id_Api_Endpoint = @IdEndpointHris
)
BEGIN
    INSERT INTO N_HRIS_Api_Akses_Endpoint (Id_Api_Aplikasi, Id_Api_Endpoint, Flag_Aktif)
    VALUES (@IdAppHris, @IdEndpointHris, 'Y');
END
ELSE
BEGIN
    UPDATE N_HRIS_Api_Akses_Endpoint
    SET Flag_Aktif = 'Y'
    WHERE Id_Api_Aplikasi = @IdAppHris AND Id_Api_Endpoint = @IdEndpointHris AND Flag_Aktif <> 'Y';
END;

COMMIT TRANSACTION;

/* VERIFIKASI — jalankan sesudahnya. Harus mengembalikan tepat satu baris, aktif='Y'. */
SELECT
    ep.Id_Endpoint_Api,
    ep.Method,
    ep.Url_Route,
    ep.Flag_Aktif        AS Endpoint_Aktif,
    ap.Nama_Aplikasi,
    ae.Flag_Aktif        AS Akses_Aktif
FROM N_LMS_Endpoint_Api ep
JOIN N_LMS_Akses_Endpoint ae ON ae.Id_Endpoint_Api = ep.Id_Endpoint_Api
JOIN N_LMS_Api_Aplikasi ap   ON ap.Id_Api_Aplikasi = ae.Id_Api_Aplikasi
WHERE ep.Url_Route = '/api/v1/pa/third-party/penilaian/complete';

SELECT
    ep.Id_Api_Endpoint,
    ep.Method,
    ep.Url_Route,
    ep.Flag_Aktif        AS Endpoint_Aktif,
    ap.Nama_Aplikasi,
    ae.Flag_Aktif        AS Akses_Aktif
FROM N_HRIS_Api_Endpoint ep
JOIN N_HRIS_Api_Akses_Endpoint ae ON ae.Id_Api_Endpoint = ep.Id_Api_Endpoint
JOIN N_HRIS_Api_Aplikasi ap       ON ap.Id_Api_Aplikasi = ae.Id_Api_Aplikasi
WHERE ep.Url_Route = '/api/v1/pa/third-party/penilaian/complete';
