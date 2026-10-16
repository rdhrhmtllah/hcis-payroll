# HCIS Enterprise Suite — Spesifikasi & Pakem Lengkap Design System

> **STATUS DOKUMEN: AUTHORITATIVE & BINDING (SUMBER TUNGGAL KEBENARAN)**  
> Seluruh agen AI, pengembang frontend, dan desainer wajib mematuhi seluruh spesifikasi teknis, formula CSS, arsitektur token, standar ikon vektor, dan aturan komponen di dalam dokumen ini tanpa improvisasi liar atau penyimpangan.

---

## DAFTAR ISI
1. [Prinsip Desain & Visual Core](#1-prinsip-desain--visual-core)
2. [Standar Ikon Vektor Resmi (Larangan Emoji Keyboard)](#2-standar-ikon-vektor-resmi-larangan-emoji-keyboard)
3. [Token Desain: Warna, Gradasi & Efek Kristal](#3-token-desain-warna-gradasi--efek-kristal)
4. [Token Desain: Keseragaman Sudut (Border Radius)](#4-token-desain-keseragaman-sudut-border-radius)
5. [Token Desain: Elevasi, Bayangan & Pembiasan Kaca](#5-token-desain-elevasi-bayangan--pembiasan-kaca)
6. [Sistem Gerak & Animasi Kontekstual (Motion Suite)](#6-sistem-gerak--animasi-kontekstual-motion-suite)
7. [Spesifikasi Komponen: HcisButton](#7-spesifikasi-komponen-hcisbutton)
8. [Spesifikasi Komponen: HcisBadge](#8-spesifikasi-komponen-hcisbadge)
9. [Spesifikasi Komponen: HcisIcon](#9-spesifikasi-komponen-hcisicon)
10. [Spesifikasi Komponen: HcisResultState](#10-spesifikasi-komponen-hcisresultstate)
11. [Spesifikasi Komponen Lanjutan (StatCard, DataTable, Dialog, FilterBar)](#11-spesifikasi-komponen-lanjutan)
12. [Standar Maksimalisasi & Ekstensi Komponen Reusable](#12-standar-maksimalisasi--ekstensi-komponen-reusable)
13. [Jaminan Kompatibilitas ke Belakang (Zero Breaking Changes)](#13-jaminan-kompatibilitas-ke-belakang-zero-breaking-changes)
14. [Standar Form Validation, Error Handling & Tabular Numbers](#14-standar-form-validation-error-handling--tabular-numbers)
15. [Standar Responsivitas & Breakpoints Layar](#15-standar-responsivitas--breakpoints-layar)
16. [Standar Pembuatan Halaman Demo & Studio Interaktif](#16-standar-pembuatan-halaman-demo--studio-interaktif)
17. [12 Aturan Baku & Larangan Keras Bagi Agen AI](#17-12-aturan-baku--larangan-keras-bagi-agen-ai)

---

## 1. PRINSIP DESAIN & VISUAL CORE

### 💎 1.1. Crystal Glassmorphism (Kaca Kristal Presisi)
- **Gradasi Diagonal 135°**: Semua elemen permukaan tidak menggunakan warna datar (*flat single hex*), melainkan gradasi linier 135° (`linear-gradient(135deg, ...)`) untuk menciptakan pantulan prisma kristal alami.
- **Sub-Pixel Border 1px**: Setiap komponen dibingkai garis batas semi-transparan `1px solid rgba(warna, 0.28)` yang menjaga ketegasan batas kontur di atas latar terang maupun gelap.
- **Top Crystal Inset Sheen**: Setiap komponen memiliki pantulan cahaya bagian atas melalui `box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85)`.
- **Pembiasan Kaca Nyata (*Real Glass Refraction*)**: Menggunakan `backdrop-filter: blur(12px-16px) saturate(180%)` untuk menyerap dan membiaskan warna di balik permukaan.

### 📐 1.2. Pakem Keseragaman Sudut Kelengkungan (*Unified Border Radius*)
- **Aturan Baku**: Semua tombol, isian input text, dropdown select, dan bilah pencarian **WAJIB MEMILIKI RADIUS YANG SAMA (8px / `var(--hcis-radius-md)`)** di semua tingkatan ukuran (`xs`, `sm`, `md`, `lg`).
- **Pill Capsule**: Bentuk kapsul penuh (`9999px` / `var(--hcis-radius-pill)`) secara eksklusif hanya untuk `HcisBadge`, *tag chips*, dan tombol dengan prop eksplisit `pill`.

### 🎯 1.3. Perataan Optis Presisi (*Dead-Center Optical Centering*)
- Tombol dan wadah ikon menggunakan `display: inline-flex; align-items: center; justify-content: center; line-height: 1;`.
- Ikon SVG / Bootstrap Icons diatur `flex-shrink: 0; display: inline-flex;` untuk menjamin tidak ada pergeseran 1-pixel vertikal saat teks diubah.

---

## 2. STANDAR IKON VEKTOR RESMI (LARANGAN EMOJI KEYBOARD)

### 🚫 2.1. Larangan Mutlak Karakter Emoji Keyboard / Unicode
- **DILARANG KERAS** menggunakan karakter emoji keyboard / unicode mentah (seperti ❌, ⚠️, 🔍, 💾, ⚙️, 🔔, 🌑, 🔮, 🏷️, 🚀, dll.) di dalam template komponen produksi, tombol antarmuka, header tabel, pesan validasi, atau lencana status.
- **ALASAN**:
  1. Emoji memiliki rendering yang berbeda-beda secara drastis di tiap OS (Windows, macOS, iOS, Android, Linux).
  2. Emoji tidak dapat diwarnai secara presisi dengan CSS `currentColor` atau token sentiment.
  3. Emoji tidak dapat dianimasikan secara kontekstual dengan keyframes CSS tingkat tinggi.
  4. Emoji merusak citra enterprise grade dan standar estetika HCIS.

### ✅ 2.2. Kewajiban Menggunakan Ikon Vektor Resmi (`HcisIcon`)
- Seluruh ikon antarmuka wajib dirender menggunakan komponen `<HcisIcon name="..." />` berbasis **Bootstrap Icons (`bi-*`)** atau **Lucide Icons**.
- **Contoh Konversi Baku**:
  - Hapus/Batal ➔ `<HcisIcon name="x-circle-fill" />` atau `<HcisIcon name="trash3" />` *(Bukan ❌ / 🗑️)*
  - Peringatan/Warning ➔ `<HcisIcon name="exclamation-triangle-fill" />` *(Bukan ⚠️)*
  - Sukses/Disetujui ➔ `<HcisIcon name="check-circle-fill" />` *(Bukan ✅)*
  - Pencarian ➔ `<HcisIcon name="search" />` *(Bukan 🔍)*
  - Pengaturan ➔ `<HcisIcon name="gear-fill" />` *(Bukan ⚙️)*
  - Unduh/Simpan ➔ `<HcisIcon name="download" />` / `<HcisIcon name="floppy" />` *(Bukan 💾)*
  - Jam Pasir/Pending ➔ `<HcisIcon name="hourglass-split" />` *(Bukan ⏳)*
  - Lonceng/Notifikasi ➔ `<HcisIcon name="bell-fill" />` *(Bukan 🔔)*

---

## 3. TOKEN DESAIN: WARNA, GRADASI & EFEK KRISTAL

### 🎨 3.1. Matriks 9 Palet Sentiment

| Kode Varian | Nama Palet | Gradasi Latar Belakang (Light / Subtle) | Warna Border (1px) | Warna Teks | Gradasi Solid (`appearance="solid"`) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`primary` / `brand`** | Royal Sapphire | `linear-gradient(135deg, #4f46e5 0%, #4338ca 100%)` *(Button)* / `rgba(99,102,241,0.12)` *(Badge)* | `rgba(99,102,241,0.35)` | `#ffffff` *(Btn)* / `#4338ca` *(Badge)* | `#4f46e5 ➔ #4338ca` |
| **`obsidian`** | Dark Obsidian | `linear-gradient(135deg, #1e293b 0%, #0f172a 100%)` | `rgba(255,255,255,0.16)` | `#ffffff` | `#1e293b ➔ #0f172a` |
| **`secondary`** | Slate Crystal Card | `linear-gradient(135deg, #ffffff 0%, #f8fafc 100%)` | `rgba(203,213,225,0.85)` | `#1e293b` | `#334155 ➔ #1e293b` |
| **`glass`** | Frosted Crystal Glass | `rgba(255, 255, 255, 0.72)` + `blur(16px)` | `rgba(255,255,255,0.85)` | `#0f172a` | N/A (Kaca Murni) |
| **`success`** | Zamrud Emerald | `linear-gradient(135deg, #10b981 0%, #059669 100%)` *(Btn)* / `rgba(16,185,129,0.12)` *(Badge)* | `rgba(16,185,129,0.35)` | `#ffffff` *(Btn)* / `#047857` *(Badge)* | `#10b981 ➔ #047857` |
| **`warning`** | Amber Gold | `linear-gradient(135deg, #f59e0b 0%, #d97706 100%)` *(Btn)* / `rgba(245,158,11,0.14)` *(Badge)* | `rgba(245,158,11,0.35)` | `#ffffff` *(Btn)* / `#b45309` *(Badge)* | `#f59e0b ➔ #b45309` |
| **`danger`** | Coral Crimson | `linear-gradient(135deg, #ef4444 0%, #dc2626 100%)` *(Btn)* / `rgba(239,68,68,0.12)` *(Badge)* | `rgba(239,68,68,0.35)` | `#ffffff` *(Btn)* / `#b91c1c` *(Badge)* | `#ef4444 ➔ #b91c1c` |
| **`danger-quiet`**| Quiet Crimson | `linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)` | `rgba(239,68,68,0.35)` | `#b91c1c` | `#dc2626 ➔ #991b1b` |
| **`ghost`** | Quiet Ghost | `transparent` (Hover: `rgba(99,102,241,0.06)`) | `transparent` | `#475569` | N/A |

---

## 4. TOKEN DESAIN: KESERAGAMAN SUDUT (BORDER RADIUS)

```css
:root {
    --hcis-radius-xs:   0.25rem;    /* 4px    — Kotak centang mini */
    --hcis-radius-sm:   0.375rem;   /* 6px    — Tooltip & lencana mini */
    --hcis-radius-md:   0.5rem;     /* 8px    — PAKEM KONTROL: Tombol, Input, Dropdown, Search */
    --hcis-radius-lg:   0.75rem;    /* 12px   — Inner card, Filter box */
    --hcis-radius-xl:   1.0rem;     /* 16px   — Kartu Utama, Dialog Modal, Drawer */
    --hcis-radius-2xl:  1.5rem;     /* 24px   — Hero Banner */
    --hcis-radius-pill: 9999px;     /* 9999px — Kapsul: Badge, Filter Chip, Pill Button */
}
```

---

## 5. TOKEN DESAIN: ELEVASI, BAYANGAN & PEMBIASAN KACA

### 🌌 5.1. Formula Multi-Tier Elevation
```css
/* Elevasi Standar (Resting) */
--hcis-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
--hcis-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05);
--hcis-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.04);
--hcis-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.10), 0 8px 10px -6px rgb(0 0 0 / 0.05);

/* Mekar Berwarna saat Hover (Hover Bloom Shadow) */
--hcis-btn-primary-shadow-hover: 0 6px 20px -2px rgba(79, 70, 229, 0.45), 0 2px 6px -1px rgba(79, 70, 229, 0.3);
--hcis-btn-success-shadow-hover: 0 6px 20px -2px rgba(16, 185, 129, 0.45), 0 2px 6px -1px rgba(16, 185, 129, 0.3);
--hcis-btn-warning-shadow-hover: 0 6px 20px -2px rgba(245, 158, 11, 0.45), 0 2px 6px -1px rgba(245, 158, 11, 0.3);
--hcis-btn-danger-shadow-hover:  0 6px 20px -2px rgba(239, 68, 68, 0.45), 0 2px 6px -1px rgba(239, 68, 68, 0.3);
--hcis-btn-obsidian-shadow-hover: 0 6px 20px -2px rgba(15, 23, 42, 0.55), 0 2px 6px -1px rgba(15, 23, 42, 0.4);
```

---

## 6. SISTEM GERAK & ANIMASI KONTEKSTUAL (MOTION SUITE)

### ⚡ 6.1. Transisi Mikro Tombol (`HcisButton`)
- **Luminous Shimmer Sweep**: Pseudoelemen `::after` selebar 60px dengan kemiringan `-22deg` menyapu dari kiri ke kanan (`left: 140%`) selama 650ms saat tombol di-hover.
- **Spring Elastic Lift**: `transform: translateY(-2px);` menggunakan transisi kurva `cubic-bezier(0.34, 1.56, 0.64, 1) 200ms`.
- **Active Press Bounce**: `transform: scale(0.965) translateY(1px);` berdurasi 80ms saat tombol diklik.

### 🌀 6.2. Definisi 9 Keyframes Animasi Ikon
```css
/* 1. Laser Micro-Spinner (Default Loading) */
@keyframes hcis-spin {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 2. Jam Pasir Realistis (180° Flip & Pause Loop) */
@keyframes hcis-hourglass-flip {
    0%   { transform: rotate(0deg); }
    38%  { transform: rotate(180deg); }
    50%  { transform: rotate(180deg); }
    88%  { transform: rotate(360deg); }
    100% { transform: rotate(360deg); }
}

/* 3. Roda Gigi Mesin (Continuous Mechanical Spin) */
@keyframes hcis-gear-spin {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 4. Dering Lonceng Realistis (Realistic Bell Ring) */
@keyframes hcis-bell-ring {
    0%, 100% { transform: rotate(0deg); }
    15%      { transform: rotate(16deg); }
    30%      { transform: rotate(-14deg); }
    45%      { transform: rotate(10deg); }
    60%      { transform: rotate(-6deg); }
    75%      { transform: rotate(2deg); }
}

/* 5. Osilasi Jarum Kompas (Compass Seeking) */
@keyframes hcis-compass-seek {
    0%, 100% { transform: rotate(0deg); }
    25%      { transform: rotate(-35deg); }
    50%      { transform: rotate(25deg); }
    75%      { transform: rotate(-10deg); }
}

/* 6. Aliran Data Cloud (Vertical Stream Flow) */
@keyframes hcis-stream-vertical {
    0%, 100% { transform: translateY(0); opacity: 1; }
    50%      { transform: translateY(-4px); opacity: 0.65; }
}

/* 7. Kilau Magis AI (Sparkle Twinkle & Luminescence) */
@keyframes hcis-sparkle-twinkle {
    0%, 100% { transform: scale(1) rotate(0deg); filter: drop-shadow(0 0 0 transparent); }
    50%      { transform: scale(1.22) rotate(15deg); filter: drop-shadow(0 0 6px currentColor); }
}

/* 8. Denyut Radar Konsentris (Radar Glowing Pulse) */
@keyframes hcis-badge-pulse {
    0%   { transform: scale(0.8); opacity: 0.8; }
    100% { transform: scale(2.2); opacity: 0; }
}

/* 9. Melayang Santai Anti-Gravity (Levitation Float) */
@keyframes hcis-float-levitate {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-6px); }
}
```

---

## 7. SPESIFIKASI KOMPONEN: HCISBUTTON

### 📐 7.1. Matriks Skala Ukuran Tombol
| Ukuran (`size`) | Tinggi (*Height*) | Padding Horizontal | Ukuran Font | Ukuran Ikon | Diameter Spinner | Border Radius |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **`xs`** | `1.75rem` (28px) | `0.55rem` | `0.6875rem` (11px) | `2xs` (12px) | `0.75rem` | **`8px`** |
| **`sm`** | `2.00rem` (32px) | `0.75rem` | `0.75rem` (12px) | `xs` (14px) | `0.825rem` | **`8px`** |
| **`md`** | `2.375rem` (38px) | `1.00rem` | `0.8125rem` (13px) | `sm` (16px) | `0.95rem` | **`8px`** |
| **`lg`** | `2.75rem` (44px) | `1.25rem` | `0.9375rem` (15px) | `md` (20px) | `1.15rem` | **`8px`** |

### 🎛️ 7.2. Tabel Props Lengkap `HcisButton`
| Prop | Tipe | Default | Pilihan Validasi | Deskripsi & Perilaku |
| :--- | :--- | :--- | :--- | :--- |
| `label` | `String` | `''` | Bebas | Teks label tombol (dapat digantikan dengan slot default). |
| `variant` | `String` | `'primary'` | `primary`, `obsidian`, `secondary`, `glass`, `ghost`, `success`, `warning`, `danger`, `danger-quiet` | Palet visual tombol. |
| `size` | `String` | `'md'` | `xs`, `sm`, `md`, `lg` | Skala dimensi tombol (semua ukuran wajib radius 8px). |
| `pill` | `Boolean` | `false` | `true`, `false` | Mengubah bentuk sudut menjadi kapsul bulat penuh (`9999px`). |
| `block` | `Boolean` | `false` | `true`, `false` | Lebar penuh 100% kontainer (`width: 100%`). |
| `iconOnly` | `Boolean` | `false` | `true`, `false` | Aspek rasio 1:1 persegi/bulat, wajib sertakan `aria-label`. |
| `leadingIcon` | `String` | `''` | Nama ikon Bootstrap | Ikon di sebelah kiri teks. |
| `leadingAnimation` | `String` | `''` | `spin`, `flip`, `gear`, `ring`, `sparkle`, `pulse`, dll. | Animasi aktif pada leading icon. |
| `trailingIcon` | `String` | `''` | Nama ikon Bootstrap | Ikon di sebelah kanan teks. |
| `trailingAnimation` | `String` | `''` | `stream`, `pulse`, `sparkle`, dll. | Animasi aktif pada trailing icon. |
| `loading` | `Boolean` | `false` | `true`, `false` | Menampilkan spinner dan menonaktifkan interaksi klik. |
| `loadingIcon` | `String` | `''` | e.g. `hourglass-split`, `gear-fill` | Ikon pengganti micro-spinner default saat loading. |
| `loadingAnimation`| `String` | `''` | `flip`, `gear`, `stream`, `ring` | Animasi untuk custom loading icon. |
| `disabled` | `Boolean` | `false` | `true`, `false` | Status nonaktif (opacity 0.52, pointer-events none). |

---

## 8. SPESIFIKASI KOMPONEN: HCISBADGE

### 🏷️ 8.1. Tabel Props Lengkap `HcisBadge`
| Prop | Tipe | Default | Nilai Validasi | Deskripsi |
| :--- | :--- | :--- | :--- | :--- |
| `status` | `String` | `''` | Kode status bisnis | Otomatis memetakan label, warna, ikon, dan denyut via Auto-Map. |
| `label` | `String` | `''` | Bebas | Teks manual jika tidak menggunakan prop `status`. |
| `variant` | `String` | `'neutral'` | `primary`, `brand`, `purple`, `teal`, `success`, `warning`, `danger`, `info`, `obsidian`, `neutral` | Palet warna badge. |
| `appearance` | `String` | `'subtle'` | `subtle`, `solid`, `glass` | Gaya tampilan: kaca pastel lembut, solid batu permata, atau kaca es tebal. |
| `size` | `String` | `'md'` | `xs`, `sm`, `md`, `lg` | Skala ukuran badge (`xs`=18px, `sm`=22px, `md`=26px, `lg`=30px). |
| `icon` | `String\|Boolean` | `''` | Nama ikon / `false` | Ikon khusus (atau `false` untuk menyembunyikan ikon bawaan status). |
| `iconAnimation` | `String` | `''` | `spin`, `flip`, `gear`, `pulse` | Animasi gerak pada ikon badge. |
| `dot` | `Boolean` | `false` | `true`, `false` | Menampilkan indikator lingkaran titik (*dot*). |
| `pulse` | `Boolean` | `false` | `true`, `false` | Menyalakan radar glowing pulse di sekitar dot. |
| `removable` | `Boolean` | `false` | `true`, `false` | Menambahkan tombol 'x' untuk filter chip dan memicu event `@remove`. |

### 🧠 8.2. Kamus Resmi Smart Status Auto-Map
```javascript
const STATUS_MAP = {
    // Approved & Active -> Emerald Zamrud (Success)
    APPROVED:        { label: 'Disetujui', variant: 'success', icon: 'check-circle-fill' },
    SETUJU:          { label: 'Disetujui', variant: 'success', icon: 'check-circle-fill' },
    COMPLETED:       { label: 'Selesai', variant: 'success', icon: 'check-all' },
    ACTIVE:          { label: 'Aktif', variant: 'success', icon: 'record-fill' },
    PASSED:          { label: 'Lolos', variant: 'success', icon: 'award-fill' },
    VERIFIED:        { label: 'Terverifikasi', variant: 'success', icon: 'patch-check-fill' },
    SUCCESS:         { label: 'Berhasil', variant: 'success', icon: 'check-circle-fill' },
    SYNCED:          { label: 'Tersinkron', variant: 'success', icon: 'cloud-check-fill' },

    // Pending & Processing -> Amber Gold / Royal Sapphire + Motion
    PENDING:         { label: 'Menunggu Persetujuan', variant: 'warning', icon: 'hourglass-split', iconAnimation: 'flip', pulse: true },
    PENDING_MANAGER: { label: 'Menunggu Manajer', variant: 'warning', icon: 'hourglass-split', iconAnimation: 'flip', pulse: true },
    PENDING_HR:      { label: 'Menunggu HR Admin', variant: 'warning', icon: 'clock-history', pulse: true },
    IN_REVIEW:       { label: 'Dalam Peninjauan', variant: 'warning', icon: 'search', pulse: true },
    WAITING:         { label: 'Menunggu Antrean', variant: 'warning', icon: 'hourglass-split', iconAnimation: 'flip', pulse: true },
    IN_PROGRESS:     { label: 'Sedang Diproses', variant: 'brand', icon: 'gear-wide-connected', iconAnimation: 'gear', pulse: true },
    PROCESSING:      { label: 'Memproses Data', variant: 'brand', icon: 'gear-fill', iconAnimation: 'gear', pulse: true },
    SYNCING:         { label: 'Menyinkronkan', variant: 'brand', icon: 'arrow-repeat', iconAnimation: 'spin', pulse: true },

    // Danger & Rejected -> Coral Crimson (Danger)
    REJECTED:        { label: 'Ditolak', variant: 'danger', icon: 'x-circle-fill' },
    TOLAK:           { label: 'Ditolak', variant: 'danger', icon: 'x-circle-fill' },
    CANCELLED:       { label: 'Dibatalkan', variant: 'danger', icon: 'slash-circle-fill' },
    EXPIRED:         { label: 'Kadaluarsa', variant: 'danger', icon: 'exclamation-circle-fill' },
    FAILED:          { label: 'Gagal', variant: 'danger', icon: 'exclamation-triangle-fill' },
    SUSPENDED:       { label: 'Ditangguhkan', variant: 'danger', icon: 'pause-circle-fill' },

    // Neutral & Draft -> Obsidian Slate (Neutral)
    DRAFT:           { label: 'Draf', variant: 'neutral', icon: 'file-earmark-text' },
    INACTIVE:        { label: 'Nonaktif', variant: 'neutral', icon: 'dash-circle' },
    ARCHIVED:        { label: 'Diarsipkan', variant: 'neutral', icon: 'archive' },
    CLOSED:          { label: 'Ditutup', variant: 'neutral', icon: 'lock-fill' },
};
```

---

## 9. SPESIFIKASI KOMPONEN: HCISICON

### 🌟 9.1. Skala Ukuran `HcisIcon`
- `2xs`: `0.75rem` (12px)
- `xs`: `0.875rem` (14px)
- `sm`: `1.0rem` (16px)
- `md`: `1.25rem` (20px) — Standar
- `lg`: `1.5rem` (24px)
- `xl`: `1.75rem` (28px)
- `2xl`: `2.0rem` (32px)
- `3xl`: `2.5rem` (40px)

### 💎 9.2. Varian Wadah Permata (*Jewel Box Variants*)
- `plain`: Ikon SVG murni tanpa wadah.
- `boxed`: Kotak kristal persegi berkurva `8px` (`var(--hcis-radius-md)`), bergradasi 135°, dengan *top inset sheen*.
- `circle`: Wadah kristal bulat penuh (`border-radius: 50%`).
- `glass`: Wadah kristal buram translusen dengan `backdrop-filter: blur(12px)`.

---

## 10. SPESIFIKASI KOMPONEN: HCISRESULTSTATE

- **2 Mode Layout**:
  - `banner`: Tampilan hero terpusat (untuk halaman kosong, hasil submit transaksi sukses, atau error fatal).
  - `inline`: Banner horizontal kompak (untuk notifikasi hasil dalam kartu atau tabel).
- **Sentiment Tiers**:
  - `success`: Cincin permata hijau zamrud dengan animasi centang berkilau.
  - `pending`: Spinner cincin ganda (*dual-ring laser spinner*) dengan status antrean.
  - `danger`: Cincin koral merah dengan tanda peringatan.
  - `empty`: Wadah obsidian slate dengan ilustrasi data kosong.
- **Eyebrow Kicker Pill**: Kapsul status kecil di atas judul utama untuk konteks modul instan.

---

## 11. SPESIFIKASI KOMPONEN LANJUTAN

### 📊 A. `HcisStatCard` & `HcisStatGroup`
- **4 Mode Tata Letak (Layout Modes)**:
  - `default`: Tata letak vertikal eksekutif standar dengan header label, ikon wadah permata, metrik besar `tabular-nums`, pil delta tren, dan opsional bilah target progress.
  - `horizontal`: Tata letak side-by-side (ikon di sebelah kiri, metrik & label tertumpuk di sebelah kanan) ideal untuk bilah filter, header modul, atau ringkasan cepat.
  - `compact`: Mini widget berketinggian padat untuk sidebar, kartu detail karyawan, atau toolbar mobile.
  - `hero`: Kartu metrik heroik berdampak tinggi dengan angka berukuran ekstra besar (`2.35rem`) dan pencahayaan radial *ambient luminescence*.
- **4 Tingkatan Penampilan (Appearance Tiers)**:
  - `crystal`: Permukaan gradasi 135° lembut dengan *top inset sheen* dan batas 1px translusen (Standar Baku).
  - `solid`: Gradasi kaya berkejenuhan tinggi untuk kartu metrik yang membutuhkan penekanan khusus.
  - `glass`: Refraksi kaca buram tebal (`backdrop-filter: blur(16px) saturate(200%)`).
  - `obsidian`: Kristal gelap tengah malam (*midnight dark crystal*) `#1e293b ➔ #0f172a` untuk metrik status keamanan atau server.
- **Fitur Metrik Kaya**:
  - `prefix` (misal `Rp`, `$`, `#`) dan `suffix` (misal `Org`, `Hari`, `%`, `Jt`) dengan tipografi presisi.
  - `progress` (0-100) & `target` untuk visualisasi persentase capaian target / kuota dengan mikro-bar kapsul gradasi.
  - `iconAnimation` (`float`, `spin`, `gear`, `pulse`, `ring`, `sparkle`) untuk ikon bergerak dinamis.
  - `:interactive="true"` untuk kartu yang dapat diklik dengan animasi angkat pegas (`translateY(-3px)`) dan umpan balik saat ditekan.
- **Komponen `HcisStatGroup`**:
  - Menyusun baris grid responsif secara otomatis dengan skala kolom `:cols="1 | 2 | 3 | 4 | 5 | 'auto'"` dan skala celah antarkartu `:gap="'xs' | 'sm' | 'md' | 'lg'"`.
  - Meneruskan konfigurasi `layout` dan `appearance` ke seluruh kartu anak atau menerima `<slot>` untuk komposisi kustom bebas.

### 🗄️ B. `HcisDataTable`
- Header tabel menggunakan permukaan `var(--hcis-surface-soft)` dengan teks tebal (*uppercase letter-spacing 0.05em*).
- Baris tabel dilengkapi *subtle hover illumination* (`rgba(99,102,241, 0.03)`).
- Kolom aksi tombol menggunakan ukuran `sm` (32px) dengan border radius seragam `8px`.
- Kolom status wajib menggunakan `HcisBadge` dengan ukuran `sm`.

### 🗂️ C. `HcisFilterBar`
- Bilah filter berkurva `12px` dengan bayangan mengambang halus.
- Input pencarian dan dropdown memiliki tinggi `38px` dan radius `8px` yang sejajar dengan tombol aksi `Cari` / `Reset`.
- Menampung barisan *removable filter chips* secara dinamis di bawah bilah utama.

### 🖼️ D. `HcisDialog` & `HcisDrawer`
- Lapisan latar belakang (*backdrop overlay*): `rgba(15, 23, 42, 0.55)` dengan `backdrop-filter: blur(8px)`.
- Panel modal: `border-radius: 16px-20px`, bergaris batas kristal 1px, dengan *floating multi-tier shadow*.
- Transisi masuk: Pegas mikro (*spring scale entrance* `scale(0.95) ➔ scale(1.0)`).

---

## 12. STANDAR MAKSIMALISASI & EKSTENSI KOMPONEN REUSABLE

### 🔁 12.1. Prinsip Anti-Reinvention (Zero Throwaway UI)
- Setiap kali membangun halaman atau fitur baru di HCIS, **WAJIB MEMAKAI KOMPONEN RESMI** dari `@/components/ui` (`HcisButton`, `HcisBadge`, `HcisIcon`, `HcisCard`, `HcisField`, `HcisDataTable`, `HcisFilterBar`, `HcisDialog`, dll.).
- **Dilarang** menulis tombol mentah `<button class="btn ...">`, lencana mentah `<span class="badge ...">`, atau styling ad-hoc lokal yang menduplikasi fungsionalitas design system.

### 🧩 12.2. Cara Menambah Fitur Baru ke Komponen Eksisting
Bila sebuah fitur baru (misal animasi baru, slot baru, atau tipe interaksi baru) belum ada di komponen design system:
1. **Perluas Komponen Pusat**: Tambahkan prop atau slot baru langsung ke file komponen di `resources/js/components/ui/Hcis*.vue`.
2. **Jangan Buat Komponen Duplikat**: Dilarang membuat `HcisButtonV2.vue` atau `CustomBadge.vue` terpisah.
3. **Daftarkan di Barrel Export**: Pastikan selalu diekspor melalui `resources/js/components/ui/index.js`.
4. **Dokumentasikan di Studio**: Tambahkan kontrolnya di studio demo `/design-system/*`.

---

## 13. JAMINAN KOMPATIBILITAS KE BELAKANG (ZERO BREAKING CHANGES)

### 🛡️ 13.1. Protokol Backward Compatibility 100%
Setiap perubahan pada komponen design system **TIDAK BOLEH MEMATAHKAN KODE PEMANGGIL EKSISTING**:
1. **Nilai Default Tetap Stabil**: Setiap penambahan prop baru harus memiliki nilai `default` yang mempertahankan tampilan & perilaku lama jika prop tersebut tidak dipassing.
2. **Pertahankan Alias / Deprecated Names**: Jika sebuah prop diganti namanya, sediakan jembatan computed (*aliasing bridge*) agar pemanggil lama tetap berfungsi tanpa error.
3. **Slot Fallback**: Selalu sediakan slot fallback (misalnya `<slot>{{ computedLabel }}</slot>`) agar teks yang dipassing via slot maupun prop sama-sama didukung.

---

## 14. STANDAR 6 POLA LATAR BELAKANG SIGNATURE (SIGNATURE BACKGROUND PATTERNS)

Aplikasi HCIS Enterprise Suite menetapkan secara resmi **6 Pola Latar Belakang Matematika Vektor Murni** (*zero HTTP request, 60fps render*) untuk memperkuat identitas tata kelola dan kemewahan visual:

### 📐 14.1. Pola 1: "Executive Blueprint Grid" (`.hcis-pattern-grid`)
- **Karakter Visual**: Garis-garis grid tegak lurus presisi 24px/32px bernuansa *engineering blueprint*, tata kelola kepatuhan, dan integritas data fintech.
- **Penempatan**: `HcisHeader`, `HcisResultState`, `Login.vue`, `HcisEmptyState`, `HcisCard`, `HcisStatCard`.

### 🔘 14.2. Pola 2: "Dot Matrix Lattice" (`.hcis-pattern-dots`)
- **Karakter Visual**: Kisi titik-titik mikro berjarak 16px/20px bernuansa modern, taktil, kartu eksekutif, dan diagram kanvas interaktif.
- **Penempatan**: `HcisStatCard` (Hero/Obsidian), Kartu Profil Pegawai, Header Dialog/Drawer.

### ➕ 14.3. Pola 3: "Precision Crosshair Matrix" (`.hcis-pattern-crosshair`)
- **Karakter Visual**: Tanda silang mikro (`+`) di setiap persimpangan 24px gaya kanvas Figma/CAD/Vercel.
- **Penempatan**: Diagram Alur Approval (`FeatureApprovalFlow`), Matrix Evaluasi KPI, Query Builder.

### 🔀 14.4. Pola 4: "Diagonal Engineering Stripes" (`.hcis-pattern-stripes`)
- **Karakter Visual**: Garis-garis arsir diagonal miring 45° bernuansa ketelitian struktural.
- **Penempatan**: Zona Dropzone File Upload, Kartu Status Draft / In Review, Watermark Dokumen.

### 🎯 14.5. Pola 5: "Concentric Orbit Rings" (`.hcis-pattern-rings`)
- **Karakter Visual**: Cincin radar melingkar bertingkat yang memancar dari sudut kanan atas.
- **Penempatan**: Kartu Sertifikasi LMS, Banner ID Karyawan, Hero Stat Card Capaian Target.

### ⬡ 14.6. Pola 6: "Isometric Honeycomb Hex" (`.hcis-pattern-hex`)
- **Karakter Visual**: Pola heksagonal modular 3D bernuansa keamanan dan integrasi data.
- **Penempatan**: Matriks Otorisasi Governance, Log Audit Keamanan, Kartu Integrasi API.

### 🎭 14.7. Aturan Gradient Vignette Masking & Sentiment Tonal Matching
1. **Tidak Boleh Menutupi 100% Permukaan**: Pola **dilarang** tampil merata seperti wallpaper datar. Pola **wajib** menggunakan *Crystal Radial/Diagonal Vignette Mask Falloff* (`mask-image: radial-gradient(ellipse 90% 75% at 100% 0%, black 0%, rgba(0, 0, 0, 0.4) 38%, transparent 75%)`) sehingga pola berkonsentrasi lembut di sudut kanan atas dan memudar mulus ke arah tengah/kiri bawah kartu.
2. **Sentiment Tonal Harmony**: Pola di dalam kartu mengadopsi warna pendar sentimen kartu terkait (`currentColor` dinamis sesuai `--hcis-brand-600`, `#7c3aed`, `#0d9488`, `--hcis-success-600`, dll) dengan tingkat opasitas mikro (`0.05`–`0.18`) agar menyatu sebagai tekstur kristal.

### 🎛️ 14.8. Prop Standar Komponen UI
- `HcisCard`: `:pattern="'none' | 'dots' | 'grid' | 'crosshair' | 'stripes' | 'rings' | 'hex'"` (default: `'none'`)
- `HcisStatCard`: `:pattern="'none' | 'dots' | 'grid' | 'crosshair' | 'stripes' | 'rings' | 'hex'"` (default: `'none'`)
- `HcisResultState`: `:pattern="'auto' | 'grid' | 'dots' | 'none'"` (default: `'auto'`)

### 📐 14.9. Hierarki Tipografi & Harmoni Tombol Kartu (*Card Typography & Button Harmony*)
1. **Header Title**: Ukuran tajam dan proporsional: `0.95rem` (15.2px) dengan bobot `font-weight: 700`, `letter-spacing: -0.015em`, warna `--hcis-slate-900`.
2. **Header Description**: Ringan dan kontras lembut: `0.78125rem` (12.5px), bobot `font-weight: 450`, warna `--hcis-slate-500`.
3. **Body Copy (`.ds-body`)**: Terbaca jelas dan santun: `0.8125rem` (13px), bobot `font-weight: 400`, `line-height: 1.5`, warna `--hcis-slate-600`.
4. **Data Meta Labels (`.ds-micro-label`)**: Ringkas dan formal: `0.6875rem` (11px), bobot `font-weight: 600`, `text-transform: uppercase`, `letter-spacing: 0.04em`, warna `--hcis-slate-400`.
5. **Data Metric Values (`.ds-micro-value`)**: Kuat dan presisi: `0.875rem` (14px), bobot `font-weight: 700`, warna `--hcis-slate-800`, `tabular-nums`.
6. **Harmoni & Dimensi Tombol Kartu (*Card Action Buttons*)**:
   - **Ukuran `xs`**: Tinggi `28px` (`1.75rem`), radius `8px` (`--hcis-radius-md`), padding `12px` (`0.75rem`) &bull; Sangat serasi untuk header action bar dan mini card footer.
   - **Ukuran `sm`**: Tinggi `32px` (`2.0rem`), radius `9px`, padding `15px` (`0.95rem`) &bull; Ideal untuk primary CTA di footer kartu.
   - **Ukuran `md`**: Tinggi `38px` (`2.375rem`), radius `10px` (`--hcis-radius-lg`), padding `18px` (`1.15rem`) &bull; Standar form dan dialog.
   - **Ukuran `lg`**: Tinggi `44px` (`2.75rem`), radius `12px` (`--hcis-radius-xl`), padding `22px` (`1.35rem`) &bull; Standar hero CTA.
   - Bobot teks tombol dioptimalkan ke `font-weight: 600` dengan permukaan kristal berspekular ganda (*specular bevel highlight*).

---

## 15. STANDAR FORM VALIDATION, ERROR HANDLING & TABULAR NUMBERS

### 📝 15.1. Integrasi Form & Error State
- Seluruh input form wajib dibungkus dengan `HcisField` yang mengelola `label`, `hint`, `required`, dan `error`.
- Saat input mengalami error validasi, otomatis tambahkan `aria-invalid="true"`, border merah koral (`--hcis-danger-500`), dan pesan error dengan ikon peringatan vektor.

### 🔢 15.2. Tipografi Angka Monospace (*Tabular Figures*)
- Seluruh tampilan angka yang berubah-ubah (saldo, persentase metrik KPI, jam absensi, ID dokumen, nominal rupiah) **WAJIB MENGGUNAKAN** `font-variant-numeric: tabular-nums;` (atau kelas `.font-mono` / `.font-tabular`) agar lebar angka seragam dan tidak bergoyang saat nilai berubah.

### 🏷️ 15.3. Standar Segmented Prepend/Append Addons & Inline Prefix/Suffix
1. **Segmented Addon Blocks (`prepend` / `append` / `#prepend` / `#append`)**:
   - Blok bersegmen menyatu di kiri (`prepend`) atau kanan (`append`) dengan permukaan satin kristal (`linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)`) dan border presisi `1px solid #cbd5e1`.
   - **Teks Statis**: Digunakan untuk protokol (`https://`), domain (`.hcis-cloud.id`), atau kode entitas (`EMP-`).
   - **Dropdown Select Segmented**: Digunakan untuk kode negara (`+62 🇮🇩 ▾`), mata uang (`IDR (Rp) ▾`), atau satuan periode (`Hari ▾ / Bulan ▾ / Tahun ▾`). Menghilangkan border ganda dengan panah SVG terintegrasi.
   - **Tombol Aksi Segmented**: Digunakan untuk tombol *"Kirim OTP"*, *"Salin Tautan"*, atau *"Terapkan"* berukuran `size="xs"` (28px).
2. **Inline Floating Indicators (`prefix` / `suffix`)**:
   - Teks mengambang ringan di dalam ruang input (`Rp`, `$`, `%`, `Hari`, `kg`) dengan tipografi `font-weight: 700` dan warna `slate-500`.

### 🔒 15.4. Standar Status Non-Aktif (*Disabled & Readonly State*)
- **Larangan Double Opacity Wash**: Jangan gunakan `opacity: 0.4 - 0.6` yang membuat teks pudar tidak terbaca.
- **Tipografi Nilai Tetap Terbaca (*High-Legibility Text*)**: Nilai data wajib tetap berbobot `font-weight: 600` dengan warna abu-abu arang tegas (`--hcis-slate-700` / `#334155`).
- **Permukaan Satin Redup (*Satin Slate Wash*)**: Latar belakang menggunakan gradasi lembut `linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%)` dengan border halus `1px solid #e2e8f0` dan kursor `cursor: not-allowed;`.

---

## 16. STANDAR RESPONSIVITAS & BREAKPOINTS LAYAR

| Breakpoint | Ukuran Lebar | Perilaku Desain Komponen |
| :--- | :--- | :--- |
| **Mobile (`xs`)** | `< 640px` | Tombol CTA utama menjadi `block` (100% lebar layar). Teks lencana badge dipotong elipsis maks `9rem`. Padding stage studio menjadi `0.85rem`. |
| **Tablet (`sm`-`md`)**| `640px - 1024px` | Grid filter bar berubah menjadi 2 kolom. Tombol aksi tabel beralih ke ukuran `sm` (32px). |
| **Desktop (`lg`-`xl`)**| `> 1024px` | Tampilan multi-kolom penuh, efek hover micro-lift aktif penuh, drawer modal melayang halus. |

---

## 17. STANDAR PEMBUATAN HALAMAN DEMO & STUDIO INTERAKTIF

Setiap berkas demo di `resources/js/Pages/SYSTEM/DesignSystem/demos/*.vue` **wajib menyertakan**:
1. **Interactive Configurator Studio**:
   - Panggung pratinjau reaktif (*Live Preview Stage*).
   - Pengubah latar kanvas (*Stage Background Switcher*): `Light`, `Dark 🌑`, `Mesh 🔮`.
2. **Matriks Pengaturan Lengkap**: Form kontrol lengkap untuk seluruh props (varian, ukuran, ikon, animasi, bentuk, status).
3. **Live Reactive Vue Code Generator**:
   - Kotak kode gelap (`#0f172a`) dengan sintaks yang otomatis berubah *real-time*.
   - Tombol **"Salin Kode"** (*one-click clipboard copy*) dengan umpan balik visual instan.
4. **Matriks Kasus Penggunaan Riil**: Contoh penggunaan dalam tabel, formulir, atau modul bisnis HCIS nyata.

---

## 18. 12 ATURAN BAKU & LARANGAN KERAS BAGI AGEN AI

1. 🚫 **DILARANG MENGGUNAKAN EMOJI KEYBOARD / UNICODE**: Wajib selalu menggunakan `<HcisIcon name="..." />` berbasis vektor Bootstrap/Lucide Icons.
2. 🚫 **DILARANG MEMBUAT WARNA FLAT / MATI**: Semua komponen utama wajib menggunakan gradasi diagonal 135° kristal dan *inset top highlight sheen*.
3. 🚫 **DILARANG MENGGUNAKAN BORDER RADIUS ACAK**: Seluruh kontrol interaktif (tombol, input field, filter chip) **WAJIB MENGIKUTI HIERARKI KURVA KONSENTRIS**: `8px` (`size-xs` / `--hcis-radius-md`), `9px` (`size-sm`), `10px` (`size-md` / `--hcis-radius-lg`), dan `12px` (`size-lg` / `--hcis-radius-xl`) untuk keharmonisan visual dengan kontur kartu `14px`–`16px`.
4. 🚫 **DILARANG MEMBUAT THROW-AWAY / DUPLICATE UI**: Wajib memakai komponen resmi `@/components/ui`. Jika butuh fitur baru, perluas komponen eksisting dengan jaminan backward compatibility.
5. 🚫 **DILARANG MEMATAHKAN KODE LAMA (BREAKING CHANGES)**: Penambahan prop/fitur baru tidak boleh mengubah default behavior pemanggil lama.
6. 🚫 **DILARANG MENGABAIKAN AKSESIBILITAS**: Tombol `iconOnly` wajib menyertakan `aria-label`. Tombol `loading` wajib memiliki `aria-busy="true"`.
7. 🚫 **DILARANG MENJALANKAN BUILD BERAT SECARA BERULANG**: Dilarang menjalankan `npm run build` atau `vitest` tanpa henti selama proses pengembangan aktif, karena Vite HMR sudah aktif secara *real-time*.
8. 🚫 **DILARANG MEMINDAHKAN FILE TANPA CEK GERBANG**: Setiap pemindahan atau pembuatan file harus mematuhi gerbang verifikasi modul (`docs/architecture/module-registry.md`).
9. 🚫 **DILARANG MEMBUAT ANIMASI TANPA PREFERS-REDUCED-MOTION**: Semua keyframe CSS wajib memiliki aturan reduced-motion untuk pengguna dengan sensitivitas gerak.
10. 🚫 **DILARANG MENCAMPURKAN STYLE GLOBAL TANPA PREFIX**: Gunakan prefix `.hcis-*` untuk semua kelas CSS agar tidak terjadi tabrakan *cascade*.
11. 🚫 **DILARANG MEMBUAT KARTU TANPA INSET SHEEN & ELEVASI**: Setiap permukaan kartu wajib memiliki border `1px` dan `var(--hcis-shadow-*)`.
12. 🚫 **DILARANG MENGHAPUS DOKUMENTASI INI**: Dokumen ini adalah konstitusi desain antarmuka HCIS Enterprise Suite.
