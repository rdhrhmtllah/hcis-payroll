# Menerapkan `design.md` ke lapis 2 design system

Tanggal: 13 Agustus 2026 · Branch: `feat/system-design`

## Maksud dalam satu kalimat

Seluruh nilai token di `design.md` dipasang apa adanya sebagai **lapis 2 (peran)**
`resources/css/design-system.css`, dan ~110 komponen `Hcis*` dirapikan supaya
hasilnya terbaca sebagai keputusan, bukan sebagai kerusakan.

## Amandemen — 13 Agustus 2026, sesudah implementasi

Sebagian besar nilai `design.md` **dicabut** setelah hasilnya terlihat di
layar — dicoba dulu, bukan diduga. Bagian 1.1, 1.2, 1.4, 1.6, dan 2
tergantikan oleh catatan ini.

| Yang dicabut | Kembali ke | Sebab, terukur |
| --- | --- | --- |
| Palet teks (`text.primary` `#607080`, `text.tertiary` `#475569`) | slate-900/800/500/400 | Rentang total teks runtuh **6,96:1 → 1,07:1**; `strong` dan `text` jadi **identik** (1,00:1); dan urutannya **terbalik** — `text` 5,09:1 sementara `muted` 7,58:1, jadi judul lebih pudar daripada keterangannya. Ini penyebab utama kesan "semuanya menyatu". |
| Skala huruf (10,4–13,6px) | 11–32px | Delapan tingkat hierarki muat di rentang 3,2px: judul kartu (13,12px) praktis sebesar isinya (11,52px). |
| Skala jarak (1–6,72px) | 4–64px | Padding kartu jatuh ke 5px sehingga isinya menempel ke tepi; `--sm`/biasa/`--lg` berselisih 2,7px total sehingga varian ukurannya hilang tanpa pernah dihapus. |
| `color.surface.base` `#000000` | `var(--hcis-white)` | Kartu hitam berisi teks `#607080`. Bukan tema gelap — satu token terbalik sendirian, karena `design.md` tidak menyediakan pasangan teksnya. |
| `color.text.inverse` `#334155` | `var(--hcis-white)` | Dipakai di ATAS `--hcis-surface-inverse` (slate-900): 1,9:1. |
| `color.surface.raised` `#f8fafc` | `var(--hcis-white)` | Persis sama dengan `--hcis-surface-soft` → **1,00:1**. Permukaan yang namanya "terangkat" sewarna dengan yang cekung. |
| `shadow.1` sebagai `--hcis-shadow-sm` | tangga lama; `shadow.1` jadi `--hcis-shadow-raised` | Bayangan dekatnya alfa 0,05 (setengah `-sm`), jadi panel putih di atas halaman slate-50 kehilangan separasinya — dan kartu vs halaman hanya 1,05:1, jadi bayangan menanggung seluruh pekerjaan pemisahan. Sorot dalamnya putih 0,9: di atas permukaan putih ia tidak menggambar apa pun. |

**Satu sebab yang sama di semuanya.** Ekstraktor `design.md` membaca computed
style dari elemen yang perannya bukan seperti nama tokennya: lencana dan
keterangan kaki untuk "skala huruf", `gap` ikon untuk "skala jarak", teks
sekunder untuk `text.primary`, permukaan yang kebetulan gelap untuk
`surface.base`. Berkasnya sendiri sudah memperingatkan — *"inference
confidence is low; verify generated brand context"*.

**Yang tetap terpasang, dan justru bagian `design.md` yang nilainya sehat:**
`'Nunito', sans-serif`; radius 10–15,2px (8 langkah, `calc()` dibongkar);
`shadow.4` sebagai `--hcis-shadow-xs`; `shadow.1` sebagai `--hcis-shadow-raised`
untuk permukaan bertint/berkaca; keluarga `glow-*` (`shadow.2` untuk merek,
`shadow.3` untuk keadaan); tangga gerak delapan tingkat 160–500ms;
`surface.strong` `#6366f1` pada `--hcis-surface-brand`; `border.strong`
`#64748b` pada `--hcis-border-strong` — yang ini justru **menaikkan** kontras
batas kontrol dari 3,47:1 ke 4,76:1.

Polanya: yang bertahan adalah properti yang **nilainya sama dari elemen mana
pun ia diambil** — radius, bayangan, durasi, keluarga huruf. Yang gugur adalah
**skala** dan **peran**, karena keduanya soal hubungan antarelemen, dan
hubungan tidak bisa dibaca dari satu elemen.

