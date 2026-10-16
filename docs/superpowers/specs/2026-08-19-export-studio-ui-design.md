# Export Studio — Desain UI, Tata Letak, dan Perjalanan Pengguna

**Tanggal:** 19 Agustus 2026
**Modul:** LMS › SOP › Export (halaman) + SYSTEM › ReportStudio (mesin bersama)
**Spec induk:** `docs/superpowers/specs/2026-08-18-export-studio-design.md`
**Fase sebelumnya:** fase 1 (kontrak & endpoint) dan fase 2 (layar pertama) — selesai

Dokumen ini **tidak** mengubah kontrak dataset, endpoint, atau skema database
mana pun. Ia hanya menyentuh lapisan tampilan dan urutan tindakan pengguna.

---

## 1. Masalah

Layar Export Studio yang ada hari ini menyimpang dari spec induk §6 pada hal-hal
yang bisa diperiksa, bukan yang bisa diperdebatkan.

### 1.1 Halaman berjalan tanpa CSS-nya sendiri

`resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.vue` memakai kelas
`sop-scope`, `stitch-card`, `stitch-pad`, `sop-eyebrow`, `sop-h`, `sop-stack`,
`sop-fade-up`, dan seluruh keluarga `shero*`. Tetapi:

- ia **tidak mengimpor** `resources/js/Pages/LMS/SOP/Shared/support/sopTheme.css`,
  padahal di situlah `.sop-scope .stitch-card` (baris 66), `.sop-scope .sop-eyebrow`
  (88), `.sop-scope .sop-h` (95), `.sop-scope .sop-stack` (59), dan
  `.sop-scope .sop-fade-up` (585) didefinisikan. Empat belas berkas `.vue` lain
  mengimpornya; halaman ini tidak ada dalam daftar itu.
- ia **tidak punya blok `<style>` sama sekali**, sedangkan `.shero*` bukan kelas
  global — ia disalin-tempel ke `<style scoped>` di sepuluh berkas (sembilan
  halaman SOP plus `contentDashboard.vue`; contoh: `SopPenjadwalan.vue:494`).

Akibatnya hero dan kartunya dirender sebagai div polos. Yang masih tampil hanya
grid Bootstrap dan CSS scoped milik komponen `ReportStudio`.

**Konsekuensi desain:** perbaikannya bukan menyalin `.shero` untuk kesebelas
kalinya. `HcisHeader` sudah menyediakan `variant="shero"` lengkap dengan lapisan
aurora (`HcisHeader.vue:13`), grid overlay (`:14`), ikon, lencana, serta slot
`breadcrumb`, `actions`, dan `console`.

### 1.2 Katalog kehilangan pengelompokannya di tengah jalan

Endpoint katalog mengirim 30 dimensi yang sudah bergrup — Identitas 7, Paket 7,
Item 6, Versi 1, Baca 4, Kuis 3, Atestasi 2 (`SopDatasetKepatuhan.php:47`).
`PanelDimensiUkuran.vue:70` memetakan ulang katalog menjadi `{key, label}` saja,
sehingga field `grup` dibuang dan pengguna berhadapan dengan 30 baris datar.

### 1.3 Tindakan merusak tanpa konfirmasi

`GaleriTemplate.vue:50` memancarkan `hapus` langsung dari klik, tanpa dialog.

### 1.4 Perjalanan terputus tepat setelah tindakan utama

`ReportStudio.vue:233` menutup alur export dengan teks
`"Laporan diproses (…). Cek panel Aktivitas."` — sebuah kalimat yang menyebut
tempat tanpa mengantar ke sana, padahal Pusat Aktivitas bisa dibuka secara
programatik lewat `useShellState()` (`resources/js/composables/useShellState.js:171`,
kunci `showActivity`).

### 1.5 Warna lahir di tempat yang salah

Kelima komponen `ReportStudio` menulis **enam puluh** warna mentah
(`#4f46e5`, `#e2e8f0`, `#64748b`, …) langsung di `<style scoped>` — ReportStudio 16,
GaleriTemplate 20, PanelDimensiUkuran 7, PanelPratinjau 10, PanelSaringan 7. Ini lolos
`designSystemGuards.test.js` semata karena cakupan penjaganya adalah
`resources/js/components/ui` dan `resources/js/Pages` — `components/SYSTEM/`
tidak termasuk keduanya. Konsekuensi nyatanya: tema gelap tidak berlaku di layar
ini.

