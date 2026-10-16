# HCIS Design System — Cakupan & Kesepakatan

**Tanggal:** 10 Agustus 2026
**Cabang:** `feat/system-design`
**Status:** menunggu review pemilik repo

---

## 1. Masalah

HCIS punya 389 halaman Vue (~688.000 baris) tanpa bahasa visual tunggal. Bukti yang dikumpulkan saat brainstorming:

| Temuan | Angka |
|---|---|
| Halaman Vue | 389 |
| Komponen bersama | 14 |
| Berkas dengan `<style>` | 390, di antaranya **61 non-scoped** |
| Pustaka komponen aktif bersamaan | Bootstrap 5 (190 berkas), Element Plus (167 berkas), PrimeVue (**1 berkas**) |
| Set ikon aktif | Font Awesome, Bootstrap Icons, PrimeIcons, Element Plus Icons |

Pola UI yang berulang tapi tidak punya komponen bersama:

| Pola | Berkas | Sudah ada? |
|---|---|---|
| Lencana / chip / pill | **268** | ✅ `HcisBadge` |
| Loading / skeleton | **247** | ❌ |
| Keadaan kosong | **160** | ✅ `HcisEmptyState` |
| Notifikasi (ElMessage/toast/Swal) | **146** | ❌ |
| Pagination | **124** | ❌ |
| Modal / dialog | **118** | ❌ |
| Tabel | **87** | ❌ |
| Kartu statistik | **72** | ❌ |
| Date picker · Select | 56 · 54 | ❌ (Element) |
| Upload · Tooltip | 25 · 25 | ❌ (Element) |
| Breadcrumb · Tabs | 11 · 6 | ❌ |

**Palet bukan masalahnya.** 15 warna tersering di 389 halaman semuanya sudah menjadi token `--hcis-*` — indigo `#6366f1` muncul 804×, `#e2e8f0` 1.223×. Radius pun sebagian besar cocok (`12px` 1.349×, `8px` 1.167×, `999px` 695×, `20px` 431×); yang menyimpang hanya `10px` (640×) dan `6px` (426×). Yang hilang bukan palet, melainkan **deklarasi**: aturan pakai, larangan, dan peta migrasi.

---

## 2. Keputusan

| # | Keputusan | Pilihan |
|---|---|---|
| K1 | Cakupan akhir | Seluruh aplikasi. Definisi dibereskan sekarang, migrasi berjalan pelan. |
| K2 | Mesin widget berat | **Element Plus tetap**, tapi diwarnai ulang lewat token — tidak tampil mentah. |
| K3 | Bootstrap & PrimeVue | Dipensiunkan untuk kode baru. PrimeVue praktis mati (1 berkas). |
| K4 | Langkah pertama | **Jembatan token Element Plus** — daya ungkit terbesar; 167 berkas berubah tanpa disentuh. |
| K5 | Pembungkus Element | **Tidak dibungkus.** Halaman memakai `<el-*>` langsung; registry yang menyatakan prop mana boleh dan mana dilarang. |
| K6 | Etalase resmi | **Hanya `/design-system`.** Storybook dan Claude Design dipensiunkan. |
| K7 | Sumber deklarasi | Satu berkas `registry.js` — dirender halaman, dibaca agent. Tidak ada salinan kedua. |

### Kenapa K6

Sebelum keputusan ini ada tiga cermin: `/design-system`, Storybook, dan Claude Design. Ketiganya diperbarui manual. Cermin Claude Design **sudah terbukti berbahaya** — saat memperbaiki durasi spinner, nilai yang sama harus disunting di `design-system.css` dan di preview HTML secara terpisah. `/design-system` menang karena dibangun dari komponen `Hcis*` yang asli: kalau komponennya rusak, halamannya ikut rusak, jadi ia tidak bisa berbohong.

Berkas Claude Design sudah dihapus (9 berkas). Berkas Storybook (`.storybook/`, `*.stories.js`) **sengaja tidak dihapus** karena masih untracked di git — penghapusan akan permanen. "Pensiun" di sini berarti berhenti dirawat.

---

## 3. Arsitektur

### 3.1 Sumber tunggal — `resources/js/components/ui/registry.js`

Deklarasi tiap komponen hidup sebagai data, bukan sebagai markup. Halaman `/design-system` merendernya; `CLAUDE.md` menunjuk agent ke sana. Manusia dan agent membaca objek yang sama, sehingga dokumentasi tidak bisa tertinggal dari kode.

Dua kelas entri:

| | `owned` | `borrowed` |
|---|---|---|
| Contoh | `HcisButton`, `HcisBadge`, `HcisCard` | `el-select`, `el-date-picker`, `el-dialog` |
| Yang kita miliki | markup, perilaku, gaya | **hanya gaya** (lewat jembatan token) |
| Diubah di | berkas komponennya | `element-plus-bridge.css` |
| Larangan khas | — | menimpa selektor `.el-*` di berkas halaman |

Medan tiap entri: `name`, `kind` (`owned`/`borrowed`), `status` (`ready`/`draft`/`planned`/`deprecated`), `summary`, `usedIn` (jumlah berkas), `props`/`events`/`slots` (untuk `owned`), `allowed`/`forbidden` (untuk `borrowed`, dengan alasan), `replaces` (peta migrasi dari pola lama), `rules.do[]`, `rules.dont[]`.

### 3.2 Rute

| Rute | Isi |
|---|---|
| `/design-system` | Prinsip, alur token→komponen→layout→page, dan **tabel inventaris** seluruh komponen beserta statusnya |
| `/design-system/{komponen}` | Satu halaman per komponen |

Tabel inventaris membuat komponen yang **belum ada** ikut terlihat, sehingga tidak ada yang membuat versi lokal karena mengira belum tersedia.

Pemecahan per rute juga menjaga tiap berkas Vue tetap kecil dan aman disunting — halaman tunggal berisi 15 komponen akan tumbuh melewati batas nyaman.

### 3.3 Template tujuh blok

Setiap halaman komponen memakai urutan yang sama, tanpa kecuali:

1. **Identitas** — nama, status, dipakai di berapa berkas
2. **Anatomi** — render asli, bagian-bagian diberi label
3. **Varian** — matriks lengkap
4. **State** — default · hover · focus · disabled · loading · error
5. **API** — props, events, slots
6. **Aturan pakai** — daftar **Lakukan** dan **Jangan**
7. **Pola migrasi** — kelas/pola lama yang digantikan

Blok 6 dan 7 yang selama ini tidak ada di mana pun; keduanya yang menutup ruang tafsir.

### 3.4 Review sebagai tahap hidup halaman

Halaman komponen dibuat **sebelum** komponennya final. Blok 3 sementara menampilkan opsi A/B/C berdampingan yang dirender dengan komponen asli; setelah diputuskan, opsi yang kalah dibuang dan blok itu menjadi bentuk finalnya. Halaman review dan halaman dokumentasi adalah benda yang sama pada dua tahap hidup — tidak ada artefak terbuang.

---

## 4. Dokumen untuk agent

| Berkas | Isi | Alasan lokasi |
|---|---|---|
| `CLAUDE.md` | Aturan UI tak-bisa-ditawar, ±50 baris | Otomatis masuk konteks tiap sesi |
| `resources/js/components/ui/registry.js` | Deklarasi tiap komponen | Sumber tunggal; dibaca agent dan dirender halaman |
| `docs/design-system/element-plus-bridge.md` | Peta `--el-*` → `--hcis-*`, batas yang boleh ditimpa | Teknis dan panjang |
| `docs/design-system/migration.md` | Resep migrasi per pola lama. **Pemicu diisi pemilik repo** | Terpisah agar pemicu bisa disetel tanpa menyentuh aturan lain |

### Aturan tak-bisa-ditawar (isi `CLAUDE.md`)

1. UI baru memakai `Hcis*` dan token `--hcis-*`. Jangan menulis heksadesimal, px, atau radius langsung.
2. Element Plus adalah satu-satunya sumber widget berat. Dandani lewat jembatan token — **jangan menimpa selektor `.el-*` di berkas halaman**.
3. Bootstrap dan PrimeVue tidak dipakai untuk kode baru.
4. **Dilarang membuat komponen bersama baru tanpa persetujuan pemilik repo.** Kalau butuh yang belum ada, laporkan — jangan bikin varian lokal.
5. `<style>` di komponen halaman wajib `scoped`.
6. Setiap pembatasan wajib dilaporkan (top-N, limit, sampling).
7. Jangan mendefinisikan ulang arah design system. Sampaikan sebagai usulan, jangan langsung ubah.

Aturan 4 dan 7 secara khusus mencegah agent "mendefinisikan baru".

---

## 5. Urutan kerja

### Aturan pengaman: tahap ini tidak mengubah tampilan aplikasi

Seluruh pekerjaan di bawah ini **hanya menambah permukaan deklarasi**. Tidak ada halaman yang disunting, dan tidak ada perubahan yang terlihat oleh pengguna aplikasi. Penyesuaian halaman baru dimulai setelah deklarasinya dianggap mantap, sebagai keputusan terpisah.

