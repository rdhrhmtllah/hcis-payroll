# Masa Berlaku & Jadwal Review Versi SOP — desain

Tanggal: 2026-08-27
Status: **SLICE A SELESAI — DDL & DML dieksekusi ke development 2026-08-27**

> Uji terfokus hijau (PHPUnit + Vitest), gerbang struktural bersih, build lulus.
> SQL Tracker dan produksi masih `PENDING` — lihat kedua README di
> `docs/changes/`. Slice B/C/D pada §7 belum dikerjakan.

Halaman: `/sop/admin/dokumen/{dokumen}/versi/{versi?}` (langkah 4 — Pemberkasan)

Pendahulu yang wajib dibaca lebih dulu:

- `plans/sop/2026-08-26-wizard-versi-dokumen-design.md` — wizard empat langkah
- `docs/changes/DML-2026-042-sederhanakan-alur-versi/README.md` — keputusan
  "review boleh kosong" yang dokumen ini **kuatkan**, bukan batalkan
- `docs/architecture/feature-mapping-lms-sop-master-document.md` §"`Tanggal_Kadaluarsa`
  berhenti diminta" — keputusan 2026-08-15 yang dokumen ini **balikkan sebagian**,
  dengan sebab yang dicatat di §2.3

---

## 1. Masalah

Tiga keluhan yang dilaporkan 2026-08-26, semuanya dibuktikan terhadap `Web_HRIS`
development pada 2026-08-27.

### 1.1 Pemberkasan hanya meminta satu tanggal

Langkah Pemberkasan hanya menanyakan `Tanggal_Berlaku`. Sebuah versi bisa terbit
tanpa pernah menyatakan sampai kapan ia berlaku dan kapan ia ditinjau ulang.
Untuk dokumen yang mengikat orang, itu metadata yang kurang — dan tidak ada
satu pun mekanisme yang bisa mengingatkan siapa pun, karena tidak ada tanggal
untuk diingatkan.

`Tanggal_Kadaluarsa` **sudah ada** sebagai kolom di `N_SOP_Item_Versi` tetapi
tidak punya form, tidak punya penulis, dan tidak punya pembaca yang bertindak
atasnya. Kolom mati.

### 1.2 Menyimpan catatan review butuh dua klik

`LangkahReview.vue` memegang barisnya sendiri dan menyimpannya lewat tombol
sendiri (`Simpan Catatan Review`). Cangkang wizard menyimpan `form` versi lewat
jalur lain. Akibatnya admin yang menambah catatan review lalu menekan
**Simpan & Lanjut ke Pengesahan** kehilangan catatannya: aksi utama tidak pernah
menyentuh baris review.

Gejala yang dilaporkan: *"itu membuat user ilfeel secara experience."*

Lebih buruk lagi dan belum dilaporkan: berpindah langkah lewat stepper meng-unmount
`LangkahReview`, dan ketikan yang belum disimpan hilang **tanpa peringatan** —
lencana "N perubahan belum disimpan" hanya menghitung `form`, tidak menghitung
review.

### 1.3 Review kosong terbaca seperti pekerjaan tertinggal

`SopTahapVersiService::langkahVersi()` melaporkan:

```php
'REVIEW' => $review.' putaran review',   // Ringkasan
'REVIEW' => $review > 0,                 // Lengkap
```

Versi yang melewati Review tanpa catatan — perpanjangan, atau review yang
berjalan mulus — selamanya berbunyi **"0 putaran review"** dengan `Lengkap =
false` di stepper dan di rail ringkasan. Padahal `DML-2026-042` sudah mencabut
syarat `CATATAN_REVIEW` justru karena review kosong itu sah.

Jadi gerbangnya sudah benar; yang salah tinggal kalimatnya. Dan kalimat itulah
yang dibaca admin sebagai "tahap review belum selesai".

---

## 2. Keputusan

### 2.1 Kadaluarsa TIDAK meng-obsolete apa pun

Permintaan awal: *"otomatis obsolete, tapi selagi belum ada versi terbaru user
tetap bisa baca versi yang sudah kadaluarsa."*

Kedua hal itu tidak bisa lewat `OBSOLETE`. Lensa karyawan menolak versi
`OBSOLETE` — `SopSecurePdfController::tokenAdmin()` menyebutnya eksplisit
sebagai "sudah dicabut" dan hanya pintu admin yang boleh membukanya. Job harian
yang meng-`OBSOLETE`-kan versi kadaluarsa tidak memberi karyawan peringatan; ia
memberi mereka 403.

