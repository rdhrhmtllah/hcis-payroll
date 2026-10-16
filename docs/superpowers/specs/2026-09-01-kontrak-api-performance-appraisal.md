# Kontrak API Performance Appraisal — batas antara BE dan FE

Dokumen ini adalah **satu-satunya** rujukan frontend untuk modul PA. Frontend tidak
membaca controller, service, atau tabel; frontend membaca berkas ini.

Aturannya dua arah:

- **Backend** tidak boleh mengubah bentuk respons tanpa mengubah dokumen ini lebih dulu,
  lengkap dengan barisnya di *Riwayat perubahan kontrak* di bawah.
- **Frontend** tidak boleh menuntut bentuk baru dengan menebak dari kode backend.
  Permintaannya ditulis di papan kerja (`plans/kpi/pa-papan-kerja.md`), bukan
  dikerjakan sendiri.

Tanpa dua aturan itu, dua orang yang bekerja paralel akan sama-sama benar menurut
kodenya masing-masing dan tetap tidak nyambung saat digabungkan.

---

## 0. Ringkasan

| Hal | Nilai |
|---|---|
| Prefiks | `/api/v1/performance-appraisal` |
| Halaman | `GET /performance-appraisal` (Inertia) |
| Komponen halaman | `resources/js/Pages/KPI/PerformanceAppraisal/Cycle/siklus.vue` |
| Kunci akses | `PerformanceAppraisalPage` |
| Middleware | `auth`, `check.jabatan:PerformanceAppraisalPage` |
| Status kontrak | **v1 — berlaku**, per 1 September 2026 |

---

## 0b. Seam — frontend tidak memanggil HTTP sendiri

| Berkas | Pemilik | Isi |
|---|---|---|
| `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js` | **BE** | satu-satunya tempat yang bicara HTTP |
| `.../api/fixtures/*.json` | **BE** | data boneka, bentuknya **persis** respons asli |
| Halaman & komponen | **FE** | hanya memanggil `paApi`, tidak pernah `axios` |

```js
import paApi from '../api/paApi.js';

const { siklus } = await paApi.daftarSiklus();
```

`paApi.MODE` bernilai `dummy` secara baku: mengembalikan fixture, tanpa jaringan dan
tanpa database. Beralih ke data sungguhan cukup dengan `VITE_PA_API_MODE=live` di
`.env` — tidak ada baris halaman yang perlu disentuh.

Galat sudah diterjemahkan menjadi `PaApiError` dengan `message` yang **boleh
ditampilkan apa adanya** dan `status` untuk bercabang.

**Fixture-nya bukan karangan.** `tests/Feature/KPI/PerformanceAppraisal/KontrakResponsTest.php`
memanggil endpoint sungguhan lalu membandingkan susunan kuncinya dengan fixture. Kunci
yang hilang maupun kunci yang bertambah sama-sama membuatnya merah — jadi fixture tidak
bisa basi tanpa ada yang tahu.

---

## 1. Bentuk respons

Seluruh endpoint memakai `ResponseHelper`. **Isi ada di `result`, bukan `data`.**

Berhasil:

```json
{ "success": true, "status": 200, "message": "Berhasil", "result": { } }
```

Gagal:

```json
{ "success": false, "status": 422, "message": "kalimat yang boleh ditampilkan apa adanya" }
```

**`message` pada 422 ditulis untuk dibaca manusia** dan boleh ditampilkan apa adanya ke
pengguna. Ini bukan kelonggaran: pesan seperti "Kategori PEER tidak mengizinkan penilai
manual menurut aturan levelnya" adalah satu-satunya keterangan yang menjelaskan kenapa
tombolnya ditolak. Menggantinya dengan "terjadi kesalahan" membuang informasinya.

| Kode | Arti | Yang harus dilakukan layar |
|---|---|---|
| 200 | berhasil | tampilkan hasil |
| 422 | **aturan** yang menolak, bukan sistem yang rusak | tampilkan `message` apa adanya |
| 403 | user tidak punya `PerformanceAppraisalPage` | jangan tangani; halamannya memang tidak boleh dibuka |
| 500 | sistem rusak | pesan umum, jangan tampilkan detail |

Validasi Laravel (`422` dengan `errors`) hanya muncul pada endpoint yang menerima body.

---

## 2. Daftar endpoint

### 2.1 `GET /cycles` — daftar siklus

Tanpa parameter. Tidak berhalaman: siklus berjumlah beberapa per tahun, bukan ribuan.

```json
{
  "result": {
    "siklus": [
      {
        "Id_PA_Cycle": 1,
        "Kode_Cycle": "PA-2026",
        "Nama_Cycle": "Penilaian Kinerja 2026",
        "Tahun": 2026,
        "Status_Cycle": "PREPARED",
        "Periode_Dinilai_Dari": "2026-01-01",
        "Periode_Dinilai_Sampai": "2026-06-30",
        "Pengisian_Dari": null,
        "Pengisian_Sampai": null,
        "Locked_At": null,
        "Published_At": null,
        "peserta": { "diikutkan": 900, "dikecualikan": 51, "tertunda": 7 },
        "operasi_boleh": ["SUNTING_SALINAN", "SUNTING_PESERTA", "BUAT_PENUGASAN", "..."]
      }
    ]
  }
}
```

**`operasi_boleh` adalah kontrak terpenting di halaman ini.** Layar TIDAK boleh
menyimpulkan sendiri tombol mana yang hidup dari `Status_Cycle`. Matriks status→operasi
tinggal di `PaCycleGate` dan ditegakkan saat tombolnya ditekan; menyalinnya ke frontend
melahirkan pintu kedua yang harus dijaga sama persis, dan begitu keduanya berselisih
tombolnya tampak hidup lalu ditolak.

Nama operasi yang mungkin muncul:

| Operasi | Tombol yang seharusnya hidup |
|---|---|
| `SUNTING_SALINAN` | menyunting salinan aturan siklus |
| `SUNTING_BOBOT_PILAR` | menyunting bobot KPI vs kompetensi |
| `PILIH_PAKET_SOAL` | memilih paket soal per level |
| `SUNTING_PESERTA` | **Siapkan siklus** |
| `TULIS_OVERRIDE` | menyimpang dari aturan level untuk satu orang |
| `BUAT_PENUGASAN` | menugaskan penilai, membatalkan penugasan, **Kunci** |
| `TERIMA_PAYLOAD` | (tidak ada tombol; jalur HC Learn) |
| `HITUNG_HASIL` | **Hitung hasil** |
| `KALIBRASI` | menggeser grade |
| `TERBITKAN` | menerbitkan hasil |

### 2.2 `POST /cycles/{cycleId}/siapkan`

Tanpa body. Membentuk peserta dan baris hasil, menyalin aturan, lalu memindahkan siklus
`DRAFT` → `PREPARED`. **Aman dijalankan berulang** — tidak menggandakan apa pun.

```json
{ "result": { "salinan": { "policy": 13, "detail": 45, "pita": 12 },
              "peserta": { "diikutkan": 900, "dikecualikan": 51, "tertunda": 7 } } }
```

### 2.3 `POST /cycles/{cycleId}/kunci`

Tanpa body. `{ "result": { "status": "LOCKED" } }`.

Ditolak 422 bila masih ada peserta `TERTUNDA`, atau bila tak seorang pun `DIIKUTKAN`.
Layar sebaiknya menawarkan jalan ke daftar tertunda dari pesan itu.

### 2.4 `GET /cycles/{cycleId}/tertunda`

```json
{ "result": { "tertunda": [
  { "Id_PA_Participant": 12, "Kode_Perusahaan": "001", "Kode_Karyawan": "A1",
    "Nama_Karyawan_Saat_Cycle": "…", "ID_Level_Saat_Cycle": 2,
    "Nama_Level_Saat_Cycle": "STAFF" }
] } }
```

Daftar kosong berarti keranjangnya bersih — bukan berarti gagal memuat. Bedakan
keduanya di layar.

### 2.5 `GET /participants/{participantId}/usulan-penilai`

```json
{
  "result": {
    "usulan": {
      "SELF": {
        "kategori_id": 3, "kode_kategori": "SELF", "nama_kategori": "Penilaian Diri",
        "min_rater": 1, "maks_rater": 1, "terpasang": 0,
        "butuh_manual": false, "izinkan_manual": false,
        "calon": [ { "kode_perusahaan": "001", "kode_karyawan": "A1", "nama": "…",
                     "organization_relationship_id": null, "kode_relasi": null,
                     "sudah_ditugaskan": false } ]
      }
    },
    "kesiapan": {
      "siap_dibuka": false,
      "siap_dihitung": false,
      "kategori": {
        "SELF": { "min_rater": 1, "maks_rater": 1, "min_rater_selesai": 1,
                  "terpasang": 0, "selesai": 0,
                  "cukup_terpasang": false, "cukup_selesai": false }
      }
    }
  }
}
```

Kuncinya adalah **kode kategori** (`SELF`, `ATASAN`, `PEER`, `BAWAHAN`), berurutan
menurut `Urutan_Tampil` aturannya. Urutan itu sengaja dipertahankan — tampilkan menurut
urutan kunci yang datang, jangan diurut ulang di layar.

`butuh_manual: true` berarti mesin tidak punya satu pun calon (relasi organisasinya
belum ada). `izinkan_manual: false` berarti kategori itu memang tidak boleh diisi
tangan. Keduanya berbeda dan harus terbaca berbeda di layar.

### 2.6 `POST /participants/{participantId}/penugasan`

```json
{ "penilai": [
  { "kode_kategori": "ATASAN", "kode_perusahaan": "001", "kode_karyawan": "B2",
    "sumber": "USULAN", "organization_relationship_id": 91, "alasan_manual": null }
] }
```

`sumber` hanya `USULAN` atau `MANUAL`. `alasan_manual` **wajib diisi** bila
`sumber = MANUAL`. Seluruh daftar berhasil atau seluruhnya batal.

`{ "result": { "dibuat": 3, "kategori_dihitung_ulang": [1, 2] } }`

Sesudah panggilan ini, `Bobot_Dalam_Kategori` seluruh penilai di kategori yang tersentuh
sudah dihitung ulang. Layar tidak perlu menghitungnya sendiri.

### 2.7 `POST /assignments/{assignmentId}/batalkan`

```json
{ "alasan": "salah pilih penilai" }
```

`alasan` wajib, maksimal 500 karakter. POST, bukan DELETE, justru karena membawa body.
Ditolak 422 bila penugasannya sudah `SELESAI`.

### 2.8 `POST /cycles/{cycleId}/hitung` dan `POST /participants/{participantId}/hitung`

Per siklus:

```json
{ "result": { "dihitung": 880, "tertahan": 20,
              "gagal": [ { "participant_id": 41, "pesan": "…" } ],
              "diproses": 40, "terakhir_id": 44321, "selesai": true } }
```

Per peserta:

```json
{ "result": { "result_id": 5, "status": "DIHITUNG", "versi": 1,
              "nilai_kpi": 104.5, "nilai_kompetensi": 82.31, "final_score": 90.13,
              "kode_grade": "A", "kode_kuadran": "STRIVING_STAR",
              "alasan_tertahan": null } }
```

**`TERTAHAN` bukan kegagalan.** Itu keputusan sadar: orang tanpa KPI disetujui, orang
yang bobot KPI-nya bukan 100, dan orang yang tak satu pun kategori penilainya memenuhi
ambang **sengaja tidak diberi angka**. `alasan_tertahan` menjelaskan yang mana, dan
harus ditampilkan. Menyatukan "tertahan" dengan "gagal" di satu penghitung membuat
"berhasil sebagian" terbaca seperti selesai.

`alasan_tertahan` tetap bertipe string, tetapi sejak 2 September 2026 ia **boleh memuat
lebih dari satu sebab** — sebab pilar KPI dan sebab pilar kompetensi bisa muncul
bersamaan, dirangkai dalam satu paragraf. Sebelumnya keduanya dipilih dengan `??`
sehingga sebab KPI selalu menutupi sebab kompetensi; akibatnya admin memperbaiki satu
masalah, menjalankan ulang perhitungan satu siklus penuh, lalu baru mengetahui masalah
kedua yang sejak awal sudah diketahui mesin. Karena itu **jangan merancang layar dengan
asumsi satu kalimat**: sediakan ruang untuk dua kalimat, dan jangan memotongnya dengan
elipsis tanpa menyediakan cara membaca utuhnya. Isinya dipendekkan di sisi server pada
500 karakter (batas kolomnya), ditandai `…` bila itu terjadi.

`nilai_kpi` dan `final_score` **boleh melebihi 100** — sampai 125 pada sisi KPI. Jangan
memasang batas 100 di layar, jangan memakai lebar bar 100% sebagai maksimum.

### 2.9 `GET /cycles/{cycleId}/participants` — daftar peserta, berhalaman

Parameter query, semuanya opsional: `status_peserta`, `status_hasil`, `id_divisi`,
`id_level`, `q` (nama atau awalan kode karyawan), `halaman`, `per_halaman`.

```json
{
  "result": {
    "peserta": [
      { "Id_PA_Participant": 44309, "Kode_Karyawan": "A1001",
        "Nama_Karyawan_Saat_Cycle": "…", "ID_Level_Saat_Cycle": 2,
        "Nama_Level_Saat_Cycle": "STAFF", "Nama_Divisi_Saat_Cycle": "…",
        "Status_Peserta": "DIIKUTKAN", "Alasan_Dikecualikan": null,
        "Id_PA_Result": 902, "Status_Hasil": "DIHITUNG",
        "Nilai_KPI": 85, "Nilai_Kompetensi": 55, "Final_Score": 79,
        "Kode_Grade_Sistem": "B", "Kode_Grade_Final": "B", "Flag_Dikalibrasi": "T",
        "Alasan_Tertahan": null, "Calculation_Version": 1,
        "penilai_terpasang": 1, "penilai_selesai": 1 }
    ],
    "halaman": 1, "per_halaman": 25, "total": 859, "total_halaman": 172,
    "dipangkas": false,
    "ringkasan": { "DIIKUTKAN": 859, "DIKECUALIKAN": 99, "TERTUNDA": 0 }
  }
}
```

**`per_halaman` dipangkas server di 100.** Ketika itu terjadi `dipangkas` bernilai
`true` — tampilkan. Pembatasan yang tidak terlihat membuat orang mengira datanya memang
hanya sebanyak itu.

`ringkasan` menghitung SELURUH siklus, bukan halaman ini.

Seluruh identitas (nama, level, divisi, jabatan) adalah keadaan **saat siklus**, bukan
keadaan hari ini. Orang yang pindah divisi bulan depan tidak mengubah hasil ini.

### 2.10 `GET /participants/{participantId}/hasil` — detail hasil satu orang

