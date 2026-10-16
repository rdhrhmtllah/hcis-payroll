/* =====================================================================
   DML-2026-115-seed-sop-prioritas
   ---------------------------------------------------------------------
   Mengisi master dbo.N_SOP_Prioritas di production (emi_db_web_hr), yang
   saat ini KOSONG (0 baris) sementara development punya 3 baris.

   Temuan sumber : docs/audit/daftar-temuan-sop-production-2026-09-16.md
                   (Daftar 5 - Data master)

   ---------------------------------------------------------------------
   KENAPA PERLU, PADAHAL TIDAK MENYEBABKAN ERROR
   ---------------------------------------------------------------------
   SopPrioritasService::semua() jatuh ke konstanta PHP self::BAWAAN ketika
   tabelnya kosong, jadi layar tetap menampilkan tiga tingkat dan tidak ada
   500. Tapi selama tabelnya kosong:

     - tingkat prioritas buatan admin tidak bisa ada sama sekali;
     - Id_SOP_Prioritas tidak pernah terbentuk, sehingga apa pun yang
       menautkan prioritas lewat Id tidak punya sasaran;
     - production dan development berbeda diam-diam, dan perbedaan itu baru
       ketahuan saat admin mencoba menambah tingkat baru.

   Tiga baris di bawah SAMA PERSIS dengan development dan dengan konstanta
   BAWAAN di app/Services/LMS/SOP/SopPrioritasService.php baris 37-41, jadi
   perilaku layar tidak berubah sedikit pun setelah seed ini dijalankan -
   yang berubah hanya sumbernya: dari konstanta menjadi master.

   ---------------------------------------------------------------------
   SIFAT PERUBAHAN
   ---------------------------------------------------------------------
   - INSERT saja. Tidak ada UPDATE, DELETE, MERGE, TRUNCATE, atau DROP.
   - Idempoten: berjangkar pada kunci bisnis Kode (UQ_N_SOP_Prioritas_Kode),
     jadi menjalankannya berulang tidak menggandakan baris, dan di
     development menjadi no-op penuh.
   - Id_SOP_Prioritas adalah IDENTITY dan SENGAJA TIDAK DISALIN. Nilai
     identity berbeda antar server; menyamakannya dengan SET IDENTITY_INSERT
     akan menabrak counter production. Seluruh relasi diselesaikan lewat
     Kode, bukan Id.
   - Created_At memakai GETDATE() pada saat dijalankan, bukan menyalin cap
     waktu development - baris ini memang baru lahir di production.
   - Created_By dibiarkan NULL, sama seperti development: baris ini lahir
     dari seed sistem, bukan dari tindakan seorang pengguna.

   Target     : emi_db_web_hr (production)
   Aman juga di: Web_HRIS (development) -> no-op, ketiga Kode sudah ada
   Baris terdampak yang diharapkan: 3 di production, 0 di development
   Rollback   : lihat bagian ROLLBACK di kaki berkas (MANUAL)
   Verifikasi : verify.sql
   ===================================================================== */

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

PRINT '=== DML-2026-115 dijalankan pada database: ' + DB_NAME() + ' ===';
GO

/* Penjaga: tabelnya harus ada sebelum apa pun disentuh. */
IF OBJECT_ID('dbo.N_SOP_Prioritas', 'U') IS NULL
BEGIN
    RAISERROR('dbo.N_SOP_Prioritas tidak ditemukan. DML dibatalkan.', 16, 1);
END
GO

BEGIN TRANSACTION;

DECLARE @Sebelum int = (SELECT COUNT(*) FROM dbo.N_SOP_Prioritas);

INSERT INTO dbo.N_SOP_Prioritas
    (Kode, Nama, Urutan, Warna, Flag_Aktif, Created_By, Created_At, Updated_By, Updated_At)
SELECT  v.Kode, v.Nama, v.Urutan, v.Warna, 'Y', NULL, GETDATE(), NULL, NULL
FROM   (VALUES
            ('KRITIS', 'Sangat Penting', 10, '#EF4444'),
            ('TINGGI', 'Tinggi',         20, '#F59E0B'),
            ('NORMAL', 'Normal',         30, '#94A3B8')
       ) AS v (Kode, Nama, Urutan, Warna)
WHERE NOT EXISTS (
        SELECT 1
        FROM dbo.N_SOP_Prioritas p
        WHERE p.Kode = v.Kode
      );

DECLARE @Masuk int = @@ROWCOUNT;
DECLARE @Sesudah int = (SELECT COUNT(*) FROM dbo.N_SOP_Prioritas);

PRINT 'Baris sebelum : ' + CAST(@Sebelum  AS varchar(10));
PRINT 'Baris masuk   : ' + CAST(@Masuk    AS varchar(10));
PRINT 'Baris sesudah : ' + CAST(@Sesudah  AS varchar(10));

/* Penjaga hasil: apa pun keadaan awalnya, setelah DML ini ketiga Kode wajib
   ada dan jumlahnya tidak boleh menyusut. Kalau tidak, batalkan seluruhnya. */
IF @Sesudah < @Sebelum
    OR (SELECT COUNT(*) FROM dbo.N_SOP_Prioritas WHERE Kode IN ('KRITIS', 'TINGGI', 'NORMAL')) <> 3
BEGIN
    ROLLBACK TRANSACTION;
    RAISERROR('Hasil tidak sesuai harapan. Seluruh perubahan dibatalkan.', 16, 1);
END
ELSE
BEGIN
    COMMIT TRANSACTION;
    PRINT 'COMMIT. Master N_SOP_Prioritas siap.';
END
GO

/* Tampilkan hasil akhir. */
SELECT Id_SOP_Prioritas, Kode, Nama, Urutan, Warna, Flag_Aktif, Created_At
FROM   dbo.N_SOP_Prioritas
ORDER BY Urutan, Kode;
GO

/* =====================================================================
   ROLLBACK -- SENGAJA DIKOMENTARI, JANGAN DIJALANKAN OTOMATIS.

   Hanya sah dijalankan bila seed ini terbukti keliru DAN belum ada paket
   yang memakai ketiga Kode tersebut. Periksa dulu:

     SELECT Prioritas, COUNT(*) FROM dbo.N_SOP_Master_Paket GROUP BY Prioritas;

   Kalau masih ada paket yang memakainya, JANGAN dihapus - layar akan
   kehilangan lencana prioritasnya.

   DELETE FROM dbo.N_SOP_Prioritas
   WHERE Kode IN ('KRITIS', 'TINGGI', 'NORMAL');
   ===================================================================== */
