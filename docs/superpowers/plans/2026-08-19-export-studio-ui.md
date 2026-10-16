# Export Studio — UI, Tata Letak, dan Perjalanan Pengguna: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Layar Export Studio yang memakai design system HCIS sepenuhnya —
bertoken, bertema, terpakai di ponsel, dan mengantar pengguna sampai ke hasil
export-nya, bukan berhenti di kalimat yang menyebut tempat lain.

**Architecture:** Halaman Inertia tetap tipis dan menyerahkan seluruh perakit ke
`components/SYSTEM/ReportStudio/`. Perombakan terjadi di lapisan tampilan
komponen itu: kokpit dua panel `HcisSplitter`, panel-panel yang dirakit dari
`Hcis*`, dan tiga komponen baru untuk bilah aksi, kartu tugas, dan ringkasan
definisi ponsel. Tidak ada endpoint, service, atau skema database yang berubah.

**Tech Stack:** Vue 3 (Composition API), Inertia, Bootstrap (grid saja), CSS
vanilla bertoken, komponen `@/components/ui` (HCIS), Vitest + @vue/test-utils.

**Spec:** `docs/superpowers/specs/2026-08-19-export-studio-ui-design.md`
**Spec induk:** `docs/superpowers/specs/2026-08-18-export-studio-design.md`

## Global Constraints

- **Tanpa Tailwind.** Bootstrap hanya untuk grid; sisanya CSS vanilla.
- **Warna hanya dari token peran.** Tidak ada hex, tidak ada `rgba()`. Yang
  beralfa ditulis `rgb(var(--token-rgb) / .5)`.
- **Impor lintas berkas memakai alias `@`** (→ `resources/js`), tidak pernah
  jalur relatif berjenjang.
- **Komponen bersama dilarang tinggal di dalam `Pages/`** — resolver Inertia
  memetakan seluruh isi `Pages/` menjadi nama halaman.
- **`components/SYSTEM/ReportStudio/` bukan design system.** Ia *memakai*
  `Hcis*`, tidak *menjadi* `Hcis*`, dan **tidak** didaftarkan ke `registry.js`.
- **Delapan uji lama di `ReportStudio.test.js` wajib tetap hijau tanpa disunting.**
- **Aturan rumah:** kartu 12 per halaman, tabel 24 per halaman **dengan pemilih
  jumlah per halaman**, judul dipotong 1 baris, deskripsi 2 baris, keadaan
  memuat memakai skeleton yang meniru bentuk isi.
- **Takaran bahasa visual:** kaca maksimal 3 area dan gradasi besar maksimal 3
  area per halaman. Di layar ini: hero + satu panel kunci.
- **Setiap pembatasan wajib dilaporkan.** Pemotongan baris, kolom yang
  disamarkan, dan plafon apa pun harus terlihat pengguna — tidak boleh senyap.
- **Deteksi viewport memakai idiom yang sudah ada di repo**
  (`HcisTabs.vue:310` dan `:531`): periksa `typeof window.matchMedia === 'function'`
  lebih dulu, baru `.matches`.
- **Jangan `git add -A`.** Tambahkan berkas yang disebut tiap task saja.

### Peta token (dipakai di seluruh task)

| Warna mentah sekarang | Token pengganti |
| --- | --- |
| `#fff` | `var(--hcis-surface)` |
| `#f8fafc` | `var(--hcis-surface-soft)` |
| `#f1f5f9` | `var(--hcis-surface-soft)` |
| `#eef2f7` | `var(--hcis-border-subtle)` |
| `#e2e8f0` | `var(--hcis-border)` |
| `#cbd5e1` | `var(--hcis-text-subtle)` (saat warna ikon) / `var(--hcis-border-medium)` (saat garis) |
| `#94a3b8` | `var(--hcis-text-subtle)` |
| `#64748b` | `var(--hcis-text-muted)` |
| `#475569`, `#334155` | `var(--hcis-text)` |
| `#4f46e5` | `var(--hcis-brand-600)` |
| `#4338ca` | `var(--hcis-brand-700)` |
| `#eef2ff` | `var(--hcis-surface-brand)` |
| `#c7d2fe` | `var(--hcis-border-brand)` |
| `#b91c1c` | `var(--hcis-danger-700)` |
| `#92400e` | `var(--hcis-warning-800)` |
| `#fffbeb` | `var(--hcis-warning-50)` |

Perintah uji di seluruh rencana ini:

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

---

## Struktur berkas

| Berkas | Tanggung jawab |
| --- | --- |
| `Pages/LMS/SOP/Export/SopExportStudioPage.vue` | Hero + meneruskan `baseUrl`. Tetap tipis. |
| `ReportStudio/ReportStudio.vue` | Rangka kokpit, keadaan, orkestrasi API |
| `ReportStudio/GaleriTemplate.vue` | Rel template: tab, paginasi, aksi per kartu |
| `ReportStudio/PanelDimensiUkuran.vue` | Pemilih dimensi/ukuran + chip grup + urutan |
| `ReportStudio/PanelSaringan.vue` | Baris saringan |
| `ReportStudio/PanelPratinjau.vue` | Lima keadaan + tabel hasil |
| `ReportStudio/BilahAksi.vue` | **baru** — format, simpan, jalankan, sebab tombol mati |
| `ReportStudio/KartuTugasExport.vue` | **baru** — task_id, status, buka Pusat Aktivitas |
| `ReportStudio/RingkasanDefinisi.vue` | **baru** — definisi sebagai chip terbaca (ponsel) |
| `components/ui/designSystemGuards.test.js` | Penjaga NOL untuk `components/SYSTEM/` |

---

### Task 1: Halaman berhenti berjalan tanpa CSS-nya

Halaman memakai `.shero*` dan `.stitch-card` tetapi tidak mengimpor
`sopTheme.css` dan tidak punya blok `<style>`, sehingga hero dan kartunya
dirender polos. `HcisHeader variant="shero"` sudah menyediakan seluruh hero itu
— aurora, grid overlay, ikon, lencana, dan slot.

**Files:**
- Modify: `resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.vue`
- Test: `resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.test.js` (create)

**Interfaces:**
- Consumes: `HcisHeader` dari `@/components/ui`
- Produces: halaman dengan akar `.sop-scope` dan hero ber-slot `console`

- [ ] **Step 1: Tulis uji yang gagal**

```js
// resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.test.js
import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SopExportStudioPage from './SopExportStudioPage.vue';

vi.mock('@/components/SYSTEM/ReportStudio/ReportStudio.vue', () => ({
    default: { name: 'ReportStudio', template: '<div data-uji="studio" />' },
}));

describe('SopExportStudioPage', () => {
    const pasang = () =>
        mount(SopExportStudioPage, {
            props: { baseUrl: '/api/v1/sop/export-studio' },
            global: { stubs: { HcisHeader: false } },
        });

    it('memakai HcisHeader design system, bukan salinan .shero ke sebelas', () => {
        const w = pasang();
        expect(w.findComponent({ name: 'HcisHeader' }).exists()).toBe(true);
        // Kelas hero tulis tangan tidak boleh lahir lagi di halaman ini.
        expect(w.html()).not.toContain('shero-aurora');
    });

    it('membawa kelas scope SOP supaya sopTheme.css menjangkaunya', () => {
        expect(pasang().find('.sop-scope').exists()).toBe(true);
    });

    it('meneruskan baseUrl ke perakit', () => {
        expect(pasang().find('[data-uji="studio"]').exists()).toBe(true);
    });
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.test.js
```

