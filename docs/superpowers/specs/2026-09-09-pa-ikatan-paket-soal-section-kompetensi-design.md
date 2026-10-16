# Ikatan Paket Soal PA: Bagian → Kompetensi

**Tanggal:** 9 September 2026 (r2 — 9 September 2026, setelah kontrak HC Learn dibaca dan ditanyakan)
**Halaman:** `/performance-appraisal/master/ikatan-paket-soal`
**Status:** rancangan disetujui, DDL belum ditulis

## 1. Kenapa ini berubah

Selama ini HC Learn melempar **kode soal** beserta kompetensinya. Katalog yang
kita terima memuat `Detail[].Kode_Kompetensi`, dan seluruh pemeriksaan cakupan di
`PaQuestionBindingMasterService::periksa()` bersandar pada kolom itu.

HC Learn mengubah kontraknya: yang dilempar sekarang **ujian beserta paket, alat
tes, dan bagian-bagiannya**, dan bagian-bagian itu **tidak berlabel kompetensi**.
Kompetensi tidak lagi datang dari mereka.

Akibatnya pemetaan kompetensi berpindah ke sisi kita: admin yang melabeli tiap
bagian ke kompetensi mana ia mengukur. Itu menambah satu turunan di bawah ikatan
paket soal, mengubah aturan simpan, dan mengubah apa yang diperiksa saat
pemeriksaan kesehatan.

`N_PA_Competency_Kode_Soal` — jembatan kompetensi↔kode soal yang dulu
direncanakan untuk kontrak lama — **tidak pernah dipakai** (dicabut pada
amandemen DDL-2026-067 r2; tabelnya ada di database dengan 0 baris, 0 rujukan
kode, 0 FK masuk). Rancangan ini menggantikan perannya, jadi tabel itu dihapus.

## 2. Bentuk kontrak HC Learn

Dibaca dari dokumentasi mereka pada 9 September 2026. **Satu endpoint, satu
panggilan** — tidak ada panggilan detail terpisah:

```
GET /api/v1/pa/nama-tes?limit=25&page=1        ← parameter WAJIB terurut abjad

result[]                                        ← UJIAN
  .id_master_ujian        int                   "satuan yang DIJADWALKAN kepada peserta"
  .nama, .kode, .jumlah_soal
  .paket                  object                satu ujian TEPAT SATU paket
     .id_master_paket_ujian  int
     .nama, .kode, .total_soal, .jumlah_alat_tes
     .alat_tes[]          array                 BERURUT = urutan pengerjaan, tanpa field nomor
        .id_jenis_indikator  int
        .nama, .tes_khusus, .jumlah_bagian
        .bagian[]         array                 KOSONG pada tes khusus
           .id_jenis_soal    int                "pengenal BANK SOAL"
           .kode_soal, .nama, .keterangan, .jumlah_soal, .durasi_detik
```

Tiga sifat kontrak ini yang membentuk seluruh rancangan di bawah:

- **Yang dijadwalkan adalah ujian, bukan paket.** `/api/v1/pa/hasil/{id_ujian_token}`
  juga berpangkal di sana. Mengikat paket berarti mengikat sesuatu yang tidak bisa
  dijadwalkan — dan itu baru ketahuan saat penjadwalan, bukan saat pengikatan.
- **Bagian tidak punya id per-instance.** Tidak ada `id_bagian`, tidak ada
  `id_komponen`, tidak ada `nomor`. Yang tersedia hanya `id_jenis_soal`, yang
  dokumentasi HC Learn sendiri sebut sebagai pengenal **bank soal**: *"Bank yang
  sama bisa dipakai beberapa alat tes."* HC Learn mengonfirmasi (9 September 2026)
  bahwa hanya itu yang bisa mereka sediakan.
- **Satu paket boleh memuat lebih dari satu alat tes.** Untuk PA 360, HC Learn
  menyatakan akan ada satu atau dua alat tes, dipilih adminnya saat menyusun soal.

Karena itu identitas satu bagian adalah **pasangan
`(id_jenis_indikator, id_jenis_soal)`**, bukan satu field.

## 3. Keputusan yang diambil