### 1.6 Janji ponsel yang belum ditepati

Spec induk §6 menuliskan: *"Di ponsel tampil galeri, jalankan, dan unduh;
perakitnya turun jadi ringkasan definisi yang bisa dibaca, tidak bisa diubah."*
Implementasinya nol — yang ada hanya `col-12 col-xl-5` / `col-12 col-xl-7`,
sehingga di layar 360px sebuah transfer dua kolom berisi 30 dimensi ditumpuk apa
adanya.

---

## 2. Keputusan yang mengikat

Diputuskan pemilik proyek pada sesi 19 Agustus 2026:

1. **Bentuk layar: kokpit dua panel.** Bukan wizard (spec induk §6 sudah
   menolaknya karena membunuh pratinjau langsung), bukan rel kiri tiga zona
   (rel-nya belum ada isinya — template resmi baru satu), bukan kanvas berlaci
   (merakit jadi buka-tutup).
2. **Mode ponsel dikerjakan sekarang, persis sesuai spec induk §6:** galeri,
   pilih format, jalankan, dan unduh tetap hidup; perakit turun menjadi ringkasan
   definisi yang bisa dibaca dan tidak bisa diubah.

Keduanya tidak dibuka ulang dalam implementasi.

---

## 3. Rangka layar

```
┌──────────────────────────────────────────────────────────────┐
│ HcisHeader variant="shero"                                   │
│   title  "Export Studio"                                     │
│   badge  "Sumber: Kepatuhan"                                 │
│   #console → 3 chip HIDUP:                                   │
│        Mode · Kolom terpilih · Perkiraan baris               │
├──────────────────────────────────────────────────────────────┤
│ Rel template — HcisCollapse (terbuka) + HcisTabs "pills"     │
│   [Resmi n] [Milik Saya n] [Dibagikan n]                     │
├──────────────────────────┬───────────────────────────────────┤
│ PERAKIT                  ║ PRATINJAU                         │
│   HcisSplitterPane kiri  ║   HcisSplitterPane kanan          │
├──────────────────────────┴───────────────────────────────────┤
│ BilahAksi (sticky, dasar kartu)                              │
│   format ▾ · [Simpan sebagai…] · [▶ Jalankan export]         │
└──────────────────────────────────────────────────────────────┘
```

**`HcisSplitter`** `orientation="horizontal"`, `variant="flat"`,
`initialRatio="42"`, `minRatio="28"`, `collapsible`. Panel pratinjau boleh
dimaksimalkan saat memeriksa laporan rinci berkolom banyak.

**Chip di slot `console` harus hidup, bukan hiasan.** "30 dimensi tersedia"
adalah angka yang benar tapi tidak menuntun ke mana pun. Yang ditampilkan:
mode aktif, jumlah kolom yang akan keluar, dan perkiraan baris dari pratinjau
terakhir. Saat pratinjau belum ada, chip ketiga menulis "—", bukan angka palsu.

**Bilah aksi pindah ke dasar layar dan dibuat lengket.** Hari ini ia terkubur di
bawah tabel pratinjau di dalam kolom kanan; pada laporan rinci, tombol
"Jalankan export" hilang di bawah lipatan — tombol paling penting di halaman ini
justru yang paling sulit ditemukan.

**Rel template tidak lagi memakan satu kartu penuh.** Dengan satu template resmi
dan nol template pengguna, tiga tab setinggi kartu adalah ruang yang tidak
dibayar isinya. Ia menjadi rel yang bisa dilipat, dan mengingat keadaan
lipatannya selama sesi berjalan.

Aturan rumah yang berlaku di rel ini: **12 kartu per halaman** (paginasi muncul
begitu terlampaui), **judul dipotong 1 baris**, **deskripsi dipotong 2 baris**.

---

## 4. Perakit

