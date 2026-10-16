# Wizard Versi Dokumen SOP — desain

Tanggal: 2026-08-26
Status: **KODE & DATA SELESAI — UJI BELUM DIJALANKAN**

> Seluruh kode dan berkas uji sudah ditulis, dan kedua DML sudah dieksekusi ke
> development. `php artisan test`, `npx vitest`, dan `npm run build` **sengaja
> ditahan sampai ACC** sesuai kesepakatan sesi 2026-08-26. Yang sudah
> dijalankan hanya pemeriksaan sintaksis statis (`php -l`, `node --check`) dan
> kueri baca untuk diagnosis.

Halaman: `/sop/admin/dokumen` → `/sop/admin/dokumen/{dokumen}/versi/{versi?}`

Pendahulu yang wajib dibaca lebih dulu:

- `plans/sop/2026-08-23-alur-berlangkah-versi-design.md` — rancangan empat langkah
- `docs/changes/DML-2026-041-approved-tanpa-berkas/README.md`
- `docs/changes/DML-2026-042-sederhanakan-alur-versi/README.md` — **keputusan yang
  dokumen ini hormati, bukan batalkan**

---

## 1. Masalah

Dokumen SOP sudah punya pemisahan yang benar: **identitas induk** (judul, nomor,
jenis, APQC, departemen, entitas) didefinisikan sekali di dokumen, lalu **versi**
didefinisikan berulang di dalamnya. Yang belum beres adalah pendefinisian versi
itu sendiri.

Tiga temuan, semuanya dibuktikan terhadap `Web_HRIS` development pada 2026-08-26.

### 1.1 Layar melompat meninggalkan langkah yang sedang dikerjakan

Gejala yang dilaporkan: *"setelah selesai tahap 1 dan 2 terasa seperti tidak
tersimpan; saat mau lanjut selalu balik ke Review terus-menerus."*

Sebabnya bukan penyimpanan. Data tersimpan dengan benar. Yang salah adalah
pemilih langkah di layar:

`resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue:1246`

```js
langkahPertamaBelumSelesai() {
    const belum = ls.find((l) => l.Keadaan !== 'selesai' && !this.langkahLengkap(l));
    if (belum) return belum.Nomor;
```

Fungsi ini memilih **langkah pertama yang isinya belum lengkap**, tanpa
mempedulikan versinya sedang berdiri di tahap mana. Karena server melaporkan
langkah Pengajuan **selalu** lengkap (`SopTahapVersiService:305`,
`'PENGAJUAN' => true`), layar tak pernah berhenti di Pengajuan.

Bukti pada versi nyata di DB — id 9248, tahap **DRAFTING** (= langkah 1):

```
1. Pengajuan       keadaan=berjalan  lengkap=Y   ← versi sebenarnya DI SINI
2. Review          keadaan=belum     lengkap=Y
3. Pengesahan      keadaan=belum     lengkap=T   ← tetapi layar membuka INI
4. Terbit & Sebar  keadaan=belum     lengkap=T
```

Untuk versi tanpa catatan review, yang terbuka adalah langkah 2 — persis gejala
"selalu balik ke Review". Dan `simpanVersi()` memanggil `muatAlur(id, true)`,
sehingga lompatannya terjadi **setiap kali menyimpan**: kerja admin tersimpan,
tetapi layar tak pernah menunjukkannya.

Akar masalahnya bukan satu baris keliru, melainkan **dua sumber kebenaran**:
server memutuskan syarat transisi, layar memutuskan langkah yang dibuka.

### 1.2 Tahapan terasa seperti tab, bukan perjalanan

Seluruh langkah bisa diklik kapan saja (`pilihLangkah()` tanpa penjaga). Tak ada
bedanya antara langkah yang sudah ditempuh, sedang ditempuh, dan yang belum.
Rasa "perjalanan" hilang, dan tahap yang tercatat di server kehilangan artinya
di mata pemakai.

### 1.3 Ruang kerja sesak dan berkas raksasa

`SopDokumenVersiModal.vue` = **2.326 baris** memikul dua layar sekaligus
(riwayat versi + formulir empat langkah) di dalam modal `size="lg"`. Rich text
review, lampiran gambar, pemilih pengesah, dan pemilih berkas semuanya
berdesakan di ruang modal.