Lagipula auto-obsolete **sudah ada dan sudah benar**: `SopItemVersionService`
mencabut versi lama begitu penggantinya terbit.

Karena itu:

> `OBSOLETE` tetap milik satu sebab saja — ada pengganti yang terbit.
> Kadaluarsa adalah keadaan **keberlakuan**, bukan tahap: `KEDALUWARSA`.
> Dokumennya tetap terbaca, ditandai, dan admin dinotifikasi.

Dengan begitu SOP tidak pernah mati sendiri di tengah operasional, tapi tidak
ada yang bisa berpura-pura tidak tahu.

### 2.2 Aturan masa berlaku adalah DATA, bukan konstanta

"Default 2 tahun" disimpan sebagai `Masa_Berlaku_Bulan` di
`N_SOP_Master_Jenis_Dokumen`, bukan konstanta di kode.

Modul ini konsisten menaruh aturan sebagai data — `Cap_Tanggal`, `Status_Teknis`,
`Butuh_Berkas`, seluruh tabel transisi dan syarat. Konstanta 24 di dalam service
akan jadi satu-satunya aturan SOP yang butuh rilis untuk diubah, padahal
biaya DDL-nya sama saja. Kebijakan bisa 3 tahun dan Instruksi Kerja 1 tahun tanpa
menyentuh kode.

### 2.3 Kenapa keputusan 2026-08-15 dibalik

Masa berlaku dicabut 2026-08-15 dengan alasan yang tercatat: *"tak ada satu pun
aturan SOP yang bertindak atasnya."* Alasan itu benar pada saat itu, dan
mencabut kolom mati adalah keputusan yang tepat.

Yang berubah sekarang bukan pendapat, melainkan fakta: slice B dan C dari
rancangan ini **memberi** kolom itu aturan yang bertindak atasnya — status
`KEDALUWARSA`, spanduk pembaca, dan notifikasi hitung mundur. Menghidupkan
kolomnya tanpa slice B/C akan mengulang persis kondisi 2026-08-15, dan itulah
sebabnya §7 menuliskan B/C/D sebagai rencana yang mengikat, bukan gagasan.

`SopDokumenKeberlakuanTest` sengaja **tidak** disentuh di slice A. Tesnya
menegaskan tanggal kadaluarsa tidak menggugurkan apa pun; di slice A itu masih
benar. Tes itulah yang harus merah lebih dulu di slice B — persis seperti
komentar di dalamnya berharap.

### 2.4 Tombol maju tak pernah lagi turun jadi "Simpan"

Ditambahkan 2026-08-27 setelah pengguna menguji langkah 1 dan 3.

Dua mekanisme berbeda menghasilkan keluhan yang sama — sebuah **jembatan klik**
yang tak diminta siapa pun.

**Langkah 1.** Endpoint template `GET /langkah` hanya mengirim rangka langkah,
tanpa aksi. Layar terpaksa mengarang, dan ia memaku
`{ Jenis: 'SIMPAN', Label: 'Simpan Draft Versi' }` untuk versi tanpa id. Selain
melahirkan klik ekstra, kalimat yang dirakit layar adalah sumber kebenaran kedua
— persis yang seluruh rancangan wizard ini ada untuk menghindarinya.

> Endpoint template kini mengirim **bentuk yang sama** dengan endpoint alur
> versi (`{Langkah, Langkah_Aktif, Aksi_Utama, Aksi_Lain, Tujuan}`) lewat
> `SopTahapVersiService::alurTemplate()`. Aksinya dihitung dari transisi maju
> `DRAFTING`, sehingga tombolnya berbunyi **"Simpan & Lanjut ke Review"** dan
> namanya ikut kalau tahap itu dinamai ulang di master.

**Langkah 3 dan 4.** `aksiUtama()` menurunkan aksinya jadi `SIMPAN` selama masih
ada syarat yang belum terpenuhi. Alasannya tertulis: *"menawarkan tombol lanjut
yang pasti ditolak berarti penolakan datang sesudah admin menekannya."*

