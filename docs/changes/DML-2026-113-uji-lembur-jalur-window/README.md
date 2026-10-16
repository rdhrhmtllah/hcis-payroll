# DML-2026-113 — Data uji jalur window, gerbang, dan kombinasi lembur

Gelombang ketiga, menutup jalur yang belum tersentuh
[DML-2026-111](../DML-2026-111-uji-fn-perhitungan-lembur/README.md) dan
[DML-2026-112](../DML-2026-112-uji-lembur-long-shift/README.md). Data uji di database
**pengembangan** `Web_HRIS`; bukan perubahan skema dan tidak untuk dijalankan di
produksi. Sesuai permintaan, `Fleksibel='Y'` dan `Dihitung_Setelah > 0` **tidak**
termasuk dan tetap belum teruji.

## Yang ditutup

| Kelompok | Jalur |
|---|---|
| W | pengambilan CheckIn/CheckOut lewat **window** `BCI/ECI/BCO/ECO` untuk baris `CHECKINOUT` **mentah** (`Hasil` dan `Untuk_Tanggal` NULL) |
| S | gerbang `Flag_Sakit_Izin_Cuti` |
| X | transaksi lembur **dibatalkan** (header `Status <> NULL`) |
| AB | `LEMBUR_AWAL` + `LEMBUR` pada hari dan orang yang sama |
| NA | `LEMBUR_AWAL` pada shift malam yang menyeberang hari |
| M | dua transaksi lembur pada tanggal yang sama (non-determinisme) |

Kelompok W adalah yang terpenting. SP mencari `Hasil='In' AND Untuk_Tanggal=<tanggal>`
lebih dulu; kalau tidak ketemu, ia jatuh ke `CheckTime BETWEEN @TempBCI AND @TempECI`
(ambil paling awal) dan `BETWEEN @TempBCO AND @TempECO` (ambil paling akhir). Di data
dev yang nyata mayoritas baris `CHECKINOUT` punya `Hasil` dan `Untuk_Tanggal` NULL —
bentuk mentah dari mesin absen — jadi **jalur window inilah yang dipakai produksi**,
dan 111/112 tidak pernah menyentuhnya karena selalu mengisi kedua kolom itu.

## Hasil verifikasi (2026-09-16, `Web_HRIS`)

`database/dml/DML-2026-113-uji-lembur-jalur-window/verify.sql` → **23 periksa, 23 lulus, 0 gagal.**
DML-2026-111 (18/18) dan DML-2026-112 (20/20) dijalankan ulang setelah penyemaian ini
dan tetap hijau, jadi data baru tidak mencemari yang lama.

| # | Kry | Tgl | Kolom | Harap | Nyata | Yang dibuktikan |
|---|---|---|---|---|---|---|
| W1 | R53 | 14 | Overtime | 180 | **180** | jalur window, kasus normal |
| W2 | S55 | 14 | RealLate | 15 | **15** | CheckIn = tap paling AWAL (08:45), bukan 08:50 |
| W2b | S55 | 14 | Overtime | 170 | **170** | CheckOut = tap paling AKHIR (19:20), bukan 19:00 |
| W2c | S55 | 14 | Overtime_Calculated | 155 | **155** | 170 − telat 15 |
| W3 | S56 | 14 | CheckOut_Terisi | 0 | **0** | tap 23:45 di luar window → CheckOut NULL |
| W3b | S56 | 14 | Overtime | 0 | **0** | akibatnya lembur hangus total |
| W4 | T57 | 14 | CheckOut_Terisi | 1 | **1** | tap 23:45 **sama persis**, window lebih lebar |
| W4b | T57 | 14 | Overtime | 330 | **330** | diplafon di estimasi 22:00 |
| W5 | A3 | 18 | Overtime_Shift | 360 | **360** | window lintas hari pada shift malam |
| S1 | P44 | 17 | Flag_SIC_Ya | 1 | **1** | sakit disetujui terdeteksi |
| S1b | P44 | 17 | Overtime_Shift | 0 | **0** | long shift tertutup gerbang sakit |
| S2 | T36 | 14 | Flag_SIC_Ya | 1 | **1** | sakit disetujui terdeteksi |
| S2b | T36 | 14 | Overtime | 180 | **180** | lembur pengajuan **tetap dibayar** walau sakit |
| X1 | T58 | 14 | Overtime | 0 | **0** | header `Status='Y'` → transaksi diabaikan |
| AB1 | V59 | 14 | Overtime_Before | 120 | **120** | lembur awal |
| AB1b | V59 | 14 | Overtime | 180 | **180** | lembur setelah shift, hari sama |
| AB1c | V59 | 14 | Overtime_Real | 300 | **300** | keduanya dijumlahkan |
| AB1d | V59 | 14 | Overtime_Calculated | 300 | **300** | tanpa potongan telat |
| AB1e | V59 | 14 | Koefisien_OT_Gross | 5 | **5** | FLOOR(300/60) |
| NA1 | A38 | 16 | Overtime_Before | 120 | **120** | lembur awal pada shift malam |
| NA1b | A38 | 16 | Overtime_Shift | 240 | **240** | long shift tetap jalan |
| NA1c | A38 | 16 | Overtime_Calculated | 360 | **360** | 120 + 240 |
| M1 | W60 | 14 | Overtime ∈ {180, 300} | 1 | **1** | lihat catatan di bawah |

