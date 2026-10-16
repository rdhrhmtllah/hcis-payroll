# Performance Appraisal — sisa yang menunggu keputusan

Ditulis 6 September 2026, sesudah rantai penuh (`RantaiPenuhTest`) hijau dan seluruh
suite PA — PHP maupun JS — bersih.

Berkas ini hanya memuat hal yang **tidak boleh diputuskan sendiri oleh agen**: perubahan
aturan produk, penghapusan data, dan eksekusi ke production. Yang sudah selesai tidak
diulang di sini; jejaknya ada di pesan commit dan `docs/changes/`.

---

## 1. ~~Pembatalan penugasan mustahil setelah siklus dibuka~~ — SELESAI

Diputuskan 6 September 2026: **boleh**, lewat operasi gerbangnya sendiri.

- `BATALKAN_PENUGASAN` dan `TAMBAH_PENILAI` — keduanya `DRAFT, PREPARED, LOCKED, OPEN`.
- `BUAT_PENUGASAN` **tidak** ikut dilonggarkan: ia dipakai delapan tempat termasuk
  pembentukan massal, yang di tengah siklus akan melahirkan ribuan penugasan sekaligus.
- `CLOSED` dan `PUBLISHED` tetap ditutup. Di sana admin sedang memverifikasi atau angkanya
  sudah dilihat orang. Bila memang perlu, siklusnya dibuka lagi — `CLOSED → OPEN` sudah
  diizinkan dan meninggalkan jejak.
- Penambahan penilai ditolak bila hasil pesertanya sudah `TERBIT`: menambahnya berarti
  nilai yang sudah dilihat orang berubah kelak tanpa ada yang mengatakannya.

Penambahan ikut dibuka karena **mengganti penilai butuh dua-duanya** — batalkan yang lama
dan tambah yang baru. Membuka pembatalan saja meninggalkan kasus "penilainya diganti"
tetap buntu.

Dilengkapi hari yang sama: **mengeluarkan peserta** dari siklus yang sudah berjalan, lewat
`KECUALIKAN_PESERTA` (`DRAFT, PREPARED, LOCKED, OPEN`). Pembatalan penugasan tanpa ini
tidak cukup — kasus nyatanya bukan "satu penilai salah" melainkan "orangnya resign", dan
tanpa jalan keluar penugasannya tetap menagih pekerjaan untuk orang yang tidak akan
dinilai. `SUNTING_PESERTA` sengaja TIDAK ikut dilonggarkan: ia juga berarti MENGIKUTKAN
orang, dan orang yang diikutkan sesudah kunci lahir tanpa satu pun penilai karena
pembentukan penugasan berhenti di `PREPARED`. Pengecualian ditolak bila hasil pesertanya
sudah `TERBIT`, cerminan penjaga yang sama pada penambahan penilai.

## 2. ~~Halaman Leader belum bisa dicapai siapa pun~~ — PINTUNYA SELESAI

Diputuskan 6 September 2026: **menu global**, seperti "Penilaian Saya". Dikerjakan lewat
`DML-2026-105` (dev DONE) — satu baris `N_HRIS_Menus` ber-`Is_Global_Menu='Y'` dan satu
baris `N_HRIS_Grup_Fitur_Map`, keduanya menyalin header dan grup dari baris "Penilaian
Saya" alih-alih menulis angka.

Alasannya bukan kenyamanan melainkan cara daftar per orang gagal: orang yang baru diangkat
menjadi atasan tidak akan mengeluh halamannya hilang — ia hanya tidak akan pernah
membukanya. Yang ditanggung: karyawan tanpa bawahan melihat satu butir menu tambahan, dan
halamannya menyambut dengan keterangan yang sudah ada ("Anda belum memiliki scope tim").

### Sumber "siapa bawahan saya" — SELESAI

Diputuskan 6 September 2026: **ikut mesin penugasan**. `PaLeaderService::kodeAnggota()`
kini membaca `N_HRIS_Organization_Relationship`, dua arah sekaligus (`BAWAHAN` dari sisi
subjek dan cerminnya `ATASAN` dari sisi terkait), dengan penyaring keberlakuan yang disalin
dari `PaAssignmentBuilderService::hangatkanCalon()`.

Yang membuatnya bukan sekadar kerapian:

| | penugasan kategori ATASAN | penilainya TIDAK melihat orangnya di halaman tim |
|---|---|---|
| `HRIS_Karyawan_Team` (lama) | 1.721 | **1.717** |
| `N_HRIS_Organization_Relationship` (baru) | 1.721 | **0** |

Dibuktikan terhadap 467 leader; hasilnya sama persis dengan kueri SQL yang ditulis
terpisah. Perlu diketahui: **6.972 dari 7.008 baris relasi di development ber-`Sumber_Data`
= `DUMMY_DEV`**, jadi angka 467 bukan gambaran production. Yang dibuktikan adalah
kesepadanan kedua sisi, dan itu berlaku berapa pun barisnya.