### 1.4 Temuan sampingan — field yang tak ada pembacanya

`Tanggal_Kadaluarsa` masih diminta dari admin, padahal masa berlaku SOP sudah
dicabut lintas-lapis pada 2026-08-15. **Tak ada satu pun aturan atau layar SOP
yang bertindak atasnya** — tak ada kedaluwarsa, tak ada pengingat, tak ada
gerbang. Satu-satunya yang membacanya adalah register kontrol dokumen
(`SopDocumentControlService:191,240`), yang membawanya apa adanya ke kolom
ekspor.

Jadi ia field yang meminta keputusan dari admin lalu hanya dicetak ulang.
Inputnya dibuang; kolom DB dan kolom ekspornya **dibiarkan**, sehingga 6 versi
lama yang sudah terisi tetap tercetak seperti sekarang. Versi baru akan
mengosongkannya.

> Keputusan yang tersisa untuk pemilik produk: apakah kolom itu juga sebaiknya
> dibuang dari register kontrol dokumen. Di luar cakupan pekerjaan ini.

---

## 2. Keputusan arah

**Server memutuskan, layar menggambar.**

Satu kalimat itu yang membedakan rancangan ini dari tambalan. Selama layar masih
ikut memutuskan langkah mana yang terbuka, kelas bug 1.1 akan lahir kembali
dengan wajah lain.

### 2.1 Yang TIDAK diubah — dan alasannya

`DML-2026-042` (2026-08-24) membuang tiga hal secara sadar. Dokumen ini
menghormati ketiganya:

| Yang dibuang 042 | Alasan aslinya | Sikap dokumen ini |
|---|---|---|
| Syarat `CATATAN_REVIEW` untuk maju | "Review yang mulus memang tak meninggalkan catatan; memaksa mengarang putaran kosong membuat catatannya berhenti berarti" | **Tetap dibuang.** Review boleh kosong. |
| Tahap `HOLD` | "Menahan pekerjaan tak butuh status" | **Tetap dibuang.** |
| Gerbang isi sebelum Terbit | "Satu gerbang: Filing and Registered" | **Tetap satu gerbang.** |

Rancangan awal dokumen ini sempat mengusulkan syarat `RINGKASAN` dan
menghidupkan `CATATAN_REVIEW`. Keduanya **dicabut** setelah 042 dibaca: menambah
gerbang bukan cara membuat alur terasa solid, itu cuma membuatnya sempit.

### 2.2 Tahap dan Langkah — dua hal berbeda, dipetakan satu-satu

| Istilah | Arti | Sumber |
|---|---|---|
| **Status** | keadaan teknis yang dibaca 40+ pembaca | `N_SOP_Item_Versi.Status` (DRAFT/PUBLISHED/OBSOLETE) |
| **Tahap** | di mana versi berdiri secara resmi; dicap tanggal, tercatat di log | `N_SOP_Item_Versi.Tahap_Id` → `N_SOP_Master_Tahap_Versi` |
| **Langkah** | bagian formulir yang dilihat admin | diturunkan dari `Tahap.Langkah` |

Aturan barunya cuma tiga baris, dan ketiganya **posisional** — tak ada satu pun
yang menebak dari isi:

```
Langkah_Aktif = langkah tempat tahap berjalan berada
Terkunci      = Nomor > langkah berjalan
Selesai       = Nomor < langkah berjalan
```

Karena tak ada lagi tebakan berbasis isi, bug 1.1 kehilangan tempat hidup.

Hitungan berkas / pengesah / putaran review tetap ditampilkan — sebagai
**keterangan di kolom ringkasan**, bukan sebagai gerbang.

### 2.3 Langkah 4 dan tahap `APPROVED`

Dengan langkah depan terkunci, muncul kebuntuan: langkah 4 berisi berkas final,
sedangkan satu-satunya jalan maju dari `APPROVAL` adalah *menerbitkan* — dan
berkas itulah syarat terbit.