| Bagian | Sekarang | Menjadi |
| --- | --- | --- |
| Mode Ringkasan/Rinci | tombol pil buatan sendiri | `HcisTabs variant="segmented"` |
| Dimensi | `HcisTransfer` dengan 30 item datar | chip grup di atas `HcisTransfer` |
| Ukuran | `HcisTransfer` | tetap, dengan kepala panel bertoken |
| Saringan | `<select>` / `<input>` telanjang | `HcisSelect` (filterable) + `HcisField` |
| Urutan pengelompokan | tombol `<button>` polos | `HcisButton variant="ghost" size="sm"` |
| Galat validasi | inline per panel | **dipertahankan apa adanya** |

**Chip grup menyaring di pembungkus, bukan di komponen design system.** Deret
`Semua 30 · Identitas 7 · Paket 7 · Item 6 · Versi 1 · Baca 4 · Kuis 3 · Atestasi 2`
menyaring `daftar` sebelum diserahkan ke `HcisTransfer`. `HcisTransfer` tidak
ditambal — aturan itu sudah tertulis di komentar `PanelDimensiUkuran.vue:20-26`
untuk kasus pengurutan, dan berlaku sama di sini.

Chip menyaring **sisi kiri saja** (yang tersedia). Sisi kanan (yang terpilih)
tidak pernah disaring: menyembunyikan pilihan yang sudah diambil adalah cara
tercepat membuat orang mengira pilihannya hilang.

**Galat inline dipertahankan tanpa perubahan.** Ia sudah benar, dan lebih baik
daripada toast — `"Mode ringkasan membutuhkan minimal satu ukuran"` tidak berguna
melayang di pojok layar.

---

## 5. Pratinjau

`HcisDataTable` menggantikan tabel tulis tangan di `PanelPratinjau.vue:51`.
Propsnya menerima `columns` sebagai array biasa, jadi kolom dinamis dari
compiler tetap bekerja. Yang didapat tanpa menulis kode: paginasi
**24 baris dengan pemilih jumlah per halaman** (aturan rumah), keadaan memuat,
kepala tabel lengket, dan gulir mendatar di dalam wadahnya sendiri.

Lima keadaan yang sudah benar hari ini **dipertahankan seluruhnya**, hanya
diganti pakaiannya:

| Keadaan | Perlakuan |
| --- | --- |
| `memuat` | `HcisSkeleton` peniru bentuk, hanya di panel ini — perakit tetap bisa disentuh |
| `belum` | `HcisEmptyState`, pesannya diambil dari galat definisi yang sedang berlaku |
| `kosong` | `HcisEmptyState` — **tetap dibedakan** dari `belum` |
| `berat` | nada peringatan, **bukan kegagalan**; tombol Jalankan tetap hidup |
| `galat` | `HcisResultState` + "Coba lagi" |

`hasil.catatan` **tidak boleh bisa disembunyikan atau dilipat.** Pemotongan baris
dan kolom yang disamarkan adalah mode gagal terburuk fitur ini, justru karena
angka yang tersisa tetap terbaca masuk akal.

---

## 6. Perjalanan setelah menekan tombol

| Sekarang | Menjadi |
| --- | --- |
| kotak nama template nyempil di bilah aksi | `HcisDialog` "Simpan sebagai" — nama + deskripsi |
| — | setelah simpan: tab **Milik Saya** aktif, kartu barunya ditandai |
| hapus langsung dari klik | `HcisDialog sentiment="danger"` dengan nama template disebut |
| `pesanAksi` sebagai paragraf abu | `hcisMessage` toast **dan** kartu tugas inline |
| "Cek panel Aktivitas" | kartu tugas: `task_id`, status, tombol **Buka Pusat Aktivitas** |
| tombol mati tanpa sebab | sebab ketidakaktifan ditulis di sebelah tombol |

Tombol "Buka Pusat Aktivitas" memanggil `useShellState()` dan menyetel
`showActivity = true`. Studio **tidak** membangun UI kemajuan export sendiri —
spec induk §6 sudah menutup pintu itu: *"Untuk kemajuan export tidak ada UI baru
— Activity Center dan progress polling sudah berjalan."*

---

## 7. Ponsel

