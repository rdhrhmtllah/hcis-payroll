# Performance Appraisal 360° — Rancangan

| | |
|---|---|
| **Status** | Rancangan brainstorming — **belum ada kode, belum ada DDL** |
| **Tanggal** | 2026-08-29 |
| **Cabang** | `feat/performance-apprisal` |
| **Modul** | `KPI` → submodul `Appraisal` (grup fitur `KPI`, `N_HRIS_Master_Grup_Fitur` Id=2) |
| **Prefix tabel** | `N_PA_` |
| **Artifact visual** | https://claude.ai/code/artifact/e890bcc5-10ea-4323-8b12-d99c1ca01245 |
| **Kelengkapan** | Bagian 1–3 selesai · Bagian 4 (halaman & peran) dan 5 (rencana fase) **belum** |

---

## 0. Cara membaca dokumen ini

Dokumen ini adalah **checkpoint brainstorming**, bukan spesifikasi final dan bukan rencana implementasi.

Untuk agent atau orang lain yang melanjutkan:

1. **Jangan menulis kode atau DDL dari dokumen ini.** Masih ada enam hal terbuka di Bagian 11, dan lima di antaranya menunggu jawaban pihak lain (HC Learn, HC/PIC, tim IT).
2. **Angka database di Bagian 10 bertanggal dan dibaca dari lingkungan development.** Sebaran datanya tidak mencerminkan produksi. Yang bisa dipercaya adalah bentuk skemanya, bukan populasinya.
3. **Bagian 12 mencatat kekeliruan yang sudah dikoreksi.** Baca itu sebelum mengulang penelusuran yang sama.
4. **Nama tabel dan kolom di Bagian 7 adalah rancangan**, belum diverifikasi terhadap DDL apa pun.
5. Aturan wajib yang berlaku: `AGENTS.md`, `docs/architecture/module-registry.md`, `docs/architecture/module-structure-inventory.md`, `docs/database/table-design-rules.md`.

---

## 1. Konteks & sumber kebenaran

Sistem penilaian kinerja EVO Group berbasis **360° Feedback**, menggabungkan dua pilar:

| Pilar | Mengukur | Sumber data |
|---|---|---|
| **KPI** | Hasil kerja, pencapaian target | Data KPI aktual vs target di HCIS |
| **360° Feedback** | Kompetensi manajerial + Core Value | Survei multi-penilai lewat HC Learn |

Keduanya dipetakan ke **HAV Map** (9-box) sebelum menghasilkan skor akhir dan grading.

### Sumber material

| Sumber | Isi | Catatan |
|---|---|---|
| Deck `Send to IT PERFORMANCE APPRAISAL EVO GROUP 2026.pptx` | 23 slide dari HC Learning & Development | Sebagian besar screenshot mockup; yang **unik** hanya matriks bobot per level (slide 4), diagram 360/180/90 (slide 5–7), dimensi kompetensi (slide 8), dan matriks dimensi per band (slide 9) |
| Mockup HTML "EVO Group Performance" | Prototype kasar dari PIC | Menunjukkan halaman peserta & admin |
| `ringkasan-diskusi-performance-appraisal.md` | Hasil brainstorming sesi sebelumnya | Beberapa asumsinya sudah terbantah — lihat Bagian 12 |
| Checklist pertanyaan + jawaban PIC | 9 kategori | **Bagian 4 (Anonimitas) tidak pernah ditanyakan** — nomornya melompat dari 3 ke 5 |
| Database `Web_HRIS` (development) | Verifikasi skema | Dibaca read-only 2026-08-29 |

### Dimensi kompetensi (deck slide 8)

**Kompetensi Manajerial** — K01 Analytical Thinking · K02 Problem Solving · K03 Decision Making · K04 Planning and Organizing · K05 Achievement Orientation · K06 Customer Focus · K07 Impactful Communication · K08 Change & Adaptability · K09 Teamwork & Collaboration · K10 Business Sense · K11 Team Leadership

**Core Value** — Focus on Customer · Agility & Improvement · Mutual Growth · Integrity · Leadership · Yes, We Can Spirit

**Behavior (Core Value)** — Discipline/Integrity · Diligent/YWC · Empathy/Focus on Customer

**Qualitative** — Strength · Development Area · Initiative · Recommendation

### Matriks dimensi per band jabatan (deck slide 9)

| Dimensi | Pramukarya | Staff | Leaders |
|---|:---:|:---:|:---:|
| Kompetensi Manajerial | – | ✔ | ✔ |
| Core Value | – | ✔ | ✔ |
| Behavior (Core Value) | ✔ | – | – |
| Qualitative Question | – | – | ✔ |

> **Perhatikan:** ini pengelompokan **tiga band** (Pramukarya/Staff/Leaders) untuk menentukan *dimensi apa yang dinilai*. Berbeda dari pengelompokan **12 level** di bawah yang menentukan *bobot penilai*. Dua-duanya harus dimodelkan; jangan disatukan.

### Matriks bobot penilai per level (deck slide 4)

| Level | Atasan 2 | Atasan 1 | Self | Rekan Kerja | Bawahan |
|---|---|---:|---:|---:|---:|
| Pramukarya | (adjustable) | 100% | – | – | – |
| Staff | (adjustable) | 80% | 20% | – | – |
| Sr. Staff | (adjustable) | 80% | 20% | – | – |
| Officer (Specialist) | (adjustable) | 60% | 15% | 25% | – |
| Officer | (adjustable) | 55% | 10% | 10% | 25% |
| Sr Officer | (adjustable) | 55% | 10% | 10% | 25% |
| Assistant Manager | (adjustable) | 50% | 10% | 10% | 30% |
| Sr. Assistant Manager | (adjustable) | 50% | 10% | 10% | 30% |
| Manager | (adjustable) | 45% | 10% | 15% | 30% |
| Sr. Manager | (adjustable) | 45% | 10% | 15% | 30% |
| General Manager | (adjustable) | 40% | 10% | 20% | 30% |
| Sr. General Manager | (adjustable) | 40% | 10% | 20% | 30% |

Kolom "Atasan 2" tertulis *(adjustable)* di semua baris — belum ada angkanya, dan datanya nyaris tidak ada (lihat Bagian 10).

### Kuadran HAV Map (deck slide 22 + mockup)

Score_Type Y = Performa (skor KPI), sumbu X = Kompetensi.

| Box | Nama | Performa × Kompetensi |
|---|---|---|
| 1 | Striving Star | Tinggi × Tinggi |
| 2 | Future Star | Tinggi × Sedang |
| 3 | Future Star | Sedang × Tinggi |
| 4 | Potential Candidate | Sedang × Sedang |
| 5 | Top Performer | Tinggi × Rendah |
| 6 | Contributor | Sedang × Rendah |
| 7 | Raw Diamond | Rendah × Tinggi |
| 8 | Problem Employee | Rendah × Sedang |
| 9 | Deadwood | Rendah × Rendah |

---

## 2. Ringkasan keputusan

