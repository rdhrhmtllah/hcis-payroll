# Papan kerja Performance Appraisal — dua jalur paralel

Dibuat 1 September 2026. Berkas ini yang dibaca **kedua agent** sebelum mulai, dan
diperbarui **sesudah** selesai. Kalau ada yang perlu ditanyakan ke jalur sebelah,
tulisnya di sini, bukan menebak dari kodenya.

Batas antar jalur adalah `docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md`.

**Backend mengikuti `plans/kpi/pa-peta-kerja.md`** — urutan lapisan per fitur, beserta
keadaan tiap lapisan. Berkas ini mengurus koordinasi antar jalur; berkas itu mengurus
urutan pengerjaan backend.

---

## 1. Kepemilikan berkas — tidak boleh tumpang tindih

| Jalur | Boleh menulis | Contoh |
|---|---|---|
| **BE** | `app/**`, `database/**`, `routes/**`, `tests/Feature/**`, `docs/database/**`, `docs/changes/**` | service, DDL, controller, uji feature |
| **FE** | `resources/js/Pages/KPI/PerformanceAppraisal/**` **kecuali `api/`**, uji komponen PA | halaman, komponen halaman, gaya |
| **Berdua** | `docs/superpowers/specs/…kontrak-api…md`, berkas ini | kontrak dan papan kerja |

### Zona bahaya — pemiliknya SATU, jangan disentuh yang lain

| Berkas | Pemilik | Kenapa |
|---|---|---|
| `database/ddl/status.json` | **BE** | Satu berkas untuk seluruh repo. Dua penulis serentak = konflik yang sulit dibaca, dan sudah pernah terjadi lintas-sesi. |
| `routes/web.php` | **BE** | Hanya satu baris `require` milik PA. FE tidak pernah perlu menyentuhnya. |
| `resources/js/components/ui/**` (kit Hcis) | **BUKAN keduanya** | Kit dipakai 300+ halaman lain. Kebutuhan baru diajukan di papan ini, bukan disunting sendiri. Menyunting satu komponen kit mengubah halaman modul lain tanpa ada yang tahu. |
| `app/Http/Middleware/LmsApiGuard.php` | **BE** | Jalur callback HC Learn untuk LMS, SOP, dan PA sekaligus. |
| `resources/js/Pages/KPI/PerformanceAppraisal/api/**` | **BE** | Inilah seam-nya. `paApi.js` dan fixture-nya dijaga uji kontrak; menyuntingnya dari sisi FE akan memutus jaminan itu tanpa ada yang tahu. Butuh bentuk lain? Tulis di antrean §6. |

---

## 2. Cara menjalankan berdua tanpa saling mematikan

**Satu worktree, satu branch, dua direktori berbeda.** Bukan dua worktree — dan itu
keputusan sadar:

- Dua worktree yang sama-sama menjalankan `npm run dev` akan berebut port 5173. Yang
  kalah tetap **mengumumkan** 5173, sehingga soket HMR nyasar ke tetangga dan halaman
  memuat ulang tiap dua detik dengan JS dari worktree yang salah. Gejalanya menyamar
  jadi "modal hilang sendiri".
- `bootstrap/cache/routes-v7.php` bersifat per-worktree. Worktree yang cache rutenya
  lebih tua akan menjawab "Target class does not exist" untuk kode yang sudah benar.

Aturannya karena itu:

| | BE | FE |
|---|---|---|
| `npm run dev` | **tidak pernah** | ya, satu-satunya |
| `npx vite build` | **tidak pernah selama FE bekerja** | ya, bila perlu |
| `php artisan test` | ya | tidak |
| `php artisan route:clear` | ya, sesudah menambah rute | tidak |
| menjalankan DDL/DML | ya | tidak |

`php artisan test` menembak database development yang sama dengan yang dipakai layar.
Selama uji berjalan, layar FE bisa terasa menggantung. Karena itu BE menjalankan uji
**di akhir sesi**, bukan tiap langkah — dan memberi tahu di papan ini ketika akan
menjalankannya lama.

---

## 3. Data untuk FE — mulai sekarang, tanpa database

**Mode boneka sudah menyala secara baku.** FE tidak perlu database, tidak perlu siklus,
tidak perlu menunggu BE:

```js
import paApi from '../api/paApi.js';

const { siklus } = await paApi.daftarSiklus();   // dari fixture, ada jeda 320 ms
```

Dua puluh enam fixture tersedia di `…/api/fixtures/`, bentuknya persis respons asli dan
**dijaga uji kontrak** supaya tidak basi. Contoh pemakaiannya ada di
`Cycle/siklus.vue`.

Beralih ke data sungguhan: `VITE_PA_API_MODE=live` di `.env`. Tidak ada baris halaman
yang perlu disentuh.

### Data sungguhan untuk hari integrasi

Tanpa satu pun siklus, semua layar PA kosong dan FE tidak bisa melihat hasil kerjanya.

```
php artisan pa:demo-cycle          # membuat/menyegarkan siklus contoh
php artisan pa:demo-cycle --hapus  # membuangnya kembali
```

Perintah itu hanya menyentuh siklus ber-kode `PA-DEMO-FE` dan tidak pernah menyentuh
siklus lain. Aman dijalankan berulang.

---

## 4. Urutan yang masuk akal

FE tidak harus menunggu BE. Yang endpointnya sudah ada bisa dikerjakan sekarang.

| # | Layar | Endpoint | Bisa dikerjakan? |
|---|---|---|---|
| 1 | Daftar siklus + aksi | `daftarSiklus`, `siapkanSiklus`, `kunciSiklus` | **sekarang, mode boneka** — sudah ada halamannya, tinggal dipertajam |
| 2 | Keranjang tertunda | `keranjangTertunda` | **sekarang, mode boneka** |
| 3 | Penugasan penilai per peserta | `usulanPenilai`, `tugaskanPenilai`, `batalkanPenugasan` | **sekarang, mode boneka** |
| 4 | Ringkasan hasil | `hitungSiklus`, `hitungPeserta` | **sekarang, mode boneka** |
| 5 | Daftar peserta berhalaman | `daftarPeserta` | **sekarang, mode boneka** |
| 6 | Detail hasil satu orang (radar) | `detailHasil` | **sekarang, mode boneka** |
| 7 | Kalibrasi | `kalibrasi` | **sekarang, mode boneka** |
| 8 | HAV Map (sebaran 9 kotak) | `havMap` | **sekarang, mode boneka** |
| 9–17 | Admin PA: sembilan layar master data | `masterReferensi`, `masterDaftar`, `masterDetail`, `masterSimpan`, `masterPerbarui`, `masterNonaktif`, `simpanRincianAturan`, `aktifkanAturan` | **sekarang, mode boneka** — kontrak bagian 4 |

---

## 5. Aturan yang menghindarkan miskomunikasi

1. **Bentuk respons tidak pernah berubah diam-diam.** BE mengubah kontrak lebih dulu,
   menulis barisnya di *Riwayat perubahan kontrak*, baru mengubah kode.
2. **FE tidak menyimpulkan aturan bisnis.** Tombol hidup/mati datang dari
   `operasi_boleh`; ambang, bobot, dan status datang dari respons. Menyalin aturannya ke
   layar akan melahirkan pintu kedua yang harus dijaga sama persis.
3. **Nama field respons = nama kolom database.** Tidak ada alias. Kalau FE butuh nama
   lain, itu urusan tampilan di komponen, bukan di kontrak.
4. **422 = pesan untuk dibaca manusia.** Ditampilkan apa adanya.
5. **Angka boleh > 100.** Sisi KPI berjalan sampai 125 dan pita grade teratas terbuka
   ke atas. Jangan memasang batas 100 di mana pun.
6. **Yang belum ada ditulis di §3 kontrak**, bukan dikarang endpoint-nya oleh FE atau
   ditebak kebutuhannya oleh BE.

---

## 6. Antrean permintaan

Diisi kedua jalur. Yang mengerjakan mencoret dan menuliskan tanggalnya.

### FE → BE