Enam tes penjaga arah ditambahkan supaya yang dicabut tidak kembali diam-diam
lewat penyalinan ulang `design.md`: `#000000` pada `--hcis-surface`, rasio
display÷isi di atas 2, padding kartu ≥ 12px, `raised` ≠ `soft`, tangga teks
menurun terang dengan rentang ≥ 400 langkah slate, dan tangga bayangan tidak
boleh dialiaskan ke satu tingkat.

**Yang tidak jadi diperlukan** karena permukaan kembali terang: pembalikan
`gradient-hero`, token `--hcis-icon-*`, dan sorotan baris yang menerangkan.
Yang **tetap** dari bagian 2 adalah token `--hcis-row-*` — bukan karena
warnanya, tapi karena ia memperbaiki cacat lain yang ketemu di jalan (lihat
bawah).

## Cacat yang ketemu saat mengerjakan

Sorotan baris tabel ditulis **dua kali dengan angka berbeda**: pada `tr` di
`design-system.css` dan pada `td` di `HcisTable.vue`. Latar `td` selalu menang
atas latar `tr`, jadi nilai di `design-system.css` tidak pernah terlihat —
dan siapa pun yang menyetelnya di sana akan menyimpulkan perubahannya tidak
berpengaruh, lalu mencari sebabnya di tempat yang salah. Sekarang keduanya
menunjuk `--hcis-row-hover` / `-striped` / `-selected`, dan ada tes yang
membaca berkas komponennya langsung.

## Keputusan pemilik proyek

Tiga keputusan diambil di percakapan 13 Agustus 2026 dan **tidak dibuka lagi**:

1. `design.md` diterapkan **apa adanya**, termasuk skala huruf 10,4–13,6px,
   skala jarak 1–6,72px, dan `surface.base=#000000`.
2. Nilainya mendarat di **lapis 2**, jadi baku global — bukan sebagai tema
   lapis 3 yang bisa dinyalakan.
3. Pemetaan permukaan dibaca **ordinal**: `surface.base` → `--hcis-surface`,
   yaitu permukaan kartu/dialog/dropdown/input, bukan kanvas halaman.

Konsekuensi yang sudah disampaikan dan diterima: `#607080` di atas `#000000`
menghasilkan kontras 4,2:1. Karena seluruh anak tangga huruf jatuh di
10,4–13,6px, semuanya tergolong "teks normal" dengan ambang 4,5:1 — jadi
bagian aksesibilitas `design.md` akan gagal terhadap bagian warnanya sendiri.
Ini dicatat sekali di sini dan tidak diulang di tempat lain.

## Radius ledak sebenarnya

Alias warisan di `app.css` menunjuk ke **primitif**, bukan ke token peran:

```css
--surface: var(--hcis-white);      --text: var(--hcis-slate-700);
--border: var(--hcis-slate-200);   --primary: var(--hcis-brand-500);
```

Karena primitif tidak disentuh, ~800 halaman warisan **tidak** ikut berubah.
Yang berubah: komponen `resources/js/components/ui/`, halaman
`resources/js/Pages/DesignSystem/`, dan `Pages/Activity/SystemHealth.vue`
(satu-satunya halaman yang sudah memanggil `useElementBridge()`).

---

## 1 · Peta token

### 1.1 Huruf

`design.md` memberi delapan anak tangga (`xs`…`4xl`); kita punya tujuh nama.
Pemetaan ordinal, ditambah satu nama baru untuk anak tangga kedelapan.

| Token | Sekarang | Jadi | Sumber |
| --- | --- | --- | --- |
| `--hcis-font-size-2xs` | 0.6875rem (11px) | `0.65rem` (10,4px) | `font.size.xs` |
| `--hcis-font-size-xs` | 0.75rem (12px) | `0.68rem` (10,88px) | `font.size.sm` |
| `--hcis-font-size-sm` | 0.875rem (14px) | `0.72rem` (11,52px) | `font.size.md` |
| `--hcis-font-size-md` | 1rem (16px) | `0.78rem` (12,48px) | `font.size.lg` |
| `--hcis-font-size-lg` | 1.125rem (18px) | `0.8rem` (12,8px) | `font.size.xl` |
| `--hcis-font-size-xl` | 1.5rem (24px) | `0.8125rem` (13px) | `font.size.2xl` |
| `--hcis-font-size-2xl` | 2rem (32px) | `0.82rem` (13,12px) | `font.size.3xl` |
| `--hcis-font-size-3xl` | — | `0.85rem` (13,6px) | `font.size.4xl` **(baru)** |
| `--hcis-font-size-base` | — | `1rem` (16px) | `font.size.base` **(baru)** |
| `--hcis-line-base` | — | `1.5` (24px/16px) | `font.lineHeight.base` **(baru)** |