| # | Keputusan | Diputuskan oleh | Alasan |
|---|---|---|---|
| K01 | Lingkup penuh sesuai mockup, dikerjakan **bertahap** | User | Supaya tiap fase selesai rapi sebelum berikutnya |
| K02 | Struktur organisasi diturunkan dari `KPI_Approval_Flow_Performance` | User | Bagan organisasi memang tidak pernah dibangun di HCIS |
| K03 | Cycle = **dua rentang tanggal** (periode dinilai + jadwal pengisian); Quarter/Semester/Yearly hanya label turunan | User | Perubahan Policy di tengah tahun jadi operasi biasa; menghapus percabangan YTD vs Full-Year |
| K04 | Tumpang tindih periode **dilarang keras**, dijaga per-karyawan | User | Satu bulan kerja tidak boleh dinilai dua kali |
| K05 | HC Learn = alat test & config soal; HCIS = penampung | User | Mengikuti pola integrasi LMS yang sudah ada |
| K06 | Simpan **bundel mentah** + proyeksi ternormalisasi lewat pemeta berversi | User | Bentuk payload belum pasti dan akan berubah |
| K07 | Matriks & bobot = **tabel konfigurasi per (cycle × level)** mencakup seluruh level master | User | Matriks PIC 12 baris, master level 16 — hardcode akan meninggalkan 4 kelompok tanpa aturan |
| K08 | Freelance dan Direktur **ikut** dinilai | User | — |
| K09 | Specialist vs struktural (180° vs 360°) **ditetapkan admin**, bukan diturunkan sistem | User | Sesuai matriks; tidak ada penandanya di database |
| K10 | Peer & Bawahan ditetapkan **manual per orang, dengan rekomendasi sistem** | User | Meringankan admin tanpa sistem memutuskan sendiri |
| K11 | Karyawan hanya melihat **kesimpulan akhir** setelah dipublikasikan admin | User | Tidak pernah ada tampilan per-penilai |
| K12 | Master **range/pita nilai tersendiri per sumbu** (KPI dan PA) | User | Konversi dan banding jadi konfigurasi, bukan konstanta |
| K13 | Nilai KPI memakai `KpiScoringService::accumulatedScore()` yang sudah ada | Rancangan | Mencegah dua tempat memberi angka berbeda untuk orang yang sama |
| K14 | Nilai KPI **di-snapshot**, tidak ditarik ulang saat dibaca | Rancangan | `KPI_Update_Performance` punya `Cancelled_At`/`Flag_Aktif` — nilai bulanan bisa dibatalkan belakangan |
| K15 | Perhitungan **batch**, bukan saat dibaca | Rancangan | Sistem ini sudah pernah kena CPU 100% karena agregat dihitung per-request |
| K16 | Review setelah kalibrasi memakai **mesin approval generik HCIS** dgn `Source_Type = ROLE` | Rancangan | Sudah ada dan teruji; tidak perlu jalur persetujuan buatan sendiri |
| K17 | Bucket kasus nyangkut **memblokir** penguncian cycle | Rancangan | Kalau hanya informatif, akan diabaikan dan baru ketahuan saat hasil keluar |
| K18 | Bobot **di dalam** satu kategori penilai (mis. Atasan-1 70 / Atasan-2 30) ditetapkan admin **per peserta**, bukan di master | User | Jumlah atasan berbeda-beda per orang, dan hanya admin yang tahu atasan mana yang lebih berbobot |
| K19 | `ID_Level` peserta diambil lewat `View_Golongan_Sub_Golongan_Level_Jabatan`, bukan langsung dari `Karyawan` | User | `Karyawan` tidak punya kolom `ID_Level`; jalurnya `ID_Level_Jabatan` → view → `ID_Level` |
| K20 | `Kode_Perusahaan` **tidak** dimodelkan dulu di Cycle | User | Semua level dan seluruh 958 karyawan aktif ada di perusahaan `001`; bentuk sekarang sudah benar |
| K21 | `N_HRIS_Organization_Relationship` diisi lewat **endpoint konverter** dari `HRIS_Approval_Flow` dan `KPI_Approval_Flow` | User | Struktur organisasi tidak pernah dibangun; approval flow adalah satu-satunya sumber yang ada |
| K22 | Nama tabel PA punya **satu daftar resmi** di bagian 7; nama lain dinyatakan ditinggalkan | User | Dua dokumen sempat memakai nama berbeda untuk tabel yang sama |
| K23 | Bobot pilar KPI vs kompetensi **tidak** disimpan di `N_PA_Rater_Policy`; punya cetak biru `N_PA_Pillar_Weight` dan salinan siklus `N_PA_Cycle_Pillar_Weight_Snapshot` yang **boleh diubah setelah kunci** | User | Bobot pilar tidak menentukan siapa mengisi apa; admin harus bisa mengubahnya setelah semua orang menilai lalu hitung ulang dari skor mentah, dan itu mustahil kalau ikut dibekukan bersama aturan penilai |
| K24 | Tabel `Policy` diberi nama `N_PA_Rater_Policy` | User | Setelah bobot pilar keluar, isinya murni aturan penilai; nama generik mengundang aturan non-penilai ikut ditumpuk ke sana |
| K26 | **Aturan disalin ke siklus, kamus tidak.** `Rater_Policy`, `Pillar_Weight`, `Competency_Requirement`, dan `Score_Range` punya salinan siklus; `Rater_Category`, `Competency_Type`, `Competency`, dan `Competency_Kode_Soal` tidak | User | Kamus menyatakan arti, dan arti tidak boleh berbeda antar siklus — kode `FRUITS` yang berarti K01 di satu siklus dan K02 di siklus lain membuat hasil tidak bisa dibandingkan. Kamus yang keliru diperbaiki global. Jejaknya sudah aman karena `N_PA_Assignment_Score` menyimpan `Kode_Soal_Sumber` mentah dan `Id_Competency` hasil terjemahannya sekaligus |
| K27 | Master kompetensi HCIS tetap acuan; isi paket HC Learn **ditarik otomatis** saat pemilihan, tidak diketik ulang | User | Kalau master diturunkan dari paket, tiap paket otomatis dianggap lengkap dan ketidakcocokan dengan matriks jadi mustahil terdeteksi. Yang manual hanya memetakan kode soal baru, sekali per kode |
| K28 | Status siklus punya **master tersendiri** `N_PA_Cycle_Status` berisi tujuh status; `KALKULASI`/`KALIBRASI`/`REVIEW` dibuang | User | Tiga dokumen memuat tiga daftar berbeda dan `Status_Cycle` cuma `varchar`, jadi dokumen mana pun yang kebetulan dibaca sebuah sesi menjadi kebenaran bagi yang dibangun berikutnya. Master menutup celah itu di satu tempat yang dibaca sama oleh semua sesi |
| K29 | Master pita nilai membawa `Target_Persen` untuk *forced distribution* | User | Halaman Kalibrasi membandingkan porsi aktual tiap grade dengan targetnya; angka itu sifat pita grade-nya sendiri, jadi kolom dan bukan tabel |
| K30 | `N_PA_Cycle` boleh punya tanggal kosong selama `DRAFT`, dan membawa kolom `Tahun` | User | Mockup memuat siklus draft bertahun 2027 tanpa tanggal; memaksa NOT NULL berarti admin mengarang tanggal yang tak bisa dibedakan dari tanggal sungguhan |
| K31 | **Tiap kolom berisi daftar nilai tetap wajib punya master ber-FK**, bukan `varchar` bebas — meski daftarnya tidak akan tumbuh | User | Risikonya bukan pertumbuhan melainkan salah eja lintas sesi. `COMPETENCY` alih-alih `KOMPETENSI` akan diterima `varchar` lalu diam-diam tidak cocok dengan apa pun. Membalik alasan di README 067 yang menyimpulkan sebaliknya |
| K32 | Lapisan ketiga berbentuk **jarang** — hanya menyimpan yang menyimpang — bukan materialisasi penuh per peserta | User | Materialisasi penuh menaruh risikonya di jalur **tulis**: satu bug penyemaian menaburkan ribuan baris salah yang tampak wajar dan hanya bisa dibereskan dengan migrasi data. Bentuk jarang memindahkannya ke jalur **baca**: satu resolver, salah semuanya sekaligus dan kentara, diperbaiki dengan deploy kode tanpa menyentuh data. Efek sampingnya menguntungkan — resolusi wajib lewat satu tempat, jadi hanya ada satu hal yang perlu diuji, dan perintah verifikasi selisih lapisan 2 vs 3 tidak lagi diperlukan karena jalur bakunya tidak menyimpan apa pun yang bisa berbeda |
| K25 | Konfigurasi berlapis **tiga**: cetak biru lintas siklus → snapshot per (siklus, level) → **baris per orang** yang disemai saat penjadwalan dan bertanda sesuai/menyimpang | User | Koreksi satu orang tidak boleh memaksa membatalkan seluruh siklus. Lapisan snapshot tetap ada karena penanda penyimpangan butuh acuan yang diam — cetak biru berubah antar siklus, jadi tidak bisa jadi pembanding, dan tanpa snapshot riwayat "siklus 1 dulu pakai aturan apa" hanya bisa ditebak dari suara terbanyak |

---

## 3. Lima prinsip

Semua keputusan detail diturunkan dari lima ini. Kalau salah satu berubah, banyak hal ikut berubah.

1. **Cetak biru per level → dimaterialkan per individu.** Matriks & bobot ditetapkan per level sebagai *bawaan*, lalu dibekukan per orang di dalam cycle. Admin selalu bisa mengoreksi per orang.
   *Kata PIC: "per level dulu blueprint-nya, tapi saat ditetapkan tetap per individu agar fleksibel".*

2. **Cycle adalah dua rentang tanggal.** Periode yang dinilai, dan jadwal pengisian. Frekuensi hanya label turunan.

3. **HCIS penampung, HC Learn alat test.** Bundel hasil disimpan utuh dan abadi, lalu dipetakan lewat pemeta bernomor versi.

4. **Karyawan hanya melihat kesimpulan akhir.** Tidak pernah ada tampilan per-penilai. Ini menghapus kebutuhan aturan "minimal N penilai sebelum skor ditampilkan", karena tidak ada agregat kecil yang dipertontonkan ke subjek.

5. **Konfigurasi, bukan konstanta.** Level, matriks, bobot, range nilai — semuanya tabel yang bisa diatur.

---

## 4. Arsitektur & alur

### Empat fase satu siklus

