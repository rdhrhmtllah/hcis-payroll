/* =====================================================================
   30 — Blok penanda tangan: pengesah boleh lebih dari satu orang

   Dijalankan MANUAL di SSMS, SETELAH 29.

   KENAPA. Pengesahan di sini bukan alur persetujuan — tanda tangan
   basahnya ada di dokumen fisik. Yang disimpan sistem adalah DEKLARASI
   siapa saja yang menyetujui SOP ini. Dan blok tanda tangan di dokumen
   nyata hampir selalu lebih dari satu baris (disiapkan / diperiksa /
   disetujui), sehingga satu kolom Disahkan_Oleh tak akan pernah cocok
   dengan kertasnya.

   KOLOM TUNGGAL DI N_SOP_Item_Versi TIDAK DIHAPUS, dan itu disengaja.
   Yang membacanya bukan cuma layar Master:
     - SopDocumentControlService menghitung tanpa_pengesahan, menyaring
       ember TANPA_PENGESAHAN, dan menyusun register kepatuhan
     - SopComplianceExportService mengekspornya
     - SopAdminBuilderController & SopBerkasController menulisnya
   Menghapusnya berarti membongkar semua itu sekaligus, tanpa satu pun
   yang menjadi lebih benar.

   Maka pembagiannya:
     - TABEL INI  = blok lengkap, kebenaran tentang siapa saja meneken
     - KOLOM TUNGGAL = cermin penanda tangan PERTAMA, supaya pembaca lama
                       tetap menjawab pertanyaan yang memang mereka
                       tanyakan ("ada pengesahan atau tidak")
   Pembacaan memakai aturan: pakai baris tabel ini bila ada; bila tidak,
   kolom tunggal dibaca sebagai blok berisi satu baris. Dengan begitu
   versi lama dan versi buatan Builder tak perlu diisi mundur sama sekali.

   NAMA DAN JABATAN DISALIN, BUKAN DI-JOIN. Sama seperti kolom tunggal:
   dokumen terkendali mencatat siapa meneken PADA SAAT ITU. Kalau ditarik
   dari master karyawan saat dibaca, SOP yang sudah sah berubah sendiri
   catatannya begitu orangnya naik jabatan atau resign.
   ===================================================================== */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES
               WHERE TABLE_NAME = 'N_SOP_Item_Versi_Pengesah')
BEGIN
    CREATE TABLE N_SOP_Item_Versi_Pengesah (
        Id_SOP_Item_Versi_Pengesah INT IDENTITY(1,1) NOT NULL,
        Id_SOP_Item_Versi          INT           NOT NULL,

        /* Label baris tanda tangan, apa adanya seperti di kertas:
           "Disiapkan oleh", "Diperiksa oleh", "Disetujui oleh",
           "Mengetahui". Sengaja teks bebas, bukan daftar tetap — blok
           tanda tangan berbeda antar jenis dokumen, dan mengunci
           daftarnya berarti satu perubahan skema tiap kali ada label
           baru. */
        Peran                      NVARCHAR(50)  NULL,

        /* SNAPSHOT. Panjangnya mengikuti kolom tunggal di
           N_SOP_Item_Versi supaya salinan tak pernah terpotong saat
           berpindah antar keduanya. */
        Nama                       NVARCHAR(150) NOT NULL,
        Jabatan                    NVARCHAR(150) NULL,

        /* RUJUKAN, pendamping snapshot. NULL itu sah dan bermakna:
           penanda tangan bisa orang di luar HRIS. */
        Kode_Karyawan              VARCHAR(20)   NULL,

        /* Urutan baris di blok — kertas punya urutan, dan urutan itu
           bagian dari maknanya (yang menyiapkan bukan yang menyetujui). */
        Urutan                     INT           NOT NULL CONSTRAINT DF_SOP_Pengesah_Urutan DEFAULT (1),

        Created_By                 INT           NULL,
        Created_At                 DATETIME      NULL,
        Updated_By                 INT           NULL,
        Updated_At                 DATETIME      NULL,
        Flag_Aktif                 CHAR(1)       NOT NULL CONSTRAINT DF_SOP_Pengesah_Aktif DEFAULT ('Y'),

        CONSTRAINT PK_N_SOP_Item_Versi_Pengesah PRIMARY KEY (Id_SOP_Item_Versi_Pengesah),
        CONSTRAINT FK_N_SOP_Item_Versi_Pengesah_Versi
            FOREIGN KEY (Id_SOP_Item_Versi) REFERENCES N_SOP_Item_Versi (Id_SOP_Item_Versi)
    );

    /* Pembacaan selalu "semua penanda tangan versi ini, urut". */
    CREATE INDEX IX_N_SOP_Item_Versi_Pengesah_Versi
        ON N_SOP_Item_Versi_Pengesah (Id_SOP_Item_Versi, Urutan)
        WHERE Flag_Aktif = 'Y';

    PRINT '30: tabel N_SOP_Item_Versi_Pengesah dibuat.';
END
ELSE
BEGIN
    PRINT '30: tabel N_SOP_Item_Versi_Pengesah sudah ada — tidak ada perubahan.';
END
GO

/* TIDAK ADA PENGISIAN MUNDUR, dan itu bukan kelalaian. Aturan bacanya
   sudah menangani versi lama: tanpa baris di sini, kolom tunggal dibaca
   sebagai blok berisi satu baris. Menyalin empat baris yang isinya
   masukan uji ("sasdsdasd", "asdasd", "Si Anu") hanya memindahkan sampah
   ke tabel yang baru bersih. */

/* Pemeriksaan pasca-jalan. */
SELECT
    (SELECT COUNT(*) FROM N_SOP_Item_Versi_Pengesah)                  AS baris_blok,
    (SELECT COUNT(*) FROM N_SOP_Item_Versi WHERE Disahkan_Oleh IS NOT NULL)
                                                                      AS versi_dgn_kolom_tunggal;
GO

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila perubahan ini dibatalkan.

     DROP TABLE N_SOP_Item_Versi_Pengesah;

   Kolom tunggal di N_SOP_Item_Versi tetap utuh, dan pembacaan kembali
   sepenuhnya ke sana — persis keadaan sebelum tabel ini ada.
   --------------------------------------------------------------------- */