Diharapkan: GAGAL pada uji pertama — `HcisHeader` belum dipakai, dan
`shero-aurora` masih ada di markup.

- [ ] **Step 3: Ganti hero tulis tangan dengan `HcisHeader`**

Ganti seluruh blok `<section class="shero …">` di `SopExportStudioPage.vue`
menjadi:

```vue
<template>
    <div class="container-fluid px-0 sop-scope sop-export-studio">
        <HcisHeader
            variant="shero"
            icon="bi-sliders"
            title="Export Studio"
            subtitle="Rakit laporan SOP sendiri, lihat hasilnya sebelum diunduh."
            badge="Sumber: Kepatuhan"
            class="mb-4"
        >
            <template #actions>
                <span class="ses-tanggal"><i class="bi bi-calendar3"></i> {{ tanggal }}</span>
            </template>
        </HcisHeader>

        <div class="mb-5 sop-stack">
            <ReportStudio :base-url="baseUrl" :dataset-awal="datasetAwal" />
        </div>
    </div>
</template>
```

Pada blok `<script setup>`, tambahkan impor berikut (pertahankan `defineProps`
dan `tanggal` yang sudah ada apa adanya):

```js
import HcisHeader from '@/components/ui/HcisHeader.vue';
import '@/Pages/LMS/SOP/Shared/support/sopTheme.css';
```

Tambahkan blok gaya di akhir berkas:

```vue
<style scoped>
.ses-tanggal {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .8rem;
    color: var(--hcis-text-inverse);
}
</style>
```

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.test.js
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.vue resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.test.js
git commit -m "fix(sop): Export Studio memakai HcisHeader dan mengimpor sopTheme"
```

---

### Task 2: Enam puluh warna mentah, lalu tutup celahnya

Enam puluh warna mentah membuat tema gelap tidak berlaku di layar ini. Ia lolos
selama ini karena cakupan `designSystemGuards.test.js` adalah `components/ui`
dan `Pages` — `components/SYSTEM/` tidak termasuk keduanya. Berhubung
`components/SYSTEM/` hari ini **hanya** berisi ReportStudio, menutup celahnya
tidak menyeret berkas lain.

**Files:**
- Modify: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue` (blok `<style>`)
- Modify: `resources/js/components/SYSTEM/ReportStudio/GaleriTemplate.vue` (blok `<style>`)
- Modify: `resources/js/components/SYSTEM/ReportStudio/PanelDimensiUkuran.vue` (blok `<style>`)
- Modify: `resources/js/components/SYSTEM/ReportStudio/PanelPratinjau.vue` (blok `<style>`)
- Modify: `resources/js/components/SYSTEM/ReportStudio/PanelSaringan.vue` (blok `<style>`)
- Test: `resources/js/components/ui/designSystemGuards.test.js`

**Interfaces:**
- Consumes: token peran dari `resources/css/design-system.css`
- Produces: `components/SYSTEM/` bebas warna mentah, dijaga NOL

- [ ] **Step 1: Tulis penjaga yang gagal**

Tambahkan tepat di bawah definisi `BERKAS_BARU` di
`resources/js/components/ui/designSystemGuards.test.js`:

```js
/*
 * Komponen fitur milik modul SYSTEM. Ia memakai Hcis*, tidak menjadi Hcis*,
 * tapi aturan warnanya sama persis: warna hanya lahir di design-system.css.
 * Celah ini nyata — 60 warna mentah di ReportStudio lolos berbulan-bulan
 * semata karena jalurnya tidak masuk cakupan mana pun.
 */
const BERKAS_SYSTEM = telusuri('resources/js/components/SYSTEM').filter(
    (f) => !f.endsWith('.test.js'),
);
```

Lalu tambahkan pemeriksaan berikut di dalam `describe('komponen: dirakit dari
token, bukan dari nilai', …)`:

```js
it('NOL — komponen fitur di components/SYSTEM tidak memakai warna mentah', () => {
    const pelanggaran = [];

    for (const berkas of BERKAS_SYSTEM) {
        const cocok = gaya(berkas).match(WARNA_MENTAH);
        if (cocok) pelanggaran.push(berkas + ' → ' + [...new Set(cocok)].join(', '));
    }

    expect(pelanggaran, pelanggaran.join('\n')).toEqual([]);
});
```

- [ ] **Step 2: Jalankan penjaga, pastikan MERAH**

```bash
npx vitest run resources/js/components/ui/designSystemGuards.test.js -t "components/SYSTEM"
```

Diharapkan: GAGAL, menyebut kelima berkas ReportStudio beserta hex-nya. Penjaga
yang belum pernah terlihat merah tidak membuktikan apa pun — jangan lewati
langkah ini.

- [ ] **Step 3: Ganti seluruh warna mentah dengan token**

Terapkan **Peta token** di bagian Global Constraints ke blok `<style>` kelima
berkas. Jumlah per berkas sebagai daftar periksa: `ReportStudio.vue` 16,
`GaleriTemplate.vue` 20, `PanelDimensiUkuran.vue` 7, `PanelPratinjau.vue` 10,
`PanelSaringan.vue` 7.

Dua penggantian tidak lurus satu-lawan-satu dan disengaja:

- `#f1f5f9` sebagai latar (kepala tabel, kotak catatan) menjadi
  `var(--hcis-surface-soft)` — Slate-50, bukan Slate-100. Tidak ada token peran
  untuk Slate-100 sebagai permukaan, dan memakai primitif dari komponen
  melanggar lapis peran. Selisihnya satu tingkat dan tidak mengubah keterbacaan.
- `#cbd5e1` pada tombol urutan yang mati menjadi `var(--hcis-text-subtle)`,
  karena di sana ia warna ikon, bukan garis.

Contoh hasil untuk `ReportStudio.vue`:

```css
.rs-mode-btn {
    border: 1px solid var(--hcis-border);
    background: var(--hcis-surface);
    border-radius: 999px;
    padding: .2rem .75rem;
    font-size: .8rem;
    color: var(--hcis-text);
}
.rs-mode-btn.aktif {
    background: var(--hcis-brand-600);
    border-color: var(--hcis-brand-600);
    color: var(--hcis-text-on-brand);
}
.rs-grain { font-size: .78rem; color: var(--hcis-text-muted); margin: 0 0 1rem; }
```

- [ ] **Step 4: Jalankan penjaga, pastikan HIJAU**

```bash
npx vitest run resources/js/components/ui/designSystemGuards.test.js
```

Seluruh berkas penjaga dijalankan, bukan hanya pemeriksaan baru: penjaga di
wilayah bersama menyentuh setiap pemanggil.