```json
{
  "result": {
    "peserta": { "…identitas beku…", "Nilai_KPI": 85, "Nilai_KPI_Sebelum_Cap": 92.4,
                 "Nilai_Kompetensi": 55, "Bobot_KPI_Terpakai": 80,
                 "Bobot_Kompetensi_Terpakai": 20, "Final_Score": 79,
                 "Kode_Grade_Sistem": "B", "Kode_Grade_Final": "B",
                 "Alasan_Tertahan": null },
    "kuadran": { "Kode_Kuadran": "TOP_PERFORMER", "Nomor_Box": 5, "Warna": "…" },
    "kategori": [ { "Kode_Kategori": "SELF", "Nilai": 55, "Bobot_Terpakai": 100,
                    "Jumlah_Rater_Diharapkan": 1, "Jumlah_Rater_Mengisi": 1 } ],
    "kompetensi": [ { "Kode_Competency": "K01", "Nama_Competency": "Analytical Thinking",
                      "Kode_Type": "MANAJERIAL", "Kode_Kategori": null,
                      "Nilai": 40, "Jumlah_Penilai": 1, "agregat": true } ],
    "catatan": [ { "Kode_Type": "STRENGTH", "Isi": "…", "dari_penilai": true } ],
    "penilai": [ { "Id_PA_Assignment": 1, "Kode_Kategori": "SELF",
                   "Bobot_Dalam_Kategori": 100, "Status_Penugasan": "SELESAI",
                   "Due_At": "2026-07-31",
                   "terlambat": false, "hari_terlambat": null } ],
    "riwayat_kalibrasi": [],
    "kalibrasi_penilai": [ { "kalibrasi_id": 12, "assignment_id": 34740,
                   "penilai": { "kode_karyawan": "A4", "nama": "Aji" },
                   "kategori": { "kode": "ATASAN", "nama": "Atasan" },
                   "alasan": "…", "pada": "2026-09-18 10:15:00", "oleh": 634,
                   "perubahan": [ { "competency_id": 11, "kode": "CV01", "nama": "Integritas",
                                    "skor_sebelum": 90, "skor_sesudah": 82,
                                    "skor_maksimal": 100 } ],
                   "perubahan_jawaban": [ { "kode_soal": "PA-STAFF-Q07", "competency_id": 21,
                                    "nama": "Strength", "jawaban_sebelum": "…",
                                    "jawaban_sesudah": "…" } ] } ],
    "aturan_penilai": { "SELF": { }, "ATASAN": { } },
    "penyimpangan": { "rater": 0, "pilar": 0, "kompetensi": 0, "total": 0 }
  }
}
```

**Dua riwayat kalibrasi, sengaja dua kunci.** `riwayat_kalibrasi` milik forum (admin
mengubah hasil akhir); `kalibrasi_penilai` (sejak 18 September 2026, §3.5) milik penilai yang
menyelaraskan skornya sendiri sebelum mengirim, terbaru lebih dulu. Menyatukannya membuat
pembaca audit tidak bisa lagi membedakan siapa yang mengubah apa.

**`kompetensi[]` memuat dua jenis baris.** Yang `agregat: true` (dan `Kode_Kategori`
kosong) adalah nilai lintas kategori — **itulah yang digambar radar**. Sisanya rincian
per kategori penilai. Menggambar keduanya sekaligus menghasilkan radar berlapis yang salah.

Layar ini **wajib tetap bisa dibuka untuk peserta `TERTAHAN`** — justru di sanalah
`Alasan_Tertahan` dibaca. Peserta `TERTUNDA` pun bisa dibuka; `aturan_penilai` akan
kosong, dan itu jawabannya, bukan kegagalan.

`Nilai_KPI_Sebelum_Cap` menjawab "kenapa dia 125 padahal capaiannya 177" tanpa
menghitung ulang.

**`terlambat` dan `hari_terlambat` dihitung SERVER, bukan layar.** Keduanya diturunkan
dari `Due_At < sekarang` dan status bukan `SELESAI`/`DIBATALKAN` — tidak disimpan sebagai
status, karena status tersimpan menuntut pekerjaan yang membalik baris tiap tengah malam,
dan pekerjaan semacam itu yang mati diam-diam menghasilkan lencana salah tanpa ada yang
tahu. Jangan menghitungnya lagi di layar: dua rumus untuk satu gagasan pasti berselisih,
dan yang berselisih di sini adalah angka yang dipakai mengejar orang.

`Due_At` kosong berarti siklusnya belum punya jendela pengisian. Penugasan seperti itu
tidak pernah terlambat, dan itu jawaban yang benar untuknya — bukan data yang hilang.
`Due_At` bertipe **DATE**, bukan datetime — nilainya `"2026-07-31"`, tanpa jam. Artinya
**sampai akhir hari itu**. Jangan membandingkannya dengan `new Date(Due_At) < now` di
layar: itu jatuh di tengah malam dan akan menandai orang terlambat pada hari yang masih
miliknya. Keterlambatan sudah dihitung server; pakai `terlambat` dan `hari_terlambat`.

`hari_terlambat` dihitung dalam **hari kalender**, bukan selisih jam dibagi 24 — tenggat
kemarin berarti `1`, bukan `0`. Itu yang dimaksud orang ketika mengatakan "terlambat
sehari", dan lencana merah bertuliskan "Terlambat 0 hari" membuat orang berhenti
mempercayai layarnya.

### 2.11 `POST /results/{resultId}/kalibrasi`

```json
{ "kode_grade": "B_PLUS", "pa_hav_quadrant_id": 2, "alasan": "hasil kalibrasi forum HR" }
```

Salah satu dari `kode_grade` atau `pa_hav_quadrant_id` boleh kosong, tetapi **tidak
keduanya**. `alasan` wajib, maksimal 500 karakter.

```json
{ "result": { "result_id": 902, "kode_grade_sistem": "B",
              "kode_grade_final": "B_PLUS", "kuadran_id": 2,
              "kode_kuadran": "FUTURE_STAR_A" } }
```

**`kode_grade_sistem` tidak ikut berubah.** Hasil mesin dan hasil kalibrasi hidup
berdampingan supaya "berapa hitungannya sebelum digeser" bisa dijawab tanpa menghitung
ulang, dan tanpa mempercayai ingatan siapa pun.

Ditolak 422 bila: hasilnya `TERTAHAN` (belum punya grade — perbaiki sebabnya lalu hitung
ulang), kalibrasinya tidak mengubah apa pun, atau grade tujuannya tidak ada di pita
salinan siklus tersebut.

### 2.12 `GET /cycles/{cycleId}/hav-map` — sebaran sembilan kotak

```json
{
  "result": {
    "kuadran": [
      { "Kode_Kuadran": "STRIVING_STAR", "Nama_Kuadran": "Striving Star",
        "Nomor_Box": 1, "Tingkat_Performa": "TINGGI", "Tingkat_Kompetensi": "TINGGI",
        "Warna": "…", "jumlah": 0 }
    ],
    "grade": [
      { "Kode_Range": "A", "Nama_Range": "Istimewa", "Nilai_Dari": 90,
        "Nilai_Sampai": 999.99, "Target_Persen": 10, "Warna": "…",
        "jumlah": 0, "persen": 0 }
    ],
    "ringkasan": { "dihitung": 2, "tertahan": 6, "belum_dihitung": 950,
                   "terbit": 0, "total": 958 }
  }
}
```

**Sembilan kotak selalu dikembalikan, termasuk yang `jumlah: 0`.** Jangan disaring —
matriks berlubang lebih menyesatkan daripada kotak kosong, dan "tidak ada seorang pun di
Deadwood" adalah temuan tersendiri.

`Target_Persen` adalah **pembanding, bukan kuota**. Tidak ada satu pun bagian sistem ini
yang memaksa sebaran mengikuti target, dan deck PIC pun tidak pernah memintanya.

Sebaran grade dihitung dari `Kode_Grade_Final` — grade yang berlaku setelah kalibrasi,
bukan grade mesin. Memakai grade sistem akan menggambar sebaran yang tidak cocok dengan
apa pun yang dilihat karyawan.

`persen` dihitung terhadap peserta yang **sudah bergrade**, bukan terhadap seluruh
peserta siklus. Angka "100%" pada satu grade karena baru dua orang dihitung adalah
benar, dan `ringkasan` yang menjelaskannya — tampilkan keduanya berdampingan.

---

### 2.13 Penerbitan hasil — dua langkah, dan itu menghindari satu jebakan

`GET /cycles/{cycleId}/penerbitan` — cacah hasil per status.

```json
{ "result": { "terbit": 2, "dihitung": 0, "tertahan": 6,
              "belum_dihitung": 851, "total": 859 } }
```

`POST /cycles/{cycleId}/terbitkan` — menerbitkan seluruh hasil `DIHITUNG`. **Siklusnya
tetap `CLOSED`.** Aman dijalankan berulang.

```json
{ "result": { "diterbitkan": 2, "sudah_terbit": 0, "tertahan": 6,
              "belum_dihitung": 851, "total_peserta": 859, "siap_ditutup": false } }
```

**Tampilkan angka yang dilewati, jangan cuma "berhasil".** Menerbitkan 2 dari 859 dan
melaporkannya sebagai sukses polos adalah cara paling halus menyembunyikan bahwa 857
orang belum punya hasil.

`POST /participants/{participantId}/terbitkan` — menerbitkan satu orang, untuk yang
tertinggal dan baru selesai diperbaiki.

`POST /cycles/{cycleId}/tutup-penerbitan` — memindahkan **siklus** ke `PUBLISHED`.

```json
{ "result": { "cycle_id": 3, "status": "PUBLISHED",
              "published_at": "2026-09-01 15:08:57" } }
```

**Ini pintu satu arah.** Sesudah `PUBLISHED`, gerbang tidak mengizinkan operasi apa pun:
tidak ada perhitungan ulang, penerbitan susulan, maupun kalibrasi. Karena itu server
**menolak** selama masih ada peserta tertahan atau belum dihitung — kalau tidak, mereka
terkunci selamanya.

Penolakan itu **tidak menghalangi siapa pun**: hasil yang siap sudah terbit dan sudah
terlihat karyawan. Yang tertunda hanya penutupan administratifnya. Layar sebaiknya
mengatakan begitu, bukan menampilkannya sebagai kegagalan.

Ditolak 422 bila: tak satu pun hasil `DIHITUNG` (untuk `terbitkan`), hasilnya `TERTAHAN`
(untuk penerbitan per orang), atau masih ada yang belum selesai (untuk penutupan).

**Hasil yang sudah `TERBIT` tidak bisa dihitung ulang.** `POST /participants/{id}/hitung`
menolaknya: karyawan sudah melihat angkanya, dan menggantinya diam-diam tidak
meninggalkan jejak apa pun. Yang benar untuk hasil terbit yang keliru adalah **kalibrasi**
(§2.11), karena kalibrasi mencatat sebab, pelaku, dan nilai sebelumnya.

### 2.14 Penyimpangan per peserta (K32)

Hanya boleh saat siklus `DRAFT` atau `PREPARED` — sesudah dikunci, aturan sudah beku.

`PUT /participants/{participantId}/override/penilai`

```json
{ "penilai": [
  { "pa_rater_category_id": 1, "bobot_persen": 75, "min_rater": 1,
    "alasan_perubahan": "atasan lebih berbobot untuk peran ini" },
  { "pa_rater_category_id": 3, "bobot_persen": 25, "min_rater": 1,
    "alasan_perubahan": "penyesuaian pasangannya" }
] }
```

```json
{ "result": { "participant_id": 59653, "tersimpan": 2, "dicabut": 0,
              "bobot_efektif": { "SELF": 25, "ATASAN": 75 } } }
```

**Dikirim SELURUHNYA, bukan per baris.** Bobot antar kategori wajib berjumlah 100
**sesudah digabung dengan aturan level** — dan jumlah itu hanya bisa diperiksa ketika
seluruh penyimpangannya diketahui. Satu override yang tampak wajar sendirian bisa membuat
gabungannya 105.

Layarnya karena itu **satu formulir berisi semua kategori dengan penjumlah hidup**, bukan
tabel yang tiap barisnya punya tombol simpan.

`bobot_efektif` adalah gabungan yang sebenarnya berlaku. **Tampilkan itu**, bukan angka
yang dikirim — keduanya berbeda ketika sebagian kategori tidak disimpangkan.

Daftar kosong (`"penilai": []`) berarti mencabut seluruh penyimpangan penilai.

Untuk **mencabut satu kategori** dari peserta ini, kirim `"flag_dipakai": "T"` tanpa
bobot. Untuk **mengubahnya**, `bobot_persen` wajib ada.

`PUT /participants/{participantId}/override/pilar`

```json
{ "bobot_kpi": 70, "bobot_kompetensi": 30, "alasan_perubahan": "…" }
```

Ditolak bila jumlahnya bukan 100, atau bila angkanya **sama dengan aturan levelnya** —
override yang tidak mengubah apa pun hanya membuat peserta itu tampak menyimpang padahal
tidak.

`PUT /participants/{participantId}/override/syarat` — bentuk sama, kuncinya `syarat`,
tiap butir `pa_competency_type_id` + `alasan_perubahan`, opsional `flag_dipakai` dan
`flag_wajib`.

`POST /participants/{participantId}/override/cabut` — mencabut ketiganya sekaligus.

```json
{ "result": { "participant_id": 59653,
              "dicabut": { "penilai": 2, "pilar": 0, "syarat": 0 } } }
```

**`alasan_perubahan` wajib di setiap butir**, dan kolomnya NOT NULL di skema. Penyimpangan
tanpa sebab adalah peserta yang diperlakukan berbeda dari sesama levelnya tanpa ada yang
bisa menjelaskan kenapa.

Penyimpangan bersifat **sparse**: normalnya kosong. `penyimpangan` di §2.10 menghitung
barisnya — nol berarti peserta itu mengikuti aturan levelnya sepenuhnya.

---

### 2.15 Bobot pilar per siklus (K23)

Pembagian KPI lawan kompetensi, **per level**. Satu-satunya salinan siklus yang masih
boleh disunting sesudah siklus dikunci — sampai `CLOSED` — karena ia tidak menyentuh siapa
menilai siapa, hanya menimbang dua angka yang sudah terkumpul.

`GET /cycles/{cycleId}/bobot-pilar`

```json
{ "result": {
    "siklus": { "Id_PA_Cycle": 657, "Kode_Cycle": "PA-2026-H1", "Status_Cycle": "LOCKED" },
    "boleh_sunting": true,
    "bobot": [ {
      "Id_PA_Cycle_Pillar_Weight_Snapshot": 12, "ID_Level": 2, "Nama_Level": "STAFF",
      "Bobot_KPI": "80.00", "Bobot_Kompetensi": "20.00",
      "Alasan_Perubahan": null, "Updated_At": null, "Updated_By": null,
      "hasil_terbit": 2, "hasil_dihitung": 0,
      "dapat_disunting": false,
      "alasan_terkunci": "Ada 2 hasil yang sudah terbit pada level ini; ..."
    } ]
} }
```

`PUT /cycles/{cycleId}/bobot-pilar/{levelId}`