`--hcis-font-body` jadi `'Nunito', sans-serif` persis seperti
`font.family.stack` — cadangan `'Segoe UI', system-ui, -apple-system` dilepas.
`resources/views/app.blade.php:53` ikut diselaraskan; sekarang ia menyebut
`'Plus Jakarta Sans'` lebih dulu, dan ada penjaga yang menuntut keluarga huruf
seragam antar berkas supaya tidak terjadi pergantian huruf sesaat setelah
halaman muncul.

`--hcis-weight-regular: 400` sudah sama dengan `font.weight.base`; tangga bobot
500/600/700 tidak disebut `design.md` dan dipertahankan.

**Yang harus diketahui pembaca kode nanti:** `design.md` menetapkan
`font.size.base=16px` padahal puncak skalanya 13,6px. Jadi teks yang lewat
`.hcis-type-*` akan **lebih kecil** dari teks yang mewarisi akar. Itu ada di
sumbernya; jangan "perbaiki" tanpa bicara.

### 1.2 Jarak

| Token | Sekarang | Jadi | Sumber |
| --- | --- | --- | --- |
| `--hcis-space-1` | 0.25rem | `0.0625rem` (1px) | `space.1` |
| `--hcis-space-2` | 0.5rem | `0.2rem` (3,2px) | `space.2` |
| `--hcis-space-3` | 0.75rem | `0.22rem` (3,52px) | `space.3` |
| `--hcis-space-4` | 1rem | `0.25rem` (4px) | `space.4` |
| `--hcis-space-5` | 1.25rem | `0.32rem` (5,12px) | `space.5` |
| `--hcis-space-6` | 1.5rem | `0.375rem` (6px) | `space.6` |
| `--hcis-space-7` | — | `0.38rem` (6,08px) | `space.7` **(baru)** |
| `--hcis-space-8` | 2rem | `0.42rem` (6,72px) | `space.8` |
| `--hcis-space-10` | 2.5rem | `0.525rem` (8,4px) | ekstrapolasi |
| `--hcis-space-12` | 3rem | `0.63rem` (10,08px) | ekstrapolasi |
| `--hcis-space-16` | 4rem | `0.84rem` (13,44px) | ekstrapolasi |

`design.md` berhenti di langkah 8. Tiga token sisanya diteruskan pada slope
yang sama — `space.8 / 8 = 0,84px` per langkah — bukan dipertahankan pada
nilai lama. Mempertahankan yang lama akan menghasilkan lompatan 6,72px → 40px
di tengah satu skala, dan itu persis "one-off spacing exception" yang
`design.md` larang.

### 1.3 Radius

| Token | Sekarang | Jadi | Sumber |
| --- | --- | --- | --- |
| `--hcis-radius-base` | 0.625rem (10px) | tetap | `radius.xs` — sudah sama |
| `--hcis-radius-sm` | 6px | `0.72rem` (11,52px) | `radius.sm` |
| `--hcis-radius-md` | 8px | `0.75rem` (12px) | `radius.md` |
| `--hcis-radius-lg` | 10px | `0.8rem` (12,8px) | `radius.lg` |
| `--hcis-radius-xl` | 14px | `0.82rem` (13,12px) | `radius.xl` |
| `--hcis-radius-2xl` | — | `0.875rem` (14px) | `radius.2xl` **(baru)** |
| `--hcis-radius-3xl` | — | `0.9rem` (14,4px) | `radius.step7` **(baru)** |
| `--hcis-radius-4xl` | — | `0.95rem` (15,2px) | `radius.step8` **(baru)** |
| `--hcis-radius-pill` | 999px | tetap | struktural, bukan skala |

Turunan `calc(var(--hcis-radius-base) * n)` dibongkar jadi nilai langsung —
skala `design.md` bukan skala berkelipatan, jadi rumusnya tidak lagi berlaku.

