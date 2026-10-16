# Penerapan `design.md` ke Lapis 2 — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Langkahnya memakai kotak centang (`- [ ]`) untuk penandaan.

**Tujuan:** Memasang seluruh nilai token `design.md` sebagai lapis 2 (peran) di `resources/css/design-system.css`, lalu merapikan permukaan yang arah terangnya terbalik supaya hasilnya terbaca sebagai keputusan.

**Arsitektur:** Tiap kelompok token dikunci lebih dulu oleh satu berkas tes baru, `designMdTokens.test.js`, yang membaca `design-system.css` sebagai teks dan menuntut nilai persis dari `design.md`. Tes ditulis dan dilihat gagal sebelum nilainya dipasang, jadi kontraknya ada di kode dan bukan di ingatan. Perubahan hanya menyentuh lapis 2 dan satu primitif baru; primitif lain, alias warisan di `app.css`, dan ~800 halaman warisan tidak disentuh.

**Tumpukan:** Vue 3 `<script setup>`, Vite, Vitest, CSS custom properties tiga lapis.

**Spec:** `docs/superpowers/specs/2026-08-13-design-md-lapis-2-design.md`

## Batasan Global

Berlaku pada **setiap** tugas di bawah:

- **Jangan commit tanpa aba-aba.** CLAUDE.md §6. Langkah commit di tiap tugas disiapkan lengkap tapi hanya dijalankan kalau pemilik proyek memintanya.
- **Warna mentah hanya sah di baris yang mendefinisikan token**, yaitu baris yang cocok `^\s*--hcis-[\w-]+\s*:`. Nilai bayangan multi-baris akan menjatuhkan penjaga NOL. Tulis satu baris.
- **`rgba()` dilarang.** Bentuk yang benar: `rgb(var(--token-rgb) / 0.5)`.
- **`!important` hanya sah sekali, di ujung deklarasi.** Ditulis per lapis di daftar koma, browser membuang seluruh deklarasinya tanpa peringatan.
- **Angka ratchet tidak boleh dinaikkan.** Kalau sebuah penjaga gagal, itu sinyal caranya yang salah — bukan ambangnya.
- **Bahasa:** komentar kode dan pesan commit dalam bahasa Indonesia; nama variabel tetap Inggris.
- **Jangan sentuh** `resources/css/app.css` (alias warisan menunjuk primitif — itu yang menjaga ~800 halaman lama tidak ikut berubah), primitif lapis 1 selain penambahan `--hcis-steel-500`, dan halaman di luar `Pages/DesignSystem/`.

**Perintah verifikasi:**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js      # tes tugas berjalan
npx vitest run resources/js/components/ui/designSystemGuards.test.js  # penjaga design system
npm run test:unit                                                     # seluruh tes
npm run build; echo $?                                                # HARUS 0
```

`npm run build | tail` mengambil status keluar dari `tail`, bukan dari build. Selalu periksa `echo $?` di baris terpisah.

---

### Task 1: Berkas tes kontrak + primitif `steel-500`

Berkas tes ini jadi tulang punggung seluruh rencana. Tugas-tugas berikutnya hanya menambah blok `describe` ke dalamnya.

**Berkas:**
- Buat: `resources/js/components/ui/designMdTokens.test.js`
- Ubah: `resources/css/design-system.css` — lapis 1, sesudah baris 191 (`--hcis-black: rgb(var(--hcis-black-rgb));`)

**Antarmuka:**
- Menghasilkan: fungsi pembantu `nilaiToken(nama)` di dalam berkas tes, dipakai tugas 2–5. Tanda tangannya: `nilaiToken(nama: string) => string | null` — mengembalikan nilai token setelah `:` dan sebelum `;`, sudah dipangkas spasi dan komentar akhir baris, atau `null` kalau tokennya tidak ada.

- [ ] **Langkah 1: Tulis tes yang gagal**

Buat `resources/js/components/ui/designMdTokens.test.js`:

```js
/*
 * Kontrak design.md.
 *
 * Nilai di sini disalin dari design.md apa adanya — termasuk yang terasa
 * salah. Kalau sebuah nilai perlu berubah, yang diubah adalah design.md dan
 * berkas ini bersama-sama, lewat keputusan pemilik proyek. Mengubah
 * design-system.css sendirian akan membuat tes ini merah, dan itu memang
 * gunanya: nilai token tidak boleh bergeser diam-diam.
 *
 * Spec: docs/superpowers/specs/2026-08-13-design-md-lapis-2-design.md
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const AKAR = path.resolve(__dirname, '../../../..');
const DS = readFileSync(path.join(AKAR, 'resources/css/design-system.css'), 'utf8');

/**
 * Nilai sebuah token, diambil dari definisi PERTAMA-nya di design-system.css.
 *
 * Sengaja yang pertama: lapis 3 (tema) mendefinisikan ulang sebagian token di
 * bawah [data-hcis-theme]. Yang jadi kontrak design.md adalah lapis 2, dan
 * lapis 2 selalu lebih dulu di berkas ini.
 */
