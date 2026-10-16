# Papan Harian Visitor — monitoring kehadiran hari ini

- Tanggal: 2026-08-25
- Modul: `VISITOR` / submodul `Workflow` / fitur `Reporting`
- Basis data pengembangan: `Web_HRIS` @ team311
- Tanpa DDL, tanpa perubahan menu, tanpa kunci halaman baru

## Masalah

Admin tidak punya satu layar untuk menjawab: siapa yang seharusnya datang hari
ini, siapa yang sudah di dalam, siapa yang belum keluar, dan permit mana yang
kedaluwarsa hari ini.

## Keadaan yang ditemukan sebelum merancang

Empat pengukuran yang membentuk desain ini. Semuanya diambil dari `Web_HRIS`
pada 2026-08-25, bukan dari asumsi.

**1. Kolom kehadiran nol persen terisi.**

```
Waktu_Aktual_Masuk   : 0/7  (0%)     tabel efektif : 0/4 (0%)
Waktu_Aktual_Keluar  : 0/7  (0%)     tabel efektif : 0/4 (0%)
Status_Kunjungan     : TERJADWAL 4, MENUNGGU_APPROVAL 3
```

`CHECKED_IN` dan `CHECKED_OUT` tak pernah sekali pun terpakai.

**2. Check-in ada tetapi tidak dapat dijangkau.**
`GuestRegistrationController::checkIn()` dan `checkOut()` lengkap dan menulis
kolom itu, tetapi **tidak ada satu pun route VISITOR** yang menunjuk keduanya
dan tidak ada pemanggil di frontend. Itulah sebab angka nol di atas — bukan
sepi, melainkan mustahil.

**3. UI mengklaim kemampuan yang tak ada, di tiga tempat.** Spanduk halaman
Verifikator memasang lencana "Check-in / check-out", panel tamunya menyebut
"catatan jam check-in/check-out aktual", dan method controller-nya ada. Tak satu
pun dapat dieksekusi.

**4. Tab "Aktivitas harian" sudah merupakan papan ini.** `LaporanHarian.vue`
menampilkan Tipe, Nama, Plat, Asal, Tujuan, **Jam Masuk**, dan **Status Keluar**,
menggabungkan tamu dan permit, dan defaultnya sudah hari ini. Ia hanya kurang
tiga hal: tanpa aksi, tanpa pengelompokan keadaan, dan dua kolom terakhirnya
mustahil terisi.

**5. Permit tidak punya kolom masuk/keluar sama sekali** — tidak di detail,
header, maupun `N_VISITOR_Permit_Efektif`. Permit murni jendela masa berlaku.

## Keputusan

| Pertanyaan | Keputusan |
|---|---|
| Check-in yang belum tersambung | Disatukan ke pekerjaan ini; tombolnya tinggal di papan |
| Permit di papan | Masa berlaku saja; pencatatan kendaraan tidak dibangun |
| Hak menandai masuk/keluar | Sama dengan hak melihat papan (`visitorLaporanPage`) |
| Tab baru atau naikkan yang ada | **Naikkan "Aktivitas harian"**, bukan tab ketiga |

Menambah tab ketiga akan melahirkan dua tampilan harian yang nyaris kembar, dan
yang lama tetap menampilkan kolom yang tak pernah terisi.

## Rancangan

### 1. Isi papan

Dikelompokkan menurut keadaan, bukan tabel datar:

| Kelompok | Aturan | Berdata hari ini |
|---|---|---|
| Harus datang hari ini | `Tanggal_Kunjungan` = hari ini, disetujui, belum masuk | ✅ 2 |
| Sedang di dalam | `Waktu_Aktual_Masuk` terisi, `Waktu_Aktual_Keluar` kosong | setelah check-in hidup |
| Masih di dalam dari hari sebelumnya | sedang di dalam, tanggal kunjungan < hari ini | setelah check-in hidup |
| Sudah keluar hari ini | `Waktu_Aktual_Keluar` = hari ini | setelah check-in hidup |
| Permit kedaluwarsa hari ini / ≤7 hari | `Tanggal_Kadaluarsa` | ✅ |
| Permit disetujui tanpa tanggal | disetujui, `Tanggal_*` NULL | ✅ **1** |

