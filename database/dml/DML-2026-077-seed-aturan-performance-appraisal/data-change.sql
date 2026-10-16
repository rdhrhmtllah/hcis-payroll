/* Change ID: DML-2026-077-seed-aturan-performance-appraisal
   Target: Web_HRIS development only.
   Purpose: Mengisi tiga tabel ATURAN Performance Appraisal yang selama ini
            kosong, sehingga satu siklus benar-benar bisa disiapkan. Persiapan
            siklus bekerja dengan MENYALIN cetak biru, dan menyalin dari yang
            kosong menghasilkan yang kosong.

   YANG DIISI
   ----------
     N_PA_Rater_Policy + N_PA_Rater_Policy_Detail   aturan penilai per level
     N_PA_Pillar_Weight                             bobot KPI vs kompetensi
     N_PA_Competency_Requirement                    jenis kompetensi per level

   YANG SENGAJA TIDAK DIISI
   ------------------------
     N_PA_Score_Range          Deck PIC TIDAK memuat ambang grade sama sekali.
                               Kode A/B+/B/C+ yang muncul di sana hanya data
                               contoh pada screenshot, bukan definisi pita, dan
                               tidak ada target distribusi paksa di mana pun.
                               Ini bagian dari T05, bukan sekadar belum disalin.

     N_PA_Competency_Kode_Soal Menunggu daftar kode soal dari HC Learn.

   ASAL ANGKANYA
   -------------
   Bobot penilai per level: deck PIC slide 4, tabel 12 baris, disalin apa adanya.
   Deck slide 18 memuat angka LAIN untuk hal yang sama (tiga kelompok kasar:
   Managerial/Specialist/Staff). Slide 4 yang dipakai, atas keputusan user,
   karena rancangannya memang per level dan nama levelnya cocok dengan master.
   Baris "Officer (Specialist)" pada slide 4 TIDAK dipakai: specialist bukan
   level dan tidak ada di HRIS_Level; kasusnya ditangani sebagai override per
   peserta, yang memang alasan tabel override dibuat.

   Kolom "Atasan 2" pada deck tertulis adjustable. Itu BUKAN kategori terpisah,
   melainkan Bobot_Dalam_Kategori di N_PA_Assignment (K18). Atasan-1 dan
   Atasan-2 sama-sama kategori ATASAN.

   YANG MASIH SEMENTARA, DAN HARUS DIGANTI LEWAT HALAMAN MASTER
   ------------------------------------------------------------
   1. BOBOT PILAR. Deck hanya memberi empat pita (Staff 80/20,
      SPV/Section Head 70/30, Manager 65/35, Senior Manager/GM 55/45) dan salah
      satu namanya, "SPV / Section Head", tidak ada di HRIS_Level. Nilai di
      bawah ini mengikuti arah gradasi deck (semakin tinggi level, porsi KPI
      semakin kecil) dan berjangkar pada level yang deck sebut. Level yang tidak
      disebut deck diisi wajar di antaranya. User sudah menyatakan pemetaan
      sebenarnya akan diatur lewat halaman tersendiri.

   2. MIN/MAKS PENILAI. Deck hanya memberi SATU contoh (slide 17: 1 self,
      1 atasan, 3 peer, 5 bawahan untuk level Managerial). Angka di bawah ini
      konservatif dan wajar, bukan salinan.

   3. BATAS "LEADERS" pada kebutuhan kompetensi. Deck slide 9 menyebut tiga
      kelompok — Pramukarya, Staff, Leaders — tanpa menyatakan Leaders mulai
      level berapa. Di sini Leaders diambil mulai ASSISTEN MANAGER.

   LEVEL YANG SENGAJA TIDAK DIBERI ATURAN
   --------------------------------------
     FREELANCE (98 orang)  Dikecualikan dari PA atas keputusan user. Penyaringan
                           terjadi saat penyiapan peserta, jadi tidak perlu
                           aturan sama sekali.
     NOT SET, BELUM        Bukan jenjang jabatan sungguhan, nol orang. Dibiarkan
                           tanpa aturan DENGAN SENGAJA: siapa pun yang mendarat
                           di sana akan masuk keranjang TERTUNDA dan terlihat,
                           alih-alih dinilai memakai aturan karangan.

   DIREKTUR dan DIREKTUR UTAMA tidak disebut deck dan nol orang, tetapi keduanya
   jenjang sungguhan, jadi diberi aturan meneruskan pola General Manager. Ini
   EKSTRAPOLASI, bukan data deck.

   Sifat: idempoten. Dijalankan ulang tidak menggandakan apa pun. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50270, 'DML-2026-077 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Rater_Policy', 'U') IS NULL
   OR OBJECT_ID('N_PA_Rater_Policy_Detail', 'U') IS NULL
   OR OBJECT_ID('N_PA_Pillar_Weight', 'U') IS NULL
   OR OBJECT_ID('N_PA_Competency_Requirement', 'U') IS NULL
    THROW 50271, 'DML-2026-077 membutuhkan DDL-2026-064, 065, dan 067.', 1;
GO

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Aturan penilai per level — deck slide 4                          *
 * ------------------------------------------------------------------ */