Keputusan: **`APPROVED` dihidupkan kembali, tetapi dipetakan ke Langkah 4**
(`Kelompok = TERBIT`), bukan ke Langkah 3 seperti sebelum 042.

Keluhan 042 adalah *"admin menekan Simpan & Lanjut dua kali untuk satu
langkah"* — dan itu terjadi justru karena `APPROVAL` dan `APPROVED` sama-sama
duduk di langkah 3. Dengan `APPROVED` di langkah 4, tetap berlaku **satu tombol
lanjut per langkah**. Keluhan 042 terjawab, bukan diabaikan.

Dua bagian kode sudah menantikan susunan ini:

- `SopTahapVersiService:335` — `if ($kini->Kode_Tahap === 'APPROVED') return 4;`
  dengan komentar *"Approved MENUTUP langkah Pengesahan dan MEMBUKA Terbit & Sebar"*
- `SopDokumenService:1642` — *"realisasi pengesah sudah dituntut untuk masuk Approved"*

Alur resmi sesudah perubahan:

```
DRAFTING ──► REVIEW ──► APPROVAL ──► APPROVED ──► FILING_REGISTERED ──► OBSOLETE
langkah 1    langkah 2   langkah 3    langkah 4     langkah 4
Pengajuan    Review      Pengesahan   Pemberkasan   (menutup langkah 4)
                              │            │
                              └── CANCEL ──┘
```

---

## 3. Kontrak server

Satu endpoint yang sudah ada — `GET /api/v1/sop/admin/dokumen/versi/{id}/langkah`
— diperluas. Tak ada endpoint baru.

### 3.1 Payload

```jsonc
{
  "Tahap": { "Kode_Tahap": "APPROVAL", "Nama_Tahap": "Pengesahan", "Warna": "#…" },
  "Langkah_Aktif": 3,                         // ← BARU: langkah yang harus dibuka
  "Langkah": [
    {
      "Nomor": 3,
      "Nama": "Pengesahan",
      "Kelompok": "PENGESAHAN",
      "Keadaan": "berjalan",                  // selesai | berjalan | belum | cancel
      "Terkunci": false,                      // ← BARU: Nomor > langkah berjalan
      "Tanggal": "2026-08-20",
      "Ringkasan": "2 pengesah",
      "Kurang": []                            // ← BARU: syarat yang belum terpenuhi
    }
  ],
  "Aksi_Utama": {                             // ← BARU: satu aksi, sudah bernama
    "Jenis": "SIMPAN_LANJUT",                 // SIMPAN | SIMPAN_LANJUT | TERBITKAN
    "Label": "Lanjut ke Pemberkasan",
    "Ke_Tahap": "APPROVED",
    "Syarat_Kurang": []
  },
  "Aksi_Lain": [                              // ← BARU
    { "Jenis": "KEMBALIKAN", "Label": "Kembalikan ke Review", "Ke_Tahap": "REVIEW" },
    { "Jenis": "BATALKAN",   "Label": "Batalkan Versi",       "Ke_Tahap": "CANCEL" }
  ],
  "Tujuan": [ /* kontrak lama, dipertahankan */ ],
  "Syarat_Kini": { /* kontrak lama, dipertahankan */ }
}
```

Kontrak lama (`Tujuan`, `Syarat_Kini`) **tidak dibuang** — Builder dan layar lain
masih membacanya.

### 3.2 Yang hilang dari kode

| Dibuang | Berkas | Alasan |
|---|---|---|
| `langkahPertamaBelumSelesai()` | `SopDokumenVersiModal.vue:1246` | layar tak lagi memilih langkah |
| `langkahLengkap()` | idem | pemakainya ikut hilang |
| input `Tanggal_Kadaluarsa` | modal + validasi controller | nol pembaca (§1.4) |

**`Lengkap` per langkah TETAP ADA dan tak berubah artinya.** Ia fakta yang jujur
— "berkas final belum ada", "belum ada pengesah" — dan dipakai kolom ringkasan
untuk menerangkan keadaan. Yang salah bukan field-nya, melainkan memakainya untuk
**memilih langkah yang dibuka**. Dua uji yang mengunci artinya
(`SopTahapVersiTest:889` dan `:911`) karena itu tetap hijau tanpa disentuh.

