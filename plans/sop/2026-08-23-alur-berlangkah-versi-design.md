# Alur Berlangkah Versi Dokumen SOP — desain

Tanggal: 2026-08-23
Status: **TERLAKSANA, dengan koreksi keputusan 2026-08-24**

> **Koreksi keputusan 2026-08-24:** konsep "rencana pengesah" terpisah di
> langkah Pengajuan dibatalkan. Tanggal Permintaan dan Tanggal Respon berada di
> Pengajuan. Pengesah diisi satu kali di langkah Pengesahan. Bagian-bagian lama
> di bawah yang menyebut RENCANA dipertahankan sebagai jejak rancangan awal,
> bukan keadaan implementasi akhir.

Prasyarat yang sudah berjalan: `DDL-2026-032` (tahap versi), `DDL-2026-034` &
`DDL-2026-036` (tanggal proses), `DDL-2026-038` (catatan review)

---

## 1. Masalah

Delapan tahap versi sudah hidup dan sudah menegakkan aturannya, tetapi **layar
tidak memperlihatkannya sebagai perjalanan**. Yang dilihat admin adalah satu form
panjang berisi seluruh field sekaligus: isi, berkas, lima tanggal, catatan
review, blok pengesah, nomor SK — semuanya sejajar, tanpa petunjuk mana yang
relevan sekarang.

Akibatnya tiga hal:

1. **Tahap terasa seperti hiasan.** Ada badge-nya, ada tombol pindahnya, tetapi
   form di sebelahnya tak berubah sedikit pun antara Drafting dan Approval.
2. **Syarat naik tahap baru diketahui setelah ditolak.** Server menolak dengan
   pesan yang jelas, tetapi admin baru membacanya sesudah menekan tombol.
3. **Pertanyaan "menunggu tanda tangan siapa" tak bisa dijawab.** Blok pengesah
   hanya berisi yang **sudah** menandatangani; tak ada tempat menyatakan siapa
   yang **akan**.

## 2. Keputusan arah

**Berlangkah sebagai jalur utama, bukan sebagai kurungan.**

Rancangan awal yang sempat dipertimbangkan — menyembunyikan field per tahap —
ditolak karena melahirkan sumber kebenaran kedua: master tahap sudah menyimpan
"apa yang penting kapan" (`Butuh_Berkas`, `Butuh_Pengesahan`), dan layar yang
menyimpan aturan yang sama akan menyimpang begitu master diubah.

Tiga aturan yang mengikat seluruh rancangan ini:

| Aturan | Alasan |
|---|---|
| Urutan langkah **diturunkan** dari `N_SOP_Master_Tahap_Versi.Urutan` | tak ada daftar langkah kedua di kode |
| Pemetaan langkah ↔ kelompok field **disimpan di master** | pola yang sama dengan `Cap_Tanggal` dan `Butuh_Berkas` |
| Langkah **bisa dilompati**, tidak terkunci maju | dokumen yang tiba sudah bertanda tangan, dan koreksi 42 dokumen lama |

Yang **tetap terkunci** hanyalah yang sudah terkunci sekarang: transisi tahap
yang syaratnya dibaca dari master, dan penerbitan versi yang menuntut berkas +
pengesah. Layar memandu; server memutuskan.

## 3. Bentuk layar

Layar versi menjadi daftar langkah. Langkah berjalan terbuka lebar; yang sudah
lewat mengerut menjadi satu baris ringkasan **beserta tanggalnya**.

```
✓ 1. Pengajuan          10 Jan 2026 · 3 rencana pengesah
✓ 2. Review             15 Jan 2026 · 2 putaran
▸ 3. Pengesahan         menunggu — pengesah ✗ · nomor SK ✗
  4. Terbit & Sebar     terkunci sampai langkah 3 tuntas
```

Tanggal pada baris ringkasan **bukan field baru**: ia `Tanggal_Permintaan`,
`Tanggal_Respon`, `Tanggal_Akhir_Approval`, dan `Tanggal_Obsolete` yang sudah
tercap otomatis oleh perpindahan tahap. Layar ini karena itu sekaligus menjadi
riwayat perjalanan dokumen, bukan hanya form pengisian — sebuah wizard biasa
lenyap setelah selesai, yang ini tinggal.

