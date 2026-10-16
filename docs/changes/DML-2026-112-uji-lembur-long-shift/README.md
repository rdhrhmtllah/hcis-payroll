# DML-2026-112 — Data uji lembur LONG SHIFT

Pelengkap [DML-2026-111](../DML-2026-111-uji-fn-perhitungan-lembur/README.md). Kalau
111 menguji `dbo.N_HRIS_FN_CalculateOvertime` (lembur dari **pengajuan**), 112 menguji
blok **"PERHITUNGAN LEMBUR LONG SHIFT (LEVEL 1,2,21)"** di dalam
`dbo.HRIS_SP_GET_ABSEN_NEW_V3` — lembur **otomatis** yang melekat pada shift, tanpa
transaksi pengajuan. Data uji di database **pengembangan** `Web_HRIS`; bukan perubahan
skema dan tidak untuk dijalankan di produksi.

## Rumus yang diuji

Gerbang — semua harus benar, kalau tidak `Overtime_Shift` = 0:

```
Flag_LongShift = 'Y'
AND Flag_Sakit_Izin_Cuti = 'T'
AND StatusHari = 'WORK'
AND Status_Karyawan = 'JOIN'
AND ID_Level IN (1, 2, 21)
AND CheckOut IS NOT NULL
```

Perhitungan:

```
Plafon         = Durasi_Lembur (jam, HRIS_Waktu_Kerja) x 60
Titik mulai    = Temp_JamKeluar - Durasi_Lembur jam
Sudah dijalani = DATEDIFF(MINUTE, Titik mulai, CheckOut)
Overtime_Shift = MIN(Sudah dijalani, Plafon), 0 bila negatif
```

Karena `Jam_Keluar < Jam_Masuk` pada shift malam, `Temp_JamKeluar` digeser ke H+1.
Untuk **EMI-B2** (20:00–08:00, `Durasi_Lembur` 6) pada tanggal kerja D: titik mulai =
D+1 02:00, plafon 360 menit. Untuk **EMI-LS2** (19:00–07:00, `Durasi_Lembur` 4): titik
mulai = D+1 03:00, plafon 240 menit.

## Hasil verifikasi (2026-09-16, `Web_HRIS`)

`database/dml/DML-2026-112-uji-lembur-long-shift/verify.sql` → **20 periksa, 20 lulus, 0 gagal.**

| # | Kry | Tgl | Shift | CheckIn | CheckOut | Kolom | Harap | Nyata |
|---|---|---|---|---|---|---|---|---|
| L1 | P44 | 15 | EMI-B2 | 15/19:50 | 16/08:00 | Overtime_Shift | 360 | **360** |
| L1b | P44 | 15 | EMI-B2 | | | Koefisien_OT_Gross | 6 | **6** |
| L1c | P44 | 15 | EMI-B2 | | | Koefisien_OT | 1 | **1** |
| L2 | A3 | 14 | EMI-B2 | 14/19:50 | 15/05:30 | Overtime_Shift | 210 | **210** |
| L3 | D11 | 14 | EMI-B2 | 14/19:50 | 15/10:00 | Overtime_Shift | 360 | **360** |
| L4 | P44 | 14 | EMI-B2 | 14/19:50 | 15/01:00 | Overtime_Shift | 0 | **0** |
| L5 | A3 | 17 | EMI-B2 | 17/19:50 | — | Overtime_Shift | 0 | **0** |
| L6 | A38 | 14 | EMI-LS2 | 14/18:50 | 15/07:00 | Overtime_Shift | 240 | **240** |
| L6b | A38 | 15 | EMI-LS2 | 15/18:50 | 16/05:00 | Overtime_Shift | 120 | **120** |
| L7 | K32 | 14 | EMI-LS1 | 06:50 | 19:00 | Overtime_Shift | 0 | **0** |
| L8 | C9 | 14 | EMI-LS1 | 06:50 | 19:00 | Overtime_Shift | 0 | **0** |
| L9 | A3 | 16 | EMI-B2 | 16/20:45 | 17/08:00 | Overtime_Shift | 360 | **360** |
| L9b | A3 | 16 | EMI-B2 | | | RealLate | 45 | **45** |
| L9c | A3 | 16 | EMI-B2 | | | Overtime_Gross | 360 | **360** |
| L9d | A3 | 16 | EMI-B2 | | | Overtime_Calculated | 315 | **315** |
| L10 | D11 | 16 (OFF) | — | 16/19:50 | 17/08:00 | Overtime_Shift | 0 | **0** |
| L11 | D11 | 15 | EMI-B2 | 15/19:50 | 16/10:00 | Overtime | 120 | **120** |
| L11b | D11 | 15 | EMI-B2 | | | Overtime_Shift | 360 | **360** |
| L11c | D11 | 15 | EMI-B2 | | | Overtime_Calculated | 480 | **480** |
| L12 | A1 | 14 | EMI-B2 | 14/19:50 | 15/08:00 | Overtime_Shift | 0 | **0** |

