# Angka acuan PA hasil ekstraksi deck PIC — 31 Agustus 2026

Sumber: `Send to IT PERFORMANCE APPRAISAL EVO GROUP 2026.pptx`, 23 slide. Sebagian besar
angka berada di **gambar screenshot UI**, bukan teks slide, jadi ekstraksinya menggabungkan
pembacaan XML dan pembacaan gambar. Dokumen ini menyalin apa adanya — termasuk yang saling
bertentangan — supaya keputusan diambil sadar, bukan diam-diam.

## 1. Bobot pilar: KPI vs 360° (slide 19)

| Level di deck | KPI | 360° |
|---|---:|---:|
| Staff | 80 | 20 |
| SPV / Section Head | 70 | 30 |
| Manager | 65 | 35 |
| Senior Manager / GM | 55 | 45 |

Empat pita, sementara `HRIS_Level` punya 16 baris. Nama "SPV / Section Head" **tidak ada**
di master level.

## 2. Matriks penilai per level (slide 4) — 12 baris

| Level di deck | Atasan 1 | Self | Rekan | Bawahan |
|---|---:|---:|---:|---:|
| Pramukarya | 100 | – | – | – |
| Staff | 80 | 20 | – | – |
| Sr. Staff | 80 | 20 | – | – |
| Officer (Specialist) | 60 | 15 | 25 | – |
| Officer | 55 | 10 | 10 | 25 |
| Sr Officer | 55 | 10 | 10 | 25 |
| Assisstant Manager | 50 | 10 | 10 | 30 |
| Sr. Assisstant Manager | 50 | 10 | 10 | 30 |
| Manager | 45 | 10 | 15 | 30 |
| Sr. Manager | 45 | 10 | 15 | 30 |
| General Manager | 40 | 10 | 20 | 30 |
| Sr. General Manager | 40 | 10 | 20 | 30 |

Kolom "Atasan 2" tertulis *adjustable* — ini persis `Bobot_Dalam_Kategori` di
`N_PA_Assignment` (K18), bukan kategori tersendiri.

## 3. Konflik: slide 18 memberi angka lain

Slide 18 "Referensi Bobot per Level" menyajikan pembagian penilai yang **berbeda**:

| Kelompok | Self | Atasan | Peer | Bawahan |
|---|---:|---:|---:|---:|
| Managerial | 10 | 40 | 25 | 25 |
| Specialist | 20 | 50 | 30 | – |
| Staff | 30 | 70 | – | – |

Bertentangan dengan slide 4 pada hampir semua baris — Manager di slide 4 memberi Atasan 45,
di slide 18 memberi 40; Staff di slide 4 memberi Self 20, di slide 18 memberi 30.

## 4. Pita grade — TIDAK ADA di deck

Tidak ditemukan satu pun tabel batas bawah/atas grade. Kode grade yang muncul (A, B+, B, C+)
hanya berasal dari **data contoh** di screenshot, bukan definisi ambang. Tidak ada target
distribusi paksa di mana pun; slide 21 hanya menampilkan jumlah orang per kuadran pada
contoh, bukan target persen.

Konsekuensi: **`N_PA_Score_Range` tidak bisa diisi dari deck.** Ini bagian dari T05, bukan
sekadar belum sempat disalin.

## 5. Skala penilaian — bertentangan di dalam deck sendiri

- Skala 1–5: slide 20 (4.6 / 4.5), slide 13 (radar sumbu 0–5, Final Score 4.05).
- Skala 0–100: slide 13 gambar lain (78% / 70% / 75%), slide 23 (83% / 86% / 89% / 91%).

Keduanya dipakai untuk konsep yang sama, "Nilai Akhir". Ini menegaskan T05 memang terbuka,
dan membenarkan keputusan menyimpan `Skor_Maksimal` per butir alih-alih mengasumsikannya.

## 6. Jenis kompetensi per kelompok (slide 9)

| Dimensi | Pramukarya | Staff | Leaders |
|---|:---:|:---:|:---:|
| Kompetensi Manajerial | – | ✔ | ✔ |
| Core Value | – | ✔ | ✔ |
| Behavior (Core Value) | ✔ | – | – |
| Qualitative Question | – | – | ✔ |

Tiga kelompok, bukan 16 level. "Leaders" belum didefinisikan mulai level berapa.

## 7. HAV Map (slide 21)

Score_Type Y = skor KPI, sumbu X = skor kompetensi.

| Performa × Kompetensi | Nama |
|---|---|
| Tinggi × Tinggi | Striving Star |
| Tinggi × Sedang | Future Star |
| Sedang × Tinggi | Future Star |
| Sedang × Sedang | Potential Candidate |
| Tinggi × Rendah | Top Performer |
| Sedang × Rendah | Contributor |
| Rendah × Tinggi | Raw Diamond |
| Rendah × Sedang | Problem Employee |
| Rendah × Rendah | Deadwood |

"Future Star" dipakai untuk **dua** kuadran berbeda. Disalin apa adanya; perlu dicocokkan
dengan sembilan baris yang sudah ada di `N_PA_Hav_Quadrant`.

## 8. Apa yang bisa dan tidak bisa diisi sekarang

| Tabel | Bisa diisi? | Penghalang |
|---|---|---|
| `N_PA_Rater_Policy` + `_Detail` | ya, setelah konflik slide 4 vs 18 diputus | keputusan |
| `N_PA_Pillar_Weight` | ya, setelah pemetaan 4 pita → 16 level | keputusan |
| `N_PA_Competency_Requirement` | ya, setelah "Leaders" didefinisikan | keputusan |
| `N_PA_Score_Range` | **tidak** | ambang grade tidak ada di deck (T05) |
| `N_PA_Competency_Kode_Soal` | **tidak** | menunggu daftar kode soal HC Learn |
| Ketiga `N_PA_Cycle_*_Snapshot` | tidak diisi manual | lahir saat siklus disiapkan |
