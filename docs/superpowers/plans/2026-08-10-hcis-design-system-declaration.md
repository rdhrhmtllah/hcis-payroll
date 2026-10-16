# HCIS Design System — Declaration Surface Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun permukaan deklarasi design system HCIS — satu registry data, halaman `/design-system` yang merender darinya, lima komponen `Hcis*` baru, dan jembatan token Element Plus berlingkup — tanpa mengubah tampilan satu pun halaman yang sudah ada.

**Architecture:** Deklarasi tiap komponen hidup sebagai data di `resources/js/components/ui/registry.js`. Halaman `/design-system` merender dari data itu lewat satu komponen halaman generik plus komponen demo per entri, sehingga menambah komponen berarti menambah satu entri registry dan satu berkas demo. Komponen Element Plus tidak dibungkus — hanya dideklarasikan sebagai `borrowed` dan didandani lewat `element-plus-bridge.css` yang dibatasi kelas induk `.ds-preview`.

**Tech Stack:** Vue 3 (script setup), Inertia 2, Vite, Element Plus 2.13, vitest + @vue/test-utils + jsdom.

## Global Constraints

- **NOL perubahan tampilan pada halaman yang sudah ada.** Dilarang menyunting berkas di `resources/js/Pages/**` selain di dalam `resources/js/Pages/DesignSystem/`. Dilarang menambah impor CSS global ke `resources/js/app.js`.
- `element-plus-bridge.css` **wajib** seluruhnya bersarang di bawah `.ds-preview`. Tidak boleh ada satu pun selektor tingkat akar.
- Setiap `<style>` di komponen halaman **wajib** `scoped`.
- Warna, jarak, radius, dan durasi **wajib** memakai token `--hcis-*`. Dilarang menulis heksadesimal, px, atau ms mentah di komponen baru.
- Komponen baru tidak boleh memakai kelas Bootstrap (`btn`, `card-body`, `col-md-*`, `row`) atau PrimeVue.
- Bahasa antarmuka: Indonesia. Nama kode, prop, dan berkas: Inggris.
- Perintah tes: `npm run test:unit`.
- Status registry yang sah: `ready` | `draft` | `planned` | `deprecated`. Komponen yang keputusannya belum disahkan pemilik repo ditandai `draft`.

---

## Struktur berkas

**Dibuat:**

| Berkas | Tanggung jawab |
|---|---|
| `resources/js/components/ui/registry.js` | Sumber tunggal deklarasi seluruh entri (owned + borrowed) |
| `resources/js/components/ui/registry.test.js` | Menjaga invarian bentuk registry |
| `resources/js/components/ui/HcisStatCard.vue` | Kartu statistik |
| `resources/js/components/ui/HcisSkeleton.vue` | Balok pemuatan |
| `resources/js/components/ui/HcisTable.vue` | Tabel presentasional ringan |
| `resources/js/components/ui/HcisTabs.vue` | Tab navigasi dalam halaman |
| `resources/js/components/ui/HcisBreadcrumb.vue` | Remah roti |
| `resources/js/components/ui/HcisNewComponents.test.js` | Tes lima komponen baru |
| `resources/css/element-plus-bridge.css` | Peta `--el-*` → `--hcis-*`, berlingkup `.ds-preview` |
| `resources/js/Pages/DesignSystem/Index.vue` | Beranda + tabel inventaris |
| `resources/js/Pages/DesignSystem/Component.vue` | Halaman komponen generik, tujuh blok |
| `resources/js/Pages/DesignSystem/partials/DsSection.vue` | Blok bertajuk |
| `resources/js/Pages/DesignSystem/partials/DsApiTable.vue` | Tabel props/events/slots |
| `resources/js/Pages/DesignSystem/partials/DsRules.vue` | Daftar Lakukan / Jangan |
| `resources/js/Pages/DesignSystem/partials/DsMigration.vue` | Tabel pola lama → komponen |
| `resources/js/Pages/DesignSystem/demos/*.vue` | Satu berkas demo per entri (anatomi, varian, state) |

**Dimodifikasi:**

| Berkas | Perubahan |
|---|---|
| `resources/js/components/ui/index.js` | Tambah ekspor lima komponen baru |
| `routes/web.php:123-127` | Ganti rute tunggal menjadi beranda + rute per komponen |
| `resources/css/design-system.css` | Tambah kelas untuk lima komponen baru + perbaiki `hcis-btn--md` |
| `resources/js/Pages/DesignSystem.vue` | **Dihapus**, digantikan folder `DesignSystem/` |

**Catatan turunan dari K5.** Karena Element Plus tidak dibungkus, entri berikut hanya **dideklarasikan**, tidak dibangun: `el-select`, `el-date-picker`, `el-upload`, `el-tooltip`, `el-dialog` (modal), `el-pagination`, `ElMessage` (notifikasi). Ini menyimpang dari daftar Putaran 3–4 di spec §5 yang sempat menyebut Modal dan Pagination sebagai komponen; K5 mengalahkannya. Bila pemilik repo menginginkan pembungkus untuk salah satunya, itu keputusan baru di luar rencana ini.

---

## Task 1: Registry — bentuk data dan invariannya

**Files:**
- Create: `resources/js/components/ui/registry.js`
- Test: `resources/js/components/ui/registry.test.js`

**Interfaces:**
- Consumes: —
- Produces: `export const registry` (array), `export function getEntry(slug)`, `export const VALID_STATUS`, `export const VALID_KIND`. Setiap entri: `{ slug, name, kind, status, summary, usedIn, group, props?, events?, slots?, allowed?, forbidden?, replaces, rules: { do: [], dont: [] } }`.

- [ ] **Step 1: Tulis tes yang gagal**

```js
// resources/js/components/ui/registry.test.js
import { describe, expect, it } from 'vitest';
import { registry, getEntry, VALID_KIND, VALID_STATUS } from './registry';

describe('registry design system', () => {
    it('setiap entri punya medan wajib dan nilai yang sah', () => {
        expect(registry.length).toBeGreaterThan(0);

        for (const entry of registry) {
            expect(typeof entry.slug, `slug pada ${entry.name}`).toBe('string');
            expect(entry.slug).toMatch(/^[a-z0-9-]+$/);
            expect(typeof entry.name).toBe('string');
            expect(VALID_KIND).toContain(entry.kind);
            expect(VALID_STATUS).toContain(entry.status);
            expect(typeof entry.summary).toBe('string');
            expect(entry.summary.length).toBeGreaterThan(10);
            expect(Array.isArray(entry.rules.do)).toBe(true);
            expect(Array.isArray(entry.rules.dont)).toBe(true);
            expect(Array.isArray(entry.replaces)).toBe(true);
        }
    });

    it('slug bersifat unik', () => {
        const slugs = registry.map((e) => e.slug);
        expect(new Set(slugs).size).toBe(slugs.length);
    });

    it('entri owned mendeklarasikan props, entri borrowed mendeklarasikan batas pemakaian', () => {
        for (const entry of registry) {
            if (entry.kind === 'owned') {
                expect(Array.isArray(entry.props), `props pada ${entry.slug}`).toBe(true);
            } else {
                expect(Array.isArray(entry.allowed), `allowed pada ${entry.slug}`).toBe(true);
                expect(Array.isArray(entry.forbidden), `forbidden pada ${entry.slug}`).toBe(true);
                for (const f of entry.forbidden) {
                    expect(typeof f.what).toBe('string');
                    expect(f.why.length, `alasan larangan ${f.what}`).toBeGreaterThan(10);
                }
            }
        }
    });

    it('getEntry mengembalikan entri berdasarkan slug dan undefined bila tak ada', () => {
        expect(getEntry('button').name).toBe('HcisButton');
        expect(getEntry('tidak-ada')).toBeUndefined();
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npm run test:unit -- registry`
Expected: FAIL — `Failed to resolve import "./registry"`

- [ ] **Step 3: Tulis registry**