DECLARE @Policy TABLE (
    ID_Level bigint NOT NULL,
    Kode_Policy varchar(50) NOT NULL,
    Nama_Policy nvarchar(100) NOT NULL,
    Bobot_Atasan decimal(5,2) NOT NULL,
    Bobot_Self decimal(5,2) NOT NULL,
    Bobot_Peer decimal(5,2) NOT NULL,
    Bobot_Bawahan decimal(5,2) NOT NULL
);

INSERT INTO @Policy (ID_Level, Kode_Policy, Nama_Policy, Bobot_Atasan, Bobot_Self, Bobot_Peer, Bobot_Bawahan)
VALUES
    ( 1, 'PA-RTR-PRAMUKARYA', N'Aturan Penilai — Non Clerk / Pramukarya', 100,  0,  0,  0),
    ( 2, 'PA-RTR-STAFF',      N'Aturan Penilai — Staff',                   80, 20,  0,  0),
    ( 3, 'PA-RTR-SRSTAFF',    N'Aturan Penilai — Senior Staff',            80, 20,  0,  0),
    ( 4, 'PA-RTR-OFFICER',    N'Aturan Penilai — Officer',                 55, 10, 10, 25),
    ( 5, 'PA-RTR-SROFFICER',  N'Aturan Penilai — Senior Officer',          55, 10, 10, 25),
    ( 6, 'PA-RTR-ASMAN',      N'Aturan Penilai — Assisten Manager',        50, 10, 10, 30),
    ( 7, 'PA-RTR-SRASMAN',    N'Aturan Penilai — Senior Assisten Manager', 50, 10, 10, 30),
    ( 8, 'PA-RTR-MANAGER',    N'Aturan Penilai — Manager',                 45, 10, 15, 30),
    ( 9, 'PA-RTR-SRMANAGER',  N'Aturan Penilai — Senior Manager',          45, 10, 15, 30),
    (10, 'PA-RTR-GM',         N'Aturan Penilai — General Manager',         40, 10, 20, 30),
    (11, 'PA-RTR-SRGM',       N'Aturan Penilai — Senior General Manager',  40, 10, 20, 30),
    (12, 'PA-RTR-DIREKTUR',   N'Aturan Penilai — Direktur',                40, 10, 20, 30),
    (13, 'PA-RTR-DIRUT',      N'Aturan Penilai — Direktur Utama',          40, 10, 20, 30);

