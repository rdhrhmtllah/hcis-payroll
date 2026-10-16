# Dashboard Monitoring SOP — lapis kesehatan dokumen

Tanggal: 2026-08-27
Status: **KODE SELESAI — UJI BELUM DIJALANKAN**

> Seluruh kode dan berkas uji sudah ditulis. `php artisan test`, `npx vitest`,
> dan `npm run build` **ditahan sampai ACC** sesuai kesepakatan sesi. Yang sudah
> dijalankan: pemeriksaan sintaksis statis (`php -l`, `node --check`) dan
> pembuktian klasifikasi terhadap data uji di dalam transaksi yang dibatalkan.

Halaman: `/sop/admin/monitoring` — tab **Dashboard** (`TabRingkasan.vue`)

---

## 1. Masalah

### 1.1 Lapis dokumen tidak menghitung dokumen

`SopDocumentControlService::baseVersiAktif()` — yang memberi makan
`/dokumen/ringkasan` **dan** register dokumen — berangkat dari:

```php
N_SOP_Item_Versi v
  JOIN N_SOP_Paket_Item   i ON i.Id_Item_Versi_Aktif = v.Id_SOP_Item_Versi
  JOIN N_SOP_Master_Paket p ON p.Id_SOP_Paket = i.Id_SOP_Paket
 WHERE p.Status = 'PUBLISHED'
```

`N_SOP_Dokumen` — rumah identitas yang dibangun Fase 5 dan diperkaya
DDL-2026-016/019/023/026/027/030 — **tidak pernah ikut**. Butirannya karena itu
BARIS PAKET, dan konsekuensinya tiga:

| Akibat | Keterangan |
|---|---|
| Terhitung berkali-kali | Satu dokumen di 3 paket = 3 baris. Fan-out Fase 3 memang memasang pointer versi aktif pada semua item perujuk, jadi ini konsekuensi, bukan kebetulan. |
| Tak terlihat sama sekali | Dokumen yang belum masuk paket mana pun bukan bernilai nol — barisnya tak ada. |
| Membaca cerminan | `i.Judul_Item` & `i.Nomor_SOP_Resmi` adalah salinan di item paket, bukan identitas dokumen. |

Padahal seluruh gagasan Master Dokumen adalah **satu dokumen dipakai banyak
paket**. Register yang menghitung baris paket mengukur hal yang berbeda dari
yang dinamainya.

### 1.2 Yang TIDAK bermasalah

Lapis kepatuhan (`SopMonitoringService::summary()` dan sebelas endpoint
analitik) **sudah** menghitung dari `N_SOP_Penjadwalan_Detail` saja — penyebutnya
memang sudah "wajib-only". Yang selama ini hilang bukan kebenaran angkanya,
melainkan **kalimat yang menyatakan angka itu diukur atas apa**.

Karena itu mesin analitik ±7.200 baris tidak dibongkar. Membongkar yang sudah
benar hanya menambah risiko tanpa menambah jawaban.

---

## 2. Keputusan

### 2.1 Tiga kelompok, dihitung bukan disimpan

```text
WAJIB          punya ≥1 penugasan aktif — di mana pun
TERBUKA        bukan wajib, tapi ada di ≥1 paket TERBIT
BELUM_BEREDAR  sah di master, tak masuk paket mana pun
```

**Wajib menang.** Satu dokumen bisa dijadwalkan di paket A dan cuma dibuka di
paket B; yang menentukan adalah ada tidaknya kewajiban.

**Saling lepas dan menjumlah tepat ke total.** Syarat ini dipegang keras dan
dikunci uji: angka yang tak menjumlah adalah sumber keraguan yang tak pernah
selesai, dan dashboard yang diragukan tak dipakai siapa pun.

Tak ada kolom baru, tak ada DDL — klasifikasi diturunkan saat dibaca.

### 2.2 `Mode_Distribusi` BUKAN penanda wajib

Keputusan 20 Agu 2026: paket `PUBLIK` **sengaja tetap boleh dijadwalkan**.
`Mode_Distribusi` menjawab "siapa boleh melihat", penjadwalan menjawab "siapa
wajib membaca". Klasifikasi karena itu membaca penjadwalan, bukan distribusi.

### 2.3 Gerbang paket publik: diikuti, lalu dilaporkan

`SopMonitoringPeopleService::terapkanSaringanPaket()` hanya menghitung penugasan
paket `PUBLIK` bila karyawannya sudah pernah membukanya. Gerbang itu punya cacat
yang sudah tercatat: penugasan pada paket publik yang belum dibuka siapa pun
hilang dari seluruh laporan.

Dua pilihan buruk, dan jalan tengah yang dipilih:

- Mengabaikan gerbangnya → dashboard menyebut angka berbeda dari tab Karyawan.
  **Dua angka untuk satu hal adalah kerusakan yang lebih besar daripada cacat
  yang sudah tercatat.**
- Mengikutinya diam-diam → cacatnya terus bekerja tanpa tanda.

**Keputusan: ikuti gerbangnya, lalu laporkan yang tersaring sebagai angka
bernama** — `Tersaring_Gerbang_Publik: { Penugasan, Paket }`, ditampilkan
terang di panel. Kedua lapis tetap sepakat, dan tak ada yang disembunyikan.

### 2.4 Dua lapis, urutannya disengaja

Lapis dokumen ditaruh **paling atas**. Lapis kepatuhan di bawahnya dibuka
sebuah baris yang menyebut penyebutnya:

> *Angka di bawah ini diukur atas **38** dokumen **wajib** — 24 dokumen lain tak
> masuk hitungan karena tak diwajibkan ke siapa pun.*