| Tanggal | Permintaan | Status |
|---|---|---|
| 2026-09-01 | ~~Endpoint daftar peserta satu siklus: berhalaman, saring status peserta dan divisi, muat ringkasan kesiapan penilai~~ | **selesai 2026-09-01**, kontrak §2.9 |
| 2026-09-01 | ~~Endpoint detail hasil satu peserta: nilai per kompetensi per kategori + baris agregat untuk radar~~ | **selesai 2026-09-01**, kontrak §2.10 |
| 2026-09-01 | ~~Endpoint kalibrasi~~ | **selesai 2026-09-01**, kontrak §2.11 |
| | ~~Endpoint sebaran HAV Map seluruh peserta~~ | **selesai 2026-09-01**, kontrak §2.12 |
| 2026-09-01 | ~~Ubah label menu global jadi `Administrator PA`, sekaligus pastikan modul dan seluruh child menu PA muncul untuk H45~~ | **SELESAI — diverifikasi 2026-09-02.** Label sudah `Administrator PA` (DML-2026-088) pada kedelapan halaman master. Akses H45 (user 4) diperiksa lewat gerbang sungguhan, bukan lewat isi tabel: `PerformanceAppraisalPage`, `...SayaPage`, `...LeaderPage`, `PaMasterPitaNilaiPage`, `PaMasterKompetensiPage` — **semuanya LOLOS**. Header `Performance Appraisal` kini memuat tiga baris: halaman admin, `Penilaian Saya` (urut 1), `Penilaian Tim` (urut 2). |
| 2026-09-01 | ~~Daftarkan mapping halaman admin untuk FE-PA-01 Peserta & Kesiapan~~ | **selesai 2026-09-01**; A03 tetap bagian dari `PerformanceAppraisalPage` (`/performance-appraisal`), bukan `Jenis_page` baru |
| 2026-09-01 | ~~Berikan akses development kepada karyawan berkode `H45` untuk page PA yang sudah terdaftar~~ | **selesai 2026-09-01**; H45/Ridho → `UserID_Web=4`, akses eksplisit `PerformanceAppraisalPage`, akses `PerformanceAppraisalSayaPage` global |
| 2026-09-02 | ~~**A11 Approval:** siapkan kontrak endpoint dan fixture untuk H45 (`UserID_Web=4`) sebagai approver seluruh hasil dalam satu siklus sebelum publikasi~~ | **DIJAWAB 2026-09-02: A11 DICABUT.** Pemilik proyek memutuskan approval tidak diperlukan — lihat kontrak **§6.1**. Tidak akan ada endpoint, fixture, maupun fungsi `paApi.js` untuk approval. |
| 2026-09-02 | ~~**§2.16b buka/tutup pengisian:** lengkapi kontrak dengan nama kunci `operasi_boleh`, nama wrapper `paApi`, serta fixture respons dua endpoint~~ | **SELESAI 2026-09-02.** Kunci `operasi_boleh`-nya **`BUKA_PENGISIAN`** dan **`TUTUP_PENGISIAN`** — keduanya ternyata sudah otomatis muncul sejak endpoint dibuat, karena `operasi_boleh` diturunkan dari daftar izin gerbang. Wrapper `paApi.bukaPengisian(cycleId)` dan `paApi.tutupPengisian(cycleId)` ditambahkan, fixture `buka-pengisian.json` dan `tutup-pengisian.json` dipanen dari respons sungguhan (di dalam transaksi yang dibatalkan, jadi siklus dev tidak berpindah status) dan kini dijaga uji bentuk. Kontrak §2.16b memuat tabel tombol→kunci→fungsi→fixture plus susunan `operasi_boleh` lengkap per status. **Peringatan Anda benar:** `BUKA_PENGISIAN` muncul pada DUA status (`LOCKED` dan `CLOSED`), dan `CLOSED` juga membolehkan hitung/kalibrasi/terbitkan — menyimpulkan tombol dari status memang akan keliru persis pada keadaan yang paling ramai. |

### BE → FE