Enam keputusan berikut diambil bersama pemilik fitur pada 9 September 2026.
Semuanya menutup pertanyaan yang, bila dibiarkan terbuka, akan menghasilkan dua
tafsir yang berbeda hasilnya.

### 3.1 Satu bagian = tepat satu kompetensi

Label menunjuk **kompetensi satuan** (`N_PA_Competency`: CV01–CV06, K01–K11,
QL01–QL04, BH01–BH03), bukan jenis kompetensi (`N_PA_Competency_Type`:
CORE_VALUE, MANAJERIAL, KUALITATIF, BEHAVIOR).

Pemetaannya **satu-satu**: satu bagian membawa tepat satu kompetensi, dan satu
kompetensi dipegang tepat satu bagian. Bagian dari **seluruh** alat tes dalam
paket itu diratakan menjadi satu daftar — admin melabeli daftar rata itu, tanpa
peduli bagian mana berasal dari alat tes yang mana.

**Konsekuensi:** ujian yang diikat wajib punya jumlah bagian yang sama persis
dengan jumlah kompetensi wajib level itu. Berdasarkan
`N_PA_Competency_Requirement` per 9 September 2026:

| Level | Dimensi wajib | Kompetensi wajib |
|---|---|---|
| NON CLERK/PRAMUKARYA | 1 | 3 |
| STAFF … SENIOR OFFICER | 2 | 17 |
| ASSISTEN MANAGER … DIREKTUR UTAMA | 3 | 21 |

HC Learn menyatakan tidak ada batas atas jumlah bagian per paket, jadi 21 bagian
untuk ASSISTEN MANAGER ke atas memang bisa disusun.

### 3.2 Pasangan yang kembar menolak pengikatan

`(id_jenis_indikator, id_jenis_soal)` unik **hanya selama** satu alat tes tidak
memakai bank soal yang sama dua kali. Dokumentasi HC Learn tidak menjaminnya.

Bila kekembaran itu terjadi, pengikatan **ditolak** dengan pesan yang menyebut
bagian mana yang kembar, dan admin diminta meminta HC Learn memisahkan banknya.

Kita tidak bisa memperbaiki data mereka, tetapi kita bisa menolak membangun di
atas keambiguan. Alternatifnya — menyimpannya diam-diam — berarti dua bagian
berbeda terbaca sebagai bagian yang sama, dan nilainya pulang ke kompetensi yang
salah **tanpa galat apa pun**.

### 3.3 Kelengkapan wajib, tanpa bagian sisa

Simpan ditolak kecuali kelima syarat terpenuhi:

1. tidak ada pasangan `(indikator, jenis soal)` yang kembar dalam ujian itu;
2. cacah bagian **sama persis** dengan cacah kompetensi wajib level;
3. **setiap** bagian punya label;
4. **setiap** kompetensi wajib dipegang tepat satu bagian;
5. tidak ada label di luar daftar kompetensi wajib level itu.

Tidak ada bagian yang boleh dibiarkan tanpa label, dan tidak ada bagian
"tidak dipakai". Sebuah ujian karena itu terikat pada satu kelompok level saja:
ujian 21-bagian tidak bisa melayani STAFF yang butuh 17.

Aturan lama tetap berlaku: sel yang kategori penilainya tidak menilai level itu
(menurut `N_PA_Rater_Policy`) tetap ditolak.

### 3.4 Label melekat pada sel binding, dengan penyalinan antar sel

Detail menggantung di `N_PA_Question_Binding_Master` — satu salinan label per sel
(level × kategori penilai).

Karena kompetensi wajib ditentukan per **level** saja, ujian yang sama di level
yang sama akan selalu menghasilkan pelabelan yang identik di sel mana pun. Untuk
menghindari admin mengetik ulang 21 baris di tiap sel, halaman menawarkan
"salin label dari sel …" untuk sel lain yang memakai pasangan (ujian, level)
yang sama.

Salinannya tetap terpisah: memperbaiki label di sel sumber **tidak** menular ke
sel yang sudah menyalin.

### 3.5 Pemeriksaan hanya di dua gerbang penulisan

Katalog HC Learn dipanggil di `simpan()` dan `salinKeSiklus()` saja — bukan di
tiap kunjungan halaman. Matriks tidak memanggil HC Learn sama sekali.