Menaruhnya di bawah berarti orang membaca persentase lebih dulu, baru tahu
persen dari apa.

---

## 3. Kontrak

`GET /api/v1/sop/admin/monitoring/dokumen/kesehatan` — saringan sama persis
dengan endpoint monitoring lain (`saringan()` di controller).

```jsonc
{
  "Komposisi": {
    "Total": 62,
    "Baris": [ { "Kelompok": "WAJIB", "Nama": "Wajib",
                 "Keterangan": "Ditugaskan ke orang tertentu — kepatuhannya diukur",
                 "Jumlah": 38, "Persen": 61.3 } ]
  },
  "Piramid": [ { "Id": 3, "Nama": "Prosedur", "Kode": "SOP", "Level": 3,
                 "Total": 21, "Wajib": 6, "Terbuka": 1, "Belum_Beredar": 14 } ],
  "APQC": { "Baris": [...], "Total_Proses": 13, "Proses_Kosong": 3, "Tanpa_Proses": 2 },
  "Kepemilikan": { "Baris": [...], "Tanpa_Pemilik": 9 },
  "Versi_Menggantung": { "Total": 7,
    "Baris": [ { "Kode_Tahap": "DRAFTING", "Nama_Tahap": "Drafting",
                 "Warna": "#64748B", "Langkah": 1, "Jumlah": 4, "Umur_Hari": 23 } ] },
  "Tersaring_Gerbang_Publik": { "Penugasan": 3, "Paket": 1 }
}
```

Satu permintaan untuk seluruh lapis. Dipecah per panel akan membuat kartu
komposisi tergambar lebih dulu lalu piramid menyusul dengan total yang sekilas
berbeda — dan admin membaca selisih sesaat itu sebagai data yang tak konsisten.

### 3.1 Klasifikasi: tiga kueri, penggabungan di PHP

Rancangan pertama menanam `EXISTS` ke dalam sebuah `CASE`, yang menuntut
sub-kueri diubah jadi SQL mentah berikut nilainya. **Dibatalkan sebelum
dipakai**: menjahit binding ke dalam teks SQL rapuh pada nilai yang memuat tanda
tanya — tanda tanya di dalam nilai yang baru disisipkan akan tergantikan binding
berikutnya — dan kerapuhan itu duduk tepat di jalur yang menerima query string.

Gantinya: satu kueri daftar dokumen, satu daftar id wajib, satu daftar id
beredar; digabung di PHP. Jumlah dokumen berorde puluhan sampai ratusan.

### 3.2 Apa yang TIDAK disaring organisasi

`idBeredar()` dan `versiMenggantung()` sengaja tak ikut saringan organisasi:

- "beredar" adalah sifat dokumen terhadap paket, bukan terhadap sekelompok
  karyawan — menyaringnya membuat dokumen berpindah kelompok hanya karena
  saringan digeser;
- versi menggantung adalah keadaan di meja redaksi, bukan keadaan penugasan —
  menyaringnya dengan departemen karyawan menjawab pertanyaan yang tak seorang
  pun ajukan.

---

## 4. Berkas

```text
app/Services/LMS/SOP/SopDokumenKesehatanService.php          BARU  ~330 baris
app/Http/Controllers/.../SopAdminMonitoringController.php    +dokumenKesehatan()
routes/LMS/SOP/Governance/SopGovernance.php                  +1 rute

resources/js/.../monitoring/ringkasan/BabKesehatanDokumen.vue          BARU
resources/js/.../ringkasan/panel/PanelKomposisiDokumen.vue             BARU
resources/js/.../ringkasan/panel/PanelPiramidJenis.vue                 BARU
resources/js/.../ringkasan/panel/PanelLubangTataKelola.vue             BARU
resources/js/.../monitoring/TabRingkasan.vue        +lapis 1, +baris penyebut

tests/Feature/LMS/SOP/SopDokumenKesehatanTest.php                      BARU 15 uji
resources/js/.../panel/PanelKomposisiDokumen.test.js                   BARU 5 uji
resources/js/.../panel/PanelPiramidJenis.test.js                       BARU 5 uji
```

Gerbang rute memakai ulang `menuSopMonitoringPage` yang sudah ada — nol baris
RBAC baru.

---

## 5. Keputusan tampilan yang perlu dicatat

- **Tingkat piramid yang KOSONG tetap ditampilkan.** Piramid timpang adalah
  temuan paling penting di panel itu; menyembunyikan tingkat kosong karena tak
  ada datanya membuat ketimpangan tak pernah terlihat.
- **Proses APQC tanpa SOP ikut dihitung.** Lubang tak bisa dilihat dari daftar
  yang hanya memuat yang terisi.
- **Batang bertumpuk, bukan tiga grafik.** Pertanyaannya bukan "berapa Prosedur"
  melainkan "dari 21 Prosedur, berapa yang sampai ke orang".
- **Lebar batang dihitung dari jumlah, bukan dari persen yang sudah dibulatkan
  server.** Tiga persen yang masing-masing dibulatkan berjumlah 99,9% dan
  menyisakan celah di ujung batang.
- **Panel "Yang Menunggu Dikerjakan" tak menampilkan apa pun yang baik-baik
  saja.** Dashboard penuh angka hijau mengajari orang berhenti membacanya. Nol
  tetap terbaca — warnanya hijau, bukan abu pucat yang menyerupai gagal muat.

---

## 6. Diverifikasi terhadap data uji

Dijalankan di dalam transaksi yang dibatalkan (DB tetap bersih):

