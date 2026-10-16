# Alur Skenario UAT Performance Appraisal — 8 Oktober 2026

Pendamping dokumen `IT_BA_UAT KPI-PERFORMANCE-APPRAISAL.docx` (UAT-PA-001 s.d. 013).
Lingkungan: development (`Web_HRIS`). Periode dinilai: **1 Jan 2026 – 30 Sep 2026**.

## 0. Yang berubah dari dokumen UAT

1. **Tidak ada siklus siap pakai.** Seluruh data siklus dev dikosongkan pagi ini, dan siklus UAT
   pertama (#14287, validasi manual) juga dihapus siang ini. UAT dimulai dari UAT-PA-002, dan
   UAT-PA-007 s.d. 013 dijalankan pada **siklus UAT yang sama** yang dibuat di UAT-PA-002.
2. **Peserta hanya level Officer (4) dan Senior Officer (5).** Master *Pengecualian Level*
   (diatur 2 Okt) mengecualikan level 1, 2, 3, 6–15, dan 21. Peserta ≈ 190 orang
   (10 karyawan asli + 180 karyawan dummy `UJI…`). Kurva normal terbentuk karena grupnya ≥ 30.
3. **Esai BISA diuji (master diubah sementara, 8 Okt siang).** CV01 Focus on Customer dan CV02 Agility &
   Improvement diaktifkan, CV04 Integrity dinonaktifkan, dan Matriks Soal level 4 dan 5 (keempat jenis
   penilai) diikat ke ujian HC Learn **100164** "360 Employee Assessment (Atasan) - OFFICER": 14 bagian =
   K01–K11 Manajerial, K12 = CV01, K13 = CV02, **K14 = CV03 Mutual Growth — ESAI (1 soal)**.
   Sesudah UAT master ini **wajib dikembalikan** (level 4 → 100166, level 5 → 100165, CV04 aktif,
   CV01/CV02 nonaktif); keadaan sebelumnya tersimpan di checkpoint `sebelum-hapus-14287`.
   Esai Strength / Development Area (kompetensi KUALITATIF level 6–13) tetap tidak diuji — ujian HC Learn
   untuk level itu tidak ada.

## 0a. Sesi Master Data (dijalankan PALING AWAL, sebelum Buat Siklus)

Tester boleh create → update → delete di master data, **asal hanya pada data uji baru** dan
master kembali ke keadaan semula sebelum siklus dibuat. Alasannya:

- Saat **Buat Siklus**, aturan penilai, bobot pilar, syarat kompetensi, dan rentang nilai
  **disalin** ke siklus. Sesudah itu suntingan master tidak mengubah siklus.
- **Paket soal** disalin di langkah *Pengaturan KPI*; **pengecualian level** dibaca saat
  *Peserta* dijalankan; **atribut kompetensi** (berskor, radar, KKM) dibekukan saat *Kunci*.
  Sebelum titik-titik itu, master dibaca **langsung** — mengubahnya mengubah siklus.
- Ikatan paket soal level 4/5 memakai aturan **1 bagian ujian = 1 kompetensi wajib**
  (14 = 11 Manajerial + CV01 + CV02 + CV03). **Menambah atau menonaktifkan satu kompetensi Manajerial /
  Core Value saja sudah membuat ikatan level 4/5 tidak sehat**, dan langkah Pengaturan KPI
  tidak bisa selesai. Sistem kini memperingatkan hal ini saat kompetensi diaktifkan kembali.

| Master | Create → Update → Delete yang AMAN | JANGAN disentuh |
|---|---|---|
| Kompetensi | Buat "UAT Test Kompetensi" bertipe **KUALITATIF**, tak berskor → ubah nama/deskripsi → nonaktifkan | Kompetensi K01–K11, CV01–CV06 (aktif/nonaktif, berskor) |
| Jenis Kompetensi | Buat jenis baru "UAT Test" → ubah → hapus/nonaktifkan | Manajerial, Core Value |
| Syarat Kompetensi | Tambah syarat di level yang dikecualikan (mis. Freelance 21) → ubah → hapus | Level 4 dan 5 |
| Bobot Penilaian (pilar) | Ubah level 3 (mis. 70/30) → kembalikan | Level 4 dan 5 (75/25) |
| Aturan Penilai | Ubah bobot level 3 → kembalikan | Level 4 dan 5 (55/25/10/10) |
| Jenis Penilai | PEER: "boleh kalibrasi" Y → T → Y | SELF (harus tetap T) |
| Skala Nilai (HAV) | Ubah **nama** satu pita HAV Map (Capaian KPI / Kompetensi) → kembalikan | Batas nilai pita. Kategori *Grade* sengaja disembunyikan: grade ditentukan kurva (μ + z × σ), datanya tetap dipakai sistem |
| Grup Kurva | Tambah grup "UAT Test" berisi level yang dikecualikan → hapus | Grup berisi level 4/5. Ingat: menyimpan grup **mengganti seluruh susunan** |
| Ikatan Paket Soal | Hanya lihat & periksa kesehatan | Ikatan level 4 dan 5 (keduanya 100164) |
| Pengecualian Level | Tambah level yang tidak dipakai (mis. 16) → hapus | Jangan cabut pengecualian level 2 (Staff) dan jangan kecualikan level 4/5 |
| Kuadran HAV | Ubah deskripsi satu kotak → kembalikan | Batas skor kotak |

**Titik periksa sebelum lanjut ke Buat Siklus** (wajib, 2 menit):
1. Matriks Ikatan Paket Soal: semua sel level 4 dan 5 **Sehat**, ujian **100164**.
2. Kompetensi aktif bertipe Manajerial + Core Value = **14** (K01–K11, CV01, CV02, CV03); CV04 nonaktif.
3. Pengecualian Level: level 4 dan 5 **tidak** ada di daftar.
4. Bobot pilar level 4/5 = 75/25; aturan penilai level 4/5 = 55/25/10/10.

Bila salah satu tidak cocok, kembalikan dulu — jangan membuat siklus di atas master yang berubah.

## 1. Orang dan data KPI yang sudah disiapkan

KPI disetujui + diverifikasi (yang dibaca mesin PA):

| Kasus | Orang (level) | KPI 2026 |
|---|---|---|
| A. KPI lengkap | Aji A4 (4), Imam J31 (4), Miftah R48 (4), Iqbal K32 (5), Ridho H45 (5) | Jan–Sep |
| B. September diisi saat demo | Dimas E15 (4), Satria W60 (4) | Jan–Agu; jadwal Sep dibuka |
| C. Tanpa KPI → hasil TERTAHAN | Fauzan F23 (4) — akun KPI ada, pengajuan tidak; Samuel V59 (4), Raden S55 (4) — tanpa akun KPI | tidak ada |

Jadwal **"UAT PA - Update Performance September 2026"** (#1755): perorangan, periode 2026-09,
aktif 8–31 Okt. Dimas dan Satria ada di tim KPI Ridho (menu *Update Performance Team*),
approver langkah 1 = Ridho, langkah 2 (verifikasi, peran `ROLE_KPI_VERIF`) = Ridho / Iqbal.
Sumber data: DML-2026-171 (data uji dev).

Relasi organisasi antar-karyawan asli (dipakai sebagai rater):

- **Aji** — atasan: Iqbal; rekan: Imam, Ridho
- **Imam** — atasan: Iqbal, Aji; rekan: Aji
- **Dimas** — atasan: Ridho
- **Ridho** — atasan: Arya (C9); bawahan: Dimas, Fauzan; rekan: Iqbal, Aji
- **Iqbal** — bawahan: Aji, Imam; rekan: Ridho

Aturan penilai level 4/5: Atasan 55% · Bawahan 25% · Rekan 10% · Diri 10%.
Bobot pilar level 4/5: KPI 75% · Kompetensi 25%.

## 2. Urutan sesi

| # | Langkah | Test case | Akun |
|---|---|---|---|
| 0 | Master data: create → update → delete pada data uji, lalu titik periksa | (sesi master) | Ridho / Aji |
| 1 | Hak akses & menu | UAT-PA-001 | Ridho, karyawan biasa (mis. Imam), leader (Iqbal) |
| 2 | Buat siklus | UAT-PA-002 | Ridho |
| 3 | Penyiapan 5 langkah, kunci | UAT-PA-003 | Ridho |
| 4 | Buka pengisian | UAT-PA-004 | Ridho |
| 5 | Isi KPI September (demo KPI) | — (pendukung) | Ridho |
| 6 | Rater mengisi 3 lembar (termasuk esai K14) | UAT-PA-005 | Satria |
| 7 | Sisa lembar diisi simulasi (`pa:uat-isi-otomatis`) | — (developer) | server |
| 8 | Monitoring & tutup pengisian | UAT-PA-006 | Ridho |
| 9 | Hitung & verifikasi | UAT-PA-007 | Ridho |
| 10 | Kurva normal, kunci | UAT-PA-008 | Ridho |
| 11 | Balancing | UAT-PA-009 s.d. 011 | Ridho (forum: Ridho, Iqbal) |
| 12 | Rilis ke leader | UAT-PA-012 | Ridho, leader Iqbal |
| 13 | Terbit, lalu tutup penerbitan (paling akhir) | UAT-PA-013 | Ridho, karyawan Aji |

## 2a. Angka acuan (dari uji coba penyiapan di dev, 8 Okt pagi — sebelum master esai)

Angka peserta dan penugasan tidak bergantung pada ujian, jadi tetap berlaku. Yang berubah karena
master esai hanya ujiannya (100164) dan jumlah kompetensi (14).

Uji coba lewat service yang sama dengan layar menghasilkan angka berikut. Saat UAT manual,
angka di layar harus sama; selisih = temuan.

| Titik periksa | Yang harus tampil |
|---|---|
| Buat siklus | Kode otomatis `PA-2026-01`, status DRAFT, metode KURVA; cetak biru menyalin 2 aturan penilai (8 rincian), 2 bobot pilar, 12 rentang nilai |
| Langkah sesudah dibuat | Bobot Penilaian ✓ dan Aturan Penilai ✓ otomatis; Pengaturan KPI belum |
| Pengaturan KPI → salin dari master | 8 sel terisi (level 4 dan 5 → **100164**, empat kategori, 14 butir) → ✓ |
| Peserta (sesudah Raden dikecualikan manual) | **189 diikutkan**, 1 dikecualikan manual, **768 dikecualikan otomatis** (level di master Pengecualian Level), 0 tertunda |
| Penugasan Penilai | **1.134 penugasan**: Diri 189 · Atasan 189 · Rekan 378 · Bawahan 378; 0 peserta tanpa penilai |
| Kunci (percobaan pertama) | **DITOLAK**: "Frans Bachtiar (A1) — SUDAH_RESIGN" di HC Learn. Ia penilai *Bawahan* untuk Samuel (V59). Panel kunci dan lencana tab Penugasan menyebutnya sebagai penghalang beserta peserta yang perlu diganti penilainya. Cabut penugasan Frans dengan alasan (Kelola Rater) — konfirmasi memperingatkan bila sisa rater di bawah minimum, dan Frans tidak diusulkan lagi; centang pengganti bila perlu lalu Simpan. Kunci lagi → LOCKED. Kasus negatif untuk UAT-PA-003 |
| Sesudah kunci | ±1.133 penugasan aktif; salinan atribut kompetensi terbentuk |
| Buka pengisian | Status OPEN |
| Jadwalkan akses HC Learn | Lihat peringatan di bawah |

**Penjadwalan HC Learn.** Kiriman dipotong per 150 penugasan. Dialog progres punya 4 tahap
(Validasi → Kirim Jadwal → Respons HC Learn → Siap Pengisian); persen = **jadwal terkirim**. Sesudah
semua terkirim, statusnya *Menunggu HC Learn* — bukan selesai. Dialog boleh ditutup: panel
"Jadwal terkirim — menunggu jawaban HC Learn" tetap tampil di halaman Penugasan, mengecek otomatis
tiap 20 detik (ada tombol **Cek Sekarang**), sampai *Akses Siap* = total.

## 3. Langkah rinci — apa yang dipilih

### UAT-PA-002 Buat siklus
- Nama **UAT PA 2026**, tahun **2026**.
- Periode dinilai **01-01-2026 s.d. 30-09-2026** (menentukan bulan KPI yang dibaca: Jan–Sep).
- Jendela pengisian **08-10-2026 s.d. 31-10-2026**.
- Metode grade **KURVA**.
- Negatif: simpan tanpa nama / tanpa tahun → ditolak.

### UAT-PA-003 Penyiapan
1. **Bobot Penilaian** — level 4 dan 5 tampil 75/25. Ubah satu level lalu kembalikan (bukti bisa disesuaikan).
2. **Pengaturan KPI (paket soal)** — level 4 dan 5 → ujian **100164** (14 bagian, K14 esai), untuk keempat kategori.
3. **Aturan Penilai** — Atasan 55 / Bawahan 25 / Rekan 10 / Diri 10.
4. **Peserta** — jalankan penyiapan. Level 1, 2, 3, 6+ otomatis dikecualikan oleh master.
   **Kecualikan 1 orang secara manual dengan alasan** (mis. Raden S55 "uji pengecualian manual"),
   lalu cek tercatat.
5. **Penugasan Penilai** — bentuk penugasan. Cek Aji punya Diri, Atasan (Iqbal), Rekan (Imam/Ridho).
- **Kunci siklus.**
- Negatif: lompat ke langkah berikut sebelum langkah sebelumnya selesai; kunci selagi ada peserta tertunda / tanpa penilai.

### UAT-PA-004 Buka pengisian
- Status menjadi OPEN; token HC Learn terbentuk; tugas muncul di *Penilaian Saya* rater.

### Langkah 5 — Isi KPI September (pendukung, sebelum Hitung)
1. Login **Ridho** → *Update Performance Team* → tab *To Update* → **Dimas** periode 2026-09 → isi realisasi → kirim.
2. Ridho menyetujui (langkah 1) lalu memverifikasi (langkah 2) → realisasi berstatus *disetujui*.
3. **Satria: isi dan kirim, tetapi JANGAN disetujui.** Ini memunculkan kasus
   "KPI 1 realisasi belum disetujui" di hasil PA (hasil tetap dihitung dari Jan–Agu).

### UAT-PA-005 Pengisian penilaian
- Login **Satria (W60)** → *Penilaian Saya*. Ia punya **3 lembar**: Diri sendiri + 2 lembar sebagai
  **Bawahan** (menilai atasannya). Untuk tiap lembar: Mulai menilai → kerjakan di HC Learn
  (65 soal skala + **1 soal esai K14 Mutual Growth**) → kembali ke HCIS.
- Sesudah satu lembar dikirim di HC Learn, statusnya **Terkumpul**, belum Selesai. Tombol
  **Kirim semua** baru bisa ditekan sesudah ketiga lembar terkumpul → status menjadi Selesai (✓).
- Negatif: tekan Kirim semua saat masih ada lembar yang belum dikerjakan → ditolak, sisanya disebut.
- Esai: jawaban K14 Satria harus terbaca di HCIS (detail hasil / kesimpulan kualitatif peserta yang
  dinilainya) sesudah dihitung.

### Langkah 7 — Simulasi sisa penugasan (developer)
Tester cukup mengisi **3 lembar manual** lewat HC Learn (Satria: 1 lembar diri sendiri + 2 lembar bawahan) —
itu sudah membuktikan jalur login → isi → kembali → Kirim. Jalankan simulasi **sesudah** Satria menekan
Kirim semua (atau tambahkan `--kecuali-penilai=W60`). Sisanya (±1.100 lembar, 65 soal per lembar)
diisi developer di server dengan jawaban karangan lewat jalur callback HC Learn yang sama:

```
php artisan pa:uat-isi-otomatis <id_siklus>                      # pratinjau: berapa lembar per kategori
php artisan pa:uat-isi-otomatis <id_siklus> --jalankan \
    --kecuali-penilai=<kode,...>                                  # lembar manual yang belum selesai
```

- Lembar yang sudah dikerjakan (Terkumpul/Selesai) tidak pernah ditimpa.
- Nilai disebar per peserta (tinggi 25% / sedang 50% / rendah 25%) supaya kurva, HAV Map, dan
  Balancing punya sebaran yang bisa dinilai; penilaian diri sedikit lebih tinggi.
- "Kirim semua" ditekan atas nama penilai simulasi; penilai yang dikecualikan menekannya sendiri.
- Di HC Learn lembar simulasi tetap tampak belum dikerjakan — yang diuji alur HCIS sesudah nilai masuk.
- Esai: sejak 8 Okt siang L4/L5 diikat ke ujian **100164** yang bagian K14 "Mutual Growth"-nya esai
  (lihat §0.3); perintah ini mengirim K14 sebagai esai (`--esai=K14`, bawaan) dengan jawaban bervariasi.

### UAT-PA-006 Monitoring & tutup
- Saring status termasuk Terlambat; cetak ulang akses satu rater dengan alasan.
- **Tutup pengisian** → CLOSED. Lembar yang masih terkumpul dilepas otomatis oleh penutupan.
- Negatif: rater mengirim setelah ditutup → ditolak.

### UAT-PA-007 Hitung & verifikasi — kasus yang harus terlihat
| Orang | Yang diharapkan |
|---|---|
| Aji, Imam, Iqbal, Miftah, Ridho | DIHITUNG; KPI dari Jan–Sep |
| Dimas | DIHITUNG; KPI Jan–Sep (September dari demo) |
| Satria | DIHITUNG; KPI Jan–Agu, tercatat 1 realisasi belum disetujui |
| Fauzan | **TERTAHAN** — "Tidak ada KPI disetujui pada rentang periode siklus" (punya akun KPI, tanpa pengajuan) |
| Samuel | **TERTAHAN** — "Karyawan tidak punya akun KPI" |
| Raden | Dikecualikan (dari langkah Peserta) — tidak dihitung |
- Antrean tindak lanjut memuat Fauzan & Samuel beserta sebabnya.
- Ringkasan tahap (dihitung/tertahan/belum/terbit) sama dengan daftar hasil.

### UAT-PA-008 Kurva normal
- Grup Officer & Senior Officer (≈190) tanpa peringatan grup kecil; grade "sementara".
- Kunci kurva → aktivitas terakhir "Kurva dikunci oleh Ridho".
- Buka kunci dengan alasan, kunci lagi. Negatif: alasan < 5 karakter.

### UAT-PA-009 s.d. 011 Balancing
- Buat sesi "Balancing Officer": peserta **Aji, Imam, Dimas** (grade tampil B+ bukan B_PLUS);
  coba centang Fauzan → tidak bisa, alasannya tampil. Tim penilai: **Ridho, Iqbal**.
- Board: naikkan satu kompetensi Aji, isi alasan; Undo/Redo; coba After 120 / −5 → tidak bisa simpan/simulasi.
- Simulasikan → tab Perubahan, HAV Map, Kurva. Tandai sisa tanpa perubahan → Simpan draft → Finalkan → **Hitung ulang**.
- Cek angka resmi Aji = angka simulasi terakhir.

### UAT-PA-012 Rilis ke leader
- Rilis ke leader. Login **Iqbal** → *Penilaian Tim*: Aji dan Imam tampil dengan nilai & grade.

### UAT-PA-013 Terbit & tutup penerbitan (PALING AKHIR, satu arah)
- Terbitkan; Fauzan/Samuel (tertahan) tetap menunggu.
- Login **Aji** → *Penilaian Saya* → hasil tampil.
- Negatif: tutup penerbitan selagi ada yang belum terbit → ditolak.
- Untuk menutup: putuskan dulu Fauzan/Samuel (mis. kecualikan dengan alasan), terbitkan sisanya, lalu tutup → PUBLISHED.

## 4. Konsistensi angka (kriteria penerimaan)
Bandingkan Final, grade, dan kotak HAV **Aji** di: Monitoring Cycle → board Balancing → detail hasil →
Penilaian Tim (Iqbal) → Penilaian Saya (Aji). Harus sama persis.