Alasan itu benar ketika tombolnya belum ikut menyimpan. Ia **sudah tidak benar**:
`SIMPAN_LANJUT` menyimpan dulu lalu memindahkan, jadi syaratnya diperiksa
terhadap data **sesudah** simpan. Seluruh syarat yang ada — berkas, pengesah,
tiga tanggal — dipasok dari formulir yang sedang dibuka, sehingga justru klik
itulah yang memenuhinya.

Yang lama menciptakan urutan yang persis dikeluhkan: admin mengetik pengesah →
tombol masih "Simpan" karena server menilai data **tersimpan** → ia menekan
Simpan → barulah "Simpan & Lanjut ke Pemberkasan" muncul → ia menekannya lagi.
Tombol yang menggambarkan masa lalu, sementara kliknya bertindak atas masa kini.

> `Syarat_Kurang` tetap dilaporkan dan tetap ditampilkan sebagai keterangan di
> samping tombol — **kabar, bukan penghalang**. Penegakannya tak pernah menjadi
> tugas tombol: `pindah()` dan `terbitkanVersi()` tetap menolak dengan pesan
> yang menyebut apa yang kurang.

Tombolnya hijau (`success`) hanya ketika tak ada lagi yang kurang; selama masih
ada, ia `primary`. Menghijaukannya sejak awal berarti menjanjikan sesuatu yang
belum tentu terjadi.

`Simpan Saja` tetap ada sebagai tombol sekunder di setiap langkah — jalan keluar
bagi admin yang isiannya belum lengkap dan ingin melanjutkan besok tanpa
versinya terlanjur berpindah tahap. Sama polanya dengan `Simpan Sementara` di
langkah Review.

### 2.5 Review kosong = sah, dan kalimatnya harus mengatakan itu

Gerbangnya sudah dicabut `DML-2026-042`. Yang diperbaiki hanya pelaporannya:
langkah Review yang **sudah dilewati** adalah langkah selesai, apa pun jumlah
catatannya.

### 2.6 Tinjauan Akhir berdiri sebagai langkah kelima

Ditambahkan 2026-08-27 (`DML-2026-051`).

Penerbitan menggeser pointer dokumen, meng-obsolete versi lama, merakit ulang
nomor resmi, dan merekonsiliasi paket perujuk — semuanya tak bisa ditarik.
Sampai perubahan ini, satu-satunya cara melihat kembali apa yang sudah diisi
sebelum menerbitkan adalah menyusuri ulang empat langkah satu per satu, dan tak
seorang pun melakukannya.

`FILING_REGISTERED` dipindah dari langkah 4 ke **langkah 5** dengan
`Kelompok = TINJAUAN`; langkah 4 tinggal `APPROVED` (Pemberkasan). Langkah
kelima itu **lahir sebagai data**, sama seperti empat pendahulunya — konstanta di
layar akan menjadikannya satu-satunya langkah yang butuh rilis untuk diubah.

Namanya **Tinjauan Akhir**, bukan Review: langkah 2 sudah memakai nama itu untuk
catatan review, dan dua kotak di rel yang sama berbunyi identik adalah cara
termurah membuat admin salah membuka langkah.

**Aksi antar-langkah.** Server mengirim `Aksi_Antar` di samping `Aksi_Utama`:

| Langkah dibuka | Tombol | Yang terjadi |
|---|---|---|
| 4 · Pemberkasan | Simpan & Lanjut ke Tinjauan Akhir | simpan, lalu **layar** pindah ke langkah 5 — tahap tak bergerak |
| 5 · Tinjauan Akhir | Terbitkan | pratinjau dampak, lalu terbit |

Layar memilih di antara dua aksi buatan server; ia tidak merakit satu pun
kalimatnya. Memilih memang miliknya — hanya layar yang tahu langkah mana yang
terbuka.

**Pelonggaran kunci yang sempit.** Langkah tujuan penerbitan boleh dibuka
sebelum ditempati; ia memang ada untuk dilihat sebelum diterbitkan, dan
menguncinya sampai versinya terbit berarti rekap yang hanya bisa dibaca sesudah
terlambat. Pengecualiannya hanya berlaku ketika aksi utamanya `TERBITKAN`, jadi
langkah maju yang lain tetap terkunci seperti semula.

**Modal dampak tetap.** `SopTerbitPratinjau` menjawab pertanyaan yang berbeda —
paket mana naik versi, berapa karyawan terdampak, dan revisi ini mayor atau minor
— dan itu pantas dapat konfirmasi tepat sebelum tindakan yang tak bisa ditarik.
Ia juga dipakai bersama panel Master Dokumen; meleburnya ke langkah 5 akan
menghidupkan logika dampak di dua tempat.

