# PA — Balancing berulang sesudah Calculate

- Tanggal: 30 September 2026
- Status: DISETUJUI pemilik proyek, 30 September 2026
- Modul: **KPI** → submodul **Performance Appraisal** → fitur **Balancing** (baru)
- Sumber konsep: `docs/KPI/performance-appraisal/Konsep - Performance Appraisal.pdf`
  (langkah Calculate → Balancing 1/2 → Publish, halaman Kalibrasi dan Balancing)
- Bersinggungan dengan: `2026-09-18-pa-grade-kurva-normal-design.md` (kurva dikunci per
  siklus), `2026-09-18-pa-wizard-kalibrasi-penilai-design.md` (Kalibrasi Penilai, lapisan
  skor efektif), `PaCalibrationService` (Kalibrasi Forum)

## 1. Ringkasan

Sesudah Calculate, hasil PA dibahas dalam forum **Balancing**: atasan dan forum
meninjau per kompetensi, menggeser skor yang dirasa tidak adil (Before → After), dan
merangkum kualitatifnya (Strengths / Area Development). Balancing adalah **unit yang
berulang**: satu siklus bisa melewati Balancing 1, 2, 3, … dan setiap putaran berakhir
dengan hitung ulang (kurva ikut dihitung ulang dan dikunci lagi) sebelum putaran
berikutnya atau Publish.

```
Input 360 → Calculate → [ Sesi Balancing → FINAL → Hitung ulang ]ⁿ → Publish
```

Admin Talent Analyst yang mengoperasikan seluruhnya; anggota forum hanya dicatat, tidak
login. Selama sesi berlangsung admin bisa **mensimulasikan** hitung ulang untuk melihat
grade dan HAV baru tanpa menyimpan apa pun.

## 2. Keputusan

| # | Keputusan | Catatan |
|---|---|---|
| B1 | Balancing adalah **unit berulang** dengan jumlah putaran bebas. | Urutan di PDF (Staff–SPV → Dept Head → Div Head; Section Head+ → Div Head → BOD) adalah kebiasaan, bukan aturan sistem. |
| B2 | Yang diubah adalah **skor per kompetensi** (Before/After). KPI **hanya dibaca**. | Nilai KPI berasal dari modul KPI dan sudah punya alur persetujuannya sendiri. |
| B3 | Kualitatif = **ringkasan admin** (Strengths / Area Development, teks kaya), bukan lagi jawaban per pertanyaan. | Before diambil dari ringkasan Calculate, After diisi di sesi. |
| B4 | **Kurva dihitung ulang dan dikunci lagi pada setiap hitung ulang.** | Pilihan "B" pemilik proyek. Grade orang di luar sesi bisa ikut bergeser; jejaknya dicatat (§7). |
| B5 | **Admin Talent Analyst** menginput semuanya. Forum dicatat (bisa lebih dari satu orang beserta perannya), tanpa login. | Pilihan "A". |
| B6 | **Sesi bebas** dengan filter kandidat: level / kelompok level, divisi, departemen, grup kurva, grade, kotak HAV, jumlah sesi yang sudah dilewati. | Kebebasan dijaga filter, bukan dikunci ke pola tetap, supaya perbaikan proses oleh Talent Analyst tidak merusak sistem. |
| B7 | **Kalibrasi Forum dihapus** dari alur. Riwayatnya dipertahankan sebagai arsip baca-saja. | Pilihan "A". Pergeseran grade sekarang hanya lewat skor kompetensi dan kurva, sehingga grade selalu cocok dengan angkanya. |
| B8 | **Pendekatan 1: lapisan penyesuaian di atas hasil hitung**, satu pintu baca. | Lembar penilai dan skor mentah HC Learn tidak disentuh. |
| B9 | Nilai Kompetensi baru = **hasil hitung + Σ bobotₖ × (Afterₖ − Beforeₖ)**, bobot kompetensi ditampilkan di tabel. | Pilihan "A": menghormati bobot butir yang berlaku. Definisi bobot di §6.1. |
| B10 | **Semua peserta sesi tercatat**, termasuk yang tidak diubah ("tanpa perubahan"). | Jejak menunjukkan orang itu sudah dibahas, bukan terlewat. |
| B11 | **Simulasi** memakai mesin yang sama dengan hitung ulang nyata, tanpa menulis ke database. | Diminta pemilik proyek 30 Sep 2026. |

