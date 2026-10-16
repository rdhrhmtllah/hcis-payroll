# Performance Appraisal — yang wajib dibaca sebelum menyentuh apa pun

Halaman ini adalah pintu masuk modul PA untuk **agent mana pun**, backend maupun
frontend. Tujuannya satu: menghemat waktu Anda dan waktu pemilik proyek, dengan
memastikan Anda tidak membangun di atas asumsi yang sudah pernah dibantah.

Modul ini sudah punya **41 tabel, 21 change database, 5 service, dan belasan keputusan
yang ditutup lewat perdebatan.** Banyak yang tampak seperti pilihan bebas sebenarnya
sudah diputuskan — dan membukanya kembali bukan cuma boros, tetapi menghasilkan kode
yang bertabrakan dengan yang sudah ada.

---

## 1. Urutan baca

### Lapis 0 — aturan proyek, berlaku di luar PA juga

| Berkas | Kenapa wajib |
|---|---|
| `AGENTS.md` | Gerbang keselamatan database, alur DDL/SQL Tracker, status wajib tiap change. Tidak bisa ditawar. |
| `CLAUDE.md` | Struktur modul wajib. File baru harus jelas grup, submodul, fitur, lapisannya. |
| `docs/architecture/module-registry.md` | Grup fitur yang sah. PA milik **KPI**. |
| `docs/database-environment-policy.md` | Development vs production. Agent tidak pernah menjalankan DDL production. |
| `docs/architecture/ketentuan-tak-tertulis.md` | Pertimbangan yang tidak diatur `AGENTS.md`: apa yang boleh diputuskan sendiri, kapan menahan hasil alih-alih menormalkan, kenapa nilai tidak pernah dipotong. |

### Lapis 1 — PA: apa dan kenapa

Baca berurutan; yang belakangan mengoreksi yang duluan.

| # | Berkas | Isi |
|---|---|---|
| 1 | `plans/kpi/2026-08-31-performance-appraisal-arsitektur-konsolidasi.md` | Gambaran utuh arsitektur PA |
| 2 | `plans/kpi/2026-08-31-angka-acuan-dari-deck-pic.md` | Angka acuan dari deck PIC — bobot pilar, kategori penilai |
| 3 | `plans/kpi/2026-09-01-sumber-nilai-kpi-untuk-pa.md` | **Dari mana `Nilai_KPI` diambil**, dan tiga temuan terukur yang mengubah rancangan |
| 4 | `plans/kpi/2026-09-01-lapisan-hasil-pa-rancangan.md` | Rancangan tujuh tabel hasil |
| 5 | `plans/kpi/2026-09-01-penilaian-arsitektur-pa.md` | Penilaian jujur: mana yang solid, mana yang belum |
| 6 | `docs/architecture/pa-aturan-yang-tidak-dijaga-database.md` | **18 aturan yang tidak bisa ditegakkan skema** — dan siapa yang menjaganya sekarang |
| 7 | `plans/kpi/pa-peta-kerja.md` | **Urutan lapisan per fitur dan keadaannya.** Baca sebelum mulai menulis kode: ia menyebut fitur mana yang masih berlubang, dan aturan bahwa satu fitur diselesaikan L0–L7 sebelum fitur berikutnya |

### Lapis 2 — kalau Anda mengerjakan frontend

| Berkas | Isi |
|---|---|
| `docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md` | **Kontrak API. Satu-satunya rujukan FE.** Jangan membaca controller. |
| `plans/kpi/pa-papan-kerja.md` | Kepemilikan berkas, zona bahaya, antrean permintaan antar jalur |
| `docs/design-system.md` + `docs/HCIS/` | Bahasa visual HCIS |
| `docs/architecture/frontend-architecture.md` | Definisi arsitektur frontend |

### Lapis 3 — kalau Anda mengerjakan backend

| Berkas | Isi |
|---|---|
| `docs/architecture/backend-coding-standard.md` | Standar penulisan backend |
| `docs/database/naming-conventions.md` | `Id_PA_<Entitas>`, `PA_<Entitas>_Id`, `Flag_<Makna> char(1)` |
| `docs/database/table-design-rules.md` | Aturan desain tabel |
| `docs/workflows/ddl-sql-tracker.md` | Alur change database dari dev ke production |
| `docs/database/tables/kpi/n-pa-*.md` | **41 dokumen tabel, satu per tabel.** Baca yang Anda sentuh. |
| `docs/changes/DDL-2026-0{61..83}-*/README.md` | Kenapa tiap change dibuat, termasuk revisi dan alasannya |
| `docs/architecture/testing-and-quality-standard.md` | Standar uji |

