/* =====================================================================
   33 — Tautan Dokumen SOP pada item OVERLAY DRAFT
   =====================================================================

   MASALAH YANG DITUTUP
   --------------------
   N_SOP_Paket_Draft_Item — salinan kerja item untuk paket yang SUDAH TERBIT —
   tak punya kolom Id_SOP_Dokumen sama sekali. Akibatnya, pada paket terbit:

     * item yang jelas bertaut dokumen tampil seolah belum, karena tautannya
       hidup di tabel live dan tak ikut terbawa ke overlay;
     * menambah item baru di sana MUSTAHIL memakai Dokumen SOP yang sudah ada —
       ia hanya bisa mengetik judul + memilih berkas, lalu dokumennya DIBUAT
       BARU saat promosi (SopDokumenService::ensureForItem).

   Yang kedua itu yang paling menyakitkan: alur pembetulan yang benar —
   "hapus item yang SOP-nya salah, tambahkan item baru dengan SOP yang benar" —
   berhenti di langkah kedua. Paket terbit praktis tak bisa diberi SOP yang
   sudah ada, dengan cara apa pun.

   KENAPA BUKAN "GANTI DOKUMEN DI TEMPAT"
   --------------------------------------
   Progres karyawan dicatat per (Id_SOP_Paket_Item, Id_SOP_Item_Versi).
   Menukar dokumen pada item yang SAMA meninggalkan dua pilihan yang dua-duanya
   buruk: progresnya dipertahankan (catatan berbunyi "sudah selesai" untuk SOP
   yang tak pernah dibaca) atau dihapus (riwayat kepatuhan musnah). Hapus +
   tambah baru menceritakan kebenarannya: item lama nonaktif dengan progresnya
   utuh, item baru mulai dari nol.

   AMAN DIJALANKAN BERULANG. Kode menjaga diri dengan Schema::hasColumn(), jadi
   sebelum skrip ini dijalankan perilakunya tetap seperti sekarang.
   ===================================================================== */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.N_SOP_Paket_Draft_Item')
      AND name = 'Id_SOP_Dokumen'
)
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Draft_Item
        ADD Id_SOP_Dokumen BIGINT NULL;

    PRINT 'N_SOP_Paket_Draft_Item.Id_SOP_Dokumen ditambahkan.';
END
ELSE
    PRINT 'N_SOP_Paket_Draft_Item.Id_SOP_Dokumen sudah ada — dilewati.';
GO

/* Indeks pencarian: dipakai saat promosi menautkan item live ke dokumennya,
   dan saat memeriksa dokumen kembar di dalam satu paket. Terfilter supaya tak
   memuat baris tanpa dokumen (PARAGRAF/TAUTAN), yang jumlahnya banyak. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.N_SOP_Paket_Draft_Item')
      AND name = 'IX_N_SOP_Paket_Draft_Item_Dokumen'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Paket_Draft_Item_Dokumen
        ON dbo.N_SOP_Paket_Draft_Item (Id_SOP_Dokumen)
        WHERE Id_SOP_Dokumen IS NOT NULL;

    PRINT 'IX_N_SOP_Paket_Draft_Item_Dokumen dibuat.';
END
ELSE
    PRINT 'IX_N_SOP_Paket_Draft_Item_Dokumen sudah ada — dilewati.';
GO

/* TIDAK ADA BACKFILL, dan itu disengaja.
   Baris overlay yang sudah ada mewakili item live yang tautannya tetap terbaca
   lewat Id_SOP_Paket_Item_Asal (SopPaketDraftService::items() men-join ke sana).
   Mengisi kolom ini mundur hanya menggandakan sumber kebenaran untuk data yang
   sudah benar — dan dua sumber untuk satu nilai selalu berakhir menyimpang.
   Kolom ini hanya diisi oleh item yang LAHIR di overlay. */