Kolom `Tanggal_Kadaluarsa` di DB **tidak dijatuhkan** — sejalan dengan keputusan
2026-08-15 yang sengaja menyisakan kolomnya.

### 3.3 Pengesahan yang bertahan — penjaga terbit

`terbitkanVersi()` saat ini hanya memeriksa `Status = DRAFT` dan kesahihan isi.
Ia **tidak** memeriksa tahap, sehingga penerbitan masih mungkin dari tahap mana
pun lewat pemanggilan langsung.

Ditambahkan penjaga **berbasis data, bukan kode tahap yang dihafal**: penerbitan
ditolak bila `FILING_REGISTERED` bukan tujuan sah dari tahap versi saat ini.
Aturannya tetap tinggal di `N_SOP_Master_Tahap_Versi_Transisi`.

> Penjaga baru menyentuh setiap pemanggil `terbitkanVersi()`. Sesuai pelajaran
> `penjaga-baru-jalankan-suite-penuh`, suite SOP penuh wajib dijalankan, dan
> penjaga ini wajib punya uji yang menuntut **penolakan** — bukan hanya uji jalur
> sukses (`penjaga-mati-nilai-sudah-dinormalkan`).

---

## 4. Bentuk layar

### 4.1 Wadah — halaman penuh

Rute baru, memakai gerbang yang **sudah ada**:

```php
Route::middleware(['auth', 'check.jabatan:menuSopDokumenPage'])->group(function () {
    Route::get('sop/admin/dokumen/{dokumen}/versi/{versi?}', …)
        ->name('sop.admin.dokumen.versi');
});
```

Memakai ulang `menuSopDokumenPage` berarti **nol baris RBAC baru**. Halaman ini
halaman rincian, bukan menu sidebar, jadi tak butuh baris di
`N_HRIS_Grup_Fitur_Map` — dan dengan begitu terhindar dari jebakan
"halaman baru = 403 untuk semua" (`rbac-role-keanggotaan-grup`).

### 4.2 Susunan

```
┌─ /sop/admin/dokumen/{dokumen}/versi/{versi} ─────────────┐
│ ← Master Dokumen  ·  SOP-QA-001 · Prosedur Kalibrasi     │
│ Versi 2.0  ·  tahap: Pengesahan                          │
├──────────────────────────────────────────────────────────┤
│  ①Pengajuan ──② Review ──③ Pengesahan ──④ Pemberkasan    │
│   selesai      selesai    ● berjalan      🔒 terkunci     │
├───────────────┬──────────────────────────────────────────┤
│ Ringkasan     │  LANGKAH 3 · PENGESAHAN                  │
│ versi         │                                          │
│ • nomor  2.0  │  (isi langkah)                           │
│ • berkas  –   │                                          │
│ • pengesah 2  │                                          │
│ • review  1   │                                          │
│ [Riwayat]     │                                          │
├───────────────┴──────────────────────────────────────────┤
│ [Kembalikan] [Batalkan]   [Simpan] [Lanjut ke Pemberkasan]│
└──────────────────────────────────────────────────────────┘
```

- **Rel langkah** — selesai (✓, bisa dibuka & disunting), berjalan (●, terbuka),
  terkunci (🔒, isinya tampak sebagai pratinjau abu-abu yang tak bisa disunting)
- **Kolom ringkasan** — keterangan, bukan gerbang; tombol ke riwayat versi
- **Kaki halaman** — satu `Aksi_Utama` bernama di kanan, aksi lain di kiri.
  Labelnya datang dari server; layar tak menyusun kalimat sendiri.

Langkah terkunci **tetap terlihat isinya**. Kotak yang gelap total membuat admin
tak tahu apa yang menantinya; yang terkunci adalah menyuntingnya, bukan
melihatnya.

### 4.3 Isi tiap langkah

