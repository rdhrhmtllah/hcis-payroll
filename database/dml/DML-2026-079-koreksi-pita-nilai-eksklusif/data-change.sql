/* Change ID: DML-2026-079-koreksi-pita-nilai-eksklusif
   Target: Web_HRIS development only.
   Purpose: Memperbaiki batas pita N_PA_Score_Range yang ditanam DML-2026-078.

   DUA CACAT YANG DIPERBAIKI
   -------------------------
   1. BATAS ATAS DIPERLAKUKAN INKLUSIF, PADAHAL DEKLARASINYA EKSKLUSIF.
      Aturan tabel ini adalah Nilai_Dari <= x < Nilai_Sampai. DML-078 menanam pita
      bergaya inklusif (80,00-89,99 lalu 90,00-100,00), sehingga terbentuk LUBANG
      di setiap perbatasan: nilai tepat 89,99 tidak masuk B+ (karena batas atasnya
      eksklusif) dan tidak masuk A (karena A mulai 90). Nilai yang jatuh di lubang
      itu menghasilkan grade kosong tanpa sebab yang terbaca.

   2. PITA TERATAS TERTUTUP DI 100, PADAHAL NILAI BISA MELEBIHINYA.
      Keputusan user 1 September 2026: pita tetap berpatokan 0-100, TETAPI nilai di
      atas 100 TIDAK dipotong - ia tetap masuk pita teratas. Memotongnya dianggap
      menyalahi, karena performa memang boleh melampaui target.

      Ini nyata, bukan teoretis: KPI di-cap pada 125 (config services.kpi), sehingga
      nilai akhir Staff dengan bobot 80/20 bisa mencapai 0,8x125 + 0,2x100 = 120.
      Dengan pita lama, orang itu jatuh di LUAR semua pita dan pulang tanpa grade -
      justru orang yang pencapaiannya paling tinggi.

   KENAPA PENJAGA DI DML-078 TIDAK MENANGKAPNYA
   --------------------------------------------
   Penjaga di sana hanya memeriksa MIN(Nilai_Dari)=0 dan MAX(Nilai_Sampai)=100, lalu
   memeriksa tumpang tindih. Karena pita-pita itu SALING BERJARAK (89,99 lalu 90),
   tidak ada yang tumpang tindih, dan batas ujungnya memang 0 dan 100. Penjaganya
   lulus justru KARENA cacatnya. Yang tidak pernah diperiksa adalah KEBERSAMBUNGAN:
   batas atas satu pita harus sama persis dengan batas bawah pita berikutnya.
   Penjaga di bawah memeriksa itu.

   BATAS ATAS PITA TERATAS
   -----------------------
   Dipakai 999,99 sebagai penanda "terbuka ke atas", bukan 125. Angka 125 berasal
   dari config services.kpi.max_achievement_score yang bisa diubah; menyalinnya ke
   data akan menciptakan sumber kebenaran kedua yang diam-diam berbeda saat config
   berubah. Termasuk pita kompetensi: batas 100 yang eksklusif akan membuang nilai
   kompetensi yang tepat 100.

   Sifat: idempoten. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50290, 'DML-2026-079 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Score_Range', 'U') IS NULL
    THROW 50291, 'DML-2026-079 membutuhkan N_PA_Score_Range.', 1;

IF (SELECT COUNT(*) FROM N_PA_Score_Range WHERE Flag_Aktif = 'Y') <> 12
    THROW 50292, 'DML-2026-079 mengharapkan 12 pita aktif hasil DML-2026-078.', 1;
GO

BEGIN TRANSACTION;

DECLARE @Betul TABLE (
    Score_Type varchar(20) NOT NULL,
    Score_Usage varchar(20) NOT NULL,
    Kode_Range varchar(40) NOT NULL,
    Nilai_Dari decimal(6,2) NOT NULL,
    Nilai_Sampai decimal(6,2) NOT NULL
);

/* Batas atas EKSKLUSIF: nilai x masuk pita bila Nilai_Dari <= x < Nilai_Sampai.
   Batas atas satu pita = batas bawah pita berikutnya, tanpa lubang. */
