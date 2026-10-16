/* Change ID: DDL-2026-092-pa-sel-penilai-belum-berbobot
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: 2 kolom dilonggarkan, 4 CHECK ditulis ulang, 1 indeks disusun
                            ulang, register the change <= 1.
   Dependencies: DDL-2026-064 (tabel aturan penilai sudah ada).

   "BERLAKU" DAN "BERBOBOT" DIPISAHKAN, SUPAYA URUTANNYA BISA DIUCAPKAN.

   Alur yang benar menurut pemilik proyek: matriks kategori penilai per level diisi LEBIH
   DULU — kategori mana yang menilai level mana — baru sesudahnya bobot dan kuotanya
   ditetapkan di Komposisi Penilai.

   Model hari ini tidak bisa mengucapkan urutan itu. Satu baris `N_PA_Rater_Policy_Detail`
   menyatakan keberlakuan, bobot, dan kuota SEKALIGUS, dan kolomnya tidak menyediakan
   keadaan antara:

     Bobot_Persen  NOT NULL, CHECK 0..100  -> 0 sah, dan 0 TIDAK BISA DIBEDAKAN dari
                                              "belum diisi". Sel yang sebenarnya menunggu
                                              angka akan terbaca sebagai kategori yang
                                              memang tidak berbobot, lalu ikut dihitung
                                              sebagai bagian dari 100% yang menyumbang nol.
     Min_Rater     NOT NULL, CHECK >= 1    -> mencentang satu sel memaksa menetapkan kuota
                                              pada saat yang sama.

   Sesudah perubahan ini keduanya boleh NULL, dan NULL berarti satu hal saja: BERLAKU,
   BELUM BERBOBOT. Bukan nol, bukan tak terhingga — belum diisi.

   YANG MENJAGA AGAR "BELUM" TIDAK LOLOS KE SIKLUS

   Tidak ada yang longgar dari perubahan ini. `PaCyclePreparationService` sudah memeriksa
   bobot SEBELUM menyalin ke siklus lewat `PaBobotGuard::pastikanSeratus()`, dan sel
   ber-NULL tidak akan pernah menjumlah 100 — jadi siklus tetap menolak berjalan. Yang
   ditambahkan di sisi kode hanyalah pesan yang menyebut sebabnya dengan benar: "tiga sel
   belum diberi bobot", bukan "totalnya 80%".

   NILAI LAMA TIDAK DISENTUH. Keempat puluh lima baris yang sudah ada memang sudah berbobot;
   melonggarkan kolom tidak mengubah satu pun di antaranya. Terukur 9 September 2026: nol
   baris ber-Bobot_Persen NULL sebelum perubahan ini.

   INDEKS IX_..._Category MENYERTAKAN Bobot_Persen, jadi ia ikut disusun ulang — bukan karena
   definisinya berubah, melainkan karena SQL Server menolak ALTER COLUMN atas kolom yang
   sedang disertakan sebuah indeks.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50422, 'DDL-2026-092 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

/* ---------- 1. Lepaskan yang menghalangi ALTER COLUMN ---------- */

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Bobot')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Bobot;

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Min_Rater')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Rater;

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Maks_Rater')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Maks_Rater;

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Min_Selesai')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Selesai;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail') AND name = 'IX_PA_Rater_Policy_Detail_Category'
)
    DROP INDEX IX_PA_Rater_Policy_Detail_Category ON N_PA_Rater_Policy_Detail;
GO

/* ---------- 2. Longgarkan kedua kolomnya ---------- */

ALTER TABLE N_PA_Rater_Policy_Detail ALTER COLUMN Bobot_Persen decimal(5,2) NULL;
ALTER TABLE N_PA_Rater_Policy_Detail ALTER COLUMN Min_Rater smallint NULL;
GO

/* ---------- 3. Tulis ulang penjaganya supaya menoleransi "belum diisi" ---------- */

/* Rentangnya tetap dijaga; yang ditambahkan hanya izin untuk BELUM ADA nilainya. Membuang
   penjaganya sama sekali akan membiarkan bobot 250% masuk tanpa suara. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Bobot')
    ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Bobot
    CHECK (Bobot_Persen IS NULL OR (Bobot_Persen >= 0 AND Bobot_Persen <= 100));

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Min_Rater')
    ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Rater
    CHECK (Min_Rater IS NULL OR Min_Rater >= 1);

/* Perbandingan dengan NULL memulangkan UNKNOWN, dan CHECK meloloskan UNKNOWN — tetapi
   ketiadaan Min_Rater ditulis EKSPLISIT di sini supaya pembacanya tidak perlu mengandalkan
   pengetahuan itu untuk yakin skripnya benar. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Maks_Rater')
    ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Maks_Rater
    CHECK (Maks_Rater IS NULL OR Min_Rater IS NULL OR Maks_Rater >= Min_Rater);

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Min_Selesai')
    ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Selesai
    CHECK (
        Min_Rater_Selesai IS NULL
        OR (
            Min_Rater_Selesai >= 1
            AND (Maks_Rater IS NULL OR Min_Rater_Selesai <= Maks_Rater)
        )
    );

/* Sel yang BERLAKU tetapi belum berbobot harus punya kuota yang juga belum ditetapkan, dan
   sebaliknya. Separuh terisi adalah keadaan yang tidak berarti apa-apa: bobot tanpa kuota
   tidak bisa membentuk penugasan, kuota tanpa bobot tidak bisa menyumbang skor. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Sepasang')
    ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Sepasang
    CHECK ((Bobot_Persen IS NULL AND Min_Rater IS NULL) OR (Bobot_Persen IS NOT NULL AND Min_Rater IS NOT NULL));
GO

/* ---------- 4. Susun ulang indeksnya ---------- */

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail') AND name = 'IX_PA_Rater_Policy_Detail_Category'
)
BEGIN
    CREATE INDEX IX_PA_Rater_Policy_Detail_Category
        ON N_PA_Rater_Policy_Detail (PA_Rater_Category_Id, Flag_Aktif)
        INCLUDE (PA_Rater_Policy_Id, Bobot_Persen);
END;
GO

/* ---------- 5. Pendaftaran perubahan ---------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-092-pa-sel-penilai-belum-berbobot'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-092-pa-sel-penilai-belum-berbobot',
         'DDL',
         'Sel aturan penilai boleh berlaku tanpa bobot',
         'Bobot_Persen dan Min_Rater dilonggarkan jadi NULL supaya "kategori ini menilai level ini" bisa dinyatakan LEBIH DULU, dan bobot serta kuotanya ditetapkan menyusul di Komposisi Penilai. NULL berarti BERLAKU TETAPI BELUM BERBOBOT - bukan nol.',
         'database/ddl/DDL-2026-092-pa-sel-penilai-belum-berbobot/forward.sql',
         'database/ddl/DDL-2026-092-pa-sel-penilai-belum-berbobot/forward.sql',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Rentang bobot 0..100 dan Min_Rater >= 1 TETAP dijaga; yang ditambahkan hanya izin untuk belum ada nilainya. CK_..._Sepasang baru menolak keadaan separuh terisi. Nilai lama tidak disentuh: nol baris ber-NULL sebelum perubahan ini. Siklus tetap menolak berjalan atas sel yang belum berbobot - pastikanSeratus() tidak akan pernah menjumlah 100 dari NULL.',
         'performance-appraisal');
END;
GO
