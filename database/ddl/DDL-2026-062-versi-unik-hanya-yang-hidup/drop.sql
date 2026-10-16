/*  DDL-2026-062 — PENGEMBALIAN (manual)
    ------------------------------------------------------------------------
    Alat bantu pemulihan MANUAL. Agen tidak pernah menjalankannya.

    Urutannya kebalikan dari forward: lepaskan FK dulu, baru kembalikan tipe
    kolom, baru kembalikan indeks. FK yang masih terpasang menolak ALTER COLUMN
    pada kolom yang dirujuknya.

    PERINGATAN — BAGIAN 1 BISA GAGAL, DAN GAGALNYA BENAR.
    Sesudah forward berjalan, mungkin sudah lahir dokumen dengan DUA versi
    bernomor sama (satu batal, satu hidup) — itulah gunanya change ini. Indeks
    lama menolak keadaan itu dan akan gagal dengan galat 1505 selama baris
    tersebut masih ada.

    Itu bukan cacat skripnya: mengembalikan indeks berarti memutuskan nomor mana
    yang harus diubah, dan keputusan itu tak boleh diambil diam-diam oleh sebuah
    skrip pemulihan. Daftar bentroknya diperlihatkan lebih dulu di bawah.
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;

/* Perlihatkan yang akan menghalangi, sebelum mencoba apa pun. */
SELECT Id_SOP_Dokumen, Versi_Ke, COUNT(*) AS Jumlah
  FROM N_SOP_Item_Versi
 WHERE Id_SOP_Dokumen IS NOT NULL
 GROUP BY Id_SOP_Dokumen, Versi_Ke HAVING COUNT(*) > 1;
GO

/* ── 4'. Lepaskan FK rujukan versi ─────────────────────────────────────── */
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Progress_Versi')
ALTER TABLE N_SOP_Progress DROP CONSTRAINT FK_N_SOP_Progress_Versi;
GO
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Paket_Item_Versi_Aktif')
ALTER TABLE N_SOP_Paket_Item DROP CONSTRAINT FK_N_SOP_Paket_Item_Versi_Aktif;
GO

/* ── 3'. Lepaskan FK pointer, kembalikan tipe kolom ────────────────────── */
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Dokumen_Versi_Aktif')
ALTER TABLE N_SOP_Dokumen DROP CONSTRAINT FK_N_SOP_Dokumen_Versi_Aktif;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'IX_N_SOP_Dokumen_Tipe')
DROP INDEX IX_N_SOP_Dokumen_Tipe ON N_SOP_Dokumen;
GO

ALTER TABLE N_SOP_Dokumen ALTER COLUMN Id_Versi_Aktif BIGINT NULL;
GO

CREATE NONCLUSTERED INDEX IX_N_SOP_Dokumen_Tipe
    ON N_SOP_Dokumen (Flag_Aktif, Tipe)
    INCLUDE (Judul, Nomor_SOP_Resmi, Id_Versi_Aktif);
GO

/* ── 2'. Lepaskan penjaga satu-PUBLISHED ───────────────────────────────── */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
             AND name = 'UX_N_SOP_Item_Versi_Satu_Published')
DROP INDEX UX_N_SOP_Item_Versi_Satu_Published ON N_SOP_Item_Versi;
GO

/* ── 1'. Kembalikan indeks nomor versi yang lama ───────────────────────── */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
                 AND name = 'UX_N_SOP_Item_Versi_Dokumen_Urut')
CREATE UNIQUE NONCLUSTERED INDEX UX_N_SOP_Item_Versi_Dokumen_Urut
    ON N_SOP_Item_Versi (Id_SOP_Dokumen, Versi_Ke)
 WHERE Id_SOP_Dokumen IS NOT NULL;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
             AND name = 'UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup')
DROP INDEX UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup ON N_SOP_Item_Versi;
GO