```json
{ "bobot_kpi": 70, "bobot_kompetensi": 30, "alasan": "penyesuaian arahan direksi" }
```

```json
{ "result": { "cycle_id": 657, "id_level": 2, "bobot_kpi": 70, "bobot_kompetensi": 30,
              "berubah": true, "hasil_perlu_hitung_ulang": 14,
              "pesan": "Bobot tersimpan. 14 hasil pada level ini dihitung dengan bobot lama dan perlu dihitung ulang sebelum diterbitkan." } }
```

**`dapat_disunting` dan `alasan_terkunci` datang dari server; jangan disimpulkan layar
dari `hasil_terbit`.** Aturannya milik server, dan layar yang menyimpulkannya sendiri akan
berselisih begitu aturannya berubah. Pakai keduanya untuk menonaktifkan barisnya
**sebelum** orang menyunting — penolakan yang baru muncul sesudah tombol simpan ditekan
adalah cara paling melelahkan menyampaikan aturan.

**Hasil yang sudah `TERBIT` mengunci level itu selamanya.** Hasil terbit tidak pernah
dihitung ulang, jadi mengubah bobotnya membuat angka yang sudah dilihat orang tidak akan
pernah cocok dengan bobot yang tertulis di siklusnya — tanpa satu pun pesan galat. Kalau
hasilnya memang perlu digeser, jalurnya kalibrasi (§2.11).

**`hasil_perlu_hitung_ulang` bukan basa-basi.** Baris berstatus `DIHITUNG` lahir dari bobot
yang lama. Tawarkan hitung ulang (§2.8) di tempat, jangan berharap admin ingat sendiri.

`alasan` wajib **hanya bila siklusnya sudah dikunci** dan nilainya benar-benar berubah.
Sebelum kunci, bobot masih bagian dari penyiapan.

### 2.16 Menyunting keikutsertaan peserta

Hanya saat siklus `DRAFT` atau `PREPARED`.

`PUT /participants/{participantId}/status`

```json
{ "status": "DIKECUALIKAN", "alasan": "cuti panjang sampai Desember" }
```

```json
{ "result": { "participant_id": 59653, "nama": "Diva Mona Lisa",
              "status_lama": "DIIKUTKAN", "status_baru": "DIKECUALIKAN",
              "berubah": true, "penugasan_dibatalkan": 3,
              "pesan": "Peserta dikecualikan, dan 3 penugasan penilaian ikut dibatalkan ..." } }
```

`POST /participants/status-massal`

```json
{ "participant_ids": [59653, 59654], "status": "DIKECUALIKAN", "alasan": "perampingan siklus" }
```

```json
{ "result": { "diminta": 2, "berhasil": 1, "gagal": 1,
              "rincian_gagal": [ { "participant_id": 59654, "sebab": "..." } ] } }
```

**Hanya `DIIKUTKAN` dan `DIKECUALIKAN` yang bisa diminta.** `TERTUNDA` adalah **keadaan,
bukan pilihan**: artinya level orang itu belum punya aturan penilai. Menetapkannya dengan
tangan mengarang penghalang yang tidak ada, dan gerbang penguncian siklus kemudian menolak
karena penghalang karangan itu. Jangan menaruhnya di dropdown.

**`DIKECUALIKAN` selalu wajib beralasan.** Enam bulan kemudian, satu-satunya penjelasan
kenapa orang itu tidak dinilai adalah baris tersebut.

**`DIIKUTKAN` ditolak bila levelnya belum punya salinan aturan penilai** — orangnya akan
masuk siklus tanpa aturan siapa menilai siapa, dan itu bukan keikutsertaan melainkan lubang
yang baru ketahuan saat penugasan dibentuk.

**`penugasan_dibatalkan` wajib ditampilkan.** Mengecualikan orang ikut membatalkan seluruh
penugasan penilaian atasnya; tanpa itu, penilainya tetap melihat tugas untuk orang yang
sudah tidak ada di siklus dan mengerjakannya dengan sia-sia.

**Status yang sudah sama bukan galat** — `berubah: false`, 200. Dua orang menekan tombol
yang sama bukan kesalahan yang perlu diteriakkan.

Pada jalur massal, **yang gagal tidak menghentikan sisanya**. Tampilkan `rincian_gagal`
apa adanya; berhenti di tengah membuat admin harus menebak sudah sampai mana.


### 2.16b Membuka dan menutup pengisian — dua alamat yang sebelumnya TIDAK ADA

```
POST /cycles/{cycleId}/buka-pengisian     LOCKED | CLOSED  ->  OPEN
POST /cycles/{cycleId}/tutup-pengisian    OPEN             ->  CLOSED
```

Sampai 2 September 2026 **tidak ada satu baris kode produksi pun yang menulis `OPEN`
maupun `CLOSED`** — satu-satunya penulisnya adalah perintah data contoh `pa:demo-cycle`.
Akibatnya siklus sungguhan mentok di `LOCKED` selamanya, dan empat operasi inti yang
bergerbang pada kedua status itu (terima payload, hitung, kalibrasi, terbitkan) tidak
pernah terjangkau lewat aplikasi.

```json
{ "result": { "cycle_id": 1931, "status_lama": "LOCKED", "status": "OPEN",
              "dibuka_ulang": false } }

{ "result": { "cycle_id": 1931, "status": "CLOSED", "penugasan_belum_selesai": 12 } }
```

**`dibuka_ulang: true`** berarti siklus yang sudah `CLOSED` dibuka lagi. Bedakan di layar:
hasil yang sudah dihitung menjadi basi dan perlu dihitung ulang. Membuka ulang sengaja
DIIZINKAN — menutup terlalu cepat adalah kekeliruan yang wajar, dan aturan tanpa jalan
kembali hanya memindahkan perbaikannya ke SQL manual. Berhenti di `PUBLISHED`.

**Kunci `operasi_boleh` dan nama pembungkusnya** — jangan menyimpulkan tombolnya dari
`status_cycle`:

| Tombol | Kunci di `operasi_boleh` | Fungsi `paApi.js` | Fixture |
|---|---|---|---|
| Kunci siklus | `KUNCI_SIKLUS` | `kunciSiklus(cycleId)` | `kunci.json` |
| Buka pengisian | `BUKA_PENGISIAN` | `bukaPengisian(cycleId)` | `buka-pengisian.json` |
| Tutup pengisian | `TUTUP_PENGISIAN` | `tutupPengisian(cycleId)` | `tutup-pengisian.json` |

`BUKA_PENGISIAN` muncul pada **dua** status (`LOCKED` dan `CLOSED`), dan `CLOSED` juga
membolehkan `HITUNG_HASIL`, `KALIBRASI`, serta `TERBITKAN`. Itu sebabnya menyimpulkan
tombol dari status akan keliru pada keadaan yang paling ramai. Susunan lengkapnya:

```
DRAFT      SUNTING_SALINAN, PILIH_PAKET_SOAL, SUNTING_PESERTA, TULIS_OVERRIDE,
           BUAT_PENUGASAN, SUNTING_BOBOT_PILAR
PREPARED   …sama seperti DRAFT… + KUNCI_SIKLUS
LOCKED     SUNTING_BOBOT_PILAR, BUKA_PENGISIAN
OPEN       SUNTING_BOBOT_PILAR, TERIMA_PAYLOAD, TUTUP_PENGISIAN
CLOSED     SUNTING_BOBOT_PILAR, TERIMA_PAYLOAD, HITUNG_HASIL, KALIBRASI,
           TERBITKAN, BUKA_PENGISIAN
PUBLISHED  (kosong)
```

**`penugasan_belum_selesai`** dilaporkan, bukan menghalangi. Menutup siklus dengan 200
penilaian belum masuk itu sah — tetapi tidak boleh terjadi tanpa disadari, jadi tampilkan
angkanya pada konfirmasi.

`buka-pengisian` **menolak** siklus yang `Pengisian_Dari`/`Pengisian_Sampai`-nya kosong:
tanpa jendela, penugasan lahir tanpa tenggat, dan penilai yang tidak mengisi tidak akan
pernah tercatat terlambat.

`POST /cycles/{cycleId}/kunci` kini bergerbang **hanya dari `PREPARED`**. Sebelumnya ia
satu-satunya perpindahan status yang tidak digerbangi sama sekali, sehingga siklus `CLOSED`
berisi hasil yang sudah terbit bisa dikembalikan ke `LOCKED` — dan tanpa jalan kembali itu
berarti mati permanen.

---

### 2.16c `GET /cycles/{cycleId}/pratinjau-penyiapan` — lihat dulu, baru putuskan

**Tidak menulis apa pun.** Dipanggil SEBELUM `POST /cycles/{id}/siapkan`, dan hasilnya
ditampilkan sebagai konfirmasi — bukan sesudahnya sebagai laporan.

Sebelum ini, "Siapkan" langsung membentuk peserta untuk seluruh karyawan aktif — 958 baris
sekaligus — dan admin baru tahu hasilnya sesudah semuanya terlanjur ada. Kalau ternyata satu
level belum punya aturan penilai, orang-orangnya mendarat di keranjang `TERTUNDA` dan siklus
tidak bisa dikunci.

```json
{ "result": {
    "cycle_id": 1931,
    "cacah": { "diikutkan": 859, "dikecualikan": 99, "tertunda": 0, "sudah_ada": 0 },
    "sebab_dikecualikan": {
      "Level FREELANCE dikecualikan dari Performance Appraisal.": 98,
      "Mengundurkan diri": 1
    },
    "tertunda_per_level": {},
    "level_tanpa_aturan": [],
    "contoh": {
      "dikecualikan": [ { "kode_karyawan": "A9", "nama": "…", "sebab": "…" } ],
      "tertunda": []
    },
    "ini_pratinjau": true,
    "catatan": "Tidak ada yang akan tertunda. Penyiapan bisa dijalankan."
} }
```

**`level_tanpa_aturan` adalah bagian yang paling perlu ditonjolkan.** Level yang muncul di
situ berarti karyawannya sah dinilai dan levelnya ada, tetapi **tidak ada aturan yang
mengatakan siapa yang menilai mereka** — mereka akan mendarat di `TERTUNDA`, dan siklus
tidak bisa dikunci selama keranjang itu terisi. Tiap baris membawa `jumlah_karyawan`, karena
"level 2 tidak punya aturan" tanpa angka tidak memberi tahu apakah itu menyangkut tiga orang
atau tiga ratus.

**`sebab_dikecualikan` sudah dikelompokkan per sebab**, bukan daftar per orang: 98 baris
"Level FREELANCE dikecualikan" akan menenggelamkan satu baris yang justru perlu dilihat.
`contoh` memuat paling banyak 10 nama per kelompok, sekadar supaya angkanya punya wajah.

**`ini_pratinjau` selalu `true`.** Pratinjau yang tidak mengatakan dirinya pratinjau akan
disangka hasil.

Angkanya dijamin sama persis dengan penyiapan sungguhan: keduanya memakai method keputusan
kelayakan yang sama, dan ada uji yang menuntut kecocokannya. Digerbangi `SUNTING_SALINAN`,
jadi ditolak pada siklus yang sudah dikunci.

---

### 2.17 `GET /cycles/{cycleId}/ringkasan` — dashboard admin

```json
{ "result": {
    "siklus": { "Id_PA_Cycle": 657, "Kode_Cycle": "PA-2026-H1", "Status_Cycle": "OPEN" },
    "operasi_boleh": ["TERIMA_PAYLOAD", "SUNTING_BOBOT_PILAR"],
    "peserta": { "total": 958, "diikutkan": 859, "tertunda": 0, "dikecualikan": 99,
                 "per_status": { "DIIKUTKAN": 859, "DIKECUALIKAN": 99 } },
    "penugasan": { "total": 1720, "selesai": 430, "terlambat": 62,
                   "penyelesaian_persen": 25,
                   "per_status": { "BELUM_MULAI": 1100, "BERJALAN": 190, "SELESAI": 430 } },
    "hasil": { "total": 958, "terbit": 2, "tertahan": 6,
               "per_status": { "BELUM_DIHITUNG": 950, "TERTAHAN": 6, "TERBIT": 2 } },
    "per_divisi": [ { "id_divisi": 3, "nama_divisi": "PRODUCTION",
                      "total": 240, "selesai": 60, "terlambat": 12,
                      "penyelesaian_persen": 25 } ]
} }
```

`penyelesaian_persen` sudah dibulatkan server — jangan dihitung ulang dari
`selesai / total`.

**Jumlah `per_divisi` selalu sama dengan `penugasan.total`.** Peserta tanpa divisi masuk
sebagai `"(Tanpa divisi)"`, tidak dibuang — kalau dibuang, selisihnya akan dicari orang
berjam-jam.

### 2.18 `GET /cycles/{cycleId}/monitoring` — peta panas dan daftar terlambat

Query: `id_divisi`, `id_level`, `pa_rater_category_id`, `status` (`BELUM_MULAI`|`BERJALAN`),
`q` (nama yang dinilai **atau** nama penilainya), `halaman`, `per_halaman` (maksimum 100).

```json
{ "result": {
    "peta_panas": {
      "status": ["BELUM_MULAI", "BERJALAN", "SELESAI"],
      "baris": [ { "divisi": "PRODUCTION", "sel": [180, 20, 40], "total": 240 } ]
    },
    "terlambat": {
      "baris": [ { "Id_PA_Assignment": 147, "Due_At": "2026-07-31",
                   "Status_Penugasan": "BELUM_MULAI", "hari_terlambat": 12,
                   "Nama_Penilai_Saat_Cycle": "Budi", "Kode_Karyawan_Penilai": "A1",
                   "Kode_Kategori": "ATASAN", "Nama_Kategori": "Atasan Langsung",
                   "Id_PA_Participant": 59653, "Nama_Karyawan_Saat_Cycle": "Ani",
                   "Nama_Divisi_Saat_Cycle": "PRODUCTION",
                   "Nama_Level_Saat_Cycle": "STAFF" } ],
      "halaman": 1, "per_halaman": 25, "total": 62, "total_halaman": 3,
      "dipangkas": false
    },
    "pilihan_saringan": {
      "divisi": [ { "id": 3, "nama": "PRODUCTION" } ],
      "level": [ { "id": 2, "nama": "STAFF" } ],
      "kategori_penilai": [ { "id": 1, "kode": "SELF", "nama": "Diri Sendiri" } ],
      "status_penugasan": [ { "kode": "BELUM_MULAI", "nama": "BELUM_MULAI" } ]
    }
} }
```

**`peta_panas.baris[].sel` selalu selengkap `peta_panas.status`** — nol pun ditulis. Sel
yang kadang tidak ada memaksa layar menebak apakah kosong berarti nol atau berarti belum
dimuat.

**`pilihan_saringan` berisi yang BENAR-BENAR ada di siklus ini**, bukan seluruh master.
Dropdown berisi divisi yang tak seorang pun menempatinya hanya membuat orang menyaring,
menatap tabel kosong, dan mengira sistemnya rusak.

