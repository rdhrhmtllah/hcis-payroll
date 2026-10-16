# Cap KPI per parameter

| Bidang | Nilai |
|---|---|
| Tanggal | 2026-08-28 |
| Modul | `KPI` |
| Submodul | Performance / Update, Approval, Monitoring/Enforcement |
| Status | Menunggu tinjauan pengguna |
| Change ID database | `DDL-2026-045-kpi-cap-per-parameter` (belum dibuat) |

## Masalah

Cap skor KPI hari ini bekerja di **garis akhir**: seluruh parameter dihitung apa
adanya, dijumlahkan menjadi satu persentase, baru persentase itu dijepit ke
rentang 50–125.

Akibatnya parameter yang meledak menutupi parameter yang jeblok. Dua parameter
berbobot sama, satu tercapai 300% dan satu 20%, menghasilkan total 160% yang
lalu dijepit menjadi 125% — nilai penuh, seolah tidak ada yang bermasalah.

Yang diinginkan: penjepitan terjadi di **pintu masuk**, pada achievement tiap
parameter, sebelum parameter itu sempat menyumbang ke total.

## Keputusan yang sudah diambil

Empat keputusan ini diambil pengguna dalam sesi 2026-08-28 dan menjadi dasar
seluruh rancangan di bawah. Jangan ditawar ulang saat implementasi.

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Bentuk cap | **Dua sisi** — batas bawah dan batas atas per parameter |
| 2 | Angka batas | **50% dan 125%**, sama dengan batas total yang berlaku sekarang |
| 3 | Data lama | **Tidak dihitung ulang.** Aturan berlaku untuk perhitungan yang terjadi setelah rilis |
| 4 | Jejak audit header | **Artinya dialihkan** ke tingkat parameter, kolomnya tidak dicabut |

Turunan dari keputusan 1: karena rata-rata tertimbang dari nilai-nilai yang
semuanya berada di dalam [50, 125] pasti juga berada di dalam [50, 125], **cap
total tidak akan pernah menggigit lagi**. Ia tetap tinggal di kode sebagai jaring
pengaman, bukan sebagai mekanisme aktif.

Turunan dari keputusan 3: "setelah rilis" berarti setiap perhitungan yang
dipicu setelah rilis — termasuk draf yang disimpan ulang dan record menunggu
approval yang nilai aktualnya diedit approver. Record yang tidak dipicu apa pun
tidak pernah disentuh.

## Kosakata

Nama-nama di sistem ini tertukar dan itu sumber kebingungan yang berulang. Tabel
berikut adalah rujukan tunggal; seluruh dokumen memakai kolom "Sebutan di
dokumen ini".

Untuk satu parameter dengan bobot 50, target 10, aktual 15:

| Sebutan di dokumen ini | Rumus | Nilai | Kunci service | Kolom DB | Label layar |
|---|---|---|---|---|---|
| **achievement** | rasio × 100 | 150% | `achievement` | `Score` | "Achievement" (pengisian), "Score" (enforcement) |
| **skor** | bobot × rasio | 75 | `score` | `Achievement` | "Score" (pengisian), "Achievement" (enforcement) |

Di tingkat header:

| Sebutan | Isi | Kolom DB | Label layar |
|---|---|---|---|
| total persen | Σskor ÷ Σbobot × 100 | `Total_Score` | "Total Achievement" |
| jumlah skor | Σskor | `Total_Score_Achievement` | "Total Score" |

Kolom `Score` menyimpan achievement dan kolom `Achievement` menyimpan skor.
Kekeliruan ini **tidak diperbaiki** dalam pekerjaan ini: mengganti nama kolom
akan memutus setiap pembacanya, dan itu pekerjaan tersendiri dengan risikonya
sendiri. Yang diperbaiki hanyalah label layar yang terbalik (lihat Bagian
"Halaman enforcement").

## Rancangan

### 1. Titik penjepitan