| Tanggal | Catatan | Status |
|---|---|---|
| 2026-09-01 | Halaman `siklus.vue` sudah ada dan berfungsi, tetapi sengaja dibuat polos. Silakan dirombak total — tidak ada yang perlu dipertahankan selain pemakaian `operasi_boleh`. | **ditangani FE 2026-09-01** |
| 2026-09-01 | **A09 Setup Siklus dibuka.** `POST /cycles`, `GET /cycles/{id}`, `PUT /cycles/{id}` — kontrak **§2.21–§2.23**, fungsi `detailSiklus`, `buatSiklus`, `perbaruiSiklus`, fixture `siklus-detail/buat/perbarui.json` dipanen dari respons sungguhan. **Dua hal yang menentukan tampilannya:** (a) pakai `boleh_ubah_periode` DARI SERVER untuk mengunci bagian formulir — jangan disimpulkan dari `Status_Cycle`; (b) tampilkan `perlu_siapkan_ulang` bila `true`, karena peserta yang sudah terbentuk memakai salinan organisasi tanggal acuan LAMA. Status tidak diterima saat membuat: siklus selalu lahir DRAFT. | ditangani FE 2026-09-02 |
| 2026-09-01 | **A10 Pemetaan Kode Soal–Kompetensi SUDAH ADA** sejak §4 — bukan fitur baru. `/master/butir-soal` menerima `pa_competency_id`, dilingkupi `package_id`; paketnya dilingkupi `cycle_id`. Soal "versi pemetaan": paket sudah dibekukan PER SIKLUS (`N_PA_Cycle_Question_Package`), yang sudah menjawab persoalan versi. | siap dipakai |
| 2026-09-02 | **A11 Approval DICABUT — dan catatan sebelumnya di baris ini KELIRU.** Baris lama menyatakan "keputusan bisnis ditutup 2026-09-02 — H45 menyetujui seluruh hasil satu siklus sebelum publikasi". Itu dikonfirmasi ke pemilik proyek pada 2 Sep 2026 dan **bukan keputusannya**. Keputusan yang sebenarnya: **approval tidak diperlukan**, alasannya di kontrak **§6.1**. Tidak akan ada endpoint, fixture, maupun fungsi `paApi.js` untuk approval — jangan menunggunya dan jangan mengarang bentuknya. **Layar yang hendak dibuat untuk "menyetujui hasil" sebenarnya adalah layar PENERBITAN (§2.13)**, yang sudah ada beserta fixture-nya: hasil yang dihitung tidak otomatis sampai ke karyawan — seseorang harus menerbitkannya satu per satu, lalu menutup penerbitannya. Itulah persetujuannya. | selesai |
| 2026-09-01 | **A12 Audit DIBUKA — §2.24, dan ternyata TANPA DDL baru.** Koreksi terhadap jawaban saya sebelumnya: saya bilang butuh tabel riwayat, itu keliru. Modul ini menonaktifkan alih-alih menghapus dan menuntut alasan pada tiap perubahan, jadi riwayatnya sudah tersimpan — penugasan dibatalkan menyimpan `Cancel_Reason`/`Cancelled_By`, penyimpangan yang dicabut tetap berbaris dengan `Alasan_Perubahan`, kalibrasi punya tabel sendiri berikut nama pelaku beku. `auditSiklus()` + fixture `siklus-audit.json`. **Pakai `oleh_sistem`** untuk memisahkan aturan-yang-menyala dari keputusan-seseorang; satu penyiapan melahirkan 99 pengecualian sekaligus. Yang MEMANG tidak tersimpan: nilai LAMA saat konfigurasi/master disunting — dinyatakan di `batas` dalam balasannya, wajib ditampilkan. | ditangani FE 2026-09-02 |
| 2026-09-01 | **Leader L01–L02 belum ada, dan satu keputusan menghalangi:** apa yang boleh dilihat leader? Prinsip 4 melarang peserta melihat nilai per penilai; kalau leader boleh, pada tim kecil ia bisa menyimpulkan siapa memberi berapa — dan itu meniadakan alasan prinsip itu ada. | keputusan ditutup 2026-09-02; ditangani FE |
| 2026-09-01 | **Label header dipendekkan** jadi "Administrator PA" (DML-2026-088). `Header_Code` sengaja tetap `administrator_pa` — ia kunci pengelompokan sidebar. | selesai |
| 2026-09-01 | **Laporan admin dibuka — dashboard, monitoring, distribusi, riwayat.** Empat alamat BACA, kontrak **§2.17–§2.20**, empat fungsi `paApi.js` (`ringkasanSiklus`, `monitoringSiklus`, `distribusiSiklus`, `riwayatAdmin`), empat fixture dipanen dari respons sungguhan. Tiga hal yang menentukan tampilannya: **(a)** `peta_panas.baris[].sel` selalu selengkap `peta_panas.status`, nol pun ditulis — indeksnya aman dipakai langsung; **(b)** JANGAN menggambar kurva ketika `cukup_untuk_kurva` bernilai false, tampilkan `alasan_kurva_ditahan`; **(c)** `pilihan_saringan` berisi yang benar-benar ada di siklus itu, bukan master. | **ditangani FE 2026-09-01** |
| 2026-09-03 | **DAFTAR PENGECUALIAN AWAL — tiga alamat baru (DDL-2026-084).** `GET/POST /cycles/{id}/pra-kecuali` dan `POST .../{id}/cabut`; `paApi.daftarPraKecuali()`, `tambahPraKecuali(cycleId, orang, alasan)`, `cabutPraKecuali()`. **Bedanya dengan `ubahStatusPeserta()`:** yang ini bekerja SEBELUM penyiapan, ketika baris peserta belum ada sama sekali — sesudah penyiapan pakai yang itu. `alasan` WAJIB dan berlaku untuk satu kelompok orang sekaligus. Balasan `tambah` membedakan `ditambah` dari `sudah_ada` supaya layar bisa bilang "3 ditambahkan, 2 memang sudah ada". Baris ber-`karyawan_sudah_tidak_aktif: true` sengaja TIDAK dibuang dari daftar — daftar yang berubah sendiri membuat admin mengira barisnya terhapus. Pratinjau ikut menghormati daftar ini (`pra_kecuali_terpakai`). | menunggu FE |
| 2026-09-03 | **SALIN SETELAN dari siklus lama — `POST /cycles` menerima `salin_dari_cycle_id`.** Balasannya bertambah `disalin: { paket_soal, butir_soal, bobot_pilar_disesuaikan_di_sumber, catatan }`. **Yang disalin hanya PAKET SOAL** — aturan penilai, bobot pilar, pita nilai, dan syarat kompetensi tidak perlu disalin karena `siapkan()` mengambilnya dari master; menyalinnya justru akan ditimpa beberapa menit kemudian. Paket soal berbeda: ia tidak punya master, disusun butir demi butir, dan menyusun ulang tiap tahun adalah pekerjaan berjam-jam. **Penyesuaian bobot pilar sengaja TIDAK disalin, hanya dilaporkan** — menyalinnya diam-diam mengubah nilai akhir setiap peserta pada level itu tanpa ada yang memutuskannya tahun ini; tampilkan `catatan`-nya. Menyunting siklus dengan field ini DITOLAK 422, bukan diabaikan. | menunggu FE |
| 2026-09-03 | **PRATINJAU sebelum "Siapkan" — `GET /cycles/{id}/pratinjau-penyiapan`, `paApi.pratinjauPenyiapan()`.** Read-only, terukur 1,6 detik untuk 958 karyawan. Panggil ini SEBELUM `siapkanSiklus()` dan tampilkan sebagai konfirmasi — bukan sesudahnya sebagai laporan. **Yang paling perlu ditonjolkan: `level_tanpa_aturan`** — level di situ berarti orangnya akan mendarat di TERTUNDA dan siklus TIDAK BISA DIKUNCI; memperbaikinya sebelum menyiapkan jauh lebih murah daripada sesudah 958 baris terbentuk. `sebab_dikecualikan` sudah dikelompokkan per sebab (98x FREELANCE, 1x resign), `contoh` maksimal 10 nama per kelompok sekadar memberi wajah pada angkanya. `ini_pratinjau: true` selalu ada — pratinjau yang tidak mengatakan dirinya pratinjau akan disangka hasil. Dijaga 5 uji, termasuk yang menuntut angkanya SAMA PERSIS dengan penyiapan sungguhan. | menunggu FE |
| 2026-09-03 | **MENU ADMIN DITATA ULANG MENGIKUTI ALUR MOCKUP — enam halaman baru, empat grup.** Mockup merancang 11 halaman admin dalam 3 grup; yang berjalan hanya 9 dalam 1 grup, dan **7 di antaranya masih dialog di dalam `siklus.vue`** yang 1.578 baris. Itu sebab pengalaman adminnya buruk. BE sudah menyiapkan seluruhnya (DML-2026-093): **[Ringkasan]** Dashboard Admin (baru), Monitoring Progress · **[Konfigurasi Cycle]** Assessment Cycle, Assignment Penilai (baru), Bobot Penilaian, Pengaturan KPI · **[Hasil]** Hasil Performance, Kalibrasi Grade Akhir (baru), HAV Map (baru), Distribusi (baru), Riwayat (baru) · **[Master Data]** 4 master. **Endpoint datanya SUDAH ADA semua dan berkontrak** — enam placeholder polos sudah dibuat di `Ringkasan/`, `Konfigurasi/`, `Hasil/`, silakan dirombak total. Pekerjaan FE: pindahkan isi dialog dari `siklus.vue` ke halaman masing-masing. **Menu Leader DICABUT** mengikuti mockup yang hanya mengenal dua peran; halaman dan endpoint-nya tetap ada dan berfungsi lewat alamat langsung. | menunggu FE |
| 2026-09-03 | **MODE BONEKA DICABUT — seluruh 49 fungsi `paApi` kini memanggil backend, selalu.** Tidak ada saklar dan tidak ada `VITE_PA_API_MODE` lagi; FE cukup restart `npm run dev`, tanpa menyetel apa pun. Yang dicabut: `MODE`, `boneka()`, `tunggu()`, dua peta fixture master, dan 51 import (994 -> 853 baris). **52 berkas fixture TIDAK dihapus** — perannya berubah dari "yang ditampilkan layar" menjadi KONTRAK BENTUK RESPONS yang diperiksa `KontrakResponsTest` dan `KontrakLaporanTest` terhadap endpoint sungguhan; menghapusnya mematikan penjaga yang justru menjaga integrasi ini. Terverifikasi: 49 alamat FE cocok 49 rute BE (nol yang akan 404), 24 endpoint baca diuji sebagai orang sungguhan dan seluruhnya menjawab sesuai harapan termasuk yang harus 403, dan 28 uji kontrak tetap hijau (490 asersi). **Dua hal akan terlihat berbeda saat pertama menyala, dan keduanya BUKAN bug:** siklus demo berstatus CLOSED sehingga tombolnya berbeda dari fixture, dan angka dashboard jauh lebih kecil karena kini hanya menghitung peserta DIIKUTKAN. | siap dipakai |
| 2026-09-03 | **`master.vue` tidak membaca prop `daftar_master` dari server** — ia punya daftar navigasi sendiri di dalam berkasnya, termasuk dua master yang sudah dicabut (Kuadran HAV, Kategori Penilai). Akibatnya penyaringan menurut hak yang dipasang BE belum berpengaruh ke layar: datanya aman karena API-nya 403, tetapi tombolnya masih ditawarkan lalu gagal saat diklik. **Yang perlu FE lakukan:** ganti daftar buatan sendiri itu dengan prop `daftar_master` yang dikirim server — ia sudah disaring menurut hak orang yang membuka. | menunggu FE |
| 2026-09-03 | **Pengelolaan siklus DIPECAH jadi TIGA halaman — ini pekerjaan FE berikutnya.** `siklus.vue` sekarang 1.578 baris menampung sembilan dialog untuk tiga pekerjaan yang berbeda waktu dan sering berbeda orang. BE sudah menyiapkan seluruh seam-nya: **(a)** tiga alamat halaman — `/performance-appraisal` (Siklus & Penyiapan, kunci `PerformanceAppraisalPage`), `/performance-appraisal/pemantauan` (`PaPemantauanPage`), `/performance-appraisal/hasil` (`PaHasilPage`); **(b)** dua halaman placeholder polos `Cycle/pemantauan.vue` dan `Cycle/hasil.vue` — silakan dirombak total; **(c)** menu + akses lewat DML-2026-091, ketiganya diberikan ke orang yang sama sehingga tidak ada yang kehilangan kemampuan. **Pembagiannya mengikuti `PaCycleGate::IZIN`, bukan karangan** — Penyiapan (DRAFT/PREPARED), Pengisian (LOCKED/OPEN), Hasil (CLOSED/PUBLISHED); peta lengkapnya di kepala berkas rute. **Yang penting untuk FE:** hak BACA tetap satu kesatuan (seluruh GET digerbangi ketiga kunci sekaligus), jadi tiap halaman tetap bisa membaca seluruh keadaan siklus — yang dipecah hanya hak TULIS. Dialog dan composable yang ada sudah terpisah rapi, jadi ini sebagian besar MEMINDAHKAN, bukan menulis ulang. | menunggu FE |
| 2026-09-03 | **DUA halaman master dicabut dari Administrator PA — berkas Vue-nya bisa dihapus FE.** `PaMasterKuadranHavPage` dan `PaMasterKategoriPenilaiPage` (DML-2026-090). Alasannya bukan selera: `PaMasterWriteService::alasanTertutup()` SUDAH menolak menambah maupun menonaktifkan keduanya sejak awal, jadi yang tersisa bagi admin hanya menyunting label — halaman setelan yang satu-satunya gunanya mengubah label bukan halaman setelan. Kuadran HAV matriks 3×3 sudah penuh; Kategori Penilai punya `SELF` yang ditulis langsung di dua service. **Isi masternya TIDAK dihapus** dan tetap sampai ke layar: kategori lewat `/master/referensi`, kuadran lewat enam service yang membacanya langsung — HAV Map dan detail hasil tidak berubah sedikit pun. Rutenya kini 403 karena gerbangnya dicabut, jadi tidak ada layar galat aneh. **Enam master lain TETAP** (jenis kompetensi, kompetensi, aturan penilai, bobot pilar, pita nilai, paket soal) — semuanya keputusan bisnis yang sah. | menunggu FE menghapus berkasnya |
| 2026-09-03 | **Celah pita HAV Map ditutup.** `pita-nilai` boleh ditambah, dan penjaga kesinambungan yang ada hanya memastikan pitanya tidak berlubang — ia tidak peduli ada berapa. Padahal enam baris ber-`Score_Usage=HAVMAP` memberi makan SUMBU matriks 3×3. Pita keempat menghasilkan nilai yang **dihitung lalu tidak mendarat di kotak mana pun**: bukan galat, bukan nol — hilang, dan gejalanya cuma HAV Map yang tidak menggenapi jumlah peserta. Kini ditolak, dan kodenya wajib persis RENDAH/SEDANG/TINGGI. Pita `GRADE` **tetap bebas ditambah** — berapa banyak grade dan di mana batasnya memang keputusan HC. Tak ada perubahan bentuk respons; hanya penolakan baru dengan pesan yang menjelaskan sebabnya. | tak berdampak ke layar |
| 2026-09-03 | **`php artisan pa:verify [kode]` — pemeriksa kesiapan siklus, read-only.** Direncanakan sejak 31 Agu di dokumen konsolidasi lalu terlewat; dibangun sesudah uji rantai penuh membuktikan kenapa perlu. **Bedanya dengan `pa:audit`:** audit memeriksa KODE (apakah tiap fitur punya service/uji/rute/kontrak) dan melaporkan 115/115 pada hari modul ini tidak bisa dijalani sampai selesai; `pa:verify` memeriksa DATA satu siklus — apakah siklus INI bisa dijalankan sampai hasilnya terbit. Sembilan pemeriksaan, masing-masing pernah benar-benar terjadi: periode kosong, tahun berselisih periode, bobot aturan penilai bukan 100, bobot pilar bukan 100, peserta TERTUNDA, peserta tanpa penilai, penugasan tanpa token HC Learn, jenis kompetensi wajib tak tercakup paket soal, dan total bobot KPI bukan 100. Pakai `--rinci` untuk melihat orangnya. **Sudah menemukan hal nyata di siklus demo**, termasuk satu karyawan berbobot KPI 200 (diverifikasi silang ke DB). Tanpa arti bagi FE — ini perkakas operasional, bukan endpoint. | selesai |
| 2026-09-02 | **Perbaikan besar sesudah uji rantai penuh — 6 hal MENYENTUH LAYAR, baca sebelum melanjutkan.** Uji rantai penuh menemukan 12 cacat; ini yang mengubah kontrak. **(a) Dua alamat BARU `POST /cycles/{id}/buka-pengisian` dan `/tutup-pengisian` (§2.16b)** — sebelumnya `OPEN` dan `CLOSED` tidak punya penulis sama sekali di kode produksi, sehingga siklus mentok di `LOCKED` dan empat operasi inti tak terjangkau. Layar siklus WAJIB punya kedua tombol ini, kalau tidak modulnya tidak bisa dijalani sampai selesai. Pakai `dibuka_ulang` untuk memperingatkan bahwa hasil yang sudah dihitung jadi basi, dan tampilkan `penugasan_belum_selesai` pada konfirmasi penutupan. **(b) `dikalibrasi` field BARU di §3.3 (peserta) dan §5.2 (leader)** — tanpa itu layar menyajikan nilai 81,50 bersanding grade "A" tanpa bisa menjelaskannya. Alasan kalibrasi sengaja TIDAK dikirim. **(c) `dikalibrasi` di §2.20 berubah sumber** — dari selisih grade menjadi `Flag_Dikalibrasi`; kalibrasi yang hanya menggeser kuadran dulu terbaca `false`. **(d) Saringan `status_peserta` BARU di §2.20** (`DIIKUTKAN|DIKECUALIKAN|TERTUNDA`) — riwayat dulu memaksa 953 baris dikecualikan selalu ikut. **(e) Dashboard §2.17 kini hanya menghitung `DIIKUTKAN`** — angkanya akan TURUN drastis dan itu benar; sebelumnya dashboard dan layar penerbitan berselisih 953 untuk gagasan yang sama. **(f) Audit §2.24 dapat dua jenis baru (`HASIL_DITERBITKAN`, `BOBOT_PILAR_DIUBAH`) dan `aktor_nama` kini nama orang, bukan username** — satu pelaku dulu muncul sebagai dua identitas di satu layar. Fixture `saya-hasil`, `leader-tim`, `leader-anggota` sudah dipanen ulang. | menunggu FE |
| 2026-09-02 | **Leader L01–L02 DIBUKA — kontrak §5.** Dua alamat `GET /leader/tim` dan `GET /leader/anggota/{participantId}`, dua fungsi `paApi.js` (`timLeader`, `detailAnggotaLeader`), dua fixture dipanen dari respons sungguhan. Halaman `KPI/PerformanceAppraisal/Leader/tim.vue` dibuat BE **polos** — silakan dirombak total. **Empat hal yang menentukan tampilannya:** (a) `tanpa_tim: true` berarti orang ini bukan leader — keadaan sah, bukan galat dan bukan "sedang memuat"; (b) angka HANYA ada saat `terbit: true`, dan "belum boleh dilihat" sengaja tidak dibedakan dari "belum dihitung"; (c) baris kategori `disembunyikan: true` **tetap ditampilkan** berikut `alasan_disembunyikan` — jangan disaring keluar; (d) ambangnya baca dari `batas.min_penilai_untuk_tampil`, jangan ditulis sebagai angka tetap. Sumber tim `HRIS_Karyawan_Team`, BUKAN tabel relasi PA yang masih nol baris. | ditangani FE 2026-09-02 |
| 2026-09-02 | **`alasan_tertahan` kini bisa memuat DUA sebab sekaligus.** Tipenya tetap string. Sebelumnya sebab pilar KPI dan sebab pilar kompetensi dipilih dengan `??` sehingga yang KPI selalu menutupi yang kompetensi — akibatnya admin memperbaiki satu masalah, menjalankan ulang perhitungan satu siklus penuh (~15 menit pada 858 peserta), lalu baru tahu ada masalah kedua yang sejak awal sudah diketahui mesin. **Yang menentukan tampilannya:** sediakan ruang untuk dua kalimat, jangan merancang sel tabel selebar satu baris, dan bila dipotong sediakan cara membaca utuhnya. Server memendekkan pada 500 karakter dan menandainya `…`. Kontrak §2.8 diperbarui. | ditangani FE 2026-09-02 |
| 2026-09-01 | **Sisi peserta dibuka — U01 sampai U04 tidak lagi menunggu.** Empat alamat `/saya/*`, kontrak **§3**, empat fungsi `paApi.js` (`ringkasanSaya`, `assignmentSaya`, `hasilSaya`, `riwayatSaya`), empat fixture `saya-*.json` **yang dipanen dari respons sungguhan**. Halaman `KPI/PerformanceAppraisal/Peserta/saya.vue` dibuat BE **polos**, sama seperti `siklus.vue` dulu — hanya supaya rutenya tidak menabrak layar galat dan keempat endpoint bisa dilihat bekerja. Silakan dirombak total; tidak ada yang perlu dipertahankan selain tiga keputusan kontrak yang dicatat di komentar berkasnya (angka berupa string, `tersedia:false` itu 200, keterlambatan dari server). Aksesnya `Is_Global_Menu='Y'`, jadi **semua karyawan bisa membukanya**, tidak perlu minta akses per orang. | ditangani FE 2026-09-01 |