Efek yang perlu disadari: rentangnya menyempit dari 6–14px jadi 10–15,2px, dan
elemen kecil (`radius-sm`, dipakai di dalam kontrol) sekarang **lebih bulat**
daripada tombol. Itu bawaan urutan di `design.md`.

### 1.4 Bayangan

`design.md` memberi empat. Semua ditulis ulang ke bentuk beralfa bertoken,
**satu baris per token** — penjaga NOL hanya menerima nilai mentah di baris
yang cocok `^\s*--hcis-[\w-]+\s*:`, jadi nilai multi-baris akan gagal tes.
`rgba()` tetap dilarang.

| Token | Nilai | Sumber |
| --- | --- | --- |
| `--hcis-shadow-xs` | `0 1px 3px 0 rgb(var(--hcis-shadow-rgb) / 0.04)` | `shadow.4` |
| `--hcis-shadow-sm` | `0 1px 2px 0 rgb(var(--hcis-shadow-rgb) / 0.05), 0 10px 26px -16px rgb(var(--hcis-shadow-rgb) / 0.22), inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)` | `shadow.1` |
| `--hcis-glow-brand` | `0 8px 18px -8px rgb(var(--hcis-brand-500-rgb) / 0.6)` | `shadow.2` |
| `--hcis-glow-warning` | `0 0 0 4px rgb(var(--hcis-warning-500-rgb) / 0.12), 0 22px 44px -20px rgb(var(--hcis-warning-500-rgb) / 0.4), inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)` | `shadow.3` |

`--hcis-shadow-rgb` sudah `slate-900-rgb` = `15 23 42`, persis rona `shadow.1`
dan `shadow.4`. `--hcis-brand-500-rgb` = `99 102 241` dan
`--hcis-warning-500-rgb` = `245 158 11`, keduanya cocok tanpa primitif baru.

Token yang tidak punya sumber di `design.md` **dialiaskan**, tidak dikarang:

```css
--hcis-shadow-md: var(--hcis-shadow-sm);
--hcis-shadow-lg: var(--hcis-shadow-sm);
--hcis-shadow-card: var(--hcis-shadow-sm);
--hcis-shadow-brand: var(--hcis-shadow-sm);
--hcis-shadow-glow: var(--hcis-glow-brand);
```

Artinya sistem turun dari empat tingkat ketinggian jadi dua — persis sebanyak
yang `design.md` sediakan. Mengarang tingkat ketiga sama dengan mengarang
pengecualian.

`--hcis-glow-success` dan `--hcis-glow-danger` wajib ada (dituntut penjaga
`token bahasa visual lengkap`) tapi tak disebut `design.md`. Keduanya
diturunkan dari resep `shadow.3` — cincin 4px + halo + sorot dalam — dengan
rona diganti ke `success-500-rgb` / `danger-500-rgb`. Alasan memilih resep
`shadow.3` dan bukan `shadow.2`: `shadow.3` adalah satu-satunya contoh keadaan
berwarna di `design.md`, dan keluarga `glow-*` harus satu dialek.

### 1.5 Gerak

| Token | Sekarang | Jadi | Sumber |
| --- | --- | --- | --- |
| `--hcis-duration-press` | 75ms | `160ms` | `motion.duration.instant` |
| `--hcis-duration-fast` | 150ms | `200ms` | `motion.duration.fast` |
| `--hcis-duration-normal` | 200ms | `220ms` | `motion.duration.normal` |
| `--hcis-duration-slow` | — | `240ms` | `motion.duration.slow` **(baru)** |
| `--hcis-duration-slower` | — | `250ms` | `motion.duration.slower` **(baru)** |
| `--hcis-duration-panel` | — | `300ms` | `motion.duration.step6` **(baru)** |
| `--hcis-duration-slide` | — | `380ms` | `motion.duration.step7` **(baru)** |
| `--hcis-duration-page` | — | `500ms` | `motion.duration.step8` **(baru)** |
| `--hcis-slide-duration` | 320ms | `var(--hcis-duration-slide)` | 380ms |

Kickback tombol melambat dari 75ms ke 160ms. Itu terasa — CLAUDE.md §4
menyebut kickback sebagai umpan balik yang terjadi tepat di bawah jari
pengguna, dan 160ms sudah di ambang terasa lamban. Tetap dipasang; kalau ingin
dikembalikan, itu keputusan terpisah.

`--hcis-ease` dan `--hcis-ease-spring` tidak disebut `design.md` dan tidak
disentuh.

### 1.6 Warna