## 3. Alur dan status sesi

### 3.1 Status

| Status | Arti | Transisi |
|---|---|---|
| `TERBUKA` | Sesi dibuat, peserta dipilih, belum ada isian | → `DRAFT` saat isian pertama disimpan; → `DIBATALKAN` |
| `DRAFT` | Isian sedang dikerjakan | → `FINAL`; → `DIBATALKAN` |
| `FINAL` | Isian dikunci, siap dihitung ulang | → (dihitung ulang, tercatat di `Hitung_Ulang_At`) |
| `DIBATALKAN` | Sesi dibatalkan beserta alasan; isiannya tidak berlaku | akhir |

Sesi `FINAL` tidak bisa dibuka lagi. Koreksi sesudahnya dilakukan lewat **sesi baru**:
pola "berulang" yang sama, dan riwayatnya tetap utuh.

### 3.2 Penjaga

| Kapan | Syarat |
|---|---|
| Membuat sesi | Siklus `CLOSED`; kurva siklus terkunci bila metode `KURVA` |
| Memilih peserta | Hasil aktif berstatus `DIHITUNG`, belum terbit ke karyawan, dan **tidak ada di sesi lain yang `TERBUKA`/`DRAFT`** |
| Menyimpan After | Dalam rentang skala (§6.2); **alasan wajib** bila After ≠ Before |
| Finalisasi | Setiap peserta sudah ditinjau (`TANPA_PERUBAHAN` atau `DIUBAH`) |
| Hitung ulang | Tidak ada sesi `TERBUKA`/`DRAFT` di siklus itu |
| Publish (rilis leader atau terbit) | Tidak ada sesi `FINAL` yang belum dihitung ulang, dan tidak ada sesi `TERBUKA`/`DRAFT` |

Kandidat yang tidak memenuhi syarat tetap tampil di layar pemilihan dalam keadaan
nonaktif, **dengan alasannya** (belum DIHITUNG / sedang di sesi X / sudah terbit).

## 4. Data

Enam tabel baru, awalan `N_PA_Balancing_`, semuanya **hanya-sisip untuk nilai yang sudah
final**: isian sesi `FINAL` tidak pernah disunting. Nama kolom mengikuti konvensi tabel PA
yang ada (`Id_PA_*`, `Flag_Aktif`, `Created_*`/`Updated_*`). Nomor DDL ditetapkan saat
rencana implementasi, sesudah memeriksa semua branch.

| Tabel | Isi utama |
|---|---|
| `N_PA_Balancing_Sesi` | Siklus, nama sesi, PIC (user), status, catatan rapat (teks kaya), `Final_At/_By`, `Hitung_Ulang_At/_By`, `Dibatalkan_At/_By`, `Alasan_Batal`, `Row_Version` untuk kunci optimistis |
| `N_PA_Balancing_Forum` | Sesi, karyawan, peran (mis. Dept Head, Div Head, BOD). Bisa banyak. Tanpa akses login |
| `N_PA_Balancing_Peserta` | Sesi, peserta, **snapshot Before** (Nilai KPI, Nilai Kompetensi, Final, grade, kotak HAV, versi hitung), status tinjau (`BELUM` / `TANPA_PERUBAHAN` / `DIUBAH`), alasan umum |
| `N_PA_Balancing_Kompetensi` | Baris peserta sesi, kompetensi, **bobot** (§6.1, dibekukan saat sesi dibuat), Before, After, alasan. Satu baris untuk **setiap** kompetensi, termasuk yang tidak diubah |
| `N_PA_Balancing_Kualitatif` | Baris peserta sesi, jenis (`STRENGTHS` / `AREA_DEVELOPMENT`), Before, After (HTML aman lewat `PaTeksKaya::bersihkan`) |
| `N_PA_Balancing_Jejak_Hitung` | Satu baris per orang per hitung ulang: Final, grade, kotak HAV sebelum dan sesudah, **sebab** (`BALANCING` / `KURVA` / `KEDUANYA`), sesi yang memicunya |