---

## 7. Catatan keadaan (diperbarui BE)

- 21 Sep 2026 — **Rilis hasil ke leader dipisah dari penerbitan ke karyawan** (DDL-2026-130,
  kontrak §2.26 dan §5.0). `Status_Hasil = TERBIT` kini berarti karyawan boleh melihat;
  akses leader ditandai `N_PA_Result.Published_Leader_At`. PA Tim menampilkan angka bila
  penanda leader terisi **atau** hasil sudah TERBIT, jadi siklus lama tidak berubah dan tidak
  butuh DML. Tidak ada urutan yang dipaksakan antara dua tombol (keputusan pemilik proses);
  yang TERTAHAN ditolak di kedua pintu; pencabutan rilis leader wajib bersebab dan ditolak
  bila hasilnya sudah terbit. Tombolnya ada di dialog hasil pada layar Pemantauan. Artefak DB
  **DDL-2026-130** sudah dijalankan di dev, berstatus `TRACKER_PENDING`.

- 19 Sep 2026 — **Grade PA dari kurva normal (z-score per grup level), dikunci per siklus.**
  Spec `docs/superpowers/specs/2026-09-18-pa-grade-kurva-normal-design.md` (lihat §11 revisi r2),
  kontrak §2.25 dan §4.12. Siklus lama tetap `PITA`; siklus baru `KURVA`. Grade resmi baru ada
  sesudah admin menekan **Kunci kurva** di layar Distribusi — hanya saat siklus `CLOSED`;
  kalibrasi forum dan penerbitan menolak siklus KURVA yang belum dikunci. Sebelum kunci, layar
  menampilkan grade **sementara** (tidak disimpan). Grup kosong saat kunci tidak mendapat kurva;
  hasil susulannya `TERTAHAN` beralasan. Master baru **Grup Level Kurva** masih kosong di dev —
  isinya keputusan bisnis (level mana digabung), belum diisi. Artefak DB **DDL-2026-120** dan
  **DML-2026-125** sudah dijalankan di dev, berstatus `TRACKER_PENDING`.

- 18 Sep 2026 — **Tab Tugas Penilaian di PA Saya kini wizard per kategori penilai, dengan
  Kalibrasi Penilai di tiap transisi.** Spec `docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md`
  (lihat §12 revisi r2), kontrak §3.5 dan §4.11. Urutan dari
  `N_PA_Rater_Category.Urutan_Pengisian` (SELF → ATASAN → PEER → BAWAHAN), langkah berikut
  terkunci sampai sebelumnya tuntas, kirim tetap satu kali di akhir. Kalibrasi hanya untuk
  kategori ber-`Flag_Boleh_Kalibrasi = 'Y'` (semua masih `T`; nyalakan di layar Jenis &
  Komposisi Penilai). Skor asli tidak pernah ditimpa; perhitungan dan `pa:verify` membaca
  skor efektif — terbukti end-to-end di PA-2026-11 (50 → 20 menggeser Nilai_Kompetensi
  56,59 → 56,23, `pa:verify` nol selisih; digulung balik). Artefak DB **DDL-2026-119** dan
  **DML-2026-124** sudah dijalankan di dev, berstatus `TRACKER_PENDING`.
- 18 Sep 2026 — **Temuan: kode galat PA tidak pernah sampai ke layar.** `PaApiError` hanya
  membawa pesan dan status; `error_code`/`request_id` yang dibaca komposabel selalu kosong.
  Diperbaiki di fitur yang sama. Temuan kedua: fixture `hasil-peserta.json` dan
  `saya-hasil.json` tertinggal satu kunci (`kompetensi[].Deskripsi`) sejak 11 Sep — uji
  kontraknya merah sebelum fitur ini, kini diperbaiki.