## Tiga hal yang perlu keputusan

**1. Window checkout menyempit ketika ada pengajuan LEMBUR, dan bisa menelan tap yang sah.**
W3 dan W4 punya tap yang **identik** (08:25 dan 23:45) pada shift yang sama. Bedanya hanya
estimasi lembur: W3 sampai 19:30, W4 sampai 22:00. Karena SP menimpa
`@TempECO = Tanggal_Lembur_Sampai + 4 jam`, window W3 menjadi `15:30 .. 23:30` sehingga
tap 23:45 jatuh di luar dan `CheckOut` menjadi NULL — lemburnya hangus **seluruhnya**,
bukan sekadar terpotong. Tanpa pengajuan lembur, window default-nya `15:30 .. D+1 02:00`
dan tap yang sama tertangkap. Orang yang lembur lebih lama dari estimasinya justru
berisiko kehilangan seluruh haknya.

**2. `Flag_Sakit_Izin_Cuti` tidak menyentuh lembur dari pengajuan.** S2 membuktikan
seseorang yang hari itu punya transaksi sakit **disetujui** tetap mendapat `Overtime` 180
menit. Flag itu hanya menutup blok long shift (S1) dan deteksi alpha. Perlu dipastikan ke
HR apakah ini memang aturannya.

**3. Dua transaksi lembur pada tanggal yang sama: pemenangnya tidak ditentukan.** SP
mengambil `SELECT @JenisLembur = ..., @Tanggal_Lembur_Dari_web = ... FROM ...` **tanpa
`TOP 1` dan tanpa `ORDER BY`**. Pada M1, W60 punya dua transaksi di 14 Sep (estimasi
16:30–19:30 dan 16:30–21:30); yang menang pada eksekusi ini adalah **LM09-26-0008**
(estimasi 21:30) sehingga `Overtime` = 300. Hasil itu bergantung rencana eksekusi dan
bisa berubah setelah statistik/indeks berubah. Kalau memang hanya boleh ada satu
transaksi lembur per karyawan per tanggal, penjaganya harus di sisi aplikasi; kalau boleh
lebih dari satu, SP perlu aturan pemilihan yang eksplisit.

## Catatan teknis

`HRIS_Counter` untuk prefix `IS09-26-` bernilai 2, jadi `IS09-26-0003` diambil di skrip
ini dan counter dinaikkan menjadi 3 supaya pengajuan sakit/izin nyata berikutnya
mendapat `0004`, bukan nomor kembar. Blok pembersihan **tidak** menurunkan counter itu
kembali — menurunkannya justru berisiko membuat nomor kembar.

Prefix `LM09-26-` masih belum punya baris `HRIS_Counter`, jadi `LM09-26-0006..0008` aman:
generator aplikasi menyemai dirinya dari `MAX(No_Transaksi)`.

## Pembersihan

Blok `ROLLBACK / PEMBERSIHAN` berkomentar di akhir
`database/dml/DML-2026-113-uji-lembur-jalur-window/data-change.sql`. Jangkarnya
`CHECKINOUT.Memoinfo IN ('UJI-WINDOW','UJI-GERBANG')`, `No_Transaksi` `LM09-26-0006`
s/d `LM09-26-0008`, dan `IS09-26-0003`.