---

## 2. Keputusan yang SUDAH DITUTUP — jangan dibuka lagi

Semuanya sudah diperdebatkan dan diputuskan pemilik proyek. Kalau Anda merasa salah
satunya keliru, **tulis keberatan Anda di papan kerja dan lanjutkan bekerja** — jangan
diam-diam membangun yang lain.

### Nilai dan skor

| # | Keputusan |
|---|---|
| 1 | Nilai KPI diambil **YTD** lewat `KpiScoringService::accumulatedScore()`, mengikuti rentang periode siklus. PA **tidak membaca satu pun kolom nilai bulanan** — keempat kolom nilai di `KPI_Update_Performance` sudah terbukti tidak saling sepakat. |
| 2 | Hanya KPI berstatus **disetujui** yang dihitung. |
| 3 | Orang tanpa KPI **tidak** otomatis diberi nol. Sistem menandai `TERTAHAN`, admin yang memutuskan. |
| 4 | Nilai di atas 100 **tidak dipotong**. Pita grade teratas terbuka ke atas (`999.99`). Sisi KPI berjalan sampai 125. |
| 5 | `Total_Bobot` KPI selalu 100. Bobot yang bukan 100 **menahan** hasilnya, bukan dipakai diam-diam. |
| 6 | `N_PA_Metode_Nilai` **dibatalkan**. Kolom yang isinya selalu sama tidak menyimpan informasi apa pun. |

### Struktur aturan

| # | Keputusan |
|---|---|
| K10 | Sistem hanya **mengusulkan** penilai; admin yang menugaskan per orang. |
| K18 | `Bobot_Dalam_Kategori` (di dalam kategori) dan `Bobot_Persen` (antar kategori) berbeda dan **berkali**, bukan bertukar. |
| K23 | Bobot pilar adalah **satu-satunya** salinan siklus yang sengaja **tidak** dibekukan saat penguncian. |
| K26 | Aturan mendapat salinan siklus; kamus **tidak**. |
| K31 | Kolom yang isinya daftar tetap **dan berbeda-beda** butuh master + FK. Kolom yang isinya selalu satu nilai tidak punya daftar, jadi tidak butuh master. |
| K32 | Override per peserta bersifat **sparse** — normalnya kosong. Hanya `PaParticipantRuleResolver` yang boleh menggabungkan salinan dengan override, dan itu **dijaga uji pemindai** (`ResolverSatuPintuTest`). |

### Cakupan terhadap deck PIC — apa yang BUKAN dari deck

Diaudit 1 September 2026. Angka yang tidak berasal dari deck ditandai di sini supaya
tidak diperlakukan sebagai ketetapan bisnis.

| Hal | Keadaan |
|---|---|
| Matriks penilai per level | Mengikuti **slide 4**. Slide 18 memberi angka berbeda di hampir semua baris; konfliknya **belum diputus pemilik proyek**. |
| Baris "Officer (Specialist)" slide 4 (60/15/**25 peer**/–) | **Hilang.** `HRIS_Level` hanya punya satu `OFFICER`, dan seed memakai baris *Officer* biasa. Jalur specialist tanpa bawahan belum bisa dinyatakan. |
| Bobot pilar | Hanya 3 dari 13 level berasal dari deck (Staff, Manager, Sr Manager/GM). **Enam level dikarang**, termasuk `OFFICER` 75/25 — 188 karyawan, populasi terbesar kedua. Pemetaan sebenarnya akan dikerjakan lewat halaman tersendiri (keputusan pemilik proyek). |
| Definisi "Leaders" (slide 9) | **Dikarang agent** sebagai level ≥ 6. Bertabrakan dengan matriks penilai yang sudah memberi bawahan mulai level 4. |
| Pita grade `N_PA_Score_Range` | **Tidak ada di deck sama sekali.** Seed sekarang acak-tapi-wajar atas izin pemilik proyek. |
| HAV Map, skala 1–5 vs 0–100, "Atasan 2 adjustable", Qualitative Question | Tercakup benar. |

### Cakupan terhadap deck PIC — apa yang BUKAN dari deck