**Nilai yang berlaku** untuk satu kompetensi seorang peserta = After dari sesi `FINAL`
**terbaru** yang memuat peserta itu; bila tidak ada, nilai hasil hitung. Kualitatif After
dari sesi yang sama menjadi catatan admin di `N_PA_Result_Note` pada hitung ulang.

Bentuk `N_PA_Result` **tidak berubah**. `Kode_Grade_Final` selalu sama dengan
`Kode_Grade_Sistem` untuk siklus yang memakai Balancing (B7).

## 5. Satu pintu baca

Semua pembaca nilai kompetensi (Calculate, simulasi, radar, HAV, ekspor) lewat satu
service lapisan balancing, sejalan dengan pola `PaSkorEfektif`. Tes pemindai
(`SkorEfektifSatuPintuTest` atau tes saudara) diperluas: kode yang membaca
`N_PA_Result_Competency` untuk keperluan nilai tanpa melewati lapisan ini gagal tes,
kecuali ditandai eksplisit sebagai pembaca mentah.

## 6. Perhitungan

### 6.1 Bobot kompetensi

Bobot kompetensi *k* = **porsi tepat kompetensi itu di dalam Nilai Kompetensi**, yaitu
turunan Nilai Kompetensi terhadap skor kompetensi *k*:

```
bobotₖ = Σ_kategori  B_kat × Σ_penilai  ŵ_penilai × ( Σ bobot butir k milik penilai
                                                     / Σ bobot semua butir penilai )
```

- `B_kat` = bobot kategori terpakai sesudah redistribusi `PaBobotGuard` (per 1).
- `ŵ_penilai` = `Bobot_Dalam_Kategori` yang dinormalkan ulang di antara penilai yang mengisi.
- Bobot butir = `Bobot_Sumber`; bila ada butir tanpa bobot, setiap butir bernilai 1
  (sama dengan `nilaiSatuPenilai`).

Artinya: menaikkan skor kompetensi *k* sebesar Δ pada semua penilai menaikkan Nilai
Kompetensi **tepat** sebesar bobotₖ × Δ. Rumus B9 karenanya bukan pendekatan; hasilnya
sama dengan menggeser jawaban semua penilai untuk kompetensi itu. Σ bobotₖ = 1 bila semua
butir terpetakan ke kompetensi; butir tanpa kompetensi tetap dihitung tetapi tidak bisa
digeser.

Bobot dihitung dari data penugasan saat **sesi dibuat** dan dibekukan di
`N_PA_Balancing_Kompetensi`, supaya angka di rapat tidak berubah di tengah jalan.

### 6.2 Skala Before/After

Before/After memakai skala yang sama dengan nilai kompetensi hasil (**0–100**,
`N_PA_Result_Competency.Nilai` agregat lintas kategori). After dibatasi 0–100 (T2).

### 6.3 Hitung ulang nyata

Dijalankan admin sesudah satu atau lebih sesi `FINAL`, dalam **satu transaksi**:

1. Nilai Kompetensi setiap peserta = hasil hitung + Σ bobotₖ × (nilai berlakuₖ − hasil
   hitungₖ), dengan nilai berlaku dari §4. Selisihnya selalu diukur dari **hasil hitung
   mesin**, bukan dari Before sesi terakhir, sehingga beberapa sesi berurutan tidak saling
   menjumlah dua kali. Before di sesi kedua dan seterusnya adalah nilai berlaku saat sesi
   dibuat (After sesi sebelumnya), hanya untuk ditampilkan.
2. Final = Nilai KPI × bobot KPI + Nilai Kompetensi × bobot Kompetensi (rumus yang ada).
3. Kurva setiap grup: `PaKurvaService::buka` (alasan "Hitung ulang balancing") →
   hitung μ, σ, batas grade dari Final yang baru → `kunci`. Kurva lama tetap tersimpan
   nonaktif.