Hasil pemeriksaan yang **sudah terjadi** di kedua gerbang itu disimpan di baris
ikatannya dan ditampilkan matriks sebagai status terakhir, lengkap dengan cap
waktunya ("diperiksa 3 hari lalu") supaya tidak ada yang mengiranya realtime.
Tersedia tombol "Periksa Sekarang" per sel untuk pemeriksaan manual.

`salinKeSiklus()` **memeriksa ulang segar saat itu juga** dan tidak memercayai
status tersimpan. Status tersimpan hanya untuk ditampilkan; kalau gerbang
penyalinan ikut memercayainya, status yang sudah basi berminggu-minggu akan
meloloskan ujian rusak ke siklus baru — persis kerusakan yang mau dicegah.

### 3.6 Siklus berjalan tidak disentuh

Bila pemeriksaan menemukan masalah, akibatnya hanya satu: **penyalinan ke siklus
baru ditolak**. Siklus yang sedang berjalan tidak ditandai, tidak dihentikan, dan
hasilnya tidak ditahan.

Risiko yang diterima secara sadar: soal dikerjakan di sisi HC Learn (PA hanya
menjadwalkan dan menerima token), jadi bagian yang dinonaktifkan di tengah siklus
benar-benar tidak akan dikerjakan penilai, dan satu kompetensi akan pulang tanpa
nilai — tanpa peringatan apa pun sampai hasilnya terlihat janggal. Snapshot
`N_PA_Cycle_Question_Package_Item` melindungi pembukuan kita, bukan pengiriman
soal mereka.

## 4. Model data

### 4.1 Header — `N_PA_Question_Binding_Master` (sudah ada)

Satu baris per sel matriks. `PA_Rater_Category_Id` NULL berarti baku untuk
seluruh kategori di level itu.

Perubahan:

| Kolom | Perubahan | Alasan |
|---|---|---|
| `Kode_Paket_Sumber` → `Id_Master_Ujian` | ganti nama | isinya kini id **ujian** — satuan yang dijadwalkan. `kode` tidak unik: 23 alat di katalog HC Learn semuanya `kode = "RDT"`, dan `/detail/nama-tes/RDT` mengembalikan padanan pertama dengan HTTP 200, jadi memakai `kode` gagal secara diam-diam |
| `Id_Master_Paket_Ujian` varchar(100) | BARU | paket di dalam ujian. Satu ujian tepat satu paket, jadi nilainya tunggal per sel |
| `Nama_Paket_Sumber` → `Nama_Ujian_Sumber` | ganti nama | yang diikat ujiannya |
| `Nama_Paket_Ujian_Sumber` nvarchar(255) | BARU | nama paketnya, untuk ditampilkan berdampingan |
| `Jumlah_Soal` → `Jumlah_Bagian` | ganti nama | satuannya kini bagian |
| `Sidik_Susunan` → `Sidik_Bagian` | ganti nama + ganti isi | lihat di bawah |
| `Status_Kesehatan` varchar(20) | BARU | `SEHAT` / `BERMASALAH` / `BELUM_DIPERIKSA` |
| `Masalah_Json` nvarchar(max) | BARU | daftar masalah hasil pemeriksaan terakhir |
| `Diverifikasi_At` / `Diverifikasi_By` | dipakai ulang | cap waktu pemeriksaan; jangan menambah kolom kembar |

**`Id_Jenis_Indikator` sengaja TIDAK di header.** Satu paket boleh memuat dua
alat tes, sehingga satu sel menyeberangi keduanya. Menaruhnya di header memaksa
satu sel hanya bisa memuat satu alat tes, dan ujian dua-alat-tes menjadi mustahil
diikat.

**Sidik hanya memotret sisi HC Learn.** Sidik lama meng-hash
`Kode_Soal|Kode_Kompetensi|Jenis_Soal|Skor_Maksimal|Bobot_Penilaian` — mencampur
struktur mereka dengan kompetensi yang dulu juga milik mereka. Setelah kompetensi
jadi milik kita, campuran itu berbahaya: admin yang mengganti labelnya sendiri
akan memicu peringatan, yaitu sistem menuduh HC Learn atas perubahan yang kita
buat sendiri. `Sidik_Bagian` karena itu meng-hash **hanya himpunan pasangan
`(id_jenis_indikator, id_jenis_soal)` terurut**. Label kita tidak perlu di-hash —
baris detailnya sendiri sudah merupakan labelnya, dan membandingkannya langsung
lebih tepat sekaligus lebih mudah dijelaskan.