Daftar terlambat diurut **paling lama terlambat lebih dulu**.

### 2.19 `GET /cycles/{cycleId}/distribusi` — sebaran nilai

Query: `id_level`, `id_divisi`.

```json
{ "result": {
    "asal_angka": { "per_status": { "DIHITUNG": 120, "TERBIT": 2 },
                    "dinilai": 122, "tertahan_tanpa_nilai": 6,
                    "catatan": "Sebaran mencakup seluruh hasil yang sudah punya nilai akhir..." },
    "keseluruhan": { "jumlah": 122, "rata_rata": 84.31, "standar_deviasi": 7.78,
                     "terendah": 61.0, "tertinggi": 99.5,
                     "cukup_untuk_kurva": true, "alasan_kurva_ditahan": null },
    "per_level": [ { "id_level": 2, "nama_level": "STAFF", "jumlah": 80,
                     "rata_rata": 83.1, "standar_deviasi": 6.4,
                     "cukup_untuk_kurva": true, "alasan_kurva_ditahan": null } ],
    "grade": [ { "kode_grade": "B", "jumlah": 61, "persen": 50.0 } ],
    "kuadran": [ { "id_kuadran": 9, "kode_kuadran": "STAR", "nama_kuadran": "Star",
                   "nomor_box": 9, "jumlah": 12, "persen": 9.8 } ],
    "histogram": [ { "dari": 80.0, "sampai": 85.0, "jumlah": 34 } ],
    "min_untuk_kurva": 30, "lebar_kelompok": 5
} }
```

**Hasil yang belum terbit IKUT dihitung, dan itu memang maksudnya** — layar ini dibaca
justru sebelum penerbitan, untuk memutuskan apakah sebarannya wajar dan perlu dikalibrasi.
Menyaringnya menjadi "yang sudah terbit saja" akan membuatnya kosong persis ketika ia
paling dibutuhkan. Karena itu `asal_angka` selalu ikut: **tampilkan**, supaya pembacanya
tahu angka itu dari status mana.

**Jangan menggambar kurva ketika `cukup_untuk_kurva` bernilai `false`.** Rata-rata dan
simpangan bakunya tetap benar dan boleh ditampilkan sebagai angka — yang ditahan hanya
penggambarannya sebagai kurva, karena kurva lonceng di atas segelintir orang terlihat
meyakinkan tentang sesuatu yang tidak diketahui. Tampilkan `alasan_kurva_ditahan`.

`standar_deviasi` bernilai `null` bila hanya ada satu nilai — simpangan baku satu angka
memang tidak ada. Itu bukan kegagalan.

`histogram` memakai batas atas **eksklusif**, sama dengan pita nilai di modul ini.

### 2.20 `GET /riwayat` — riwayat penilaian lintas siklus (admin)

Query: `tahun`, `cycle_id`, `id_divisi`, `id_level`, `kode_grade`, `status_hasil`, `q`
(nama **atau** kode karyawan), `halaman`, `per_halaman` (maksimum 100).

```json
{ "result": {
    "baris": [ { "Id_PA_Participant": 59653, "Kode_Karyawan": "A1",
                 "Nama_Karyawan_Saat_Cycle": "Ani", "Nama_Divisi_Saat_Cycle": "PRODUCTION",
                 "Kode_Cycle": "PA-2026-H1", "Tahun": 2026,
                 "Final_Score": "82.50", "Kode_Grade_Final": "B",
                 "Kode_Grade_Sistem": "B_PLUS", "Status_Hasil": "TERBIT",
                 "Alasan_Tertahan": null, "dikalibrasi": true } ],
    "halaman": 1, "per_halaman": 25, "total": 958, "total_halaman": 39,
    "dipangkas": false,
    "tren": [ { "id_cycle": 657, "kode_cycle": "PA-2026-H1", "tahun": 2026,
                "jumlah": 122, "rata_rata": 84.31,
                "terendah": 61.0, "tertinggi": 99.5 } ],
    "pilihan_saringan": { "siklus": [], "tahun": [2026], "grade": [], "status_hasil": [] }
} }
```

**Bukan versi admin dari §3.4.** Yang ini memuat hasil yang **belum terbit dan yang
tertahan** — justru itulah pekerjaan yang belum selesai. Jangan memakainya di layar
karyawan.

Peserta yang **belum punya baris hasil sama sekali** tetap muncul, dengan kolom hasilnya
`null`. Membuang mereka berarti orang yang paling perlu ditindaklanjuti justru tak pernah
ditemukan lagi.

**`dikalibrasi`** dibaca server dari kolom `Flag_Dikalibrasi`. Sampai 2 September 2026 ia
diturunkan dari selisih `Kode_Grade_Sistem` dan `Kode_Grade_Final`, dan itu keliru:
kalibrasi yang hanya menggeser **kuadran** tak terbaca sebagai kalibrasi. Terbukti — satu
peserta digeser dari `POTENTIAL_CANDIDATE` ke `STRIVING_STAR`, melompat dari kotak 4 ke
kotak 1 pada matriks sembilan kotak, dengan grade tetap `B`, dan riwayat melaporkan
`false`. Perpindahan kuadran justru sering lebih berkonsekuensi daripada perpindahan
grade: kuadran itulah yang dipakai untuk keputusan suksesi. Jangan menyimpulkannya sendiri
di layar.

**Saringan `status_peserta`** (`DIIKUTKAN` | `DIKECUALIKAN` | `TERTUNDA`) sebelumnya tidak
ada sama sekali, sehingga riwayat melaporkan total 958 yang 953 di antaranya baris
`DIKECUALIKAN` tanpa hasil — dan admin tidak punya cara menyingkirkannya. Barisnya tetap
BOLEH dilihat (riwayat memang tempat menelusuri orang yang dikecualikan beserta alasannya);
yang berubah adalah ia tidak lagi memaksa ikut.

`tren` memakai **saringan yang sama** dengan tabelnya, dan diurut **terlama lebih dulu**
(tabelnya terbaru lebih dulu — itu disengaja: tabel dibaca dari yang paling relevan, tren
dibaca kiri ke kanan).


### 2.21 `GET /cycles/{cycleId}` — detail siklus

```json
{ "result": {
    "siklus": { "Id_PA_Cycle": 657, "Kode_Cycle": "PA-DEMO-FE",
                "Nama_Cycle": "Siklus Contoh", "Tahun": 2026, "Deskripsi": null,
                "Periode_Dinilai_Dari": "2026-01-01", "Periode_Dinilai_Sampai": "2026-06-30",
                "Pengisian_Dari": null, "Pengisian_Sampai": null,
                "Tanggal_Acuan_Organisasi": null, "Status_Cycle": "CLOSED",
                "Locked_At": "…", "Published_At": null },
    "operasi_boleh": ["HITUNG_HASIL", "KALIBRASI", "TERBITKAN"],
    "boleh_ubah_periode": false,
    "alasan_periode_terkunci": "Siklus berstatus CLOSED. Mengubah periode sesudah dikunci …",
    "peserta": { "total": 958, "per_status": { "DIIKUTKAN": 859, "DIKECUALIKAN": 99 } },
    "kelengkapan": { "salinan_aturan_penilai": 6, "salinan_bobot_pilar": 6,
                     "salinan_pita_nilai": 12, "salinan_syarat_kompetensi": 28,
                     "paket_soal": 3 }
} }
```

**`boleh_ubah_periode` datang dari server; jangan disimpulkan dari `Status_Cycle`.**
Aturannya milik server, dan layar yang menyimpulkannya sendiri akan berselisih begitu
aturannya berubah — yang berselisih di sini menentukan apakah sebuah formulir boleh
disunting. Tampilkan `alasan_periode_terkunci` apa adanya saat ia terisi.

`kelengkapan` mencacah salinan yang sudah terbentuk. Nol pada `salinan_aturan_penilai`
berarti siklus belum disiapkan, bukan berarti tidak ada aturannya.

### 2.22 `POST /cycles` — membuat siklus

```json
{ "kode_cycle": "PA-2026-H1", "nama_cycle": "Semester 1 2026", "tahun": 2026,
  "deskripsi": null,
  "periode_dinilai_dari": "2026-01-01", "periode_dinilai_sampai": "2026-06-30",
  "pengisian_dari": "2026-07-01", "pengisian_sampai": "2026-07-31",
  "tanggal_acuan_organisasi": "2026-06-30" }
```

```json
{ "result": { "cycle_id": 1392, "kode_cycle": "PA-2026-H1", "status": "DRAFT" } }
```

**Status tidak diterima dari kiriman.** Siklus selalu lahir `DRAFT`; yang lahir terkunci
akan melewati penyiapan dan pesertanya tidak pernah terbentuk.

`kode_cycle` **dinaikkan ke huruf besar** dan wajib unik. Kode kembar ditolak dengan pesan
yang menyebut kodenya, bukan nama indeks.

### 2.23 `PUT /cycles/{cycleId}` — menyunting siklus

Kirim hanya field yang berubah.

```json
{ "result": { "cycle_id": 657, "periode_berubah": ["Periode_Dinilai_Sampai"],
              "perlu_siapkan_ulang": true } }
```

**Penyuntingannya DUA TINGKAT, dan bedanya bukan soal kehati-hatian:**

| Field | Kapan boleh |
|---|---|
| `nama_cycle`, `deskripsi` | **kapan saja** — tidak mengubah angka siapa pun |
| `tahun`, `periode_dinilai_*`, `pengisian_*`, `tanggal_acuan_organisasi` | hanya `DRAFT` / `PREPARED` |

Periode menentukan baris KPI mana yang ditarik untuk setiap peserta, sehingga mengubahnya
sesudah siklus dikunci menggeser nilai orang **tanpa satu pun jejak yang mengatakannya**.

**`perlu_siapkan_ulang` wajib ditampilkan.** Peserta yang sudah terbentuk memakai salinan
organisasi pada tanggal acuan LAMA; mengubah periodenya tidak menyiapkan ulang mereka
dengan sendirinya, dan tak ada yang akan memberi tahu kalau layar diam.

### Penjaga yang akan Anda temui sebagai 422

| Kiriman | Ditolak karena |
|---|---|
| `tahun` tidak sesuai tahun periode | **penjaga terpenting di sini** — lihat di bawah |
| `periode_dinilai_sampai` < `..._dari` | rentang terbalik |
| `pengisian_sampai` < `pengisian_dari` | rentang terbalik |
| `kode_cycle` sudah dipakai | kode wajib unik |
| ubah periode saat `OPEN`/`CLOSED`/`PUBLISHED` | periode beku sesudah kunci |

**Kenapa tahun wajib sesuai periodenya.** Pilar KPI menyaring jadwal KPI memakai **tahun**
siklus, sementara bulan-bulannya diturunkan dari **periode dinilai**. Kalau keduanya
berselisih — misalnya tahun 2026 dengan periode Juli–Desember 2025 — saringannya tidak
menemukan satu baris pun, **setiap** peserta berakhir "Tidak ada KPI disetujui", dan sebab
sebenarnya tidak muncul di layar mana pun. Siklusnya terlihat berjalan; hasilnya kosong
semua. Karena itu ia ditolak saat ditulis, bukan dibiarkan sampai perhitungan.


### 2.24 `GET /cycles/{cycleId}/audit` — lini masa audit

Query: `jenis`, `participant_id`, `q` (sasaran **atau** sebab), `hanya_keputusan_manusia`,
`halaman`, `per_halaman` (maksimum 100).

```json
{ "result": {
    "siklus": { "Id_PA_Cycle": 657, "Kode_Cycle": "PA-DEMO-FE", "Status_Cycle": "CLOSED" },
    "kejadian": [ {
      "waktu": "2026-09-01 23:24:27", "jenis": "KALIBRASI",
      "participant_id": 59653,
      "sasaran": "Diva Mona Lisa",
      "sebab": "penyesuaian hasil rapat kalibrasi divisi",
      "rincian": "Grade B menjadi A",
      "aktor_id": 4, "aktor_nama": "Budi Santoso",
      "oleh_sistem": false, "aktor_tidak_diketahui": false
    } ],
    "halaman": 1, "per_halaman": 25, "total": 102, "total_halaman": 5,
    "dipangkas": false,
    "jenis_tersedia": ["PENUGASAN_DIBATALKAN", "OVERRIDE_PENILAI", "OVERRIDE_PILAR",
                       "OVERRIDE_SYARAT", "KALIBRASI", "PESERTA_DIKECUALIKAN",
                       "HASIL_DITERBITKAN", "BOBOT_PILAR_DIUBAH",
                       "SIKLUS_DIKUNCI", "SIKLUS_DITERBITKAN"],
    "cacah": { "keputusan_manusia": 3, "oleh_sistem": 99 },
    "batas": { "konfigurasi_tanpa_nilai_lama": true, "catatan": "…" }
} }
```

**Tidak ada tabel riwayat baru, dan itu bukan jalan pintas.** Modul ini sejak awal
menonaktifkan alih-alih menghapus, dan menuntut alasan pada tiap perubahan yang menggeser
nilai orang — sehingga riwayatnya sudah tersimpan sebagai baris yang masih ada.

**`oleh_sistem` memisahkan dua hal yang tampak sama.** Kejadian tanpa pelaku adalah
**aturan yang menyala**, bukan keputusan seseorang: satu kali penyiapan siklus melahirkan
99 pengecualian FREELANCE sekaligus, dan tanpa pemisah itu tiga keputusan manusia
tenggelam di baliknya. Sediakan penyaringnya di layar; jangan menyaring diam-diam.

**`cacah` selalu memuat KEDUA angka**, tak peduli saringannya. Layar yang menampilkan
keputusan manusia saja tetap harus bisa berkata "dan 99 lainnya lahir dari aturan" — kalau
tidak, orang menyimpulkan tidak ada yang terjadi.

**`participant_id` dikembalikan, bukan hanya diterima sebagai saringan.** Tanpa itu layar
bisa menyaring per peserta tetapi tidak bisa membuat tautan "lihat kejadian orang ini" dari
sebuah baris — saringan yang hanya bisa diisi dari luar, tak pernah dari apa yang sedang
dilihat. Kosong untuk kejadian setingkat siklus (`SIKLUS_DIKUNCI`, `SIKLUS_DITERBITKAN`).

**Dua jenis ditambahkan 2 September 2026, dan keduanya menutup kebutaan yang sama:
peristiwanya sudah tersimpan sejak awal, hanya tidak pernah dibaca.**

- **`HASIL_DITERBITKAN`** — penerbitan PER ORANG. Sebelumnya satu-satunya jenis penerbitan
  adalah `SIKLUS_DITERBITKAN`, yang bersumber dari kolom yang hanya terisi bila penerbitan
  DITUTUP — dan itu ditolak selama masih ada peserta tertahan. Pada siklus yang tidak
  pernah tuntas (keadaan normal, bukan pengecualian), tindakan paling berkonsekuensi bagi
  seorang karyawan tidak meninggalkan satu baris pun di audit.
