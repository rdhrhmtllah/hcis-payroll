# Pertanyaan untuk HC Learn — Kontrak Paket Soal PA 360

**Tanggal:** 9 September 2026
**Dasar:** dokumentasi `GET /api/v1/pa/nama-tes` di `https://cat-evo-stagging-595840247695.asia-southeast1.run.app/docs/api`
**Kanal:** PA 360 · USERKEY `ef19441355b9c578` · Kode `A7`

Konteks singkat untuk HC Learn: di sisi HCIS, penilaian PA menuntut tiap **kompetensi** punya sumber nilainya sendiri. Kompetensi wajibnya berbeda menurut level jabatan — 3 untuk Pramukarya, 17 untuk Staff sampai Senior Officer, dan 21 untuk Assisten Manager ke atas (Core Value 6 + Manajerial 11 + Kualitatif 4). Admin HCIS akan memetakan satuan soal dari HC Learn ke kompetensi-kompetensi itu, lalu nilainya dipulangkan per kompetensi.

Empat pertanyaan pertama menentukan bentuk tabelnya, jadi kami menahan implementasi sampai ada jawabannya.

---

## A. Menghambat — kami tidak bisa mulai tanpa ini

### A1. Apakah `bagian[]` bisa membawa id per-instance, bukan id bank soal?

Yang tersedia sekarang hanya `bagian[].id_jenis_soal`, dan dokumentasi Anda sendiri menyatakan itu pengenal **bank soal**: *"Bank yang sama bisa dipakai beberapa alat tes."* Tidak ada `id_bagian`, `id_komponen`, maupun `nomor`.

**Kenapa ini menghambat.** Kami perlu menyimpan "bagian X mengukur kompetensi Y" secara permanen. Kalau pengenalnya bisa berulang, dua bagian berbeda akan terbaca sebagai bagian yang sama, dan nilainya pulang ke kompetensi yang salah — **tanpa galat apa pun**. Kami sengaja tidak mau memakai posisi dalam array sebagai pengenal, karena penyisipan satu bagian di tengah akan menggeser seluruh pemetaan diam-diam.

**Yang kami harapkan:** satu field id yang unik dan stabil untuk tiap bagian di dalam satu paket, mis. `bagian[].id_bagian`.

**Bila tidak bisa:** apakah pasangan `(alat_tes.id_jenis_indikator, bagian.id_jenis_soal)` dijamin unik dalam satu paket — yakni satu alat tes tidak pernah memakai bank soal yang sama dua kali? Kalau dijamin, kami pakai pasangan itu.

### A2. Untuk PA 360, satuan yang dipetakan ke kompetensi itu **bagian** atau **alat tes**?

Katalog yang ada sekarang jelas katalog rekrutmen — nama paketnya `"PAKET REKRUTMEN: TIU A + KRAEPELIN (STAFF)"`, isinya TIU, Kraeplin, PAPI Kostick, DISC. Struktur seperti itu tidak berpadanan dengan kompetensi PA.

**Yang perlu kami tahu:** untuk PA 360 nanti, apakah satu bagian mewakili satu kompetensi, atau satu **alat tes** yang mewakili satu kompetensi (sehingga satu paket berisi banyak alat tes)?

Jawabannya menentukan pada tingkat mana pemetaan kami dibuat, dan tidak bisa kami tebak dari katalog rekrutmen.

### A3. Berapa banyak satuan yang bisa dimuat satu paket?

Kami butuh sampai **21 satuan** untuk level Assisten Manager ke atas, 17 untuk Staff, dan 3 untuk Pramukarya.

Dari katalog sekarang, TIU punya sekitar tiga bagian, dan tes khusus (Kraeplin, PAPI, DISC) punya `jumlah_bagian = 0` karena — sesuai dokumentasi Anda — *"soalnya memang tidak dipecah per bagian"*.

**Yang perlu kami tahu:** apakah paket PA 360 bisa disusun sampai 21 satuan? Kalau ada batas atas, berapa?

### A4. Yang kami ikat itu `id_master_ujian` atau `id_master_paket_ujian`?

Dokumentasi menyebut `result[].id_master_ujian` sebagai *"satuan yang dijadwalkan kepada peserta"*, dan `/api/v1/pa/hasil/{id_ujian_token}` juga berpangkal di token ujian. Karena itu kami berencana menyimpan **`id_master_ujian`** sebagai acuan ikatan, dan `id_master_paket_ujian` hanya sebagai keterangan.

