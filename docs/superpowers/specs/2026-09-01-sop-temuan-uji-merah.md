# Penelusuran 45 uji merah modul SOP

Tanggal: 2026-09-01
Cabang: `feat/sop/harderning`
Sumber data: `docs/superpowers/plans/baseline/2026-09-01-sebab-uji-merah.txt`

Seluruh 45 kegagalan sudah diverifikasi **bukan regresi** — tiap berkas dijalankan
ulang pada commit `b7c69ad7` (sebelum pekerjaan hardening) dan menghasilkan angka
identik. Dokumen ini menelusuri **sebabnya**.

---

## Ringkasan kelas

| Kelas | Isi | Jumlah |
|---|---|---|
| A | Uji mendahului penjaga tata kelola | 14 |
| B | Uji mengunci aturan yang sudah sengaja diubah | 7 |
| C | Data dev tak memenuhi prasyarat fikstur | 6 |
| **F** | **Bug nyata** — F-1 & F-2 sudah diperbaiki | **4** |
| ? | Belum ditelusuri | 14 |

---

## ✅ F-1 — Penugasan eksplisit atas paket PUBLIK hilang dari laporan — **SUDAH DIPERBAIKI**

**Ditemukan lewat:** `SopRingkasanSaringanTest > unfiltered summary still matches
the original queries` (181 vs 182). Uji itu kini hijau.

**Cacatnya.** Gerbang paket publik memakai "sudah dibuka" sebagai proksi untuk
"ini kewajiban". Proksi itu benar bagi `MANDIRI` — karyawan yang membuka SOP
publik didaftarkan sendiri oleh sistem, dan orang yang sekadar penasaran memang
tak boleh masuk angka kepatuhan. Tetapi ia salah bagi `MANUAL`/`AUTO_RULE`:
penugasan yang dibuat admin secara sadar ikut terbuang dari seluruh laporan.

Terbukti pada `UJI0066` — ditugaskan MANUAL pada 2026-08-09 ke paket
`[DEMO] Pengenalan SOP untuk Karyawan Baru`, status `BELUM`, tak muncul di
hitungan tertunda. Sembilan penugasan serupa ada di dev.

**Perbaikannya.** Niat penugasan dibaca dari kolom `Sumber` yang memang
merekamnya, bukan ditebak dari perilaku karyawannya. Dev: tertunda 181 → 182.

**Yang ditemukan saat memperbaiki, dan lebih penting daripada cacatnya sendiri.**
Syarat gerbang itu tersalin **verbatim di lima tempat**. Menambal satu salinan
membuat empat sisanya melaporkan angka berbeda atas data yang sama — tanpa satu
pun tanda, karena tiap salinan tetap menjawab dengan angka yang terlihat masuk
akal.

Dua uji menangkapnya seketika, dan keduanya bekerja karena menguji **efek**,
bukan pemanggilan:

- `SopDatasetKepatuhanTest > gerbang penugasan sama dengan lapisan monitoring`
- `SopDokumenKesehatanTest > penugasan publik yang belum dibuka dilaporkan terpisah`

Karena itu syaratnya tidak ditambal satu per satu melainkan dipindahkan ke satu
definisi, `App\Services\LMS\SOP\Support\SopGerbangPaket`, yang dipanggil
kelima tempat itu.

Satu tempat tak bisa ikut: penghitung `Tersaring_Gerbang_Publik` adalah
**cerminan terbalik** gerbang — ia menghitung yang DIBUANG — sehingga bentuk
kuerinya berlawanan. Ia disesuaikan terpisah, dan yang menahan keduanya tetap
sepakat adalah uji `test_penghitung_tersaring_sepakat_dengan_gerbangnya()`.

**Catatan yang layak disebut.** Komentar di `SopDatasetKepatuhan::baseQuery()`
sudah menuliskan cacat ini beserta tempat perbaikannya: *"Memperbaikinya harus
dilakukan di terapkanSaringanPaket() supaya seluruh modul ikut berubah, bukan di
sini sendirian."* Penulis sebelumnya sudah tahu; separuh instruksi itu kini
dijalankan.

**Yang TIDAK perlu diubah, berlawanan dengan dugaan awal saya:**
`N_SOP_Coverage_Rollup`. Baik pemeliharaan inkrementalnya maupun
`SopRollupRecalc` tak mengenal `Mode_Distribusi` maupun `Sumber` sama sekali —
rollup selalu menghitung kesembilan penugasan itu. Jalur tersaringlah yang
membuangnya, sehingga perbaikan ini **mendekatkan** kedua jalur, bukan
memisahkannya.

**Commit:** `d4f5a840`

---

## ✅ F-2 — Dua kosakata untuk klasifikasi yang sama — **SUDAH DIPERBAIKI**

