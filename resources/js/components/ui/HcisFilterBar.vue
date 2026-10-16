<template>
    <div
        class="hcis-filter-bar"
        :class="[
            `hcis-filter-bar--${variant}`,
            `hcis-filter-bar--sentiment-${sentiment}`,
            `hcis-filter-bar--size-${size}`,
            {
                'hcis-filter-bar--glow': glow,
                'is-expanded': isExpanded,
                'is-mobile': isMobile,
            }
        ]"
    >
        <!-- Quick Status Chips Bar -->
        <div v-if="$slots.chips || (quickChips.length && !isMobile)" class="hcis-filter-bar__chips">
            <slot name="chips">
                <div class="hcis-filter-chips-wrapper">
                    <button
                        v-for="(chip, idx) in quickChips"
                        :key="chip.id || chip.value || idx"
                        type="button"
                        class="hcis-chip-btn"
                        :class="[
                            chip.color ? `hcis-chip-btn--${chip.color}` : '',
                            { 'is-active': isChipActive(chip) }
                        ]"
                        @click="onChipClick(chip)"
                    >
                        <span v-if="chip.ping" class="hcis-chip-ping-dot" />
                        <HcisIcon v-if="chip.icon" :name="chip.icon" size="xs" class="me-1.5" />
                        <span class="hcis-chip-label">{{ chip.label }}</span>
                        <span
                            v-if="chip.badge !== undefined && chip.badge !== null && chip.badge !== false"
                            class="hcis-chip-badge"
                        >
                            {{ chip.badge }}
                        </span>
                    </button>
                </div>
            </slot>
        </div>

        <!-- Main Row: Search Input & Action Controls -->
        <div class="hcis-filter-bar__main">
            <!-- Search Input Box -->
            <div class="hcis-filter-bar__search">
                <slot name="search">
                    <HcisField
                        id="hcis-filter-search-input"
                        :model-value="ketikanLokal"
                        :placeholder="searchPlaceholder"
                        type="search"
                        clearable
                        class="hcis-filter-search-field"
                        @update:model-value="onSearchInput"
                    >
                        <template #prefix>
                            <HcisIcon name="search" size="xs" class="text-muted" />
                        </template>

                        <template v-if="commandKey" #suffix>
                            <button
                                type="button"
                                class="hcis-filter-cmdk"
                                title="Tekan Ctrl+K untuk pencarian cepat"
                                @click.stop="$emit('command')"
                            >
                                <kbd>Ctrl</kbd><kbd>K</kbd>
                            </button>
                        </template>
                    </HcisField>
                </slot>
            </div>

            <!-- Actions Right (Filter Drawer Toggle, View Switcher, Reset, Export, Custom Actions) -->
            <div class="hcis-filter-bar__actions">
                <!-- Pengalih tampilan; di ponsel ikut pindah ke laci. -->
                <div v-if="showViewToggle && !isMobile" class="hcis-filter-view-group">
                    <button
                        v-for="opt in opsiTampilan"
                        :key="opt.key"
                        type="button"
                        class="hcis-view-btn"
                        :class="{ 'is-active': viewMode === opt.key }"
                        :title="opt.title"
                        @click="onViewModeChange(opt.key)"
                    >
                        <HcisIcon :name="opt.icon" size="xs" />
                    </button>
                </div>

                <!-- Toggle Filter Button (Desktop) -->
                <button
                    v-if="hasFilters && !isMobile"
                    type="button"
                    class="hcis-filter-toggle-btn"
                    :class="{ 'is-active': isExpanded || activeFilterCount > 0 }"
                    @click="toggleExpand"
                >
                    <HcisIcon name="funnel-fill" size="xs" />
                    <span>Filter</span>
                    <span v-if="activeFilterCount > 0" class="hcis-filter-count-badge">
                        {{ activeFilterCount }}
                    </span>
                    <HcisIcon
                        v-if="collapsible"
                        :name="isExpanded ? 'chevron-up' : 'chevron-down'"
                        size="2xs"
                        class="ms-1 text-muted"
                    />
                </button>

                <!-- Reset Filter Button -->
                <button
                    v-if="activeFilterCount > 0 || search || activeChipValue !== null"
                    type="button"
                    class="hcis-filter-reset-btn"
                    title="Reset Semua Filter"
                    @click="onReset"
                >
                    <HcisIcon name="arrow-counterclockwise" size="xs" />
                    <span>Reset</span>
                </button>

                <!-- Export Data Action Dropdown -->
                <template v-if="allowExport">
                    <div class="hcis-filter-export-dropdown">
                        <button
                            type="button"
                            class="hcis-filter-export-btn"
                            :class="{ 'is-open': showExportMenu }"
                            @click="toggleExportMenu"
                        >
                            <HcisIcon name="download" size="xs" />
                            <span>Ekspor Data</span>
                            <HcisIcon name="chevron-down" size="2xs" class="ms-0.5" />
                        </button>

                        <div v-if="showExportMenu" class="hcis-filter-export-menu" @click.stop>
                            <button type="button" class="hcis-export-item" @click="triggerExport('excel')">
                                <HcisIcon name="file-earmark-excel-fill" size="xs" class="text-emerald-600 me-2" />
                                <span>Ekspor Excel (.xlsx)</span>
                            </button>
                            <button type="button" class="hcis-export-item" @click="triggerExport('pdf')">
                                <HcisIcon name="file-earmark-pdf-fill" size="xs" class="text-rose-600 me-2" />
                                <span>Ekspor Dokumen PDF</span>
                            </button>
                            <button type="button" class="hcis-export-item" @click="triggerExport('csv')">
                                <HcisIcon name="file-earmark-text-fill" size="xs" class="text-indigo-600 me-2" />
                                <span>Ekspor CSV Data</span>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Extra Custom Actions Slot -->
                <slot name="actions" />
            </div>
        </div>

        <!--
            Jumlah hasil. Tanpa angka ini, menyaring terasa seperti menebak:
            orang tidak tahu apakah saringannya berdampak, dan tidak tahu
            apakah nol hasil berarti "tidak ada" atau "saringannya salah".
        -->
        <p v-if="resultsCount !== null" class="hcis-filter-bar__hasil" aria-live="polite">
            <HcisIcon :name="resultsCount === 0 ? 'exclamation-circle' : 'list-ul'" size="2xs" />
            <template v-if="resultsCount === 0">
                Tidak ada hasil untuk saringan ini
            </template>
            <template v-else>
                Menampilkan <strong>{{ resultsCount }}</strong> hasil
            </template>
        </p>

        <!-- Active Filter Tags Bar -->
        <div v-if="showActiveTags && activeTags.length > 0" class="hcis-filter-bar__active-tags">
            <div class="hcis-tags-header">
                <span class="hcis-tags-label me-2">
                    <HcisIcon name="funnel" size="2xs" class="me-1" /> Filter Aktif:
                </span>
                <div class="hcis-tags-container">
                    <span
                        v-for="tag in activeTags"
                        :key="tag.key"
                        class="hcis-active-tag-pill"
                    >
                        <strong class="hcis-tag-key">{{ tag.label }}:</strong>
                        <span class="hcis-tag-val">{{ tag.value }}</span>
                        <button type="button" class="hcis-tag-close-btn" title="Hapus filter ini" @click="removeFilter(tag.key)">
                            <HcisIcon name="x-lg" size="2xs" />
                        </button>
                    </span>
                    <button type="button" class="hcis-clear-all-tags-btn" @click="onReset">
                        Bersihkan Semua
                    </button>
                </div>
            </div>
        </div>

        <!-- Collapsible Advanced Filters Drawer / Grid Row (desktop) -->
        <Transition name="hcis-filter-expand">
            <div v-if="hasFilters && !isMobile && (isExpanded || !collapsible)" class="hcis-filter-bar__filters">
                <slot name="filters">
                    <div class="hcis-filter-grid">
                        <template v-for="f in filters" :key="f.key">
                            <div class="hcis-filter-col" :style="gayaKolom(f)">
                                <HcisFilterField
                                    :filter="f"
                                    :model-value="filterValues[f.key]"
                                    @update:model-value="(v) => onFilterSelectChange(f.key, v)"
                                />
                            </div>
                        </template>
                    </div>
                </slot>
            </div>
        </Transition>

        <!--
            ══ MODE PONSEL ══════════════════════════════════════════════════
            Saringan tidak ditampilkan sebaris di layar sempit — ia pindah ke
            tombol apung dan laci bawah.
        -->
        <Teleport v-if="adaLaci && isMobile" to="body">
            <button
                type="button"
                class="hcis-filter-fab"
                :aria-label="jumlahLaci > 0 ? `Filter, ${jumlahLaci} aktif` : 'Filter'"
                @click="bukaLaci"
            >
                <HcisIcon name="funnel-fill" size="sm" />
                <span>Filter</span>
                <span v-if="jumlahLaci > 0" class="hcis-filter-fab__badge">{{ jumlahLaci }}</span>
            </button>
        </Teleport>

        <HcisDrawer
            v-if="adaLaci && isMobile"
            v-model="laciTerbuka"
            direction="btt"
            size="85vh"
            title="Filter & Saringan"
            :description="jumlahLaci > 0 ? `${jumlahLaci} saringan aktif` : 'Sesuaikan kriteria pencarian'"
            icon="bi bi-sliders"
            @close="batalkanDraf"
        >
            <div class="hcis-filter-sheet">
                <div v-if="quickChips.length" class="hcis-filter-sheet__field">
                    <span class="hcis-filter-sheet__judul">
                        <HcisIcon name="lightning-charge-fill" size="2xs" /> Saringan Cepat
                    </span>
                    <div class="hcis-filter-sheet__chips">
                        <button
                            v-for="(chip, idx) in quickChips"
                            :key="chip.id || chip.value || idx"
                            type="button"
                            class="hcis-chip-btn"
                            :class="[
                                chip.color ? `hcis-chip-btn--${chip.color}` : '',
                                { 'is-active': drafChip === chip.value }
                            ]"
                            @click="drafChip = chip.value"
                        >
                            <HcisIcon v-if="chip.icon" :name="chip.icon" size="xs" class="me-1.5" />
                            <span class="hcis-chip-label">{{ chip.label }}</span>
                            <span
                                v-if="chip.badge !== undefined && chip.badge !== null && chip.badge !== false"
                                class="hcis-chip-badge"
                            >
                                {{ chip.badge }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Pengalih tampilan -->
                <div v-if="showViewToggle" class="hcis-filter-sheet__field">
                    <span class="hcis-filter-sheet__judul">
                        <HcisIcon name="columns-gap" size="2xs" /> Tampilan
                    </span>
                    <div class="hcis-filter-sheet__tampilan">
                        <button
                            v-for="opt in opsiTampilan"
                            :key="opt.key"
                            type="button"
                            class="hcis-filter-sheet__tampilan-btn"
                            :class="{ 'is-active': drafView === opt.key }"
                            @click="drafView = opt.key"
                        >
                            <HcisIcon :name="opt.icon" size="sm" /> {{ opt.label }}
                        </button>
                    </div>
                </div>

                <div v-for="f in filters" :key="f.key" class="hcis-filter-sheet__field">
                    <HcisFilterField
                        :filter="f"
                        :model-value="draf[f.key]"
                        @update:model-value="(v) => (draf[f.key] = v)"
                    />
                </div>
            </div>

            <template #footer>
                <div class="hcis-filter-sheet__aksi">
                    <button
                        v-if="activeFilterCount > 0"
                        type="button"
                        class="hcis-filter-sheet__reset"
                        @click="resetDariLaci"
                    >
                        <HcisIcon name="arrow-counterclockwise" size="xs" /> Reset
                    </button>

                    <button type="button" class="hcis-filter-sheet__terapkan" @click="terapkanDraf">
                        <HcisIcon name="check-lg" size="sm" />
                        Terapkan{{ jumlahDraf > 0 ? ` (${jumlahDraf})` : '' }}
                    </button>
                </div>
            </template>
        </HcisDrawer>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisField from './HcisField.vue';
import HcisFilterField from './HcisFilterField.vue';
import HcisDrawer from './HcisDrawer.vue';

defineOptions({ name: 'HcisFilterBar' });

const props = defineProps({
    search: { type: String, default: '' },
    searchPlaceholder: { type: String, default: 'Cari berdasarkan kata kunci...' },
    filters: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
    quickChips: { type: Array, default: () => [] },
    activeChipValue: { type: [String, Number], default: null },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'flat', 'glass', 'hero', 'obsidian'].includes(v),
    },
    sentiment: {
        type: String,
        default: 'primary',
        validator: (v) => [
            'primary', 'brand', 'purple', 'violet', 'teal', 'cyan',
            'danger', 'warning', 'success', 'info', 'neutral', 'obsidian'
        ].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    glow: { type: Boolean, default: false },
    collapsible: { type: Boolean, default: true },
    commandKey: { type: Boolean, default: false },
    allowExport: { type: Boolean, default: false },
    showViewToggle: { type: Boolean, default: false },
    viewMode: { type: String, default: 'table' },
    viewOptions: { type: Array, default: null },
    resultsCount: { type: Number, default: null },
    searchDebounce: { type: Number, default: 300 },
    showActiveTags: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:search',
    'update:viewMode',
    'filter-change',
    'chip-click',
    'reset',
    'export',
    'command',
]);