**Rekapnya baca-saja tanpa kecuali.** Tiap blok punya tombol "Sunting" yang
melompat balik ke langkah pemiliknya, bukan field kedua yang bisa diubah di
tempat. Field yang sama di dua tempat adalah dua sumber kebenaran, dan yang satu
pasti tertinggal. Field kosong **disebut**, tidak disembunyikan — rekap yang
menyembunyikan yang kosong meyakinkan admin bahwa semuanya beres, tepat sebelum
tindakan yang tak bisa ditarik.

---

## 3. Bentuk data

### 3.1 `DDL-2026-049-sop-masa-berlaku-versi`

| Tabel | Kolom | Tipe | Null | Ket |
|---|---|---|---|---|
| `N_SOP_Item_Versi` | `Tanggal_Review_Berikutnya` | `date` | YES | baru |
| `N_SOP_Master_Jenis_Dokumen` | `Masa_Berlaku_Bulan` | `int` | YES | baru |
| `N_SOP_Master_Jenis_Dokumen` | `Siklus_Review_Bulan` | `int` | YES | baru |
| `N_SOP_Item_Versi` | `Tanggal_Kadaluarsa` | `date` | YES | **sudah ada**, tidak disentuh |

Semua `NULL`: versi lama tidak punya nilainya, dan memaksa `NOT NULL` berarti
memilihkan tanggal untuk dokumen yang tak seorang pun pernah putuskan. Kewajiban
ditegakkan di gerbang transisi (§3.2), bukan di kolom.

Tidak ada kolom audit baru — ketiganya menempel pada baris yang audit-nya sudah
ada.

**Kenapa `Tanggal_Review_Berikutnya`, bukan `Tanggal_Review`.** Nama
`Tanggal_Review` sudah dipakai di `N_SOP_Item_Versi_Review` untuk tanggal
putaran review yang **sudah terjadi**. Dua kolom bernama sama dengan arah waktu
berlawanan adalah jebakan yang cepat atau lambat ditabrak oleh kueri yang
menggabungkan keduanya.

### 3.2 `DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal`

Dua bagian.

**(a) Seed baku.** `Masa_Berlaku_Bulan = 24`, `Siklus_Review_Bulan = 12` untuk
seluruh jenis dokumen aktif (7 baris pada development per 2026-08-27). Hanya
mengisi yang masih `NULL`, sehingga menjalankannya dua kali tidak menimpa
penyetelan yang sudah diubah admin.

**(b) Tiga baris syarat** di `N_SOP_Master_Tahap_Versi_Syarat` untuk tahap
`FILING_REGISTERED`:

| Kode_Syarat | Nama_Syarat | Urutan |
|---|---|---|
| `TANGGAL_BERLAKU` | Tanggal mulai berlaku | 3 |
| `TANGGAL_KADALUARSA` | Tanggal kadaluarsa | 4 |
| `TANGGAL_REVIEW_BERIKUTNYA` | Jadwal review berikutnya | 5 |

Urutan 1–2 sudah ditempati `BERKAS` dan `REALISASI_PENGESAH`.

Gerbangnya di `FILING_REGISTERED` — yaitu saat **meninggalkan** Pemberkasan
menuju terbit — bukan di simpan. Draft boleh setengah jadi; yang tidak boleh
setengah jadi adalah dokumen yang mulai mengikat orang.

`TANGGAL_BERLAKU` ikut dijaga meski selama ini hanya bertanda wajib di form dan
tidak pernah menjadi gerbang. Dua tanggal baru dihitung darinya; membiarkan
akarnya tidak terjaga sementara turunannya wajib adalah aturan yang tidak
konsisten dengan dirinya sendiri.

### 3.3 Catatan data development

`N_SOP_Item_Versi` berisi **1 baris** pada 2026-08-27 (`DML-2026-047`
mengosongkan rantai dokumen SOP). Tidak ada kekhawatiran backfill: nol baris
memiliki `Tanggal_Kadaluarsa` terisi. Angka "6 dari 76 versi" di
`feature-mapping-lms-sop-master-document.md` sudah basi sejak reset itu.

---

## 4. Resolver default

`app/Services/LMS/SOP/SopMasaBerlakuService.php` — berkas baru.

