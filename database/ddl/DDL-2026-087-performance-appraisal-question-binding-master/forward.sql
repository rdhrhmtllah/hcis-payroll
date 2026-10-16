/* Change ID: DDL-2026-087-performance-appraisal-question-binding-master
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-086 (paket per kategori penilai), DDL-2026-075 (N_PA_Rater_Category).
   Scope: satu tabel — pilihan paket soal baku per (level x kategori penilai).

   MASALAH YANG DISELESAIKAN

   Pengikatan paket soal hanya ada per siklus. Setiap siklus baru, admin mengisi ulang
   seluruh matriks dari nol — pada data sekarang 20 sel, dan akan bertambah setiap kali ada
   level atau kategori penilai baru. Pekerjaan yang sama, diulang, dengan peluang salah yang
   sama besar tiap kali.

   Tabel ini menyimpan pilihan bakunya. Siklus baru menyalinnya, lalu admin hanya
   menyesuaikan yang berbeda.

   YANG DISIMPAN ADALAH PILIHAN, BUKAN ISI PAKETNYA

   Master menyimpan KODE paket HC Learn, bukan salinan butirnya. Butir yang benar-benar
   dipakai selalu diambil dari HC Learn saat penyalinan, karena penilaian nanti berjalan
   memakai soal yang ADA DI SANA — bukan yang pernah kita catat.

   TETAPI HC LEARN BISA BERUBAH TANPA MEMBERI TAHU

   Paket yang tahun lalu memuat sebelas kompetensi bisa tinggal sembilan, skalanya bisa
   bergeser dari 1-5 ke 1-4, atau paketnya dihapus sama sekali. Semua itu terjadi diam-diam
   di sisi mereka, dan siklus yang menyalin master begitu saja akan berjalan dengan susunan
   yang tidak pernah disetujui siapa pun.

   Karena itu master menyimpan SIDIK SUSUNAN saat ditetapkan: satu nilai ringkas atas
   seluruh pasangan (kode soal, kompetensi, jenis, skor maksimal, bobot). Saat diverifikasi
   ulang, sidik yang berbeda berarti HC Learn mengubah sesuatu — dan admin diberi tahu
   SEBELUM menyalinnya ke siklus, bukan setelah penilaian berjalan.

   Sidiknya sengaja atas SUSUNAN, bukan atas teks pertanyaan. Teks soal tidak pernah masuk
   ke HCIS, dan tidak perlu: yang mengubah arti nilai adalah komposisi dan skalanya.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50470, 'DDL-2026-087 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL
    THROW 50471, 'DDL-2026-087 membutuhkan tabel N_PA_Rater_Category.', 1;
GO

IF OBJECT_ID('N_PA_Question_Binding_Master', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Question_Binding_Master (
        Id_PA_Question_Binding_Master int IDENTITY(1,1) NOT NULL,

        ID_Level bigint NOT NULL,

        /* NULL = baku untuk SEMUA kategori penilai pada level itu. Sama artinya dengan
           kolom senama di N_PA_Cycle_Question_Package, dan urutan pencariannya juga sama:
           cocokkan kategori tepat lebih dulu, baru jatuh ke NULL. */
        PA_Rater_Category_Id int NULL,

        /* Kode paket HC Learn. Yang disimpan pilihannya, bukan isinya. */
        Kode_Paket_Sumber varchar(100) NOT NULL,
        Nama_Paket_Sumber nvarchar(255) NULL,
        Sumber_Kontrak varchar(30) NOT NULL CONSTRAINT DF_PA_QBindMaster_Sumber DEFAULT ('HCLEARN'),

        /* Bentuk paket SAAT DITETAPKAN. Dibandingkan ulang saat verifikasi; selisihnya
           berarti HC Learn mengubah paketnya sejak terakhir kali kita melihatnya. */
        Jumlah_Kompetensi int NULL,
        Jumlah_Soal int NULL,
        Sidik_Susunan char(64) NULL,

        Diverifikasi_At datetime2(0) NULL,
        Diverifikasi_By int NULL,

        Catatan nvarchar(400) NULL,

        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_QBindMaster_Flag DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_QBindMaster_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,

        CONSTRAINT PK_PA_Question_Binding_Master PRIMARY KEY CLUSTERED (Id_PA_Question_Binding_Master),
        CONSTRAINT FK_PA_QBindMaster_RaterCategory FOREIGN KEY (PA_Rater_Category_Id)
            REFERENCES N_PA_Rater_Category (Id_PA_Rater_Category),
        CONSTRAINT CK_PA_QBindMaster_Flag CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;
GO

/* Satu sel hanya boleh punya satu pilihan AKTIF. Tersaring pada `Flag_Aktif = 'Y'` supaya
   pilihan lama tetap tersimpan — "kenapa dulu levelnya memakai paket itu" harus bisa
   dijawab setelah pilihannya diganti. SQL Server memperlakukan NULL sebagai nilai yang
   sama di indeks unik, sehingga baris baku juga dijamin tunggal per level. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Question_Binding_Master')
      AND name = 'UX_PA_QBindMaster_Level_Rater'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_QBindMaster_Level_Rater
        ON N_PA_Question_Binding_Master (ID_Level, PA_Rater_Category_Id)
        WHERE Flag_Aktif = 'Y';
END;
GO

/* Jalur baca utamanya: seluruh matriks sekaligus. Dipanggil sekali saat halaman master
   dibuka dan sekali saat siklus baru menyalinnya. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Question_Binding_Master')
      AND name = 'IX_PA_QBindMaster_Aktif'
)
BEGIN
    CREATE INDEX IX_PA_QBindMaster_Aktif
        ON N_PA_Question_Binding_Master (Flag_Aktif)
        INCLUDE (ID_Level, PA_Rater_Category_Id, Kode_Paket_Sumber, Sidik_Susunan);
END;
GO
