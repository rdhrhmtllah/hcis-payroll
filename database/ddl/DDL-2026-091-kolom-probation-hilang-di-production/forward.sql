-- DDL-2026-091-kolom-probation-hilang-di-production
-- Target: production (Karyawan). Development SUDAH punya kolomnya.
-- BELUM DIEKSEKUSI.
--
-- BAGIAN 0 : gerbang basis data
-- BAGIAN 1 : tambahkan kolom bila belum ada
-- BAGIAN 2 : verifikasi
-- BAGIAN 3 : catatan untuk peninjau
--
-- ── TEMUAN ───────────────────────────────────────────────────────────────────
--
-- Dua widget dashboard cuti MATI di production. Log:
--
--   CutiDashboardService::saldoAkanAktif failed
--   CutiDashboardService::karyawanBerpotensiKonflik failed
--   SQLSTATE[42S22]: Invalid column name 'Tanggal_Akhir_Probation'.
--
-- Kolomnya ADA di development (team311/Web_HRIS) dan di salinan lokal, tetapi
-- TIDAK ADA di production. Ini bukan cacat kode: CutiDashboardService memang
-- boleh memilih kolom yang ada di skema resmi.
--
-- ── KENAPA DRIFT INI BISA TERJADI ────────────────────────────────────────────
--
-- Tidak ada satu pun berkas di database/ddl/ yang pernah menambahkan kolom ini.
-- Artinya ia masuk ke development di luar alur DDL — langsung ALTER di sana —
-- sehingga tak pernah ada artefak yang bisa dijalankan ke production. Persis
-- kegagalan yang alur DDL di AGENTS.md ada untuk mencegah.
--
-- ── DEFINISI YANG DISALIN DARI DEVELOPMENT ───────────────────────────────────
--
-- sys.columns pada Web_HRIS: datetime, NULL diizinkan, tanpa default constraint.
-- Terisi 1 dari 965 baris — jadi kolomnya praktis kosong bahkan di development;
-- yang dibutuhkan production hanya KEBERADAANNYA supaya kueri tidak gagal.
--
-- ── RISIKO ───────────────────────────────────────────────────────────────────
--
-- Menambah kolom NULL tanpa default adalah perubahan metadata saja di SQL Server:
-- tidak menulis ulang tabel, tidak mengunci lama, walau Karyawan besar. Tidak ada
-- data yang berubah; baris yang sudah ada bernilai NULL.
--
-- Pertanyaan yang TIDAK dijawab berkas ini: apakah nilainya perlu diisi. Dengan
-- 1 dari 965 baris terisi di development, fiturnya tampak belum dipakai. Pengisian
-- data adalah DML terpisah dan keputusan pemilik proses, bukan bagian dari ini.

-- ── BAGIAN 0 : gerbang basis data ────────────────────────────────────────────
IF DB_NAME() NOT IN ('Web_HRIS', 'Web_HRIS_Cut_Off')
BEGIN
    RAISERROR('DDL-2026-091 dijalankan pada basis data yang salah.', 16, 1);
    SET NOEXEC ON;
END;
GO

IF OBJECT_ID('dbo.Karyawan', 'U') IS NULL
BEGIN
    RAISERROR('Tabel Karyawan tidak ada di basis data ini.', 16, 1);
    SET NOEXEC ON;
END;
GO

-- ── BAGIAN 1 : tambahkan kolom bila belum ada ────────────────────────────────
-- Bersyarat, jadi aman dijalankan di development (di sana ia no-op) maupun
-- dijalankan ulang di production.
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('dbo.Karyawan')
       AND name = 'Tanggal_Akhir_Probation'
)
BEGIN
    ALTER TABLE dbo.Karyawan ADD Tanggal_Akhir_Probation datetime NULL;
END;
GO

-- ── BAGIAN 2 : verifikasi ────────────────────────────────────────────────────
-- Harus memulangkan satu baris: datetime, is_nullable = 1.
SELECT c.name AS kolom, t.name AS tipe, c.is_nullable
  FROM sys.columns c
  JOIN sys.types t ON t.user_type_id = c.user_type_id
 WHERE c.object_id = OBJECT_ID('dbo.Karyawan')
   AND c.name = 'Tanggal_Akhir_Probation';
GO

SET NOEXEC OFF;
GO

-- ── BAGIAN 3 : catatan untuk peninjau ────────────────────────────────────────
--
-- Setelah ini dijalankan, kedua widget berhenti melempar dan menampilkan nilai
-- kosong (NULL) untuk seluruh karyawan production. Itu benar dan jujur: datanya
-- memang belum ada. Bila probation perlu benar-benar dihitung, isian datanya
-- diajukan sebagai DML tersendiri.
--
-- Layak diperiksa bersamaan: kolom lain apa lagi yang ada di development tetapi
-- tidak di production. Perintah hanya-baca `php artisan db:inventaris-skema`
-- membandingkan keduanya; drift ini kemungkinan bukan satu-satunya.
