# Dari mana `Nilai_KPI` diambil untuk Performance Appraisal — temuan terukur

Ditelusuri 1 September 2026 pada `Web_HRIS` development. Semua angka di bawah dibaca
langsung dari database, bukan dari ingatan atau dokumentasi.

**Peringatan kecukupan data:** `KPI_Update_Performance` hanya berisi 116 baris, dan
hanya **7 dari 958 karyawan aktif** yang punya baris KPI berstatus disetujui. Jadi
temuan bentuk di bawah dapat dipercaya, tetapi sebaran nilainya belum tentu mewakili
produksi.

---

## 1. Tabelnya sudah ketemu

`KPI_Update_Performance` (header, 116 baris) dan `KPI_Update_Performance_Detail`
(305 baris, satu baris per butir KPI).

Himpunan yang tampaknya resmi: `Status_Pengajuan = 'disetujui'` **dan**
`Flag_Aktif = 'Y'` — **17 baris di 6 periode**. Sisanya menunggu, ditolak, atau
sudah dinonaktifkan.

---

## 2. Butirannya BULANAN — dan metodenya sudah diputuskan

Kolom `Periode` berformat `YYYY-MM`: `2026-01` sampai `2026-08`. PA wajib menggabungkan
beberapa bulan menjadi satu angka siklus.

**Ditetapkan user: pakai metode YTD, mengikuti rentang periode siklus PA.**

Kabar baiknya, metode itu **sudah ada dan tidak perlu ditulis ulang**:
`KpiScoringService::accumulatedScore()`. Docblock-nya menyebut dirinya "metode kanonik
untuk dashboard, dan (per keputusan) juga untuk YTD homepage/leader" — jadi memakainya
otomatis membuat PA sepakat dengan angka yang dilihat karyawan di layar KPI.

Cara kerjanya: menjumlahkan target dan actual **per parameter** sepanjang rentang
periode, mengevaluasi rumus atas jumlah itu, menormalkan `Σweighted ÷ Σbobot × 100`,
lalu men-cap. Perhatikan bahwa ia menjumlahkan dulu baru menghitung — **bukan**
merata-ratakan skor bulanan. Keduanya menghasilkan angka berbeda, dan yang benar adalah
yang pertama.

**Konsekuensi ke rancangan:** PA tidak membaca kolom nilai bulanan mana pun. PA memanggil
service ini dengan rentang periode siklusnya. Jadi pertanyaan "kolom mana yang dipakai"
sebetulnya larut dengan sendirinya — yang disimpan `N_PA_Result` adalah **hasil panggilan
service**, bukan salinan kolom.

---

## 3. Ada EMPAT kolom nilai, dan ketiganya tidak sepakat

| Kolom | Rentang terukur (baris aktif) |
|---|---|
| `Total_Score` | 50 – 125 |
| `Total_Score_Raw` | 15 – 177,75 |
| `Total_Score_Achievement` | 7,5 – 177,75 |
| `Total_Score_Achievement_Raw` | mengikuti pasangannya |

Dugaan wajar adalah `Achievement = Score × Bobot ÷ 100`. **Diuji, dan tidak berlaku.**
Dari 26 baris yang ter-cap (`Flag_Capped = 'Y'`):

- **7 baris** nilainya mengikuti `Total_Score` yang sudah di-cap,
- **14 baris** mengikuti `Total_Score_Raw` yang belum di-cap,
- **5 baris** tidak mengikuti keduanya.

Jadi **tidak ada satu aturan** yang menghubungkan keempat kolom itu. Memilih salah satu
tanpa memeriksa berarti memilih angka yang tidak bisa dipertanggungjawabkan saat
ditanya.

---

## 4. Temuan yang paling berbahaya: skala kedua pilar tidak sama

Cap KPI terbaca langsung dari data: `Cap_Min_Applied = 50`, `Cap_Max_Applied = 125`.
Maka **nilai KPI bisa melebihi 100** — terukur sampai 125 pada kolom ter-cap, dan
177,75 pada kolom mentah.

Sementara itu sisi kompetensi PA dinormalkan ke **0–100**.

Rumus final PA adalah `KPI × bobot + kompetensi × bobot`. Kalau satu pilar berjalan
0–125 dan pilar lain 0–100, maka:

- orang dengan KPI 125 dan kompetensi 100 tidak mendapat 100, melainkan lebih;
- pita grade yang dibuat untuk 0–100 tidak lagi punya arti;
- kuadran HAV Map bergeser, karena sumbu KPI-nya lebih panjang daripada sumbu
  kompetensi.