> **Penempatan.** Ditaruh bersama 40-an service SOP lain di `app/Services/LMS/SOP/`,
> **bukan** di pohon `DMS` baru, meski registry database sudah memetakan seluruh
> halaman SOP ke grup `DMS` (id 7) sejak `DML-2026-044`. Alasannya: DML-044
> memindahkan **menu**, bukan berkas — tidak ada satu file pun ikut pindah.
> Satu service sendirian di pohon DMS sementara saudaranya di LMS memberi modul
> ini dua rumah sekaligus. Migrasi `LMS/SOP` → `DMS/SOP` layak jadi task
> tersendiri yang direview. Diputuskan bersama pengguna, 2026-08-27.

Tanggung jawabnya satu: dari sebuah dokumen, menghasilkan dua angka bulan dan
dua tanggal turunan.

```php
/** @return array{Masa_Berlaku_Bulan:int, Siklus_Review_Bulan:int} */
public function aturanDokumen(int $idDokumen): array

/** @return array{Tanggal_Kadaluarsa:?string, Tanggal_Review_Berikutnya:?string} */
public function turunanDari(int $idDokumen, ?string $tanggalBerlaku): array
```

`Jenis_Dokumen_Id` pada `N_SOP_Dokumen` boleh `NULL`, dan jenis yang ada boleh
belum disetel. Karena itu ada fallback berlapis: nilai jenis → `24`/`12`. Tanpa
fallback, dokumen tanpa jenis akan lolos dari seluruh mekanisme peringatan
diam-diam — persis kegagalan yang rancangan ini ada untuk menutupnya.

`turunanDari()` mengembalikan dua `null` bila `$tanggalBerlaku` kosong. Menebak
tanggal kadaluarsa dari hari ini ketika tanggal berlakunya belum diputuskan
berarti mengarang fakta.

---

## 5. Perilaku

### 5.1 Layar Pemberkasan

Kartu **"Mulai Berlaku"** menjadi **"Masa Berlaku & Jadwal Review"** dengan tiga
picker: Tanggal Mulai Berlaku, Tanggal Kadaluarsa, Jadwal Review Berikutnya.

**Auto-isi yang tahu diri.** Dua tanggal turunan terisi otomatis begitu Tanggal
Berlaku diisi atau diubah — **selama admin belum menyentuhnya sendiri.** Sekali
admin mengetik tanggal kadaluarsanya sendiri, tanggal itu berhenti mengikuti,
dan sebuah tautan kecil *"ikuti baku lagi"* mengembalikannya. Auto-isi yang
menimpa ketikan orang adalah persis jenis perilaku yang keluhan ini soal.

Jejak "sudah disentuh" hidup di komponen, bukan di `form`: ia bukan fakta
tentang versi, ia fakta tentang sesi penyuntingan. Menyimpannya ke server
berarti membuat kolom untuk mengingat apa yang dilakukan tangan admin
tiga bulan lalu.

**Angkanya datang dari server sekali.** `Masa_Berlaku_Bulan` dan
`Siklus_Review_Bulan` ikut dalam prop `dokumen` yang sudah dibawa halaman
Inertia — bukan endpoint baru per perubahan tanggal. Layar menghitung
`berlaku + N bulan`; itu aritmetika atas tanggal yang **diketik admin**, tidak
menyentuh jam browser sama sekali, jadi keberatan "jam klien bisa salah" yang
berlaku untuk `keberlakuanItem()` tidak berlaku di sini.

Server tetap menghitung default yang sama saat menyimpan bila kolomnya kosong,
sehingga jalur API di luar wizard tidak melahirkan versi tanpa masa berlaku.

**Urutan divalidasi di dua tempat:** `berlaku < review berikutnya ≤ kadaluarsa`,
di layar untuk kecepatan kabar dan di server untuk penegakan.

### 5.2 Catatan review pindah kepemilikan

`LangkahReview` berhenti memegang barisnya sendiri. Barisnya hidup di cangkang
(`SopVersiWizard.review`), komponennya menjadi terkendali lewat `v-model:baris`.

Yang **tidak** berubah: muatannya tetap dikirim ke endpoint review terpisah,
tidak digabung ke `PUT /versi/{id}`. Alasan aslinya masih benar — lampiran
gambar tidak boleh ikut terangkut di setiap penyimpanan kecil. Yang pindah hanya
tempat tinggal state-nya, bukan bentuk muatannya.

