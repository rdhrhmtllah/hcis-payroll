<template>
    <div class="dip">
        <div v-if="!halaman.length" class="dip-kosong">
            <HcisIcon name="shield-slash" size="md" />
            <span>Peran ini belum memiliki hak akses halaman.</span>
        </div>

        <template v-else>
            <!-- Kontrol Bar: Filter Pencarian Mini & Buka/Tutup Semua -->
            <div class="dip-toolbar">
                <div class="dip-search-wrap">
                    <HcisIcon name="search" size="xs" class="dip-search-icon" />
                    <input
                        v-model="kataKunci"
                        type="text"
                        class="dip-search-input"
                        placeholder="Cari halaman atau menu…"
                    />
                    <button
                        v-if="kataKunci"
                        type="button"
                        class="dip-search-clear"
                        title="Hapus kata kunci"
                        @click="kataKunci = ''"
                    >
                        <HcisIcon name="x-lg" size="xs" />
                    </button>
                </div>

                <div class="dip-toolbar-actions">
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        :leading-icon="semuaTerbuka ? 'chevron-contract' : 'chevron-expand'"
                        :label="semuaTerbuka ? 'Tutup Semua' : 'Buka Semua'"
                        @click="toggleSemua"
                    />
                </div>
            </div>

            <!-- List Halaman Terkelompok dengan HcisCollapse (Bisa Buka Tutup) -->
            <div v-if="!kelompokTersaring.length" class="dip-kosong py-3">
                <HcisIcon name="search" size="sm" />
                <span>Tidak ada halaman yang cocok dengan kata kunci "{{ kataKunci }}".</span>
            </div>

            <HcisCollapse v-else v-model="activeSections" variant="card" class="dip-collapse">
                <HcisCollapseItem
                    v-for="g in kelompokTersaring"
                    :key="g.header"
                    :name="g.header"
                    class="dip-collapse-item"
                >
                    <template #title>
                        <div class="d-flex align-items-center gap-2.5 min-w-0">
                            <div class="dip-group-icon">
                                <HcisIcon name="folder2-open" size="xs" />
                            </div>
                            <span class="dip-group-title text-truncate">{{ g.header }}</span>
                            <HcisBadge
                                :label="`${g.halaman.length}`"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                                class="dip-group-count"
                            />
                        </div>
                    </template>

                    <template #default>
                        <div class="dip-halaman-list">
                            <div v-for="h in g.halaman" :key="h.jenis_page" class="dip-item-row">
                                <div class="dip-item-info min-w-0">
                                    <div class="d-flex align-items-center gap-1.5 min-w-0">
                                        <span class="dip-nama text-truncate" :title="h.jenis_page">
                                            {{ h.nama_menu }}
                                        </span>
                                        <HcisBadge
                                            v-if="baru.has(kunci(h))"
                                            label="Baru"
                                            variant="success"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                    </div>
                                    <span v-if="h.jenis_page && h.jenis_page !== h.nama_menu" class="dip-sub-code">
                                        {{ h.jenis_page }}
                                    </span>
                                </div>

                                <div class="dip-actions-wrap">
                                    <template v-if="h.aksi && h.aksi.length">
                                        <span
                                            v-for="a in h.aksi"
                                            :key="a"
                                            class="dip-action-pill"
                                            :class="aksiClass(a)"
                                        >
                                            {{ a }}
                                        </span>
                                    </template>
                                    <span v-else class="dip-action-pill dip-action-pill--view"> VIEW </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </HcisCollapseItem>
            </HcisCollapse>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisBadge, HcisButton, HcisCollapse, HcisCollapseItem, HcisIcon } from '@/components/ui';

/** Daftar halaman × aksi milik peran, dikelompokkan per header menu dengan kemampuan buka tutup accordion. */
const props = defineProps({
    halaman: { type: Array, default: () => [] },
    /** jenis_page (huruf kecil) yang baru bagi karyawan — ditandai "Baru" */
    baru: { type: Set, default: () => new Set() },
});

const kataKunci = ref('');
const activeSections = ref([]);

const kunci = (h) => String(h.jenis_page || '').toLowerCase();

const kelompok = computed(() => {
    const per = new Map();
    for (const h of props.halaman) {
        const k = h.header || 'Lainnya';
        if (!per.has(k)) per.set(k, []);
        per.get(k).push(h);
    }
    return [...per.entries()].map(([header, halaman]) => ({ header, halaman }));
});

const kelompokTersaring = computed(() => {
    const q = kataKunci.value.trim().toLowerCase();
    if (!q) return kelompok.value;

    const hasil = [];
    for (const g of kelompok.value) {
        const headerCocok = g.header.toLowerCase().includes(q);
        const halamanCocok = g.halaman.filter(
            (h) =>
                h.nama_menu?.toLowerCase().includes(q) ||
                h.jenis_page?.toLowerCase().includes(q) ||
                (h.aksi || []).some((a) => a.toLowerCase().includes(q)),
        );

        if (headerCocok) {
            hasil.push(g);
        } else if (halamanCocok.length) {
            hasil.push({ header: g.header, halaman: halamanCocok });
        }
    }
    return hasil;
});

