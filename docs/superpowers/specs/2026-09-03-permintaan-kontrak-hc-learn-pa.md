# Permintaan Kontrak API — Performance Appraisal 360° (HCIS ↔ HC Learn)

**Dari:** Tim HCIS, EVO Group
**Kepada:** Tim HC Learn
**Tanggal:** 3 September 2026
**Perihal:** usulan bentuk request dan response untuk fitur Performance Appraisal

---

## Ringkasan permintaan

Ada **lima** hal yang kami butuhkan. Satu di antaranya perubahan struktural; sisanya
tambahan yang, bila mekanismenya sudah ada di HC Learn, cukup dipakai apa adanya.

| # | Permintaan | Sifat |
|---|---|---|
| 2 | Satu karyawan bisa memegang **beberapa token aktif** pada paket soal yang sama | perubahan struktural |
| 3 | Hasil dikirim **per butir soal**, bukan satu angka akhir | bentuk response baru |
| 3b | Pemberitahuan saat penilai **membuka** formulir | tambahan ringan |
| 3c | Token bisa **dicabut** sebelum diisi | tambahan |
| 4 | Cara membaca **daftar kode soal** satu paket | mungkin sudah ada |

Bagian 5 memuat tujuh hal yang perlu kami pastikan sebelum membangun sisi HCIS-nya.

Kami sudah menyelesaikan sisi HCIS sampai perhitungan dan penerbitan hasil, jadi setiap
field di bawah sudah punya kode yang mengonsumsinya — bukan perkiraan kebutuhan.

---

## 1. Latar belakang singkat

HCIS sedang membangun fitur **Performance Appraisal 360°**. Seperti Training dan SOP
Kuis, form penilaiannya akan dikerjakan di HC Learn, dan HCIS menerima hasilnya lewat
callback.

Bedanya dengan Training dan SOP Kuis ada dua, dan keduanya membuat kontrak yang berjalan
sekarang belum cukup:

**Pertama, yang mengisi form bukan yang dinilai.** Pada Training, orang yang mengerjakan
ujian adalah pemilik nilainya. Pada Performance Appraisal, seorang karyawan menilai
**orang lain** — atasannya, rekannya, bawahannya, dan dirinya sendiri.

**Kedua, kami butuh nilai per butir soal, bukan satu angka.** Callback yang berjalan
sekarang mengirim satu field `Score` (0–100). Performance Appraisal menghitung nilai per
kompetensi, sehingga rincian tiap butir harus sampai ke HCIS.

Kabar baiknya, untuk hal pertama **HC Learn tidak perlu memahami siapa menilai siapa.**
Token yang HCIS kirimkan sudah mewakili satu penugasan penilaian, dan HCIS yang
menyimpan artinya. HC Learn cukup mengembalikan token itu apa adanya bersama hasilnya.

---

## 2. Permintaan pertama: satu karyawan, beberapa token aktif

Ini satu-satunya perubahan struktural yang kami butuhkan.

Seorang manajer dengan lima bawahan akan menerima **lima form terpisah** dari paket soal
yang sama. Kontrak yang berjalan sekarang mengasumsikan satu karyawan mendapat satu
ujian per paket.

Usulan bentuk request, mengembangkan `api/v1/penjadwalan/training/store` yang sudah ada:

```json
{
  "Id_Master_Ujian": "PA-STAFF-2027",
  "Started_At": "2026-09-01 08:00:00",
  "Finished_At": "2026-09-30 17:00:00",
  "Jenis_User": "internal",
  "Id_Penugasan": [
    { "Kode_Karyawan": "A1", "Id_Integrasi": 9001, "Label_Subjek": "Budi Santoso — Staff, IT" },
    { "Kode_Karyawan": "A1", "Id_Integrasi": 9002, "Label_Subjek": "Sari Wulandari — Staff, IT" },
    { "Kode_Karyawan": "A1", "Id_Integrasi": 9003, "Label_Subjek": "Diri Sendiri" }
  ]
}
```

- `Kode_Karyawan` adalah **orang yang mengisi form** dan login ke HC Learn.
- `Id_Integrasi` adalah nomor penugasan milik HCIS. **Satu token untuk satu baris ini**,
  bukan satu token per karyawan.