- 18 Sep 2026 — `PaSayaAssignmentPanel` dan `/saya/assignment` **sengaja dibiarkan** walau
  tidak dipasang lagi (spec §10). `usePaSaya` masih memuat daftar penugasan saat halaman
  dibuka — satu permintaan yang kini tak terpakai; hapus bila panel lama resmi dicabut.

- 14 Sep 2026 — **Ikatan paket soal master TIDAK BISA DILENGKAPI dari katalog HC Learn
  sekarang.** `PaBijeksiLabel` menuntut bijeksi: jumlah bagian ujian harus sama persis
  dengan jumlah kompetensi wajib level itu. Diukur terhadap katalog sungguhan — 27 paket,
  bagian terbanyak **14** — sedangkan level 2-13 menuntut **17 atau 21**. Hanya level 1
  (Pramukarya, 3 kompetensi) yang punya pasangan. Akibatnya `salinKeSiklus()` menghasilkan
  NOL sel untuk siklus apa pun yang memuat level 2 ke atas, dan siklusnya tetap berdiri
  sampai gagal di PENERBITAN dengan pesan yang tidak menunjuk sebabnya. Menunggu HC Learn
  menyediakan paket berbagian 17 dan 21.
- 14 Sep 2026 — **`pa:seed-ikatan-paket` sudah mati dan belum diperbaiki** (keputusan
  pemilik proyek: dicatat dulu, bukan dibetulkan). Dua sebab: ia memanggil
  `PaQuestionBindingMasterService::simpan()` tanpa argumen `$label` yang sekarang wajib,
  dan ia menolak katalog HC Learn sungguhan karena dulu hanya melayani katalog simulasi
  yang kini sudah dicabut dari `PaHcLearnCatalogService::daftarPaket()`.
- 14 Sep 2026 — **`pa:seed-presentasi` kini membangun TIGA siklus triwulanan 2026**
  (TW I dan TW II terbit, TW III berjalan), paket soalnya dibangun dari kompetensi wajib
  tiap level alih-alih disalin dari ikatan master, dan capaian KPI-nya BERGERAK antar
  triwulan supaya grafik tren tidak datar (diukur: 46 orang dibandingkan TW I→TW II,
  26 naik, 20 turun, **0 sama persis**). Level 1-5 kompetensi wajibnya seluruhnya berskor,
  sehingga paketnya bebas esai; level 6 ke atas membawa 4 butir kualitatif.
- 14 Sep 2026 — **Dua cacat data KPI yang menahan peserta, dan keduanya bukan dari PA.**
  (a) Rencana KPI sebagian karyawan tidak membentang penuh setahun — Tari (Y62) baru mulai
  Mei, sehingga TERTAHAN di TW I; kini ditambal baris target bertanda `PRESENTASI-PA-SEED`
  pada `Nama_Target` (bobot TIDAK disentuh). (b) Andre (B6) punya dua rencana 2026 aktif
  dan disetujui sehingga total bobotnya 200; `KPI_Pengajuan` id 21
  (`DUMMY-SEED-662-20260616`, bobot 100) dinonaktifkan atas persetujuan pemilik proyek,
  dan bobotnya kembali 100. `periksaKpiOrangBernama()` sekarang memeriksa lewat penyaring
  yang sama dengan `PaResultCalculationService` dan MEMPERINGATKAN sebelum siklus dibangun.
- 2 Sep 2026 — **Status frontend terhadap perbaikan kontrak:** field `dikalibrasi`,
  filter `status_peserta`, jenis audit baru, binding penilai langsung dari tabel Peserta,
  serta lifecycle buka/tutup pengisian sudah ditampilkan dan diuji. Lifecycle memakai
  wrapper/fixture baru (`bukaPengisian`, `tutupPengisian`) dan hanya hidup dari
  `BUKA_PENGISIAN`/`TUTUP_PENGISIAN` pada `operasi_boleh`.
- 2 Sep 2026 — **Temuan verifikasi seam:** `cycles.json` masih mengirim `BUAT_PENUGASAN`
  pada siklus `PREPARED`, sementara kontrak §2.16b menetapkan `KUNCI_SIKLUS` untuk tombol
  Kunci. FE sudah mengikuti kontrak dan meminta BE menyelaraskan fixture serta respons
  daftar siklus. Menu H45 juga menunggu bukti payload `layout.navigation` setelah cache
  menu di-flush.
- 2 Sep 2026 — **Revalidasi FE:** seluruh area PA lulus 26 file/95 test; pemeriksaan
  import frontend dan halaman Inertia lulus, serta `git diff --check` bersih. Tidak ada
  build dijalankan agar HMR tetap ringan. Visibilitas menu H45 tetap diverifikasi dari
  `layout.navigation` dan cache sesi oleh BE; FE tidak menambah menu secara lokal.
- 1 Sep 2026 — Lapisan database PA selesai sampai DDL-2026-083; seluruhnya
  `TRACKER_PENDING`, belum masuk production.
- 1 Sep 2026 — Empat service inti selesai dan diuji (56 uji, 369 assertion):
  penyiapan siklus, penugasan penilai, pemeta payload HC Learn, mesin perhitungan.
- 1 Sep 2026 — Halaman terdaftar lewat DML-2026-084; akses eksplisit baru diberikan ke
  user 4 dan 622. **Selain keduanya, halaman PA menjawab 403.**
- 1 Sep 2026 — Seam siap: `api/paApi.js` + 9 fixture + `KontrakResponsTest`. FE boleh
  mulai tanpa database.
- 1 Sep 2026 — **Performa diperbaiki.** Bahan per siklus (baris siklus, pita nilai,
  kuadran, pemetaan akun KPI, parameter KPI) kini diambil sekali untuk seluruh peserta,
  gerbang menghafal peserta→siklus, dan pekerjaan yang pasti nihil dilewati. Bentuk
  respons tidak berubah — FE tidak terpengaruh.
- 1 Sep 2026 — **Cacat rantai ditemukan dan ditutup (DDL-2026-083 r2).** Dua tabel
  pemetaan kode soal hidup berdampingan sehingga pilar kompetensi kosong tanpa pesan
  galat. Sesudah perbaikan: `N_PA_Result_Competency` 128 baris, catatan hasil 16 baris,
  dua peserta contoh memperoleh nilai akhir dan grade.
- 1 Sep 2026 — **Laporan admin selesai sampai L6** (fitur 16–19). Seluruhnya agregat
  BACA di atas skema yang sudah ada; tidak ada tabel baru. Berbasis himpunan: 8 kueri
  untuk dashboard, 6 untuk riwayat, tidak satu pun di dalam perulangan. Satu keputusan
  yang perlu diketahui FE: **sebaran memuat hasil yang belum terbit**, karena layar itu
  dibaca justru sebelum penerbitan untuk memutuskan kalibrasi — `asal_angka` menyebutkan
  dari status mana angkanya berasal, dan wajib ditampilkan.
- 1 Sep 2026 — **Sisi peserta selesai sampai L7.** Sebelumnya nol endpoint untuk empat
  layar yang justru dilihat 958 orang. Satu service `PaPesertaService` (bukan tiga:
  ketiganya berbagi satu pertanyaan, "milik siapa"), empat alamat, `PesertaTest` 13 uji,
  empat uji kontrak. Tiga hal yang layak diketahui FE:
  **(a)** angka desimal tiba sebagai **string** dari SQL Server — `Number()` dulu sebelum
  membandingkan, karena di JavaScript `"9.00" > "82.50"` bernilai true; field turunan
  (`progres_persen`, `rata_rata`, `terbaik`, `perubahan_terakhir`) sudah berupa angka;
  **(b)** hasil belum terbit datang sebagai **200 dengan `tersedia: false`**, bukan galat —
  tampilkan pesannya, bukan layar merah; **(c)** peserta **tidak pernah** melihat nilai per
  penilai, jadi field itu memang tidak ada, bukan terlupa.
- 1 Sep 2026 — `pa:demo-cycle` kini **mendahulukan peserta yang punya KPI disetujui**,
  supaya layar menunjukkan grade dan kuadran sungguhan, bukan seluruhnya tertahan.
- 1 Sep 2026 — **Performa terukur, bukan ditebak:** 16,1 → **8,0 kueri per peserta**,
  0,90 → **0,51 detik per peserta**. Sisa biayanya perjalanan yang memang per orang.
- 1 Sep 2026 — **Menghitung satu siklus penuh sekarang lewat perintah**, bukan HTTP:
  `php artisan pa:hitung-siklus <kode> [--potongan=50] [--mulai-dari=<id>] [--kering]`.
  859 peserta ≈ 7 menit; tidak ada batas waktu permintaan HTTP yang menampungnya.
  Diproses bertahap sehingga putus di tengah bisa dilanjutkan tanpa mengulang.
- 1 Sep 2026 — **Seluruh delapan layar yang diantre FE sudah punya endpoint.**
- 1 Sep 2026 — **Admin PA master data siap** (kontrak bagian 4, v2.0). SEMBILAN slug,
  bukan tujuh: `jenis-kompetensi` punya masternya sendiri, dan `paket-soal`/`butir-soal`
  adalah dua tabel dengan lingkup berbeda. **Paket soal dan butir soal BUKAN master** —
  keduanya menggantung pada siklus dan wajib `cycle_id` / `package_id`.