| # | Langkah | Isi | Syarat maju |
|---|---|---|---|
| 1 | Pengajuan | mode penomoran + `Versi_Ke`, `Ringkasan_Perubahan`, `Tanggal_Permintaan`, `Tanggal_Respon` | — |
| 2 | Review | catatan review berulang: tanggal, hasil (rich text), lampiran gambar | — (boleh kosong) |
| 3 | Pengesahan | blok pengesah (`SopPengesahPicker`), `Tanggal_Akhir_Approval` | realisasi pengesah |
| 4 | Pemberkasan | berkas final / paket ujian HC Learn, `Tanggal_Berlaku`, pratinjau terbit | berkas final + pengesah |

`Tanggal_Kadaluarsa` **dibuang** dari seluruh langkah (§1.4).

### 4.4 Perjalanan pemakai

**Versi baru.** Dari daftar dokumen → modal riwayat → "Buat Versi Pertama" →
halaman wizard di langkah 1 dengan langkah 2-4 terkunci. Isi Pengajuan → "Simpan
& Lanjut ke Review". Versi lahir di `DRAFTING`, lalu berpindah ke `REVIEW`;
langkah 1 menjadi ✓ dan **tetap bisa dibuka lagi**.

> Formulir ditandai bersih **sebelum** pesan sukses, bukan sesudah — jendela
> ~1,5 detik tempat "belum disimpan" muncul setelah simpan berhasil sudah pernah
> menggigit (`form-bersih-sebelum-pesan-sukses`).

**Menyimpan tanpa maju.** Tombol "Simpan" tetap ada di setiap langkah. Setelah
simpan, layar **tinggal di langkah yang sama** — inilah perbaikan langsung atas
gejala yang dilaporkan.

