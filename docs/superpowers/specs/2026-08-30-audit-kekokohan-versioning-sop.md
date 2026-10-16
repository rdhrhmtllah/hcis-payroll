# Audit kekokohan manajemen versi SOP

**Tanggal**: 30 Agustus 2026
**Lingkup**: siklus hidup `N_SOP_Item_Versi` — penomoran, status, tahap, pointer versi berlaku
**Sifat**: temuan — **sudah dikerjakan 30 Agu 2026**, lihat status per temuan.

## Status per temuan (per 30 Agu 2026)

| # | Temuan | Status |
|---|---|---|
| 1 | Tiga representasi satu keadaan | ✅ `SopVersiKeadaan` jadi penjawab tunggal |
| 2 | 10 dari 12 rujukan tanpa FK | ✅ DDL-2026-062 — 3 FK dipasang (log sengaja tetap tanpa FK) |
| 3 | Tak ada penegak satu-PUBLISHED | ✅ indeks unik terfilter + `lockForUpdate` |
| 4 | Penerbitan tak atomik | ✅ satu transaksi berkunci dari publish sampai rekonsiliasi |
| 5 | Balapan penomoran | ✅ hitung nomor + insert di dalam transaksi berkunci |
| 6 | Tak ada penjaga tulis-bersamaan | ✅ `Cap_Versi` (Updated_At jatuh ke Created_At) |
| 7 | Nomor dikonsumsi draft batal | ✅ DDL-2026-062 + `SopNomorVersi`; sisa data dirapikan DML-2026-063 |
| 8 | Keadaan disimpulkan tiap layar | ✅ empat layar membaca fakta server |
| 9 | Tipe non-DOKUMEN tak bisa terbit | ⛔ **BELUM** — menunggu keputusan produk |

Satu koreksi penting atas temuan 7: aturannya ternyata ditulis di **lima**
tempat, bukan empat. Yang kelima (`nomorVersiTerpakai`, dipakai `storeVersi`
sebagai pemeriksaan di muka) baru ketahuan saat user mencoba dan ditolak
"Versi 01 sudah ada" pada nomor yang diusulkan sistem itu sendiri.

## Cara audit ini dikerjakan

Mulai dari **data**, bukan dari kode. Data yang menyimpang membuktikan invarian
mana yang tak pernah ditegakkan; data yang bersih tak membuktikan apa pun selain
bahwa jalurnya belum pernah dilewati.

Populasi dev saat audit: **46 versi ber-dokumen**. Kecil. Bersihnya data di
bawah ini harus dibaca sebagai "belum tersentuh", bukan "aman".

## Hasil pemeriksaan data — semuanya nol

| # | Pemeriksaan | Temuan |
|---|---|---|
| A1 | `Status` versi ≠ `Status_Teknis` tahapnya | 0 |
| A2 | `PUBLISHED` tapi `Flag_Aktif='T'` | 0 |
| A3 | tahap `CANCEL` tapi `Flag_Aktif='Y'` | 0 |
| B1 | `Id_Versi_Aktif` menunjuk versi bukan `PUBLISHED` | 0 |
| B2 | `Id_Versi_Aktif` menunjuk versi dokumen lain | 0 |
| B3 | punya versi `PUBLISHED` tapi pointer kosong | 0 |
| B4 | pointer menunjuk baris yang tidak ada | 0 |
| B5 | dokumen dengan lebih dari satu versi `PUBLISHED` | 0 |

Yang menarik justru ini: **tak satu pun dari delapan invarian itu ditegakkan
basis data.** Semuanya bergantung pada disiplin kode.

---

## Temuan

### 1. Tiga representasi untuk satu keadaan — dan ketiganya bisa berselisih

`Status`, `Tahap_Id`, dan `Flag_Aktif` sama-sama menyatakan keadaan versi.
Hubungannya tak dijaga apa pun: `CHECK` yang ada hanya membatasi nilai
masing-masing kolom (`Status IN (DRAFT, PUBLISHED, OBSOLETE)`,
`Flag_Aktif IN (Y,T)`), bukan hubungan di antara mereka.

Akibat yang **sudah terbukti menggigit**: `CANCEL.Status_Teknis = 'DRAFT'`,
sehingga versi batal tetap berstatus DRAFT. Setiap penjaga yang ditulis
`Status === 'DRAFT'` meloloskannya. Ditemukan di `ubahVersi`, `hapusVersi`,
`simpanReviewVersi`, dan **15 tempat di layar** — beberapa sudah ditambal
30 Agu 2026, tapi penambalan itu satu per satu.

**Bobot: tinggi.** Ini akar yang melahirkan cacat berulang, bukan satu cacat.

### 2. Sepuluh dari dua belas rujukan ke versi tidak berpagar FK

