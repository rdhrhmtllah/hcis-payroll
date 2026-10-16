# PA — aturan yang tidak dijaga database, dan siapa yang menjaganya

Setiap change PA punya blok "yang sengaja tidak dijaga database". Semuanya sah tidak
jadi CHECK — lintas baris, lintas tabel, atau harus mengeraskan literal bisnis ke skema.
Yang tidak sah adalah membiarkannya hanya sebagai kalimat di README.

**Berkas ini daftar tunggalnya.** Menambah aturan "tidak dijaga database" di README
change baru **wajib** menambah barisnya di sini, beserta penjaganya.

Diperbarui 1 September 2026.

---

## Σ harus 100 — dijaga `PaBobotGuard`

| # | Aturan | Asal | Penjaga |
|---|---|---|---|
| 1 | Bobot detail penilai per salinan siklus berjumlah 100 | DDL-071 | `pastikanSeratus()` |
| 2 | Target distribusi grade per siklus berjumlah 100 | DDL-071 | `pastikanSeratus()` |
| 3 | Bobot hasil resolusi seluruh kategori per peserta berjumlah 100 | DDL-074 | `pastikanSeratus()` |
| 4 | `Bobot_Dalam_Kategori` per (peserta, kategori) berjumlah 100 | DDL-075 | `pastikanSeratus()` + `bagiRata()` |
| 5 | Normalisasi ulang bobot sesudah penilai dibatalkan | DDL-075 | `redistribusi()` |

Kegagalannya diam: bobot berjumlah 70 menghasilkan nilai 30 persen lebih rendah tanpa
satu pun error, dan angkanya tetap tampak wajar.

`redistribusi()` ada supaya pencegahannya mendahului pemeriksaannya. Kalau hanya ada
pemeriksa, tiap pemanggil membuat pembagi ulangnya sendiri dan pembulatannya berbeda-beda.

---

## Bergantung `Status_Cycle` — dijaga `PaCycleGate`

| # | Aturan | Asal | Operasi gerbang |
|---|---|---|---|
| 6 | Salinan aturan membeku saat `LOCKED` | DDL-071 | `SUNTING_SALINAN` |
| 7 | Paket soal tidak boleh diganti sesudah kunci | DDL-073 | `PILIH_PAKET_SOAL` |
| 8 | Override per peserta tidak boleh ditulis sesudah kunci | DDL-074 | `TULIS_OVERRIDE` |
| 9 | Payload tidak diterima sesudah siklus ditutup | DDL-076 | `TERIMA_PAYLOAD` |

Ini yang taruhannya paling besar. **Lapisan salinan siklus ada justru karena janji
"aturan membeku saat dikunci".** Kalau janji itu tidak ditegakkan, seluruh alasan
keberadaan lapisan kedua runtuh sementara skemanya tetap terlihat rapi.

Gerbangnya memakai **daftar putih**: status yang tidak disebut berarti dilarang. Aturan
yang bekerja dengan menyebut larangan akan diam-diam mengizinkan status baru yang kelak
ditambahkan.

Pengecualian yang disengaja tercatat di gerbang itu sendiri: `SUNTING_BOBOT_PILAR`
berhenti di `CLOSED`, bukan di `LOCKED` (K23).

---

## Belum berpenjaga — ditangani per kasus

| # | Aturan | Asal | Rencana |
|---|---|---|---|
| 10 | `Alasan_Manual` wajib saat sumber `MANUAL` | DDL-075 | validasi form penugasan |
| 11 | `Alasan_Tertahan` wajib saat status `TERTAHAN` | DDL-080 | mesin perhitungan |
| 12 | Paket soal menutup jenis kompetensi wajib | DDL-073 | **DIJAGA sejak 1 Sep 2026** — `PaResultCalculationService::jenisWajibYangTidakTercakup()` menahan hasil dan menyebut jenis yang kurang |
| 13 | Seluruh butir kontrak benar-benar datang | DDL-076 | pemeta payload; yang kurang → peserta `TERTAHAN` |
| 14 | Siapa layak masuk siklus (aktif, jenis kontrak, batas resign) | DDL-072 | service persiapan siklus |
| 15 | Peserta `DIIKUTKAN` wajib menunjuk salinan aturan | DDL-072 | service persiapan siklus, gerbang G10 |
| 16 | Pita nilai tidak boleh tumpang tindih | DDL-067 | sudah dijaga DML-079 saat seed; perlu dijaga lagi bila ada halaman master pita |
| 17 | `Kode_Grade_Final` beda dari `Kode_Grade_Sistem` hanya bila dikalibrasi | DDL-080 | service kalibrasi |
| 18 | Urutan status hasil | DDL-080 | mesin perhitungan dan service penerbitan |

