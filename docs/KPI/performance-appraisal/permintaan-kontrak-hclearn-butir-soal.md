# Permintaan perubahan kontrak HC Learn — butir soal pada asesmen 360 (PA)

Tanggal: 20 September 2026 · Dari: tim HCIS · Untuk: tim HC Learn
Modul: KPI / Performance Appraisal · Sifat: **aditif**, tidak mengubah field yang sudah ada

## 1. Yang kami butuhkan

Layar Kalibrasi Penilai di HCIS menampilkan hasil asesmen 360 kepada penilai, dan penilai boleh
merevisi skor serta jawaban naratifnya sebelum dikirim. Permintaan pemilik proses: tampilkan
**per pertanyaan** — teks pertanyaannya dan jawaban penilai atas pertanyaan itu — supaya yang
dikalibrasi adalah butir yang jelas, bukan satu angka gabungan.

Hari ini itu tidak bisa dilakukan, karena unit terkecil yang HC Learn kirim adalah **bagian**.

## 2. Bukti keadaan sekarang (panggilan langsung, 20 September 2026)

Domain staging: `https://cat-evo-stagging-595840247695.asia-southeast1.run.app`

| Pemeriksaan | Hasil |
|---|---|
| `GET /api/v1/pa/nama-tes?limit=3&page=1` | HTTP 200. Kedalaman: ujian → paket → alat_tes → **bagian**. Tidak ada daftar soal. |
| `GET /api/v1/pa/nama-tes/100166` | HTTP 200. Sama dalamnya, berhenti di bagian. |
| `GET /api/v1/pa/soal/{id}`, `/pa/nama-tes/detail/{id}`, `/pa/bagian/{id}/soal`, `/pa/master-soal/{id}` | HTTP 404 — tidak ada. |
| `/docs/api` dan `/docs` | HTTP 200, tetapi tidak menyebut satu pun alamat `api/v1/pa/...`. |
| Callback `pa-callback-hasil` (dokumentasi HC Learn, disalin 10 Sep 2026) | Satu baris `Detail` = satu bagian. `Jawaban_Teks` satu teks per bagian. |

Bentuk bagian yang kami terima sekarang:

```json
{
  "id_jenis_soal": 100158,
  "kode_soal": "K01",
  "nama": "KOMPETENSI 1",
  "keterangan": "Analytical Thinking",
  "jumlah_soal": 5,
  "boleh_kembali": true,
  "tampil_semua_soal": true
}
```

`jumlah_soal: 5` memberi tahu kami ada lima pertanyaan di dalamnya, tetapi kelima pertanyaan itu
tidak pernah dikirim. Demikian pula pada callback hasil:

```json
{
  "Kode_Soal": "BE",
  "Jenis_Soal": "Business Ethic",
  "Skor": 21,
  "Skor_Maksimal": 100,
  "Jumlah_Soal": 20,
  "Jumlah_Terjawab": 20,
  "Jawaban_Teks": null
}
```

Dua puluh pertanyaan sudah diringkas menjadi satu skor, dan bila bagian itu beresai, seluruh
esainya menjadi satu `Jawaban_Teks`.

## 3. Yang kami minta

### 3.1 Katalog — daftar butir per bagian

`GET /api/v1/pa/nama-tes` dan `GET /api/v1/pa/nama-tes/{id}`: tiap `bagian` membawa `butir`.

```json
{
  "id_jenis_soal": 100158,
  "kode_soal": "K01",
  "nama": "KOMPETENSI 1",
  "keterangan": "Analytical Thinking",
  "jumlah_soal": 5,
  "butir": [
    { "kode_butir": "K01-01", "nomor": 1, "pertanyaan": "Seberapa cepat yang bersangkutan mengenali akar masalah?", "tipe": "SKALA", "skor_maksimal": 100 },
    { "kode_butir": "K01-05", "nomor": 5, "pertanyaan": "Tuliskan contoh nyata cara ia menganalisis masalah.", "tipe": "ESAI", "skor_maksimal": null }
  ]
}
```

Tanpa ini, pertanyaan hanya bisa ditampilkan kalau kebetulan sudah ada jawabannya. Pertanyaan
yang diajukan tetapi belum dijawab siapa pun akan tak terlihat sama sekali, dan admin tidak bisa
membedakan "tidak ditanyakan" dari "belum dijawab".

### 3.2 Callback hasil — jawaban per butir

`pa-callback-hasil`: tiap item `Detail` membawa `Butir`.

```json
{
  "Kode_Soal": "K01",
  "Jenis_Soal": "Analytical Thinking",
  "Skor": 85,
  "Skor_Maksimal": 100,
  "Jumlah_Soal": 5,
  "Jumlah_Terjawab": 5,
  "Jawaban_Teks": null,
  "Butir": [
    { "Kode_Butir": "K01-01", "Nomor": 1, "Pertanyaan": "Seberapa cepat yang bersangkutan mengenali akar masalah?", "Skor": 80, "Skor_Maksimal": 100, "Jawaban_Teks": null },
    { "Kode_Butir": "K01-05", "Nomor": 5, "Pertanyaan": "Tuliskan contoh nyata cara ia menganalisis masalah.", "Skor": null, "Skor_Maksimal": null, "Jawaban_Teks": "Ia memecah masalah menjadi tiga bagian sebelum memutuskan." }
  ]
}
```

### 3.3 Syarat yang menentukan

1. **`Kode_Butir` harus stabil.** Sama antar pengiriman, antar penilai, dan antar peserta selama
   soalnya soal yang sama. Kode inilah jangkar kalibrasi kami, sebagaimana `Kode_Soal` sekarang.
   Kode yang berubah-ubah membuat jawaban dan pertanyaan tidak pernah bertemu lagi setelah
   tersimpan, dan riwayat kalibrasi menjadi tidak bisa ditelusuri.
2. **`Pertanyaan` ikut di callback, bukan hanya di katalog.** Soal bisa disunting setelah
   penilaian berjalan; yang kami tampilkan harus pertanyaan yang benar-benar dibaca penilai saat
   itu, bukan versi terbarunya.
3. **Aditif.** `Skor`, `Skor_Maksimal`, dan `Jawaban_Teks` tingkat bagian tetap dikirim seperti
   sekarang. Perhitungan nilai kami memakai angka tingkat bagian, dan kami tidak akan
   menjumlahkan sendiri dari butir.
4. **Boleh bertahap.** Kalau mudah dikerjakan salah satu lebih dulu, dahulukan **3.2** (callback).
   Dengan itu saja, jawaban per butir sudah bisa ditampilkan dan dikalibrasi. Katalog (3.1) hanya
   dibutuhkan untuk menampilkan pertanyaan yang belum dijawab.

## 4. Dampak di sisi HCIS setelah kontrak berubah

- Kolom `Kode_Butir`, `Nomor_Butir`, dan `Teks_Pertanyaan` pada `N_PA_Assignment_Score`, serta
  kunci butir pada `N_PA_Kalibrasi_Penilai_Jawaban`.
- Pemeta payload menyimpan butir, layar Kalibrasi Penilai menampilkan pertanyaan beserta
  jawabannya, dan kalibrasi naratif berpindah jangkar dari bagian ke butir.
- Selama HC Learn belum mengirim `Butir`, layar tetap jalan seperti sekarang (per bagian).

## 5. Yang kami butuhkan dari HC Learn

1. Konfirmasi apakah `Kode_Butir` yang stabil memang tersedia di sisi mereka.
2. Perkiraan waktu, dan apakah staging bisa lebih dulu.
3. Satu contoh JSON sungguhan dari staging setelah perubahan, untuk kami jadikan fixture uji.
