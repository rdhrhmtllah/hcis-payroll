/* =====================================================================
   26B — Keunikan versi berpindah ke DOKUMEN
   Fase 2, Master Dokumen SOP.

   Dijalankan MANUAL di SSMS. Modul SOP tidak memakai migration Laravel;
   gerbangnya adalah tests/Feature/SopDokumenFase2Test.php.

   -------------------------------------------------------------------
   KENAPA TERFILTER, dan kenapa itu bukan soal gaya.

   sop:dokumen-verify melaporkan 18 dari 54 baris N_SOP_Item_Versi
   ber-Id_SOP_Dokumen NULL. Ketiganya-belasnya bukan cacat: mereka versi
   yatim milik item yang Tipe_Item-nya pernah diubah menjauh dari tipe
   ber-versi (di dev: seluruhnya PARAGRAF). Memaksa mereka punya dokumen
   akan mengarang entitas kepatuhan untuk sesuatu yang bukan dokumen.

   SQL Server menganggap NULL SETARA di unique index. Karena itu

       CREATE UNIQUE INDEX ... ON N_SOP_Item_Versi (Id_SOP_Dokumen, Versi_Ke)

   tanpa filter akan DITOLAK dengan error 2601 pada baris (NULL, 1) yang
   kedua. Filternya membuat index hanya menegakkan keunikan pada baris
   yang benar-benar bertaut dokumen.

   -------------------------------------------------------------------
   KENAPA UX_N_SOP_Item_Versi_Urut TIDAK DI-DROP DI SINI.

   Rencana induk menyebut fase ini "re-key". Membuang index lama sekarang
   akan menghapus satu-satunya pagar yang melindungi ke-18 baris
   ber-dokumen-NULL itu, sementara pagar penggantinya SENGAJA tak
   menyentuh mereka — nol perlindungan, bukan perpindahan perlindungan.

   Selama 1 dokumen = 1 item (keadaan Fase 2), KEDUA kendala terpenuhi
   bersamaan, jadi mempertahankan yang lama tak berbiaya apa pun.

   Pembuangannya adalah pekerjaan FASE 3: di sanalah satu dokumen mulai
   dirujuk banyak item, Id_SOP_Paket_Item pada baris versi benar-benar
   jadi vestigial, dan index lama berubah dari pagar jadi penghalang.
   Membuangnya di sana juga menempatkannya di fase yang memang mengubah
   perilaku — bukan di fase yang menjanjikan nol perubahan.
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
                 AND name = 'UX_N_SOP_Item_Versi_Dokumen_Urut')
BEGIN
    CREATE UNIQUE INDEX UX_N_SOP_Item_Versi_Dokumen_Urut
        ON N_SOP_Item_Versi (Id_SOP_Dokumen, Versi_Ke)
        WHERE Id_SOP_Dokumen IS NOT NULL;

    PRINT '26B: UX_N_SOP_Item_Versi_Dokumen_Urut dibuat (unik, terfilter).';
END
ELSE
BEGIN
    PRINT '26B: UX_N_SOP_Item_Versi_Dokumen_Urut sudah ada — tidak ada perubahan.';
END
GO

/* Pemeriksaan pasca-jalan: keduanya harus mengembalikan baris.
   is_unique = 1 DAN has_filter = 1 pada index baru; index lama masih ada. */
SELECT i.name, i.is_unique, i.has_filter, i.filter_definition
FROM   sys.indexes i
WHERE  i.object_id = OBJECT_ID('N_SOP_Item_Versi')
  AND  i.name IN ('UX_N_SOP_Item_Versi_Dokumen_Urut', 'UX_N_SOP_Item_Versi_Urut');
GO

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila Fase 2 dibatalkan.

       DROP INDEX UX_N_SOP_Item_Versi_Dokumen_Urut ON N_SOP_Item_Versi;

   Tak ada data yang hilang: index ini murni kendala dan tak menyimpan
   apa pun yang tidak sudah ada di kolomnya. Kode Fase 2 tetap berjalan
   tanpanya — yang hilang hanya penegakannya, bukan fungsinya.
   --------------------------------------------------------------------- */
