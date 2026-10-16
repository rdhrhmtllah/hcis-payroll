# Peta kerja Performance Appraisal — per fitur, per lapisan

Dibuat 1 September 2026 setelah pemilik proyek menegur pola kerja yang melompat-lompat.

> "kita kan awalnya sedang mendesign dari Arsitektur database dulu, lalu tiba-tiba kita
> melompat-lompat, nah harusnya kita bertahap dulu dari arsitektur database, baru buat
> variabel, service, helper, dll, lalu baru buat controller — dan itu pun harus per
> fitur atau tertata dan rapi, biar tidak ada satu pun yang terlewat."

Teguran itu benar. Pengerjaan sebelumnya mengikuti **urutan permintaan**, bukan urutan
lapisan, dan akibatnya terukur: empat dari sepuluh operasi yang sudah punya gerbang
ternyata **tidak punya jalur kode sama sekali**. Tak satu pun ketahuan sampai peta ini
dibuat.

---

## 1. Urutan lapisan — dikerjakan dari atas ke bawah, per fitur

| # | Lapisan | Selesai berarti |
|---|---|---|
| **L0** | Arsitektur database | DDL + DML dijalankan, dokumen tabel ada, ledger terisi, ERD selaras |
| **L1** | Katalog & konstanta | Nilai tetap, kode status, katalog master — di kode, bukan tersebar sebagai literal |
| **L2** | Service, helper, penjaga | Seluruh aturan bisnis. Tidak ada aturan yang hidup di controller |
| **L3** | Uji service | Tiap penjaga pernah terlihat **MERAH** sebelum dinyatakan menjaga |
| **L4** | Controller, rute, validasi | Tipis. Menerjemahkan permintaan dan penolakan, tidak lebih |
| **L5** | Kontrak API, fixture, paApi | Bentuk respons tertulis sebelum frontend menebaknya |
| **L6** | Akses: kunci halaman, menu, DML | Tanpa ini halamannya 403 untuk semua orang |
| **L7** | Uji kontrak | Fixture dijaga terhadap respons sungguhan, supaya tak basi |
| **L8** | Halaman | Jalur frontend. Bukan pekerjaan backend |

**Aturannya: satu fitur diselesaikan sampai L7 sebelum fitur berikutnya dimulai.**
Melompat ke L4 pada fitur B sementara fitur A baru sampai L2 adalah persis yang
menghasilkan empat lubang di bawah — tak ada yang salah pada tiap langkahnya, hanya tak
ada yang memeriksa apa yang belum dikerjakan.

Yang boleh mendahului: **L0 seluruh modul** boleh diselesaikan lebih dulu untuk semua
fitur sekaligus, karena tabel saling merujuk dan memecahnya menghasilkan urutan
ketergantungan yang berbelit.

---

## 2. Keadaan sekarang

Tanda: **✔** selesai · **◐** sebagian · **✖** belum ada · **✍** ditulis, belum dijalankan
· **—** memang tidak berlaku

| # | Fitur | L0 | L1 | L2 | L3 | L4 | L5 | L6 | L7 |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| 1 | Siklus & penyiapan peserta | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✍ |
| 2 | Aturan, salinan, resolver | ✔ | ✔ | ✔ | ✔ | — | — | — | — |
| 3 | Override per peserta (K32) | ✔ | ✔ | ✔ | ✖ | ✔ | ✔ | — | ✖ |
| 4 | Penugasan penilai | ✔ | ✔ | ✔ | ◐ | ✔ | ✔ | ✔ | ✖ |
| 5 | Integrasi HC Learn — **ARAH MASUK saja** | ✔ | ✔ | ✔ | ◐ | ✔ | ◐ | ✔ | ✖ |
| 6 | Perhitungan hasil | ✔ | ✔ | ✔ | ◐ | ✔ | ✔ | ✔ | ✍ |
| 7 | Kalibrasi | ✔ | ✔ | ✔ | **✖** | ✔ | ✔ | ✔ | ✍ |
| 8 | Publikasi hasil | ✔ | ✔ | ✔ | ✍ | ✔ | ✔ | ✔ | ✖ |
| 9 | **Bobot pilar per siklus (K23)** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 10 | **Sunting peserta manual** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 11 | Laporan & HAV Map | ✔ | ✔ | ✔ | **✖** | ✔ | ✔ | ✔ | ✍ |
| 12 | Master data admin | ✔ | ✔ | ✔ | **✖** | ✔ | ✔ | ✔ | ✖ |
| 13 | **Dashboard peserta** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 14 | **Daftar assignment peserta** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 15 | **Riwayat penilaian peserta** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 16 | **Dashboard admin** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 17 | **Monitoring progress** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 18 | **Distribusi performance** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 19 | **Riwayat penilaian admin** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 20 | **Pemberitahuan in-app (B9)** | — | ✔ | ✔ | ✔ | — | — | — | — |
| 21 | **Setup siklus (A09)** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 22 | **Lini masa audit (A12)** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |
| 23 | **Dashboard & detail tim leader (L01–L02)** | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✖ |