Cap dipasang di `KpiScoringService::scoreParameter()` — satu-satunya pintu yang
dilewati seluruh perhitungan per-parameter di backend. Sembilan pemanggil
(empat `calculateFormulaStream`, `accumulatedScore`, homepage, dua dashboard,
approval) ikut tanpa disentuh.

Cap **tidak** dipasang di `KpiFormulaHelper::evaluate()`. Itu penilai rumus
murni; menaruh kebijakan bisnis di sana membuat perintah verifikasi dan alat
diagnosa kehilangan akses ke angka mentah.

Yang dijepit adalah **rasio**, lalu achievement dan skor sama-sama diturunkan
dari rasio yang sudah dijepit:

```
rasio_mentah  = formula(aktual, target)
rasio         = clamp(rasio_mentah, min/100, max/100)

achievement   = rasio × 100
skor          = bobot × rasio
```

Menjepit achievement sendirian lalu menghitung skor dari rasio mentah akan
membuat keduanya tak sinkron, dan total berhenti sama dengan rata-rata baris
yang ditampilkan.

Kembalian `scoreParameter()` diperluas, nama kunci lama dipertahankan:

```php
[
    'achievement'     => 125.0,   // sudah dijepit
    'score'           => 62.5,    // sudah dijepit
    'achievement_raw' => 150.0,   // baru
    'score_raw'       => 75.0,    // baru
    'is_capped'       => true,    // baru
]
```

Pemanggil yang belum butuh angka mentah cukup mengabaikan kunci tambahan.

### 2. Konfigurasi

Dua kunci baru di `config/services.php`, terpisah dari cap total:

```php
'param_max_achievement_score' => (int) env('KPI_PARAM_MAX_ACHIEVEMENT_SCORE', 125),
'param_min_achievement_score' => (int) env('KPI_PARAM_MIN_ACHIEVEMENT_SCORE', 50),
```

Terpisah, bukan menumpang kunci lama, karena dua lapisan itu harus bisa disetel
sendiri-sendiri. Kalau menumpang, satu penyetelan akan menggeser keduanya
diam-diam.

Batas dinyatakan dalam satuan **achievement (persen)**, bukan skor. Batas dalam
satuan skor tidak bisa ditulis sebagai satu angka karena berbeda-beda menurut
bobot (bobot 50 → 62,5; bobot 20 → 25; bobot 10 → 12,5).

Endpoint `GET /api/v1/update-performance/kpi-cap-config` mengirim kedua pasang
batas — per-parameter dan total — agar frontend tidak perlu menebak.

### 3. Skema

`DDL-2026-045`. Tiga kolom pada **dua** tabel detail:
`KPI_Update_Performance_Detail` dan `KPI_Update_Performance_Detail_Sementara`.

| Kolom | Tipe | Null | Default | Isi |
|---|---|---|---|---|
| `Score_Raw` | sama dengan `Score` | ya | — | achievement sebelum dijepit |
| `Achievement_Raw` | sama dengan `Achievement` | ya | — | skor sebelum dijepit |
| `Flag_Capped` | `char(1)` | tidak | `'T'` | `Y` bila parameter ini dijepit |

Tipe `Score_Raw`/`Achievement_Raw` disamakan dengan kolom pasangannya, dibaca
dari skema hidup saat menulis DDL — jangan diasumsikan.

Nullable disengaja: baris lama tidak diisi, dan **kekosongan itulah pembeda
antara record beraturan lama dan baru** (lihat Bagian 4). Mengisinya dengan nol
akan menghapus pembeda itu dan menuliskan angka yang tidak pernah dihitung.

Header tidak bertambah kolom. `KPI_Update_Performance_Sementara` tidak disentuh:
ia hanya menyimpan total yang sudah dijepit, dan keterangan penjepitan di layar
peninjauan dihitung di sisi klien.

### 4. Arti baru kolom audit header

Kolom header tetap, sumber angkanya berpindah dari penjepitan total ke
penjumlahan kolom mentah per detail:

| Kolom | Arti baru |
|---|---|
| `Total_Score_Raw` | (Σ`Achievement_Raw` ÷ Σbobot) × 100 |
| `Total_Score_Achievement_Raw` | Σ`Achievement_Raw` |
| `Flag_Capped` | `'Y'` bila **ada minimal satu** parameter dijepit |
| `Cap_Min_Applied` / `Cap_Max_Applied` | batas **per-parameter** yang dipakai |

Konsekuensi yang diterima secara sadar: kolom-kolom ini menyandang dua arti —
pada record lama "totalnya pernah dipangkas", pada record baru "ada parameternya
yang dipangkas". Karena riwayat tidak dihitung ulang, ini tak terhindarkan.

Pembedanya gratis dan andal: **detail record lama punya `Score_Raw` kosong,
record baru terisi.** Tidak perlu kolom penanda tambahan.

Penulisan `Cap_Min_Applied`/`Cap_Max_Applied` saat ini tidak konsisten — jalur
Update Performance selalu mengisinya, jalur approval mengisi `null` ketika tidak
ter-cap. Diseragamkan menjadi **selalu diisi**, karena nilainya menjelaskan
aturan yang berlaku saat record dibuat, bukan sekadar jejak kejadian.

### 5. Record enforcement

Karyawan yang tidak mengisi dicatat dengan seluruh parameter nol. Dengan lantai
50% per parameter, tiap barisnya menjadi 50% — totalnya **tetap 50**, persis
seperti hari ini, tetapi barisnya dapat terbaca seolah ia mencapai separuh
target padahal tidak menyerahkan apa pun.

Perlakuan: baris tetap ditulis 50 mengikuti aturan (supaya total konsisten
dengan detailnya), tetapi `Score_Raw`/`Achievement_Raw` diisi **0** dan
`Flag_Capped` diisi `'Y'`. Angka nol yang sebenarnya tidak hilang, ia berpindah
ke kolom mentah, dan halaman enforcement — yang memang sudah menampilkan
pasangan capped/raw — memperlihatkan keduanya.

### 6. Halaman enforcement

Selain menampilkan angka baru, halaman ini menyimpan tiga cacat yang wajib
dibereskan justru karena cap berpindah ke wilayah yang ia kelola.

**a. Penjepit duplikat.** `KpiEnforcementService::applyAchievementCap()` adalah
salinan dengan default `150/0`, dan `KpiEnforcementController` mengirim `0/150`
ke panel pratinjau. Keduanya menyimpang dari 125/50 yang sebenarnya berlaku.
Selama config terisi penyimpangan itu tak terlihat; begitu tidak, halaman cap
justru yang paling awal menampilkan angka salah. Keduanya diarahkan ke
`KpiScoringService`.

**b. Label per parameter terbalik.** Di modal detail halaman ini, judul "Score"
menampilkan achievement dan judul "Achievement" menampilkan skor — kebalikan
dari halaman pengisian, dan kebalikan dari header modalnya sendiri yang justru
benar. Selama cap berada di total, itu hanya membingungkan. Setelah cap pindah
ke parameter, halaman inilah tempat orang memeriksa parameter mana yang
dipangkas, dan label terbalik akan membuat mereka membaca kolom yang salah.
Label diperbaiki mengikuti kosakata di dokumen ini. Nama kolom DB dan kunci
payload tidak diubah.

**c. Nilai mentah per parameter belum punya tempat di tampilan.** Ditambahkan,
beserta penanda parameter mana yang dijepit.

### 7. Frontend

Rumus per parameter dihitung ulang di JavaScript pada **lima berkas**, tujuh
titik: `updatePerformance.vue` (pengisian personal), `team/updatePerformance.vue`,
`team/updatePerformanceByPass.vue`, `Approval/Performance/performanceApproval.vue`,
`Approval/Verification/VerifikasiPerformancePanel.vue`.

