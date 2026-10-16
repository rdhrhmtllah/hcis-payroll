<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisDataTable Cockpit Configurator</h3>
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
                <div style="width: 100%; max-width: 940px;">
                    <HcisDataTable
                        :title="studioTitle"
                        :subtitle="studioSubtitle"
                        :icon="studioIcon"
                        :badge="studioBadge"
                        :badge-variant="studioBadgeVariant"
                        :variant="studioVariant"
                        :sentiment="studioSentiment"
                        :glow="studioGlow"
                        :loading="studioLoading"
                        :columns="demoColumns"
                        :data="demoData"
                        :total="28"
                        :page="currentPage"
                        :page-size="10"
                        :allow-add="studioAllowAdd"
                        :add-label="studioAddLabel"
                        :allow-export="studioAllowExport"
                        :searchable="studioSearchable"
                        :quick-chips="demoChips"
                        :active-chip-value="activeChip"
                        :selectable="studioSelectable"
                        v-model:selected-keys="selectedKeys"
                        v-model:search="searchQuery"
                        @add="handleAdd"
                        @chip-click="activeChip = $event.value"
                        @page-change="currentPage = $event"
                    >
                        <template #bulk-actions="{ selectedKeys: keys }">
                            <div class="d-flex align-items-center gap-2">
                                <HcisButton size="xs" variant="primary" leading-icon="check2-all" label="Setujui Terpilih" />
                                <HcisButton size="xs" variant="danger" leading-icon="trash" label="Hapus Terpilih" />
                            </div>
                        </template>

                        <template #cell-status="{ row }">
                            <HcisBadge :label="row.status" :variant="row.status === 'APPROVED' ? 'success' : row.status === 'ACTIVE' ? 'primary' : 'warning'" size="sm" dot />
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="d-flex gap-1 justify-content-center">
                                <HcisButton size="xs" variant="ghost" icon-only leading-icon="pencil" title="Ubah" />
                                <HcisButton size="xs" variant="danger-quiet" icon-only leading-icon="trash" title="Hapus" />
                            </div>
                        </template>
                    </HcisDataTable>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Judul Modul (Title)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Varian Kontainer (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Surface Solid)</option>
                        <option value="glass">Glass (Frosted Glass)</option>
                        <option value="hero">Hero (Luminescent Accent)</option>
                        <option value="flat">Flat (Minimalist)</option>
                        <option value="obsidian">Obsidian (Midnight Dark)</option>
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
                    <label class="ds-control-label">Aura Glow</label>
                    <div class="pt-1">
                        <div class="form-check form-switch m-0">
                            <input id="dt-glow" v-model="studioGlow" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-glow">Ambient Halo Glow</label>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur Cockpit Terpadu</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dt-sel" v-model="studioSelectable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-sel">Multi-Select Baris</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dt-add" v-model="studioAllowAdd" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-add">Tombol Tambah Data</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dt-exp" v-model="studioAllowExport" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-exp">Tombol Ekspor Data</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dt-sea" v-model="studioSearchable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-sea">Filter Pencarian</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dt-lod" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-lod">Loading Skeleton</label>
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
            <span class="ds-group-label">1. Skenario Tabel Data Otorisasi Role HCIS (Glass + Glow):</span>
            <HcisDataTable
                variant="glass"
                sentiment="primary"
                glow
                title="Glassmorphism Role Matrix"
                subtitle="Matriks hak akses dan izin menu karyawan"
                :columns="demoColumns"
                :data="demoData.slice(0, 2)"
                :show-pagination="false"
            />
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <HcisAlert
            variant="info"
            title="Komposisi Komponen HcisDataTable Terpadu"
            description="HcisDataTable secara elegan mengikat HcisContainer + HcisFilterBar + HcisTable + HcisPagination ke dalam satu kesatuan kokpit data yang kohesif."
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisDataTable, HcisBadge, HcisButton, HcisAlert, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Kelola Otorisasi Matrix Menu');
const studioSubtitle = ref('Modul terpadu untuk mengelola hak akses dan matriks peran pengguna');
const studioIcon = ref('shield-lock-fill');
const studioBadge = ref('Terstandarisasi');
const studioBadgeVariant = ref('brand');
const studioVariant = ref('card');
const studioSentiment = ref('primary');
const studioGlow = ref(true);
const studioSelectable = ref(true);
const studioAllowAdd = ref(true);
const studioAddLabel = ref('Tambah Otorisasi');
const studioAllowExport = ref(true);
const studioSearchable = ref(true);
const studioLoading = ref(false);

const searchQuery = ref('');
const activeChip = ref('all');
const selectedKeys = ref([1]);
const currentPage = ref(1);
const copied = ref(false);

const demoChips = [
    { label: 'Semua (28)', value: 'all', icon: 'people-fill' },
    { label: 'Disetujui (18)', value: 'approved', color: 'success', icon: 'check-circle-fill', ping: true },
    { label: 'Pending (10)', value: 'pending', color: 'warning', icon: 'clock-history' },
];

const demoColumns = [
    { key: 'role', label: 'Nama Peran / Jabatan', sortable: true },
    { key: 'menu', label: 'Modul Menu', sortable: true },
    { key: 'status', label: 'Status Otorisasi', sortable: true, width: '150px' },
    { key: 'actions', label: 'Aksi', width: '100px', align: 'center' },
];

const demoData = [
    { id: 1, role: 'Manager Human Capital', menu: 'Persetujuan Shift & Cuti', status: 'APPROVED' },
    { id: 2, role: 'Senior Software Engineer', menu: 'KPI Enforcement & SOP', status: 'ACTIVE' },
    { id: 3, role: 'Staff Finance & Payroll', menu: 'Konversi Lembur & Overtime', status: 'PENDING_MANAGER' },
];

function handleAdd() {
    // Demo add trigger
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`title="${studioTitle.value}"`);
    props.push(`subtitle="${studioSubtitle.value}"`);
    if (studioIcon.value) props.push(`icon="${studioIcon.value}"`);
    if (studioBadge.value) props.push(`badge="${studioBadge.value}"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioSentiment.value !== 'primary') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioGlow.value) props.push(`:glow="true"`);
    if (studioSelectable.value) props.push(`:selectable="true"`);
    if (studioAllowAdd.value) props.push(`:allow-add="true"\n    add-label="${studioAddLabel.value}"`);
    if (studioAllowExport.value) props.push(`:allow-export="true"`);
    props.push(`:columns="columns"`);
    props.push(`:data="tableData"`);

    return `<HcisDataTable\n    ${props.join('\n    ')}\n    @add="onAdd"\n    @page-change="onPageChange"\n>\n    <template #cell-status="{ row }">\n        <HcisBadge :label="row.status" size="sm" />\n    </template>\n</HcisDataTable>`;
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