> **Fitur 5 dipersempit namanya pada 2 September 2026, dan itu koreksi bukan kosmetik.**
> Yang lengkap hanya arah MASUK: callback HC Learn diterima, dipetakan, dijaga uji.
> Arah KELUAR — mengirim penugasan ke HC Learn lalu menyimpan tokennya di
> `N_PA_Assignment_Integration` — **tidak punya satu pun penulis di jalur produksi**;
> yang mengisinya hanya perintah data contoh. Akibatnya tak seorang penilai pun bisa
> menerima tautan formulirnya, dan seluruh sisi pengisian PA belum pernah berjalan di
> luar data contoh.
>
> Sebelum ini barisnya bernama "Integrasi HC Learn" dan terbaca selesai — audit yang
> menghitung setengah rantai sebagai rantai utuh lebih berbahaya daripada audit yang
> tidak ada, karena ia memberi rasa aman.
>
> Arah keluar menunggu `docs/superpowers/specs/2026-09-01-kontrak-hc-learn-pa-siap-kirim.md`,
> yang **belum dikirim** — ditahan sengaja atas keputusan pemilik proyek, dan pemicu
> pengirimannya sudah menyala pada 2 September 2026.

---

## 2c. Pembanding bentuk respons kini satu tempat — 1 September 2026

`KontrakLaporanTest` lahir terpisah dari `KontrakResponsTest` karena bahan ujinya berbeda
sifat: yang lama menyiapkan siklus lewat `siapkan` yang menyentuh seluruh karyawan aktif
dan memakan menit-menit, yang baru cukup membangun segelintir baris yang bentuknya
lengkap.

Logika perbandingannya diangkat ke trait `Support\MembandingkanBentukRespons` — dua
salinan pembanding adalah dua hal yang bisa berselisih, dan yang berselisih di situ justru
penjaga yang seharusnya mencegah perselisihan.

`KontrakResponsTest` sempat memegang salinan privatnya; **dialihkan 1 September 2026**,
sehingga kini hanya ada satu pembanding di seluruh modul.

---

## 2b. Tujuh fitur yang baru ketahuan dari mockup — 1 September 2026

Dua belas fitur di atas diturunkan dari **operasi gerbang** `PaCycleGate`. Sesudah mockup
akhirnya bisa dibaca, ketahuan bahwa peta itu melewatkan tujuh layar — dan **seluruh sisi
peserta** ada di antaranya.

| Layar mockup | Keadaan |
|---|---|
| Dashboard Saya | enam penghitung (termasuk **Terlambat**), cincin progres, tenggat terdekat |
| Daftar Assignment | tabel penugasan tempat ia menjadi PENILAI, dengan tenggat & status |
| Hasil Performance Saya | ◐ ada endpointnya, tetapi belum ada penyempitan "milik saya" |
| Riwayat Penilaian (peserta) | tren empat periode + tabel per periode |
| Dashboard Admin | total karyawan, cycle aktif, completion rate, progres per divisi |
| Monitoring Progress | **heatmap per departemen**, daftar overdue, saringan lima dimensi |
| Distribusi Performance | **kurva normal per tingkat jabatan** (mean & standar deviasi), bell curve, distribusi grade |
| Riwayat Penilaian (admin) | rekap seluruh karyawan lintas periode + tren |

**Tidak satu pun butuh tabel baru** — seluruhnya bisa dihitung dari skema yang ada. Yang
belum ada adalah service, endpoint, dan kontraknya.

Pelajarannya: peta yang bersumber dari kode hanya bisa menemukan yang **sudah terpikir**.
Cakupan terhadap mockup harus diukur terhadap mockup.

---

## 2d. Lubang yang tidak terlihat dari peta mana pun — 1 September 2026

Sesudah keduapuluh fitur layar terdaftar dan `pa:audit` menyentuh 100%, modul ini masih
**tidak mengirim satu pun pemberitahuan**. Nol berkas menyentuh `N_HRIS_Notification`.

Ia tidak muncul di peta karena peta ini disusun dari layar dan operasi gerbang, dan
pemberitahuan bukan keduanya. Tetapi tanpanya, seluruh yang di atas berdiri pada asumsi
yang tak pernah dipenuhi: penilai dianggap tahu ia punya tugas. Layar "terlambat" yang
menghitung hari dengan cermat kehilangan artinya kalau orangnya tidak pernah diberi tahu
sejak awal.

