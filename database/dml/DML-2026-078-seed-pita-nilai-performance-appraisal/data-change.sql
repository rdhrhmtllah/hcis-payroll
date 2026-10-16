/* Change ID: DML-2026-078-seed-pita-nilai-performance-appraisal
   Target: Web_HRIS development only.
   Purpose: Mengisi N_PA_Score_Range dengan pita HAV Map dan pita grade,
            supaya perhitungan punya ambang untuk dipakai.

   NILAI DI SINI SEMENTARA — DAN INI PERLU DIBACA
   ----------------------------------------------
   Deck PIC TIDAK memuat satu pun ambang grade. Kode A/B+/B/C+ yang terlihat di
   sana hanya data contoh pada screenshot, bukan definisi pita, dan tidak ada
   target distribusi paksa di mana pun. Jadi angka di bawah ini WAJAR, bukan
   RESMI, dan dipasang atas permintaan user supaya jalur perhitungan bisa
   dirakit dan diuji sebelum HC menjawab.

   SKALANYA 0-100. Deck memakai DUA skala untuk hal yang sama: 1-5 pada slide 20
   dan 13 (4.6 / 4.05), dan persen pada slide 13 lain dan slide 23 (78% / 83%).
   Yang dipilih di sini persen, karena HAV Map dan grade di mockup dibaca sebagai
   persen. Kalau T05 dijawab dengan skala 1-5, yang berubah cukup ANGKA di tabel
   ini — bukan skemanya, dan bukan kode perhitungannya.

   YANG SENGAJA TIDAK DIISI: kegunaan KONVERSI.
   Normalisasi skor tidak memerlukan tabel lookup: tiap butir sudah membawa
   Skor_Maksimal sendiri di N_PA_Assignment_Score, jadi konversinya aritmetika.
   Menanam tabel konversi sekarang justru menciptakan sumber kebenaran kedua yang
   bisa bertentangan dengan Skor_Maksimal yang datang dari HC Learn.

   Sifat: idempoten. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50281, 'DML-2026-078 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Score_Range', 'U') IS NULL
   OR OBJECT_ID('N_PA_Hav_Quadrant', 'U') IS NULL
    THROW 50282, 'DML-2026-078 membutuhkan DDL-2026-067 dan 068.', 1;
GO

BEGIN TRANSACTION;

DECLARE @Pita TABLE (
    Score_Type varchar(20) NOT NULL,
    Score_Usage varchar(20) NOT NULL,
    Kode_Range varchar(40) NOT NULL,
    Nama_Range nvarchar(100) NOT NULL,
    Nilai_Dari decimal(6,2) NOT NULL,
    Nilai_Sampai decimal(6,2) NOT NULL,
    Target_Persen decimal(5,2) NULL,
    Warna varchar(20) NULL,
    Urutan_Tampil smallint NOT NULL
);

/* Tiga tingkat sumbu HAV Map. Kodenya WAJIB sama persis dengan yang sudah
   dipakai sembilan kuadran di N_PA_Hav_Quadrant, karena di situlah keduanya
   bertemu. Penjaga di bawah menuntutnya. */