Diaudit 1 September 2026. Angka yang tidak berasal dari deck ditandai di sini supaya
tidak diperlakukan sebagai ketetapan bisnis.

| Hal | Keadaan |
|---|---|
| Matriks penilai per level | Mengikuti **slide 4**. Slide 18 memberi angka berbeda di hampir semua baris; konfliknya **belum diputus pemilik proyek**. |
| Baris "Officer (Specialist)" slide 4 (60/15/**25 peer**/–) | **Hilang.** `HRIS_Level` hanya punya satu `OFFICER`, dan seed memakai baris *Officer* biasa. Jalur specialist tanpa bawahan belum bisa dinyatakan. |
| Bobot pilar | Hanya 3 dari 13 level berasal dari deck (Staff, Manager, Sr Manager/GM). **Enam level dikarang**, termasuk `OFFICER` 75/25 — 188 karyawan, populasi terbesar kedua. Pemetaan sebenarnya akan dikerjakan lewat halaman tersendiri (keputusan pemilik proyek). |
| Definisi "Leaders" (slide 9) | **Dikarang agent** sebagai level ≥ 6. Bertabrakan dengan matriks penilai yang sudah memberi bawahan mulai level 4. |
| Pita grade `N_PA_Score_Range` | **Tidak ada di deck sama sekali.** Seed sekarang acak-tapi-wajar atas izin pemilik proyek. |
| HAV Map, skala 1–5 vs 0–100, "Atasan 2 adjustable", Qualitative Question | Tercakup benar. |

### Integrasi HC Learn

| # | Keputusan |
|---|---|
| 7 | HC Learn memakai **satu URL callback tetap** milik LMS. PA **tidak punya endpoint sendiri** — `LmsApiGuard` mengenali token PA lalu mendelegasikan. Ini keputusan yang sama dengan modul SOP, dan alasannya sama: dua pintu harus dijaga sama persis. |
| 8 | Satu token PA mewakili satu **penugasan**, sehingga "A menilai B" sudah terjawab tanpa HC Learn perlu tahu apa pun tentang peserta. |
| 9 | HC Learn **tidak** diminta menyeragamkan skala. Selama `Skor` selalu disertai `Skor_Maksimal`, HCIS menormalkannya sendiri. |
| 10 | Kontrak HC Learn **sengaja belum dikirim** — daftar permintaannya masih tumbuh, dan dikirim sekali saja. Lihat `docs/superpowers/specs/2026-09-01-kontrak-hc-learn-pa-siap-kirim.md`. |

---

## 3. Jebakan yang sudah memakan korban di modul ini

1. **Amplop respons memakai `result`, bukan `data`.** Sudah salah sekali. Frontend
   memakai `paApi.js` yang membukanya di satu tempat.
2. **Batas atas pita nilai bersifat eksklusif** (`Nilai_Dari <= x < Nilai_Sampai`).
   DML-2026-078 menyeed-nya seolah inklusif dan meninggalkan lubang di tiap batas;
   penjaganya lolos justru **karena** cacatnya — ia memeriksa min/maks dan tumpang
   tindih, tidak pernah memeriksa kesinambungan. Diperbaiki DML-2026-079.
3. **Unique index SQL Server memperlakukan NULL sebagai SAMA.** Ini dipakai sengaja di
   `N_PA_Result_Competency` supaya baris agregat otomatis terbatas satu per
   (hasil, kompetensi). Jangan "diperbaiki".
4. **Halaman baru yang hanya punya rute menjawab 403 untuk semua orang.** Butuh tiga
   baris: `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map`, `HRIS_Page_Access` — lalu
   `InertiaShellService::flushMenuCache()`. Lihat DML-2026-084.
5. **Penjaga yang tidak pernah terlihat MERAH belum tentu bekerja.** Dua kali di modul
   ini uji penolakan "lulus" karena error PHP, bukan karena constraint database menyala.
   Uji penolakan wajib menuntut error datang dari SQL Server dan mencatat constraint-nya.
6. **`artisan test` menembak database dev yang sama dengan layar pengguna**, dan RCSI di
   `Web_HRIS` mati. Selama uji jalan, UI ikut menggantung. Jalankan di akhir, bukan tiap
   langkah.
7. **Database dev ada di `team311.dyndns.info:1433`, bukan lokal.** Satu round-trip
   ±72 ms. Yang lambat bukan kodenya melainkan jumlah kuerinya — hitung kueri per
   peserta sebelum menulis loop.