```text
tanpa paket          baris=1  BELUM_BEREDAR
di 1 paket terbit    baris=1  TERBUKA
punya penugasan      baris=1  WAJIB
di 3 paket + tugas   baris=1  WAJIB      ← penggandaan tertutup
publik belum dibuka  baris=1  TERBUKA    ← dilaporkan terpisah: 1 penugasan, 1 paket
Total 6 = 2 + 2 + 2 · baris yang tak menjumlah: 0
```

---

## 7. Yang sengaja TIDAK dikerjakan

- **Membongkar empat bab kepatuhan.** Mesinnya sudah menghitung dari penjadwalan
  saja; yang kurang cuma kalimat penyebut, dan itu sudah ditambahkan.
- **Mengubah `SopDocumentControlService::baseVersiAktif()`.** Kueri itu juga
  menopang halaman Master Dokumen. Butirannya tetap baris paket — **dan itu
  benar untuk daftar barisnya**, karena satu dokumen memang muncul di beberapa
  paket. Yang keliru dulu adalah memakainya sebagai jumlah DOKUMEN.
- **Kolom klasifikasi di DB.** Keadaan yang bisa diturunkan tak disimpan dua
  kali.
- **Saringan kelompok yang menembus ke lapis kepatuhan.** Kartu komposisi sudah
  mengemit `pilih`, tetapi penyaluran ke sebelas endpoint analitik adalah
  pekerjaan tersendiri dengan risikonya sendiri.

## 8. Sisa pekerjaan

| | |
|---|---|
| **Wajib sebelum dipercaya** | Jalankan suite penuh + vitest + build sesudah ACC |
| **Wajib diverifikasi di data nyata** | Penggandaan baris paket baru bisa diukur setelah ada dokumen yang dipakai >1 paket. DB dev saat ini berisi 1 dokumen. |
| **Menunggu keputusan** | Apakah label register dokumen diubah dari "dokumen" menjadi "baris paket" supaya berhenti bersaing dengan angka dashboard |
| **Cacat yang diwarisi** | Gerbang paket publik masih menyembunyikan penugasan yang belum dibuka dari lapis kepatuhan; kini setidaknya jumlahnya dilaporkan |

---

# Lanjutan — dua lensa: Fungsional & Publik (2026-08-27, sore)

Status: **KODE SELESAI — UJI BELUM DIJALANKAN**

## 9. Cacat yang ditemukan pada klasifikasi pertama

`N_SOP_Progress.Id_SOP_Penjadwalan_Detail` adalah **NOT NULL**: tak ada tempat
menyimpan jejak baca tanpa penugasan. Karena itu karyawan yang membuka SOP
`PUBLIK` **didaftarkan sendiri oleh sistem**:

```php
// SopPendaftaranMandiri
app(SopPenjadwalanService::class)->assign($idPaket, [$kode],
    ['sumber' => 'MANDIRI'], null);   // Created_By NULL — tak ada admin
```

Klasifikasi §2.1 memakai "punya penugasan aktif" sebagai tanda kewajiban.
Akibatnya **dokumen publik berpindah ke kelompok Wajib begitu satu orang
membacanya sukarela** — yang dibaca sebagai "ada kewajiban" sebenarnya "ada
yang pernah membuka".

Sinyal yang benar sudah tersedia: `N_SOP_Penjadwalan.Sumber` ∈ `MANUAL` ·
`AUTO_RULE` · `MANDIRI`. Dua yang pertama kewajiban; yang ketiga minat.

## 10. Klasifikasi diperbaiki, istilah diseragamkan

```text
FUNGSIONAL     penugasan aktif ber-Sumber MANUAL / AUTO_RULE
PUBLIK         bukan fungsional, ada di ≥1 paket TERBIT
BELUM BEREDAR  tak masuk paket mana pun
```

`WAJIB` → `FUNGSIONAL`, `TERBUKA` → `PUBLIK`, di kode **dan** di layar. Modul
ini sudah pernah rugi karena dua kosakata untuk satu hal.

## 11. Saringan `sumber` — satu pintu untuk sebelas endpoint

Ditambahkan di `SopMonitoringPeopleService::terapkanSaringanPaket()`, gerbang
yang dilewati seluruh analitik. Tanpa nilai `sumber`, perilakunya persis
seperti sebelumnya — tak ada pemanggil lama yang berubah diam-diam.

**Satu jebakan yang hampir terlewat:** `adaSaringan()` menentukan apakah
`summary()` memakai kueri tersaring atau `N_SOP_Coverage_Rollup` yang
pra-agregat. Tanpa menambahkan `'sumber'` ke daftarnya, kartu coverage akan
melaporkan angka **tak tersaring** sementara kartu di sebelahnya tersaring —
tanpa satu pun tanda bahwa keduanya menghitung himpunan berbeda.

Dampaknya terukur pada data peraga:

| | tanpa saringan | hanya kewajiban |
|---|---|---|
| coverage | 61 | **64** |
| tertunda | 180 | **64** |

116 orang yang selama ini dihitung sebagai tunggakan kepatuhan sebenarnya
pembaca sukarela yang belum selesai menelusuri SOP publik.

## 12. Lensa Publik — metrik yang berbeda, bukan metrik yang sama difilter

`SopJangkauanPublikService` · `GET /dokumen/jangkauan-publik`

| Ukuran | Cara hitung |
|---|---|
| Jangkauan | karyawan yang pernah **membuka** ÷ seluruh karyawan aktif |
| Tersentuh vs Membuka | selisihnya = orang yang mengetuk pintu lalu berbalik |
| Terpopuler | urut jumlah pembuka mandiri |
| **Diabaikan** | publik, terbit, **nol** pembuka |
| Tren | pembukaan mingguan dari `Waktu_Buka` |
| Sebaran unit | unit mana yang membaca sukarela |

