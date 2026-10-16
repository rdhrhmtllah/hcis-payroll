-- DML-2026-080-registrasi-api-permit-mitra
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker.
-- BELUM DIEKSEKUSI.
--
-- Mendaftarkan SATU aplikasi mitra B2B dan memberinya akses ke ketiga endpoint
-- permit tamu. Endpointnya sendiri sudah terdaftar lewat
-- DDL-2026-079-visitor-permit-api-b2b; berkas ini hanya menautkan aplikasi ke
-- endpoint itu.
--
-- Prasyarat: DDL-2026-079 sudah dieksekusi di basis data yang sama.
--
-- Untuk mitra KEDUA dan seterusnya: salin berkas ini sebagai change baru dengan
-- nomor sendiri. Jangan menjalankan ulang berkas ini dengan nilai berbeda —
-- catatan change harus menggambarkan satu tindakan yang benar-benar terjadi.
--
-- ==========================================================================
-- YANG HARUS DIISI SEBELUM EKSEKUSI (tiga nilai wajib, satu opsional)
-- ==========================================================================
--
-- Api_Public / Api_Secret DIBANGKITKAN SAAT EKSEKUSI dan tidak pernah ditulis
-- ke berkas ini. Kunci rahasia yang masuk repositori akan tetap ada di riwayat
-- git selamanya, bahkan sesudah dihapus dari berkasnya.
--
-- Serahkan pasangan kuncinya ke mitra lewat jalur rahasia — bukan lewat repo,
-- chat, atau tiket. Api_Secret setara kunci gerbang: siapa pun yang memegangnya
-- dapat menerbitkan izin akses fisik ke area perusahaan.
--
-- Konvensi kunci publik mengikuti NAMA APLIKASI, huruf besar tanpa spasi,
-- lalu garis bawah dan nilai acak — sama seperti 'WEBCAREERS_...' milik Web
-- Careers. Kunci publik terbaca di log dan header, jadi awalan yang menyebutkan
-- pemiliknya membuat jejak permintaan bisa ditelusuri tanpa membuka tabel.
--
-- Saran pembangkitan (jalankan di mesin Anda, bukan di berkas ini):
--   php -r "echo 'B2B_'.bin2hex(random_bytes(12)).PHP_EOL;"   -- public, ganti awalannya
--   php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"          -- secret
--
-- Idempoten: dijalankan dua kali tidak menghasilkan aplikasi atau hak akses
-- ganda.

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @NamaAplikasi varchar(150) = N'<ISI_SAAT_EKSEKUSI>';
DECLARE @ApiPublic    varchar(100) = N'<ISI_SAAT_EKSEKUSI>';
DECLARE @ApiSecret    varchar(255) = N'<ISI_SAAT_EKSEKUSI>';

-- OPSIONAL. Karyawan penanggung jawab permit dari aplikasi ini.
--
-- Boleh dibiarkan kosong (N''). Bila kosong, pemohon permitnya adalah APLIKASI
-- itu sendiri: kodenya 'API#<id>' dan namanya nama aplikasi. Itu bukan
-- kekurangan melainkan kenyataan — permit mitra memang tidak diajukan karyawan
-- mana pun.
--
-- Bila diisi, kodenya WAJIB ada di tabel Karyawan, dan orang itulah yang muncul
-- sebagai pemohon di meja verifikator serta di proyeksi gerbang. Pilih yang
-- memang bertanggung jawab atas kerja sama ini, bukan orang yang tidak
-- tahu-menahu.
--
-- Konsekuensi bila kosong: notifikasi ke pemohon tidak terkirim karena tidak
-- ada karyawan untuk dikirimi. Tidak merugikan — mitra memang mengetahui
-- keputusan lewat endpoint status, bukan lewat notifikasi HCIS.
DECLARE @KodeKaryawanPIC varchar(20) = N'';

DECLARE @GrupVisitor int = 4;   -- N_HRIS_Master_Grup_Fitur: VISITOR (diverifikasi 2026-09-09)

/* =====================================================================
   PENJAGA — menolak jalan kalau ada yang belum diisi
   ===================================================================== */

IF @NamaAplikasi = N'<ISI_SAAT_EKSEKUSI>'
   OR @ApiPublic = N'<ISI_SAAT_EKSEKUSI>'
   OR @ApiSecret = N'<ISI_SAAT_EKSEKUSI>'
BEGIN
    RAISERROR('Isi @NamaAplikasi, @ApiPublic, dan @ApiSecret terlebih dahulu.', 16, 1);
    RETURN;
END

-- PIC boleh kosong. Tetapi PIC yang DIISI dan tidak ada di tabel Karyawan lebih
-- buruk daripada tidak diisi sama sekali: pemohonnya jadi tidak punya nama,
-- padahal Nama_Pemohon di proyeksi bersifat NOT NULL. Galatnya baru muncul saat
-- permit pertama diterbitkan dan tidak menyebut PIC sama sekali.
IF @KodeKaryawanPIC <> N''
   AND NOT EXISTS (SELECT 1 FROM Karyawan WHERE Kode_Karyawan = @KodeKaryawanPIC)
BEGIN
    RAISERROR('Kode_Karyawan_PIC diisi tetapi tidak ada di tabel Karyawan. Kosongkan, atau isi dengan kode yang benar.', 16, 1);
    RETURN;
