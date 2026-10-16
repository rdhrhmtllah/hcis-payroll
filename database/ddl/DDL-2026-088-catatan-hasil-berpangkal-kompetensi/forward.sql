/* Change ID: DDL-2026-088-catatan-hasil-berpangkal-kompetensi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: tidak ada.
   Scope: N_PA_Result_Note — SATU kolom baru, satu foreign key, satu indeks, dan satu
          pelonggaran NOT NULL.

   YANG DISELESAIKAN

   Dimensi kualitatif ada DUA KALI di basis data ini, dan keduanya menjawab pertanyaan yang
   sama — "kategori naratif apa saja yang dikenal sistem":

       N_PA_Competency (jenis KUALITATIF)   QL01 Strength, QL02 Development Area,
                                            QL03 Initiative, QL04 Recommendation
       N_PA_Result_Note_Type                STRENGTH, DEVELOPMENT_AREA

   Yang kedua disemai 1 September 2026, sebelum daftar pertama ada. Sejak itu keduanya harus
   dijaga sinkron dengan tangan, lewat sebuah berkas config berisi pemetaan yang ditulis
   manual — dan ketidaksinkronannya sudah berakibat: dua dari empat pertanyaan kualitatif
   dijawab enam penilai lalu tidak pernah sampai ke report peserta, karena tidak ada jenis
   catatan yang bersesuaian.

   Sesudah perubahan ini, catatan hasil berpangkal langsung pada KOMPETENSI. Menambah dimensi
   kualitatif berikutnya cukup satu baris di master kompetensi; panelnya ikut dengan
   sendirinya, tanpa master kedua dan tanpa config pemetaan.

   KENAPA KOMPETENSI, BUKAN BUTIR SOAL

   Butir soal milik paket per siklus, dan paket berbeda per level: pada data sekarang hanya
   level 8 dan 10 yang punya butir kualitatif. Kalau panel report diturunkan dari butir,
   peserta level 2 tidak akan punya panel sama sekali — admin tidak bisa menuliskan
   kesimpulan apa pun untuk orang yang tetap dinilai dan tetap menerima report. Kompetensi
   berada di master, ada bagi semua level, dan karena itu panelnya konsisten.

   EXPAND, BUKAN GANTI DI TEMPAT

   Kolom lama `PA_Result_Note_Type_Id` TIDAK dibuang di sini, hanya dilonggarkan menjadi
   NULL-able. Dua alasan. Pertama, 88 baris yang sudah ada masih menunjuk ke sana, dan
   membuangnya bersamaan dengan pemindahan data berarti tidak ada jalan mundur bila
   pemetaannya keliru. Kedua, agen tidak menjalankan DROP — pencabutan kolomnya adalah
   perubahan terpisah yang dijalankan manusia setelah data barunya terbukti benar di
   production.

   URUTAN YANG MENGIKAT

   Jalankan DDL ini LEBIH DULU, baru bagian DML-nya
   (`database/dml/DDL-2026-088-catatan-hasil-berpangkal-kompetensi/data-change.sql`) yang
   mengisi kolom barunya. Terbalik, DML itu gagal karena kolomnya belum ada.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50530, 'DDL-2026-088 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* TANPA TRANSAKSI EKSPLISIT, DAN ITU DISENGAJA.

   Tiap langkah di bawah butuh batasnya sendiri (`GO`): foreign key dan indeks menunjuk
   kolom yang baru dibuat langkah sebelumnya, dan SQL Server belum mengenalnya dalam batch
   yang sama. Sementara `GO` mengakhiri batch, dan transaksi yang dibuka di satu batch
   TIDAK bertahan ke batch berikutnya — ia digulung balik dengan pesan "A transaction that
   was started in a MARS batch is still active at the end of the batch".

   Membungkusnya tetap aman karena tiap langkah atomik sendiri dan dijaga IF NOT EXISTS:
   jalan yang terhenti di tengah tinggal dijalankan ulang, dan langkah yang sudah jadi
   dilewati. */

/* ------------------------------------------------------------------ *
 * 1. Kolom baru                                                       *
 * ------------------------------------------------------------------ *
 * NULL-able pada tahap ini: barisnya diisi DML-2026-106 sesudah kolomnya ada. Menuntutnya
 * NOT NULL sekarang membuat penambahan kolom itu sendiri gagal pada tabel yang sudah
 * berisi 88 baris.                                                                     */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Result_Note') AND name = 'PA_Competency_Id'
)
    ALTER TABLE N_PA_Result_Note ADD PA_Competency_Id INT NULL;
GO

/* ------------------------------------------------------------------ *
 * 2. Foreign key                                                      *
 * ------------------------------------------------------------------ *
 * Kompetensi yang sudah dipakai catatan tidak boleh lenyap: tanpa penjaga ini, catatan
 * akan menunjuk kategori yang tidak ada dan layar hasil menampilkannya tanpa judul.   */

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Note_Competency')
    ALTER TABLE N_PA_Result_Note
        ADD CONSTRAINT FK_PA_Result_Note_Competency
        FOREIGN KEY (PA_Competency_Id) REFERENCES N_PA_Competency (Id_PA_Competency);
GO

/* ------------------------------------------------------------------ *
 * 3. Indeks pembacaan                                                 *
 * ------------------------------------------------------------------ *
 * Layar hasil membaca catatan satu peserta lalu mengelompokkannya per kategori. Tanpa
 * indeks ini pengelompokan itu memindai seluruh tabel setiap kali satu hasil dibuka.  */

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Result_Note_Competency')
    CREATE INDEX IX_PA_Result_Note_Competency
        ON N_PA_Result_Note (PA_Result_Id, PA_Competency_Id);
GO

/* ------------------------------------------------------------------ *
 * 4. Kolom lama dilonggarkan                                          *
 * ------------------------------------------------------------------ *
 * Supaya baris BARU tidak wajib lagi menyebut jenis catatan lama. Kolomnya sendiri tetap
 * ada beserta foreign key-nya — pencabutannya perubahan tersendiri, dijalankan manusia
 * sesudah data barunya terbukti benar di production.                                   */

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Result_Note')
      AND name = 'PA_Result_Note_Type_Id'
      AND is_nullable = 0
)
    ALTER TABLE N_PA_Result_Note ALTER COLUMN PA_Result_Note_Type_Id INT NULL;
GO

/* ------------------------------------------------------------------ *
 * 5. Penjaga                                                          *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Result_Note') AND name = 'PA_Competency_Id'
)
    THROW 50531, 'Kolom PA_Competency_Id tidak terbentuk.', 1;

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Note_Competency')
    THROW 50532, 'Foreign key ke N_PA_Competency tidak terbentuk.', 1;

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Result_Note')
      AND name = 'PA_Result_Note_Type_Id'
      AND is_nullable = 0
)
    THROW 50533, 'Kolom lama masih NOT NULL; baris baru akan tetap dipaksa mengisinya.', 1;

/* VERIFIKASI */
SELECT c.name, t.name AS tipe, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('N_PA_Result_Note')
  AND c.name IN ('PA_Competency_Id', 'PA_Result_Note_Type_Id');
