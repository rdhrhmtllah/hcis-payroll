# Grade Performance Appraisal dari Kurva Normal — Rancangan

Tanggal: 18 September 2026 · Modul: KPI / Performance Appraisal · Status: diimplementasikan (r2, 19 September 2026 — lihat §11)

## 1. Ringkasan

Hari ini grade PA lahir dari **pita nilai tetap** di master (`N_PA_Score_Range` baris
`FINAL|GRADE`, disalin ke `N_PA_Cycle_Score_Range_Snapshot`): nilai akhir 80–90 selalu B+,
berapa pun sebaran siklusnya. Itu salah secara kebijakan — grade harus **relatif terhadap
sebaran siklus itu**, sehingga batasnya berbeda tiap siklus.

Rancangan ini mengganti asal grade menjadi **z-score per grup level**: tiap grade punya batas
bawah dalam satuan simpangan baku; pada saat admin **mengunci kurva**, sistem menghitung rata-rata
(μ) dan simpangan baku (σ) tiap grup dari hasil siklus, menurunkan pita nilai absolut
(μ + z·σ), dan membekukannya. Mesin grade yang sudah ada (cocokkan nilai ke pita), kalibrasi
forum, dan penerbitan tetap dipakai.

Rujukan cara kerja HC hari ini: `Dummy Data_ Normal Curve.xlsx` (47 orang, nilai akhir = 75% KPI
+ 25% kompetensi, grade A/B+/B−/C dari μ±σ). Analisisnya: model z-score, bukan kuota; batas di
rumus `IF` adalah μ±σ yang dibulatkan dan **diketik manual** — sumber kesalahan yang rancangan
ini hilangkan. Seluruh 47 grade Excel direproduksi tanpa selisih.

## 2. Keputusan pemilik proyek (18 September 2026)

| # | Keputusan |
|---|---|
| G1 | Model **z-score** (μ, σ), bukan kuota persentase. Kurva tidak dipaksa berbentuk lonceng. |
| G2 | Batas z dan jumlah grade **diatur di master**, lalu dibekukan per siklus. |
| G3 | μ dan σ dihitung **per grup level**, bukan seluruh siklus. |
| G4 | Grup level diatur admin lewat **master baru Grup Level Kurva** (mis. Asisten Manager digabung Senior Officer) supaya tidak ada grup yang terlalu kecil. |
| G5 | Grup di bawah ambang (30) → **peringatan**, kurva tetap bisa dikunci. |
| G6 | Kurva ditetapkan saat admin menekan **Kunci kurva**. Sebelumnya grade hanya sementara; forum kalibrasi dan penerbitan baru bisa sesudahnya. |
| G7 | Pendekatan **A**: kurva menghasilkan pita absolut per grup, disimpan beku per siklus. |
| G8 | Isi awal master: **6 grade, tiap grade selebar 1σ, μ = batas bawah B** — A ≥ +2 · B+ ≥ +1 · B ≥ 0 · C+ ≥ −1 · C ≥ −2 · D di bawahnya. |

Bawaan yang ditetapkan dalam rancangan dan boleh diubah: populasi kurva = hasil `DIHITUNG`
saja; σ sampel (setara `STDEV` Excel); nilai di atas 100% dipakai apa adanya; hasil yang dihitung
sesudah kunci memakai pita beku; buka kunci wajib alasan dan ditolak bila sudah ada yang terbit;
siklus lama yang sudah terbit tidak disentuh.

## 3. Dasar registry

KPI / Performance Appraisal. Semua berkas di bawah `app/Services/KPI/PerformanceAppraisal/`,
`resources/js/Pages/KPI/PerformanceAppraisal/`, `routes/KPI/PerformanceAppraisal/`. Halaman
master baru mengikuti aturan pendaftaran menu (`Jenis_page` + `N_HRIS_Grup_Fitur_Map` + akses,
lalu `flushMenuCache`).

## 4. Data

### 4.1 Master