Dibangun sebagai fitur 20. Tiga aturan yang menyertainya, semuanya sudah pernah memakan
korban di modul lain:

1. `Module` wajib `'KPI'` — `'PA'` diam-diam berubah menjadi `'HCIS'`.
2. `source` wajib `'MAIN_NOTIF'` — nilai lain tersimpan tetapi tak pernah terlihat.
3. Dikirim **sesudah commit**, tidak pernah di dalam transaksi.

---

## 2e. Siklus penuh dijalankan — 1 September 2026

Pertama kalinya modul ini dijalankan pada skala sebenarnya, bukan diekstrapolasi dari
pengukuran satu orang.

### Terbit massal

| | |
|---|---|
| Diterbitkan | **859** hasil (99 `DIKECUALIKAN` memang tidak ikut) |
| Durasi | 15,26 detik |
| Kueri | **19** — 0,022 per hasil |
| Pemberitahuan | 859, rasio tepat 1,0 per hasil |
| Insert notifikasi | dipotong jadi 8 pernyataan, binding terbesar **1.989** dari batas SQL Server 2.100 |

Batas 2.100 parameter kini **terkonfirmasi pada beban sungguhan**, bukan dibaca dari kode.

### Perhitungan 859 peserta

```
15,7 menit — 1,10 detik/orang — 0 dihitung, 859 tertahan, 0 gagal
```

Seluruhnya tertahan karena dev tidak punya KPI disetujui untuk mereka; itu perilaku yang
benar, dan yang diukur di sini memang jalurnya, bukan angkanya.

### Ekstrapolasi lama meleset 2,2× — dan sebabnya BUKAN kode

Catatan sebelumnya menyebut 0,51 detik/orang. Angka barunya 1,10. Yang berubah bukan
jumlah kueri melainkan tautannya:

```
perjalanan bolak-balik ke DB dev:  72 ms  (saat garis dasar dibuat)
                                  147 ms  (1 September 2026)

8,0 kueri x 147 ms = 1,17 dtk/orang   ≈ 1,10 yang terukur
8,0 kueri x  72 ms = 0,58 dtk/orang   = garis dasar lama
```

**Pelajarannya: 0,51 detik/orang tidak pernah menjadi sifat kodenya.** Ia sifat kode DAN
tautannya, dan menuliskannya sebagai satu angka menyembunyikan separuh yang bisa berubah
tanpa satu baris pun disunting. Yang layak dicatat adalah **~8 kueri per peserta**; waktu
dindingnya selalu angka itu dikali kelambatan tautan di lingkungan yang bersangkutan.

Konsekuensi praktis: di production yang basis datanya sedatacenter (misal 10 ms), siklus
yang sama selesai sekitar **satu menit**, bukan enam belas. Ukur di sana, jangan bawa
angka dev.

### Dua jalur, dua ongkos — dan yang terukur di atas adalah yang MURAH

| Jalur | Kueri/peserta | Kapan |
|---|---|---|
| Pendek (tertahan) | **8,0** | KPI tidak ada; berhenti awal |
| Penuh (terhitung) | **15,5** | KPI + kompetensi + kuadran |

Seluruh 859 peserta pada jalan di atas menempuh jalur **pendek**, karena dev tidak punya
KPI disetujui untuk mereka. Siklus production yang sebagian besar pesertanya memang punya
KPI akan menempuh jalur penuh, **hampir dua kali lipat ongkosnya**:

```
859 peserta x 15,5 kueri x 147 ms (dev)  ~ 33 menit
859 peserta x 15,5 kueri x  10 ms (prod) ~  2,2 menit
```

Jadi angka "15,7 menit" di atas **jangan dipakai merencanakan production** — ia jalur
murah pada tautan lambat. Yang layak dibawa: 15,5 kueri per peserta, dikali latensi
lingkungan yang bersangkutan.

Perhitungannya berpotongan dan bisa dilanjutkan (`--potongan`, `--mulai-dari`), jadi
lamanya bukan risiko kehilangan pekerjaan.

---

## 3. Empat lubang yang baru ketahuan

Semuanya sudah punya gerbang di `PaCycleGate` — artinya arsitekturnya **sudah
memutuskan operasi itu ada**, tetapi tidak ada satu baris pun yang menjalankannya.