INSERT INTO @Betul (Score_Type, Score_Usage, Kode_Range, Nilai_Dari, Nilai_Sampai)
VALUES
    ('KPI',        'HAVMAP', 'RENDAH',   0.00,  70.00),
    ('KPI',        'HAVMAP', 'SEDANG',  70.00,  85.00),
    ('KPI',        'HAVMAP', 'TINGGI',  85.00, 999.99),

    ('KOMPETENSI', 'HAVMAP', 'RENDAH',   0.00,  70.00),
    ('KOMPETENSI', 'HAVMAP', 'SEDANG',  70.00,  85.00),
    ('KOMPETENSI', 'HAVMAP', 'TINGGI',  85.00, 999.99),

    ('FINAL',      'GRADE',  'D',        0.00,  50.00),
    ('FINAL',      'GRADE',  'C',       50.00,  60.00),
    ('FINAL',      'GRADE',  'C_PLUS',  60.00,  70.00),
    ('FINAL',      'GRADE',  'B',       70.00,  80.00),
    ('FINAL',      'GRADE',  'B_PLUS',  80.00,  90.00),
    ('FINAL',      'GRADE',  'A',       90.00, 999.99);

/* Penjaga 1: setiap pita yang mau ditulis harus benar-benar ada di tabel. */
IF EXISTS (
    SELECT 1 FROM @Betul b
    WHERE NOT EXISTS (
        SELECT 1 FROM N_PA_Score_Range r
        WHERE r.Score_Type = b.Score_Type AND r.Score_Usage = b.Score_Usage AND r.Kode_Range = b.Kode_Range
    )
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50293, 'DML-2026-079: ada pita sasaran yang tidak ditemukan di tabel.', 1;
END;

UPDATE r
SET r.Nilai_Dari = b.Nilai_Dari,
    r.Nilai_Sampai = b.Nilai_Sampai,
    r.Updated_At = SYSDATETIME()
FROM N_PA_Score_Range r
JOIN @Betul b
  ON b.Score_Type = r.Score_Type AND b.Score_Usage = r.Score_Usage AND b.Kode_Range = r.Kode_Range
WHERE r.Nilai_Dari <> b.Nilai_Dari OR r.Nilai_Sampai <> b.Nilai_Sampai;

/* ------------------------------------------------------------------ *
 * Penjaga hasil — yang seharusnya ada sejak DML-078                    *
 * ------------------------------------------------------------------ */

/* Penjaga 2: KEBERSAMBUNGAN. Inilah yang tidak pernah diperiksa sebelumnya.
   Setiap pita, kecuali yang paling bawah, batas bawahnya wajib sama persis dengan
   batas atas pita sebelumnya dalam kelompok yang sama. Lubang sekecil apa pun
   berarti ada nilai yang tidak punya pita. */
IF EXISTS (
    SELECT 1
    FROM N_PA_Score_Range r
    OUTER APPLY (
        SELECT TOP 1 p.Nilai_Sampai
        FROM N_PA_Score_Range p
        WHERE p.Score_Type = r.Score_Type AND p.Score_Usage = r.Score_Usage
          AND p.Flag_Aktif = 'Y' AND p.Nilai_Dari < r.Nilai_Dari
        ORDER BY p.Nilai_Dari DESC
    ) sebelum
    WHERE r.Flag_Aktif = 'Y'
      AND sebelum.Nilai_Sampai IS NOT NULL
      AND sebelum.Nilai_Sampai <> r.Nilai_Dari
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50294, 'DML-2026-079: ada lubang atau tumpang tindih antar pita.', 1;
END;

/* Penjaga 3: setiap kelompok wajib mulai dari 0. */
IF EXISTS (
    SELECT 1 FROM N_PA_Score_Range WHERE Flag_Aktif = 'Y'
    GROUP BY Score_Type, Score_Usage HAVING MIN(Nilai_Dari) <> 0.00
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50295, 'DML-2026-079: ada kelompok pita yang tidak mulai dari 0.', 1;
END;

/* Penjaga 4: setiap kelompok wajib TERBUKA KE ATAS. Nilai di atas 100 tidak boleh
   kehilangan pita — itu inti koreksi ini. */
IF EXISTS (
    SELECT 1 FROM N_PA_Score_Range WHERE Flag_Aktif = 'Y'
    GROUP BY Score_Type, Score_Usage HAVING MAX(Nilai_Sampai) < 999.99
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50296, 'DML-2026-079: ada kelompok pita yang masih tertutup di bawah 999,99.', 1;
END;

/* Penjaga 5: uji nyata, bukan sekadar bentuk. Enam nilai yang dulu jatuh di lubang
   atau di luar pita sekarang WAJIB menemukan tepat satu pita. */
DECLARE @Uji TABLE (Score_Type varchar(20), Score_Usage varchar(20), Nilai decimal(6,2), Harap varchar(40));
INSERT INTO @Uji VALUES
    ('FINAL',      'GRADE',   89.99, 'B_PLUS'),   /* dulu jatuh di lubang       */
    ('FINAL',      'GRADE',  100.00, 'A'),        /* dulu tepat di batas tertutup */
    ('FINAL',      'GRADE',  120.00, 'A'),        /* melampaui target           */
    ('FINAL',      'GRADE',   49.99, 'D'),
    ('KPI',        'HAVMAP', 125.00, 'TINGGI'),   /* plafon cap KPI             */
    ('KOMPETENSI', 'HAVMAP', 100.00, 'TINGGI');   /* kompetensi sempurna        */

IF EXISTS (
    SELECT 1 FROM @Uji u
    OUTER APPLY (
        SELECT COUNT(*) n, MIN(r.Kode_Range) kode
        FROM N_PA_Score_Range r
        WHERE r.Flag_Aktif = 'Y' AND r.Score_Type = u.Score_Type AND r.Score_Usage = u.Score_Usage
          AND u.Nilai >= r.Nilai_Dari AND u.Nilai < r.Nilai_Sampai
    ) h
    WHERE h.n <> 1 OR h.kode <> u.Harap
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50297, 'DML-2026-079: uji nilai nyata gagal menemukan pita yang benar.', 1;
END;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-079-koreksi-pita-nilai-eksklusif'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-079-koreksi-pita-nilai-eksklusif',
         'DML',
         'Koreksi batas pita nilai PA: eksklusif dan terbuka ke atas',
         'Memperbaiki batas pita N_PA_Score_Range hasil DML-2026-078 yang diperlakukan inklusif sehingga berlubang di tiap perbatasan, dan menutup pita teratas di 100 sehingga nilai di atas 100 kehilangan grade.',
         'database/dml/DML-2026-079-koreksi-pita-nilai-eksklusif/data-change.sql',
         'docs/changes/DML-2026-079-koreksi-pita-nilai-eksklusif/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Memperbaiki DML-2026-078. Dua cacat: (1) batas atas dideklarasikan EKSKLUSIF tapi ditanam bergaya inklusif (80-89,99 lalu 90-100), menimbulkan lubang di tiap perbatasan sehingga nilai tepat 89,99 tidak punya grade; (2) pita teratas tertutup di 100 padahal nilai akhir bisa mencapai 120 karena KPI di-cap 125, sehingga orang berpencapaian tertinggi justru pulang tanpa grade. Keputusan user: pita tetap berpatokan 0-100 tetapi nilai di atas 100 TIDAK dipotong, tetap masuk pita teratas. Batas atas teratas memakai 999,99 sebagai penanda terbuka, BUKAN 125, supaya tidak menduplikasi config services.kpi.max_achievement_score. Penjaga DML-078 lulus JUSTRU KARENA cacatnya: ia memeriksa min=0/max=100 dan tumpang tindih, tidak pernah memeriksa KEBERSAMBUNGAN antar pita.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