const isExpanded = ref(!props.collapsible);
const showExportMenu = ref(false);
const isMobile = ref(false);

const hasFilters = computed(() => props.filters.length > 0);

const opsiTampilan = computed(() => {
    if (props.viewOptions && props.viewOptions.length > 0) {
        return props.viewOptions.map((opt) => ({
            key: opt.key || opt.value || opt.id,
            label: opt.label || opt.title || opt.key,
            icon: opt.icon || opt.ikon || (opt.key === 'table' || opt.key === 'tabel' ? 'table' : (opt.key === 'dokumen' ? 'file-earmark-text-fill' : 'grid-fill')),
            title: opt.title || opt.judul || opt.label || opt.key,
        }));
    }
    return [
        { key: 'table', label: 'Tabel', icon: 'table', title: 'Tampilan Tabel' },
        { key: 'grid', label: 'Kartu', icon: 'grid-fill', title: 'Tampilan Grid Kartu' },
    ];
});

const activeFilterCount = computed(() => {
    let count = 0;
    props.filters.forEach((f) => {
        const val = props.filterValues[f.key];
        if (bernilai(val)) count++;
    });
    return count;
});

const activeTags = computed(() => {
    const tags = [];
    props.filters.forEach((f) => {
        const val = props.filterValues[f.key];
        if (bernilai(val)) {
            let displayVal = val;
            if (f.type === 'select' && f.options) {
                if (Array.isArray(val)) {
                    const optLabels = val.map((v) => {
                        const opt = f.options.find((o) => String(o.value) === String(v));
                        return opt ? opt.label : v;
                    });
                    displayVal = optLabels.join(', ');
                } else {
                    const opt = f.options.find((o) => String(o.value) === String(val));
                    if (opt) displayVal = opt.label;
                }
            } else if (Array.isArray(val)) {
                displayVal = val.join(' s/d ');
            }
            tags.push({ key: f.key, label: f.label, value: displayVal });
        }
    });
    return tags;
});