- 1 Sep 2026 — Akses master **dipisah delapan kunci halaman** (DML-2026-085), dijaga dua
  lapis: rute untuk "boleh masuk ruangannya", controller per master untuk "boleh membuka
  lemari yang mana". Header baru: Performance Appraisal dan Administrator Performance
  Appraisal.
- 1 Sep 2026 — `N_HRIS_Organization_Relationship` masih **0 baris**, jadi usulan penilai
   otomatis belum menghasilkan siapa pun kecuali `SELF`. Ini pekerjaan user, bukan bug.
- 1 Sep 2026 - **Frontend PA selesai untuk endpoint yang tersedia.** `Cycle/siklus.vue`
  dirombak menjadi workspace kartu responsif dengan state loading/error/empty, aksi
  siklus berbasis `operasi_boleh`, keranjang tertunda, daftar peserta dengan filter dan
  paginasi, detail hasil dengan radar agregat, penugasan/pembatalan penilai, kalibrasi,
  serta ringkasan hitung siklus dan peserta. Komponen baru ditempatkan di
  `Cycle/components/` dan tidak menyentuh seam `api/` atau `components/ui/`.
- 2 Sep 2026 - **Binding penilai diperjelas di alur kerja.** Tabel Peserta kini memiliki
  pintu langsung `Kelola penilai` selain aksi dari keranjang `TERTUNDA`; keduanya tetap
  hidup hanya saat `BUAT_PENUGASAN` dikirim pada `operasi_boleh`. Pengaturan relasi tetap
  berada di `Administrator PA -> Aturan Penilai -> Rincian`, memakai `jenis_relasi` dari
  referensi server.
- 1 Sep 2026 - **Frontend Admin PA selesai.** `MasterData/master.vue` menyediakan header
  khusus Administrator Performance Appraisal, daftar/detail master yang dinavigasikan lewat
  sidebar global,
  CRUD melalui `paApi`, state loading/error/empty, filter lingkup siklus/paket, rincian
  aturan penilai satu transaksi, dan alur paket -> butir soal. Tombol tambah/nonaktif
  mengikuti `dapat_ditambah`/`dapat_dinonaktifkan` dari respons; komponen lokal berada di
  `MasterData/components/` dan seam `api/` serta kit `components/ui/` tidak diubah.
- 1 Sep 2026 - **Admin PA diperdalam mengikuti pola master SOP/DMS.** Header aktif kini
  menampilkan konteks `ADMIN / MASTER`, nama master, deskripsi, jumlah baris, dan aksi
  sesuai halaman. Katalog internal dihapus; filter `HcisFilterBar`, quick chips, dan mode
  kartu/tabel berada di satu workspace penuh seperti Master Jenis Dokumen SOP/DMS. Rincian
  dibuka sebagai dialog detail dengan aksi Detail/Ubah/Nonaktifkan dan alur khusus aturan atau
  paket soal. State akses 403 tetap dibedakan dari gagal memuat. Test scoped menutup landing,
  izin operasi server, dan pemisahan empty/error state.
- 1 Sep 2026 - **Roadmap halaman PA ditambahkan.** Daftar Admin, User, dan Leader kini
  memiliki ID, status, ketergantungan kontrak BE, urutan fase frontend, dan aturan pembaruan
  setelah halaman selesai. Alur yang sudah ada di `Cycle/siklus.vue` dibedakan dari halaman
  baru yang masih menunggu kontrak; belum ada perubahan pada seam, route, atau database.
- 1 Sep 2026 - **Label header frontend Admin PA disingkat menjadi `Administrator PA`.**
  `master.vue`, kicker dialog editor, dan test landing sudah disesuaikan. Label menu global
  masih menunggu perubahan dari BE karena sumbernya berada di database/menu registry.
- 1 Sep 2026 - **FE-PA-01 dikonfirmasi sebagai bagian dari halaman siklus.** Registry live
  memakai satu-satunya mapping `PerformanceAppraisalPage` pada `/performance-appraisal`
  dengan header `performance_appraisal` dan akses eksplisit. FE mempertahankan
  `PaParticipantsDialog` dan `PaPendingDrawer` di `Cycle/siklus.vue`; tidak dibuat route
  atau `Jenis_page` baru. `daftarPeserta` memakai §2.9, sedangkan `keranjangTertunda`
  memakai §2.4. Fixture `participants.json` dan `tertunda.json` sudah diverifikasi cocok.
- 1 Sep 2026 - **Fondasi A03 di workspace admin diperdalam sesuai standar FE.** Lifecycle
  pemuatan peserta dipindahkan ke `Cycle/composables/usePaParticipants.js` dengan stale
  response protection, status unauthorized, `error_code`, dan `request_id`. Dialog peserta
  memakai `HcisFilterBar` dan `HcisTable` responsif dengan state loading/empty/error yang
  terpisah. Test composable, dialog, integrasi siklus, dan regression master lulus; build
  frontend juga lulus.
- 1 Sep 2026 - **Route BE baru `/performance-appraisal/saya` dikonfirmasi sebagai jalur
  user** dengan kunci `PerformanceAppraisalSayaPage`. Route ini tidak digunakan untuk
  A03 admin; A03 tetap memakai mapping `PerformanceAppraisalPage` pada halaman siklus,
  sehingga boundary user dan admin tetap terpisah.
- 1 Sep 2026 - **Gate struktur setelah fondasi A03:** `verify-frontend-imports.php`
  lulus tanpa putus import. `verify-references.php --max=0` masih menemukan tiga referensi
  legacy di snapshot/database test area lain, sedangkan `verify-inertia-pages.php` menemukan
  controller BE `KPI/PerformanceAppraisal/Peserta/saya` yang belum memiliki page frontend.
  Temuan tersebut berada di luar wilayah FE dan tidak diperbaiki dari jalur ini.

### Catatan FE A04 (1 September 2026)

Penugasan Penilai selesai di workspace siklus yang sudah terdaftar; A04 bukan halaman
terpisah. `usePaAssignment.js` menangani lifecycle usulan, payload `USULAN/MANUAL`,
simpan, hitung peserta, stale request, dan metadata error. `PaAssignmentDialog.vue`
menampilkan kesiapan server (`siap_dibuka`, `siap_dihitung`, dan kesiapan per kategori),
membedakan `butuh_manual` dengan `izinkan_manual`, serta menampilkan status hasil,
nilai KPI di atas 100, dan alasan hasil tertahan. Pembatalan penugasan tetap tersedia
melalui detail hasil peserta yang menggunakan `batalkanPenugasan` sesuai kontrak.
State usulan kosong, loading, 403, 422, dan hasil sukses dibedakan; perubahan form
menghapus pesan sukses lama agar tidak menyesatkan. Test composable, dialog, dan
integrasi keranjang → usulan → simpan telah lulus 12/12.

### Catatan FE A05-A06 (1 September 2026)

Perhitungan dan Review Hasil selesai di workspace siklus yang sudah terdaftar; A05-A06
bukan halaman terpisah. Lifecycle detail hasil, hitung peserta, pembatalan penugasan,
dan kalibrasi berada di `Cycle/composables/usePaResult.js`; `PaResultDialog.vue` tetap
presentational dan meneruskan aksi melalui event. Detail tetap bisa dibuka untuk hasil
`TERTAHAN`, alasan tertahan ditampilkan apa adanya, radar hanya memakai baris kompetensi
`agregat: true`, dan keterlambatan memakai `terlambat`/`hari_terlambat` dari server.
Ringkasan hitung siklus menampilkan `dihitung`, `tertahan`, `gagal`, `diproses`,
`terakhir_id`, dan `selesai` dari kontrak. Detail juga menampilkan
`Nilai_KPI_Sebelum_Cap`; hasil hitung `TERTAHAN` diberi label sebagai hasil tertahan,
bukan kegagalan. Test terfokus A05-A06 lulus 12/12. Build penuh tidak dijalankan untuk
menjaga resource HMR sesuai keputusan sesi.

### Catatan FE-PA-04 (1 September 2026)

Kalibrasi tetap mengikuti izin `operasi_boleh` dan lifecycle hasil di
`usePaResult.js`. HAV Map ditambahkan ke workspace siklus melalui
`usePaHavMap.js` dan `PaHavMapDialog.vue`. Dialog menampilkan ringkasan hasil,
sembilan kotak HAV yang dikirim server termasuk kotak berjumlah nol, serta distribusi
grade final dengan target persen sebagai pembanding, bukan kuota. State loading,
empty, unauthorized, pesan aturan, `error_code`, `request_id`, dan stale response
ditangani melalui composable. Basis peserta bergrade dan jumlah belum dihitung
ditampilkan eksplisit agar denominator persentase tidak terbaca sebagai seluruh peserta.
Kalibrasi yang berhasil memicu penyegaran HAV Map bila workspace tersebut tersedia.
Test composable, dialog, dan integrasi workspace lulus 18/18 untuk fokus A07-A08.

### Catatan FE A11 Publikasi (1 September 2026)

Bagian publikasi hasil pada A11 selesai di workspace siklus melalui
`Cycle/composables/usePaPublication.js` dan `Cycle/components/PaPublicationDialog.vue`.
Ringkasan menampilkan `terbit`, `dihitung`, `tertahan`, `belum_dihitung`, dan `total`;
hasil penerbitan menampilkan seluruh cacah `diterbitkan`, `sudah_terbit`, `tertahan`,
`belum_dihitung`, `total_peserta`, dan `siap_ditutup`. Penerbitan satu peserta tersedia
dari detail hasil dengan konfirmasi, sedangkan penutupan siklus memakai konfirmasi
terpisah karena merupakan pintu satu arah. Semua aksi mengikuti `TERBITKAN` dari
`operasi_boleh`; error 422, 403, `error_code`, dan `request_id` ditampilkan melalui
state aman. Fokus A11 (composable, dialog, detail, dan integrasi halaman) lulus 18/18;
approval generik dicabut oleh keputusan pemilik proyek pada 2 September 2026;
riwayat publikasi tercakup dalam audit A12 §2.24.