```js
// resources/js/components/ui/registry.js
/**
 * Sumber tunggal deklarasi design system HCIS.
 *
 * Berkas ini dibaca oleh dua pihak:
 *   1. Halaman /design-system, yang merender seluruh isinya.
 *   2. Agent (Claude Code dkk), lewat penunjuk di CLAUDE.md.
 *
 * Karena itu TIDAK BOLEH ada salinan kedua dari informasi di sini.
 * Menambah komponen = menambah satu entri di sini + satu berkas demo
 * di resources/js/Pages/DesignSystem/demos/.
 *
 * kind:
 *   owned    — markup, perilaku, dan gaya milik kita
 *   borrowed — milik Element Plus; kita hanya memiliki gayanya lewat
 *              element-plus-bridge.css. Dilarang menimpa .el-* di berkas halaman.
 */

export const VALID_KIND = ['owned', 'borrowed'];
export const VALID_STATUS = ['ready', 'draft', 'planned', 'deprecated'];

export const registry = [
    {
        slug: 'button',
        name: 'HcisButton',
        kind: 'owned',
        status: 'draft',
        group: 'Aksi & status',
        usedIn: 0,
        summary: 'Tombol aksi. Satu aksi primer per layar; sisanya turun ke secondary atau ghost.',
        props: [
            { name: 'label', type: 'String', default: "''", note: 'Dipakai bila slot bawaan kosong' },
            { name: 'variant', type: 'String', default: "'primary'", note: 'primary · secondary · ghost · danger · danger-quiet' },
            { name: 'size', type: 'String', default: "'md'", note: 'sm · md' },
            { name: 'type', type: 'String', default: "'button'", note: 'Sengaja bukan submit, agar tidak mengirim formulir tanpa sengaja' },
            { name: 'href', type: 'String', default: "''", note: 'Bila diisi, dirender sebagai <a> — bukan <button>' },
            { name: 'loading', type: 'Boolean', default: 'false', note: 'Menampilkan spinner dan menonaktifkan tombol' },
            { name: 'disabled', type: 'Boolean', default: 'false', note: '' },
            { name: 'block', type: 'Boolean', default: 'false', note: 'Lebar penuh' },
            { name: 'iconOnly', type: 'Boolean', default: 'false', note: 'Wajib disertai aria-label' },
        ],
        events: [],
        slots: [
            { name: 'default', note: 'Teks tombol' },
            { name: 'leading', note: 'Ikon di depan teks; disembunyikan saat loading' },
            { name: 'trailing', note: 'Ikon di belakang teks; disembunyikan saat loading' },
        ],
        replaces: [
            { from: '.btn.btn-primary (Bootstrap)', to: '<HcisButton>' },
            { from: '.btn.btn-outline-secondary (Bootstrap)', to: '<HcisButton variant="secondary">' },
            { from: '<el-button>', to: '<HcisButton>' },
        ],
        rules: {
            do: [
                'Satu tombol primary per layar.',
                'Pakai variant="ghost" untuk aksi di dalam baris tabel dan kepala kartu.',
                'Isi href bila aksinya berpindah halaman, agar Ctrl+klik dan buka-di-tab-baru tetap jalan.',
                'Sertakan aria-label pada tombol khusus ikon.',
            ],
            dont: [
                'Jangan pakai variant="danger" di halaman biasa — merah solid hanya untuk dialog konfirmasi.',
                'Jangan pakai <el-button>; kita punya tombol sendiri.',
                'Jangan menaruh tombol di dalam tombol.',
            ],
        },
    },
    {
        slug: 'badge',
        name: 'HcisBadge',
        kind: 'owned',
        status: 'draft',
        group: 'Aksi & status',
        usedIn: 268,
        summary: 'Lencana pil untuk status. Bukan tombol, bukan tautan — ia tidak bisa diklik.',
        props: [
            { name: 'label', type: 'String', default: "''", note: 'Dipakai bila slot bawaan kosong' },
            { name: 'variant', type: 'String', default: "'neutral'", note: 'neutral · primary · success · warning · danger · info' },
            { name: 'size', type: 'String', default: "'md'", note: 'sm (dalam tabel) · md' },
            { name: 'dot', type: 'Boolean', default: 'false', note: 'Titik indikator di depan teks' },
        ],
        events: [],
        slots: [{ name: 'default', note: 'Isi lencana' }],
        replaces: [
            { from: '.stitch-badge (13 berkas, saling divergen)', to: '<HcisBadge>' },
            { from: '.badge.bg-success (Bootstrap)', to: '<HcisBadge variant="success">' },
            { from: '<el-tag>', to: '<HcisBadge>' },
        ],
        rules: {
            do: [
                'Petakan status baru ke salah satu dari enam varian yang ada.',
                'Pakai size="sm" di dalam baris tabel agar barisnya tidak melar.',
                'Sertakan teks, bukan hanya warna — warna sendirian bukan penanda yang cukup.',
            ],
            dont: [
                'Jangan memberi @click pada lencana. Kalau bisa diklik, itu tombol.',
                'Jangan membuat warna status baru di luar enam varian.',
                'Jangan memakai lencana untuk teks panjang; batasnya terpotong di 9rem.',
            ],
        },
    },
    {
        slug: 'stat-card',
        name: 'HcisStatCard',
        kind: 'owned',
        status: 'draft',
        group: 'Aksi & status',
        usedIn: 72,
        summary: 'Kartu angka ringkas untuk dasbor. Angka adalah alasan kartu ini ada, jadi ia yang paling besar.',
        props: [
            { name: 'label', type: 'String', default: 'wajib', note: 'Nama metrik' },
            { name: 'value', type: 'String · Number', default: 'wajib', note: 'Angka utama' },
            { name: 'delta', type: 'String', default: "''", note: 'mis. "12%"' },
            { name: 'trend', type: 'String', default: "''", note: "'up' · 'down' — arah panah" },
            { name: 'sentiment', type: 'String', default: "'neutral'", note: "'good' · 'bad' · 'neutral' — warna, dipisah dari arah" },
            { name: 'caption', type: 'String', default: "''", note: 'Keterangan kecil di bawah delta' },
            { name: 'loading', type: 'Boolean', default: 'false', note: 'Mengganti angka dengan skeleton, label tetap terlihat' },
        ],
        events: [],
        slots: [{ name: 'default', note: 'Konten tambahan di bawah angka' }],
        replaces: [
            { from: '.quick-stat-card (modul SOP)', to: '<HcisStatCard>' },
            { from: '.ck-stat (kokpit legacy)', to: '<HcisStatCard>' },
            { from: '.kpi-card', to: '<HcisStatCard>' },
        ],
        rules: {
            do: [
                'Pisahkan trend dan sentiment. "Keterlambatan naik 12%" berarti trend="up" sentiment="bad".',
                'Pakai loading agar tata letak tidak melompat saat data tiba.',
            ],
            dont: [
                'Jangan mengikat warna ke arah panah — banyak metrik HCIS yang "naik = buruk".',
                'Jangan menaruh lebih dari satu angka utama dalam satu kartu.',
            ],
        },
    },
    {
        slug: 'card',
        name: 'HcisCard',
        kind: 'owned',
        status: 'ready',
        group: 'Wadah',
        usedIn: 0,
        summary: 'Wadah dasar untuk konten berkelompok, dengan header, body, dan footer opsional.',
        props: [
            { name: 'title', type: 'String', default: "''", note: 'Bila kosong dan slot header kosong, header tidak dirender' },
            { name: 'description', type: 'String', default: "''", note: '' },
            { name: 'interactive', type: 'Boolean', default: 'false', note: 'Efek terangkat saat hover' },
        ],
        events: [],
        slots: [
            { name: 'default', note: 'Body' },
            { name: 'header', note: 'Tambahan di bawah judul' },
            { name: 'actions', note: 'Kanan header' },
            { name: 'footer', note: 'Hanya dirender bila diisi' },
        ],
        replaces: [
            { from: '.card > .card-body (Bootstrap)', to: '<HcisCard>' },
            { from: '.sop-glass-card (kokpit legacy)', to: '<HcisCard>' },
        ],
        rules: {
            do: ['Pakai interactive hanya bila seluruh kartu memang bisa diklik.'],
            dont: ['Jangan menyarangkan kartu di dalam kartu.'],
        },
    },
    {
        slug: 'field',
        name: 'HcisField',
        kind: 'owned',
        status: 'ready',
        group: 'Formulir',
        usedIn: 0,
        summary: 'Input teks dengan label, hint, dan galat dalam satu elemen <label>.',
        props: [
            { name: 'id', type: 'String', default: 'wajib', note: 'Menyambungkan label ke input' },
            { name: 'label', type: 'String', default: 'wajib', note: 'Tidak ada mode tanpa label' },
            { name: 'modelValue', type: 'String · Number', default: "''", note: 'Mendukung v-model' },
            { name: 'type', type: 'String', default: "'text'", note: '' },
            { name: 'placeholder', type: 'String', default: "''", note: 'Bukan pengganti label' },
            { name: 'hint', type: 'String', default: "''", note: 'Disembunyikan saat error terisi' },
            { name: 'error', type: 'String', default: "''", note: 'Isinya sekaligus memicu gaya galat' },
            { name: 'required', type: 'Boolean', default: 'false', note: '' },
            { name: 'disabled', type: 'Boolean', default: 'false', note: '' },
        ],
        events: [{ name: 'update:modelValue', payload: 'String', note: '' }],
        slots: [],
        replaces: [{ from: '.form-group > .form-control (Bootstrap)', to: '<HcisField>' }],
        rules: {
            do: ['Selalu isi label. Placeholder hilang begitu orang mengetik.'],
            dont: ['Jangan memakai placeholder sebagai pengganti label.'],
        },
    },
    {
        slug: 'empty-state',
        name: 'HcisEmptyState',
        kind: 'owned',
        status: 'ready',
        group: 'Umpan balik',
        usedIn: 160,
        summary: 'Yang dilihat orang saat daftar kosong: apa yang tidak ada, kenapa, dan jalan keluarnya.',
        props: [
            { name: 'icon', type: 'String', default: "'○'", note: 'Satu karakter, dirender aria-hidden' },
            { name: 'title', type: 'String', default: 'wajib', note: 'Sebutkan apa yang tidak ada' },
            { name: 'description', type: 'String', default: "''", note: 'Satu sampai dua kalimat' },
        ],
        events: [],
        slots: [{ name: 'actions', note: 'Baris hanya dirender bila diisi' }],
        replaces: [{ from: 'teks "Tidak ada data" polos di dalam tabel', to: '<HcisEmptyState>' }],
        rules: {
            do: ['Bedakan belum-ada, tidak-ketemu, sudah-beres, dan gagal-muat.'],
            dont: ['Jangan menulis judul "Data kosong" — sebutkan apa yang tidak ada.'],
        },
    },
    {
        slug: 'skeleton',
        name: 'HcisSkeleton',
        kind: 'owned',
        status: 'draft',
        group: 'Umpan balik',
        usedIn: 247,
        summary: 'Balok abu pengganti konten yang sedang dimuat, seukuran konten aslinya.',
        props: [
            { name: 'width', type: 'String', default: "'100%'", note: '' },
            { name: 'height', type: 'String', default: "'1rem'", note: '' },
            { name: 'rounded', type: 'String', default: "'sm'", note: 'sm · md · pill' },
            { name: 'lines', type: 'Number', default: '1', note: 'Bila >1, merender beberapa baris' },
        ],
        events: [],
        slots: [],
        replaces: [{ from: 'spinner di tengah kartu kosong', to: '<HcisSkeleton>' }],
        rules: {
            do: ['Buat ukurannya menyerupai konten asli agar tata letak tidak melompat.'],
            dont: ['Jangan memakai skeleton untuk tunggu yang lebih pendek dari ~300ms — kedipannya justru mengganggu.'],
        },
    },
    {
        slug: 'table',
        name: 'HcisTable',
        kind: 'owned',
        status: 'draft',
        group: 'Data',
        usedIn: 87,
        summary: 'Tabel presentasional ringan untuk data yang sudah siap tampil. Bukan grid data.',
        props: [
            { name: 'columns', type: 'Array', default: 'wajib', note: '[{ key, label, align?, width? }]' },
            { name: 'rows', type: 'Array', default: '[]', note: '' },
            { name: 'rowKey', type: 'String', default: "'id'", note: '' },
            { name: 'loading', type: 'Boolean', default: 'false', note: 'Merender baris skeleton' },
            { name: 'dense', type: 'Boolean', default: 'false', note: 'Padding baris lebih rapat' },
        ],
        events: [],
        slots: [
            { name: 'cell:<key>', note: 'Mengganti isi sel kolom tertentu' },
            { name: 'empty', note: 'Ditampilkan saat rows kosong; bawaannya HcisEmptyState' },
        ],
        replaces: [{ from: '<table class="table table-striped"> (Bootstrap)', to: '<HcisTable>' }],
        rules: {
            do: ['Pakai untuk data yang sudah dipaginasi di server.'],
            dont: [
                'Jangan memakai HcisTable bila butuh sortir, seleksi baris, atau kolom beku — pakai <el-table>.',
                'Jangan menaruh lebih dari tiga aksi per baris.',
            ],
        },
    },
    {
        slug: 'tabs',
        name: 'HcisTabs',
        kind: 'owned',
        status: 'draft',
        group: 'Navigasi',
        usedIn: 6,
        summary: 'Tab untuk berpindah bagian di dalam satu halaman, tanpa memuat ulang.',
        props: [
            { name: 'modelValue', type: 'String', default: 'wajib', note: 'key tab aktif' },
            { name: 'tabs', type: 'Array', default: 'wajib', note: '[{ key, label, badge? }]' },
        ],
        events: [{ name: 'update:modelValue', payload: 'String', note: '' }],
        slots: [{ name: 'default', note: 'Isi tab aktif' }],
        replaces: [{ from: '.nav.nav-tabs (Bootstrap)', to: '<HcisTabs>' }],
        rules: {
            do: ['Pakai bila bagiannya setara dan jumlahnya tetap.'],
            dont: ['Jangan memakai tab untuk alur berurutan — itu wizard, bukan tab.'],
        },
    },
    {
        slug: 'breadcrumb',
        name: 'HcisBreadcrumb',
        kind: 'owned',
        status: 'draft',
        group: 'Navigasi',
        usedIn: 11,
        summary: 'Jejak lokasi halaman di dalam hierarki modul.',
        props: [{ name: 'items', type: 'Array', default: 'wajib', note: '[{ label, href? }] — item terakhir tanpa href' }],
        events: [],
        slots: [],
        replaces: [{ from: '.breadcrumb (Bootstrap)', to: '<HcisBreadcrumb>' }],
        rules: {
            do: ['Biarkan item terakhir tanpa href — itu halaman saat ini.'],
            dont: ['Jangan memakai remah roti untuk alur yang tidak berhierarki.'],
        },
    },

    // ---------- borrowed: milik Element Plus ----------
    {
        slug: 'el-select',
        name: 'el-select',
        kind: 'borrowed',
        status: 'draft',
        group: 'Formulir',
        usedIn: 54,
        summary: 'Pilihan tunggal maupun ganda, dengan pencarian. Didandani lewat jembatan token.',
        allowed: ['filterable', 'multiple', 'clearable', 'loading', 'remote', 'disabled', 'placeholder'],
        forbidden: [
            { what: 'size="large"', why: 'Tidak ada padanannya di skala ukuran kita; hanya default dan small yang dipetakan.' },
            { what: 'menimpa .el-select__wrapper di berkas halaman', why: 'Gaya Element hanya boleh diubah lewat element-plus-bridge.css, agar tidak divergen antar halaman.' },
        ],
        replaces: [
            { from: '<select class="form-select"> (Bootstrap)', to: '<el-select>' },
            { from: '<select> polos', to: '<el-select>' },
        ],
        rules: {
            do: ['Sertakan placeholder yang menyebut apa yang dipilih.', 'Pakai filterable bila pilihannya lebih dari sepuluh.'],
            dont: ['Jangan membungkusnya jadi komponen Hcis* sendiri (keputusan K5).'],
        },
    },
    {
        slug: 'el-date-picker',
        name: 'el-date-picker',
        kind: 'borrowed',
        status: 'draft',
        group: 'Formulir',
        usedIn: 56,
        summary: 'Pemilih tanggal dan rentang tanggal.',
        allowed: ['type="date"', 'type="daterange"', 'type="month"', 'clearable', 'disabled-date'],
        forbidden: [
            { what: 'format tanggal selain DD/MM/YYYY untuk tampilan', why: 'Seluruh HCIS memakai urutan hari-bulan-tahun; format lain membingungkan pengguna.' },
            { what: 'type="datetimerange"', why: 'Belum ada kebutuhan nyata; tambahkan lewat keputusan tersendiri bila diperlukan.' },
        ],
        replaces: [{ from: '<input type="date">', to: '<el-date-picker>' }],
        rules: {
            do: ['Tetapkan value-format ISO untuk data, dan format DD/MM/YYYY untuk tampilan.'],
            dont: ['Jangan mengandalkan format bawaan Element.'],
        },
    },
    {
        slug: 'el-dialog',
        name: 'el-dialog',
        kind: 'borrowed',
        status: 'draft',
        group: 'Wadah',
        usedIn: 118,
        summary: 'Dialog dan modal. Aksinya memakai HcisButton, bukan tombol Element.',
        allowed: ['width', 'destroy-on-close', 'close-on-click-modal', 'align-center'],
        forbidden: [
            { what: '<el-button> di dalam footer dialog', why: 'Tombol adalah komponen owned; memakai tombol Element membuat dua bahasa tombol berdampingan.' },
            { what: 'dialog bersarang di dalam dialog', why: 'Tidak ada jalan keluar yang jelas bagi pengguna, dan fokus keyboard menjadi kacau.' },
        ],
        replaces: [{ from: '.modal.fade (Bootstrap)', to: '<el-dialog>' }],
        rules: {
            do: ['Pakai variant="danger" pada tombol konfirmasi di dalam dialog — hanya di sinilah merah solid dibenarkan.'],
            dont: ['Jangan memakai dialog untuk pesan yang bisa disampaikan lewat notifikasi.'],
        },
    },
    {
        slug: 'el-pagination',
        name: 'el-pagination',
        kind: 'borrowed',
        status: 'draft',
        group: 'Data',
        usedIn: 124,
        summary: 'Navigasi halaman untuk daftar panjang.',
        allowed: ['layout', 'page-sizes', 'total', 'background'],
        forbidden: [
            { what: 'paginasi di sisi klien untuk data lebih dari 200 baris', why: 'Seluruh data harus diunduh dulu; ini penyebab halaman berat yang sudah tercatat di audit CPU.' },
        ],
        replaces: [{ from: '.pagination (Bootstrap)', to: '<el-pagination>' }],
        rules: {
            do: ['Paginasi di server.', 'Tampilkan total agar orang tahu sisa datanya.'],
            dont: ['Jangan menyembunyikan paginasi saat hanya ada satu halaman — posisinya jadi berpindah-pindah.'],
        },
    },
    {
        slug: 'el-upload',
        name: 'el-upload',
        kind: 'borrowed',
        status: 'draft',
        group: 'Formulir',
        usedIn: 25,
        summary: 'Unggah berkas, tunggal maupun jamak.',
        allowed: ['drag', 'accept', 'limit', 'auto-upload', 'before-upload'],
        forbidden: [
            { what: 'unggah tanpa menetapkan accept', why: 'Pengguna baru tahu jenis berkasnya salah setelah unggahan gagal di server.' },
        ],
        replaces: [{ from: '<input type="file"> polos', to: '<el-upload>' }],
        rules: {
            do: ['Sebutkan batas ukuran dan jenis berkas di dekat kontrolnya.'],
            dont: ['Jangan mengandalkan pesan galat server untuk validasi jenis berkas.'],
        },
    },
    {
        slug: 'el-tooltip',
        name: 'el-tooltip',
        kind: 'borrowed',
        status: 'draft',
        group: 'Umpan balik',
        usedIn: 25,
        summary: 'Keterangan singkat yang muncul saat kursor diarahkan.',
        allowed: ['content', 'placement', 'effect="dark"'],
        forbidden: [
            { what: 'menaruh informasi penting hanya di dalam tooltip', why: 'Tidak muncul pada perangkat sentuh dan sulit dijangkau pembaca layar.' },
        ],
        replaces: [{ from: 'atribut title= polos', to: '<el-tooltip>' }],
        rules: {
            do: ['Pakai untuk penjelasan tambahan yang boleh terlewat.'],
            dont: ['Jangan memakai tooltip untuk pesan galat.'],
        },
    },
    {
        slug: 'el-message',
        name: 'ElMessage',
        kind: 'borrowed',
        status: 'draft',
        group: 'Umpan balik',
        usedIn: 146,
        summary: 'Notifikasi sekilas untuk hasil aksi. Dipanggil sebagai fungsi, bukan komponen.',
        allowed: ["type: 'success' | 'warning' | 'error' | 'info'", 'duration', 'showClose'],
        forbidden: [
            { what: 'SweetAlert (Swal) dan alert() bawaan', why: 'Tiga mekanisme notifikasi berdampingan membuat perilaku aplikasi tidak dapat diramalkan.' },
            { what: 'duration: 0 tanpa showClose', why: 'Notifikasi menetap selamanya tanpa cara menutupnya.' },
        ],
        replaces: [
            { from: 'Swal.fire(...)', to: 'ElMessage(...)' },
            { from: 'alert(...)', to: 'ElMessage(...)' },
        ],
        rules: {
            do: ['Pakai untuk hasil aksi yang berhasil.'],
            dont: ['Jangan memakai notifikasi untuk galat yang menuntut keputusan — itu dialog.'],
        },
    },
];

export function getEntry(slug) {
    return registry.find((entry) => entry.slug === slug);
}
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `npm run test:unit -- registry`
Expected: PASS, 4 tes

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/ui/registry.js resources/js/components/ui/registry.test.js
git commit -m "feat(ds): tambah registry deklarasi design system"
```