Konsekuensi penting untuk jembatan token: `element-plus-bridge.css` **tidak diimpor global** pada tahap ini. Kalau diimpor di `app.js`, ia seketika mengubah rupa `<el-*>` di 167 halaman — mengubah aplikasi tanpa menyentuh satu berkas halaman pun. Karena itu jembatan dibangun dengan **ruang lingkup terbatas**: hanya berlaku di dalam rute `/design-system`, dibungkus kelas induk (mis. `.ds-preview`). Dengan begitu hasilnya bisa dilihat dan disepakati lebih dulu. Mengaktifkannya secara global adalah langkah tersendiri yang memerlukan persetujuan eksplisit.

### Tahapan

1. **Kerangka deklarasi** — `registry.js` + beranda inventaris + halaman `Button` lengkap tujuh blok, sebagai contoh template
2. **Jembatan token Element Plus** (`element-plus-bridge.css`), **berlingkup `/design-system` saja** — supaya rupanya bisa dinilai tanpa menyentuh produksi
3. **Putaran review 1–4**, tiap putaran menghasilkan halaman komponen final:
   - Putaran 1 — Aksi & status: Button, Badge, StatCard
   - Putaran 2 — Formulir: Field, Select, DatePicker, Upload
   - Putaran 3 — Data & navigasi: Table, Pagination, Modal, Tabs, Breadcrumb
   - Putaran 4 — Umpan balik: Loading/Skeleton, Notification, EmptyState, Tooltip
4. **`CLAUDE.md`** ditulis setelah registry stabil

Setelah keempatnya selesai dan dianggap mantap, barulah dibahas langkah berikutnya: mengaktifkan jembatan token secara global dan menetapkan pemicu migrasi (§7 B1). Keduanya **di luar tahap ini**.

---

## 6. Batas (non-goals)

- **Tidak menyentuh satu pun halaman yang sudah ada pada tahap ini** — tidak menyunting berkasnya, dan tidak mengubah tampilannya lewat CSS global. Deklarasi dulu sampai mantap; penyesuaian menyusul sebagai keputusan terpisah.
- **Bukan** menulis ulang 389 halaman. Migrasi berjalan pelan; pemicunya ditentukan pemilik repo.
- **Bukan** membangun pengganti Element Plus. Widget berat tetap milik Element.
- **Tidak** ada mode gelap pada tahap ini. Token saat ini `color-scheme: light` saja.
- **Tidak** ada pembungkus `Hcis*` untuk komponen Element (K5).
- **Tidak** ada penghapusan Bootstrap dari halaman lama sebagai pekerjaan tersendiri — ia luruh mengikuti migrasi.

---

## 7. Belum diputuskan

| # | Butir | Penentu |
|---|---|---|
| B1 | **Pemicu migrasi** halaman lama | Pemilik repo. Catatan: `DesignSystem.vue:86` **sudah menyatakan** "dimigrasikan saat ada perubahan fitur" — perlu disahkan atau diganti. |
| B2 | 11 keputusan Putaran 1 (Button ×5, Badge ×5, StatCard ×3 → lihat §5) | Diputuskan saat review, sambil melihat |
| B3 | Nasib berkas Storybook | Dipensiunkan; dihapus atau tidak menunggu keputusan eksplisit |
| B4 | Dua radius menyimpang (`10px`, `6px`) | Dibulatkan ke token terdekat atau ditokenkan |

---

## 8. Temuan sampingan

Ditemukan saat penelusuran, di luar cakupan tapi layak dicatat:

- **`app.blade.php:53`** menulis `font-family: 'Plus Jakarta Sans', 'Nunito', …` padahal Plus Jakarta Sans tidak pernah dimuat — tidak ada `@font-face`, berkas font, maupun tautan CDN. Deklarasi mati yang menyesatkan pembaca. Yang benar-benar dipakai adalah Nunito.
- **PrimeVue + `@primeuix/themes` + `primeicons`** terpasang untuk 1 berkas. Kandidat pencabutan dependensi, terkait beban bundel login.
- **`design-system.css`** blok `prefers-reduced-motion` semula memampatkan `animation-duration` ke `0.01ms` tanpa menyetel `animation-iteration-count`, sehingga spinner `infinite` berputar ±100.000 kali per detik. Sudah diperbaiki; pola rusak yang sama patut dicari di CSS lain.
- **`hcis-btn--md`** dihasilkan oleh `HcisButton.vue:33` tapi tidak punya aturan CSS. Ukuran atau varian yang salah ketik jatuh diam-diam ke bawaan tanpa peringatan.
