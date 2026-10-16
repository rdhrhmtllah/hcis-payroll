/* Change ID: DDL-2026-085-performance-appraisal-cycle-setup-step
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-066 (N_PA_Cycle). Jalankan sesudahnya.
   Scope: satu tabel dan satu kolom — keadaan penyiapan siklus, per langkah.

   MASALAH YANG DISELESAIKAN

   Menyiapkan siklus sebenarnya empat pekerjaan: menentukan bobot pilar, memilih paket
   soal, menyusun aturan penilai, lalu memutuskan siapa ikut. Sampai sekarang ketiganya
   yang pertama tinggal di halaman MASTER GLOBAL yang sama untuk semua siklus, dan yang
   keempat satu tombol tanpa persiapan.

   Akibatnya admin tidak pernah bisa menjawab "apa setelan siklus A, dan apa setelan
   siklus B" — tidak ada yang menyimpan jawabannya per siklus, dan tidak ada yang
   menyimpan sudah sampai mana ia menyiapkannya.

   KENAPA POSISINYA DISIMPAN, BUKAN DISIMPULKAN DARI ISI

   `Langkah_Setup_Aktif` menyimpan posisi admin sebagai FAKTA, bukan kesimpulan dari
   kelengkapan isi. Layar yang menebak langkah aktif dari "mana yang sudah terisi" akan
   melemparkan admin kembali ke langkah paling belakang yang kebetulan kosong setiap kali
   ia menyimpan — termasuk ketika ia sengaja melewati satu langkah untuk kembali nanti.
   Cacat itu sudah pernah terjadi di modul lain dan mahal ditemukan.

   Isi tabel langkah menjelaskan APA yang sudah dikerjakan. Kolom di siklus menjelaskan
   DI MANA admin berada. Keduanya perlu, dan tidak saling menggantikan.

   KENAPA ADA 'DRAFT' TERPISAH DARI 'SELESAI'

   Admin harus bisa menyimpan setengah jalan dan pulang. Tanpa keadaan ketiga, satu-satunya
   pilihan adalah "belum" atau "sudah", dan pekerjaan setengah jadi terpaksa dilaporkan
   sebagai salah satunya — yang pertama menghapus jejak kerjanya, yang kedua berbohong
   kepada penjaga yang akan memeriksa kesiapan sebelum penyiapan berjalan.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50430, 'DDL-2026-085 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
    THROW 50431, 'DDL-2026-085 membutuhkan tabel N_PA_Cycle.', 1;
GO

/* ------------------------------------------------------------------ *
 * 1. Posisi admin di dalam alur penyiapan                             *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Cycle') AND name = 'Langkah_Setup_Aktif'
)
BEGIN
    /* NULL berarti admin belum pernah membuka penyiapannya sama sekali — dibedakan dari
       "sedang di langkah pertama", karena yang pertama boleh dilompati oleh siklus salinan
       yang seluruh setelannya sudah terbawa. */
    ALTER TABLE N_PA_Cycle ADD Langkah_Setup_Aktif varchar(20) NULL;
END;
GO

/* ------------------------------------------------------------------ *
 * 2. Keadaan tiap langkah                                             *
 * ------------------------------------------------------------------ */

IF OBJECT_ID('N_PA_Cycle_Setup_Step', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Setup_Step (
        Id_PA_Cycle_Setup_Step int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,

        /* BOBOT_PILAR | PAKET_SOAL | ATURAN_PENILAI | PESERTA
           Sengaja TANPA CHECK constraint. Daftar langkah masih akan bertambah, dan
           menambah langkah lewat perubahan skema membuat setiap penambahan butuh DDL
           produksi — biaya yang tidak sebanding untuk daftar yang dijaga di kode. */
        Kode_Langkah varchar(20) NOT NULL,

        /* BELUM | DRAFT | SELESAI — lihat catatan di kepala berkas tentang kenapa DRAFT
           harus berdiri sendiri. */
        Status_Langkah varchar(10) NOT NULL CONSTRAINT DF_PA_SetupStep_Status DEFAULT ('BELUM'),

        /* Ringkasan yang ditampilkan di kartu langkah tanpa perlu membuka langkahnya:
           "6 level terisi", "3 paket soal", "aturan disalin dari master v4". Diisi
           layanan yang menyimpan langkahnya, karena hanya ia yang tahu apa yang berubah. */
        Ringkasan nvarchar(400) NULL,

        Disimpan_At datetime2(0) NULL,
        Disimpan_By int NULL,

        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_SetupStep_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,

        CONSTRAINT PK_PA_Cycle_Setup_Step PRIMARY KEY CLUSTERED (Id_PA_Cycle_Setup_Step),
        CONSTRAINT FK_PA_SetupStep_Cycle FOREIGN KEY (PA_Cycle_Id)
            REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT CK_PA_SetupStep_Status CHECK (Status_Langkah IN ('BELUM', 'DRAFT', 'SELESAI'))
    );
END;
GO

/* Satu langkah hanya boleh punya SATU baris per siklus. Unique biasa, bukan tersaring:
   tidak ada Flag_Aktif di sini karena langkah tidak dicabut — ia berpindah keadaan, dan
   riwayat perpindahannya bukan urusan tabel ini (audit siklus yang memikulnya). */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Setup_Step')
      AND name = 'UX_PA_SetupStep_Cycle_Langkah'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_SetupStep_Cycle_Langkah
        ON N_PA_Cycle_Setup_Step (PA_Cycle_Id, Kode_Langkah);
END;
GO
