/* Change ID: DDL-2026-091-pa-level-dikecualikan
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: 1 tabel dibuat, 2 indeks, 1 baris seed, register the change <= 1.
   Dependencies: tidak ada.

   LEVEL YANG TIDAK PERNAH DINILAI, SEBAGAI DATA — BUKAN SEBAGAI KONSTANTA.

   Sampai hari ini pengecualian level ditulis di kode: `private const LEVEL_FREELANCE = 21`
   DISALIN di tiga service dan dibaca di lima titik yang tugasnya berbeda-beda:

     PaCompetencyRequirementService :117  menyalakan tanda "Non-PA" dan mematikan barisnya
                                          di layar Matriks Kompetensi
                                   :131  membebaskannya dari alarm "level berisi orang
                                          tetapi tanpa satu pun dimensi"
     PaCyclePreparationService     :1443 menandai tiap karyawannya DIKECUALIKAN berikut
                                          alasannya saat siklus disiapkan
                                   :1563 membuang levelnya dari levelYangDipakai()
     PaCycleSetupStepService       :319  cakupan level pada langkah penyiapan

   Mengecualikan level KEDUA berarti menyunting kelimanya. Satu saja terlewat dan hasilnya
   belah tanpa suara: lupa :131 membuat layar berteriak selamanya tentang level yang justru
   sengaja dikecualikan; lupa :1443 membuat orangnya tetap menjadi peserta padahal setiap
   layar lain berlagak levelnya tidak ada. Tidak ada satu pun uji yang bisa menangkap belahan
   itu, karena kelimanya memang benar sendiri-sendiri.

   HAMPIR MENGGIGIT, DAN INI BUKAN ANDAI-ANDAI. Terukur 9 September 2026: level 14 (NOT SET)
   dan 15 (BELUM) sama sekali tidak punya syarat kompetensi, aturan penilai, maupun bobot
   pilar — persis seperti FREELANCE — tetapi TIDAK ikut dikecualikan di kode. Yang menahannya
   hanya kebetulan: nol karyawan di sana. Begitu satu orang mendarat di salah satunya, ia
   menjadi peserta yang tidak mungkin dinilai oleh siapa pun.

   ALASANNYA WAJIB, sama seperti N_PA_Cycle_Pre_Exclusion. Pengecualian tanpa alasan
   menghasilkan level yang hilang dari penilaian tanpa satu orang pun bisa menjawab kenapa —
   dan setahun kemudian tidak ada yang berani mencabutnya karena tidak tahu apa yang
   dilindunginya.

   DICABUT DENGAN MENONAKTIFKAN, BUKAN MENGHAPUS. Baris yang dihapus membuat "kenapa level
   itu tidak dinilai pada siklus 2026-H1 padahal sekarang dinilai" mustahil dijawab.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50421, 'DDL-2026-091 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_PA_Level_Exclusion', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Level_Exclusion (
        Id_PA_Level_Exclusion int IDENTITY(1,1) NOT NULL,

        /* Menunjuk HRIS_Level.ID_Level. TANPA foreign key, dan itu disengaja: HRIS_Level
           adalah tabel bersama milik HRIS yang dipakai lintas modul, dan memasang FK dari
           tabel PA ke sana membuat setiap perubahan di tabel itu harus memperhitungkan PA.
           Keutuhannya dijaga di layanan, dan level yang tidak dikenal hanya berarti barisnya
           tidak pernah cocok dengan siapa pun. */
        ID_Level int NOT NULL,

        /* Wajib. Lihat catatan di kepala berkas. */
        Alasan nvarchar(500) NOT NULL,

        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_LevelExcl_Flag DEFAULT ('Y'),

        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_LevelExcl_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,

        CONSTRAINT PK_PA_Level_Exclusion PRIMARY KEY CLUSTERED (Id_PA_Level_Exclusion),
        CONSTRAINT CK_PA_LevelExcl_Flag CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;
GO

/* Satu level tidak boleh punya dua baris AKTIF. Indeks tersaring, bukan unique constraint
   biasa: baris yang sudah dicabut (`Flag_Aktif = 'T'`) memang boleh berulang — level bisa
   dikecualikan, dicabut, lalu dikecualikan lagi dengan alasan berbeda, dan riwayat keduanya
   harus tetap ada. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Level_Exclusion') AND name = 'UX_PA_LevelExcl_Level'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_LevelExcl_Level
        ON N_PA_Level_Exclusion (ID_Level)
        WHERE Flag_Aktif = 'Y';
END;
GO

/* Jalur baca satu-satunya: "level mana saja yang dikecualikan". Dipanggil di lima titik,
   beberapa di antaranya di dalam penyiapan siklus yang berjalan atas ribuan karyawan, jadi
   daftarnya dibaca sekali lalu di-memo di layanan. Indeks ini membuat pembacaan itu tidak
   pernah menyentuh tabelnya. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Level_Exclusion') AND name = 'IX_PA_LevelExcl_Aktif'
)
BEGIN
    CREATE INDEX IX_PA_LevelExcl_Aktif
        ON N_PA_Level_Exclusion (Flag_Aktif)
        INCLUDE (ID_Level, Alasan);
END;
GO

/* ---------- Seed: memindahkan konstanta yang sudah ada, bukan menambah kebijakan baru ----------

   Level 21 (FREELANCE) sudah dikecualikan hari ini oleh kode. Barisnya dibuat di sini supaya
   perilaku SESUDAH perubahan ini persis sama dengan SEBELUMNYA. Tanpa seed ini, melepas
   konstanta dari kode berarti FREELANCE mendadak ikut dinilai — 98 orang yang selama ini
   memang tidak pernah masuk.

   Level 14 (NOT SET) dan 15 (BELUM) SENGAJA TIDAK ikut di-seed. Keduanya memang tidak
   berkonfigurasi, tetapi mengecualikannya adalah keputusan baru — dan keputusan baru tidak
   boleh menumpang pada perubahan yang tugasnya memindahkan yang lama. Keduanya nol karyawan,
   jadi tidak ada yang mendesak; admin bisa menambahkannya lewat layar sesudah ini. */
IF NOT EXISTS (SELECT 1 FROM N_PA_Level_Exclusion WHERE ID_Level = 21 AND Flag_Aktif = 'Y')
BEGIN
    INSERT INTO N_PA_Level_Exclusion (ID_Level, Alasan)
    VALUES (21, N'Level FREELANCE dikecualikan dari Performance Appraisal.');
END;
GO

/* ---------- Pendaftaran perubahan ---------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-091-pa-level-dikecualikan'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-091-pa-level-dikecualikan',
         'DDL',
         'Master level yang dikecualikan dari Performance Appraisal',
         'Memindahkan pengecualian level dari konstanta LEVEL_FREELANCE yang disalin di tiga service dan dibaca di lima titik, menjadi satu tabel data beralasan wajib. Di-seed dengan level 21 supaya perilakunya tidak berubah.',
         'database/ddl/DDL-2026-091-pa-level-dikecualikan/forward.sql',
         'database/ddl/DDL-2026-091-pa-level-dikecualikan/forward.sql',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Seed hanya level 21 (FREELANCE) - memindahkan yang sudah ada, bukan menambah kebijakan. Level 14 NOT SET dan 15 BELUM sengaja tidak ikut walau sama-sama tak berkonfigurasi; keduanya nol karyawan dan itu keputusan admin, bukan bagian dari pemindahan ini.',
         'performance-appraisal');
END;
GO