- **`N_PA_Score_Range.Batas_Z decimal(5,2) NULL`** — batas bawah z untuk baris `FINAL|GRADE`;
  NULL hanya untuk grade terendah. `Nilai_Dari/Nilai_Sampai` tidak lagi dipakai untuk grade
  kurva (tetap dipakai HAV map dan siklus bermetode `PITA`).
- **`N_PA_Curve_Group`** — `Id_PA_Curve_Group`, `Kode_Grup` (unik aktif), `Nama_Grup`,
  `Urutan_Tampil`, `Flag_Aktif`, jejak Created/Updated.
- **`N_PA_Curve_Group_Level`** — `PA_Curve_Group_Id` FK, `ID_Level`; indeks unik tersaring:
  satu level di satu grup aktif. Level yang tidak terpetakan menjadi grupnya sendiri saat
  disalin ke siklus, dan ditandai peringatan.

### 4.2 Salinan siklus

- **`N_PA_Cycle.Metode_Grade char(5) NOT NULL DEFAULT 'KURVA'`** (`PITA` | `KURVA`), dijaga FK
  ke kamus kode atau CHECK sesuai preseden kolom kode proyek.
- **`N_PA_Cycle_Score_Range_Snapshot.Batas_Z`** — ikut disalin dari master.
- **`N_PA_Cycle_Curve_Group`** + **`N_PA_Cycle_Curve_Group_Level`** — salinan grup saat
  penyiapan siklus; boleh disunting selama kurva belum dikunci.

### 4.3 Hasil kunci

- **`N_PA_Cycle_Curve`** — satu baris per grup per penguncian: `PA_Cycle_Id`,
  `PA_Cycle_Curve_Group_Id`, `Jumlah_Peserta`, `Rata_Rata`, `Simpangan_Baku`, `Flag_Aktif`,
  `Locked_At/By`, `Unlocked_At/By`, `Alasan_Buka`. Hanya ditambah dan dinonaktifkan, tidak
  pernah dihapus — riwayat penguncian utuh.
- **`N_PA_Cycle_Curve_Band`** — pita absolut hasil kurva: `PA_Cycle_Curve_Id`, `Kode_Range`,
  `Nama_Range`, `Batas_Z`, `Nilai_Dari`, `Nilai_Sampai` (atas eksklusif, sama dengan pita lama).
- **`N_PA_Result.PA_Cycle_Curve_Id int NULL`** — kurva yang memberi grade hasil itu (audit).

Artefak: satu DDL baru (**DDL-2026-120**) untuk seluruh objek di atas, satu DML
(**DML-2026-125**) untuk: `Metode_Grade = 'PITA'` pada siklus yang sudah ada, isi awal `Batas_Z`
enam grade (G8), dan pendaftaran menu halaman Grup Level Kurva. Grup level awal **tidak**
diisi DML — itu keputusan bisnis per organisasi, diisi admin lewat layar.

## 5. Perhitungan dan penguncian

1. **Sebelum kunci** (siklus `KURVA`): `hitung()` mengisi nilai akhir seperti biasa;
   `Kode_Grade_Sistem`/`Kode_Grade_Final` dibiarkan NULL. Layar admin menampilkan
   **grade sementara** yang dihitung langsung dari μ dan σ grup yang sedang berjalan, tidak
   disimpan.
2. **Kunci kurva** — operasi gerbang baru `KUNCI_KURVA` (**`CLOSED` saja**, r2), satu transaksi:
   populasi per grup = hasil `DIHITUNG` (tanpa `TERTAHAN` dan peserta dikecualikan); catat n,
   μ, σ sampel; turunkan pita dari `Batas_Z` salinan; beri grade sistem & final seluruh hasil
   `DIHITUNG` secara set-based, set `PA_Cycle_Curve_Id`. Peringatan (tidak memblok): grup < 30,
   grup σ = 0 atau n < 2 (semua anggotanya mendapat grade yang rentangnya memuat z = 0), hasil
   masih `TERTAHAN`.