### Empat langkah dan isinya

| # | Langkah | Tahap yang diwakili | Kelompok field |
|---|---|---|---|
| 1 | Pengajuan | Drafting, Review | identitas versi, ringkasan perubahan, isi/berkas, **rencana pengesah** |
| 2 | Review | Review, Approval, Hold | catatan review berulang + lampiran, tanggal permintaan & respon |
| 3 | Pengesahan | Approval, Approved | realisasi pengesahan, nomor SK, tanggal pengesahan & akhir approval |
| 4 | Terbit & Sebar | Filing and Registered | tanggal berlaku, kadaluarsa, tombol terbit |

Satu langkah bisa mewakili lebih dari satu tahap: Hold bukan langkah tersendiri
melainkan keadaan yang menempel pada langkah berjalan.

## 4. Perubahan data

### 4.1 Pemetaan langkah — kolom baru di master tahap

```text
N_SOP_Master_Tahap_Versi.Langkah      int         NULL   -- 1..4
N_SOP_Master_Tahap_Versi.Kelompok     varchar(60) NULL   -- kunci kelompok field
```

Nilainya seed, bukan kode. Tahap baru yang ditambah admin cukup menyatakan ia
duduk di langkah berapa; kalau `Langkah` kosong, tahap itu tak memunculkan
langkah sendiri dan menempel pada langkah berjalan (perilaku Hold dan Cancel).

Nama kelompok disaring **daftar putih** di service, sama seperti `Cap_Tanggal`:
salah ketik di master berakhir sebagai kelompok yang dilewati, bukan sebagai
rujukan ke komponen karangan.

### 4.2 Rencana pengesah — satu kolom, bukan tabel baru

`N_SOP_Item_Versi_Pengesah` sudah ada, terisi 36 baris pada 16 versi. Menambah
tabel kedua yang isinya sama persis akan melahirkan dua daftar penanda tangan
untuk satu versi, dan layar harus tahu keduanya.

```text
N_SOP_Item_Versi_Pengesah.Jenis   varchar(12) NOT NULL DEFAULT 'REALISASI'
                                              -- 'RENCANA' | 'REALISASI'
```

| Jenis | Diisi di | Menjawab |
|---|---|---|
| `RENCANA` | langkah 1 | siapa yang **akan** menandatangani |
| `REALISASI` | langkah 3 | siapa yang **sudah**, kapan, nomor SK berapa |

Backfill: 36 baris yang ada seluruhnya menjadi `REALISASI` — itu memang artinya
selama ini, dan default kolomnya dipilih supaya jalur lama yang tak menyebut
`Jenis` tetap menulis hal yang sama.

**Konsekuensi yang disengaja:** penjaga terbit (`pastikanAdaPengesah`) hanya
menghitung `REALISASI`. Rencana tak pernah cukup untuk menerbitkan — kalau tidak,
dokumen bisa terbit atas nama orang yang belum tanda tangan.

## 5. Checklist syarat — diturunkan, bukan ditulis ulang

Tiap langkah menampilkan apa yang kurang untuk maju, dibaca dari master tahap
yang sama dengan yang menolak:

```
Untuk naik ke Approved:  berkas ✓ · pengesah ✗
```

Endpoint baru:

```text
GET /api/v1/sop/admin/dokumen/versi/{idVersi}/langkah
```

Mengembalikan: langkah sekarang, seluruh langkah beserta ringkasannya, tahap
yang sedang ditempati, tujuan yang sah, dan **syarat tiap tujuan beserta status
terpenuhinya**. Satu panggilan, karena layar butuh semuanya sekaligus untuk
menggambar daftar langkah.

Tak ada aturan syarat yang ditulis di klien. Layar hanya menggambar apa yang
dikirim server.

## 6. Yang TIDAK berubah