Yang **sengaja tak ada**: tenggat, keterlambatan, mandek, tingkat penyelesaian.
Tak ada kewajiban yang dilanggar, jadi keempatnya bukan bernilai nol —
melainkan tak punya arti. Bab Publik membuka dengan kalimat yang menyatakan itu,
supaya orang tak menyimpulkan tabnya belum jadi.

### Titik buta yang dinyatakan, bukan disembunyikan

Dokumen publik yang tak pernah dibuka tak meninggalkan jejak apa pun. "Berapa
yang belum menyentuh" karena itu adalah **selisih** terhadap seluruh karyawan
aktif — sebuah kesimpulan, bukan pembacaan. Kalimat itu tercetak di panelnya.

## 13. Berkas

```text
SopDokumenKesehatanService.php       ubah  FUNGSIONAL/PUBLIK + SUMBER_KEWAJIBAN
SopMonitoringPeopleService.php       ubah  saringan 'sumber' + adaSaringan()
SopJangkauanPublikService.php        BARU  ~250 baris
SopAdminMonitoringController.php     +endpoint, +'sumber' pada saringan()
TabRingkasan.vue                     ubah  dua lensa + kirim sumber
ringkasan/BabJangkauanPublik.vue     BARU
panel/PanelJangkauan.vue             BARU
panel/PanelDokumenDiabaikan.vue      BARU
tests/.../SopJangkauanPublikTest.php BARU  6 uji
tests/.../SopDokumenKesehatanTest.php ubah  +3 penjaga sumber (18 uji)
```

## 14. Data peraga

`sop:seed-demo` kini membuat **kohort MANDIRI** pada paket publik: 120 karyawan
menemukannya bertahap sepanjang 60 hari, sebagian membuka sebagian dokumen, dan
satu dokumen sengaja tak disentuh siapa pun. Tanpa kohort itu tab Publik
menampilkan nol di setiap angka — bukan karena tak ada yang membaca, melainkan
karena dunia peraga tak pernah punya pembacanya.

Hasil terukur: jangkauan 118/958 (12,3%), tiga dokumen terpopuler (111 · 77 ·
52 pembuka), satu diabaikan, tren 9 minggu, 10 unit.

## 15. Sebaran: lima kartu diringkas jadi satu, sumbunya dipilih

Piramid jenis dan sebaran APQC berdiri sebagai **dua kartu terpisah** yang
menjawab satu pertanyaan yang sama — *dokumen kita bertumpuk di mana?* — dengan
grafik yang bentuknya identik. Di saat yang sama `Kepemilikan` sudah dihitung
sejak awal tetapi **tak pernah tergambar sama sekali**: datanya ada, kartunya
tak pernah dibuat.

Keduanya diganti satu kartu **Sebaran Dokumen** dengan pemilih sumbu:

| Sumbu | Sumber | Baris nol ditampilkan? | Tautan Master Dokumen |
|---|---|---|---|
| Jenis Dokumen | master jenis, urut `Level_Hirarki` | ya — tingkat kosong adalah temuan | `?jenis=` |
| Proses APQC | master APQC, urut `Urutan` | ya — proses tanpa SOP adalah lubang | `?apqc=` |
| Unit Pemilik | teramati, urut jumlah | tidak — unit yang memang tak pernah menerbitkan SOP bukan temuan | `?departemen=` |
| Tipe Isi | teramati, urut jumlah | tidak | `?tipe=` |
| Paket Penugasan | `N_SOP_Paket_Item`, urut jumlah | ya — paket kosong = kerja berhenti di tengah | — |

**Semuanya dihitung dalam satu balasan.** Empat dari lima sumbu hanya
mengelompokkan koleksi `klasifikasi()` yang sudah di tangan; hanya sumbu paket
menambah dua kueri. Perpindahan sumbu karena itu seketika — tanpa permintaan,
tanpa pemuat berputar. Satu permintaan per pergantian sumbu akan menukar tinggi
layar dengan jeda, dan itu bukan pertukaran yang lebih baik.

### 15.1 Satu bentuk baris untuk semua sumbu

```
Id · Nilai · Kode · Nama · Label · Tanpa · Total · Fungsional · Publik · Belum_Beredar
```

Medan yang tak relevan bagi sebuah sumbu datang sebagai `null`, **bukan hilang**.
Panelnya karena itu tak punya satu pun cabang per sumbu — begitu layar mulai
bertanya "sumbu apa ini?", sumbu keenam akan menuntut cabang keenam dan
kelimanya perlahan menyimpang. Dijaga `test_semua_sumbu_memakai_bentuk_baris_yang_sama`.

### 15.2 Sumbu paket TIDAK menjumlah — dan mengakuinya

Satu dokumen boleh dipakai beberapa paket sekaligus; fan-out Fase 3 memang
dirancang begitu. Menjumlahkan seluruh batang sumbu paket karena itu **tidak**
menghasilkan jumlah dokumen. Tiap sumbu membawa `Menjumlah` dan `Catatan`:

> 1 dokumen dipakai lebih dari satu paket, jadi 42 dokumen menempati 29 tempat.
> Menjumlahkan seluruh batang tidak menghasilkan jumlah dokumen.

Sumbu yang tak menjumlah tanpa mengatakannya adalah persis sumber keraguan yang
membuat dashboard berhenti dipercaya. Dijaga
`test_sumbu_yang_mengaku_menjumlah_benar_benar_menjumlah` — yang juga menuntut
sumbu yang MENGAKU menjumlah benar-benar menjumlah tepat ke total.

