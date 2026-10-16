/* Change ID: DML-2026-152-kpi-jepit-skor-menunggu-approval

   Penjepitan skor per parameter (50-125%) baru ter-deploy ke produksi sekitar
   12 Sep 2026, bukan 28 Agt 2026. Update performance yang diajukan di antara
   dua tanggal itu tersimpan TANPA dijepit, dan approver melihatnya apa adanya
   (kasus nyata: AKPI-08-2026-0284, target 3 jam, aktual 44 -> 1466,67%).

   Cakupan (keputusan user 29 Sep 2026): HANYA transaksi yang MASIH MENUNGGU
   approval dan diajukan mulai 28 Agt 2026. Yang sudah disetujui/ditolak dan
   data sebelum 28 Agt tidak disentuh. Draft tidak perlu: finalisasi kini
   menghitung ulang skornya.

   Ukuran di produksi (baca saja, 29 Sep 2026): 21 header, 53 detail
   (48 di atas 125, 5 di bawah 50).

   Rumusnya meniru KpiScoringService::scoreFromRatio dan aggregate/applyCap:
     detail : Score (achievement %) dijepit ke [50,125];
              Achievement (skor berbobot) = ROUND(Bobot * Score/100, 2);
              nilai lama dipindah ke Score_Raw / Achievement_Raw; Flag_Capped='Y'.
     header : Total_Score = jepit(ROUND(SUM(Achievement)/SUM(Bobot)*100, 2), 50, 125)
              Total_Score_Raw = ROUND(SUM(raw)/SUM(Bobot)*100, 2)
              Id_Performance  = band Range_Awal <= FLOOR(Total_Score), terendah bila tak ada.

   IDEMPOTEN: hanya detail ber-Score_Raw NULL di luar batas yang dijaring, dan
   detail yang sudah diproses tidak lagi NULL. Menjalankan ulang = 0 baris. */

SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @Maks DECIMAL(9, 2) = 125.00;
DECLARE @Min DECIMAL(9, 2) = 50.00;

DECLARE @Detail TABLE (Id_Update_Performance_Detail INT PRIMARY KEY, Id_Update_Performance INT NOT NULL);

INSERT INTO @Detail (Id_Update_Performance_Detail, Id_Update_Performance)
SELECT d.Id_Update_Performance_Detail, d.Id_Update_Performance
FROM KPI_Update_Performance_Detail d
JOIN KPI_Update_Performance h ON h.Id_Update_Performance = d.Id_Update_Performance
WHERE h.Flag_Aktif = 'Y'
  AND h.Status IS NULL
  AND h.Flag_Approval IS NULL
  AND h.Tanggal >= '2026-08-28'
  AND d.Score_Raw IS NULL
  AND (d.Score > @Maks OR d.Score < @Min);

/* 1. Detail: jepit, simpan nilai lama sebagai jejak mentah. */
UPDATE d
SET d.Score_Raw = d.Score,
    d.Achievement_Raw = d.Achievement,
    d.Score = CASE WHEN d.Score > @Maks THEN @Maks ELSE @Min END,
    d.Achievement = ROUND(CAST(pd.Bobot AS DECIMAL(18, 4)) * (CASE WHEN d.Score > @Maks THEN @Maks ELSE @Min END) / 100.0, 2),
    d.Flag_Capped = 'Y'
FROM KPI_Update_Performance_Detail d
JOIN @Detail t ON t.Id_Update_Performance_Detail = d.Id_Update_Performance_Detail
JOIN KPI_Pengajuan_Det det ON det.Id_Pengajuan_Det = d.Id_Pengajuan_Det
JOIN KPI_Pengajuan_Detail pd ON pd.Id_Pengajuan_Detail = det.Id_Pengajuan_Detail;

/* 2. Header: hitung ulang total dari SELURUH detailnya. */
;WITH agg AS (
    SELECT d.Id_Update_Performance,
           SUM(CAST(d.Achievement AS DECIMAL(18, 4))) AS jumlah,
           SUM(CAST(ISNULL(d.Achievement_Raw, d.Achievement) AS DECIMAL(18, 4))) AS jumlah_mentah,
           SUM(CAST(pd.Bobot AS DECIMAL(18, 4))) AS bobot
    FROM KPI_Update_Performance_Detail d
    JOIN KPI_Pengajuan_Det det ON det.Id_Pengajuan_Det = d.Id_Pengajuan_Det
    JOIN KPI_Pengajuan_Detail pd ON pd.Id_Pengajuan_Detail = det.Id_Pengajuan_Detail
    WHERE d.Id_Update_Performance IN (SELECT DISTINCT Id_Update_Performance FROM @Detail)
    GROUP BY d.Id_Update_Performance
),
hitung AS (
    SELECT a.Id_Update_Performance,
           a.jumlah,
           a.jumlah_mentah,
           ROUND(a.jumlah / NULLIF(a.bobot, 0) * 100, 2) AS persen,
           ROUND(a.jumlah_mentah / NULLIF(a.bobot, 0) * 100, 2) AS persen_mentah
    FROM agg a
),
jepit AS (
    SELECT x.*,
           CASE WHEN x.persen > @Maks THEN @Maks WHEN x.persen < @Min THEN @Min ELSE x.persen END AS total
    FROM hitung x
)
UPDATE h
SET h.Total_Score = j.total,
    h.Total_Score_Achievement = j.jumlah,
    h.Total_Score_Raw = j.persen_mentah,
    h.Total_Score_Achievement_Raw = ROUND(j.jumlah_mentah, 2),
    h.Flag_Capped = 'Y',
    h.Metode_Cap = 'PARAMETER',
    h.Cap_Min_Applied = @Min,
    h.Cap_Max_Applied = @Maks,
    h.Id_Performance = ISNULL(
        (SELECT TOP 1 mp.Id_Master_Performance FROM KPI_Master_Performance mp
          WHERE mp.Range_Awal <= FLOOR(j.total) ORDER BY mp.Range_Awal DESC),
        (SELECT TOP 1 mp.Id_Master_Performance FROM KPI_Master_Performance mp ORDER BY mp.Range_Awal ASC))
FROM KPI_Update_Performance h
JOIN jepit j ON j.Id_Update_Performance = h.Id_Update_Performance
WHERE j.persen IS NOT NULL;

SELECT (SELECT COUNT(*) FROM @Detail) AS detail_dijepit,
       (SELECT COUNT(DISTINCT Id_Update_Performance) FROM @Detail) AS header_dihitung_ulang;

/* 3. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-152-kpi-jepit-skor-menunggu-approval')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-152-kpi-jepit-skor-menunggu-approval', 'DML', 'Jepit skor KPI yang menunggu approval (diajukan 28 Agt - 12 Sep 2026)', 'Update performance yang diajukan sebelum penjepitan per parameter ter-deploy ke produksi tersimpan tanpa dijepit. Hanya transaksi yang masih menunggu approval.', 'database/dml/DML-2026-152-kpi-jepit-skor-menunggu-approval/data-change.sql', 'docs/changes/DML-2026-152-kpi-jepit-skor-menunggu-approval/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'kpi-cap-skor-parameter');

COMMIT TRANSACTION;