- `Label_Subjek` hanya teks untuk ditampilkan di layar, supaya pengisi tahu sedang
  menilai siapa. HC Learn tidak perlu menyimpannya sebagai relasi atau memvalidasinya.

Response yang kami harapkan, per `Id_Integrasi`:

```json
{
  "result": [
    { "Id_Integrasi": 9001, "Id_Ujian_Token": "tok-aaa", "Link_Ujian": "https://..." },
    { "Id_Integrasi": 9002, "Id_Ujian_Token": "tok-bbb", "Link_Ujian": "https://..." },
    { "Id_Integrasi": 9003, "Id_Ujian_Token": "tok-ccc", "Link_Ujian": "https://..." }
  ]
}
```

---

## 3. Permintaan kedua: hasil per butir soal

Ini bagian yang benar-benar baru. Bentuknya kami susun mengikuti respons rekrutmen yang
pernah Tim HC Learn tunjukkan, karena struktur itu sudah sangat mendekati kebutuhan
kami.

```json
{
  "Kode_Karyawan": "A1",
  "Id_Penjadwalan_Integrasi": 9001,
  "Id_Ujian_Token": "tok-aaa",
  "Id_Ujian_Nilai_Akhir": "hasil-7f3c9a",
  "Api_Version": "pa-v1",
  "Finished_At": "2026-09-12 10:45:00",
  "Total_Nilai": 66.20,
  "Detail": [
    {
      "Kode_Soal": "Q-8871",
      "Jenis_Soal": "skala",
      "Skor": 4,
      "Skor_Maksimal": 5,
      "Bobot_Penilaian": 20
    },
    {
      "Kode_Soal": "K02",
      "Jenis_Soal": "skala",
      "Skor": 3,
      "Skor_Maksimal": 5,
      "Bobot_Penilaian": 30
    },
    {
      "Kode_Soal": "QL01",
      "Jenis_Soal": "teks",
      "Skor": null,
      "Skor_Maksimal": null,
      "Bobot_Penilaian": null,
      "Jawaban_Teks": "Konsisten menutup temuan audit sebelum tenggat."
    }
  ]
}
```

### Penjelasan tiap field

| Field | Score_Usage di HCIS |
|---|---|
| `Id_Ujian_Token` | Kunci sambung utama. Dari sini HCIS menemukan penugasannya. |
| `Id_Penjadwalan_Integrasi` | Pencocokan tambahan. Bila berbeda dengan token, token yang dipakai. |
| `Id_Ujian_Nilai_Akhir` | Penanda satu hasil, dipakai mencegah nilai ganda bila kiriman terulang. Mohon unik per hasil. |
| `Api_Version` | Penanda versi bentuk, supaya perubahan di kemudian hari tidak perlu ditebak. |
| `Finished_At` | Waktu pengisian selesai. |
| `Total_Nilai` | Kami pakai sebagai pembanding, bukan sumber utama; HCIS menghitung ulang dari `Detail`. |
| `Detail[].Kode_Soal` | Dicocokkan dengan susunan paket (bagian 4) untuk menemukan kompetensinya. Cukup kode yang sama dengan yang dikirim di bagian 4 — kompetensinya tidak perlu diulang di sini. |
| `Detail[].Skor` dan `Skor_Maksimal` | **Mohon selalu dikirim berpasangan.** Lihat catatan di bawah. |
| `Detail[].Bobot_Penilaian` | Bobot antar butir, dipakai memverifikasi perhitungan. |
| `Detail[].Jawaban_Teks` | Isi jawaban untuk butir yang berupa uraian. |

### Teks pertanyaan tidak perlu dikirim di sini

Sama seperti bagian 4: kami hanya butuh `Kode_Soal` untuk mencocokkannya dengan susunan
paket. Bunyi pertanyaannya tidak kami simpan dan tidak kami tampilkan di mana pun.

### Catatan tentang skala penilaian

Kami **tidak meminta HC Learn menyeragamkan skala.** Selama `Skor` selalu disertai
`Skor_Maksimal` milik butir itu, HCIS menormalkannya sendiri — dan satu paket boleh
memuat butir berskala 1–5 bersama butir berskala 0–100 tanpa masalah.

Satu-satunya permintaan: untuk butir berangka, `Skor_Maksimal` mohon tidak dikirim nol
atau kosong. Untuk butir uraian, `Skor` dan `Skor_Maksimal` memang kosong dan itu sudah
benar.

