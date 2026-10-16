-- DML-2026-143-visitor-registrasi-api-cek-kartu-publik
-- Target: Web_HRIS (development dan production)
--
-- Mendaftarkan layanan CEK KARTU PERMIT PUBLIK (Cloud Run, repo terpisah
-- rdhrhmtllah/cek-kartu-permit) sebagai aplikasi API, dan mengizinkannya HANYA
-- ke satu endpoint: GET /api/v1/hcis/visitor/kartu-permit/{kode}.
--
-- Api_Public / Api_Secret DIBANGKITKAN SAAT EKSEKUSI dan TIDAK PERNAH ditulis
-- ke berkas ini. Isi kedua variabel di bawah sebelum menjalankan, lalu simpan
-- pasangan kuncinya ke Secret Manager layanan publik — bukan ke repo, chat,
-- atau tiket. Gunakan kunci BERBEDA untuk development dan production.
--
-- Idempoten: aplikasi, endpoint, dan hak aksesnya tidak digandakan.

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @ApiPublic varchar(100) = N'<ISI_SAAT_EKSEKUSI>';
DECLARE @ApiSecret varchar(255) = N'<ISI_SAAT_EKSEKUSI>';
DECLARE @Nama      nvarchar(100) = N'Cek Kartu Permit Publik';
DECLARE @Rute      nvarchar(255) = N'/api/v1/hcis/visitor/kartu-permit/{kode}';

IF @ApiPublic = N'<ISI_SAAT_EKSEKUSI>' OR @ApiSecret = N'<ISI_SAAT_EKSEKUSI>'
BEGIN
    RAISERROR('Isi @ApiPublic dan @ApiSecret terlebih dahulu.', 16, 1);
    RETURN;
END

DECLARE @Grup int = (SELECT TOP 1 Id_Grup_Fitur FROM N_HRIS_Master_Grup_Fitur WHERE Kode_Grup_Fitur = 'VISITOR');

IF @Grup IS NULL
BEGIN
    RAISERROR('Grup fitur VISITOR tidak ditemukan.', 16, 1);
    RETURN;
END

BEGIN TRANSACTION;

/* 1. Aplikasi konsumen */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = @Nama)
    INSERT INTO N_HRIS_Api_Aplikasi (Id_Grup_Fitur, Nama_Aplikasi, Api_Public, Api_Secret, Base_Url, Arah, Created_At, Flag_Aktif)
    VALUES (@Grup, @Nama, @ApiPublic, @ApiSecret, NULL, 'IN', SYSDATETIME(), 'Y');

/* 2. Endpoint — Url_Route WAJIB pola rute ("{kode}" apa adanya), karena
      HrisApiGuard mencocokkan whitelist ke route()->uri(). */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Endpoint WHERE Url_Route = @Rute AND Method = 'GET')
    INSERT INTO N_HRIS_Api_Endpoint (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_At, Flag_Aktif)
    VALUES (@Grup, @Rute, 'GET', 'IN', N'Status kartu permit (data minimal) untuk layanan cek kartu publik', SYSDATETIME(), 'Y');

/* 3. Hak akses — HANYA endpoint ini */
DECLARE @IdAplikasi int = (SELECT TOP 1 Id_Api_Aplikasi FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = @Nama);
DECLARE @IdEndpoint int = (SELECT TOP 1 Id_Api_Endpoint FROM N_HRIS_Api_Endpoint WHERE Url_Route = @Rute AND Method = 'GET');

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Akses_Endpoint WHERE Id_Api_Aplikasi = @IdAplikasi AND Id_Api_Endpoint = @IdEndpoint)
    INSERT INTO N_HRIS_Api_Akses_Endpoint (Id_Api_Aplikasi, Id_Api_Endpoint, Created_At, Flag_Aktif)
    VALUES (@IdAplikasi, @IdEndpoint, SYSDATETIME(), 'Y');

COMMIT TRANSACTION;

/* Verifikasi: aplikasi hanya punya SATU endpoint, yaitu endpoint ini. */
/*
SELECT a.Nama_Aplikasi, e.Method, e.Url_Route, ae.Flag_Aktif
  FROM N_HRIS_Api_Aplikasi a
  JOIN N_HRIS_Api_Akses_Endpoint ae ON ae.Id_Api_Aplikasi = a.Id_Api_Aplikasi
  JOIN N_HRIS_Api_Endpoint e ON e.Id_Api_Endpoint = ae.Id_Api_Endpoint
 WHERE a.Nama_Aplikasi = N'Cek Kartu Permit Publik';
*/

/* Pencabutan darurat (kunci bocor): nonaktifkan aplikasinya, lalu terbitkan kunci baru. */
/*
UPDATE N_HRIS_Api_Aplikasi SET Flag_Aktif = 'T' WHERE Nama_Aplikasi = N'Cek Kartu Permit Publik';
*/