INSERT INTO @Pita (Score_Type, Score_Usage, Kode_Range, Nama_Range, Nilai_Dari, Nilai_Sampai, Target_Persen, Warna, Urutan_Tampil)
VALUES
    ('KPI',        'HAVMAP', 'RENDAH', N'Performa Rendah',    0.00, 69.99, NULL, '#EF4444', 1),
    ('KPI',        'HAVMAP', 'SEDANG', N'Performa Sedang',   70.00, 84.99, NULL, '#F59E0B', 2),
    ('KPI',        'HAVMAP', 'TINGGI', N'Performa Tinggi',   85.00,100.00, NULL, '#22C55E', 3),

    ('KOMPETENSI', 'HAVMAP', 'RENDAH', N'Kompetensi Rendah',  0.00, 69.99, NULL, '#EF4444', 1),
    ('KOMPETENSI', 'HAVMAP', 'SEDANG', N'Kompetensi Sedang', 70.00, 84.99, NULL, '#F59E0B', 2),
    ('KOMPETENSI', 'HAVMAP', 'TINGGI', N'Kompetensi Tinggi', 85.00,100.00, NULL, '#22C55E', 3),

    /* Empat kode teratas terlihat di screenshot deck; C dan D ditambahkan
       supaya pitanya menutup sampai nol — tanpa itu, nilai di bawah 60 tidak
       punya grade sama sekali dan hasilnya kosong tanpa sebab yang terbaca. */
    ('FINAL',      'GRADE',  'A',      N'Istimewa',          90.00,100.00, 10.00, '#16A34A', 1),
    ('FINAL',      'GRADE',  'B_PLUS', N'Sangat Baik',       80.00, 89.99, 20.00, '#4ADE80', 2),
    ('FINAL',      'GRADE',  'B',      N'Baik',              70.00, 79.99, 40.00, '#A3E635', 3),
    ('FINAL',      'GRADE',  'C_PLUS', N'Cukup',             60.00, 69.99, 20.00, '#FACC15', 4),
    ('FINAL',      'GRADE',  'C',      N'Kurang',            50.00, 59.99,  8.00, '#FB923C', 5),
    ('FINAL',      'GRADE',  'D',      N'Sangat Kurang',      0.00, 49.99,  2.00, '#EF4444', 6);

/* Penjaga 1: tiap kelompok (sumbu, kegunaan) harus menutup 0 sampai 100 penuh,
   tanpa lubang dan tanpa tumpang tindih. Lubang berarti ada nilai yang tidak
   punya pita, dan itu muncul sebagai hasil kosong tanpa penjelasan. */