```
FASE 1 PENYIAPAN ──kunci──► FASE 2 PENGISIAN ──tutup──► FASE 3 PERHITUNGAN ──terbit──► FASE 4 PUBLIKASI
   admin                        penilai                     sistem                        admin
   · cycle & 2 rentang          · assignment di HCIS        · agregasi 4 tahap            · kalibrasi wajib
   · matriks & bobot            · form di HC Learn          · tarik nilai KPI             · review & approval
   · penetapan penilai          · bundel kembali            · final score & grade         · terbit per orang
   · bucket                     · pengingat in-app          · redistribusi bobot          · HAV Map & riwayat
        │
        └─ bucket WAJIB bersih sebelum cycle boleh dikunci
```

Tiga gerbang mengunci hal yang berbeda: setelah **kunci** penilai tidak bisa diubah, setelah **tutup** nilai tidak bisa masuk, setelah **terbit** karyawan bisa melihat.

### Status cycle

Tujuh status, dan **daftar resminya ada di tabel `N_PA_Cycle_Status`**, bukan di dokumen ini. Baris di bawah hanya cerminan.

```
DRAFT → PREPARED → LOCKED → OPEN → CLOSED → PUBLISHED → ARCHIVED
```

`KALKULASI`, `KALIBRASI`, dan `REVIEW` yang dulu tercantum di sini **sudah dibuang** (K28). Kemajuan perhitungan dibaca dari `N_PA_Result.Status_Hasil` per peserta, dan review memakai mesin approval generik yang punya statusnya sendiri (K16) — menyalinnya ke status siklus melahirkan dua jawaban untuk satu pertanyaan.

Mockup PIC hanya menampilkan empat lencana: Draft, Active, Closed, Archived. Itu penyederhanaan tampilan, bukan pertentangan — `ACTIVE` menaungi `PREPARED`, `LOCKED`, dan `OPEN`. Pemetaannya tersimpan sebagai data di kolom `Kode_Badge`.

### Alur ujung ke ujung

```
Master Level ─┐
Matriks rater ├─► Cetak biru cycle ──► Materialisasi peserta (±958)
Bobot/level   │        (Draft)                    │
Range nilai  ─┘                                   │
                                                  ▼
KPI_Approval_Flow_Performance ──────────► Usulan penilai
  · Atasan-1 = Approver order_flow 1              │
  · Atasan-2 = Approver order_flow 2              ▼
  · Bawahan  = kebalikan Atasan-1        Admin tetapkan per orang ──► BUCKET
  · Peer     = se-Atasan-1                        │                   (tanpa atasan, level
  · Self     = diri sendiri                       │                    NOT SET, peer kosong,
                                                  ▼                    resign)
                                            KUNCI CYCLE
                                          (snapshot beku)
                                                  │
                                                  ▼
Penilai buka assignment di HCIS ──mint token──► HC Learn (isi form di sana)
                                                  │
                        ┌─────────────────────────┘
                        ▼
        N_PA_Assignment_Payload (mentah, abadi)
                        │ pemeta v1/v2…
                        ▼
        N_PA_Assignment_Score (skor per butir)
                        │
                        ▼
        agregasi 4 tahap ──► Nilai Kompetensi
                        │
  KPI (akumulasi bulan periode cycle) ──┤
                        ▼
              FINAL SCORE + GRADE SISTEM
                        │
                  KALIBRASI (wajib tiap cycle, lapisan terpisah)
                        │
                  REVIEW / APPROVAL (approval generik, Source_Type=ROLE)
                        │
                  PUBLIKASI per orang
                   │              │
                   ▼              ▼
          Karyawan lihat    HAV Map · Distribusi · Riwayat (admin)
```

---

## 5. Sumber relasi penilai

HCIS **tidak punya bagan organisasi**. Tabel `Karyawan` (52 kolom) tidak punya kolom atasan sama sekali. Sumber yang dipakai: **`KPI_Approval_Flow_Performance`**.

```
Kolom: Id_Approval_Flow, Id_Divisi, Id_Level,
       Kode_Karyawan_Approver, Kode_Karyawan_Requester, order_flow, Status
```

| Peran penilai | Diturunkan dari | Keandalan |
|---|---|---|
| **Self** | Diri sendiri | Tidak perlu tabel |
| **Atasan 1** | `Approver` dengan `order_flow = 1` | Langsung terpakai |
| **Atasan 2** | `Approver` dengan `order_flow = 2` | **Nyaris kosong** — hanya 7 baris di dev |
| **Bawahan** | Kebalikan Atasan 1 | **Butuh plafon** — 30 anak buah = 30 penilai |
| **Rekan Kerja** | Sesama `Requester` di bawah `Approver` yang sama | Lintas divisi harus ditambah manual |

**Semua di atas hanya USULAN.** Sistem tidak pernah menetapkan sendiri; admin menetapkan per orang (K10).

### Kandidat lain yang sudah ditolak

| Tabel | Kenapa tidak dipakai |
|---|---|
| `HRIS_Karyawan_Team` | 108 baris, 14 leader, 73 anggota — terlalu sempit |
| `KPI_Karyawan_Team` | 56 baris |
| `N_AMS_User_Leader_Teams` | **0 baris.** Bentuknya bagus (Relation_Type, Effective dates, Is_Primary, scope) tapi tidak pernah dipakai — jangan tertukar sebagai sumber |

---

## 6. Integrasi HC Learn

### 6.1 Pola yang sudah berjalan di LMS

```
HCIS ──mint token bertanda tangan──► redirect ke Link_Ujian (UI HC Learn)
                                            │ karyawan mengerjakan DI SANA
HCIS ◄──POST /exam/complete (HMAC guard)────┘
      payload: Kode_Karyawan, Id_Penjadwalan_Integrasi, Id_Ujian_Token, Score
```

- Auth: HMAC-SHA512 lewat `App\Helpers\SignatureHelper` — header `X-HC-Public`, `X-HC-Timestamp`, `X-HC-Nonce`, `X-HC-Body-Hash`, `X-HC-Signature`.
- Config: `services.frans.hclearn_api.{domain,public,secret}` (env `HCLEARN_API_*`).
- Callback masuk lewat **satu URL tetap**; `LmsThirdPartyCallbackController` mendeteksi jenisnya dari token.
- Rute: `routes/LMS/Integration/LmsThirdPartyApi.php`, `routes/LMS/SOP/Integration/SopThirdPartyApi.php`.

**Kontrak saat ini hanya mengirim satu angka `Score` (0–100)** — tidak cukup untuk PA.

### 6.2 Contoh bentuk payload yang diperkirakan

Diberikan user 2026-08-29. **Ini payload REKRUTMEN (`api/v2/nilai-hasil-tes-rekrutmen/…`), bukan kontrak PA** — dipakai sebagai acuan bentuk saja.

```json
{
  "success": true,
  "status": 200,
  "message": "Data Ditemukan",
  "result": {
    "kandidat": {
      "Nama_Kandidat": "UJI MT 06",
      "Jenis_User": "eksternal",
      "Nama_Jabatan": "-",
      "Job_Level": "Semua Level",
      "Started_At": "2026-08-28 11:20:14.000",
      "Finished_At": "2026-08-29 23:59:00.000",
      "Waktu_Mulai_Akses": "2026-08-28 11:27:21.410",
      "Waktu_Selesai_Akses": "2026-08-28 11:30:16.313",
      "Total_Nilai": 66.2,
      "Ambang_Batas_Nilai": 75,
      "Status_Kelulusan": "Tidak Lulus",
      "Warna": "#d01616",
      "Flag_Camera": "Y",
      "Nama_Tes": "General Reasoning Test",
      "Nama_Paket": "General Reasoning Test",
      "Kode_Hak_Akses": "TIU",
      "Id_Jenis_Indikator_Kraeplin": null,
      "No_Peserta": "CK280826-000003",
      "Tgl_Lahir": "2001-08-06 00:00:00.000",
      "No_HP": "628110000006",
      "Email": "ujimt06@evotest.local",
      "Jenis_Kelamin": "Laki-Laki",
      "Posisi_Dilamar": "SPV FINISH GOOD"
    },
    "is_multi": false,
    "kode_akses": "TIU",
    "tabs": [
      {
        "Id_Ujian_Nilai_Akhir": "O1mPl90BDlrqv5OQ1oGxdZXkpneMYARAjDJm30BwV24LPKa9bWy7jNz6gaQe6Adz",
        "Label": "TIU - General Reasoning Test",
        "Kode": "TIU",
        "Id_Indikator": "23",
        "Total_Nilai": 66.2,
        "Status_Kelulusan": "Tidak Lulus",
        "Ambang_Batas_Nilai": 75,
        "Warna": "#d01616",
        "Summary": null,
        "domain": [
          {
            "Id_Nilai_Jenis_Soal": "0qNo456gLbzodnaN6l9xKYZekrV7vRYOMx8Pm2p45jO01DX3BQAWwGyqMaygkXMx",
            "Jenis_Soal": "Pengenalan Objek",
            "Bobot_Penilaian": 20,
            "Skor_Maksimal": 100,
            "Score_Peserta": 41,
            "Status_Interpretation": "Low",
            "Flag_Spesial_Case": null,
            "Warna": "#d01616",
            "Remaks": "<p>Menunjukkan kompetensi yang masih memerlukan pengembangan…</p>",
            "Flag_Interpretation": "Y",
            "Keterangan": "Identifikasi Buah",
            "Kode_Soal": "FRUITS"
          },
          { "…": "Kewarganegaraan, Bobot 30, Score 60, Average, Kode_Soal KWN" },
          { "…": "Pengetahuan Acak, Bobot 50, Score 80, High, Kode_Soal PA" }
        ]
      }
    ]
  },
  "meta": {
    "request_id": "…", "trace_id": "…", "correlation_id": "…",
    "timestamp": "2026-08-29T06:38:08.040223Z", "timezone": "Asia/Jakarta",
    "version_api": "v2", "environment": "local", "app_version": "1.0.0",
    "path": "api/v2/nilai-hasil-tes-rekrutmen/…", "method": "GET",
    "response_status": 200, "success": true, "execution_time_ms": 204.06,
    "data_summary": { "records_returned": 4 }
  }
}
```

