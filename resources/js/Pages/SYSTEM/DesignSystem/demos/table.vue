<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisTable Crystal Grid Configurator</h3>
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
                <div style="width: 100%; max-width: 900px; min-width: 0;">
                    <HcisTable
                        v-model:selected-keys="selectedKeys"
                        :columns="columns"
                        :rows="studioLoading ? [] : rows"
                        :loading="studioLoading"
                        :variant="studioVariant"
                        :sentiment="studioSentiment"
                        :size="studioSize"
                        :responsive="studioResponsive"
                        :sticky-first-col="studioStickyFirstCol"
                        :glow="studioGlow"
                        :header-pattern="studioHeaderPattern === 'none' ? false : studioHeaderPattern"
                        :header-gradient="studioHeaderGradient"
                        :selectable="studioSelectable"
                        :striped="studioStriped"
                        :hoverable="studioHoverable"
                        :bordered="studioBordered"
                        :sticky-header="studioStickyHeader"
                        :interactive="studioInteractive"
                        sort-key="nama"
                    >
                        <template #bulk-actions="{ selectedKeys: keys, clear }">
                            <div class="d-flex align-items-center gap-2">
                                <HcisButton
                                    size="xs"
                                    variant="primary"
                                    leading-icon="check-circle-fill"
                                    label="Otorisasi Terpilih"
                                    @click="handleBulkAction('Otorisasi', keys)"
                                />
                                <HcisButton
                                    size="xs"
                                    variant="secondary"
                                    leading-icon="download"
                                    label="Ekspor"
                                    @click="handleBulkAction('Ekspor', keys)"
                                />
                                <button type="button" class="hcis-table__selection-clear-btn" @click="clear">
                                    <HcisIcon name="x-circle" size="xs" />
                                    <span>Batal</span>
                                </button>
                            </div>
                        </template>

                        <template #cell-status="{ value }">
                            <HcisBadge :label="value" :variant="statusVariant[value] || 'neutral'" size="sm" dot />
                        </template>

                        <template #cell-aksi>
                            <div class="d-flex gap-1">
                                <HcisButton icon-only aria-label="Detail" variant="ghost" size="sm">
                                    <template #leading><HcisIcon name="eye" size="xs" /></template>
                                </HcisButton>
                                <HcisButton icon-only aria-label="Edit" variant="ghost" size="sm">
                                    <template #leading><HcisIcon name="pencil" size="xs" /></template>
                                </HcisButton>
                            </div>
                        </template>

                        <template #footer>
                            <div class="d-flex justify-content-between align-items-center text-xs text-slate-600">
                                <span>Terpilih: <strong>{{ selectedKeys.length }}</strong> dari {{ rows.length }} baris</span>
                                <span>Total Data: {{ rows.length }} Record Kepegawaian</span>
                            </div>
                        </template>
                    </HcisTable>

                    <div v-if="lastActionMessage" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Event:</strong> {{ lastActionMessage }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Varian Wadah (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Surface Solid Mewah)</option>
                        <option value="glass">Glass (Kaca Blur Transparan)</option>
                        <option value="flat">Flat (Minimalis Sejajar)</option>
                        <option value="obsidian">Obsidian (Midnight Dark Mode)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Sentimen Aksen (Sentiment)</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="primary">primary (Royal Sapphire)</option>
                        <option value="purple">purple (Amethyst Violet)</option>
                        <option value="teal">teal (Mint Marine)</option>
                        <option value="danger">danger (Coral Crimson)</option>
                        <option value="warning">warning (Amber Gold)</option>
                        <option value="success">success (Zamrud Emerald)</option>
                        <option value="obsidian">obsidian (Cosmic Night)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Mode Responsif (Responsive)</label>
                    <select v-model="studioResponsive" class="form-select form-select-sm">
                        <option value="scroll">scroll (Touch Kinetic Scroll)</option>
                        <option value="cards">cards (Transformasi Kartu Mobile)</option>
                        <option value="auto">auto (Otomatis)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Pattern Header Tabel</label>
                    <select v-model="studioHeaderPattern" class="form-select form-select-sm">
                        <option value="dots">Dot-Matrix (Crystal Stippling)</option>
                        <option value="grid">Micro-Grid (Geometric Grid)</option>
                        <option value="mesh">Diagonal Mesh (Isometric)</option>
                        <option value="none">Polos (Tanpa Pattern)</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Kerapatan Sel (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">sm (Kompak - Dense 36px)</option>
                        <option value="md">md (Standar - Balanced 48px)</option>
                        <option value="lg">lg (Lapang - Executive 56px)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur Interaktivitas & Responsif Grid</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-stkcol" v-model="studioStickyFirstCol" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-stkcol">Sticky Kolom Pertama (Pinned)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-glow" v-model="studioGlow" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-glow">Halo Glow</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-hgrad" v-model="studioHeaderGradient" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-hgrad">Header Gradient</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-sel" v-model="studioSelectable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-sel">Multi-Select & Banner</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-str" v-model="studioStriped" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-str">Striped (Zebra)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-hov" v-model="studioHoverable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-hov">Hover Highlight</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-stk" v-model="studioStickyHeader" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-stk">Sticky Header</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-bor" v-model="studioBordered" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-bor">Bordered Grid</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tbl-load" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tbl-load">Loading Shimmer</label>
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
        <!-- Scenario 1: Responsive Kinetic Touch Scroll with Pinned Column -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Tabel Lebar Multikolom dengan Sticky Kolom Kiri (Mobile & Tablet):</span>
            <HcisTable
                :columns="wideColumns"
                :rows="wideRows"
                sticky-first-col
                variant="card"
                sentiment="primary"
                size="sm"
            >
                <template #cell-status="{ value }">
                    <HcisBadge :label="value" :variant="value === 'Aktif' ? 'success' : 'warning'" size="sm" dot />
                </template>
            </HcisTable>
        </div>

        <!-- Scenario 2: Mobile Stack Card Mode -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Mode Kartu Responsif Mobile (responsive="cards"):</span>
            <HcisTable
                :columns="columns"
                :rows="rows"
                responsive="cards"
                variant="glass"
                sentiment="purple"
                glow
            >
                <template #cell-status="{ value }">
                    <HcisBadge :label="value" :variant="statusVariant[value] || 'neutral'" size="sm" dot />
                </template>
            </HcisTable>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Keadaan Kosong (Empty State):</span>
            <HcisTable
                :columns="columns"
                :rows="[]"
                empty-title="Tidak ada transaksi lembur"
                empty-description="Seluruh permohonan lembur telah selesai diproses oleh manajemen."
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisTable, HcisBadge, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioVariant = ref('card');
const studioSentiment = ref('primary');
const studioSize = ref('md');
const studioResponsive = ref('scroll');
const studioStickyFirstCol = ref(false);
const studioGlow = ref(true);
const studioHeaderGradient = ref(true);
const studioHeaderPattern = ref('dots');
const studioSelectable = ref(true);
const studioStriped = ref(false);
const studioHoverable = ref(true);
const studioBordered = ref(false);
const studioStickyHeader = ref(false);
const studioInteractive = ref(true);
const studioLoading = ref(false);
const selectedKeys = ref([1, 3]);
const lastActionMessage = ref('');
const copied = ref(false);

const columns = [
    { key: 'noTransaksi', label: 'No. Transaksi', sortable: true, width: '140px' },
    { key: 'nama', label: 'Nama Karyawan', sortable: true },
    { key: 'departemen', label: 'Departemen', sortable: true },
    { key: 'status', label: 'Status Otorisasi', sortable: true, width: '130px' },
    { key: 'nominal', label: 'Nominal Lembur', sortable: true, align: 'right' },
    { key: 'aksi', label: 'Aksi', width: '90px', align: 'center' },
];

const rows = [
    { id: 1, noTransaksi: 'TRX-2026-001', nama: 'Ahmad Fauzi', departemen: 'Teknologi Informasi', status: 'Disetujui', nominal: 'Rp 450.000' },
    { id: 2, noTransaksi: 'TRX-2026-002', nama: 'Budi Santoso', departemen: 'Produksi I', status: 'Menunggu', nominal: 'Rp 320.000' },
    { id: 3, noTransaksi: 'TRX-2026-003', nama: 'Citra Dewi', departemen: 'Human Capital', status: 'Disetujui', nominal: 'Rp 580.000' },
    { id: 4, noTransaksi: 'TRX-2026-004', nama: 'Dedi Kurniawan', departemen: 'Logistik', status: 'Ditolak', nominal: 'Rp 150.000' },
];

const wideColumns = [
    { key: 'nik', label: 'NIK', width: '120px' },
    { key: 'nama', label: 'Nama Lengkap', width: '180px' },
    { key: 'jabatan', label: 'Jabatan', width: '160px' },
    { key: 'departemen', label: 'Departemen', width: '160px' },
    { key: 'lokasi', label: 'Lokasi Kerja', width: '140px' },
    { key: 'tglMasuk', label: 'Tanggal Masuk', width: '130px' },
    { key: 'status', label: 'Status', width: '110px' },
];

const wideRows = [
    { id: 101, nik: 'EMP-00912', nama: 'Rendra Pratama', jabatan: 'Lead Engineer', departemen: 'Engineering', lokasi: 'Jakarta HQ', tglMasuk: '12 Jan 2021', status: 'Aktif' },
    { id: 102, nik: 'EMP-00913', nama: 'Siti Nurhaliza', jabatan: 'HR Specialist', departemen: 'Human Capital', lokasi: 'Surabaya Branch', tglMasuk: '05 Mar 2022', status: 'Aktif' },
    { id: 103, nik: 'EMP-00914', nama: 'Hendro Wijaya', jabatan: 'Logistics Supervisor', departemen: 'Supply Chain', lokasi: 'Medan Hub', tglMasuk: '18 Agu 2023', status: 'Cuti' },
];

const statusVariant = {
    'Disetujui': 'success',
    'Menunggu': 'warning',
    'Ditolak': 'danger',
};

function handleBulkAction(action, keys) {
    lastActionMessage.value = `Aksi Massal: "${action}" dieksekusi pada data ID: [${keys.join(', ')}]`;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`:columns="columns"`);
    props.push(`:rows="dataRows"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioSentiment.value !== 'primary') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioResponsive.value !== 'scroll') props.push(`responsive="${studioResponsive.value}"`);
    if (studioStickyFirstCol.value) props.push(`sticky-first-col`);
    if (studioHeaderPattern.value !== 'dots') props.push(`header-pattern="${studioHeaderPattern.value}"`);
    if (!studioHeaderGradient.value) props.push(`:header-gradient="false"`);
    if (studioGlow.value) props.push(`:glow="true"`);
    if (studioSelectable.value) props.push(`v-model:selected-keys="selectedKeys"\n    selectable`);
    if (studioStriped.value) props.push(`striped`);
    if (studioHoverable.value) props.push(`hoverable`);
    if (studioBordered.value) props.push(`bordered`);
    if (studioStickyHeader.value) props.push(`sticky-header`);

    return `<HcisTable\n    ${props.join('\n    ')}\n>\n    <template #cell-status="{ value }">\n        <HcisBadge :label="value" size="sm" dot />\n    </template>\n</HcisTable>`;
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
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%; min-width: 0; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; width: 100%; max-width: 100%; min-width: 0; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }

.ds-studio-panel {
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
}

.ds-preview-stage {
    min-width: 0;
    max-width: 100%;
    width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
    padding: 1.5rem;
}

@media (max-width: 768px) {
    .ds-preview-stage {
        padding: 0.75rem 0.25rem !important;
        min-height: auto;
    }
    .ds-studio-body {
        padding: 0.75rem !important;
    }
    .ds-studio-header {
        padding: 0.75rem 1rem !important;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .ds-stage-switcher {
        width: 100%;
        justify-content: space-between;
    }
    .ds-stage-btn {
        flex: 1;
        text-align: center;
    }
}
</style>