Semuanya harus menjepit dengan aturan yang sama. Kalau tidak, layar dan server
menampilkan angka berbeda untuk data yang sama — dan karena penjepitan kini
mengubah nilai akhir (bukan lagi hanya angka paling ujung), selisihnya akan
terlihat oleh setiap pengisi.

Ketiga halaman pengisian juga memuat batas 125/50 sebagai nilai cadangan keras.
Cadangan itu tetap boleh ada, tetapi harus mengikuti batas **per-parameter**.

Keterangan penjepitan di kartu ringkasan berubah dari kalimat tentang total
menjadi kalimat tentang parameter: sebutkan berapa parameter yang dijepit dan
berapa total tanpa penjepitan.

### 8. Surface yang ikut otomatis

Ikut lewat `KpiScoringService`, tanpa perubahan logika, tetapi wajib
diverifikasi:

- `StaffPerformanceDashboardController` — 5 titik
- `DashboardCeoController` — 4 titik
- `HomepageKpiService` — lewat `accumulatedScore()`
- `KpiExportService` dan `resources/views/exports/kpi-performance-pdf.blade.php`
  — kolom `capped` berubah arti mengikuti Bagian 4

Catatan khusus YTD: `accumulatedScore()` menjumlahkan target dan aktual
sepanjang rentang lalu mengevaluasi rumus **satu kali**, sehingga cap mengenai
rasio akumulasi, bukan tiap bulan. Ini konsisten dengan metode YTD yang berlaku,
tetapi harus dinyatakan di kode agar selisihnya terhadap penjumlahan skor
bulanan tidak dibaca sebagai cacat.

`VerifyKpiYtd` memanggil `KpiFormulaHelper::evaluate()` langsung sehingga
melewati cap. Untuk perintah verifikasi itu benar — tugasnya membandingkan,
bukan menilai — dan diberi catatan tegas di kode.

## Yang tidak berubah

Dinyatakan eksplisit supaya tidak ada yang "dirapikan" tanpa sengaja.

- **Gerbang PICA.** Wajib PICA bila achievement < 100. Karena batas cap
  mengapit 100 (50 dan 125), tidak ada satu pun keputusan PICA yang berubah:
  20% menjadi 50% (tetap di bawah), 300% menjadi 125% (tetap di atas).
- **Kelayakan enforcement.** Siapa yang dianggap belum mengisi, dan sifat
  idempotennya, tidak tersentuh.
- **Pencarian band performa.** Tetap `floor(total setelah cap)` lalu band
  tertinggi yang `Range_Awal`-nya tidak melebihi skor.
- **`KpiCapBackfillService`.** Tetap apa adanya; ia masih sah untuk record
  beraturan lama yang memang tidak dihitung ulang.
- **Nama kolom DB dan kunci payload API.** Hanya label layar yang diperbaiki.
- **Seluruh record yang sudah final** dan tidak dipicu perhitungan ulang.

## Pengujian

Saat ini **tidak ada satu pun tes** yang menyentuh cap. Perubahan ini menyentuh
mesin skor yang dipakai enam surface, jadi tes dibangun lebih dulu, bukan
sesudahnya.

**Unit — `KpiScoringService`**

- rasio di atas batas atas dijepit; achievement dan skor sama-sama turun
- rasio di bawah batas bawah dijepit
- rasio tepat di batas tidak ditandai ter-cap
- rasio di dalam rentang lewat tanpa perubahan, `is_capped` bernilai salah
- rumus mengembalikan nilai negatif → dijepit ke batas bawah, bukan melempar
- rumus kosong → nol, lalu dijepit ke batas bawah
- `achievement_raw`/`score_raw` selalu memuat angka sebelum penjepitan

**Rantai achievement → skor → total**

- kasus acuan: bobot 50 : 50, parameter A 300% dan B 20% → total **87,5**,
  bukan 125