Primitif baru — **satu**, dan itulah seluruh pelebaran paletnya:

```css
--hcis-steel-500-rgb: 96 112 128;
--hcis-steel-500: rgb(var(--hcis-steel-500-rgb));
```

`#607080` tidak ada di keluarga slate mana pun; ia rona sendiri yang lebih
hijau. Ditulis dalam bentuk `-rgb` lebih dulu mengikuti pola seluruh lapis 1,
supaya bisa dipakai beralfa tanpa `rgba()`.

`--hcis-black` dan `--hcis-black-rgb` **sudah ada** (baris 119 dan 191) —
tidak perlu ditambah. Delapan warna `design.md` sisanya cocok persis dengan
primitif yang sudah hidup.

Lapis 2:

| Token | Jadi | Sumber `design.md` |
| --- | --- | --- |
| `--hcis-surface` | `var(--hcis-black)` | `color.surface.base` `#000000` |
| `--hcis-surface-rgb` | `var(--hcis-black-rgb)` | turunan wajib |
| `--hcis-surface-raised` | `var(--hcis-slate-50)` | `color.surface.raised` `#f8fafc` |
| `--hcis-surface-soft` | `var(--hcis-white)` | `color.surface.muted` `#ffffff` |
| `--hcis-surface-brand` | `var(--hcis-indigo-500)` | `color.surface.strong` `#6366f1` |
| `--hcis-surface-inverse` | `var(--hcis-white)` | diturunkan — kebalikan surface |
| `--hcis-text` | `var(--hcis-steel-500)` | `color.text.primary` `#607080` |
| `--hcis-text-strong` | `var(--hcis-steel-500)` | `color.text.primary` |
| `--hcis-text-muted` | `var(--hcis-slate-600)` | `color.text.tertiary` `#475569` |
| `--hcis-text-subtle` | `var(--hcis-slate-500)` | diturunkan dari `border.strong` |
| `--hcis-text-inverse` | `var(--hcis-slate-700)` | `color.text.inverse` `#334155` |
| `--hcis-border` | `var(--hcis-slate-200)` | `color.border.default` — sudah sama |
| `--hcis-border-strong` | `var(--hcis-slate-500)` | `color.border.strong` `#64748b` |

`--hcis-text-strong` dan `--hcis-text` jadi kembar karena `design.md` hanya
punya satu warna teks utama. Hierarki teks setelah ini dibawa **bobot**, bukan
warna.

---

## 2 · Merapikan yang terbalik

`--hcis-surface` hitam membalik arah terang seluruh permukaan tertutup: kartu,
dialog, dropdown, tooltip, popover, dan isian jadi gelap sementara halamannya
terang. Kalau dibiarkan begitu saja, hasilnya terbaca sebagai kerusakan.

Arah rasa yang dituju: **obsidian** — kartu hitam pekat, garis rambut terang,
sorot dalam putih dari `shadow.1` di tepi atas tiap permukaan, indigo `#6366f1`
sebagai satu-satunya warna kuat. `shadow.1` memang sudah membawa
`inset 0 1px 0 rgb(255 255 255 / .9)` untuk itu, dan
`--hcis-glass-border: rgb(var(--hcis-white-rgb) / 0.55)` yang sudah ada
kebetulan justru menguat di atas kaca gelap.

Keputusan per titik:

- `--hcis-glass-bg` dan `-bg-strong` ikut jadi kaca hitam lewat
  `--hcis-surface-rgb`. **Dibiarkan** — itu justru yang membuat panel kaca
  terbaca di atas halaman terang.
- `--hcis-gradient-hero` memakai `var(--hcis-surface)` di titik tengah, jadi
  gradasinya kini brand-50 → **hitam** → brand-100. Titik tengahnya
  **diganti ke `--hcis-surface-soft`** (`#ffffff`). Alasannya: hero adalah
  area besar di belakang teks judul, dan lompatan terang→hitam→terang di satu
  gradasi bukan "gradasi lembut" seperti yang CLAUDE.md §2b maksud. Ini
  perubahan pada penyusun gradasi, bukan pada nilai `design.md` mana pun.
- Ikon di dalam permukaan gelap mewarisi `currentColor` dari `--hcis-text`,
  jadi ikut otomatis. Yang **tidak** ikut adalah ikon yang warnanya dikunci
  varian (sukses/bahaya/peringatan); ketiganya dinaikkan satu langkah rona
  (`-500` → `-400`) hanya di dalam permukaan gelap.
