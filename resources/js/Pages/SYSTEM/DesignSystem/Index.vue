<template>
    <Head title="Katalog Komponen — HCIS Design System" />

    <div class="hcis-page hcis-stack hcis-stack--md">
        <!-- Top Portal Navigation -->
        <DsPortalNav />

        <!-- Hero Header -->
        <HcisHeader
            title="Satu Bahasa Visual untuk Seluruh HCIS"
            subtitle="Katalog komponen native, token desain, aturan pakai resmi, dan pola migrasi enterprise terpadu."
        >
            <template #actions>
                <Link href="/design-system/pola" class="text-decoration-none">
                    <HcisButton label="Eksplorasi Pola Halaman" variant="primary" icon="layout-wtf" />
                </Link>
            </template>
        </HcisHeader>

        <!-- System Context Banner (Corrected HcisContextBanner Props & Scope) -->
        <!-- <HcisContextBanner
            title="HCIS UI Design System v2.0"
            subtitle="Konteks Katalog Komponen Native"
            code="CATALOG_V2"
            icon="collection"
            role-label="System Architect"
            scope-label="Seluruh Modul (42 Komponen Native)"
            scope-variant="success"
            variant="surface"
            :capabilities="[
                { id: 'owned', label: 'Komponen Native', icon: 'box-seam' },
                { id: 'vitest', label: '100% Vitest Tested', icon: 'check-circle' },
            ]"
        /> -->

        <!-- Dynamic Statistics Row (HcisStatGroup & HcisStatCard) -->
        <HcisStatGroup>
            <HcisStatCard label="Milik Kita" :value="ownedCount" caption="markup, perilaku, gaya milik kita" />
            <HcisStatCard label="Pinjaman" :value="borrowedCount" caption="Element Plus, disesuaikan via bridge" />
            <HcisStatCard label="Sudah Disahkan" :value="readyCount" sentiment="good" caption="komponen siap & aman dipakai" />
            <HcisStatCard label="Menunggu Review" :value="draftCount" sentiment="neutral" caption="dalam tahap evaluasi akhir" />
        </HcisStatGroup>

        <!-- Navigation Tabs (HcisTabs) -->
        <HcisTabs v-model="activeTab" :tabs="portalTabs" variant="underline" />

        <!-- TAB 1: KOMPONEN & INVENTARIS — ruang kerja: rel kiri + isi kanan -->
        <section v-if="activeTab === 'inventory'" class="ds-workspace">
            <!--
                Rel rumpun. Memakai HcisTabs vertikal, bukan deretan tombol
                rakitan: ia sudah membawa peran ARIA, penelusuran panah
                atas-bawah, penanda aktif yang MELUNCUR, dan mode terlipat.
                Rumpun juga bukan "pil" — ia navigasi, bukan saringan cepat.
            -->
            <aside class="ds-rail">
                <HcisTabs
                    v-model="selectedGroup"
                    :tabs="rumpunTabs"
                    orientation="vertical"
                    variant="pills"
                    collapsible
                    aria-label="Rumpun komponen"
                />
            </aside>

            <div class="ds-workspace__main hcis-stack hcis-stack--md">
                <!--
                    Seluruh isinya bawaan HcisFilterBar: pencarian, saringan,
                    pil status, dan pengalih tampilan.

                    Sebelumnya keempatnya dirakit ulang di halaman ini lalu
                    dibungkus HcisContainer — dua lapis yang tidak perlu, dan
                    komponen yang sudah punya semuanya justru tidak dipakai.
                    `variant="glass"` sudah menjadi wadahnya sendiri.
                -->
                <HcisFilterBar
                    v-model:search="searchQuery"
                    v-model:view-mode="viewMode"
                    variant="glass"
                    :filters="definisiSaringan"
                    :filter-values="nilaiSaringan"
                    :quick-chips="pilStatus"
                    :active-chip-value="selectedStatus"
                    show-view-toggle
                    search-placeholder="Cari komponen, slug, atau rumpun… (mis. button, badge, date)"
                    @filter-change="onSaringanBerubah"
                    @chip-click="onPilStatus"
                    @reset="resetFilters"
                />

                <!-- EMPTY FILTER STATE -->
                <HcisEmptyState
                    v-if="filteredRegistry.length === 0"
                    icon="search"
                    title="Tidak ada komponen yang cocok"
                    description="Coba ubah kata kunci pencarian atau reset filter untuk melihat komponen lain."
                >
                    <template #actions>
                        <HcisButton label="Reset Filter" variant="secondary" @click="resetFilters" />
                    </template>
                </HcisEmptyState>

                <!-- GRID CARDS VIEW (Refactored using native HcisCard component with interactive mode) -->
                <div v-else-if="viewMode === 'grid'" class="ds-cards-grid">
                    <Link
                        v-for="row in halamanIni"
                        :key="row.slug"
                        :href="`/design-system/${row.slug}`"
                        class="text-decoration-none ds-card-link"
                    >
                        <HcisCard
                            :title="row.name"
                            :description="row.summary"
                            :interactive="true"
                            class="h-100 ds-interactive-card"
                        >
                            <template #actions>
                                <HcisBadge
                                    :label="statusLabel[row.status]"
                                    :variant="statusVariant[row.status]"
                                    size="sm"
                                    dot
                                />
                            </template>

                            <template #footer>
                                <div class="ds-card-footer-content">
                                    <div class="ds-row-tags">
                                        <HcisBadge
                                            :label="row.kind === 'owned' ? 'milik kita' : 'pinjaman'"
                                            :variant="row.kind === 'owned' ? 'primary' : 'neutral'"
                                            size="sm"
                                        />
                                        <HcisBadge
                                            v-if="row.group"
                                            :label="row.group"
                                            variant="neutral"
                                            size="sm"
                                        />
                                    </div>
                                    <span class="ds-card-arrow">
                                        Buka <HcisIcon name="arrow-right" size="xs" />
                                    </span>
                                </div>
                            </template>
                        </HcisCard>
                    </Link>
                </div>

                <!-- TABLE VIEW (Refactored using HcisCard & HcisTable) -->
                <HcisCard v-else title="Inventaris Komponen" description="Status dan klasifikasi lengkap setiap komponen.">
                    <HcisTable :columns="columns" :rows="halamanIni" row-key="slug">
                        <template #cell-name="{ row }">
                            <Link :href="`/design-system/${row.slug}`" class="ds-link">{{ row.name }}</Link>
                        </template>
                        <template #cell-kind="{ value }">
                            <HcisBadge
                                :label="value === 'owned' ? 'milik kita' : 'pinjaman'"
                                :variant="value === 'owned' ? 'primary' : 'neutral'"
                                size="sm"
                            />
                        </template>
                        <template #cell-status="{ value }">
                            <HcisBadge :label="statusLabel[value]" :variant="statusVariant[value]" size="sm" dot />
                        </template>
                        <template #cell-usedIn="{ value }">
                            <span class="ds-muted">{{ value ? `${value} berkas` : '—' }}</span>
                        </template>
                    </HcisTable>
                </HcisCard>

                <!--
                    Ambang wajib: kartu 12, tabel 24 — dan pengguna boleh
                    mengubahnya sendiri. Sebelumnya 67 kartu ditumpahkan
                    sekaligus tanpa satu pun jalan mempersempitnya.
                -->
                <HcisPagination
                    v-if="filteredRegistry.length > perHalaman"
                    v-model="halaman"
                    :page-size="perHalaman"
                    :total="filteredRegistry.length"
                    :page-sizes="pilihanPerHalaman"
                    @update:page-size="gantiPerHalaman"
                />
            </div>
        </section>

        <!-- TAB 2: TOKEN DESAIN & WARNA -->
        <section v-else-if="activeTab === 'tokens'" class="hcis-stack hcis-stack--lg">
            <HcisCard title="Palet Warna Utama" description="Klik pada warna untuk menyalin nama variabel CSS ke clipboard.">
                <div class="hcis-stack hcis-stack--md">
                    <div>
                        <h4 class="ds-subhead">Warna Brand (Indigo)</h4>
                        <div class="hcis-swatch-grid">
                            <div v-for="c in brandColors" :key="c.var" class="hcis-swatch" @click="copyToken(c.var)">
                                <div class="hcis-swatch__sample" :style="{ background: `var(${c.var})` }"></div>
                                <div class="hcis-swatch__meta">
                                    <span class="hcis-swatch__name">{{ c.name }}</span>
                                    <span class="hcis-swatch__value">{{ c.hex }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="ds-subhead">Warna Slate (Netral & Teks)</h4>
                        <div class="hcis-swatch-grid">
                            <div v-for="c in slateColors" :key="c.var" class="hcis-swatch" @click="copyToken(c.var)">
                                <div class="hcis-swatch__sample" :style="{ background: `var(${c.var})` }"></div>
                                <div class="hcis-swatch__meta">
                                    <span class="hcis-swatch__name">{{ c.name }}</span>
                                    <span class="hcis-swatch__value">{{ c.hex }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="ds-subhead">Warna Status & Informasi</h4>
                        <div class="hcis-swatch-grid">
                            <div v-for="c in statusColors" :key="c.var" class="hcis-swatch" @click="copyToken(c.var)">
                                <div class="hcis-swatch__sample" :style="{ background: `var(${c.var})` }"></div>
                                <div class="hcis-swatch__meta">
                                    <span class="hcis-swatch__name">{{ c.name }}</span>
                                    <span class="hcis-swatch__value">{{ c.hex }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </HcisCard>

            <HcisCard title="Skala Tipografi & Bobot Teks" description="Standar huruf dan hierarki visual yang konsisten.">
                <div class="ds-typography-demo">
                    <div class="ds-typo-item">
                        <span class="ds-typo-label">Judul Halaman (700 Black)</span>
                        <div class="hcis-title">Satu Bahasa Visual HCIS</div>
                    </div>
                    <div class="ds-typo-item">
                        <span class="ds-typo-label">Judul Seksi / Kartu (600 Bold)</span>
                        <div class="hcis-card__title">Inventaris Komponen & Status</div>
                    </div>
                    <div class="ds-typo-item">
                        <span class="ds-typo-label">Tombol & Label (500 Medium)</span>
                        <div class="ds-demo-text--medium">Tombol Aksi, Input Field, Navigasi Tab</div>
                    </div>
                    <div class="ds-typo-item">
                        <span class="ds-typo-label">Teks Isi (400 Regular)</span>
                        <div class="hcis-subtitle">
                            Setiap komponen dideklarasikan di satu tempat: aturan pakai, larangan, dan pola migrasi.
                        </div>
                    </div>
                </div>
            </HcisCard>
        </section>

        <!-- TAB 3: PANDUAN & ARSITEKTUR -->
        <section v-else-if="activeTab === 'architecture'" class="hcis-stack hcis-stack--lg">
            <HcisCard title="Alur Keputusan Visual" description="Dari token sampai halaman. Setiap lapis hanya boleh memakai lapis di kirinya.">
                <div class="ds-flow-architecture">
                    <div class="ds-arch-step">
                        <span class="ds-arch-step__num">1</span>
                        <h4 class="ds-arch-step__title">Token Desain</h4>
                        <p class="ds-arch-step__desc">Definisi warna, jarak, sudut (radius), dan bayangan di <code>design-system.css</code>.</p>
                    </div>
                    <div class="ds-arch-arrow"><HcisIcon name="arrow-right" /></div>
                    <div class="ds-arch-step">
                        <span class="ds-arch-step__num">2</span>
                        <h4 class="ds-arch-step__title">Komponen Primitif</h4>
                        <p class="ds-arch-step__desc">Komponen Vue di <code>resources/js/components/ui/</code> yang terdaftar di <code>registry.js</code>.</p>
                    </div>
                    <div class="ds-arch-arrow"><HcisIcon name="arrow-right" /></div>
                    <div class="ds-arch-step">
                        <span class="ds-arch-step__num">3</span>
                        <h4 class="ds-arch-step__title">Layout Shell</h4>
                        <p class="ds-arch-step__desc">Kerangka halaman (AppShell, AppSidebar, AppTopbar) yang mengatur struktur layar.</p>
                    </div>
                    <div class="ds-arch-arrow"><HcisIcon name="arrow-right" /></div>
                    <div class="ds-arch-step">
                        <span class="ds-arch-step__num">4</span>
                        <h4 class="ds-arch-step__title">Halaman Fitur</h4>
                        <p class="ds-arch-step__desc">Halaman Inertia.vue tempat bisnis logika dan antarmuka akhir disajikan.</p>
                    </div>
                </div>
            </HcisCard>
        </section>

        <!-- Motion Settings Banner -->
        <DsMotionBanner />
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    HcisHeader,
    HcisContextBanner,
    HcisBadge,
    HcisCard,
    HcisStatGroup,
    HcisStatCard,
    HcisTable,
    HcisEmptyState,
    HcisButton,
    HcisIcon,
    HcisTabs,
    HcisFilterBar,
    HcisPagination,
    hcisMessage,
} from '@/components/ui';
import { registry } from '@/components/ui/registry';
import DsPortalNav from './partials/DsPortalNav.vue';
import DsMotionBanner from './partials/DsMotionBanner.vue';

const activeTab = ref('inventory');

const portalTabs = computed(() => [
    {
        key: 'inventory',
        label: 'Komponen & Inventaris',
        icon: 'puzzle',
        badge: filteredRegistry.value.length,
    },
    { key: 'tokens', label: 'Token Desain & Warna', icon: 'palette' },
    { key: 'architecture', label: 'Panduan & Arsitektur', icon: 'rulers' },
]);
const searchQuery = ref('');
const selectedGroup = ref('all');
const selectedKind = ref('all');
const selectedStatus = ref('all');
const viewMode = ref('grid');

/* ── Rel rumpun ──────────────────────────────────────────────────────── */

/*
 * Ikon per rumpun. WAJIB ada untuk setiap rumpun, bukan hiasan: saat rel
 * dilipat, label disembunyikan dan yang tersisa HANYA ikonnya. Rumpun tanpa
 * ikon berubah jadi tombol kosong yang tidak bisa dikenali sama sekali.
 *
 * Sebelumnya hanya entri "Semua Rumpun" yang punya ikon dan sisanya dibuat
 * dari groupList tanpa properti icon — jadi delapan dari sembilan rel tampil
 * tanpa ikon.
 */
const IKON_RUMPUN = {
    'Aksi & status': 'lightning-charge',
    Navigasi: 'signpost-2',
    'Tata letak': 'layout-three-columns',
    'Umpan balik': 'chat-square-dots',
    Wadah: 'box-seam',
    Formulir: 'ui-checks',
    Data: 'table',
    Visualisasi: 'graph-up',
    Fondasi: 'bricks',
};

const rumpunTabs = computed(() => [
    { key: 'all', label: 'Semua Rumpun', icon: 'grid-3x3-gap', badge: registry.length },
    ...groupList.value.map((g) => ({
        key: g.name,
        label: g.name,
        // Cadangan 'tag' supaya rumpun baru yang belum dipetakan tetap punya
        // ikon — bukan menghilang saat rel dilipat.
        icon: IKON_RUMPUN[g.name] ?? 'tag',
        badge: g.count,
    })),
]);

/* ── Saringan: bentuknya diserahkan ke HcisFilterBar ─────────────────── */
const definisiSaringan = [
    {
        key: 'kind',
        label: 'Jenis',
        type: 'select',
        options: [
            { value: 'all', label: 'Semua Jenis' },
            { value: 'owned', label: 'Milik Kita' },
            { value: 'borrowed', label: 'Pinjaman (Element Plus)' },
        ],
    },
];

const nilaiSaringan = computed(() => ({ kind: selectedKind.value }));

/*
 * Status jadi pil cepat, bukan dropdown kedua: nilainya cuma empat dan paling
 * sering dipakai. Menyembunyikannya di balik dropdown menambah satu klik untuk
 * hal yang paling sering diklik. Yang jumlahnya nol tidak ditampilkan — pil
 * yang selalu memberi hasil kosong hanya menambah kebisingan.
 */
const pilStatus = computed(() => [
    { value: 'all', label: 'Semua Status', badge: registry.length },
    ...['ready', 'draft', 'planned', 'deprecated']
        .map((s) => ({
            value: s,
            label: statusLabel[s],
            badge: registry.filter((e) => e.status === s).length,
        }))
        .filter((p) => p.badge > 0),
]);

function onSaringanBerubah({ key, value }) {
    if (key === 'kind') selectedKind.value = value || 'all';
}

function onPilStatus(chip) {
    selectedStatus.value = chip?.value ?? 'all';
}

/* ── Paginasi ────────────────────────────────────────────────────────── */
const halaman = ref(1);

/*
 * Ambangnya berbeda per bentuk tampilan, dan itu disengaja: satu kartu memakan
 * ruang berkali lipat satu baris tabel, jadi angka yang sama menghasilkan
 * pengalaman yang sama sekali berbeda.
 */
const AMBANG = { grid: 12, table: 24 };
const perHalamanManual = ref(null);
const perHalaman = computed(() => perHalamanManual.value ?? AMBANG[viewMode.value] ?? 12);
const pilihanPerHalaman = [12, 24, 48, 96];

function gantiPerHalaman(n) {
    perHalamanManual.value = n;
    halaman.value = 1;
}

const halamanIni = computed(() => {
    const mulai = (halaman.value - 1) * perHalaman.value;
    return filteredRegistry.value.slice(mulai, mulai + perHalaman.value);
});

/*
 * Kembali ke halaman 1 setiap kali hasil saringan berubah. Tanpa ini, orang
 * yang sedang di halaman 5 lalu mengetik pencarian melihat daftar kosong —
 * bukan karena tidak ada hasilnya, tapi karena halaman 5 sudah tidak ada.
 */
watch([searchQuery, selectedGroup, selectedKind, selectedStatus, viewMode], () => {
    halaman.value = 1;
});

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

const groupList = computed(() => {
    const map = {};
    for (const item of registry) {
        const grp = item.group || 'Lainnya';
        map[grp] = (map[grp] || 0) + 1;
    }
    return Object.keys(map).map((g) => ({ name: g, count: map[g] }));
});

const filteredRegistry = computed(() => {
    return registry.filter((item) => {
        if (selectedGroup.value !== 'all' && (item.group || 'Lainnya') !== selectedGroup.value) {
            return false;
        }
        if (selectedKind.value !== 'all' && item.kind !== selectedKind.value) {
            return false;
        }
        if (selectedStatus.value !== 'all' && item.status !== selectedStatus.value) {
            return false;
        }
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase().trim();
            const matchName = item.name.toLowerCase().includes(q);
            const matchSlug = item.slug.toLowerCase().includes(q);
            const matchSummary = (item.summary || '').toLowerCase().includes(q);
            const matchGroup = (item.group || '').toLowerCase().includes(q);
            return matchName || matchSlug || matchSummary || matchGroup;
        }
        return true;
    });
});

function resetFilters() {
    searchQuery.value = '';
    selectedGroup.value = 'all';
    selectedKind.value = 'all';
    selectedStatus.value = 'all';
}

function copyToken(varName) {
    const textToCopy = `var(${varName})`;
    navigator.clipboard.writeText(textToCopy).then(() => {
        hcisMessage.success(`Token ${textToCopy} berhasil disalin ke clipboard!`);
    });
}

const brandColors = [
    { name: 'Brand 50', var: '--hcis-brand-50', hex: '#eef2ff' },
    { name: 'Brand 100', var: '--hcis-brand-100', hex: '#e0e7ff' },
    { name: 'Brand 200', var: '--hcis-brand-200', hex: '#c7d2fe' },
    { name: 'Brand 300', var: '--hcis-brand-300', hex: '#a5b4fc' },
    { name: 'Brand 400', var: '--hcis-brand-400', hex: '#818cf8' },
    { name: 'Brand 500', var: '--hcis-brand-500', hex: '#6366f1' },
    { name: 'Brand 600', var: '--hcis-brand-600', hex: '#4f46e5' },
    { name: 'Brand 700', var: '--hcis-brand-700', hex: '#4338ca' },
];

const slateColors = [
    { name: 'Slate 50', var: '--hcis-slate-50', hex: '#f8fafc' },
    { name: 'Slate 100', var: '--hcis-slate-100', hex: '#f1f5f9' },
    { name: 'Slate 200', var: '--hcis-slate-200', hex: '#e2e8f0' },
    { name: 'Slate 300', var: '--hcis-slate-300', hex: '#cbd5e1' },
    { name: 'Slate 500', var: '--hcis-slate-500', hex: '#64748b' },
    { name: 'Slate 700', var: '--hcis-slate-700', hex: '#334155' },
    { name: 'Slate 800', var: '--hcis-slate-800', hex: '#1e293b' },
    { name: 'Slate 900', var: '--hcis-slate-900', hex: '#0f172a' },
];

const statusColors = [
    { name: 'Success 500', var: '--hcis-success-500', hex: '#10b981' },
    { name: 'Success 700', var: '--hcis-success-700', hex: '#047857' },
    { name: 'Warning 500', var: '--hcis-warning-500', hex: '#f59e0b' },
    { name: 'Warning 700', var: '--hcis-warning-700', hex: '#b45309' },
    { name: 'Danger 500', var: '--hcis-danger-500', hex: '#ef4444' },
    { name: 'Danger 700', var: '--hcis-danger-700', hex: '#b91c1c' },
    { name: 'Info 500', var: '--hcis-info-500', hex: '#06b6d4' },
    { name: 'Info 700', var: '--hcis-info-700', hex: '#0e7490' },
];
</script>

<style scoped>

/*
 * Ruang kerja: rel tetap di kiri, isi bergulir di kanan.
 *
 * Rel-nya "sticky", bukan "fixed" — supaya ia berhenti di ujung bagian ini dan
 * tidak menimpa isi halaman di bawahnya. "align-self: start" wajib: tanpa itu
 * grid meregangkan <aside> setinggi kolom sebelahnya, dan sticky tidak pernah
 * punya ruang untuk bekerja.
 */
.ds-workspace {
    display: grid;
    /*
     * Kolom kiri `auto`, BUKAN minmax(12rem, 15rem).
     *
     * HcisTabs vertikal sudah memiliki lebarnya sendiri: 15rem, menyusut ke
     * 3.75rem saat dilipat, beranimasi 250ms. Kalau grid ikut memaku lebar
     * kolomnya, grid yang menang — track menyusut di dalam kolom yang tidak
     * ikut menyusut, jadi tersisa pita kosong di kiri dan kolom kanan tidak
     * pernah tahu ada ruang yang bebas.
     *
     * Dengan `auto`, kolomnya mengikuti lebar track apa adanya. Karena track
     * yang beranimasi, kolom kanan ikut melebar mulus di frame yang sama —
     * tanpa satu baris JavaScript pun.
     *
     * Aturan umumnya: kalau sebuah komponen sudah mengurus lebarnya sendiri,
     * halaman tidak boleh ikut menentukannya. Cukup satu pemilik.
     */
    grid-template-columns: auto minmax(0, 1fr);
    gap: var(--hcis-space-5);
    align-items: start;
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.ds-rail {
    position: sticky;
    top: calc(var(--hcis-topbar-height, 4rem) + 1.25rem);
    align-self: start;
    z-index: 10;
    max-width: 100%;
    min-width: 0;
}

/* minmax(0, 1fr) di atas DAN min-width: 0 di sini — dua-duanya perlu. Tanpa
   salah satunya, tabel lebar memaksa kolomnya melebar dan rel terdorong
   keluar layar. */
.ds-workspace__main {
    min-width: 0;
    max-width: 100%;
}

@media (max-width: 991.98px) {
    .ds-workspace {
        grid-template-columns: minmax(0, 1fr);
    }

    /* Di layar sempit rel jadi baris atas biasa — menempelkannya di sana cuma
       memakan tinggi layar yang sudah sedikit. */
    .ds-rail {
        position: static;
    }
}

/*
 * Judul 1 baris, keterangan 2 baris.
 *
 * Bukan soal rapi saja: tinggi kartu yang seragam membuat kisi terbaca sebagai
 * satu kesatuan, dan satu kartu yang lebih tinggi merusak seluruh barisnya.
 * Ringkasan komponen di registry panjangnya sangat berbeda — ada yang satu
 * kalimat, ada yang lima.
 *
 * :deep() diperlukan karena judul dan keterangan dirender DI DALAM HcisCard,
 * jadi gaya berlingkup halaman ini tidak menjangkaunya tanpa itu.
 */
.ds-interactive-card :deep(.hcis-card__title) {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.ds-interactive-card :deep(.hcis-card__description) {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    /* Menahan tinggi dua baris meski keterangannya cuma satu, supaya kaki
       kartu (lencana + panah) sejajar di seluruh baris kisi. */
    min-height: calc(2 * 1.5em);
}











.ds-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--hcis-space-4);
}

.ds-card-link {
    display: block;
}

.ds-interactive-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.ds-card-footer-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.ds-row-tags {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.ds-card-arrow {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-brand-600);

    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.ds-interactive-card:hover .ds-card-arrow {
    transform: translateX(4px);
}

.ds-link {
    color: var(--hcis-brand-700);
    font-weight: 700;
    text-decoration: none;
}

.ds-link:hover {
    text-decoration: underline;
}

.ds-muted {
    color: var(--hcis-text-muted);
}

.ds-subhead {
    margin: 0 0 var(--hcis-space-3);
    color: var(--hcis-text-strong);
    font-size: var(--hcis-font-size-sm);
    font-weight: 800;
}

.hcis-swatch-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.75rem;
}

.hcis-swatch {
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-subtle);
    overflow: hidden;
    cursor: pointer;
    background: var(--hcis-surface);
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-swatch:hover {
    transform: translateY(-2px);
    box-shadow: var(--hcis-shadow-md);
}

.hcis-swatch__sample {
    height: 48px;
    width: 100%;
}

.hcis-swatch__meta {
    padding: 0.4rem 0.5rem;
    display: flex;
    flex-direction: column;
}

.hcis-swatch__name {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
}

.hcis-swatch__value {
    font-size: 0.675rem;
    color: var(--hcis-text-muted);
}

.ds-typography-demo {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-5);
}

