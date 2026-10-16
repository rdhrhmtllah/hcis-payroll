# Otomasi Uji 48 Skenario UAT Modul SOP — Rancangan

**Tanggal:** 13 Agustus 2026
**Tujuan:** gladi bersih sebelum UAT sungguhan digelar
**Dokumen yang diuji:** `docs/uat/IT_BA_UAT SOP.docx` — lihat [rancangannya](2026-08-13-uat-sop-design.md)

---

## Latar Belakang

Dokumen UAT berisi 48 skenario yang akan dijalankan manusia dan diakhiri tanda
tangan. Yang dibangun di sini **bukan penggantinya** — UAT disebut UAT justru
karena ada orang bisnis yang menilai lalu meneken, dan itu tak bisa
diotomatiskan.

Yang dibangun adalah **gladi bersihnya**: menjalankan skenario yang sama secara
otomatis sebelum hari-H, supaya sesi UAT tidak habis untuk menemukan tombol
rusak. Tiap skenario yang sudah terbukti mesin adalah dua puluh menit waktu
Division Head yang tidak terbuang.

Efek sampingnya berumur panjang: dokumen UAT dipakai sekali, sedangkan suite ini
terus menangkap regresi tiap kali modul SOP disentuh.

## Keputusan yang Sudah Disepakati

1. **Cakupan penuh.** Seluruh 48 skenario dipetakan, bukan sebagian.
2. **Database dev yang sekarang dipakai apa adanya.** Tidak membuat salinan,
   tidak memindahkan server.
3. **Pembersihan lewat `DELETE` bersasar** pada tabel SOP, dibatasi ID yang
   tercatat di jurnal jalan-uji.
4. **Playwright** untuk lapis peramban, bukan Laravel Dusk — Inertia+Vue adalah
   SPA, dan Playwright menunggu transisi SPA secara otomatis sementara Dusk
   (berbasis Selenium) menuntut penantian manual yang rapuh.

## Kontrak Keselamatan

Ini bagian yang paling tidak boleh dikompromikan. Suite berjalan di database yang
dipakai orang bekerja.

**Boleh** — `DELETE` **hanya** pada tabel dalam daftar-putih di bawah, dan
**selalu** dengan `WHERE` yang membatasi pada ID di jurnal jalan-uji:

```
N_SOP_Dokumen                 N_SOP_Master_Paket            N_SOP_Penjadwalan
N_SOP_Item_Versi              N_SOP_Paket_Item              N_SOP_Penjadwalan_Detail
N_SOP_Item_Versi_Berkas       N_SOP_Paket_Section           N_SOP_Penjadwalan_Integrasi
N_SOP_Item_Versi_Pengesah     N_SOP_Paket_Versi             N_SOP_Progress
N_SOP_Kategori                N_SOP_Paket_Versi_Item        N_SOP_Coverage_Rollup
N_SOP_Favorit                 N_SOP_Paket_Draft             N_SOP_Kepatuhan_Riwayat
N_SOP_Absensi                 N_SOP_Paket_Draft_Item        N_SOP_Assignment_Rule
N_SOP_Log                     N_SOP_Paket_Draft_Item_Versi  N_SOP_Automation_Run
                              N_SOP_Paket_Draft_Section
```

Daftar ini **ditanam di dalam perintah pembersih**, bukan disusun saat jalan.
Pembersih menolak menyentuh nama tabel di luar daftar, dan pada jalan pertama
mencocokkannya dengan `INFORMATION_SCHEMA.TABLES` — beberapa nama `N_SOP_*` yang
muncul di kode ternyata indeks atau tetapan, bukan tabel, dan sebuah pembersih
yang menerima nama tabel dari hasil pemindaian kode adalah pembersih yang suatu
hari akan menghapus sesuatu yang tak pernah dimaksudkan.

**`N_SOP_Setting` sengaja TIDAK masuk daftar.** Skenario 025 mengubah setelan,
bukan membuatnya. Yang benar adalah mengembalikan nilai lamanya, bukan menghapus
barisnya — menghapus setelan meninggalkan modul tanpa konfigurasi.

### Dua tabel milik modul lain: dihindari, bukan dibersihkan

Penelusuran menemukan dua tempat di mana modul SOP menulis ke luar wilayahnya:

| Tabel | Pemilik | Ditulis saat |
|---|---|---|
| `N_LMS_Master_Konten` (+ `_Berkas`, `_Media`) | modul **LMS** | mengunggah berkas SOP |
| `N_HRIS_Notification` | seluruh **HCIS** | notifikasi & pengingat SOP |