function isChipActive(chip) {
    if (props.activeChipValue !== null) {
        return props.activeChipValue === chip.value;
    }
    return chip.active || false;
}

function onChipClick(chip) {
    emit('chip-click', chip);
}

/*
 * Pencarian ditunda; sisanya tidak.
 *
 * Mengetik menghasilkan satu peristiwa per huruf, dan tiap peristiwa memicu
 * penyaringan penuh — di daftar besar itu membuat kursor tersendat. Memilih
 * dari dropdown atau menekan pil hanya sekali, jadi menundanya justru membuat
 * antarmuka terasa lamban tanpa alasan.
 *
 * Nilai yang diketik ditahan lokal supaya kotaknya tetap responsif selama
 * jedanya berjalan — kalau tidak, huruf akan terasa "balik" saat prop induk
 * belum menyusul.
 */
const ketikanLokal = ref(props.search);
let jedaCari = null;

watch(
    () => props.search,
    (v) => {
        if (v !== ketikanLokal.value) ketikanLokal.value = v;
    },
);

function onSearchInput(val) {
    ketikanLokal.value = val;

    if (!props.searchDebounce) {
        emit('update:search', val);
        return;
    }

    clearTimeout(jedaCari);
    jedaCari = setTimeout(() => emit('update:search', val), props.searchDebounce);
}