---

## Task 2: Lima komponen baru

**Files:**
- Create: `resources/js/components/ui/HcisStatCard.vue`, `HcisSkeleton.vue`, `HcisTable.vue`, `HcisTabs.vue`, `HcisBreadcrumb.vue`
- Modify: `resources/js/components/ui/index.js`
- Modify: `resources/css/design-system.css` (tambah kelas `.hcis-stat`, `.hcis-skeleton`, `.hcis-tabs`, `.hcis-breadcrumb`, dan `.hcis-btn--md`, `.hcis-btn--danger-quiet`, `.hcis-btn--icon`)
- Test: `resources/js/components/ui/HcisNewComponents.test.js`

**Interfaces:**
- Consumes: token `--hcis-*` dari `design-system.css`; `HcisEmptyState` (dipakai sebagai isi bawaan slot `empty` pada `HcisTable`); `HcisSkeleton` (dipakai `HcisStatCard` dan `HcisTable` saat `loading`).
- Produces: lima komponen di atas, diekspor dari `resources/js/components/ui/index.js`.

- [ ] **Step 1: Tulis tes yang gagal**

```js
// resources/js/components/ui/HcisNewComponents.test.js
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisStatCard from './HcisStatCard.vue';
import HcisSkeleton from './HcisSkeleton.vue';
import HcisTable from './HcisTable.vue';
import HcisTabs from './HcisTabs.vue';
import HcisBreadcrumb from './HcisBreadcrumb.vue';

describe('HcisStatCard', () => {
    it('memisahkan arah tren dari warnanya', () => {
        const wrapper = mount(HcisStatCard, {
            props: { label: 'Keterlambatan', value: 12, delta: '12%', trend: 'up', sentiment: 'bad' },
        });

        expect(wrapper.find('.hcis-stat__delta').classes()).toContain('hcis-stat__delta--bad');
        expect(wrapper.find('.hcis-stat__delta').text()).toContain('▲');
        expect(wrapper.find('.hcis-stat__value').text()).toBe('12');
    });

    it('menyembunyikan angka dan menampilkan skeleton saat loading, label tetap ada', () => {
        const wrapper = mount(HcisStatCard, { props: { label: 'Menunggu', value: 5, loading: true } });

        expect(wrapper.find('.hcis-stat__label').text()).toBe('Menunggu');
        expect(wrapper.find('.hcis-stat__value').exists()).toBe(false);
        expect(wrapper.findComponent(HcisSkeleton).exists()).toBe(true);
    });
});

describe('HcisSkeleton', () => {
    it('merender beberapa baris sesuai prop lines', () => {
        const wrapper = mount(HcisSkeleton, { props: { lines: 3 } });
        expect(wrapper.findAll('.hcis-skeleton__line')).toHaveLength(3);
    });
});

describe('HcisTable', () => {
    const columns = [{ key: 'nama', label: 'Nama' }, { key: 'status', label: 'Status' }];

    it('merender kolom dan baris', () => {
        const rows = [{ id: 1, nama: 'Anggota A', status: 'Aktif' }];
        const wrapper = mount(HcisTable, { props: { columns, rows } });

        expect(wrapper.findAll('th')).toHaveLength(2);
        expect(wrapper.text()).toContain('Anggota A');
    });

    it('menampilkan keadaan kosong saat rows kosong', () => {
        const wrapper = mount(HcisTable, { props: { columns, rows: [] } });
        expect(wrapper.find('.hcis-empty-state').exists()).toBe(true);
    });

    it('merender skeleton saat loading', () => {
        const wrapper = mount(HcisTable, { props: { columns, rows: [], loading: true } });
        expect(wrapper.find('.hcis-skeleton').exists()).toBe(true);
        expect(wrapper.find('.hcis-empty-state').exists()).toBe(false);
    });
});

describe('HcisTabs', () => {
    it('menandai tab aktif dan memancarkan perubahan', async () => {
        const tabs = [{ key: 'a', label: 'Antrian' }, { key: 'b', label: 'Riwayat' }];
        const wrapper = mount(HcisTabs, { props: { modelValue: 'a', tabs } });

        expect(wrapper.findAll('.hcis-tabs__tab')[0].classes()).toContain('is-active');

        await wrapper.findAll('.hcis-tabs__tab')[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([['b']]);
    });
});

describe('HcisBreadcrumb', () => {
    it('merender item terakhir tanpa tautan dan menandainya sebagai halaman saat ini', () => {
        const wrapper = mount(HcisBreadcrumb, {
            props: { items: [{ label: 'Beranda', href: '/' }, { label: 'SOP' }] },
        });

        expect(wrapper.findAll('a')).toHaveLength(1);
        expect(wrapper.find('[aria-current="page"]').text()).toBe('SOP');
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npm run test:unit -- HcisNewComponents`
Expected: FAIL — modul `./HcisStatCard.vue` tidak ditemukan