- **`BOBOT_PILAR_DIUBAH`** — setingkat LEVEL, jadi `participant_id` kosong dan `sasaran`
  berisi nama levelnya. Alasannya diwajibkan sesudah siklus dikunci justru karena
  perubahan ini menggeser nilai akhir SETIAP peserta pada level itu; alasannya tersimpan
  lalu tidak pernah sampai ke layar.

`aktor_nama` kini memulangkan **nama orangnya**, bukan `Username`. Sebelumnya satu
`aktor_id` yang sama muncul sebagai dua identitas berbeda di satu layar: baris kalibrasi
berbunyi "Ridho" (nama yang dibekukan), baris lainnya "NOIR" (username).

**`aktor_nama` mendahulukan nama yang DIBEKUKAN.** Kalibrasi menyimpan namanya sendiri
justru karena pelaku yang sudah keluar dari perusahaan membuat join ke daftar user kosong
— persis pada baris yang paling perlu dijelaskan. Untuk sumber lain namanya diambil dari
daftar user, dan bisa `null` bila usernya sudah tidak ada.

**Batas yang harus Anda tampilkan.** `batas.konfigurasi_tanpa_nilai_lama` bernilai `true`:
perubahan konfigurasi siklus dan master hanya menyisakan waktu dan pelakunya — **nilai
sebelumnya tidak disimpan**, sehingga lini masa ini tidak bisa mengatakan dari apa menjadi
apa untuk keduanya. Pembaca yang mengira ini riwayat lengkap akan menyimpulkan tidak ada
perubahan pada hal-hal yang justru tidak terekam.


---

### 2.25 Kurva grade siklus (18 September 2026)

Rancangan: `docs/superpowers/specs/2026-09-18-pa-grade-kurva-normal-design.md`. Siklus
ber-`Metode_Grade = 'KURVA'` mendapat grade dari z-score per grup level; siklus `PITA` (semua
siklus sebelum 18 Sep 2026) tetap memakai pita nilai tetap.

| Metode | Alamat | Badan | Gerbang |
|---|---|---|---|
| GET | `/cycles/{cycleId}/kurva` | — | baca |
| POST | `/cycles/{cycleId}/kurva/kunci` | — | `KUNCI_KURVA` (CLOSED saja) |
| POST | `/cycles/{cycleId}/kurva/buka` | `{ "alasan": "…" }` (5–500) | `BUKA_KURVA`; ditolak bila ada hasil `TERBIT` |
| PUT | `/cycles/{cycleId}/kurva/metode` | `{ "metode": "PITA" atau "KURVA" }` | hanya sebelum kunci dan sebelum ada terbit |
| PUT | `/cycles/{cycleId}/kurva/grup` | `{ "grup": [ { "kode", "nama", "level_ids": [] } ] }` | hanya selama belum dikunci |

Semua alamat tulis mengembalikan bentuk yang sama dengan GET (fixture `kurva-siklus.json`):
`metode`, `terkunci`, `boleh_kunci`, `kunci {pada, oleh}` atau `null`, `grup[]` (`id, kode, nama, level[],
n, rata, sd, pita[] {kode, nama, batas_z, nilai_dari, nilai_sampai, urutan}, sebaran {kode:
jumlah}, peringatan[]`), `peringatan[]`, `hasil_sesudah_kunci`, `sudah_terbit`, `tertahan`.

**Sebelum dikunci** pita dan sebaran dihitung langsung dari hasil `DIHITUNG` (pratinjau) dan
`Kode_Grade_Sistem` hasil **NULL**. Grade menurut kurva saat ini datang sebagai
`Grade_Sementara` di `GET /cycles/{id}/participants` dan `GET /participants/{id}/hasil`, dan
sebaran grade `GET /cycles/{id}/distribusi` dihitung darinya (`grade_sementara: true`,
`metode_grade`); tandai "sementara", jangan simpan. **Sesudah dikunci** μ, σ (sampel), dan
pita beku; hasil yang dihitung sesudahnya memakai pita beku. Grup yang kosong saat dikunci
tidak mendapat kurva (`pita: []` + peringatan); hasil yang menyusul di grup itu `TERTAHAN`
dengan alasan. Pada siklus KURVA `grade[].target_persen` Distribusi berisi proporsi teoretis
dari batas z, bukan `Target_Persen` master.
`sd` NULL = grup kurang dari dua orang. Kalibrasi forum dan penerbitan siklus `KURVA` ditolak
(422, pesan menyebut "Kunci kurva") selama kurva belum dikunci. Pita: bawah inklusif, atas
eksklusif; grade teratas terbuka sampai 999,99.

---

### 2.26 Rilis hasil ke leader (21 September 2026)

`Status_Hasil = 'TERBIT'` kini berarti satu hal saja: **karyawan** boleh melihat hasilnya.
Akses **leader** ke angka anggota timnya ditandai terpisah (`N_PA_Result.Published_Leader_At`,
DDL-2026-130), sehingga leader bisa meninjau lebih dulu tanpa orang yang dinilai ikut tahu.

| Metode | Alamat | Badan | Gerbang |
|---|---|---|---|
| POST | `/cycles/{cycleId}/rilis-leader` | — | `RILIS_LEADER` (OPEN, CLOSED) + kurva terkunci |
| POST | `/participants/{participantId}/rilis-leader` | — | sama, per orang |
| POST | `/participants/{participantId}/cabut-rilis-leader` | `{ "alasan": "…" }` (5–500) | sama |

Massal menjawab `{dirilis, sudah_dirilis, belum_layak, total_peserta}`; per orang menjawab
`{participant_id, result_id, published_leader_at}`; pencabutan menjawab
`{participant_id, result_id, alasan}`.

**Tidak ada urutan yang dipaksakan**: menerbitkan ke karyawan tanpa pernah merilis ke leader
tetap sah. Yang `TERTAHAN` ditolak di kedua pintu. Pencabutan ditolak bila hasilnya sudah
`TERBIT` — karyawannya sudah melihat angka itu, jadi menutupnya dari leader tidak
menyembunyikan apa pun. Merilis ulang menghapus jejak pencabutan sebelumnya. Rilis ke leader
**tidak** mengirim pemberitahuan kepada karyawan.

---

## 3. Sisi peserta (`/api/v1/performance-appraisal/saya`)

Empat alamat yang dipakai **seluruh karyawan**, bukan admin. Kunci halamannya
`PerformanceAppraisalSayaPage` dan dipasang `Is_Global_Menu='Y'`, jadi tidak ada baris
akses yang perlu diminta per orang.

### 3.0 Aturan yang mengatasi semua aturan lain di bagian ini

**Tidak satu pun alamat di sini menerima identitas peserta.** Tidak ada
`{participantId}`, tidak ada `kode_karyawan`. Lingkupnya selalu user yang login.

Jangan meminta parameter semacam itu ditambahkan "supaya atasan bisa ikut melihat" —
untuk itu sudah ada Bagian 2 dengan gerbangnya sendiri. Endpoint peserta yang menerima id
lalu mempercayainya adalah cara paling lazim membuat orang membaca hasil penilaian
rekannya hanya dengan mengganti angka di alamat.

`cycle_id` boleh dikirim karena ia **mempersempit, bukan memperlebar**: apa pun isinya,
yang kembali tetap hanya milik yang sedang login.

**Hanya hasil `TERBIT` yang terlihat.** `DIHITUNG` berarti angkanya sudah ada tetapi belum
diterbitkan; menampilkannya meniadakan gunanya langkah penerbitan. Ini berlaku di §3.3
**dan** §3.4 — riwayat yang memuat hasil belum terbit membocorkan lewat pintu belakang
persis yang dijaga di pintu depan.

**`hasil.dikalibrasi`** menandai bahwa grade orang ini pernah disesuaikan manusia — dan
itu perlu ditampilkan. Kalibrasi menggeser grade TANPA menyentuh nilai akhir, sehingga
tanpa penanda ini layar menyajikan nilai 81,50 bersanding dengan grade "A" (pita A mulai
90) dan tidak punya apa pun untuk menjelaskannya; angka yang bertentangan dengan gradenya
sendiri terbaca sebagai sistem yang keliru menghitung. **Alasan** kalibrasinya sengaja
TIDAK dikirim: ia ditulis untuk audit dan bisa memuat pertimbangan yang bukan urusan orang
yang dinilai.

**Peserta tidak pernah melihat nilai per penilai.** Radar per kompetensi dan nilai akhir,
ya; "atasan memberi 90, bawahan memberi 80", tidak pernah. Karena itu §3.3 sengaja tidak
memuat `kategori`, sementara §2.10 memuatnya. Catatan pun tampil tanpa nama penulisnya.

### 3.1 `GET /saya/ringkasan` — dashboard peserta

Query opsional: `cycle_id`.

```json
{ "result": {
    "karyawan": { "kode_karyawan": "0001", "nama": "Frans Bachtiar" },
    "total_assignment": 8, "belum_dimulai": 3, "sedang_dikerjakan": 1,
    "sudah_selesai": 4, "deadline_dekat": 2, "terlambat": 1,
    "progres_persen": 50, "ambang_dekat_hari": 3
} }
```

`progres_persen` sudah dibulatkan **di server**; jangan menghitungnya lagi dari
`sudah_selesai / total_assignment`, karena dua pembulatan untuk satu gagasan akan
berselisih pada angka-angka tertentu dan tidak ada yang bisa menjelaskan mana yang benar.

`deadline_dekat` berarti tenggatnya tinggal `ambang_dekat_hari` hari atau kurang **dan
belum terlambat** — keduanya saling meniadakan, jadi satu penugasan tidak pernah terhitung
di dua penghitung sekaligus. Ambangnya dikirim supaya layar bisa menulis "≤ 3 hari" tanpa
menebak.

### 3.2 `GET /saya/assignment` — penugasan tempat SAYA menjadi penilai

Query: `status` (`BELUM_MULAI`|`BERJALAN`|`SELESAI`), `q` (nama yang dinilai),
`cycle_id`, `halaman`, `per_halaman` (maksimum **100**).

```json
{ "result": {
    "assignment": [ {
      "Id_PA_Assignment": 147, "Status_Penugasan": "BELUM_MULAI",
      "Due_At": "2026-07-31", "Started_At": null, "Finished_At": null,
      "Kode_Kategori": "SELF", "Nama_Kategori": "Diri Sendiri",
      "Id_PA_Participant": 59653, "Kode_Karyawan": "0001",
      "Nama_Karyawan_Saat_Cycle": "Frans Bachtiar",
      "Nama_Jabatan_Saat_Cycle": "Staff IT", "Nama_Divisi_Saat_Cycle": "IT",
      "Kode_Cycle": "PA-2026-H1", "Nama_Cycle": "Semester 1 2026", "Tahun": 2026,
      "Id_Ujian_Token": "abc123",
      "terlambat": false, "hari_terlambat": null, "sisa_hari": 12,
      "diri_sendiri": true
    } ],
    "halaman": 1, "per_halaman": 25, "total": 8, "total_halaman": 1,
    "dipangkas": false
} }
```

Urut **tenggat terdekat lebih dulu**; yang tanpa tenggat jatuh ke belakang, bukan ke depan.

`dipangkas` bernilai `true` bila `per_halaman` yang diminta melebihi 100 dan dipotong.
Tampilkan itu — pembatasan yang tidak diberitahukan membuat orang mengira datanya memang
sebanyak itu.

`diri_sendiri` menandai penilaian diri. Tampilkan sebagai "Diri Sendiri"; tanpa penanda
ini ia terbaca seperti orang lain yang kebetulan senama.

`Id_Ujian_Token` adalah pintu ke HC Learn. Kosong berarti belum disemai.

### 3.3 `GET /saya/hasil` — hasil penilaian saya

Query opsional: `cycle_id`. Tanpa itu, siklus terbaru yang sudah terbit.

```json
{ "result": {
    "tersedia": true, "pesan": null,
    "hasil": { "Kode_Cycle": "PA-2026-H1", "Nama_Cycle": "Semester 1 2026", "Tahun": 2026,
               "Nama_Level_Saat_Cycle": "Staff", "Nilai_KPI": 104.2,
               "Nilai_Kompetensi": 88.5, "Final_Score": 96.4,
               "Kode_Grade_Final": "B", "Published_At": "2026-08-01 09:00:00" },
    "kuadran": { "Kode_Kuadran": "STAR", "Nama_Kuadran": "Star", "Nomor_Box": 9,
                 "Warna": "#16a34a" },
    "kompetensi": [ { "Kode_Competency": "INT", "Nama_Competency": "Integritas",
                      "Kode_Type": "CORE", "Nama_Type": "Core", "Nilai": 90.0 } ],
    "catatan": [ { "Kode_Type": "STRENGTH", "Nama_Type": "Kekuatan",
                   "Isi": "Konsisten menyelesaikan tepat waktu." } ]
} }
```

Belum terbit:

```json
{ "result": { "tersedia": false,
              "pesan": "Hasil penilaian Anda belum diterbitkan.",
              "hasil": null, "kompetensi": [], "catatan": [] } }
```

**Ini 200, bukan 404.** Belum terbit adalah keadaan yang sah dan sering — tampilkan
`pesan` apa adanya, jangan layar galat.

`kompetensi` hanya memuat baris **agregat** lintas kategori penilai. Lihat §3.0.

### 3.4 `GET /saya/riwayat` — tren lintas siklus

```json
{ "result": {
    "periode": [ { "Id_PA_Cycle": 3, "Kode_Cycle": "PA-2025-H2", "Tahun": 2025,
                   "Nama_Jabatan_Saat_Cycle": "Staff IT",
                   "Nilai_KPI": 98.0, "Nilai_Kompetensi": 85.0, "Final_Score": 91.5,
                   "Kode_Grade_Final": "B", "Kode_Kuadran": "STAR",
                   "Nama_Kuadran": "Star", "Nomor_Box": 9,
                   "Published_At": "2026-01-10 09:00:00" } ],
    "jumlah_periode": 4, "rata_rata": 93.2, "terbaik": 96.4,
    "perubahan_terakhir": 3.2
} }
```

`periode` urut **terlama lebih dulu** — tren dibaca kiri ke kanan. Jangan membaliknya di
layar; itu satu langkah yang mudah terlupa di salah satu dari dua layar yang memakainya.

`perubahan_terakhir` adalah selisih terhadap periode **sebelumnya**, bukan terhadap
rata-rata: "naik 3,2 dari periode lalu" yang ditanyakan orang. `null` bila periodenya baru
satu — itu orang baru, bukan kesalahan.

`Nama_Jabatan_Saat_Cycle` dibekukan per periode, jadi riwayat orang yang naik jabatan
menunjukkan jabatannya saat itu, bukan yang sekarang.

### 3.5 Wizard tugas penilaian dan Kalibrasi Penilai (18 September 2026)