| Operasi gerbang | Dipakai berapa service | Akibatnya |
|---|:--:|---|
| ~~`TERBITKAN`~~ | **selesai 1 Sep** | Hasil bisa dihitung tetapi **tidak pernah bisa diterbitkan**. `N_PA_Result.Published_At/By` dan `N_PA_Cycle.Published_At/By` tak pernah ditulis siapa pun. Karyawan tidak akan pernah melihat hasilnya |
| `TULIS_OVERRIDE` | **0** | Tiga tabel override hanya punya PEMBACA (resolver). Tak seorang pun bisa membuat penyimpangan. Seluruh rancangan sparse K32 — yang dibela panjang lebar saat desain — belum pernah bisa dipakai |
| `SUNTING_PESERTA` | **0** | Peserta hanya lahir dari `siapkan()`. Admin tidak bisa mengecualikan atau mengikutkan satu orang secara manual |
| `SUNTING_BOBOT_PILAR` | **0** | K23 menetapkan bobot pilar sengaja TIDAK beku saat penguncian — justru supaya bisa disunting sesudahnya. Penyuntingnya tidak ada. Yang ada hanya master lintas siklus |

Keempatnya bukan pekerjaan yang "belum sempat": keputusan arsitekturalnya sudah diambil,
gerbangnya sudah dibangun dan diuji, hanya jalurnya yang tidak pernah ditulis.

---

## 4. Yang mengejar ketertinggalan lebih dulu

Urutannya menutup lubang terdalam lebih dulu, bukan yang paling mudah.

1. **L3 untuk yang sudah terlanjur jalan.** Menjalankan 71 uji yang sudah ada, lalu
   menulis uji untuk kalibrasi, laporan, dan master data — tiga fitur yang sudah sampai
   L4–L6 tanpa satu pun uji service. Ini bukan langkah mundur: tiga fitur itu sudah
   dipakai frontend lewat kontrak, dan kesalahannya akan mahal.
2. **Fitur 8 — Publikasi hasil.** L2 sampai L7. Tanpa ini modulnya tidak menghasilkan
   apa pun bagi karyawan, dan itu tujuan seluruh modul.
3. **Fitur 3 — Override per peserta.** L2 sampai L7. Sudah punya tabel, resolver, dan
   penjaga satu-pintu; tinggal jalur tulisnya.
4. ~~**Fitur 9 dan 10.**~~ **Selesai 1 September 2026.** `PaCyclePillarWeightService` dan
   `PaParticipantEditService`, empat alamat, kontrak §2.15–§2.16, `CyclePillarWeightTest`
   (10 uji) dan `ParticipantEditTest` (8 uji), uji kontrak di `KontrakLaporanTest`.
   **Alarm "operasi gerbang tanpa jalur kode" kini bersih** — tidak ada lagi operasi yang arsitekturnya putuskan ada tetapi tak
   dijalankan satu baris pun. Uji kontrak (L7) untuk keduanya belum ditulis.
5. ~~**Fitur 13–15 — seluruh sisi peserta.**~~ **Selesai 1 September 2026** sampai L7.
   Satu service `PaPesertaService`, empat alamat `/saya/*`, kontrak §3, `PesertaTest`
   (13 uji) dan empat uji kontrak. Aksesnya lewat `Is_Global_Menu='Y'` (DML-2026-086),
   bukan 958 baris `HRIS_Page_Access` — halaman Vue-nya milik jalur FE.
6. ~~**Fitur 16–19.**~~ **Selesai 1 September 2026** sampai L6. Tiga service
   (`PaAdminDashboardService` untuk 16 **dan** 17, `PaDistribusiService`,
   `PaRiwayatService`), empat alamat baca di `PaReportController`, kontrak §2.17–§2.20,
   `AdminDashboardTest` (10 uji), `DistribusiTest` (7), `RiwayatTest` (8). Seluruhnya
   berbasis himpunan — 8 dan 6 kueri per halaman, tanpa satu pun kueri di dalam
   perulangan. **Uji kontrak (L7) ditulis di `KontrakLaporanTest`** bersama fitur 9 dan
   10; halaman Vue (L8) milik jalur FE.

Selama keempatnya dikerjakan, **frontend tidak terhalang**: kontrak v1, v1.2, dan v2
sudah lengkap untuk dua belas layar yang sedang dibangun.

---

## 5. Aturan yang berlaku mulai sekarang

1. Satu fitur diselesaikan **L0 → L7** sebelum fitur berikutnya dimulai.
2. Permintaan yang datang di tengah **ditulis di antrean papan kerja**, tidak langsung
   dikerjakan — kecuali pemilik proyek menyatakan itu mendahului.
3. Peta ini diperbarui **sesudah** tiap lapisan selesai, bukan sebelum.
4. Fitur tidak boleh dinyatakan selesai selama masih ada operasi gerbang miliknya yang
   nol pemakai. Pemeriksaannya satu perintah:

   ```
   grep -rl "PaCycleGate::<OPERASI>" app/ | grep -v PaCycleGate.php
   ```