// Default: buka grup pertama atau buka semua bila item sedikit
watch(
    () => props.halaman,
    (daftar) => {
        if (!daftar || !daftar.length) {
            activeSections.value = [];
            return;
        }
        const grup = kelompok.value;
        // Buka grup pertama sebagai pratinjau awal agar rapi
        if (grup.length > 0) {
            activeSections.value = [grup[0].header];
        }
    },
    { immediate: true },
);

// Jika pengguna mengetik pencarian, otomatis buka semua section yang cocok
watch(kataKunci, (q) => {
    if (q.trim()) {
        activeSections.value = kelompokTersaring.value.map((g) => g.header);
    }
});

const semuaTerbuka = computed(() => {
    if (!kelompokTersaring.value.length) return false;
    return kelompokTersaring.value.every((g) => activeSections.value.includes(g.header));
});

function toggleSemua() {
    if (semuaTerbuka.value) {
        activeSections.value = [];
    } else {
        activeSections.value = kelompokTersaring.value.map((g) => g.header);
    }
}

function aksiClass(a) {
    const up = String(a).toUpperCase();
    if (up === 'SEMUA AKSI' || up === 'ALL') return 'dip-action-pill--all';
    if (up === 'DELETE') return 'dip-action-pill--delete';
    if (up === 'CREATE') return 'dip-action-pill--create';
    if (up === 'EDIT') return 'dip-action-pill--edit';
    return 'dip-action-pill--view';
}
</script>

<style scoped>
.dip {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    min-height: 0;
}

/* ══ TOOLBAR & SEARCH ══ */
.dip-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-bottom: 0.25rem;
}

.dip-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 11rem;
}

.dip-search-icon {
    position: absolute;
    left: 0.65rem;
    color: var(--hcis-text-muted, #94a3b8);
    pointer-events: none;
}

.dip-search-input {
    width: 100%;
    padding: 0.35rem 1.75rem 0.35rem 1.85rem;
    font-size: 0.775rem;
    border-radius: var(--hcis-radius-md, 6px);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.9);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    outline: none;
    transition: all 0.15s ease;
}

.dip-search-input:focus {
    border-color: var(--hcis-brand-500, #0284c7);
    box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
}

.dip-search-clear {
    position: absolute;
    right: 0.4rem;
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, #94a3b8);
    cursor: pointer;
    padding: 0.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dip-search-clear:hover {
    color: var(--hcis-text-strong, #0f172a);
}

/* ══ COLLAPSE LIST ══ */
.dip-collapse {
    gap: 0.45rem !important;
}

.dip-collapse-item :deep(.hcis-collapse-item__header) {
    padding: 0.65rem 0.85rem !important;
    background: #f8fafc;
    border-radius: var(--hcis-radius-lg, 10px);
    transition: background-color 0.15s ease;
}

.dip-collapse-item :deep(.hcis-collapse-item__header:hover) {
    background: #f1f5f9;
}

.dip-collapse-item.hcis-collapse-item--active :deep(.hcis-collapse-item__header) {
    background: #f1f5f9;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}

.dip-collapse-item :deep(.hcis-collapse-item__content) {
    padding: 0.35rem 0.85rem 0.75rem 0.85rem !important;
    background: #ffffff;
}

.dip-group-icon {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid rgba(203, 213, 225, 0.7);
    color: var(--hcis-brand-600, #0284c7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dip-group-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.01em;
}

.dip-group-count {
    font-size: 0.6875rem !important;
    font-weight: 650 !important;
    background: #e2e8f0 !important;
    color: #475569 !important;
}

/* ══ ITEMS LIST INSIDE ACCORDION ══ */
.dip-halaman-list {
    display: flex;
    flex-direction: column;
}

.dip-item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
}

.dip-item-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.dip-item-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.dip-nama {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #1e293b);
    line-height: 1.35;
}

.dip-sub-code {
    font-size: 0.6875rem;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-muted, #94a3b8);
}

/* ══ ACTION PILLS ══ */
.dip-actions-wrap {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    flex-shrink: 0;
}

.dip-action-pill {
    display: inline-block;
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    line-height: 1.25;
}

.dip-action-pill--view {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.dip-action-pill--create {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.dip-action-pill--edit {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.dip-action-pill--delete {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.dip-action-pill--all {
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #d8b4fe;
}

.dip-kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8125rem;
    color: var(--hcis-text-muted, #94a3b8);
    padding: 1.5rem 0.5rem;
    text-align: center;
}

.min-w-0 {
    min-width: 0;
}
</style>