**Mohon dikonfirmasi**, karena kalau kami mengikat paket, kami mengikat sesuatu yang tidak bisa dijadwalkan — dan itu baru ketahuan pada saat penjadwalan, bukan saat pengikatan.

---

## B. Penting — memengaruhi keandalan, bukan bentuk tabelnya

### B1. Bagaimana bagian yang dinonaktifkan terlihat lewat API?

Skenario yang kami jaga: admin HC Learn menonaktifkan satu bagian **setelah** admin HCIS memetakannya ke kompetensi.

**Yang perlu kami tahu:** bagian itu hilang dari `bagian[]`, atau tetap muncul dengan penanda status? Kalau hilang begitu saja, kami bisa mendeteksinya; kalau tetap muncul tanpa penanda, kami tidak punya cara tahu, dan satu kompetensi akan pulang tanpa nilai tanpa ada yang menyadarinya.

Hal yang sama untuk paket/ujian yang dinonaktifkan: hilang dari daftar, atau ada penandanya?

### B2. Apakah `tabs[].domain[]` pada endpoint hasil membawa pengenal bagian?

`GET /api/v1/pa/hasil/{id_ujian_token}` didokumentasikan memuat *"rincian per bagian: bobot, skor maksimal, skor peserta, interpretasi"*, tetapi field di dalam `domain[]` tidak dirinci.

**Yang perlu kami tahu:** apakah tiap entri `domain[]` membawa `id_jenis_soal` (atau id bagian dari A1)? Tanpa itu kami tidak bisa memulangkan nilai ke kompetensi yang benar — kami hanya menerima daftar nilai tanpa tahu nilai mana milik bagian mana.

### B3. Kredensial PA 360 sekarang ditolak

Pasangan USERKEY `ef19441355b9c578` dengan header `X-Kode-Karyawan: A7` dijawab:

```
HTTP 401 {"success":false,"status":401,"message":"Akses ditolak: Kredensial tidak valid"}
```

Pesannya **bukan** "Invalid Signature", jadi tanda tangan HMAC-nya diterima dan yang ditolak pasangan identitasnya. Mohon dipastikan pasangan mana yang berlaku untuk kanal PA 360 di lingkungan staging.

### B4. Apakah katalog PA akan dipisah dari katalog rekrutmen?

`GET /api/v1/pa/nama-tes` sekarang mengembalikan alat tes rekrutmen. Apakah nanti kanal PA hanya memuat ujian yang memang ditujukan untuk PA 360, atau kami yang harus menyaringnya sendiri? Kalau kami yang menyaring, dengan field apa?

---

## C. Catatan kecil

### C1. Contoh di dokumentasi akan gagal tanda tangan

Contoh yang tertulis:

```
curl -X GET "https://hclearn.example.com/api/v1/pa/nama-tes?page=1&limit=25"
```

Server Anda mengurutkan parameter query secara abjad sebelum memverifikasi tanda tangan, sehingga `?page=1&limit=25` ditolak sementara `?limit=25&page=1` diterima. Kami sudah membuktikannya langsung ke server staging Anda pada 9 September 2026 dan sudah menyesuaikan sisi kami. Mungkin baik bila contohnya diperbaiki, karena galatnya muncul sebagai "Invalid Signature" yang menuduh kunci, bukan urutan parameter.

---

## Ringkasan yang kami tunggu

| No | Pertanyaan | Menentukan |
|---|---|---|
| A1 | id bagian per-instance | bentuk tabel detail |
| A2 | bagian atau alat tes sebagai satuan kompetensi | tingkat pemetaan |
| A3 | berapa satuan maksimal per paket | apakah aturan kelengkapan bisa dipenuhi |
| A4 | ikat `id_master_ujian` atau paket | isi kolom acuan |
| B1 | bagian nonaktif terlihat bagaimana | bisa/tidaknya pemeriksaan kesehatan |
| B2 | `domain[]` bawa pengenal bagian | bisa/tidaknya nilai dipulangkan per kompetensi |
| B3 | kredensial staging PA 360 | bisa/tidaknya kami menguji |
| B4 | katalog PA terpisah atau tidak | perlu/tidaknya penyaringan di sisi kami |
