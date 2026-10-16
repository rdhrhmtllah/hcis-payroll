/* Change ID: DDL-2026-163-sinkron-prod-indeks

   Index yang ADA di development tetapi TIDAK ADA di production, dibandingkan
   berdasarkan DEFINISI (kolom kunci + DESC, INCLUDE, UNIQUE, filter), bukan
   nama saja, diukur 2026-10-06 (baca saja). Akun audit production tidak
   melihat teks filter (filter_definition NULL, has_filter = 1), jadi index
   berfilter dianggap sama bila kunci, INCLUDE, dan UNIQUE-nya sama.

   Hasil: satu index, pada tabel baru DDL-2026-162 (= DDL-2026-158).
   Tidak ada index yang kurang pada tabel production yang sudah ada.

   Jalankan SETELAH DDL-2026-162. IDEMPOTEN: dijaga nama index per tabel. */

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;

/* 1. dbo.N_SOP_Item_Versi_Lampiran.IX_SOP_Versi_Lampiran_Versi — tabel baru dari DDL-2026-162 */
IF OBJECT_ID(N'dbo.N_SOP_Item_Versi_Lampiran', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Item_Versi_Lampiran') AND name = N'IX_SOP_Versi_Lampiran_Versi')
    CREATE NONCLUSTERED INDEX [IX_SOP_Versi_Lampiran_Versi] ON dbo.[N_SOP_Item_Versi_Lampiran] ([Id_SOP_Item_Versi], [Flag_Aktif], [Urutan]);

/* VERIFIKASI — hasil HARUS nol baris. */
SELECT N'IX_SOP_Versi_Lampiran_Versi' AS hilang
WHERE NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Item_Versi_Lampiran') AND name = N'IX_SOP_Versi_Lampiran_Versi');