Dua akibat langsung:

1. Berpindah langkah lalu kembali tidak lagi membuang ketikan review.
2. `simpan()` bisa menyertakan review tanpa komponennya harus sedang terpasang.

Logika baca berkas, batas 2 MB, dan batas 10 lampiran tetap di komponen —
itu urusan tampilan, bukan urusan cangkang.

### 5.3 Satu klik, dua kiriman

`simpan()` menjadi:

1. `PUT /versi/{id}` — muatan `form`.
2. `PUT /versi/{id}/review` — **hanya bila** baris review berbeda dari yang
   termuat terakhir.

Urutan pada `SIMPAN_LANJUT` tetap: simpan (versi + review) → baru pindah tahap.
Simpan gagal berarti tahap tidak pindah — syarat transisi harus diperiksa
terhadap data yang sudah tersimpan.

Tombol `Simpan Catatan Review` turun pangkat menjadi checkpoint: label
**"Simpan Sementara"**, varian `ghost`, dan hanya muncul saat ada perubahan
review yang belum tersimpan. Ia untuk admin yang ingin mengunci progres lalu
melanjutkan nanti — bukan syarat untuk maju.

`kotor` / `berubah` ikut menghitung review, sehingga lencana "N perubahan belum
disimpan" dan penjaga "tinggalkan halaman?" akhirnya jujur untuk review juga.

### 5.4 Pelaporan langkah Review

Di `SopTahapVersiService::langkahVersi()`:

```php
'REVIEW' => $review > 0 || $langkahKini > $langkah['Nomor'],   // Lengkap
```

```php
// Ringkasan
$review > 0            => "{$review} putaran review"
$langkahKini > $nomor  => 'tanpa catatan review'
default                => 'belum ada catatan'
```

*"0 putaran review"* adalah kalimat yang membuat admin mengira ada yang
tertinggal. *"Tanpa catatan review"* menyatakan fakta yang sama sebagai keadaan
yang sah — dan itulah kasus perpanjangan.

`Keadaan` sudah posisional sejak `a7e8943`; tidak disentuh.

---

## 6. Pengujian

| Berkas | Yang dibuktikan |
|---|---|
| `tests/Unit/LMS/SOP/SopMasaBerlakuTest.php` | resolver per jenis; fallback tanpa jenis; fallback jenis tanpa setelan; `null` saat tanggal berlaku kosong |
| `tests/Feature/LMS/SOP/SopWizardVersiTest.php` | maju ke `FILING_REGISTERED` ditolak tanpa ketiga tanggal, diterima dengan; urutan tanggal terbalik ditolak |
| `tests/Feature/LMS/SOP/SopTahapVersiTest.php` | Review terlewati tanpa catatan → `Lengkap` true, Ringkasan `'tanpa catatan review'` |
| `resources/js/.../SopVersiWizard.test.js` | satu klik Simpan & Lanjut memanggil PUT versi lalu PUT review; **tidak** memanggil PUT review bila tak ada perubahan; review bertahan melewati pergantian langkah |
| `resources/js/.../LangkahPemberkasan.test.js` | auto-isi mengikuti tanggal berlaku; berhenti mengikuti setelah disentuh; "ikuti baku lagi" mengembalikannya |

`SopDokumenKeberlakuanTest` **tidak disentuh** — lihat §2.3.

---

## 7. Slice B, C, D — rencana yang tinggal dieksekusi

Ketiganya membaca kolom yang lahir di slice A. Urutannya terkunci oleh data,
bukan oleh selera.

### 7.1 Slice B — `KEDALUWARSA` & `PERLU_TINJAU` hidup lagi

**Sentuhan utama:** `SopProgressReadService::keberlakuanItem()`.

Dua cabang dikembalikan **sebelum** cabang `BERLAKU`, sesudah `VERSI_LAMA` dan
`BELUM_BERLAKU` (urutan itu penting — versi yang sudah digantikan tidak perlu
diberi tahu bahwa ia kadaluarsa):

```
Tanggal_Kadaluarsa < hari ini            → KEDALUWARSA
Tanggal_Review_Berikutnya < hari ini     → PERLU_TINJAU
```

Keduanya **tidak** menutup akses. `SopSecurePdfController` dan
`SopProgressReadService` tidak disentuh — status ini hanya menandai.

