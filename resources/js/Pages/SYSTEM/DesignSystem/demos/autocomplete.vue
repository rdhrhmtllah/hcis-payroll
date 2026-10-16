<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisAutocomplete Configurator</h3>
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
                <div style="width: 100%; max-width: 380px;">
                    <HcisField
                        id="studio-autocomplete-field"
                        :label="studioLabel"
                        :hint="studioHint"
                        :required="studioRequired"
                    >
                        <HcisAutocomplete
                            v-model="studioValue"
                            :fetch-suggestions="cariPosisiStudio"
                            :placeholder="studioPlaceholder"
                            :size="studioSize"
                            :prefix-icon="studioPrefixIcon ? 'search' : ''"
                            :trigger-on-focus="studioTriggerOnFocus"
                            :clearable="studioClearable"
                            :disabled="studioDisabled"
                            :readonly="studioReadonly"
                        />
                    </HcisField>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Field Pembungkus</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Placeholder</label>
                    <input v-model="studioPlaceholder" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 32px)</option>
                        <option value="md">Sedang (md - 38px)</option>
                        <option value="lg">Besar (lg - 44px)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur & Mode Interaktif</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="ac-pre" v-model="studioPrefixIcon" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ac-pre">Ikon Pencarian (Prefix Icon)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="ac-tof" v-model="studioTriggerOnFocus" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ac-tof">Muncul Otomatis saat Fokus</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="ac-clr" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ac-clr">Tombol Hapus (Clearable)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="ac-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ac-dis">Nonaktif (Disabled)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="ac-ro" v-model="studioReadonly" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ac-ro">Hanya Baca (Readonly)</label>
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
        <!-- Group 1: Scoped Slot Employee Selection -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Pencarian Cepat Data Karyawan & Jabatan Organisasi:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisField label="Pencarian Karyawan (Scoped Slot)" hint="Ketik nama, NIK, atau divisi" required>
                    <HcisAutocomplete
                        v-model="demoKaryawan"
                        :fetch-suggestions="cariKaryawan"
                        placeholder="Ketik nama karyawan..."
                        prefix-icon="search"
                        clearable
                    >
                        <template #default="{ item }">
                            <div class="d-flex align-items-center gap-2 py-1">
                                <span class="badge bg-indigo-100 text-indigo-700 font-mono">{{ item.nik }}</span>
                                <div>
                                    <div class="fw-bold text-slate-800 text-xs">{{ item.name }}</div>
                                    <div class="text-slate-500 text-2xs">{{ item.dept }}</div>
                                </div>
                            </div>
                        </template>
                    </HcisAutocomplete>
                </HcisField>

                <HcisField label="Pencarian Kode Unit Kerja / Cost Center" hint="Pencarian asynchronous dinamis" required>
                    <HcisAutocomplete
                        v-model="demoUnit"
                        :fetch-suggestions="cariUnit"
                        placeholder="Ketik kode cost center..."
                        prefix-icon="building"
                        clearable
                    />
                </HcisField>
            </div>
        </div>

        <!-- Group 2: Sizing Comparison -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Perbandingan Hierarki Ukuran Autocomplete (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Kecil (sm - 32px)">
                    <HcisAutocomplete v-model="demoSizeSm" :suggestions-list="posisiList" size="sm" />
                </HcisField>
                <HcisField label="Standar (md - 38px)">
                    <HcisAutocomplete v-model="demoSizeMd" :suggestions-list="posisiList" size="md" />
                </HcisField>
                <HcisField label="Besar (lg - 44px)">
                    <HcisAutocomplete v-model="demoSizeLg" :suggestions-list="posisiList" size="lg" />
                </HcisField>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Siklus Hidup Autocomplete:</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Status Normal Terisi">
                    <HcisAutocomplete :model-value="'Senior Backend Engineer'" :suggestions-list="posisiList" />
                </HcisField>
                <HcisField label="Status Nonaktif (Disabled)">
                    <HcisAutocomplete :model-value="'Staff HRD & GA'" :suggestions-list="posisiList" disabled />
                </HcisField>
                <HcisField label="Status Hanya Baca (Readonly)">
                    <HcisAutocomplete :model-value="'Plant Production Manager'" :suggestions-list="posisiList" readonly />
                </HcisField>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisField, HcisAutocomplete, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Data Masters
const posisiList = [
    'Software Engineer', 'Senior Frontend Developer', 'HR Business Partner',
    'Talent Acquisition Specialist', 'Finance Controller', 'Plant Manager',
    'Production Operator', 'QA Inspector'
];

const karyawanList = [
    { nik: 'EMP-001', name: 'Andi Pratama', dept: 'Human Capital' },
    { nik: 'EMP-002', name: 'Budi Santoso', dept: 'Information Technology' },
    { nik: 'EMP-003', name: 'Citra Lestari', dept: 'Finance & Accounting' },
    { nik: 'EMP-004', name: 'Dedi Kurniawan', dept: 'Manufacturing Plant' }
];

const unitList = [
    'CC-101 (Kantor Pusat)', 'CC-201 (Pabrik Cikarang)', 'CC-202 (Pabrik Karawang)',
    'CC-301 (Gudang Surabaya)', 'CC-401 (Kantor Cabang Medan)'
];

// Studio State
const studioStageBg = ref('light');
const studioValue = ref('Senior Frontend Developer');
const studioLabel = ref('Jabatan Organisasi');
const studioHint = ref('Ketik posisi jabatan untuk menampilkan saran otomatis');
const studioPlaceholder = ref('Ketik nama jabatan...');
const studioSize = ref('md');
const studioPrefixIcon = ref(true);
const studioTriggerOnFocus = ref(true);
const studioClearable = ref(true);
const studioRequired = ref(true);
const studioDisabled = ref(false);
const studioReadonly = ref(false);
const copied = ref(false);

// Scenarios State
const demoKaryawan = ref('');
const demoUnit = ref('CC-101 (Kantor Pusat)');
const demoSizeSm = ref('Software Engineer');
const demoSizeMd = ref('Software Engineer');
const demoSizeLg = ref('Software Engineer');

// Autocomplete suggestion fetchers
function cariPosisiStudio(query, cb) {
    const q = (query || '').toLowerCase();
    const results = posisiList.filter(item => item.toLowerCase().includes(q));
    setTimeout(() => cb(results), 100);
}

function cariKaryawan(query, cb) {
    const q = (query || '').toLowerCase();
    const results = karyawanList.filter(k =>
        k.name.toLowerCase().includes(q) || k.nik.toLowerCase().includes(q) || k.dept.toLowerCase().includes(q)
    );
    setTimeout(() => cb(results), 100);
}

function cariUnit(query, cb) {
    const q = (query || '').toLowerCase();
    const results = unitList.filter(u => u.toLowerCase().includes(q));
    setTimeout(() => cb(results), 100);
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="posisiTerpilih"`);
    props.push(`:fetch-suggestions="fetchSuggestions"`);
    if (studioPlaceholder.value) props.push(`placeholder="${studioPlaceholder.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioPrefixIcon.value) props.push(`prefix-icon="search"`);
    if (!studioTriggerOnFocus.value) props.push(`:trigger-on-focus="false"`);
    if (!studioClearable.value) props.push(`:clearable="false"`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioReadonly.value) props.push(`readonly`);

    return `<HcisField label="${studioLabel.value}" hint="${studioHint.value}">\n    <HcisAutocomplete\n        ${props.join('\n        ')}\n    />\n</HcisField>`;
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