### 6.3 Sepuluh implikasi dari payload ini

| # | Temuan | Konsekuensi |
|---|---|---|
| **P01** | `tabs[].domain[]` = **skor per kompetensi**. `Kode_Soal` adalah kunci pemetaan | Petakan `Kode_Soal` → `N_PA_Competency.Kode`. Simpan `Kode_Soal` mentah supaya butir tak dikenali tetap terlihat |
| **P02** | `Skor_Maksimal: 100` — kompetensi kembali pada **skala 0–100, bukan 1–5** | **Membatalkan asumsi Bagian 3.** Kedua sumbu kini perlu konversi. **Jangan hardcode skalanya** — baca `Skor_Maksimal` dari payload dan normalisasi, karena nilainya bisa berbeda per tes |
| **P03** | Ada **dua sistem bobot** yang tidak boleh tertukar | `Bobot_Penilaian` (20/30/50) = bobot **antar-kompetensi di dalam satu tes**, ditentukan HC Learn. `N_PA_Cycle_Rater_Policy_Detail_Snapshot` = bobot **antar-kategori penilai**, ditentukan HCIS. Dua sumbu berbeda, keduanya sah |
| **P04** | Rumus `Total_Nilai` **terverifikasi secara aritmetika**: `(41×20 + 60×30 + 80×50) ÷ 100 = 66.2`, dan `Σ Bobot_Penilaian = 100` | Bisa dipakai sebagai uji-silang saat memproyeksikan bundel — kalau hasil hitung ulang ≠ `Total_Nilai`, pemetaannya salah |
| **P05** | **Payload tidak menyebut siapa menilai siapa.** Hanya ada satu orang (`kandidat` = peserta tes) | Di 360°, peserta tes adalah **penilai**, yang dinilai orang lain. Korelasi **harus** dari token milik HCIS, bukan dari isi payload. Ini pertanyaan kontrak terpenting ke HC Learn |
| **P06** | Ini payload **rekrutmen** — `Posisi_Dilamar`, `Jenis_User: eksternal`, `Status_Kelulusan` | Konsep lulus/tidak lulus (`Ambang_Batas_Nilai`, `Status_Kelulusan`) **tidak berlaku di PA** — jangan diproyeksikan |
| **P07** | Ini **GET (tarik)**, bukan POST callback (dorong) | Pola yang diusulkan: callback ringan bilang "selesai", lalu HCIS **menarik** detail lewat GET. Kalau pemetaan gagal, bisa ditarik ulang tanpa minta HC Learn kirim ulang |
| **P08** | `Status_Interpretation` (Low/Average/High) + `Remaks` (HTML) = narasi siap pakai per kompetensi | Bisa jadi bahan awal Strength / Development Area. **HTML wajib disanitasi** sebelum ditampilkan |
| **P09** | `tabs[]` jamak + `is_multi` | Satu tarikan bisa membawa beberapa tes. Cocok sekali dengan matriks dimensi slide 9: Manajerial, Core Value, Behavior, Qualitative sebagai tab berbeda. **Perlu dikonfirmasi ke HC Learn** |
| **P10** | Skor desimal (`66.2`); id berupa token 64 karakter | Kolom `decimal` presisi eksplisit. Simpan `Id_Ujian_Nilai_Akhir` / `Id_Nilai_Jenis_Soal` sebagai kunci **idempotensi** supaya tarikan ganda tidak menghasilkan proyeksi ganda |

---

## 7. Model data

40 tabel berawalan `N_PA_` (mengikuti preseden `N_SOP_`: submodul memakai awalannya sendiri).
Wajib mengikuti `docs/database/table-design-rules.md`: `Flag_<Makna> char(1)` Y/T, `int` untuk id, `datetime2`, panjang `varchar` eksplisit, presisi `decimal` eksplisit, **tanpa CHECK constraint** untuk daftar nilai bisnis, kolom audit sesuai lifecycle.

### Daftar nama resmi

Bagian ini adalah **satu-satunya sumber nama tabel PA**. Dokumen lain merujuk ke sini, tidak mendefinisikan nama sendiri.

Polanya tetap, supaya nama tabel berikutnya bisa ditebak tanpa membuka daftar:

```
N_PA_<Entitas>                       cetak biru / kamus, lintas siklus
N_PA_Cycle_<Entitas>_Snapshot        salinan milik satu siklus, per level
N_PA_Participant_<Entitas>           salinan milik satu peserta
N_PA_Result_<Entitas>                rincian hasil per peserta
```

**`Snapshot` berarti "salinan yang diambil saat persiapan siklus", bukan otomatis "beku".** Kapan salinan itu berhenti boleh diubah adalah aturan daur hidup masing-masing tabel, bukan bagian dari namanya. `N_PA_Cycle_Rater_Policy_Snapshot` membeku saat siklus dikunci; `N_PA_Cycle_Pillar_Weight_Snapshot` sengaja tetap boleh diubah sesudahnya.

**Setiap kolom berisi daftar nilai tetap wajib punya master ber-FK** (K31), termasuk yang daftarnya tidak akan tumbuh. Sembilan sudah ada — `N_PA_Cycle_Status`, `N_PA_Rater_Policy_Status`, `N_PA_Score_Type`, `N_PA_Score_Usage`, `N_PA_Participant_Status`, `N_PA_Assignment_Status`, `N_PA_Assignment_Source`, `N_PA_Result_Status`, `N_PA_Result_Note_Type`. Tidak ada lagi yang menyusul.

**Batasnya juga sudah teruji.** Kolom metode nilai KPI sempat diusulkan dengan alasan K31, lalu dibatalkan user: K31 berlaku untuk kolom yang nilainya BERBEDA-BEDA dari daftar tetap. Kolom yang selamanya berisi satu nilai tidak menyimpan informasi, jadi tidak ada daftarnya dan tidak perlu masternya.

**Tidak semua tabel punya ketiga lapisan.** Yang menentukan adalah K26: aturan disalin, kamus tidak. Dan di antara yang disalin pun, hanya yang bisa berbeda per orang yang punya lapisan ketiga.

#### Lapisan 1 — cetak biru dan kamus, lintas siklus

| # | Tabel | Status | Jenis | Peran |
|---|---|---|---|---|
| 1 | `N_PA_Rater_Policy` | **ada di DB** (DDL-064 r3) | aturan | Blueprint aturan penilai per level |
| 2 | `N_PA_Rater_Policy_Detail` | **ada di DB** (DDL-064 r3) | aturan | Bobot & aturan jumlah penilai per kategori |
| 3 | `N_PA_Rater_Category` | **ada di DB** (DDL-064 r3) | kamus | Master ATASAN/BAWAHAN/PEER/SELF |
| 4 | `N_PA_Rater_Policy_Status` | **ada di DB** (DDL-070 r1) | kamus | Master DRAFT/ACTIVE/INACTIVE untuk Rater Policy |
| 5 | `N_PA_Pillar_Weight` | **ada di DB** (DDL-065 r1) | aturan | Pembagian pilar KPI vs kompetensi per level |
| 6 | `N_PA_Competency_Type` | **ada di DB** (DDL-067 r2) | kamus | Master jenis: MANAJERIAL/CORE_VALUE/BEHAVIOR/KUALITATIF |
| 7 | `N_PA_Competency` | **ada di DB** (DDL-067 r2) | kamus | Master 24 kompetensi |
| 8 | `N_PA_Competency_Kode_Soal` | **ada di DB** (DDL-067 r2, kosong) | kamus | Pemetaan `Kode_Soal` HC Learn → kompetensi |
| 9 | `N_PA_Competency_Requirement` | **ada di DB** (DDL-067 r2, kosong) | aturan | Jenis kompetensi apa yang wajib per level |
| 10 | `N_PA_Score_Range` | **ada di DB** (DDL-067 r2, kosong) | aturan | Pita nilai: konversi, HAV Map, grade |
| 11 | `N_PA_Hav_Quadrant` | **ada di DB** (DDL-068 r1) | kamus | Sembilan kotak HAV Map; menamai kombinasi tingkat |
| 12 | `N_PA_Score_Type` | **ada di DB** (DDL-070 r1) | kamus | Master KPI/KOMPETENSI/FINAL |
| 13 | `N_PA_Score_Usage` | **ada di DB** (DDL-070 r1) | kamus | Master HAVMAP/GRADE/KONVERSI |
| 14 | `N_PA_Participant_Status` | **ada di DB** (DDL-072 r1) | kamus | Master status peserta: TERTUNDA / DIIKUTKAN / DIKECUALIKAN |
| 15 | `N_PA_Assignment_Status` | **ada di DB** (DDL-075 r1) | kamus | Master status penugasan: BELUM_MULAI / BERJALAN / SELESAI / DIBATALKAN |
| 16 | `N_PA_Assignment_Source` | **ada di DB** (DDL-075 r1) | kamus | Master asal penugasan: USULAN / MANUAL |
| 17 | `N_PA_Result_Status` | **ada di DB** (DDL-080 r1) | kamus | Master status hasil: BELUM_DIHITUNG / DIHITUNG / TERBIT / TERTAHAN |
| 18 | `N_PA_Result_Note_Type` | **ada di DB** (DDL-080 r1) | kamus | Master jenis catatan hasil: STRENGTH / DEVELOPMENT_AREA |