- [ ] **Step 3: Tambah kelas ke `design-system.css`**

Sisipkan sebelum `@keyframes hcis-spin` (baris ~356). Perhatikan `.hcis-btn--md` — kelas ini dihasilkan `HcisButton.vue` tapi selama ini tidak punya aturan, sehingga ukuran salah ketik jatuh diam-diam ke bawaan.

```css
/* Ukuran bawaan tombol, dinyatakan eksplisit agar kelas yang dihasilkan komponen tidak kosong. */
.hcis-btn--md { min-height: 2.5rem; padding: 0 var(--hcis-space-4); font-size: var(--hcis-font-size-sm); }
.hcis-btn--danger-quiet { border-color: var(--hcis-danger-500); background: var(--hcis-surface); color: var(--hcis-danger-700); }
.hcis-btn--danger-quiet:hover:not(:disabled) { background: var(--hcis-danger-50); }
.hcis-btn--icon { width: 2.5rem; padding: 0; }
.hcis-btn--icon.hcis-btn--sm { width: 2rem; }

.hcis-stat { border: 1px solid var(--hcis-border); border-radius: var(--hcis-radius-xl); background: var(--hcis-surface); box-shadow: var(--hcis-shadow-sm); padding: var(--hcis-space-5); }
.hcis-stat__label { margin: 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
.hcis-stat__value { margin: var(--hcis-space-2) 0 0; color: var(--hcis-text-strong); font-size: 1.75rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
.hcis-stat__delta { display: inline-flex; align-items: center; gap: var(--hcis-space-1); margin-top: var(--hcis-space-2); font-size: var(--hcis-font-size-xs); font-weight: 800; }
.hcis-stat__delta--good { color: var(--hcis-success-700); }
.hcis-stat__delta--bad { color: var(--hcis-danger-700); }
.hcis-stat__delta--neutral { color: var(--hcis-text-muted); }
.hcis-stat__caption { margin: var(--hcis-space-1) 0 0; color: var(--hcis-text-subtle); font-size: var(--hcis-font-size-xs); }

.hcis-skeleton { display: flex; flex-direction: column; gap: var(--hcis-space-2); }
.hcis-skeleton__line { background: linear-gradient(90deg, var(--hcis-slate-100), var(--hcis-slate-200), var(--hcis-slate-100)); background-size: 200% 100%; animation: hcis-shimmer 1600ms linear infinite; }
.hcis-skeleton__line--sm { border-radius: var(--hcis-radius-sm); }
.hcis-skeleton__line--md { border-radius: var(--hcis-radius-md); }
.hcis-skeleton__line--pill { border-radius: var(--hcis-radius-pill); }

.hcis-table--dense th, .hcis-table--dense td { padding: var(--hcis-space-2) var(--hcis-space-3); }

.hcis-tabs { display: flex; gap: var(--hcis-space-1); border-bottom: 1px solid var(--hcis-border); }
.hcis-tabs__tab { display: inline-flex; align-items: center; gap: var(--hcis-space-2); border: 0; border-bottom: 2px solid transparent; padding: var(--hcis-space-3) var(--hcis-space-4); background: transparent; color: var(--hcis-text-muted); cursor: pointer; font: inherit; font-size: var(--hcis-font-size-sm); font-weight: 800; }
.hcis-tabs__tab:hover { color: var(--hcis-brand-700); }
.hcis-tabs__tab.is-active { border-bottom-color: var(--hcis-brand-600); color: var(--hcis-brand-700); }

.hcis-breadcrumb { display: flex; align-items: center; gap: var(--hcis-space-2); flex-wrap: wrap; font-size: var(--hcis-font-size-xs); }
.hcis-breadcrumb a { color: var(--hcis-text-muted); text-decoration: none; font-weight: 700; }
.hcis-breadcrumb a:hover { color: var(--hcis-brand-700); }
.hcis-breadcrumb__sep { color: var(--hcis-text-subtle); }
.hcis-breadcrumb__current { color: var(--hcis-text-strong); font-weight: 800; }

@keyframes hcis-shimmer { to { background-position: -200% 0; } }
```