Rancangan: `docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md`.
Empat alamat di bawah prefiks `/saya`. Sama seperti §3.0: **tidak ada identitas peserta yang
diterima**. `{kode}` hanya memilih kategori di antara tugas milik yang login; `assignment_id`
pada kalibrasi diperiksa kepemilikannya, dan milik orang lain dijawab 404 — bukan 403,
supaya keberadaannya tidak bocor.

| Metode | Alamat | Fixture |
|---|---|---|
| GET | `/saya/wizard?cycle_id=` | `saya-wizard.json` |
| GET | `/saya/wizard/langkah/{kode}/lembar?cycle_id=` | `saya-lembar.json` |
| GET | `/saya/wizard/langkah/{kode}/pratinjau?cycle_id=` | `saya-pratinjau.json` |
| POST | `/saya/kalibrasi` | `saya-kalibrasi.json` |

`cycle_id` opsional dan hanya mempersempit; tanpa itu dipakai siklus terbuka terbaru tempat
yang login punya tugas. `{kode}` cocok dengan `[A-Z_]{1,40}`; kode yang bukan kategori milik
yang login — atau tidak ada siklus terbuka sama sekali — dijawab 404
`PA_LANGKAH_TIDAK_DIKENAL`.

**`GET /saya/wizard`** — kerangka langkah. `langkah[]` urut menurut
`N_PA_Rater_Category.Urutan_Pengisian` (NULL paling akhir), dan hanya memuat kategori yang
memang punya lembar untuk yang login. `status` salah satu dari `TUNTAS` (semua lembar sudah
terisi), `AKTIF` (langkah pertama yang belum tuntas), atau `TERKUNCI` — beserta
`sebab_terkunci` yang siap ditampilkan. Kuncinya ketat: langkah berikut baru terbuka setelah
yang sebelumnya tuntas. `langkah_aktif` bernilai kode kategori, atau `"RINGKASAN"` bila semua
langkah tuntas. `boleh_kirim` sama dengan `pengiriman.bisa_kirim`; pengiriman tetap satu kali
di akhir lewat `POST /saya/tugas/kirim-semua` yang sudah ada.

Kunci ini berlaku di HCIS saja. HC Learn tidak mengenalnya: tautan lembar yang terlanjur
dibagikan tetap bisa diisi. Karena itu layar tidak boleh menyimpulkan urutan dari status
lembar — baca `status` langkah dari server.

**`GET …/lembar`** — daftar lembar satu kategori, paling banyak 200 (`dipangkas: true` bila
lebih; `total` adalah jumlah sebenarnya). Pada langkah `TERKUNCI`, `url_ujian`,
`short_token`, dan `akses_otp` bernilai `null` — tautannya tidak dikirim, bukan sekadar
disembunyikan layar.

**`GET …/pratinjau`** — tabel kalibrasi satu kategori. `kompetensi[].rata_rata` adalah
rata-rata skor **efektif** yang dinormalkan ke 0–100 atas `jumlah_dinilai` orang.
`orang[].sel[]` membawa `skor_asli` (dari HC Learn, tidak pernah berubah) dan `skor_efektif`
(kalibrasi terbaru bila ada), serta `terkalibrasi`. `boleh_diubah` bernilai `true` hanya bila
kategori ber-`Flag_Boleh_Kalibrasi = 'Y'`, lembarnya `TERKUMPUL`, siklusnya `OPEN`, dan
lembarnya punya sedikitnya satu skor angka ATAU satu jawaban esai.
`orang[].naratif[]` membawa `kode_soal` (kunci revisi esai), `jawaban` (teks POLOS efektif),
`jawaban_html` (HTML aman efektif — revisi apa adanya, atau teks HC Learn yang di-escape per
baris; pakai ini untuk editor dan tampilan), `jawaban_asli` (teks HC Learn), dan `terkalibrasi`.
Pratinjau tidak mencatat "sudah dilihat" — ia murni tampilan.

**`POST /saya/kalibrasi`**

```json
{ "assignment_id": 34740,
  "perubahan": [ { "competency_id": 11, "skor": 82 } ],
  "perubahan_jawaban": [ { "kode_soal": "PA-STAFF-Q07", "jawaban": "Teks yang diperjelas." } ],
  "alasan": "Diselaraskan dengan rekan setim" }
```

`perubahan` (skor) dan `perubahan_jawaban` (esai, sejak 18 Sep 2026) boleh salah satu atau
keduanya — sedikitnya satu wajib ada. Satu alasan untuk satu orang, berlaku bagi semua yang
diubah pada tindakan itu. Kompetensi atau esai yang sama dengan nilai efektif sekarang
diabaikan (esai dibandingkan setelah spasi tepinya dipangkas); bila semuanya sama, jawabannya
422 `PA_TIDAK_ADA_PERUBAHAN` — itu pula yang membuat tekan-ganda aman. Esai **tidak boleh
dikosongkan** dan paling panjang 5.000 karakter teks. `jawaban` boleh HTML dari editor teks
kaya: server MENYARINGNYA (hanya `p br strong b em i u s ol ul li blockquote`, tanpa atribut;
daftar Quill 2 `<ol><li data-list="bullet">` diluruskan menjadi `<ul>`) dan menyimpannya sebagai
HTML. Kosong dihitung dari teks terbacanya, jadi `<p><br></p>` = kosong.
Jawaban sukses memuat `kalibrasi_id`, `assignment_id`, `cycle_id`, `kategori`, `diubah`,
`diubah_jawaban`, **dan `pratinjau` kategori itu UTUH**, bukan hanya baris orang itu, karena
rata-rata per kompetensi ikut berubah. Layar cukup mengganti pratinjaunya dengan yang diterima.

Nilai dan teks asli tidak pernah ditimpa. Pemetaan ulang payload HC Learn tidak menghapus
kalibrasi, karena kalibrasi terikat pada (penugasan, kompetensi) dan (penugasan, kode soal),
bukan pada baris skor. Esai revisi dipakai di catatan hasil dan `jawaban_naratif` detail
admin; teks aslinya terbaca di `kalibrasi_penilai[].perubahan_jawaban[]` (§2.10).

| HTTP | `error_code` | Kapan |
|---|---|---|
| 404 | `PA_PENUGASAN_TIDAK_DITEMUKAN` | Tidak ada, atau bukan milik penilai yang login |
| 404 | `PA_LANGKAH_TIDAK_DIKENAL` | `{kode}` bukan kategori tugas milik yang login |
| 409 | `PA_SIKLUS_TIDAK_TERBUKA` | Gerbang `KALIBRASI_PENILAI` menolak (siklus bukan `OPEN`) |
| 409 | `PA_LEMBAR_BEKU` | Status penugasan bukan `TERKUMPUL` (sudah terkirim atau dilepas) |
| 403 | `PA_KALIBRASI_DITUTUP` | Kategori ber-`Flag_Boleh_Kalibrasi = 'T'` |
| 422 | `PA_SKOR_DI_LUAR_RENTANG` | Di luar `0 … Skor_Maksimal` |
| 422 | `PA_KOMPETENSI_TIDAK_DI_LEMBAR` | `competency_id` tidak berskor di lembar itu |
| 422 | `PA_ALASAN_WAJIB` | Alasan kosong atau lebih dari 500 karakter |
| 422 | `PA_TIDAK_ADA_PERUBAHAN` | Semua skor dan esai sama dengan yang berlaku |
| 422 | `PA_SOAL_TIDAK_DI_LEMBAR` | `kode_soal` bukan esai terjawab di lembar itu |
| 422 | `PA_JAWABAN_KOSONG` | Esai dikosongkan (hanya spasi pun dianggap kosong) |
| 422 | `PA_JAWABAN_TERLALU_PANJANG` | Esai lebih dari 5.000 karakter |

---

## 4. Admin Performance Appraisal — master data

**Kontrak v1 di atas tidak berubah sama sekali.** Bagian ini seluruhnya tambahan, dengan
prefiks alamat sendiri: `/api/v1/performance-appraisal/master`.

### 4.0 Tiga koreksi terhadap kebutuhan yang diajukan

Ditulis di depan karena ketiganya mengubah rancangan layar, bukan sekadar penamaan.

**Pertama — masternya ada DELAPAN, bukan tujuh.** "Paket Soal" dan "Pemetaan
Soal–Kompetensi" adalah dua tabel berbeda dengan lingkup berbeda, dan "Kompetensi"
punya master jenisnya sendiri (`N_PA_Competency_Type`) yang harus bisa disunting.

**Kedua — Paket Soal dan Pemetaan Soal BUKAN master.** Keduanya menggantung pada
**siklus**, bukan berdiri sendiri lintas siklus. `paket-soal` wajib `cycle_id`,
`butir-soal` wajib `package_id`; tanpa itu server **menolak**, bukan mengembalikan
daftar bercampur. Menyuntingnya juga hanya boleh selagi siklusnya belum dikunci —
gerbang `PILIH_PAKET_SOAL` yang menjaganya, sama dengan jalur siklus.

**Ketiga — tidak semuanya bisa ditambah atau dinonaktifkan.** Setiap respons daftar
membawa `dapat_ditambah` dan `dapat_dinonaktifkan`. **Pakai keduanya untuk menghidupkan
tombol; jangan dihafal di layar.** Kalau aturannya berubah, tombolnya ikut berubah tanpa
merilis frontend.

| Master (slug) | Lingkup | Tambah | Nonaktif | Catatan |
|---|---|:---:|:---:|---|
| `jenis-kompetensi` | master | ✔ | ✔ | |
| `kompetensi` | master | ✔ | ✔ | |
| `kategori-penilai` | master | ✖ | ✖ | Kodenya kunci sambung di salinan siklus, penugasan, dan jejak hasil. Hanya nama & deskripsi yang bisa diubah — **kodenya tidak** |
| `aturan-penilai` | master | ✔ | ✔ | **Berversi.** Versi baru lahir `DRAFT` dan tidak aktif |
| `bobot-pilar` | master | ✔ | ✖ | Satu level tepat satu bobot; tabelnya tak punya `Flag_Aktif` |
| `pita-nilai` | master | ✔ | ✔ | Nonaktif ditolak selama masih ada pita lain di grup yang sama |
| `kuadran-hav` | master | ✖ | ✖ | Matriks 3×3 sudah penuh. Hanya nama, deskripsi, warna, urutan |
| `paket-soal` | **siklus** | ✔ | ✔ | Wajib `cycle_id` |
| `butir-soal` | **paket** | ✔ | ✔ | Wajib `package_id` |

### 4.1 `GET /master/referensi` — seluruh daftar acuan, satu panggilan

Ambil **sekali** saat layar admin dibuka. Delapan layar yang masing-masing mengambil
daftar levelnya sendiri berarti delapan kali menanyakan hal yang sama.

```json
{ "result": {
  "level": [ { "ID_Level": 2, "Nama_Level": "STAFF", "Level_Hierarchy": 2 } ],
  "jenis_kompetensi": [ { "Id_PA_Competency_Type": 1, "Kode_Type": "MANAJERIAL", "Nama_Type": "…" } ],
  "kategori_penilai": [ { "Id_PA_Rater_Category": 1, "Kode_Kategori": "ATASAN", "Nama_Kategori": "…" } ],
  "jenis_relasi": [ { "Id_Organization_Relationship_Type": 1, "Kode_Relasi": "ATASAN_LANGSUNG", "Nama_Relasi": "…" } ],
  "status_aturan": [ { "Kode_Status": "DRAFT", "Nama_Status": "…", "Deskripsi": "…" } ],
  "sumbu": [ { "Kode_Type": "FINAL", "Nama_Type": "…" } ],
  "kegunaan": [ { "Kode_Usage": "GRADE", "Nama_Usage": "…" } ],
  "kompetensi": [ { "Id_PA_Competency": 1, "Kode_Competency": "K01", "Nama_Competency": "…",
                    "PA_Competency_Type_Id": 1 } ],
  "jenis_catatan": [ { "Id_PA_Result_Note_Type": 1, "Kode_Type": "STRENGTH", "Nama_Type": "…" } ],
  "siklus": [ { "Id_PA_Cycle": 1, "Kode_Cycle": "PA-2026", "Nama_Cycle": "…",
                "Tahun": 2026, "Status_Cycle": "PREPARED" } ]
} }
```

### 4.2 `GET /master/{master}` — daftar, berhalaman

Query opsional: `q`, `flag_aktif` (`Y`/`T`), `halaman`, `per_halaman`, `id_level`,
`sumbu`, `kegunaan`, dan — sesuai lingkup — `cycle_id` atau `package_id`.

**Bentuk responsnya sama untuk kesembilan slug.** Yang berbeda hanya isi `baris[]`,
yang mengikuti kolom tabelnya apa adanya.

```json
{ "result": {
  "master": "kompetensi", "nama": "Kompetensi", "lingkup": "master",
  "dapat_ditambah": true, "dapat_dinonaktifkan": true,
  "baris": [ { "Id_PA_Competency": 1, "PA_Competency_Type_Id": 1,
               "Kode_Competency": "K01", "Nama_Competency": "Analytical Thinking",
               "Deskripsi": null, "Flag_Berskor": "Y",
               "Urutan_Tampil": 1, "Flag_Aktif": "Y" } ],
  "halaman": 1, "per_halaman": 50, "total": 24, "total_halaman": 1,
  "dipangkas": false
} }
```

`per_halaman` dipangkas server di **200**; ketika itu terjadi `dipangkas` bernilai
`true` — tampilkan.

### 4.3 `GET /master/{master}/{id}` — detail

```json
{ "result": { "master": "kompetensi", "nama": "Kompetensi", "baris": { } } }
```

Dua slug membawa anak:

- `aturan-penilai` → `detail[]`: rincian bobot per kategori (`Kode_Kategori`,
  `Bobot_Persen`, `Min_Rater`, `Maks_Rater`, `Min_Rater_Selesai`,
  `Flag_Izinkan_Manual`, `Organization_Relationship_Type_Id`).
- `paket-soal` → `butir[]`: seluruh butir soal paket itu.

### 4.4 `POST /master/{master}` dan `PUT /master/{master}/{id}`

Badan permintaan memakai **snake_case dari nama kolomnya**: `kode_competency`,
`nama_competency`, `pa_competency_type_id`, dan seterusnya. Field yang tidak dikenal
diabaikan.

```json
{ "result": { "id": 25, "master": "kompetensi", "baru": true } }
```

Yang **tidak bisa** diubah, dan sengaja:

| Master | Terkunci | Sebab |
|---|---|---|
| `kategori-penilai` | `Kode_Kategori` | Menjadi kunci sambung di salinan siklus dan jejak hasil yang sudah tersimpan |
| `kuadran-hav` | `Tingkat_Performa`, `Tingkat_Kompetensi`, `Nomor_Box` | Menentukan sel matriks; menggesernya menabrak kotak lain |
| `aturan-penilai` | `Versi`, `Status_Policy` | Versi lahir otomatis; status berpindah lewat §4.7 |