**Ditemukan lewat:** `SopDokumenKelompokTest` (3 uji). Ketiganya kini hijau.

| Kelompok | Export Studio (dulu) | Dashboard (dulu) | **Sekarang** |
|---|---|---|---|
| ditugaskan | `WAJIB` | `FUNGSIONAL` | **`DITUGASKAN`** |
| beredar | `TERBUKA` | `PUBLIK` | **`BEREDAR`** |
| belum beredar | `BELUM_BEREDAR` | `BELUM_BEREDAR` | `BELUM_BEREDAR` |

**Kenapa kata ketiga, bukan memilih salah satu yang sudah ada.** Keduanya
sama-sama bertabrakan dengan konsep lain di modul ini:

- `PUBLIK` juga nilai `Mode_Distribusi` — satu kata untuk dua hal berbeda
  (mode distribusi paket vs klasifikasi peredaran dokumen).
- `WAJIB` satu kata dengan kunci dimensi `wajib` (`pi.Flag_Wajib`) di dataset
  kepatuhan. Collation SQL Server tak peduli besar-kecil huruf, dan kueri audit
  untuk pekerjaan ini sendiri sempat tertipu: 7 template yang dikira memakai
  nilai kelompok ternyata memakai kunci dimensi.

`DITUGASKAN` dan `BEREDAR` tidak bertabrakan dengan apa pun, dan keduanya sudah
menjadi kata yang dibaca pengguna di dalam kurung pada label yang berlaku —
"Wajib (Ditugaskan)", "Terbuka (Beredar)".

**Nilainya kini hanya ada di satu tempat.** Dashboard meminjam dari
`SopDokumenKelompok` alih-alih menyimpan salinan; `SUMBER_KEWAJIBAN` juga
dipinjam dari `SopGerbangPaket`.

**Nol migrasi.** Klasifikasi ini dihitung saat baca, tidak tersimpan di kolom
mana pun, dan nol template laporan tersimpan menyebut kosakata ini.

**Yang sengaja TIDAK ikut berubah,** mengikuti preseden penggantian istilah
Monitoring: nama kelas CSS (`.is-fungsional`) dan bentuk kunci respons API
(`Fungsional`/`Publik`). Keduanya tak terbaca pengguna, dan menggantinya
melipatgandakan permukaan risiko tanpa menambah kejelasan.

### Salinan keenam gerbang paket, ditemukan saat mengerjakan ini

`SopDokumenKelompok::whereWajib()` menulis gerbang paket publik sebagai **SQL
mentah**, sehingga pencarian yang menyisir pola query builder — termasuk
pencarian saya saat memperbaiki F-1 — melewatinya begitu saja. Akibatnya
perbaikan F-1 diam-diam mematahkan janji yang tertulis di komentarnya sendiri:
*"SENGAJA SAMA PERSIS dengan terapkanSaringanPaket()"*.

`SopGerbangPaket` karena itu kini punya bentuk kedua, `sql()`, mengikuti
falsafah `SopDokumenKelompok` sendiri: dua bentuk, satu sumber. Pelajarannya
tercatat di kelas itu — janji dalam komentar tidak menahan apa pun; berbagi
kode menahannya.

**Commit:** `1ee949fa`

---

## 🔴 F-3 & F-4 — Belum dipastikan, kandidat kuat

- `SopExportRiniTest > row count matches the assignment times item join` — 1.380 vs
  1.420, selisih 40 baris antara ekspor rinci dan perkalian penugasan × item.
- `SopPerhatianDokumenTest` (2) — dokumen tertentu tidak muncul di penyaring
  tinjauan yang seharusnya menangkapnya.

Keduanya bukan kegagalan fikstur maupun istilah; angkanya berselisih. Perlu
ditelusuri dengan cara yang sama seperti F-1.

---

## Kelas A — Uji mendahului penjaga tata kelola (14)

Seluruhnya galat identik: *"Versi ini belum boleh diterbitkan dari tahap Drafting.
Selesaikan pengesahannya lebih dulu, lalu terbitkan dari langkah Pemberkasan."*

Alur tahap yang berlaku:
`DRAFTING → REVIEW → APPROVAL → APPROVED → FILING_REGISTERED`.
Tidak ada jalan pintas dari DRAFTING ke terbit, dan memang tidak boleh ada.

Uji-uji ini menerbitkan versi langsung dari DRAFTING — ditulis sebelum penjaga
pengesahan-wajib dipasang. **Penjaganya bekerja; ujinya yang usang.**

`Alur007` (4) · `Alur044` (3) · `Kontrol013` (3) · `Alur005` (1) · `Alur015` (1) ·
`SopRiwayatDokumen` (1) · `SopSuntingDraft` (1)