onUnmounted(() => clearTimeout(jedaCari));

/* ── Laci saringan di ponsel ─────────────────────────────────────────── */

const laciTerbuka = ref(false);

/*
 * Salinan kerja. Nilai asli TIDAK disentuh sampai "Terapkan" ditekan, supaya
 * menutup laci lewat tirai, ESC, atau tombol kembali membatalkan perubahan —
 * bukan menerapkannya diam-diam.
 */
const draf = ref({});
const drafChip = ref(null);
const drafView = ref(props.viewMode);
watch(
    () => props.viewMode,
    (v) => {
        drafView.value = v;
    },
);

/*
 * Laci dibuka kalau ADA yang bisa diatur di dalamnya — bukan hanya kalau ada
 * `filters`. Pil dan pengalih tampilan ikut pindah ke sini di layar sempit,
 * jadi halaman yang cuma punya pil pun tetap butuh pintunya; tanpa ini pilnya
 * hilang sama sekali di ponsel.
 */
const adaLaci = computed(
    () => props.filters.length > 0 || props.quickChips.length > 0 || props.showViewToggle,
);

/** Lencana tombol apung: saringan terisi + pil yang sedang dipilih. */
const jumlahLaci = computed(
    () => activeFilterCount.value + (props.activeChipValue !== null ? 1 : 0),
);

function bukaLaci() {
    draf.value = { ...props.filterValues };
    drafChip.value = props.activeChipValue;
    drafView.value = props.viewMode;
    laciTerbuka.value = true;
}

/** Jumlah saringan terisi di dalam draf — dipakai di label tombol Terapkan. */
const jumlahDraf = computed(
    () => Object.values(draf.value).filter((v) => bernilai(v)).length,
);

function terapkanDraf() {
    /*
     * Dipancarkan per kunci yang BERUBAH saja, bukan seluruhnya. Pemanggil
     * yang menyaring ulang pada tiap filter-change karena itu bekerja
     * sekali per perubahan nyata, bukan sekali per kolom yang ada.
     */
    for (const [key, value] of Object.entries(draf.value)) {
        if (sama(value, props.filterValues[key])) continue;
        emit('filter-change', { key, value, values: { ...draf.value } });
    }

    if (drafChip.value !== props.activeChipValue) {
        const chip = props.quickChips.find((c) => c.value === drafChip.value);
        // Dipancarkan chip-nya, bukan nilainya — kontraknya sama persis dengan
        // klik pil di layar lebar, jadi pemanggil tidak perlu tahu dari mana
        // pilihannya datang.
        if (chip) emit('chip-click', chip);
    }

    if (drafView.value !== props.viewMode) {
        emit('update:viewMode', drafView.value);
    }

    laciTerbuka.value = false;
}

function batalkanDraf() {
    draf.value = { ...props.filterValues };
    drafChip.value = props.activeChipValue;
    drafView.value = props.viewMode;
}

function resetDariLaci() {
    onReset();
    laciTerbuka.value = false;
}

/** Terisi = bukan kosong, bukan larik kosong. */
function bernilai(v) {
    if (v === null || v === undefined || v === '') return false;
    if (Array.isArray(v)) return v.length > 0;
    return true;
}