3. **Sesudah kunci**: hitung ulang dan hasil susulan memakai pita beku grupnya — tidak mengubah
   μ dan σ; layar menyebut jumlah hasil yang dihitung sesudah kunci.
4. **Gerbang** `KALIBRASI` dan `TERBITKAN` pada siklus `KURVA` menuntut kurva terkunci aktif.
   Grade forum tetap di `Kode_Grade_Final`, di atas grade sistem kurva.
5. **Buka kunci** — `BUKA_KURVA`: wajib alasan; **ditolak bila sudah ada satu hasil `TERBIT`**.
   Keputusan forum dipertahankan dan tertandai lewat `kalibrasi_perlu_ditinjau`.
6. **Siklus `PITA`**: jalur lama, tidak berubah.
7. **`pa:verify`**: pita beku = μ + z·σ per grup; grade tiap hasil cocok dengan pita grupnya;
   menyebut grup kecil dan hasil sesudah kunci.

## 6. Layar

1. **Master Pita Nilai — tab Grade**: kolom Batas z (≥), pratinjau pita dengan contoh μ/σ;
   validasi z turun berurutan dan tepat satu grade tanpa batas.
2. **Master baru Grup Level Kurva**: daftar grup dan level; level tak terpetakan disorot;
   bantuan cacah peserta per level di siklus terakhir.
3. **Distribusi** menjadi tempat kunci: kartu per grup (n, μ, σ, histogram + kurva, garis batas
   grade, cacah per grade vs proporsi teoretis), status Sementara/Terkunci, pengaturan grup
   salinan selama belum dikunci, tombol Kunci kurva (dialog ringkasan + peringatan) dan Buka
   kunci (alasan; mati bila sudah ada yang terbit).
4. **Pemantauan / detail hasil**: lencana "sementara" sebelum kunci; detail menyebut asal
   grade (grup, μ, σ, pita). Layar karyawan dan leader tidak berubah.

## 7. Transisi

- DML menandai semua siklus yang ada `PITA` — TW I/TW II terbit tidak berubah sedikit pun.
- Siklus baru `KURVA` (bawaan kolom).
- Siklus berjalan (PA-2026-11) boleh dipindah admin ke `KURVA` selama belum ada hasil terbit;
  `Batas_Z` disalin dari master saat pindah.

## 8. Pengujian

- Reproduksi Excel: 47 nilai dummy, batas Excel (A +1, B+ 0, B− −1) → grade identik.
- Reproduksi G8 pada data yang sama: A 1, B+ 7, B 12, C+ 24, C 3, D 0.
- Per grup: dua grup dengan sebaran berbeda menghasilkan pita berbeda.
- Kunci → hitung ulang tidak menggeser μ/σ; hasil susulan memakai pita beku.
- Buka kunci ditolak setelah ada terbit; wajib alasan.
- Gerbang kalibrasi/terbit menolak siklus `KURVA` yang belum dikunci; siklus `PITA` tetap lolos.
- σ = 0 / n < 2 → grade rentang z = 0 + peringatan.
- Siklus `PITA` tidak tersentuh (hasil identik sebelum/sesudah).
- `pa:verify` menemukan pita yang disunting tangan.

## 9. Di luar cakupan

- Kuota persentase (forced distribution).
- Grade kurva untuk HAV map.
- Menghitung ulang grade siklus lama yang sudah terbit.

## 10. Risiko

- **Data condong**: z-score mengikuti sebaran apa adanya; pada data dummy 51% jatuh di C+.
  Kebijakan diatur lewat batas z di master, bukan kode.
- **Grade A langka (±2%)** pada G8, dan praktis hanya tercapai bila KPI > 100%.
- **Grup kecil di dev**: PA-2026-11 punya Senior Staff 4 dan Senior Officer 2 peserta —
  grup level wajib diatur sebelum kunci.

## 11. Revisi r2 — 19 September 2026 (sesudah implementasi)

Selisih antara rancangan di atas dan yang dibangun, plus celah yang ditemukan saat menelusuri
alur satu siklus dari awal sampai terbit.