Satu paket bisa memuat dua item yang menunjuk dokumen yang sama; batangnya
menghitung **dokumen**, bukan item (`test_sumbu_paket_menghitung_dokumen_bukan_item`).

### 15.3 Cacat yang ikut ketahuan: tautan yang batal diam-diam

Piramid lama mengirim id **mentah** ke `/sop/admin/dokumen?jenis=7`. Halaman itu
men-`decode()` hashid, jadi `decode('7')` memulangkan `null`, saringannya batal
tanpa satu pun tanda, dan admin mendarat di daftar **seluruh** dokumen di layar
yang menjanjikan satu tingkat saja.

Nilai tautan kini disiapkan server pada medan `Nilai` — hashid untuk
jenis/apqc/unit, kode mentah untuk tipe, `'BELUM'` untuk baris "tanpa", `null`
untuk paket (Master Dokumen memang tak punya saringan paket, dan tombol yang
mendarat di daftar tak tersaring lebih buruk daripada tak ada tombol). Dijaga
`test_nilai_tautan_bisa_dibaca_kembali_master_dokumen`.

### 15.4 Berkas

```
app/Services/LMS/SOP/SopDokumenKesehatanService.php   ubah  +SUMBU_SEBARAN, sebaran(), 5 pembangun sumbu
                                                            −piramid()/apqc()/kepemilikan()
resources/js/.../panel/PanelSebaranDokumen.vue        BARU  ~700 baris
resources/js/.../panel/PanelSebaranDokumen.test.js    BARU  9 uji
resources/js/.../BabKesehatanDokumen.vue              ubah  2 kartu → 1 kartu lebar penuh
tests/.../SopDokumenKesehatanTest.php                 ubah  4 uji Piramid/APQC → 9 uji Sebaran
```

Payload `Piramid`, `APQC`, dan `Kepemilikan` **dihapus** dan digantikan satu
kunci `Sebaran`; ketiganya tak punya pembaca lain di luar kartu ini.

**Yatim setelah perubahan ini:** `PanelPiramidJenis.vue`, `PanelSebaranAPQC.vue`
(berikut ujinya) — menyusul `PanelKomposisiDokumen.vue` dan
`PanelLubangTataKelola.vue` dari tahap sebelumnya. Berkasnya sengaja belum
dihapus; keputusannya menunggu.

### 15.5 Terukur terhadap data peraga (42 dokumen)

```
JENIS DOKUMEN     7 baris   0 kosong   42 batang   menjumlah
PROSES APQC      14 baris   4 kosong   42 batang   menjumlah
UNIT PEMILIK     11 baris   0 kosong   42 batang   menjumlah   (4 tanpa pemilik, di baris teratas)
TIPE ISI          2 baris   0 kosong   42 batang   menjumlah   (36 Dokumen · 6 Form)
PAKET PENUGASAN   8 baris   0 kosong   43 batang   TIDAK menjumlah + catatan
```

## 16. Strip KPI: empat kartu jadi tiga, dan satu angka ganda yang ketahuan

| Dulu | Sekarang |
|---|---|
| Tingkat Penyelesaian | **Reading Completion Rate** |
| Karyawan Tersentuh | **Total User** — user yang DIBERI PENUGASAN; sub: "X% dari N user aktif" |
| Paket Aktif | **Active Document Packages** |
| Masih Tertunda | *dihapus* |

Penamaan Inggris ini **menyimpang dari kosakata resmi** yang diseragamkan
20 Agu 2026 (`Coverage → Tingkat Penyelesaian`) atas permintaan user, dan
berlaku **hanya untuk tiga kartu ini**. "Tingkat Penyelesaian" di
`ModalExportRingkasan.vue` sengaja tidak diikutkan.

### 16.1 "Karyawan Tersentuh" dan "Masih Tertunda" adalah angka yang sama

Keduanya menghitung `DISTINCT Kode_Karyawan` dengan `Status_Paket <> SELESAI`
— yang pertama lewat `sisa.orang` dari `/analitik/momentum`, yang kedua lewat
`tertunda` dari `/summary`. Di data peraga keduanya **181**. Dua kartu bersebelahan
menampilkan satu angka dengan dua nama.

Karena itu "Karyawan Tersentuh" bukan sekadar diganti nama: karyawan yang sudah
menuntaskan semuanya **keluar** dari angka itu, jadi label lamanya pun sudah
salah sejak awal.

### 16.2 Dua angka, satu populasi

`summary()` kini memulangkan:

```
user_ditugaskan  = DISTINCT penugasan aktif, DIGERBANGI Aktif='Y'  → 222   ← angka utama kartu
user_total       = Karyawan.Aktif='Y' + saringan organisasi        → 958   ← penyebut di keterangan
```

Angka utamanya adalah yang **diberi penugasan**, bukan seluruh karyawan: kartu ini
berdiri di papan kepatuhan, dan orang tanpa satu pun penugasan tak punya apa pun
yang bisa diukur di sini. Memakai populasi penuh sebagai angka utama membuat kartu
ini menghitung populasi HR, bukan populasi yang sedang dipantau. Populasi penuhnya
tetap disebut di keterangan — tanpa itu "222" tak bisa dinilai besar atau kecil.

Gerbang `Aktif='Y'` dipasang di **kedua** sisi karena angka bawah adalah bagian
dari angka atas; tanpa itu karyawan nonaktif yang masih memegang penugasan
menghasilkan "223 dari 958" yang mustahil. Predikatnya sengaja sama dengan
`SopJangkauanPublikService` supaya dua kartu yang sama-sama menyebut "user"
tak berangkat dari populasi berbeda.