/* Penjaga: tiap baris wajib berjumlah 100 sebelum apa pun ditanam. */
IF EXISTS (
    SELECT 1 FROM @Policy
    WHERE Bobot_Atasan + Bobot_Self + Bobot_Peer + Bobot_Bawahan <> 100
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50272, 'DML-2026-077: ada baris aturan penilai yang tidak berjumlah 100.', 1;
END;

/* Penjaga: semua level yang dirujuk harus benar-benar ada di master. */
IF EXISTS (SELECT 1 FROM @Policy p WHERE NOT EXISTS (
    SELECT 1 FROM HRIS_Level l WHERE l.ID_Level = p.ID_Level))
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50273, 'DML-2026-077: ada ID_Level yang tidak ada di HRIS_Level.', 1;
END;

INSERT INTO N_PA_Rater_Policy (Kode_Policy, Nama_Policy, ID_Level, Versi, Status_Policy, Berlaku_Dari, Catatan_Versi, Activated_At)
SELECT p.Kode_Policy, p.Nama_Policy, p.ID_Level, 1, 'ACTIVE', '2026-01-01',
       N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.',
       SYSDATETIME()
FROM @Policy p
WHERE NOT EXISTS (
    SELECT 1 FROM N_PA_Rater_Policy r WHERE r.Kode_Policy = p.Kode_Policy
);

/* Detail per kategori. Bobot nol berarti kategori itu memang tidak dipakai di
   level tersebut, jadi barisnya tidak dibuat sama sekali — bukan dibuat dengan
   nol, yang akan membuat penilai wajib dicari untuk kategori yang tak dipakai. */

DECLARE @Kat TABLE (
    Kode_Kategori varchar(20) NOT NULL,
    Urutan smallint NOT NULL,
    Min_Rater smallint NOT NULL,
    Maks_Rater smallint NULL,
    Rel_Type_Id int NULL
);

INSERT INTO @Kat (Kode_Kategori, Urutan, Min_Rater, Maks_Rater, Rel_Type_Id)
VALUES
    /* SELF tidak punya jenis relasi: penilainya orang itu sendiri. */
    ('SELF',    1, 1,    1, NULL),
    ('ATASAN',  2, 1,    2, (SELECT Id_Organization_Relationship_Type FROM N_HRIS_Organization_Relationship_Type WHERE Kode_Relasi = 'ATASAN')),
    ('PEER',    3, 2,    5, (SELECT Id_Organization_Relationship_Type FROM N_HRIS_Organization_Relationship_Type WHERE Kode_Relasi = 'PEER')),
    /* Bawahan diberi plafon karena satu atasan bisa punya puluhan anak buah;
       tanpa plafon, seorang manager menghasilkan puluhan penugasan sekaligus. */
    ('BAWAHAN', 4, 2,    8, (SELECT Id_Organization_Relationship_Type FROM N_HRIS_Organization_Relationship_Type WHERE Kode_Relasi = 'BAWAHAN'));

INSERT INTO N_PA_Rater_Policy_Detail (
    PA_Rater_Policy_Id, PA_Rater_Category_Id, Organization_Relationship_Type_Id,
    Bobot_Persen, Min_Rater, Maks_Rater, Min_Rater_Selesai,
    Flag_Izinkan_Manual, Urutan_Tampil, Catatan_Aturan
)
SELECT
    r.Id_PA_Rater_Policy,
    c.Id_PA_Rater_Category,
    k.Rel_Type_Id,
    b.Bobot,
    k.Min_Rater,
    k.Maks_Rater,
    k.Min_Rater,
    /* Relasi organisasi masih kosong, jadi pemilihan manual satu-satunya jalan
       yang benar-benar bekerja sampai konverter K21 ada. */
    'Y',
    k.Urutan,
    N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.'
FROM @Policy p
JOIN N_PA_Rater_Policy r ON r.Kode_Policy = p.Kode_Policy
CROSS APPLY (VALUES
    ('SELF',    p.Bobot_Self),
    ('ATASAN',  p.Bobot_Atasan),
    ('PEER',    p.Bobot_Peer),
    ('BAWAHAN', p.Bobot_Bawahan)
) AS b(Kode, Bobot)
JOIN @Kat k ON k.Kode_Kategori = b.Kode
JOIN N_PA_Rater_Category c ON c.Kode_Kategori = b.Kode
WHERE b.Bobot > 0
  AND NOT EXISTS (
      SELECT 1 FROM N_PA_Rater_Policy_Detail d
      WHERE d.PA_Rater_Policy_Id = r.Id_PA_Rater_Policy
        AND d.PA_Rater_Category_Id = c.Id_PA_Rater_Category
  );

/* ------------------------------------------------------------------ *
 * 2. Bobot pilar per level — SEMENTARA, lihat catatan di atas          *
 * ------------------------------------------------------------------ */

DECLARE @Pilar TABLE (ID_Level bigint, Bobot_KPI decimal(5,2), Bobot_Kompetensi decimal(5,2));

INSERT INTO @Pilar (ID_Level, Bobot_KPI, Bobot_Kompetensi)
VALUES
    ( 1, 85, 15),   /* di bawah Staff, meneruskan gradasi                     */
    ( 2, 80, 20),   /* deck: Staff                                            */
    ( 3, 80, 20),
    ( 4, 75, 25),
    ( 5, 75, 25),
    ( 6, 70, 30),   /* deck: pita "SPV / Section Head"                        */
    ( 7, 70, 30),
    ( 8, 65, 35),   /* deck: Manager                                          */
    ( 9, 55, 45),   /* deck: pita "Senior Manager / GM"                       */
    (10, 55, 45),   /* deck: GM                                               */
    (11, 55, 45),
    (12, 50, 50),   /* ekstrapolasi                                           */
    (13, 50, 50);

IF EXISTS (SELECT 1 FROM @Pilar WHERE Bobot_KPI + Bobot_Kompetensi <> 100)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50274, 'DML-2026-077: ada bobot pilar yang tidak berjumlah 100.', 1;
END;

INSERT INTO N_PA_Pillar_Weight (ID_Level, Bobot_KPI, Bobot_Kompetensi, Catatan)
SELECT w.ID_Level, w.Bobot_KPI, w.Bobot_Kompetensi,
       N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.'
FROM @Pilar w
WHERE NOT EXISTS (
    SELECT 1 FROM N_PA_Pillar_Weight x WHERE x.ID_Level = w.ID_Level
);

/* ------------------------------------------------------------------ *
 * 3. Kebutuhan kompetensi per level — deck slide 9                     *
 * ------------------------------------------------------------------ */

/* Pramukarya  : Behavior saja
   Staff–Sr Officer : Manajerial + Core Value
   Assisten Manager ke atas (Leaders) : + Qualitative Question */

DECLARE @Syarat TABLE (ID_Level bigint, Kode_Type varchar(30), Flag_Wajib char(1));

INSERT INTO @Syarat (ID_Level, Kode_Type, Flag_Wajib)
SELECT l.ID_Level, t.Kode_Type, 'Y'
FROM (VALUES (1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12),(13)) AS l(ID_Level)
CROSS JOIN (VALUES ('MANAJERIAL'),('CORE_VALUE'),('BEHAVIOR'),('KUALITATIF')) AS t(Kode_Type)
WHERE (l.ID_Level = 1 AND t.Kode_Type = 'BEHAVIOR')
   OR (l.ID_Level BETWEEN 2 AND 13 AND t.Kode_Type IN ('MANAJERIAL', 'CORE_VALUE'))
   OR (l.ID_Level BETWEEN 6 AND 13 AND t.Kode_Type = 'KUALITATIF');

INSERT INTO N_PA_Competency_Requirement (ID_Level, PA_Competency_Type_Id, Flag_Wajib, Catatan)
SELECT s.ID_Level, t.Id_PA_Competency_Type, s.Flag_Wajib,
       N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.'
FROM @Syarat s
JOIN N_PA_Competency_Type t ON t.Kode_Type = s.Kode_Type
WHERE NOT EXISTS (
    SELECT 1 FROM N_PA_Competency_Requirement r
    WHERE r.ID_Level = s.ID_Level AND r.PA_Competency_Type_Id = t.Id_PA_Competency_Type
);

/* ------------------------------------------------------------------ *
 * 4. Penjaga hasil                                                     *
 * ------------------------------------------------------------------ */

IF (SELECT COUNT(*) FROM N_PA_Rater_Policy WHERE Flag_Aktif = 'Y') <> 13
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50275, 'DML-2026-077: jumlah aturan penilai aktif bukan 13.', 1;
END;

IF EXISTS (
    SELECT d.PA_Rater_Policy_Id
    FROM N_PA_Rater_Policy_Detail d
    WHERE d.Flag_Aktif = 'Y'
    GROUP BY d.PA_Rater_Policy_Id
    HAVING SUM(d.Bobot_Persen) <> 100
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50276, 'DML-2026-077: ada aturan penilai yang bobotnya tidak berjumlah 100.', 1;
END;

IF (SELECT COUNT(*) FROM N_PA_Pillar_Weight) <> 13
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50277, 'DML-2026-077: jumlah bobot pilar bukan 13.', 1;
END;

/* Pramukarya tidak boleh menuntut Kompetensi Manajerial, dan Leaders wajib
   membawa Qualitative Question. Dua hal itu yang paling mudah tergeser kalau
   batas kelompoknya kelak diubah. */
IF EXISTS (
    SELECT 1 FROM N_PA_Competency_Requirement r
    JOIN N_PA_Competency_Type t ON t.Id_PA_Competency_Type = r.PA_Competency_Type_Id
    WHERE r.ID_Level = 1 AND t.Kode_Type <> 'BEHAVIOR'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50278, 'DML-2026-077: Pramukarya menuntut jenis kompetensi di luar Behavior.', 1;
END;

IF (
    SELECT COUNT(*) FROM N_PA_Competency_Requirement r
    JOIN N_PA_Competency_Type t ON t.Id_PA_Competency_Type = r.PA_Competency_Type_Id
    WHERE t.Kode_Type = 'KUALITATIF'
) <> 8
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50279, 'DML-2026-077: jumlah level ber-Qualitative Question bukan 8.', 1;
END;

/* Tak boleh ada aturan untuk level yang sengaja dikecualikan. */
IF EXISTS (SELECT 1 FROM N_PA_Rater_Policy WHERE ID_Level IN (14, 15, 21))
   OR EXISTS (SELECT 1 FROM N_PA_Pillar_Weight WHERE ID_Level IN (14, 15, 21))
   OR EXISTS (SELECT 1 FROM N_PA_Competency_Requirement WHERE ID_Level IN (14, 15, 21))
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50280, 'DML-2026-077: level FREELANCE/NOT SET/BELUM tidak boleh punya aturan.', 1;
END;

/* ------------------------------------------------------------------ *
 * 5. Catat change-nya                                                  *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-077-seed-aturan-performance-appraisal'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-077-seed-aturan-performance-appraisal',
         'DML',
         'Isi aturan penilai, bobot pilar, dan kebutuhan kompetensi PA',
         'Mengisi N_PA_Rater_Policy dan detailnya dari deck PIC slide 4, N_PA_Pillar_Weight sebagai nilai sementara, serta N_PA_Competency_Requirement dari deck slide 9.',
         'database/dml/DML-2026-077-seed-aturan-performance-appraisal/data-change.sql',
         'docs/changes/DML-2026-077-seed-aturan-performance-appraisal/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Bobot penilai disalin apa adanya dari deck slide 4 (bukan slide 18 yang memberi angka berbeda). Bobot pilar SEMENTARA karena deck hanya memberi 4 pita dan salah satu namanya tidak ada di HRIS_Level. N_PA_Score_Range TIDAK diisi: ambang grade tidak ada di deck sama sekali. FREELANCE dikecualikan dari PA; NOT SET dan BELUM sengaja dibiarkan tanpa aturan supaya orangnya masuk keranjang TERTUNDA dan terlihat.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