function nilaiToken(nama) {
    const cocok = DS.match(new RegExp('^\\s*' + nama + '\\s*:\\s*([^;]+);', 'm'));
    if (!cocok) return null;
    return cocok[1].replace(/\/\*[\s\S]*?\*\//g, '').trim();
}

describe('design.md — primitif', () => {
    it('rona #607080 lahir sebagai --hcis-steel-500', () => {
        // design.md color.text.primary. Tidak ada di keluarga slate mana pun:
        // ia lebih hijau. Ditulis bentuk -rgb lebih dulu, mengikuti pola
        // seluruh lapis 1, supaya bisa dipakai beralfa tanpa rgba().
        expect(nilaiToken('--hcis-steel-500-rgb')).toBe('96 112 128');
        expect(nilaiToken('--hcis-steel-500')).toBe('rgb(var(--hcis-steel-500-rgb))');
    });

    it('hitam sudah ada dan tidak diduplikasi', () => {
        expect(nilaiToken('--hcis-black-rgb')).toBe('0 0 0');
        expect(nilaiToken('--hcis-black')).toBe('rgb(var(--hcis-black-rgb))');
    });
});
```

- [ ] **Langkah 2: Jalankan tes, pastikan gagal**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: GAGAL pada tes pertama — `expected null to be '96 112 128'`. Tes kedua sudah lulus karena `--hcis-black` memang sudah ada.

- [ ] **Langkah 3: Tambahkan primitifnya**

Di `resources/css/design-system.css`, cari baris `--hcis-black-rgb: 0 0 0;` (baris 119). Tambahkan tepat sesudahnya:

```css
    /* Rona teks utama design.md. Bukan anggota keluarga slate — ia lebih
       hijau, dan menempelkannya ke slate-500 akan menggeser warnanya. */
    --hcis-steel-500-rgb: 96 112 128;
```

Lalu cari baris `--hcis-black: rgb(var(--hcis-black-rgb));` (baris 191). Tambahkan tepat sesudahnya:

```css
    --hcis-steel-500: rgb(var(--hcis-steel-500-rgb));
```

- [ ] **Langkah 4: Jalankan tes, pastikan lulus**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: LULUS, 2 tes.

- [ ] **Langkah 5: Pastikan penjaga lama tetap hijau**

```bash
npx vitest run resources/js/components/ui/designSystemGuards.test.js
```

Diharapkan: LULUS. Kalau "NOL — berkas fondasi tidak menulis warna mentah di luar baris definisi token" gagal, berarti `96 112 128` mendarat di baris yang bukan definisi token — periksa penempatannya.

- [ ] **Langkah 6: Siapkan commit** *(jalankan hanya bila diminta)*

```bash
git add resources/js/components/ui/designMdTokens.test.js resources/css/design-system.css
git commit -m "feat(design-system): kunci kontrak design.md + primitif steel-500"
```

---

### Task 2: Peta warna lapis 2

**Berkas:**
- Ubah: `resources/js/components/ui/designMdTokens.test.js` — tambah satu `describe`
- Ubah: `resources/css/design-system.css:384-404` (blok Permukaan dan Teks) dan blok Batas (`--hcis-border-strong`)

**Antarmuka:**
- Memakai: `nilaiToken()` dari Task 1.
- Menghasilkan: `--hcis-surface` bernilai hitam. Seluruh tugas sesudahnya berasumsi permukaan tertutup sudah gelap.

- [ ] **Langkah 1: Tulis tes yang gagal**

Tambahkan di akhir `designMdTokens.test.js`:

```js
describe('design.md — warna lapis 2', () => {
    /*
     * design.md punya 4 nama permukaan, kita punya 5. Pemetaannya ORDINAL,
     * diputuskan pemilik proyek 13 Agustus 2026: surface.base adalah permukaan
     * KARTU, bukan kanvas halaman. Jadi kartu, dialog, dropdown, dan isian
     * menjadi hitam sementara halamannya terang.
     */
    it.each([
        ['--hcis-surface', 'var(--hcis-black)'],              // surface.base #000000
        ['--hcis-surface-rgb', 'var(--hcis-black-rgb)'],      // turunan wajib
        ['--hcis-surface-raised', 'var(--hcis-slate-50)'],    // surface.raised #f8fafc
        ['--hcis-surface-soft', 'var(--hcis-white)'],         // surface.muted #ffffff
        ['--hcis-surface-brand', 'var(--hcis-brand-500)'],    // surface.strong #6366f1
        ['--hcis-surface-inverse', 'var(--hcis-white)'],      // diturunkan
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it.each([
        ['--hcis-text', 'var(--hcis-steel-500)'],             // text.primary #607080
        ['--hcis-text-strong', 'var(--hcis-steel-500)'],      // design.md hanya punya satu teks utama
        ['--hcis-text-muted', 'var(--hcis-slate-600)'],       // text.tertiary #475569
        ['--hcis-text-subtle', 'var(--hcis-slate-500)'],      // diturunkan dari border.strong
        ['--hcis-text-inverse', 'var(--hcis-slate-700)'],     // text.inverse #334155
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it.each([
        ['--hcis-border', 'var(--hcis-slate-200)'],           // border.default #e2e8f0 — sudah sama
        ['--hcis-border-strong', 'var(--hcis-slate-500)'],    // border.strong #64748b
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });
});
```

**Kenapa `--hcis-surface-brand` memakai `var(--hcis-brand-500)` dan bukan `var(--hcis-indigo-500)`:** keduanya `#6366f1` pada tema baku, tapi `brand-500` ikut berpindah saat tema `laut`/`hutan` dinyalakan. Lapis 2 menyebut ARTI, bukan rona — dan nilai `design.md` tetap terpenuhi persis.

- [ ] **Langkah 2: Jalankan tes, pastikan gagal**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: GAGAL, 11 dari 13 tes baru merah. `--hcis-border` sudah `slate-200` dan `--hcis-surface-rgb` masih `white-rgb`.

- [ ] **Langkah 3: Pasang nilainya**

Di `resources/css/design-system.css`, ganti blok baris 384–404. Yang lama:

```css
    /* ── Permukaan ───────────────────────────────────────────────────── */
    --hcis-surface: var(--hcis-white);
    /* Kembaran triple untuk permukaan tembus pandang (varian "glass"). Tanpa
       ini, satu-satunya jalan menulis putih beralfa adalah rgba() — dan itu
       membeku saat tema gelap membalik arti "surface". */
    --hcis-surface-rgb: var(--hcis-white-rgb);
    --hcis-surface-raised: var(--hcis-white);
    --hcis-surface-soft: var(--hcis-slate-50);
    --hcis-surface-brand: var(--hcis-brand-50);
    --hcis-surface-inverse: var(--hcis-slate-900);

    /* ── Teks ────────────────────────────────────────────────────────── */
    --hcis-text: var(--hcis-slate-800);
    --hcis-text-strong: var(--hcis-slate-900);
    --hcis-text-muted: var(--hcis-slate-500);
    --hcis-text-subtle: var(--hcis-slate-400);
    --hcis-text-inverse: var(--hcis-white);
```

Jadi:

```css
    /* ── Permukaan ─────────────────────────────────────────────────────
       Peta ORDINAL design.md: surface.base adalah permukaan tertutup
       (kartu, dialog, dropdown, isian), bukan kanvas halaman. Arah terangnya
       jadi terbalik dari sebelumnya — kartu gelap di atas halaman terang. */
    --hcis-surface: var(--hcis-black);
    /* Kembaran triple untuk permukaan tembus pandang (varian "glass"). Tanpa
       ini, satu-satunya jalan menulis hitam beralfa adalah rgba() — dan itu
       membeku saat tema mengubah arti "surface". */
    --hcis-surface-rgb: var(--hcis-black-rgb);
    --hcis-surface-raised: var(--hcis-slate-50);
    --hcis-surface-soft: var(--hcis-white);
    --hcis-surface-brand: var(--hcis-brand-500);
    /* Kebalikan surface. Karena surface kini hitam, inverse jadi terang. */
    --hcis-surface-inverse: var(--hcis-white);

    /* ── Teks ──────────────────────────────────────────────────────────
       design.md hanya punya satu warna teks utama, jadi --hcis-text dan
       --hcis-text-strong kembar. Hierarki teks setelah ini dibawa BOBOT
       (--hcis-weight-*), bukan warna. */
    --hcis-text: var(--hcis-steel-500);
    --hcis-text-strong: var(--hcis-steel-500);
    --hcis-text-muted: var(--hcis-slate-600);
    --hcis-text-subtle: var(--hcis-slate-500);
    --hcis-text-inverse: var(--hcis-slate-700);
```

Lalu cari `--hcis-border-strong: var(--hcis-slate-450);` dan ganti jadi:

```css
    --hcis-border-strong: var(--hcis-slate-500);
```

`--hcis-text-brand`, `--hcis-text-on-brand`, `--hcis-text-on-danger`, `--hcis-border-rgb`, `--hcis-border-subtle`, dan `--hcis-border-brand` **tidak disentuh** — `design.md` tidak menyebutnya.

- [ ] **Langkah 4: Jalankan tes, pastikan lulus**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: LULUS, 15 tes.

- [ ] **Langkah 5: Jalankan seluruh tes**

```bash
npm run test:unit
```

Diharapkan: LULUS. Kalau ada tes komponen yang menyatakan warna tertentu, catat namanya dan laporkan — jangan diubah tanpa aba-aba.

- [ ] **Langkah 6: Siapkan commit** *(jalankan hanya bila diminta)*

```bash
git add resources/js/components/ui/designMdTokens.test.js resources/css/design-system.css
git commit -m "feat(design-system): peta warna design.md ke lapis 2"
```

---

### Task 3: Skala huruf dan keluarga huruf

**Berkas:**
- Ubah: `resources/js/components/ui/designMdTokens.test.js`
- Ubah: `resources/css/design-system.css:613-619` (ukuran) dan `:528` (keluarga)
- Ubah: `resources/views/app.blade.php:53`

- [ ] **Langkah 1: Tulis tes yang gagal**

Tambahkan di akhir `designMdTokens.test.js`:

```js
describe('design.md — huruf', () => {
    /*
     * design.md memberi DELAPAN anak tangga (xs…4xl); kita punya tujuh nama,
     * jadi --hcis-font-size-3xl lahir untuk yang kedelapan.
     *
     * Yang perlu diketahui pembacanya nanti: design.md menetapkan
     * font.size.base = 16px padahal puncak skalanya 13,6px. Jadi teks yang
     * lewat .hcis-type-* akan LEBIH KECIL dari teks yang mewarisi akar. Itu
     * ada di sumbernya — jangan "perbaiki" tanpa bicara.
     */
    it.each([
        ['--hcis-font-size-2xs', '0.65rem'],      // font.size.xs   10,4px
        ['--hcis-font-size-xs', '0.68rem'],       // font.size.sm   10,88px
        ['--hcis-font-size-sm', '0.72rem'],       // font.size.md   11,52px
        ['--hcis-font-size-md', '0.78rem'],       // font.size.lg   12,48px
        ['--hcis-font-size-lg', '0.8rem'],        // font.size.xl   12,8px
        ['--hcis-font-size-xl', '0.8125rem'],     // font.size.2xl  13px
        ['--hcis-font-size-2xl', '0.82rem'],      // font.size.3xl  13,12px
        ['--hcis-font-size-3xl', '0.85rem'],      // font.size.4xl  13,6px
        ['--hcis-font-size-base', '1rem'],        // font.size.base 16px
        ['--hcis-line-base', '1.5'],              // 24px / 16px
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('keluarga huruf persis font.family.stack', () => {
        // design.md: "Nunito, sans-serif". Cadangan Segoe UI / system-ui
        // dilepas — bukan karena tidak berguna, tapi karena design.md
        // menyebut tumpukannya secara utuh.
        expect(nilaiToken('--hcis-font-body')).toBe("'Nunito', sans-serif");
    });

    it('bobot dasar 400 sudah sesuai dan tidak diubah', () => {
        expect(nilaiToken('--hcis-weight-regular')).toBe('400');
    });
});
```

- [ ] **Langkah 2: Jalankan tes, pastikan gagal**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: GAGAL, 11 dari 12 tes baru merah. Hanya `--hcis-weight-regular` lulus.

- [ ] **Langkah 3: Pasang nilainya**

Di `design-system.css`, ganti blok ukuran huruf (baris 613–619). Yang lama:

```css
    --hcis-font-size-2xs: 0.6875rem;  /* 11px — hanya untuk teks padat: inisial avatar, keterangan kaki */
    --hcis-font-size-xs: 0.75rem;     /* 12px */
    --hcis-font-size-sm: 0.875rem;    /* 14px — ukuran isi baku */
    --hcis-font-size-md: 1rem;        /* 16px */
    --hcis-font-size-lg: 1.125rem;    /* 18px */
    --hcis-font-size-xl: 1.5rem;      /* 24px */
    --hcis-font-size-2xl: 2rem;       /* 32px */
```

Jadi:

```css
    /*
     * Skala design.md, apa adanya. Delapan anak tangga yang seluruhnya jatuh
     * di 10,4–13,6px: selisih antaranak tangga di ujung atas kurang dari
     * setengah piksel, jadi hierarkinya praktis dibawa bobot, bukan ukuran.
     *
     * font-size-base sengaja LEBIH BESAR dari seluruh skala. Itu bukan salah
     * ketik — design.md menetapkan base 16px sementara puncak skalanya 13,6px.
     */
    --hcis-font-size-2xs: 0.65rem;    /* 10,4px  — design.md font.size.xs */
    --hcis-font-size-xs: 0.68rem;     /* 10,88px — font.size.sm */
    --hcis-font-size-sm: 0.72rem;     /* 11,52px — font.size.md */
    --hcis-font-size-md: 0.78rem;     /* 12,48px — font.size.lg */
    --hcis-font-size-lg: 0.8rem;      /* 12,8px  — font.size.xl */
    --hcis-font-size-xl: 0.8125rem;   /* 13px    — font.size.2xl */
    --hcis-font-size-2xl: 0.82rem;    /* 13,12px — font.size.3xl */
    --hcis-font-size-3xl: 0.85rem;    /* 13,6px  — font.size.4xl */
    --hcis-font-size-base: 1rem;      /* 16px    — font.size.base, akar dokumen */
    --hcis-line-base: 1.5;            /* 24px pada 16px — font.lineHeight.base */
```

Lalu ganti baris 528:

```css
    --hcis-font-body: 'Nunito', 'Segoe UI', system-ui, -apple-system, sans-serif;
```

jadi:

```css
    --hcis-font-body: 'Nunito', sans-serif;
```

- [ ] **Langkah 4: Selaraskan akar dokumen**

Di `resources/views/app.blade.php` baris 53, ganti:

```css
            font-family: 'Plus Jakarta Sans', 'Nunito', system-ui, -apple-system, sans-serif;
```

jadi:

```css
            font-family: 'Nunito', sans-serif;
```

Ini wajib: ada penjaga yang menuntut keluarga huruf seragam antar berkas. Kalau akar dokumen menyebut keluarga lain lebih dulu, hurufnya berganti sesaat setelah halaman muncul — gejala yang mudah salah dibaca sebagai "cache" atau "internet lambat".

- [ ] **Langkah 5: Jalankan tes, pastikan lulus**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
npx vitest run resources/js/components/ui/designSystemGuards.test.js
```

Diharapkan: keduanya LULUS.

- [ ] **Langkah 6: Siapkan commit** *(jalankan hanya bila diminta)*

```bash
git add resources/js/components/ui/designMdTokens.test.js resources/css/design-system.css resources/views/app.blade.php
git commit -m "feat(design-system): skala dan keluarga huruf design.md"
```

---

### Task 4: Skala jarak dan radius

**Berkas:**
- Ubah: `resources/js/components/ui/designMdTokens.test.js`
- Ubah: `resources/css/design-system.css` — blok `--hcis-space-*` dan `--hcis-radius-*`

- [ ] **Langkah 1: Tulis tes yang gagal**

```js
describe('design.md — jarak', () => {
    /*
     * design.md berhenti di langkah 8 (6,72px). Tiga token sisanya diteruskan
     * pada slope yang sama: space.8 / 8 = 0,84px per langkah.
     *
     * Kenapa bukan dipertahankan pada nilai lama: itu akan menghasilkan
     * lompatan 6,72px → 40px di tengah SATU skala, dan itu persis "one-off
     * spacing exception" yang design.md larang.
     */
    it.each([
        ['--hcis-space-1', '0.0625rem'],   // 1px
        ['--hcis-space-2', '0.2rem'],      // 3,2px
        ['--hcis-space-3', '0.22rem'],     // 3,52px
        ['--hcis-space-4', '0.25rem'],     // 4px
        ['--hcis-space-5', '0.32rem'],     // 5,12px
        ['--hcis-space-6', '0.375rem'],    // 6px
        ['--hcis-space-7', '0.38rem'],     // 6,08px
        ['--hcis-space-8', '0.42rem'],     // 6,72px
        ['--hcis-space-10', '0.525rem'],   // 8,4px  — ekstrapolasi
        ['--hcis-space-12', '0.63rem'],    // 10,08px — ekstrapolasi
        ['--hcis-space-16', '0.84rem'],    // 13,44px — ekstrapolasi
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });
});

describe('design.md — radius', () => {
    /*
     * Turunan calc(--hcis-radius-base * n) dibongkar jadi nilai langsung:
     * skala design.md bukan skala berkelipatan, jadi rumusnya tidak berlaku.
     *
     * Efek yang perlu disadari: radius-sm (elemen kecil DI DALAM kontrol)
     * kini LEBIH BULAT daripada radius-md (tombol). Itu bawaan urutan di
     * design.md, bukan salah salin.
     */
    it.each([
        ['--hcis-radius-base', '0.625rem'],  // 10px    — radius.xs, sudah sama
        ['--hcis-radius-sm', '0.72rem'],     // 11,52px — radius.sm
        ['--hcis-radius-md', '0.75rem'],     // 12px    — radius.md
        ['--hcis-radius-lg', '0.8rem'],      // 12,8px  — radius.lg
        ['--hcis-radius-xl', '0.82rem'],     // 13,12px — radius.xl
        ['--hcis-radius-2xl', '0.875rem'],   // 14px    — radius.2xl
        ['--hcis-radius-3xl', '0.9rem'],     // 14,4px  — radius.step7
        ['--hcis-radius-4xl', '0.95rem'],    // 15,2px  — radius.step8
        ['--hcis-radius-pill', '999px'],     // struktural, bukan skala
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });
});
```

- [ ] **Langkah 2: Jalankan tes, pastikan gagal**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: GAGAL, 18 dari 20 tes baru merah. `--hcis-radius-base` dan `--hcis-radius-pill` sudah benar.

- [ ] **Langkah 3: Pasang jaraknya**

Ganti blok:

```css
    --hcis-space-1: 0.25rem;
    --hcis-space-2: 0.5rem;
    --hcis-space-3: 0.75rem;
    --hcis-space-4: 1rem;
    --hcis-space-5: 1.25rem;
    --hcis-space-6: 1.5rem;
    --hcis-space-8: 2rem;
    --hcis-space-10: 2.5rem;
    --hcis-space-12: 3rem;
    --hcis-space-16: 4rem;
```

Jadi:

```css
    /*
     * Skala design.md, apa adanya. Seluruh skala muat di 1–6,72px, jadi
     * "space-16" bukan lagi 64px melainkan 13,44px — nama tokennya tetap
     * karena ia dipakai ratusan kali, tapi artinya sudah berubah total.
     *
     * design.md berhenti di langkah 8. Tiga terakhir diteruskan pada slope
     * yang sama (0,84px per langkah), bukan dipertahankan pada nilai lama:
     * lompatan 6,72px → 40px di tengah satu skala adalah pengecualian, dan
     * design.md melarang pengecualian.
     */
    --hcis-space-1: 0.0625rem;  /* 1px */
    --hcis-space-2: 0.2rem;     /* 3,2px */
    --hcis-space-3: 0.22rem;    /* 3,52px */
    --hcis-space-4: 0.25rem;    /* 4px */
    --hcis-space-5: 0.32rem;    /* 5,12px */
    --hcis-space-6: 0.375rem;   /* 6px */
    --hcis-space-7: 0.38rem;    /* 6,08px */
    --hcis-space-8: 0.42rem;    /* 6,72px */
    --hcis-space-10: 0.525rem;  /* 8,4px — ekstrapolasi */
    --hcis-space-12: 0.63rem;   /* 10,08px — ekstrapolasi */
    --hcis-space-16: 0.84rem;   /* 13,44px — ekstrapolasi */
```

- [ ] **Langkah 4: Pasang radiusnya**

Ganti blok:

```css
    /*
     * Skala shadcn, diturunkan dari --radius: 0.5rem.
     * Jauh lebih rapat dari sebelumnya (12/16/20px) — ini yang membuat
     * antarmuka terasa tenang dan tidak menggelembung.
     */
    --hcis-radius-base: 0.625rem;                            /* 10px — akar skala shadcn */
    --hcis-radius-sm: calc(var(--hcis-radius-base) * 0.6);   /* 6px  — elemen kecil di dalam kontrol */
    --hcis-radius-md: calc(var(--hcis-radius-base) * 0.8);   /* 8px  — tombol, input, dropdown, tooltip */
    --hcis-radius-lg: var(--hcis-radius-base);               /* 10px — dialog */
    --hcis-radius-xl: calc(var(--hcis-radius-base) * 1.4);   /* 14px — kartu */
    --hcis-radius-pill: 999px;
```

Jadi:

```css
    /*
     * Skala design.md, apa adanya. Rumus calc() dibongkar: skala ini bukan
     * skala berkelipatan, jadi menurunkannya dari satu akar sudah tidak bisa.
     *
     * Rentangnya menyempit dari 6–14px jadi 10–15,2px, dan radius-sm (elemen
     * kecil DI DALAM kontrol) kini lebih bulat daripada radius-md (tombol).
     * Itu urutan yang design.md berikan, bukan salah salin.
     */
    --hcis-radius-base: 0.625rem;  /* 10px    — radius.xs */
    --hcis-radius-sm: 0.72rem;     /* 11,52px — radius.sm */
    --hcis-radius-md: 0.75rem;     /* 12px    — radius.md, tombol/input/dropdown */
    --hcis-radius-lg: 0.8rem;      /* 12,8px  — radius.lg, dialog */
    --hcis-radius-xl: 0.82rem;     /* 13,12px — radius.xl, kartu */
    --hcis-radius-2xl: 0.875rem;   /* 14px    — radius.2xl */
    --hcis-radius-3xl: 0.9rem;     /* 14,4px  — radius.step7 */
    --hcis-radius-4xl: 0.95rem;    /* 15,2px  — radius.step8 */
    --hcis-radius-pill: 999px;
```

- [ ] **Langkah 5: Jalankan tes, pastikan lulus**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
npm run test:unit
```

Diharapkan: keduanya LULUS.

- [ ] **Langkah 6: Siapkan commit** *(jalankan hanya bila diminta)*

```bash
git add resources/js/components/ui/designMdTokens.test.js resources/css/design-system.css
git commit -m "feat(design-system): skala jarak dan radius design.md"
```

---

### Task 5: Bayangan, glow, dan gerak

**Berkas:**
- Ubah: `resources/js/components/ui/designMdTokens.test.js`
- Ubah: `resources/css/design-system.css` — blok `--hcis-shadow-*`, `--hcis-glow-*`, `--hcis-duration-*`, `--hcis-slide-duration`

- [ ] **Langkah 1: Tulis tes yang gagal**

```js
describe('design.md — bayangan', () => {
    /*
     * design.md memberi EMPAT resep, dua di antaranya glow berwarna. Jadi
     * sistem turun dari empat tingkat ketinggian jadi dua, dan sisanya
     * dialiaskan — bukan dikarang. Mengarang tingkat ketiga sama dengan
     * mengarang pengecualian, yang design.md larang.
     *
     * Semua ditulis SATU BARIS: penjaga NOL hanya menerima nilai mentah pada
     * baris yang mendefinisikan token, jadi nilai multi-baris akan menjatuhkan
     * seluruh berkas fondasi.
     */
    it('shadow-xs = design.md shadow.4', () => {
        expect(nilaiToken('--hcis-shadow-xs')).toBe(
            '0 1px 3px 0 rgb(var(--hcis-shadow-rgb) / 0.04)',
        );
    });

    it('shadow-sm = design.md shadow.1, lengkap dengan sorot dalamnya', () => {
        expect(nilaiToken('--hcis-shadow-sm')).toBe(
            '0 1px 2px 0 rgb(var(--hcis-shadow-rgb) / 0.05), ' +
                '0 10px 26px -16px rgb(var(--hcis-shadow-rgb) / 0.22), ' +
                'inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)',
        );
    });

    it.each([
        ['--hcis-shadow-md', 'var(--hcis-shadow-sm)'],
        ['--hcis-shadow-lg', 'var(--hcis-shadow-sm)'],
        ['--hcis-shadow-card', 'var(--hcis-shadow-sm)'],
        ['--hcis-shadow-brand', 'var(--hcis-shadow-sm)'],
        ['--hcis-shadow-glow', 'var(--hcis-glow-brand)'],
    ])('%s dialiaskan, bukan dikarang', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('glow-brand = design.md shadow.2', () => {
        expect(nilaiToken('--hcis-glow-brand')).toBe(
            '0 8px 18px -8px rgb(var(--hcis-brand-500-rgb) / 0.6)',
        );
    });

    it('glow-warning = design.md shadow.3', () => {
        expect(nilaiToken('--hcis-glow-warning')).toBe(
            '0 0 0 4px rgb(var(--hcis-warning-500-rgb) / 0.12), ' +
                '0 22px 44px -20px rgb(var(--hcis-warning-500-rgb) / 0.4), ' +
                'inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)',
        );
    });

    it.each([
        ['--hcis-glow-success', 'success'],
        ['--hcis-glow-danger', 'danger'],
    ])('%s memakai resep shadow.3 dengan rona diganti', (token, rona) => {
        // design.md tidak menyebut keduanya, tapi penjaga "token bahasa visual
        // lengkap" menuntut keduanya ada. Resep shadow.3 dipilih karena ia
        // SATU-SATUNYA contoh keadaan berwarna di design.md — keluarga glow-*
        // harus satu dialek.
        expect(nilaiToken(token)).toBe(
            `0 0 0 4px rgb(var(--hcis-${rona}-500-rgb) / 0.12), ` +
                `0 22px 44px -20px rgb(var(--hcis-${rona}-500-rgb) / 0.4), ` +
                'inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9)',
        );
    });
});

describe('design.md — gerak', () => {
    it.each([
        ['--hcis-duration-press', '160ms'],    // motion.duration.instant
        ['--hcis-duration-fast', '200ms'],     // motion.duration.fast
        ['--hcis-duration-normal', '220ms'],   // motion.duration.normal
        ['--hcis-duration-slow', '240ms'],     // motion.duration.slow
        ['--hcis-duration-slower', '250ms'],   // motion.duration.slower
        ['--hcis-duration-panel', '300ms'],    // motion.duration.step6
        ['--hcis-duration-slide', '380ms'],    // motion.duration.step7
        ['--hcis-duration-page', '500ms'],     // motion.duration.step8
    ])('%s = %s', (token, nilai) => {
        expect(nilaiToken(token)).toBe(nilai);
    });

    it('slide-duration menunjuk tangga yang sama, bukan angka lepas', () => {
        expect(nilaiToken('--hcis-slide-duration')).toBe('var(--hcis-duration-slide)');
    });
});
```

- [ ] **Langkah 2: Jalankan tes, pastikan gagal**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: GAGAL, seluruh 20 tes baru merah.

- [ ] **Langkah 3: Pasang bayangannya**

Ganti blok bayangan yang lama (mulai `--hcis-shadow-xs:` sampai `--hcis-shadow-card:`) jadi:

```css
    /*
     * Empat resep design.md, dan hanya empat. Sistem ini sekarang punya DUA
     * tingkat ketinggian, bukan empat — md/lg/card dialiaskan ke shadow.1
     * alih-alih dikarang. Kedalaman antarmuka dibawa tepi dan sorot dalam,
     * bukan oleh tangga bayangan.
     *
     * Ditulis satu baris per token: penjaga NOL hanya menerima nilai mentah
     * pada baris yang mendefinisikan token.
     */
    --hcis-shadow-xs: 0 1px 3px 0 rgb(var(--hcis-shadow-rgb) / 0.04);
    --hcis-shadow-sm: 0 1px 2px 0 rgb(var(--hcis-shadow-rgb) / 0.05), 0 10px 26px -16px rgb(var(--hcis-shadow-rgb) / 0.22), inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9);
    --hcis-shadow-md: var(--hcis-shadow-sm);
    --hcis-shadow-lg: var(--hcis-shadow-sm);
    --hcis-shadow-card: var(--hcis-shadow-sm);
    --hcis-shadow-brand: var(--hcis-shadow-sm);
    --hcis-shadow-glow: var(--hcis-glow-brand);
```

- [ ] **Langkah 4: Pasang glow-nya**

Ganti blok `/* ── 3. Cahaya di sekitar elemen aktif ── */` jadi:

```css
    /* ── 3. Cahaya di sekitar elemen aktif ─────────────────────────────
       glow-brand memakai resep design.md shadow.2 (halo saja), glow-warning
       memakai shadow.3 (cincin 4px + halo + sorot dalam). design.md tidak
       menyebut success dan danger; keduanya diturunkan dari resep shadow.3
       dengan rona diganti, karena shadow.3 adalah satu-satunya contoh
       KEADAAN berwarna di sana — keluarga ini harus satu dialek. */
    --hcis-glow-brand: 0 8px 18px -8px rgb(var(--hcis-brand-500-rgb) / 0.6);
    --hcis-glow-warning: 0 0 0 4px rgb(var(--hcis-warning-500-rgb) / 0.12), 0 22px 44px -20px rgb(var(--hcis-warning-500-rgb) / 0.4), inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9);
    --hcis-glow-success: 0 0 0 4px rgb(var(--hcis-success-500-rgb) / 0.12), 0 22px 44px -20px rgb(var(--hcis-success-500-rgb) / 0.4), inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9);
    --hcis-glow-danger: 0 0 0 4px rgb(var(--hcis-danger-500-rgb) / 0.12), 0 22px 44px -20px rgb(var(--hcis-danger-500-rgb) / 0.4), inset 0 1px 0 0 rgb(var(--hcis-white-rgb) / 0.9);
```

**Penting soal urutan:** `--hcis-shadow-glow` menunjuk `--hcis-glow-brand`. Di CSS custom property urutan deklarasi tidak masalah untuk resolusi nilai, jadi ini aman meski `--hcis-glow-brand` didefinisikan belakangan di berkas yang sama.

- [ ] **Langkah 5: Pasang geraknya**

Ganti:

```css
    --hcis-duration-press: 75ms;
    --hcis-duration-fast: 150ms;
    --hcis-duration-normal: 200ms;
```

Jadi:

```css
    /*
     * Tangga durasi design.md — delapan anak tangga, bukan tiga.
     *
     * Kickback tombol melambat dari 75ms ke 160ms karena design.md tidak
     * punya anak tangga di bawah "instant". CLAUDE.md §4 menyebut kickback
     * sebagai satu-satunya gerak yang sengaja dipertahankan di mode gerak
     * tenang, karena ia terjadi tepat di bawah jari pengguna — dan 160ms
     * sudah di ambang terasa lamban. Kalau nanti mengganjal, itu keputusan
     * terpisah, bukan bug.
     */
    --hcis-duration-press: 160ms;    /* motion.duration.instant */
    --hcis-duration-fast: 200ms;     /* motion.duration.fast */
    --hcis-duration-normal: 220ms;   /* motion.duration.normal */
    --hcis-duration-slow: 240ms;     /* motion.duration.slow */
    --hcis-duration-slower: 250ms;   /* motion.duration.slower */
    --hcis-duration-panel: 300ms;    /* motion.duration.step6 */
    --hcis-duration-slide: 380ms;    /* motion.duration.step7 */
    --hcis-duration-page: 500ms;     /* motion.duration.step8 */
```

Lalu ganti `--hcis-slide-duration: 320ms;` jadi:

```css
    --hcis-slide-duration: var(--hcis-duration-slide);
```

- [ ] **Langkah 6: Jalankan tes, pastikan lulus**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
npx vitest run resources/js/components/ui/designSystemGuards.test.js
```

Diharapkan: keduanya LULUS. Kalau "NOL — berkas fondasi tidak menulis warna mentah di luar baris definisi token" gagal, berarti ada nilai bayangan yang terpecah ke baris kedua — satukan.

- [ ] **Langkah 7: Siapkan commit** *(jalankan hanya bila diminta)*

```bash
git add resources/js/components/ui/designMdTokens.test.js resources/css/design-system.css
git commit -m "feat(design-system): bayangan, glow, dan tangga gerak design.md"
```

---

### Task 6: Merapikan permukaan yang terbalik

Sesudah Task 2, permukaan tertutup gelap sementara halamannya terang. Tugas ini yang membuatnya terbaca sebagai keputusan. Arah rasanya: **obsidian** — permukaan hitam pekat, garis rambut terang, sorot dalam putih dari `shadow.1` di tepi atas, indigo sebagai satu-satunya warna kuat.

**Berkas:**
- Ubah: `resources/css/design-system.css` — `--hcis-gradient-hero`, aturan hover baris tabel, warna ikon varian

- [ ] **Langkah 1: Tulis tes yang gagal**

```js
describe('design.md — permukaan terbalik dirapikan', () => {
    it('gradient-hero tidak lagi melewati hitam di titik tengah', () => {
        /*
         * --hcis-surface kini hitam, jadi gradasi lama berbunyi
         * brand-50 → HITAM → brand-100. Lompatan terang→gelap→terang di satu
         * gradasi bukan "gradasi lembut" seperti maksud CLAUDE.md §2b.
         * Titik tengahnya pindah ke surface-soft (#ffffff).
         *
         * Ini mengubah PENYUSUN gradasi, bukan nilai design.md mana pun.
         */
        const hero = nilaiToken('--hcis-gradient-hero');
        expect(hero).not.toContain('var(--hcis-surface)');
        expect(hero).toContain('var(--hcis-surface-soft)');
    });

    it('sorotan baris menerangkan, bukan menggelapkan', () => {
        // Menggelapkan di atas hitam tidak menghasilkan perubahan yang
        // terlihat sama sekali.
        expect(nilaiToken('--hcis-row-hover')).toBe('rgb(var(--hcis-white-rgb) / 0.06)');
        expect(nilaiToken('--hcis-row-selected')).toBe('rgb(var(--hcis-white-rgb) / 0.1)');
    });

    it('ikon varian naik satu langkah rona agar terbaca di atas hitam', () => {
        expect(nilaiToken('--hcis-icon-success')).toBe('var(--hcis-success-400, var(--hcis-emerald-400))');
        expect(nilaiToken('--hcis-icon-warning')).toBe('var(--hcis-warning-400)');
        expect(nilaiToken('--hcis-icon-danger')).toBe('var(--hcis-danger-400)');
    });
});
```

- [ ] **Langkah 2: Jalankan tes, pastikan gagal**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
```

Diharapkan: GAGAL, 3 tes baru merah — `--hcis-row-hover` dan kawan-kawan belum ada.

- [ ] **Langkah 3: Perbaiki gradasi hero**

Ganti:

```css
    --hcis-gradient-hero: linear-gradient(135deg, var(--hcis-brand-50) 0%, var(--hcis-surface) 50%, var(--hcis-brand-100) 100%);
```

jadi:

```css
    /* Titik tengah memakai surface-soft, bukan surface: sejak surface jadi
       hitam, memakainya di sini menghasilkan lompatan terang→gelap→terang
       dalam satu gradasi — kebalikan dari "gradasi lembut". */
    --hcis-gradient-hero: linear-gradient(135deg, var(--hcis-brand-50) 0%, var(--hcis-surface-soft) 50%, var(--hcis-brand-100) 100%);
```

- [ ] **Langkah 4: Tambahkan token sorotan baris dan ikon varian**

Di blok lapis 2, tepat sesudah `--hcis-border-brand`, tambahkan:

```css
    /* ── Sorotan baris ─────────────────────────────────────────────────
       Sejak permukaan jadi hitam, sorotan harus MENERANGKAN. Menggelapkan
       di atas hitam tidak menghasilkan perubahan yang terlihat sama sekali —
       barisnya terasa mati saat disorot, dan gejalanya mudah salah dibaca
       sebagai "hover-nya tidak jalan". */
    --hcis-row-hover: rgb(var(--hcis-white-rgb) / 0.06);
    --hcis-row-selected: rgb(var(--hcis-white-rgb) / 0.1);

    /* ── Ikon varian di atas permukaan gelap ───────────────────────────
       Ikon biasa mewarisi currentColor dari --hcis-text dan ikut otomatis.
       Yang tidak ikut adalah ikon yang warnanya dikunci varian; ketiganya
       naik satu langkah rona supaya tetap terbaca di atas hitam.
       success-400 belum ada sebagai token peran, jadi jatuh ke primitifnya. */
    --hcis-icon-success: var(--hcis-success-400, var(--hcis-emerald-400));
    --hcis-icon-warning: var(--hcis-warning-400);
    --hcis-icon-danger: var(--hcis-danger-400);
```

- [ ] **Langkah 5: Pakai token sorotan di aturan tabel**

Temukan dulu aturan mana yang menyorot baris:

```bash
grep -n -E 'tr:hover|is-selected|row--selected|tbody tr' resources/css/design-system.css
grep -rn -E 'tr:hover|is-selected' resources/js/components/ui/HcisTable.vue resources/js/components/ui/HcisDataTable.vue
```

Untuk setiap aturan yang ditemukan, ganti nilai `background` / `background-color`-nya:

```css
/* sebelum — apa pun bentuknya, selama ia menggelapkan */
background: var(--hcis-slate-50);

/* sesudah */
background: var(--hcis-row-hover);      /* pada aturan :hover */
background: var(--hcis-row-selected);   /* pada aturan baris terpilih */
```

Kalau aturannya ada di `<style>` komponen dan bukan di `design-system.css`, ganti di sana — token dipakai dari komponen memang boleh; yang dilarang adalah mendefinisikan token di luar `design-system.css`.

Jangan menambah `!important` — ambang ratchetnya 269 dan tidak boleh naik. Kalau kekhususannya kurang, perbaiki selektornya.

- [ ] **Langkah 6: Jalankan tes, pastikan lulus**

```bash
npx vitest run resources/js/components/ui/designMdTokens.test.js
npm run test:unit
```

Diharapkan: keduanya LULUS.

- [ ] **Langkah 7: Siapkan commit** *(jalankan hanya bila diminta)*

```bash
git add resources/js/components/ui/designMdTokens.test.js resources/css/design-system.css
git commit -m "fix(design-system): rapikan permukaan yang arah terangnya terbalik"
```

---

### Task 7: Verifikasi penuh dan tinjauan mata

**Berkas:** tidak ada perubahan kode kecuali perbaikan yang ditemukan.

- [ ] **Langkah 1: Seluruh tes**

```bash
npm run test:unit
```

Diharapkan: LULUS seluruhnya. Kalau ada yang merah, perbaiki sebabnya — **jangan** menaikkan angka ratchet mana pun.

- [ ] **Langkah 2: Build**

```bash
npm run build; echo $?
```

Diharapkan: baris terakhir `0`. Jangan pakai `| tail` — ia mengambil status keluar dari `tail`, bukan dari build, dan kesalahan itu sudah pernah membuat build gagal dilaporkan sebagai sukses.

- [ ] **Langkah 3: Tinjauan mata**

Buka dan lihat, satu per satu:

1. `/design-system` — hero, kartu katalog, bilah filter
2. `/design-system` tiap komponen — terutama tombol, kartu, tabel, dialog, dropdown, isian
3. `/activity/system-health` — **ini patokannya.** Satu-satunya halaman nyata yang ikut berubah, karena ia satu-satunya yang sudah memanggil `useElementBridge()`

Yang dicari: apakah hasilnya terbaca sebagai keputusan (obsidian: permukaan hitam, garis rambut terang, sorot dalam di tepi atas) atau sebagai kerusakan (elemen hilang karena warnanya menyatu dengan latar, teks tak terbaca, batas yang lenyap).

- [ ] **Langkah 4: Laporkan temuan**

Tulis daftar apa yang terlihat rusak beserta berkasnya. **Jangan** perbaiki sambil lalu — sebagian mungkin butuh keputusan pemilik proyek, dan sebagian mungkin justru bawaan nilai `design.md` yang sudah diterima sadar.

---

## Sesudah rencana ini

Matriks 7 keadaan (`keadaan` / `interaksi` / `tepian` di `registry.js`, penjaga NOL barunya, dan perendernya di `Component.vue`) ditulis sebagai rencana terpisah. Ia tidak bergantung pada nilai token sama sekali dan berguna dengan atau tanpa `design.md`.
