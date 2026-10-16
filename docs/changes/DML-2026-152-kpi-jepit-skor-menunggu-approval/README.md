# DML-2026-152 — Jepit skor KPI yang masih menunggu approval

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-152-kpi-jepit-skor-menunggu-approval` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Artifact | `database/dml/DML-2026-152-kpi-jepit-skor-menunggu-approval/data-change.sql` (sha256 `7b0452a87310e4a4ce0600f996d8b322d91f918e2ee2750fb5b20a10cc81e4af`) |
| Bergantung pada | Perbaikan finalisasi di branch `fix/kpi/cap-skor-parameter` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Latar

Penjepitan skor per parameter (50–125%) dirilis di kode 28 Agt 2026, tetapi
baru berjalan di produksi sekitar **12 Sep 2026** (terukur dari kolom
`Score_Raw`: kosong di semua baris sebelum 12 Sep siang, terisi 100% sejak
15 Sep). Update performance yang diajukan di antaranya tersimpan tanpa dijepit,
dan approver melihatnya apa adanya — kasus yang dilaporkan:
`AKPI-08-2026-0284`, *Average Training Hour Per Employee (EMI Pabrik)*,
target 3 jam, aktual 44 → achievement 1466,67%, skor berbobot 220.

## Cakupan (keputusan user, 29 Sep 2026)

```text
KPI_Update_Performance        Flag_Aktif='Y', Status NULL, Flag_Approval NULL (menunggu),
                              Tanggal >= 2026-08-28
KPI_Update_Performance_Detail Score_Raw NULL, Score > 125 atau Score < 50
```

Terukur di produksi (baca saja, 29 Sep 2026): **21 header, 53 detail**
(48 di atas 125, 5 di bawah 50).

Tidak disentuh: transaksi yang sudah disetujui/ditolak (114 disetujui), data
sebelum 28 Agt, dan draft — finalisasi kini menghitung ulang skor draft.
Perhitungan YTD tidak terdampak (selalu menghitung ulang dari aktual/target).

## Isi

```text
Detail  Score        = jepit(Score, 50, 125)
        Achievement  = ROUND(Bobot * Score/100, 2)
        Score_Raw / Achievement_Raw = nilai lama, Flag_Capped = 'Y'
Header  Total_Score  = jepit(ROUND(Σ Achievement / Σ Bobot * 100, 2), 50, 125)
        Total_Score_Raw, Total_Score_Achievement(_Raw), Flag_Capped='Y',
        Metode_Cap='PARAMETER', Cap_Min/Max_Applied = 50/125,
        Id_Performance = band Range_Awal <= FLOOR(Total_Score)
```

Rumus meniru `KpiScoringService::scoreFromRatio()`, `aggregate()`, `applyCap()`
dan `resolveLevel()`.

## Hasil eksekusi development (2026-09-29)

```text
detail_dijepit = 0, header_dihitung_ulang = 0   (dev tidak punya baris sasaran)
N_SYSTEM_Database_Change 1 baris
```

Logika diuji di dev dalam transaksi yang di-rollback: detail 1466,67 → 125 dan
−3221 → 50, total header 87,5 (mentah −337,75) dan level header sama persis
dengan hitungan `KpiScoringService`. Dijalankan ulang: 0 baris.

## Idempoten

Hanya detail ber-`Score_Raw` NULL di luar batas yang dijaring; detail yang sudah
diproses tidak lagi NULL, sehingga eksekusi ulang tidak mengubah apa pun.

## Tugas user

1. Tinjau dan setujui SQL ini.
2. Catat di SQL Tracker persis isi berkas (cocokkan checksum), lalu jalankan di
   produksi **setelah** perbaikan finalisasi ter-deploy.
3. Verifikasi: kueri cakupan di atas harus mengembalikan 0 baris, dan
   `AKPI-08-2026-0284` tidak ikut (sudah disetujui).