Ambang: `max-width: 991.98px`. Deteksinya memakai idiom yang sudah terbukti di
repo ini (`HcisTabs.vue:310` dan `:531`) — `typeof window.matchMedia === 'function'`
lebih dulu, lalu `.matches`, dan disegarkan pada `resize`. Bukan CSS semata,
karena perakit harus benar-benar **tidak dirender**, bukan sekadar disembunyikan:
`HcisTransfer` berisi 30 item yang tak terpakai tetap membayar biaya render dan
tetap terjangkau pembaca layar.

Yang tampil di ponsel:

1. Hero (tanpa slot console)
2. Rel template — utuh; menjalankan template adalah alur ponsel yang paling masuk akal
3. **RingkasanDefinisi** — chip terbaca: `Kelompok: Divisi › Paket`, `Ukuran: 3`,
   `Saringan: 2`, `Mode: Ringkasan`
4. Kalimat jujur: perakitan definisi dilakukan di layar lebar
5. Bilah aksi: format, Jalankan, Buka Pusat Aktivitas

Pratinjau di ponsel tetap ada tetapi diringkas menjadi jumlah baris dan kolom
serta catatan kaki; tabel lebar tidak dipaksakan ke layar sempit.

---

## 8. Token dan penjaga

Seluruh warna mentah di lima komponen diganti token peran: `--hcis-brand-500`,
`--hcis-text-muted`, `--hcis-border`, `--hcis-surface-soft`, dan untuk yang
beralfa `rgb(var(--…-rgb) / .5)` — bukan `rgba()`.

Takaran bahasa visual: **kaca dan gradasi hanya di hero dan satu panel kunci**,
jauh di bawah plafon tiga area per halaman.

Setelah bersih, `designSystemGuards.test.js` mendapat pemeriksaan **NOL** baru
yang mencakup `resources/js/components/SYSTEM/` — celah yang membuat enam puluh
warna ini lolos selama ini.

> **Penjaga baru menyentuh seluruh pemanggil di wilayah bersama.** Suite
> **penuh** dijalankan, bukan hanya berkas SOP. Preseden: penutupan celah 013
> mematahkan sembilan uji lama di empat berkas dan baru ketahuan berhari-hari
> kemudian.

Angka RATCHET pada `HALAMAN_WARISAN` **tidak dinaikkan**. Bila
`SopExportStudioPage.vue` justru menambah warna mentah, itu tanda desainnya yang
salah, bukan angkanya.

---

## 9. Berkas

**Diubah**

| Berkas | Perubahan |
| --- | --- |
| `Pages/LMS/SOP/Export/SopExportStudioPage.vue` | `HcisHeader variant="shero"`; impor `sopTheme.css`; hero tulis tangan dibuang |
| `components/SYSTEM/ReportStudio/ReportStudio.vue` | splitter, rel template, bilah aksi lengket, dialog, kartu tugas, cabang ponsel |
| `components/SYSTEM/ReportStudio/GaleriTemplate.vue` | paginasi 12, pemotongan judul/deskripsi, dialog hapus |
| `components/SYSTEM/ReportStudio/PanelDimensiUkuran.vue` | chip grup, kepala panel bertoken |
| `components/SYSTEM/ReportStudio/PanelPratinjau.vue` | `HcisDataTable`, keadaan bertoken |
| `components/SYSTEM/ReportStudio/PanelSaringan.vue` | `HcisSelect` + `HcisField` |
| `components/ui/designSystemGuards.test.js` | penjaga NOL untuk `components/SYSTEM/` |

**Dibuat**

| Berkas | Isi |
| --- | --- |
| `components/SYSTEM/ReportStudio/BilahAksi.vue` | format, simpan, jalankan, sebab tombol mati |
| `components/SYSTEM/ReportStudio/RingkasanDefinisi.vue` | definisi sebagai chip terbaca (ponsel) |
| `components/SYSTEM/ReportStudio/KartuTugasExport.vue` | `task_id`, status, buka Pusat Aktivitas |

**Tidak disentuh:** `composables/useDefinisi.js`, `api/reportStudioApi.js`,
seluruh controller, service, job, route, dan skema database.