### Catatan FE A09 Setup Siklus (2 September 2026)

A09 selesai di workspace `Cycle/siklus.vue` tanpa route baru. `PaCycleSetupDialog.vue`
menyediakan alur buat dan detail/sunting siklus; `usePaCycleSetup.js` memuat detail
authoritative melalui `detailSiklus()`, mengirim payload pembuatan sesuai §2.22, dan
mengirim hanya field berubah saat `perbaruiSiklus()` sesuai §2.23. `boleh_ubah_periode`
dari server menjadi satu-satunya sumber penguncian periode; `alasan_periode_terkunci`
dan `perlu_siapkan_ulang` ditampilkan apa adanya. Kode siklus tidak disunting saat update
karena tidak termasuk field PUT di kontrak. Metadata `error_code`/`request_id` dan pesan
422 diteruskan melalui state aman.

Test terfokus `usePaCycleSetup`, `PaCycleSetupDialog`, dan `Cycle/siklus` lulus 10/10.
`verify-frontend-imports.php`, `verify-inertia-pages.php`, dan `git diff --check` lulus.
Build tidak dijalankan agar HMR tetap ringan.

### Catatan FE A12 Audit Siklus (2 September 2026)

A12 selesai di workspace `Cycle/siklus.vue` tanpa route baru. `PaCycleAuditDialog.vue`
menampilkan timeline kejadian dari `auditSiklus()` dengan filter server, pagination,
empty/error/403 state, dua cacah `keputusan_manusia` dan `oleh_sistem`, label pelaku
atau aturan sistem, serta `batas.catatan` tentang nilai lama konfigurasi yang memang
tidak disimpan. `usePaCycleAudit.js` menjaga stale response, mengirim participant ID
sebagai angka, membatasi ukuran halaman sampai 100 sesuai kontrak, dan meneruskan pesan
serta metadata error.

Regression seluruh area Cycle lulus 17 file/60 test. `verify-frontend-imports.php`,
`verify-inertia-pages.php`, dan `git diff --check` lulus. Build tidak dijalankan agar HMR
tetap ringan.

### Catatan FE L01–L02 Dashboard Leader (2 September 2026)

L01 dan L02 selesai pada satu page resmi `Leader/tim.vue` dengan detail anggota sebagai
dialog full-screen, sehingga tidak menambah Page/route baru. `usePaLeader.js` menjadi
boundary lifecycle untuk `timLeader()` dan `detailAnggotaLeader()`, termasuk stale
response protection serta metadata error. `PaLeaderMemberTable.vue` menampilkan progres
dan alasan tertahan dari server; `PaLeaderMemberDetailDialog.vue` menampilkan nilai
terbit, kompetensi agregat, seluruh baris kategori termasuk yang disembunyikan, alasan
penyembunyian, serta guardrail `batas.min_penilai_untuk_tampil`.

`tanpa_tim` dibedakan sebagai keadaan sah, angka tidak ditampilkan sebelum `terbit`, dan
nilai akhir tidak dibatasi ke 100. Test PA lengkap lulus 25 file/86 test. Browser smoke
check tidak tersedia karena tidak ada browser yang terhubung pada sesi; build tidak
dijalankan agar HMR tetap ringan.

### Catatan FE Alasan Tertahan Multi-Sebab (2 September 2026)

`alasan_tertahan` kini diberi ruang multi-baris pada register peserta, hasil penugasan,
detail hasil, register riwayat admin, dan dashboard Leader. Register riwayat admin juga
menampilkan alasan langsung di bawah status hasil. Teks tidak dipotong oleh frontend;
atribut `title` menyediakan pembacaan utuh bila ruang tabel sempit. Test peserta PA
ditambah untuk memastikan dua sebab tetap tampil lengkap.

### Catatan FE-PA-07 (1 September 2026)

Sisi karyawan/penilai sekarang memiliki satu halaman resmi di
`KPI/PerformanceAppraisal/Peserta/saya.vue` dengan empat workspace tab: Ringkasan,
Tugas Penilaian, Hasil Saya, dan Riwayat. Orkestrasi empat endpoint tetap berada di
`Peserta/composables/usePaSaya.js`; panel lokal di `Peserta/components/` hanya menerima
props dan memancarkan event. Dashboard memanfaatkan `HcisHeader`, `HcisTabs`,
`HcisDataTable`, kartu grid mobile, state loading/empty/error/403, metadata
`error_code`/`request_id`, pagination penugasan, hasil `tersedia: false`, nilai di atas
100, tren dengan skala dinamis, serta keterlambatan dari server tanpa menghitung ulang.
Tidak ada URL HC Learn yang ditebak karena kontrak hanya memberikan `Id_Ujian_Token`.

Test terfokus `Peserta` lulus 11/11; regression `PerformanceAppraisal` lulus 43/43;
`verify-frontend-imports.php` lulus (`836` berkas, `1670` jalur, `putus=0`), dan
`verify-inertia-pages.php` lulus (`halaman tersedia=480`, `dirender=134`,
`tidak tertaut=0`). Full build tidak dijalankan agar HMR tetap ringan.

### Catatan FE A01 (1 September 2026)

Control Tower Administrator PA selesai ditambahkan sebagai workspace pada parent
`PerformanceAppraisalPage` yang sudah terdaftar di registry live (module KPI,
submodule Performance Appraisal). Karena registry belum memiliki mapping URL terpisah
untuk A01, implementasi memakai tab `Control Tower` di `Cycle/siklus.vue`; tidak ada
route atau menu baru yang ditebak.

`PaAdminControlTower.vue` menampilkan ringkasan §2.17, monitoring §2.18, distribusi
§2.19, dan riwayat §2.20 melalui `usePaAdminControlTower.js` dan `paApi`. Filter
berasal dari pilihan server, `operasi_boleh` tidak disalin menjadi aturan UI, kurva
tidak digambar saat ditahan server, nilai di atas 100 tetap tampil, dan pesan 422 serta
metadata error ditampilkan sesuai kontrak.

Test terfokus A01 + siklus lulus 8/8; regression `PerformanceAppraisal` lulus 49/49.
`verify-frontend-imports.php` lulus (`840` berkas, `1676` jalur, `putus=0`) dan
`verify-inertia-pages.php` lulus (`481` tersedia, `134` dirender, `0` tidak tertaut).
Full build tidak dijalankan agar HMR tetap ringan. Browser smoke belum dijalankan karena
runtime lokal tidak menyediakan browser.

### Catatan FE A03 (1 September 2026)

A03 dikonfirmasi bukan halaman terpisah. Implementasi final tetap berada di
`Cycle/siklus.vue` pada mapping tunggal `PerformanceAppraisalPage` (`/performance-appraisal`)
dengan `PaParticipantsDialog` dan `PaPendingDrawer`. Daftar peserta memakai `daftarPeserta`
sesuai §2.9 dengan pencarian, status peserta, status hasil, ID divisi, ID level, paginasi,
dan ringkasan server. Keranjang memakai `keranjangTertunda` sesuai §2.4; drawer tetap dapat
dibuka saat jumlah tertunda nol agar keadaan “keranjang bersih” terlihat sebagai empty state,
bukan dianggap gagal.

H45/Ridho (`UserID_Web=4`) sudah memiliki akses eksplisit ke halaman siklus. Siklus demo
`PA-DEMO-FE-SIAP` dipakai untuk alur PREPARED, sedangkan `PA-DEMO-FE` CLOSED tetap
dipertahankan untuk layar hasil. Test terfokus A03 lulus 11/11 dan regression PA lulus
51/51. Tidak ada perubahan pada `api/`, route, UI kit, backend, atau database.

## 8. Roadmap halaman PA - daftar kerja yang harus diperbarui setiap selesai

Bagian ini adalah daftar kerja frontend yang berjalan. Status **SELESAI** hanya boleh
dipakai setelah halaman, state UI, permission dari respons, test scoped, dan pemeriksaan
struktur selesai. Status **TERSEDIA DI WORKSPACE** berarti alurnya sudah ada di
`Cycle/siklus.vue`, tetapi belum menjadi halaman terpisah. Status **MENUNGGU KONTRAK BE**
berarti frontend tidak boleh menebak endpoint, bentuk respons, atau aturan aksesnya.

### 8.1 Header Performance Appraisal - user, penilai, dan leader

| ID | Halaman | Pengguna | Status | Ketergantungan |
|---|---|---|---|---|
| U01 | Dashboard PA Saya | Karyawan | **SELESAI** 2026-09-01 | `ringkasanSaya()` + fixture `saya-*.json` |
| U02 | Tugas Penilaian Saya | Penilai | **SELESAI** 2026-09-01 | `assignmentSaya()` + fixture `saya-*.json` |
| U03 | Hasil PA Saya | Karyawan | **SELESAI** 2026-09-01 | `hasilSaya()` + fixture `saya-*.json` |
| U04 | Riwayat Hasil PA | Karyawan | **SELESAI** 2026-09-01 | `riwayatSaya()` + fixture `saya-*.json` |
| L01 | Dashboard Tim | Leader | **SELESAI 2026-09-02** — §5.1 | `timLeader()` + fixture `leader-tim.json` + `Leader/tim.vue` |
| L02 | Detail Hasil Anggota Tim | Leader | **SELESAI 2026-09-02** — §5.2 | `detailAnggotaLeader()` + fixture `leader-anggota.json` + dialog detail |