---

## 3b. Permintaan: beri tahu saat penilai MEMBUKA formulir

Butir yang sejak awal diketahui hilang, dan kini ditulis.

Tanpa ini, `N_PA_Assignment.Started_At` dan status `BERJALAN` **tidak punya sumber sama
sekali**. Monitoring hanya bisa membedakan "sudah selesai" dari "belum selesai", padahal
yang paling perlu dibedakan justru di dalam kelompok "belum": orang yang **belum menyentuh
sama sekali** butuh diingatkan, orang yang **sedang mengerjakan** tidak. Mengingatkan
keduanya dengan kalimat yang sama membuat pengingat kehilangan wibawanya.

Yang kami butuhkan cukup satu panggilan ringan, sekali per token, saat formulir pertama
kali dibuka:

```json
{ "Id_Ujian_Token": "tok-aaa", "Started_At": "2026-09-05 09:12:00" }
```

Tidak perlu dijamin sampai — kalau hilang, HCIS hanya kehilangan pembeda itu, bukan
hasilnya. Cukup sekali; panggilan berulang untuk token yang sama akan kami abaikan.

---

## 3c. Permintaan: token bisa DICABUT sebelum diisi

Kebutuhan yang muncul saat membangun, pada 2 September 2026, dan tidak terduga sebelumnya.

Penugasan penilaian **bisa dibatalkan** setelah tokennya terlanjur dikirim — paling sering
karena pesertanya dikecualikan dari siklus, atau karena penilainya diganti. HCIS sudah
menolak hasil yang datang dari penugasan yang dibatalkan, dan sudah memberi tahu
penilainya.

Tetapi selama tokennya masih hidup di HC Learn, penilai tetap melihat formulirnya,
**tetap mengisinya sampai selesai**, lalu kiriman hasilnya ditolak. Tenaganya terbuang
seluruhnya, dan ia baru tahu di akhir. Ini terbukti terjadi pada uji rantai penuh: enam
penugasan yang sudah dibatalkan tetap bisa dikirimkan hasilnya.

```json
{ "Id_Ujian_Token": ["tok-aaa", "tok-bbb"], "Alasan": "Peserta dikecualikan dari siklus" }
```

Yang kami harapkan: token itu berhenti bisa dibuka, dan pengisi melihat keterangan singkat
alih-alih formulir kosong atau layar galat. `Alasan` boleh ditampilkan apa adanya — HCIS
selalu mengisinya, karena pembatalan di sisi kami wajib beralasan.

Bila HC Learn sudah punya mekanisme menonaktifkan ujian, kami cukup memakai yang ada;
bentuk di atas hanya usulan.

---

## 4. Permintaan ketiga: susunan kompetensi per paket

Agar HCIS bisa memetakan hasil ke kompetensi **sebelum** siklus penilaian berjalan, bukan
setelah hasil pertama masuk, kami butuh cara membaca susunan satu paket.

### Yang kami minta — dan yang TIDAK kami minta

Kami **tidak meminta bunyi pertanyaannya**, dan mohon jangan dikirim sekalipun mudah.

Teks soal yang tersimpan di database HCIS berarti seluruh bank soal HC Learn ikut tersalin
ke sistem kami dan terbaca siapa pun yang membuka halaman konfigurasi paket. Kebocoran itu
tidak bisa ditarik kembali setelah terjadi, dan kami tidak membutuhkannya sama sekali:
HCIS hanya perlu tahu **soal mana mengukur kompetensi apa**, berapa banyak, dan skalanya.

```json
{
  "Id_Master_Ujian": "PA-STAFF-2027",
  "Detail": [
    { "Kode_Soal": "Q-8871", "Kode_Kompetensi": "K01",
      "Jenis_Soal": "skala", "Skor_Maksimal": 5, "Bobot_Penilaian": 20 },
    { "Kode_Soal": "Q-8872", "Kode_Kompetensi": "K01",
      "Jenis_Soal": "skala", "Skor_Maksimal": 5, "Bobot_Penilaian": 20 },
    { "Kode_Soal": "Q-8905", "Kode_Kompetensi": "CV04",
      "Jenis_Soal": "skala", "Skor_Maksimal": 5, "Bobot_Penilaian": 15 },
    { "Kode_Soal": "Q-9110", "Kode_Kompetensi": "QL01",
      "Jenis_Soal": "uraian", "Skor_Maksimal": null, "Bobot_Penilaian": null }
  ]
}
```