- [ ] **Step 5: Pastikan delapan uji lama masih hijau**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio resources/js/components/ui/designSystemGuards.test.js
git commit -m "refactor(sop): ReportStudio bertoken penuh, tutup celah penjaga components/SYSTEM"
```

---

### Task 3: Tiga puluh dimensi datar mendapat grupnya kembali

Katalog mengirim `grup` untuk tiap dimensi; `PanelDimensiUkuran.vue:70`
membuangnya. Chip grup menyaring **sisi tersedia saja** — menyaring sisi
terpilih adalah cara tercepat membuat orang mengira pilihannya hilang.

**Files:**
- Modify: `resources/js/components/SYSTEM/ReportStudio/PanelDimensiUkuran.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: entri katalog `{ key, label, grup, sensitif }`
- Produces: prop baru `berkelompok: Boolean` pada `PanelDimensiUkuran`

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan di dalam `describe('ReportStudio', …)` pada `ReportStudio.test.js`:

```js
it('chip grup menyaring sisi tersedia, dan tidak pernah menyaring sisi terpilih', async () => {
    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    // Pilih satu dimensi dari grup "identitas" lebih dulu.
    w.vm.pilihan.dimensi = ['divisi'];
    await flushPromises();

    const chipPaket = w.findAll('.rs-chip-grup').find((c) => c.text().includes('Paket'));
    expect(chipPaket, 'chip grup Paket harus ada').toBeTruthy();

    await chipPaket.trigger('click');
    await flushPromises();

    const panel = w.findComponent({ name: 'PanelDimensiUkuran' });
    // Sisi tersedia menyusut ke grup Paket saja...
    expect(panel.vm.opsi.map((o) => o.key)).toEqual(['paket']);
    // ...tapi yang sudah dipilih tetap utuh.
    expect(w.vm.pilihan.dimensi).toEqual(['divisi']);
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "chip grup"
```

Diharapkan: GAGAL — `.rs-chip-grup` belum ada.

- [ ] **Step 3: Tambahkan chip grup di `PanelDimensiUkuran.vue`**

Tambahkan prop `berkelompok`, keadaan `grupAktif`, dan daftar grup:

```js
const props = defineProps({
    // …prop yang sudah ada dipertahankan…
    berkelompok: { type: Boolean, default: false },
});

const grupAktif = ref('semua');

const daftarGrup = computed(() => {
    const hitung = new Map();
    for (const d of props.daftar) {
        const g = d.grup || 'lainnya';
        hitung.set(g, (hitung.get(g) ?? 0) + 1);
    }
    return [...hitung.entries()].map(([kunci, jumlah]) => ({ kunci, jumlah }));
});

/* Chip menyaring sisi TERSEDIA saja. HcisTransfer menghitung sisi kanan dari
   modelValue, jadi yang sudah dipilih tidak pernah ikut tersaring — dan itu
   memang yang diinginkan. */
const opsi = computed(() =>
    props.daftar
        .filter((d) => grupAktif.value === 'semua' || (d.grup || 'lainnya') === grupAktif.value)
        .map((d) => ({
            key: d.key,
            label: d.sensitif ? `${d.label} 🔒` : d.label,
        }))
);

defineExpose({ opsi });
```

Sisipkan deret chip tepat di atas `<HcisTransfer>`:

```vue
<div v-if="berkelompok && daftarGrup.length > 1" class="rs-chip-baris">
    <button
        type="button"
        :class="['rs-chip-grup', { aktif: grupAktif === 'semua' }]"
        @click="grupAktif = 'semua'"
    >
        Semua <span class="rs-chip-jml">{{ daftar.length }}</span>
    </button>
    <button
        v-for="g in daftarGrup"
        :key="g.kunci"
        type="button"
        :class="['rs-chip-grup', { aktif: grupAktif === g.kunci }]"
        @click="grupAktif = g.kunci"
    >
        {{ g.kunci }} <span class="rs-chip-jml">{{ g.jumlah }}</span>
    </button>
</div>
```

Gaya (bertoken):

```css
.rs-chip-baris { display: flex; flex-wrap: wrap; gap: .25rem; margin-bottom: .5rem; }
.rs-chip-grup {
    border: 1px solid var(--hcis-border);
    background: var(--hcis-surface);
    color: var(--hcis-text-muted);
    border-radius: 999px;
    padding: .12rem .55rem;
    font-size: .74rem;
    text-transform: capitalize;
}
.rs-chip-grup.aktif {
    background: var(--hcis-surface-brand);
    border-color: var(--hcis-border-brand);
    color: var(--hcis-brand-700);
}
.rs-chip-jml { font-variant-numeric: tabular-nums; opacity: .7; margin-left: .2rem; }
```

- [ ] **Step 4: Nyalakan pada panel dimensi saja**

Di `ReportStudio.vue`, tambahkan `berkelompok` pada `PanelDimensiUkuran` untuk
"Kelompokkan berdasarkan" dan untuk mode rinci ("Kolom"). Panel "Hitung"
(ukuran) **tidak** memakainya — sembilan ukuran tidak butuh disaring, dan
chip yang tidak mengurangi apa pun hanya menambah bising.

- [ ] **Step 5: Ganti dua kontrol tulis tangan yang tersisa di perakit**

Pemilih mode di `ReportStudio.vue` (`.rs-mode-pilih`, dua `<button>` pil buatan
sendiri) menjadi `HcisTabs`:

```vue
<HcisTabs
    v-model="mode"
    variant="segmented"
    size="sm"
    :tabs="[
        { key: 'ringkasan', label: 'Ringkasan' },
        { key: 'rinci', label: 'Rinci' },
    ]"
/>
```

```js
import HcisTabs from '@/components/ui/HcisTabs.vue';
```

Buang gaya `.rs-mode-btn` dan `.rs-mode-pilih` yang tidak terpakai lagi.

Tombol naik/turun urutan pengelompokan di `PanelDimensiUkuran.vue` menjadi
`HcisButton` varian hantu — tetap `<button>` sungguhan supaya terjangkau
keyboard:

```vue
<span class="rs-urutan-aksi">
    <HcisButton
        variant="ghost"
        size="sm"
        icon="bi-arrow-up"
        :disabled="i === 0"
        aria-label="Naikkan"
        @click="$emit('geser', kunci, -1)"
    />
    <HcisButton
        variant="ghost"
        size="sm"
        icon="bi-arrow-down"
        :disabled="i === modelValue.length - 1"
        aria-label="Turunkan"
        @click="$emit('geser', kunci, 1)"
    />
</span>
```

```js
import HcisButton from '@/components/ui/HcisButton.vue';
```

> Uji lama `geser menukar posisi dimensi` memanggil composable langsung, bukan
> DOM, jadi penggantian ini tidak boleh mengubahnya. Kalau ia berubah merah,
> berarti emit-nya yang salah — bukan ujinya yang perlu disesuaikan.