4. Grade semua orang di grup diperbarui dari kurva baru; kotak HAV dari Nilai KPI dan
   Nilai Kompetensi yang baru.
5. Kualitatif After menjadi catatan admin.
6. Jejak §4 ditulis untuk **setiap** orang di siklus yang berubah, dengan sebabnya.
7. Sesi-sesi yang ikut dihitung diberi `Hitung_Ulang_At/_By`.

Hasil yang sudah terbit ke karyawan tidak ikut dihitung ulang.

### 6.4 Simulasi

Tombol **Simulasikan** di board sesi menjalankan langkah 1–4 di atas **di memori**:

- memakai isian After yang sedang ada di layar, **termasuk yang belum disimpan**,
  ditambah sesi lain yang sudah `FINAL` tetapi belum dihitung ulang;
- tidak menulis ke database, tidak membuka atau mengunci kurva;
- mengembalikan per orang: Final, grade, kotak HAV sebelum → sesudah beserta sebabnya,
  termasuk **orang di luar sesi** yang grade-nya bergeser karena kurva.

Simulasi dan hitung ulang nyata memanggil fungsi murni yang sama. Satu-satunya
perbedaan adalah langkah simpan.

## 7. Layar

Menu **KPI → Performance Appraisal → Balancing**, hanya untuk admin Talent Analyst (izin
baru di matriks akses PA). Berkas halaman di `resources/js/Pages/KPI/PerformanceAppraisal/Balancing/`.

1. **Daftar sesi per siklus**: nama, PIC, jumlah peserta, forum, tanggal, status.
   Banner "N sesi FINAL belum dihitung ulang" + tombol ke Hitung Ulang.
2. **Buat sesi**: nama, PIC, forum (karyawan + peran), pemilihan peserta dengan filter B6.
   Kandidat tidak memenuhi syarat tampil abu-abu beserta alasannya.
3. **Board sesi**:
   - satu baris per peserta: Final, Kompetensi, KPI (dengan bobot), grade, HAV, status tinjau;
   - baris dibuka → **matriks kompetensi**: nama, definisi, Expected Behaviour, bobot,
     Before, **After**, alasan;
   - di bawahnya **kualitatif** Strengths / Area Development (Before baca-saja, After
     editor teks kaya);
   - catatan rapat sesi;
   - tombol **Simpan draft**, **Simulasikan**, **Finalkan sesi**, **Batalkan**.
4. **Panel simulasi**: label **"SIMULASI — belum tersimpan"**; tabel Before → After
   (Final, grade, HAV, sebab); HAV map sebelum dan sesudah dengan panah bagi yang
   berpindah kotak (memakai `hav-map.vue` / `havScatter.js`); daftar orang di luar sesi
   yang ikut bergeser.
5. **Tab "Balancing" di detail peserta** (`PaResultDialog`): sesi yang pernah diikuti,
   perubahan per kompetensi, jejak hitung ulang. Tombol dan dialog Kalibrasi Forum
   dilepas; riwayat lamanya tetap terbaca.