/** Perbandingan dangkal yang cukup untuk nilai saringan (skalar & larik). */
function sama(a, b) {
    if (Array.isArray(a) && Array.isArray(b)) {
        return a.length === b.length && a.every((x, i) => x === b[i]);
    }
    return a === b;
}

/**
 * Lebar kolom dari `col` (satuan 12). Tanpa ini semua saringan selebar sama,
 * dan rentang tanggal — yang butuh dua kali ruang dropdown — jadi sempit.
 *
 * Yang dikirim hanya VARIABEL, bukan `grid-column` langsung. Bedanya
 * menentukan: gaya inline mengalahkan media query, jadi menulis
 * `grid-column: span 3` di sini akan mengunci lebar itu sampai ke layar
 * ponsel — responsifnya mati diam-diam, dan tidak ada cara menimpanya dari
 * stylesheet selain !important.
 *
 * Dengan variabel, media query di bawah bisa menyetel `grid-column` sendiri
 * dan menang, karena keduanya tidak berebut properti yang sama.
 */
function gayaKolom(f) {
    const col = Number(f.col);
    if (!Number.isFinite(col) || col < 1 || col > 12) return {};
    return { '--hcis-filter-col': Math.round(col) };
}

function onViewModeChange(mode) {
    emit('update:viewMode', mode);
}

function onFilterSelectChange(key, val) {
    emit('filter-change', { key, value: val, values: { ...props.filterValues, [key]: val } });
}

function onFilterInputChange(key, val) {
    emit('filter-change', { key, value: val, values: { ...props.filterValues, [key]: val } });
}

function removeFilter(key) {
    const newValues = { ...props.filterValues, [key]: '' };
    emit('filter-change', { key, value: '', values: newValues });
}

function toggleExpand() {
    isExpanded.value = !isExpanded.value;
}

function onReset() {
    emit('update:search', '');
    emit('reset');
}

function toggleExportMenu() {
    showExportMenu.value = !showExportMenu.value;
}

function triggerExport(formatKey) {
    showExportMenu.value = false;
    emit('export', formatKey);
}

function checkMobile() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 768;
    }
}

onMounted(() => {
    checkMobile();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', checkMobile);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', checkMobile);
    }
});
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS FILTER BAR — LUXURY CRYSTAL COCKPIT ARCHITECTURE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-filter-bar {
    width: 100%;
    border-radius: var(--hcis-radius-xl, 16px);
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1), box-shadow 260ms ease, border-color 260ms ease;
    position: relative;
}

/* ── SIZE TIERS (sm, md, lg) ─────────────────────────────────────────── */
.hcis-filter-bar--size-sm {
    padding: 0.625rem 0.875rem;
}
.hcis-filter-bar--size-md {
    padding: 0.875rem 1.125rem;
}
.hcis-filter-bar--size-lg {
    padding: 1.125rem 1.375rem;
}

/* ── 1. VARIANT STYLES (card, glass, hero, flat, obsidian) ─────────────── */

/* Variant: Card (Solid Executive Crystal Surface) */
.hcis-filter-bar--card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-medium);
    box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.03),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

/* Variant: Glass (Frosted Acrylic Crystal) */
.hcis-filter-bar--glass {
    background: var(--hcis-glass-bg);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid var(--hcis-border-medium);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.7) inset,
        0 8px 24px -4px rgba(15, 23, 42, 0.08),
        0 16px 36px -8px rgba(15, 23, 42, 0.06);
}

/* Variant: Hero (Luminescent Gradient Accent) */
.hcis-filter-bar--hero {
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    border: 1px solid rgba(199, 210, 254, 0.9);
    box-shadow:
        0 0 0 1px rgba(99, 102, 241, 0.12),
        0 4px 20px -2px rgba(99, 102, 241, 0.1),
        0 12px 30px -6px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

/* Variant: Flat (Subtle Minimalist) */
.hcis-filter-bar--flat {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-medium);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
}

/* Variant: Obsidian (Cosmic Midnight Dark Mode) */
.hcis-filter-bar--obsidian,
.hcis-filter-bar--sentiment-obsidian {
    background: linear-gradient(180deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%) !important;
    color: var(--hcis-slate-50) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.06) inset,
        0 12px 30px -5px rgba(0, 0, 0, 0.5),
        0 24px 50px -10px rgba(0, 0, 0, 0.4) !important;
}

/* ── 2. LUMINOUS AMBIENT GLOW GRADIENT MATRIX ────────────────────────── */