### 4.5 `POST /master/{master}/{id}/nonaktif`

Tanpa badan. **Menonaktifkan, bukan menghapus** — karena itu POST, bukan DELETE.
Barisnya dirujuk salinan siklus, jejak hasil, dan riwayat kalibrasi; menghapusnya
membuat hasil lama menunjuk sesuatu yang tak ada lagi.

```json
{ "result": { "master": "kompetensi", "id": 25, "flag_aktif": "T" } }
```

### 4.6 `PUT /aturan-penilai/{policyId}/rincian`

Menyimpan **seluruh** rincian sekaligus, bukan per baris.

```json
{ "rincian": [
  { "pa_rater_category_id": 1, "bobot_persen": 45, "min_rater": 1, "maks_rater": 2,
    "min_rater_selesai": 1, "organization_relationship_type_id": 1,
    "flag_izinkan_manual": "Y", "urutan_tampil": 1, "catatan_aturan": null }
] }
```

Sengaja seluruhnya: **bobot antar kategori wajib berjumlah 100**, dan jumlah itu hanya
bisa diperiksa ketika seluruh anggotanya diketahui. Menyimpan satu baris pada satu waktu
berarti setiap simpan di tengah pekerjaan pasti melanggar aturannya sendiri.

Layar yang benar karena itu adalah **satu formulir berisi semua kategori** dengan
penjumlah hidup — bukan tabel yang tiap barisnya punya tombol simpan sendiri.

### 4.7 `POST /aturan-penilai/{policyId}/aktifkan`

Mengaktifkan satu versi dan **menonaktifkan versi aktif sebelumnya pada level yang
sama**. Dua aturan aktif di satu level bukan keadaan yang bisa dijelaskan: penyiapan
siklus akan menyalin salah satunya tanpa ada yang memutuskan yang mana.

```json
{ "result": { "policy_id": 15, "id_level": 2, "versi": 2, "menggantikan": 14 } }
```

Ditolak bila rinciannya kosong atau bobotnya belum berjumlah 100.

### 4.8 Pesan 422 yang akan Anda temui

Semuanya ditulis untuk dibaca manusia dan **boleh ditampilkan apa adanya**.

| Keadaan | Pesan |
|---|---|
| Σ bobot bukan 100 | `Bobot rincian aturan penilai #15 berjumlah 90, seharusnya 100. Rincian: 1=45 + 3=10 + 4=35.` |
| Kode dipakai baris lain | `Kode K01 sudah dipakai baris lain.` |
| Master tertutup | `Kategori Penilai tidak bisa ditambah. Kodenya menjadi kunci sambung di salinan siklus…` |
| Pita berlubang | `Pita FINAL/GRADE berlubang atau tumpang tindih: B berakhir di 80 sedangkan B_PLUS mulai di 81. Batas atas bersifat eksklusif…` |
| Nonaktif pita | `Pita B tidak bisa dinonaktifkan sendirian: FINAL/GRADE masih punya 5 pita lain…` |
| Lingkup kurang | `Paket Soal menggantung pada siklus; sebutkan cycle_id.` |
| Butir dua peran | `Satu butir tidak boleh sekaligus menjadi nilai kompetensi dan catatan kualitatif.` |
| Siklus sudah dikunci | `Operasi PILIH_PAKET_SOAL tidak diizinkan saat siklus berstatus LOCKED. Hanya boleh pada: DRAFT, PREPARED.` |
| Master salah ketik | `Master kompetensii tidak dikenal. Yang tersedia: jenis-kompetensi, kompetensi, …` |

`403` berarti user tidak punya kunci halaman master **itu** — lihat §4.9.

### 4.9 Akses: satu kunci per master

| Slug | Kunci halaman | URL |
|---|---|---|
| `jenis-kompetensi` | `PaMasterJenisKompetensiPage` | `/performance-appraisal/master/jenis-kompetensi` |
| `kompetensi` | `PaMasterKompetensiPage` | `/performance-appraisal/master/kompetensi` |
| `kategori-penilai` | `PaMasterKategoriPenilaiPage` | `/performance-appraisal/master/kategori-penilai` |
| `aturan-penilai` | `PaMasterAturanPenilaiPage` | `/performance-appraisal/master/aturan-penilai` |
| `bobot-pilar` | `PaMasterBobotPilarPage` | `/performance-appraisal/master/bobot-pilar` |
| `pita-nilai` | `PaMasterPitaNilaiPage` | `/performance-appraisal/master/pita-nilai` |
| `kuadran-hav` | `PaMasterKuadranHavPage` | `/performance-appraisal/master/kuadran-hav` |
| `paket-soal` + `butir-soal` | `PaMasterPaketSoalPage` | `/performance-appraisal/master/paket-soal` |

Delapan kunci, bukan satu — dan itu bukan kerumitan tambahan. Menyunting nama kompetensi
menyentuh satu layar; menyunting **pita grade mengubah arti nilai semua orang** pada
siklus berikutnya. Satu kunci untuk semuanya berarti siapa pun yang butuh yang pertama
otomatis mendapat yang kedua.

Menu: header **Performance Appraisal** (halaman siklus) dan **Administrator Performance
Appraisal** (delapan master).

### 4.10 Yang perlu diingat saat merancang layarnya

1. **Menyunting master tidak mengubah siklus berjalan.** Siklus yang sudah disiapkan
   memakai salinannya sendiri. Itu justru alasan master ini boleh disunting sama sekali —
   dan layar sebaiknya mengatakannya, supaya tak ada yang takut menyentuhnya.
2. **Aturan penilai berversi.** Alurnya: buat versi baru (lahir `DRAFT`) → isi rinciannya
   (§4.6) → aktifkan (§4.7). Layar yang menampilkan semua versi sekaligus tanpa
   membedakan mana yang aktif akan membingungkan.
3. **Pita nilai batas atasnya EKSKLUSIF.** Pita 80–90 memuat 80 sampai 89,99. Dua pita
   yang bertetangga harus bertemu di angka yang sama persis.
4. `flag_aktif` bakunya **tidak** disaring — daftar memuat yang aktif dan tidak. Sediakan
   penyaringnya; baris nonaktif sebaiknya terlihat berbeda, bukan disembunyikan.

### 4.11 Urutan wizard dan penanda Kalibrasi Penilai (18 September 2026)

Pengecualian dari §4.10 butir 1: **keduanya kamus hidup** dan berlaku seketika, termasuk
pada siklus yang sedang berjalan (keputusan pemilik proyek, spec wizard W5). Keduanya
diperiksa dengan hak master `kategori-penilai`, bukan sekadar hak membuka layar admin.

| Metode | Alamat | Badan | Hasil |
|---|---|---|---|
| PUT | `/master/jenis-penilai/urutan-kategori` | `{ "urutan": ["SELF","ATASAN","PEER","BAWAHAN"] }` | daftar `{id, kode, nama, urutan_pengisian, boleh_kalibrasi}` dalam urutan baru |
| PUT | `/master/jenis-penilai/kategori/{kategoriId}/kalibrasi` | `{ "boleh": true }` | `{id, kode, boleh_kalibrasi}` |

`urutan` wajib memuat setiap kategori aktif **tepat sekali**; yang kurang, berlebih, atau
kembar dijawab 422 dengan menyebut kodenya, dan tidak ada yang berubah. Penyimpanannya satu
pernyataan, jadi menukar dua posisi aman. `GET /master/jenis-penilai/matriks` kini juga
memuat `urutan_pengisian` (`null` = belum diurutkan, ditaruh paling akhir) dan
`boleh_kalibrasi` pada setiap `kategori[]`, dan daftarnya diurutkan menurut urutan itu.
Mematikan penanda tidak menghapus kalibrasi yang sudah tersimpan.

### 4.12 Grup level kurva (18 September 2026)

`GET /master/grup-kurva` dan `PUT /master/grup-kurva` (`{ "grup": [ { "kode", "nama",
"level_ids": [] } ] }`), hak halaman `PaMasterGrupKurvaPage`. PUT **mengganti seluruh
konfigurasi** sekaligus: satu level hanya di satu grup; kode unik; grup tanpa level ditolak.
Bentuk jawaban (fixture `master-grup-kurva.json`): `grup[] {id, kode, nama, urutan, level[]}`,
`level_tanpa_grup[] {id, nama}`, `cacah_level[] {level_id, jumlah}` (peserta per level di
siklus terbaru). Perubahan master tidak menggeser siklus yang sudah disiapkan — grupnya
disalin per siklus; level tanpa grup menjadi grupnya sendiri di salinan.

Master **Pita Nilai** kini membaca/menerima `Batas_Z` / `batas_z` (−9,99…9,99, kosong = grade
terendah) untuk baris `FINAL|GRADE`; himpunannya wajib tepat satu kosong dan tidak kembar.

## 5. Sisi leader (`/api/v1/performance-appraisal/leader`)

### 5.0 Dua batas yang membentuk seluruh bagian ini

**Angka tampak lewat SALAH SATU dari dua pintu** (sejak 21 September 2026, DDL-2026-130).
Kemajuan pengisian boleh dilihat kapan saja — itu informasi operasional, dan justru itulah
gunanya memantau. Tetapi `nilai_akhir`, `kode_grade`, `kode_kuadran`, dan radar kompetensi
baru keluar bila hasilnya **`TERBIT`** (karyawan sudah boleh melihat) **atau** sudah
**dirilis ke leader** (`dirilis_ke_leader: true`; karyawannya belum melihat apa pun).
Selama belum lewat pintu mana pun, ketiganya `null`, `terbit` `false`, dan
`dirilis_ke_leader` `false` — dan **"belum boleh dilihat" sengaja tidak dibedakan dari
"belum dihitung"**, karena membedakannya sudah membocorkan bahwa angkanya sudah ada.
`terbit` dan `dirilis_ke_leader` dikirim terpisah supaya layar leader bisa mengatakan
"angka ini untuk Anda dulu, orangnya belum diberi tahu".

Pintu leader dibuka admin lewat tiga alamat di §2.26; siklus lama tidak berubah karena
hasil yang sudah `TERBIT` selalu memenuhi syarat "atau" di atas.

**Tidak pernah ada nilai per penilai.** Leader melihat agregat dan rincian per KATEGORI,
tidak pernah "siapa memberi berapa" — dan rincian kategori pun disembunyikan bila
pengisinya kurang dari `batas.min_penilai_untuk_tampil` (bernilai 3).

Kenapa tiga, bukan dua? Ini aritmetika, bukan selera:

| Pengisi | Yang bisa disimpulkan |
|---|---|
| 1 | Nilai kategori **adalah** nilai orang itu |
| 2 | Aman — KECUALI bila leader salah satu pengisinya, dan itu justru lazim: ia tahu nilainya sendiri, maka nilai satunya lagi tinggal dihitung |
| 3 | Sekalipun leader salah satunya, yang bisa ia turunkan hanya JUMLAH dua sisanya |

Kategori `SELF` **selalu tampak** meski berpengisi satu: orang itu adalah anggota yang
sedang dilihat, bukan pihak ketiga yang perlu dilindungi — dan selisih antara penilaian
diri dan penilaian orang lain justru bahan percakapan yang paling berguna.

Sumber "siapa anggota saya" adalah `N_HRIS_Organization_Relationship` — tabel yang **sama**
dengan yang dibaca mesin penugasan saat menentukan siapa berhak menilai siapa. Dibaca dua
arah: `BAWAHAN` dari sisi subjek dan cerminnya `ATASAN` dari sisi terkait.

Semula `HRIS_Karyawan_Team`, dipilih 2 September 2026 ketika tabel relasi masih berisi nol
baris. Diganti 6 September 2026, dan yang menuntutnya bukan jumlah baris melainkan bahwa dua
tabel menjawab satu pertanyaan: 1.717 dari 1.721 penugasan kategori ATASAN punya penilai yang
tidak melihat orangnya di halaman timnya sendiri. Nilai `batas.sumber_tim` dalam balasan ikut
berubah — layar menampilkannya apa adanya, jadi tidak ada yang perlu disesuaikan di sisi
klien.

### 5.1 `GET /leader/tim` — L01, daftar anggota tim

Query opsional: `cycle_id`. Tanpa itu server memilih **siklus terbaru yang MEMUAT tim
ini** — bukan sekadar siklus terbaru. Jangan menirunya di layar dengan mengambil siklus
pertama dari daftar siklus; keduanya sering berbeda, dan yang berbeda menyajikan layar
kosong yang terbaca seperti kesalahan.

```json
{ "result": {
    "leader": { "kode_karyawan": "A4", "nama": "Aji" },
    "cycle": { "cycle_id": 1931, "kode_cycle": "PA-2026-H1", "nama_cycle": "PA Semester 1",
               "status_cycle": "CLOSED", "periode_dinilai_dari": "2026-01-01",
               "periode_dinilai_sampai": "2026-06-30" },
    "anggota": [
      { "participant_id": 244354, "kode_karyawan": "B12", "nama": "Budi",
        "nama_jabatan": "Staff", "status_peserta": "DIIKUTKAN", "status_hasil": "TERBIT",
        "penugasan_total": 8, "penugasan_selesai": 6, "penugasan_terlambat": 1,
        "progres_persen": 75,
        "nilai_akhir": 86.4, "kode_grade": "B", "kode_kuadran": "STRIVING_STAR",
        "terbit": true, "alasan_tertahan": null }
    ],
    "ringkasan": { "total_anggota": 4, "hasil_terbit": 1, "hasil_tertahan": 1,
                   "anggota_punya_keterlambatan": 2, "anggota_penilaian_lengkap": 1 },
    "batas": { "sumber_tim": "HRIS_Karyawan_Team", "min_penilai_untuk_tampil": 3,
               "nilai_hanya_setelah_terbit": true, "tanpa_nilai_per_penilai": true },
    "tanpa_tim": false } }
```

**`tanpa_tim: true` berarti orang ini memang bukan leader.** Itu keadaan yang sah, bukan
galat dan bukan "sedang memuat" — halaman ini akan dibuka juga oleh orang yang tidak punya
tim. Tampilkan keterangannya; jangan spinner yang tak pernah berhenti, dan jangan layar
galat.

`alasan_tertahan` **tetap terisi meski belum terbit**. Itu bukan nilai, itu pekerjaan yang
menunggu seseorang — dan leader adalah orang yang paling mungkin menindaklanjutinya.

`progres_persen` dan `penugasan_terlambat` dihitung server. `Due_At` bertipe DATE dan
tenggat tanggal berarti sampai AKHIR hari itu, jadi jangan menghitung ulang sendiri di
layar: yang tenggatnya hari ini belum terlambat.

### 5.2 `GET /leader/anggota/{participantId}` — L02, detail satu anggota

`{participantId}` memang ada di sini, berbeda dari sisi peserta yang melarangnya. Yang
menjaganya adalah pemeriksaan **keanggotaan** — bukan pemeriksaan siklus. Memeriksa siklus
saja akan meloloskan seluruh peserta siklus itu. Peserta di luar tim dijawab **422** dengan
pesan "bukan anggota tim Anda".