### 4.2 Detail — `N_PA_Question_Binding_Master_Detail` (BARU)

```
Id_PA_Question_Binding_Master_Detail  int IDENTITY(1,1) PK
PA_Question_Binding_Master_Id         int          NOT NULL  → header
Id_Jenis_Indikator                    varchar(100) NOT NULL   -- alat tes
Id_Jenis_Soal                         varchar(100) NOT NULL   -- bagian (bank soal)
Nama_Alat_Tes                         nvarchar(255) NULL
Nama_Bagian_Sumber                    nvarchar(255) NULL
Kode_Soal_Sumber                      varchar(50)  NULL
Urutan_Alat_Tes                       smallint     NULL       -- posisi dalam alat_tes[]
Urutan_Bagian                         smallint     NULL       -- posisi dalam bagian[]
PA_Competency_Id                      int          NOT NULL  → N_PA_Competency
Flag_Aktif                            char(1)      NOT NULL DEFAULT 'Y'
Created_At / Created_By / Updated_At / Updated_By

UNIQUE (PA_Question_Binding_Master_Id, Id_Jenis_Indikator, Id_Jenis_Soal)
UNIQUE (PA_Question_Binding_Master_Id, PA_Competency_Id)
```

Dua indeks unik itu yang menegakkan pemetaan satu-satu **di basis data**, bukan
hanya di kode. Yang pertama melarang satu bagian punya dua label; yang kedua
melarang satu kompetensi dipegang dua bagian.

`Urutan_Alat_Tes` dan `Urutan_Bagian` untuk **tampilan saja** dan tidak pernah
dipakai sebagai kunci. Kontrak HC Learn sendiri menyatakan urutan array adalah
urutan pengerjaan dan tidak ada field nomor; menyisipkan satu bagian di tengah
menggeser seluruhnya, dan mengunci label pada posisi akan memindahkan label ke
bagian yang salah tanpa gejala apa pun.

### 4.3 Jalur siklus

`N_PA_Cycle_Question_Package_Item` sudah punya `PA_Competency_Id`, jadi bentuknya
praktis sudah benar. Yang berubah:

| Kolom | Perubahan |
|---|---|
| `Kode_Soal` → `Id_Jenis_Soal` | arti berubah: pengenal bank soal |
| `Id_Jenis_Indikator` varchar(100) NOT NULL | BARU — pasangan identitasnya |
| `Nama_Soal_Sumber` → `Nama_Bagian_Sumber` | ikut |
| `PA_Competency_Id` | jadi NOT NULL |
| dua UNIQUE yang sama | per `PA_Cycle_Question_Package_Id` |

`N_PA_Cycle_Question_Package.Kode_Paket_Sumber` ikut berganti nama jadi
`Id_Master_Ujian`, dengan `Id_Master_Paket_Ujian` ditambahkan, sejalan dengan
headernya.

`salinKeSiklus()` menyalin detailnya juga, tetap berbasis himpunan dalam satu
transaksi — jangan kembali ke pola satu kueri per sel yang sudah pernah dibuang.

## 5. Pemeriksaan kesehatan

`periksa()` menghasilkan daftar masalah dengan kode berikut:

| Kode | Pemicu |
|---|---|
| `UJIAN_HILANG` | id ujian tidak ada lagi di katalog HC Learn |
| `BAGIAN_KEMBAR` | ada pasangan `(indikator, jenis soal)` yang muncul dua kali dalam ujian itu |
| `BAGIAN_HILANG` | ada pasangan terikat yang tak lagi ada di ujian |
| `JUMLAH_TAK_SEPADAN` | cacah bagian ≠ cacah kompetensi wajib level |
| `KOMPETENSI_KURANG` | ada kompetensi wajib yang tidak dipegang bagian mana pun |
| `KOMPETENSI_LEBIH` | ada label di luar daftar kompetensi wajib level itu |

