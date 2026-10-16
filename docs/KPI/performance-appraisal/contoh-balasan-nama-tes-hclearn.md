# Contoh balasan `GET /api/v1/pa/nama-tes` yang bisa dipakai HCIS

> Berkas pendamping: [`contoh-balasan-nama-tes-hclearn.json`](contoh-balasan-nama-tes-hclearn.json)
>
> Ditulis 10 September 2026 untuk dikirim ke HC Learn sebagai acuan pembuatan data dummy.
> Bentuknya **disalin apa adanya** dari balasan `nama-tes` yang sekarang sudah hidup di
> staging — tidak ada field baru yang diminta. Yang berbeda hanya **isinya**.

## Masalahnya dalam satu kalimat

Ujian "Employee Assessment 360" yang ada sekarang membawa **11 bagian**, dan kesebelasnya
adalah kompetensi **Manajerial** (K01–K11). Matriks kompetensi di HCIS menuntut **17** untuk
level STAFF s/d SENIOR OFFICER dan **21** untuk ASSISTEN MANAGER ke atas — jadi setiap ujian
itu selalu kurang 6 atau 10 bagian, dan tidak ada satu pun ujian di katalog yang bisa diikat
ke level mana pun di atas PRAMUKARYA.

| Dimensi | Kompetensi | Ada di ujian 360 sekarang? |
|---|---|---|
| Manajerial | K01–K11 (11) | ✅ ada, lengkap |
| Core Value | CV01–CV06 (6) | ❌ belum ada |
| Kualitatif | QL01–QL04 (4) | ❌ belum ada |

11 + 6 = **17** (level 2–5) · 11 + 6 + 4 = **21** (level 6–13)

## Empat aturan yang harus dipenuhi

**1. Cacah bagiannya SAMA PERSIS dengan cacah kompetensi levelnya — bukan minimal.**
Satu bagian mewakili tepat satu kompetensi, dan hubungannya dua arah: bagian berlebih tidak
punya kompetensi untuk dipegang, kompetensi yang tersisa tidak punya bagian yang memegangnya.
Keduanya ditolak. Jadi ujian untuk STAFF harus berisi **tepat 17** bagian, dan untuk ASSISTEN
MANAGER **tepat 21**.

**2. Identitas satu bagian adalah PASANGAN `(id_jenis_indikator, id_jenis_soal)`.**
Bukan `id_jenis_soal` saja. Satu bank soal boleh muncul di dua alat tes berbeda dan itu sah —
yang dilarang adalah pasangan yang sama muncul dua kali **di dalam satu ujian**, karena kedua
bagian itu jadi tidak bisa dibedakan dan pemetaannya tidak bisa dipercaya.

**3. Bagiannya boleh dipecah ke beberapa alat tes, dan itu justru dianjurkan.**
Contoh di berkas JSON memakai satu alat tes per dimensi (Manajerial / Core Value / Kualitatif).
HCIS meratakannya sendiri, jadi susunannya bebas — pengelompokan itu hanya membuat layar
pelabelan lebih mudah dibaca admin.

**4. `kode_soal` TIDAK dipakai untuk pemetaan, tetapi tolong tetap diisi bermakna.**
Yang mengikat kompetensi adalah label yang dibuat admin HCIS, bukan kodenya. `kode_soal` dan
`nama` hanya dibaca manusia saat melabeli — mengisinya `K01`, `CV01`, `QL01` membuat pekerjaan
itu satu menit alih-alih setengah jam. Nama kompetensi HCIS ada di tabel di bawah.

## Kompetensi yang belum ada bagiannya

| Kode | Nama | Dimensi | Sifat |
|---|---|---|---|
| CV01 | Focus on Customer | Core Value | skala |
| CV02 | Agility & Improvement | Core Value | skala |
| CV03 | Mutual Growth | Core Value | skala |
| CV04 | Integrity | Core Value | skala |
| CV05 | Leadership | Core Value | skala |
| CV06 | Yes, We Can Spirit | Core Value | skala |
| QL01 | Strength | Kualitatif | **naratif / uraian** |
| QL02 | Development Area | Kualitatif | **naratif / uraian** |
| QL03 | Initiative | Kualitatif | **naratif / uraian** |
| QL04 | Recommendation | Kualitatif | **naratif / uraian** |

Empat yang terakhir adalah pertanyaan terbuka, bukan skala 1–5. Kalau di HC Learn bentuk itu
perlu jenis soal tersendiri, silakan — HCIS tidak membaca jenis soalnya dari sini.

## Yang TIDAK diminta

Bunyi pertanyaannya. HCIS hanya perlu tahu ujian ini punya bagian apa saja beserta
identitasnya; teks soalnya tidak pernah dikirim dan tidak pernah disimpan. Meminta bunyi
pertanyaan berarti seluruh bank soal HC Learn tersalin ke basis data HCIS dan bisa dibaca
siapa pun yang membuka halaman paket soal — kebocoran yang tidak bisa ditarik kembali.

## Sudah diuji

Berkas JSON di sebelah ini dijalankan melalui jalur yang sebenarnya dipakai production
(`PaHcLearnCatalogService` → `PaBijeksiLabel` → simpan), pada 10 September 2026:

```
ujian terbaca = 2

=== 200001  Employee Assessment 360 - STAFF
    jumlah_bagian = 17 | bagian_tersedia = true | kembar = 0
    level 2 STAFF              wajib=17  -> LAYAK, dialog pelabelan terbuka
    level 6 ASSISTEN MANAGER   wajib=21  -> tidak sepadan

=== 200002  Employee Assessment 360 - ASSISTEN MANAGER
    jumlah_bagian = 21 | bagian_tersedia = true | kembar = 0
    level 2 STAFF              wajib=17  -> tidak sepadan
    level 6 ASSISTEN MANAGER   wajib=21  -> LAYAK, dialog pelabelan terbuka

SIMPAN level 2 -> status=SEHAT bagian=17
periksaSel -> {"status":"SEHAT","masalah":[]}
```

Perhatikan baris kedua tiap ujian: ujian 17 bagian **ditolak** untuk level yang menuntut 21,
dan sebaliknya. Itu memang yang diinginkan — satu ujian melayani satu kelompok level, bukan
semuanya.

## Kalau Core Value dipisah jadi ujian tersendiri

Bilang lebih dulu. Rancangan HCIS sekarang mengikat **satu Id ujian per sel (level × kategori
penilai)**, jadi ujian Manajerial + ujian Core Value yang terpisah belum bisa ditampung dan
perlu perubahan di sisi kami. Yang paling ringan bagi kedua pihak adalah menaruh semuanya di
**satu ujian** dengan alat tes yang berbeda, seperti contoh ini.