1. **Transisi tanpa DML penanda.** `N_PA_Cycle.Metode_Grade` ber-DEFAULT `'PITA'`, sehingga
   seluruh siklus lama otomatis `PITA`; siklus yang dibuat lewat aplikasi (`PaCycleSetupService`)
   ditulis `'KURVA'` secara eksplisit. Kalimat "Siklus baru `KURVA` (bawaan kolom)" di §7 tidak
   berlaku.
2. **Kunci hanya saat `CLOSED`.** Semula `OPEN, CLOSED`. Kurva satu-satunya tempat nilai seseorang
   bergantung pada orang lain; mengunci selagi pengisian terbuka membekukan kurva dari sebagian
   peserta. `BUKA_KURVA` tetap `OPEN, CLOSED`. Pratinjau mengirim `boleh_kunci` supaya tombol
   mati alih-alih menunggu 422.
3. **Grup kosong tidak dikunci.** Semula setiap grup mendapat kurva, termasuk yang nol orang —
   kurva datar yang memberi siapa pun yang menyusul grade B otomatis. Sekarang grup kosong
   dilewati; kunci ditolak bila belum ada satu pun hasil `DIHITUNG`.
4. **Peserta tanpa kurva beku DITAHAN, bukan dilempar.** Hasil yang dihitung sesudah kunci untuk
   peserta yang grupnya tanpa kurva (atau levelnya tanpa grup) menjadi `TERTAHAN` dengan alasan
   yang menyebut nilai akhirnya dan jalan keluarnya (buka lalu kunci ulang). Semula
   `RuntimeException`, yang menggagalkan penghitungan massal seluruh siklus karena satu orang.
   `gradeBeku()` kini mengembalikan `{kode, curve_id, alasan}`.
5. **Grade sementara sampai ke layar.** §6.4 menjanjikannya, implementasi pertama hanya menulis
   "Menunggu kurva". Kini `PaKurvaService::gradeSementara()` (hanya dibaca, tidak disimpan)
   mengisi `Grade_Sementara` di daftar peserta dan detail hasil, serta sebaran grade Distribusi
   (`grade_sementara: true`, `metode_grade`). Keputusan forum yang sudah ada tetap didahulukan.
6. **Pembanding sebaran siklus KURVA = proporsi teoretis** dari batas z (G8: 2,28 · 13,59 ·
   34,13 · 34,13 · 13,59 · 2,28 %), bukan `Target_Persen` master pita. `Target_Persen` ditandai
   "tidak dipakai metode kurva" di master.
7. **Detail kontrak**: `PaGrupKurvaMasterService::periksa()` publik (dipakai pengaturan grup per
   siklus); `cacah_level` berbentuk daftar `{level_id, jumlah}` karena peta berkunci data tidak
   bisa dijadikan kontrak bentuk; grade baru di master wajib membawa `batas_z`.
8. **μ dan σ dibulatkan 4 desimal SEBELUM pita diturunkan** (kunci, pratinjau, grade
   sementara). Uji ujung ke ujung di PA-2026-11 menemukan `pa:verify` menghalangi kurva yang
   baru dikunci: pita dari μ/σ penuh (C_PLUS 72,7444) berbeda 0,0001 dari pita yang diturunkan
   ulang dari μ/σ tersimpan (72,7443), sehingga terbaca "disunting tangan".
9. **`Metode_Grade` bertipe `char(5)`**: `'PITA'` terbaca `'PITA '`. Semua pembaca memangkasnya
   (`PaKurvaService::metode()`, konteks perhitungan, `pa:verify`); tanpa itu panel kurva tidak
   pernah mengenali siklus PITA dan tombol "Pindah ke kurva" tidak muncul.
10. **Sengaja tidak diubah**: hitung ulang massal sesudah kunci TIDAK menyegarkan kurva — hasilnya
   memakai pita beku (pendekatan A). Kurva yang perlu disegarkan dibuka (beralasan) lalu dikunci
   ulang, dan itu hanya mungkin sebelum ada yang terbit.