Bagian ini penting supaya tak ada yang mengira pekerjaan ini menyentuh penjagaan
yang sudah teruji:

- transisi tahap dan syaratnya — `SopTahapVersiService::pindah()`
- penjaga terbit — `pastikanIsiSah()`, termasuk kecocokan jenis berkas
- pencapan tanggal — `Cap_Tanggal`, tetap "hanya bila kosong"
- perakitan nomor dokumen dan `Nomor_SOP_Resmi`
- rekonsiliasi paket perujuk saat terbit

Seluruhnya **dibaca**, tak satu pun diubah.

## 7. Backfill dan dampak ke data lama

| Data | Perlakuan |
|---|---|
| 8 tahap | diisi `Langkah` dan `Kelompok` lewat DML |
| 36 baris pengesah | menjadi `REALISASI` |
| 76 versi | tak disentuh — langkahnya diturunkan dari tahap yang sudah ada |
| 42 dokumen lama | tak disentuh; langkah tetap bisa dilompati untuk koreksi |

Tak ada data yang berpindah arti. Yang bertambah hanya cara membacanya.

## 8. Rencana pengujian

| Lapis | Yang dijaga |
|---|---|
| Skema | kolom `Langkah`, `Kelompok`, `Jenis` ada; default `Jenis` = `REALISASI` |
| Seed | delapan tahap punya langkah yang masuk akal; Hold & Cancel tanpa langkah |
| Daftar putih | `Kelompok` karangan dilewati, tak melempar |
| Rencana vs realisasi | terbit menolak versi yang pengesahnya hanya `RENCANA` |
| Backfill | 36 baris lama seluruhnya `REALISASI` |
| Endpoint langkah | syarat tiap tujuan benar dan ikut status terpenuhinya |
| Lompat langkah | membuka langkah 3 dari langkah 1 tetap boleh |
| Browser | daftar langkah tergambar, ringkasan membawa tanggal, checklist menyebut yang kurang |

## 9. Risiko dan batas yang diketahui

**Layar versi adalah komponen besar.** `SopDokumenVersiModal.vue` sudah panjang
dan dipakai dua tempat. Penataan ulangnya dikerjakan sebagai penambahan lapisan
langkah di atas kartu yang sudah ada, bukan penulisan ulang — supaya blok yang
sudah teruji (pengesah, konten, terbit) tak ikut tersentuh.

**`SopDokumenVersiDuaLayar.test.js` sudah gagal 10 dari 11 sebelum pekerjaan
ini.** Kegagalannya terbukti identik di baseline. Pekerjaan ini tak memperbaikinya
dan tak boleh dinilai dengan angka itu; kalau angkanya berubah, itu sinyal.

**Unggah lampiran review masih belum terverifikasi end-to-end** karena kredensial
GCS di development rusak. Tak berubah oleh pekerjaan ini.

## 10. Urutan kerja

1. `DDL-2026-039` — `Langkah`, `Kelompok`, `Jenis`
2. `DML-2026-040` — seed pemetaan langkah + backfill `Jenis`
3. `SopTahapVersiService` — daftar langkah, checklist syarat, daftar putih kelompok
4. Endpoint `GET .../versi/{id}/langkah`
5. Penjaga terbit membaca `REALISASI` saja
6. Layar: lapisan langkah + ringkasan bertanggal + checklist
7. Rencana pengesah di langkah 1
8. Tes, dokumentasi, gerbang verifikasi, commit

Perkiraan: **1,5–2 hari**. Tanpa menyentuh penegakan yang sudah teruji.

## 11. Yang perlu diputuskan sebelum mulai

1. **Empat langkah** seperti tabel di bagian 3 — atau Anda ingin pembagian lain?
2. **Rencana pengesah memakai kolom `Jenis`** pada tabel yang sudah ada, bukan
   tabel baru. Setuju?
3. **Langkah 4 memuat tanggal berlaku dan kadaluarsa.** Alternatifnya keduanya
   ikut langkah 1 sebagai rencana masa berlaku. Mana yang cocok dengan
   kebiasaan Anda?
