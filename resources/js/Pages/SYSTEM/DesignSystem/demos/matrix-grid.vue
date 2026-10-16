<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisMatrixGrid Permission Studio</h3>
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
                    { 'ds-stage-dark-mode': studioStageBg === 'dark' }
                ]"
            >
                <div class="w-100 p-3 bg-white rounded-xl shadow-sm border border-slate-200">
                    <HcisMatrixGrid
                        :title="studioTitle"
                        :subtitle="studioSubtitle"
                        icon="grid-3x3-gap-fill"
                        :variant="studioVariant"
                        :readonly="studioReadonly"
                        :searchable="studioSearchable"
                        group-by="group"
                        :columns="demoColumns"
                        :rows="demoRows"
                        @cell-change="handleCellChange"
                        @row-select-all="handleRowSelectAll"
                        @col-select-all="handleColSelectAll"
                        @global-select-all="handleGlobalSelectAll"
                    />

                    <div v-if="lastEventLog" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Event:</strong> {{ lastEventLog }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Matriks</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Visual</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Standar Kartu Elegan)</option>
                        <option value="glass">Glass (Kaca Glassmorphic)</option>
                        <option value="flat">Flat (Minimal Datar)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Opsi Mode</label>
                    <div class="d-flex align-items-center gap-3 pt-2">
                        <div class="form-check form-switch m-0">
                            <input id="mg-ro" v-model="studioReadonly" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="mg-ro">Readonly (Terkunci)</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="mg-sr" v-model="studioSearchable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="mg-sr">Quick Search</label>
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
            <span class="ds-group-label">1. Skenario Matriks Glassmorphic:</span>
            <div class="p-3 bg-slate-900 rounded-xl border border-slate-700 shadow-sm">
                <HcisMatrixGrid
                    variant="glass"
                    title="Matriks Otorisasi Persetujuan Tier Eksekutif"
                    subtitle="Daftar hak otorisasi mutasi, promosi, dan terminasi."
                    icon="shield-lock-fill"
                    :columns="demoColumns"
                    :rows="demoRows.slice(0, 2)"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Mode Hanya Baca (Readonly Mode):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200">
                <HcisMatrixGrid
                    variant="flat"
                    readonly
                    title="Pratinjau Hak Akses Role Staf HR (Terkunci)"
                    :columns="demoColumns"
                    :rows="demoRows.slice(0, 2)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisMatrixGrid, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Konfigurasi Hak Akses Role Administrator');
const studioSubtitle = ref('Matriks otorisasi perizinan modul (Lihat, Tambah, Ubah, Hapus).');
const studioVariant = ref('card');
const studioReadonly = ref(false);
const studioSearchable = ref(true);
const lastEventLog = ref('');
const copied = ref(false);

const demoColumns = ref([
    { key: 'view', label: 'Lihat Data' },
    { key: 'create', label: 'Tambah Data' },
    { key: 'edit', label: 'Ubah Data' },
    { key: 'delete', label: 'Hapus Data' },
    { key: 'export', label: 'Cetak / Export' }
]);

const demoRows = ref([
    {
        id: 1,
        title: 'Master Informasi Karyawan',
        code: 'MOD-HR-001',
        group: 'Modul HR & Kepegawaian',
        permissions: { view: true, create: true, edit: true, delete: false, export: true }
    },
    {
        id: 2,
        title: 'Pengajuan Cuti & Izin Kerja',
        code: 'MOD-HR-002',
        group: 'Modul HR & Kepegawaian',
        permissions: { view: true, create: true, edit: true, delete: true, export: false }
    },
    {
        id: 3,
        title: 'Otorisasi Matriks Approval',
        code: 'MOD-ADM-001',
        group: 'Modul Administrator',
        permissions: { view: true, create: true, edit: true, delete: false, export: true }
    },
    {
        id: 4,
        title: 'Manajemen Role & User Access',
        code: 'MOD-ADM-002',
        group: 'Modul Administrator',
        permissions: { view: true, create: false, edit: false, delete: false, export: false }
    }
]);

function handleCellChange({ row, colKey, value }) {
    lastEventLog.value = `Hak Akses "${colKey.toUpperCase()}" pada "${row.title}" diubah: ${value ? 'DIBERI (GRANTED)' : 'DICABUT (DENIED)'}`;
}

function handleRowSelectAll({ row, value }) {
    lastEventLog.value = `Seluruh Hak Akses baris "${row.title}" diubah: ${value ? 'SEMUA AKTIF' : 'SEMUA NONAKTIF'}`;
}

function handleColSelectAll({ colKey, value }) {
    lastEventLog.value = `Seluruh Hak Akses kolom "${colKey.toUpperCase()}" diubah: ${value ? 'SEMUA AKTIF' : 'SEMUA NONAKTIF'}`;
}

function handleGlobalSelectAll(value) {
    lastEventLog.value = `Seluruh Matriks Otorisasi diubah: ${value ? 'SEMUA DIBERI' : 'SEMUA DICABUT'}`;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioTitle.value) props.push(`title="${studioTitle.value}"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioReadonly.value) props.push(`readonly`);
    if (studioSearchable.value) props.push(`searchable`);
    props.push(`group-by="group"`);
    props.push(`:columns="columns"`);
    props.push(`:rows="rows"`);

    return `<HcisMatrixGrid\n    ${props.join('\n    ')}\n    @cell-change="onCellChange"\n/>`;
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