- total selalu sama dengan rata-rata tertimbang achievement baris yang tampil
- Σbobot ≠ 100 (ada parameter yang belum waktunya dinilai) tetap benar

**Regresi yang harus terlihat merah lebih dulu**

- keputusan PICA tidak berubah untuk achievement 20% dan 300%
- enforcement menulis 50 pada `Score` dan 0 pada `Score_Raw`, `Flag_Capped='Y'`
- record dengan `Score_Raw` kosong (beraturan lama) tidak berubah nilainya
- header `Flag_Capped` menyala karena ada detail ter-cap, bukan karena total

**Verifikasi manual sebelum dianggap selesai**

- satu record diisi lewat halaman personal, satu lewat team, satu lewat by-pass;
  angka layar dan angka tersimpan harus sama
- approver mengedit nilai aktual pada record menunggu → total dihitung ulang
  dengan aturan baru dan tetap konsisten dengan detailnya
- halaman enforcement menampilkan pasangan capped/raw dengan label yang benar

## Urutan pengerjaan

Cap dipasang paling akhir. Sebelum itu seluruh jalur harus sudah mampu
menyimpan dan menampilkan angka mentah — kalau dibalik, ada jendela waktu ketika
angka asli sudah hilang tetapi belum ada tempat menyimpannya.

1. Tes untuk perilaku yang berlaku sekarang, sebagai garis dasar
2. DDL kolom mentah pada dua tabel detail, dijalankan di pengembangan
3. Perluasan kembalian `KpiScoringService` **tanpa menjepit** — angka mentah
   mulai ditulis, nilai akhir belum berubah sama sekali
4. Seluruh jalur penulis detail mengisi kolom mentah
5. Arti baru kolom audit header
6. Frontend: penjepitan di lima berkas, batas dari config, keterangan baru
7. Halaman enforcement: penjepit duplikat, label terbalik, tampilan mentah
8. **Penjepitan dinyalakan** di `scoreParameter()`
9. Verifikasi manual pada tiga jalur pengisian dan jalur approval

Langkah 3 sampai 7 tidak mengubah nilai apa pun bagi pengguna. Seluruh perubahan
nilai terjadi di langkah 8, dan itu satu-satunya langkah yang perlu dijadwalkan
bersama pemangku kepentingan KPI.

## Risiko

| Risiko | Penanganan |
|---|---|
| Nilai setiap karyawan berubah begitu langkah 8 menyala | Langkah 8 dipisah sendiri dan dijadwalkan; batas di config sehingga bisa dikembalikan tanpa rilis ulang |
| Frontend dan backend menjepit berbeda | Batas hanya dari `kpi-cap-config`; nilai cadangan keras disamakan; diuji dengan membandingkan angka layar dan angka tersimpan |
| Record menunggu approval terhitung separuh aturan lama separuh baru | Diterima secara sadar sebagai turunan keputusan 3; setiap perhitungan ulang memakai aturan yang berlaku saat itu dan menulis ulang seluruh detail record tersebut, bukan sebagian |
| Kolom audit header bermakna ganda | Dibedakan lewat kekosongan `Score_Raw` pada detail; dinyatakan di dokumentasi kolom |
| Laporan lintas periode memuat dua aturan | Periode mulai berlaku dicatat di dokumentasi perubahan agar tidak dibaca sebagai anomali |

## Basis verifikasi database

Diisi saat implementasi, sesuai `AGENTS.md`:

- Lingkungan: `Web_HRIS` (pengembangan) pada host team311
- Grup fitur: `KPI` dari `N_HRIS_Master_Grup_Fitur`
- Pemetaan halaman: `N_HRIS_Grup_Fitur_Map`
- Tanggal verifikasi: diisi saat DDL dibuat

Tabel yang disentuh sudah ada dan bukan tabel baru, sehingga konvensi penamaan
tabel baru tidak berlaku. Kolom baru mengikuti konvensi kolom proyek
(`Flag_<Makna>` bertipe `char(1)` berisi `Y`/`T`).