**Yang masih perlu kamu periksa di production:** berapa baris nyata
`N_HRIS_Organization_Relationship` di sana. Kalau kosong, setiap leader melihat "Anda belum
memiliki scope tim" — bukan galat, tetapi juga bukan yang diinginkan. Halaman Relasi
Organisasi (`/system/organisasi/relasi`, DML-2026-097) adalah tempat mengisinya.

---

## 3. Data simulasi di siklus 1295

**Keadaannya.** 1.159 penugasan berisi kiriman simulasi (`SIM-PA-`), 27.634 baris skor,
300 hasil terhitung.

**Kenapa belum dibersihkan.** `pa:simulasi-hclearn bersihkan 1295` menghapusnya — termasuk
data yang barusan dipakai membuktikan perhitungan cocok pada 211 hasil. Membersihkannya
sekarang membuang bukti itu; membiarkannya berarti siklus sungguhan tidak boleh memakai
siklus 1295.

**Yang perlu diputuskan.** Bersihkan sekarang, atau tandai 1295 sebagai siklus demo yang
tidak akan pernah dipakai sungguhan.

---

## 4. Empat DML menunggu SQL Tracker lalu production

Agen tidak menjalankan DDL/DML production — itu aturan `AGENTS.md`, bukan pilihan.

| Change | Isi | Dev |
|---|---|---|
| `DML-2026-101` | Menu PA jadi satu header Administrator PA | DONE |
| `DML-2026-102` | Alamat callback PA di dua registri gerbang API | DONE |
| `DML-2026-103` | `pa:sapu-tertahan` masuk scheduler | DONE |
| `DML-2026-104` | Status `MENUNGGU_VERIFIKASI` di master | DONE |

**DML-2026-104 paling mendesak**: tanpanya, callback ber-`Status_Skor` =
`MENUNGGU_VERIFIKASI` gagal *foreign key* pada kiriman pertama. Ia harus berangkat
bersama kode `Status_Skor`, bukan sesudahnya.

**DML-2026-102** harus dijalankan **sebelum** HC Learn diminta mengarahkan callback ke
alamat baru — kalau tidak, kirimannya dijawab 403 walau tanda tangannya sah, dan itu
terbaca seperti masalah kredensial di sisi mereka.

---

## 5. Yang belum dibangun di sisi HCIS

- **Endpoint 4 kontrak** (`pa/update/{token}`) kini PUNYA pemicunya, dan itu justru
  memunculkan utang baru. Sejak 6 September 2026 jendela pengisian boleh digeser selagi
  siklus berjalan (`STATUS_BOLEH_UBAH_JENDELA` mencakup `OPEN`), tetapi token yang sudah
  dicetak di HC Learn TETAP membawa jendela lama — dan tidak ada apa pun yang
  memberitahukannya. Selama pencetakan masih simulasi hal ini tak berakibat; begitu
  pencetakan sungguhan hidup, penggeseran jendela WAJIB disusul panggilan `pa/update`
  untuk setiap token siklus itu.

  **Penyimpangannya kini terdeteksi.** Sejak 6 September 2026 setiap pintu mencatat jendela
  yang berlaku saat ia dicetak (`jendela_dari`, `jendela_sampai` di `Payload_Json`), dan
  `pa:verify` melaporkan pintu yang jendelanya sudah tidak berlaku beserta contoh tokennya.
  Perbaikannya `cetak-ulang-pintu` per penugasan.

  Pintu lama yang jejaknya belum memuat jendela DILEWATI, bukan dituduh menyimpang —
  menuduhnya berarti melaporkan setiap siklus lama sebagai bermasalah, dan peringatan yang
  selalu menyala berhenti dibaca orang.

- ~~**Endpoint 5 kontrak** (`pa/reassign`) tanpa pemicu~~ — SELESAI 6 September 2026.
  Pemicunya `POST /api/v1/performance-appraisal/penugasan/{id}/cetak-ulang-pintu`,
  digerbangi operasi tersendiri `CETAK_ULANG_PINTU` (`DRAFT`–`OPEN`).

  `PILIH_PAKET_SOAL` sengaja TIDAK dilonggarkan: ia dipakai tujuh tempat termasuk
  penyuntingan ikatan soal, sehingga membukanya sampai `OPEN` berarti ISI soal bisa berubah
  selagi orang menjawabnya.

  Bentuk operasinya ditentukan skema, bukan selera: `UX_PA_CycleQPkg_Cycle_Level_Rater`
  membuat satu sel (siklus, level, kategori) hanya boleh punya SATU paket. Jadi tidak ada
  "paket lain di siklus yang sama" untuk dituju — yang ada hanyalah paket sel itu yang
  disalin ulang. Karena itu operasinya tidak menerima id paket sama sekali; ia mengambil
  paket dari sel penugasannya sendiri. Menerimanya sebagai masukan hanya membuka satu
  kemungkinan yang tersisa, yaitu menunjuk paket milik sel LAIN — penilai mengerjakan soal
  yang tidak pernah ditetapkan untuknya sementara nilainya tetap terhitung.
- **Pencetakan token** (`PaHcLearnScheduleService::jadwalDariHcLearn()`) sengaja tidak
  punya jalur tulis sungguhan — hanya simulasi. Diisi ketika HC Learn menyerahkan
  endpointnya.
