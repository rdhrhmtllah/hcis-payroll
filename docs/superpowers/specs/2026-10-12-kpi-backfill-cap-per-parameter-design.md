# Backfill cap KPI ke metode per parameter (seluruh data)

Tanggal: 12 Okt 2026. Status: rancangan disetujui user di obrolan; menunggu tinjauan spec.

## Masalah

Sejak 28 Agt 2026 skor KPI dijepit pada **achievement tiap parameter** (50–125),
bukan lagi pada total header. Waktu itu diputuskan data lama **tidak** dihitung
ulang. Akibatnya data produksi bercampur:

- record lama: parameternya tak dijepit; totalnya mungkin dijepit (`Metode_Cap`
  `TOTAL` atau `NULL`);
- record yang sudah dijepit per parameter tetapi header-nya tak berlabel
  (`Metode_Cap NULL`) — termasuk record auto-cap sebelum perbaikan 12 Okt;
- record baru berlabel `PARAMETER`.

Skor dan level bulanan antar periode jadi tidak sebanding.

## Keputusan (user, 12 Okt 2026)

| Pertanyaan | Jawaban |
|---|---|
| Tujuan | **Hitung ulang** dengan cap per parameter — membalik keputusan 28 Agt. |
| Cakupan | **Semua periode**, termasuk yang sudah disetujui/ditolak dan record auto-cap. |
| Cara eksekusi | **Endpoint yang di-hit dari Postman** — user tak bisa menulis ke DB produksi dari lokal. |
| Cadangan | **Tabel baru lewat DDL** (dijalankan lewat SQL Tracker), rollback lengkap. |
| Yang diubah (sesudah pratinjau dev) | **Hanya header terdampak cap** — punya parameter di luar 50–125 yang belum pernah dijepit — dihitung ulang skor & levelnya. Header lain hanya diberi label `Metode_Cap='PARAMETER'`; skor, level, dan detailnya tidak disentuh. |

## Sasaran

Header `KPI_Update_Performance` dengan `Flag_Aktif='Y'`, `Status IS NULL`
(tidak batal), dan `Metode_Cap` NULL atau bukan `'PARAMETER'`, beserta seluruh
baris `KPI_Update_Performance_Detail`-nya.

Tidak disentuh: header batal (`Flag_Aktif='T'`/`Status='Y'`), draft
(`*_Sementara`), header yang sudah `PARAMETER`, dan akumulasi YTD (selalu
dihitung ulang dari aktual/target oleh `KpiAkumulasiYtdService`).

Terukur di dev `Web_HRIS` (12 Okt): 3.421 header sasaran (3.381 disetujui, 37
menunggu, 3 ditolak), 12.635 detail; 4.520 detail di luar 50–125; 6.816 detail
sudah ber-`Score_Raw`; nol detail ber-`Score` NULL/negatif, nol header ber-Σbobot 0.
Angka produksi diukur lewat pratinjau sebelum eksekusi.

## Status: eksekusi dikunci (12 Okt 2026)

Pratinjau dev menunjukkan letak persen pencapaian di detail **tidak konsisten**:
5.588 baris di `Score` (aturan sekarang), **2.385 di `Achievement`** (tertukar),
1.679 tak cocok dengan rumus sama sekali, 2.983 bertarget 0. Aturan "terdampak"
yang membaca `Score` memotong record sah yang kolomnya tertukar (contoh: aktual 8,
target 12 = 66,67% tersimpan di `Achievement`; `Score` 16,67 terbaca di bawah 50).

Keputusan user: rilis **pratinjau + `diagnosis_kolom`** dulu; `mode=jalankan` dan
rollback dikembalikan **423** oleh konstanta `KpiBackfillCapController::EKSEKUSI_DIBUKA`
(di kode, bukan env). Aturan final — kolom mana yang dipercaya sebagai persen,
dan perlakuan baris tak cocok/target 0 — ditetapkan dari pratinjau produksi,
lalu kunci dibuka di PR terpisah.

## Header terdampak vs hanya label