.ds-typo-item {
    border-bottom: 1px solid var(--hcis-border-subtle);
    padding-bottom: var(--hcis-space-4);
}

.ds-typo-item:last-child {
    border-bottom: 0;
}

.ds-typo-label {
    display: block;
    margin-bottom: var(--hcis-space-1);
    color: var(--hcis-text-muted);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.ds-demo-text--medium {
    color: var(--hcis-text-strong);
    font-size: var(--hcis-font-size-md);
    font-weight: var(--hcis-weight-medium);
}

.ds-flow-architecture {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: var(--hcis-space-4);
    align-items: center;
    padding: var(--hcis-space-4) 0;
}

.ds-arch-step {
    padding: var(--hcis-space-4);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
    box-shadow: var(--hcis-shadow-xs);
}

.ds-arch-step__num {
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-brand-600);
    color: var(--hcis-white);
    font-size: var(--hcis-font-size-xs);
    font-weight: 800;
    margin-bottom: var(--hcis-space-2);
}

.ds-arch-step__title {
    margin: 0 0 var(--hcis-space-1);
    color: var(--hcis-text-strong);
    font-size: var(--hcis-font-size-sm);
    font-weight: 800;
}

.ds-arch-step__desc {
    margin: 0;
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
    line-height: var(--hcis-line-normal);
}

.ds-arch-arrow {
    text-align: center;
    color: var(--hcis-brand-500);
    font-weight: 800;
    font-size: 1.25rem;
}

code {
    background: var(--hcis-surface-soft);
    border-radius: var(--hcis-space-1);
    padding: 1px 5px;
    font-family: var(--hcis-font-mono);
    font-size: 0.85em;
}
</style>