Tambahkan juga pengecualian reduced-motion untuk shimmer, tepat di dalam blok `@media (prefers-reduced-motion: reduce)` yang sudah ada:

```css
    .hcis-skeleton__line { animation: none !important; background: var(--hcis-slate-200) !important; }
```

- [ ] **Step 4: Tulis kelima komponen**

```vue
<!-- resources/js/components/ui/HcisSkeleton.vue -->
<template>
    <div class="hcis-skeleton" aria-hidden="true">
        <div
            v-for="line in lines"
            :key="line"
            class="hcis-skeleton__line"
            :class="`hcis-skeleton__line--${rounded}`"
            :style="{ width: line === lines && lines > 1 ? '70%' : width, height }"
        ></div>
    </div>
</template>

<script setup>
defineOptions({ name: 'HcisSkeleton' });

defineProps({
    width: { type: String, default: '100%' },
    height: { type: String, default: '1rem' },
    rounded: { type: String, default: 'sm' },
    lines: { type: Number, default: 1 },
});
</script>
```

```vue
<!-- resources/js/components/ui/HcisStatCard.vue -->
<template>
    <div class="hcis-stat">
        <p class="hcis-stat__label">{{ label }}</p>

        <HcisSkeleton v-if="loading" width="60%" height="1.75rem" rounded="sm" />
        <template v-else>
            <p class="hcis-stat__value">{{ value }}</p>
            <span v-if="delta" class="hcis-stat__delta" :class="`hcis-stat__delta--${sentiment}`">
                <span aria-hidden="true">{{ trend === 'down' ? '▼' : '▲' }}</span>
                {{ delta }}
            </span>
            <p v-if="caption" class="hcis-stat__caption">{{ caption }}</p>
            <slot />
        </template>
    </div>
</template>

<script setup>
import HcisSkeleton from './HcisSkeleton.vue';

defineOptions({ name: 'HcisStatCard' });

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
    delta: { type: String, default: '' },
    trend: { type: String, default: '' },
    sentiment: { type: String, default: 'neutral' },
    caption: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});
</script>
```

```vue
<!-- resources/js/components/ui/HcisTable.vue -->
<template>
    <div class="hcis-table-wrap">
        <table class="hcis-table" :class="{ 'hcis-table--dense': dense }">
            <thead>
                <tr>
                    <th v-for="col in columns" :key="col.key" :style="{ textAlign: col.align || 'left', width: col.width }">
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody v-if="loading">
                <tr v-for="n in 3" :key="n">
                    <td v-for="col in columns" :key="col.key"><HcisSkeleton height="0.875rem" /></td>
                </tr>
            </tbody>
            <tbody v-else-if="rows.length">
                <tr v-for="row in rows" :key="row[rowKey]">
                    <td v-for="col in columns" :key="col.key" :style="{ textAlign: col.align || 'left' }">
                        <slot :name="`cell:${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="!loading && !rows.length">
            <slot name="empty">
                <HcisEmptyState title="Belum ada data" description="Data akan muncul di sini setelah tersedia." />
            </slot>
        </div>
    </div>
</template>

<script setup>
import HcisSkeleton from './HcisSkeleton.vue';
import HcisEmptyState from './HcisEmptyState.vue';

defineOptions({ name: 'HcisTable' });

defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    loading: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
});
</script>
```

```vue
<!-- resources/js/components/ui/HcisTabs.vue -->
<template>
    <div>
        <div class="hcis-tabs" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                class="hcis-tabs__tab"
                :class="{ 'is-active': tab.key === modelValue }"
                :aria-selected="tab.key === modelValue"
                @click="$emit('update:modelValue', tab.key)"
            >
                {{ tab.label }}
                <HcisBadge v-if="tab.badge" :label="String(tab.badge)" variant="neutral" size="sm" />
            </button>
        </div>
        <slot />
    </div>
</template>

<script setup>
import HcisBadge from './HcisBadge.vue';

defineOptions({ name: 'HcisTabs' });

defineProps({
    modelValue: { type: String, required: true },
    tabs: { type: Array, required: true },
});

defineEmits(['update:modelValue']);
</script>
```

```vue
<!-- resources/js/components/ui/HcisBreadcrumb.vue -->
<template>
    <nav class="hcis-breadcrumb" aria-label="Remah roti">
        <template v-for="(item, index) in items" :key="item.label">
            <a v-if="item.href && index < items.length - 1" :href="item.href">{{ item.label }}</a>
            <span v-else class="hcis-breadcrumb__current" aria-current="page">{{ item.label }}</span>
            <span v-if="index < items.length - 1" class="hcis-breadcrumb__sep" aria-hidden="true">/</span>
        </template>
    </nav>
</template>

<script setup>
defineOptions({ name: 'HcisBreadcrumb' });

defineProps({ items: { type: Array, required: true } });
</script>
```

- [ ] **Step 5: Perbarui `index.js`**

```js
export { default as HcisBadge } from './HcisBadge.vue';
export { default as HcisBreadcrumb } from './HcisBreadcrumb.vue';
export { default as HcisButton } from './HcisButton.vue';
export { default as HcisCard } from './HcisCard.vue';
export { default as HcisEmptyState } from './HcisEmptyState.vue';
export { default as HcisField } from './HcisField.vue';
export { default as HcisSkeleton } from './HcisSkeleton.vue';
export { default as HcisStatCard } from './HcisStatCard.vue';
export { default as HcisTable } from './HcisTable.vue';
export { default as HcisTabs } from './HcisTabs.vue';
```

- [ ] **Step 6: Jalankan seluruh tes**

Run: `npm run test:unit`
Expected: PASS — 3 tes lama + 4 registry + 8 komponen baru

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/ui resources/css/design-system.css
git commit -m "feat(ds): tambah StatCard, Skeleton, Table, Tabs, Breadcrumb"
```

---

## Task 3: HcisButton dan HcisBadge menyusul deklarasinya

**Files:**
- Modify: `resources/js/components/ui/HcisButton.vue`
- Modify: `resources/js/components/ui/HcisBadge.vue`
- Test: `resources/js/components/ui/HcisComponents.test.js` (tambah kasus)

**Interfaces:**
- Consumes: kelas `.hcis-btn--danger-quiet`, `.hcis-btn--icon`, `.hcis-btn--md` dari Task 2.
- Produces: `HcisButton` menerima `href` dan `iconOnly`; `HcisBadge` menerima `size` dan `dot`.

Nilai yang dipasang di sini adalah **rekomendasi**, bukan keputusan final — status registri keduanya `draft` sampai disahkan saat review.

- [ ] **Step 1: Tambah tes yang gagal**

Sisipkan ke dalam `HcisComponents.test.js`:

```js
    it('merender <a> ketika href diisi', () => {
        const wrapper = mount(HcisButton, { props: { label: 'Buka', href: '/detail' } });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('href')).toBe('/detail');
    });

    it('menolak merender tombol ikon tanpa aria-label', () => {
        const wrapper = mount(HcisButton, { props: { iconOnly: true } });
        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('mendukung ukuran dan titik pada lencana', () => {
        const wrapper = mount(HcisBadge, { props: { label: 'Aktif', variant: 'success', size: 'sm', dot: true } });

        expect(wrapper.classes()).toContain('hcis-badge--sm');
        expect(wrapper.find('.hcis-badge__dot').exists()).toBe(true);
    });
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `npm run test:unit -- HcisComponents`
Expected: FAIL — 3 tes baru gagal

- [ ] **Step 3: Ubah `HcisButton.vue`**

```vue
<template>
    <component
        :is="tag"
        :type="href ? undefined : type"
        :href="href || undefined"
        class="hcis-btn"
        :class="buttonClasses"
        :disabled="href ? undefined : (disabled || loading)"
        v-bind="$attrs"
    >
        <span v-if="loading" class="hcis-btn__spinner" aria-hidden="true"></span>
        <slot v-else name="leading" />
        <span v-if="!iconOnly"><slot>{{ label }}</slot></span>
        <slot v-else />
        <slot v-if="!loading && !iconOnly" name="trailing" />
    </component>
