<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisFilterBar Cockpit Configurator</h3>
            </div>
            <!-- Stage Background Switcher -->
            <div class="ds-stage-switcher" role="group" aria-label="Latar Panggung">
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'light' }"
                    @click="studioStageBg = 'light'"
                >
                    Light
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'dark' }"
                    @click="studioStageBg = 'dark'"
                >
                    Dark 🌑
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'mesh' }"
                    @click="studioStageBg = 'mesh'"
                >
                    Mesh 🔮
                </button>
            </div>
        </div>

        <div class="ds-studio-body">
            <!-- Preview Stage Canvas -->
            <div
                class="ds-preview-stage"
                :class="[
                    `ds-stage-bg--${studioStageBg}`,
                    { 'ds-stage-dark-mode': studioStageBg === 'dark' || studioVariant === 'obsidian' }
                ]"
            >
                <div style="width: 100%; max-width: 960px;">
                    <HcisFilterBar
                        v-model:search="searchQuery"
                        v-model:view-mode="currentViewMode"
                        :variant="studioVariant"
                        :sentiment="studioSentiment"
                        :size="studioSize"
                        :glow="studioGlow"
                        :search-placeholder="studioPlaceholder"
                        :filters="demoFilters"
                        :filter-values="selectedFilterValues"
                        :quick-chips="studioShowChips ? demoChips : []"
                        :active-chip-value="activeChip"
                        :command-key="studioCommandKey"
                        :allow-export="studioAllowExport"
                        :show-view-toggle="studioShowViewToggle"
                        :results-count="demoResultsCount"
                        @chip-click="handleChipClick"
                        @filter-change="handleFilterChange"
                        @reset="handleReset"
                        @export="handleExport"
                        @command="handleCommand"
                    />

                    <div v-if="lastEvent" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Event:</strong> {{ lastEvent }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Placeholder Pencarian</label>
                    <input v-model="studioPlaceholder" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Varian Wadah Bar (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Surface Solid Mewah)</option>
                        <option value="glass">Glass (Kaca Blur Transparan)</option>
                        <option value="hero">Hero (Luminescent Accent)</option>
                        <option value="flat">Flat (Minimalis Sejajar)</option>
                        <option value="obsidian">Obsidian (Midnight Dark Mode)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Sentimen Aksen (Sentiment)</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="primary">primary / brand (Royal Sapphire)</option>
                        <option value="purple">purple / violet (Amethyst SOP)</option>
                        <option value="teal">teal / cyan (Mint Marine)</option>
                        <option value="danger">danger (Coral Crimson)</option>
                        <option value="warning">warning (Amber Gold)</option>
                        <option value="success">success (Zamrud Emerald)</option>
                        <option value="obsidian">obsidian (Cosmic Night)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Ukuran Padding (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">sm (Kompak - Ramping)</option>
                        <option value="md">md (Standar - Proporsional)</option>
                        <option value="lg">lg (Lapang - Eksekutif)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur Cockpit Filter & Interaktivitas</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="fb-glow" v-model="studioGlow" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="fb-glow">Ambient Halo Glow Aura</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="fb-chips" v-model="studioShowChips" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="fb-chips">Quick Status Chips</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="fb-cmdk" v-model="studioCommandKey" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="fb-cmdk">Badge Ctrl+K Command</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="fb-view" v-model="studioShowViewToggle" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="fb-view">Pengalih Tampilan Table/Grid</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="fb-exp" v-model="studioAllowExport" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="fb-exp">Tombol Ekspor Data</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Generator -->
            <div class="ds-code-generator">
                <div class="ds-code-header">
                    <span class="ds-code-lang">Vue 3 SFC Template</span>
                    <button type="button" class="ds-copy-btn" @click="copySnippet">
                        <HcisIcon :name="copied ? 'check2' : 'clipboard'" size="xs" />
                        <span>{{ copied ? 'Tersalin!' : 'Salin Kode' }}</span>
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ generatedSnippet }}</code></pre>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. ENTERPRISE SCENARIOS & REAL-WORLD WORKFLOWS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Kokpit Filter Modul KPI & Kepegawaian (Glass + Glow):</span>
            <div class="d-flex flex-column gap-3">
                <HcisFilterBar
                    v-model:search="searchQuery"
                    :filters="demoFilters"
                    :quick-chips="demoChips"
                    :active-chip-value="activeChip"
                    variant="glass"
                    sentiment="primary"
                    glow
                    allow-export
                    show-view-toggle
                    command-key
                    :results-count="142"
                    @chip-click="handleChipClick"
                />
            </div>
        </div>

        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Kokpit Midnight Obsidian Dark Mode:</span>
            <div class="p-3 rounded-xl bg-slate-900">
                <HcisFilterBar
                    v-model:search="searchQuery"
                    :filters="demoFilters"
                    :quick-chips="demoChips"
                    :active-chip-value="activeChip"
                    variant="obsidian"
                    sentiment="obsidian"
                    glow
                    allow-export
                    show-view-toggle
                    command-key
                    :results-count="98"
                    @chip-click="handleChipClick"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <HcisAlert
            variant="info"
            title="Integrasi Laci Bawah (Mobile Drawer) Otomatis"
            description="Ketika layar menyempit (mobile < 768px), HcisFilterBar otomatis memindahkan saringan ke tombol apung (FAB) dan HcisDrawer bottom sheet yang ergonomis dengan pengontrolan jempol alami."
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisFilterBar, HcisAlert, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const searchQuery = ref('');
const currentViewMode = ref('table');
const studioVariant = ref('card');
const studioSentiment = ref('primary');
const studioSize = ref('md');
const studioGlow = ref(true);
const studioPlaceholder = ref('Cari nama karyawan, NIK, atau posisi...');
const studioShowChips = ref(true);
const studioCommandKey = ref(true);
const studioShowViewToggle = ref(true);
const studioAllowExport = ref(true);
const activeChip = ref('all');
const lastEvent = ref('');
const copied = ref(false);
const demoResultsCount = ref(142);