| Tabel & kolom | FK |
|---|---|
| `N_SOP_Dokumen.Id_Versi_Aktif` | **tidak ada** |
| `N_SOP_Paket_Item.Id_Item_Versi_Aktif` | **tidak ada** |
| `N_SOP_Progress.Id_SOP_Item_Versi` | **tidak ada** |
| `N_SOP_Item_Versi_Berkas.Id_SOP_Item_Versi` | **tidak ada** |
| `N_SOP_Paket_Versi_Item.Id_SOP_Item_Versi` | **tidak ada** |
| `N_SOP_Penjadwalan_Integrasi.Id_SOP_Item_Versi` | **tidak ada** |
| `N_SOP_Coverage_Rollup.Id_SOP_Item_Versi` | **tidak ada** |
| `N_SOP_Item_Versi_Pengesah` | ada |
| `N_SOP_Item_Versi_Review` | ada |

Dua yang pertama adalah pointer yang **menentukan dokumen mana yang dibaca
karyawan**. Yang ketiga adalah catatan kepatuhan.

`N_SOP_Aktivitas_Log` dan `N_SOP_Log` juga tanpa FK — itu **benar dan
disengaja**: jejak audit harus bertahan walau barisnya hilang.

**Bobot: tinggi.** `hapusVersi()` memang memeriksa pointer dan progres sebelum
menghapus, tapi itu penjaga aplikasi pada satu pintu. Tak ada yang mencegah
pintu kedua.

### 3. Tak ada penegak "satu dokumen, satu versi berlaku"

Aturannya nyata dan ditulis sebagai komentar di `SopItemVersionService::publish()`
— *"Satu dokumen terkendali hanya boleh punya SATU versi PUBLISHED"* — tetapi
ditegakkan murni oleh urutan operasi di dalam satu transaksi: baca yang
`PUBLISHED`, jadikan `OBSOLETE`, lalu terbitkan yang baru.

Bacaannya `SELECT` biasa, **tanpa `lockForUpdate`**. Yang menyelamatkannya
sekarang adalah kebetulan isolasi: RCSI mati di `Web_HRIS`, jadi pembaca antre
di belakang penulis dan dua penerbitan bersamaan terserialkan. Bergantung pada
kebetulan itu punya dua harga:

- kalau RCSI suatu saat dinyalakan (masih terbuka, belum ditolak), penjaganya
  hilang tanpa satu baris kode pun berubah;
- selama mati, penerbitan yang berebut bisa gagal dengan *"Lock request time
  out"* dari katup `SET LOCK_TIMEOUT 10000` — pesan yang tak berarti apa-apa
  bagi admin.

**Bobot: tinggi.**

### 4. Penerbitan tidak atomik dari ujung ke ujung

`publish()` transaksional. Tetapi `terbitkanVersi()` mengerjakan tiga hal lagi
**di luar** transaksi mana pun, berurutan:

1. `N_SOP_Dokumen.Id_Versi_Aktif` digeser
2. `segarkanNomorResmi()` merakit ulang nomor resmi & menyebarkannya ke item
3. rekonsiliasi seluruh paket perujuk

Gagal di tengah meninggalkan keadaan yang tak dijaga siapa pun: versi sudah
`PUBLISHED` sementara pointer dokumen masih menunjuk revisi lama, atau nomor
resmi menyebut revisi yang bukan lagi yang berlaku, atau paket belum
direkonsiliasi. Pemeriksaan B1/B3 nol **hari ini** — tak ada yang mencegahnya
besok.

**Bobot: tinggi.**

### 5. Penomoran versi punya jendela balapan

`tambahVersi()` **tidak** transaksional. Urutannya: `nomorVersiBerikut()`
membaca `MAX(Versi_Ke)`, lalu `insertGetId()`. Dua admin menekan "Tambah Versi"
pada dokumen yang sama dalam jendela itu sama-sama membaca angka yang sama.

Indeks unik menangkapnya — tetapi sebagai galat mentah **2601**, yang naik
sebagai kesalahan 500, bukan sebagai pesan yang bisa dikerjakan admin.

**Bobot: sedang.** Jarang terjadi pada beban sekarang; murah diperbaiki.

### 6. Tak ada penjaga tulis-bersamaan pada draft

`ubahVersi()` tak memeriksa apakah baris berubah sejak dibaca — tak ada
`rowversion`, tak ada perbandingan `Updated_At`. Dua admin menyunting draft yang
sama: yang menyimpan belakangan menimpa yang pertama, **tanpa satu pun tanda**.

Wizard versi berumur panjang (lima langkah, berkas, matriks pengesah), jadi
jendelanya bukan hitungan detik.

**Bobot: sedang.**

### 7. Nomor versi masih dikonsumsi draft yang batal

