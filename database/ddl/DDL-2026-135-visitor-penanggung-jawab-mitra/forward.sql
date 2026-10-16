-- Change ID: DDL-2026-135-visitor-penanggung-jawab-mitra
--
-- Kunjungan dari aplikasi mitra tidak punya karyawan pengaju. Selama ini
-- Kode_Karyawan_PIC diisi dari registry aplikasi, sehingga SELURUH kunjungan
-- mitra tercatat atas nama satu orang yang tidak pernah mengajukannya - dan
-- dari layar mana pun ia terlihat seperti orang itu yang mendaftarkan tamunya.
--
-- Dua hal dipisahkan: SIAPA YANG DITUJU (pic_tujuan, sudah ada) dan SIAPA YANG
-- BERTANGGUNG JAWAB (kolom baru ini, wajib dikirim mitra).

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail') AND name = 'Nama_Penanggung_Jawab'
)
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD Nama_Penanggung_Jawab varchar(150) NULL;
END;
GO

-- Kode_Karyawan_PIC boleh kosong pada proyeksi gerbang.
--
-- Bukan melonggarkan aturan, melainkan berhenti berbohong: kunjungan mitra
-- memang tidak punya pemiliknya di dalam HCIS. Yang TETAP wajib adalah
-- Nama_PIC - gerbang butuh nama untuk dihubungi, bukan kode - dan namanya kini
-- diambil dari karyawan bila ada kodenya, atau dari Nama_Penanggung_Jawab bila
-- tidak (lihat VisitorPermitQueryService::baseTamuQuery).
IF EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('dbo.N_VISITOR_Tamu_Efektif')
       AND name = 'Kode_Karyawan_PIC' AND is_nullable = 0
)
BEGIN
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif
        ALTER COLUMN Kode_Karyawan_PIC varchar(20) NULL;
END;
GO