#### Lapisan 2 — salinan milik satu siklus

| # | Tabel | Status | Peran |
|---|---|---|---|
| 19 | `N_PA_Cycle` | **ada di DB** (DDL-066 r2) | Header siklus |
| 20 | `N_PA_Cycle_Status` | **ada di DB** (DDL-069 r1) | Master tujuh status siklus |
| 21 | `N_PA_Cycle_Rater_Policy_Snapshot` | **ada di DB** (DDL-071 r1) | Salinan header Rater Policy per (siklus, level) |
| 22 | `N_PA_Cycle_Rater_Policy_Detail_Snapshot` | **ada di DB** (DDL-071 r1) | Salinan bobot & aturan kategori |
| 23 | `N_PA_Cycle_Pillar_Weight_Snapshot` | **ada di DB** (DDL-071 r1) | Salinan pembagian pilar; **tidak** ikut beku saat kunci |
| 24 | `N_PA_Cycle_Competency_Requirement_Snapshot` | **ada di DB** (DDL-071 r1) | Salinan kebutuhan kompetensi per (siklus, level) |
| 25 | `N_PA_Cycle_Score_Range_Snapshot` | **ada di DB** (DDL-071 r1) | Salinan pita nilai yang berlaku di siklus itu |
| 26 | `N_PA_Cycle_Question_Package` | **ada di DB** (DDL-073 r1) | Paket HC Learn yang dipilih untuk (siklus, level) |
| 27 | `N_PA_Cycle_Question_Package_Item` | **ada di DB** (DDL-073 r1) | Salinan daftar `Kode_Soal` paket saat dipilih — kontrak harapan |

#### Lapisan 3 — penyimpangan milik satu peserta, JARANG (K32)

| # | Tabel | Status | Peran |
|---|---|---|---|
| 28 | `N_PA_Participant` | **ada di DB** (DDL-072 r1) | Peserta + kondisi organisasi yang dibekukan |
| 29 | `N_PA_Participant_Rater_Override` | **ada di DB** (DDL-074 r1) | Aturan penilai satu peserta, **hanya bila menyimpang** |
| 30 | `N_PA_Participant_Pillar_Override` | **ada di DB** (DDL-074 r1) | Bobot pilar satu peserta, hanya bila menyimpang |
| 31 | `N_PA_Participant_Requirement_Override` | **ada di DB** (DDL-074 r1) | Kebutuhan kompetensi satu peserta, hanya bila menyimpang |

Ketiganya **normalnya kosong**. Baris lahir hanya saat admin mengubah aturan untuk satu orang; selebihnya nilainya datang dari lapisan dua. Penggabungannya wajib lewat satu tempat, `PaParticipantRuleResolver` — tidak ada kode lain yang boleh membaca tabel salinan siklus untuk keperluan per orang (K32).

`N_PA_Cycle_Score_Range_Snapshot` sengaja berhenti di lapisan dua. Pita grade tidak masuk akal berbeda per orang — dua orang dengan nilai sama harus mendapat grade sama.

#### Pelaksanaan dan hasil

| # | Tabel | Status | Peran |
|---|---|---|---|
| 32 | `N_PA_Assignment` | **ada di DB** (DDL-075 r1) | Penugasan penilai final |
| 33 | `N_PA_Assignment_Integration` | **ada di DB** (DDL-075 r1) | Token ujian HC Learn; tabel sendiri, bukan kolom penugasan |
| 34 | `N_PA_Assignment_Payload` | **ada di DB** (DDL-076 r1) | Bundel mentah HC Learn; sisip saja berlaku pada ISI-nya |
| 35 | `N_PA_Assignment_Score` | **ada di DB** (DDL-076 r1) | Skor per butir hasil proyeksi pemeta |
| 36 | `N_PA_Result` | **ada di DB** (DDL-080 r1) | Hasil akhir per peserta; `Final_Score` sengaja tanpa plafon 100 |
| 37 | `N_PA_Result_Rater_Category` | **ada di DB** (DDL-081 r1) | Jejak nilai dan bobot yang BENAR-BENAR terpakai per kategori |
| 38 | `N_PA_Result_Competency` | **ada di DB** (DDL-081 r1) | Nilai per kompetensi; kategori kosong = agregat lintas kategori |
| 39 | `N_PA_Calibration_History` | **ada di DB** (DDL-081 r1) | Riwayat kalibrasi; sisip saja, tanpa `Flag_Aktif` maupun `Updated_*` |
| 40 | `N_PA_Result_Note` | **ada di DB** (DDL-081 r1) | Strength / Development Area; asal-usul catatan ikut disimpan |

### Nama yang sudah ditinggalkan

Nama di kolom kiri **tidak boleh dipakai lagi**. Semuanya pernah muncul di draf awal dokumen ini sebelum DDL-2026-064 dijalankan.

| Nama lama | Nama resmi | Sebab |
|---|---|---|
| `N_PA_Policy` | `N_PA_Rater_Policy` | Setelah bobot pilar keluar, isinya murni aturan penilai (K24) |
| `N_PA_Policy_Detail` | `N_PA_Rater_Policy_Detail` | Idem |
| `N_PA_Master_Rater_Type` | `N_PA_Rater_Category` | DDL-064 sudah membuatnya; nama fisik menang |
| `N_PA_Master_Competency` | `N_PA_Competency` | Awalan `Master_` tidak dipakai `N_PA_Rater_Category`; diseragamkan |
| `N_PA_Master_Score_Range` | `N_PA_Score_Range` | Idem |
| `N_PA_Cycle_Rater_Weight` | `N_PA_Cycle_Rater_Policy_Detail_Snapshot` | Isinya salinan beku `N_PA_Rater_Policy_Detail`, bukan tabel bobot tersendiri |
| `N_PA_Cycle_Composition` | `N_PA_Cycle_Pillar_Weight_Snapshot` | **Bukan** salinan Policy. Isinya pembagian pilar KPI vs kompetensi — sempat keliru digabungkan ke salinan Policy, lalu dipisahkan lagi karena bobot pilar tidak boleh ikut dibekukan (K23) |
| `N_PA_Participant_Rater_Policy_Detail` | `N_PA_Participant_Rater_Override` | Isinya bukan salinan penuh melainkan penyimpangan; nama lama menjanjikan baris untuk setiap peserta (K32) |
| `N_PA_Participant_Pillar_Weight` | `N_PA_Participant_Pillar_Override` | Idem |
| `N_PA_Participant_Competency_Requirement` | `N_PA_Participant_Requirement_Override` | Idem, sekaligus dipendekkan |
| `N_PA_Rater_Assignment` | `N_PA_Assignment` | Menjaga keluarga `_Payload`/`_Score` tetap pendek; "assignment" di PA hanya berarti penugasan penilai |

Akhiran `_Snapshot` sengaja dipertahankan walau panjang. Nama seperti `N_PA_Cycle_Rater_Policy` saja akan terbaca sebagai tabel penghubung siklus↔policy, padahal isinya salinan. Kesalahpahaman itu merusak jaminan "promosi di tengah siklus tidak menembus garis kunci".

> **Konvensi kolom.** Sketsa kolom di bawah ditulis ringkas. Nama fisik final wajib mengikuti DDL-2026-064: primary key `Id_PA_<Entitas>`, foreign key `PA_<Entitas>_Id`, audit `Created_At`/`Created_By`/`Updated_At`/`Updated_By`, dan kolom lifecycle memakai bahasa Inggris (`Locked_At`, `Published_At`, `Snapshot_At`) sementara kolom bisnis tetap Indonesia (`Berlaku_Dari`, `Bobot_Persen`).