Pratinjau di dev (12 Okt 2026) memperlihatkan bahwa data lama menyimpan
`Achievement` dengan cara berbeda dari rumus sekarang (mis. Score 60, Achievement
75, Bobot 80). Menghitung ulang semua header akan mengubah total 1.117 header yang
tak satu pun parameternya dijepit. Karena itu:

- **terdampak** = ada detail yang hasil kalkulatornya `Flag_Capped='Y'` dan
  `Score_Raw` lamanya NULL → detail & header ditulis penuh (rumus di bawah);
- **selain itu** → hanya `Metode_Cap='PARAMETER'`; cadangan HEADER tetap ditulis
  agar label pun bisa di-rollback.

Pratinjau melaporkan `header_dihitung_ulang` dan `header_hanya_label`; angka skor
berubah, detail berubah, dan perpindahan level hanya dihitung dari header terdampak.

## Rumus — memakai `KpiScoringService` langsung

Tidak ditulis ulang di SQL: perhitungan memanggil `scoreFromRatio`, `aggregate`,
`applyCap`, dan `resolveLevel` milik aplikasi, sehingga hasilnya identik dengan
finalisasi. Batas dari `paramCapBounds()`/`capBounds()` (baku 125/50); batas yang
dipakai ikut tercatat di hasil pratinjau.

**Detail:**

```
asli_score = COALESCE(Score_Raw, Score)              -- persen achievement mentah
asli_ach   = COALESCE(Achievement_Raw, Achievement)  -- skor berbobot mentah
hasil      = scoreFromRatio(asli_score / 100, Bobot, asli_ach)
Score = hasil.achievement   Achievement = hasil.score
Score_Raw = hasil.achievement_raw   Achievement_Raw = hasil.score_raw
Flag_Capped = hasil.is_capped ? 'Y' : 'T'
```

`COALESCE` ke `_Raw` membuat proses idempoten: detail yang sudah dijepit
menghasilkan nilai yang sama. `Bobot` dari `KPI_Pengajuan_Detail` lewat
`KPI_Pengajuan_Det`.

**Header:** `aggregate(detail baru)` → `applyCap` → `Total_Score`,
`Total_Score_Achievement`, `Total_Score_Raw`, `Total_Score_Achievement_Raw`,
`Flag_Capped`, `Cap_Min_Applied`, `Cap_Max_Applied`, `Metode_Cap='PARAMETER'`,
`Id_Performance = resolveLevel(floor(Total_Score))` — persis seperti
`KpiEnforcementService` dan finalisasi normal.

## Rancangan

### DDL-2026-167-kpi-cadangan-backfill-cap

Tabel `N_KPI_Backfill_Cap_Cadangan` — satu baris per objek yang diubah:

| Kolom | Catatan |
|---|---|
| `Id_Cadangan` | bigint IDENTITY PK |
| `Batch_Id` | varchar(60) NOT NULL |
| `Jenis` | char(6) `HEADER` / `DETAIL` |
| `Id_Update_Performance` | int NOT NULL |
| `Id_Update_Performance_Detail` | int NULL (untuk `DETAIL`) |
| `Nilai_Lama_Json` | nvarchar(max) — seluruh kolom yang akan diubah |
| `Dipulihkan_At` | datetime2 NULL — terisi saat rollback |
| `Created_At` | datetime2 default SYSDATETIME() |

Indeks `(Batch_Id, Jenis, Id_Update_Performance)`. Artefak: `forward.sql`,
`drop.sql`, entri `status.json`, README perubahan, dokumentasi tabel. **Wajib
dijalankan di produksi sebelum kode ini dipakai**; tanpa tabel, endpoint
menolak menjalankan (pratinjau tetap boleh).

### Endpoint (pola backfill yang sudah ada)

Di luar grup `auth`, dikecualikan dari CSRF, dijaga header `X-Backfill-Token`
(`config('services.kpi.backfill_token')`, `hash_equals`, gagal-tertutup bila
token kosong) — sama dengan endpoint backfill lama.