### Yang dibuktikan

- **L1–L4, L6, L6b** — bentuk rumusnya: pulang tepat jam keluar memberi plafon penuh,
  pulang lebih awal dibayar aktual dari titik mulai, pulang lebih lama tetap diplafon,
  dan pulang sebelum titik mulai menghasilkan 0 (bukan angka negatif). L6/L6b memakai
  shift dengan `Durasi_Lembur` berbeda (4 jam) untuk membuktikan plafon memang ikut
  master shift, bukan angka tetap.
- **L5, L7, L8, L10, L12** — empat gerbang: `CheckOut IS NOT NULL`,
  `ID_Level IN (1,2,21)` (L7 level 5, L8 level 10), `StatusHari='WORK'`, dan
  `Status_Karyawan='JOIN'`.
- **L9** — telat **tidak** memotong `Overtime_Shift` (tetap 360) maupun
  `Overtime_Gross`; potongannya terjadi pada `Overtime_Calculated` (315).
- **L11** — long shift dan lembur pengajuan hidup berbarengan pada hari yang sama:
  `Overtime` 120 dari fungsi, `Overtime_Shift` 360 dari blok long shift, dan
  `Overtime_Calculated` = 480. Perhatikan tanggal kerjanya 15 Sep sementara estimasi
  lemburnya jatuh 16 Sep karena shift malam menyeberang hari — `Transaksi_Lembur_Det.Tanggal_Lembur`
  harus diisi tanggal kerja, **bukan** diturunkan dari tanggal estimasi.

## Catatan

**A1 (Frans Bachtiar) punya `Tanggal_Resign = 2025-09-03` walau `Aktif = 'Y'`**, dan SP
melaporkannya `Status_Karyawan = 'RESIGN'`. Ini ketahuan saat percobaan pertama: tiga
periksa gagal karena A1 semula dipakai sebagai kasus normal. Menyaring kandidat dengan
`Karyawan.Aktif = 'Y'` saja **tidak cukup** untuk skenario long shift; `Tanggal_Resign`
harus ikut diperiksa. A1 kemudian dipindah menjadi skenario gerbang L12.

`Koefisien_OT` selalu terpotong menjadi **1 jam** pada hari WORK karena
`N_HRIS_Overtime_Limits` aktif (`Effective_Date` 2026-07-25) memberi
`Max_Hours_Workday = 1`. Nilai sebelum plafon ada di `Koefisien_OT_Gross`. Ini konfigurasi,
bukan cacat perhitungan.

`Toleransi_Telat` = 0 pada EMI-B2/EMI-LS1/EMI-LS2, dan tidak ada baris
`HRIS_Lembur_Per_Karyawan` untuk ketujuh karyawan ini sehingga `Dihitung_Setelah` = 0.
Dua hal itu yang membuat angka di matriks bisa dihitung tangan.

## Pembersihan

Blok `ROLLBACK / PEMBERSIHAN` berkomentar di akhir
`database/dml/DML-2026-112-uji-lembur-long-shift/data-change.sql`. Jangkarnya
`CHECKINOUT.Memoinfo = 'UJI-LONGSHIFT'` dan `No_Transaksi = 'LM09-26-0005'`.