### A · Master — lintas siklus

**`N_PA_Competency`** (~24 baris)
`Id_Competency` · `Kode` (K01…) · `Nama` · `Jenis` (MANAJERIAL/CORE_VALUE/BEHAVIOR/KUALITATIF) · `Urutan` · `Flag_Aktif`

**`N_PA_Rater_Category`** — **sudah ada** (DDL-064 r2, 4 baris terisi)
`Id_PA_Rater_Category` · `Kode_Kategori` (ATASAN/BAWAHAN/PEER/SELF) · `Nama_Kategori` · `Deskripsi` · `Flag_Aktif` · audit
> **Atasan-1 dan Atasan-2 bukan kategori terpisah.** Keduanya masuk `ATASAN`; pembeda bobotnya (mis. 70/30) ditetapkan admin **per peserta** di `N_PA_Assignment.Bobot_Dalam_Kategori`, bukan di master. Alasannya di K18.

**`N_PA_Score_Range`** (~15 baris)
`Id_Score_Range` · `Score_Type` (KPI/KOMPETENSI/FINAL) · `Score_Usage` (HAVMAP/GRADE/KONVERSI) · `Kode` · `Nama` · `Nilai_Dari` · `Nilai_Sampai` · `Nilai_Hasil` · `Urutan` · `Warna` · `Flag_Aktif`
> Satu tabel melayani tiga kegunaan dari nilai yang sama: konversi sumbu, pita 3 tingkat HAV Map, dan pita grade.

### B · Siklus — cetak biru yang dibekukan

**`N_PA_Cycle`** (1 baris/siklus)
`Id_Cycle` · `Nama` · `Periode_Dinilai_Dari` · `Periode_Dinilai_Sampai` · `Pengisian_Dari` · `Pengisian_Sampai` · `Status` · `Id_Cycle_Sumber` · audit + `Locked_At/By` · `Published_At/By`

**`N_PA_Cycle_Rater_Policy_Detail_Snapshot`** (~60 baris/siklus)
`Id_Cycle` · `ID_Level` · `Id_Rater_Type` · `Bobot_Persen` · `Min_Rater` · `Maks_Rater` · `Flag_Aktif`
> **Adanya baris = jenis penilai itu berlaku di level tersebut.** Officer Specialist tanpa baris BAWAHAN → otomatis 180°, tanpa penanda terpisah.

**`N_PA_Cycle_Rater_Policy_Snapshot`** (~16 baris/siklus)
`Id_Cycle` · `ID_Level` · `Bobot_Kpi` · `Bobot_Kompetensi`

### C · Peserta & pengisian

**`N_PA_Participant`** (~958/siklus)
`Id_Participant` · `Id_Cycle` · `Kode_Karyawan` · `ID_Level_Snapshot` · `ID_Divisi_Sub_Divisi_Snapshot` · `Kode_Jabatan_Snapshot` · `Status` · `Alasan_Dikecualikan` · `Flag_Terbit` · `Waktu_Terbit`

**`N_PA_Assignment`** (~5.000/siklus)
`Id_PA_Assignment` · `PA_Participant_Id` (subjek) · `Kode_Perusahaan_Penilai` + `Kode_Karyawan_Penilai` · `Nama_Penilai_Saat_Cycle` · `PA_Rater_Category_Id` · **`Bobot_Dalam_Kategori`** `decimal(5,2)` · `Sumber_Penugasan` · `Alasan_Manual` · `Organization_Relationship_Id` (NULL bila manual) · `Status_Penugasan` · `Due_At` · `Started_At` · `Finished_At`
> **`Id_Cycle` tidak diduplikasi** — siklusnya sudah melekat pada peserta. **`Id_Ujian_Token` tidak di sini** melainkan di `N_PA_Assignment_Integration`, mengikuti `N_LMS_Penjadwalan_Integrasi` dan `N_SOP_Penjadwalan_Integrasi` yang tabel penugasannya sama sekali tak punya kolom token. Penugasan menunjuk **kategori** penilai, bukan baris salinan siklus, karena sejak K32 bobot efektif bisa datang dari override.

**`N_PA_Assignment_Integration`** — satu baris aktif per penugasan
`Id_PA_Assignment_Integration` · `PA_Assignment_Id` · `PA_Cycle_Question_Package_Id` · `Id_Ujian_Token` (unik saat terisi) · `Payload_Json` (balasan mint, sumber `Link_Ujian`) · `Started_At` · `Finished_At` · `Flag_Aktif`
> Baris baru hanya lahir saat paket soalnya diganti; kirim ulang selalu `UPDATE` baris yang sama, karena membuang token berarti hasil yang sedang dikirim HC Learn tak dikenali lagi. Callback mencari lewat token **tanpa** menyaring `Flag_Aktif`.
> `Bobot_Dalam_Kategori` adalah bobot penilai ini **relatif terhadap penilai lain dalam kategori yang sama untuk peserta yang sama**. Bakunya rata (dua atasan → 50/50); admin boleh mengubah (70/30). Σ per (peserta, kategori) harus 100, divalidasi saat kunci — lihat G9.

**`N_PA_Assignment_Payload`** (~5.000/siklus) — **sisip saja berlaku pada ISI-nya**
`Id_PA_Assignment_Payload` · `PA_Assignment_Id` · `Payload_Json` · `Payload_Hash` · `Id_Ujian_Nilai_Akhir` (unik saat terisi) · `Api_Version` · `Diterima_At` · `Flag_Terpeta` · `Mapper_Version`
> Lima kolom pertama tidak pernah di-`UPDATE`; `Flag_Terpeta` dan `Mapper_Version` memang berubah sesudah barisnya lahir, karena menggambarkan pemrosesan kita, bukan data mereka. Idempotensi bergantung pada `Id_Ujian_Nilai_Akhir`, **bukan** hash — hash unik berisiko menolak pengerjaan ulang sah yang isinya kebetulan sama.

**`N_PA_Assignment_Score`** (~75.000/siklus)
`Id_PA_Assignment_Score` · `PA_Assignment_Payload_Id` · `PA_Competency_Id` (NULL bila belum dikenali) · `Kode_Soal_Sumber` · `Jenis_Soal_Sumber` · `Skor` · `Skor_Maksimal` · `Bobot_Sumber` · `Jawaban_Teks` · `Mapper_Version` · `Urutan_Tampil` · `Flag_Aktif`
> **Tidak membawa penugasan** — sudah ditentukan oleh payload-nya. `Status_Interpretation` dan `Remaks` dibuang: yang pertama redundan dengan `PA_Competency_Id` NULL, yang kedua melebur ke `Jawaban_Teks`. Memetakan ulang menonaktifkan baris lama lalu menyisipkan baris v2, tidak menghapus.

### D · Hasil

**`N_PA_Result`** (~958/siklus)
`Id_Result` · `Id_Participant` · `Nilai_Kompetensi` · `Nilai_Kpi` · `Kpi_Periode_Dari` · `Kpi_Periode_Sampai` · **`Bobot_KPI_Terpakai`** · **`Bobot_Kompetensi_Terpakai`** · `Final_Score` · `Kode_Grade_Sistem` · `Kode_Grade_Final` · `Flag_Dikalibrasi` · `Kode_Kuadran_Hav` · `Dihitung_At`
> Dua kolom bobot terpakai itu wajib. Bobot pilar boleh berubah setelah pengisian selesai, jadi tiap hasil harus membawa sendiri bukti dia dihitung pakai angka berapa — kalau tidak, menghitung ulang menghapus keterangan hasil lama.

**`N_PA_Result_Rater_Category`** (~4.000/siklus)
`Id_Participant` · `PA_Rater_Category_Id` · `Nilai` · `Jumlah_Rater_Diharapkan` · `Jumlah_Rater_Mengisi` · `Bobot_Terpakai`
> Jejak redistribusi bobot antar-kategori — supaya bisa diaudit kenapa angkanya begitu. Bobot **di dalam** kategori tidak diringkas di sini; jejaknya tetap di baris `N_PA_Assignment` masing-masing.

**`N_PA_Result_Competency`** (~11.000/siklus)
`Id_Participant` · `Id_Competency` · `Id_Rater_Type` (NULL = agregat) · `Nilai`

**`N_PA_Calibration_History`** — **hanya sisip**
`Id` · `Id_Participant` · `Grade_Sebelum` · `Grade_Sesudah` · `Alasan` · `Performed_By` · `Waktu`

**`N_PA_Result_Note`** (~2.900/siklus)
`Id` · `Id_Participant` · `Jenis` (STRENGTH/DEVELOPMENT) · `Urutan` · `Teks` · `Diisi_Oleh` · audit