| Metode & URL | Fungsi |
|---|---|
| `POST /api/v1/kpi-enforcement/backfill-cap-parameter` body `mode=pratinjau` (baku) | Baca saja, sinkron. Jumlah header/detail sasaran per status approval, detail yang berubah, header yang skornya berubah, **perpindahan level** (dari → ke, jumlah), 20 contoh, batas cap yang dipakai. |
| idem, `mode=jalankan`, `requested_by=<Kode_Karyawan>` | Buat batch, balas **202** + `batch_id`. Proses di background (Cloud Tasks). |
| `GET /api/v1/kpi-enforcement/backfill-cap-parameter/{batchId}` | Status & progres batch. |
| `POST .../backfill-cap-parameter/{batchId}/rollback` | Pulihkan header & detail batch itu dari cadangan (juga di background). |

Hanya satu batch boleh berjalan pada satu waktu (penolakan 409 bila ada yang
masih jalan). `requested_by` membuat batch tampil di panel Tugas & Aktivitas
orang tersebut (`N_HRIS_Async_Tasks`, `Task_Type='kpi-backfill-cap'`).

Endpoint lama `backfill-cap` (cap **total**, metode yang sudah diganti) dibalas
**410 Gone** dengan pesan menunjuk endpoint baru, agar tak ada yang tanpa sengaja
menjalankan metode lama.

### Pemrosesan

- **Job induk** `BackfillCapParameterBatchJob`: mengambil daftar
  `Id_Update_Performance` sasaran (dipecah per 500 untuk batas 2.100 parameter),
  lalu mendispatch **job potongan** berisi 300 header.
- **Job potongan** `BackfillCapParameterChunkJob`, dalam satu transaksi:
  1. muat header + detail + bobot potongan itu (kueri set, bukan per header);
  2. hitung di PHP dengan `KpiScoringService`;
  3. simpan nilai lama ke `N_KPI_Backfill_Cap_Cadangan`;
  4. tulis hasil secara set-based: nilai hasil masuk tabel sementara lalu satu
     `UPDATE … FROM` per tabel — bukan ribuan UPDATE per baris (`Web_HRIS`
     tanpa RCSI: transaksi panjang memblokir pembaca);
  5. naikkan progres batch.
  Header yang sudah `PARAMETER` dilewati, jadi job yang terkirim ulang oleh Cloud
  Tasks tak menghitung dua kali. Batch yang dibatalkan/gagal bisa diulang dengan
  `mode=jalankan` lagi: sisanya saja yang diproses.
- **Rollback** memakai pola potongan yang sama, membaca cadangan per batch.

### Kode lama

`KpiCapBackfillService` (cap total) tidak lagi dipanggil; dihapus bersama
pemanggilnya, uji `PenandaMetodeCapTest` disesuaikan.

## Urutan rilis

1. Agent: DDL-167 + kode + uji; DDL dijalankan di dev sesudah SQL disetujui.
2. Agent: uji eksekusi penuh di dev (pratinjau → jalankan → verifikasi → rollback → jalankan lagi).
3. User: rekam DDL-167 di SQL Tracker, jalankan di produksi; deploy kode (PR ke `master`).
4. User, Postman produksi: `mode=pratinjau` → kirim hasil ke agent → sepakati angka.
5. User: `mode=jalankan` di luar jam kerja → pantau lewat panel / endpoint status.

## Pengujian

- **Kesetaraan rumus**: untuk sampel header sasaran, hasil job dibandingkan dengan
  `KpiScoringService` dihitung terpisah (identik; level sama persis).
- **Pratinjau tidak menulis**: tak ada baris berubah, tak ada cadangan.
- **Idempotensi**: menjalankan ulang batch = 0 perubahan; job potongan terkirim dua kali tak menghitung ganda.
- **Rollback**: nilai pulih identik per kolom dengan sebelum backfill.
- **Gerbang**: tanpa/salah token 401; tanpa tabel cadangan `jalankan` ditolak; batch kedua saat satu berjalan 409; endpoint lama 410.
- Uji yang ada: `tests/Feature/KPI/Monitoring`, `PenandaMetodeCapTest`.