`BAGIAN_KEMBAR` diperiksa **paling dulu**: selama pasangannya ambigu, keempat
pemeriksaan di bawahnya tidak bermakna, karena keduanya menunjuk baris yang sama.

`JUMLAH_TAK_SEPADAN` sengaja tidak menuduh salah satu pihak: pemicunya bisa
bagian ditambah/dikurangi di HC Learn, **atau** matriks kompetensi berubah di
sisi kita. Pesannya harus menyebutkan kedua kemungkinan, karena keduanya
menghasilkan gejala yang persis sama.

`Sidik_Bagian` **tidak menghasilkan masalahnya sendiri.** Perannya jalan pintas:
bila sidik yang tersimpan sama dengan sidik daftar bagian yang baru diambil,
himpunan pasangannya pasti tidak berubah, sehingga `BAGIAN_HILANG` dan
`JUMLAH_TAK_SEPADAN` yang bersumber dari sisi HC Learn bisa dilewati tanpa
dibandingkan satu per satu. Bila berbeda, perbandingan penuh dijalankan dan
masalahnya dilaporkan dengan kode di atas.

Kode `BERGESER` dari versi lama **dicabut**. Susunan yang berubah tanpa mengubah
himpunan pasangan tidak merugikan pemetaan kita, dan melaporkannya sebagai
masalah hanya melatih admin mengabaikan peringatan.

## 6. Alur admin

1. Pilih sel (level × kategori penilai).
2. Pilih **ujian** dari katalog HC Learn.
3. Sistem meratakan seluruh `bagian[]` dari seluruh `alat_tes[]` di dalam paket
   ujian itu menjadi satu daftar, dan mengambil daftar kompetensi wajib level itu.
4. **Ada pasangan kembar → tolak di sini juga**, sebutkan bagian mana.
5. **Cacah tidak sama → tolak di sini juga**, sebelum admin melabeli apa pun.
   Percuma melabeli 21 bagian kalau ujiannya berisi 25.
6. Lolos keduanya → tiap bagian mendapat satu tree select (dimensi → kompetensi).
   Kompetensi yang sudah terpakai hilang dari pilihan berikutnya, sehingga
   bijeksinya terjaga sambil diisi, bukan baru divonis saat menyimpan.
7. Tombol simpan mati sampai seluruh bagian berlabel.
8. Sel lain dengan pasangan (ujian, level) yang sama menawarkan
   "salin label dari sel …".

Bagian ditampilkan berkelompok menurut alat tesnya, dengan nama alat tes sebagai
judul kelompok — supaya admin tahu "Bagian 1" yang mana yang sedang dilabeli
ketika satu paket memuat dua alat tes yang sama-sama punya Bagian 1.

## 7. Migrasi

Dua berkas, dan pemisahannya disengaja: **DML-2026-117** mengosongkan datanya,
**DDL-2026-090** mengubah skemanya. Pengosongan itu menyentuh 18 tabel dan
sekitar 32.500 baris — terlalu besar untuk diselundupkan ke dalam sebuah DDL,
dan memang bukan perubahan skema.

DDL-nya memasang penjaga `THROW 51090` yang menghentikan seluruh transaksi bila
prasyaratnya belum dijalankan, daripada gagal belakangan di tengah pembuatan
indeks dengan pesan yang tidak menyebut sebab sesungguhnya.

### 7.1 Kenapa siklusnya ikut dikosongkan

Bukan pilihan pertama. Tiga hal memaksanya, ditemukan saat dijalankan di
development pada 9 September 2026:

1. **Data lama tidak bisa memenuhi invarian barunya.** 160 grup (360 baris)
   punya `PA_Competency_Id` yang rangkap dalam satu paket, sehingga
   `UX_PA_CycleQItem_Kompetensi` ditolak. Itu wajar pada kontrak lama — satu
   kompetensi memang diukur beberapa soal — dan justru itulah yang berubah.
2. **Menghapus paketnya saja ditolak FK.** Seluruh 7.308 baris
   `N_PA_Assignment_Integration` menunjuk salah satu paket siklus.