### Apa yang membeku saat siklus dikunci

```
BISA BERUBAH KAPAN SAJA          │  BEKU SETELAH KUNCI            │
Master_Rater_Type                │  Cycle_Rater_Weight            │  Perhitungan
Master_Score_Range      ──salin──►  Cycle_Composition     ──pakai──►  membaca snapshot,
HRIS_Level · Karyawan            │  Participant.level_snapshot    │  bukan master
                                 │                                │
        ✗ Promosi di tengah siklus tidak bisa menembus garis kunci
```

Tanpa snapshot, orang yang naik level di tengah siklus akan berpindah baris matriks — bobot penilaiannya berubah **setelah** para penilai selesai mengisi dengan asumsi lama.

### Tiga keputusan model

1. **Matriks dan bobot satu tabel, bukan dua.** Kalau dipisah, akan ada keadaan mustahil: matriks bilang peer berlaku sementara bobotnya nol.
2. **Level di-snapshot di `Participant`**, tidak dibaca dari `Karyawan` saat menghitung.
3. **`Assignment_Score` menyimpan `Kode_Soal_Sumber`** di samping `Id_Competency`. Butir tak dikenali tersimpan dengan `Id_Competency` NULL — terlihat dan bisa dilaporkan, bukan hilang.

---

## 8. Aturan perhitungan

### Empat tahap

```
Tahap 1  skor mentah per butir per penilai        (dari N_PA_Assignment_Score)
   ↓ rata-rata TERTIMBANG sesama kategori (Bobot_Dalam_Kategori)
Tahap 2  nilai per kategori penilai per kompetensi
   ↓ × bobot kategori hasil resolver (salinan siklus + N_PA_Participant_Rater_Override)
Tahap 3  NILAI KOMPETENSI
   ↓ × bobot pilar hasil resolver (salinan siklus + N_PA_Participant_Pillar_Override)
Tahap 4  FINAL SCORE ──► GRADE   +   KUADRAN HAV MAP
```

### Redistribusi bobot: normalisasi, bukan penambalan

```
Nilai = Σ(nilai_kategori × bobot) ÷ Σ(bobot kategori yang TERISI)
```

Contoh Manager (Self 10 · Atasan 45 · Peer 15 · Bawahan 30). Kalau peer kosong, pembagi jadi **85**, dan bobot efektifnya menjadi Self 11,8 · Atasan 52,9 · Bawahan 35,3. Sisa 15% terbagi proporsional dengan sendirinya — tanpa aturan tambahan, tanpa tabel penambal.

**Bahayanya:** hasilnya selalu terlihat sah walau hanya satu orang mengisi dari sepuluh yang seharusnya. Karena itu **`Min_Rater` adalah gerbangnya** — kalau jumlah pengisi di bawah ambang, peserta itu **tidak dihitung** dan masuk daftar tertahan untuk diputuskan admin, bukan menghasilkan angka yang tampak wajar. Ambangnya per (level × jenis penilai), jadi Atasan bisa wajib sementara Self boleh kosong.

### Mempertemukan dua skala

| Score_Type | Rentang | Pita yang sudah ada | Sumber |
|---|---|---|---|
| Nilai KPI | **50 – 125** | NP <75 · SY 75–89 · GD 90–105 · ET ≥106 | `KPI_Master_Performance` |
| Nilai kompetensi | **0 – 100** (per P02; `Skor_Maksimal` dari payload) | belum ada | HC Learn |
| Final score | belum ditetapkan | belum ada | hasil gabungan |

> **Terbuka.** Deck PIC menampilkan HAV Map dan radar pada skala **1–5** (skor KPI 4,6 / kompetensi 4,5), sementara payload HC Learn mengembalikan **0–100**. Skala kanonik final belum diputuskan. Yang sudah pasti: **jangan hardcode** — konversi lewat `N_PA_Score_Range`, dan baca `Skor_Maksimal` dari payload alih-alih mengasumsikannya.

### Kuadran HAV Map bukan turunan Final Score

Sumbunya adalah **nilai KPI dan nilai kompetensi secara terpisah**, masing-masing dipetakan ke pita tiga tingkat. Final Score hanya melahirkan grade.
Menggabungkan keduanya lebih dulu akan menghapus persis informasi yang membuat 9-box berguna: dua orang berskor akhir sama bisa duduk di kuadran yang sangat berbeda — KPI tinggi/kompetensi rendah (Top Performer) versus KPI rendah/kompetensi tinggi (Raw Diamond).

### Sumber nilai KPI

- Panggil `App\Services\KPI\KpiScoringService::accumulatedScore()` — yang sudah dipakai dashboard CEO & staf — dibatasi bulan-bulan periode siklus.
- **Jangan** membuat rumus sendiri: dua tempat yang memberi angka berbeda untuk orang yang sama menghancurkan kepercayaan pada sistem penilaian.
- Konsekuensi jujur: PA ikut menanggung sifat metode akumulasi, yang kurang tepat untuk KPI berbentuk rasio/persen. Utang yang sudah ada, bukan yang diciptakan PA — tapi PA membuatnya lebih terlihat.
- **Di-snapshot** ke `N_PA_Result` (K14).

### Perhitungan batch

Agregasi ±958 peserta di atas ±75.000 baris skor dijalankan **sekali** saat pengisian ditutup atau saat admin memicu hitung ulang, hasilnya tersimpan. Sistem ini sudah pernah kena CPU 100% karena dashboard approval menghitung agregat pada setiap permintaan; HAV Map dan halaman distribusi punya bentuk beban yang persis sama.

---

## 9. Penjaga (invarian)

| | Penjaga | Kalau tidak ada |
|---|---|---|
| G1 | Tumpang tindih periode ditolak **per karyawan** sebelum kunci | Satu bulan kerja dinilai dua kali dengan hasil berbeda |
| G2 | Setelah kunci, snapshot level & bobot tidak bisa berubah | Bobot bergeser di tengah jalan tanpa jejak |
| G3 | Bucket harus kosong sebelum siklus boleh dikunci | Peserta bolong baru ketahuan saat hasil keluar |
| G4 | `Assignment_Payload` sisip saja; hitung ulang menulis baris skor baru ber-versi | Perhitungan ulang menimpa bukti aslinya |
| G5 | Grade final kolom terpisah + riwayat hanya-sisip | Kalibrasi menghapus jejak nilai sistem — tidak bisa diaudit |
| G6 | Kueri sisi karyawan **selalu** menyaring status terbit | Hasil bocor sebelum disetujui |
| G7 | Σ bobot = 100 per (siklus, level), divalidasi saat simpan | Nilai akhir salah tanpa ada yang sadar |
| G8 | Proyeksi payload **idempoten** lewat `Id_Ujian_Nilai_Akhir` / `Id_Nilai_Jenis_Soal` | Tarikan ganda menghasilkan skor dobel |
| G9 | Σ `Bobot_Dalam_Kategori` = 100 per (peserta, kategori), divalidasi **saat kunci** | Bobot 70/30 yang lupa diisi jadi 70/0 — nilai kategori turun 30% tanpa ada yang sadar |
| G10 | Peserta yang `ID_Level`-nya tidak resolve lewat view golongan **memblokir** kunci | Peserta tanpa level tidak dapat Policy, lalu hilang diam-diam dari hasil |

G6 sengaja ditulis sebagai aturan **kueri**, bukan aturan tampilan. Kalau hasil hanya disembunyikan di layar, satu endpoint baru yang lupa menyaringnya akan membocorkan nilai yang belum disetujui — dan di modul penilaian kinerja, kebocoran seperti itu tidak bisa ditarik kembali.

---

## 10. Fakta database terverifikasi

Dibaca read-only dari `Web_HRIS` (development, host team311) pada **2026-08-29**.
**Sebaran datanya tidak mencerminkan produksi.** Yang bisa dipercaya adalah bentuk skemanya.