END

-- Kunci publik dipakai untuk MENCARI aplikasi di setiap permintaan; dua
-- aplikasi berkunci sama membuat permintaan salah satunya dikenali sebagai
-- milik yang lain.
IF EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Api_Public = @ApiPublic
           AND Nama_Aplikasi <> @NamaAplikasi)
BEGIN
    RAISERROR('Api_Public sudah dipakai aplikasi lain. Bangkitkan kunci publik baru.', 16, 1);
    RETURN;
END

-- Ketiga endpoint wajib sudah ada. Kalau belum, DDL-2026-079 belum dijalankan
-- di basis data ini, dan hak akses yang dibuat di bawah tidak akan menautkan
-- apa pun.
IF (SELECT COUNT(*) FROM N_HRIS_Api_Endpoint
    WHERE Url_Route IN (N'/api/v1/hcis/visitor/permit-tamu',
                        N'/api/v1/hcis/visitor/permit-tamu/{no_transaksi}',
                        N'/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal')
    AND Flag_Aktif = 'Y') < 3
BEGIN
    RAISERROR('Ketiga endpoint permit tamu belum terdaftar. Jalankan DDL-2026-079 lebih dulu.', 16, 1);
    RETURN;
END

/* =====================================================================
   BAGIAN 1 — Aplikasi mitra
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = @NamaAplikasi)
BEGIN
    INSERT INTO N_HRIS_Api_Aplikasi
        (Id_Grup_Fitur, Nama_Aplikasi, Api_Public, Api_Secret, Base_Url, Arah,
         Kode_Karyawan_PIC, Created_At, Flag_Aktif)
    VALUES
        (@GrupVisitor, @NamaAplikasi, @ApiPublic, @ApiSecret, NULL, 'IN',
         NULLIF(@KodeKaryawanPIC, N''), SYSDATETIME(), 'Y');
END
ELSE
BEGIN
    -- Aplikasinya sudah ada (mis. berkas ini dijalankan ulang). PIC-nya
    -- diluruskan, tetapi pasangan kuncinya TIDAK ditimpa: menimpa kunci yang
    -- sedang dipakai mitra akan memutus integrasinya seketika, dan tidak ada
    -- yang tahu penyebabnya selain "tiba-tiba 401".
    UPDATE N_HRIS_Api_Aplikasi
       SET Kode_Karyawan_PIC = NULLIF(@KodeKaryawanPIC, N''),
           Updated_At = SYSDATETIME()
     WHERE Nama_Aplikasi = @NamaAplikasi;
END

/* =====================================================================
   BAGIAN 2 — Hak akses ke ketiga endpoint permit tamu

   Tanpa bagian ini aplikasinya dikenali tetapi setiap panggilannya dibalas 403,
   dan /api/docs menyajikan dokumen berisi nol path kepadanya.
   ===================================================================== */

DECLARE @IdAplikasi int =
    (SELECT TOP 1 Id_Api_Aplikasi FROM N_HRIS_Api_Aplikasi WHERE Nama_Aplikasi = @NamaAplikasi);

INSERT INTO N_HRIS_Api_Akses_Endpoint (Id_Api_Aplikasi, Id_Api_Endpoint, Created_At, Flag_Aktif)
SELECT @IdAplikasi, ep.Id_Api_Endpoint, SYSDATETIME(), 'Y'
FROM   N_HRIS_Api_Endpoint ep
WHERE  ep.Url_Route IN (N'/api/v1/hcis/visitor/permit-tamu',
                        N'/api/v1/hcis/visitor/permit-tamu/{no_transaksi}',
                        N'/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal')
AND    ep.Flag_Aktif = 'Y'
AND    NOT EXISTS (SELECT 1 FROM N_HRIS_Api_Akses_Endpoint ae
                   WHERE ae.Id_Api_Aplikasi = @IdAplikasi
                   AND   ae.Id_Api_Endpoint = ep.Id_Api_Endpoint);

/* =====================================================================
   BAGIAN 3 — Verifikasi (jalankan dan periksa hasilnya)
   ===================================================================== */

SELECT  a.Id_Api_Aplikasi,
        a.Nama_Aplikasi,
        a.Kode_Karyawan_PIC,
        k.Nama AS Nama_PIC,
        a.Flag_Aktif,
        (SELECT COUNT(*) FROM N_HRIS_Api_Akses_Endpoint ae
          WHERE ae.Id_Api_Aplikasi = a.Id_Api_Aplikasi AND ae.Flag_Aktif = 'Y') AS Jumlah_Endpoint
FROM    N_HRIS_Api_Aplikasi a
LEFT JOIN Karyawan k ON k.Kode_Karyawan = a.Kode_Karyawan_PIC
WHERE   a.Nama_Aplikasi = @NamaAplikasi;

-- Harapan: satu baris dengan Jumlah_Endpoint = 3.
--
-- Kode_Karyawan_PIC NULL itu SAH: pemohon permitnya menjadi aplikasi itu
-- sendiri. Yang TIDAK sah adalah Kode_Karyawan_PIC terisi sementara Nama_PIC
-- NULL — artinya kodenya tidak ada di tabel Karyawan, dan penjaga di atas
-- seharusnya sudah menolaknya.
GO