```json
{ "result": {
    "participant_id": 244354, "cycle_id": 1931, "kode_karyawan": "B12", "nama": "Budi",
    "nama_jabatan": "Staff", "status_peserta": "DIIKUTKAN", "status_hasil": "TERBIT",
    "terbit": true, "published_at": "2026-07-01 00:00:00",
    "nilai_akhir": 86.4, "kode_grade": "B", "kode_kuadran": "STRIVING_STAR",
    "kompetensi": [
      { "kode_kompetensi": "CV-01", "nama_kompetensi": "Integritas",
        "kode_type": "CORE_VALUE", "nama_type": "Core Value",
        "nilai": 88.0, "jumlah_penilai": 5 }
    ],
    "kategori": [
      { "kode_kategori": "SELF", "nama_kategori": "Diri Sendiri",
        "jumlah_penilai_diharapkan": 1, "jumlah_penilai_mengisi": 1,
        "bobot_terpakai": 20.0, "nilai": 88.0,
        "disembunyikan": false, "alasan_disembunyikan": null },
      { "kode_kategori": "ATASAN", "nama_kategori": "Atasan",
        "jumlah_penilai_diharapkan": 1, "jumlah_penilai_mengisi": 1,
        "bobot_terpakai": 40.0, "nilai": null,
        "disembunyikan": true,
        "alasan_disembunyikan": "Hanya 1 penilai mengisi kategori ini, di bawah 3 yang dibutuhkan agar nilainya tidak menunjuk orang tertentu." }
    ],
    "alasan_tertahan": null,
    "batas": { "...": "sama seperti 5.1" } } }
```

**`dikalibrasi`** ada juga di sini, dengan alasan yang sama seperti §3.3 — dan di sisi
leader justru lebih penting: leader adalah orang yang paling mungkin ditanyai anggotanya
kenapa nilainya tidak cocok dengan gradenya. Alasan kalibrasinya tetap tidak ikut.

**Baris kategori yang `disembunyikan: true` TETAP DITAMPILKAN — jangan disaring keluar.**
Nilainya `null` dan alasannya ada di `alasan_disembunyikan`. Kategori yang lenyap tanpa
keterangan membuat leader mengira bagian penilaian itu tidak ada, padahal ada dan memang
tidak boleh ia lihat; ketiadaan yang tak dijelaskan lebih membingungkan daripada penolakan
yang jujur. `jumlah_penilai_mengisi` tetap disebut — itu bukan kebocoran, justru itu yang
membuat penyembunyiannya masuk akal dan memberi tahu leader kategori mana yang kurang
pengisi.

Ambangnya baca dari `batas.min_penilai_untuk_tampil`, jangan ditulis sebagai angka tetap
di kode layar: ia angka kebijakan yang boleh dinaikkan pemilik proses.

Selama `terbit: false`, `kompetensi` dan `kategori` **dua-duanya senarai kosong** — radar
pun tidak keluar, karena ia membocorkan hasilnya sepotong-sepotong. Yang tetap ada hanya
kemajuan pengisian dan `alasan_tertahan`.

---

## 6. Yang BELUM ada — jangan ditunggu, dan jangan dikarang

| Kebutuhan layar | Status | Sementara ini |
|---|---|---|
| Daftar peserta satu siklus (berhalaman, dengan penyaring) | **ada** — §2.9 | |
| Detail hasil satu peserta (radar kompetensi, jejak kategori) | **ada** — §2.10 | |
| Layar kalibrasi | **ada** — §2.11 | |
| Layar HAV Map (sebaran seluruh peserta di 9 kotak) | **ada** — §2.12 | |
| Menghitung SATU SIKLUS PENUH lewat HTTP | **tidak akan ada** | Terukur ±0,5 detik per orang; 859 peserta = ±7 menit, melewati batas waktu permintaan mana pun. Pakai `php artisan pa:hitung-siklus <kode>`. Endpoint `POST /cycles/{id}/hitung` tetap benar untuk siklus kecil dan hitung ulang sebagian. |
| Pemetaan kode soal → kompetensi | **ada** — §4 | `/master/butir-soal` menerima `pa_competency_id` |
| Membuat / menyunting siklus | **ada** — §2.21–§2.23 | |
| Dashboard & detail Leader PA | **ada** — §5 | |
| Approval generik (A11) | **DICABUT 2026-09-02** | Bukan ditunda — diputuskan tidak diperlukan. Alasannya di §6.1 di bawah. Jangan menunggunya, dan jangan mengarang endpoint-nya |
| Nilai LAMA saat konfigurasi disunting | **belum ada** | §2.24 melaporkan bahwa perubahan terjadi dan siapa pelakunya, bukan dari apa menjadi apa. Menyimpannya butuh DDL baru |

Frontend yang membutuhkan salah satunya menuliskannya di papan kerja. Backend yang
membuatnya menambahkannya ke dokumen ini lebih dulu.

### 6.1 Kenapa A11 Approval dicabut, bukan ditunda

Diputuskan pemilik proyek pada 2 September 2026, sesudah jalur frontend terhenti
menunggunya.

**Persetujuan yang dibutuhkan sudah ada — hanya tidak bernama "approval".** Tiga
mekanisme, semuanya sudah berjalan dan berkontrak:

| Yang sudah ada | Perannya sebagai persetujuan |
|---|---|
| Penerbitan **per orang** (§2.13) | Hasil yang dihitung TIDAK otomatis sampai ke karyawan. Seseorang harus menerbitkannya, satu per satu, dan itu keputusan sadar |
| **Tutup penerbitan** (§2.13) | Gerbang terakhir tingkat siklus; ditolak selama masih ada peserta tertahan |
| **Kalibrasi bersebab** (§2.11) | Setiap penyesuaian grade wajib beralasan, pelakunya tercatat, dan `Kode_Grade_Sistem` tetap utuh sebagai pembanding |

Menambahkan lapisan approval di atas ketiganya berarti membangun **gerbang keempat untuk
persoalan yang sama** — dan setiap gerbang tambahan adalah tempat baru bagi hasil untuk
tersangkut tanpa ada yang tahu siapa yang harus menggerakkannya.

**Dua hal yang menguatkan keputusan ini, dan keduanya fakta, bukan pendapat:**

1. Nol tabel approval di modul ini, dan `N_PA_Result_Status` hanya punya empat status yang
   tak satu pun berarti "menunggu persetujuan". Deck dan mockup tidak menyebutnya.
2. Bentuk approval yang paling sering dibayangkan — *atasan menyetujui hasil anggotanya* —
   **tidak bisa dibangun hari ini**: ia butuh "siapa atasan siapa", dan
   `N_HRIS_Organization_Relationship` berisi nol baris sementara tiga calon sumbernya hanya
   28–60% saling sepakat (lihat `php artisan pa:analisis-relasi`). Membangunnya sekarang
   berarti menebak.

**Konsekuensi bagi frontend:** tidak ada endpoint approval, tidak ada fixture approval,
dan `paApi.js` tidak akan mendapat fungsi approval. Layar yang hendak dibuat untuk
"menyetujui hasil" sebenarnya adalah layar **penerbitan** (§2.13) — itulah tempat
keputusannya diambil.

Kalau kelak ternyata memang perlu, menambahkannya di atas alur yang sudah benar jauh lebih
murah daripada membongkar alur yang terlanjur dibangun di atas tebakan.

---

## 7. Riwayat perubahan kontrak

| Tanggal | Perubahan | Alasan |
|---|---|---|
| 2026-09-21 | **§2.26 baru: rilis hasil ke leader** (3 alamat) dan §5.0: angka leader keluar lewat `TERBIT` ATAU `dirilis_ke_leader`; kunci baru `dirilis_ke_leader` di daftar tim dan detail anggota. Aditif | Leader perlu meninjau lebih dulu tanpa karyawannya ikut melihat; sebelumnya satu penanda menjaga keduanya |
| 2026-09-19 | §2.25: kunci kurva **hanya CLOSED**; `boleh_kunci` di kurva; `Grade_Sementara` di daftar peserta & detail hasil; `metode_grade` + `grade_sementara` di Distribusi. Aditif | Grade sementara yang dijanjikan spec belum sampai ke layar; kunci selagi OPEN membekukan kurva sebagian peserta |
| 2026-09-18 | **§2.25 baru: kurva grade** (5 alamat) dan **§4.12 grup level kurva** (2 alamat); master pita menambah `Batas_Z`. Aditif; siklus lama tetap `PITA` | Grade harus relatif terhadap sebaran siklus (z-score per grup level), bukan pita tetap |
| 2026-09-18 | §3.5 dan §2.10: **Kalibrasi Penilai atas jawaban esai** — `perubahan_jawaban` di `POST /saya/kalibrasi`, `naratif[]` pratinjau menambah `kode_soal`/`jawaban_asli`/`terkalibrasi`, hasil simpan menambah `diubah_jawaban`, riwayat menambah `perubahan_jawaban[]`; tiga kode galat baru. Aditif | Keputusan pemilik proyek: esai boleh direvisi, tidak boleh dikosongkan, penanda dan alasan sama dengan skor, versi terbaru dipakai di mana-mana |
| 2026-09-18 | **§3.5 baru: wizard tugas penilaian dan Kalibrasi Penilai** (empat alamat `/saya/wizard*` dan `/saya/kalibrasi`). Aditif; `/saya/tugas` dan `kirim-semua` tidak berubah | Penilai mengisi berurutan per kategori dan boleh menyelaraskan skornya sebelum mengirim; nilai asli disimpan untuk audit |
| 2026-09-18 | `kompetensi[]` di §2.10 dan §3.3 kini tercatat membawa `Deskripsi`. Koreksi, bukan perubahan: servernya sudah mengirim sejak 11 Sep 2026 tetapi fixture tertinggal | Uji kontrak merah untuk kunci yang tidak dijanjikan fixture |
| 2026-09-18 | §2.10 menambah `kalibrasi_penilai[]`. Aditif | Admin perlu melihat skor asli dan revisi penilai berikut alasannya |
| 2026-09-18 | **§4.11 baru**: dua alamat admin untuk urutan wizard dan penanda kalibrasi; `matriks` jenis penilai menambah `urutan_pengisian` dan `boleh_kalibrasi`. Aditif | Urutan dan izin kalibrasi harus bisa diatur tanpa DML |
| 2026-09-02 | **A11 Approval DICABUT** (§6.1), bukan ditunda. Tidak ada endpoint, fixture, maupun fungsi `paApi.js` yang akan dibuat | Persetujuan yang dibutuhkan sudah dijawab penerbitan dua langkah dan kalibrasi bersebab; lapisan keempat hanya menambah tempat hasil tersangkut. Bentuk "atasan menyetujui" pun mustahil hari ini karena relasi organisasi nol baris |
| 2026-09-02 | v3.0 — **Bagian 5 baru: sisi leader** (§5.0–§5.2, dua alamat `/leader/*`). Bagian lama 5 dan 6 bergeser jadi 6 dan 7. Aditif | L01–L02 sebelumnya terhalang keputusan batas keterlihatan; keputusannya diambil 2 Sep 2026 |
| 2026-09-02 | `alasan_tertahan` (§2.8) boleh memuat DUA sebab sekaligus; tipenya tetap string | Sebab KPI dan sebab kompetensi dipilih dengan `??` sehingga yang KPI selalu menutupi yang lain — admin memperbaiki satu masalah, menghitung ulang satu siklus penuh, lalu baru tahu masalah kedua |
| 2026-09-01 | v1 — kontrak awal, 8 endpoint | Memisahkan pekerjaan BE dan FE supaya bisa paralel |
| 2026-09-01 | v1.1 — tambah §2.9 daftar peserta, §2.10 detail hasil, §2.11 kalibrasi. Aditif; tidak ada bentuk lama yang berubah | Tiga layar yang diantre FE di papan kerja |
| 2026-09-01 | v2.9 — tambah §2.24 lini masa audit (A12), TANPA tabel riwayat baru. Aditif | Riwayat ternyata sudah tersimpan sebagai baris nonaktif berikut sebabnya; membuat tabel baru akan menyalin data yang sama ke tempat kedua |
| 2026-09-01 | v2.8 — tambah §2.21–§2.23: detail, buat, dan sunting siklus (A09). Aditif | Jalur FE membutuhkannya untuk layar Setup Siklus; sebelumnya hanya ada daftar dan penyiapan |
| 2026-09-01 | v2.7 — tambah §2.17–§2.20: dashboard admin, monitoring, distribusi, riwayat admin. Aditif | Empat layar mockup sisi admin yang sebelumnya nol endpoint |
| 2026-09-01 | v2.6 — koreksi: `Due_At` bertipe DATE (`"2026-07-31"`), bukan datetime. Contoh di §2.10 dan §3.2 dibetulkan | Tipe kolomnya date; contoh berjam menyesatkan layar untuk membandingkan sendiri dan menandai orang terlambat sehari lebih awal |
| 2026-09-01 | v2.5 — tambah §2.15 (bobot pilar per siklus) dan §2.16 (sunting keikutsertaan peserta). Aditif | Dua operasi gerbang yang arsitekturnya sudah putuskan ada tetapi belum punya satu baris kode pun |
| 2026-09-01 | v2.4 — **Bagian 3 baru: sisi peserta** (§3.0-§3.4, empat alamat `/saya/*`). Aditif. Penomoran bagian dirapikan: dua bagian sempat sama-sama bernomor 4 | Seluruh sisi peserta sebelumnya nol endpoint - empat layar mockup yang justru dilihat 958 orang |
| 2026-09-01 | v2.3 — `penilai[]` di §2.10 menambah `Due_At`, `terlambat`, `hari_terlambat`. Aditif | Keterlambatan dibutuhkan mockup di tiga layar; diturunkan server supaya tidak ada dua rumus |
| 2026-09-01 | v2.2 — tambah §2.14 penyimpangan per peserta (4 alamat). Aditif | Fitur 3 pada peta kerja; gerbang `TULIS_OVERRIDE` sebelumnya tanpa jalur kode |
| 2026-09-01 | v2.1 — tambah §2.13 penerbitan hasil (4 alamat). Aditif | Fitur 8 pada peta kerja; sebelumnya gerbang `TERBITKAN` tidak punya satu baris pun jalur kode |
| 2026-09-01 | v2.0 — tambah bagian 4: master data Admin PA (9 slug, list/detail/create/update/nonaktif, rincian aturan, aktivasi versi, referensi). Prefiks alamat terpisah; **kontrak v1 tidak tersentuh** | Layar master data yang diminta jalur frontend |
| 2026-09-01 | v1.2 — tambah §2.12 HAV Map. `POST /cycles/{id}/hitung` kini mengembalikan `diproses`, `terakhir_id`, `selesai` (aditif) | Layar terakhir yang diantre FE; ketiga field baru untuk pemanggilan bertahap |