8. **Periksa tabel yang SUDAH ADA sebelum membuat tabel baru.** DDL-2026-083 r1 membuat
   `N_PA_Question_Competency_Map` padahal `N_PA_Cycle_Question_Package_Item` sudah
   melakukan hal yang sama sejak DDL-2026-073. Kembarnya memutus rantai tanpa satu pun
   pesan galat: 80 baris skor tersimpan rapi, tetapi penugasan tak pernah `SELESAI`,
   1.358 jejak kategori ber-`Jumlah_Rater_Mengisi = 0`, dan pilar kompetensi kosong.
   41 dokumen tabel di `docs/database/tables/kpi/` ada supaya ini tidak terjadi.
9. **Fixture uji yang menyisipkan ke dua tempat menyembunyikan bug.** `ServiceFlowTest`
   menyisipkan ke KEDUA tabel pemetaan, jadi tetap hijau selama jalur sungguhannya
   putus. Fixture harus meniru jalur produksi, bukan menambalnya.
10. **`KPI_Users.Flag_Active` menyesatkan, dan `Kode_Users` tidak unik.** Ketujuh
    karyawan yang benar-benar punya KPI disetujui semuanya ber-`Flag_Active = NULL`;
    menyaring `= 'Y'` membuang persis orang yang datanya ada. Dan satu kode bisa
    menunjuk beberapa akun (`D12` dua, `USERS` empat) — memilih salah satunya diam-diam
    berarti berpeluang memakai KPI milik orang lain.
8. **Periksa tabel yang SUDAH ADA sebelum membuat tabel baru.** DDL-2026-083 r1 membuat
   `N_PA_Question_Competency_Map` padahal `N_PA_Cycle_Question_Package_Item` sudah
   melakukan hal yang sama sejak DDL-2026-073. Kembarnya memutus rantai tanpa satu pun
   pesan galat: 80 baris skor tersimpan rapi, tetapi penugasan tak pernah `SELESAI`,
   1.358 jejak kategori ber-`Jumlah_Rater_Mengisi = 0`, dan pilar kompetensi kosong.
   41 dokumen tabel di `docs/database/tables/kpi/` ada supaya ini tidak terjadi.
9. **Fixture uji yang menyisipkan ke dua tempat menyembunyikan bug.** `ServiceFlowTest`
   menyisipkan ke KEDUA tabel pemetaan, jadi tetap hijau selama jalur sungguhannya
   putus. Fixture harus meniru jalur produksi, bukan menambalnya.
10. **`KPI_Users.Flag_Active` menyesatkan, dan `Kode_Users` tidak unik.** Ketujuh
    karyawan yang benar-benar punya KPI disetujui semuanya ber-`Flag_Active = NULL`;
    menyaring `= 'Y'` membuang persis orang yang datanya ada. Dan satu kode bisa
    menunjuk beberapa akun (`D12` dua, `USERS` empat) — memilih salah satunya diam-diam
    berarti berpeluang memakai KPI milik orang lain.

---

## 4. Peta kode

| Lapisan | Lokasi |
|---|---|
| Service | `app/Services/KPI/PerformanceAppraisal/` — resolver, gerbang siklus, penjaga bobot, penyiapan, penugasan, pemeta payload, perhitungan |
| Controller | `app/Http/Controllers/KPI/PerformanceAppraisal/Cycle/` |
| Callback HC Learn | `app/Http/Middleware/LmsApiGuard.php` + `app/Http/Controllers/LMS/Integration/LmsThirdPartyCallbackController.php` |
| Rute | `routes/KPI/PerformanceAppraisal/Cycle/PerformanceAppraisalCycle.php` |
| Halaman | `resources/js/Pages/KPI/PerformanceAppraisal/` |
| Seam FE/BE | `resources/js/Pages/KPI/PerformanceAppraisal/api/` — **milik BE** |
| Uji | `tests/Feature/KPI/PerformanceAppraisal/` |
| Data contoh | `php artisan pa:demo-cycle` |

### Perkakas baris perintah — dan kapan memakai yang mana

