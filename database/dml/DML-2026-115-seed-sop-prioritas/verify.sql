/* =====================================================================
   DML-2026-115-seed-sop-prioritas -- VERIFIKASI

   READ ONLY. Tidak mengubah apa pun. Jalankan setelah data-change.sql.
   Harapan: 6 periksa, 6 LULUS, 0 GAGAL.
   ===================================================================== */

SET NOCOUNT ON;

PRINT '=== Verifikasi DML-2026-115 pada database: ' + DB_NAME() + ' ===';

;WITH harapan AS (
    SELECT * FROM (VALUES
        ('KRITIS', 'Sangat Penting', 10, '#EF4444'),
        ('TINGGI', 'Tinggi',         20, '#F59E0B'),
        ('NORMAL', 'Normal',         30, '#94A3B8')
    ) AS v (Kode, Nama, Urutan, Warna)
),
periksa AS (
    /* 1-3: tiap baris ada dan isinya persis sama dengan development. */
    SELECT  CAST(ROW_NUMBER() OVER (ORDER BY h.Urutan) AS int) AS No_Periksa,
            'Baris ' + h.Kode + ' ada dan isinya sama dengan development'  AS Periksa,
            CASE WHEN p.Kode IS NULL                    THEN 'GAGAL: baris tidak ada'
                 WHEN p.Nama       <> h.Nama            THEN 'GAGAL: Nama = '   + p.Nama
                 WHEN p.Urutan     <> h.Urutan          THEN 'GAGAL: Urutan = ' + CAST(p.Urutan AS varchar(10))
                 WHEN p.Warna      <> h.Warna           THEN 'GAGAL: Warna = '  + p.Warna
                 WHEN p.Flag_Aktif <> 'Y'               THEN 'GAGAL: Flag_Aktif = ' + p.Flag_Aktif
                 ELSE 'LULUS' END                                          AS Hasil
    FROM harapan h
    LEFT JOIN dbo.N_SOP_Prioritas p ON p.Kode = h.Kode

    UNION ALL
    /* 4: tidak ada Kode kembar. */
    SELECT 4,
           'Tidak ada Kode kembar',
           CASE WHEN EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas GROUP BY Kode HAVING COUNT(*) > 1)
                THEN 'GAGAL: ada Kode kembar' ELSE 'LULUS' END

    UNION ALL
    /* 5: tepat tiga baris aktif berkode baku - lebih dari itu berarti seed
          dijalankan bersamaan dengan sumber lain. */
    SELECT 5,
           'Tepat 3 baris berkode baku (KRITIS/TINGGI/NORMAL)',
           CASE WHEN (SELECT COUNT(*) FROM dbo.N_SOP_Prioritas
                      WHERE Kode IN ('KRITIS','TINGGI','NORMAL')) = 3
                THEN 'LULUS'
                ELSE 'GAGAL: ' + CAST((SELECT COUNT(*) FROM dbo.N_SOP_Prioritas
                                       WHERE Kode IN ('KRITIS','TINGGI','NORMAL')) AS varchar(10)) + ' baris' END

    UNION ALL
    /* 6: urutan baca aplikasi (ORDER BY Urutan, Kode) menghasilkan
          KRITIS -> TINGGI -> NORMAL, sama dengan konstanta BAWAAN. */
    SELECT 6,
           'Urutan baca aplikasi = KRITIS, TINGGI, NORMAL',
           CASE WHEN (SELECT STRING_AGG(Kode, ',') WITHIN GROUP (ORDER BY Urutan, Kode)
                      FROM dbo.N_SOP_Prioritas WHERE Flag_Aktif = 'Y') = 'KRITIS,TINGGI,NORMAL'
                THEN 'LULUS'
                ELSE 'GAGAL: ' + ISNULL((SELECT STRING_AGG(Kode, ',') WITHIN GROUP (ORDER BY Urutan, Kode)
                                         FROM dbo.N_SOP_Prioritas WHERE Flag_Aktif = 'Y'), '(kosong)') END
)
SELECT No_Periksa, Periksa, Hasil FROM periksa ORDER BY No_Periksa;

SELECT  SUM(CASE WHEN Hasil = 'LULUS' THEN 1 ELSE 0 END) AS Lulus,
        SUM(CASE WHEN Hasil <> 'LULUS' THEN 1 ELSE 0 END) AS Gagal
FROM (
    SELECT CASE WHEN p.Kode IS NOT NULL AND p.Nama = h.Nama AND p.Urutan = h.Urutan
                     AND p.Warna = h.Warna AND p.Flag_Aktif = 'Y'
                THEN 'LULUS' ELSE 'GAGAL' END AS Hasil
    FROM (VALUES ('KRITIS','Sangat Penting',10,'#EF4444'),
                 ('TINGGI','Tinggi',20,'#F59E0B'),
                 ('NORMAL','Normal',30,'#94A3B8')) AS h (Kode, Nama, Urutan, Warna)
    LEFT JOIN dbo.N_SOP_Prioritas p ON p.Kode = h.Kode
    UNION ALL
    SELECT CASE WHEN EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas GROUP BY Kode HAVING COUNT(*) > 1)
                THEN 'GAGAL' ELSE 'LULUS' END
    UNION ALL
    SELECT CASE WHEN (SELECT COUNT(*) FROM dbo.N_SOP_Prioritas
                      WHERE Kode IN ('KRITIS','TINGGI','NORMAL')) = 3 THEN 'LULUS' ELSE 'GAGAL' END
    UNION ALL
    SELECT CASE WHEN (SELECT STRING_AGG(Kode, ',') WITHIN GROUP (ORDER BY Urutan, Kode)
                      FROM dbo.N_SOP_Prioritas WHERE Flag_Aktif = 'Y') = 'KRITIS,TINGGI,NORMAL'
                THEN 'LULUS' ELSE 'GAGAL' END
) AS r;

/* Konteks: paket yang memakai tiap Kode. Bukan periksa lulus/gagal, hanya
   untuk memastikan seed ini memang menjawab data yang sudah ada. */
IF OBJECT_ID('dbo.N_SOP_Master_Paket', 'U') IS NOT NULL
    SELECT Prioritas, COUNT(*) AS Jumlah_Paket
    FROM   dbo.N_SOP_Master_Paket
    GROUP BY Prioritas
    ORDER BY Prioritas;