Ini bukan soal selera. Ini akan menghasilkan angka yang tampak wajar tetapi salah, dan
baru ketahuan ketika ada orang berprestasi tinggi yang grade-nya terasa aneh.

**Tiga jalan keluar, dan pilihannya keputusan bisnis:**

1. **Potong KPI di 100** untuk keperluan PA — sederhana, tetapi menghapus penghargaan
   atas pencapaian di atas target.
2. **Naikkan skala kompetensi ke 125** supaya sepadan — konsisten secara aritmetika,
   tetapi aneh dijelaskan ke penilai.
3. **Normalkan KPI ke 0–100** dengan membagi 125 — mempertahankan urutan peringkat,
   tetapi mengubah arti angka yang selama ini dilihat karyawan.

---

## 5. `Total_Bobot` — kekhawatiran saya keliru, ini koreksinya

**Ditetapkan user: `Total_Bobot` selalu 100.** Saya sempat menyimpulkan bahwa bobot yang
bukan 100 membuat nilai tidak bisa dibandingkan antar baris. **Itu salah**, dan
pembacaan `KpiScoringService::accumulatedScore()` membuktikannya:

```php
$rawPct   = $totalBobot > 0 ? ($sumWeighted / $totalBobot) * 100 : 0;
$finalPct = $cap ? $this->capValue($rawPct) : $rawPct;
```

Perhitungannya **sudah membagi dengan Σbobot**, jadi hasilnya persentase yang setara
berapa pun bobot totalnya. Tidak ada masalah pembandingan.

Yang tetap perlu diperiksa adalah **datanya**, bukan aturannya: di development ada
baris berbobot 50 dan 40, dan tiga di antaranya (`#128`, `#131`, `#132`, bertanggal
26–28 Agustus 2026) berstatus `disetujui` sekaligus `Flag_Aktif = 'Y'` — artinya masuk
himpunan resmi. Kemungkinan besar itu sisa uji coba yang baru dibuat, tetapi selama masih
ada, penjaga di PA sebaiknya menolak menghitung siklus bila menemukan bobot bukan 100
alih-alih diam-diam memakainya.

---

## 6. Banyak orang mungkin tidak punya KPI sama sekali

Di development hanya 7 dari 958 karyawan aktif yang punya baris KPI disetujui. Bahkan
bila produksi jauh lebih ramai, kasus "karyawan tanpa KPI" pasti ada.

Rancangan PA sudah menyiapkan jalannya — `N_PA_Participant_Pillar_Override` memungkinkan
bobot 0/100 untuk orang tanpa KPI, dan itu sudah diuji. Yang belum ada adalah
**aturannya**: siapa yang memutuskan seseorang tidak punya KPI, apakah otomatis dari
ketiadaan baris, dan apa bedanya "tidak punya KPI" dengan "KPI-nya belum disetujui".

---

## 7. Yang perlu diputuskan sebelum `N_PA_Result` dibangun

1. ~~**Kolom mana** yang menjadi nilai KPI.~~ **Larut** — PA memanggil
   `KpiScoringService::accumulatedScore()`, tidak membaca kolom bulanan.
2. ~~**Metode penggabungan.**~~ **Diputuskan user: YTD**, mengikuti rentang periode
   siklus PA.
3. **Perlakuan skala supaya kedua pilar sepadan — MASIH TERBUKA.** Lihat bagian 4.
   Ada opsi keempat yang baru terlihat: `accumulatedScore()` menerima `opts['cap']`, jadi
   PA bisa meminta persentase tanpa cap. Rapi secara aritmetika, tetapi membuat PA
   memakai angka yang berbeda dari yang dilihat karyawan di layar KPI — pertukaran yang
   harus disadari, bukan dipilih diam-diam.
4. **Status mana yang dihitung**: hanya `disetujui`, atau `menunggu` ikut. Masih terbuka.
5. **Perlakuan orang tanpa KPI** dan bedanya dengan yang KPI-nya belum disetujui. Masih
   terbuka.

Konsekuensi ke skema: `N_PA_Result` sebaiknya menyimpan **metode dan rentang periode
yang dipakai**, bukan hanya angkanya. Draf saat ini sudah punya `KPI_Periode_Dari` dan
`KPI_Periode_Sampai`; perlu ditambah penanda metode dan kolom nilai sumber, supaya hasil
lama tetap bisa dijelaskan setelah metodenya kelak diubah.