| Perintah | Memeriksa apa | Menulis? |
|---|---|---|
| `pa:audit` | **Kode**: apakah tiap fitur punya service, uji, rute, kontrak, halaman | tidak |
| `pa:verify [kode]` | **Data satu siklus**: apakah siklus INI bisa dijalankan sampai hasilnya terbit | tidak |
| `pa:analisis-relasi` | Calon sumber relasi organisasi, berikut kecocokan antar-sumber | tidak |
| `pa:hitung-siklus <kode>` | — | ya, menghitung hasil |
| `pa:ingatkan-penilai` | — | ya, mengirim pemberitahuan |
| `pa:demo-cycle` | — | ya, hanya siklus `PA-DEMO-FE` |

**`pa:audit` dan `pa:verify` menjawab pertanyaan yang berbeda, dan keduanya perlu.** Pada
2 September 2026 `pa:audit` melaporkan **115/115 (100%)** sementara modul ini tidak bisa
dijalani sampai selesai — `OPEN` dan `CLOSED` tidak punya penulis di kode produksi, jadi
siklus mentok di `LOCKED`. Audit kelengkapan per fitur memang buta terhadap sambungan
antar-fitur; itu sebabnya `pa:verify` ada, dan itu sebabnya rantai penuh perlu sesekali
dijalani sungguhan pada data nyata.

`pa:verify` tanpa argumen memeriksa seluruh siklus yang belum terbit. Tambahkan `--rinci`
untuk melihat daftar orangnya, bukan hanya jumlahnya.

### Empat service yang memegang aturan

| Service | Yang dijaganya |
|---|---|
| `PaParticipantRuleResolver` | **Satu-satunya** penggabung salinan siklus dengan override peserta (K32) |
| `PaCycleGate` | Matriks 10 operasi × status siklus. Operasi tak dikenal **ditolak**, bukan diloloskan |
| `PaBobotGuard` | Σ bobot = 100, pembagian ulang saat kategori kosong, sisa pembulatan ke bagian terbesar |
| `PaCyclePreparationService` / `PaAssignmentBuilderService` / `PaPayloadMapperService` / `PaResultCalculationService` | Jalur tulis: siapkan → tugaskan → terima payload → hitung |

---

## 5. Keadaan sekarang — 1 September 2026

**Selesai:** 41 tabel (DDL-2026-061…083, seluruhnya `TRACKER_PENDING`, belum
production), empat service inti beserta ujinya, satu halaman admin, seam FE/BE dengan
9 fixture dan uji kontrak.

**Belum:** daftar peserta berhalaman, detail hasil per orang, layar kalibrasi, HAV Map,
pemetaan kode soal lewat layar, pembuatan siklus lewat layar.

**Utang yang diketahui:**

- Enam dari 13 bobot pilar dan definisi "Leaders" masih karangan agent, menunggu
  keputusan pemilik proyek. Lihat tabel cakupan deck di atas.

- Enam dari 13 bobot pilar dan definisi "Leaders" masih karangan agent, menunggu
  keputusan pemilik proyek. Lihat tabel cakupan deck di atas.

- `hitungSiklus()` menjalankan ±25–30 kueri **per peserta**. Untuk satu siklus penuh di
  database dev jarak jauh itu ±30 menit. Sedang diperbaiki.
- Aturan kelayakan peserta **belum lengkap**: penyaring jenis kontrak tidak bisa
  diterapkan karena empat kolom yang mengaku menyimpannya saling bertentangan
  (`Status_Karyawan` NULL pada 901 dari 958, `status_kontrak` NULL pada 949). Saat ini
  hanya `Aktif='Y'`, minus FREELANCE, minus yang resign sebelum periode berakhir.
- `N_HRIS_Organization_Relationship` masih **0 baris**, jadi usulan penilai otomatis
  belum menghasilkan siapa pun kecuali `SELF`. Ini pekerjaan pemilik proyek.
- Sembilan dari 18 aturan di register masih belum punya penjaga.

---

## 6. Sebelum Anda mulai

1. Baca lapis 0 dan lapis 1.
2. Baca `plans/kpi/pa-papan-kerja.md` — di sana tertulis siapa memegang berkas apa, dan
   apa yang sedang dikerjakan jalur sebelah.
3. Perbarui papan kerja **sesudah** selesai, bukan sebelum.
4. Kalau ada yang ambigu soal kepemilikan berkas atau bentuk data: **berhenti dan
   tanya**, jangan menebak. Dua agent yang menebak akan sama-sama benar menurut kodenya
   masing-masing dan tetap tidak nyambung saat digabungkan.