"Masih di dalam dari hari sebelumnya" sengaja tidak dibatasi ke hari ini. Tamu
yang menginap tanpa check-out adalah baris paling penting di papan mana pun, dan
menyaringnya ke hari ini akan menyembunyikannya justru saat paling dibutuhkan.

Kelompok "permit disetujui tanpa tanggal" lahir dari pengukuran. Angkanya perlu
dibaca hati-hati: 4 dari 8 permit tidak punya tanggal, tetapi hanya **1** di
antaranya yang sudah disetujui. Tiga sisanya masih menunggu approval, jadi
belum bertanggal itu wajar dan bukan pekerjaan tertunda. Papan hanya memuat yang
sudah disetujui — permit yang sah tetapi tak pernah benar-benar berlaku.

### 2. Aksi masuk dan keluar

Tombol menempel pada baris, memanggil `checkIn()`/`checkOut()` yang sudah ada
lewat route baru, digerbangi `visitorLaporanPage`.

Dua penjaga baru — keduanya belum ada dan keduanya menghasilkan riwayat mustahil
bila dibiarkan:

- **Check-out menuntut sudah check-in.** `checkOut()` saat ini tidak memeriksa
  apa pun; ia akan menulis `Waktu_Aktual_Keluar` untuk tamu yang tak pernah
  masuk.
- **Check-in tidak boleh berulang.** Tanpa penjaga, check-in kedua menimpa
  `Waktu_Aktual_Masuk` dan jam masuk aslinya hilang tanpa jejak.

Penjaga yang sudah ada dipertahankan: tamu yang pengajuannya belum disetujui
tidak dapat check-in bila `check_in_requires_approval` menyala.

Label **"Read-only monitoring"** pada halaman Monitoring dicabut — sesudah
perubahan ini pernyataan itu tidak benar lagi.

### 3. Sisi server

| Berkas | Peran |
|---|---|
| `app/Services/VISITOR/Workflow/Reporting/Monitoring/VisitorPapanHarianService.php` | satu-satunya tempat pengelompokan dihitung |
| `LaporanVisitorController::getPapanHarian()` | endpoint `GET /laporanvisitor-papan-harian` |
| `routes/VISITOR/Guest/Visit/GuestRegistration.php` | route check-in dan check-out |

Tanpa DDL. Tanpa baris menu. Tanpa kunci halaman baru.

### 4. Analitik, dan batasnya

Empat angka: dijadwalkan hari ini, sudah masuk, masih di dalam, sudah keluar.

Yang **tidak** dibuat: rata-rata lama kunjungan, tren mingguan, jam tersibuk.
Semuanya dihitung dari kolom yang hari ini nol persen terisi; angkanya akan
tampak meyakinkan padahal karangan. Layak dikerjakan setelah check-in berjalan
dan datanya nyata — sebagai pekerjaan tersendiri.

### 5. Uji

| Yang diuji | Kenapa |
|---|---|
| Tiap kelompok diuji dengan baris yang MASUK dan yang TIDAK | Pengelompokan yang mengembalikan semuanya lolos bila hanya diuji satu sisi |
| Check-out tanpa check-in ditolak | Penjaga baru; wajib pernah terlihat merah |
| Check-in kedua ditolak dan jam masuk asli tidak berubah | Menimpa jam masuk adalah kehilangan data yang senyap |
| Tamu belum disetujui tak dapat check-in | Penjaga lama tetap berlaku |
| Route check-in benar-benar terdaftar | Persis celah yang membuat fitur ini mati diam-diam |
| Tamu menginap muncul walau tanggalnya bukan hari ini | Aturan yang paling mudah salah disaring |

## Sisa pekerjaan pengguna

Tidak ada langkah basis data. Setelah rilis, kolom kehadiran akan mulai terisi
untuk pertama kalinya; analitik lanjutan menunggu data itu terkumpul.
