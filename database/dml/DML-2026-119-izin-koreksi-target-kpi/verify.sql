/* =====================================================================
   DML-2026-119-izin-koreksi-target-kpi -- VERIFIKASI

   READ ONLY. Tidak mengubah apa pun. Jalankan setelah data-change.sql.
   Harapan: 5 periksa, 5 LULUS, 0 GAGAL.
   ===================================================================== */

SET NOCOUNT ON;

PRINT '=== Verifikasi DML-2026-119 pada database: ' + DB_NAME() + ' ===';

;WITH penerima AS (
    SELECT * FROM (VALUES
        (285,  'RIDHO RAHMATULLAH'),
        (367,  'ABDUL RACHMAN'),
        (899,  'OKKY ADMIRAL NUGRATAMA'),
        (1093, 'QORY AZZAHRA'),
        (1836, 'DESTU SYAH INANDA')
    ) AS t(UserID_Web, Nama)
),
hasil AS (
    /* 1. Kelima penerima memegang izin yang aktif. */
    SELECT
        1 AS no,
        CAST('Kelima penerima memegang kpiKoreksiTarget aktif' AS varchar(80)) AS periksa,
        CAST(CAST(COUNT(*) AS varchar(20)) + ' dari 5' AS varchar(60)) AS temuan,
        CASE WHEN COUNT(*) = 5 THEN 'LULUS' ELSE 'GAGAL' END AS status
    FROM dbo.HRIS_Page_Access AS a
    INNER JOIN penerima AS p ON p.UserID_Web = a.UserID_Web
    WHERE a.Jenis_page = 'kpiKoreksiTarget'
      AND a.Flag_Aktif = 'Y'

    UNION ALL

    /* 2. Tidak ada baris kembar. Izin ganda tidak menambah kuasa, tetapi
          membuat pencabutan berikutnya menyisakan baris yang masih hidup. */
    SELECT
        2,
        CAST('Tidak ada izin kembar per orang' AS varchar(80)),
        CAST(CAST(COUNT(*) AS varchar(20)) + ' orang punya lebih dari satu baris' AS varchar(60)),
        CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END
    FROM (
        SELECT UserID_Web
        FROM dbo.HRIS_Page_Access
        WHERE Jenis_page = 'kpiKoreksiTarget'
        GROUP BY UserID_Web
        HAVING COUNT(*) > 1
    ) AS kembar

    UNION ALL

    /* 3. Tidak ada penerima di luar daftar. Fitur ini sengaja diberikan
          per orang; siapa pun yang masuk tanpa keputusan adalah cacat. */
    SELECT
        3,
        CAST('Tidak ada penerima di luar daftar keputusan' AS varchar(80)),
        CAST(CAST(COUNT(*) AS varchar(20)) + ' penerima tak terduga' AS varchar(60)),
        CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END
    FROM dbo.HRIS_Page_Access AS a
    WHERE a.Jenis_page = 'kpiKoreksiTarget'
      AND a.Flag_Aktif = 'Y'
      AND NOT EXISTS (SELECT 1 FROM penerima p WHERE p.UserID_Web = a.UserID_Web)

    UNION ALL

    /* 4. Kunci ini TIDAK boleh masuk N_HRIS_Grup_Fitur_Map. Begitu masuk,
          setiap pemegang role KPI ikut mendapat kuasa mengubah angka
          penilaian -- persis pemisahan yang hendak dijaga fitur ini. */
    SELECT
        4,
        CAST('kpiKoreksiTarget tetap di luar N_HRIS_Grup_Fitur_Map' AS varchar(80)),
        CAST(CAST(COUNT(*) AS varchar(20)) + ' baris pemetaan role' AS varchar(60)),
        CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END
    FROM dbo.N_HRIS_Grup_Fitur_Map
    WHERE Jenis_page = 'kpiKoreksiTarget'

    UNION ALL

    /* 5. Identitas perusahaan/level/divisi ikut terisi. Ketiganya NOT NULL,
          jadi nol di sini berarti barisnya memang lahir dari penyalinan,
          bukan dari nilai yang ditebak. */
    SELECT
        5,
        CAST('Kode_Perusahaan / ID_Level / ID_Divisi terisi wajar' AS varchar(80)),
        CAST(CAST(COUNT(*) AS varchar(20)) + ' baris bernilai kosong/nol' AS varchar(60)),
        CASE WHEN COUNT(*) = 0 THEN 'LULUS' ELSE 'GAGAL' END
    FROM dbo.HRIS_Page_Access
    WHERE Jenis_page = 'kpiKoreksiTarget'
      AND (Kode_Perusahaan IS NULL OR LTRIM(RTRIM(Kode_Perusahaan)) = ''
           OR ID_Level = 0 OR ID_Divisi = 0)
)
SELECT no, periksa, temuan, status FROM hasil ORDER BY no;

PRINT '--- Rincian penerima ---';

SELECT
    a.UserID_Web,
    k.Kode_Karyawan,
    k.Nama,
    a.Kode_Perusahaan,
    a.ID_Level,
    a.ID_Divisi,
    a.Flag_Aktif,
    a.Created_At
FROM dbo.HRIS_Page_Access AS a
OUTER APPLY (
    SELECT TOP (1) kk.Kode_Karyawan, kk.Nama
    FROM dbo.Karyawan AS kk
    WHERE kk.UserID_Web = a.UserID_Web
    ORDER BY kk.Kode_Karyawan
) AS k
WHERE a.Jenis_page = 'kpiKoreksiTarget'
ORDER BY a.UserID_Web;