Nomor 14 dan 15 lahir bersama service persiapan siklus, jadi tidak perlu penjaga
terpisah — asalkan tidak ada jalur lain yang membuat peserta.

## Audit 1 September 2026 — kesembilan aturan diuji dengan MELANGGARNYA

Kesembilan baris di atas diperiksa bukan dengan membaca kode melainkan dengan mencoba
melanggar tiap aturannya. **Delapan terbukti dijaga**; nomor 12 ternyata terbuka dan
ditutup pada hari yang sama.

Dua pelajaran dari auditnya sendiri, karena keduanya nyaris membuat hasilnya salah:

1. **Putaran pertama menghasilkan tiga positif palsu.** Yang menolak ternyata gerbang
   status siklus dan galat validasi kunci muatan, bukan aturan yang hendak diuji. Audit
   yang tidak memeriksa PESAN penolakannya akan melaporkan penjaga yang tidak ada.
2. **Aturan 12 punya seluruh anggota badannya kecuali keputusannya.** Tabel ada (33 baris),
   salinan per siklus ada (28 baris), `competencyRequirements()` ada — dan satu-satunya
   pemanggilnya adalah berkas uji. Perkakas yang lengkap bukan bukti aturannya berlaku;
   yang membuktikan hanya pemanggilan dari jalur produksi.
3. **Penjaga yang benar bisa tetap tak terlihat karena sebab lain menutupinya.** Aturan 12
   dipasang 1 Sep 2026, tetapi sebabnya tidak pernah sampai ke layar: `alasan_tertahan`
   memilih sebab pilar KPI dan sebab pilar kompetensi dengan `??`, dan yang KPI selalu
   menang. Ujinya pun tidak menangkap itu — ia mati lebih dulu pada penyiapan yang kurang
   dua baris, dan galat penyiapan itu MENYAMAR sebagai penjaga yang bekerja. Diperbaiki
   2 Sep 2026: kedua sebab kini dirangkai (lihat `gabungAlasanTertahan()`), dan ujinya
   menegaskan keduanya sekaligus supaya penutupan itu tidak bisa kembali diam-diam.
   Pelajarannya: memasang penjaga belum selesai sampai sebabnya terbukti TERBACA di
   keluaran, bukan hanya terpanggil di kode.

Nomor 13 sempat tercatat terbuka juga, keliru: rantainya memang tidak lewat
`kontrak_lengkap` dibaca perhitungan, melainkan lewat penugasan yang tidak pernah menjadi
`SELESAI` — dan itu sudah dijaga `ServiceFlowTest`.

---

## Aturan memakai berkas ini

1. Menambah blok "tidak dijaga database" di README change **wajib** menambah barisnya
   di sini.
2. Aturan yang bentuknya sudah ada penjaganya **dipanggil** dari penjaga itu, bukan
   ditulis ulang di tempat pemanggilan. Dua penjaga yang sama akan berbeda perilakunya
   dalam hitungan bulan.
3. Tiap penjaga wajib punya uji yang pernah terlihat **MERAH**. Penjaga yang tidak
   pernah terlihat menyala sama dengan tidak ada.

Alasan aturan kedua bukan teoretis: modul SOP di proyek ini sudah pernah kena — aturan
terpasang di satu dari dua pintu yang setara, dan celahnya baru ketahuan berhari-hari
kemudian.