**Layar karyawan:** spanduk di `SopDetail` / `SopKontenViewer` yang menyebut
akibatnya, bukan sekadar labelnya — *"Versi ini melewati masa berlakunya pada
12 Mar 2027. Isinya masih mengikat sampai versi penggantinya terbit, dan Anda
akan diminta membacanya ulang."*

`SopDokumenKeberlakuanTest::test_tanggal_lama_tak_lagi_menggugurkan_keberlakuan`
akan merah, dan itu **hasil yang benar** — tes itu ditulis sebagai penjaga agar
pembalikan ini tidak terjadi diam-diam. Ia diganti, bukan dihapus, dengan tes
yang menegaskan aturan barunya.

`docs/design-system.md:273` sudah punya varian `EXPIRED`; `PERLU_TINJAU` butuh
varian `warning` baru.

### 7.2 Slice C — hitung mundur & notifikasi admin

**Sentuhan utama:** `SopNotificationService` (sudah ada), plus satu command
terjadwal.

Ambang: **90 / 60 / 30 hari** sebelum `Tanggal_Kadaluarsa`, dan pada
`Tanggal_Review_Berikutnya`. Satu notifikasi per ambang per versi — butuh tabel
jejak (`N_SOP_Versi_Peringatan`, kolom `Id_SOP_Item_Versi`, `Kode_Ambang`,
`Tanggal_Kirim`) supaya job harian tidak mengirim ulang notifikasi yang sama
setiap pagi. Tanpa jejak itu, admin menerima peringatan yang sama 90 kali.

Penerima: pemilik dokumen (`Entitas_Pemilik_Id` / `Departemen_Id`), bukan semua
admin — notifikasi yang mengenai semua orang berhenti dibaca siapa pun.

Command didaftarkan lewat pola penjadwalan yang sudah dipakai modul ini; lihat
`docs/architecture/transaction-and-cloud-tasks.md` sebelum menulisnya.

### 7.3 Slice D — penanda di layar admin

**Master Dokumen** (`SopMasterDokumen.vue`): kolom/badge keberlakuan pada baris
dokumen, dan penyaring "akan kadaluarsa ≤ 90 hari" / "perlu ditinjau". Angkanya
dari `keberlakuanItem()` slice B, dihitung server — layar tidak menghitung
tanggal sendiri.

**Builder** (`SopBuilderPage`): peringatan saat sebuah paket merakit item yang
versinya sudah `KEDALUWARSA`, sebelum paket itu diterbitkan. Di sinilah
peringatan paling berguna — mencegah dokumen kadaluarsa ikut tersebar, bukan
melaporkannya sesudah tersebar.

### 7.4 Yang sengaja TIDAK direncanakan

- **Auto-obsolete terjadwal.** Ditolak di §2.1.
- **Perpanjangan sebagai jenis versi tersendiri.** Perpanjangan sudah cukup
  terwakili: versi baru, tanggal baru, tanpa catatan review. Menambah penanda
  jenis berarti menambah cabang di setiap pembaca versi demi satu perbedaan yang
  sudah terbaca dari datanya.
- **Masa berlaku per dokumen** (menimpa jenisnya). Belum ada kebutuhan yang
  menyebutkannya; menambahkannya sekarang berarti menebak.

---

## 8. Ringkasan berkas

**Baru**

```
database/ddl/DDL-2026-049-sop-masa-berlaku-versi/{forward,drop}.sql
database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/data-change.sql
docs/changes/DDL-2026-049-sop-masa-berlaku-versi/README.md
docs/changes/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/README.md
app/Services/LMS/SOP/SopMasaBerlakuService.php
tests/Unit/LMS/SOP/SopMasaBerlakuTest.php
resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.test.js
```

**Disunting**

```
app/Services/LMS/SOP/SopTahapVersiService.php        §3.2, §5.4
app/Services/LMS/SOP/SopItemVersionService.php       §5.1 (default sisi server)
app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php   validasi
app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php  prop dokumen
resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue          §5.2, §5.3
resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.vue §5.1
resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahReview.vue      §5.2
resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js       §6
tests/Feature/LMS/SOP/SopWizardVersiTest.php                             §6
tests/Feature/LMS/SOP/SopTahapVersiTest.php                              §6
docs/architecture/feature-mapping-lms-sop-master-document.md             §2.3
```