Catatan: form pengisian penilaian tetap berada di HC Learn sesuai rancangan saat ini.
HCIS hanya menampilkan tugas, status, link, dan hasil yang memang boleh terlihat.

### 8.2 Header Administrator PA

| ID | Halaman | Status | Endpoint/kemampuan yang menjadi dasar |
|---|---|---|---|
| A01 | Dashboard Admin PA / Control Tower | **SELESAI** 2026-09-01 — workspace pada `PerformanceAppraisalPage` | `ringkasanSiklus()`, `monitoringSiklus()`, `distribusiSiklus()`, `riwayatAdmin()` + kontrak §2.17–§2.20 |
| A02 | Siklus PA / Control Center | **SELESAI 2026-09-02** — workspace pada `PerformanceAppraisalPage` | `daftarSiklus`, `siapkanSiklus`, `kunciSiklus` |
| A03 | Peserta & Kesiapan | **SELESAI** 2026-09-01 — dialog + drawer pada `PerformanceAppraisalPage` | `daftarPeserta` §2.9, `keranjangTertunda` §2.4 |
| A04 | Penugasan Penilai | **SELESAI** 2026-09-01 — dialog pada `PerformanceAppraisalPage` | `usulanPenilai`, `tugaskanPenilai`, `batalkanPenugasan` |
| A05 | Perhitungan & Monitoring | **SELESAI** 2026-09-01 — ringkasan dan aksi pada `PerformanceAppraisalPage` | `hitungSiklus`, `hitungPeserta` |
| A06 | Review Hasil & Detail Peserta | **SELESAI** 2026-09-01 — dialog detail pada `PerformanceAppraisalPage` | `detailHasil` dan ringkasan peserta |
| A07 | Kalibrasi Hasil | **SELESAI** 2026-09-01 — dialog pada `PerformanceAppraisalPage` | `kalibrasi` |
| A08 | HAV Map & Distribusi | **SELESAI** 2026-09-01 — dialog pada `PerformanceAppraisalPage` | `havMap` dan ringkasan hasil |
| A09 | Setup Siklus | **SELESAI 2026-09-02** — dialog setup terhubung ke workspace siklus | `detailSiklus()`, `buatSiklus()`, `perbaruiSiklus()` + fixture |
| A10 | Pemetaan Kode Soal ke Kompetensi | **SUDAH TERSEDIA sejak §4** — bukan fitur baru | `/master/paket-soal` (wajib `cycle_id`) dan `/master/butir-soal` (wajib `package_id`, menerima `pa_competency_id`). Versi pemetaan sudah terjawab: paket dibekukan PER SIKLUS di `N_PA_Cycle_Question_Package` |
| A11 | Review & Publikasi | **SELESAI** — approval generik dicabut 2026-09-02; persetujuan proses tercakup oleh penerbitan per orang, tutup penerbitan, dan kalibrasi bersebab. | Ringkasan penerbitan, terbitkan massal/per peserta, tutup penerbitan, `auditSiklus()` |
| A12 | Audit & Riwayat PA | **SELESAI 2026-09-02** — dialog audit di workspace siklus | `auditSiklus()` + fixture `siklus-audit.json`; filter server, timeline, cacah keputusan manusia/sistem, pagination, state aman, dan batas nilai lama konfigurasi |
| A13 | Master Data PA | SELESAI | Delapan workspace master, termasuk Paket Soal dan Butir Soal |

**Satu pekerjaan berikut masih terbuka:**

1. **Nilai LAMA konfigurasi.** §2.24 melaporkan bahwa perubahan terjadi dan siapa
   pelakunya, tetapi tidak dari apa menjadi apa. Menyimpannya butuh DDL baru, dan itu
   keputusan tersendiri: riwayat nilai berarti setiap suntingan master menulis baris
   tambahan selamanya.

`A02` sampai `A08` sudah selesai secara fungsi dalam satu workspace. Pemecahan menjadi
halaman terpisah adalah pekerjaan UX berikutnya dan tetap memakai seam yang sama; jangan
membuat bentuk data baru hanya untuk memisahkan halaman.

### 8.3 Isi Master Data yang sudah ditutup

`A13` terdiri dari delapan halaman/workspace dengan sembilan slug kontrak:

1. Jenis Kompetensi - `jenis-kompetensi`
2. Kompetensi - `kompetensi`
3. Kategori Penilai - `kategori-penilai`
4. Aturan Penilai - `aturan-penilai`
5. Bobot Pilar - `bobot-pilar`
6. Pita Nilai - `pita-nilai`
7. Kuadran HAV - `kuadran-hav`
8. Paket Soal + Butir Soal - `paket-soal` dan `butir-soal`

Paket Soal dan Butir Soal tetap wajib dibuka dalam konteks siklus/paket. Keduanya tidak
boleh diperlakukan sebagai katalog global tanpa `cycle_id` atau `package_id`.

### 8.4 Urutan eksekusi frontend

| Fase | Fokus | Status awal |
|---|---|---|
| FE-PA-01 | Finalisasi A03 Peserta & Kesiapan di halaman siklus | **SELESAI** 2026-09-01 — tidak ada Page/route baru |
| FE-PA-02 | Finalisasi A04 Penugasan Penilai di halaman siklus | **SELESAI** 2026-09-01 — tidak ada Page/route baru |
| FE-PA-03 | Finalisasi A05 Perhitungan dan A06 Review Hasil di halaman siklus | **SELESAI** 2026-09-01 — tidak ada Page/route baru |
| FE-PA-04 | Pisahkan A07 Kalibrasi dan A08 HAV Map & Distribusi | **SELESAI** 2026-09-01 — tetap sebagai dialog/workspace dalam `PerformanceAppraisalPage`, tanpa Page/route baru |
| FE-PA-05 | A09 Setup Siklus setelah kontrak BE tersedia | **SELESAI 2026-09-02** |
| FE-PA-06 | A10 Pemetaan Soal, A11 Review/Publikasi, A12 Audit | **SELESAI 2026-09-02** — publikasi A11 §2.13 dan audit A12 §2.24 selesai; A10 tersedia; approval generik dicabut oleh keputusan pemilik proyek |
| FE-PA-07 | U01-U04 halaman karyawan/penilai | SELESAI 2026-09-01 |
| FE-PA-08 | L01-L02 halaman leader | **SELESAI 2026-09-02** |

Setiap fase yang selesai harus menambahkan catatan tanggal di bagian 7, mengubah status
barisnya di bagian ini, dan mencatat file frontend serta test yang dijalankan. Tidak ada
perubahan pada `api/`, `components/ui/`, backend, route, atau database dari jalur FE.
Untuk fase halaman baru, mapping `Jenis_page`/URL/access dari registry wajib tersedia
sebelum file Page dibuat; status endpoint siap tidak menggantikan prasyarat tersebut.

### 8.6 Checkpoint FE-PA-01

| Item | Keputusan checkpoint |
|---|---|
| Module | KPI |
| Submodule | Performance Appraisal / Cycle |
| Feature | Peserta & Kesiapan |
| Layer yang akan dibuat setelah mapping tersedia | Named Inertia Page, composable untuk orkestrasi lifecycle, komponen presentasional lokal, dan test PA |
| State minimum | loading, success, empty, error, unauthorized, validation/422, saving, stale/retry |
| Basis data FE | `paApi.daftarPeserta(cycleId, saringan)` dan `paApi.keranjangTertunda(cycleId)`; tidak membuat endpoint atau shape baru |
| Dependency yang masih terbuka | Tidak ada untuk A03; mapping aktif adalah `PerformanceAppraisalPage` pada `/performance-appraisal` dengan akses eksplisit |
| Dampak test | focused component/page test; contract test seam tetap milik BE; browser smoke route/responsive setelah URL aktif |

### 8.5 Permintaan kontrak BE untuk roadmap halaman

Daftar ini adalah kebutuhan kontrak, bukan endpoint yang boleh ditebak frontend:

| Area | Yang perlu dikontrakkan |
|---|---|
| Setup Siklus | Detail, buat, ubah, validasi dua rentang tanggal, konfigurasi, dan pemilihan paket |
| Dashboard Admin | Agregat kesiapan, progres penilai, tertunda, hasil, dan cakupan akses |
| Penerbitan Hasil | Daftar kandidat terbit, aksi terbit, batal terbit bila ada, dan riwayat |
| Pemetaan Soal | Daftar, detail, simpan, ubah, versi, dan status kode soal terhadap kompetensi |
| User/Karyawan | Siklus aktif, tugas sendiri, hasil terbit sendiri, dan riwayat |
| Leader | Anggota dalam scope, ringkasan tim, detail hasil yang boleh terlihat |
| Audit | Riwayat perubahan konfigurasi, penugasan, kalibrasi, dan penerbitan |

Kontrak baru harus ditulis lebih dulu di `docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md`
dan dicatat di antrean bagian 6 sebelum halaman terkait mulai dibuat.