### Penjelasan tiap field

| Field | Guna di HCIS |
|---|---|
| `Kode_Soal` | Pengenal soal milik HC Learn. Buram bagi kami — dipakai hanya untuk mencocokkan skor yang masuk di bagian 3 dengan butir yang benar. Bentuknya bebas. |
| `Kode_Kompetensi` | **Yang paling kami butuhkan.** Kompetensi yang diukur soal itu. Tanpa field ini skor yang masuk tidak bisa dipetakan ke kompetensi mana pun, dan seluruh perhitungan pilar kompetensi berhenti. |
| `Jenis_Soal` | `skala` atau `uraian`. Yang uraian tidak diberi skor, dibaca sebagai catatan. |
| `Skor_Maksimal` | Skala butir itu. Boleh berbeda antar butir dalam satu paket — HCIS menormalkan sendiri. Kosongkan untuk butir uraian. |
| `Bobot_Penilaian` | Bobot antar butir, dipakai memverifikasi perhitungan kami. Boleh kosong. |

### Satu kompetensi boleh punya beberapa soal

Ini bentuk yang kami harapkan, bukan pengecualian: `K08` diukur tiga soal, `K09` dua soal,
`CV01` satu soal. Karena itu `Kode_Soal` dan `Kode_Kompetensi` dikirim sebagai dua field
terpisah — menyatukannya membuat satu kompetensi hanya bisa punya satu soal, dan susunan
paket yang sebenarnya tidak bisa diwakili.

Kami menjumlahkan skor beberapa soal menjadi satu nilai kompetensi; pembagiannya kami
hitung sendiri, jadi tidak perlu dikirim.

### Daftar kode kompetensi

`Kode_Kompetensi` mengacu pada daftar yang sudah kami sepakati bersama HC:
`K01`–`K11` (Kompetensi Manajerial), `CV01`–`CV06` (Core Value), `BH01`–`BH03`
(Behavior), `QL01`–`QL04` (Qualitative). Butir dengan kode di luar daftar itu akan kami
tolak saat impor, dengan pesan yang menyebut kodenya — bukan didiamkan, supaya
ketidakcocokan ketahuan sebelum siklus berjalan, bukan setelah hasil masuk.

Bila endpoint `api/v1/penjadwalan/training/options` yang sudah ada bisa memuat bagian
`Detail` seperti di atas, itu sudah cukup dan tidak perlu endpoint baru.

---

## 5. Hal yang perlu kami pastikan

1. Apakah satu karyawan bisa memiliki **beberapa token aktif** pada paket soal yang
   sama, satu token per penugasan?
2. Apakah `Label_Subjek` bisa ditampilkan di layar pengerjaan, cukup sebagai teks?
3. Apakah `Id_Ujian_Nilai_Akhir` bisa dijamin unik per hasil, dan tetap bernilai sama
   bila kiriman diulang?
4. Apakah `Detail` bisa memuat butir uraian dengan skor kosong?
5. Bila paket soal disunting setelah token dikirim, apakah token lama tetap
   mengembalikan susunan soal yang lama?
6. Apakah tenggat (`Finished_At`) ditampilkan kepada pengisi di layar HC Learn? HCIS
   menghitung keterlambatan dari tenggat per penugasan, dan pengisi yang tidak melihat
   tenggatnya akan tercatat terlambat tanpa pernah diberi tahu.
7. Apakah token yang sudah dicabut (bagian 3c) tetap mengembalikan hasil bila entah
   bagaimana sempat terkirim? HCIS akan menolaknya, tetapi kami ingin tahu apakah
   kirimannya masih terjadi supaya penolakan itu tidak terbaca sebagai galat.

---

## 6. Yang tidak berubah

Autentikasi, mekanisme tanda tangan, dan alamat callback tetap seperti yang berjalan
sekarang. Kami tidak meminta perubahan pada bagian itu.

Terima kasih. Kami siap berdiskusi bila ada bagian yang lebih mudah diwujudkan dengan
bentuk lain — yang kami butuhkan adalah isinya, bukan bentuk persisnya.