3. **Mengosongkan butirnya saja lebih buruk.** Ia menyisakan 49 paket kosong,
   dan `salinKeSiklus()` melewati sel yang "sudah terisi" — sehingga siklus itu
   tidak akan pernah bisa diisi ulang lewat layar mana pun. Paket kosong yang
   menghalangi perbaikannya sendiri lebih buruk daripada paket yang hilang.

Kelima siklusnya dibuat 7 September 2026 oleh perintah semaian, dua hari sebelum
perubahan ini; tidak ada penilaian sungguhan di dalamnya, dan
`pa:seed-skenario` membangunnya kembali dengan bentuk yang sudah mengikuti
kontrak baru. **Master tidak disentuh:** `N_PA_Competency` (24),
`N_PA_Competency_Requirement` (33), `N_PA_Rater_Policy` (14),
`N_PA_Score_Range` (12) utuh sesudahnya — yang dihapus hanya SNAPSHOT-nya.

### 7.2 Dua indeks lama yang harus dicabut

`UX_PA_Cycle_Question_Item_Aktif` unik pada `(paket, Kode_Soal)` saja. Sesudah
ganti nama, itu berarti **melarang satu bank soal muncul di dua alat tes** —
justru bentuk sah yang membuat identitas bagian harus majemuk. Dibiarkan, ia
menolak paket yang benar dengan pesan yang menuduh datanya kembar.

`IX_PA_Cycle_Question_Item_Cakupan` memuat `PA_Competency_Id` di daftar
INCLUDE-nya, dan `ALTER COLUMN` ditolak selama ada indeks yang bergantung
padanya. Ia dibangun ulang dengan nama kolom baru plus `Id_Jenis_Indikator`.

### 7.3 Tabel yang dibuang

`N_PA_Competency_Kode_Soal` di-DROP: 0 baris, 0 rujukan kode, 0 FK masuk, dan
perannya digantikan rancangan ini. Sudah dihapus dari ERD.

## 8. Yang masih tergantung HC Learn

Empat pertanyaan pertama sudah dijawab pada 9 September 2026 dan tertuang di
`docs/integrasi/2026-09-09-pertanyaan-hclearn-paket-soal-pa.md`. Yang masih
terbuka:

1. **Bagaimana bagian nonaktif tampak lewat API** — hilang dari `bagian[]`, atau
   tetap muncul dengan penanda. Ini menentukan apakah `BAGIAN_HILANG` bisa
   dideteksi sama sekali. Bila bagian nonaktif tetap muncul tanpa penanda, tidak
   ada cara kita mengetahuinya, dan satu kompetensi akan pulang tanpa nilai.
2. **Apakah `tabs[].domain[]` pada `/api/v1/pa/hasil/{id_ujian_token}` membawa
   `id_jenis_soal`.** Tanpa itu nilai tidak bisa dipulangkan ke kompetensi yang
   benar — kita hanya menerima daftar nilai tanpa tahu nilai mana milik bagian
   mana. Ini menghambat *pembacaan hasil*, bukan pengikatan, jadi implementasi
   rancangan ini boleh jalan lebih dulu.
3. **Kredensial.** USERKEY `ef19441355b9c578` dengan `X-Kode-Karyawan: A7`
   dijawab `401 "Akses ditolak: Kredensial tidak valid"` — bukan
   "Invalid Signature", jadi tanda tangannya benar dan yang ditolak pasangan
   identitasnya. Selama ini belum beres, alur hanya bisa dicoba lewat simulasi.
4. **Apakah katalog PA akan dipisah dari katalog rekrutmen.** Sekarang
   `/api/v1/pa/nama-tes` mengembalikan TIU, Kraeplin, PAPI, DISC. Bila tidak
   dipisah, kita perlu tahu field apa yang dipakai menyaringnya.

## 9. Di luar lingkup

- Perubahan matriks kompetensi itu sendiri (`N_PA_Competency_Requirement`).
- Cara nilai bagian diterjemahkan ke skala PA — dengan pemetaan satu-satu, nilai
  satu kompetensi adalah nilai bagiannya, tanpa aturan agregasi.
- Pembacaan hasil dari `/api/v1/pa/hasil/{id_ujian_token}` — tertahan butir 8.2.
- Penjadwalan ke HC Learn (`PaHcLearnScheduleService`) yang masih simulasi.