**Kembali & batal.** "Kembalikan ke <tahap sebelumnya>" mundur satu langkah dan
membuka kembali suntingannya. "Batalkan Versi" tersedia sejak langkah 3, sesuai
042 ("membatalkan dari langkah awal menawarkan keputusan besar sebelum ada yang
bisa ditimbang"). Keduanya wajib bersebab, lewat `SopCabutAksesDialog` yang sudah
ada (`sop-riwayat-sebab-pelaku`).

**Terbit.** Di langkah 4, setelah berkas final terpasang, "Pratinjau Terbit"
membuka `SopTerbitPratinjau` yang sudah ada — dampak ke paket perujuk terbaca
sebelum keputusan, seperti sekarang.

---

## 5. Pembagian berkas

```
routes/LMS/SOP/Governance/SopGovernance.php
  + GET sop/admin/dokumen/{dokumen}/versi/{versi?}      gerbang menuSopDokumenPage (sudah ada)

app/Http/Controllers/LMS/SOP/Governance/
  + SopAdminVersiWizardController.php      ~120 baris — hanya halaman() + render Inertia
    SopAdminDokumenController.php          tak bertambah (sudah 1.690 baris)
                                           −1 baris validasi Tanggal_Kadaluarsa

app/Services/LMS/SOP/
    SopTahapVersiService.php               +~90 baris  alurVersi(): Langkah_Aktif, Terkunci, Kurang, Aksi
                                                       (langkahVersi() & Lengkap tak berubah)
    SopDokumenService.php                  +~20 baris  penjaga tahap pada terbitkanVersi()

resources/js/Pages/LMS/SOP/Governance/Admin/
  + SopVersiWizard.vue                     ~280  cangkang halaman
  + versi/VersiStepper.vue                 ~120  rel langkah + keadaan terkunci
  + versi/VersiRingkasan.vue               ~140  kolom kiri
  + versi/LangkahPengajuan.vue             ~180
  + versi/LangkahReview.vue                ~260  catatan + lampiran
  + versi/LangkahPengesahan.vue            ~150
  + versi/LangkahPemberkasan.vue           ~240
  + versi/useVersiAlur.js                  ~110  pemuat + keadaan alur

resources/js/Pages/LMS/SOP/Shared/components/ui/
    SopDokumenVersiModal.vue               2.326 → ~900 baris; tinggal daftar riwayat
```

Modal tetap jadi pintu masuk dari daftar dokumen — ia berhenti jadi tempat
mengisi, bukan dihapus.

---

## 6. Perubahan data

Dua change record **terpisah**. Menggabungkan penghapusan data dengan penataan
master berarti satu kegagalan menyeret keduanya
(`sop-prioritas-master`: MERGE gagal senyap → tiap tahap DDL harus bisa gagal
sendirian).

### 6.1 `DML-2026-047-reset-data-sop` — bersihkan paket & dokumen

Target: `Web_HRIS` **development saja**.

Dihapus dengan `DELETE` berpredikat, **bukan `TRUNCATE`** (dilarang untuk agen
menurut `AGENTS.md`), urut dari anak ke induk:

| Urut | Tabel | Baris (2026-08-26) |
|---|---|---|
| 1 | `N_SOP_Progress` | 2.079 |
| 2 | `N_SOP_Absensi` | 7 |
| 3 | `N_SOP_Kepatuhan_Riwayat` | 303 |
| 4 | `N_SOP_Coverage_Rollup` | 132 |
| 5 | `N_SOP_Penjadwalan_Detail` | 368 |
| 6 | `N_SOP_Penjadwalan_Integrasi` | 9 |
| 7 | `N_SOP_Penjadwalan` | 22 |
| 8 | `N_SOP_Favorit` | 1 |
| 9 | `N_SOP_Paket_Versi_Item` | 88 |
| 10 | `N_SOP_Paket_Versi` | 34 |
| 11 | `N_SOP_Paket_Item` | 72 |
| 12 | `N_SOP_Paket_Section` | 26 |
| 13 | `N_SOP_Paket_Draft_Item_Versi` | 2 |
| 14 | `N_SOP_Paket_Draft_Item` | 43 |
| 15 | `N_SOP_Paket_Draft_Section` | 11 |
| 16 | `N_SOP_Paket_Draft` | 11 |
| 17 | `N_SOP_Master_Paket` | 17 |
| 18 | `N_SOP_Item_Versi_Review_Lampiran` | 2 |
| 19 | `N_SOP_Item_Versi_Review` | 1 |
| 20 | `N_SOP_Item_Versi_Pengesah` | 34 |
| 21 | `N_SOP_Item_Versi_Berkas` | 8 |
| 22 | `N_SOP_Item_Versi` | 76 |
| 23 | `N_SOP_Dokumen` | 44 |
| 24 | `N_SOP_Log` | 745 |

**Tidak disentuh** — master dan setelan:
`N_SOP_Kategori` (9) · `N_SOP_Master_APQC` (13) + tipe item (39) ·
`N_SOP_Master_Jenis_Dokumen` (7) + tipe item (8) · `N_SOP_Master_Tahap_Versi`
(8) + syarat (2) + transisi (20) · `N_SOP_Setting` (7) · `N_SOP_Prioritas` (0) ·
`N_SOP_Assignment_Rule` (0).

Library konten juga tak disentuh — ia milik modul lain, bukan "paket dan
dokumen".

Skrip menulis `SELECT COUNT(*)` sebelum dan sesudah tiap `DELETE` supaya
jumlah nyata bisa dibandingkan dengan tabel di atas. Angka yang meleset =
berhenti, bukan lanjut.

### 6.2 `DML-2026-048-approved-langkah-pemberkasan` — hidupkan tahap Disahkan

Dijalankan **sesudah** 047, saat tak ada satu versi pun tersisa — sehingga nol
baris terdampak dan urutan ini menghilangkan seluruh risiko migrasi keadaan.

```
UPDATE N_SOP_Master_Tahap_Versi
   SET Flag_Aktif='Y', Langkah=4, Kelompok='TERBIT', Nama_Tahap='Pemberkasan'
 WHERE Kode_Tahap='APPROVED';          -- dari: aktif=T, langkah=3, kel=PENGESAHAN

Transisi:
  APPROVAL          -> APPROVED           aktifkan   (baris ada, Flag_Aktif='T')
  APPROVED          -> FILING_REGISTERED  aktifkan   (baris ada, Flag_Aktif='T')
  APPROVED          -> APPROVAL           INSERT     (mundur satu langkah)
  APPROVED          -> CANCEL             aktifkan   (baris ada, Flag_Aktif='T')
  APPROVAL          -> FILING_REGISTERED  matikan    (terbit kini lewat APPROVED)
  APPROVED          -> REVIEW             biarkan mati (mundur dua langkah)

Syarat:
  INSERT N_SOP_Master_Tahap_Versi_Syarat (Tahap_Id=6, 'REALISASI_PENGESAH')
  -- menggantikan jalur cadangan Butuh_Pengesahan; FILING_REGISTERED tetap
  -- menuntut BERKAS + REALISASI_PENGESAH seperti sekarang
```

`NAMA_LANGKAH[4]` di `SopTahapVersiService` berubah dari `'Terbit & Sebar'`
menjadi `'Pemberkasan'`, mengikuti istilah yang sudah dipakai DML-042.

---

## 7. Uji & verifikasi

| Lapis | Yang diuji | Wajib pernah MERAH |
|---|---|---|
| Unit | `Langkah_Aktif` = langkah tahap berjalan untuk kedelapan tahap | ✔ regresi §1.1 |
| Unit | `Terkunci` benar di tiap posisi tahap | ✔ |
| Unit | `terbitkanVersi()` **menolak** dari `APPROVAL` sesudah 048 | ✔ |
| Unit | `terbitkanVersi()` menerima dari `APPROVED` | |
| Fitur | rute wizard 403 tanpa `menuSopDokumenPage`, 200 dengan | |
| Fitur | simpan di langkah 3 → tetap di langkah 3 (regresi gejala asli) | ✔ |
| Vitest | stepper menolak klik langkah terkunci | ✔ |
| Vitest | label `Aksi_Utama` datang dari payload, bukan disusun layar | |

Setiap penjaga baru **wajib pernah dilihat merah** sebelum dihijaukan —
operasi yang tak menyentuh apa pun menghasilkan angka sempurna
(`uji-yang-tak-menguji-apa-pun`).

Uji Inertia wajib membawa header `X-Inertia-Version` dari
`HandleInertiaRequests::version()`; `Inertia::getVersion()` masih kosong di uji
(`inertia-409-versi-di-uji`).

**Suite SOP penuh dijalankan**, bukan berkas yang tersentuh saja — penjaga di
service bersama menyentuh setiap pemanggil. Baseline: ~19 kegagalan non-SOP yang
memang sudah ada sebelum perubahan ini.

Seluruh uji, vitest, dan build **ditahan sampai ACC** sesuai kesepakatan sesi ini.

---

## 8. Yang sengaja tidak dikerjakan

- **Kolom `Langkah_Aktif` di DB** — keadaan yang sudah bisa diturunkan dari
  `Tahap_Id`; menyimpannya dua kali melahirkan perselisihan yang tak ada
  wasitnya.
- **Menjatuhkan kolom `Tanggal_Kadaluarsa`** — inputnya dibuang, kolomnya
  dibiarkan, sejalan keputusan 2026-08-15.
- **Mode "rekam SOP lama"** yang membuka semua langkah sekaligus — data lama
  dihapus, jadi tak ada yang perlu dicatat mundur. Tambahkan hanya bila
  kebutuhannya benar-benar muncul.
- **Menyentuh Builder Paket SOP** — kontrak lama (`Tujuan`, `Syarat_Kini`)
  dipertahankan justru supaya Builder tak perlu ikut berubah.
- **Menyeragamkan `.stitch-badge`** — utang lintas-modul yang butuh inisiatif
  sendiri (`css-stitch-badge-design-system-debt`).

## 9. Risiko

| Risiko | Penanganan |
|---|---|
| Penjaga terbit baru mematahkan uji lama | jalankan suite SOP penuh, bukan berkas tersentuh saja |
| 047 dijalankan ke database keliru | skrip menyebut target di kepala; hitung baris sebelum & sesudah; **development saja** |
| 048 dijalankan sebelum 047 | 048 mensyaratkan `N_SOP_Item_Versi` kosong dan berhenti bila tidak |
| Dua worktree menjalankan `npm run dev` | port HMR bentrok; gejalanya menyamar jadi "modal hilang sendiri" (`vite-hmr-port-lintas-worktree`) |
| Modal & wizard sama-sama memuat alur | `useVersiAlur.js` jadi satu-satunya pemuat; modal hanya membaca ringkasan |