Keduanya **tidak boleh masuk daftar-putih penghapusan.** Salah `WHERE` di tabel
LMS merusak materi pelatihan; salah `WHERE` di tabel notifikasi menghapus
pemberitahuan milik modul lain yang belum dibaca siapa pun.

Yang dipakai sebagai gantinya adalah menghindari menulis ke sana:

- **Test peramban tidak pernah mengunggah berkas.** Ia memilih konten yang sudah
  ada lewat pemilih Dokumen — yang memang perilaku sebenarnya di layar itu.
  Unggah berkas (skenario 005) diuji di backend, tempat `DatabaseTransactions`
  me-rollback-nya.
- **Notifikasi diuji di backend saja**, karena di sanalah ia ter-rollback. Test
  peramban yang kebetulan memicu notifikasi membiarkan barisnya — satu baris
  in-app di database dev. Tak ada pesan yang keluar dari sistem: notifikasi SOP
  seluruhnya `DELIVERY_IN_APP`.

**Dilarang, tanpa kecuali:**

- `TRUNCATE`, `DROP`, `ALTER`
- `php artisan migrate` dalam bentuk apa pun — termasuk `migrate:fresh`,
  `migrate:refresh`, `db:wipe`
- `DELETE` atau `UPDATE` tanpa `WHERE`
- menyentuh baris yang tidak dibuat oleh test itu sendiri

Alasan larangan migrasi bukan kehati-hatian umum, melainkan fakta yang terukur:
**skema tidak ada di repositori.** Hanya ada tiga migration, dua di antaranya
tabel `jobs` bawaan Laravel. Seluruh tabel `N_HRIS_*` dan `N_SOP_*` berikut
stored procedure dan fungsi yang dipanggil kode (`N_HRIS_FN_GetStatusHariRange`,
`HRIS_SP_GET_ABSEN_NEW_V`, dan lainnya) hidup hanya di dalam database. Menjalankan
migrasi tidak akan membangun ulang apa pun — ia hanya merusak, dan tak ada di
repositori ini yang bisa memulihkannya.

### Empat pengaman yang menegakkan kontrak itu

**1. `.env.uat` — env terpisah, database tetap sama.**
Menimpa `QUEUE_CONNECTION=sync`.

> **Koreksi.** Rancangan ini semula menyatakan pemutus tersebut mencegah test
> mengirim **WhatsApp sungguhan ke karyawan sungguhan**. Itu keliru, dan
> penelusuran membuktikannya: `app/Services/SOP/`, `app/Http/Controllers/Sop/`,
> dan `app/Jobs/Sop/` tidak memuat satu pun rujukan WhatsApp, dan
> `SopNotificationService` hanya memakai `DELIVERY_IN_APP`. Berkas yang menyebut
> "in-app + WhatsApp" pada penjadwalan massal adalah `ProcessBulkPenjadwalan`
> milik **LMS** — di-dispatch dari `LmsPenjadwalanTrainingController` — dan kode
> SOP justru menyebutnya sebagai cacat yang sengaja tidak ditiru
> (`SopPenjadwalanService:192`). Modul SOP memakai job sendiri,
> `App\Jobs\Sop\ProsesBulkAssignSop`, yang tidak mengirim apa pun keluar.

Alasan sebenarnya lebih membosankan tapi lebih menentukan: `.env` memakai
`QUEUE_CONNECTION=cloudtasks`, dan modul SOP punya empat pekerjaan berantre —
`ProsesBulkAssignSop`, `ProsesReconcileSop`, `ApplySopAssignmentRule`,
`RunSopAutomation`. Penyemaian progres penugasan lewat antrean begitu jumlah
sasaran melewati ambang tertentu.

Tanpa `sync`, test peramban akan menerbitkan paket dan membuat penjadwalan, lalu
**tidak menemukan apa-apa** saat memeriksa SOP Saya — bukan karena aplikasinya
salah, melainkan karena pekerjaannya menunggu di Cloud Tasks. Kegagalan itu
terlihat persis seperti bug fitur, dan akan dikejar sebagai bug fitur.

Skenario 020 tetap boleh menyasar unit sungguhan: barisnya tercatat di jurnal dan
bisa dibersihkan, dan tidak ada apa pun yang keluar dari sistem untuk dibersihkan.

**2. Gerbang sengaja.**
Suite menolak berjalan tanpa `UAT_AUTO_ALLOW=1`. `php artisan test` biasa tidak
akan pernah memicunya secara tak sengaja.