---

## 10. Uji

**Delapan uji yang sudah ada di `ReportStudio.test.js` wajib tetap hijau tanpa
disunting.** Semuanya menegaskan teks hasil render, bukan nama kelas atau
struktur — jadi ia justru pagar yang tepat untuk perombakan tampilan. Yang harus
tetap muncul:

- `Satu baris = satu karyawan × paket × item.`
- `Divisi`, `Jumlah Karyawan`
- `IP Konfirmasi 🔒`
- `Mode ringkasan membutuhkan minimal satu ukuran.`
- `Duplikat & ubah`

**Uji baru:**

| Uji | Menuntut |
| --- | --- |
| chip grup | memilih "Paket" menyisakan enam dimensi di sisi tersedia, dan **tidak** menyaring sisi terpilih |
| hapus template | klik hapus **tidak** memanggil API sebelum dialog dikonfirmasi |
| simpan template | dialog memuat nama dan deskripsi; sukses memindahkan tab aktif ke "Milik Saya" |
| kartu tugas | tombol menyetel `showActivity = true` |
| ponsel | pada viewport sempit, perakit **tidak dirender** dan ringkasan definisi muncul |
| catatan kaki | `hasil.catatan` selalu terlihat, tanpa jalur untuk melipatnya |
| penjaga warna | `components/SYSTEM/` nol warna mentah |

Penjaga hapus-tanpa-konfirmasi harus **pernah dilihat MERAH** sebelum
diperbaiki. Uji yang tidak pernah gagal tidak membuktikan apa pun.

---

## 11. Risiko dan jebakan

1. **`sop-scope` wajib ikut saat ada Teleport.** `HcisDialog` dan `HcisSelect`
   (`teleportTo: 'body'`) memindahkan simpulnya ke luar akar halaman, sehingga
   gaya `.sop-scope .…` tidak lagi menjangkaunya. Setiap lapisan ter-teleport
   wajib membawa kelas scope-nya sendiri.
2. **Leluhur ber-`transform` memerangkap `position: fixed`.** `.sop-fade-up`
   memakai `animation … both`, yang menahan `translateY(0)` selamanya dan
   membuat backdrop modal berhenti di area konten. Bilah aksi lengket dan setiap
   dialog wajib diperiksa terhadap ini.
3. **Bilah lengket menutupi baris terakhir tabel.** Panel pratinjau butuh
   `padding-bottom` setinggi bilahnya.
4. **`HcisDataTable` membawa perkakas yang tidak diminta** — tombol tambah,
   ekspor, penyaring. Semuanya dimatikan eksplisit; pratinjau bukan tabel CRUD.
5. **Uji ponsel dan uji desktop berbagi satu `window`.** Nilai `matchMedia` wajib
   dipulihkan di `afterEach`, kalau tidak berkas uji yang jalan berikutnya gagal
   secara berpindah-pindah.

---

## 12. Yang sengaja tidak dikerjakan

- **Pemilih dataset.** Baru satu dataset terdaftar
  (`SopReportServiceProvider.php:24`); empat lainnya di spec induk §5 belum ada.
  Karena itu ia disajikan sebagai **konteks** ("Sumber: Kepatuhan"), bukan
  sebagai pilihan. Pemilih akan ditambahkan saat dataset kedua benar-benar lahir.
- Penjadwalan dan pengiriman (spec induk §13).
- Kotak SQL bebas, perakit join, editor hak per-field (spec induk §6).
- UI kemajuan export tersendiri.

---

## 13. Catatan operasional

Selama sesi ini ditemukan dan diperbaiki hal terpisah yang tidak berhubungan
dengan desain: `bootstrap/cache/routes-v7.php` di worktree
`hcis-kpi-grouped` berasal dari 15 Agustus, tiga hari sebelum commit `70803cb6`
memindahkan controller ke struktur modul. Cache itu masih memegang delapan puluh
dua nama kelas lama dan membuat setiap permintaan gagal dengan
`Target class [App\Http\Controllers\AuthController] does not exist`.
Diselesaikan dengan `php artisan route:clear`. Tidak ada berkas terlacak yang
berubah.