Tampilan HP mengikuti pola layar master responsif (PR #470): matriks menjadi kartu per
kompetensi.

## 8. Penanganan galat

| Situasi | Perilaku |
|---|---|
| Peserta sudah di sesi lain yang terbuka | Ditolak, nama sesinya disebut |
| After di luar rentang, atau berubah tanpa alasan | Ditolak per baris, isian lain tidak hilang |
| Dua admin menyimpan board yang sama | Kunci optimistis (`Row_Version`): "Data sudah berubah, muat ulang"; tidak saling menimpa |
| Finalisasi dengan peserta `BELUM` | Ditolak, peserta yang belum ditinjau disebut |
| Hitung ulang saat ada sesi terbuka | Ditolak, sesinya disebut |
| Hitung ulang gagal di tengah | Transaksi dibatalkan; tidak ada kurva atau hasil setengah jadi |
| Publish dengan sesi FINAL belum dihitung ulang | Ditolak di `PaResultPublishService` |
| Simulasi gagal | Pesan saja; board tidak berubah |

## 9. Pengujian

**Otomatis (PHPUnit):**
- Bobot §6.1 memenuhi sifat tepatnya: menggeser semua jawaban kompetensi *k* sebesar Δ
  pada data uji mengubah Nilai Kompetensi sebesar bobotₖ × Δ.
- Rumus B9 untuk satu sesi dan beberapa sesi berurutan (tidak menjumlah dua kali).
- Kurva dihitung ulang dan dikunci lagi; grade orang di luar sesi bergeser dengan benar;
  kurva lama tersimpan nonaktif.
- **Simulasi = hitung ulang nyata** untuk data yang sama (tes terpenting).
- Semua penjaga §3.2.
- Peserta tanpa perubahan tetap tercatat `TANPA_PERUBAHAN`; jejak hitung ulang lengkap
  dengan sebabnya.
- Tes pemindai satu pintu (§5).
- Kualitatif After disaring `PaTeksKaya`.

**Otomatis (Vitest):** matriks Before/After, validasi alasan, label simulasi, kartu HP.

**Manual di dev:** siklus #8781 → sesi Staff–SPV → simulasi → final → hitung ulang →
sesi Section Head+ → final → hitung ulang → publish; periksa HAV map dan layar karyawan.

## 10. Di luar cakupan

- Login atau persetujuan oleh anggota forum.
- Menggeser Nilai KPI.
- Urutan putaran yang dipaksakan sistem.
- Membuka ulang sesi `FINAL` (koreksi lewat sesi baru).

## 11. Keputusan tambahan (disetujui 30 Sep 2026)

| # | Pertanyaan | Keputusan |
|---|---|---|
| T1 | Hasil yang masih membawa Kalibrasi Forum lama saat siklus itu masuk Balancing | Hitung ulang pertama melepasnya dengan sebab tercatat (`lepaskanKalibrasi`); riwayat tetap ada |
| T2 | Skala Before/After: 0–100 atau skala soal asli (mis. 1–5)? | 0–100, sama dengan radar dan hasil; layar bisa menampilkan padanan skala asli bila perlu |
| T3 | Ringkasan kualitatif Before: diisi admin saat Calculate, atau otomatis dari catatan penilai? | Diisi admin (B3); bila kosong, Before tampil kosong dan After tetap bisa diisi |

## Pembaruan 2026-10-02 (umpan balik pemakaian)

- **Tim penilai (forum) wajib minimal 1 orang** dan boleh siapa pun karyawan aktif, tidak harus
  peserta siklus. Calon dari `GET /balancing/tim-penilai` (`PaBalancingTimPenilaiService`).
  **Peran tidak diisi tangan**: server mengisinya dari jabatan
  `View_Golongan_Sub_Golongan_Level_Jabatan` (level bila jabatan kosong); kiriman ganda dicatat sekali.
- **Alasan dicatat per orang**, bukan per kompetensi: wajib bila status tinjau DIUBAH
  (`ALASAN_WAJIB`), boleh dikosongkan bila tidak ada perubahan. Alasan per kompetensi yang
  terkirim tetap disimpan, tetapi tidak lagi diwajibkan.
- **Kualitatif yang dibahas hanya Strength (QL01) dan Development Area (QL02)**
  (`PaBalancingSesiService::KUALITATIF_BALANCING`); Before = rangkuman admin. Soal dan jawaban
  penilai per soal untuk modal: `GET /participants/{id}/balancing/kualitatif`.
- Detail sesi membawa `sebelum.bobot_kpi` / `sebelum.bobot_kompetensi` untuk matriks board.
- Simulasi membawa `semua` (HAV & grade seluruh peserta siklus), `grade` (urutan), dan `kurva`
  (per grup: μ/σ, batas grade, sebaran sebelum/sesudah, nilai anggota). Rumus Nilai Kompetensi
  After di layar disamakan dengan server: Before + Σ bobot × (After − Before).
- Tahap Balancing kini bagian dari Penutupan Siklus (spec 2026-10-02-pa-penutupan-siklus).
