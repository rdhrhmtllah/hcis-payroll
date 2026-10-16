-- DDL-2026-087-otorisasi-heap-tanpa-pk — PENGEMBALIAN
-- Target: Web_HRIS
-- BELUM DIEKSEKUSI.
--
-- PERINGATAN UNTUK AGEN OTOMATIS
-- AGENTS.md melarang agen menjalankan DROP dalam bentuk apa pun, termasuk DROP
-- INDEX. Berkas ini ADA sebagai kelengkapan alur DDL dan untuk dibaca manusia;
-- ia tidak boleh dieksekusi oleh agen. Jalankan hanya oleh operator basis data
-- yang berwenang.
--
-- Mengembalikan kedua tabel ke bentuk HEAP tanpa indeks. Perlu diingat apa
-- artinya: cek-lalu-tulis di SystemAuthController::togglePermission kembali
-- tanpa penjaga, dan setiap pembacaan kembali memindai seluruh tabel.

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'Web_HRIS'
BEGIN
    RAISERROR('Skrip ini hanya untuk Web_HRIS. Basis data aktif: %s', 16, 1, DB_NAME());
    RETURN;
END;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Role_Menu_Access')
      AND name = N'UQ_NHRIS_RMA_PageAksi_Aktif'
)
    DROP INDEX UQ_NHRIS_RMA_PageAksi_Aktif ON dbo.N_HRIS_Role_Menu_Access;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Role_Menu_Access')
      AND name = N'PK_N_HRIS_Role_Menu_Access'
)
    ALTER TABLE dbo.N_HRIS_Role_Menu_Access DROP CONSTRAINT PK_N_HRIS_Role_Menu_Access;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.HRIS_Page_Access')
      AND name = N'PK_HRIS_Page_Access'
)
    ALTER TABLE dbo.HRIS_Page_Access DROP CONSTRAINT PK_HRIS_Page_Access;

SELECT t.name AS tabel, i.name AS indeks, i.type_desc
FROM sys.indexes i
JOIN sys.tables t ON t.object_id = i.object_id
WHERE t.name IN (N'N_HRIS_Role_Menu_Access', N'HRIS_Page_Access')
ORDER BY t.name, i.index_id;