**Perbaikan:** helper uji menjalankan versi melewati REVIEW → APPROVAL → APPROVED
sebelum menerbitkan. Nol perubahan kode produksi.

---

## Kelas B — Uji mengunci aturan yang sudah sengaja diubah (7)

**`SopDokumenFase2Test` (3)**
- Menuntut indeks `UX_N_SOP_Item_Versi_Dokumen_Urut`, yang sudah digantikan varian
  `_Hidup` (DDL-2026-062).
- Menegakkan keunikan `(dokumen, versi)` **tanpa menyaring `Flag_Aktif`**, padahal
  indeksnya kini sengaja terfilter ke baris hidup saja. Diperiksa: dok 17053 punya
  versi 1 dua kali (satu `T` mati, satu `Y`), dok 17054 empat kali (tiga `T`, satu
  `Y`) — **hanya satu baris hidup di keduanya**, jadi invarian yang berlaku tidak
  dilanggar.

**`SopDokumenDepartemenTest` (2)** — menuntut deret nomor dokumen mulai dari `0`.
Kode sengaja mulai dari `1`, dengan alasan tertulis di `SopDokumenService`:
*"tidak ada dokumen ke-nol… jangan menyamakan keduanya supaya konsisten"*.
Lingkup deretnya sendiri sudah benar (`KOLOM_GRUP_NOMOR` memuat `Departemen_Id`).

**`SopBulkCabutTersaringTest` (2)** — *"The sebab field is required"*. Uji tak
mengirim `sebab`, yang menjadi wajib sejak DDL-2026-014.

---

## Kelas C — Data dev tak memenuhi prasyarat fikstur (6)

Uji butuh sesuatu yang kebetulan tidak ada di dev. Bukan cacat kode maupun uji.

- `SopFormBerkas` (1) — butuh minimal satu item FORM
- `SopTerbitPengesahPerTipe` (2) — butuh versi KUIS/VIDEO tanpa pengesah
- `SopDokumenKesehatan` (1) — butuh jenis dokumen yang belum dipakai
- `SopKodePemilikNomor` (2) — fikstur memilih jenis dokumen yang sudah dinonaktifkan

---

## Belum ditelusuri (14)

Dicatat apa adanya supaya tidak dikira sudah beres:

| Uji | Tanda |
|---|---|
| `RegresiRinci` (2) | `'percobaan' => '0'` vs `'-'` |
| `SopTahapVersi` (3) | dua larik tak identik; `Undefined array key "FILING_REGISTERED"` |
| `SopNomorLengkap` (1) | tujuh kode jenis, larik tak identik |
| `SopBuilderSaringanPaket` (1) | `true is false` pada sumbu tak dikenal |
| `SopDokumenBerbagi` (1) | kunci `dipotong` tak ada di respons opsi dokumen |
| `SopDokumenVerify` (1) | pelanggaran FK saat menyiapkan data uji |
| `SopExportRinci` (1) | 1.380 vs 1.420 — lihat F-3 |
| `SopPerhatianDokumen` (2) | lihat F-4 |
| `SopPenjadwalanService` (1) | anggaran kueri: 2, seharusnya ≤1 |
| `SopDokumenKelompok` sisa | tercakup F-2 |

---

## Kesimpulan

Dari 45 uji merah, **31 sudah terjelaskan** dan **tidak satu pun berasal dari
pekerjaan hardening**. Yang benar-benar bug produksi sejauh ini **dua**, dan
keduanya nyata terlihat pengguna:

1. **F-1** — penugasan eksplisit atas paket publik hilang dari laporan (9 kasus di dev)
2. **F-2** — dua layar menyebut kelompok dokumen yang sama dengan istilah berbeda

Keduanya menunggu keputusan produk sebelum diperbaiki, dan keduanya menyentuh
banyak pemanggil sekaligus — bukan perbaikan sambil lalu.

---

## Lubang pada baseline itu sendiri

Baseline `2026-09-01-sebab-uji-merah.txt` **tidak mencakup `tests/Unit` sama
sekali** — nol rujukan. Ketika `SopTemplateSistemTest` terlihat merah saat
mengerjakan F-2, ia sempat terbaca sebagai regresi baru.

Ternyata bukan: menjalankan berkas versi sebelum perubahan kosakata memberi
angka kegagalan yang **identik** (8). Kegagalannya nyata tetapi lama, dan
sebabnya terpisah — uji itu memvalidasi SELURUH template sistem, termasuk
template dataset *dokumen*, terhadap dataset *kepatuhan*, sehingga seluruh
kunci dimensinya dilaporkan tak dikenal.

Belum diperbaiki, dan dicatat di sini supaya tidak hilang.