Sudah dirancang dan dikodekan (`nomorVersiBerikut` menyaring versi hidup), tapi
**menunggu DDL-2026-062**. Selama indeks uniknya belum disempitkan, kode
menawarkan nomor yang basis data tolak.

**Bobot: sedang — sudah ada rencananya**, lihat
`2026-08-30-sop-versi-batal-beku-dan-nomor-dipakai-ulang-design.md`.

### 8. Keadaan versi disimpulkan ulang di setiap layar

`Status === 'DRAFT'` muncul di **15 tempat** pada berkas Vue modul SOP, masing-
masing sebagai penilaian sendiri atas "boleh dikerjakan atau tidak". Sebagian
sudah dipindah ke fakta dari server (`Boleh_Terbit`, `Dibatalkan`) pada 30 Agu
2026, sisanya belum.

Bentuk terburuknya sudah ditemukan: `isCancelled` di wizard adalah rantai tujuh
`OR` yang **seluruh cabangnya meleset** — membaca `Kode` padahal muatan
mengirim `Kode_Tahap`, mencari kata "batal" pada nama tahap yang berbunyi
"Cancel", dan mencari kelompok langkah pada tahap yang tak punya langkah.
Rantai panjang membuatnya *terlihat* berlapis, dan itulah kenapa tak ada yang
memeriksanya lagi.

**Bobot: sedang.** Gejalanya terus muncul selama jawabannya tidak tunggal.

### 9. Tipe selain DOKUMEN tak bisa terbit sama sekali — TERKONFIRMASI

Ditemukan lagi saat menjalankan suite, dua kali, lewat dua tipe berbeda. Dua
aturan yang masing-masing masuk akal bertabrakan:

1. Blok pengesahan hanya berlaku bagi tipe DOKUMEN —
   `pastikanFieldSesuaiTipe()` **menolak** `Disahkan_Oleh` pada KUIS/VIDEO/FORM.
2. Sejak DML-2026-048, masuk Pemberkasan menuntut `REALISASI_PENGESAH`, tanpa
   memandang tipe.

KUIS, VIDEO, dan FORM karena itu tak bisa memperoleh pengesah → tak bisa
mencapai Pemberkasan → tak bisa diterbitkan. Selamanya.

Dua uji lama yang membuktikannya (`versi kuis terbit dengan ujian bukan berkas`,
`versi dengan berkas salah jenis ditolak terbit`) kini **dilewati dengan pesan
yang menyebutkan jalan buntunya**, bukan ditambal — mengisi `Disahkan_Oleh` akan
ditolak aturan (1), dan menyetel `Tahap_Id` langsung akan membuat ujinya hijau
atas alur yang tak ada di kenyataan. Keduanya menyembunyikan cacatnya.

**Bobot: tinggi, tapi perbaikannya keputusan produk** — apakah
`REALISASI_PENGESAH` memang harus berlaku bagi tipe yang tak punya blok
pengesahan? Sudah tercatat sebelumnya sebagai persoalan tersendiri; audit ini
hanya menegaskannya dengan bukti yang dapat dijalankan ulang.

---

## Urutan yang saya sarankan

Bukan berdasarkan besarnya, melainkan berdasarkan **apa yang menghentikan cacat
berikutnya lahir**.

1. **Satu sumber keadaan versi** (temuan 1 & 8). Server menjawab
   `Dibatalkan` / `Boleh_Disunting` / `Boleh_Terbit` sebagai fakta; layar dan
   penjaga service membacanya, tak ada yang menyimpulkan sendiri. Ini
   memutus akar yang melahirkan sisanya.
2. **Penegak invarian di basis data** (temuan 2 & 3): FK pada tiga pointer yang
   menentukan apa yang dibaca karyawan, dan indeks unik terfilter untuk
   "satu dokumen, satu `PUBLISHED`". Penjaga yang tak bisa dilewati pintu
   kedua.
3. **Penerbitan atomik** (temuan 4): satu transaksi dari `publish()` sampai
   rekonsiliasi, dengan `lockForUpdate` pada baris dokumennya.
4. **Balapan penomoran** (temuan 5) — sekalian saat mengerjakan DDL-2026-062.
5. **Tulis-bersamaan** (temuan 6) — paling murah ditunda; dampaknya nyata tapi
   terbatas pada dua admin di satu draft.

## Yang TIDAK termasuk masalah

- **Log tanpa FK** (`N_SOP_Aktivitas_Log`, `N_SOP_Log`) — disengaja; jejak audit
  harus bertahan melampaui barisnya.
- **`OBSOLETE` tetap `Flag_Aktif='Y'`** — benar; nomor revisi yang pernah
  beredar harus terkunci selamanya.
- **Pembangkitan versi batal** — sudah tertutup: `CANCEL` terminal, `tujuanSah()`
  kosong dari sana, dan rute `rollbackVersi` sudah dicabut.