Terukur: polos 958/222; disaring divisi PRODUCTION 282/94 — menyempit di kedua
sisi, dan sub-angka tak pernah melebihi induknya.

### 16.3 Keterangan kartu tak lagi tertimpa rentang

Sebelumnya keterangan **diganti** `Rentang: …` begitu saringan tanggal dipasang,
sehingga "Total User 958" tersisa berketerangan "Rentang: Jan–Mar" — persis saat
pembacanya paling perlu tahu angka itu menghitung apa. Sekarang artinya di depan,
rentang menyusul: `Paket terbit & berlaku · Jan–Mar`.

### 16.4 Berkas

```
app/Services/LMS/SOP/SopMonitoringService.php     ubah  +user_total, +user_ditugaskan
resources/js/.../ringkasan/BabKeadaan.vue         ubah  4 kartu → 3, prop 'orang' & klikKpi mati dibuang
resources/js/.../monitoring/TabRingkasan.vue      ubah  ikatan :orang dan @buka-tab yang tak lagi terpakai
tests/.../SopRingkasanSaringanTest.php            ubah  +2 uji, +2 penjaga pada uji penyempitan
```

**Yatim (sudah sejak sebelum perubahan ini):** `ZonaSekarang.vue`,
`ZonaTindakan.vue`, `ZonaTren.vue` — nol pengimpor.

### 16.5 Strip KPI dinaikkan ke paling atas

Urutan halaman tab Dashboard sekarang: lensa → sub-nav → saringan → **strip
KPI** → Lapis 1 Kesehatan Dokumen → penyebut → bab kepatuhan. Sebelumnya strip
KPI duduk di bawah seluruh lapis dokumen dan baru terlihat setelah menggulung.