- Latar hover baris tabel dan baris terpilih pindah dari menggelapkan jadi
  **menerangkan**: `rgb(var(--hcis-white-rgb) / 0.06)` dan `/ 0.1`.
  Menggelapkan di atas hitam tidak menghasilkan perubahan yang terlihat.
- `--hcis-scrim` (`slate-900 / 0.45`) tetap. Dialognya kini juga hitam, jadi
  batas antara tirai dan dialog ditegaskan oleh tepi kaca terang
  (`--hcis-glass-border`) dan sorot dalam `shadow.1`, bukan oleh beda terang.

Anggaran CLAUDE.md §2b tetap berlaku dan dijaga tes: kaca maksimal 3 area,
gradasi besar maksimal 6 di halaman inti design system.

---

## 3 · Matriks keadaan

Ini bagian `design.md` yang tidak berasal dari ekstraksi mesin, dan bagian yang
paling menaikkan mutu pemakaian.

**Aturan:** setiap komponen wajib menetapkan tujuh keadaan — `default`,
`hover`, `focus-visible`, `active`, `disabled`, `loading`, `error`. Sebuah
keadaan boleh ditandai tidak berlaku, tapi **wajib beserta alasannya**.
Yang dilarang adalah tidak memutuskan — pola yang sama dengan penjaga mode
gerak yang sudah ada.

**Tempatnya:** `resources/js/components/ui/registry.js`, tiga bidang baru per
entri:

- `keadaan` — tujuh kunci di atas, tiap nilai menjelaskan rupanya atau alasan
  tidak berlakunya.
- `interaksi` — perilaku papan ketik, penunjuk, dan sentuh.
- `tepian` — konten panjang, luapan, dan keadaan kosong.

**Penegaknya:** satu pemeriksaan NOL baru di `designSystemGuards.test.js` yang
menolak entri dengan bidang bolong.

**Hasil sampingnya:** `Pages/DesignSystem/Component.vue` merender ketiganya
dari registry, jadi halaman katalog menampilkan setiap keadaan secara langsung
dan dokumentasi tidak bisa lagi berbeda dari kontraknya.

**Urutan pengerjaan** mengikuti kepadatan yang `design.md` catat sendiri:
tombol (148 kemunculan) lebih dulu, lalu kartu (39), baru tautan (4),
navigasi (4), dan daftar (2).

---

## 4 · Yang tidak dikerjakan

- Halaman warisan tidak disentuh (CLAUDE.md §5).
- Penggantian `<i class="bi …">` ke `<HcisIcon>` tetap dilunasi per halaman
  saat halamannya disentuh, bukan disapu di sini.
- Tema lapis 3 `laut` dan `hutan` tidak disesuaikan ulang. Keduanya hanya
  menimpa rona merek, jadi tetap berfungsi — tapi di atas permukaan hitam
  hasilnya belum dilihat mata siapa pun.

---

## 5 · Verifikasi

```bash
npm run test:unit
npm run build; echo $?
```

Yang paling mungkin gagal, beserta sebabnya:

| Penjaga | Kenapa bisa gagal |
| --- | --- |
| NOL warna mentah di berkas fondasi | nilai bayangan ditulis multi-baris; hex mendarat di baris lanjutan |
| NOL `!important` di tengah daftar koma | bayangan berdaftar koma yang ditambah `!important` per lapis |
| `token bahasa visual lengkap` | `--hcis-glow-success` / `-danger` lupa didefinisikan |
| RATCHET warna mentah halaman warisan (24.834) | angka hanya boleh turun |
| RATCHET `!important` komponen (269) | merapikan permukaan gelap dengan `!important` |
| keluarga huruf seragam antar berkas | `app.blade.php` belum ikut jadi `'Nunito', sans-serif` |
| NOL keputusan mode gerak | aturan `animation` baru tanpa entri di blok `[data-hcis-motion='reduced']` |

Angka ratchet **tidak boleh dinaikkan**. Kalau salah satunya gagal, itu sinyal
cara mengerjakannya yang salah, bukan ambangnya yang salah.

Verifikasi mata, tidak bisa diwakili tes: `/design-system`, `/design-system`
tiap komponen, dan `Pages/Activity/SystemHealth.vue` — halaman terakhir itu
satu-satunya halaman nyata yang ikut berubah, jadi ia patokan apakah hasilnya
terbaca sebagai keputusan atau sebagai kerusakan.