.hcis-filter-bar--glow.hcis-filter-bar--sentiment-primary,
.hcis-filter-bar--glow.hcis-filter-bar--sentiment-brand {
    border-color: rgba(199, 210, 254, 0.9);
    box-shadow:
        0 0 0 1px rgba(99, 102, 241, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(99, 102, 241, 0.16),
        0 0 80px 10px rgba(79, 70, 229, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-filter-bar--glow.hcis-filter-bar--sentiment-purple,
.hcis-filter-bar--glow.hcis-filter-bar--sentiment-violet {
    border-color: rgba(221, 214, 254, 0.9);
    box-shadow:
        0 0 0 1px rgba(168, 85, 247, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(168, 85, 247, 0.16),
        0 0 80px 10px rgba(124, 58, 237, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-filter-bar--glow.hcis-filter-bar--sentiment-teal,
.hcis-filter-bar--glow.hcis-filter-bar--sentiment-cyan {
    border-color: rgba(178, 242, 233, 0.9);
    box-shadow:
        0 0 0 1px rgba(20, 184, 166, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(20, 184, 166, 0.16),
        0 0 80px 10px rgba(13, 148, 136, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-filter-bar--glow.hcis-filter-bar--sentiment-danger {
    border-color: rgba(254, 202, 202, 0.9);
    box-shadow:
        0 0 0 1px rgba(239, 68, 68, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(239, 68, 68, 0.16),
        0 0 80px 10px rgba(220, 38, 38, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-filter-bar--glow.hcis-filter-bar--sentiment-warning {
    border-color: rgba(253, 230, 138, 0.9);
    box-shadow:
        0 0 0 1px rgba(245, 158, 11, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(245, 158, 11, 0.16),
        0 0 80px 10px rgba(217, 119, 6, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-filter-bar--glow.hcis-filter-bar--sentiment-success {
    border-color: rgba(187, 247, 208, 0.9);
    box-shadow:
        0 0 0 1px rgba(16, 185, 129, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(16, 185, 129, 0.16),
        0 0 80px 10px rgba(5, 150, 105, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-filter-bar--glow.hcis-filter-bar--sentiment-obsidian,
.hcis-filter-bar--glow.hcis-filter-bar--obsidian {
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.08) inset,
        0 12px 30px -5px rgba(0, 0, 0, 0.5),
        0 0 35px 0 rgba(99, 102, 241, 0.25),
        0 0 80px 10px rgba(124, 58, 237, 0.12) !important;
}

/* ── 3. QUICK CHIPS BAR (Interactive Pulse Chips) ─────────────────────── */

.hcis-filter-bar__chips {
    margin-bottom: 0.875rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.75);
}

.hcis-filter-chips-wrapper {
    display: flex;
    align-items: center;
    gap: 0.4375rem;
    overflow-x: auto;
    padding-bottom: 0.125rem;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.hcis-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.3125rem 0.75rem;
    border-radius: 9999px;
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--hcis-text-strong, var(--hcis-slate-700));
    font-size: 0.78125rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition-property: var(--hcis-transition-props), backdrop-filter;
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.hcis-chip-btn:hover {
    background: var(--hcis-slate-50);
    border-color: rgba(199, 210, 254, 0.9);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    transform: translateY(-1px);
    box-shadow: 0 3px 8px -1px rgba(99, 102, 241, 0.15);
}

.hcis-chip-btn.is-active {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-white);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

/* Color-Specific Chip Variations */
.hcis-chip-btn--success.is-active {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-500) 100%);
    border-color: var(--hcis-emerald-600);
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3);
}
.hcis-chip-btn--warning.is-active {
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
    border-color: var(--hcis-amber-600);
    box-shadow: 0 4px 14px -2px rgba(245, 158, 11, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3);
}
.hcis-chip-btn--danger.is-active {
    background: linear-gradient(135deg, var(--hcis-red-600) 0%, var(--hcis-red-500) 100%);
    border-color: var(--hcis-red-600);
    box-shadow: 0 4px 14px -2px rgba(239, 68, 68, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3);
}
.hcis-chip-btn--purple.is-active {
    background: linear-gradient(135deg, var(--hcis-violet-600) 0%, #8b5cf6 100%);
    border-color: var(--hcis-violet-600);
    box-shadow: 0 4px 14px -2px rgba(139, 92, 246, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3);
}
.hcis-chip-btn--teal.is-active {
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
    border-color: #0d9488;
    box-shadow: 0 4px 14px -2px rgba(20, 184, 166, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

.hcis-chip-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.125rem;
    height: 1.125rem;
    padding: 0 0.375rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    background: rgba(224, 231, 255, 0.9);
    color: var(--hcis-indigo-800);
    transition-property: var(--hcis-transition-props), backdrop-filter;
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-chip-btn.is-active .hcis-chip-badge {
    background: rgba(255, 255, 255, 0.25);
    color: var(--hcis-white);
    backdrop-filter: blur(4px);
}

/* Radar Ping Dot */
.hcis-chip-ping-dot {
    position: relative;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-emerald-500);
}
.hcis-chip-ping-dot::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    background-color: var(--hcis-emerald-500);
    opacity: 0.75;
    animation: hcisChipPing 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
}
@keyframes hcisChipPing {
    75%, 100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

/* ── 4. MAIN ROW & SEARCH CONTROLS ───────────────────────────────────── */

.hcis-filter-bar__main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.hcis-filter-bar__search {
    flex: 1 1 240px;
    min-width: 0;
}

.hcis-filter-search-field {
    margin-bottom: 0;
}

.hcis-filter-cmdk {
    display: inline-flex;
    align-items: center;
    gap: 0.1875rem;
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 6px;
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-100) 100%);
    padding: 0.1875rem 0.4375rem;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;

    kbd {
        font-family: var(--hcis-font-mono, monospace);
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--hcis-slate-600);
        background: transparent;
        border: none;
        padding: 0;
        line-height: 1;
    }
}

.hcis-filter-cmdk:hover {
    background: linear-gradient(180deg, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
    border-color: var(--hcis-slate-400);
    transform: translateY(1px);
    box-shadow: 0 0 0 rgba(15, 23, 42, 0.08);

    kbd {
        color: var(--hcis-slate-900);
    }
}

.hcis-filter-bar__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ── 5. SEGMENTED VIEW SWITCHER ──────────────────────────────────────── */

.hcis-filter-view-group {
    display: flex;
    align-items: center;
    padding: 3px;
    border-radius: 9px;
    background: rgba(241, 245, 249, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.9);
    gap: 2px;
}

.hcis-view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-view-btn:hover {
    color: var(--hcis-slate-800);
    background: rgba(255, 255, 255, 0.6);
}

.hcis-view-btn.is-active {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.1), 0 0 0 1px rgba(15, 23, 42, 0.04);
}

/* ── 6. FILTER TOGGLE BUTTON (JEWEL BADGE) ───────────────────────────── */

.hcis-filter-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4375rem;
    height: 2.25rem;
    padding: 0 0.875rem;
    border-radius: 9px;
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-filter-toggle-btn:hover {
    border-color: rgba(199, 210, 254, 0.9);
    color: var(--hcis-indigo-700);
    background: var(--hcis-slate-50);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px -1px rgba(99, 102, 241, 0.15);
}

.hcis-filter-toggle-btn.is-active {
    border-color: var(--hcis-indigo-600);
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(99, 102, 241, 0.12) 100%);
    color: var(--hcis-indigo-700);
    box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.2);
}

.hcis-filter-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.1875rem;
    height: 1.1875rem;
    padding: 0 0.3125rem;
    border-radius: 9999px;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    color: var(--hcis-white);
    font-size: 0.6875rem;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
}

/* ── 7. RESET BUTTON ─────────────────────────────────────────────────── */

.hcis-filter-reset-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    height: 2.25rem;
    padding: 0 0.75rem;
    border-radius: 9px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-filter-reset-btn:hover {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-700);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px -1px rgba(239, 68, 68, 0.15);
}

/* ── 8. EXPORT DROPDOWN MENU ─────────────────────────────────────────── */

.hcis-filter-export-dropdown {
    position: relative;
}

.hcis-filter-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4375rem;
    height: 2.25rem;
    padding: 0 0.875rem;
    border-radius: 9px;
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-filter-export-btn:hover,
.hcis-filter-export-btn.is-open {
    border-color: rgba(199, 210, 254, 0.9);
    color: var(--hcis-indigo-700);
    background: var(--hcis-slate-50);
    box-shadow: 0 3px 8px -1px rgba(99, 102, 241, 0.15);
}

.hcis-filter-export-menu {
    position: absolute;
    top: calc(100% + 0.375rem);
    right: 0;
    z-index: 50;
    min-width: 13.5rem;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 12px;
    box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.04),
        0 12px 32px -4px rgba(15, 23, 42, 0.16),
        0 4px 12px -2px rgba(15, 23, 42, 0.08);
    padding: 0.375rem;
    display: flex;
    flex-direction: column;
    gap: 0.1875rem;
    animation: hcisExportFadeIn 160ms cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes hcisExportFadeIn {
    from {
        opacity: 0;
        transform: translateY(-6px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.hcis-export-item {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--hcis-slate-700);
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    text-align: left;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-export-item:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
    transform: translateX(2px);
}

/* ── 9. ACTIVE FILTER TAGS BAR ───────────────────────────────────────── */

.hcis-filter-bar__active-tags {
    margin-top: 0.875rem;
}

.hcis-tags-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.625rem;
    padding-top: 0.875rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.85);
}

.hcis-tags-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.hcis-tags-container {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    flex-wrap: wrap;
}

.hcis-active-tag-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3125rem;
    padding: 0.25rem 0.625rem;
    border-radius: 8px;
    background: rgba(238, 242, 255, 0.95);
    border: 1px solid rgba(199, 210, 254, 0.85);
    font-size: 0.75rem;
    box-shadow: 0 1px 2px rgba(79, 70, 229, 0.05);
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: ease;
}

.hcis-tag-key {
    color: var(--hcis-indigo-700);
    font-weight: 700;
}

.hcis-tag-val {
    color: var(--hcis-indigo-950);
    font-weight: 600;
}

.hcis-tag-close-btn {
    border: none;
    background: transparent;
    color: var(--hcis-indigo-500);
    padding: 0 0.125rem;
    cursor: pointer;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-tag-close-btn:hover {
    color: var(--hcis-red-600);
    background: rgba(239, 68, 68, 0.12);
}

.hcis-clear-all-tags-btn {
    border: none;
    background: transparent;
    color: var(--hcis-red-600);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-clear-all-tags-btn:hover {
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
}

/* ── 10. COLLAPSIBLE FILTER GRID MATRIX ──────────────────────────────── */

.hcis-filter-bar__filters {
    margin-top: 1rem;
}

.hcis-filter-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 1rem 1.125rem;
    padding-top: 1.125rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-filter-col {
    grid-column: span var(--hcis-filter-col, 3);
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

@media (max-width: 991.98px) {
    .hcis-filter-col {
        grid-column: span 6;
    }
}

@media (max-width: 575.98px) {
    .hcis-filter-col {
        grid-column: span 12;
    }
    .hcis-filter-bar__main {
        flex-wrap: nowrap;
        gap: 0.45rem;
    }
    .hcis-filter-bar__search {
        flex: 1 1 auto;
        min-width: 0;
    }
    .hcis-filter-bar__actions {
        flex-shrink: 0;
        flex-wrap: nowrap;
        gap: 0.35rem;
    }
    .hcis-filter-reset-btn {
        width: 2.25rem;
        height: 2.25rem;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        flex-shrink: 0;
    }
    .hcis-filter-reset-btn span {
        display: none;
    }
}

@media (max-width: 380px) {
    .hcis-filter-bar__main {
        gap: 0.35rem;
    }
    .hcis-filter-reset-btn {
        width: 2rem;
        height: 2rem;
        border-radius: 6px;
    }
}

/* Transition Expand */
.hcis-filter-expand-enter-active,
.hcis-filter-expand-leave-active {
    transition-property: var(--hcis-transition-props);
    transition-duration: 260ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

.hcis-filter-expand-enter-from,
.hcis-filter-expand-leave-to {
    opacity: 0;
    transform: translateY(-8px);
    max-height: 0;
}

.hcis-filter-expand-enter-to,
.hcis-filter-expand-leave-from {
    opacity: 1;
    transform: translateY(0);
    max-height: 600px;
}

.hcis-filter-bar__hasil {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    margin: 0.625rem 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.75rem;
    font-weight: 500;
}

/* ── 11. MOBILE BOTTOM SHEET & FAB ───────────────────────────────────── */

.hcis-filter-fab {
    position: fixed;
    right: 0.85rem;
    bottom: calc(0.85rem + env(safe-area-inset-bottom, 0px));
    z-index: 1040;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    height: 2.25rem;
    padding: 0 0.85rem;
    border: 0;
    border-radius: 9999px;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    color: var(--hcis-white);
    font-family: inherit;
    font-size: 0.78125rem;
    font-weight: 750;
    cursor: pointer;
    box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.25) inset;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.hcis-filter-fab:active {
    transform: scale(0.95);
}

.hcis-filter-fab__badge {
    display: inline-grid;
    place-items: center;
    min-width: 1.1rem;
    height: 1.1rem;
    padding: 0 0.25rem;
    border-radius: 9999px;
    background: var(--hcis-white);
    color: var(--hcis-indigo-700);
    font-size: 0.625rem;
    font-weight: 800;
    line-height: 1;
}

.hcis-filter-sheet {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.hcis-filter-sheet__field {
    padding: 0.55rem 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 10px;
    background: var(--hcis-slate-50);
}

.hcis-filter-sheet__aksi {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    width: 100%;
}

.hcis-filter-sheet__reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    height: 2.35rem;
    padding: 0 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 8px;
    background: var(--hcis-white);
    color: var(--hcis-slate-500);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 700;
    cursor: pointer;
    flex-shrink: 0;
}

.hcis-filter-sheet__terapkan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    flex: 1;
    height: 2.35rem;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    color: var(--hcis-white);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 3px 10px -2px rgba(79, 70, 229, 0.4);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-filter-sheet__terapkan:active {
    transform: scale(0.98);
}

.hcis-filter-sheet__judul {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.4rem;
    color: var(--hcis-slate-500);
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.hcis-filter-sheet__chips {
    display: flex;
    gap: 0.3rem;
    overflow-x: auto;
    padding: 0.15rem 0;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.hcis-filter-sheet__chips::-webkit-scrollbar {
    display: none;
}

.hcis-filter-sheet__chips .hcis-chip-btn {
    flex-shrink: 0;
}

.hcis-filter-sheet__tampilan {
    display: flex;
    gap: 0.25rem;
    padding: 2px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 8px;
    background: var(--hcis-white);
}

.hcis-filter-sheet__tampilan-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    height: 2rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--hcis-slate-500);
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: ease;
}

.hcis-filter-sheet__tampilan-btn.is-active {
    background: rgba(238, 242, 255, 0.95);
    color: var(--hcis-indigo-700);
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.1);
}
</style>