**3. Penanda + jurnal jalan-uji.**
Tiap baris yang dibuat berawalan `UAT-AUTO-<id-jalan>` pada kolom judul/nomornya,
dan **tiap ID yang dibuat dicatat ke jurnal** (`storage/app/uat-auto/<id>.json`).

Pembersihan bersandar pada jurnal, bukan pada pencocokan nama. Menghapus
berdasarkan awalan nama terasa cukup sampai suatu hari ada dokumen sungguhan
bernama awalan sama, atau sampai sebuah test gagal setelah membuat baris tapi
sebelum menamainya.

**4. Perintah pembersih berdiri sendiri.**
`php artisan sop:uat-bersihkan {id-jalan}` menghapus tepat yang dibuat satu jalan
tertentu, dan `--semua` untuk seluruh jurnal yang tertinggal. Dipanggil otomatis
di akhir suite, tapi **harus bisa dijalankan manual** — jalan yang mati di tengah
(listrik padam, CTRL-C) meninggalkan jurnal tanpa pembersihnya, dan itu keadaan
yang paling butuh perintah ini.

**Aturan yang mengikat seluruh test:** test tidak pernah mengubah dokumen atau
paket yang sudah ada. Ia membuat miliknya sendiri, memakainya, lalu membuangnya.

---

## Arsitektur

```
tests/Feature/LMS/SOP/Uat/          PHPUnit — 39 skenario, DatabaseTransactions
tests/e2e/                  Playwright — 6 skenario layar
app/Console/Commands/       sop:uat-bersihkan, sop:uat-laporan
storage/app/uat-auto/       jurnal jalan-uji (tidak di-commit)
.env.uat                    env pemutus jalur keluar
```

**Lapis backend** memakai `DatabaseTransactions`, seperti 33 test yang sudah ada
di repo ini — rollback otomatis, jurnal tak terpakai. Jurnal dan pembersih
dibutuhkan **hanya oleh lapis peramban**, karena browser memukul server lewat
HTTP di proses lain sehingga tak ada transaksi yang bisa di-rollback.

**Penandaan skenario.** Tiap test backend diberi atribut PHPUnit
`#[Group('UAT-SOP-013')]`; tiap test Playwright diberi tag `@UAT-SOP-008` pada
judulnya. Dua-duanya bisa disaring per skenario, dan dua-duanya terbaca oleh
pembangkit laporan.

---

## Pemetaan 48 Skenario

Angka di bawah adalah hasil penelusuran satu per satu, dan **berbeda dari
perkiraan kasar yang saya sebut lebih awal** (±34/±8/±6). Yang benar:

| Lapis | Jumlah |
|---|---|
| Backend (PHPUnit) | **39** |
| Peramban (Playwright) | **6** |
| Campuran — backend membuktikan sebagian, sisanya tetap manusia | **3** |

### Backend — 39 skenario

`001` `002` `003` `004` `005` `006` `007` `010` `011` `012` `013` `014` `015`
`017` `018` `020` `021` `022` `023` `024` `025` `026` `027` `028` `029` `030`
`033` `035` `037` `038` `039` `040` `041` `042` `043` `044` `045` `046` `048`

Catatan pada beberapa di antaranya:

- **002** — yang dibuktikan adalah balasan 403 pada ketujuh alamat admin.
  "Menunya tidak muncul" diverifikasi lewat data sidebar pada respons Inertia,
  bukan lewat piksel.
- **005** — unggah berkas memakai disk palsu (`Storage::fake`), bukan Google
  Cloud Storage. Yang diuji perilaku aplikasi, bukan GCS.
- **022** — inti skenario ini justru cocok untuk mesin: bandingkan jumlah di
  pratinjau, jumlah lintas seluruh halaman, dan jumlah penugasan yang benar-benar
  terbentuk. Manusia sulit menghitung ratusan baris tanpa keliru.
- **042** — berkas unduhan benar-benar diurai dan isinya dibandingkan dengan
  data sumber, bukan sekadar dicek "ada berkasnya".
- **044** dan **048** — keduanya membutuhkan antrean; `.env.uat` menyetelnya
  `sync` sehingga berjalan langsung tanpa pekerja.

### Peramban — 6 skenario