- [ ] **Step 6: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio
git commit -m "feat(sop): chip grup dimensi dan kontrol design system di perakit"
```

---

### Task 4: Saringan memakai kontrol design system

**Files:**
- Modify: `resources/js/components/SYSTEM/ReportStudio/PanelSaringan.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `HcisSelect`, `HcisSelectOption` dari `@/components/ui`
- Produces: perilaku `modelValue` yang sama persis (`{ field, op, nilai }`)

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('saringan memakai kontrol design system, bukan select telanjang', async () => {
    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    await w.find('.rs-tambah').trigger('click');
    await flushPromises();

    expect(w.findComponent({ name: 'HcisSelect' }).exists()).toBe(true);
    expect(w.find('.rs-saringan select').exists()).toBe(false);
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "kontrol design system"
```

- [ ] **Step 3: Ganti `<select>` dengan `HcisSelect`**

```vue
<div v-for="(s, i) in modelValue" :key="i" class="rs-saringan">
    <HcisSelect
        :model-value="s.field"
        :filterable="true"
        placeholder="Pilih field…"
        @update:model-value="(v) => ubah(i, 'field', v)"
    >
        <HcisSelectOption v-for="f in daftar" :key="f.key" :value="f.key" :label="f.label" />
    </HcisSelect>

    <HcisSelect
        :model-value="s.op"
        :disabled="!s.field"
        placeholder="Operator"
        @update:model-value="(v) => ubah(i, 'op', v)"
    >
        <HcisSelectOption
            v-for="op in operatorUntuk(s.field)"
            :key="op"
            :value="op"
            :label="labelOp(op)"
        />
    </HcisSelect>

    <input
        :value="nilaiTeks(s)"
        class="form-control form-control-sm"
        :placeholder="placeholderUntuk(s)"
        @input="ubah(i, 'nilai', pecah($event.target.value, s.op))"
    />

    <button type="button" class="rs-hapus" title="Hapus saringan" @click="hapus(i)">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
```

Impor keduanya:

```js
import HcisSelect from '@/components/ui/HcisSelect.vue';
import HcisSelectOption from '@/components/ui/HcisSelectOption.vue';
```

> **Sudah diperiksa:** `HcisSelect` hanya punya `teleportTo` (baku `'body'`) dan
> **tidak** punya prop kelas panel. Itu tidak apa-apa di sini: panel pilihannya
> menata dirinya dari token design system, bukan dari `.sop-scope`. Yang
> benar-benar butuh membawa kelas scope adalah lapisan yang berisi markup SOP
> kita sendiri — yaitu `HcisDialog` di Task 6 dan Task 9.

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio/PanelSaringan.vue resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
git commit -m "refactor(sop): saringan Export Studio memakai HcisSelect"
```

---

### Task 5: Pratinjau memakai `HcisDataTable`

Tabel tulis tangan diganti `HcisDataTable`, yang membawa paginasi **24 baris
dengan pemilih jumlah per halaman**, kepala lengket, dan gulir mendatar di dalam
wadahnya sendiri. Lima keadaan yang sudah benar dipertahankan seluruhnya.

**Files:**
- Modify: `resources/js/components/SYSTEM/ReportStudio/PanelPratinjau.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `hasil = { columns: [{key,label,center}], rows: [], catatan: [] }`
- Produces: tampilan; kontrak `hasil` tidak berubah

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('pratinjau memakai HcisDataTable dan catatan kaki tidak bisa dilipat', async () => {
    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    w.vm.pilihan.dimensi = ['divisi'];
    w.vm.pilihan.ukuran = ['jml_karyawan'];
    await new Promise((r) => setTimeout(r, 450));
    await flushPromises();

    expect(w.findComponent({ name: 'HcisDataTable' }).exists()).toBe(true);
    // Catatan kaki wajib polos — tidak ada pemicu lipat apa pun.
    expect(w.find('.rs-catatan details').exists()).toBe(false);
    expect(w.find('.rs-catatan button').exists()).toBe(false);
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "HcisDataTable"
```

- [ ] **Step 3: Ganti blok tabel**

Ganti `<div v-else-if="hasil" class="rs-tabel-bungkus">…</div>` menjadi:

```vue
<div v-else-if="hasil" class="rs-tabel-bungkus">
    <HcisDataTable
        :columns="kolomTabel"
        :data="barisHalaman"
        :total="hasil.rows.length"
        :page="halaman"
        :page-size="ukuranHalaman"
        :searchable="false"
        :allow-add="false"
        :allow-export="false"
        striped
        @page-change="(p) => (halaman = p)"
        @size-change="(n) => { ukuranHalaman = n; halaman = 1; }"
    />
</div>
```

Tambahkan di `<script setup>`:

```js
// Berkas ini sudah punya `import { computed } from 'vue'` — gabungkan, jangan
// menulis baris impor kedua dari modul yang sama.
import { computed, ref, watch } from 'vue';
import HcisDataTable from '@/components/ui/HcisDataTable.vue';

const halaman = ref(1);
const ukuranHalaman = ref(24);

/* HcisDataTable TIDAK memotong datanya sendiri — ia menerima `total`, `page`,
   dan `pageSize` lalu memancarkan `page-change`/`size-change`. Pemotongannya
   milik kita. */
const barisHalaman = computed(() => {
    const awal = (halaman.value - 1) * ukuranHalaman.value;
    return (props.hasil?.rows ?? []).slice(awal, awal + ukuranHalaman.value);
});

/* Hasil baru selalu mulai dari halaman 1; kalau tidak, definisi yang diubah
   bisa mendarat di halaman 7 yang sudah tidak ada isinya. */
watch(() => props.hasil, () => { halaman.value = 1; });

/* Compiler mengirim `center` untuk kolom angka; HcisTable membaca `align`. */
const kolomTabel = computed(() =>
    (props.hasil?.columns ?? []).map((c) => ({
        key: c.key,
        label: c.label,
        align: c.center ? 'center' : 'left',
    }))
);
```

Buang gaya `.rs-tabel*` yang tidak terpakai lagi, sisakan `.rs-tabel-bungkus`.

> **Sudah diperiksa:** `HcisDataTable` meneruskan kolom ke `HcisTable`, yang
> membaca `col.key`, `col.label`, `col.align`, `col.width`, `col.sortable`,
> `col.class`, `col.cellClass`. Prop `pageSizeOptions` dan `showSearch` **tidak
> ada** — yang benar `pageSizes` (milik `HcisPagination` di dalamnya) dan
> `searchable`. Jangan menambal `HcisDataTable` untuk menyesuaikannya.

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio/PanelPratinjau.vue resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
git commit -m "refactor(sop): pratinjau Export Studio memakai HcisDataTable"
```

---

### Task 6: Galeri berhenti menghapus tanpa bertanya

**Files:**
- Modify: `resources/js/components/SYSTEM/ReportStudio/GaleriTemplate.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `HcisDialog`, `HcisPagination` dari `@/components/ui`
- Produces: emit `hapus` **hanya** setelah dialog dikonfirmasi

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('hapus template tidak memanggil API sebelum dikonfirmasi', async () => {
    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    // Pindah ke tab "Milik Saya" yang punya tombol hapus.
    const galeri = w.findComponent({ name: 'GaleriTemplate' });
    galeri.vm.aktif = 'saya';
    await flushPromises();

    const tombol = w.find('.rs-btn.hapus');
    if (tombol.exists()) {
        await tombol.trigger('click');
        await flushPromises();
        expect(galeri.emitted('hapus')).toBeFalsy();
        expect(w.findComponent({ name: 'HcisDialog' }).exists()).toBe(true);
    }
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "sebelum dikonfirmasi"
```

- [ ] **Step 3: Tambahkan dialog konfirmasi, paginasi, dan pemotongan**

Ganti `@click="$emit('hapus', kodeDari(t))"` menjadi `@click="mintaHapus(t)"`,
lalu tambahkan:

```js
import { computed, ref, watch } from 'vue'; // `watch` baru; gabungkan dgn impor yang ada
import HcisDialog from '@/components/ui/HcisDialog.vue';
import HcisPagination from '@/components/ui/HcisPagination.vue';

/* Berkas ini memanggil `defineEmits([...])` tanpa menampungnya. Tampung dulu —
   `emit(...)` di bawah butuh rujukannya. */
const emit = defineEmits(['jalankan', 'duplikat', 'hapus']);

/* Uji memindahkan tab lewat `galeri.vm.aktif`; tanpa expose, ia tidak
   terjangkau. Dipasang di sini karena Task 6-lah yang pertama membutuhkannya. */
defineExpose({ aktif });

const KARTU_PER_HALAMAN = 12;
const halaman = ref(1);
const calonHapus = ref(null);

const daftarHalaman = computed(() => {
    const awal = (halaman.value - 1) * KARTU_PER_HALAMAN;
    return daftarAktif.value.slice(awal, awal + KARTU_PER_HALAMAN);
});

function mintaHapus(t) {
    calonHapus.value = t;
}

function konfirmasiHapus() {
    emit('hapus', kodeDari(calonHapus.value));
    calonHapus.value = null;
}

/* Berpindah tab mengembalikan halaman ke 1 — kalau tidak, tab dengan 2 kartu
   bisa tampil kosong karena masih berada di halaman 3 milik tab sebelumnya. */
watch(aktif, () => { halaman.value = 1; });
```

`v-for` pada kartu memakai `daftarHalaman`, dan di bawah grid:

```vue
<HcisPagination
    v-if="daftarAktif.length > KARTU_PER_HALAMAN"
    v-model="halaman"
    :total="daftarAktif.length"
    :page-size="KARTU_PER_HALAMAN"
    class="mt-3"
/>

<HcisDialog
    :model-value="calonHapus !== null"
    title="Nonaktifkan template?"
    :description="`«${namaDari(calonHapus ?? {})}» tidak akan muncul lagi di galeri Anda.`"
    sentiment="danger"
    size="sm"
    class="sop-scope"
    @update:model-value="(v) => { if (!v) calonHapus = null; }"
>
    <template #footer>
        <button type="button" class="rs-btn" @click="calonHapus = null">Batal</button>
        <button type="button" class="rs-btn hapus" @click="konfirmasiHapus">Nonaktifkan</button>
    </template>
</HcisDialog>
```

Pemotongan teks (aturan rumah):

```css
.rs-kartu h6 {
    margin: 0;
    font-size: .92rem;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.rs-kartu-desk {
    margin: 0;
    font-size: .78rem;
    color: var(--hcis-text-muted);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```

> `HcisDialog` mem-*teleport* isinya ke `body`; `class="sop-scope"` di atas
> yang menjaga gayanya tetap terjangkau. Periksa juga bahwa backdrop-nya tidak
> terperangkap leluhur ber-`transform`: `.sop-fade-up` memakai
> `animation … both` yang menahan `translateY(0)` selamanya.

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio/GaleriTemplate.vue resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
git commit -m "feat(sop): galeri template berpaginasi dan konfirmasi sebelum nonaktif"
```

---

### Task 7: Bilah aksi lengket yang mengatakan alasannya

Tombol "Jalankan export" hari ini terkubur di bawah tabel pratinjau di kolom
kanan; pada laporan rinci ia hilang di bawah lipatan. Ia juga mati tanpa
menjelaskan sebabnya.

**Files:**
- Create: `resources/js/components/SYSTEM/ReportStudio/BilahAksi.vue`
- Modify: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `HcisButton`, `HcisSelect`, `HcisSelectOption`
- Produces: `<BilahAksi>` dengan props `format` (v-model), `valid`, `sibuk`,
  `sebabMati: String`; emit `simpan`, `jalankan`

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('menuliskan sebab tombol jalankan mati, bukan sekadar mematikannya', async () => {
    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    // Belum ada ukuran → definisi tidak sah.
    expect(w.text()).toContain('Mode ringkasan membutuhkan minimal satu ukuran.');

    const bilah = w.findComponent({ name: 'BilahAksi' });
    expect(bilah.exists()).toBe(true);
    expect(bilah.props('sebabMati')).toContain('ukuran');
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "sebab tombol"
```

- [ ] **Step 3: Buat `BilahAksi.vue`**

```vue
<template>
    <div class="rs-bilah">
        <HcisSelect
            :model-value="format"
            class="rs-bilah-format"
            popper-class="sop-scope"
            @update:model-value="(v) => $emit('update:format', v)"
        >
            <HcisSelectOption value="xlsx" label="XLSX" />
            <HcisSelectOption value="csv" label="CSV" />
            <HcisSelectOption value="pdf" label="PDF" />
        </HcisSelect>

        <span class="rs-bilah-isi"></span>

        <!-- Sebab ditulis DI SEBELAH tombol, bukan disembunyikan di tooltip:
             tombol mati tanpa alasan adalah jalan buntu, bukan pencegahan. -->
        <span v-if="sebabMati" class="rs-bilah-sebab">
            <i class="bi bi-info-circle"></i> {{ sebabMati }}
        </span>

        <HcisButton
            variant="secondary"
            size="sm"
            icon="bi-bookmark"
            label="Simpan sebagai…"
            :disabled="!valid || sibuk"
            @click="$emit('simpan')"
        />

        <!-- Tetap hidup meski pratinjau terlalu berat: kehilangan pratinjau
             bukan alasan mencabut export. -->
        <HcisButton
            variant="primary"
            size="sm"
            icon="bi-play-fill"
            label="Jalankan export"
            :loading="sibuk"
            :disabled="!valid || sibuk"
            @click="$emit('jalankan')"
        />
    </div>
</template>

<script setup>
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisSelect from '@/components/ui/HcisSelect.vue';
import HcisSelectOption from '@/components/ui/HcisSelectOption.vue';

defineProps({
    format: { type: String, default: 'xlsx' },
    valid: { type: Boolean, default: false },
    sibuk: { type: Boolean, default: false },
    sebabMati: { type: String, default: '' },
});

defineEmits(['update:format', 'simpan', 'jalankan']);
</script>

<style scoped>
.rs-bilah {
    position: sticky;
    bottom: 0;
    z-index: 3;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .5rem;
    padding: .7rem .9rem;
    margin: 1rem -1rem -1rem;
    border-top: 1px solid var(--hcis-border);
    background: var(--hcis-surface);
}
.rs-bilah-format { width: 7.5rem; }
.rs-bilah-isi { flex: 1 1 auto; }
.rs-bilah-sebab { font-size: .78rem; color: var(--hcis-text-muted); }
</style>
```

- [ ] **Step 4: Pasang di `ReportStudio.vue`**

Buang blok `.rs-jalankan` beserta gayanya, lalu di bawah baris `row g-4`:

```vue
<BilahAksi
    v-model:format="format"
    :valid="valid"
    :sibuk="sibuk"
    :sebab-mati="sebabMati"
    @simpan="dialogSimpan = true"
    @jalankan="jalankanDefinisi"
/>
```

Dan di `<script setup>`:

```js
import BilahAksi from './BilahAksi.vue';

const dialogSimpan = ref(false);

/* Sebab pertama saja. Menumpuk seluruh galat di satu baris membuat bilah aksi
   membungkus dua baris dan justru tidak terbaca. */
const sebabMati = computed(() => (valid.value ? '' : Object.values(galat.value)[0] ?? ''));
```

Tambahkan `padding-bottom` pada panel pratinjau supaya baris terakhir tabel
tidak tertutup bilah lengket:

```css
.rs-pratinjau-kolom { padding-bottom: 3.5rem; }
```

- [ ] **Step 5: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio
git commit -m "feat(sop): bilah aksi lengket Export Studio dengan sebab tombol mati"
```

---

### Task 8: Perjalanan tidak lagi putus setelah "Jalankan"

**Files:**
- Create: `resources/js/components/SYSTEM/ReportStudio/KartuTugasExport.vue`
- Modify: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `useShellState()` dari `@/composables/useShellState`, `hcisMessage`
- Produces: `<KartuTugasExport :tugas="{ id, format }" />` dengan emit `buka`

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('menyediakan jalan ke Pusat Aktivitas, bukan sekadar menyebutnya', async () => {
    const { useShellState } = await import('@/composables/useShellState');
    const shell = useShellState();
    shell.state.showActivity = false;

    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    w.vm.pilihan.dimensi = ['divisi'];
    w.vm.pilihan.ukuran = ['jml_karyawan'];
    await flushPromises();

    await w.findComponent({ name: 'BilahAksi' }).vm.$emit('jalankan');
    await flushPromises();

    const kartu = w.findComponent({ name: 'KartuTugasExport' });
    expect(kartu.exists()).toBe(true);

    await kartu.find('.rs-tugas-buka').trigger('click');
    expect(shell.state.showActivity).toBe(true);
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "Pusat Aktivitas"
```

- [ ] **Step 3: Buat `KartuTugasExport.vue`**

```vue
<template>
    <div class="rs-tugas" role="status">
        <i class="bi bi-hourglass-split rs-tugas-ikon"></i>
        <div class="rs-tugas-teks">
            <strong>Laporan sedang diproses.</strong>
            <span class="rs-tugas-meta">{{ tugas.format.toUpperCase() }} · {{ tugas.id }}</span>
        </div>
        <button type="button" class="rs-tugas-buka" @click="$emit('buka')">
            Buka Pusat Aktivitas <i class="bi bi-arrow-up-right"></i>
        </button>
    </div>
</template>

<script setup>
defineProps({
    /** { id: String, format: String } */
    tugas: { type: Object, required: true },
});

defineEmits(['buka']);
</script>

<style scoped>
.rs-tugas {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-top: .75rem;
    padding: .6rem .8rem;
    border: 1px solid var(--hcis-border-brand);
    background: var(--hcis-surface-brand);
    border-radius: 10px;
    font-size: .82rem;
    color: var(--hcis-brand-700);
}
.rs-tugas-ikon { font-size: 1.1rem; }
.rs-tugas-teks { display: flex; flex-direction: column; min-width: 0; }
.rs-tugas-meta { font-size: .74rem; color: var(--hcis-text-muted); font-variant-numeric: tabular-nums; }
.rs-tugas-buka {
    margin-left: auto;
    border: 1px solid var(--hcis-border-brand);
    background: var(--hcis-surface);
    color: var(--hcis-brand-700);
    border-radius: 8px;
    font-size: .78rem;
    padding: .25rem .6rem;
    white-space: nowrap;
}
</style>
```

- [ ] **Step 4: Sambungkan di `ReportStudio.vue`**

```js
import { useShellState } from '@/composables/useShellState';
import { hcisMessage } from '@/components/ui/hcisMessage';
import KartuTugasExport from './KartuTugasExport.vue';

const shell = useShellState();
const tugas = ref(null);

function bukaPusatAktivitas() {
    shell.state.showActivity = true;
}
```

Ganti isi `jalankanDefinisi` dan `jalankanTemplate` — keduanya berbagi ekor yang
sama, jadi ekornya diangkat menjadi satu fungsi:

```js
function tandaiTerkirim(r, formatDipakai) {
    tugas.value = { id: r.task_id, format: formatDipakai };
    hcisMessage({ type: 'success', message: 'Laporan masuk antrean pemrosesan.' });
}

async function jalankanDefinisi() {
    sibuk.value = true;
    try {
        const r = await api.jalankan({ format: format.value, definisi: definisi.value });
        tandaiTerkirim(r, format.value);
    } catch (e) {
        hcisMessage({ type: 'error', message: e?.response?.data?.message ?? 'Laporan gagal dijalankan.' });
    } finally {
        sibuk.value = false;
    }
}
```

Terapkan pola yang sama pada `jalankanTemplate`. Di template, ganti
`<p v-if="pesanAksi">` dengan:

```vue
<KartuTugasExport v-if="tugas" :tugas="tugas" @buka="bukaPusatAktivitas" />
```

- [ ] **Step 5: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio
git commit -m "feat(sop): kartu tugas export mengantar ke Pusat Aktivitas"
```

---

### Task 9: "Simpan sebagai" jadi dialog, bukan kotak nyempil

**Files:**
- Modify: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue`
- Modify: `resources/js/components/SYSTEM/ReportStudio/GaleriTemplate.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `HcisDialog`, `HcisField`
- Produces: prop `tabAwal: String` pada `GaleriTemplate` supaya induk bisa
  memindahkan tab aktif setelah menyimpan

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('setelah menyimpan template, galeri berpindah ke tab Milik Saya', async () => {
    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    w.vm.pilihan.dimensi = ['divisi'];
    w.vm.pilihan.ukuran = ['jml_karyawan'];
    await flushPromises();

    await w.findComponent({ name: 'BilahAksi' }).vm.$emit('simpan');
    await flushPromises();

    // Diisi lewat input sungguhan, bukan lewat vm: `namaTemplate` tidak
    // di-expose ReportStudio, dan menambahkannya ke defineExpose hanya demi uji
    // adalah API yang lahir dari uji, bukan dari kebutuhan.
    await w.find('#rs-nama-template').setValue('Laporan Saya');
    await w.find('.rs-simpan-kirim').trigger('click');
    await flushPromises();

    expect(w.findComponent({ name: 'GaleriTemplate' }).vm.aktif).toBe('saya');
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "tab Milik Saya"
```

- [ ] **Step 3: Tambahkan dialog simpan**

```vue
<HcisDialog
    v-model="dialogSimpan"
    title="Simpan sebagai template"
    description="Template tersimpan muncul di tab «Milik Saya» dan bisa dijalankan ulang kapan saja."
    size="sm"
    class="sop-scope"
>
    <!-- HcisField adalah kontrol utuh (label + input + hint + galat), bukan
         pembungkus ber-slot. `id` WAJIB — ia yang menyambungkan <label> ke
         <input>, jadi jangan dihilangkan. -->
    <HcisField
        id="rs-nama-template"
        v-model="namaTemplate"
        label="Nama template"
        placeholder="mis. Kepatuhan per Divisi"
        required
    />
    <HcisField
        id="rs-deskripsi-template"
        v-model="deskripsiTemplate"
        label="Deskripsi"
        hint="Opsional — membantu Anda mengenalinya nanti."
    />

    <template #footer>
        <button type="button" class="rs-btn" @click="dialogSimpan = false">Batal</button>
        <button
            type="button"
            class="rs-btn utama rs-simpan-kirim"
            :disabled="!namaTemplate.trim() || sibuk"
            @click="simpanTemplate"
        >
            Simpan
        </button>
    </template>
</HcisDialog>
```

Ubah `simpanTemplate` agar menutup dialog dan memindahkan tab:

```js
const deskripsiTemplate = ref('');
const tabGaleri = ref('resmi');

async function simpanTemplate() {
    if (!namaTemplate.value.trim()) return;
    sibuk.value = true;
    try {
        await api.simpan({
            nama: namaTemplate.value.trim(),
            deskripsi: deskripsiTemplate.value.trim(),
            definisi: definisi.value,
        });
        galeri.value = await api.galeri();
        // Tab dipindah SETELAH galeri disegarkan; kalau dibalik, tab "Milik
        // Saya" tampil kosong sepersekian detik lalu terisi.
        tabGaleri.value = 'saya';
        dialogSimpan.value = false;
        namaTemplate.value = '';
        deskripsiTemplate.value = '';
        hcisMessage({ type: 'success', message: 'Template tersimpan.' });
    } catch (e) {
        hcisMessage({ type: 'error', message: e?.response?.data?.message ?? 'Template gagal disimpan.' });
    } finally {
        sibuk.value = false;
    }
}
```

Di `GaleriTemplate.vue`, jadikan tab aktif bisa dikendalikan induk:

```js
const props = defineProps({
    data: { type: Object, default: null },
    tabAwal: { type: String, default: 'resmi' },
});

const aktif = ref(props.tabAwal);
watch(() => props.tabAwal, (v) => { aktif.value = v; });

// `defineExpose({ aktif })` sudah dipasang di Task 6 — jangan ditulis dua kali.
```

Dan di `ReportStudio.vue`: `<GaleriTemplate :tab-awal="tabGaleri" … />`.

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio
git commit -m "feat(sop): simpan template lewat dialog dan pindah ke tab Milik Saya"
```

---

### Task 10: Rangka kokpit

Menyatukan semuanya: `HcisSplitter`, rel template yang bisa dilipat, dan chip
hidup di hero.

**Files:**
- Modify: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue`
- Modify: `resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `HcisSplitter` (slot `first`/`second`), `HcisCollapse`, `HcisCollapseItem`
- Produces: emit `ringkas` dari `ReportStudio` berisi
  `{ mode: String, kolom: Number, baris: Number|null }` untuk chip hero

- [ ] **Step 1: Tulis uji yang gagal**

```js
it('merakit kokpit dua panel dan melaporkan ringkasan ke halaman', async () => {
    const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
    await flushPromises();

    expect(w.findComponent({ name: 'HcisSplitter' }).exists()).toBe(true);

    w.vm.pilihan.dimensi = ['divisi'];
    w.vm.pilihan.ukuran = ['jml_karyawan'];
    await flushPromises();

    const terakhir = w.emitted('ringkas').at(-1)[0];
    expect(terakhir.mode).toBe('ringkasan');
    expect(terakhir.kolom).toBe(2);
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "kokpit dua panel"
```

- [ ] **Step 3: Bungkus perakit dan pratinjau dengan splitter**

Ganti `<div class="row g-4">…</div>` menjadi:

```vue
<HcisSplitter
    v-if="!ponsel"
    orientation="horizontal"
    variant="flat"
    :initial-ratio="42"
    :min-ratio="28"
    :max-ratio="72"
    class="rs-kokpit"
>
    <template #first>
        <div class="rs-perakit-kolom">
            <!-- Urutannya persis seperti kolom kiri lama: -->
            <!-- 1. HcisTabs mode (dari Task 3)                                -->
            <!-- 2. <p class="rs-grain">{{ katalog?.grain }}</p>                -->
            <!-- 3. mode ringkasan → PanelDimensiUkuran "Kelompokkan berdasarkan"
                    (berkelompok) + PanelDimensiUkuran "Hitung"                 -->
            <!-- 4. mode rinci     → PanelDimensiUkuran "Kolom" (berkelompok)   -->
            <!-- 5. PanelSaringan                                              -->
            <!-- 6. kotak centang Grand total (hanya mode ringkasan)            -->
        </div>
    </template>
    <template #second>
        <div class="rs-pratinjau-kolom">
            <PanelPratinjau
                :status="statusPratinjau"
                :hasil="hasil"
                :pesan-galat="pesanGalat"
                :galat-definisi="galat"
                @ulangi="mintaPratinjau"
            />
        </div>
    </template>
</HcisSplitter>
```

> **Sudah diperiksa:** slot `HcisSplitter` bernama `first` dan `second`
> (`HcisSplitter.vue:94` dan `:175`), dengan `left`/`right` sebagai cadangan.
> Bukan `start`/`end`, dan `HcisSplitterPane` tidak diperlukan di sini.

Rel template dilipat:

```vue
<HcisCollapse v-model="relTerbuka" variant="flat" class="mb-3">
    <HcisCollapseItem name="galeri" title="Mulai dari template">
        <GaleriTemplate :tab-awal="tabGaleri" :data="galeri" … />
    </HcisCollapseItem>
</HcisCollapse>
```

Ringkasan yang dipancarkan ke halaman:

```js
const emit = defineEmits(['ringkas']);

const ringkas = computed(() => ({
    mode: mode.value,
    kolom: mode.value === 'ringkasan'
        ? pilihan.dimensi.length + pilihan.ukuran.length
        : pilihan.kolom.length,
    baris: hasil.value?.rows?.length ?? null,
}));

watch(ringkas, (v) => emit('ringkas', v), { deep: true, immediate: true });
```

- [ ] **Step 4: Tampilkan chip di hero**

Di `SopExportStudioPage.vue`, tangkap emit dan isi slot `console`:

```vue
<template #console>
    <div class="ses-chip-baris">
        <span class="ses-chip">Mode: {{ ringkas.mode === 'rinci' ? 'Rinci' : 'Ringkasan' }}</span>
        <span class="ses-chip">Kolom: {{ ringkas.kolom }}</span>
        <span class="ses-chip">Baris: {{ ringkas.baris ?? '—' }}</span>
    </div>
</template>
```

```js
const ringkas = ref({ mode: 'ringkasan', kolom: 0, baris: null });
```

dan `<ReportStudio … @ringkas="(v) => (ringkas = v)" />`.

Chip ketiga menulis `—` saat pratinjau belum ada. Jangan menampilkan `0`: itu
angka yang terbaca sebagai fakta padahal artinya "belum dihitung".

- [ ] **Step 5: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.test.js
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio resources/js/Pages/LMS/SOP/Export
git commit -m "feat(sop): kokpit dua panel Export Studio dengan chip ringkasan hidup"
```

---

### Task 11: Ponsel menepati janji spec

Perakit **tidak dirender** di ponsel, bukan disembunyikan CSS: tiga puluh item
`HcisTransfer` yang tak terpakai tetap membayar biaya render dan tetap
terjangkau pembaca layar.

**Files:**
- Create: `resources/js/components/SYSTEM/ReportStudio/RingkasanDefinisi.vue`
- Modify: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue`
- Test: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

**Interfaces:**
- Consumes: `pilihan`, `katalog`, `mode` dari `useDefinisi`
- Produces: `<RingkasanDefinisi :mode :pilihan :katalog />`

- [ ] **Step 1: Tulis uji yang gagal**

```js
describe('ReportStudio di ponsel', () => {
    const asliMatchMedia = window.matchMedia;

    beforeEach(() => {
        window.matchMedia = (q) => ({
            matches: true, media: q, addEventListener() {}, removeEventListener() {},
        });
    });

    // Nilai matchMedia WAJIB dipulihkan: berkas uji berbagi satu window, dan
    // kebocorannya muncul sebagai kegagalan yang berpindah-pindah di berkas lain.
    afterEach(() => { window.matchMedia = asliMatchMedia; });

    it('tidak merender perakit, dan menggantinya dengan ringkasan yang terbaca', async () => {
        const w = mount(ReportStudio, { props: { baseUrl: '/x' } });
        await flushPromises();

        w.vm.pilihan.dimensi = ['divisi'];
        await flushPromises();

        expect(w.findComponent({ name: 'PanelDimensiUkuran' }).exists()).toBe(false);
        expect(w.findComponent({ name: 'HcisTransfer' }).exists()).toBe(false);

        const ringkasan = w.findComponent({ name: 'RingkasanDefinisi' });
        expect(ringkasan.exists()).toBe(true);
        expect(ringkasan.text()).toContain('Divisi');

        // Galeri dan bilah aksi tetap hidup — itulah alur ponselnya.
        expect(w.findComponent({ name: 'GaleriTemplate' }).exists()).toBe(true);
        expect(w.findComponent({ name: 'BilahAksi' }).exists()).toBe(true);
    });
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js -t "ponsel"
```

- [ ] **Step 3: Buat `RingkasanDefinisi.vue`**

```vue
<template>
    <div class="rs-ringkasan">
        <div class="rs-ringkasan-chip">
            <span class="rs-ringkasan-kunci">Mode</span>
            <span>{{ mode === 'rinci' ? 'Rinci' : 'Ringkasan' }}</span>
        </div>

        <div v-if="mode === 'ringkasan'" class="rs-ringkasan-chip">
            <span class="rs-ringkasan-kunci">Kelompok</span>
            <span>{{ label(pilihan.dimensi, katalog?.dimensi).join(' › ') || '—' }}</span>
        </div>
        <div v-if="mode === 'ringkasan'" class="rs-ringkasan-chip">
            <span class="rs-ringkasan-kunci">Ukuran</span>
            <span>{{ label(pilihan.ukuran, katalog?.ukuran).join(', ') || '—' }}</span>
        </div>
        <div v-else class="rs-ringkasan-chip">
            <span class="rs-ringkasan-kunci">Kolom</span>
            <span>{{ label(pilihan.kolom, katalog?.dimensi).join(', ') || '—' }}</span>
        </div>

        <div class="rs-ringkasan-chip">
            <span class="rs-ringkasan-kunci">Saringan</span>
            <span>{{ pilihan.saringan.length || 'tidak ada' }}</span>
        </div>

        <p class="rs-ringkasan-catatan">
            Definisi dirakit di layar lebar. Di sini Anda tetap bisa menjalankan
            template dan mengunduh hasilnya.
        </p>
    </div>
</template>

<script setup>
defineProps({
    mode: { type: String, default: 'ringkasan' },
    pilihan: { type: Object, required: true },
    katalog: { type: Object, default: null },
});

const label = (kunci, daftar) =>
    (kunci ?? []).map((k) => (daftar ?? []).find((d) => d.key === k)?.label ?? k);
</script>

<style scoped>
.rs-ringkasan { display: flex; flex-direction: column; gap: .4rem; }
.rs-ringkasan-chip {
    display: flex;
    gap: .5rem;
    padding: .4rem .6rem;
    border: 1px solid var(--hcis-border);
    border-radius: 8px;
    background: var(--hcis-surface-soft);
    font-size: .82rem;
    color: var(--hcis-text);
}
.rs-ringkasan-kunci {
    min-width: 5.5rem;
    color: var(--hcis-text-muted);
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.rs-ringkasan-catatan { margin: .3rem 0 0; font-size: .78rem; color: var(--hcis-text-muted); }
</style>
```

- [ ] **Step 4: Tambahkan cabang ponsel di `ReportStudio.vue`**

```js
import RingkasanDefinisi from './RingkasanDefinisi.vue';

const PONSEL = '(max-width: 991.98px)';

const ponsel = ref(
    typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia(PONSEL).matches
);

function segarkanViewport() {
    ponsel.value = typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia(PONSEL).matches;
}

onMounted(() => window.addEventListener('resize', segarkanViewport));
onBeforeUnmount(() => window.removeEventListener('resize', segarkanViewport));
```

Di template, dampingi `<HcisSplitter v-if="!ponsel">` dengan:

```vue
<div v-else class="rs-ponsel">
    <RingkasanDefinisi :mode="mode" :pilihan="pilihan" :katalog="katalog" />
    <p v-if="hasil" class="rs-ponsel-hasil">
        Pratinjau terakhir: {{ hasil.rows.length }} baris · {{ hasil.columns.length }} kolom
    </p>
    <ul v-if="hasil?.catatan?.length" class="rs-catatan">
        <li v-for="(c, i) in hasil.catatan" :key="i"><i class="bi bi-info-circle"></i> {{ c }}</li>
    </ul>
</div>
```

Catatan kaki tetap ikut ke ponsel. Pemotongan baris dan kolom yang disamarkan
adalah hal yang paling berbahaya untuk disembunyikan, dan layar kecil bukan
alasan untuk menyembunyikannya.

- [ ] **Step 5: Jalankan uji, pastikan HIJAU**

```bash
npx vitest run resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
```

- [ ] **Step 6: Jalankan SELURUH suite**

```bash
npm run test:unit
```

Penjaga baru dari Task 2 menyentuh wilayah bersama. Bandingkan hasilnya dengan
baseline kegagalan non-SOP yang memang sudah ada sebelum rencana ini dimulai —
kegagalan yang bukan milik kita jangan diperbaiki diam-diam di sini, dan jangan
pula dianggap milik kita.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/SYSTEM/ReportStudio
git commit -m "feat(sop): mode ponsel Export Studio sesuai spec"
```

---

## Verifikasi akhir

- [ ] `npm run test:unit` — hijau, atau kegagalannya terbukti milik baseline
- [ ] `npx vitest run resources/js/components/ui/designSystemGuards.test.js` — hijau
- [ ] Delapan uji lama di `ReportStudio.test.js` hijau **tanpa pernah disunting**
- [ ] `npm run build` — lolos
- [ ] Buka `/sop/admin/export-studio` di peramban dan periksa langsung:
      hero bergradasi (bukan kotak polos), splitter bisa digeser, chip grup
      menyaring, bilah aksi menempel saat digulir, dialog hapus muncul,
      tombol Pusat Aktivitas membuka laci kanan, dan pada lebar 375px perakit
      benar-benar hilang diganti ringkasan
- [ ] `git status` — tidak ada berkas tak terduga yang ikut ter-stage