IF EXISTS (
    SELECT 1
    FROM @Pita p
    OUTER APPLY (
        SELECT MIN(x.Nilai_Dari) Bawah, MAX(x.Nilai_Sampai) Atas
        FROM @Pita x WHERE x.Score_Type = p.Score_Type AND x.Score_Usage = p.Score_Usage
    ) g
    WHERE g.Bawah <> 0.00 OR g.Atas <> 100.00
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50283, 'DML-2026-078: ada kelompok pita yang tidak menutup 0 sampai 100.', 1;
END;

IF EXISTS (
    SELECT 1
    FROM @Pita a
    JOIN @Pita b
      ON b.Score_Type = a.Score_Type AND b.Score_Usage = a.Score_Usage
     AND b.Kode_Range <> a.Kode_Range
     AND b.Nilai_Dari <= a.Nilai_Sampai
     AND b.Nilai_Sampai >= a.Nilai_Dari
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50284, 'DML-2026-078: ada pita yang saling tumpang tindih.', 1;
END;

/* Penjaga 2: target distribusi grade harus berjumlah 100. */
IF (SELECT SUM(Target_Persen) FROM @Pita WHERE Score_Usage = 'GRADE') <> 100.00
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50285, 'DML-2026-078: target distribusi grade tidak berjumlah 100.', 1;
END;

/* Penjaga 3 — yang paling penting. Setiap tingkat yang dipakai sembilan kuadran
   HAV Map wajib punya pita pada KEDUA sumbunya. Kalau tidak, ada kuadran yang
   tidak akan pernah bisa dicapai oleh nilai apa pun, dan itu tidak akan terlihat
   sebagai error melainkan sebagai kotak yang selamanya kosong. */
IF EXISTS (
    SELECT q.Tingkat_Performa
    FROM N_PA_Hav_Quadrant q
    WHERE q.Flag_Aktif = 'Y'
      AND NOT EXISTS (
          SELECT 1 FROM @Pita p
          WHERE p.Score_Type = 'KPI' AND p.Score_Usage = 'HAVMAP'
            AND p.Kode_Range = q.Tingkat_Performa
      )
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50286, 'DML-2026-078: ada tingkat performa kuadran yang tidak punya pita KPI.', 1;
END;

IF EXISTS (
    SELECT q.Tingkat_Kompetensi
    FROM N_PA_Hav_Quadrant q
    WHERE q.Flag_Aktif = 'Y'
      AND NOT EXISTS (
          SELECT 1 FROM @Pita p
          WHERE p.Score_Type = 'KOMPETENSI' AND p.Score_Usage = 'HAVMAP'
            AND p.Kode_Range = q.Tingkat_Kompetensi
      )
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50287, 'DML-2026-078: ada tingkat kompetensi kuadran yang tidak punya pita.', 1;
END;

INSERT INTO N_PA_Score_Range (
    Score_Type, Score_Usage, Kode_Range, Nama_Range,
    Nilai_Dari, Nilai_Sampai, Nilai_Hasil, Target_Persen, Warna, Urutan_Tampil
)
SELECT p.Score_Type, p.Score_Usage, p.Kode_Range, p.Nama_Range,
       p.Nilai_Dari, p.Nilai_Sampai, NULL, p.Target_Persen, p.Warna, p.Urutan_Tampil
FROM @Pita p
WHERE NOT EXISTS (
    SELECT 1 FROM N_PA_Score_Range r
    WHERE r.Score_Type = p.Score_Type AND r.Score_Usage = p.Score_Usage AND r.Kode_Range = p.Kode_Range
);

/* Penjaga hasil, dibaca dari tabel sungguhan bukan dari variabel tabel. */
IF (SELECT COUNT(*) FROM N_PA_Score_Range WHERE Flag_Aktif = 'Y') <> 12
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50288, 'DML-2026-078: jumlah pita aktif bukan 12.', 1;
END;

/* Batas ATAS sengaja tidak dituntut lagi bernilai 100.
   DML-2026-079 membuka pita teratas sampai 999.99 atas keputusan pemilik proyek: nilai
   di atas 100 adalah pencapaian sah dan tidak dipotong. Penjaga ini dulu menuntut tepat
   100, sehingga ia gagal setiap kali dijalankan ulang sesudah 079 - penjaga yang
   menuntut keadaan yang sudah sengaja diubah bukan penjaga, melainkan halangan.
   Yang tetap dituntut: pitanya mulai dari 0 dan tidak berlubang. */
IF EXISTS (
    SELECT r.Score_Type, r.Score_Usage
    FROM N_PA_Score_Range r
    WHERE r.Flag_Aktif = 'Y'
    GROUP BY r.Score_Type, r.Score_Usage
    HAVING MIN(r.Nilai_Dari) <> 0.00
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50289, 'DML-2026-078: ada kelompok pita tersimpan yang tidak mulai dari 0.', 1;
END;

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-078-seed-pita-nilai-performance-appraisal'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-078-seed-pita-nilai-performance-appraisal',
         'DML',
         'Isi pita HAV Map dan pita grade PA (sementara)',
         'Mengisi N_PA_Score_Range dengan tiga tingkat HAV Map pada sumbu KPI dan KOMPETENSI, serta enam pita grade pada sumbu FINAL beserta target distribusinya.',
         'database/dml/DML-2026-078-seed-pita-nilai-performance-appraisal/data-change.sql',
         'docs/changes/DML-2026-078-seed-pita-nilai-performance-appraisal/README.md',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'ANGKANYA SEMENTARA. Deck PIC tidak memuat satu pun ambang grade; kode A/B+/B/C+ di sana hanya data contoh pada screenshot dan tidak ada target distribusi di mana pun. Skala yang dipakai 0-100 karena mockup membaca HAV Map dan grade sebagai persen, sementara deck sendiri memakai dua skala (1-5 dan persen) untuk hal yang sama - kalau T05 dijawab 1-5, yang berubah cukup ANGKA di tabel ini. Score_Usage KONVERSI sengaja tidak diisi karena normalisasi memakai Skor_Maksimal per butir; tabel konversi akan jadi sumber kebenaran kedua yang bisa bertentangan.',
         'performance-appraisal');
END;

COMMIT TRANSACTION;
GO