| ID | Kenapa harus peramban |
|---|---|
| `008` | Layar dua-tahap: riwayat harus tergantikan formulir, bukan bertumpuk. Hanya terlihat di DOM sungguhan. |
| `009` | Konfirmasi saat meninggalkan formulir terisi — perilaku dialog, bukan endpoint. |
| `016` | Mengubah urutan dengan menyeret. API `reorder` bisa diuji backend, tapi yang dijanjikan skenario adalah seretnya. |
| `019` | Pratinjau paket "persis seperti yang dilihat karyawan" — pernyataan tentang render. |
| `031` | Pembaca PDF kembali ke halaman terakhir. Butuh peramban sungguhan; lihat catatan di bawah. |
| `032` | Tombol konfirmasi terkunci sampai dokumen terbaca tuntas. Endpoint-nya juga diuji backend, tapi terkuncinya tombol adalah janji antarmuka. |

### Campuran — 3 skenario

| ID | Bagian yang mesin bisa | Bagian yang tetap manusia |
|---|---|---|
| `034` | callback nilai masuk dan tercatat pada progres | sistem kuis eksternalnya sendiri |
| `036` | endpoint check-in menerima dan mencatat waktunya | pengambilan wajah lewat kamera |
| `047` | baris notifikasi dan pekerjaan pengingat terbentuk dengan isi yang benar | pesannya benar-benar sampai ke ponsel |

Ditambah satu sisa manual di dalam `031`: **"berkas tidak dapat diunduh maupun
dicetak"**. Ketiadaan tombol unduh bisa dipastikan mesin, tapi "tidak bisa
dicetak" adalah pernyataan tentang peramban dan sistem operasi penguji — bukan
tentang aplikasi ini. Itu tetap diverifikasi manusia.

**Konsekuensi yang harus diterima sejak awal: laporan ini tidak akan pernah
menunjukkan 48/48 hijau.** Angka penuhnya adalah **45 otomatis + 3 campuran**,
dengan empat sisa manual yang dicantumkan sebagai `MANUAL`, bukan sebagai
kegagalan. Laporan yang berpura-pura bisa menguji kamera akan berbohong.

---

## Laporan Kesiapan

`php artisan sop:uat-laporan` membaca keluaran JUnit XML dari PHPUnit dan JSON
dari Playwright, lalu menghasilkan tabel seluruh 48 skenario:

| Status | Arti |
|---|---|
| `PASS` | terbukti otomatis |
| `FAIL` | terbukti gagal — perbaiki sebelum mengundang orang |
| `MANUAL` | memang tak bisa diotomatiskan; wajib dijalankan manusia saat sesi |
| `BELUM` | testnya belum ditulis |

Keluarannya markdown di `plans/sop/uat-kesiapan-<tanggal>.md`.

**Dokumen UAT tidak ditimpa.** Kolom Hasil Aktual dan Status di dalamnya tetap
hak penguji manusia untuk mengisi saat sesi; laporan ini adalah berkas terpisah
yang dibaca sebelum sesi. Mengisi kolom itu otomatis akan mengubah dokumen
kesaksian menjadi keluaran mesin, dan tanda tangan di bawahnya jadi menjamin
sesuatu yang tak pernah dilihat penandatangannya.

---

## Urutan Pengerjaan

| Tahap | Isi | Kenapa urutannya begini |
|---|---|---|
| 0 | `.env.uat`, gerbang `UAT_AUTO_ALLOW`, jurnal, `sop:uat-bersihkan` | Tanpa ini, tahap mana pun yang menulis data berbahaya |
| 1 | 14 skenario kontrol | Yang di Acceptance Criteria tak bisa ditawar; juga paling murah karena hampir semuanya backend |
| 2 | 25 skenario backend sisanya | |
| 3 | Playwright + 6 skenario layar | Butuh tahap 0 sudah kokoh, karena inilah yang meninggalkan baris sungguhan |
| 4 | `sop:uat-laporan` | Baru berguna setelah ada yang dilaporkan |

Tiap tahap berdiri sendiri dan bisa dihentikan tanpa merusak yang sudah jadi.

---

## Yang TIDAK Dikerjakan

- **Tidak membuat database uji terpisah.** Keputusan Anda; kontrak keselamatan di
  atas yang menggantikan perannya.
- **Tidak menyentuh skema.** Tidak ada migration baru, tidak ada perubahan tabel.
- **Tidak mengubah dokumen UAT.** Laporan kesiapan adalah berkas terpisah.
- **Tidak menguji modul HCIS lain.** Hanya 48 skenario di dokumen UAT SOP.
- **Tidak menambal `24A_sop_menu_akses_dml.sql`.** Suite akan gagal dengan pesan
  yang menyebut hak akses mana yang kurang, mengikuti pola `markTestSkipped` yang
  sudah dipakai `SopMasterDokumenPageTest` — bukan merah tanpa penjelasan.