</template>

<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ name: 'HcisButton', inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: '' },
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    href: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    iconOnly: { type: Boolean, default: false },
});

const attrs = useAttrs();

// Tombol khusus ikon tanpa nama tidak bisa dipakai pembaca layar.
// Ia sengaja tidak dirender sama sekali agar kesalahan ini terlihat saat pengembangan,
// bukan diam-diam lolos ke produksi.
const tag = computed(() => {
    if (props.iconOnly && !attrs['aria-label']) return 'template';
    return props.href ? 'a' : 'button';
});

const buttonClasses = computed(() => [
    `hcis-btn--${props.variant}`,
    `hcis-btn--${props.size}`,
    { 'hcis-btn--block': props.block, 'hcis-btn--icon': props.iconOnly },
]);
</script>
```

- [ ] **Step 4: Ubah `HcisBadge.vue`**

```vue
<template>
    <span class="hcis-badge" :class="[`hcis-badge--${variant}`, `hcis-badge--${size}`]">
        <span v-if="dot" class="hcis-badge__dot" aria-hidden="true"></span>
        <span class="hcis-badge__text"><slot>{{ label }}</slot></span>
    </span>
</template>

<script setup>
defineOptions({ name: 'HcisBadge' });

defineProps({
    label: { type: String, default: '' },
    variant: { type: String, default: 'neutral' },
    size: { type: String, default: 'md' },
    dot: { type: Boolean, default: false },
});
</script>
```

Tambahkan kelas pendukungnya ke `design-system.css`, tepat setelah blok `.hcis-badge--info`:

```css
.hcis-badge--md { min-height: 1.625rem; padding: 0 var(--hcis-space-2); font-size: var(--hcis-font-size-xs); }
.hcis-badge--sm { min-height: 1.25rem; padding: 0 6px; font-size: 0.68rem; }
.hcis-badge__dot { width: 0.5rem; height: 0.5rem; flex: none; border-radius: var(--hcis-radius-pill); background: currentColor; }
.hcis-badge__text { max-width: 9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hcis-badge--primary { border-color: var(--hcis-brand-300); }
.hcis-badge--success { border-color: #6ee7b7; }
.hcis-badge--warning { border-color: #fcd34d; }
.hcis-badge--danger { border-color: #fca5a5; }
.hcis-badge--info { border-color: #67e8f9; }
```

- [ ] **Step 5: Jalankan seluruh tes**

Run: `npm run test:unit`
Expected: PASS semua

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/ui resources/css/design-system.css
git commit -m "feat(ds): Button dukung href & iconOnly, Badge dukung size & dot"
```

---

## Task 4: Jembatan token Element Plus, berlingkup

**Files:**
- Create: `resources/css/element-plus-bridge.css`

**Interfaces:**
- Consumes: token `--hcis-*`.
- Produces: kelas induk `.ds-preview` yang memetakan variabel Element Plus. **Tidak diimpor di `app.js`** — hanya diimpor oleh `resources/js/Pages/DesignSystem/Component.vue`.

- [ ] **Step 1: Tulis berkasnya**

```css
/*
 * Jembatan token Element Plus.
 *
 * BERLINGKUP — seluruh isi berkas ini bersarang di bawah .ds-preview,
 * sehingga TIDAK ADA satu pun halaman aplikasi yang berubah rupa.
 * Mengaktifkannya secara global adalah keputusan tersendiri yang
 * memerlukan persetujuan pemilik repo; lihat spec §5.
 *
 * Element Plus memakai variabel CSS-nya sendiri (--el-*), jadi kita cukup
 * memetakannya ke token kita. Menimpa selektor .el-* satu per satu dilarang:
 * ia rapuh terhadap pembaruan versi dan cepat divergen antar halaman.
 */
.ds-preview {
    --el-color-primary: var(--hcis-brand-600);
    --el-color-primary-light-3: var(--hcis-brand-400);
    --el-color-primary-light-5: var(--hcis-brand-300);
    --el-color-primary-light-7: var(--hcis-brand-200);
    --el-color-primary-light-8: var(--hcis-brand-100);
    --el-color-primary-light-9: var(--hcis-brand-50);
    --el-color-primary-dark-2: var(--hcis-brand-700);

    --el-color-success: var(--hcis-success-500);
    --el-color-warning: var(--hcis-warning-500);
    --el-color-danger: var(--hcis-danger-500);
    --el-color-error: var(--hcis-danger-500);
    --el-color-info: var(--hcis-info-500);

    --el-text-color-primary: var(--hcis-text-strong);
    --el-text-color-regular: var(--hcis-text);
    --el-text-color-secondary: var(--hcis-text-muted);
    --el-text-color-placeholder: var(--hcis-text-subtle);
    --el-text-color-disabled: var(--hcis-text-subtle);

    --el-border-color: var(--hcis-border-strong);
    --el-border-color-light: var(--hcis-border);
    --el-border-color-lighter: var(--hcis-border);
    --el-border-color-hover: var(--hcis-brand-300);
    --el-border-radius-base: var(--hcis-radius-md);
    --el-border-radius-small: var(--hcis-radius-sm);
    --el-border-radius-round: var(--hcis-radius-pill);

    --el-bg-color: var(--hcis-surface);
    --el-bg-color-page: var(--hcis-surface-soft);
    --el-bg-color-overlay: var(--hcis-surface);
    --el-fill-color-light: var(--hcis-surface-soft);
    --el-fill-color-blank: var(--hcis-surface);
    --el-mask-color: rgba(15, 23, 42, 0.45);

    --el-font-family: var(--hcis-font-body);
    --el-font-size-base: var(--hcis-font-size-sm);
    --el-font-size-small: var(--hcis-font-size-xs);

    --el-component-size: 2.625rem;
    --el-component-size-small: 2rem;

    --el-box-shadow-light: var(--hcis-shadow-sm);
    --el-box-shadow: var(--hcis-shadow-md);
    --el-transition-duration: var(--hcis-duration-fast);
}

/*
 * Lapisan mengambang (dropdown, popper, dialog) dipasang Element di <body>,
 * di luar .ds-preview. Karena itu ia perlu penanda tersendiri: komponen yang
 * memakainya wajib menambahkan popper-class="ds-preview".
 */
.ds-preview.el-popper,
.ds-preview.el-select-dropdown {
    border-radius: var(--hcis-radius-md);
    box-shadow: var(--hcis-shadow-md);
}
```

- [ ] **Step 2: Verifikasi tidak ada selektor tingkat akar**

Run: `grep -nE "^[^.@/ ]|^\.(?!ds-preview)" resources/css/element-plus-bridge.css || echo "BERSIH"`
Expected: `BERSIH` — setiap selektor diawali `.ds-preview`

- [ ] **Step 3: Pastikan tidak diimpor global**

Run: `grep -n "element-plus-bridge" resources/js/app.js || echo "TIDAK DIIMPOR GLOBAL - BENAR"`
Expected: `TIDAK DIIMPOR GLOBAL - BENAR`

- [ ] **Step 4: Commit**

```bash
git add resources/css/element-plus-bridge.css
git commit -m "feat(ds): jembatan token Element Plus berlingkup .ds-preview"
```

---

## Task 5: Kerangka halaman `/design-system`

**Files:**
- Create: `resources/js/Pages/DesignSystem/Index.vue`, `Component.vue`, `partials/DsSection.vue`, `partials/DsApiTable.vue`, `partials/DsRules.vue`, `partials/DsMigration.vue`
- Modify: `routes/web.php:123-127`
- Delete: `resources/js/Pages/DesignSystem.vue`

**Interfaces:**
- Consumes: `registry`, `getEntry` dari Task 1; komponen `Hcis*`; `element-plus-bridge.css` dari Task 4.
- Produces: rute `design-system` dan `design-system.component`.

- [ ] **Step 1: Ganti rute**

Grup `Route::middleware(['auth'])->group(function () {` di baris 123 **berlanjut ke rute-rute lain di bawahnya** — jangan menyentuh baris pembuka maupun penutupnya. Yang diganti hanya blok rute di baris 124–126:

```php
    Route::get('/design-system', function () {
        return inertia('DesignSystem');
    })->name('design-system');
```

menjadi:

```php
    Route::get('/design-system', function () {
        return inertia('DesignSystem/Index');
    })->name('design-system');

    Route::get('/design-system/{slug}', function (string $slug) {
        return inertia('DesignSystem/Component', ['slug' => $slug]);
    })->where('slug', '[a-z0-9-]+')->name('design-system.component');
```

Verifikasi setelah menyunting: `php -l routes/web.php` harus melaporkan `No syntax errors detected`.

- [ ] **Step 2: Tulis partials**

```vue
<!-- resources/js/Pages/DesignSystem/partials/DsSection.vue -->
<template>
    <section class="ds-section">
        <header class="ds-section__head">
            <span class="ds-section__no">{{ no }}</span>
            <div>
                <h2 class="ds-section__title">{{ title }}</h2>
                <p v-if="description" class="ds-section__desc">{{ description }}</p>
            </div>
        </header>
        <div class="ds-section__body"><slot /></div>
    </section>
</template>

<script setup>
defineProps({
    no: { type: [String, Number], required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<style scoped>
.ds-section { border-top: 1px solid var(--hcis-border); padding-top: var(--hcis-space-6); margin-top: var(--hcis-space-8); }
.ds-section__head { display: flex; align-items: flex-start; gap: var(--hcis-space-3); margin-bottom: var(--hcis-space-4); }
.ds-section__no { display: grid; place-items: center; width: 1.5rem; height: 1.5rem; flex: none; border-radius: var(--hcis-radius-sm); background: var(--hcis-brand-600); color: #fff; font-size: var(--hcis-font-size-xs); font-weight: 800; }
.ds-section__title { margin: 0; color: var(--hcis-text-strong); font-size: var(--hcis-font-size-lg); font-weight: 800; }
.ds-section__desc { margin: var(--hcis-space-1) 0 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); line-height: var(--hcis-line-normal); }
</style>
```

```vue
<!-- resources/js/Pages/DesignSystem/partials/DsApiTable.vue -->
<template>
    <div v-if="props.length" class="ds-api">
        <h3 class="ds-api__label">Props</h3>
        <div class="hcis-table-wrap">
            <table class="hcis-table">
                <thead><tr><th>Nama</th><th>Tipe</th><th>Bawaan</th><th>Catatan</th></tr></thead>
                <tbody>
                    <tr v-for="p in props" :key="p.name">
                        <td><code>{{ p.name }}</code></td><td><code>{{ p.type }}</code></td>
                        <td><code>{{ p.default }}</code></td><td>{{ p.note }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="events.length" class="ds-api">
        <h3 class="ds-api__label">Events</h3>
        <ul class="ds-api__list"><li v-for="e in events" :key="e.name"><code>{{ e.name }}</code> — {{ e.note || e.payload }}</li></ul>
    </div>

    <div v-if="slots.length" class="ds-api">
        <h3 class="ds-api__label">Slots</h3>
        <ul class="ds-api__list"><li v-for="s in slots" :key="s.name"><code>{{ s.name }}</code> — {{ s.note }}</li></ul>
    </div>

    <div v-if="allowed.length" class="ds-api">
        <h3 class="ds-api__label">Prop yang direstui</h3>
        <ul class="ds-api__list"><li v-for="a in allowed" :key="a"><code>{{ a }}</code></li></ul>
    </div>

    <div v-if="forbidden.length" class="ds-api">
        <h3 class="ds-api__label">Dilarang</h3>
        <ul class="ds-api__list ds-api__list--dont">
            <li v-for="f in forbidden" :key="f.what"><code>{{ f.what }}</code> — {{ f.why }}</li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    props: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    slots: { type: Array, default: () => [] },
    allowed: { type: Array, default: () => [] },
    forbidden: { type: Array, default: () => [] },
});
</script>

<style scoped>
.ds-api { margin-bottom: var(--hcis-space-5); }
.ds-api__label { margin: 0 0 var(--hcis-space-2); color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
.ds-api__list { margin: 0; padding-left: var(--hcis-space-5); color: var(--hcis-text); font-size: var(--hcis-font-size-sm); line-height: var(--hcis-line-relaxed); }
.ds-api__list--dont { color: var(--hcis-danger-700); }
code { background: var(--hcis-surface-soft); border-radius: 4px; padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.8em; }
</style>
```

```vue
<!-- resources/js/Pages/DesignSystem/partials/DsRules.vue -->
<template>
    <div class="ds-rules">
        <div class="ds-rules__col ds-rules__col--do">
            <h3>Lakukan</h3>
            <ul><li v-for="r in doList" :key="r">{{ r }}</li></ul>
        </div>
        <div class="ds-rules__col ds-rules__col--dont">
            <h3>Jangan</h3>
            <ul><li v-for="r in dontList" :key="r">{{ r }}</li></ul>
        </div>
    </div>
</template>

<script setup>
defineProps({
    doList: { type: Array, default: () => [] },
    dontList: { type: Array, default: () => [] },
});
</script>

<style scoped>
.ds-rules { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: var(--hcis-space-4); }
.ds-rules__col { border-radius: var(--hcis-radius-md); padding: var(--hcis-space-4); }
.ds-rules__col--do { background: var(--hcis-success-50); }
.ds-rules__col--dont { background: var(--hcis-danger-50); }
.ds-rules__col h3 { margin: 0 0 var(--hcis-space-2); font-size: var(--hcis-font-size-xs); font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
.ds-rules__col--do h3 { color: var(--hcis-success-700); }
.ds-rules__col--dont h3 { color: var(--hcis-danger-700); }
.ds-rules__col ul { margin: 0; padding-left: var(--hcis-space-5); font-size: var(--hcis-font-size-sm); line-height: var(--hcis-line-relaxed); color: var(--hcis-text); }
</style>
```

```vue
<!-- resources/js/Pages/DesignSystem/partials/DsMigration.vue -->
<template>
    <div class="hcis-table-wrap">
        <table class="hcis-table">
            <thead><tr><th>Pola lama</th><th>Diganti dengan</th></tr></thead>
            <tbody>
                <tr v-for="m in items" :key="m.from">
                    <td><code>{{ m.from }}</code></td>
                    <td><code>{{ m.to }}</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
defineProps({ items: { type: Array, default: () => [] } });
</script>

<style scoped>
code { background: var(--hcis-surface-soft); border-radius: 4px; padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.8em; }
</style>
```

- [ ] **Step 3: Tulis beranda inventaris**

```vue
<!-- resources/js/Pages/DesignSystem/Index.vue -->
<template>
    <Head title="HCIS Design System" />

    <div class="hcis-page hcis-stack hcis-stack--lg">
        <header class="hcis-stack hcis-stack--sm">
            <p class="hcis-eyebrow">HCIS Design System</p>
            <h1 class="hcis-title">Satu bahasa visual untuk seluruh HCIS.</h1>
            <p class="hcis-subtitle">
                Setiap komponen dideklarasikan di satu tempat: aturan pakai, larangan, dan pola lama yang digantikannya.
                Halaman ini merender langsung dari <code>registry.js</code> — tidak ada salinan kedua yang bisa tertinggal.
            </p>
        </header>

        <section class="hcis-grid hcis-grid--4">
            <HcisStatCard label="Komponen milik kita" :value="ownedCount" caption="markup, perilaku, gaya" />
            <HcisStatCard label="Komponen pinjaman" :value="borrowedCount" caption="Element Plus, hanya gaya" />
            <HcisStatCard label="Siap dipakai" :value="readyCount" sentiment="good" />
            <HcisStatCard label="Menunggu pengesahan" :value="draftCount" sentiment="neutral" />
        </section>

        <HcisCard title="Inventaris" description="Status setiap komponen. Yang belum ada pun tercantum, agar tidak ada yang membuat versi lokal karena mengira belum tersedia.">
            <HcisTable :columns="columns" :rows="registry" row-key="slug">
                <template #cell:name="{ row }">
                    <Link :href="`/design-system/${row.slug}`" class="ds-link">{{ row.name }}</Link>
                </template>
                <template #cell:kind="{ value }">
                    <HcisBadge :label="value === 'owned' ? 'milik kita' : 'pinjaman'" :variant="value === 'owned' ? 'primary' : 'neutral'" size="sm" />
                </template>
                <template #cell:status="{ value }">
                    <HcisBadge :label="statusLabel[value]" :variant="statusVariant[value]" size="sm" dot />
                </template>
                <template #cell:usedIn="{ value }">
                    <span class="ds-muted">{{ value ? `${value} berkas` : '—' }}</span>
                </template>
            </HcisTable>
        </HcisCard>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { HcisBadge, HcisCard, HcisStatCard, HcisTable } from '../../components/ui';
import { registry } from '../../components/ui/registry';

const columns = [
    { key: 'name', label: 'Komponen' },
    { key: 'group', label: 'Rumpun' },
    { key: 'kind', label: 'Jenis' },
    { key: 'status', label: 'Status' },
    { key: 'usedIn', label: 'Dipakai di' },
];

const statusLabel = { ready: 'Siap', draft: 'Draf', planned: 'Rencana', deprecated: 'Pensiun' };
const statusVariant = { ready: 'success', draft: 'warning', planned: 'neutral', deprecated: 'danger' };

const ownedCount = computed(() => registry.filter((e) => e.kind === 'owned').length);
const borrowedCount = computed(() => registry.filter((e) => e.kind === 'borrowed').length);
const readyCount = computed(() => registry.filter((e) => e.status === 'ready').length);
const draftCount = computed(() => registry.filter((e) => e.status === 'draft').length);
</script>

<style scoped>
.ds-link { color: var(--hcis-brand-700); font-weight: 800; text-decoration: none; }
.ds-link:hover { text-decoration: underline; }
.ds-muted { color: var(--hcis-text-muted); }
code { background: var(--hcis-surface-soft); border-radius: 4px; padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.85em; }
</style>
```

- [ ] **Step 4: Tulis halaman komponen generik**

```vue
<!-- resources/js/Pages/DesignSystem/Component.vue -->
<template>
    <Head :title="`${entry?.name ?? 'Tidak ditemukan'} — Design System`" />

    <div v-if="!entry" class="hcis-page">
        <HcisEmptyState icon="?" title="Komponen tidak ditemukan" :description="`Tidak ada entri dengan slug &quot;${slug}&quot; di registry.`">
            <template #actions><HcisButton label="Kembali ke inventaris" variant="secondary" href="/design-system" /></template>
        </HcisEmptyState>
    </div>

    <div v-else class="hcis-page hcis-stack ds-preview">
        <HcisBreadcrumb :items="[{ label: 'Design System', href: '/design-system' }, { label: entry.name }]" />

        <DsSection no="1" title="Identitas">
            <div class="hcis-row" style="gap: var(--hcis-space-3)">
                <h1 class="hcis-title">{{ entry.name }}</h1>
                <HcisBadge :label="statusLabel[entry.status]" :variant="statusVariant[entry.status]" dot />
                <HcisBadge :label="entry.kind === 'owned' ? 'milik kita' : 'pinjaman — Element Plus'" variant="neutral" />
            </div>
            <p class="hcis-subtitle">{{ entry.summary }}</p>
            <p v-if="entry.usedIn" class="ds-note">Pola ini muncul di <strong>{{ entry.usedIn }} berkas</strong> pada kode yang ada sekarang.</p>
        </DsSection>

        <DsSection no="2" title="Anatomi" description="Dirender dengan komponen aslinya — bila komponennya rusak, blok ini ikut rusak.">
            <component :is="demo" section="anatomy" />
        </DsSection>

        <DsSection no="3" title="Varian">
            <component :is="demo" section="variants" />
        </DsSection>

        <DsSection no="4" title="State">
            <component :is="demo" section="states" />
        </DsSection>

        <DsSection no="5" title="API">
            <DsApiTable
                :props="entry.props || []" :events="entry.events || []" :slots="entry.slots || []"
                :allowed="entry.allowed || []" :forbidden="entry.forbidden || []"
            />
        </DsSection>

        <DsSection no="6" title="Aturan pakai" description="Ini yang menutup ruang tafsir. Agent membaca daftar yang sama dari registry.js.">
            <DsRules :do-list="entry.rules.do" :dont-list="entry.rules.dont" />
        </DsSection>

        <DsSection no="7" title="Pola migrasi" description="Pola lama yang digantikan komponen ini.">
            <DsMigration :items="entry.replaces" />
        </DsSection>
    </div>
</template>

<script setup>
import { computed, defineAsyncComponent } from 'vue';
import { Head } from '@inertiajs/vue3';
import { HcisBadge, HcisBreadcrumb, HcisButton, HcisEmptyState } from '../../components/ui';
import { getEntry } from '../../components/ui/registry';
import DsSection from './partials/DsSection.vue';
import DsApiTable from './partials/DsApiTable.vue';
import DsRules from './partials/DsRules.vue';
import DsMigration from './partials/DsMigration.vue';
import '../../../css/element-plus-bridge.css';

const props = defineProps({ slug: { type: String, required: true } });

const statusLabel = { ready: 'Siap', draft: 'Draf', planned: 'Rencana', deprecated: 'Pensiun' };
const statusVariant = { ready: 'success', draft: 'warning', planned: 'neutral', deprecated: 'danger' };

const entry = computed(() => getEntry(props.slug));

const demos = import.meta.glob('./demos/*.vue');
const demo = computed(() => {
    const path = `./demos/${props.slug}.vue`;
    return demos[path] ? defineAsyncComponent(demos[path]) : null;
});
</script>

<style scoped>
.ds-note { margin: var(--hcis-space-2) 0 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
</style>
```

- [ ] **Step 5: Hapus halaman lama**

```bash
git rm resources/js/Pages/DesignSystem.vue
```

- [ ] **Step 6: Verifikasi build**

Run: `npm run build`
Expected: sukses, tanpa galat resolusi impor

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/DesignSystem routes/web.php
git commit -m "feat(ds): kerangka /design-system dengan inventaris & halaman per komponen"
```

---

## Task 6: Berkas demo untuk 17 entri

**Files:**
- Create: `resources/js/Pages/DesignSystem/demos/{slug}.vue` — satu per entri registry

**Interfaces:**
- Consumes: komponen `Hcis*`, komponen Element Plus, prop `section` (`'anatomy' | 'variants' | 'states'`).
- Produces: satu komponen demo per slug; dipetakan otomatis lewat `import.meta.glob` di `Component.vue`.

Setiap berkas demo memakai bentuk yang sama:

```vue
<template>
    <div v-if="section === 'anatomy'"><!-- satu contoh utuh, bagian diberi label --></div>
    <div v-else-if="section === 'variants'"><!-- matriks varian --></div>
    <div v-else-if="section === 'states'"><!-- default, hover, focus, disabled, loading, error --></div>
</template>

<script setup>
defineProps({ section: { type: String, required: true } });
</script>
```

- [ ] **Step 1: Tulis demo `button.vue`** sebagai acuan bentuk

```vue
<template>
    <div v-if="section === 'anatomy'" class="hcis-row">
        <HcisButton label="Simpan perubahan" />
        <HcisButton variant="secondary"><template #leading><span aria-hidden="true">↓</span></template>Unduh</HcisButton>
        <HcisButton variant="ghost" href="/design-system">Buka detail →</HcisButton>
        <HcisButton icon-only aria-label="Hapus" variant="danger-quiet" size="sm">🗑</HcisButton>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-stack">
        <div class="hcis-row"><HcisButton label="primary" /><HcisButton label="secondary" variant="secondary" /><HcisButton label="ghost" variant="ghost" /><HcisButton label="danger-quiet" variant="danger-quiet" /><HcisButton label="danger" variant="danger" /></div>
        <div class="hcis-row"><HcisButton label="sm" size="sm" /><HcisButton label="md" /><HcisButton label="block" block /></div>
    </div>

    <div v-else-if="section === 'states'" class="hcis-row">
        <HcisButton label="default" />
        <HcisButton label="disabled" disabled />
        <HcisButton label="loading" loading />
    </div>
</template>

<script setup>
import { HcisButton } from '../../../components/ui';
defineProps({ section: { type: String, required: true } });
</script>
```

- [ ] **Step 2: Tulis demo untuk sembilan entri `owned` sisanya**

`badge.vue`, `stat-card.vue`, `card.vue`, `field.vue`, `empty-state.vue`, `skeleton.vue`, `table.vue`, `tabs.vue`, `breadcrumb.vue` — masing-masing mengikuti bentuk di atas, memakai prop yang tercantum di registry entri tersebut, dan **hanya memakai token** untuk gaya tambahan.

- [ ] **Step 3: Tulis demo untuk tujuh entri `borrowed`**

`el-select.vue`, `el-date-picker.vue`, `el-dialog.vue`, `el-pagination.vue`, `el-upload.vue`, `el-tooltip.vue`, `el-message.vue`.

Komponen Element yang memasang lapisan mengambang **wajib** diberi `popper-class="ds-preview"`, karena lapisan itu dipasang di `<body>`, di luar jangkauan kelas induk:

```vue
<el-select v-model="value" placeholder="Pilih unit" filterable popper-class="ds-preview">
    <el-option label="Produksi" value="produksi" />
    <el-option label="Gudang" value="gudang" />
</el-select>
```

- [ ] **Step 4: Verifikasi setiap entri punya demo**

`registry.js` memakai sintaks ESM, jadi `require()` akan gagal. Pakai impor dinamis:

```bash
node --input-type=module -e "
import { registry } from './resources/js/components/ui/registry.js';
import fs from 'node:fs';
const miss = registry
  .filter(e => !fs.existsSync('resources/js/Pages/DesignSystem/demos/' + e.slug + '.vue'))
  .map(e => e.slug);
console.log(miss.length ? 'HILANG: ' + miss.join(', ') : 'LENGKAP');
"
```
Expected: `LENGKAP`

- [ ] **Step 5: Jalankan build dan tes**

Run: `npm run build && npm run test:unit`
Expected: keduanya sukses

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/DesignSystem/demos
git commit -m "feat(ds): demo untuk seluruh entri registry"
```

---

## Task 7: Pemeriksaan akhir — pastikan nol dampak ke halaman lama

**Files:** tidak ada perubahan; ini gerbang verifikasi.

- [ ] **Step 1: Pastikan tak ada halaman lama tersentuh**

Run:
```bash
git diff --name-only master... | grep "^resources/js/Pages/" | grep -v "^resources/js/Pages/DesignSystem"
```
Expected: kosong. Bila ada keluaran, aturan global dilanggar — kembalikan berkas itu.

- [ ] **Step 2: Pastikan tak ada impor CSS global baru**

Run: `git diff master... -- resources/js/app.js`
Expected: kosong

- [ ] **Step 3: Pastikan jembatan tetap berlingkup**

Run: `grep -c "ds-preview" resources/css/element-plus-bridge.css`
Expected: ≥ 3, dan tidak ada selektor tanpa `.ds-preview`

- [ ] **Step 4: Jalankan seluruh tes dan build**

Run: `npm run test:unit && npm run build`
Expected: keduanya PASS

- [ ] **Step 5: Commit**

```bash
git commit --allow-empty -m "chore(ds): gerbang verifikasi nol dampak"
```

---

## Setelah rencana ini

Di luar cakupan, menunggu keputusan pemilik repo:

1. **Review Putaran 1–4** — mengesahkan atau mengubah nilai `draft` di registry, lalu menaikkan statusnya ke `ready`.
2. **`CLAUDE.md`** — ditulis setelah registry stabil, menunjuk ke `registry.js`.
3. **Mengaktifkan jembatan token global** — memindahkan isi `.ds-preview` ke `:root` dan mengimpornya di `app.js`. Inilah langkah yang mengubah rupa 167 halaman.
4. **Pemicu migrasi** (spec §7 B1) — termasuk mengesahkan atau mengganti kalimat yang sudah tayang di halaman lama.