| Yang dicek | Hasil | Artinya untuk PA |
|---|---|---|
| Kolom atasan di `Karyawan` | **Tidak ada** dari 52 kolom | Relasi harus diturunkan dari approval flow |
| Karyawan aktif (`Aktif='Y'`) | 958 (total 965) | Basis populasi peserta |
| `HRIS_Karyawan_Team` | 108 baris · 14 leader · 73 anggota | Terlalu sempit jadi sumber organisasi |
| `KPI_Karyawan_Team` | 56 baris | Idem |
| `N_AMS_User_Leader_Teams` | **0 baris** | Bentuk bagus, tak pernah dipakai — jangan tertukar |
| `KPI_Approval_Flow_Performance` | 923 baris · `order_flow=1` 916 · `order_flow=2` **7** | **900 di antaranya data uji** — `UJI0001`…`UJI0009`, masing-masing tepat 100 bawahan. Data asli hanya 16 baris / 4 approver (A4=7, C9=4, K32=3, H45=2) |
| Karyawan aktif tanpa atasan | 44 (setelah data uji dikeluarkan: hampir semua) | Cakupan produksi **belum diketahui** |
| Master `HRIS_Level` | **16 level**, `Level_Hierarchy` 0–15 | 12 level matriks PIC **ada semua**, plus FREELANCE(0), DIREKTUR(12), DIREKTUR UTAMA(13), NOT SET(14), BELUM(15) |
| Penanda "Specialist" | **Tidak ada**; `HRIS_Level_Jabatan` hanya 4 kolom | Specialist adalah *jabatan* (`TECHNICAL SERVICE SPECIALIST`), bukan level → 180° vs 360° ditetapkan admin |
| `ID_Divisi_Sub_Divisi` | 0 karyawan aktif kosong; 10 nilai berbeda | Bisa dipakai untuk penyaringan kandidat peer |
| Pita nilai KPI | `KPI_Master_Performance`: NP 0–74 · SY 75–89 · GD 90–105 · ET 106–999 | — |
| Cap skor KPI | `services.kpi.{min,max}_achievement_score` = **50 / 125** | Bukan 0–100 |
| Callback HC Learn saat ini | `POST /exam/complete` → `Kode_Karyawan`, `Id_Penjadwalan_Integrasi`, `Id_Ujian_Token`, `Score` (0–100) | Kontrak harus diperluas |
| Mesin approval generik | `N_HRIS_Jenis_Approval` mendukung `Source_Type` = TABLE / ROLE / DIRECT_EMPLOYEE / GROUP_TABLE / KPI_TABLE | **`ROLE` sudah dipakai** (`ADMIN_HC_CUTI_VERIFIKATOR`, `SHE_ADMIN`, `ROLE_KPI_VERIF`) → PA menumpang, tidak membangun sendiri |

### Level `HRIS_Level` lengkap

```
 0 FREELANCE               6 ASSISTEN MANAGER           12 DIREKTUR
 1 NON CLERK/PRAMUKARYA    7 SENIOR ASSISTEN MANAGER    13 DIREKTUR UTAMA
 2 STAFF                   8 MANAGER                    14 NOT SET
 3 SENIOR STAFF            9 SENIOR MANAGER             15 BELUM
 4 OFFICER                10 GENERAL MANAGER
 5 SENIOR OFFICER         11 SENIOR GENERAL MANAGER
```

---

## 11. Yang belum diputuskan

Diurutkan dari yang paling menghambat.

| # | Menunggu | Pertanyaan | Memblokir |
|---|---|---|---|
| T01 | **Tim HC Learn** | Bentuk JSON bundel hasil **untuk PA** (bukan rekrutmen). Cukup satu contoh nyata | Fase 2 |
| T02 | **Tim HC Learn** | **Bagaimana payload menyatakan "A menilai B"?** Payload rekrutmen hanya punya satu orang (P05). Ini pertanyaan kontrak terpenting | Fase 2 |
| T03 | **Tim HC Learn** | Apakah alat testnya sanggup memuat form 360° multi-penilai — satu orang mengerjakan beberapa asesmen untuk beberapa subjek berbeda dalam satu periode? | Fase 2 |
| T04 | **Tim HC Learn** | Arti `tabs[]` dan `is_multi` untuk PA — apakah tiap dimensi (Manajerial / Core Value / Behavior / Qualitative) jadi tab terpisah? (P09) | Pemeta |
| T05 | **HC / PIC** | Skala kanonik final + isi pita: batas Tinggi/Sedang/Rendah tiap sumbu HAV Map, dan batas grade A…C. Strukturnya siap, angkanya keputusan HC | Fase 3 |
| ~~T06~~ | — | ~~Angka bobot **Atasan 2**~~ — **TERTUTUP oleh K18.** Bukan angka master: bobot Atasan-1 vs Atasan-2 ditetapkan admin per peserta lewat `Bobot_Dalam_Kategori`. Kolom *(adjustable)* di matriks deck memang benar adjustable | — |
| T11 | **HC / PIC** | **Angka pembagian pilar KPI vs Kompetensi per level.** Deck slide 4 hanya memuat bobot antar-penilai (sisi kompetensi); pembagian pilarnya belum pernah muncul di materi mana pun dan belum pernah ditanyakan | Fase 3 |
| T07 | **HC / PIC** | Ambang minimum penilai per kategori sebelum satu kategori sah dihitung, dan perlakuan penilai yang tidak mengisi sampai tenggat | Fase 3 |
| T08 | **HC / PIC** | Perubahan di tengah siklus: promosi, mutasi, resign setelah cycle dikunci — ikut snapshot atau matriks berubah? **PIC sendiri menandai ini paling *tricky*** | Fase 2–3 |
| T09 | **HC / PIC** | Penilai yang resign: penugasan terisi tetap dihitung atau dibuang? Kalau yang resign Atasan 1 (bobot terbesar), siapa penggantinya? | Fase 2–3 |
| T10 | **Tim IT** | **Kelengkapan `KPI_Approval_Flow_Performance` di produksi.** Menentukan apakah penetapan penilai bisa dibantu usulan sistem atau nyaris seluruhnya manual | Rencana fase |

> **Kalau T03 dijawab "tidak", ada lubang yang belum dirancang.** Formulir penilaian tidak ada di deck maupun mockup, karena memang dirancang hidup di HC Learn. Tetapi mockup masih menyisakan CSS untuk formulir di dalam HCIS — `.scale-opt`, `.comp-dot`, `.competency-nav`, `.indicator-item` — jadi PIC pernah membuatnya lalu mencabutnya. Bila HC Learn ternyata tidak sanggup memuat 360° multi-penilai, pengisian harus pindah ke HCIS, dan itu menuntut master yang **sama sekali belum ada**: bank soal per kompetensi, skala penilaian beserta label jangkarnya, dan indikator perilaku per butir. Bukan pekerjaan kecil, dan seluruhnya bergantung pada satu jawaban.

---

## 12. Riwayat koreksi

Dicatat supaya penelusuran yang sama tidak diulang.

| Klaim awal | Kenyataan | Bukti |
|---|---|---|
| "900 dari 958 karyawan aktif tanpa atasan — auto-generate rater mustahil" | **Salah.** `HRIS_Karyawan_Team` memang sempit, tapi bukan satu-satunya sumber. `KPI_Approval_Flow_Performance` punya bentuk yang tepat | User menunjukkan tabel approval flow |
| "914 karyawan punya Atasan-1 — cakupan bagus" | **Juga salah.** 900 dari 914 adalah data uji `UJI0001`…`UJI0009` | Kueri per-approver: 9 akun uji × tepat 100 bawahan |
| "Master level DB cuma 7, tidak cocok dgn 12 level PIC" | **Salah.** Saya hanya melihat level yang *terpakai*. Master `HRIS_Level` punya 16 dan mencakup semuanya | `SELECT * FROM HRIS_Level` |
| "RBAC HCIS tak bisa memisahkan wewenang di dalam halaman, PA butuh mekanisme sendiri" | **Salah.** Mesin approval generik sudah mendukung `Source_Type = ROLE` | `N_HRIS_Jenis_Approval` — 3 entri role aktif |
| "Kompetensi berskala 1–5 (dari deck)" | **Terbantah payload.** `Skor_Maksimal: 100` → 0–100. Skala kanonik jadi terbuka kembali (T05) | Payload HC Learn 2026-08-29 |
| Ringkasan diskusi lama: "Atasan = dari field `atasan_langsung` di org chart" | Field itu **tidak ada** di `Karyawan` | 52 kolom diperiksa |

---

## 13. Yang belum dikerjakan

| Bagian | Isi | Status |
|---|---|---|
| Bagian 1 | Arsitektur & alur data | ✅ |
| Bagian 2 | Model data | ✅ |
| Bagian 3 | Perhitungan | ✅ (perlu revisi setelah P02 — skala) |
| **Bagian 4** | **Halaman & peran** — 4 dashboard (diri sendiri, leader, admin, super admin), pemetaan halaman mockup ke rute, kebutuhan `N_HRIS_Grup_Fitur_Map` + `flushMenuCache` | ❌ belum |
| **Bagian 5** | **Rencana fase pengerjaan** — urutan Fase A/B/C… (penomoran sengaja huruf agar tidak tertukar dengan Fase 1–4 milik siklus) | ❌ belum |

### Prasyarat sebelum implementasi

1. Jawaban T01–T04 dari tim HC Learn (memblokir seluruh jalur pengisian).
2. Jawaban T05 dari HC (memblokir perhitungan & HAV Map).
3. Verifikasi T10 ke produksi (menentukan bentuk rencana fase).
4. Halaman baru **wajib** punya baris di `N_HRIS_Grup_Fitur_Map` + `flushMenuCache`, atau 403 untuk semua orang.
5. DDL dan DML terpisah, mengikuti alur DDL di `AGENTS.md`.