Komentar lama pada Lapis 1 ("ditaruh PALING ATAS dengan sengaja … supaya orang
tak membaca persentase sebelum tahu persen dari apa") ikut ditulis ulang, bukan
dibiarkan — komentar yang menjelaskan urutan yang sudah tak berlaku lebih buruk
daripada tak ada komentar. Kekhawatirannya sendiri sudah dijawab §16.3: penyebut
tiap angka kini tertulis di keterangan kartunya, tak lagi menumpang pada urutan
halaman.

Di dalam `BabKeadaan.vue`, strip juga dinaikkan **di atas kartu "Sejak kunjungan
terakhir"**. Kartu kabar itu muncul bersyarat, jadi selama ia di atas, baris
pertama halaman berpindah-pindah tergantung ada tidaknya kabar — dan angka utama
yang letaknya tak tetap harus dicari ulang tiap kali halaman dibuka.

Urutan `bab` di sub-nav ikut dibalik (`keadaan` sebelum `dokumen`) berikut
`aktif` awalnya: tombolnya menggulung ke bagian, jadi daftar yang tak seurut
layar membuat "berikutnya" melompat ke belakang.

## 17. Strip KPI dokumen dilebur ke strip utama

Lapis 1 punya strip KPI-nya sendiri berisi empat kartu. Dua dinaikkan ke strip
utama, dua dihapus:

| Kartu | Nasib |
|---|---|
| Dokumen Terupload | naik ke strip utama (kartu ke-4) |
| Rata-rata Lead Time | naik ke strip utama (kartu ke-5) |
| Coverage Per Dokumen | dihapus |
| Coverage Per Paket | dihapus |
| kartu "Analisis Coverage (Dokumen & Paket)" | dihapus |

Strip utama jadi lima kartu; `bkd-kpi-grid` beserta seluruh CSS-nya hilang dari
`BabKesehatanDokumen.vue`. Kartu Lead Time yang tersisa dijadikan lebar penuh —
satu kartu setengah lebar bersebelahan dengan ruang kosong terbaca sebagai
tata letak yang rusak, bukan sebagai keputusan.

### 17.1 Angkanya dititipkan, bukan diambil ulang

`Dokumen Terupload` dan `Rata-rata Lead Time` berasal dari `/dokumen/kesehatan`
— permintaan terberat di halaman ini — sedangkan strip utama membaca `/summary`.
Memanggil endpoint itu dari dua komponen berarti **dua kali kerja server untuk
satu layar**, dan dua angka yang bisa berselisih sesaat.

Karena itu `BabKesehatanDokumen` menitipkannya lewat `@ringkas` (yang memang
sudah ada untuk penyebut), induk menyimpannya di `penyebut`, dan meneruskannya
ke `BabKeadaan` sebagai prop `dokumen`. Konsekuensinya dua kartu paling kanan
terisi sesaat setelah tiga kartu pertama — itulah gunanya `loading`.

**Penandanya `siap`, bukan `total`.** Menyimpulkan "sudah sampai" dari `total > 0`
membuat perusahaan yang master dokumennya memang kosong melihat kartu berputar
selamanya — persis keadaan yang paling perlu terbaca sebagai nol. `siap: true`
juga dikirim pada jalur GAGAL: galat yang diam membuat dua kartu itu berputar
tanpa akhir sementara pesan galatnya sudah tampil di bagian bawah.

### 17.2 Sisa yang perlu diputuskan

`coverageDokumen()` dan `coveragePaket()` di `SopDokumenKesehatanService` kini
**tak punya pembaca satu pun**, tetapi masih dihitung tiap kali dashboard dibuka
(±4 kueri). Servernya sengaja tidak disentuh — instruksinya "hide", dan
membuang kemampuan server atas instruksi menyembunyikan tampilan adalah
pelebaran yang tak diminta. Perlu keputusan terpisah.

Yatim bertambah: `PanelCoverageDokumenPaket.vue` (berikut ujinya).

## 18. Strip KPI jadi compact

`layout="compact"`, `gap="sm"`, lima kolom. Kartunya kehilangan baris
"Target / %" di atas batang progres, dan keterangannya jadi satu baris
ber-ellipsis (teks penuhnya tetap ada sebagai `title`). Keterangan ikut
dipendekkan supaya tetap terbaca di lebar yang lebih sempit:

| Kartu | Keterangan |
|---|---|
| Reading Completion Rate | Dari seluruh penugasan aktif |
| Total User | 11% dari 958 user aktif |
| Active Document Packages | Terbit & berlaku |
| Dokumen Terupload | 23 fungsional · 4 publik · 14 belum beredar |
| Rata-rata Lead Time | Respon → terbit · N dokumen |

### 18.1 Compact tidak punya kerangka pemuat

Cabang `compact`/`dense` di `HcisStatCard.vue` **tidak menggambar
`HcisSkeleton`** — hanya cabang `default`/`hero` yang punya, dan kelas
`.is-loading` tak punya satu pun aturan gaya di `design-system.css`. Artinya
prop `loading` menjadi tanpa efek begitu tata letaknya compact, dan kartu yang
sedang menunggu akan menampilkan **"0"** — yang tak bisa dibedakan dari
perusahaan yang memang belum punya satu dokumen pun.

Karena itu keadaan menunggu dinyatakan pada NILAINYA: `—` dengan keterangan
"Memuat…". Dua kesiapan dipisah — tiga kartu pertama menunggu `/summary`, dua
terakhir menunggu titipan lapis dokumen — sebab satu penanda bersama akan
menahan kartu yang datanya sebenarnya sudah sampai.

## 19. Dua lensa dilebur jadi satu halaman

Pemisahan Fungsional/Publik sebagai **dua tab** (§12) dicabut. Alasannya satu:
tab kedua menyembunyikan seluruh isinya di balik satu klik yang jarang
dilakukan, dan yang tak pernah dibuka sama saja dengan tak ada.

Urutan halaman sekarang:

```
sub-nav → saringan → strip KPI → Lapis 1 Kesehatan Dokumen
                   → Jangkauan Dokumen Publik → penyebut → bab kepatuhan
```

Yang **tidak** ikut dicabut adalah alasan pemisahannya. Bagian publik tetap
berdiri sebagai bagiannya sendiri, dengan kalimat pembukanya yang menyatakan
tenggat/keterlambatan/tingkat penyelesaian tak berlaku di sana — dan kalimat itu
kini justru lebih dibutuhkan, sebab bagiannya berdiri tepat di bawah angka
kepatuhan yang ukurannya sama sekali lain. `paramsPublik` juga tetap: bagian ini
tak boleh menerima saringan `sumber` maupun `date_mode`.

| Kartu | Nasib |
|---|---|
| Jangkauan | tetap, berganti nama jadi **Jangkauan Dokumen Publik** |
| Apa yang Dipedulikan Orang | dihapus |
| Minat dari Waktu ke Waktu | dihapus |

Bagian publik karena itu tinggal SATU kartu. Pembungkusnya tetap sebuah
komponen: kalimat pembuka, keadaan memuat, galat, dan keadaan "belum ada dokumen
publik" semuanya masih diperlukan, dan endpointnya sendiri terpisah.

`Terpopuler`, `Diabaikan`, `Tren`, dan `Unit` di `/dokumen/jangkauan-publik`
kini tak punya pembaca — bersama `Coverage_Dokumen`/`Coverage_Paket` (§17.2),
tinggal `Jangkauan` saja yang terbaca dari endpoint itu. Servernya sengaja tak
disentuh; keputusannya menunggu.

Yatim bertambah: `PanelDokumenDiabaikan.vue`.

## 20. Rentang tanggal: audit, dua cacat, dan baku 30 hari

Diperiksa lewat EFEK, bukan lewat pembacaan kode: tiap sumber dipanggil dua kali
— tanpa rentang, lalu dengan rentang satu hari di 2020 — lalu hasilnya
dibandingkan. Angka yang sama persis berarti saringannya tak menggigit.

### 20.1 Hasil audit

| Kartu | Ikut rentang | Keterangan |
|---|---|---|
| Reading Completion Rate | **ya** | 63% → 0% |
| Total User (angka utama) | **ya** | 109 → 0 |
| Total User (penyebut 958) | tidak | populasi sekarang |
| Active Document Packages | tidak | paket yang berlaku sekarang |
| Sebaran / Dokumen Terupload | tidak | lapis dokumen |
| Lead Time | tidak | lapis dokumen |
| Antrean Kerja | tidak | pekerjaan yang menunggu SEKARANG |
| Jangkauan Dokumen Publik | tidak | seluruh jejak minat |
| Peringkat Unit | **ya** | isi berubah |
| Heatmap | **ya** | isi berubah |
| Kartu kabar (pulse) | tidak | punya jendelanya sendiri |

Pemisahannya disengaja: **lapis kepatuhan berjendela, lapis dokumen tidak.**
Yang tidak berjendela kini MENYATAKANNYA — keterangan kartunya berakhir
"· keadaan sekarang", dan tiap bagian membawa satu baris penjelas saat rentang
aktif. Sebelumnya keterangan mereka justru menempelkan rentang, yakni mengaku
tersaring padahal angkanya tak bergeser sedikit pun.

### 20.2 Dua cacat yang ditemukan audit

**Lead Time selalu nol.** `leadTime()` menyaring `Versi_Ke = 1`, padahal
`nomorVersiBerikut()` memulai dari **nol** — jadi `1` menunjuk versi KEDUA. Di
basis data ini ada 42 versi bernomor 0 (29 terbit) dan hanya 1 bernomor 1 (tak
terbit), sehingga kartunya menghitung himpunan kosong dan menampilkan 0 hari
selamanya. Diganti "terbitan paling awal per dokumen" lewat `Tanggal_Publish`,
yang tak bergantung pada kesepakatan penomoran. **0 → 6,2 hari dari 28 dokumen.**

**Kelompok dokumen bergeser karena rentang.** `idFungsional()` menerapkan
rentang tanggal, sehingga dokumen yang ditugaskan enam bulan lalu berpindah ke
"belum beredar" begitu layar disetel ke 30 hari — dan komposisi, sebaran,
antrean "sah tapi tak beredar", serta kalimat penyebut ikut berbohong. Rentang
dilepas di sana (saringan organisasi tetap). **23 fungsional → tetap 23.**

Keduanya dijaga uji baru di `SopDokumenKesehatanTest.php`.

### 20.3 Baku 30 hari terakhir — dan mengapa itu semula justru memberatkan

`SaringanMonitoring::mungkinCache()` dulu mematikan cache begitu ada saringan
APA PUN, termasuk rentang tanggal. Dengan baku berentang, **tiap kunjungan jadi
"tersaring" dan cache tak pernah kena sama sekali**:

```
                              SEBELUM                 SESUDAH
Tanpa rentang · cache kosong  3.619 ms / 58 kueri     3.351 ms / 58 kueri
Tanpa rentang · cache hangat  2.304 ms / 37 kueri     2.239 ms / 37 kueri
30 hari · cache kosong        3.100 ms / 50 kueri     3.451 ms / 58 kueri
30 hari · cache hangat        3.100 ms / 50 kueri     2.164 ms / 37 kueri  ← tercepat
```

Yang membuat kunci cache meledak bukan rentang tanggal melainkan saringan
organisasi (kategori × paket × divisi × jabatan). Rentang cuma punya segelintir
bentuk — enam chip + sesekali rentang khusus — jadi ia dipindah dari "mematikan
cache" menjadi "bagian dari kunci". Tanpa perubahan itu, baku 30 hari justru
membuat tiap kunjungan berulang **lebih berat**, kebalikan dari tujuannya.

**`sumber` ikut masuk kunci**, dan itu perbaikan tersendiri: ia tak pernah
terhitung sebagai saringan, sehingga hasil yang dihitung DENGAN saringan sumber
ditulis ke kunci yang berarti "tanpa saringan". Selama semua pemanggil mengirim
sumber yang sama, hal itu tak terlihat — satu pemanggil dengan sumber berbeda
meracuni kunci bersama selama 10–15 menit. Itu benar-benar terjadi saat audit
ini berjalan: kartu heatmap tampil kosong selama sepuluh menit karena satu
panggilan probe.

Catatan operasional: `php artisan cache:clear` **tidak** membersihkannya —
store bawaannya `file`, sedangkan `mungkinCache()` memakai `database`.

### 20.4 Tampilan baku sekarang

```
                  SEMUA WAKTU   30 HARI (baku)
coverage          63%           61%
user ditugaskan   109           71
paket aktif       7             7      (keadaan sekarang)
lapis dokumen     41 / 23 fungsional / 6,2 hari / 23 antrean  (tak berubah)
```

### 20.5 "Seluruh Waktu" dicabut, bukan sekadar bukan-baku

Tanpa rentang, tiap agregat menyapu seluruh riwayat penugasan sejak modul ini
hidup. Pilihannya karena itu dihapus dari layar — dan ditutup di **tiga** pintu,
sebab pintu yang cuma tertutup di satu tempat sama saja terbuka:

1. chip "Semua Waktu" dihapus dari `quickChips`;
2. `resetSaringan()` kembali ke baku 30 hari, bukan mengosongkan rentang —
   tombolnya pun berganti dari "Bersihkan" jadi "Kembali ke 30 Hari", dan hanya
   muncul saat rentangnya memang sudah digeser;
3. `onSaringan()` mengembalikan rentang yang dikosongkan lewat pemilih tanggal —
   pemilihnya sendiri tetap bisa dikosongkan pengguna, dan tanpa penjaga ini
   layar akan diam-diam kembali ke jalur terberat.

Fallback label "Semua Waktu (Seluruh Riwayat)" di `ModalExportRingkasan.vue`
karena itu kini tak terjangkau; dibiarkan sebagai pengaman, bukan sebagai jalur
yang dipakai.

### 20.6 Judul kartu berbahasa Inggris

Kelima judul strip KPI memakai bahasa Inggris; **keterangan di bawahnya tetap
Indonesia**, dan itu keputusan eksplisit, bukan pekerjaan setengah jadi.

```
Reading Completion Rate   Dari seluruh penugasan aktif
Total User                11% dari 958 user aktif
Active Document Packages  Terbit & berlaku · keadaan sekarang
Uploaded Documents        23 fungsional · 4 publik · 14 belum beredar · keadaan sekarang
Average Lead Time         Respon → terbit · 28 dari 41 dokumen sudah terbit · keadaan sekarang
```

`Uploaded Documents` sengaja TIDAK diubah jadi "Dokumen Terpublish": angkanya
menghitung seluruh dokumen aktif (41), termasuk 13 yang belum pernah terbit,
sehingga label "terpublish" akan berselisih dengan "Sudah terbit: 28" pada kartu
Lead Time. Diputuskan user setelah selisihnya ditunjukkan.