const selectedFilterValues = ref({
    status: '',
    department: '',
    dateRange: [],
    minScore: 0,
});

const demoChips = [
    { label: 'Semua Karyawan', value: 'all', badge: 142, icon: 'people-fill' },
    { label: 'Disetujui', value: 'approved', badge: 98, icon: 'check-circle-fill', color: 'success', ping: true },
    { label: 'Pending Review', value: 'pending', badge: 32, icon: 'clock-history', color: 'warning' },
    { label: 'Ditolak', value: 'rejected', badge: 12, icon: 'x-circle-fill', color: 'danger' },
    { label: 'Revisi SOP', value: 'revision', badge: 8, icon: 'file-earmark-diff', color: 'purple' },
];

const demoFilters = [
    {
        key: 'status',
        label: 'Status Otorisasi',
        type: 'select',
        icon: 'patch-check-fill',
        col: 3,
        options: [
            { label: 'Semua Status', value: '' },
            { label: 'Approved (Disetujui)', value: 'APPROVED' },
            { label: 'Pending Manager (Menunggu)', value: 'PENDING_MANAGER' },
            { label: 'Rejected (Ditolak)', value: 'REJECTED' },
        ],
    },
    {
        key: 'department',
        label: 'Departemen / Unit Kerja',
        type: 'select',
        icon: 'building',
        col: 3,
        options: [
            { label: 'Semua Unit', value: '' },
            { label: 'Teknologi Informasi & Digital', value: 'IT' },
            { label: 'Human Capital & Organization', value: 'HC' },
            { label: 'Produksi & Operasional Pabrik', value: 'MFG' },
            { label: 'Keuangan & Akuntansi Korporat', value: 'FIN' },
        ],
    },
    {
        key: 'dateRange',
        label: 'Rentang Periode Penilaian',
        type: 'daterange',
        icon: 'calendar-range',
        col: 4,
    },
    {
        key: 'minScore',
        label: 'Nilai Target Min (Score)',
        type: 'number',
        icon: 'trophy-fill',
        min: 0,
        max: 100,
        step: 5,
        col: 2,
    },
];

function handleChipClick(chip) {
    activeChip.value = chip.value;
    lastEvent.value = `Chip diklik: ${chip.label} (${chip.value})`;
    demoResultsCount.value = chip.value === 'all' ? 142 : chip.badge;
}

function handleFilterChange(val) {
    lastEvent.value = `Filter diubah: ${JSON.stringify(val.values)}`;
}

function handleReset() {
    searchQuery.value = '';
    activeChip.value = 'all';
    selectedFilterValues.value = { status: '', department: '', dateRange: [], minScore: 0 };
    demoResultsCount.value = 142;
    lastEvent.value = 'Filter di-reset ke nilai default';
}

function handleExport(format) {
    lastEvent.value = `Permintaan ekspor data format: ${format.toUpperCase()}`;
}

function handleCommand() {
    lastEvent.value = 'Pintasan Ctrl+K Quick Command ditekan!';
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model:search="searchQuery"`);
    props.push(`v-model:view-mode="viewMode"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioSentiment.value !== 'primary') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioGlow.value) props.push(`:glow="true"`);
    if (studioPlaceholder.value) props.push(`search-placeholder="${studioPlaceholder.value}"`);
    if (studioShowChips.value) props.push(`:quick-chips="quickChips"`);
    if (studioCommandKey.value) props.push(`:command-key="true"`);
    if (studioAllowExport.value) props.push(`:allow-export="true"`);
    if (studioShowViewToggle.value) props.push(`:show-view-toggle="true"`);
    if (demoResultsCount.value !== null) props.push(`:results-count="${demoResultsCount.value}"`);

    return `<HcisFilterBar\n    ${props.join('\n    ')}\n    :filters="filters"\n    :filter-values="filterValues"\n    @filter-change="onFilterChange"\n    @reset="onReset"\n    @export="onExport"\n/>`;
});

function copySnippet() {
    navigator.clipboard.writeText(generatedSnippet.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}
</script>

<style scoped>
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.5rem; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }
</style>
